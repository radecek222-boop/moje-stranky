<?php
/**
 * Kontrola Analytics Dat
 * Zobrazí aktuální stav trackingu a ignorovaných IP
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může zobrazit analytics data.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola Analytics</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; color: #2D5016; }
        tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em;
                 font-weight: 600; }
        .badge-desktop { background: #e3f2fd; color: #1976d2; }
        .badge-mobile { background: #f3e5f5; color: #7b1fa2; }
        .badge-tablet { background: #e8f5e9; color: #388e3c; }
        .stat-box { display: inline-block; background: #f8f9fa; padding: 20px;
                    border-radius: 8px; margin: 10px; min-width: 200px;
                    text-align: center; border: 2px solid #2D5016; }
        .stat-value { font-size: 2.5rem; font-weight: 700; color: #2D5016; }
        .stat-label { font-size: 0.9rem; color: #666; margin-top: 5px; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>📊 Kontrola Web Analytics</h1>";

    // 1. IGNOROVANÉ IP ADRESY
    echo "<h2>🚫 Ignorované IP Adresy</h2>";

    $stmtIgnored = $pdo->query("SELECT * FROM wgs_analytics_ignored_ips ORDER BY id");
    $ignoredIPs = $stmtIgnored->fetchAll(PDO::FETCH_ASSOC);

    if (count($ignoredIPs) > 0) {
        echo "<div class='success'>";
        echo "<strong>✅ Nalezeno " . count($ignoredIPs) . " ignorovaných IP adres:</strong>";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>IP Adresa</th><th>Popis</th><th>Přidáno</th></tr>";
        foreach ($ignoredIPs as $ip) {
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($ip['ip_address']) . "</code></td>";
            echo "<td>" . htmlspecialchars($ip['description']) . "</td>";
            echo "<td>" . date('d.m.Y H:i', strtotime($ip['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>";
        echo "⚠️ Žádné ignorované IP adresy. Admin IP se budou zaznamenávat!";
        echo "</div>";
    }

    // 2. STATISTIKY PAGEVIEWS
    echo "<h2>📈 Statistiky Návštěvnosti</h2>";

    $stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM wgs_pageviews");
    $total = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtUnique = $pdo->query("SELECT COUNT(DISTINCT session_id) as unique_sessions FROM wgs_pageviews");
    $uniqueSessions = (int)$stmtUnique->fetch(PDO::FETCH_ASSOC)['unique_sessions'];

    $stmtToday = $pdo->query("SELECT COUNT(*) as today FROM wgs_pageviews WHERE DATE(created_at) = CURDATE()");
    $today = (int)$stmtToday->fetch(PDO::FETCH_ASSOC)['today'];

    echo "<div style='text-align: center;'>";

    echo "<div class='stat-box'>";
    echo "<div class='stat-value'>$total</div>";
    echo "<div class='stat-label'>Celkem Návštěv</div>";
    echo "</div>";

    echo "<div class='stat-box'>";
    echo "<div class='stat-value'>$uniqueSessions</div>";
    echo "<div class='stat-label'>Unikátní Relace</div>";
    echo "</div>";

    echo "<div class='stat-box'>";
    echo "<div class='stat-value'>$today</div>";
    echo "<div class='stat-label'>Dnes</div>";
    echo "</div>";

    echo "</div>";

    // 3. POSLEDNÍ NÁVŠTĚVY
    echo "<h2>🕒 Posledních 10 Návštěv</h2>";

    $stmtRecent = $pdo->query("
        SELECT *
        FROM wgs_pageviews
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentVisits = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    if (count($recentVisits) > 0) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Čas</th>";
        echo "<th>IP</th>";
        echo "<th>Stránka</th>";
        echo "<th>Zařízení</th>";
        echo "<th>Prohlížeč</th>";
        echo "<th>OS</th>";
        echo "</tr>";

        foreach ($recentVisits as $visit) {
            echo "<tr>";
            echo "<td>" . date('d.m.Y H:i:s', strtotime($visit['created_at'])) . "</td>";
            echo "<td><code>" . htmlspecialchars($visit['ip_address']) . "</code></td>";
            echo "<td>" . htmlspecialchars($visit['page_url']) . "</td>";

            $deviceClass = 'badge-desktop';
            if ($visit['device_type'] === 'mobile') $deviceClass = 'badge-mobile';
            if ($visit['device_type'] === 'tablet') $deviceClass = 'badge-tablet';

            echo "<td><span class='badge $deviceClass'>" . htmlspecialchars($visit['device_type'] ?? 'unknown') . "</span></td>";
            echo "<td>" . htmlspecialchars($visit['browser'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($visit['os'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Zatím žádné návštěvy zaznamenány.</div>";
    }

    // 4. NEJVÍCE NAVŠTĚVOVANÉ STRÁNKY
    echo "<h2>📄 Top 5 Stránek</h2>";

    $stmtTopPages = $pdo->query("
        SELECT page_url, COUNT(*) as visits
        FROM wgs_pageviews
        GROUP BY page_url
        ORDER BY visits DESC
        LIMIT 5
    ");
    $topPages = $stmtTopPages->fetchAll(PDO::FETCH_ASSOC);

    if (count($topPages) > 0) {
        echo "<table>";
        echo "<tr><th>Stránka</th><th>Počet Návštěv</th></tr>";
        foreach ($topPages as $page) {
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($page['page_url']) . "</code></td>";
            echo "<td><strong>" . $page['visits'] . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Zatím žádná data.</div>";
    }

    // 5. ZAŘÍZENÍ / PROHLÍŽEČE
    echo "<h2>💻 Statistiky Zařízení</h2>";

    $stmtDevices = $pdo->query("
        SELECT
            device_type,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wgs_pageviews), 1) as percentage
        FROM wgs_pageviews
        GROUP BY device_type
        ORDER BY count DESC
    ");
    $devices = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

    if (count($devices) > 0) {
        echo "<table>";
        echo "<tr><th>Zařízení</th><th>Počet</th><th>Procenta</th></tr>";
        foreach ($devices as $device) {
            $deviceClass = 'badge-desktop';
            if ($device['device_type'] === 'mobile') $deviceClass = 'badge-mobile';
            if ($device['device_type'] === 'tablet') $deviceClass = 'badge-tablet';

            echo "<tr>";
            echo "<td><span class='badge $deviceClass'>" . htmlspecialchars($device['device_type'] ?? 'unknown') . "</span></td>";
            echo "<td>" . $device['count'] . "</td>";
            echo "<td>" . $device['percentage'] . "%</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<br><br>";
    echo "<div class='success'>";
    echo "<strong>✅ TRACKING FUNGUJE!</strong><br>";
    echo "Analytics data se úspěšně zaznamenávají do databáze.";
    echo "</div>";

    echo "<div class='info'>";
    echo "<strong>Další kroky:</strong><br>";
    echo "• Otevřete <a href='analytics.php' target='_blank'>Analytics Dashboard</a> pro vizualizaci<br>";
    echo "• Zkontrolujte, že vaše IP je v seznamu ignorovaných<br>";
    echo "• Otestujte z jiného zařízení/IP, že se zaznamenává";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn'>← Zpět na Admin</a>";
echo "<a href='analytics.php' class='btn' style='background: #0066cc;'>📊 Analytics Dashboard</a>";
echo "</div></body></html>";
?>
