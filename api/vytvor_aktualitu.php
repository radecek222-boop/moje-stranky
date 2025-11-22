<?php
/**
 * API endpoint pro vytvoření nové vlastní aktuality
 * Přijímá: datum, svátek, komentář, obsah ve 3 jazycích, fotografie
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
    if (!$rateLimiter->checkLimit('create_aktualita', $_SERVER['REMOTE_ADDR'], 10, 3600)) {
        sendJsonError('Příliš mnoho požadavků na vytvoření', 429);
    }

    // Validace vstupních dat
    $datum = $_POST['datum'] ?? '';
    $svatek = trim($_POST['svatek'] ?? '');
    $komentar = trim($_POST['komentar'] ?? '');
    $obsahCz = trim($_POST['obsah_cz'] ?? '');
    $obsahEn = trim($_POST['obsah_en'] ?? '');
    $obsahIt = trim($_POST['obsah_it'] ?? '');

    // Validace datumu
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        sendJsonError('Neplatný formát data (očekáváno YYYY-MM-DD)');
    }

    $datumObj = DateTime::createFromFormat('Y-m-d', $datum);
    if (!$datumObj || $datumObj->format('Y-m-d') !== $datum) {
        sendJsonError('Neplatné datum');
    }

    // Validace obsahů
    if (empty($obsahCz)) {
        sendJsonError('Chybí český obsah');
    }

    if (empty($obsahEn)) {
        sendJsonError('Chybí anglický obsah');
    }

    if (empty($obsahIt)) {
        sendJsonError('Chybí italský obsah');
    }

    // Kontrola zda už pro toto datum existuje aktualita
    $stmtCheck = $pdo->prepare("
        SELECT id FROM wgs_natuzzi_aktuality WHERE datum = :datum
    ");
    $stmtCheck->execute(['datum' => $datum]);

    if ($stmtCheck->rowCount() > 0) {
        sendJsonError('Pro toto datum již aktualita existuje. Smazat nejdřív starou aktualitu.');
    }

    // Zpracování fotografií
    $uploadedPhotos = [];
    $uploadDir = __DIR__ . '/../uploads/aktuality/';

    // Vytvořit složku pokud neexistuje
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Projít všechny nahrané soubory
    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'foto_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

            if (!in_array($file['type'], $allowedTypes)) {
                continue; // Přeskočit nepovolené typy
            }

            if ($file['size'] > 5 * 1024 * 1024) { // Max 5 MB
                continue; // Přeskočit příliš velké soubory
            }

            // Generovat unikátní název
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = 'aktualita_' . $datum . '_' . uniqid() . '.' . $extension;
            $targetPath = $uploadDir . $newFilename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $uploadedPhotos[] = 'uploads/aktuality/' . $newFilename;
            }
        }
    }

    // Přidat odkazy na fotky do obsahu (pokud byly nahrány)
    if (!empty($uploadedPhotos)) {
        $fotoMarkdown = "\n\n";
        foreach ($uploadedPhotos as $index => $photoPath) {
            $fotoMarkdown .= "![Fotografie " . ($index + 1) . "](/" . $photoPath . ")\n\n";
        }

        // Přidat fotky na konec každého jazykového obsahu
        $obsahCz .= $fotoMarkdown;
        $obsahEn .= $fotoMarkdown;
        $obsahIt .= $fotoMarkdown;
    }

    // Vložit do databáze
    $stmt = $pdo->prepare("
        INSERT INTO wgs_natuzzi_aktuality
        (datum, svatek_cz, komentar_dne, obsah_cz, obsah_en, obsah_it, zdroje_json, vygenerovano_ai, created_by_admin)
        VALUES
        (:datum, :svatek, :komentar, :obsah_cz, :obsah_en, :obsah_it, :zdroje, FALSE, TRUE)
    ");

    $zdroje = json_encode([
        'created_by' => 'admin',
        'user_id' => $_SESSION['user_id'] ?? 0,
        'created_at' => date('Y-m-d H:i:s'),
        'uploaded_photos' => $uploadedPhotos
    ], JSON_UNESCAPED_UNICODE);

    $stmt->execute([
        'datum' => $datum,
        'svatek' => $svatek ?: null,
        'komentar' => $komentar ?: null,
        'obsah_cz' => $obsahCz,
        'obsah_en' => $obsahEn,
        'obsah_it' => $obsahIt,
        'zdroje' => $zdroje
    ]);

    $newId = $pdo->lastInsertId();

    // Audit log
    error_log(sprintf(
        "ADMIN CREATE AKTUALITA: User %d created aktualita #%d for date %s | %d photos uploaded",
        $_SESSION['user_id'] ?? 0,
        $newId,
        $datum,
        count($uploadedPhotos)
    ));

    sendJsonSuccess('Aktualita byla úspěšně vytvořena', [
        'id' => $newId,
        'datum' => $datum,
        'pocet_fotek' => count($uploadedPhotos),
        'fotky' => $uploadedPhotos
    ]);

} catch (PDOException $e) {
    error_log("Database error in vytvor_aktualitu.php: " . $e->getMessage());
    sendJsonError('Chyba při vytváření aktuality v databázi');
} catch (Exception $e) {
    error_log("Error in vytvor_aktualitu.php: " . $e->getMessage());
    sendJsonError('Neočekávaná chyba při vytváření aktuality');
}
?>
