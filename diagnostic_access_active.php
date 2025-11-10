<?php
/**
 * AKTIVNÍ DIAGNOSTIKA: Testování a validace řízení přístupu
 * Real-time testování všech cest a detekce problémů
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Přístup odepřen');
}

$pdo = getDbConnection();

// ============================
// FUNKCE PRO TESTOVÁNÍ
// ============================

/**
 * Simuluje načtení reklamací pro danou roli
 */
function testRoleAccess($pdo, $role, $userId, $userEmail) {
    // Načti strukturu tabulky
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $whereParts = [];
    $params = [];

    // KOPIE LOGIKY Z load.php
    $userRole = strtolower(trim($role));
    $isProdejce = in_array($userRole, ['prodejce', 'user'], true);
    $isTechnik = in_array($userRole, ['technik', 'technician'], true);

    if ($isProdejce) {
        // PRODEJCE: Vidí pouze SVÉ reklamace
        if ($userId !== null && in_array('created_by', $columns)) {
            $whereParts[] = 'r.created_by = :created_by';
            $params[':created_by'] = $userId;
        } else {
            $whereParts[] = '1 = 0';
        }
    } elseif ($isTechnik) {
        // TECHNIK: Vidí VŠECHNY reklamace (žádný filtr)
    } else {
        // GUEST: Vidí pouze své (email match)
        $guestConditions = [];

        if ($userId !== null && in_array('created_by', $columns)) {
            $guestConditions[] = 'r.created_by = :created_by';
            $params[':created_by'] = $userId;
        }

        if ($userEmail && in_array('email', $columns)) {
            $guestConditions[] = 'LOWER(TRIM(r.email)) = LOWER(TRIM(:user_email))';
            $params[':user_email'] = $userEmail;
        }

        if (!empty($guestConditions)) {
            $whereParts[] = '(' . implode(' OR ', $guestConditions) . ')';
        } else {
            $whereParts[] = '1 = 0';
        }
    }

    $whereClause = '';
    if (!empty($whereParts)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
    }

    $sql = "SELECT COUNT(*) as total,
                   SUM(CASE WHEN created_by_role = 'guest' THEN 1 ELSE 0 END) as guest_claims,
                   SUM(CASE WHEN created_by_role = 'prodejce' THEN 1 ELSE 0 END) as prodejce_claims,
                   SUM(CASE WHEN created_by_role = 'technik' THEN 1 ELSE 0 END) as technik_claims
            FROM wgs_reklamace r
            $whereClause";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'sql' => $sql,
        'where' => $whereClause,
        'params' => $params,
        'results' => $result
    ];
}

/**
 * Validuje že klíčové soubory existují
 */
function validateFiles() {
    $files = [
        'app/controllers/login_controller.php' => 'Autentizace uživatelů',
        'app/controllers/load.php' => 'Načítání reklamací (KRITICKÝ)',
        'app/controllers/save.php' => 'Ukládání reklamací',
        'app/controllers/save_photos.php' => 'Ukládání fotek',
        'novareklamace.php' => 'Formulář pro vytvoření reklamace',
        'seznam.php' => 'Seznam reklamací',
        'assets/js/seznam.js' => 'JavaScript pro seznam',
        'api/get_photos_api.php' => 'API pro načítání fotek',
    ];

    $results = [];
    foreach ($files as $path => $description) {
        $fullPath = __DIR__ . '/' . $path;
        $exists = file_exists($fullPath);
        $results[] = [
            'path' => $path,
            'description' => $description,
            'exists' => $exists,
            'size' => $exists ? filesize($fullPath) : 0
        ];
    }

    return $results;
}

/**
 * Kontroluje databázovou strukturu
 */
function validateDatabase($pdo) {
    $results = [];

    // Kontrola tabulek
    $tables = ['wgs_reklamace', 'wgs_users', 'wgs_photos', 'wgs_documents'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        $results['tables'][$table] = $exists;
    }

    // Kontrola RBAC sloupců v wgs_reklamace
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $results['rbac_columns'] = [
        'created_by' => in_array('created_by', $columns),
        'created_by_role' => in_array('created_by_role', $columns),
        'zpracoval_id' => in_array('zpracoval_id', $columns),
    ];

    // Kontrola indexů
    $stmt = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = 'idx_created_by'");
    $results['indexes']['idx_created_by'] = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = 'idx_created_by_role'");
    $results['indexes']['idx_created_by_role'] = $stmt->rowCount() > 0;

    return $results;
}

// ============================
// SPUŠTĚNÍ DIAGNOSTIKY
// ============================

$diagnostics = [
    'files' => validateFiles(),
    'database' => validateDatabase($pdo),
    'users' => [],
    'role_tests' => []
];

// Načti uživatele
$stmt = $pdo->query("SELECT id, name, email, role FROM wgs_users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$diagnostics['users'] = $users;

// Zjisti celkový počet reklamací
$stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_reklamace");
$totalClaims = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Test pro každého uživatele
foreach ($users as $user) {
    $test = testRoleAccess($pdo, $user['role'], $user['id'], $user['email']);
    $diagnostics['role_tests'][] = [
        'user' => $user,
        'test' => $test
    ];
}

// Test pro guest (nepřihlášený)
$guestTest = testRoleAccess($pdo, 'guest', null, 'test@test.cz');
$diagnostics['role_tests'][] = [
    'user' => ['id' => null, 'name' => 'Guest (test)', 'email' => 'test@test.cz', 'role' => 'guest'],
    'test' => $guestTest
];

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivní diagnostika přístupů</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wgs-white: #FFFFFF;
            --wgs-black: #000000;
            --wgs-grey: #555555;
            --wgs-light-grey: #999999;
            --wgs-border: #E0E0E0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--wgs-white);
            color: var(--wgs-black);
            padding: 2rem;
        }

        .header {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid var(--wgs-black);
        }

        h1 {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--wgs-light-grey);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .section {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
            color: var(--wgs-black);
        }

        .status-ok { color: #4CAF50; font-weight: 600; }
        .status-error { color: #e74c3c; font-weight: 600; }
        .status-warning { color: #f57c00; font-weight: 600; }

        .test-box {
            background: #f8f8f8;
            border: 2px solid var(--wgs-border);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .test-box h3 {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
            color: var(--wgs-black);
        }

        .test-result {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 1rem;
            font-size: 0.8rem;
            margin: 0.5rem 0;
            padding: 0.5rem;
            background: var(--wgs-white);
            border: 1px solid var(--wgs-border);
        }

        .test-label {
            font-weight: 600;
            color: var(--wgs-grey);
        }

        .test-value {
            color: var(--wgs-black);
            font-family: monospace;
        }

        code {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 0.25rem 0.5rem;
            font-family: monospace;
            font-size: 0.7rem;
        }

        .sql-box {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 1rem;
            font-family: monospace;
            font-size: 0.7rem;
            overflow-x: auto;
            margin: 0.5rem 0;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid;
        }

        .badge-admin { background: var(--wgs-black); color: var(--wgs-white); border-color: var(--wgs-black); }
        .badge-prodejce { background: #e3f2fd; color: #1976d2; border-color: #1976d2; }
        .badge-technik { background: #fff3e0; color: #f57c00; border-color: #f57c00; }
        .badge-guest { background: #f3e5f5; color: #7b1fa2; border-color: #7b1fa2; }

        .back-link {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.75rem 1.5rem;
            background: var(--wgs-black);
            color: var(--wgs-white);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid var(--wgs-black);
            transition: all 0.3s;
        }

        .back-link:hover {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            margin: 1rem 0;
        }

        table th {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 0.75rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--wgs-border);
        }

        .summary-stat {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin: 1rem 0;
        }

        .stat-card {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 1rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--wgs-light-grey);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>AKTIVNÍ DIAGNOSTIKA PŘÍSTUPŮ</h1>
        <p class="subtitle">Real-time testování a validace všech cest systému</p>
    </div>

    <!-- SOUHRN -->
    <div class="section">
        <h2>SOUHRN DIAGNOSTIKY</h2>
        <div class="summary-stat">
            <div class="stat-card">
                <div class="stat-value"><?= $totalClaims ?></div>
                <div class="stat-label">Reklamace celkem</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($users) ?></div>
                <div class="stat-label">Uživatelé</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($diagnostics['files']) ?></div>
                <div class="stat-label">Klíčové soubory</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($diagnostics['role_tests']) ?></div>
                <div class="stat-label">Testů provedeno</div>
            </div>
        </div>
    </div>

    <!-- VALIDACE SOUBORŮ -->
    <div class="section">
        <h2>VALIDACE KLÍČOVÝCH SOUBORŮ</h2>
        <table>
            <thead>
                <tr>
                    <th>Soubor</th>
                    <th>Popis</th>
                    <th>Status</th>
                    <th>Velikost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($diagnostics['files'] as $file): ?>
                <tr>
                    <td><code><?= htmlspecialchars($file['path']) ?></code></td>
                    <td><?= htmlspecialchars($file['description']) ?></td>
                    <td class="<?= $file['exists'] ? 'status-ok' : 'status-error' ?>">
                        <?= $file['exists'] ? '✓ EXISTUJE' : '✗ CHYBÍ' ?>
                    </td>
                    <td><?= $file['exists'] ? number_format($file['size'] / 1024, 1) . ' KB' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- VALIDACE DATABÁZE -->
    <div class="section">
        <h2>VALIDACE DATABÁZOVÉ STRUKTURY</h2>

        <h3 style="font-size: 0.85rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">Tabulky:</h3>
        <?php foreach ($diagnostics['database']['tables'] as $table => $exists): ?>
        <div class="test-result">
            <div class="test-label"><?= htmlspecialchars($table) ?></div>
            <div class="test-value <?= $exists ? 'status-ok' : 'status-error' ?>">
                <?= $exists ? '✓ EXISTUJE' : '✗ CHYBÍ' ?>
            </div>
        </div>
        <?php endforeach; ?>

        <h3 style="font-size: 0.85rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">RBAC Sloupce (wgs_reklamace):</h3>
        <?php foreach ($diagnostics['database']['rbac_columns'] as $column => $exists): ?>
        <div class="test-result">
            <div class="test-label"><?= htmlspecialchars($column) ?></div>
            <div class="test-value <?= $exists ? 'status-ok' : 'status-error' ?>">
                <?= $exists ? '✓ EXISTUJE' : '✗ CHYBÍ - RBAC NENÍ NAINSTALOVÁN!' ?>
            </div>
        </div>
        <?php endforeach; ?>

        <h3 style="font-size: 0.85rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-black);">Indexy:</h3>
        <?php foreach ($diagnostics['database']['indexes'] as $index => $exists): ?>
        <div class="test-result">
            <div class="test-label"><?= htmlspecialchars($index) ?></div>
            <div class="test-value <?= $exists ? 'status-ok' : 'status-warning' ?>">
                <?= $exists ? '✓ EXISTUJE' : '⚠ CHYBÍ (nepovinné, ale doporučené)' ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- TESTY PŘÍSTUPŮ PRO KAŽDOU ROLI -->
    <div class="section">
        <h2>SIMULACE PŘÍSTUPŮ - REÁLNÉ TESTY</h2>
        <p style="font-size: 0.85rem; color: var(--wgs-grey); margin-bottom: 1.5rem;">
            Každý test simuluje přihlášení uživatele a zobrazuje kolik reklamací by viděl v seznam.php.
            SQL query je identické s load.php.
        </p>

        <?php foreach ($diagnostics['role_tests'] as $roleTest):
            $user = $roleTest['user'];
            $test = $roleTest['test'];
            $results = $test['results'];

            $roleClass = 'badge-guest';
            $role = strtolower($user['role']);
            if ($role === 'admin') $roleClass = 'badge-admin';
            elseif ($role === 'prodejce') $roleClass = 'badge-prodejce';
            elseif ($role === 'technik') $roleClass = 'badge-technik';
        ?>
        <div class="test-box">
            <h3>
                <span class="badge <?= $roleClass ?>"><?= htmlspecialchars(strtoupper($user['role'])) ?></span>
                <?= htmlspecialchars($user['name']) ?>
                (ID: <?= $user['id'] ?? 'NULL' ?>, Email: <?= htmlspecialchars($user['email']) ?>)
            </h3>

            <div class="test-result">
                <div class="test-label">Viditelné reklamace:</div>
                <div class="test-value status-ok">
                    <strong><?= $results['total'] ?></strong> z <?= $totalClaims ?> celkem
                </div>
            </div>

            <div class="test-result">
                <div class="test-label">Z toho od zákazníků:</div>
                <div class="test-value"><?= $results['guest_claims'] ?? 0 ?></div>
            </div>

            <div class="test-result">
                <div class="test-label">Z toho od prodejců:</div>
                <div class="test-value"><?= $results['prodejce_claims'] ?? 0 ?></div>
            </div>

            <div class="test-result">
                <div class="test-label">Z toho od techniků:</div>
                <div class="test-value"><?= $results['technik_claims'] ?? 0 ?></div>
            </div>

            <?php if (!empty($test['where'])): ?>
            <h4 style="font-size: 0.75rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-grey); text-transform: uppercase;">SQL WHERE klauzule:</h4>
            <div class="sql-box"><?= htmlspecialchars($test['where']) ?></div>
            <?php else: ?>
            <div style="padding: 1rem; background: #e8f5e9; border: 2px solid #4CAF50; margin-top: 1rem;">
                <strong style="color: #2e7d32;">✓ ŽÁDNÝ FILTR</strong> - Vidí všechny reklamace
            </div>
            <?php endif; ?>

            <?php if (!empty($test['params'])): ?>
            <h4 style="font-size: 0.75rem; margin: 1rem 0 0.5rem 0; color: var(--wgs-grey); text-transform: uppercase;">SQL parametry:</h4>
            <div class="sql-box"><?= htmlspecialchars(print_r($test['params'], true)) ?></div>
            <?php endif; ?>

            <!-- VALIDACE -->
            <?php
            $expectedBehavior = '';
            $isCorrect = false;

            if ($role === 'admin') {
                $expectedBehavior = "Admin by měl vidět VŠECHNY reklamace ($totalClaims)";
                $isCorrect = ($results['total'] == $totalClaims);
            } elseif ($role === 'technik') {
                $expectedBehavior = "Technik by měl vidět VŠECHNY reklamace ($totalClaims)";
                $isCorrect = ($results['total'] == $totalClaims);
            } elseif ($role === 'prodejce') {
                $expectedBehavior = "Prodejce by měl vidět POUZE SVÉ reklamace (created_by = {$user['id']})";
                $isCorrect = true; // Nemůžeme automaticky validovat správný počet
            } else {
                $expectedBehavior = "Guest by měl vidět pouze své reklamace (filtr podle emailu)";
                $isCorrect = true;
            }
            ?>

            <div style="padding: 1rem; background: <?= $isCorrect ? '#e8f5e9' : '#fff3e0' ?>; border: 2px solid <?= $isCorrect ? '#4CAF50' : '#f57c00' ?>; margin-top: 1rem;">
                <strong style="color: <?= $isCorrect ? '#2e7d32' : '#e65100' ?>;">
                    <?= $isCorrect ? '✓' : '⚠' ?> <?= htmlspecialchars($expectedBehavior) ?>
                </strong>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- DETEKCE PROBLÉMŮ -->
    <div class="section">
        <h2>DETEKCE PROBLÉMŮ</h2>
        <?php
        $problems = [];

        // Kontrola RBAC
        if (!$diagnostics['database']['rbac_columns']['created_by']) {
            $problems[] = [
                'severity' => 'KRITICKÉ',
                'message' => 'Sloupec created_by neexistuje! RBAC systém není nainstalován.',
                'solution' => 'Jdi do admin.php?tab=tools a spusť instalaci Role-Based Access.'
            ];
        }

        // Kontrola souborů
        foreach ($diagnostics['files'] as $file) {
            if (!$file['exists']) {
                $problems[] = [
                    'severity' => 'KRITICKÉ',
                    'message' => "Soubor {$file['path']} neexistuje!",
                    'solution' => 'Zkontroluj strukturu projektu.'
                ];
            }
        }

        // Kontrola že technik vidí všechny reklamace
        foreach ($diagnostics['role_tests'] as $roleTest) {
            if ($roleTest['user']['role'] === 'technik') {
                if ($roleTest['test']['results']['total'] != $totalClaims) {
                    $problems[] = [
                        'severity' => 'VAROVÁNÍ',
                        'message' => "Technik {$roleTest['user']['name']} vidí pouze {$roleTest['test']['results']['total']} z $totalClaims reklamací!",
                        'solution' => 'Technik by měl vidět všechny reklamace. Zkontroluj logiku v load.php.'
                    ];
                }
            }
        }

        if (empty($problems)):
        ?>
        <div style="padding: 2rem; background: #e8f5e9; border: 4px solid #4CAF50; text-align: center;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">✓</div>
            <h3 style="font-size: 1.2rem; color: #2e7d32; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
                SYSTÉM FUNGUJE SPRÁVNĚ
            </h3>
            <p style="color: #555; font-size: 0.9rem;">
                Všechny testy prošly úspěšně. Řízení přístupu je správně nakonfigurováno.
            </p>
        </div>
        <?php else: ?>
        <?php foreach ($problems as $problem): ?>
        <div style="padding: 1.5rem; background: <?= $problem['severity'] === 'KRITICKÉ' ? '#ffebee' : '#fff3e0' ?>; border: 4px solid <?= $problem['severity'] === 'KRITICKÉ' ? '#e74c3c' : '#f57c00' ?>; margin-bottom: 1rem;">
            <h3 style="font-size: 0.9rem; color: <?= $problem['severity'] === 'KRITICKÉ' ? '#c62828' : '#e65100' ?>; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
                <?= $problem['severity'] === 'KRITICKÉ' ? '✗' : '⚠' ?> <?= htmlspecialchars($problem['severity']) ?>
            </h3>
            <p style="color: #333; margin-bottom: 0.75rem; font-size: 0.85rem;">
                <strong>Problém:</strong> <?= htmlspecialchars($problem['message']) ?>
            </p>
            <p style="color: #555; font-size: 0.8rem;">
                <strong>Řešení:</strong> <?= htmlspecialchars($problem['solution']) ?>
            </p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="/admin.php?tab=tools" class="back-link">← ZPĚT NA NÁSTROJE</a>

    <div style="margin-top: 2rem; text-align: center; color: var(--wgs-light-grey); font-size: 0.8rem;">
        <small>WGS SERVICE - AKTIVNÍ DIAGNOSTIKA © 2025 | Vygenerováno: <?= date('d.m.Y H:i:s') ?></small>
    </div>
</body>
</html>
