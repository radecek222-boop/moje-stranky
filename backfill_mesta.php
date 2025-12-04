<?php
/**
 * Backfill měst pro pageviews
 * Doplní město pro záznamy kde chybí
 *
 * POZOR: Používá geolokační API s rate limity:
 * - ipapi.co: 1500 požadavků/den
 * - ip-api.com: 45 požadavků/min
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/GeolocationService.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Přístup odepřen - pouze pro administrátory");
}

header('Content-Type: text/html; charset=utf-8');

$pdo = getDbConnection();
$geoService = new GeolocationService($pdo);

// Parametry
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // Max IP adres na jedno spuštění
$execute = isset($_GET['execute']) && $_GET['execute'] === '1';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Backfill měst</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0; }
        .stat { background: #f9f9f9; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #333; }
        .stat-label { font-size: 0.8rem; color: #666; }
        .ok { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; }
        .btn { display: inline-block; padding: 12px 24px; background: #333; color: white; text-decoration: none; border-radius: 8px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        .btn-danger { background: #c00; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class='container'>
<h1>Backfill měst pro Analytics</h1>";

// Statistiky
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) as celkem,
        SUM(CASE WHEN city IS NOT NULL AND city != '' THEN 1 ELSE 0 END) as s_mestem,
        SUM(CASE WHEN city IS NULL OR city = '' THEN 1 ELSE 0 END) as bez_mesta,
        COUNT(DISTINCT ip_address) as unikatnich_ip
    FROM wgs_pageviews
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

echo "<div class='stats'>
    <div class='stat'>
        <div class='stat-value'>{$stats['celkem']}</div>
        <div class='stat-label'>Celkem pageviews</div>
    </div>
    <div class='stat'>
        <div class='stat-value ok'>{$stats['s_mestem']}</div>
        <div class='stat-label'>S městem</div>
    </div>
    <div class='stat'>
        <div class='stat-value error'>{$stats['bez_mesta']}</div>
        <div class='stat-label'>Bez města</div>
    </div>
</div>";

// Najít unikátní IP bez města
$ipStmt = $pdo->prepare("
    SELECT DISTINCT ip_address, country_code, COUNT(*) as pocet
    FROM wgs_pageviews
    WHERE (city IS NULL OR city = '')
      AND ip_address IS NOT NULL
      AND ip_address != ''
    GROUP BY ip_address, country_code
    ORDER BY pocet DESC
    LIMIT :limit
");
$ipStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$ipStmt->execute();
$ipAdresy = $ipStmt->fetchAll(PDO::FETCH_ASSOC);

$pocetIP = count($ipAdresy);
echo "<p>Nalezeno <strong>$pocetIP</strong> unikátních IP adres bez města (limit: $limit)</p>";

if ($pocetIP === 0) {
    echo "<p class='ok'>Všechny záznamy již mají město!</p>";
    echo "<p><a href='analytics.php' class='btn'>Zpět na Analytics</a></p>";
    echo "</div></body></html>";
    exit;
}

if (!$execute) {
    // Náhled
    echo "<h2>Náhled - IP adresy k doplnění</h2>";
    echo "<table>
        <tr><th>IP adresa</th><th>Země</th><th>Počet pageviews</th></tr>";

    foreach ($ipAdresy as $ip) {
        echo "<tr>
            <td><code>{$ip['ip_address']}</code></td>
            <td>{$ip['country_code']}</td>
            <td>{$ip['pocet']}</td>
        </tr>";
    }
    echo "</table>";

    echo "<p class='warning'>
        <strong>Upozornění:</strong> Geolokační API má limity (ipapi.co: 1500/den).
        Spouštějte postupně s menším limitem.
    </p>";

    echo "<a href='?execute=1&limit=$limit' class='btn'>Spustit backfill ($pocetIP IP)</a>";
    echo "<a href='?limit=20' class='btn'>Limit 20</a>";
    echo "<a href='?limit=100' class='btn'>Limit 100</a>";
    echo "<a href='analytics.php' class='btn'>Zpět</a>";

} else {
    // Spustit backfill
    echo "<h2>Probíhá backfill...</h2>";
    echo "<table>
        <tr><th>IP</th><th>Město</th><th>Aktualizováno</th><th>Stav</th></tr>";

    $uspesnych = 0;
    $chyb = 0;

    foreach ($ipAdresy as $ip) {
        $ipAddress = $ip['ip_address'];

        // Získat geolokaci
        $geoData = $geoService->getLocationFromIP($ipAddress);
        $mesto = $geoData['city'] ?? null;

        if ($mesto) {
            // Aktualizovat všechny pageviews s touto IP
            $updateStmt = $pdo->prepare("
                UPDATE wgs_pageviews
                SET city = :city,
                    country_code = COALESCE(country_code, :country_code)
                WHERE ip_address = :ip
                  AND (city IS NULL OR city = '')
            ");
            $updateStmt->execute([
                'city' => $mesto,
                'country_code' => $geoData['country_code'],
                'ip' => $ipAddress
            ]);
            $aktualizovano = $updateStmt->rowCount();

            echo "<tr>
                <td><code>$ipAddress</code></td>
                <td class='ok'>$mesto</td>
                <td>$aktualizovano</td>
                <td class='ok'>OK</td>
            </tr>";
            $uspesnych++;
        } else {
            echo "<tr>
                <td><code>$ipAddress</code></td>
                <td class='error'>-</td>
                <td>0</td>
                <td class='error'>API nevrátilo město</td>
            </tr>";
            $chyb++;
        }

        // Pauza mezi požadavky (ochrana rate limitu)
        usleep(200000); // 200ms = max 5 req/s

        flush();
        ob_flush();
    }

    echo "</table>";

    echo "<h3>Výsledek</h3>";
    echo "<p class='ok'>Úspěšně doplněno: <strong>$uspesnych</strong> IP adres</p>";
    if ($chyb > 0) {
        echo "<p class='error'>Neúspěšných: <strong>$chyb</strong> (API nevrátilo město)</p>";
    }

    // Nové statistiky
    $newStats = $pdo->query("
        SELECT
            SUM(CASE WHEN city IS NOT NULL AND city != '' THEN 1 ELSE 0 END) as s_mestem,
            SUM(CASE WHEN city IS NULL OR city = '' THEN 1 ELSE 0 END) as bez_mesta
        FROM wgs_pageviews
    ")->fetch(PDO::FETCH_ASSOC);

    echo "<p>Nový stav: <strong class='ok'>{$newStats['s_mestem']}</strong> s městem,
          <strong class='error'>{$newStats['bez_mesta']}</strong> bez města</p>";

    if ($newStats['bez_mesta'] > 0) {
        echo "<p><a href='?execute=1&limit=$limit' class='btn'>Pokračovat v backfillu</a></p>";
    }

    echo "<p><a href='analytics.php' class='btn'>Zpět na Analytics</a></p>";
}

echo "</div></body></html>";
?>
