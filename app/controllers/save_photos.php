<?php
/**
 * Save Photos Controller
 * Ukládání fotek z formuláře novareklamace.php
 */

require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

try {
    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // BEZPEČNOST: Rate limiting - ochrana proti DoS útokům
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimit = checkRateLimit("upload_photos_$ip", 20, 3600); // 20 uploadů za hodinu

    if (!$rateLimit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'error' => 'Příliš mnoho požadavků. Zkuste to za ' . ceil($rateLimit['retry_after'] / 60) . ' minut.',
            'retry_after' => $rateLimit['retry_after']
        ]);
        exit;
    }

    // Zaznamenat pokus o upload
    recordLoginAttempt("upload_photos_$ip");

    // Získání reklamace ID
    $reklamaceId = $_POST['reklamace_id'] ?? null;
    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    // BEZPEČNOST: Validace reklamace_id - musí být pouze alfanumerické znaky
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
        throw new Exception('Neplatné ID reklamace');
    }

    // Získání typu fotek
    $photoType = $_POST['photo_type'] ?? 'problem';
    $photoCount = intval($_POST['photo_count'] ?? 0);

    if ($photoCount === 0) {
        throw new Exception('Žádné fotky k nahrání');
    }

    // BEZPEČNOST: Kontrola počtu fotek (max 20 fotek)
    if ($photoCount > 20) {
        throw new Exception('Příliš mnoho fotek. Maximum je 20 fotek na upload.');
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

    // Vytvoření uploads adresáře, pokud neexistuje
    $uploadsDir = __DIR__ . '/../../uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Vytvoření podadresáře pro konkrétní reklamaci (basename pro extra bezpečnost)
    $reklamaceDir = $uploadsDir . '/reklamace_' . basename($reklamaceId);
    if (!is_dir($reklamaceDir)) {
        mkdir($reklamaceDir, 0755, true);
    }

    $savedPhotos = [];

    // Procházení všech fotek
    for ($i = 0; $i < $photoCount; $i++) {
        $photoDataKey = "photo_$i";
        $filenameKey = "filename_$i";

        if (!isset($_POST[$photoDataKey])) {
            continue;
        }

        $photoData = $_POST[$photoDataKey];
        $originalFilename = $_POST[$filenameKey] ?? "photo_$i.jpg";

        // BEZPEČNOST: Kontrola velikosti base64 dat (max 13MB = ~10MB obrázek)
        $base64Size = strlen($photoData);
        $maxBase64Size = 13 * 1024 * 1024; // 13MB

        if ($base64Size > $maxBase64Size) {
            throw new Exception("Fotka $i je příliš velká. Maximální velikost je 10 MB.");
        }

        // Dekódování base64
        // Data jsou ve formátu: data:image/jpeg;base64,/9j/4AAQ...
        if (preg_match('/^data:image\/(\w+);base64,/', $photoData, $matches)) {
            $imageType = $matches[1];
            $photoData = substr($photoData, strpos($photoData, ',') + 1);
        } else {
            $imageType = 'jpeg';
        }

        $photoData = base64_decode($photoData);
        if ($photoData === false) {
            throw new Exception("Nepodařilo se dekódovat fotku $i");
        }

        // BEZPEČNOST: MIME type validace
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $photoData);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'video/quicktime' // iPhone videa
        ];

        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new Exception("Nepodařilo se uložit fotku $i: Nepovolený typ souboru ($mimeType). Povolené typy: JPG, PNG, GIF, WebP, MP4.");
        }

        // Generování unikátního názvu souboru
        $timestamp = time();
        $randomString = bin2hex(random_bytes(4));
        $filename = "photo_{$reklamaceId}_{$timestamp}_{$randomString}.{$imageType}";
        $filePath = $reklamaceDir . '/' . $filename;

        // Uložení souboru
        if (file_put_contents($filePath, $photoData) === false) {
            throw new Exception("Nepodařilo se uložit fotku $i");
        }

        // Relativní cesta pro databázi
        $relativePathForDb = "uploads/reklamace_{$reklamaceId}/{$filename}";

        // Vložení do databáze (s file_path a file_name podle PHOTOS_FIX_REPORT.md)
        $stmt = $pdo->prepare("
            INSERT INTO wgs_photos (
                reklamace_id, section_name, photo_path, file_path, file_name,
                photo_type, created_at
            ) VALUES (
                :reklamace_id, :section_name, :photo_path, :file_path, :file_name,
                :photo_type, NOW()
            )
        ");

        $stmt->execute([
            ':reklamace_id' => $reklamaceId,
            ':section_name' => $photoType,
            ':photo_path' => $relativePathForDb,
            ':file_path' => $relativePathForDb,
            ':file_name' => $filename,
            ':photo_type' => 'image'
        ]);

        $savedPhotos[] = [
            'photo_id' => $pdo->lastInsertId(),
            'path' => $relativePathForDb,
            'filename' => $filename
        ];
    }

    // Úspěšná odpověď
    echo json_encode([
        'status' => 'success',
        'message' => 'Fotky úspěšně nahrány',
        'photos' => $savedPhotos,
        'count' => count($savedPhotos)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
