<?php
/**
 * TEST STATISTIKY API
 */

// Zapnout error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== TEST STATISTIKY API ===\n\n";

// Test 1: Include init.php
echo "1. Načítání init.php...\n";
try {
    require_once __DIR__ . '/init.php';
    echo "   init.php načten\n\n";
} catch (Exception $e) {
    echo "   Chyba: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 2: Zjistit session
echo "2. Kontrola session...\n";
echo "   Session ID: " . session_id() . "\n";
echo "   is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'not set') . "\n\n";

// Simuluj admin session
$_SESSION['is_admin'] = true;
echo "   Nastaveno is_admin = true\n\n";

// Test 3: DB Connection
echo "3. Test databázového připojení...\n";
try {
    $pdo = getDbConnection();
    echo "   Připojení k databázi OK\n\n";
} catch (Exception $e) {
    echo "   Chyba DB: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 4: Základní SQL dotaz
echo "4. Test základního SQL dotazu...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   SQL dotaz OK - počet reklamací: " . $result['count'] . "\n\n";
} catch (Exception $e) {
    echo "   Chyba SQL: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 5: Test buildFilterWhere funkce
echo "5. Test buildFilterWhere()...\n";
try {
    $_GET['rok'] = '2025';
    $_GET['mesic'] = '11';

    // Zkopírovat funkci z API
    function buildFilterWhere_test() {
        $conditions = [];
        $params = [];

        if (!empty($_GET['rok'])) {
            $conditions[] = "YEAR(created_at) = :rok";
            $params[':rok'] = (int)$_GET['rok'];
        }

        if (!empty($_GET['mesic'])) {
            $conditions[] = "MONTH(created_at) = :mesic";
            $params[':mesic'] = (int)$_GET['mesic'];
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }

    list($where, $params) = buildFilterWhere_test();
    echo "   WHERE: " . $where . "\n";
    echo "   Params: " . print_r($params, true) . "\n";
    echo "   buildFilterWhere OK\n\n";
} catch (Exception $e) {
    echo "   Chyba: " . $e->getMessage() . "\n\n";
}

// Test 6: Test getSummaryStatistiky
echo "6. Test getSummaryStatistiky simulace...\n";
try {
    // Celkem reklamací VŠECH
    $stmtAll = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
    $totalAll = (int)($stmtAll->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    echo "   Total all: $totalAll\n";

    // Částka celkem VŠECH
    $stmtRevenueAll = $pdo->query("
        SELECT SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as total
        FROM wgs_reklamace
    ");
    $revenueAll = (float)($stmtRevenueAll->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    echo "   Revenue all: $revenueAll €\n";

    echo "   getSummaryStatistiky simulace OK\n\n";
} catch (Exception $e) {
    echo "   Chyba: " . $e->getMessage() . "\n\n";
}

echo "=== VŠECHNY TESTY DOKONČENY ===\n";
