<?php
/**
 * Advanced Diagnostics API
 * Ultra hloubková diagnostika celého projektu
 *
 * BEZPEČNOST: READ-ONLY mode - žádné změny v DB nebo souborech
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

try {
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

    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userId = $_SESSION['user_id'] ?? 'admin';
    $identifier = "advanced_diagnostics_{$ip}_{$userId}";

    $rateLimiter = new RateLimiter(getDbConnection());
    $rateCheck = $rateLimiter->checkLimit($identifier, 'advanced_diagnostics', [
        'max_attempts' => 50,
        'window_minutes' => 10,
        'block_minutes' => 30
    ]);

    if (!$rateCheck['allowed']) {
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'message' => $rateCheck['message']
        ]);
        exit;
    }

    $action = $_GET['action'] ?? '';
    $pdo = getDbConnection();

    switch ($action) {

        // ============================================
        // ADVANCED SQL ANALYZER
        // ============================================
        case 'analyze_sql_advanced':
            $result = analyzeSQLAdvanced($pdo);
            break;

        // ============================================
        // CODE QUALITY ANALYZER
        // ============================================
        case 'analyze_code_quality':
            $result = analyzeCodeQuality();
            break;

        // ============================================
        // SECURITY DEEP SCAN
        // ============================================
        case 'security_deep_scan':
            $result = securityDeepScan();
            break;

        // ============================================
        // PERFORMANCE PROFILER
        // ============================================
        case 'analyze_performance':
            $result = analyzePerformance();
            break;

        // ============================================
        // DEPENDENCY TRACKER
        // ============================================
        case 'analyze_dependencies':
            $result = analyzeDependencies();
            break;

        // ============================================
        // FILE STRUCTURE ANALYZER
        // ============================================
        case 'analyze_file_structure':
            $result = analyzeFileStructure();
            break;

        // ============================================
        // API ENDPOINTS DEEP TEST
        // ============================================
        case 'test_api_endpoints_deep':
            $result = testApiEndpointsDeep();
            break;

        // ============================================
        // PREPARE AI ANALYSIS DATA
        // ============================================
        case 'prepare_ai_data':
            $result = prepareAIAnalysisData();
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Neznámá akce: ' . $action
            ]);
            exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// ============================================
// ADVANCED SQL ANALYZER
// ============================================
function analyzeSQLAdvanced($pdo) {
    $results = [
        'missing_indexes' => [],
        'slow_queries_analysis' => [],
        'orphaned_records' => [],
        'foreign_key_issues' => [],
        'data_integrity_issues' => [],
        'table_statistics' => [],
        'index_usage' => [],
        'recommended_optimizations' => []
    ];

    // 1. MISSING INDEXES - Detailní analýza
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Získat všechny sloupce
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

        // Získat existující indexy
        $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $indexedColumns = array_column($indexes, 'Column_name');

        foreach ($columns as $column) {
            $colName = $column['Field'];

            // Kontrola zda sloupec by měl mít index
            $shouldHaveIndex = false;
            $reason = '';
            $priority = 'low';

            // Foreign keys
            if (preg_match('/_id$/', $colName) && !in_array($colName, $indexedColumns)) {
                $shouldHaveIndex = true;
                $reason = 'Foreign key - často používaný v JOIN';
                $priority = 'high';
            }

            // Časové sloupce
            if (in_array($colName, ['created_at', 'updated_at', 'deleted_at']) && !in_array($colName, $indexedColumns)) {
                $shouldHaveIndex = true;
                $reason = 'Časový sloupec - často používaný v WHERE/ORDER BY';
                $priority = 'medium';
            }

            // Email, username (unikátní vyhledávání)
            if (in_array($colName, ['email', 'username', 'phone']) && !in_array($colName, $indexedColumns)) {
                $shouldHaveIndex = true;
                $reason = 'Vyhledávací sloupec - často používaný v WHERE';
                $priority = 'high';
            }

            // Status sloupce
            if (in_array($colName, ['status', 'is_active', 'is_deleted']) && !in_array($colName, $indexedColumns)) {
                $shouldHaveIndex = true;
                $reason = 'Status sloupec - filtrování dat';
                $priority = 'medium';
            }

            if ($shouldHaveIndex) {
                $indexType = in_array($colName, ['email', 'username']) ? 'UNIQUE' : 'INDEX';

                $results['missing_indexes'][] = [
                    'table' => $table,
                    'column' => $colName,
                    'reason' => $reason,
                    'priority' => $priority,
                    'sql_command' => "ALTER TABLE `$table` ADD " . ($indexType === 'UNIQUE' ? 'UNIQUE ' : '') . "INDEX `idx_{$colName}` (`$colName`);",
                    'estimated_impact' => estimateIndexImpact($pdo, $table, $colName)
                ];
            }
        }
    }

    // 2. ORPHANED RECORDS - Hledání záznamů bez parent
    $orphanedQueries = [
        'wgs_reklamace' => [
            'check' => "SELECT COUNT(*) as count FROM wgs_reklamace WHERE created_by NOT IN (SELECT id FROM wgs_users) AND created_by IS NOT NULL",
            'description' => 'Reklamace bez existujícího uživatele'
        ],
        'wgs_photos' => [
            'check' => "SELECT COUNT(*) as count FROM wgs_photos WHERE reklamace_id NOT IN (SELECT id FROM wgs_reklamace)",
            'description' => 'Fotky bez existující reklamace'
        ],
        'wgs_notes' => [
            'check' => "SELECT COUNT(*) as count FROM wgs_notes WHERE reklamace_id NOT IN (SELECT id FROM wgs_reklamace)",
            'description' => 'Poznámky bez existující reklamace'
        ]
    ];

    foreach ($orphanedQueries as $table => $config) {
        try {
            $stmt = $pdo->query($config['check']);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($count > 0) {
                $results['orphaned_records'][] = [
                    'table' => $table,
                    'count' => $count,
                    'description' => $config['description'],
                    'severity' => $count > 100 ? 'high' : 'medium',
                    'recommended_action' => 'Zvážit DELETE nebo přidání CASCADE foreign key'
                ];
            }
        } catch (Exception $e) {
            // Tabulka neexistuje nebo dotaz selhal
        }
    }

    // 3. DATA INTEGRITY ISSUES
    foreach ($tables as $table) {
        try {
            // Kontrola NULL hodnot ve sloupcích, které by NULL být neměly
            $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($columns as $column) {
                $colName = $column['Field'];
                $isNullable = $column['Null'] === 'YES';

                // Pokud sloupec obsahuje "email" nebo "name" a je nullable, upozornit
                if (!$isNullable && in_array($colName, ['email', 'name', 'title'])) {
                    $nullCount = $pdo->query("SELECT COUNT(*) as count FROM `$table` WHERE `$colName` IS NULL OR `$colName` = ''")->fetch(PDO::FETCH_ASSOC)['count'];

                    if ($nullCount > 0) {
                        $results['data_integrity_issues'][] = [
                            'table' => $table,
                            'column' => $colName,
                            'issue' => "Nalezeno $nullCount prázdných hodnot v NOT NULL sloupci",
                            'severity' => 'high'
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Skip
        }
    }

    // 4. TABLE STATISTICS
    $stmt = $pdo->query("
        SELECT
            TABLE_NAME,
            TABLE_ROWS,
            AVG_ROW_LENGTH,
            DATA_LENGTH,
            INDEX_LENGTH,
            DATA_FREE,
            ENGINE,
            TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY DATA_LENGTH DESC
    ");

    $results['table_statistics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. INDEX USAGE ANALYSIS
    foreach ($tables as $table) {
        $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($indexes as $index) {
            if ($index['Key_name'] !== 'PRIMARY') {
                $results['index_usage'][] = [
                    'table' => $table,
                    'index_name' => $index['Key_name'],
                    'column' => $index['Column_name'],
                    'cardinality' => $index['Cardinality'],
                    'is_unique' => $index['Non_unique'] == 0,
                    'recommendation' => $index['Cardinality'] < 10 ? 'Nízká cardinalita - zvážit odstranění indexu' : 'OK'
                ];
            }
        }
    }

    return $results;
}

function estimateIndexImpact($pdo, $table, $column) {
    try {
        $rowCount = $pdo->query("SELECT COUNT(*) as count FROM `$table`")->fetch(PDO::FETCH_ASSOC)['count'];

        if ($rowCount < 100) return 'Nízký (malá tabulka)';
        if ($rowCount < 10000) return 'Střední (tisíce záznamů)';
        return 'Vysoký (desítky tisíc+ záznamů)';
    } catch (Exception $e) {
        return 'Neznámý';
    }
}

// ============================================
// CODE QUALITY ANALYZER
// ============================================
function analyzeCodeQuality() {
    $results = [
        'dead_code' => [],
        'duplicates' => [],
        'complexity' => [],
        'unused_variables' => [],
        'missing_functions' => [],
        'syntax_issues' => [],
        'best_practices' => []
    ];

    $projectRoot = __DIR__ . '/..';

    // 1. DEAD CODE - Nepoužívané funkce
    $phpFiles = glob($projectRoot . '/{*.php,app/**/*.php,includes/**/*.php,api/**/*.php}', GLOB_BRACE);

    $definedFunctions = [];
    $calledFunctions = [];

    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);

        // Najít definované funkce
        preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches);
        foreach ($matches[1] as $funcName) {
            $definedFunctions[$funcName][] = [
                'file' => str_replace($projectRoot, '', $file),
                'line' => getLineNumber($content, "function $funcName")
            ];
        }

        // Najít volané funkce
        preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches);
        foreach ($matches[1] as $funcName) {
            if ($funcName !== 'function' && $funcName !== 'if' && $funcName !== 'while') {
                $calledFunctions[$funcName] = true;
            }
        }
    }

    // Najít nepoužívané funkce
    foreach ($definedFunctions as $funcName => $locations) {
        if (!isset($calledFunctions[$funcName]) && !in_array($funcName, ['__construct', '__destruct', '__toString'])) {
            $results['dead_code'][] = [
                'type' => 'Nepoužívaná funkce',
                'name' => $funcName,
                'locations' => $locations,
                'recommendation' => 'Zvážit odstranění nebo dokumentaci proč je zachována'
            ];
        }
    }

    // 2. DUPLICATES - Duplicitní kód
    $fileHashes = [];
    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);
        $hash = md5($content);

        if (isset($fileHashes[$hash])) {
            $results['duplicates'][] = [
                'type' => 'Identický soubor',
                'file1' => str_replace($projectRoot, '', $fileHashes[$hash]),
                'file2' => str_replace($projectRoot, '', $file),
                'recommendation' => 'Sloučit nebo odstranit duplicitu'
            ];
        } else {
            $fileHashes[$hash] = $file;
        }
    }

    // 3. COMPLEXITY - Vysoká komplexita funkcí
    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);

        preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*{([^}]*(?:{[^}]*}[^}]*)*)}/', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $funcName = $match[1];
            $funcBody = $match[2];

            // Spočítat komplexitu (if, while, for, foreach, case)
            $complexity = 1; // Base complexity
            $complexity += substr_count($funcBody, 'if');
            $complexity += substr_count($funcBody, 'while');
            $complexity += substr_count($funcBody, 'for');
            $complexity += substr_count($funcBody, 'foreach');
            $complexity += substr_count($funcBody, 'case');

            if ($complexity > 10) {
                $results['complexity'][] = [
                    'function' => $funcName,
                    'file' => str_replace($projectRoot, '', $file),
                    'complexity' => $complexity,
                    'severity' => $complexity > 20 ? 'high' : 'medium',
                    'recommendation' => 'Rozdělit na menší funkce'
                ];
            }
        }
    }

    // 4. SYNTAX ISSUES - Potenciální syntaktické problémy
    foreach ($phpFiles as $file) {
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);

        if ($return_var !== 0) {
            $results['syntax_issues'][] = [
                'file' => str_replace($projectRoot, '', $file),
                'error' => implode("\n", $output),
                'severity' => 'critical'
            ];
        }
    }

    // 5. BEST PRACTICES
    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);
        $relativePath = str_replace($projectRoot, '', $file);

        // Kontrola SQL injection rizik
        if (preg_match('/\$_(GET|POST|REQUEST)\s*\[.*?\].*?(SELECT|INSERT|UPDATE|DELETE)/i', $content)) {
            $results['best_practices'][] = [
                'file' => $relativePath,
                'issue' => 'Potenciální SQL injection - použití $_GET/$_POST v SQL dotazu',
                'severity' => 'critical',
                'recommendation' => 'Použít prepared statements'
            ];
        }

        // Kontrola XSS rizik
        if (preg_match('/echo\s+\$_(GET|POST|REQUEST)/i', $content)) {
            $results['best_practices'][] = [
                'file' => $relativePath,
                'issue' => 'Potenciální XSS - echo $_GET/$_POST bez escapování',
                'severity' => 'high',
                'recommendation' => 'Použít htmlspecialchars() nebo escapeHtml()'
            ];
        }

        // Kontrola eval()
        if (preg_match('/eval\s*\(/i', $content)) {
            $results['best_practices'][] = [
                'file' => $relativePath,
                'issue' => 'Použití eval() - bezpečnostní riziko',
                'severity' => 'critical',
                'recommendation' => 'Odstranit eval() a použít alternativní řešení'
            ];
        }
    }

    return $results;
}

function getLineNumber($content, $search) {
    $lines = explode("\n", $content);
    foreach ($lines as $num => $line) {
        if (strpos($line, $search) !== false) {
            return $num + 1;
        }
    }
    return 0;
}

// ============================================
// SECURITY DEEP SCAN
// ============================================
function securityDeepScan() {
    $results = [
        'xss_risks' => [],
        'sql_injection_risks' => [],
        'csrf_missing' => [],
        'file_upload_risks' => [],
        'session_security' => [],
        'exposed_files' => [],
        'weak_passwords' => [],
        'rate_limiting' => []
    ];

    $projectRoot = __DIR__ . '/..';
    $phpFiles = glob($projectRoot . '/{*.php,app/**/*.php,includes/**/*.php,api/**/*.php}', GLOB_BRACE);

    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);
        $relativePath = str_replace($projectRoot, '', $file);

        // 1. XSS RISKS
        $xssPatterns = [
            '/echo\s+\$_(GET|POST|REQUEST|COOKIE)\[/' => 'Echo $_GET/$_POST bez escapování',
            '/innerHTML\s*=\s*[^\'"]/' => 'innerHTML bez sanitizace',
            '/\$_(GET|POST)\[.*?\].*?onclick=/' => 'User input v onclick atributu',
        ];

        foreach ($xssPatterns as $pattern => $description) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lineNum = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                $results['xss_risks'][] = [
                    'file' => $relativePath,
                    'line' => $lineNum,
                    'pattern' => $description,
                    'severity' => 'high',
                    'context' => getContextLines($content, $lineNum)
                ];
            }
        }

        // 2. SQL INJECTION RISKS
        $sqlPatterns = [
            '/(?:SELECT|INSERT|UPDATE|DELETE).*?\$_(GET|POST|REQUEST)\[/i' => 'SQL dotaz s user input',
            '/query\([\'"].*?\$\w+.*?[\'"]\)/i' => 'SQL query s proměnnou bez prepared statement',
        ];

        foreach ($sqlPatterns as $pattern => $description) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lineNum = substr_count(substr($content, 0, $matches[0][1]), "\n") + 1;
                $results['sql_injection_risks'][] = [
                    'file' => $relativePath,
                    'line' => $lineNum,
                    'pattern' => $description,
                    'severity' => 'critical',
                    'context' => getContextLines($content, $lineNum)
                ];
            }
        }

        // 3. CSRF MISSING
        if (strpos($file, '/api/') !== false && preg_match('/\$_POST/', $content)) {
            if (!preg_match('/validateCSRFToken|csrf_token/i', $content)) {
                $results['csrf_missing'][] = [
                    'file' => $relativePath,
                    'issue' => 'API endpoint s POST bez CSRF ochrany',
                    'severity' => 'high'
                ];
            }
        }

        // 4. FILE UPLOAD RISKS
        if (preg_match('/\$_FILES/', $content)) {
            if (!preg_match('/mime_content_type|finfo_file|getimagesize/', $content)) {
                $results['file_upload_risks'][] = [
                    'file' => $relativePath,
                    'issue' => 'File upload bez validace MIME typu',
                    'severity' => 'high',
                    'recommendation' => 'Přidat validaci MIME typu a file extension'
                ];
            }
        }
    }

    // 5. EXPOSED FILES
    $sensitiveFiles = [
        '.env',
        'config.php',
        'db_config.php',
        '.git/config',
        'composer.json',
        'phpinfo.php'
    ];

    foreach ($sensitiveFiles as $file) {
        $fullPath = $projectRoot . '/' . $file;
        if (file_exists($fullPath)) {
            $results['exposed_files'][] = [
                'file' => $file,
                'severity' => in_array($file, ['.env', 'config.php']) ? 'critical' : 'medium',
                'recommendation' => 'Zajistit, že soubor není přístupný přes web'
            ];
        }
    }

    return $results;
}

function getContextLines($content, $lineNum, $context = 3) {
    $lines = explode("\n", $content);
    $start = max(0, $lineNum - $context - 1);
    $end = min(count($lines), $lineNum + $context);

    $contextLines = [];
    for ($i = $start; $i < $end; $i++) {
        $contextLines[] = [
            'line' => $i + 1,
            'content' => $lines[$i],
            'is_error_line' => ($i + 1) === $lineNum
        ];
    }

    return $contextLines;
}

// ============================================
// PERFORMANCE ANALYZER
// ============================================
function analyzePerformance() {
    $results = [
        'large_files' => [],
        'unminified_assets' => [],
        'missing_lazy_load' => [],
        'cache_headers' => [],
        'database_performance' => []
    ];

    $projectRoot = __DIR__ . '/..';

    // 1. LARGE FILES
    $assetFiles = glob($projectRoot . '/{assets/**/*,uploads/**/*}', GLOB_BRACE);

    foreach ($assetFiles as $file) {
        if (is_file($file)) {
            $size = filesize($file);

            if ($size > 500000) { // 500KB
                $results['large_files'][] = [
                    'file' => str_replace($projectRoot, '', $file),
                    'size' => formatBytes($size),
                    'size_bytes' => $size,
                    'recommendation' => $size > 2000000 ? 'KRITICKÉ - Komprimovat nebo optimalizovat' : 'Zvážit kompresi'
                ];
            }
        }
    }

    usort($results['large_files'], function($a, $b) {
        return $b['size_bytes'] - $a['size_bytes'];
    });

    // 2. UNMINIFIED ASSETS
    $jsFiles = glob($projectRoot . '/assets/js/*.js');
    $cssFiles = glob($projectRoot . '/assets/css/*.css');

    foreach (array_merge($jsFiles, $cssFiles) as $file) {
        if (!preg_match('/\.min\.(js|css)$/', $file)) {
            $size = filesize($file);
            $results['unminified_assets'][] = [
                'file' => str_replace($projectRoot, '', $file),
                'size' => formatBytes($size),
                'potential_savings' => formatBytes($size * 0.3) . ' (30%)',
                'recommendation' => 'Minifikovat pro produkci'
            ];
        }
    }

    // 3. MISSING LAZY LOAD
    $htmlFiles = glob($projectRoot . '/{*.php,app/**/*.php}', GLOB_BRACE);

    foreach ($htmlFiles as $file) {
        $content = file_get_contents($file);

        // Najít <img> tagy bez loading="lazy"
        preg_match_all('/<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!preg_match('/loading\s*=\s*["\']lazy["\']/i', $match[0])) {
                $results['missing_lazy_load'][] = [
                    'file' => str_replace($projectRoot, '', $file),
                    'image' => $match[1],
                    'recommendation' => 'Přidat loading="lazy" atribut'
                ];
            }
        }
    }

    return $results;
}

function formatBytes($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// ============================================
// DEPENDENCY ANALYZER
// ============================================
function analyzeDependencies() {
    $results = [
        'require_map' => [],
        'circular_dependencies' => [],
        'missing_files' => [],
        'dependency_graph' => []
    ];

    $projectRoot = __DIR__ . '/..';
    $phpFiles = glob($projectRoot . '/{*.php,app/**/*.php,includes/**/*.php,api/**/*.php}', GLOB_BRACE);

    $dependencies = [];

    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);
        $relativePath = str_replace($projectRoot, '', $file);

        // Najít require/include
        preg_match_all('/(?:require|include)(?:_once)?\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches);

        $fileDeps = [];
        foreach ($matches[1] as $depPath) {
            // Normalizovat cestu
            $fullPath = dirname($file) . '/' . $depPath;
            $fullPath = realpath($fullPath);

            if ($fullPath) {
                $depRelPath = str_replace($projectRoot, '', $fullPath);
                $fileDeps[] = $depRelPath;
            } else {
                // Missing file
                $results['missing_files'][] = [
                    'file' => $relativePath,
                    'missing_dependency' => $depPath,
                    'severity' => 'high'
                ];
            }
        }

        $dependencies[$relativePath] = $fileDeps;
    }

    $results['require_map'] = $dependencies;

    // Detect circular dependencies
    foreach ($dependencies as $file => $deps) {
        foreach ($deps as $dep) {
            if (isset($dependencies[$dep]) && in_array($file, $dependencies[$dep])) {
                $results['circular_dependencies'][] = [
                    'file1' => $file,
                    'file2' => $dep,
                    'severity' => 'high',
                    'recommendation' => 'Refaktorovat pro odstranění cyklické závislosti'
                ];
            }
        }
    }

    return $results;
}

// ============================================
// FILE STRUCTURE ANALYZER
// ============================================
function analyzeFileStructure() {
    $projectRoot = __DIR__ . '/..';

    $structure = [
        'total_files' => 0,
        'by_extension' => [],
        'by_directory' => [],
        'large_directories' => [],
        'deep_nesting' => []
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $structure['total_files']++;

            $ext = $file->getExtension();
            if (!isset($structure['by_extension'][$ext])) {
                $structure['by_extension'][$ext] = 0;
            }
            $structure['by_extension'][$ext]++;

            $dir = dirname(str_replace($projectRoot, '', $file->getPathname()));
            if (!isset($structure['by_directory'][$dir])) {
                $structure['by_directory'][$dir] = 0;
            }
            $structure['by_directory'][$dir]++;

            // Check deep nesting
            $depth = substr_count($file->getPathname(), '/');
            if ($depth > 10) {
                $structure['deep_nesting'][] = str_replace($projectRoot, '', $file->getPathname());
            }
        }
    }

    // Find large directories
    arsort($structure['by_directory']);
    $structure['large_directories'] = array_slice($structure['by_directory'], 0, 10, true);

    return $structure;
}

// ============================================
// API ENDPOINTS DEEP TEST
// ============================================
function testApiEndpointsDeep() {
    $endpoints = [
        '/api/admin_api.php',
        '/api/control_center_api.php',
        '/api/notification_api.php',
        '/api/protokol_api.php',
        '/api/statistiky_api.php'
    ];

    $results = [];

    foreach ($endpoints as $endpoint) {
        $start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . $endpoint . '?action=ping');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = (microtime(true) - $start) * 1000; // ms
        curl_close($ch);

        $results[] = [
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'response_time' => round($responseTime, 2) . ' ms',
            'status' => in_array($httpCode, [200, 400]) ? 'OK' : 'FAILED',
            'is_json' => json_decode($response) !== null
        ];
    }

    return $results;
}

// ============================================
// PREPARE AI ANALYSIS DATA
// ============================================
function prepareAIAnalysisData() {
    $projectRoot = __DIR__ . '/..';

    $data = [
        'project_info' => [
            'name' => 'WGS Service',
            'php_version' => PHP_VERSION,
            'total_files' => 0,
            'total_size' => 0
        ],
        'critical_files' => [],
        'error_prone_patterns' => [],
        'recommendations' => []
    ];

    // Gather critical files
    $criticalPaths = [
        'init.php',
        'api/control_center_api.php',
        'app/controllers/auth.php'
    ];

    foreach ($criticalPaths as $path) {
        $fullPath = $projectRoot . '/' . $path;
        if (file_exists($fullPath)) {
            $data['critical_files'][] = [
                'path' => $path,
                'size' => filesize($fullPath),
                'lines' => count(file($fullPath)),
                'sample' => substr(file_get_contents($fullPath), 0, 1000) // First 1000 chars
            ];
        }
    }

    return $data;
}
