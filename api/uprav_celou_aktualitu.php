<?php
/**
 * API endpoint pro úpravu celého obsahu aktuality
 * Umožňuje adminovi upravit celý markdown obsah článku
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola admin oprávnění
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen - pouze pro administrátory', 403);
}

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
        $extension = pathinfo($fotka['name'], PATHINFO_EXTENSION);
        $fileName = 'aktualita_' . $aktualitaId . '_clanek_' . $index . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        // Přesunout soubor
        if (!move_uploaded_file($fotka['tmp_name'], $filePath)) {
            sendJsonError('Chyba při nahrávání souboru');
        }

        // URL fotky pro použití v markdownu
        $fotkaUrl = '/uploads/aktuality/' . $fileName;

        // Nahradit placeholder skutečnou URL v obsahu
        $novyObsahCely = str_replace('PLACEHOLDER_NEW_PHOTO', $fotkaUrl, $novyObsahCely);
    }

    // Aktualizovat obsah v databázi
    $stmtUpdate = $pdo->prepare("
        UPDATE wgs_natuzzi_aktuality
        SET obsah_cz = :obsah
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        'obsah' => $novyObsahCely,
        'id' => $aktualitaId
    ]);

    // Audit log
    error_log(sprintf(
        "ADMIN EDIT AKTUALITA: User %d edited aktualita #%d (článek index %d) on %s | Length: %d -> %d chars",
        $_SESSION['user_id'] ?? 0,
        $aktualitaId,
        $index,
        $aktualita['datum'],
        strlen($staryObsahCely),
        strlen($novyObsahCely)
    ));

    sendJsonSuccess('Článek byl úspěšně upraven', [
        'aktualita_id' => $aktualitaId,
        'index' => $index,
        'delka_noveho_obsahu' => strlen($novyObsahCely)
    ]);

} catch (PDOException $e) {
    error_log("Database error in uprav_celou_aktualitu.php: " . $e->getMessage());
    sendJsonError('Chyba při ukládání změn do databáze');
} catch (Exception $e) {
    error_log("Error in uprav_celou_aktualitu.php: " . $e->getMessage());
    sendJsonError('Neočekávaná chyba při ukládání');
}
?>
