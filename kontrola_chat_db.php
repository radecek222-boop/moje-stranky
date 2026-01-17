<?php
/**
 * Diagnostický skript: Kontrola databáze chatu
 *
 * Tento skript zkontroluje zda:
 * 1. Existuje tabulka wgs_hry_chat
 * 2. Existuje tabulka wgs_hry_chat_likes
 * 3. Všechny potřebné sloupce existují
 * 4. Jsou v tabulkách nějaká data
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: Chat databáze</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th, table td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        table th { background: #333; color: white; }
        table tr:nth-child(even) { background: #f9f9f9; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        pre { background: #f8f8f8; padding: 15px; border-radius: 5px;
              border-left: 4px solid #333; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>📊 Diagnostika Chat Databáze</h1>";

    $problemy = [];
    $varovani = [];
    $uspech = [];

    // ============================================================================
    // 1. KONTROLA TABULKY wgs_hry_chat
    // ============================================================================

    echo "<h2>1️⃣ Kontrola tabulky <code>wgs_hry_chat</code></h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_hry_chat'");
    $tabulkaExistuje = $stmt->fetch() !== false;

    if (!$tabulkaExistuje) {
        $problemy[] = "Tabulka <code>wgs_hry_chat</code> NEEXISTUJE!";
        echo "<div class='error'><strong>❌ KRITICKÁ CHYBA:</strong> Tabulka <code>wgs_hry_chat</code> neexistuje!</div>";
        echo "<div class='info'>
            <strong>Řešení:</strong> Tabulka musí být vytvořena. Pravděpodobně chybí inicializační skript.<br>
            Zkontrolujte soubory v <code>/setup/</code> složce.
        </div>";
    } else {
        $uspech[] = "Tabulka <code>wgs_hry_chat</code> existuje";
        echo "<div class='success'>✅ Tabulka <code>wgs_hry_chat</code> existuje</div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_hry_chat");
        $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Struktura tabulky:</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

        $existujiciSloupce = [];
        foreach ($sloupce as $sloupec) {
            $existujiciSloupce[] = $sloupec['Field'];
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($sloupec['Field']) . "</code></td>";
            echo "<td>" . htmlspecialchars($sloupec['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Zkontrolovat požadované sloupce
        $pozadovaneSloupce = ['id', 'user_id', 'username', 'zprava', 'cas', 'likes_count', 'edited_at', 'mistnost_id'];
        $chybejiciSloupce = array_diff($pozadovaneSloupce, $existujiciSloupce);

        if (!empty($chybejiciSloupce)) {
            foreach ($chybejiciSloupce as $sloupec) {
                $varovani[] = "Sloupec <code>{$sloupec}</code> chybí v tabulce <code>wgs_hry_chat</code>";
            }
            echo "<div class='warning'>";
            echo "<strong>⚠️ CHYBĚJÍCÍ SLOUPCE:</strong><ul>";
            foreach ($chybejiciSloupce as $sloupec) {
                echo "<li><code>{$sloupec}</code>";
                if ($sloupec === 'likes_count') {
                    echo " - <a href='pridej_chat_likes.php'>Spustit migraci likes</a>";
                } elseif ($sloupec === 'edited_at') {
                    echo " - <a href='pridej_chat_edit.php'>Spustit migraci edit</a>";
                }
                echo "</li>";
            }
            echo "</ul></div>";
        } else {
            $uspech[] = "Všechny požadované sloupce existují v <code>wgs_hry_chat</code>";
            echo "<div class='success'>✅ Všechny požadované sloupce existují</div>";
        }

        // Zkontrolovat počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_hry_chat");
        $pocet = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

        echo "<div class='info'><strong>📊 Počet zpráv v databázi:</strong> {$pocet}</div>";

        if ($pocet == 0) {
            $varovani[] = "V tabulce <code>wgs_hry_chat</code> nejsou žádné zprávy";
            echo "<div class='warning'>⚠️ Tabulka je prázdná - zatím nebyly odeslány žádné zprávy</div>";
        } else {
            // Zobrazit posledních 5 zpráv
            $stmt = $pdo->query("
                SELECT id, user_id, username, zprava, cas,
                       COALESCE(likes_count, 0) as likes_count,
                       edited_at
                FROM wgs_hry_chat
                ORDER BY cas DESC
                LIMIT 5
            ");
            $zpravy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h3>Posledních 5 zpráv:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>User ID</th><th>Username</th><th>Zpráva</th><th>Čas</th><th>Likes</th><th>Upraveno</th></tr>";
            foreach ($zpravy as $zprava) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($zprava['id']) . "</td>";
                echo "<td>" . htmlspecialchars($zprava['user_id']) . "</td>";
                echo "<td>" . htmlspecialchars($zprava['username']) . "</td>";
                echo "<td>" . htmlspecialchars($zprava['zprava']) . "</td>";
                echo "<td>" . htmlspecialchars($zprava['cas']) . "</td>";
                echo "<td>" . htmlspecialchars($zprava['likes_count']) . "</td>";
                echo "<td>" . htmlspecialchars($zprava['edited_at'] ?? '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }

    // ============================================================================
    // 2. KONTROLA TABULKY wgs_hry_chat_likes
    // ============================================================================

    echo "<h2>2️⃣ Kontrola tabulky <code>wgs_hry_chat_likes</code></h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_hry_chat_likes'");
    $tabulkaLikesExistuje = $stmt->fetch() !== false;

    if (!$tabulkaLikesExistuje) {
        $varovani[] = "Tabulka <code>wgs_hry_chat_likes</code> neexistuje";
        echo "<div class='warning'>";
        echo "<strong>⚠️ VAROVÁNÍ:</strong> Tabulka <code>wgs_hry_chat_likes</code> neexistuje!<br>";
        echo "<strong>Řešení:</strong> <a href='pridej_chat_likes.php'>Spustit migraci likes</a>";
        echo "</div>";
    } else {
        $uspech[] = "Tabulka <code>wgs_hry_chat_likes</code> existuje";
        echo "<div class='success'>✅ Tabulka <code>wgs_hry_chat_likes</code> existuje</div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_hry_chat_likes");
        $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Struktura tabulky:</h3>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($sloupce as $sloupec) {
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($sloupec['Field']) . "</code></td>";
            echo "<td>" . htmlspecialchars($sloupec['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($sloupec['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Zkontrolovat počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_hry_chat_likes");
        $pocetLikes = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

        echo "<div class='info'><strong>📊 Počet likes v databázi:</strong> {$pocetLikes}</div>";
    }

    // ============================================================================
    // 3. TEST VLOŽENÍ A NAČTENÍ DAT
    // ============================================================================

    if ($tabulkaExistuje && isset($_GET['test']) && $_GET['test'] === '1') {
        echo "<h2>3️⃣ Test vložení a načtení dat</h2>";

        try {
            $testUserId = 'test_' . time();
            $testUsername = 'Test User';
            $testZprava = 'Testovací zpráva - ' . date('Y-m-d H:i:s');

            // Vložit testovací zprávu
            $stmt = $pdo->prepare("
                INSERT INTO wgs_hry_chat (user_id, username, zprava, cas)
                VALUES (:user_id, :username, :zprava, NOW())
            ");
            $stmt->execute([
                'user_id' => $testUserId,
                'username' => $testUsername,
                'zprava' => $testZprava
            ]);

            $testId = $pdo->lastInsertId();
            echo "<div class='success'>✅ Testovací zpráva vložena (ID: {$testId})</div>";

            // Načíst zprávu zpět
            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_chat WHERE id = :id");
            $stmt->execute(['id' => $testId]);
            $nactenaZprava = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($nactenaZprava) {
                echo "<div class='success'>✅ Testovací zpráva úspěšně načtena z databáze</div>";
                echo "<pre>" . print_r($nactenaZprava, true) . "</pre>";
            } else {
                echo "<div class='error'>❌ Nepodařilo se načíst testovací zprávu z databáze</div>";
            }

            // Smazat testovací zprávu
            $stmt = $pdo->prepare("DELETE FROM wgs_hry_chat WHERE id = :id");
            $stmt->execute(['id' => $testId]);
            echo "<div class='info'>🗑️ Testovací zpráva smazána</div>";

        } catch (PDOException $e) {
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI TESTU:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    }

    // ============================================================================
    // 4. SHRNUTÍ
    // ============================================================================

    echo "<h2>📋 Shrnutí</h2>";

    if (!empty($problemy)) {
        echo "<div class='error'><strong>❌ KRITICKÉ PROBLÉMY:</strong><ul>";
        foreach ($problemy as $problem) {
            echo "<li>{$problem}</li>";
        }
        echo "</ul></div>";
    }

    if (!empty($varovani)) {
        echo "<div class='warning'><strong>⚠️ VAROVÁNÍ:</strong><ul>";
        foreach ($varovani as $varovaniItem) {
            echo "<li>{$varovaniItem}</li>";
        }
        echo "</ul></div>";
    }

    if (!empty($uspech) && empty($problemy)) {
        echo "<div class='success'><strong>✅ VŠE V POŘÁDKU:</strong><ul>";
        foreach ($uspech as $uspechItem) {
            echo "<li>{$uspechItem}</li>";
        }
        echo "</ul></div>";
    }

    // Akční tlačítka
    echo "<hr style='margin: 30px 0;'>";
    echo "<h3>🔧 Nástroje</h3>";

    if ($tabulkaExistuje) {
        echo "<a href='?test=1' class='btn'>▶ Spustit test vložení/načtení dat</a>";
    }

    if (!$tabulkaLikesExistuje || !empty($chybejiciSloupce)) {
        echo "<a href='pridej_chat_likes.php' class='btn'>➕ Spustit migraci likes</a>";
        echo "<a href='pridej_chat_edit.php' class='btn'>➕ Spustit migraci edit</a>";
    }

    echo "<a href='hry.php' class='btn' style='background:#666;'>← Zpět do herní zóny</a>";
    echo "<a href='vsechny_tabulky.php' class='btn' style='background:#666;'>📊 Zobrazit všechny tabulky</a>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
