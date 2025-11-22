<?php
require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Mimozáruční filtr</title></head><body>";
echo "<h1>Test filtru Mimozáruční servis</h1>";

try {
    $pdo = getDbConnection();
    
    // Test 1: Bez filtru
    echo "<h2>Test 1: Všechny záznamy (bez filtru)</h2>";
    $stmt1 = $pdo->query("
        SELECT reklamace_id, created_by, 
               COALESCE(u.name, 'Mimozáruční servis') as prodejce
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $result1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($result1, true) . "</pre>";
    
    // Test 2: S filtrem mimozáruční (created_by IS NULL)
    echo "<h2>Test 2: Pouze Mimozáruční servis (created_by IS NULL)</h2>";
    $stmt2 = $pdo->query("
        SELECT reklamace_id, created_by,
               COALESCE(u.name, 'Mimozáruční servis') as prodejce
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.id
        WHERE r.created_by IS NULL
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $result2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($result2, true) . "</pre>";
    
    // Test 3: S filtrem rok + měsíc + mimozáruční
    echo "<h2>Test 3: Rok 2025 + Měsíc 11 + Mimozáruční</h2>";
    $stmt3 = $pdo->query("
        SELECT reklamace_id, created_by,
               COALESCE(u.name, 'Mimozáruční servis') as prodejce,
               created_at
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.id
        WHERE YEAR(r.created_at) = 2025 
          AND MONTH(r.created_at) = 11
          AND r.created_by IS NULL
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $result3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($result3, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
