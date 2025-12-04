<?php
/**
 * Automatické přiřazení technika k zakázce
 *
 * Když technik otevře zakázku, automaticky se mu přiřadí (assigned_to = jeho ID)
 * Pouze pokud:
 * 1. Je přihlášen jako technik
 * 2. Zakázka ještě NEMÁ přiřazeného technika
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Kontrola přihlášení
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Uživatel není přihlášen'
        ]);
        exit;
    }

    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Povolena pouze POST metoda'
        ]);
        exit;
    }

    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    // CSRF ochrana
    $csrfToken = $data['csrf_token'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Neplatný CSRF token'
        ]);
        exit;
    }

    $reklamaceId = $data['reklamace_id'] ?? null;

    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    // Kontrola role - pouze technik
    $userRole = strtolower(trim($_SESSION['role'] ?? ''));
    $isTechnik = in_array($userRole, ['technik', 'technician'], true);

    if (!$isTechnik) {
        // Není technik - nic se neděje
        echo json_encode([
            'success' => true,
            'assigned' => false,
            'message' => 'Uživatel není technik, žádné přiřazení'
        ]);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $pdo = getDbConnection();

    // Najít zakázku
    $stmt = $pdo->prepare("
        SELECT id, reklamace_id, assigned_to, technik
        FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id OR cislo = :cislo OR id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId,
        ':id' => is_numeric($reklamaceId) ? (int)$reklamaceId : 0
    ]);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakazka) {
        throw new Exception('Zakázka nenalezena');
    }

    // Kontrola: Má už přiřazeného technika?
    if ($zakazka['assigned_to'] !== null && $zakazka['assigned_to'] > 0) {
        // Už má technika - nepřepisovat
        echo json_encode([
            'success' => true,
            'assigned' => false,
            'message' => 'Zakázka už má přiřazeného technika',
            'current_technician_id' => $zakazka['assigned_to']
        ]);
        exit;
    }

    // PŘIŘADIT technika
    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET assigned_to = :user_id,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':id' => $zakazka['id']
    ]);

    // Získat údaje technika
    $stmt = $pdo->prepare("SELECT name, email, phone FROM wgs_users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $technik = $stmt->fetch(PDO::FETCH_ASSOC);

    // Logování
    error_log(sprintf(
        "✓ Auto-assign: Technik %s (ID: %d) byl přiřazen k zakázce %s",
        $technik['name'] ?? 'Neznámý',
        $userId,
        $zakazka['reklamace_id']
    ));

    echo json_encode([
        'success' => true,
        'assigned' => true,
        'message' => 'Technik byl automaticky přiřazen k zakázce',
        'technician_id' => $userId,
        'technician_name' => $technik['name'] ?? '',
        'technician_email' => $technik['email'] ?? '',
        'technician_phone' => $technik['phone'] ?? ''
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
