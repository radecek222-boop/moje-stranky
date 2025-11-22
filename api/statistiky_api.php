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

$action = $_GET['action'] ?? '';

try {
    $pdo = Database::getInstance()->getConnection();

    switch ($action) {
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
    $stmtMonth = $pdo->prepare("SELECT COUNT(*) as count FROM wgs_reklamace $where");
    $stmtMonth->execute($params);
    $totalMonth = (int)($stmtMonth->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Částka v měsíci (filtrované)
    $stmtRevenueMonth = $pdo->prepare("
        SELECT SUM(CAST(COALESCE(cena_celkem, cena, 0) AS DECIMAL(10,2))) as total
        FROM wgs_reklamace
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
    $stmt = $pdo->query("
        SELECT DISTINCT u.id, u.name
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
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as count FROM wgs_reklamace $where");
    $stmtCount->execute($params);
    $totalCount = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Hlavní dotaz
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
            DATE_FORMAT(r.created_at, '%d.%m.%Y') as datum,
            r.created_at as datum_raw
        FROM wgs_reklamace r
        LEFT JOIN wgs_users prodejce ON r.created_by = prodejce.id
        LEFT JOIN wgs_users technik ON r.zpracoval_id = technik.id AND technik.role = 'technik'
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
            COALESCE(model, 'Neuvedeno') as model,
            COUNT(*) as pocet
        FROM wgs_reklamace
        $where
        GROUP BY model
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmtModely->execute($params);
    $modely = $stmtModely->fetchAll(PDO::FETCH_ASSOC);

    // 2. Lokality - extrahujeme město z adresy
    $stmtMesta = $pdo->prepare("
        SELECT
            TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(adresa, ',', -1), '\n', 1)) as mesto,
            COUNT(*) as pocet
        FROM wgs_reklamace
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
        LEFT JOIN wgs_users u ON r.created_by = u.id
        $where
        GROUP BY u.name, u.id
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmtProdejci->execute($params);
    $prodejci = $stmtProdejci->fetchAll(PDO::FETCH_ASSOC);

    // 4. Statistiky techniků
    $stmtTechnici = $pdo->prepare("
        SELECT
            COALESCE(u.name, '-') as technik,
            COUNT(*) as pocet,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) as celkem,
            SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) * 0.33 as vydelek
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.zpracoval_id = u.id AND u.role = 'technik'
        $where
        GROUP BY u.name, u.id
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
        $conditions[] = "YEAR(created_at) = :rok";
        $params[':rok'] = (int)$_GET['rok'];
    }

    // Měsíc
    if (!empty($_GET['mesic'])) {
        $conditions[] = "MONTH(created_at) = :mesic";
        $params[':mesic'] = (int)$_GET['mesic'];
    }

    // Prodejci (multi-select) - může být pole
    if (!empty($_GET['prodejci'])) {
        $prodejci = is_array($_GET['prodejci']) ? $_GET['prodejci'] : [$_GET['prodejci']];

        $prodejciConditions = [];
        foreach ($prodejci as $idx => $prodejce) {
            if ($prodejce === 'mimozarucni') {
                $prodejciConditions[] = "created_by IS NULL";
            } else {
                $key = ":prodejce_$idx";
                $prodejciConditions[] = "created_by = $key";
                $params[$key] = (int)$prodejce;
            }
        }

        if (!empty($prodejciConditions)) {
            $conditions[] = "(" . implode(" OR ", $prodejciConditions) . ")";
        }
    }

    // Technici (multi-select) - může být pole
    if (!empty($_GET['technici'])) {
        $technici = is_array($_GET['technici']) ? $_GET['technici'] : [$_GET['technici']];

        $techniciConditions = [];
        foreach ($technici as $idx => $technik) {
            $key = ":technik_$idx";
            $techniciConditions[] = "zpracoval_id = $key";
            $params[$key] = (int)$technik;
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
            $zemeConditions[] = "LOWER(fakturace_firma) = $key";
            $params[$key] = strtolower($z);
        }

        if (!empty($zemeConditions)) {
            $conditions[] = "(" . implode(" OR ", $zemeConditions) . ")";
        }
    }

    $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

    return [$where, $params];
}
