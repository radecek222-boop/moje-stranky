<?php
/**
 * Statistiky API
 * API pro načtení statistických dat z reklamací
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Neautorizovaný přístup'
    ]);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'summary':
            getSummaryStats($pdo);
            break;

        case 'salesperson':
            getSalespersonStats($pdo);
            break;

        case 'technician':
            getTechnicianStats($pdo);
            break;

        case 'models':
            getModelStats($pdo);
            break;

        case 'orders':
            getFilteredOrders($pdo);
            break;

        case 'charts':
            getChartsData($pdo);
            break;

        case 'list_salespersons':
            listSalespersons($pdo);
            break;

        case 'ping':
            echo json_encode(['status' => 'success', 'message' => 'pong', 'timestamp' => time()]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Neznámá akce'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Summary statistiky - celkové přehledy
 */
function getSummaryStats($pdo) {
    // Aplikovat filtry z GET parametrů
    $filters = getFilters();
    $where = buildWhereClause($filters);
    $params = buildParams($filters);

    // Celkem zakázek
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM wgs_reklamace $where");
    $stmt->execute($params);
    $totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Celkový obrat (používáme sloupec 'cena' který skutečně existuje)
    $stmt = $pdo->prepare("SELECT SUM(CAST(COALESCE(cena, 0) AS DECIMAL(10,2))) as total FROM wgs_reklamace $where");
    $stmt->execute($params);
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Průměrná zakázka
    $avgOrder = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

    // Aktivní technici v období (počítáme podle technik_milan_kolin a technik_radek_zikmund)
    $activeTechs = 0;
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN technik_milan_kolin > 0 THEN 1 ELSE 0 END) as milan,
            SUM(CASE WHEN technik_radek_zikmund > 0 THEN 1 ELSE 0 END) as radek
        FROM wgs_reklamace
        $where
    ");
    $stmt->execute($params);
    $techCount = $stmt->fetch(PDO::FETCH_ASSOC);
    $activeTechs = (($techCount['milan'] > 0) ? 1 : 0) + (($techCount['radek'] > 0) ? 1 : 0);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_orders' => (int)$totalOrders,
            'total_revenue' => round((float)$totalRevenue, 2),
            'avg_order' => round($avgOrder, 2),
            'active_techs' => (int)$activeTechs
        ]
    ]);
}

/**
 * Statistiky prodejců
 */
function getSalespersonStats($pdo) {
    $filters = getFilters();
    $where = buildWhereClause($filters);
    $params = buildParams($filters);

    // Používáme zpracoval místo prodejce (skutečný sloupec)
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(zpracoval, 'Neuvedeno') as prodejce,
            COUNT(*) as pocet_zakazek,
            SUM(CAST(COALESCE(cena, 0) AS DECIMAL(10,2))) as celkova_castka,
            AVG(CAST(COALESCE(cena, 0) AS DECIMAL(10,2))) as prumer_zakazka,
            SUM(CASE WHEN fakturace_firma = 'cz' OR fakturace_firma = '' OR fakturace_firma IS NULL THEN 1 ELSE 0 END) as cz_count,
            SUM(CASE WHEN fakturace_firma = 'sk' THEN 1 ELSE 0 END) as sk_count,
            SUM(CASE WHEN stav = 'done' THEN 1 ELSE 0 END) as hotove_count
        FROM wgs_reklamace
        $where
        GROUP BY zpracoval
        ORDER BY pocet_zakazek DESC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vypočítat procento hotových
    foreach ($data as &$row) {
        $total = (int)$row['pocet_zakazek'];
        $hotove = (int)$row['hotove_count'];
        $row['hotove_procento'] = $total > 0 ? round(($hotove / $total) * 100, 1) : 0;
        $row['celkova_castka'] = round((float)$row['celkova_castka'], 2);
        $row['prumer_zakazka'] = round((float)$row['prumer_zakazka'], 2);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
}

/**
 * Statistiky techniků
 */
function getTechnicianStats($pdo) {
    $filters = getFilters();
    $where = buildWhereClause($filters);
    $params = buildParams($filters);

    // Technici mají vlastní sloupce: technik_milan_kolin a technik_radek_zikmund (částky)
    // Použijeme UNION pro vytvoření řádků pro oba techniky
    $stmt = $pdo->prepare("
        SELECT
            'Milan Kolín' as technik,
            COUNT(*) as pocet_zakazek,
            COUNT(CASE WHEN stav = 'done' THEN 1 END) as pocet_dokonceno,
            SUM(CAST(COALESCE(technik_milan_kolin, 0) AS DECIMAL(10,2))) as celkova_castka_dokonceno,
            SUM(CAST(COALESCE(technik_milan_kolin, 0) AS DECIMAL(10,2))) as vydelek,
            AVG(CASE WHEN technik_milan_kolin > 0 THEN CAST(technik_milan_kolin AS DECIMAL(10,2)) END) as prumer_zakazka,
            SUM(CASE WHEN fakturace_firma = 'cz' OR fakturace_firma = '' OR fakturace_firma IS NULL THEN 1 ELSE 0 END) as cz_count,
            SUM(CASE WHEN fakturace_firma = 'sk' THEN 1 ELSE 0 END) as sk_count,
            SUM(CASE WHEN stav = 'done' THEN 1 ELSE 0 END) as hotove_count
        FROM wgs_reklamace
        $where AND technik_milan_kolin > 0

        UNION ALL

        SELECT
            'Radek Zikmund' as technik,
            COUNT(*) as pocet_zakazek,
            COUNT(CASE WHEN stav = 'done' THEN 1 END) as pocet_dokonceno,
            SUM(CAST(COALESCE(technik_radek_zikmund, 0) AS DECIMAL(10,2))) as celkova_castka_dokonceno,
            SUM(CAST(COALESCE(technik_radek_zikmund, 0) AS DECIMAL(10,2))) as vydelek,
            AVG(CASE WHEN technik_radek_zikmund > 0 THEN CAST(technik_radek_zikmund AS DECIMAL(10,2)) END) as prumer_zakazka,
            SUM(CASE WHEN fakturace_firma = 'cz' OR fakturace_firma = '' OR fakturace_firma IS NULL THEN 1 ELSE 0 END) as cz_count,
            SUM(CASE WHEN fakturace_firma = 'sk' THEN 1 ELSE 0 END) as sk_count,
            SUM(CASE WHEN stav = 'done' THEN 1 ELSE 0 END) as hotove_count
        FROM wgs_reklamace
        $where AND technik_radek_zikmund > 0

        ORDER BY pocet_zakazek DESC
    ");
    $stmt->execute(array_merge($params, $params)); // Parametry 2x pro oba SELECT
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vypočítat úspěšnost
    foreach ($data as &$row) {
        $total = (int)$row['pocet_zakazek'];
        $hotove = (int)$row['hotove_count'];
        $row['uspesnost'] = $total > 0 ? round(($hotove / $total) * 100, 1) : 0;
        $row['vydelek'] = round((float)$row['vydelek'], 2);
        $row['celkova_castka_dokonceno'] = round((float)$row['celkova_castka_dokonceno'], 2);
        $row['prumer_zakazka'] = round((float)$row['prumer_zakazka'], 2);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
}

/**
 * Nejporuchovější modely
 */
function getModelStats($pdo) {
    $filters = getFilters();
    $where = buildWhereClause($filters);
    $params = buildParams($filters);

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(model, 'Neuvedeno') as model,
            COUNT(*) as pocet_reklamaci,
            SUM(CAST(COALESCE(cena, 0) AS DECIMAL(10,2))) as celkova_castka,
            AVG(CAST(COALESCE(cena, 0) AS DECIMAL(10,2))) as prumerna_castka
        FROM wgs_reklamace
        $where
        GROUP BY model
        ORDER BY pocet_reklamaci DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Celkový počet reklamací pro výpočet podílu
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM wgs_reklamace $where");
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 1;

    // Vypočítat podíl
    foreach ($data as &$row) {
        $row['podil_procent'] = round(((int)$row['pocet_reklamaci'] / $total) * 100, 2);
        $row['celkova_castka'] = round((float)$row['celkova_castka'], 2);
        $row['prumerna_castka'] = round((float)$row['prumerna_castka'], 2);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
}

/**
 * Filtrované zakázky
 */
function getFilteredOrders($pdo) {
    $filters = getFilters();
    $where = buildWhereClause($filters);
    $params = buildParams($filters);

    $stmt = $pdo->prepare("
        SELECT
            id,
            cislo,
            jmeno,
            zpracoval as prodejce,
            CASE
                WHEN technik_milan_kolin > 0 THEN 'Milan Kolín'
                WHEN technik_radek_zikmund > 0 THEN 'Radek Zikmund'
                ELSE '-'
            END as technik,
            CAST(COALESCE(cena, 0) AS DECIMAL(10,2)) as castka,
            stav,
            COALESCE(UPPER(fakturace_firma), 'CZ') as zeme,
            DATE_FORMAT(created_at, '%d.%m.%Y') as datum
        FROM wgs_reklamace
        $where
        ORDER BY created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as &$row) {
        $row['castka'] = round((float)$row['castka'], 2);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
}

/**
 * Data pro grafy
 */
function getChartsData($pdo) {
    $filters = getFilters();
    $where = buildWhereClause($filters);
    $params = buildParams($filters);

    // Rozdělení podle měst - extrahujeme z adresy
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(adresa, ',', -1), '\n', 1)),
                'Neuvedeno'
            ) as mesto,
            COUNT(*) as pocet
        FROM wgs_reklamace
        $where
        GROUP BY mesto
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rozdělení podle zemí - používáme fakturace_firma
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(UPPER(fakturace_firma), 'CZ') as zeme,
            COUNT(*) as pocet
        FROM wgs_reklamace
        $where
        GROUP BY fakturace_firma
    ");
    $stmt->execute($params);
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Nejporuchovější modely (top 10)
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(model, 'Neuvedeno') as model,
            COUNT(*) as pocet
        FROM wgs_reklamace
        $where
        GROUP BY model
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'cities' => $cities,
            'countries' => $countries,
            'models' => $models
        ]
    ]);
}

/**
 * Vrátit seznam prodejců (pro filtr)
 */
function listSalespersons($pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT zpracoval as prodejce
        FROM wgs_reklamace
        WHERE zpracoval IS NOT NULL AND zpracoval != ''
        ORDER BY zpracoval ASC
    ");

    $salespersons = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'status' => 'success',
        'data' => $salespersons
    ]);
}

/**
 * Získat filtry z GET parametrů
 */
function getFilters() {
    return [
        'country' => $_GET['country'] ?? null,
        'status' => $_GET['status'] ?? null,
        'salesperson' => $_GET['salesperson'] ?? null,
        'technician' => $_GET['technician'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null
    ];
}

/**
 * Sestavit WHERE klauzuli
 */
function buildWhereClause($filters) {
    $conditions = ['1=1'];

    if (!empty($filters['country'])) {
        // Převést na lowercase pro porovnání s fakturace_firma ENUM('cz', 'sk')
        $conditions[] = "LOWER(fakturace_firma) = LOWER(:country)";
    }

    if (!empty($filters['status'])) {
        $conditions[] = "stav = :status";
    }

    if (!empty($filters['salesperson'])) {
        $conditions[] = "zpracoval = :salesperson";
    }

    if (!empty($filters['technician'])) {
        // Filtr podle jména technika (Milan Kolín nebo Radek Zikmund)
        if ($filters['technician'] === 'Milan Kolín') {
            $conditions[] = "technik_milan_kolin > 0";
        } elseif ($filters['technician'] === 'Radek Zikmund') {
            $conditions[] = "technik_radek_zikmund > 0";
        }
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = "DATE(created_at) >= :date_from";
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = "DATE(created_at) <= :date_to";
    }

    return 'WHERE ' . implode(' AND ', $conditions);
}

/**
 * Sestavit parametry pro prepared statement
 */
function buildParams($filters) {
    $params = [];

    if (!empty($filters['country'])) {
        $params[':country'] = $filters['country'];
    }

    if (!empty($filters['status'])) {
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['salesperson'])) {
        $params[':salesperson'] = $filters['salesperson'];
    }

    // Technik se filtruje přímo v WHERE (technik_milan_kolin > 0), nepotřebujeme parametr

    if (!empty($filters['date_from'])) {
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $params[':date_to'] = $filters['date_to'];
    }

    return $params;
}
