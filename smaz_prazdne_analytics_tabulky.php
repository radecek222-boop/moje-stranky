<?php
/**
 * Migrace: Odstranění prázdných analytics tabulek
 *
 * Tyto tabulky byly vytvořeny pro rozšířený analytics systém,
 * ale NIKDY nebyly použity (0 záznamů).
 *
 * PONECHANÉ tabulky (aktivně používané):
 * - wgs_pageviews (hlavní analytics)
 * - wgs_analytics_heatmap_clicks
 * - wgs_analytics_heatmap_scroll
 * - wgs_analytics_geolocation_cache
 * - wgs_analytics_ignored_ips
 * - wgs_analytics_funnels
 * - wgs_analytics_bot_whitelist
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Odstraneni prazdnych analytics tabulek</title>
    <style>
        body { font-family: 'Poppins', sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #222; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        h1 { color: #39ff14; border-bottom: 3px solid #39ff14; padding-bottom: 10px; }
        h2 { color: #ccc; margin-top: 1.5rem; }
        .success { background: #1a3a1a; border: 1px solid #39ff14; color: #39ff14; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3a1a1a; border: 1px solid #ff4444; color: #ff4444; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3a3a1a; border: 1px solid #ff8800; color: #ff8800; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2a3a; border: 1px solid #4488ff; color: #88bbff; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.6rem; text-align: left; border-bottom: 1px solid #444; font-size: 0.85rem; }
        th { background: #333; color: #39ff14; text-transform: uppercase; font-size: 0.75rem; }
        .btn { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #c82333; }
        .btn-secondary { background: #666; }
        .btn-secondary:hover { background: #555; }
        .status-empty { color: #39ff14; }
        .status-has-data { color: #ff4444; font-weight: bold; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 0.8rem; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1rem 0; }
        .stat { background: #333; padding: 1rem; border-radius: 5px; text-align: center; }
        .stat-value { font-size: 2rem; color: #39ff14; }
        .stat-label { color: #888; font-size: 0.75rem; text-transform: uppercase; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Odstraneni prazdnych analytics tabulek</h1>";

    // Tabulky k odstranění - POUZE ty které jsou prázdné a nepoužívané
    $tabulkyKOdstraneni = [
        'wgs_analytics_bot_detections' => 'Detekce botu - nikdy nepouzito',
        'wgs_analytics_conversions' => 'Konverze - nikdy nepouzito',
        'wgs_analytics_events' => 'Udalosti - nikdy nepouzito',
        'wgs_analytics_fingerprints' => 'Otisky zarizeni - nikdy nepouzito',
        'wgs_analytics_realtime' => 'Realtime navstevy - nikdy nepouzito',
        'wgs_analytics_replay_frames' => 'Session replay - nikdy nepouzito',
        'wgs_analytics_report_schedules' => 'Planovane reporty - nikdy nepouzito',
        'wgs_analytics_reports' => 'Generovane reporty - nikdy nepouzito',
        'wgs_analytics_sessions' => 'Sessions tracking - nikdy nepouzito',
        'wgs_analytics_user_scores' => 'User scoring - nikdy nepouzito',
        'wgs_analytics_utm_campaigns' => 'UTM tracking - nikdy nepouzito',
        'wgs_gdpr_audit_log' => 'GDPR audit log - nikdy nepouzito',
        'wgs_gdpr_consents' => 'GDPR souhlasy - nikdy nepouzito',
        'wgs_gdpr_data_requests' => 'GDPR zadosti - nikdy nepouzito',
        'wgs_github_webhooks' => 'GitHub webhooks - nikdy nepouzito',
        'wgs_content_texts' => 'CMS texty - nikdy nepouzito',
        'wgs_system_config' => 'System config - nikdy nepouzito',
        'wgs_supervisor_assignments' => 'Supervisor assignments - nikdy nepouzito'
    ];

    // Zjistit které tabulky existují a jsou prázdné
    $existujiciPrazdne = [];
    $existujiciSData = [];
    $neexistujici = [];

    foreach ($tabulkyKOdstraneni as $tabulka => $popis) {
        // Zkontrolovat jestli tabulka existuje
        $checkTable = $pdo->query("SHOW TABLES LIKE '$tabulka'");
        if (!$checkTable->fetch()) {
            $neexistujici[$tabulka] = $popis;
            continue;
        }

        // Zkontrolovat počet záznamů
        $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM `$tabulka`");
        $count = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        if ($count === 0) {
            $existujiciPrazdne[$tabulka] = ['popis' => $popis, 'count' => 0];
        } else {
            $existujiciSData[$tabulka] = ['popis' => $popis, 'count' => $count];
        }
    }

    // Statistiky
    echo "<div class='stats'>
        <div class='stat'><div class='stat-value'>" . count($existujiciPrazdne) . "</div><div class='stat-label'>Prazdnych k odstraneni</div></div>
        <div class='stat'><div class='stat-value'>" . count($existujiciSData) . "</div><div class='stat-label'>S daty (preskocit)</div></div>
        <div class='stat'><div class='stat-value'>" . count($neexistujici) . "</div><div class='stat-label'>Jiz neexistuji</div></div>
    </div>";

    // Tabulka všech tabulek
    echo "<h2>Prehled tabulek</h2>";
    echo "<table>
        <tr><th>Tabulka</th><th>Popis</th><th>Zaznamu</th><th>Status</th></tr>";

    foreach ($tabulkyKOdstraneni as $tabulka => $popis) {
        if (isset($neexistujici[$tabulka])) {
            echo "<tr style='opacity: 0.5;'>
                <td><code>$tabulka</code></td>
                <td>$popis</td>
                <td>-</td>
                <td>NEEXISTUJE</td>
            </tr>";
        } elseif (isset($existujiciSData[$tabulka])) {
            $count = $existujiciSData[$tabulka]['count'];
            echo "<tr>
                <td><code>$tabulka</code></td>
                <td>$popis</td>
                <td class='status-has-data'>$count</td>
                <td class='status-has-data'>OBSAHUJE DATA - PRESKOCIT</td>
            </tr>";
        } else {
            echo "<tr>
                <td><code>$tabulka</code></td>
                <td>$popis</td>
                <td class='status-empty'>0</td>
                <td class='status-empty'>PRAZDNA - SMAZAT</td>
            </tr>";
        }
    }
    echo "</table>";

    // Varování pokud nějaká tabulka obsahuje data
    if (!empty($existujiciSData)) {
        echo "<div class='warning'><strong>POZOR:</strong> " . count($existujiciSData) . " tabulek obsahuje data a NEBUDOU smazány:<br>";
        foreach ($existujiciSData as $t => $info) {
            echo "<code>$t</code> ({$info['count']} zaznamu), ";
        }
        echo "</div>";
    }

    if (empty($existujiciPrazdne)) {
        echo "<div class='success'><strong>Vsechny prazdne tabulky jiz byly odstraneny.</strong></div>";
        echo "<a href='/admin.php' class='btn btn-secondary'>Zpet do admin</a>";
    } else {
        // Pokud execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<h2>Probiha migrace...</h2>";

            $uspesne = 0;
            $chyby = 0;

            foreach ($existujiciPrazdne as $tabulka => $info) {
                try {
                    // Poslední kontrola - je opravdu prázdná?
                    $finalCheck = $pdo->query("SELECT COUNT(*) FROM `$tabulka`")->fetchColumn();
                    if ($finalCheck > 0) {
                        echo "<div class='warning'><strong>$tabulka:</strong> Nyni obsahuje $finalCheck zaznamu - PRESKOCENO</div>";
                        continue;
                    }

                    // Smazat tabulku
                    $pdo->exec("DROP TABLE IF EXISTS `$tabulka`");
                    echo "<div class='success'><strong>$tabulka:</strong> SMAZANA</div>";
                    $uspesne++;

                } catch (PDOException $e) {
                    echo "<div class='error'><strong>$tabulka:</strong> CHYBA - " . htmlspecialchars($e->getMessage()) . "</div>";
                    $chyby++;
                }
            }

            echo "<div class='" . ($chyby === 0 ? 'success' : 'warning') . "'>";
            echo "<strong>MIGRACE DOKONCENA</strong><br>";
            echo "Uspesne smazano: $uspesne tabulek<br>";
            if ($chyby > 0) {
                echo "Chyby: $chyby";
            }
            echo "</div>";

            // Zobrazit zbývající tabulky
            echo "<h2>Zbyvajici tabulky v databazi</h2>";
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $analyticsTables = array_filter($tables, fn($t) => strpos($t, 'wgs_analytics') === 0 || strpos($t, 'wgs_gdpr') === 0);

            if (!empty($analyticsTables)) {
                echo "<div class='info'>Zbyvajici analytics/GDPR tabulky (" . count($analyticsTables) . "):<br>";
                foreach ($analyticsTables as $t) {
                    $cnt = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
                    echo "<code>$t</code> ($cnt zaznamu)<br>";
                }
                echo "</div>";
            }

            echo "<a href='/admin.php' class='btn btn-secondary'>Zpet do admin</a>";
            echo " <a href='/vsechny_tabulky.php' class='btn btn-secondary'>Zobrazit SQL strukturu</a>";

        } else {
            // Zobrazit tlačítko pro spuštění
            echo "<div class='warning'>";
            echo "<strong>POZOR:</strong> Tato akce je nevratna!<br>";
            echo "Bude smazano <strong>" . count($existujiciPrazdne) . "</strong> prazdnych tabulek.";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>Ponechane tabulky (aktivne pouzivane):</strong><br>";
            echo "<code>wgs_pageviews</code> - hlavni analytics<br>";
            echo "<code>wgs_analytics_heatmap_clicks</code> - heatmapa kliku<br>";
            echo "<code>wgs_analytics_heatmap_scroll</code> - heatmapa scrollu<br>";
            echo "<code>wgs_analytics_geolocation_cache</code> - geolokace cache<br>";
            echo "<code>wgs_analytics_ignored_ips</code> - blokovane IP<br>";
            echo "<code>wgs_analytics_funnels</code> - funnely<br>";
            echo "<code>wgs_analytics_bot_whitelist</code> - whitelist botu";
            echo "</div>";

            echo "<a href='?execute=1' class='btn' onclick=\"return confirm('Opravdu smazat " . count($existujiciPrazdne) . " prazdnych tabulek? Tato akce je NEVRATNA!');\">Smazat " . count($existujiciPrazdne) . " tabulek</a>";
            echo "<a href='/admin.php' class='btn btn-secondary'>Zrusit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
