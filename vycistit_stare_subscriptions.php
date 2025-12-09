<?php
/**
 * Vycisteni starych/duplicitnich push subscriptions
 *
 * Safari vytvari novou subscription pri kazdem spusteni PWA.
 * Tento skript ponecha jen nejnovejsi subscription pro kazdeho uzivatele
 * a smaze stare/nefunkcni.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Vycisteni Subscriptions</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #222; border-bottom: 3px solid #222; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #222; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer;
               font-size: 16px; }
        .btn:hover { background: #444; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
<div class='container'>
<h1>Vycisteni Starych Push Subscriptions</h1>
<p>Spusteno: " . date('Y-m-d H:i:s') . "</p>";

try {
    $pdo = getDbConnection();

    // =====================================================
    // 1. AKTUALNI STAV
    // =====================================================
    echo "<h2>1. Aktualni Stav</h2>";

    // Celkove statistiky
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as celkem,
            SUM(CASE WHEN aktivni = 1 THEN 1 ELSE 0 END) as aktivni,
            SUM(CASE WHEN aktivni = 0 THEN 1 ELSE 0 END) as neaktivni
        FROM wgs_push_subscriptions
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>Metrika</th><th>Hodnota</th></tr>
        <tr><td>Celkem subscriptions</td><td><strong>{$stats['celkem']}</strong></td></tr>
        <tr><td>Aktivni</td><td>{$stats['aktivni']}</td></tr>
        <tr><td>Neaktivni</td><td>{$stats['neaktivni']}</td></tr>
    </table>";

    // Subscriptions podle uzivatele
    $stmt = $pdo->query("
        SELECT
            user_id,
            COUNT(*) as pocet,
            MIN(datum_vytvoreni) as nejstarsi,
            MAX(datum_vytvoreni) as nejnovejsi
        FROM wgs_push_subscriptions
        WHERE aktivni = 1
        GROUP BY user_id
        HAVING COUNT(*) > 1
        ORDER BY pocet DESC
        LIMIT 20
    ");
    $duplicity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($duplicity)) {
        echo "<h3>Uzivatele s vice nez 1 subscription (duplicity)</h3>";
        echo "<table>
            <tr><th>User ID</th><th>Pocet Subscriptions</th><th>Nejstarsi</th><th>Nejnovejsi</th><th>K odstraneni</th></tr>";

        $celkemKOdstraneni = 0;
        foreach ($duplicity as $row) {
            $kOdstraneni = $row['pocet'] - 1; // Ponechat jen 1 nejnovejsi
            $celkemKOdstraneni += $kOdstraneni;
            echo "<tr>
                <td>" . ($row['user_id'] ?? 'NULL') . "</td>
                <td><strong>{$row['pocet']}</strong></td>
                <td>{$row['nejstarsi']}</td>
                <td>{$row['nejnovejsi']}</td>
                <td style='color: red;'>{$kOdstraneni}</td>
            </tr>";
        }
        echo "</table>";

        echo "<div class='warning'>
            <strong>Nalezeno {$celkemKOdstraneni} duplicitnich subscriptions</strong> k odstraneni.
            Pro kazdeho uzivatele bude ponechana pouze nejnovejsi subscription.
        </div>";
    } else {
        echo "<div class='success'>Zadne duplicitni subscriptions nenalezeny.</div>";
    }

    // Stare neaktivni subscriptions
    $stmt = $pdo->query("
        SELECT COUNT(*) as pocet FROM wgs_push_subscriptions WHERE aktivni = 0
    ");
    $neaktivniPocet = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

    if ($neaktivniPocet > 0) {
        echo "<div class='info'>
            <strong>{$neaktivniPocet} neaktivnich subscriptions</strong> bude odstraneno.
        </div>";
    }

    // Subscriptions stare vice nez 7 dni bez uspesneho odeslani
    $stmt = $pdo->query("
        SELECT COUNT(*) as pocet
        FROM wgs_push_subscriptions
        WHERE aktivni = 1
        AND posledni_uspesne_odeslani IS NULL
        AND datum_vytvoreni < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stareBezUspechu = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

    if ($stareBezUspechu > 0) {
        echo "<div class='info'>
            <strong>{$stareBezUspechu} starych subscriptions</strong> (7+ dni) bez uspesneho odeslani bude deaktivovano.
        </div>";
    }

    // =====================================================
    // 2. PROVEDENI CISTENI
    // =====================================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>2. Provadim Cisteni...</h2>";

        $smazano = 0;
        $deaktivovano = 0;

        // A) Smazat neaktivni subscriptions
        $stmt = $pdo->exec("DELETE FROM wgs_push_subscriptions WHERE aktivni = 0");
        $smazanoNeaktivni = $stmt;
        echo "<div class='success'>Smazano {$smazanoNeaktivni} neaktivnich subscriptions.</div>";
        $smazano += $smazanoNeaktivni;

        // B) Pro kazdeho uzivatele ponechat jen nejnovejsi subscription
        // Nejprve zjistit ID nejnovejsich subscriptions pro kazdeho uzivatele
        $stmt = $pdo->query("
            SELECT MAX(id) as keep_id, user_id
            FROM wgs_push_subscriptions
            WHERE aktivni = 1 AND user_id IS NOT NULL
            GROUP BY user_id
        ");
        $keepIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!empty($keepIds)) {
            $keepIdsStr = implode(',', $keepIds);

            // Smazat duplicity (vsechny krome nejnovejsich)
            $stmt = $pdo->prepare("
                DELETE FROM wgs_push_subscriptions
                WHERE aktivni = 1
                AND user_id IS NOT NULL
                AND id NOT IN ({$keepIdsStr})
            ");
            $stmt->execute();
            $smazanoDuplicit = $stmt->rowCount();
            echo "<div class='success'>Smazano {$smazanoDuplicit} duplicitnich subscriptions.</div>";
            $smazano += $smazanoDuplicit;
        }

        // C) Deaktivovat stare subscriptions bez uspesneho odeslani
        $stmt = $pdo->exec("
            UPDATE wgs_push_subscriptions
            SET aktivni = 0
            WHERE aktivni = 1
            AND posledni_uspesne_odeslani IS NULL
            AND datum_vytvoreni < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $deaktivovano = $stmt;
        echo "<div class='success'>Deaktivovano {$deaktivovano} starych subscriptions bez uspesneho odeslani.</div>";

        // D) Smazat nove deaktivovane
        $stmt = $pdo->exec("DELETE FROM wgs_push_subscriptions WHERE aktivni = 0");
        $smazanoDeaktivovanych = $stmt;
        if ($smazanoDeaktivovanych > 0) {
            echo "<div class='success'>Smazano {$smazanoDeaktivovanych} deaktivovanych subscriptions.</div>";
            $smazano += $smazanoDeaktivovanych;
        }

        // =====================================================
        // 3. VYSLEDEK
        // =====================================================
        echo "<h2>3. Vysledek</h2>";

        // Nove statistiky
        $stmt = $pdo->query("
            SELECT
                COUNT(*) as celkem,
                SUM(CASE WHEN aktivni = 1 THEN 1 ELSE 0 END) as aktivni
            FROM wgs_push_subscriptions
        ");
        $noveStats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<table>
            <tr><th>Metrika</th><th>Pred</th><th>Po</th><th>Rozdil</th></tr>
            <tr>
                <td>Celkem subscriptions</td>
                <td>{$stats['celkem']}</td>
                <td><strong>{$noveStats['celkem']}</strong></td>
                <td style='color: green;'>-" . ($stats['celkem'] - $noveStats['celkem']) . "</td>
            </tr>
            <tr>
                <td>Aktivni</td>
                <td>{$stats['aktivni']}</td>
                <td><strong>{$noveStats['aktivni']}</strong></td>
                <td>-" . ($stats['aktivni'] - $noveStats['aktivni']) . "</td>
            </tr>
        </table>";

        echo "<div class='success'>
            <strong>CISTENI DOKONCENO!</strong><br>
            Celkem smazano: {$smazano} subscriptions<br>
            Deaktivovano: {$deaktivovano} subscriptions<br><br>
            Push notifikace budou nyni rychlejsi a spolehlivejsi.
        </div>";

        echo "<a href='/diagnostika_push_notifikace.php' class='btn'>Zpet na diagnostiku</a>";
        echo "<a href='/test_push_detail.php' class='btn'>Otestovat push</a>";

    } else {
        // =====================================================
        // NAHLED
        // =====================================================
        echo "<h2>2. Co bude provedeno</h2>";

        echo "<ol>
            <li>Smazat vsechny <strong>neaktivni</strong> subscriptions ({$neaktivniPocet})</li>
            <li>Pro kazdeho uzivatele ponechat <strong>pouze nejnovejsi</strong> subscription</li>
            <li>Deaktivovat subscriptions <strong>starsi 7 dni</strong> bez uspesneho odeslani ({$stareBezUspechu})</li>
            <li>Smazat vsechny deaktivovane subscriptions</li>
        </ol>";

        $celkemOdhad = $neaktivniPocet + (isset($celkemKOdstraneni) ? $celkemKOdstraneni : 0) + $stareBezUspechu;

        echo "<div class='warning'>
            <strong>Odhadovany pocet smazanych subscriptions:</strong> ~{$celkemOdhad}<br>
            Tato akce je <strong>nevratna</strong>. Uzivatele si budou muset znovu povolit notifikace.
        </div>";

        echo "<a href='?execute=1' class='btn btn-danger'>SPUSTIT CISTENI</a>";
        echo "<a href='/diagnostika_push_notifikace.php' class='btn' style='background: #666;'>Zrusit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div></body></html>";
?>
