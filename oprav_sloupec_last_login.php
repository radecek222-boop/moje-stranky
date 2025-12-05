<?php
/**
 * Migrace: Oprava sloupce last_login v tabulce wgs_users
 *
 * Tento skript BEZPECNE opravi sloupec pro sledovani posledniho prihlaseni.
 * Kontroluje a resi rozpor mezi last_login a last_login_at.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Oprava sloupce last_login</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
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
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava sloupce last_login</h1>";

    // 1. Zjistit aktualni stav sloupcu
    echo "<h2>1. Analyza struktury tabulky wgs_users</h2>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $maLastLogin = false;
    $maLastLoginAt = false;
    $typLastLogin = null;
    $typLastLoginAt = null;

    echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Default</th></tr>";
    foreach ($sloupce as $sloupec) {
        $jmeno = $sloupec['Field'];
        $highlight = '';

        if ($jmeno === 'last_login') {
            $maLastLogin = true;
            $typLastLogin = $sloupec['Type'];
            $highlight = ' style="background: #d4edda;"';
        }
        if ($jmeno === 'last_login_at') {
            $maLastLoginAt = true;
            $typLastLoginAt = $sloupec['Type'];
            $highlight = ' style="background: #fff3cd;"';
        }

        echo "<tr{$highlight}>";
        echo "<td><code>{$jmeno}</code></td>";
        echo "<td>{$sloupec['Type']}</td>";
        echo "<td>{$sloupec['Null']}</td>";
        echo "<td>{$sloupec['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. Zobrazit vysledek analyzy
    echo "<h2>2. Vysledek analyzy</h2>";

    if ($maLastLogin && !$maLastLoginAt) {
        echo "<div class='success'>";
        echo "<strong>STAV: OK</strong><br>";
        echo "Sloupec <code>last_login</code> existuje. Zadna migrace neni potreba.";
        echo "</div>";

    } elseif (!$maLastLogin && $maLastLoginAt) {
        echo "<div class='warning'>";
        echo "<strong>STAV: POTREBA MIGRACE</strong><br>";
        echo "Existuje sloupec <code>last_login_at</code>, ale kod ocekava <code>last_login</code>.<br>";
        echo "Migrace prejmenuje sloupec.";
        echo "</div>";

    } elseif ($maLastLogin && $maLastLoginAt) {
        echo "<div class='warning'>";
        echo "<strong>STAV: DUPLICITNI SLOUPCE</strong><br>";
        echo "Existuji oba sloupce: <code>last_login</code> i <code>last_login_at</code>.<br>";
        echo "Migrace prenese data z <code>last_login_at</code> do <code>last_login</code> a smaze duplicitni sloupec.";
        echo "</div>";

    } else {
        echo "<div class='error'>";
        echo "<strong>STAV: CHYBI SLOUPEC</strong><br>";
        echo "Neexistuje ani <code>last_login</code> ani <code>last_login_at</code>.<br>";
        echo "Migrace vytvori sloupec <code>last_login</code>.";
        echo "</div>";
    }

    // 3. Zobrazit aktualni data
    echo "<h2>3. Aktualni data uzivatelu</h2>";

    $selectSloupce = "id, name, email, role";
    if ($maLastLogin) $selectSloupce .= ", last_login";
    if ($maLastLoginAt) $selectSloupce .= ", last_login_at";

    $stmt = $pdo->query("SELECT {$selectSloupce} FROM wgs_users ORDER BY id DESC LIMIT 10");
    $uzivatele = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($uzivatele)) {
        echo "<table><tr><th>ID</th><th>Jmeno</th><th>Email</th><th>Role</th>";
        if ($maLastLogin) echo "<th>last_login</th>";
        if ($maLastLoginAt) echo "<th>last_login_at</th>";
        echo "</tr>";

        foreach ($uzivatele as $uzivatel) {
            echo "<tr>";
            echo "<td>{$uzivatel['id']}</td>";
            echo "<td>{$uzivatel['name']}</td>";
            echo "<td>{$uzivatel['email']}</td>";
            echo "<td>{$uzivatel['role']}</td>";
            if ($maLastLogin) echo "<td>" . ($uzivatel['last_login'] ?? '<em>NULL</em>') . "</td>";
            if ($maLastLoginAt) echo "<td>" . ($uzivatel['last_login_at'] ?? '<em>NULL</em>') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // 4. Spusteni migrace
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>4. Provadeni migrace</h2>";

        $pdo->beginTransaction();

        try {
            if (!$maLastLogin && $maLastLoginAt) {
                // Prejmenovat last_login_at na last_login
                echo "<div class='info'>Prejmenuvam sloupec <code>last_login_at</code> na <code>last_login</code>...</div>";
                $pdo->exec("ALTER TABLE wgs_users CHANGE COLUMN last_login_at last_login DATETIME DEFAULT NULL");
                echo "<div class='success'>Sloupec prejmenovan.</div>";

            } elseif ($maLastLogin && $maLastLoginAt) {
                // Prenest data a smazat duplicitni sloupec
                echo "<div class='info'>Prenaším data z <code>last_login_at</code> do <code>last_login</code>...</div>";
                $pdo->exec("UPDATE wgs_users SET last_login = COALESCE(last_login, last_login_at) WHERE last_login IS NULL AND last_login_at IS NOT NULL");
                echo "<div class='success'>Data prenesena.</div>";

                echo "<div class='info'>Mazam duplicitni sloupec <code>last_login_at</code>...</div>";
                $pdo->exec("ALTER TABLE wgs_users DROP COLUMN last_login_at");
                echo "<div class='success'>Duplicitni sloupec smazan.</div>";

            } elseif (!$maLastLogin && !$maLastLoginAt) {
                // Vytvorit novy sloupec
                echo "<div class='info'>Vytvarim sloupec <code>last_login</code>...</div>";
                $pdo->exec("ALTER TABLE wgs_users ADD COLUMN last_login DATETIME DEFAULT NULL");
                echo "<div class='success'>Sloupec vytvoren.</div>";

            } else {
                echo "<div class='info'>Zadna migrace neni potreba.</div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE USPESNE DOKONCENA</strong><br>";
            echo "Sloupec <code>last_login</code> je nyni spravne nastaven.";
            echo "</div>";

            // Zobrazit finalni stav
            echo "<h3>Finalni stav:</h3>";
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users LIKE 'last%'");
            $finalniSloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Default</th></tr>";
            foreach ($finalniSloupce as $sloupec) {
                echo "<tr>";
                echo "<td><code>{$sloupec['Field']}</code></td>";
                echo "<td>{$sloupec['Type']}</td>";
                echo "<td>{$sloupec['Null']}</td>";
                echo "<td>{$sloupec['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Zobrazit tlacitko pro spusteni
        if (!$maLastLogin || $maLastLoginAt) {
            echo "<h2>4. Spusteni migrace</h2>";
            echo "<p>Kliknete pro spusteni migrace:</p>";
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        } else {
            echo "<div class='success'>";
            echo "<strong>Zadna migrace neni potreba.</strong><br>";
            echo "Sloupec <code>last_login</code> je spravne nastaven.";
            echo "</div>";
        }
    }

    echo "<p style='margin-top: 30px;'><a href='/admin.php' class='btn'>Zpet do admin panelu</a></p>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
