<?php
/**
 * Save Photos from Photocustomer
 * Ukládání fotek z photocustomer.php (návštěva technika)
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // BEZPEČNOST: Rate limiting - ochrana proti DoS útokům
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimit = checkRateLimit("upload_customer_$ip", 30, 3600); // 30 uploadů za hodinu

    if (!$rateLimit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Příliš mnoho požadavků. Zkuste to za ' . ceil($rateLimit['retry_after'] / 60) . ' minut.',
            'retry_after' => $rateLimit['retry_after']
        ]);
        exit;
    }

    // Zaznamenat pokus o upload
    recordLoginAttempt("upload_customer_$ip");

    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    // Získání reklamace ID
    $reklamaceId = $data['reklamace_id'] ?? null;
    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    // BEZPEČNOST: Validace reklamace_id - musí být pouze alfanumerické znaky
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
        throw new Exception('Neplatné ID reklamace');
    }

    // Získání sections
    $sections = $data['sections'] ?? [];
    if (empty($sections)) {
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

    // Vytvoření podadresáře pro konkrétní reklamaci (basename pro extra bezpečnost)
    $reklamaceDir = $uploadsDir . '/' . basename($reklamaceId);
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
            // Dekódování base64
            // Data jsou ve formátu: data:image/jpeg;base64,/9j/4AAQ...
            if (preg_match('/^data:image\/(\w+);base64,/', $photoData, $matches)) {
                $imageType = $matches[1];
                $photoData = substr($photoData, strpos($photoData, ',') + 1);
            } else {
                $imageType = 'jpeg';
            }

            $decodedData = base64_decode($photoData);
            if ($decodedData === false) {
                continue; // Skip invalid images
            }

            // Generování unikátního názvu souboru
            $timestamp = time();
            $randomString = bin2hex(random_bytes(4));
            $filename = "{$sectionName}_{$reklamaceId}_{$index}_{$timestamp}_{$randomString}.{$imageType}";
            $filePath = $reklamaceDir . '/' . $filename;

            // Uložení souboru
            if (file_put_contents($filePath, $decodedData) === false) {
                throw new Exception("Nepodařilo se uložit fotku $sectionName/$index");
            }

            // Relativní cesta pro databázi
            $relativePathForDb = "uploads/photos/{$reklamaceId}/{$filename}";

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
                ':photo_type' => 'image',
                ':photo_order' => $photoOrder
            ]);

            $savedPhotos[] = [
                'photo_id' => $pdo->lastInsertId(),
                'section' => $sectionName,
                'path' => $relativePathForDb,
                'filename' => $filename
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
