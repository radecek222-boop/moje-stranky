<?php
/**
 * Get Photos API
 * Načítání fotek z databáze pro protokol a photocustomer
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/reklamace_id_validator.php';

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

    // PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
    session_write_close();

    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Povolena pouze GET metoda');
    }

    // Získání a validace reklamace ID
    $reklamaceId = sanitizeReklamaceId($_GET['reklamace_id'] ?? null, 'reklamace_id');

    // Databázové připojení
    $pdo = getDbConnection();

    // BEZPEČNOST: Ověření existence reklamace
    $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    // Načtení fotek z databáze
    $stmt = $pdo->prepare("
        SELECT
            id, section_name, photo_path, file_path, file_name,
            photo_type, photo_order
        FROM wgs_photos
        WHERE reklamace_id = :reklamace_id
        ORDER BY photo_order ASC, id ASC
    ");
    $stmt->execute([':reklamace_id' => $reklamaceId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Připravit oba formáty: sections (pro zpětnou kompatibilitu) a photos (pro seznam.js)
    $photosList = [];
    $sections = [
        'before' => [],
        'id' => [],
        'problem' => [],
        'repair' => [],
        'after' => []
    ];

    foreach ($photos as $photo) {
        $photoPath = $photo['photo_path'];
        $sectionName = $photo['section_name'];

        // BEZPEČNOST: Path Traversal ochrana - ověření že cesta vede do uploads/
        $fullPath = __DIR__ . '/../' . $photoPath;
        $realPath = realpath($fullPath);
        $uploadsDir = realpath(__DIR__ . '/../uploads');

        // Pokud realpath selže nebo cesta nevede do uploads/, přeskočit
        if (!$realPath || !$uploadsDir || strpos($realPath, $uploadsDir) !== 0) {
            continue; // Přeskočit podezřelý soubor
        }

        if (file_exists($fullPath)) {
            // Pro seznam.js - jednoduché pole s cestami
            $photosList[] = [
                'id' => $photo['id'],
                'photo_path' => $photoPath,
                'section_name' => $sectionName,
                'photo_type' => $photo['photo_type'],
                'photo_order' => $photo['photo_order']
            ];

            // Pro protokol.min.js - sections s base64 daty (zpětná kompatibilita)
            if (isset($sections[$sectionName])) {
                // Načtení obrázku a převod na base64
                $imageData = file_get_contents($fullPath);
                if ($imageData !== false) {
                    // Detekce MIME typu
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $fullPath);
                    finfo_close($finfo);

                    // Převod na base64 data URI
                    $base64Data = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

                    $sections[$sectionName][] = [
                        'type' => $photo['photo_type'],
                        'data' => $base64Data
                    ];
                }
            }
        }
    }

    // Úspěšná odpověď s oběma formáty
    echo json_encode([
        'success' => true,
        'total_photos' => count($photosList),
        'photos' => $photosList,          // Pro seznam.js (nový formát)
        'sections' => $sections            // Pro protokol.min.js (původní formát)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'total_photos' => 0,
        'photos' => [],
        'sections' => [
            'before' => [],
            'id' => [],
            'problem' => [],
            'repair' => [],
            'after' => []
        ]
    ]);
}
