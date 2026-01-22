<?php
/**
 * Statistiky API - NOVÁ VERZE 2.0
 * API pro reporty a vyúčtování
 */

// DEBUG: Zobrazit skutečnou chybu
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "status" => "error",
        "debug_error" => true,
        "message" => $e->getMessage(),
        "file" => basename($e->getFile()),
        "line" => $e->getLine(),
        "trace" => $e->getTraceAsString()
    ]);
    exit;
});

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

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

// Rozlišit podle HTTP metody
$action = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? ($_POST['action'] ?? '')
    : ($_GET['action'] ?? '');

try {
    $pdo = getDbConnection();

    // Zjistit zda existuje sloupec dokonceno_kym
    // FIX: rowCount() nefunguje pro SELECT/SHOW v PDO - použít fetch() místo toho
    $stmtCol = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'dokonceno_kym'");
    $GLOBALS['hasDokoncenokym'] = ($stmtCol->fetch() !== false);

    // Zjistit zda existuje sloupec datum_dokonceni
    $stmtCol2 = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'datum_dokonceni'");
    $GLOBALS['hasDatumDokonceni'] = ($stmtCol2->fetch() !== false);

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

        case 'detail_zakazky':
            getDetailZakazky($pdo);
            break;

        case 'seznam_techniku':
            getSeznamTechniku($pdo);
            break;

        case 'seznam_prodejcu':
            getSeznamProdejcu($pdo);
            break;

        case 'upravit_zakazku':
            upravitZakazku($pdo);
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
    // FIX: Použít sloupec cena_celkem (zde se ukládá částka z protokolu)
    $stmtRevenueAll = $pdo->query("
        SELECT SUM(CAST(COALESCE(cena_celkem, 0) AS DECIMAL(10,2))) as total
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
    // FIX: Použít sloupec cena_celkem (zde se ukládá částka z protokolu)
    $stmtRevenueMonth = $pdo->prepare("
        SELECT SUM(CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2))) as total
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
 * POZN: "Mimozáruční servis" je nyní samostatný checkbox, ne v tomto filtru
 * Pouze uživatelé s rolí 'prodejce' (ne technici, ne admin)
 */
function loadProdejci($pdo) {
    // FIX: Vracet user_id jako id, protože created_by v reklamacích obsahuje user_id
    $stmt = $pdo->query("
        SELECT DISTINCT u.user_id as id, u.name
        FROM wgs_users u
        WHERE u.is_active = 1 AND u.role = 'prodejce'
        ORDER BY u.name ASC
    ");

    $prodejci = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    // Technici používají numerické id (assigned_to ukládá numerické id)
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
    // Technik: priorita dokonceno_kym (kdo dokončil), pak assigned_to, pak textový sloupec technik
    $hasDokoncenokym = $GLOBALS['hasDokoncenokym'] ?? false;

    // JOIN podmínka pro technika - podle dokonceno_kym nebo assigned_to
    $technikJoin = $hasDokoncenokym
        ? "LEFT JOIN wgs_users technik ON (r.dokonceno_kym = technik.id OR (r.dokonceno_kym IS NULL AND r.assigned_to = technik.id)) AND technik.role = 'technik'"
        : "LEFT JOIN wgs_users technik ON r.assigned_to = technik.id AND technik.role = 'technik'";

    // Použít datum_dokonceni pokud existuje
    $hasDatumDokonceni = $GLOBALS['hasDatumDokonceni'] ?? false;
    $datumSloupec = $hasDatumDokonceni ? 'COALESCE(r.datum_dokonceni, r.created_at)' : 'r.created_at';

    $sql = "
        SELECT
            r.id,
            r.cislo as cislo_reklamace,
            r.jmeno as jmeno_zakaznika,
            r.adresa,
            r.model,
            r.assigned_to as assigned_to_raw,
            " . ($hasDokoncenokym ? "r.dokonceno_kym as dokonceno_kym_raw," : "") . "
            CASE
                WHEN r.stav = 'done' THEN COALESCE(technik.name, r.technik, '-')
                ELSE '-'
            END as technik,
            COALESCE(prodejce.name, 'Mimozáruční servis') as prodejce,
            CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) as castka_celkem,
            CASE
                WHEN r.stav = 'done' THEN CAST(COALESCE(r.cena_celkem, 0) * (COALESCE(technik.provize_procent, 33) / 100) AS DECIMAL(10,2))
                ELSE 0.00
            END as vydelek_technika,
            UPPER(COALESCE(r.fakturace_firma, 'cz')) as zeme,
            DATE_FORMAT({$datumSloupec}, '%d.%m.%Y') as datum,
            {$datumSloupec} as datum_raw
        FROM wgs_reklamace r
        LEFT JOIN wgs_users prodejce ON r.created_by = prodejce.user_id
        $technikJoin
        $where
        ORDER BY {$datumSloupec} DESC
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
            SUM(CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2))) as celkem
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        $where
        GROUP BY u.name, u.user_id
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmtProdejci->execute($params);
    $prodejci = $stmtProdejci->fetchAll(PDO::FETCH_ASSOC);

    // 4. Statistiky techniků - ROZDĚLENO NA REKLAMACE A POZ
    // Technik: priorita dokonceno_kym (kdo dokončil), pak assigned_to, pak textový sloupec technik
    $hasDokoncenokym = $GLOBALS['hasDokoncenokym'] ?? false;

    // JOIN podmínka pro technika - podle dokonceno_kym nebo assigned_to
    $technikJoinChart = $hasDokoncenokym
        ? "LEFT JOIN wgs_users u ON (r.dokonceno_kym = u.id OR (r.dokonceno_kym IS NULL AND r.assigned_to = u.id)) AND u.role = 'technik'"
        : "LEFT JOIN wgs_users u ON r.assigned_to = u.id AND u.role = 'technik'";

    // 4a. REKLAMACE (s prodejcem - created_by vyplněno) - individuální provize
    $stmtTechniciReklamace = $pdo->prepare("
        SELECT
            CASE
                WHEN r.stav = 'done' THEN COALESCE(u.name, r.technik, '-')
                ELSE '-'
            END as technik,
            COUNT(CASE WHEN r.stav = 'done' THEN 1 END) as pocet,
            SUM(CASE WHEN r.stav = 'done' THEN CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) ELSE 0 END) as celkem,
            SUM(CASE WHEN r.stav = 'done' THEN CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_procent, 33) / 100) ELSE 0 END) as vydelek
        FROM wgs_reklamace r
        $technikJoinChart
        $where
        AND (r.created_by IS NOT NULL AND r.created_by != '')
        GROUP BY CASE WHEN r.stav = 'done' THEN COALESCE(u.name, r.technik, '-') ELSE '-' END
        HAVING COUNT(CASE WHEN r.stav = 'done' THEN 1 END) > 0
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmtTechniciReklamace->execute($params);
    $techniciReklamace = $stmtTechniciReklamace->fetchAll(PDO::FETCH_ASSOC);

    // 4b. POZ (bez prodejce - created_by prázdné) - individuální provize POZ
    $stmtTechniciPoz = $pdo->prepare("
        SELECT
            CASE
                WHEN r.stav = 'done' THEN COALESCE(u.name, r.technik, '-')
                ELSE '-'
            END as technik,
            COUNT(CASE WHEN r.stav = 'done' THEN 1 END) as pocet,
            SUM(CASE WHEN r.stav = 'done' THEN CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) ELSE 0 END) as celkem,
            SUM(CASE WHEN r.stav = 'done' THEN CAST(COALESCE(r.cena_celkem, 0) AS DECIMAL(10,2)) * (COALESCE(u.provize_poz_procent, 50) / 100) ELSE 0 END) as vydelek
        FROM wgs_reklamace r
        $technikJoinChart
        $where
        AND (r.created_by IS NULL OR r.created_by = '')
        GROUP BY CASE WHEN r.stav = 'done' THEN COALESCE(u.name, r.technik, '-') ELSE '-' END
        HAVING COUNT(CASE WHEN r.stav = 'done' THEN 1 END) > 0
        ORDER BY pocet DESC
        LIMIT 10
    ");
    $stmtTechniciPoz->execute($params);
    $techniciPoz = $stmtTechniciPoz->fetchAll(PDO::FETCH_ASSOC);

    // Zaokrouhlit částky
    foreach ($prodejci as &$p) {
        $p['celkem'] = round((float)$p['celkem'], 2);
    }
    foreach ($techniciReklamace as &$t) {
        $t['celkem'] = round((float)$t['celkem'], 2);
        $t['vydelek'] = round((float)$t['vydelek'], 2);
    }
    foreach ($techniciPoz as &$t) {
        $t['celkem'] = round((float)$t['celkem'], 2);
        $t['vydelek'] = round((float)$t['vydelek'], 2);
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'modely' => $modely,
            'mesta' => $mesta,
            'prodejci' => $prodejci,
            'techniciReklamace' => $techniciReklamace,
            'techniciPoz' => $techniciPoz
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

    // Použít datum_dokonceni pokud existuje, jinak created_at
    $hasDatumDokonceni = $GLOBALS['hasDatumDokonceni'] ?? false;
    $datumSloupec = $hasDatumDokonceni ? 'COALESCE(r.datum_dokonceni, r.created_at)' : 'r.created_at';

    // Rok - podle data dokončení
    if (!empty($_GET['rok'])) {
        $conditions[] = "YEAR({$datumSloupec}) = :rok";
        $params[':rok'] = (int)$_GET['rok'];
    }

    // Měsíc - podle data dokončení
    if (!empty($_GET['mesic'])) {
        $conditions[] = "MONTH({$datumSloupec}) = :mesic";
        $params[':mesic'] = (int)$_GET['mesic'];
    }

    // Checkbox "Zobrazit mimozáruční servisy"
    // Pokud NENÍ zaškrtnutý, vyfiltrovat záznamy s prázdným created_by
    $zobrazitMimozarucni = isset($_GET['zobrazit_mimozarucni']) && $_GET['zobrazit_mimozarucni'] === '1';
    if (!$zobrazitMimozarucni) {
        $conditions[] = "(r.created_by IS NOT NULL AND r.created_by != '')";
    }

    // Checkbox "Zobrazit pouze dokončené"
    // Pokud je zaškrtnutý, zobrazit pouze záznamy se stavem 'done'
    $pouzeDokoncene = isset($_GET['pouze_dokoncene']) && $_GET['pouze_dokoncene'] === '1';
    if ($pouzeDokoncene) {
        $conditions[] = "r.stav = 'done'";
    }

    // Prodejci (multi-select) - může být pole
    if (!empty($_GET['prodejci'])) {
        $prodejci = is_array($_GET['prodejci']) ? $_GET['prodejci'] : [$_GET['prodejci']];

        $prodejciConditions = [];
        foreach ($prodejci as $idx => $prodejce) {
            $key = ":prodejce_$idx";
            $prodejciConditions[] = "r.created_by = $key";
            $params[$key] = $prodejce;  // VARCHAR, ne INT
        }

        if (!empty($prodejciConditions)) {
            $conditions[] = "(" . implode(" OR ", $prodejciConditions) . ")";
        }
    }

    // Technici (multi-select) - může být pole
    // FIX 2025-01-08: Každý parametr musí mít unikátní jméno (PDO neumožňuje opakování)
    if (!empty($_GET['technici'])) {
        $technici = is_array($_GET['technici']) ? $_GET['technici'] : [$_GET['technici']];
        $hasDokoncenokym = $GLOBALS['hasDokoncenokym'] ?? false;

        $techniciConditions = [];
        foreach ($technici as $idx => $technik) {
            $technikId = (int)$technik;

            // PDO vyžaduje unikátní jména parametrů - nelze použít stejný parametr 2x
            if ($hasDokoncenokym) {
                $keyDok = ":technik_dok_$idx";
                $keyAss = ":technik_ass_$idx";
                $params[$keyDok] = $technikId;
                $params[$keyAss] = $technikId;
                $techniciConditions[] = "(r.dokonceno_kym = $keyDok OR (r.dokonceno_kym IS NULL AND r.assigned_to = $keyAss))";
            } else {
                $keyAss = ":technik_ass_$idx";
                $params[$keyAss] = $technikId;
                $techniciConditions[] = "r.assigned_to = $keyAss";
            }
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

/**
 * Detail zakázky pro editaci
 */
function getDetailZakazky($pdo) {
    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Chybí ID zakázky']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.cislo as reklamace_id,
            r.jmeno as jmeno_zakaznika,
            r.adresa,
            r.model,
            r.assigned_to,
            r.created_by,
            r.fakturace_firma as faktura_zeme
        FROM wgs_reklamace r
        WHERE r.id = :id
    ");

    $stmt->execute(['id' => $id]);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakazka) {
        echo json_encode(['status' => 'error', 'message' => 'Zakázka nenalezena']);
        return;
    }

    echo json_encode([
        'status' => 'success',
        'zakazka' => $zakazka
    ]);
}

/**
 * Seznam techniků pro select
 * DŮLEŽITÉ: assigned_to je INT(11), musíme vracet numeric id!
 */
function getSeznamTechniku($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, email, user_id
        FROM wgs_users
        WHERE role LIKE '%technik%' OR role LIKE '%technician%'
        ORDER BY name ASC
    ");

    $technici = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'technici' => $technici
    ]);
}

/**
 * Seznam prodejců pro select
 * POZNÁMKA: created_by je VARCHAR(50), můžeme použít user_id
 */
function getSeznamProdejcu($pdo) {
    $stmt = $pdo->query("
        SELECT id, user_id, name, email
        FROM wgs_users
        WHERE role = 'prodejce'
        ORDER BY name ASC
    ");

    $prodejci = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'prodejci' => $prodejci
    ]);
}

/**
 * Upravit zakázku - změnit technika, prodejce, zemi
 */
function upravitZakazku($pdo) {
    // CSRF kontrola
    require_once __DIR__ . '/../includes/csrf_helper.php';
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']);
        return;
    }

    $id = $_POST['id'] ?? '';
    $assignedTo = $_POST['assigned_to'] ?? null;
    $createdBy = $_POST['created_by'] ?? null;
    $fakturaZeme = $_POST['faktura_zeme'] ?? 'CZ';

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Chybí ID zakázky']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Zjistit zda existuje sloupec dokonceno_kym
        $hasDokoncenokym = $GLOBALS['hasDokoncenokym'] ?? false;

        // UPDATE zakázky - pokud existuje dokonceno_kym, updatovat i ten
        if ($hasDokoncenokym) {
            $stmt = $pdo->prepare("
                UPDATE wgs_reklamace
                SET
                    assigned_to = :assigned_to,
                    dokonceno_kym = :assigned_to,
                    created_by = :created_by,
                    fakturace_firma = :faktura_zeme,
                    updated_at = NOW()
                WHERE id = :id
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE wgs_reklamace
                SET
                    assigned_to = :assigned_to,
                    created_by = :created_by,
                    fakturace_firma = :faktura_zeme,
                    updated_at = NOW()
                WHERE id = :id
            ");
        }

        $stmt->execute([
            'assigned_to' => $assignedTo ?: null,
            'created_by' => $createdBy ?: null,
            'faktura_zeme' => $fakturaZeme,
            'id' => $id
        ]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Zakázka úspěšně upravena'
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Chyba při úpravě zakázky: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Chyba při ukládání změn'
        ]);
    }
}
