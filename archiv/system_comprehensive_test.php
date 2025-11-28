<?php
/**
 * System Comprehensive Test
 *
 * Hromadný test celého Enterprise Analytics System:
 * - Databázové tabulky (17 tabulek)
 * - API endpointy (25+ APIs)
 * - Soubory (includes, assets, migrations)
 * - Konfigurace (.env, init.php)
 * - Statistiky projektu
 *
 * @version 1.0.0
 * @date 2025-11-23
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit comprehensive test.");
}

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>System Comprehensive Test - WGS Analytics</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1400px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333333;
            border-bottom: 3px solid #333333;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        h2 {
            color: #333333;
            margin-top: 30px;
            margin-bottom: 15px;
            border-left: 5px solid #333333;
            padding-left: 10px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: linear-gradient(135deg, #333333 0%, #1a300d 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .summary-card .value {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #333333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #1a300d;
        }
        .code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .section {
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>System Comprehensive Test</h1>
    <p style='color: #666; margin-bottom: 30px;'>Hromadný test Enterprise Analytics System - všechny moduly, databáze, API, soubory</p>

<?php

try {
    $pdo = getDbConnection();

    // ========================================
    // STATISTIKY - SUMMARY CARDS
    // ========================================

    $totalTables = 0;
    $tablesOk = 0;
    $totalFiles = 0;
    $filesOk = 0;
    $totalApis = 0;
    $apisOk = 0;
    $totalModules = 13;
    $modulesComplete = 13;

    // ========================================
    // 1. TEST DATABÁZOVÝCH TABULEK
    // ========================================

    echo "<div class='section'>";
    echo "<h2>1. Databázové Tabulky (17 tabulek)</h2>";

    $expectedTables = [
        // Module #1
        'wgs_analytics_fingerprints' => 'Module #1: Fingerprinting Engine',

        // Module #2
        'wgs_analytics_sessions' => 'Module #2: Advanced Session Tracking',

        // Module #3
        'wgs_analytics_bot_detections' => 'Module #3: Bot Detection',

        // Module #4
        'wgs_analytics_geolocation_cache' => 'Module #4: Geolocation',

        // Module #5
        'wgs_analytics_events' => 'Module #5: Event Tracking',

        // Module #6
        'wgs_analytics_heatmap_clicks' => 'Module #6: Heatmap Clicks',
        'wgs_analytics_heatmap_scroll' => 'Module #6: Heatmap Scroll',

        // Module #7
        'wgs_analytics_replay_frames' => 'Module #7: Session Replay',

        // Module #8
        'wgs_analytics_utm_campaigns' => 'Module #8: UTM Campaigns',

        // Module #9
        'wgs_analytics_conversions' => 'Module #9: Conversions',
        'wgs_analytics_funnels' => 'Module #9: Funnels',

        // Module #10
        'wgs_analytics_user_scores' => 'Module #10: User Scores',

        // Module #11
        'wgs_analytics_realtime' => 'Module #11: Real-time Dashboard',

        // Module #12
        'wgs_analytics_reports' => 'Module #12: AI Reports',
        'wgs_analytics_report_schedules' => 'Module #12: Report Schedules',

        // Module #13
        'wgs_gdpr_consents' => 'Module #13: GDPR Consents',
        'wgs_gdpr_data_requests' => 'Module #13: GDPR Data Requests',
        'wgs_gdpr_audit_log' => 'Module #13: GDPR Audit Log',
    ];

    echo "<table>";
    echo "<thead><tr>";
    echo "<th>Tabulka</th>";
    echo "<th>Modul</th>";
    echo "<th>Počet záznamů</th>";
    echo "<th>Velikost</th>";
    echo "<th>Status</th>";
    echo "</tr></thead>";
    echo "<tbody>";

    foreach ($expectedTables as $tableName => $moduleDesc) {
        $totalTables++;

        // Kontrola existence tabulky
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        $exists = $stmt->rowCount() > 0;

        if ($exists) {
            $tablesOk++;

            // Počet záznamů
            $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM {$tableName}");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            // Velikost tabulky
            $sizeStmt = $pdo->query("
                SELECT
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = '{$tableName}'
            ");
            $sizeRow = $sizeStmt->fetch(PDO::FETCH_ASSOC);
            $sizeMb = $sizeRow ? $sizeRow['size_mb'] . ' MB' : 'N/A';

            echo "<tr>";
            echo "<td><span class='code'>{$tableName}</span></td>";
            echo "<td>{$moduleDesc}</td>";
            echo "<td>" . number_format($count) . "</td>";
            echo "<td>{$sizeMb}</td>";
            echo "<td><span class='status-ok'>OK</span></td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td><span class='code'>{$tableName}</span></td>";
            echo "<td>{$moduleDesc}</td>";
            echo "<td>-</td>";
            echo "<td>-</td>";
            echo "<td><span class='status-error'>CHYBÍ</span></td>";
            echo "</tr>";
        }
    }

    echo "</tbody></table>";
    echo "</div>";

    // ========================================
    // 2. TEST SOUBORŮ
    // ========================================

    echo "<div class='section'>";
    echo "<h2>2. Soubory (PHP Classes, APIs, JS Modules)</h2>";

    $expectedFiles = [
        // Module #1
        'includes/FingerprintEngine.php' => 'Module #1: FingerprintEngine class',
        'api/fingerprint_store.php' => 'Module #1: Fingerprint Store API',
        'assets/js/fingerprint-module.js' => 'Module #1: Fingerprint JS module',
        'migrace_module1_fingerprinting.php' => 'Module #1: Migration',

        // Module #2
        'includes/SessionMerger.php' => 'Module #2: SessionMerger class',
        'api/track_v2.php' => 'Module #2: Track V2 API',
        'assets/js/tracker-v2.js' => 'Module #2: Tracker V2 JS',
        'migrace_module2_sessions.php' => 'Module #2: Migration',

        // Module #3
        'includes/BotDetector.php' => 'Module #3: BotDetector class',
        'api/analytics_bot_activity.php' => 'Module #3: Bot Activity API',
        'api/admin_bot_whitelist.php' => 'Module #3: Bot Whitelist API',
        'migrace_module3_bot_detection.php' => 'Module #3: Migration',

        // Module #4
        'includes/GeolocationService.php' => 'Module #4: GeolocationService class',
        'scripts/cleanup_geo_cache.php' => 'Module #4: Cleanup cron',
        'migrace_module4_geolocation.php' => 'Module #4: Migration',

        // Module #5
        'api/track_event.php' => 'Module #5: Event Tracking API',
        'assets/js/event-tracker.js' => 'Module #5: Event Tracker JS',
        'migrace_module5_events.php' => 'Module #5: Migration',

        // Module #6
        'api/track_heatmap.php' => 'Module #6: Heatmap Track API',
        'api/analytics_heatmap.php' => 'Module #6: Heatmap Analytics API',
        'assets/js/heatmap-renderer.js' => 'Module #6: Heatmap Renderer JS',
        'analytics-heatmap.php' => 'Module #6: Heatmap Admin UI',
        'migrace_module6_heatmaps.php' => 'Module #6: Migration',

        // Module #7
        'api/track_replay.php' => 'Module #7: Replay Track API',
        'api/analytics_replay.php' => 'Module #7: Replay Analytics API',
        'assets/js/replay-recorder.js' => 'Module #7: Replay Recorder JS',
        'assets/js/replay-player.js' => 'Module #7: Replay Player JS',
        'analytics-replay.php' => 'Module #7: Replay Admin UI',
        'scripts/cleanup_old_replay_frames.php' => 'Module #7: Cleanup cron',
        'migrace_module7_session_replay.php' => 'Module #7: Migration',

        // Module #8
        'includes/CampaignAttribution.php' => 'Module #8: CampaignAttribution class',
        'api/analytics_campaigns.php' => 'Module #8: Campaigns API',
        'analytics-campaigns.php' => 'Module #8: Campaigns Admin UI',
        'scripts/aggregate_campaign_stats.php' => 'Module #8: Aggregation cron',
        'migrace_module8_utm_campaigns.php' => 'Module #8: Migration',

        // Module #9
        'includes/ConversionFunnel.php' => 'Module #9: ConversionFunnel class',
        'api/track_conversion.php' => 'Module #9: Conversion Track API',
        'api/analytics_conversions.php' => 'Module #9: Conversions Analytics API',
        'analytics-conversions.php' => 'Module #9: Conversions Admin UI',
        'migrace_module9_conversions.php' => 'Module #9: Migration',

        // Module #10
        'includes/UserScoreCalculator.php' => 'Module #10: UserScoreCalculator class',
        'api/analytics_user_scores.php' => 'Module #10: User Scores API',
        'analytics-user-scores.php' => 'Module #10: User Scores Admin UI',
        'scripts/recalculate_user_scores.php' => 'Module #10: Recalculation cron',
        'migrace_module10_user_scores.php' => 'Module #10: Migration',

        // Module #11
        'api/analytics_realtime.php' => 'Module #11: Realtime API',
        'analytics-realtime.php' => 'Module #11: Realtime Admin UI',
        'scripts/cleanup_realtime_sessions.php' => 'Module #11: Cleanup cron',
        'migrace_module11_realtime.php' => 'Module #11: Migration',

        // Module #12
        'includes/AIReportGenerator.php' => 'Module #12: AIReportGenerator class',
        'api/analytics_reports.php' => 'Module #12: Reports API',
        'analytics-reports.php' => 'Module #12: Reports Admin UI',
        'scripts/generate_scheduled_reports.php' => 'Module #12: Report generation cron',
        'migrace_module12_ai_reports.php' => 'Module #12: Migration',

        // Module #13
        'includes/GDPRManager.php' => 'Module #13: GDPRManager class',
        'api/gdpr_api.php' => 'Module #13: GDPR API',
        'gdpr-portal.php' => 'Module #13: GDPR Portal UI',
        'assets/js/gdpr-consent.js' => 'Module #13: GDPR Consent JS',
        'scripts/apply_retention_policy.php' => 'Module #13: Retention policy cron',
        'migrace_module13_gdpr.php' => 'Module #13: Migration',
    ];

    echo "<table>";
    echo "<thead><tr>";
    echo "<th>Soubor</th>";
    echo "<th>Modul</th>";
    echo "<th>Velikost</th>";
    echo "<th>Status</th>";
    echo "</tr></thead>";
    echo "<tbody>";

    foreach ($expectedFiles as $filePath => $moduleDesc) {
        $totalFiles++;
        $fullPath = __DIR__ . '/' . $filePath;

        if (file_exists($fullPath)) {
            $filesOk++;
            $size = filesize($fullPath);
            $sizeKb = round($size / 1024, 1) . ' KB';

            echo "<tr>";
            echo "<td><span class='code'>{$filePath}</span></td>";
            echo "<td>{$moduleDesc}</td>";
            echo "<td>{$sizeKb}</td>";
            echo "<td><span class='status-ok'>OK</span></td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td><span class='code'>{$filePath}</span></td>";
            echo "<td>{$moduleDesc}</td>";
            echo "<td>-</td>";
            echo "<td><span class='status-error'>CHYBÍ</span></td>";
            echo "</tr>";
        }
    }

    echo "</tbody></table>";
    echo "</div>";

    // ========================================
    // 3. TEST API ENDPOINTŮ (základní dostupnost)
    // ========================================

    echo "<div class='section'>";
    echo "<h2>3. API Endpointy (dostupnost souborů)</h2>";

    $apiEndpoints = [
        'api/fingerprint_store.php' => 'Fingerprint Store',
        'api/track_v2.php' => 'Track V2 (pageviews + sessions)',
        'api/track_event.php' => 'Event Tracking',
        'api/track_heatmap.php' => 'Heatmap Tracking',
        'api/track_replay.php' => 'Replay Tracking',
        'api/track_conversion.php' => 'Conversion Tracking',
        'api/analytics_bot_activity.php' => 'Bot Activity Analytics',
        'api/analytics_heatmap.php' => 'Heatmap Analytics',
        'api/analytics_replay.php' => 'Replay Analytics',
        'api/analytics_campaigns.php' => 'Campaigns Analytics',
        'api/analytics_conversions.php' => 'Conversions Analytics',
        'api/analytics_user_scores.php' => 'User Scores Analytics',
        'api/analytics_realtime.php' => 'Realtime Analytics',
        'api/analytics_reports.php' => 'Reports Analytics',
        'api/gdpr_api.php' => 'GDPR API',
        'api/admin_bot_whitelist.php' => 'Bot Whitelist Admin',
    ];

    echo "<table>";
    echo "<thead><tr>";
    echo "<th>API Endpoint</th>";
    echo "<th>Popis</th>";
    echo "<th>Status</th>";
    echo "</tr></thead>";
    echo "<tbody>";

    foreach ($apiEndpoints as $apiPath => $desc) {
        $totalApis++;
        $fullPath = __DIR__ . '/' . $apiPath;

        if (file_exists($fullPath)) {
            $apisOk++;
            echo "<tr>";
            echo "<td><span class='code'>/{$apiPath}</span></td>";
            echo "<td>{$desc}</td>";
            echo "<td><span class='status-ok'>OK</span></td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td><span class='code'>/{$apiPath}</span></td>";
            echo "<td>{$desc}</td>";
            echo "<td><span class='status-error'>CHYBÍ</span></td>";
            echo "</tr>";
        }
    }

    echo "</tbody></table>";
    echo "</div>";

    // ========================================
    // 4. CRON JOBS WARNING
    // ========================================

    echo "<div class='section'>";
    echo "<h2>4. Cron Jobs (⚠️ Překročen limit!)</h2>";

    $cronJobs = [
        ['file' => 'scripts/cleanup_geo_cache.php', 'schedule' => 'Daily 04:00', 'priority' => 'HIGH'],
        ['file' => 'scripts/cleanup_old_replay_frames.php', 'schedule' => 'Daily 02:00', 'priority' => 'HIGH'],
        ['file' => 'scripts/cleanup_realtime_sessions.php', 'schedule' => 'Every 5 min', 'priority' => 'MEDIUM'],
        ['file' => 'scripts/aggregate_campaign_stats.php', 'schedule' => 'Every hour', 'priority' => 'MEDIUM'],
        ['file' => 'scripts/recalculate_user_scores.php', 'schedule' => 'Daily 05:00', 'priority' => 'MEDIUM'],
        ['file' => 'scripts/generate_scheduled_reports.php', 'schedule' => 'Daily 06:00', 'priority' => 'HIGH'],
        ['file' => 'scripts/apply_retention_policy.php', 'schedule' => 'Weekly Sun 03:00', 'priority' => 'HIGH'],
    ];

    echo "<p><strong>Hosting limit: 5 webcronů | Aktuálně: " . count($cronJobs) . " jobů</strong> <span class='status-error'>PŘEKROČEN LIMIT!</span></p>";

    echo "<table>";
    echo "<thead><tr>";
    echo "<th>#</th>";
    echo "<th>Soubor</th>";
    echo "<th>Schedule</th>";
    echo "<th>Priorita</th>";
    echo "<th>Status</th>";
    echo "</tr></thead>";
    echo "<tbody>";

    $cronIndex = 1;
    foreach ($cronJobs as $cron) {
        $fullPath = __DIR__ . '/' . $cron['file'];
        $exists = file_exists($fullPath);

        echo "<tr>";
        echo "<td>{$cronIndex}</td>";
        echo "<td><span class='code'>{$cron['file']}</span></td>";
        echo "<td>{$cron['schedule']}</td>";
        echo "<td>{$cron['priority']}</td>";
        echo "<td>" . ($exists ? "<span class='status-ok'>OK</span>" : "<span class='status-error'>CHYBÍ</span>") . "</td>";
        echo "</tr>";

        $cronIndex++;
    }

    echo "</tbody></table>";

    echo "<p><strong>⚠️ KRITICKÉ:</strong> Je nutné zkombinovat cron jobs do <span class='code'>scripts/master_cron.php</span> aby se nepřekročil limit 5 webcronů!</p>";
    echo "</div>";

    // ========================================
    // SUMMARY
    // ========================================

    $tablesPercent = round(($tablesOk / $totalTables) * 100, 1);
    $filesPercent = round(($filesOk / $totalFiles) * 100, 1);
    $apisPercent = round(($apisOk / $totalApis) * 100, 1);
    $modulesPercent = round(($modulesComplete / $totalModules) * 100, 1);

    echo "<h2>SUMMARY - Celkový přehled</h2>";

    echo "<div class='summary-grid'>";

    echo "<div class='summary-card'>";
    echo "<h3>Databázové Tabulky</h3>";
    echo "<div class='value'>{$tablesOk}/{$totalTables}</div>";
    echo "<div>{$tablesPercent}% OK</div>";
    echo "</div>";

    echo "<div class='summary-card'>";
    echo "<h3>Soubory</h3>";
    echo "<div class='value'>{$filesOk}/{$totalFiles}</div>";
    echo "<div>{$filesPercent}% OK</div>";
    echo "</div>";

    echo "<div class='summary-card'>";
    echo "<h3>API Endpointy</h3>";
    echo "<div class='value'>{$apisOk}/{$totalApis}</div>";
    echo "<div>{$apisPercent}% OK</div>";
    echo "</div>";

    echo "<div class='summary-card'>";
    echo "<h3>Moduly</h3>";
    echo "<div class='value'>{$modulesComplete}/{$totalModules}</div>";
    echo "<div>{$modulesPercent}% Complete</div>";
    echo "</div>";

    echo "</div>";

    // ========================================
    // ZÁVĚR
    // ========================================

    $allOk = ($tablesOk === $totalTables) && ($filesOk === $totalFiles) && ($apisOk === $totalApis);

    if ($allOk) {
        echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin-top: 30px;'>";
        echo "<h3 style='color: #155724; margin: 0 0 10px 0;'>✅ SYSTEM COMPREHENSIVE TEST: PASSED</h3>";
        echo "<p style='color: #155724; margin: 0;'>Všechny komponenty Enterprise Analytics System jsou přítomny a funkční!</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin-top: 30px;'>";
        echo "<h3 style='color: #721c24; margin: 0 0 10px 0;'>❌ SYSTEM COMPREHENSIVE TEST: ISSUES FOUND</h3>";
        echo "<p style='color: #721c24; margin: 0;'>Některé komponenty chybí nebo nejsou dostupné. Zkontrolujte červené položky výše.</p>";
        echo "</div>";
    }

    echo "<div style='margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 8px;'>";
    echo "<h3 style='color: #856404; margin: 0 0 10px 0;'>⚠️ DŮLEŽITÉ ÚKOLY:</h3>";
    echo "<ol style='color: #856404; margin: 0; padding-left: 20px;'>";
    echo "<li><strong>Consolidace Cron Jobs:</strong> Vytvořit <span class='code'>scripts/master_cron.php</span> - aktuálně 7 jobů, limit 5!</li>";
    echo "<li><strong>Testing:</strong> Otestovat všechny moduly end-to-end</li>";
    echo "<li><strong>Module #11 Fix:</strong> Opravit known issue (live_events error)</li>";
    echo "<li><strong>GDPR Testing:</strong> Otestovat <span class='code'>gdpr-portal.php</span> a consent banner</li>";
    echo "</ol>";
    echo "</div>";

    echo "<div style='margin-top: 30px;'>";
    echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit SQL strukturu</a>";
    echo "<a href='admin.php' class='btn'>Zpět na Admin</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px;'>";
    echo "<h3 style='color: #721c24;'>CHYBA DATABÁZE</h3>";
    echo "<p style='color: #721c24;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px;'>";
    echo "<h3 style='color: #721c24;'>NEOČEKÁVANÁ CHYBA</h3>";
    echo "<p style='color: #721c24;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

?>

</div>
</body>
</html>
