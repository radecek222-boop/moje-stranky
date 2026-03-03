<?php
/**
 * Čištění analytics - odstranění návštěv přihlášených uživatelů
 *
 * Smaže záznamy z wgs_pageviews kde user_id IS NOT NULL
 * (prodejci, technici, admin přihlášení do systému)
 *
 * Bezpečné: lze spustit vícekrát, anonymní návštěvy zůstanou nedotčeny.
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tuto migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Čištění analytics - přihlášení uživatelé</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #111; border-bottom: 2px solid #333; padding-bottom: 10px; font-size: 1.4rem; }
        .success { background: #f0f0f0; border: 1px solid #999; color: #111;
                   padding: 12px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #f5f5f5; border: 1px solid #bbb; color: #333;
                   padding: 12px; border-radius: 4px; margin: 10px 0; }
        .info    { background: #fafafa; border: 1px solid #ccc; color: #333;
                   padding: 12px; border-radius: 4px; margin: 10px 0; }
        .error   { background: #eee; border: 2px solid #555; color: #000;
                   padding: 12px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #222; color: #fff; padding: 8px 12px; text-align: left; font-size: 0.85rem; }
        td { padding: 7px 12px; border-bottom: 1px solid #eee; font-size: 0.85rem; }
        tr:hover td { background: #f9f9f9; }
        .btn { display: inline-block; padding: 10px 24px; background: #222; color: white;
               text-decoration: none; border-radius: 4px; margin: 10px 5px 10px 0;
               font-size: 0.9rem; cursor: pointer; border: none; }
        .btn:hover { background: #444; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .cislo { font-weight: 700; font-size: 1.2rem; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // Kontrola jestli tabulka existuje
    $tabulkaCheck = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
    if ($tabulkaCheck->rowCount() === 0) {
        echo "<div class='error'><strong>Tabulka wgs_pageviews neexistuje.</strong></div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<h1>Čištění analytics — přihlášení uživatelé</h1>";

    // Statistiky před čištěním
    $celkem     = $pdo->query("SELECT COUNT(*) FROM wgs_pageviews")->fetchColumn();
    $prihlaseni = $pdo->query("SELECT COUNT(*) FROM wgs_pageviews WHERE user_id IS NOT NULL")->fetchColumn();
    $anonymni   = $pdo->query("SELECT COUNT(*) FROM wgs_pageviews WHERE user_id IS NULL")->fetchColumn();

    echo "<div class='info'>
        <strong>Aktuální stav tabulky wgs_pageviews:</strong><br><br>
        <table>
            <tr><th>Typ záznamu</th><th>Počet</th><th>Podíl</th></tr>
            <tr><td>Celkem záznamů</td><td class='cislo'>" . number_format($celkem, 0, ',', ' ') . "</td><td>100 %</td></tr>
            <tr><td>Anonymní návštěvníci (zachovat)</td><td class='cislo'>" . number_format($anonymni, 0, ',', ' ') . "</td>
                <td>" . ($celkem > 0 ? round($anonymni / $celkem * 100, 1) : 0) . " %</td></tr>
            <tr><td>Přihlášení uživatelé (smazat)</td><td class='cislo'>" . number_format($prihlaseni, 0, ',', ' ') . "</td>
                <td>" . ($celkem > 0 ? round($prihlaseni / $celkem * 100, 1) : 0) . " %</td></tr>
        </table>
    </div>";

    // Přehled podle rolí (user_id prefix: PRO = prodejce, TCH = technik, ADMIN)
    $podleRole = $pdo->query("
        SELECT
            CASE
                WHEN user_id LIKE 'PRO%' THEN 'Prodejce'
                WHEN user_id LIKE 'TCH%' THEN 'Technik'
                WHEN user_id LIKE 'ADMIN%' THEN 'Admin'
                ELSE 'Jiný'
            END AS role,
            COUNT(*) AS pocet
        FROM wgs_pageviews
        WHERE user_id IS NOT NULL
        GROUP BY role
        ORDER BY pocet DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($podleRole)) {
        echo "<div class='info'><strong>Záznamy přihlášených uživatelů podle role:</strong><br><br><table>
            <tr><th>Role</th><th>Počet záznamů ke smazání</th></tr>";
        foreach ($podleRole as $radek) {
            echo "<tr><td>{$radek['role']}</td><td class='cislo'>" . number_format($radek['pocet'], 0, ',', ' ') . "</td></tr>";
        }
        echo "</table></div>";
    }

    if (isset($_GET['provest']) && $_GET['provest'] === '1') {

        // Spustit mazání
        $smazano = $pdo->exec("DELETE FROM wgs_pageviews WHERE user_id IS NOT NULL");

        echo "<div class='success'>
            <strong>Hotovo.</strong> Smazáno <strong class='cislo'>" . number_format($smazano, 0, ',', ' ') . "</strong>
            záznamů přihlášených uživatelů.<br><br>
            Zbývá <strong>" . number_format($anonymni, 0, ',', ' ') . "</strong> anonymních návštěv.
        </div>";

        echo "<a href='analytics.php' class='btn'>Zpět do Analytics</a>";

    } else {

        if ($prihlaseni === 0) {
            echo "<div class='success'><strong>Žádné záznamy přihlášených uživatelů nenalezeny.</strong> Analytics jsou čisté.</div>";
        } else {
            echo "<div class='warning'>
                <strong>Bude smazáno:</strong> " . number_format($prihlaseni, 0, ',', ' ') . " záznamů přihlášených uživatelů.<br>
                Anonymní návštěvy (" . number_format($anonymni, 0, ',', ' ') . " záznamů) zůstanou nedotčeny.<br><br>
                Tato operace je <strong>nevratná</strong>.
            </div>";
            echo "<a href='?provest=1' class='btn btn-danger'>Smazat záznamy přihlášených uživatelů</a>
                  <a href='analytics.php' class='btn'>Zrušit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>Chyba:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
