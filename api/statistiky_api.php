<?php
/**
 * Statistiky API - NOVÁ VERZE 2.0
 * API pro reporty a vyúčtování
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300'); // 5 minut cache

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

// PERFORMANCE FIX: Uvolnit session lock pro paralelní zpracování
// Audit 2025-11-24: Long-running statistiky queries blokují ostatní requesty
session_write_close();

$action = $_GET['action'] ?? '';

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'ping':
            echo json_encode([
                'status' => 'success',
                'message' => 'Statistiky API is reachable'
            ]);
            break;

        case 'summary':
            getSummaryStatistiky($pdo);
            break;

        case 'load_prodejci':
            loadProdejci($pdo);
            break;

        case 'load_technici':
            loadTechnici($pdo);
            break;

        case 'get_zakazky':
            getZakazky($pdo);
            break;

        case 'get_charts':
            getCharty($pdo);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Neznámá akce'
            ]);
    }
} catch (Exception $e) {
    error_log("Statistiky API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba serveru: ' . $e->getMessage()
    ]);
}

/**
 * Summary statistiky - 4 karty
 * 1. Celkem reklamací (všechny)
 * 2. Reklamací v měsíci (filtrované)
 * 3. Částka celkem (všechny)
 * 4. Částka v měsíci (filtrované)
 */
function getSummaryStatistiky($pdo) {
    // Celkem reklamací VŠECH (bez filtru)
    $stmtAll = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
    $totalAll = (int)($stmtAll->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Částka celkem VŠECH
    $stmtRevenueAll = $pdo->query("
        SELECT SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as total
        FROM wgs_reklamace
    ");
    $revenueAll = (float)($stmtRevenueAll->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // Filtrované statistiky
    list($where, $params) = buildFilterWhere();

    // Reklamací v měsíci (filtrované)
    $stmtMonth = $pdo->prepare("SELECT COUNT(*) as count FROM wgs_reklamace r $where");
    $stmtMonth->execute($params);
    $totalMonth = (int)($stmtMonth->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Částka v měsíci (filtrované)
    $stmtRevenueMonth = $pdo->prepare("
        SELECT SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) as total
        FROM wgs_reklamace r
        $where
    ");
    $stmtRevenueMonth->execute($params);
    $revenueMonth = (float)($stmtRevenueMonth->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_all' => $totalAll,
            'total_month' => $totalMonth,
            'revenue_all' => round($revenueAll, 2),
            'revenue_month' => round($revenueMonth, 2)
        ]
    ]);
}

/**
 * Načíst seznam prodejců pro multi-select
 * Včetně "Mimozáruční servis" (created_by IS NULL)
 */
function loadProdejci($pdo) {
    // FIX: Vracet user_id jako id, protože created_by v reklamacích obsahuje user_id
    $stmt = $pdo->query("
        SELECT DISTINCT u.user_id as id, u.name
        FROM wgs_users u
        WHERE u.is_active = 1
        ORDER BY u.name ASC
    ");

    $prodejci = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Přidat "Mimozáruční servis" na začátek
    array_unshift($prodejci, [
        'id' => 'mimozarucni',
        'name' => 'Mimozáruční servis'
    ]);

    echo json_encode([
        'status' => 'success',
        'data' => $prodejci
    ]);
}

/**
 * Načíst seznam techniků pro multi-select
 * Pouze registrovaní technici (role='technik')
 */
function loadTechnici($pdo) {
    $stmt = $pdo->query("
        SELECT id, name
        FROM wgs_users
        WHERE role = 'technik' AND is_active = 1
        ORDER BY name ASC
    ");

    $technici = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $technici
    ]);
}

/**
 * Získat zakázky podle filtrů
 * Sloupce: Reklamace ID, Adresa, Model, Technik, Prodejce, Částka celkem, Výdělek technika (33%), Země, Datum
 * Max 50 řádků pro UI, ale pro export všechny
 */
function getZakazky($pdo) {
    $proExport = isset($_GET['pro_export']) && $_GET['pro_export'] === '1';
    $stranka = isset($_GET['stranka']) ? (int)$_GET['stranka'] : 1;
    $limit = $proExport ? 100000 : 50; // Pro export bez limitu (nebo vysoký limit)
    $offset = ($stranka - 1) * $limit;

    list($where, $params) = buildFilterWhere();

    // Celkový počet záznamů (pro stránkování)
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as count FROM wgs_reklamace r $where");
    $stmtCount->execute($params);
    $totalCount = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Hlavní dotaz
    // Technik: nejprve zkusit JOIN na assigned_to, pak fallback na textový sloupec technik
    $sql = "
        SELECT
            r.cislo as cislo_reklamace,
            r.adresa,
            r.model,
            COALESCE(technik.name, r.technik, '-') as technik,
            COALESCE(prodejce.name, 'Mimozáruční servis') as prodejce,
            CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2)) as castka_celkem,
            CAST(COALESCE(r.cena_celkem, r.cena, 0) * 0.33 AS DECIMAL(10,2)) as vydelek_technika,
            UPPER(COALESCE(r.fakturace_firma, 'cz')) as zeme,
            DATE_FORMAT(r.created_at, '%d.%m.%Y') as datum,
            r.created_at as datum_raw
        FROM wgs_reklamace r
        LEFT JOIN wgs_users prodejce ON r.created_by = prodejce.user_id
        LEFT JOIN wgs_users technik ON r.assigned_to = technik.id AND technik.role = 'technik'
        $where
        ORDER BY r.created_at DESC
    ";

    if (!$proExport) {
        $sql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);

    // Bind parametry
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    if (!$proExport) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    $zakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'zakazky' => $zakazky,
            'total_count' => $totalCount,
            'stranka' => $stranka,
            'celkem_stranek' => ceil($totalCount / $limit)
        ],
        // DEBUG INFO - odstranit po vyřešení
        'debug' => [
            'GET_params' => $_GET,
            'where_clause' => $where,
            'bound_params' => $params,
            'sql_query' => $sql
        ]
    ]);
}

/**
 * Získat data pro grafy
 * 1. Nejporuchovější modely
 * 2. Lokality (města)
 * 3. Statistiky prodejců
 * 4. Statistiky techniků
 */
function getCharty($pdo) {
    list($where, $params) = buildFilterWhere();

    // 1. Nejporuchovější modely
    $stmtModely = $pdo->prepare("
        SELECT
            COALESCE(r.model, 'Neuvedeno') as model,
            COUNT(*) as pocet
        FROM wgs_reklamace r
        $where
        GROUP BY r.model
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmtModely->execute($params);
    $modely = $stmtModely->fetchAll(PDO::FETCH_ASSOC);

    // 2. Lokality - extrahujeme město z adresy (druhá část po čárce)
    $stmtMesta = $pdo->prepare("
        SELECT
            TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(r.adresa, ',', 2), ',', -1)) as mesto,
            COUNT(*) as pocet
        FROM wgs_reklamace r
        $where
        GROUP BY mesto
        ORDER BY pocet DESC
        LIMIT 15
    ");
    $stmtMesta->execute($params);
    $mesta = $stmtMesta->fetchAll(PDO::FETCH_ASSOC);

    // 3. Statistiky prodejců
    $stmtProdejci = $pdo->prepare("
        SELECT
            COALESCE(u.name, 'Mimozáruční servis') as prodejce,
            COUNT(*) as pocet,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) as celkem
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        $where
        GROUP BY u.name, u.user_id
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmtProdejci->execute($params);
    $prodejci = $stmtProdejci->fetchAll(PDO::FETCH_ASSOC);

    // 4. Statistiky techniků
    // Technik: nejprve zkusit JOIN na assigned_to, pak fallback na textový sloupec technik
    $stmtTechnici = $pdo->prepare("
        SELECT
            COALESCE(u.name, r.technik, '-') as technik,
            COUNT(*) as pocet,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) as celkem,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) * 0.33 as vydelek
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.assigned_to = u.id AND u.role = 'technik'
        $where
        GROUP BY COALESCE(u.name, r.technik, '-')
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmtTechnici->execute($params);
    $technici = $stmtTechnici->fetchAll(PDO::FETCH_ASSOC);

    // Zaokrouhlit částky
    foreach ($prodejci as &$p) {
        $p['celkem'] = round((float)$p['celkem'], 2);
    }
    foreach ($technici as &$t) {
        $t['celkem'] = round((float)$t['celkem'], 2);
        $t['vydelek'] = round((float)$t['vydelek'], 2);
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'modely' => $modely,
            'mesta' => $mesta,
            'prodejci' => $prodejci,
            'technici' => $technici
        ]
    ]);
}

/**
 * Sestavit WHERE klauzuli a parametry z filtrů
 * Podporuje multi-select (pole prodejců, techniků, zemí)
 */
function buildFilterWhere() {
    $conditions = [];
    $params = [];

    // Rok
    if (!empty($_GET['rok'])) {
        $conditions[] = "YEAR(r.created_at) = :rok";
        $params[':rok'] = (int)$_GET['rok'];
    }

    // Měsíc
    if (!empty($_GET['mesic'])) {
        $conditions[] = "MONTH(r.created_at) = :mesic";
        $params[':mesic'] = (int)$_GET['mesic'];
    }

    // Prodejci (multi-select) - může být pole
    if (!empty($_GET['prodejci'])) {
        $prodejci = is_array($_GET['prodejci']) ? $_GET['prodejci'] : [$_GET['prodejci']];

        $prodejciConditions = [];
        foreach ($prodejci as $idx => $prodejce) {
            if ($prodejce === 'mimozarucni') {
                // Mimozáruční = prázdné created_by (zákazník bez přihlášení)
                $prodejciConditions[] = "(r.created_by IS NULL OR r.created_by = '')";
            } else {
                $key = ":prodejce_$idx";
                $prodejciConditions[] = "r.created_by = $key";
                $params[$key] = $prodejce;  // VARCHAR, ne INT
            }
        }

        if (!empty($prodejciConditions)) {
            $conditions[] = "(" . implode(" OR ", $prodejciConditions) . ")";
        }
    }

    // Technici (multi-select) - může být pole
    // FIX: assigned_to může obsahovat buď numerické id nebo textové user_id
    // Používáme subquery pro nalezení obou hodnot
    if (!empty($_GET['technici'])) {
        $technici = is_array($_GET['technici']) ? $_GET['technici'] : [$_GET['technici']];

        $techniciConditions = [];
        foreach ($technici as $idx => $technik) {
            $keyId = ":technik_id_$idx";
            // Hledáme podle numerického id nebo přes subquery user_id
            $techniciConditions[] = "(r.assigned_to = $keyId OR r.assigned_to = (SELECT user_id FROM wgs_users WHERE id = $keyId LIMIT 1))";
            $params[$keyId] = (int)$technik;
        }

        if (!empty($techniciConditions)) {
            $conditions[] = "(" . implode(" OR ", $techniciConditions) . ")";
        }
    }

    // Země (multi-select) - může být pole
    if (!empty($_GET['zeme'])) {
        $zeme = is_array($_GET['zeme']) ? $_GET['zeme'] : [$_GET['zeme']];

        $zemeConditions = [];
        foreach ($zeme as $idx => $z) {
            $key = ":zeme_$idx";
            $zemeConditions[] = "UPPER(COALESCE(r.fakturace_firma, 'cz')) = $key";
            $params[$key] = strtoupper($z);
        }

        if (!empty($zemeConditions)) {
            $conditions[] = "(" . implode(" OR ", $zemeConditions) . ")";
        }
    }

    $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

    return [$where, $params];
}
