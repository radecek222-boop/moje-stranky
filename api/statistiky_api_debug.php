<?php
/**
 * DEBUG VERZE - Statistiky API
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../init.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== STATISTIKY API DEBUG ===\n\n";

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
echo "1. is_admin: " . ($isAdmin ? 'true' : 'false') . "\n";

if (!$isAdmin) {
    echo "   ❌ Přístup odepřen\n";
    exit;
}

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

$action = $_GET['action'] ?? '';
echo "2. Action: '$action'\n\n";

try {
    $pdo = getDbConnection();
    echo "3. ✅ DB připojení OK\n\n";

    switch ($action) {
        case 'summary':
            echo "4. Volám getSummaryStatistiky()...\n";

            // Inline test
            echo "   4.1 Test basic query...\n";
            $stmtAll = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
            $totalAll = (int)($stmtAll->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
            echo "   Total: $totalAll\n";

            echo "   4.2 Test revenue query...\n";
            $stmtRevenueAll = $pdo->query("
                SELECT SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as total
                FROM wgs_reklamace
            ");
            $revenueAll = (float)($stmtRevenueAll->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
            echo "   Revenue: $revenueAll\n";

            echo "   ✅ getSummaryStatistiky test OK\n";
            break;

        case 'load_prodejci':
            echo "4. Volám loadProdejci()...\n";

            $stmt = $pdo->query("
                SELECT DISTINCT u.id, u.name
                FROM wgs_users u
                WHERE u.is_active = 1
                ORDER BY u.name ASC
            ");
            $prodejci = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "   Načteno prodejců: " . count($prodejci) . "\n";
            foreach ($prodejci as $p) {
                echo "   - ID: {$p['id']}, Name: {$p['name']}\n";
            }

            echo "   ✅ loadProdejci test OK\n";
            break;

        case 'load_technici':
            echo "4. Volám loadTechnici()...\n";

            $stmt = $pdo->query("
                SELECT id, name
                FROM wgs_users
                WHERE role = 'technik' AND is_active = 1
                ORDER BY name ASC
            ");
            $technici = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "   Načteno techniků: " . count($technici) . "\n";
            foreach ($technici as $t) {
                echo "   - ID: {$t['id']}, Name: {$t['name']}\n";
            }

            echo "   ✅ loadTechnici test OK\n";
            break;

        case 'get_zakazky':
            echo "4. Volám getZakazky()...\n";

            $sql = "
                SELECT
                    r.reklamace_id,
                    r.adresa,
                    r.model,
                    COALESCE(technik.name, '-') as technik,
                    COALESCE(prodejce.name, 'Mimozáruční servis') as prodejce,
                    CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2)) as castka_celkem,
                    CAST(COALESCE(r.cena_celkem, r.cena, 0) * 0.33 AS DECIMAL(10,2)) as vydelek_technika,
                    UPPER(COALESCE(r.fakturace_firma, 'cz')) as zeme,
                    DATE_FORMAT(r.created_at, '%d.%m.%Y') as datum
                FROM wgs_reklamace r
                LEFT JOIN wgs_users prodejce ON r.created_by = prodejce.id
                LEFT JOIN wgs_users technik ON r.zpracoval_id = technik.id AND technik.role = 'technik'
                ORDER BY r.created_at DESC
                LIMIT 2
            ";

            echo "   SQL:\n" . $sql . "\n";

            $stmt = $pdo->query($sql);
            $zakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "   Načteno zakázek: " . count($zakazky) . "\n";

            echo "   ✅ getZakazky test OK\n";
            break;

        default:
            echo "4. ❌ Neznámá akce: '$action'\n";
    }

} catch (Exception $e) {
    echo "\n❌ EXCEPTION:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG DONE ===\n";
