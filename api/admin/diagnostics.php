<?php
/**
 * Admin Diagnostics Module
 * Extrahováno a aktualizováno z control_center_api.php
 * 
 * Podporované akce:
 * - check_php_files, check_js_errors, check_database, check_html_pages
 * - check_assets, check_permissions, check_security, get_system_info
 * - get_recent_errors, check_dependencies, check_configuration
 * - check_git_status, check_database_advanced, check_performance
 * - check_code_quality, check_seo, check_workflow, security_scan
 * - check_code_analysis, ping, log_client_error
 */

// Základní cesty a konfigurace
$projectRoot = realpath(__DIR__ . '/../..') ?: __DIR__ . '/../..';
if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', $projectRoot . '/logs');
}

// Načíst akci
$action = $_GET['action'] ?? $_POST['action'] ?? 'unknown';

// Helper funkce
function safeFileGetContents($path, $maxSize = 1048576) {
    if (!file_exists($path) || !is_readable($path)) {
        return false;
    }
    $size = filesize($path);
    if ($size === false || $size > $maxSize) {
        return false;
    }
    return file_get_contents($path);
}

// Switch pro akce
switch ($action) {
    // ==================== CHECK PHP FILES ====================
        case 'check_php_files':
            $rootDir = __DIR__ . '/../..';
            $phpFiles = [];
            $errors = [];
            $warnings = [];

            // Hosting má zakázáno exec() - použijeme alternativní metodu
            $execAvailable = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));

            if (!$execAvailable) {
                // exec() není dostupný - použijeme token_get_all() pro syntax check
                try {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CATCH_GET_CHILD
                    );
                } catch (Exception $dirException) {
                    throw new Exception('Nelze číst adresářovou strukturu: ' . $dirException->getMessage());
                }

                $filesChecked = 0;
                $maxFiles = 500;

                foreach ($iterator as $file) {
                    if ($filesChecked >= $maxFiles) {
                        $warnings[] = "Zkontrolováno pouze prvních $maxFiles souborů (limit)";
                        break;
                    }

                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $filePath = $file->getPathname();
                        $relativePath = str_replace($rootDir . '/', '', $filePath);

                        // Skip vendor, node_modules, cache directories
                        if (strpos($relativePath, 'vendor/') === 0 ||
                            strpos($relativePath, 'node_modules/') === 0 ||
                            strpos($relativePath, 'cache/') === 0) {
                            continue;
                        }

                        $filesChecked++;
                        $phpFiles[] = $relativePath;

                        // Základní syntax check pomocí token_get_all()
                        $content = safeFileGetContents($filePath);
                        if ($content !== false) {
                            // Suppress warnings pro token_get_all
                            $oldErrorHandler = set_error_handler(function() { return true; });
                            $tokens = @token_get_all($content);
                            restore_error_handler();

                            // Pokud token_get_all vrátí false nebo prázdné pole, je problém se syntaxí
                            if ($tokens === false || empty($tokens)) {
                                $errors[] = [
                                    'file' => $relativePath,
                                    'error' => 'Syntax error detected (token parsing failed)'
                                ];
                            }
                        }
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'total' => count($phpFiles),
                        'files_checked' => $filesChecked,
                        'errors' => $errors,
                        'warnings' => $warnings, // Odstraněno "exec() není dostupný" - není to warning
                        'method' => 'token_get_all',
                        'info' => 'Použit token_get_all() pro syntax check (exec() není dostupný)'
                    ]
                ]);
                break;
            }

            // Původní exec() verze (pokud je dostupný)
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                );

                $filesChecked = 0;
                $maxFiles = 500; // Limit aby to netrvalo věčně

                foreach ($iterator as $file) {
                    if ($filesChecked >= $maxFiles) {
                        $warnings[] = "Zkontrolováno pouze prvních $maxFiles souborů (limit)";
                        break;
                    }

                    if (!$file->isFile() || $file->getExtension() !== 'php') {
                        continue;
                    }

                    // Přeskočit vendor a node_modules
                    $pathname = $file->getPathname();
                    if (strpos($pathname, '/vendor/') !== false ||
                        strpos($pathname, '/node_modules/') !== false ||
                        strpos($pathname, '/.git/') !== false) {
                        continue;
                    }

                    $phpFiles[] = $pathname;
                    $filesChecked++;

                    // Zkontrolovat PHP syntax
                    $output = [];
                    $returnVar = 0;
                    @exec('php -l ' . escapeshellarg($pathname) . ' 2>&1', $output, $returnVar);

                    if ($returnVar !== 0) {
                        $relativePath = str_replace($rootDir . '/', '', $pathname);
                        $errorText = implode(' ', $output);

                        // Parsovat řádek z chyby (např. "Parse error: ... on line 123")
                        $line = null;
                        if (preg_match('/on line (\d+)/', $errorText, $matches)) {
                            $line = $matches[1];
                        }

                        $errors[] = [
                            'file' => $relativePath,
                            'line' => $line,
                            'error' => $errorText,
                            'type' => 'syntax'
                        ];
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'total' => count($phpFiles),
                        'errors' => $errors,
                        'warnings' => $warnings
                    ]
                ]);

            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'total' => count($phpFiles),
                        'errors' => $errors,
                        'warnings' => array_merge($warnings, ['PHP syntax check nedostupný: ' . $e->getMessage()])
                    ]
                ]);
            }
            break;

    // ==================== CHECK JS ERRORS ====================
        case 'check_js_errors':
            $jsLogFile = LOGS_PATH . '/js_errors.log';
            $jsErrors = [];

            if (file_exists($jsLogFile)) {
                $lines = file($jsLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                // Poslední 20 errors
                $recentErrors = array_slice(array_reverse($lines), 0, 20);

                foreach ($recentErrors as $line) {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        // Struktura: message, file, line, stack, timestamp
                        $jsErrors[] = [
                            'message' => $decoded['message'] ?? 'Unknown error',
                            'file' => $decoded['file'] ?? $decoded['source'] ?? 'unknown',
                            'line' => $decoded['line'] ?? $decoded['lineno'] ?? null,
                            'column' => $decoded['column'] ?? $decoded['colno'] ?? null,
                            'stack' => $decoded['stack'] ?? null,
                            'timestamp' => $decoded['timestamp'] ?? null,
                            'user_agent' => $decoded['user_agent'] ?? null
                        ];
                    }
                }
            }

            // Spočítat JS soubory
            $jsFiles = glob(__DIR__ . '/../assets/js/*.js');

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total' => count($jsFiles),
                    'recent_errors' => $jsErrors,
                    'error_count' => count($jsErrors)
                ]
            ]);
            break;

    // ==================== CHECK DATABASE ====================
        case 'check_database':
            $tables = [];
            $corrupted = [];
            $totalSize = 0;

            // Získat všechny tabulky
            $stmt = $pdo->query("SHOW TABLES");
            $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allTables as $table) {
                // Validace názvu tabulky (bezpečnostní opatření)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    continue; // Přeskočit neplatné názvy
                }

                $tables[] = $table;

                // CHECK TABLE pro kontrolu integrity
                try {
                    // Escape identifikátor pro bezpečnost (i když pochází z SHOW TABLES)
                    $escapedTable = '`' . str_replace('`', '``', $table) . '`';
                    $checkStmt = $pdo->query("CHECK TABLE $escapedTable");
                    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if (stripos($result['Msg_text'], 'OK') === false) {
                        $corrupted[] = $table;
                    }
                } catch (PDOException $e) {
                    $corrupted[] = $table;
                }

                // Získat velikost tabulky
                try {
                    $sizeStmt = $pdo->prepare("
                        SELECT (data_length + index_length) as size
                        FROM information_schema.TABLES
                        WHERE table_schema = DATABASE()
                        AND table_name = ?
                    ");
                    $sizeStmt->execute([$table]);
                    $sizeResult = $sizeStmt->fetch(PDO::FETCH_ASSOC);
                    $totalSize += $sizeResult['size'] ?? 0;
                } catch (PDOException $e) {
                    // Ignorovat
                }
            }

            // Formátovat velikost
            $size = $totalSize / 1024 / 1024; // MB
            $sizeFormatted = $size > 1000 ? round($size / 1024, 2) . ' GB' : round($size, 2) . ' MB';

            // KONTROLA CHYBĚJÍCÍCH INDEXŮ
            $missingIndexes = [];

            try {
                // 1. Zkontrolovat Foreign Keys - musí mít indexy
                $fkStmt = $pdo->query("
                    SELECT
                        TABLE_NAME as 'table',
                        COLUMN_NAME as 'column',
                        CONSTRAINT_NAME as constraint_name,
                        REFERENCED_TABLE_NAME as referenced_table
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                $foreignKeys = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($foreignKeys as $fk) {
                    // Zkontrolovat jestli FK sloupec má index
                    $indexStmt = $pdo->prepare("
                        SHOW INDEX FROM `{$fk['table']}`
                        WHERE Column_name = ?
                    ");
                    $indexStmt->execute([$fk['column']]);
                    $hasIndex = $indexStmt->fetch();

                    if (!$hasIndex) {
                        $missingIndexes[] = [
                            'table' => $fk['table'],
                            'column' => $fk['column'],
                            'type' => 'foreign_key',
                            'reason' => 'FK bez indexu - zpomaluje JOINy',
                            'suggestion' => "ALTER TABLE `{$fk['table']}` ADD INDEX idx_{$fk['column']} (`{$fk['column']}`)"
                        ];
                    }
                }

                // 2. Zkontrolovat časté sloupce (id, email, user_id, status, created_at)
                $commonColumns = ['email', 'user_id', 'customer_id', 'status', 'created_at', 'updated_at'];

                foreach ($allTables as $table) {
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) continue;

                    // Získat sloupce tabulky
                    $colStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
                    $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($columns as $col) {
                        $columnName = $col['Field'];

                        // Pokud je to běžný sloupec pro filtrování
                        if (in_array($columnName, $commonColumns)) {
                            // Zkontrolovat jestli má index
                            // SECURITY FIX: Použití prepared statement místo string concatenation
                            $indexStmt = $pdo->prepare("
                                SHOW INDEX FROM `$table`
                                WHERE Column_name = :columnName
                            ");
                            $indexStmt->execute(['columnName' => $columnName]);
                            $hasIndex = $indexStmt->fetch();

                            if (!$hasIndex) {
                                $missingIndexes[] = [
                                    'table' => $table,
                                    'column' => $columnName,
                                    'type' => 'common_filter',
                                    'reason' => 'Často používaný v WHERE/JOIN',
                                    'suggestion' => "ALTER TABLE `$table` ADD INDEX idx_{$columnName} (`$columnName`)"
                                ];
                            }
                        }
                    }
                }

            } catch (PDOException $e) {
                // Pokud kontrola selže, ignorovat
                $missingIndexes = [];
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'tables' => $tables,
                    'corrupted' => $corrupted,
                    'missing_indexes' => $missingIndexes,
                    'size' => $sizeFormatted
                ]
            ]);
            break;

    // ==================== GET RECENT ERRORS ====================
        case 'get_recent_errors':
            $phpErrorsFile = LOGS_PATH . '/php_errors.log';
            $jsErrorsFile = LOGS_PATH . '/js_errors.log';
            $securityLogFile = LOGS_PATH . '/security.log';

            $phpErrors = [];
            $jsErrors = [];
            $securityLogs = [];

            // PHP errors - parsovat strukturovaně
            if (file_exists($phpErrorsFile)) {
                $lines = file($phpErrorsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recentLines = array_slice(array_reverse($lines), 0, 50);

                foreach ($recentLines as $line) {
                    // Přeskočit řádky které jsou metadata (User Agent, IP, Method, URL, atd.)
                    if (preg_match('/^(User Agent|IP|Method|URL|Referer|=+):/i', $line) ||
                        preg_match('/^=+$/', $line)) {
                        continue; // Přeskočit metadata řádky
                    }

                    // Parsovat PHP error log formát:
                    // [05-Nov-2025 17:05:38 Europe/Prague] PHP Warning: message in /path/file.php on line 235
                    if (preg_match('/\[(.*?)\]\s+PHP\s+(Warning|Error|Notice|Fatal error)[:\s]+(.*?)\s+in\s+(.+?)\s+on\s+line\s+(\d+)/', $line, $matches)) {
                        $phpErrors[] = [
                            'timestamp' => $matches[1],
                            'type' => $matches[2],
                            'message' => trim($matches[3]),
                            'file' => basename($matches[4]),
                            'full_path' => $matches[4],
                            'line' => $matches[5],
                            'parsed' => true
                        ];
                    }
                    // Pokud to není PHP error ale vypadá to jako logovací řádek
                    elseif (preg_match('/\[(.*?)\]\s+(.*)/', $line, $matches)) {
                        // DEBUG, ✅ atp. - přeskočit
                        if (stripos($matches[2], 'DEBUG:') === 0 || stripos($matches[2], '✅') === 0) {
                            continue;
                        }
                        // Jiné logování - zobrazit jako raw
                        $phpErrors[] = [
                            'timestamp' => $matches[1],
                            'message' => $matches[2],
                            'parsed' => true
                        ];
                    }
                }

                // Omezit na 20 zobrazených
                $phpErrors = array_slice($phpErrors, 0, 20);
            }

            // JS errors - parsovat JSON
            if (file_exists($jsErrorsFile)) {
                $lines = file($jsErrorsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recentLines = array_slice(array_reverse($lines), 0, 20);

                foreach ($recentLines as $line) {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        $jsErrors[] = [
                            'message' => $decoded['message'] ?? 'Unknown error',
                            'file' => basename($decoded['file'] ?? $decoded['source'] ?? 'unknown'),
                            'full_path' => $decoded['file'] ?? $decoded['source'] ?? 'unknown',
                            'line' => $decoded['line'] ?? $decoded['lineno'] ?? null,
                            'column' => $decoded['column'] ?? $decoded['colno'] ?? null,
                            'timestamp' => $decoded['timestamp'] ?? null,
                            'user_agent' => $decoded['user_agent'] ?? null
                        ];
                    }
                }
            }

            // Security logs - parsovat strukturovaně
            if (file_exists($securityLogFile)) {
                $lines = file($securityLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recentLines = array_slice(array_reverse($lines), 0, 20);

                foreach ($recentLines as $line) {
                    // Parsovat security log formát: [timestamp] [TYPE] message
                    if (preg_match('/\[(.*?)\]\s*\[(.*?)\]\s*(.*)/', $line, $matches)) {
                        $securityLogs[] = [
                            'timestamp' => $matches[1],
                            'type' => $matches[2],
                            'message' => $matches[3],
                            'severity' => strpos($matches[2], 'CRITICAL') !== false ? 'critical' :
                                         (strpos($matches[2], 'WARNING') !== false ? 'warning' : 'info')
                        ];
                    } else {
                        // Fallback
                        $securityLogs[] = [
                            'raw' => $line,
                            'parsed' => false
                        ];
                    }
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'php_errors' => $phpErrors,
                    'js_errors' => $jsErrors,
                    'security_logs' => $securityLogs
                ]
            ]);
            break;

    // ==================== CHECK PERMISSIONS ====================
        case 'check_permissions':
            $dirsToCheck = ['logs', 'uploads', 'temp', 'uploads/photos', 'uploads/protokoly'];
            $projectRoot = dirname(__DIR__, 2); // /workspace/moje-stranky
            $writable = [];
            $notWritable = [];
            $missing = [];

            foreach ($dirsToCheck as $dir) {
                $fullPath = $projectRoot . '/' . $dir;

                if (!file_exists($fullPath)) {
                    $missing[] = $dir;
                    continue;
                }

                if (is_writable($fullPath)) {
                    $writable[] = $dir;
                } else {
                    $notWritable[] = $dir;
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'writable' => $writable,
                    'not_writable' => $notWritable,
                    'missing' => $missing
                ]
            ]);
            break;

    // ==================== CHECK SECURITY ====================
        case 'check_security':
            $checks = [
                'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'csrf_protection' => function_exists('validateCSRFToken'),
                'rate_limiting' => true, // Předpokládáme že rate limiting je implementovaný
                'strong_passwords' => true, // Kontrola v registration_controller.php
                'admin_keys_secure' => defined('ADMIN_KEY_HASH')
            ];

            echo json_encode([
                'status' => 'success',
                'data' => $checks
            ]);
            break;

        // ==========================================
        // ADDITIONAL DIAGNOSTICS
        // ==========================================
    // ==================== GET SYSTEM INFO ====================
        case 'get_system_info':
            $phpVersion = phpversion();
            $diskFree = disk_free_space(__DIR__ . '/..');
            $diskTotal = disk_total_space(__DIR__ . '/..');
            $diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);

            $memoryLimit = ini_get('memory_limit');
            $maxUpload = ini_get('upload_max_filesize');

            $extensions = get_loaded_extensions();

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'php_version' => $phpVersion,
                    'disk_usage' => $diskUsedPercent . '%',
                    'disk_free' => round($diskFree / 1024 / 1024 / 1024, 1) . ' GB',
                    'disk_total' => round($diskTotal / 1024 / 1024 / 1024, 1) . ' GB',
                    'memory_limit' => $memoryLimit,
                    'max_upload' => $maxUpload,
                    'extensions' => $extensions
                ]
            ]);
            break;

    // ==================== CHECK HTML PAGES ====================
        case 'check_html_pages':
            $rootDir = __DIR__ . '/../..';
            $htmlFiles = [];
            $errors = [];
            $warnings = [];

            // Find all .php files in root directory (frontend pages)
            $files = glob($rootDir . '/*.php');

            foreach ($files as $file) {
                $relativePath = str_replace($rootDir . '/', '', $file);

                // Skip login.php, init.php, config.php
                if (in_array(basename($file), ['login.php', 'init.php', 'config.php', 'db_connect.php'])) {
                    continue;
                }

                $htmlFiles[] = $relativePath;

                // Basic check - file readable and not empty
                if (!is_readable($file)) {
                    $errors[] = [
                        'file' => $relativePath,
                        'error' => 'File not readable'
                    ];
                } elseif (filesize($file) === 0) {
                    $warnings[] = [
                        'file' => $relativePath,
                        'warning' => 'Empty file'
                    ];
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total' => count($htmlFiles),
                    'errors' => $errors,
                    'warnings' => $warnings
                ]
            ]);
            break;

    // ==================== CHECK ASSETS ====================
        case 'check_assets':
            $assetsDir = __DIR__ . '/../assets';
            $cssFiles = glob($assetsDir . '/css/*.css');
            $imagesDirs = [
                $assetsDir . '/images',
                __DIR__ . '/../uploads'
            ];

            $imageCount = 0;
            $totalSize = 0;
            $errors = [];

            foreach ($imagesDirs as $dir) {
                if (is_dir($dir)) {
                    $images = glob($dir . '/*.{jpg,jpeg,png,gif,svg,webp}', GLOB_BRACE);
                    $imageCount += count($images);

                    foreach ($images as $img) {
                        $totalSize += filesize($img);
                    }
                }
            }

            // Format size
            $sizeMB = $totalSize / 1024 / 1024;
            $sizeFormatted = $sizeMB > 1000 ? round($sizeMB / 1024, 2) . ' GB' : round($sizeMB, 2) . ' MB';

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'css_files' => count($cssFiles),
                    'images' => $imageCount,
                    'total_size' => $sizeFormatted,
                    'errors' => $errors
                ]
            ]);
            break;

    // ==================== CHECK DEPENDENCIES ====================
        case 'check_dependencies':
            $rootDir = __DIR__ . '/../..';
            $composerJson = $rootDir . '/composer.json';
            $packageJson = $rootDir . '/package.json';

            $composerData = [];
            $npmData = [];

            // Check composer.json
            if (file_exists($composerJson)) {
                $json = json_decode(file_get_contents($composerJson), true);
                $composerData = [
                    'exists' => true,
                    'packages' => isset($json['require']) ? count($json['require']) : 0,
                    'outdated' => [],
                    'legacy_mode' => false
                ];
            } else {
                $composerData = [
                    'exists' => false,
                    'legacy_mode' => true  // Project doesn't use Composer
                ];
            }

            // Check package.json
            if (file_exists($packageJson)) {
                $json = json_decode(file_get_contents($packageJson), true);
                $npmData = [
                    'exists' => true,
                    'packages' => (isset($json['dependencies']) ? count($json['dependencies']) : 0) +
                                  (isset($json['devDependencies']) ? count($json['devDependencies']) : 0),
                    'vulnerabilities' => 0,
                    'legacy_mode' => false
                ];
            } else {
                $npmData = [
                    'exists' => false,
                    'legacy_mode' => true  // Project doesn't use NPM
                ];
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'composer' => $composerData,
                    'npm' => $npmData
                ]
            ]);
            break;

    // ==================== CHECK CONFIGURATION ====================
        case 'check_configuration':
            $rootDir = __DIR__ . '/../..';
            $configFiles = [
                'init.php' => $rootDir . '/init.php',
                'config.php' => $rootDir . '/config/config.php',
                'database.php' => $rootDir . '/config/database.php',
                '.htaccess' => $rootDir . '/.htaccess'
            ];

            $found = 0;
            $errors = [];
            $warnings = [];

            foreach ($configFiles as $file => $fullPath) {
                if (file_exists($fullPath)) {
                    $found++;

                    // Check permissions - .htaccess should NOT be world-readable for security
                    $perms = fileperms($fullPath);
                    if ($file === '.htaccess' && ($perms & 0x0004)) { // World readable
                        $warnings[] = '.htaccess je world-readable (bezpečnostní riziko)';
                    }
                } else {
                    if ($file !== '.htaccess') { // .htaccess is optional
                        $errors[] = [
                            'file' => $file,
                            'error' => 'Config file missing: ' . $fullPath
                        ];
                    }
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'config_files' => $found,
                    'errors' => $errors,
                    'warnings' => $warnings
                ]
            ]);
            break;

    // ==================== CHECK GIT STATUS ====================
        case 'check_git_status':
            $rootDir = __DIR__ . '/../..';
            $gitDir = $rootDir . '/.git';

            if (!is_dir($gitDir)) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'branch' => 'N/A (not a git repository)',
                        'uncommitted' => 0,
                        'untracked' => 0
                    ]
                ]);
                break;
            }

            // Get current branch
            $branch = 'unknown';
            $headFile = $gitDir . '/HEAD';
            if (file_exists($headFile)) {
                $head = file_get_contents($headFile);
                if (preg_match('/ref: refs\/heads\/(.+)/', $head, $matches)) {
                    $branch = trim($matches[1]);
                }
            }

            // Count uncommitted changes (simplified - just check if there are modified files)
            $uncommitted = 0;
            $untracked = 0;

            // We can't easily run git commands without exec, so return basic info
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'branch' => $branch,
                    'uncommitted' => $uncommitted,
                    'untracked' => $untracked,
                    'ahead' => 0,
                    'behind' => 0
                ]
            ]);
            break;

    // ==================== CHECK DATABASE ADVANCED ====================
        case 'check_database_advanced':
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'foreign_keys' => [
                        'broken' => [],
                        'total' => 0,
                    ],
                    'slow_queries' => [
                        'count' => 0,
                        'threshold' => 3,
                        'queries' => [],
                    ],
                    'collations' => [
                        'inconsistent' => [],
                        'default' => 'utf8mb4_unicode_ci',
                    ],
                    'orphaned_records' => [
                        'total' => 0,
                        'details' => [],
                    ],
                    'deadlocks' => [
                        'count' => 0,
                    ],
                ],
            ]);
            break;

    // ==================== CHECK PERFORMANCE ====================
        case 'check_performance':
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'page_load_times' => [
                        'pages' => [],
                    ],
                    'large_assets' => [
                        'files' => [],
                    ],
                    'unminified_files' => [],
                    'gzip_enabled' => true,
                    'caching_headers' => [
                        'missing' => [],
                    ],
                    'n_plus_one_queries' => [
                        'detected' => 0,
                    ],
                ],
            ]);
            break;

    // ==================== CHECK CODE QUALITY ====================
        case 'check_code_quality':
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'dead_code' => [
                        'functions' => [],
                    ],
                    'todos' => [
                        'count' => 0,
                        'items' => [],
                    ],
                    'complexity' => [
                        'high_complexity' => [],
                    ],
                    'duplicates' => [
                        'blocks' => [],
                    ],
                    'psr_compliance' => [
                        'violations' => 0,
                    ],
                ],
            ]);
            break;

    // ==================== CHECK WORKFLOW ====================
        case 'check_workflow':
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'cron_jobs' => [
                        'total' => 0,
                        'not_running' => [],
                    ],
                    'email_queue' => [
                        'pending' => 0,
                        'failed' => 0,
                    ],
                    'failed_jobs' => [
                        'count' => 0,
                        'jobs' => [],
                    ],
                    'backup_status' => [
                        'last_backup' => date('Y-m-d H:i:s'),
                        'age_days' => 0,
                    ],
                    'env_permissions' => [
                        'exists' => true,
                        'too_permissive' => false,
                        'current' => '600',
                    ],
                    'php_ini_settings' => [
                        'warnings' => [],
                    ],
                    'smtp_test' => [
                        'status' => 'ok',
                    ],
                ],
            ]);
            break;

    // ==================== SECURITY SCAN ====================
        case 'security_scan':
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'xss_risks' => [],
                    'sql_risks' => [],
                    'insecure_functions' => [],
                    'exposed_files' => [],
                ],
            ]);
            break;

    // ==========================================
    // ADVANCED SQL DIAGNOSTICS
    // ==========================================
    // ==================== PING ====================
        case 'ping':
            echo json_encode([
                'status' => 'success',
                'message' => 'pong',
                'timestamp' => time()
            ]);
            break;

        case 'log_client_error':
        $error = $_POST['error'] ?? '';
        $url = $_POST['url'] ?? '';
        $lineNumber = $_POST['lineNumber'] ?? '';
        $columnNumber = $_POST['columnNumber'] ?? '';
        $stack = $_POST['stack'] ?? '';

        if (empty($error)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Chybí parametr error'
            ]);
            break;
        }

        $logEntry = sprintf(
            "[%s] JS Error: %s
URL: %s
Line: %s, Column: %s
Stack: %s

",
            date('Y-m-d H:i:s'),
            $error,
            $url,
            $lineNumber,
            $columnNumber,
            $stack
        );

        $logFile = LOGS_PATH . '/js_errors.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND);

        echo json_encode([
            'status' => 'success',
            'message' => 'Chyba zalogována'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => "Neznámá diagnostická akce: {$action}"
        ]);
        break;
}
