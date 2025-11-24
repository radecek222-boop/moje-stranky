<?php
/**
 * API: Statistiky uživatele pro welcome modal
 * Vrací přehled zakázek podle role uživatele
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Kontrola přihlášení
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_admin'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Uživatel není přihlášen'
        ]);
        exit;
    }

    $pdo = getDbConnection();

    // Získat informace o uživateli
    $userId = $_SESSION['user_id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? null;
    $userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    // Sestavit WHERE podmínky podle role (stejná logika jako v load.php)
    $whereParts = [];
    $params = [];

    if (!$isAdmin) {
        $isProdejce = in_array($userRole, ['prodejce', 'user'], true);
        $isTechnik = in_array($userRole, ['technik', 'technician'], true);

        if ($isProdejce) {
            // PRODEJCE: Vidí pouze SVÉ reklamace
            if ($userId !== null) {
                $whereParts[] = 'created_by = :created_by';
                $params[':created_by'] = $userId;
            } else {
                // Bez user_id nevidí nic
                $whereParts[] = '1 = 0';
            }
        } elseif ($isTechnik) {
            // TECHNIK: Vidí VŠECHNY zakázky (žádný filtr)
            // Necháme prázdné whereParts
        } else {
            // GUEST: Vidí zakázky se svým emailem
            if ($userEmail) {
                $whereParts[] = 'LOWER(TRIM(email)) = LOWER(TRIM(:email))';
                $params[':email'] = $userEmail;
            } else {
                $whereParts[] = '1 = 0';
            }
        }
    }
    // Admin vidí VŠE (žádný filtr)

    // Sestavit WHERE klauzuli
    $whereClause = '';
    if (!empty($whereParts)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
    }

    // Získat počty podle stavů
    $sql = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN stav = 'wait' THEN 1 ELSE 0 END) as ceka,
            SUM(CASE WHEN stav = 'open' THEN 1 ELSE 0 END) as domluvena,
            SUM(CASE WHEN stav = 'done' THEN 1 ELSE 0 END) as hotovo
        FROM wgs_reklamace
        $whereClause
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Formátovat výstup
    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total' => (int) $stats['total'],
            'ceka' => (int) $stats['ceka'],
            'domluvena' => (int) $stats['domluvena'],
            'hotovo' => (int) $stats['hotovo'],
            'nevyreseno' => (int) $stats['ceka'] + (int) $stats['domluvena']
        ],
        'user' => [
            'name' => $_SESSION['user_name'] ?? 'Uživatel',
            'role' => $userRole,
            'is_admin' => $isAdmin
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Database error in get_user_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba při načítání statistik'
    ]);
} catch (Exception $e) {
    error_log("Error in get_user_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
