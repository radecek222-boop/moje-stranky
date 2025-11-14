#!/usr/bin/env php
<?php
/**
 * Email Queue Processor - Cron Worker
 * Zpracovává frontu emailů
 *
 * Doporučený cron (každou minutu):
 * * * * * * php /path/to/scripts/process_email_queue.php >> /path/to/logs/email_queue.log 2>&1
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/EmailQueue.php';

// Prevent multiple instances running at once
$lockFile = sys_get_temp_dir() . '/wgs_email_queue.lock';

if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);

    // If lock is older than 5 minutes, assume crashed and remove it
    if ($lockAge > 300) {
        unlink($lockFile);
    } else {
        // Another instance is running
        exit(0);
    }
}

// Create lock file
touch($lockFile);

try {
    $queue = new EmailQueue();

    // Process up to 50 emails per run
    $results = $queue->processQueue(50);

    if ($results['processed'] > 0) {
        echo date('Y-m-d H:i:s') . " - Processed: {$results['processed']}, ";
        echo "Sent: {$results['sent']}, Failed: {$results['failed']}\n";
    }

} catch (Exception $e) {
    error_log("Email queue processor error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    // Remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
