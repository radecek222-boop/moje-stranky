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

    // Celkový obrat (pokud máme sloupec castka/cena)
    $stmt = $pdo->prepare("SELECT SUM(CAST(castka AS DECIMAL(10,2))) as total FROM wgs_reklamace $where");
    $stmt->execute($params);
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Průměrná zakázka
    $avgOrder = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

    // Aktivní technici v období
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT technik) as count
        FROM wgs_reklamace
        $where AND technik IS NOT NULL AND technik != ''
    ");
    $stmt->execute($params);
    $activeTechs = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

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

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(prodejce, 'Neuvedeno') as prodejce,
            COUNT(*) as pocet_zakazek,
            SUM(CAST(COALESCE(castka, 0) AS DECIMAL(10,2))) as celkova_castka,
            AVG(CAST(COALESCE(castka, 0) AS DECIMAL(10,2))) as prumer_zakazka,
            SUM(CASE WHEN zeme = 'CZ' OR zeme = '' OR zeme IS NULL THEN 1 ELSE 0 END) as cz_count,
            SUM(CASE WHEN zeme = 'SK' THEN 1 ELSE 0 END) as sk_count,
            SUM(CASE WHEN stav = 'HOTOVO' THEN 1 ELSE 0 END) as hotove_count
        FROM wgs_reklamace
        $where
        GROUP BY prodejce
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

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(technik, 'Neuvedeno') as technik,
            COUNT(*) as pocet_zakazek,
            SUM(CAST(COALESCE(castka, 0) AS DECIMAL(10,2))) as celkova_castka,
            SUM(CAST(COALESCE(castka, 0) AS DECIMAL(10,2))) * 0.33 as vydelek,
            AVG(CAST(COALESCE(castka, 0) AS DECIMAL(10,2))) as prumer_zakazka,
            SUM(CASE WHEN zeme = 'CZ' OR zeme = '' OR zeme IS NULL THEN 1 ELSE 0 END) as cz_count,
            SUM(CASE WHEN zeme = 'SK' THEN 1 ELSE 0 END) as sk_count,
            SUM(CASE WHEN stav = 'HOTOVO' THEN 1 ELSE 0 END) as hotove_count
        FROM wgs_reklamace
        $where
        GROUP BY technik
        ORDER BY pocet_zakazek DESC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vypočítat úspěšnost
    foreach ($data as &$row) {
        $total = (int)$row['pocet_zakazek'];
        $hotove = (int)$row['hotove_count'];
        $row['uspesnost'] = $total > 0 ? round(($hotove / $total) * 100, 1) : 0;
        $row['vydelek'] = round((float)$row['vydelek'], 2);
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
            SUM(CAST(COALESCE(castka, 0) AS DECIMAL(10,2))) as celkova_castka,
            AVG(CAST(COALESCE(castka, 0) AS DECIMAL(10,2))) as prumerna_castka
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
            prodejce,
            technik,
            CAST(COALESCE(castka, 0) AS DECIMAL(10,2)) as castka,
            stav,
            COALESCE(zeme, 'CZ') as zeme,
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

    // Rozdělení podle měst
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(mesto, 'Neuvedeno') as mesto,
            COUNT(*) as pocet
        FROM wgs_reklamace
        $where
        GROUP BY mesto
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rozdělení podle zemí
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(zeme, 'CZ') as zeme,
            COUNT(*) as pocet
        FROM wgs_reklamace
        $where
        GROUP BY zeme
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
        $conditions[] = "zeme = :country";
    }

    if (!empty($filters['status'])) {
        $conditions[] = "stav = :status";
    }

    if (!empty($filters['salesperson'])) {
        $conditions[] = "prodejce = :salesperson";
    }

    if (!empty($filters['technician'])) {
        $conditions[] = "technik = :technician";
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

    if (!empty($filters['technician'])) {
        $params[':technician'] = $filters['technician'];
    }

    if (!empty($filters['date_from'])) {
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $params[':date_to'] = $filters['date_to'];
    }

    return $params;
}
