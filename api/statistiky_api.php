<?php
/**
 * Statistiky API
 * API pro načtení statistických dat z reklamací
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');
// ✅ PERFORMANCE: Cache-Control header (5 minut)
// Statistiky se nemění velmi často, můžeme cachovat
header('Cache-Control: private, max-age=300'); // 5 minut

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

    // Aktivní technici v období (počítáme registrované techniky z wgs_users)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) as active_techs
        FROM wgs_users u
        LEFT JOIN wgs_reklamace r ON r.zpracoval_id = u.id
        WHERE u.role = 'technik'
        AND (r.id IS NULL OR (1=1 $where))
    ");
    $stmt->execute($params);
    $activeTechs = (int)($stmt->fetch(PDO::FETCH_ASSOC)['active_techs'] ?? 0);

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
 * JOIN s wgs_users přes created_by (kdo zakázku vytvořil)
 */
function getSalespersonStats($pdo) {
    $filters = getFilters();
    $where = buildWhereClause($filters, 'r', true); // true = JOIN s users
    $params = buildParams($filters);

    // JOIN s wgs_users přes created_by - prodejce je ten, kdo zakázku vytvořil
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(u.name, 'Neuvedeno') as prodejce,
            COUNT(*) as pocet_zakazek,
            SUM(CAST(COALESCE(r.cena, 0) AS DECIMAL(10,2))) as celkova_castka,
            AVG(CAST(COALESCE(r.cena, 0) AS DECIMAL(10,2))) as prumer_zakazka,
            SUM(CASE WHEN r.fakturace_firma = 'cz' OR r.fakturace_firma = '' OR r.fakturace_firma IS NULL THEN 1 ELSE 0 END) as cz_count,
            SUM(CASE WHEN r.fakturace_firma = 'sk' THEN 1 ELSE 0 END) as sk_count,
            SUM(CASE WHEN r.stav = 'done' THEN 1 ELSE 0 END) as hotove_count
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.id
        $where
        GROUP BY u.name, u.id
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
 *
 * NOVÁ LOGIKA (2025-11-17):
 * - Zobrazuje POUZE registrované techniky z wgs_users (role='technik')
 * - JOIN s wgs_reklamace přes zpracoval_id
 * - Pokud není registrovaný žádný technik → prázdné statistiky
 * - Dynamické - přidá se nový technik → automaticky se zobrazí
 */
function getTechnicianStats($pdo) {
    try {
        $filters = getFilters();
        // WHERE podmínky pro reklamace (datum, země atd.)
        $whereConditions = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $whereConditions[] = "r.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "r.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['country'])) {
            $whereConditions[] = "r.fakturace_firma = :country";
            $params[':country'] = strtolower($filters['country']);
        }

        $whereClause = !empty($whereConditions) ? 'AND ' . implode(' AND ', $whereConditions) : '';

        // NOVÁ LOGIKA: JOIN s wgs_users WHERE role='technik'
        $sql = "
            SELECT
                u.id as user_id,
                u.user_id as user_code,
                u.name as technik,
                u.email,
                COUNT(r.id) as pocet_zakazek,
                COUNT(CASE WHEN r.stav = 'done' THEN 1 END) as pocet_dokonceno,
                SUM(CAST(COALESCE(r.cena, 0) AS DECIMAL(10,2))) as celkova_castka_dokonceno,
                SUM(CAST(COALESCE(r.cena, 0) AS DECIMAL(10,2))) as vydelek,
                AVG(CASE WHEN r.cena > 0 THEN CAST(r.cena AS DECIMAL(10,2)) END) as prumer_zakazka,
                SUM(CASE WHEN r.fakturace_firma = 'cz' OR r.fakturace_firma = '' OR r.fakturace_firma IS NULL THEN 1 ELSE 0 END) as cz_count,
                SUM(CASE WHEN r.fakturace_firma = 'sk' THEN 1 ELSE 0 END) as sk_count,
                SUM(CASE WHEN r.stav = 'done' THEN 1 ELSE 0 END) as hotove_count
            FROM wgs_users u
            LEFT JOIN wgs_reklamace r ON r.zpracoval_id = u.id $whereClause
            WHERE u.role = 'technik'
            GROUP BY u.id, u.user_id, u.name, u.email
            ORDER BY pocet_zakazek DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Vypočítat úspěšnost a zaokrouhlit hodnoty
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
    } catch (Exception $e) {
        error_log("getTechnicianStats error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Chyba při načítání statistik techniků: ' . $e->getMessage()
        ]);
    }
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
 * JOIN s wgs_users pro zobrazení jména prodejce
 */
function getFilteredOrders($pdo) {
    $filters = getFilters();
    $where = buildWhereClause($filters, 'r', true); // JOIN s users
    $params = buildParams($filters);

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.cislo,
            r.jmeno,
            COALESCE(prodejce.name, 'Neuvedeno') as prodejce,
            COALESCE(technik.name, '-') as technik,
            CAST(COALESCE(r.cena, 0) AS DECIMAL(10,2)) as castka,
            r.stav,
            CASE
                WHEN r.stav = 'wait' THEN 'ČEKÁ'
                WHEN r.stav = 'open' THEN 'DOMLUVENÁ'
                WHEN r.stav = 'done' THEN 'HOTOVO'
                ELSE r.stav
            END as stav_text,
            COALESCE(UPPER(r.fakturace_firma), 'CZ') as zeme,
            DATE_FORMAT(r.created_at, '%d.%m.%Y') as datum
        FROM wgs_reklamace r
        LEFT JOIN wgs_users prodejce ON r.created_by = prodejce.id
        LEFT JOIN wgs_users technik ON r.zpracoval_id = technik.id AND technik.role = 'technik'
        $where
        ORDER BY r.created_at DESC
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
 * Načítá VŠECHNY registrované aktivní uživatele z wgs_users
 */
function listSalespersons($pdo) {
    $stmt = $pdo->query("
        SELECT name as prodejce
        FROM wgs_users
        WHERE is_active = 1
        ORDER BY name ASC
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
 * @param array $filters Filtry
 * @param string $tableAlias Alias tabulky (např. 'r' pro reklamace)
 * @param bool $useUserJoin True pokud dotaz JOINuje s wgs_users
 */
function buildWhereClause($filters, $tableAlias = '', $useUserJoin = false) {
    $conditions = ['1=1'];
    $prefix = $tableAlias ? $tableAlias . '.' : '';

    if (!empty($filters['country'])) {
        // Převést na lowercase pro porovnání s fakturace_firma ENUM('cz', 'sk')
        $conditions[] = "LOWER({$prefix}fakturace_firma) = LOWER(:country)";
    }

    if (!empty($filters['status'])) {
        $conditions[] = "{$prefix}stav = :status";
    }

    if (!empty($filters['salesperson'])) {
        // Pokud máme JOIN s users, filtrujeme podle u.name
        if ($useUserJoin) {
            $conditions[] = "u.name = :salesperson";
        } else {
            // Jinak podle zpracoval (pro zpětnou kompatibilitu)
            $conditions[] = "{$prefix}zpracoval = :salesperson";
        }
    }

    if (!empty($filters['technician'])) {
        // Filtr podle ID nebo user_id technika z wgs_users
        // Frontend může poslat buď číselné ID nebo user_id string
        if (is_numeric($filters['technician'])) {
            // Číselné ID - přímé porovnání
            $conditions[] = "{$prefix}zpracoval_id = :technician_id";
        } else {
            // String user_id - subquery
            $conditions[] = "{$prefix}zpracoval_id IN (SELECT id FROM wgs_users WHERE user_id = :technician_id AND role = 'technik')";
        }
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = "DATE({$prefix}created_at) >= :date_from";
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = "DATE({$prefix}created_at) <= :date_to";
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
        $params[':technician_id'] = $filters['technician'];
    }

    if (!empty($filters['date_from'])) {
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $params[':date_to'] = $filters['date_to'];
    }

    return $params;
}
