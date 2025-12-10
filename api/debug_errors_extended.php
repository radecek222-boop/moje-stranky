<?php
/**
 * Rozšířená diagnostika: Error logy podle uživatelů
 *
 * Zobrazuje chyby seskupené podle:
 * - Typu uživatele (prodejce, technik, admin)
 * - Typu chyby (SQL, PHP, JS)
 * - Času výskytu
 *
 * URL: /api/debug_errors_extended.php
 */

require_once __DIR__ . '/../init.php';

// Bezpečnost - pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    die(json_encode(['error' => 'Přístup odepřen - pouze pro admina']));
}

header('Content-Type: application/json; charset=utf-8');

$dnesniDatum = date('Y-m-d');
$result = [
    'datum' => $dnesniDatum,
    'cas_kontroly' => date('H:i:s'),
    'chyby_podle_typu' => [
        'kriticke' => [],
        'sql_chyby' => [],
        'session_chyby' => [],
        'ostatni' => []
    ],
    'chyby_podle_uzivatele' => [
        'prodejce' => [],
        'technik' => [],
        'admin' => [],
        'neznamy' => []
    ],
    'opravene_chyby' => [],
    'neopravene_chyby' => []
];

// Načíst všechny uživatele pro identifikaci
$pdo = getDbConnection();
$uzivatele = [];
try {
    $stmt = $pdo->query("SELECT user_id, name, role FROM wgs_users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uzivatele[$row['user_id']] = [
            'name' => $row['name'],
            'role' => $row['role']
        ];
    }
} catch (Exception $e) {
    // Ignorovat
}

// Definice opravených chyb (po migraci 17:54:15)
$opraveneChybyPatterns = [
    'wgs_supervisor_assignments' => 'Tabulka vytvořena migrací',
    "r.prodejce" => 'SQL dotaz opraven - použito COALESCE(u.name)',
    'Data truncated for column \'stav\'' => 'Mapování stav opraveno (HOTOVO -> done)',
    'Incorrect integer value.*user_id' => 'Sloupec user_id změněn na VARCHAR(50)'
];

// Čas migrace
$casMigrace = strtotime('2025-12-10 17:54:15');

// PHP Error Log
$phpLogPath = __DIR__ . '/../logs/php_errors.log';
if (file_exists($phpLogPath)) {
    $logContent = file_get_contents($phpLogPath);
    $lines = explode("\n", $logContent);

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        // Filtrovat pouze dnešní záznamy
        if (strpos($line, $dnesniDatum) === false &&
            strpos($line, date('d-M-Y')) === false &&
            !preg_match('/\[' . date('d') . '-[A-Za-z]+-' . date('Y') . '/', $line)) {
            continue;
        }

        // Extrahovat čas z logu
        $casChyby = null;
        if (preg_match('/\[(\d{2}-\w+-\d{4}\s+(\d{2}:\d{2}:\d{2}))/', $line, $matches)) {
            $casChyby = strtotime($matches[1]);
        }

        // Určit typ chyby
        $typChyby = 'ostatni';
        if (stripos($line, 'SQLSTATE') !== false || stripos($line, 'SQL') !== false) {
            $typChyby = 'sql_chyby';
        } elseif (stripos($line, 'Fatal') !== false || stripos($line, 'Error:') !== false) {
            $typChyby = 'kriticke';
        } elseif (stripos($line, 'SESSION') !== false || stripos($line, 'session') !== false) {
            $typChyby = 'session_chyby';
        }

        // Určit uživatele
        $typUzivatele = 'neznamy';
        if (preg_match('/PRO\d+/', $line)) {
            $typUzivatele = 'prodejce';
        } elseif (preg_match('/TCH\d+/', $line)) {
            $typUzivatele = 'technik';
        } elseif (preg_match('/ADMIN\d+/', $line) || stripos($line, 'admin') !== false) {
            $typUzivatele = 'admin';
        }

        // Zkontrolovat jestli je chyba opravená
        $jeOpravena = false;
        $duvodOpravy = null;
        foreach ($opraveneChybyPatterns as $pattern => $duvod) {
            if (stripos($line, $pattern) !== false || preg_match('/' . $pattern . '/i', $line)) {
                $jeOpravena = true;
                $duvodOpravy = $duvod;
                break;
            }
        }

        // Pokud chyba nastala PO migraci, není opravená
        if ($casChyby && $casChyby > $casMigrace) {
            $jeOpravena = false;
            $duvodOpravy = null;
        }

        // Přeskočit debug/info logy (ne chyby)
        $jeDebugLog = (
            stripos($line, 'Cache MISS') !== false ||
            stripos($line, 'Cache HIT') !== false ||
            stripos($line, 'Cached geocoding') !== false ||
            stripos($line, 'Option:') !== false ||
            stripos($line, 'DEBUG') !== false ||
            stripos($line, 'Email send:') !== false ||
            stripos($line, 'PDF report uložen') !== false
        );

        if ($jeDebugLog) {
            continue; // Přeskočit informační logy
        }

        $chybaZaznam = [
            'cas' => $casChyby ? date('H:i:s', $casChyby) : 'neznámý',
            'zprava' => substr($line, 0, 300),
            'typ_uzivatele' => $typUzivatele,
            'pred_migraci' => $casChyby && $casChyby < $casMigrace
        ];

        if ($jeOpravena) {
            $chybaZaznam['oprava'] = $duvodOpravy;
            $result['opravene_chyby'][] = $chybaZaznam;
        } else {
            $result['neopravene_chyby'][] = $chybaZaznam;
        }

        // Přidat do kategorií
        $result['chyby_podle_typu'][$typChyby][] = $chybaZaznam;
        $result['chyby_podle_uzivatele'][$typUzivatele][] = $chybaZaznam;
    }
}

// Statistiky
$result['statistiky'] = [
    'celkem_chyb' => count($result['opravene_chyby']) + count($result['neopravene_chyby']),
    'opraveno' => count($result['opravene_chyby']),
    'neopraveno' => count($result['neopravene_chyby']),
    'cas_migrace' => date('Y-m-d H:i:s', $casMigrace),
    'chyby_po_migraci' => count(array_filter($result['neopravene_chyby'], function($ch) use ($casMigrace) {
        return !$ch['pred_migraci'];
    })),
    'podle_typu' => [
        'kriticke' => count($result['chyby_podle_typu']['kriticke']),
        'sql' => count($result['chyby_podle_typu']['sql_chyby']),
        'session' => count($result['chyby_podle_typu']['session_chyby']),
        'ostatni' => count($result['chyby_podle_typu']['ostatni'])
    ],
    'podle_uzivatele' => [
        'prodejce' => count($result['chyby_podle_uzivatele']['prodejce']),
        'technik' => count($result['chyby_podle_uzivatele']['technik']),
        'admin' => count($result['chyby_podle_uzivatele']['admin']),
        'neznamy' => count($result['chyby_podle_uzivatele']['neznamy'])
    ]
];

// Stav systému
if ($result['statistiky']['chyby_po_migraci'] === 0) {
    $result['stav'] = 'OK - Všechny známé chyby byly opraveny';
} elseif ($result['statistiky']['neopraveno'] < 5) {
    $result['stav'] = 'VAROVÁNÍ - Málo neopravených chyb';
} else {
    $result['stav'] = 'CHYBA - Zjištěny nové chyby po migraci';
}

// Seznam oprav provedených
$result['provedene_opravy'] = [
    [
        'cas' => '2025-12-10 17:54:15',
        'popis' => 'Migrace: oprav_chyby_z_logu.php',
        'opravy' => [
            'wgs_pageviews.user_id změněn na VARCHAR(50)',
            'Vytvořena tabulka wgs_supervisor_assignments'
        ]
    ],
    [
        'cas' => '2025-12-10 17:52:00',
        'popis' => 'Opravy v kódu',
        'opravy' => [
            'save.php: stav = "done" místo "HOTOVO"',
            'protokol_api.php: COALESCE(u.name, "Neznámý")',
            'admin_api.php: COALESCE(u.name, "Neznámý")',
            'track_pageview.php: CREATE TABLE s VARCHAR(50) user_id'
        ]
    ]
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
