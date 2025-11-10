<?php
/**
 * Get Photos API
 * Načítání fotek z databáze pro protokol a photocustomer
 */

require_once __DIR__ . '/../init.php';

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

    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Povolena pouze GET metoda');
    }

    // Získání reklamace ID
    $reklamaceId = $_GET['reklamace_id'] ?? null;
    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    // BEZPEČNOST: Validace reklamace_id - musí být pouze alfanumerické znaky
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
        throw new Exception('Neplatné ID reklamace');
    }

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

    // Připravit pole fotek s cestami
    $photosList = [];

    foreach ($photos as $photo) {
        $photoPath = $photo['photo_path'];

        // Kontrola existence souboru na disku
        $fullPath = __DIR__ . '/../' . $photoPath;
        if (file_exists($fullPath)) {
            $photosList[] = [
                'id' => $photo['id'],
                'photo_path' => $photoPath,
                'section_name' => $photo['section_name'],
                'photo_type' => $photo['photo_type'],
                'photo_order' => $photo['photo_order']
            ];
        }
    }

    // Úspěšná odpověď
    echo json_encode([
        'success' => true,
        'total_photos' => count($photosList),
        'photos' => $photosList
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'total_photos' => 0,
        'photos' => []
    ]);
}
