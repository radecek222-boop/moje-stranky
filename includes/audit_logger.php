<?php
/**
 * Audit Logger
 * Strukturované logování kritických operací pro forensic analýzu a compliance
 *
 * Použití:
 *   auditLog('admin_login', ['method' => 'admin_key']);
 *   auditLog('user_deleted', ['user_id' => 123, 'reason' => 'GDPR'], $adminId);
 *   auditLog('key_rotated', ['old_hash' => '...', 'new_hash' => '...']);
 */

/**
 * Zaloguje audit událost
 *
 * @param string $action Název akce (např. 'admin_login', 'user_deleted', 'key_rotated')
 * @param array $details Detaily akce (pole s libovolnými daty)
 * @param mixed $userId Volitelné ID uživatele (pokud není uvedeno, vezme se ze session)
 */
function auditLog($action, $details = [], $userId = null) {
    try {
        // Určit user_id
        $effectiveUserId = $userId ?? $_SESSION['user_id'] ?? 'anonymous';

        // Získat user_name
        $userName = $_SESSION['user_name'] ?? null;

        // Pokud user_name není v session, ale máme user_id, načíst z databáze
        if (!$userName && $effectiveUserId && $effectiveUserId !== 'anonymous') {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT jmeno, prijmeni FROM wgs_users WHERE user_id = :user_id LIMIT 1");
                $stmt->execute(['user_id' => $effectiveUserId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $userName = trim(($user['jmeno'] ?? '') . ' ' . ($user['prijmeni'] ?? ''));
                }
            } catch (Exception $e) {
                // Pokud selže DB dotaz, použít fallback
                error_log("Audit log DB error: " . $e->getMessage());
            }
        }

        // Fallback pokud stále nemáme jméno
        if (!$userName) {
            $userName = 'Unknown';
        }

        // Sestavení audit záznamu
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'user_id' => $effectiveUserId,
            'user_name' => $userName,
            'is_admin' => isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];

        // Určení cesty k log souboru (po měsících)
        $logDir = defined('LOGS_PATH') ? LOGS_PATH : __DIR__ . '/../logs';
        $logFile = $logDir . '/audit_' . date('Y-m') . '.log';

        // Vytvoření logs adresáře, pokud neexistuje
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Zapsání do logu (JSON formát pro snadné parsování)
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        file_put_contents(
            $logFile,
            $logLine,
            FILE_APPEND | LOCK_EX
        );

        // V development módu také vypsat do error logu
        if (defined('APP_ENV') && APP_ENV === 'development') {
            error_log("AUDIT: {$action} - " . json_encode($details, JSON_UNESCAPED_UNICODE));
        }

    } catch (Exception $e) {
        // Pokud se nepodaří zapsat audit log, zalogovat chybu
        error_log("AUDIT LOG ERROR: " . $e->getMessage());
    }
}

/**
 * Získá audit logy pro daný časový rozsah
 *
 * @param string $from Datum od (YYYY-MM-DD)
 * @param string $to Datum do (YYYY-MM-DD)
 * @param string|null $action Filtr podle akce (volitelné)
 * @param int|null $userId Filtr podle user_id (volitelné)
 * @return array Pole audit záznamů
 */
function getAuditLogs($from, $to, $action = null, $userId = null) {
    $logs = [];
    $logDir = defined('LOGS_PATH') ? LOGS_PATH : __DIR__ . '/../logs';

    // Načíst všechny relevantní log soubory
    $startMonth = date('Y-m', strtotime($from));
    $endMonth = date('Y-m', strtotime($to));

    $currentMonth = $startMonth;
    while ($currentMonth <= $endMonth) {
        $logFile = $logDir . '/audit_' . $currentMonth . '.log';

        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $entry = json_decode($line, true);

                if (!$entry) continue;

                // Aplikovat filtry
                if ($entry['timestamp'] < $from || $entry['timestamp'] > $to) continue;
                if ($action && $entry['action'] !== $action) continue;
                if ($userId && $entry['user_id'] != $userId) continue;

                $logs[] = $entry;
            }
        }

        // Přejít na další měsíc
        $currentMonth = date('Y-m', strtotime($currentMonth . '-01 +1 month'));
    }

    return $logs;
}

/**
 * Smaže staré audit logy
 *
 * @param int $daysToKeep Počet dní, které zachovat (default 365)
 */
function cleanOldAuditLogs($daysToKeep = 365) {
    $logDir = defined('LOGS_PATH') ? LOGS_PATH : __DIR__ . '/../logs';
    $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));

    $files = glob($logDir . '/audit_*.log');

    foreach ($files as $file) {
        $fileName = basename($file);
        // Extract YYYY-MM from filename
        if (preg_match('/audit_(\d{4}-\d{2})\.log/', $fileName, $matches)) {
            $fileMonth = $matches[1] . '-01';

            if ($fileMonth < $cutoffDate) {
                unlink($file);
                error_log("Deleted old audit log: {$fileName}");
            }
        }
    }
}
