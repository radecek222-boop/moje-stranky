<?php
/**
 * Apply GDPR Retention Policy - Cron Job
 *
 * Automatické vynucování retention policy pro osobní data.
 * Anonymizuje nebo maže data starší než retention period.
 *
 * Spouští se: Týdně v neděli v 03:00
 * Cron: 0 3 * * 0 php /path/to/scripts/apply_retention_policy.php
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #13 - GDPR Compliance Tools
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/GDPRManager.php';

// ========================================
// KONFIGURACE
// ========================================
$retentionDays = 730; // 2 roky default (GDPR doporučení)
$debug = true; // Výpis do konzole

// ========================================
// SPUŠTĚNÍ
// ========================================
echo "==========================================\n";
echo "GDPR RETENTION POLICY - START\n";
echo "==========================================\n";
echo "Datum: " . date('Y-m-d H:i:s') . "\n";
echo "Retention period: {$retentionDays} dní\n\n";

try {
    $pdo = getDbConnection();
    $gdprManager = new GDPRManager($pdo);

    // Vypočítat cutoff date
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

    echo "Cutoff date: {$cutoffDate}\n";
    echo "Všechna data starší než tento datum budou anonymizována nebo smazána.\n\n";

    // ========================================
    // 1. ANONYMIZACE SESSIONS
    // ========================================
    echo "1️⃣  Anonymizace starých sessions...\n";

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wgs_analytics_sessions
        WHERE created_at < :cutoff_date
        AND ip_address IS NOT NULL
    ");
    $stmt->execute(['cutoff_date' => $cutoffDate]);
    $oldSessionsCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "Nalezeno: {$oldSessionsCount} starých sessions k anonymizaci\n";

    if ($oldSessionsCount > 0) {
        // Anonymizovat IP adresy
        $stmt = $pdo->prepare("
            UPDATE wgs_analytics_sessions
            SET ip_address = NULL,
                user_agent = NULL
            WHERE created_at < :cutoff_date
            AND ip_address IS NOT NULL
        ");
        $stmt->execute(['cutoff_date' => $cutoffDate]);

        echo "Anonymizováno {$oldSessionsCount} sessions (IP + User Agent)\n\n";
    } else {
        echo "Žádné sessions k anonymizaci.\n\n";
    }

    // ========================================
    // 2. ANONYMIZACE EVENTS
    // ========================================
    echo "2️⃣  Anonymizace starých events...\n";

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wgs_analytics_events
        WHERE created_at < :cutoff_date
    ");
    $stmt->execute(['cutoff_date' => $cutoffDate]);
    $oldEventsCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "Nalezeno: {$oldEventsCount} starých events\n";

    if ($oldEventsCount > 0) {
        // Ponechat pouze agregovaná data - smazat personal info
        $stmt = $pdo->prepare("
            UPDATE wgs_analytics_events
            SET event_properties = NULL
            WHERE created_at < :cutoff_date
            AND event_properties IS NOT NULL
        ");
        $stmt->execute(['cutoff_date' => $cutoffDate]);

        echo "Anonymizováno {$oldEventsCount} events (event_properties smazány)\n\n";
    } else {
        echo "Žádné events k anonymizaci.\n\n";
    }

    // ========================================
    // 3. ANONYMIZACE PAGEVIEWS
    // ========================================
    echo "3️⃣  Anonymizace starých pageviews...\n";

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wgs_analytics_pageviews
        WHERE created_at < :cutoff_date
    ");
    $stmt->execute(['cutoff_date' => $cutoffDate]);
    $oldPageviewsCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "Nalezeno: {$oldPageviewsCount} starých pageviews\n";

    if ($oldPageviewsCount > 0) {
        // Ponechat URL a datum, smazat referrer a query params
        $stmt = $pdo->prepare("
            UPDATE wgs_analytics_pageviews
            SET referrer_url = NULL
            WHERE created_at < :cutoff_date
            AND referrer_url IS NOT NULL
        ");
        $stmt->execute(['cutoff_date' => $cutoffDate]);

        echo "Anonymizováno {$oldPageviewsCount} pageviews (referrer smazán)\n\n";
    } else {
        echo "Žádné pageviews k anonymizaci.\n\n";
    }

    // ========================================
    // 4. SMAZÁNÍ STARÝCH GDPR CONSENT RECORDS
    // ========================================
    echo "4️⃣  Smazání starých consent records...\n";

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wgs_gdpr_consents
        WHERE created_at < :cutoff_date
        AND withdrawn_at IS NOT NULL
    ");
    $stmt->execute(['cutoff_date' => $cutoffDate]);
    $oldConsentsCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "Nalezeno: {$oldConsentsCount} starých withdrawn consents\n";

    if ($oldConsentsCount > 0) {
        // Smazat pouze withdrawn consents starší než retention period
        $stmt = $pdo->prepare("
            DELETE FROM wgs_gdpr_consents
            WHERE created_at < :cutoff_date
            AND withdrawn_at IS NOT NULL
        ");
        $stmt->execute(['cutoff_date' => $cutoffDate]);

        echo "Smazáno {$oldConsentsCount} starých withdrawn consents\n\n";
    } else {
        echo "Žádné consent records ke smazání.\n\n";
    }

    // ========================================
    // 5. SMAZÁNÍ DOKONČENÝCH DATA REQUESTS
    // ========================================
    echo "5️⃣  Smazání dokončených data requests...\n";

    // Smazat completed requests starší než 90 dní
    $requestsCutoffDate = date('Y-m-d H:i:s', strtotime('-90 days'));

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wgs_gdpr_data_requests
        WHERE created_at < :cutoff_date
        AND status = 'completed'
    ");
    $stmt->execute(['cutoff_date' => $requestsCutoffDate]);
    $oldRequestsCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "Nalezeno: {$oldRequestsCount} starých completed requests (>90 dní)\n";

    if ($oldRequestsCount > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM wgs_gdpr_data_requests
            WHERE created_at < :cutoff_date
            AND status = 'completed'
        ");
        $stmt->execute(['cutoff_date' => $requestsCutoffDate]);

        echo "Smazáno {$oldRequestsCount} completed requests\n\n";
    } else {
        echo "Žádné requests ke smazání.\n\n";
    }

    // ========================================
    // 6. ČIŠTĚNÍ STARÝCH AUDIT LOGS
    // ========================================
    echo "6️⃣  Čištění starých audit logs...\n";

    // Ponechat audit logs 5 let (GDPR accountability)
    $auditCutoffDate = date('Y-m-d H:i:s', strtotime('-1825 days')); // 5 let

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wgs_gdpr_audit_log
        WHERE created_at < :cutoff_date
    ");
    $stmt->execute(['cutoff_date' => $auditCutoffDate]);
    $oldAuditLogsCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo "Nalezeno: {$oldAuditLogsCount} starých audit logs (>5 let)\n";

    if ($oldAuditLogsCount > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM wgs_gdpr_audit_log
            WHERE created_at < :cutoff_date
        ");
        $stmt->execute(['cutoff_date' => $auditCutoffDate]);

        echo "Smazáno {$oldAuditLogsCount} starých audit logs\n\n";
    } else {
        echo "Žádné audit logs ke smazání.\n\n";
    }

    // ========================================
    // 7. STATISTIKY
    // ========================================
    echo "7️⃣  Finální statistiky:\n";
    echo "Sessions anonymizováno: {$oldSessionsCount}\n";
    echo "Events anonymizováno: {$oldEventsCount}\n";
    echo "Pageviews anonymizováno: {$oldPageviewsCount}\n";
    echo "Consents smazáno: {$oldConsentsCount}\n";
    echo "Requests smazáno: {$oldRequestsCount}\n";
    echo "Audit logs smazáno: {$oldAuditLogsCount}\n\n";

    $totalProcessed = $oldSessionsCount + $oldEventsCount + $oldPageviewsCount + $oldConsentsCount + $oldRequestsCount + $oldAuditLogsCount;

    echo "Celkem zpracováno: {$totalProcessed} záznamů\n\n";

    // ========================================
    // 8. LOGOVÁNÍ DO AUDIT LOGU
    // ========================================
    $gdprManager->logGDPRAction('retention_policy_applied', [
        'cutoff_date' => $cutoffDate,
        'retention_days' => $retentionDays,
        'sessions_anonymized' => $oldSessionsCount,
        'events_anonymized' => $oldEventsCount,
        'pageviews_anonymized' => $oldPageviewsCount,
        'consents_deleted' => $oldConsentsCount,
        'requests_deleted' => $oldRequestsCount,
        'audit_logs_deleted' => $oldAuditLogsCount,
        'total_processed' => $totalProcessed
    ]);

    echo "==========================================\n";
    echo "GDPR RETENTION POLICY - DOKONČENO \n";
    echo "==========================================\n";
    echo "Konec: " . date('Y-m-d H:i:s') . "\n";

} catch (PDOException $e) {
    echo "\n";
    echo "==========================================\n";
    echo "CHYBA DATABÁZE \n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    exit(1);

} catch (Exception $e) {
    echo "\n";
    echo "==========================================\n";
    echo "NEOČEKÁVANÁ CHYBA \n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>
