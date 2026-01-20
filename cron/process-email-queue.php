#!/usr/bin/env php
<?php
/**
 * Email Queue Processor - Webcron Endpoint
 * Pro Český hosting (THINline) - volá se přes Webcron
 *
 * URL: https://www.wgs-service.cz/cron/process-email-queue.php
 */

// Základní zabezpečení - pouze GET požadavky
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Volitelné: Kontrola IP adresy (uncomment pokud chceš)
/*
$allowedIPs = [
    '127.0.0.1',
    '::1',
    // Přidej IP adresu Českého hostingu
];

$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIP, $allowedIPs)) {
    http_response_code(403);
    die('Forbidden');
}
*/

// Nastavit timeout na 5 minut (pro zpracování více emailů)
set_time_limit(300);

// Logování
$logFile = __DIR__ . '/../logs/email_queue_cron.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    if (!is_dir($logDir, 0755, true)) {
    if (!mkdir($logDir, 0755, true) && !is_dir($logDir, 0755, true)) {
        error_log('Failed to create directory: ' . $logDir, 0755, true);
    }
}
}

/**
 * LogMessage
 *
 * @param mixed $message Message
 */
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    if (file_put_contents($logFile, $log, FILE_APPEND) === false) {
    error_log('Failed to write file');
}
    echo $log; // Zobrazit i v outputu
}

logMessage("======================================");
logMessage("Email Queue Processor - START");
logMessage("======================================");

// Načíst potřebné soubory
require_once __DIR__ . '/../init.php';

// Načíst PHPMailer autoloader
$autoloadPath = __DIR__ . '/../lib/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    logMessage("PHPMailer autoloader načten");
} else {
    logMessage("VAROVÁNÍ: PHPMailer autoloader nenalezen - použije se fallback");
}

require_once __DIR__ . '/../includes/EmailQueue.php';

try {
    $queue = new EmailQueue();

    // Získat všechny čekající emaily
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_email_queue
        WHERE status = 'pending'
        AND scheduled_at <= NOW()
        AND attempts < max_attempts
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $result['count'];

    logMessage("Čekající emaily: $pendingCount");

    if ($pendingCount === 0) {
        logMessage("Žádné emaily ke zpracování");
        logMessage("======================================");
        http_response_code(200);
        exit;
    }

    // Zpracovat až 50 emailů najednou (kvůli timeoutu)
    $limit = 50;
    $processed = 0;
    $sent = 0;
    $failed = 0;

    // DŮLEŽITÉ: Atomický SELECT s FOR UPDATE zámkem
    // Zabraňuje race condition když běží více instancí cronu
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT * FROM wgs_email_queue
        WHERE status = 'pending'
        AND scheduled_at <= NOW()
        AND attempts < max_attempts
        ORDER BY priority DESC, scheduled_at ASC
        LIMIT ?
        FOR UPDATE SKIP LOCKED
    ");
    $stmt->execute([$limit]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Okamžitě označit všechny vybrané emaily jako 'sending'
    if (!empty($emails)) {
        $ids = array_column($emails, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE wgs_email_queue SET status = 'sending' WHERE id IN ($placeholders)")
            ->execute($ids);
    }

    $pdo->commit();

    foreach ($emails as $email) {
        logMessage("Zpracovávám email #{$email['id']} pro {$email['recipient_email']}");

        // Pokus o odeslání
        $result = $queue->sendEmail($email);

        if ($result['success']) {
            // Úspěch
            $pdo->prepare("
                UPDATE wgs_email_queue
                SET status = 'sent', sent_at = NOW(), error_message = NULL
                WHERE id = ?
            ")->execute([$email['id']]);

            $sent++;
            logMessage("✓ Email #{$email['id']} úspěšně odeslán");
        } else {
            // Selhání
            $attempts = $email['attempts'] + 1;

            if ($attempts >= $email['max_attempts']) {
                // Vyčerpány pokusy
                $pdo->prepare("
                    UPDATE wgs_email_queue
                    SET status = 'failed', attempts = ?, error_message = ?
                    WHERE id = ?
                ")->execute([$attempts, $result['message'], $email['id']]);

                $failed++;
                logMessage("✗ Email #{$email['id']} selhal po $attempts pokusech: {$result['message']}");
            } else {
                // Zkusit znovu později
                $nextSchedule = date('Y-m-d H:i:s', strtotime('+' . ($attempts * 15) . ' minutes'));
                $pdo->prepare("
                    UPDATE wgs_email_queue
                    SET status = 'pending', attempts = ?, error_message = ?, scheduled_at = ?
                    WHERE id = ?
                ")->execute([$attempts, $result['message'], $nextSchedule, $email['id']]);

                logMessage("⟳ Email #{$email['id']} pokus $attempts selhal: {$result['message']}");
                logMessage("   Zkusím znovu za " . ($attempts * 15) . " minut");
            }
        }

        $processed++;

        // Pauza mezi emaily (1 sekunda)
        sleep(1);
    }

    logMessage("--------------------------------------");
    logMessage("Zpracováno: $processed emailů");
    logMessage("Odesláno: $sent");
    logMessage("Selhalo: $failed");
    logMessage("======================================");

    http_response_code(200);

} catch (Exception $e) {
    logMessage("CHYBA: " . $e->getMessage());
    logMessage("======================================");
    http_response_code(500);
}
