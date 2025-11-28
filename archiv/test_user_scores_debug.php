<?php
/**
 * DEBUG TEST - zobrazí PŘESNOU PDO chybu
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background:#000;color:#0f0;padding:20px;font-family:monospace;'>";
echo "=== USER SCORES API DEBUG ===\n\n";

require_once __DIR__ . '/init.php';

try {
    $pdo = getDbConnection();
    echo "✓ DB připojení OK\n\n";

    // Simulovat parametry z API
    $dateFrom = date('Y-m-d', strtotime('-30 days'));
    $dateTo = date('Y-m-d');
    $limit = 10;
    $offset = 0;

    echo "Parametry:\n";
    echo "  date_from: {$dateFrom}\n";
    echo "  date_to: {$dateTo}\n";
    echo "  limit: {$limit}\n";
    echo "  offset: {$offset}\n\n";

    // Test 1: Základní SELECT
    echo "TEST 1: Základní SELECT z wgs_analytics_user_scores\n";
    echo str_repeat('-', 60) . "\n";

    $sql = "
    SELECT
        us.*,
        s.session_start,
        s.session_end,
        s.device_type,
        s.country_code,
        s.city
    FROM wgs_analytics_user_scores us
    LEFT JOIN wgs_analytics_sessions s ON us.session_id = s.session_id
    WHERE 1=1
    ";

    $params = [];

    // Date range filter
    if ($dateFrom) {
        $sql .= " AND DATE(us.created_at) >= :date_from";
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $sql .= " AND DATE(us.created_at) <= :date_to";
        $params['date_to'] = $dateTo;
    }

    $sql .= " ORDER BY us.created_at DESC LIMIT :limit OFFSET :offset";

    echo "SQL:\n{$sql}\n\n";
    echo "Params:\n";
    print_r($params);
    echo "\n";

    $stmt = $pdo->prepare($sql);

    // Bind parametry
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
        echo "Binding :{$key} => {$value}\n";
    }

    // LIMIT a OFFSET jako INT
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    echo "Binding :limit => {$limit} (INT)\n";
    echo "Binding :offset => {$offset} (INT)\n\n";

    echo "Executing...\n";
    $stmt->execute();

    $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "SELECT OK - nalezeno " . count($scores) . " záznamů\n\n";

    // Test 2: COUNT query
    echo "TEST 2: COUNT query\n";
    echo str_repeat('-', 60) . "\n";

    $countSql = "SELECT COUNT(*) as total FROM wgs_analytics_user_scores WHERE 1=1";
    $countParams = [];

    if ($dateFrom) {
        $countSql .= " AND DATE(created_at) >= :date_from";
        $countParams['date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $countSql .= " AND DATE(created_at) <= :date_to";
        $countParams['date_to'] = $dateTo;
    }

    echo "SQL:\n{$countSql}\n\n";
    echo "Params:\n";
    print_r($countParams);
    echo "\n";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "COUNT OK - celkem {$total} záznamů\n\n";

    echo "VŠECHNY TESTY PROŠLY!\n";
    echo "API by mělo fungovat. Problém je možná jinde.\n";

} catch (PDOException $e) {
    echo "\nPDO EXCEPTION!\n";
    echo str_repeat('=', 60) . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Error Message: " . $e->getMessage() . "\n";
    echo "Error Info:\n";
    print_r($e->errorInfo ?? []);
    echo "\n\nFile: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "\nEXCEPTION!\n";
    echo str_repeat('=', 60) . "\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "</pre>";
?>
