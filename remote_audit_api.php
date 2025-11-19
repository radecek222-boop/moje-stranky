<?php
/**
 * Remote Audit API
 * Diagnostický endpoint pro zjištění stavu produkčního serveru
 *
 * URL: https://www.wgs-service.cz/remote_audit_api.php?token=AUDIT2025
 *
 * BEZPEČNOST: Po použití tento soubor SMAZAT!
 */

header('Content-Type: application/json; charset=utf-8');

// Jednoduchá autentizace (temporary token)
$requiredToken = 'AUDIT2025';
$providedToken = $_GET['token'] ?? '';

if ($providedToken !== $requiredToken) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid token']));
}

// Rate limiting
$ipFile = __DIR__ . '/temp/audit_ip_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.txt';
if (file_exists($ipFile)) {
    $lastAccess = (int)file_get_contents($ipFile);
    if (time() - $lastAccess < 60) {
        http_response_code(429);
        die(json_encode(['error' => 'Too many requests. Wait 60 seconds.']));
    }
}
@mkdir(__DIR__ . '/temp', 0755, true);
file_put_contents($ipFile, time());

$audit = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => [],
    'php' => [],
    'files' => [],
    'database' => [],
    'smtp' => [],
    'email_queue' => [],
    'composer' => [],
    'env' => []
];

try {
    // ===== SERVER INFO =====
    $audit['server'] = [
        'hostname' => gethostname(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        'php_version' => PHP_VERSION,
        'os' => PHP_OS,
        'disk_free' => disk_free_space('.'),
        'disk_total' => disk_total_space('.')
    ];

    // ===== PHP INFO =====
    $audit['php'] = [
        'version' => PHP_VERSION,
        'extensions' => get_loaded_extensions(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'display_errors' => ini_get('display_errors'),
        'error_reporting' => error_reporting()
    ];

    // ===== FILE CHECKS =====
    $criticalFiles = [
        'init.php',
        'config/config.php',
        'includes/EmailQueue.php',
        'app/notification_sender.php',
        'cron/process-email-queue.php',
        'api/protokol_api.php',
        'vendor/autoload.php',
        'composer.json',
        'composer.lock',
        '.env',
        '.htaccess'
    ];

    foreach ($criticalFiles as $file) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            $audit['files'][$file] = [
                'exists' => true,
                'size' => filesize($fullPath),
                'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                'readable' => is_readable($fullPath),
                'writable' => is_writable($fullPath)
            ];
        } else {
            $audit['files'][$file] = ['exists' => false];
        }
    }

    // ===== COMPOSER / VENDOR =====
    $audit['composer']['vendor_exists'] = file_exists(__DIR__ . '/vendor/autoload.php');

    if ($audit['composer']['vendor_exists']) {
        // Zkontrolovat PHPMailer
        $phpmailerPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        $audit['composer']['phpmailer_installed'] = file_exists($phpmailerPath);

        if (file_exists(__DIR__ . '/composer.lock')) {
            $composerLock = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true);
            $packages = [];
            foreach ($composerLock['packages'] ?? [] as $pkg) {
                $packages[$pkg['name']] = $pkg['version'];
            }
            $audit['composer']['packages'] = $packages;
        }
    }

    // ===== .ENV FILE =====
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        $envLines = explode("\n", $envContent);
        $envKeys = [];

        foreach ($envLines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);

                // SECURITY: Hide sensitive values
                if (preg_match('/(PASS|KEY|SECRET|TOKEN)/i', $key)) {
                    $envKeys[$key] = '***HIDDEN***';
                } else {
                    $envKeys[$key] = trim($value);
                }
            }
        }

        $audit['env'] = [
            'exists' => true,
            'size' => filesize($envPath),
            'keys' => $envKeys
        ];
    } else {
        $audit['env'] = ['exists' => false];
    }

    // ===== DATABASE CONNECTION =====
    require_once __DIR__ . '/init.php';

    try {
        $pdo = getDbConnection();
        $audit['database']['connected'] = true;

        // Get database version
        $stmt = $pdo->query("SELECT VERSION() as version");
        $dbVersion = $stmt->fetch(PDO::FETCH_ASSOC);
        $audit['database']['version'] = $dbVersion['version'];

        // Get database name
        $stmt = $pdo->query("SELECT DATABASE() as dbname");
        $dbName = $stmt->fetch(PDO::FETCH_ASSOC);
        $audit['database']['database_name'] = $dbName['dbname'];

        // Check tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $audit['database']['tables'] = $tables;
        $audit['database']['table_count'] = count($tables);

        // ===== SMTP SETTINGS =====
        $stmt = $pdo->query("
            SELECT id, smtp_host, smtp_port, smtp_encryption, smtp_username,
                   smtp_from_email, smtp_from_name, is_active,
                   last_test_at, last_test_status
            FROM wgs_smtp_settings
            WHERE is_active = 1
            ORDER BY id DESC
            LIMIT 1
        ");
        $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($smtpSettings) {
            // Hide password
            $audit['smtp'] = $smtpSettings;
            $audit['smtp']['smtp_password'] = '***HIDDEN***';
        } else {
            $audit['smtp'] = ['configured' => false];
        }

        // ===== EMAIL QUEUE STATUS =====
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count
            FROM wgs_email_queue
            GROUP BY status
        ");
        $queueStats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $queueStats[$row['status']] = (int)$row['count'];
        }
        $audit['email_queue']['stats'] = $queueStats;

        // Failed emails details
        $stmt = $pdo->query("
            SELECT id, recipient_email, subject, attempts, max_attempts,
                   error_message, created_at, scheduled_at
            FROM wgs_email_queue
            WHERE status = 'pending' AND attempts >= max_attempts
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $failedEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $audit['email_queue']['failed_emails'] = $failedEmails;

        // Recent emails
        $stmt = $pdo->query("
            SELECT id, notification_id, recipient_email, subject, status,
                   attempts, created_at, sent_at
            FROM wgs_email_queue
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $recentEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $audit['email_queue']['recent_emails'] = $recentEmails;

        // Check for duplicate SMTP config in wgs_system_config
        $stmt = $pdo->query("
            SELECT config_key, config_value
            FROM wgs_system_config
            WHERE config_key IN ('smtp_host', 'smtp_port', 'smtp_username')
        ");
        $systemConfigSmtp = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $systemConfigSmtp[$row['config_key']] = $row['config_value'];
        }

        if (!empty($systemConfigSmtp)) {
            $audit['smtp']['duplicate_in_system_config'] = $systemConfigSmtp;
        }

    } catch (PDOException $e) {
        $audit['database'] = [
            'connected' => false,
            'error' => $e->getMessage()
        ];
    }

} catch (Exception $e) {
    $audit['error'] = $e->getMessage();
    $audit['trace'] = $e->getTraceAsString();
}

// Return JSON response
echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
