<?php
/**
 * Save Photos from Photocustomer
 * Ukládání fotek z photocustomer.php (návštěva technika)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/reklamace_id_validator.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Kontrola přihlášení (admin nebo technik)
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Neautorizovaný přístup. Přihlaste se prosím.'
        ]);
        exit;
    }

    // BEZPEČNOST: CSRF ochrana - kontrola před zpracováním dat
    // Načteme JSON data dříve, abychom mohli zkontrolovat CSRF token
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Neplatná JSON data'
        ]);
        exit;
    }

    // Kontrola CSRF tokenu
    $csrfToken = $data['csrf_token'] ?? '';
    if (is_array($csrfToken)) {
        $csrfToken = ''; // Bezpečnost: odmítnout array
    }

    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Neplatný bezpečnostní token. Obnovte stránku a zkuste to znovu.'
        ]);
        exit;
    }

    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // FIX 9: Databázový rate limiting - ochrana proti DoS útokům
    $pdo = getDbConnection();
    $rateLimiter = new RateLimiter($pdo);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rateCheck = $rateLimiter->checkLimit(
        $ip,
        'photo_upload',
        ['max_attempts' => 30, 'window_minutes' => 60, 'block_minutes' => 60]
    );

    if (!$rateCheck['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => $rateCheck['message'],
            'retry_after' => strtotime($rateCheck['reset_at']) - time()
        ]);
        exit;
    }

    // FIX 9: RateLimiter již zaznamenal pokus automaticky v checkLimit()

    // JSON data už jsou načtena výše (pro CSRF kontrolu)
    // $data je už k dispozici

    // Získání a validace reklamace ID
    $reklamaceId = sanitizeReklamaceId($data['reklamace_id'] ?? null, 'reklamace_id');
    $reklamaceStorageKey = reklamaceStorageKey($reklamaceId);

    // Získání sections
    $sections = $data['sections'] ?? [];
    if (empty($sections)) {
        throw new Exception('Žádné fotky k nahrání');
    }

    // BEZPEČNOST: Kontrola počtu fotek (max 50 fotek celkově)
    $totalPhotos = 0;
    foreach ($sections as $photos) {
        $totalPhotos += count($photos);
    }

    if ($totalPhotos > 50) {
        throw new Exception('Příliš mnoho fotek. Maximum je 50 fotek na upload.');
    }

    if ($totalPhotos === 0) {
        throw new Exception('Žádné fotky k nahrání');
    }

    // Databázové připojení
    $pdo = getDbConnection();

    // BEZPEČNOST: Ověření existence reklamace PŘED zápisem souborů
    $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    // Vytvoření uploads/photos adresáře
    $uploadsDir = __DIR__ . '/../uploads/photos';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Vytvoření podadresáře pro konkrétní reklamaci (bezpečný klíč bez lomítek)
    $reklamaceDir = $uploadsDir . '/' . $reklamaceStorageKey;
    if (!is_dir($reklamaceDir)) {
        mkdir($reklamaceDir, 0755, true);
    }

    $savedPhotos = [];
    $photoOrder = 0;

    // Procházení všech sekcí (before, id, problem, repair, after)
    foreach ($sections as $sectionName => $photos) {
        if (empty($photos)) {
            continue;
        }

        foreach ($photos as $index => $photoData) {
            // OPRAVENO: Fotky jsou objekty {type, data, size} ne jen string
            // Frontend posílá: {type: 'image', data: 'base64...', size: 12345}
            $base64Data = '';
            $photoType = 'image';

            if (is_array($photoData)) {
                // Nový formát z photocustomer.js
                $base64Data = $photoData['data'] ?? '';
                $photoType = $photoData['type'] ?? 'image';
            } elseif (is_string($photoData)) {
                // Starý formát (fallback)
                $base64Data = $photoData;
            } else {
                // Neplatný formát
                error_log("SAVE_PHOTOS: Neplatný formát fotky v sekci $sectionName, index $index");
                continue;
            }

            if (empty($base64Data)) {
                continue;
            }

            // BEZPEČNOST: Kontrola velikosti base64 dat (max 13MB = ~10MB obrázek)
            $base64Size = strlen($base64Data);
            $maxBase64Size = 13 * 1024 * 1024; // 13MB

            if ($base64Size > $maxBase64Size) {
                throw new Exception("Fotka v sekci '$sectionName' (index $index) je příliš velká. Maximální velikost je 10 MB.");
            }

            // Dekódování base64
            // Data jsou ve formátu: data:image/jpeg;base64,/9j/4AAQ...
            if (preg_match('/^data:(image|video)\/(\w+);base64,/', $base64Data, $matches)) {
                $mediaType = $matches[1]; // 'image' nebo 'video'
                $imageType = $matches[2]; // 'jpeg', 'png', 'mp4', atd.
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            } else {
                $imageType = 'jpeg';
                $mediaType = 'image';
            }

            $decodedData = base64_decode($base64Data);
            if ($decodedData === false) {
                error_log("SAVE_PHOTOS: Nepodařilo se dekódovat base64 data v sekci $sectionName, index $index");
                continue; // Skip invalid images
            }

            // Generování unikátního názvu souboru
            $timestamp = time();
            $randomString = bin2hex(random_bytes(4));
            $filename = "{$sectionName}_{$reklamaceStorageKey}_{$index}_{$timestamp}_{$randomString}.{$imageType}";
            $filePath = $reklamaceDir . '/' . $filename;

            // Uložení souboru
            if (file_put_contents($filePath, $decodedData) === false) {
                throw new Exception("Nepodařilo se uložit fotku $sectionName/$index");
            }

            // Relativní cesta pro databázi
            $relativePathForDb = "uploads/photos/{$reklamaceStorageKey}/{$filename}";

            // Vložení do databáze (podle PHOTOS_FIX_REPORT.md musí obsahovat file_path a file_name)
            $stmt = $pdo->prepare("
                INSERT INTO wgs_photos (
                    reklamace_id, section_name, photo_path, file_path, file_name,
                    photo_type, photo_order, uploaded_at, created_at
                ) VALUES (
                    :reklamace_id, :section_name, :photo_path, :file_path, :file_name,
                    :photo_type, :photo_order, NOW(), NOW()
                )
            ");

            $stmt->execute([
                ':reklamace_id' => $reklamaceId,
                ':section_name' => $sectionName,
                ':photo_path' => $relativePathForDb,
                ':file_path' => $relativePathForDb,
                ':file_name' => $filename,
                ':photo_type' => $photoType, // Použití správného photo_type
                ':photo_order' => $photoOrder
            ]);

            $savedPhotos[] = [
                'photo_id' => $pdo->lastInsertId(),
                'section' => $sectionName,
                'path' => $relativePathForDb,
                'filename' => $filename,
                'type' => $photoType
            ];

            $photoOrder++;
        }
    }

    // Úspěšná odpověď
    echo json_encode([
        'success' => true,
        'message' => 'Fotky úspěšně nahrány',
        'photos' => $savedPhotos,
        'count' => count($savedPhotos)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
