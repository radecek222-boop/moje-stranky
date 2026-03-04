<?php
/**
 * Debug skript: Test načítání chat zpráv
 *
 * Tento skript zkusí přesně stejný SELECT jako hry.php
 * a ukáže co vrací.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit test.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test načítání chat zpráv</title>
    <style>
        body { font-family: monospace; margin: 50px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #333; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; }
        pre { background: #f8f8f8; padding: 15px; border-left: 4px solid #333; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Test načítání chat zpráv z hry.php</h1>";

try {
    $pdo = getDbConnection();

    echo "<h2>1️⃣ Přesně stejný SQL dotaz jako v hry.php (řádky 44-52)</h2>";

    $sql = "
        SELECT c.id, c.user_id, c.username, c.zprava, c.cas,
               COALESCE(c.likes_count, 0) as likes_count,
               c.edited_at
        FROM wgs_hry_chat c
        WHERE c.mistnost_id IS NULL
        ORDER BY c.cas DESC
        LIMIT 200
    ";

    echo "<pre>" . htmlspecialchars($sql) . "</pre>";

    echo "<h2>2️⃣ Spuštění dotazu...</h2>";

    $stmtChat = $pdo->query($sql);
    $chatZpravy = $stmtChat->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='success'>✅ Dotaz proběhl ÚSPĚŠNĚ</div>";

    echo "<h2>3️⃣ Počet načtených zpráv: <strong>" . count($chatZpravy) . "</strong></h2>";

    if (empty($chatZpravy)) {
        echo "<div class='error'>❌ CHYBA: \$chatZpravy je prázdný array!</div>";
        echo "<p>Toto je důvod proč se na stránce hry.php zobrazuje 'Zatím žádné zprávy'</p>";

        // Zkusit bez ORDER BY DESC
        echo "<h2>4️⃣ Zkusím bez ORDER BY DESC...</h2>";
        $sql2 = "
            SELECT c.id, c.user_id, c.username, c.zprava, c.cas,
                   COALESCE(c.likes_count, 0) as likes_count,
                   c.edited_at
            FROM wgs_hry_chat c
            WHERE c.mistnost_id IS NULL
            LIMIT 200
        ";
        $stmt2 = $pdo->query($sql2);
        $test2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Počet zpráv bez ORDER BY: " . count($test2) . "</p>";

        // Zkusit úplně základní query
        echo "<h2>5️⃣ Zkusím úplně základní SELECT *...</h2>";
        $sql3 = "SELECT * FROM wgs_hry_chat WHERE mistnost_id IS NULL LIMIT 10";
        $stmt3 = $pdo->query($sql3);
        $test3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Počet zpráv ze základního SELECT: " . count($test3) . "</p>";

        if (count($test3) > 0) {
            echo "<pre>" . print_r($test3[0], true) . "</pre>";
        }

    } else {
        echo "<div class='success'>✅ Zprávy byly načteny!</div>";

        // array_reverse() jako v hry.php
        $chatZpravy = array_reverse($chatZpravy);

        echo "<h2>4️⃣ Prvních 5 zpráv (po array_reverse):</h2>";

        foreach (array_slice($chatZpravy, 0, 5) as $i => $zprava) {
            echo "<h3>Zpráva " . ($i + 1) . ":</h3>";
            echo "<pre>" . print_r($zprava, true) . "</pre>";

            // Zkusit formátování času jako v hry.php řádek 670
            echo "<p><strong>Formátovaný čas:</strong> ";
            echo date('j.n.Y H:i', strtotime($zprava['cas']));
            echo "</p>";
        }

        echo "<h2>5️⃣ Simulace HTML výstupu (jako v hry.php řádky 660-684):</h2>";

        echo "<div style='background: #1a1a1a; color: white; padding: 15px; border-radius: 8px;'>";

        foreach (array_slice($chatZpravy, 0, 5) as $zprava) {
            $likesCount = (int)($zprava['likes_count'] ?? 0);
            $jeUpravena = !empty($zprava['edited_at']);

            echo "<div style='border-bottom: 1px solid #333; padding: 10px; margin: 5px 0;'>";
            echo "<div style='display: flex; justify-content: space-between;'>";
            echo "<span style='font-weight: bold;'>" . htmlspecialchars($zprava['username']) . "</span>";
            echo "<span style='color: #999;'>" . date('j.n.Y H:i', strtotime($zprava['cas'])) . "</span>";
            if ($jeUpravena) {
                echo "<span style='color: #999; font-size: 0.8em;'>(upraveno)</span>";
            }
            echo "</div>";
            echo "<div style='margin-top: 5px;'>" . htmlspecialchars($zprava['zprava']) . "</div>";
            if ($likesCount > 0) {
                echo "<div style='color: #ff4444; font-size: 0.9em;'>♥ " . $likesCount . "</div>";
            }
            echo "</div>";
        }

        echo "</div>";
    }

    // Zkontrolovat co je v catch bloku
    echo "<h2>6️⃣ Zkontrolovat PHP error log</h2>";
    $logFile = __DIR__ . '/logs/php_errors.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $lines = explode("\n", $logContent);
        $lastLines = array_slice($lines, -20); // Posledních 20 řádků

        echo "<h3>Posledních 20 řádků z php_errors.log:</h3>";
        echo "<pre style='max-height: 300px; overflow-y: auto;'>";
        echo htmlspecialchars(implode("\n", $lastLines));
        echo "</pre>";
    } else {
        echo "<p>Log soubor neexistuje: " . htmlspecialchars($logFile) . "</p>";
    }

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ PDO CHYBA:</strong><br>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Code: " . htmlspecialchars($e->getCode()) . "<br>";
    echo "</div>";

    echo "<h2>TOTO JE PROBLÉM!</h2>";
    echo "<p>V hry.php na řádcích 68-72 je catch blok který při této chybě nastaví \$chatZpravy = []</p>";
    echo "<p>Proto se na stránce zobrazuje 'Zatím žádné zprávy'</p>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ OBECNÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><a href='hry.php' style='padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px;'>← Zpět do herní zóny</a>";

echo "</div></body></html>";
?>
