<?php
/**
 * Diagnostika: Test filtrování na frontendu
 *
 * Tento endpoint simuluje přesně to, co dostane JavaScript
 * a ukazuje, proč může filtrování selhat.
 *
 * Spustit jako přihlášený prodejce:
 * https://www.wgs-service.cz/api/debug_frontend_filter.php
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Nepřihlášen - přihlaste se prosím'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDbConnection();

// 1. Co PHP nastavuje do CURRENT_USER (stejná logika jako v seznam.php)
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

$supervisedUserIds = [];
if ($currentUserId && !$isAdmin) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $idCol = in_array('user_id', $columns) ? 'user_id' : 'id';
        $numericIdCol = 'id';

        $currentNumericId = $currentUserId;
        if (!is_numeric($currentUserId)) {
            $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE user_id = :user_id LIMIT 1");
            $stmt->execute([':user_id' => $currentUserId]);
            $numericId = $stmt->fetchColumn();
            if ($numericId) {
                $currentNumericId = $numericId;
            }
        }

        $stmt = $pdo->prepare("
            SELECT u.{$idCol}
            FROM wgs_supervisor_assignments sa
            JOIN wgs_users u ON u.{$numericIdCol} = sa.salesperson_user_id
            WHERE sa.supervisor_user_id = :user_id
        ");
        $stmt->execute([':user_id' => $currentNumericId]);
        $supervisedUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Ignorovat chyby s tabulkou
    }
}

$currentUserData = [
    "id" => $currentUserId,
    "name" => $_SESSION['user_name'] ?? "Admin",
    "email" => $_SESSION['user_email'] ?? "",
    "phone" => $_SESSION['user_phone'] ?? "",
    "role" => $_SESSION['role'] ?? "admin",
    "is_admin" => $isAdmin,
    "supervised_user_ids" => $supervisedUserIds
];

// 2. Co load.php vrací (prvních 10 záznamů)
$userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));
$isProdejce = in_array($userRole, ['prodejce', 'user'], true);

$whereParts = [];
$params = [];

if (!$isAdmin && $isProdejce) {
    $whereParts[] = 'r.created_by = :created_by';
    $params[':created_by'] = $currentUserId;
}

$whereClause = '';
if (!empty($whereParts)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
}

$sql = "SELECT r.id, r.reklamace_id, r.jmeno, r.created_by, r.stav FROM wgs_reklamace r $whereClause LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loadPhpData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Simulace JavaScript filterByUserRole
$jsFilterResults = [];
foreach ($loadPhpData as $record) {
    $createdBy = (string)($record['created_by'] ?? '');
    $myId = (string)$currentUserId;

    $match = ($createdBy === $myId);

    $jsFilterResults[] = [
        'reklamace_id' => $record['reklamace_id'],
        'created_by' => $createdBy,
        'myId' => $myId,
        'comparison' => "'{$createdBy}' === '{$myId}'",
        'result' => $match ? 'ZOBRAZÍ SE' : 'SKRYJE SE',
        'reason' => $match ? 'created_by se shoduje s user_id' : 'NESHODUJE SE!'
    ];
}

// 4. Typ kontrola
$typeCheck = [
    'currentUserId_type' => gettype($currentUserId),
    'currentUserId_value' => $currentUserId,
    'session_user_id_raw' => $_SESSION['user_id'] ?? 'NENÍ NASTAVENO',
    'is_numeric' => is_numeric($currentUserId)
];

// 5. Kontrola sloupců v tabulce wgs_reklamace
require_once __DIR__ . '/../includes/db_metadata.php';
$tableColumns = db_get_table_columns($pdo, 'wgs_reklamace');
$hasCreatedByColumn = in_array('created_by', $tableColumns, true);

// 6. Test skutečného API volání load.php
// Simulujeme co by load.php vrátil - použijeme curl interně nebo přímé volání
$loadPhpTest = [
    'columns_in_table' => $tableColumns,
    'has_created_by' => $hasCreatedByColumn,
    'sql_used' => $sql,
    'params_used' => $params
];

// Výstup
echo json_encode([
    'krok_1_current_user_pro_js' => [
        'popis' => 'Toto PHP předává do JavaScriptu jako CURRENT_USER',
        'data' => $currentUserData
    ],
    'krok_2_load_php_vraci' => [
        'popis' => 'Toto load.php vrací (prvních 10 záznamů)',
        'sql' => $sql,
        'params' => $params,
        'pocet' => count($loadPhpData),
        'data' => $loadPhpData
    ],
    'krok_3_js_filter_simulace' => [
        'popis' => 'Simulace JavaScript Utils.filterByUserRole()',
        'logika' => "if (createdBy === myId) return true;",
        'vysledky' => $jsFilterResults
    ],
    'krok_4_typ_kontrola' => $typeCheck,
    'krok_5_sloupce_tabulky' => $loadPhpTest,
    'ZAVER' => [
        'backend_vraci_zaznamy' => count($loadPhpData),
        'po_js_filtru_projde' => count(array_filter($jsFilterResults, fn($x) => $x['result'] === 'ZOBRAZÍ SE')),
        'has_created_by_column' => $hasCreatedByColumn,
        'problem' => count($loadPhpData) > 0 && count(array_filter($jsFilterResults, fn($x) => $x['result'] === 'ZOBRAZÍ SE')) === 0
            ? 'NALEZEN: Backend vrací data, ale JS filtr je všechny odfiltruje!'
            : (count($loadPhpData) === 0 ? 'Backend nevrací žádná data - zkontrolujte SQL dotaz' : 'Data by se měla zobrazit')
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
