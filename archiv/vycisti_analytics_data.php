<?php
/**
 * Vyčištění všech analytics dat
 *
 * Tento skript smaže VŠECHNA data z analytics tabulek:
 * - wgs_pageviews
 * - wgs_analytics_sessions
 * - wgs_analytics_realtime
 * - wgs_analytics_events
 * - wgs_analytics_heatmap_clicks
 * - wgs_analytics_heatmap_scroll
 * - wgs_analytics_replay_frames
 * - wgs_analytics_user_scores
 * - wgs_analytics_conversions
 * - wgs_analytics_fingerprints
 * - wgs_analytics_bot_detections
 *
 * POZOR: Tato akce je nevratná!
 *
 * @date 2025-11-24
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Vyčištění Analytics dat</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
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
            color: #333;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #555;
        }
        .btn-danger {
            background: #721c24;
        }
        .btn-danger:hover {
            background: #5a1a1f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #333;
            color: white;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Vyčištění Analytics dat</h1>";
    echo "<p><strong>Datum:</strong> " . date('d.m.Y H:i:s') . "</p>";

    // =======================================================
    // DEFINICE TABULEK
    // =======================================================
    $tabulky = [
        'wgs_pageviews' => 'Pageviews (hlavní analytics)',
        'wgs_analytics_sessions' => 'Analytics Sessions',
        'wgs_analytics_realtime' => 'Realtime Analytics',
        'wgs_analytics_events' => 'Analytics Events',
        'wgs_analytics_heatmap_clicks' => 'Heatmap Clicks',
        'wgs_analytics_heatmap_scroll' => 'Heatmap Scroll',
        'wgs_analytics_replay_frames' => 'Session Replay Frames',
        'wgs_analytics_user_scores' => 'User Scores',
        'wgs_analytics_conversions' => 'Conversions',
        'wgs_analytics_fingerprints' => 'Fingerprints',
        'wgs_analytics_bot_detections' => 'Bot Detections',
        'wgs_analytics_utm_campaigns' => 'UTM Campaigns',
        'wgs_analytics_geolocation_cache' => 'Geolocation Cache',
    ];

    // =======================================================
    // ZOBRAZIT AKTUÁLNÍ STAV
    // =======================================================
    echo "<h2>Aktuální stav tabulek</h2>";

    echo "<table>";
    echo "<tr><th>Tabulka</th><th>Počet záznamů</th><th>Stav</th></tr>";

    $celkovyPocet = 0;

    foreach ($tabulky as $tabulka => $nazev) {
        // Zkontrolovat, jestli tabulka existuje
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
        if (!$stmt || $stmt->rowCount() === 0) {
            echo "<tr><td><code>{$tabulka}</code></td><td>-</td><td><em>Neexistuje</em></td></tr>";
            continue;
        }

        // Počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `{$tabulka}`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        $celkovyPocet += $count;

        $stav = $count > 0 ? "<span style='color: #856404;'>Data k vyčištění</span>" : "<span style='color: #155724;'>Prázdná</span>";

        echo "<tr>";
        echo "<td><code>{$tabulka}</code><br><small>{$nazev}</small></td>";
        echo "<td><strong>" . number_format($count, 0, ',', ' ') . "</strong></td>";
        echo "<td>{$stav}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div class='info'><strong>Celkem záznamů k vyčištění:</strong> " . number_format($celkovyPocet, 0, ',', ' ') . "</div>";

    // =======================================================
    // AKCE: SMAZAT VŠE
    // =======================================================
    if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
        echo "<h2>Mazání všech analytics dat...</h2>";

        $smazano = 0;

        foreach ($tabulky as $tabulka => $nazev) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
            if ($stmt && $stmt->rowCount() > 0) {
                try {
                    // TRUNCATE je rychlejší než DELETE pro velké tabulky
                    $pdo->exec("TRUNCATE TABLE `{$tabulka}`");
                    echo "<div class='success'>Vymazáno: <code>{$tabulka}</code></div>";
                    $smazano++;
                } catch (PDOException $e) {
                    // Pokud TRUNCATE selže (foreign keys), zkusit DELETE
                    try {
                        $stmt = $pdo->exec("DELETE FROM `{$tabulka}`");
                        echo "<div class='success'>Smazáno z <code>{$tabulka}</code>: <strong>{$stmt}</strong> záznamů</div>";
                        $smazano++;
                    } catch (PDOException $e2) {
                        echo "<div class='error'>Chyba při mazání <code>{$tabulka}</code>: " . htmlspecialchars($e2->getMessage()) . "</div>";
                    }
                }
            }
        }

        echo "<div class='success'><strong>HOTOVO!</strong> Vyčištěno tabulek: <strong>{$smazano}</strong></div>";
        echo "<a href='vycisti_analytics_data.php' class='btn'>Zpět</a>";
        echo "<a href='admin.php' class='btn'>Admin Panel</a>";
        echo "<a href='statistiky.php' class='btn'>Statistiky</a>";

    // =======================================================
    // ZOBRAZIT MOŽNOSTI
    // =======================================================
    } else {
        echo "<h2>Možnosti vyčištění</h2>";

        echo "<div class='warning'>";
        echo "<strong>POZOR:</strong> Mazání dat je nevratné!<br>";
        echo "Tato akce smaže VŠECHNA analytics data včetně:";
        echo "<ul>";
        echo "<li>Pageviews (návštěvnost stránek)</li>";
        echo "<li>Session data (relace uživatelů)</li>";
        echo "<li>Heatmap data (kliknutí a scrollování)</li>";
        echo "<li>Conversion data (konverze)</li>";
        echo "<li>Bot detekce</li>";
        echo "</ul>";
        echo "</div>";

        if ($celkovyPocet > 0) {
            echo "<a href='?action=delete_all' class='btn btn-danger' onclick=\"return confirm('Opravdu smazat VŠECHNA analytics data ({$celkovyPocet} záznamů)?\\n\\nTato akce je NEVRATNÁ!')\">Smazat všechna analytics data</a>";
        } else {
            echo "<div class='success'>Všechny analytics tabulky jsou již prázdné.</div>";
        }

        echo "<hr>";
        echo "<a href='admin.php' class='btn' style='background:#666'>Zpět na Admin</a>";
        echo "<a href='statistiky.php' class='btn' style='background:#666'>Statistiky</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
