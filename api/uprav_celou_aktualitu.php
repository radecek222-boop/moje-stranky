<?php
/**
 * API endpoint pro úpravu celého obsahu aktuality
 * Umožňuje adminovi upravit celý markdown obsah článku
 * Automaticky překládá do EN a IT pomocí Google Translate s cache
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/translator.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola admin oprávnění
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen - pouze pro administrátory', 403);
}

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

try {
    $pdo = getDbConnection();

    // Rate limiting
    require_once __DIR__ . '/../includes/rate_limiter.php';
    $rateLimiter = new RateLimiter($pdo);
    if (!$rateLimiter->checkLimit('edit_aktualita', $_SERVER['REMOTE_ADDR'], 30, 3600)) {
        sendJsonError('Příliš mnoho požadavků na úpravu', 429);
    }

    // Validace vstupních dat
    $aktualitaId = filter_var($_POST['aktualita_id'] ?? '', FILTER_VALIDATE_INT);
    $jazyk = $_POST['jazyk'] ?? 'cz';  // Vždy CZ
    $index = (int)($_POST['index'] ?? 0);
    $novyObsah = $_POST['novy_obsah'] ?? '';
    $jeNovyClanek = ($index === -1);  // Index -1 znamená přidat nový článek

    if (!$aktualitaId) {
        sendJsonError('Chybí ID aktuality');
    }

    if (empty(trim($novyObsah))) {
        sendJsonError('Obsah nesmí být prázdný');
    }

    // Získat aktuální záznam - pouze CZ
    $stmtGet = $pdo->prepare("
        SELECT id, datum, obsah_cz
        FROM wgs_natuzzi_aktuality
        WHERE id = :id
    ");
    $stmtGet->execute(['id' => $aktualitaId]);
    $aktualita = $stmtGet->fetch(PDO::FETCH_ASSOC);

    if (!$aktualita) {
        sendJsonError('Aktualita nebyla nalezena', 404);
    }

    $staryObsahCely = $aktualita['obsah_cz'];

    // Pokud je to nový článek, přidat na konec
    if ($jeNovyClanek) {
        $novyObsahCely = $staryObsahCely . "\n\n" . $novyObsah;
    } else {
        // Rozdělit na jednotlivé články
        $parts = preg_split('/(?=^## )/m', $staryObsahCely);

        $noveCasti = [];
        $currentIndex = 0;
        $articleUpdated = false;

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // První část je hlavní nadpis - zachovat
            if ($currentIndex === 0 && !preg_match('/^## /', $part)) {
                $noveCasti[] = $part;
                continue;
            }

            // Pokud je to článek s ## nadpisem
            if (preg_match('/^## /', $part)) {
                if ($currentIndex === $index) {
                    // Nahradit tímto článkem novým obsahem
                    $noveCasti[] = $novyObsah;
                    $articleUpdated = true;
                } else {
                    // Zachovat původní článek
                    $noveCasti[] = $part;
                }
                $currentIndex++;
            }
        }

        if (!$articleUpdated) {
            sendJsonError('Článek s indexem ' . $index . ' nebyl nalezen');
        }

        // Složit zpět dohromady
        $novyObsahCely = implode("\n\n", $noveCasti);
    }

    // Zpracovat upload fotky pokud byla nahrána
    if (isset($_FILES['fotka']) && $_FILES['fotka']['error'] === UPLOAD_ERR_OK) {
        $fotka = $_FILES['fotka'];

        // Validace typu souboru
        $povoleneMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fotka['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $povoleneMimeTypes)) {
            sendJsonError('Neplatný formát souboru. Povolené formáty: JPG, PNG, GIF, WebP');
        }

        // Validace velikosti (max 5MB)
        if ($fotka['size'] > 5 * 1024 * 1024) {
            sendJsonError('Soubor je příliš velký. Maximální velikost je 5 MB.');
        }

        // Vytvořit složku pokud neexistuje
        $uploadDir = __DIR__ . '/../uploads/aktuality/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generovat unikátní název souboru
        $indexSuffix = $jeNovyClanek ? 'novy' : $index;
        $baseFileName = 'aktualita_' . $aktualitaId . '_clanek_' . $indexSuffix . '_' . time();

        // Konvertovat do WebP pro optimalizaci (úspora ~80% velikosti)
        $webpFileName = $baseFileName . '.webp';
        $webpFilePath = $uploadDir . $webpFileName;

        // Načíst originální obrázek podle typu
        $sourceImage = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($fotka['tmp_name']);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($fotka['tmp_name']);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($fotka['tmp_name']);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($fotka['tmp_name']);
                break;
        }

        if (!$sourceImage) {
            sendJsonError('Nepodařilo se zpracovat obrázek');
        }

        // Konvertovat do WebP s kvalitou 85 (dobrý poměr kvalita/velikost)
        if (!imagewebp($sourceImage, $webpFilePath, 85)) {
            imagedestroy($sourceImage);
            sendJsonError('Chyba při konverzi obrázku do WebP formátu');
        }

        // Uvolnit paměť
        imagedestroy($sourceImage);

        // URL fotky pro použití v markdownu (WebP verze)
        $fotkaUrl = '/uploads/aktuality/' . $webpFileName;

        // Nahradit placeholder skutečnou URL v obsahu
        $novyObsahCely = str_replace('PLACEHOLDER_NEW_PHOTO', $fotkaUrl, $novyObsahCely);

        // Audit log - zaznamenat konverzi
        error_log(sprintf(
            "WEBP CONVERSION: Original size: %d bytes (%s) → WebP: %s",
            $fotka['size'],
            $mimeType,
            $webpFileName
        ));
    }

    // Automaticky přeložit do EN a IT
    $translator = new WGSTranslator($pdo);

    // Přeložit celý obsah do angličtiny a italštiny
    $obsahEn = $translator->preloz($novyObsahCely, 'en', 'aktualita', $aktualitaId);
    $obsahIt = $translator->preloz($novyObsahCely, 'it', 'aktualita', $aktualitaId);

    // Aktualizovat všechny jazykové verze v databázi
    $stmtUpdate = $pdo->prepare("
        UPDATE wgs_natuzzi_aktuality
        SET obsah_cz = :obsah_cz,
            obsah_en = :obsah_en,
            obsah_it = :obsah_it,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        'obsah_cz' => $novyObsahCely,
        'obsah_en' => $obsahEn,
        'obsah_it' => $obsahIt,
        'id' => $aktualitaId
    ]);

    // Audit log
    $prelozenoInfo = ($obsahEn !== $novyObsahCely || $obsahIt !== $novyObsahCely)
        ? ' | Translations: EN=' . strlen($obsahEn) . ', IT=' . strlen($obsahIt)
        : ' | Translations: cached';

    if ($jeNovyClanek) {
        error_log(sprintf(
            "ADMIN ADD AKTUALITA: User %d added new article to aktualita #%d on %s | Length: %d -> %d chars%s",
            $_SESSION['user_id'] ?? 0,
            $aktualitaId,
            $aktualita['datum'],
            strlen($staryObsahCely),
            strlen($novyObsahCely),
            $prelozenoInfo
        ));
    } else {
        error_log(sprintf(
            "ADMIN EDIT AKTUALITA: User %d edited aktualita #%d (článek index %d) on %s | Length: %d -> %d chars%s",
            $_SESSION['user_id'] ?? 0,
            $aktualitaId,
            $index,
            $aktualita['datum'],
            strlen($staryObsahCely),
            strlen($novyObsahCely),
            $prelozenoInfo
        ));
    }

    $successMessage = $jeNovyClanek
        ? 'Nový článek byl úspěšně přidán a automaticky přeložen'
        : 'Článek byl úspěšně upraven a automaticky přeložen';

    sendJsonSuccess($successMessage, [
        'aktualita_id' => $aktualitaId,
        'index' => $index,
        'delka_noveho_obsahu' => strlen($novyObsahCely),
        'je_novy' => $jeNovyClanek,
        'preklady' => [
            'en' => strlen($obsahEn),
            'it' => strlen($obsahIt)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in uprav_celou_aktualitu.php: " . $e->getMessage());
    sendJsonError('Chyba při ukládání změn do databáze');
} catch (Exception $e) {
    error_log("Error in uprav_celou_aktualitu.php: " . $e->getMessage());
    sendJsonError('Neočekávaná chyba při ukládání');
}
?>
