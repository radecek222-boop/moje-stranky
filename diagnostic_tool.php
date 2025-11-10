<?php
/**
 * UNIVERZÁLNÍ DIAGNOSTICKÝ NÁSTROJ
 * AI testovací prostředí pro WGS Service
 * Umožňuje spouštět PHP kód, SQL dotazy, testovat cesty, SESSION, atd.
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin
// Kontrola PŘED simulací - kontroluj původní admin status nebo aktuální
$isRealAdmin = (isset($_SESSION['_original_admin_diagnostic']['is_admin']) && $_SESSION['_original_admin_diagnostic']['is_admin'] === true)
    || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

if (!$isRealAdmin) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins; padding: 40px; text-align: center;"><h1>Přístup odepřen</h1><p>Pouze admin může používat diagnostické nástroje.</p><p><a href="/login">Přihlásit se jako admin</a></p></body></html>');
}

$tab = $_GET['tab'] ?? 'sql';
$autorun = $_GET['autorun'] ?? false;
$generateReport = $_GET['report'] ?? false;

// ADMIN ROLE SIMULATION
// Uložit původní admin session
if (!isset($_SESSION['_original_admin_diagnostic'])) {
    $_SESSION['_original_admin_diagnostic'] = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'is_admin' => $_SESSION['is_admin'] ?? null,
        'name' => $_SESSION['name'] ?? null,
    ];
}

// Akce: Simulovat roli nebo uživatele
$simulateAction = $_GET['simulate'] ?? null;
if ($simulateAction === 'reset') {
    // Reset na původní admin session
    $original = $_SESSION['_original_admin_diagnostic'];
    $_SESSION['user_id'] = $original['user_id'];
    $_SESSION['email'] = $original['email'];
    $_SESSION['role'] = $original['role'];
    $_SESSION['is_admin'] = $original['is_admin'];
    $_SESSION['name'] = $original['name'];
    unset($_SESSION['_simulating_diagnostic']);
    header('Location: diagnostic_tool.php?tab=simulate');
    exit;
} elseif ($simulateAction === 'role') {
    $roleToSimulate = $_GET['role'] ?? null;

    switch ($roleToSimulate) {
        case 'admin':
            $_SESSION['user_id'] = 1;
            $_SESSION['email'] = 'admin@wgs-service.cz';
            $_SESSION['role'] = 'admin';
            $_SESSION['is_admin'] = true;
            $_SESSION['name'] = 'Admin (SIMULACE)';
            $_SESSION['_simulating_diagnostic'] = 'admin';
            break;

        case 'prodejce':
            $_SESSION['user_id'] = 7;
            $_SESSION['email'] = 'naty@naty.cz';
            $_SESSION['role'] = 'prodejce';
            $_SESSION['is_admin'] = false;
            $_SESSION['name'] = 'Naty Prodejce (SIMULACE)';
            $_SESSION['_simulating_diagnostic'] = 'prodejce';
            break;

        case 'technik':
            $_SESSION['user_id'] = 15;
            $_SESSION['email'] = 'milan@technik.cz';
            $_SESSION['role'] = 'technik';
            $_SESSION['is_admin'] = false;
            $_SESSION['name'] = 'Milan Technik (SIMULACE)';
            $_SESSION['_simulating_diagnostic'] = 'technik';
            break;

        case 'guest':
            $_SESSION['user_id'] = null;
            $_SESSION['email'] = 'jiri@novacek.cz';
            $_SESSION['role'] = 'guest';
            $_SESSION['is_admin'] = false;
            $_SESSION['name'] = 'Jiří Nováček (SIMULACE)';
            $_SESSION['_simulating_diagnostic'] = 'guest';
            break;
    }

    header('Location: diagnostic_tool.php?tab=simulate');
    exit;
} elseif ($simulateAction === 'user') {
    // Simulovat konkrétního uživatele z databáze
    $userId = $_GET['user_id'] ?? null;
    if ($userId) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT id, email, role, name FROM wgs_users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                $_SESSION['is_admin'] = false;
                $_SESSION['name'] = ($user['name'] ?? $user['email']) . ' (SIMULACE)';
                $_SESSION['_simulating_diagnostic'] = 'user:' . $user['id'];
            }
        } catch (Exception $e) {
            // Ignore
        }
    }
    header('Location: diagnostic_tool.php?tab=simulate');
    exit;
}

$currentSimulation = $_SESSION['_simulating_diagnostic'] ?? null;

// Zpracování akcí
$result = null;
$error = null;
$executionTime = 0;

// Generování reportu
if ($generateReport) {
    $reportData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
        'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
        'loaded_extensions' => get_loaded_extensions(),
        'diagnostics' => []
    ];

    try {
        $pdo = getDbConnection();

        // Diagnostika databáze
        $reportData['diagnostics'][] = [
            'category' => 'Database',
            'test' => 'Connection',
            'status' => 'OK',
            'details' => $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)
        ];

        // Tabulky
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $reportData['diagnostics'][] = [
            'category' => 'Database',
            'test' => 'Tables',
            'status' => 'OK',
            'details' => implode(', ', $tables)
        ];

        // Kontrola wgs_reklamace
        if (in_array('wgs_reklamace', $tables)) {
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $reportData['diagnostics'][] = [
                'category' => 'Database',
                'test' => 'wgs_reklamace columns',
                'status' => 'OK',
                'details' => implode(', ', $columns)
            ];

            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM wgs_reklamace");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            $reportData['diagnostics'][] = [
                'category' => 'Database',
                'test' => 'wgs_reklamace count',
                'status' => 'OK',
                'details' => $count['cnt'] . ' rows'
            ];
        }

        // Kontrola wgs_users
        if (in_array('wgs_users', $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM wgs_users");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            $reportData['diagnostics'][] = [
                'category' => 'Database',
                'test' => 'wgs_users count',
                'status' => 'OK',
                'details' => $count['cnt'] . ' users'
            ];
        }
    } catch (Exception $e) {
        $reportData['diagnostics'][] = [
            'category' => 'Database',
            'test' => 'Connection',
            'status' => 'ERROR',
            'details' => $e->getMessage()
        ];
    }

    // Kontrola souborů
    $criticalFiles = [
        'init.php' => __DIR__ . '/init.php',
        'load.php' => __DIR__ . '/app/controllers/load.php',
        'save.php' => __DIR__ . '/app/controllers/save.php',
        '.env' => __DIR__ . '/.env',
        'admin.php' => __DIR__ . '/admin.php'
    ];

    foreach ($criticalFiles as $name => $path) {
        $reportData['diagnostics'][] = [
            'category' => 'Files',
            'test' => $name,
            'status' => file_exists($path) ? 'OK' : 'ERROR',
            'details' => file_exists($path) ?
                'Exists, ' . filesize($path) . ' bytes, ' . substr(sprintf('%o', fileperms($path)), -4) :
                'File not found'
        ];
    }

    // PHP Extensions
    $requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json', 'mbstring', 'curl'];
    foreach ($requiredExtensions as $ext) {
        $reportData['diagnostics'][] = [
            'category' => 'PHP Extensions',
            'test' => $ext,
            'status' => extension_loaded($ext) ? 'OK' : 'ERROR',
            'details' => extension_loaded($ext) ? 'Loaded' : 'Not loaded'
        ];
    }

    // SESSION data
    $reportData['session'] = [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'is_admin' => $_SESSION['is_admin'] ?? null
    ];

    // Generuj textový report
    $reportText = "WGS SERVICE - DIAGNOSTICKÝ REPORT\n";
    $reportText .= "=====================================\n\n";
    $reportText .= "Vygenerováno: " . $reportData['timestamp'] . "\n";
    $reportText .= "PHP Verze: " . $reportData['php_version'] . "\n";
    $reportText .= "Server: " . $reportData['server_software'] . "\n";
    $reportText .= "Document Root: " . $reportData['document_root'] . "\n";
    $reportText .= "Session Status: " . $reportData['session_status'] . "\n\n";

    $reportText .= "SESSION DATA:\n";
    $reportText .= "-------------\n";
    foreach ($reportData['session'] as $key => $value) {
        $reportText .= "  {$key}: " . ($value ?? 'NULL') . "\n";
    }
    $reportText .= "\n";

    $reportText .= "DIAGNOSTIKA:\n";
    $reportText .= "------------\n";
    foreach ($reportData['diagnostics'] as $diag) {
        $reportText .= "[{$diag['status']}] {$diag['category']} > {$diag['test']}\n";
        $reportText .= "      {$diag['details']}\n";
    }
    $reportText .= "\n";

    $reportText .= "PHP EXTENSIONS:\n";
    $reportText .= "---------------\n";
    foreach ($reportData['loaded_extensions'] as $ext) {
        $reportText .= "  - {$ext}\n";
    }

    // Download report
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="wgs-diagnostic-report-' . date('Y-m-d-His') . '.txt"');
    echo $reportText;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    try {
        $startTime = microtime(true);

        switch ($action) {
            case 'sql':
                $sql = $_POST['sql'] ?? '';
                if (empty($sql)) {
                    throw new Exception('SQL dotaz je prázdný');
                }

                $pdo = getDbConnection();
                $stmt = $pdo->query($sql);

                // Zjisti typ dotazu
                $sqlUpper = strtoupper(trim($sql));
                if (strpos($sqlUpper, 'SELECT') === 0 || strpos($sqlUpper, 'SHOW') === 0 || strpos($sqlUpper, 'DESCRIBE') === 0) {
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $result = ['affected_rows' => $stmt->rowCount(), 'message' => 'Dotaz byl úspěšně proveden'];
                }
                break;

            case 'php':
                $code = $_POST['php_code'] ?? '';
                if (empty($code)) {
                    throw new Exception('PHP kód je prázdný');
                }

                // Bezpečnostní kontrola
                $forbidden = ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open'];
                foreach ($forbidden as $func) {
                    if (stripos($code, $func) !== false) {
                        throw new Exception("Zakázaná funkce: {$func}");
                    }
                }

                ob_start();
                $evalResult = eval($code);
                $output = ob_get_clean();

                $result = [
                    'output' => $output,
                    'return_value' => $evalResult,
                    'code_executed' => $code
                ];
                break;

            case 'path':
                $path = $_POST['path'] ?? '';
                if (empty($path)) {
                    throw new Exception('Cesta je prázdná');
                }

                $result = [
                    'path' => $path,
                    'exists' => file_exists($path),
                    'is_file' => is_file($path),
                    'is_dir' => is_dir($path),
                    'is_readable' => is_readable($path),
                    'is_writable' => is_writable($path),
                    'size' => file_exists($path) && is_file($path) ? filesize($path) : null,
                    'permissions' => file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : null,
                    'realpath' => realpath($path)
                ];

                if (is_dir($path)) {
                    $result['contents'] = scandir($path);
                }
                break;

            case 'session':
                $result = [
                    'session_id' => session_id(),
                    'session_data' => $_SESSION,
                    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
                    'cookie_params' => session_get_cookie_params()
                ];
                break;

            case 'env':
                $result = [
                    'get' => $_GET,
                    'post' => $_POST,
                    'server' => $_SERVER,
                    'cookie' => $_COOKIE
                ];
                break;
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

    } catch (Exception $e) {
        $error = $e->getMessage();
        $executionTime = (microtime(true) - $startTime) * 1000;
    }
}

// Auto-diagnostika
$autodiagnostics = null;
if ($autorun) {
    $autodiagnostics = [];

    try {
        $pdo = getDbConnection();

        // Test databázového připojení
        $autodiagnostics[] = [
            'test' => 'Databázové připojení',
            'status' => 'OK',
            'message' => 'Připojeno k databázi: ' . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)
        ];

        // Test tabulky wgs_reklamace
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_reklamace'");
        if ($stmt->rowCount() > 0) {
            $autodiagnostics[] = ['test' => 'Tabulka wgs_reklamace', 'status' => 'OK', 'message' => 'Tabulka existuje'];

            // Kontrola sloupců
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $requiredColumns = ['id', 'created_by', 'created_by_role', 'zpracoval_id'];
            foreach ($requiredColumns as $col) {
                if (in_array($col, $columns)) {
                    $autodiagnostics[] = ['test' => "Sloupec {$col}", 'status' => 'OK', 'message' => 'Existuje'];
                } else {
                    $autodiagnostics[] = ['test' => "Sloupec {$col}", 'status' => 'CHYBA', 'message' => 'Neexistuje'];
                }
            }
        } else {
            $autodiagnostics[] = ['test' => 'Tabulka wgs_reklamace', 'status' => 'CHYBA', 'message' => 'Tabulka neexistuje'];
        }

        // Test SESSION
        $autodiagnostics[] = [
            'test' => 'PHP SESSION',
            'status' => session_status() === PHP_SESSION_ACTIVE ? 'OK' : 'CHYBA',
            'message' => 'Session ID: ' . session_id()
        ];

        // Test souborů
        $criticalFiles = [
            'init.php' => __DIR__ . '/init.php',
            'load.php' => __DIR__ . '/app/controllers/load.php',
            'save.php' => __DIR__ . '/app/controllers/save.php',
            '.env' => __DIR__ . '/.env'
        ];

        foreach ($criticalFiles as $name => $path) {
            if (file_exists($path)) {
                $autodiagnostics[] = ['test' => "Soubor {$name}", 'status' => 'OK', 'message' => 'Existuje'];
            } else {
                $autodiagnostics[] = ['test' => "Soubor {$name}", 'status' => 'CHYBA', 'message' => 'Neexistuje'];
            }
        }

        // Test PHP extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'session', 'json'];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                $autodiagnostics[] = ['test' => "PHP extension {$ext}", 'status' => 'OK', 'message' => 'Načteno'];
            } else {
                $autodiagnostics[] = ['test' => "PHP extension {$ext}", 'status' => 'CHYBA', 'message' => 'Není načteno'];
            }
        }

        // Test práv
        $testPath = __DIR__ . '/test_write_' . time() . '.tmp';
        if (@file_put_contents($testPath, 'test')) {
            @unlink($testPath);
            $autodiagnostics[] = ['test' => 'Práva zápisu', 'status' => 'OK', 'message' => 'Lze zapisovat do hlavního adresáře'];
        } else {
            $autodiagnostics[] = ['test' => 'Práva zápisu', 'status' => 'VAROVÁNÍ', 'message' => 'Nelze zapisovat do hlavního adresáře'];
        }

    } catch (Exception $e) {
        $autodiagnostics[] = ['test' => 'Auto-diagnostika', 'status' => 'CHYBA', 'message' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostický nástroj - WGS Service</title>
    <link rel="stylesheet" href="assets/css/styles.min.css">
    <style>
        :root {
            --wgs-white: #FFFFFF;
            --wgs-black: #000000;
            --wgs-dark-grey: #222222;
            --wgs-grey: #555555;
            --wgs-light-grey: #999999;
            --wgs-border: #E0E0E0;
        }

        body {
            background: var(--wgs-white);
            color: var(--wgs-black);
            font-family: 'Poppins', sans-serif;
            padding: 0;
            margin: 0;
        }

        .diagnostic-header {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 2rem;
            border-bottom: 2px solid var(--wgs-black);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .diagnostic-header-content h1 {
            color: var(--wgs-white);
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .diagnostic-header-content p {
            color: var(--wgs-light-grey);
            margin: 0;
            font-size: 0.9rem;
        }

        .btn-download-report {
            background: var(--wgs-white);
            color: var(--wgs-black);
            border: 2px solid var(--wgs-white);
            padding: 0.8rem 2rem;
            font-family: 'Poppins', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-download-report:hover {
            background: var(--wgs-light-grey);
            border-color: var(--wgs-light-grey);
        }

        .diagnostic-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--wgs-border);
        }

        .tab {
            padding: 1rem 1.5rem;
            background: var(--wgs-white);
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--wgs-grey);
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--wgs-black);
            background: #f8f8f8;
        }

        .tab.active {
            color: var(--wgs-black);
            border-bottom-color: var(--wgs-black);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .editor-group {
            margin-bottom: 2rem;
        }

        .editor-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--wgs-grey);
            margin-bottom: 0.5rem;
        }

        textarea, .code-editor {
            width: 100%;
            min-height: 200px;
            padding: 1rem;
            border: 1px solid var(--wgs-border);
            background: var(--wgs-white);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--wgs-black);
            resize: vertical;
        }

        textarea:focus, .code-editor:focus {
            outline: none;
            border-color: var(--wgs-black);
            box-shadow: 0 0 0 1px var(--wgs-black);
        }

        .btn-execute {
            background: var(--wgs-black);
            color: var(--wgs-white);
            border: 2px solid var(--wgs-black);
            padding: 0.875rem 1.5rem;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s;
            white-space: normal;
            line-height: 1.4;
        }

        .btn-execute:hover {
            background: var(--wgs-white);
            color: var(--wgs-black);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .btn-secondary {
            background: var(--wgs-white);
            color: var(--wgs-grey);
            border: 2px solid var(--wgs-border);
        }

        .btn-secondary:hover {
            background: #f8f8f8;
            border-color: var(--wgs-grey);
            color: var(--wgs-black);
        }

        .result-box {
            margin-top: 2rem;
            padding: 1.5rem;
            border: 1px solid var(--wgs-border);
            background: var(--wgs-white);
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--wgs-border);
        }

        .result-title {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--wgs-black);
        }

        .execution-time {
            font-size: 0.75rem;
            color: var(--wgs-grey);
        }

        .error-box {
            background: #f8f8f8;
            border-left: 4px solid var(--wgs-black);
            padding: 1rem;
            margin-top: 1rem;
        }

        .error-box p {
            color: var(--wgs-black);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        thead {
            background: var(--wgs-white);
            border-bottom: 2px solid var(--wgs-black);
        }

        th {
            padding: 0.75rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--wgs-black);
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--wgs-border);
            font-size: 0.85rem;
            color: var(--wgs-dark-grey);
        }

        tr:hover {
            background: #f8f8f8;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        pre {
            background: #f8f8f8;
            padding: 1rem;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: var(--wgs-dark-grey);
            border-left: 4px solid var(--wgs-border);
        }

        .diagnostic-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--wgs-border);
        }

        .diagnostic-item:last-child {
            border-bottom: none;
        }

        .diagnostic-item:hover {
            background: #f8f8f8;
        }

        .diagnostic-name {
            font-weight: 600;
            color: var(--wgs-black);
            font-size: 0.9rem;
        }

        .diagnostic-status {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border: 1px solid var(--wgs-border);
        }

        .diagnostic-status.ok {
            background: var(--wgs-black);
            color: var(--wgs-white);
            border-color: var(--wgs-black);
        }

        .diagnostic-status.error {
            background: var(--wgs-white);
            color: var(--wgs-black);
            border-color: var(--wgs-black);
        }

        .diagnostic-status.warning {
            background: var(--wgs-grey);
            color: var(--wgs-white);
            border-color: var(--wgs-grey);
        }

        .diagnostic-message {
            color: var(--wgs-grey);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            padding: 1rem;
            border: 1px solid var(--wgs-border);
            background: var(--wgs-white);
            cursor: pointer;
            transition: all 0.3s;
        }

        .quick-action:hover {
            background: #f8f8f8;
            border-color: var(--wgs-black);
        }

        .quick-action-title {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--wgs-black);
            margin-bottom: 0.5rem;
        }

        .quick-action-desc {
            font-size: 0.85rem;
            color: var(--wgs-grey);
        }

        .back-link {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.5rem 0;
            color: var(--wgs-grey);
            text-decoration: none;
            font-size: 0.85rem;
            border-bottom: 1px solid transparent;
            transition: all 0.3s;
        }

        .back-link:hover {
            color: var(--wgs-black);
            border-bottom-color: var(--wgs-black);
        }
    </style>
</head>
<body>
    <div class="diagnostic-header">
        <div class="diagnostic-header-content">
            <h1>DIAGNOSTICKÝ NÁSTROJ</h1>
            <p>AI testovací prostředí pro WGS Service - PHP, SQL, Cesty, Session, Auto-diagnostika</p>
            <?php if ($currentSimulation): ?>
                <div style="margin-top: 0.75rem; padding: 0.5rem 1rem; background: #fff3e0; color: #000; border-left: 4px solid #f57c00; font-size: 0.85rem; font-weight: 600;">
                    SIMULACE AKTIVNÍ: <?= htmlspecialchars($_SESSION['name'] ?? $_SESSION['email'] ?? 'neznámý') ?> &nbsp;|&nbsp; <a href="?simulate=reset" style="color: #000; text-decoration: underline;">Reset na Admin</a>
                </div>
            <?php endif; ?>
        </div>
        <a href="?report=1" class="btn-download-report">STÁHNOUT REPORT</a>
    </div>

    <div class="diagnostic-container">
        <div class="tabs">
            <button class="tab <?= $tab === 'sql' ? 'active' : '' ?>" onclick="window.location.href='?tab=sql'">
                SQL DOTAZY
            </button>
            <button class="tab <?= $tab === 'php' ? 'active' : '' ?>" onclick="window.location.href='?tab=php'">
                PHP KÓD
            </button>
            <button class="tab <?= $tab === 'path' ? 'active' : '' ?>" onclick="window.location.href='?tab=path'">
                CESTY & SOUBORY
            </button>
            <button class="tab <?= $tab === 'session' ? 'active' : '' ?>" onclick="window.location.href='?tab=session'">
                SESSION
            </button>
            <button class="tab <?= $tab === 'env' ? 'active' : '' ?>" onclick="window.location.href='?tab=env'">
                PROMĚNNÉ
            </button>
            <button class="tab <?= $tab === 'auto' ? 'active' : '' ?>" onclick="window.location.href='?tab=auto&autorun=1'">
                AUTO-DIAGNOSTIKA
            </button>
            <button class="tab <?= $tab === 'simulate' ? 'active' : '' ?>" onclick="window.location.href='?tab=simulate'">
                SIMULACE ROLÍ
            </button>
        </div>

        <!-- SQL TAB -->
        <div class="tab-content <?= $tab === 'sql' ? 'active' : '' ?>">
            <h2 style="margin-bottom: 1rem; font-size: 1.5rem;">SQL DOTAZY</h2>

            <div class="quick-actions">
                <div class="quick-action" onclick="document.getElementById('sql').value='SHOW TABLES;'; document.forms[0].submit();">
                    <div class="quick-action-title">Zobrazit tabulky</div>
                    <div class="quick-action-desc">SHOW TABLES</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('sql').value='SELECT * FROM wgs_reklamace LIMIT 10;'; document.forms[0].submit();">
                    <div class="quick-action-title">První 10 reklamací</div>
                    <div class="quick-action-desc">SELECT * FROM wgs_reklamace</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('sql').value='SHOW COLUMNS FROM wgs_reklamace;'; document.forms[0].submit();">
                    <div class="quick-action-title">Struktura tabulky</div>
                    <div class="quick-action-desc">SHOW COLUMNS</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('sql').value='SELECT * FROM wgs_users;'; document.forms[0].submit();">
                    <div class="quick-action-title">Všichni uživatelé</div>
                    <div class="quick-action-desc">SELECT * FROM wgs_users</div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="sql">
                <div class="editor-group">
                    <label class="editor-label" for="sql">SQL DOTAZ</label>
                    <textarea name="sql" id="sql" placeholder="SELECT * FROM wgs_reklamace WHERE id = 1;"><?= isset($_POST['sql']) ? htmlspecialchars($_POST['sql']) : '' ?></textarea>
                </div>
                <button type="submit" class="btn-execute">SPUSTIT SQL</button>
                <button type="button" class="btn-execute btn-secondary" onclick="document.getElementById('sql').value=''; window.location.href='?tab=sql';">VYMAZAT</button>
            </form>
        </div>

        <!-- PHP TAB -->
        <div class="tab-content <?= $tab === 'php' ? 'active' : '' ?>">
            <h2 style="margin-bottom: 1rem; font-size: 1.5rem;">PHP KÓD</h2>

            <div class="quick-actions">
                <div class="quick-action" onclick="document.getElementById('php_code').value='print_r($_SESSION);';">
                    <div class="quick-action-title">Zobrazit SESSION</div>
                    <div class="quick-action-desc">print_r($_SESSION)</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('php_code').value='phpinfo();';">
                    <div class="quick-action-title">PHP Info</div>
                    <div class="quick-action-desc">phpinfo()</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('php_code').value='echo date(\'Y-m-d H:i:s\');';">
                    <div class="quick-action-title">Aktuální čas</div>
                    <div class="quick-action-desc">date()</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('php_code').value='print_r(get_defined_constants(true)[\'user\']);';">
                    <div class="quick-action-title">User konstanty</div>
                    <div class="quick-action-desc">get_defined_constants()</div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="php">
                <div class="editor-group">
                    <label class="editor-label" for="php_code">PHP KÓD (bez &lt;?php ?&gt;)</label>
                    <textarea name="php_code" id="php_code" placeholder="echo 'Hello World';"><?= isset($_POST['php_code']) ? htmlspecialchars($_POST['php_code']) : '' ?></textarea>
                </div>
                <button type="submit" class="btn-execute">SPUSTIT PHP</button>
                <button type="button" class="btn-execute btn-secondary" onclick="document.getElementById('php_code').value=''; window.location.href='?tab=php';">VYMAZAT</button>
            </form>
        </div>

        <!-- PATH TAB -->
        <div class="tab-content <?= $tab === 'path' ? 'active' : '' ?>">
            <h2 style="margin-bottom: 1rem; font-size: 1.5rem;">CESTY & SOUBORY</h2>

            <div class="quick-actions">
                <div class="quick-action" onclick="document.getElementById('path').value='<?= __DIR__ ?>/init.php'; document.forms[0].submit();">
                    <div class="quick-action-title">init.php</div>
                    <div class="quick-action-desc">Hlavní inicializační soubor</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('path').value='<?= __DIR__ ?>/app/controllers'; document.forms[0].submit();">
                    <div class="quick-action-title">Controllers</div>
                    <div class="quick-action-desc">Adresář kontrolerů</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('path').value='<?= __DIR__ ?>/.env'; document.forms[0].submit();">
                    <div class="quick-action-title">.env soubor</div>
                    <div class="quick-action-desc">Konfigurační soubor</div>
                </div>
                <div class="quick-action" onclick="document.getElementById('path').value='<?= __DIR__ ?>/assets'; document.forms[0].submit();">
                    <div class="quick-action-title">Assets</div>
                    <div class="quick-action-desc">Adresář assets</div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="path">
                <div class="editor-group">
                    <label class="editor-label" for="path">CESTA K SOUBORU NEBO ADRESÁŘI</label>
                    <textarea name="path" id="path" style="min-height: 80px;" placeholder="/home/user/moje-stranky/init.php"><?= isset($_POST['path']) ? htmlspecialchars($_POST['path']) : '' ?></textarea>
                </div>
                <button type="submit" class="btn-execute">TESTOVAT CESTU</button>
                <button type="button" class="btn-execute btn-secondary" onclick="document.getElementById('path').value=''; window.location.href='?tab=path';">VYMAZAT</button>
            </form>
        </div>

        <!-- SESSION TAB -->
        <div class="tab-content <?= $tab === 'session' ? 'active' : '' ?>">
            <h2 style="margin-bottom: 1rem; font-size: 1.5rem;">SESSION DATA</h2>
            <p style="color: var(--wgs-grey); margin-bottom: 2rem;">Zobrazení aktuálních SESSION dat a cookie parametrů</p>

            <form method="POST">
                <input type="hidden" name="action" value="session">
                <button type="submit" class="btn-execute">NAČÍST SESSION DATA</button>
            </form>
        </div>

        <!-- ENV TAB -->
        <div class="tab-content <?= $tab === 'env' ? 'active' : '' ?>">
            <h2 style="margin-bottom: 1rem; font-size: 1.5rem;">PROMĚNNÉ PROSTŘEDÍ</h2>
            <p style="color: var(--wgs-grey); margin-bottom: 2rem;">GET, POST, SERVER, COOKIE proměnné</p>

            <form method="POST">
                <input type="hidden" name="action" value="env">
                <button type="submit" class="btn-execute">NAČÍST PROMĚNNÉ</button>
            </form>
        </div>

        <!-- AUTO-DIAGNOSTIKA TAB -->
        <div class="tab-content <?= $tab === 'auto' ? 'active' : '' ?>">
            <h2 style="margin-bottom: 1rem; font-size: 1.5rem;">AUTO-DIAGNOSTIKA</h2>
            <p style="color: var(--wgs-grey); margin-bottom: 2rem;">Automatická kontrola systému - databáze, soubory, práva, PHP extensions</p>

            <?php if ($autodiagnostics): ?>
                <div class="result-box">
                    <div class="result-header">
                        <span class="result-title">VÝSLEDKY DIAGNOSTIKY</span>
                        <span class="execution-time"><?= count($autodiagnostics) ?> testů</span>
                    </div>

                    <?php foreach ($autodiagnostics as $diag): ?>
                        <div class="diagnostic-item">
                            <div>
                                <div class="diagnostic-name"><?= htmlspecialchars($diag['test']) ?></div>
                                <div class="diagnostic-message"><?= htmlspecialchars($diag['message']) ?></div>
                            </div>
                            <span class="diagnostic-status <?= strtolower($diag['status']) === 'ok' ? 'ok' : (strtolower($diag['status']) === 'chyba' ? 'error' : 'warning') ?>">
                                <?= htmlspecialchars($diag['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <a href="?tab=auto&autorun=1" class="btn-execute" style="display: inline-block; text-decoration: none;">SPUSTIT AUTO-DIAGNOSTIKU</a>
            <?php endif; ?>
        </div>

        <!-- SIMULACE ROLÍ TAB -->
        <div class="tab-content <?= $tab === 'simulate' ? 'active' : '' ?>">
            <h2 style="margin-bottom: 1rem; font-size: 1.5rem;">SIMULACE ROLÍ A UŽIVATELŮ</h2>
            <p style="color: var(--wgs-grey); margin-bottom: 2rem;">Admin může testovat aplikaci z pohledu různých rolí a uživatelů bez přepínání účtů</p>

            <?php if ($currentSimulation): ?>
                <div style="background: #fff3e0; border-left: 4px solid #000; padding: 1rem; margin-bottom: 2rem;">
                    <strong style="color: #000;">AKTIVNÍ SIMULACE:</strong>
                    <span style="color: #555; margin-left: 0.5rem;">Momentálně testuješ aplikaci jako <strong><?= htmlspecialchars($_SESSION['name'] ?? $_SESSION['email'] ?? 'neznámý uživatel') ?></strong></span>
                </div>
            <?php else: ?>
                <div style="background: #f8f8f8; border-left: 4px solid #000; padding: 1rem; margin-bottom: 2rem;">
                    <strong style="color: #000;">NORMÁLNÍ REŽIM:</strong>
                    <span style="color: #555; margin-left: 0.5rem;">Jsi přihlášen jako admin - všechna práva</span>
                </div>
            <?php endif; ?>

            <!-- Aktuální SESSION -->
            <div style="background: #f8f8f8; border: 1px solid var(--wgs-border); padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1rem; font-size: 1rem; font-weight: 600; letter-spacing: 0.05em; color: #000;">AKTUÁLNÍ SESSION</h3>
                <table style="width: 100%;">
                    <tr>
                        <td style="font-weight: 600; color: #000; width: 150px;">user_id:</td>
                        <td style="color: #555;"><?= isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : 'NULL' ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: #000;">email:</td>
                        <td style="color: #555;"><?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'NULL' ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: #000;">role:</td>
                        <td style="color: #555;"><?= isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'NULL' ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: #000;">is_admin:</td>
                        <td style="color: #555;"><?= isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? 'true' : 'false' ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; color: #000;">name:</td>
                        <td style="color: #555;"><?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'NULL' ?></td>
                    </tr>
                </table>
            </div>

            <!-- Simulace rolí -->
            <h3 style="margin-bottom: 1rem; font-size: 1rem; font-weight: 600; letter-spacing: 0.05em; color: #000;">RYCHLÁ SIMULACE ROLÍ</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <a href="?simulate=role&role=admin" style="display: block; padding: 1.5rem; border: 2px solid var(--wgs-border); background: <?= $currentSimulation === 'admin' ? '#000' : '#fff' ?>; color: <?= $currentSimulation === 'admin' ? '#fff' : '#000' ?>; text-decoration: none; transition: all 0.3s;">
                    <div style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.5rem;">ADMIN</div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Vidí vše, může vše</div>
                </a>

                <a href="?simulate=role&role=prodejce" style="display: block; padding: 1.5rem; border: 2px solid var(--wgs-border); background: <?= $currentSimulation === 'prodejce' ? '#000' : '#fff' ?>; color: <?= $currentSimulation === 'prodejce' ? '#fff' : '#000' ?>; text-decoration: none; transition: all 0.3s;">
                    <div style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.5rem;">PRODEJCE</div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Vidí všechny reklamace</div>
                </a>

                <a href="?simulate=role&role=technik" style="display: block; padding: 1.5rem; border: 2px solid var(--wgs-border); background: <?= $currentSimulation === 'technik' ? '#000' : '#fff' ?>; color: <?= $currentSimulation === 'technik' ? '#fff' : '#000' ?>; text-decoration: none; transition: all 0.3s;">
                    <div style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.5rem;">TECHNIK</div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Vidí pouze přiřazené</div>
                </a>

                <a href="?simulate=role&role=guest" style="display: block; padding: 1.5rem; border: 2px solid var(--wgs-border); background: <?= $currentSimulation === 'guest' ? '#000' : '#fff' ?>; color: <?= $currentSimulation === 'guest' ? '#fff' : '#000' ?>; text-decoration: none; transition: all 0.3s;">
                    <div style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.5rem;">GUEST</div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">Vidí pouze své (email)</div>
                </a>
            </div>

            <!-- Simulace konkrétního uživatele -->
            <h3 style="margin-bottom: 1rem; font-size: 1rem; font-weight: 600; letter-spacing: 0.05em; color: #000;">SIMULACE KONKRÉTNÍHO UŽIVATELE</h3>
            <?php
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->query("SELECT id, email, role, name FROM wgs_users ORDER BY email");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <form method="GET" style="margin-bottom: 2rem;">
                    <input type="hidden" name="simulate" value="user">
                    <div style="display: flex; gap: 1rem; align-items: end;">
                        <div style="flex: 1;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #555; margin-bottom: 0.5rem;">VYBER UŽIVATELE</label>
                            <select name="user_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--wgs-border); background: #fff; font-family: 'Poppins', sans-serif; font-size: 0.9rem;">
                                <option value="">-- Vyber uživatele --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['email']) ?> (<?= htmlspecialchars($user['role'] ?? 'user') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-execute" style="white-space: nowrap;">SIMULOVAT UŽIVATELE</button>
                    </div>
                </form>
            <?php
            } catch (Exception $e) {
                echo '<p style="color: #555;">Nelze načíst seznam uživatelů: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>

            <!-- Reset -->
            <?php if ($currentSimulation): ?>
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--wgs-border);">
                    <a href="?simulate=reset" class="btn-execute btn-secondary" style="display: inline-block; text-decoration: none; background: #555; border-color: #555; color: white;">RESET NA ADMIN</a>
                </div>
            <?php endif; ?>

            <!-- Test odkazy -->
            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--wgs-border);">
                <h3 style="margin-bottom: 1rem; font-size: 1rem; font-weight: 600; letter-spacing: 0.05em; color: #000;">TESTOVACÍ ODKAZY</h3>
                <p style="color: #555; margin-bottom: 1rem; font-size: 0.9rem;">Otevři tyto stránky v novém okně a uvidíš je z pohledu simulované role:</p>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="/seznam.php" target="_blank" style="padding: 0.5rem 1rem; background: #000; color: white; text-decoration: none; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase;">SEZNAM REKLAMACÍ</a>
                    <a href="/admin.php" target="_blank" style="padding: 0.5rem 1rem; background: #555; color: white; text-decoration: none; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase;">ADMIN PANEL</a>
                    <a href="/nova-reklamace.php" target="_blank" style="padding: 0.5rem 1rem; background: #555; color: white; text-decoration: none; font-size: 0.75rem; letter-spacing: 0.05em; text-transform: uppercase;">NOVÁ REKLAMACE</a>
                </div>
            </div>
        </div>

        <!-- VÝSLEDKY -->
        <?php if ($result !== null): ?>
            <div class="result-box">
                <div class="result-header">
                    <span class="result-title">VÝSLEDEK</span>
                    <span class="execution-time">Čas: <?= number_format($executionTime, 2) ?> ms</span>
                </div>

                <?php if (is_array($result)): ?>
                    <?php if (isset($result[0]) && is_array($result[0])): ?>
                        <!-- SQL SELECT výsledek -->
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($result[0]) as $column): ?>
                                        <th><?= htmlspecialchars($column) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="color: var(--wgs-grey); font-size: 0.85rem; margin-top: 1rem;">
                            Počet řádků: <?= count($result) ?>
                        </p>
                    <?php else: ?>
                        <!-- Ostatní pole -->
                        <pre><?= htmlspecialchars(print_r($result, true)) ?></pre>
                    <?php endif; ?>
                <?php else: ?>
                    <pre><?= htmlspecialchars(print_r($result, true)) ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <div class="error-box">
                <p><strong>CHYBA:</strong> <?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <a href="/admin.php?tab=tools" class="back-link">Zpět na Admin Tools</a>
    </div>
</body>
</html>
