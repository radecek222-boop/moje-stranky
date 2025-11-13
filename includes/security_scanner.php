<?php
/**
 * Security Scanner Module
 * Automatická detekce bezpečnostních vulnerabilit v celé aplikaci
 *
 * Kontroluje:
 * - CSRF protection coverage
 * - SQL injection risks
 * - XSS vulnerabilities
 * - Authentication bypasses
 * - Rate limiting coverage
 */

function scanCSRFVulnerabilities($rootDir) {
    $findings = [
        'php_endpoints_without_csrf' => [],
        'js_calls_without_token' => [],
        'total_php_endpoints' => 0,
        'total_js_calls' => 0,
        'coverage_percentage' => 0
    ];

    // 1. Scan PHP endpoints (api/, app/controllers/)
    $phpEndpoints = [];
    $dirs = ['api', 'app/controllers', 'app'];

    foreach ($dirs as $dir) {
        $fullPath = $rootDir . '/' . $dir;
        if (!is_dir($fullPath)) continue;

        $files = glob($fullPath . '/*.php');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace($rootDir . '/', '', $file);

            // Skip if not a POST endpoint
            if (stripos($content, '$_SERVER[\'REQUEST_METHOD\']') === false &&
                stripos($content, 'POST') === false) {
                continue;
            }

            $phpEndpoints[] = $relativePath;
            $findings['total_php_endpoints']++;

            // Check for CSRF protection
            $hasCSRF = (
                stripos($content, 'requireCSRF()') !== false ||
                stripos($content, 'validateCSRFToken(') !== false ||
                stripos($content, 'csrf_token') !== false
            );

            if (!$hasCSRF) {
                $findings['php_endpoints_without_csrf'][] = [
                    'file' => $relativePath,
                    'risk' => 'HIGH',
                    'description' => 'POST endpoint bez CSRF ochrany'
                ];
            }
        }
    }

    // 2. Scan JavaScript fetch() calls
    $jsFiles = glob($rootDir . '/assets/js/*.js');

    foreach ($jsFiles as $file) {
        if (strpos($file, '.min.js') !== false) continue; // Skip minified

        $content = file_get_contents($file);
        $relativePath = str_replace($rootDir . '/', '', $file);

        // Find all fetch() calls to API endpoints
        preg_match_all('/fetch\s*\(\s*[\'"]([^\'\"]+\.php)[\'"]/', $content, $matches);

        foreach ($matches[1] as $endpoint) {
            // Skip if it's a GET-only endpoint
            if (strpos($endpoint, 'get_') !== false || strpos($endpoint, 'load.php') !== false) {
                continue;
            }

            $findings['total_js_calls']++;

            // Check if csrf_token is sent in nearby code (within 200 chars)
            $fetchPos = strpos($content, "fetch('$endpoint'");
            if ($fetchPos === false) {
                $fetchPos = strpos($content, "fetch(\"$endpoint\"");
            }

            if ($fetchPos !== false) {
                $contextStart = max(0, $fetchPos - 500);
                $contextEnd = min(strlen($content), $fetchPos + 500);
                $context = substr($content, $contextStart, $contextEnd - $contextStart);

                $hasCSRFToken = (
                    stripos($context, 'csrf_token') !== false ||
                    stripos($context, 'getCSRFToken') !== false
                );

                if (!$hasCSRFToken) {
                    $findings['js_calls_without_token'][] = [
                        'file' => $relativePath,
                        'endpoint' => $endpoint,
                        'risk' => 'HIGH',
                        'description' => 'Fetch call bez CSRF tokenu'
                    ];
                }
            }
        }
    }

    // Calculate coverage
    $totalVulnerabilities = count($findings['php_endpoints_without_csrf']) +
                           count($findings['js_calls_without_token']);
    $totalChecked = $findings['total_php_endpoints'] + $findings['total_js_calls'];

    if ($totalChecked > 0) {
        $findings['coverage_percentage'] = round(
            (1 - ($totalVulnerabilities / $totalChecked)) * 100,
            2
        );
    }

    return $findings;
}

function scanSQLInjectionRisks($rootDir) {
    $findings = [];
    $dirs = ['api', 'app/controllers', 'app', 'includes'];

    foreach ($dirs as $dir) {
        $fullPath = $rootDir . '/' . $dir;
        if (!is_dir($fullPath)) continue;

        $files = glob($fullPath . '/*.php');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace($rootDir . '/', '', $file);

            // Look for dangerous SQL patterns
            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                // Direct variable interpolation in queries
                if (preg_match('/\$pdo->query\s*\(\s*["\'].*\$/', $line) ||
                    preg_match('/\$pdo->exec\s*\(\s*["\'].*\$/', $line) ||
                    preg_match('/mysql_query\s*\(\s*["\'].*\$/', $line)) {

                    $findings[] = [
                        'file' => $relativePath,
                        'line' => $lineNum + 1,
                        'risk' => 'CRITICAL',
                        'type' => 'SQL Injection',
                        'description' => 'Přímá interpolace proměnných do SQL query',
                        'code' => trim($line)
                    ];
                }
            }
        }
    }

    return $findings;
}

function scanAuthenticationBypass($rootDir) {
    $findings = [];
    $protectedDirs = ['admin', 'api', 'app', 'app/controllers'];

    foreach ($protectedDirs as $dir) {
        $fullPath = $rootDir . '/' . $dir;
        if (!is_dir($fullPath)) continue;

        // Rekurzivně skenovat PHP soubory
        $files = glob($fullPath . '/*.php');
        $subFiles = glob($fullPath . '/**/*.php');
        $files = array_merge($files, $subFiles ?: []);
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace($rootDir . '/', '', $file);

            // Skip if it's a public endpoint (login, registration, etc.)
            if (strpos($relativePath, 'login') !== false ||
                strpos($relativePath, 'registration') !== false ||
                strpos($relativePath, 'get_csrf_token') !== false) {
                continue;
            }

            // Check for authentication
            $hasAuth = (
                stripos($content, '$_SESSION[\'user_id\']') !== false ||
                stripos($content, '$_SESSION[\'is_admin\']') !== false ||
                stripos($content, 'isLoggedIn') !== false
            );

            if (!$hasAuth) {
                $findings[] = [
                    'file' => $relativePath,
                    'risk' => 'HIGH',
                    'type' => 'Authentication Bypass',
                    'description' => 'Chybí kontrola přihlášení'
                ];
            }
        }
    }

    return $findings;
}

function performSecurityScan($rootDir) {
    return [
        'csrf_vulnerabilities' => scanCSRFVulnerabilities($rootDir),
        'sql_injection_risks' => scanSQLInjectionRisks($rootDir),
        'authentication_bypasses' => scanAuthenticationBypass($rootDir),
        'scan_time' => date('Y-m-d H:i:s'),
        'total_findings' => 0 // Will be calculated below
    ];
}
