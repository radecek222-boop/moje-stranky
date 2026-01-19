<?php
/**
 * Oprava dokonceno_kym pro zakázku GREY M
 * Nastavit Milan Kolín (ID 9) jako toho, kdo zakázku dokončil
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
    <title>Oprava dokonceno_kym - GREY M</title>
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
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: 600; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; cursor: pointer;
               border: none; }
        .btn:hover { background: #1a1a1a; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Oprava dokonceno_kym - GREY M</h1>";
    echo "<p><strong>Zákazník:</strong> Pelikán Martin</p>";
    echo "<p><strong>Problém:</strong> Sloupec dokonceno_kym obsahuje Radek Zikmund, ale měl by být Milan Kolín</p>";

    // Zkontrolovat zda existuje sloupec dokonceno_kym
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'dokonceno_kym'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ SLOUPEC dokonceno_kym NEEXISTUJE</strong><br>";
        echo "Tento sloupec není v databázi, takže oprava není potřeba.";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    // Načíst aktuální stav
    $stmt = $pdo->prepare("
        SELECT cislo, jmeno, assigned_to, technik, created_by, dokonceno_kym
        FROM wgs_reklamace
        WHERE cislo LIKE :cislo
        LIMIT 1
    ");
    $stmt->execute(['cislo' => '%GREY%M%']);
    $zaznam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zaznam) {
        echo "<div class='error'><strong>CHYBA:</strong> Záznam GREY M nebyl nalezen v databázi.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'><strong>AKTUÁLNÍ STAV:</strong></div>";
    echo "<table>";
    echo "<tr><th>Pole</th><th>Hodnota</th></tr>";
    echo "<tr><td>Číslo zakázky</td><td>{$zaznam['cislo']}</td></tr>";
    echo "<tr><td>Jméno zákazníka</td><td>{$zaznam['jmeno']}</td></tr>";
    echo "<tr><td>assigned_to</td><td><code>" . ($zaznam['assigned_to'] ?? 'NULL') . "</code></td></tr>";
    echo "<tr><td>technik</td><td><code>" . ($zaznam['technik'] ?? 'NULL') . "</code></td></tr>";
    echo "<tr><td>created_by</td><td><code>" . ($zaznam['created_by'] ?? 'NULL') . "</code></td></tr>";
    echo "<tr><td><strong>dokonceno_kym</strong></td><td><strong style='color: red;'><code>" . ($zaznam['dokonceno_kym'] ?? 'NULL') . "</code></strong></td></tr>";
    echo "</table>";

    // Zjistit kdo je ID 16 (pravděpodobně Radek Zikmund)
    if ($zaznam['dokonceno_kym']) {
        $stmt = $pdo->prepare("SELECT id, name FROM wgs_users WHERE id = :id");
        $stmt->execute(['id' => $zaznam['dokonceno_kym']]);
        $staryTechnik = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staryTechnik) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ PROBLÉM NALEZEN:</strong><br>";
            echo "Sloupec <code>dokonceno_kym</code> = <strong>{$staryTechnik['id']}</strong> ({$staryTechnik['name']})<br>";
            echo "Ale měl by být: <strong>9</strong> (Milan Kolín)";
            echo "</div>";
        }
    }

    // Milan Kolín
    $stmt = $pdo->prepare("SELECT id, name FROM wgs_users WHERE id = 9 LIMIT 1");
    $stmt->execute();
    $milanKolin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$milanKolin) {
        echo "<div class='error'><strong>CHYBA:</strong> Milan Kolín (ID 9) nebyl nalezen.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>";
    echo "<strong>Milan Kolín v databázi:</strong><br>";
    echo "id: <code>{$milanKolin['id']}</code><br>";
    echo "name: <code>{$milanKolin['name']}</code>";
    echo "</div>";

    // Pokud je execute=1, provést opravu
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET dokonceno_kym = :milan_id
            WHERE cislo = :cislo
        ");

        $vysledek = $stmt->execute([
            'milan_id' => $milanKolin['id'],
            'cislo' => $zaznam['cislo']
        ]);

        if ($vysledek) {
            echo "<div class='success'>";
            echo "<strong>✓ ZAKÁZKA ÚSPĚŠNĚ OPRAVENA</strong><br>";
            echo "Sloupec <code>dokonceno_kym</code> změněn na: <strong>Milan Kolín (ID 9)</strong>";
            echo "</div>";

            // Zobrazit nový stav
            $stmt = $pdo->prepare("SELECT assigned_to, technik, dokonceno_kym FROM wgs_reklamace WHERE cislo = :cislo");
            $stmt->execute(['cislo' => $zaznam['cislo']]);
            $novy = $stmt->fetch(PDO::FETCH_ASSOC);

            echo "<div class='info'>";
            echo "<strong>NOVÝ STAV:</strong><br>";
            echo "assigned_to: <strong>{$novy['assigned_to']}</strong><br>";
            echo "technik: <strong>{$novy['technik']}</strong><br>";
            echo "dokonceno_kym: <strong>{$novy['dokonceno_kym']}</strong>";
            echo "</div>";

            echo "<p><a href='/vymaz_cache.php' class='btn'>Vymazat cache</a>";
            echo "<a href='/statistiky.php' class='btn'>Otevřít statistiky</a></p>";
        } else {
            echo "<div class='error'><strong>CHYBA:</strong> Aktualizace selhala.</div>";
        }
    } else {
        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>PROVÉST OPRAVU (nastavit dokonceno_kym)</button>";
        echo "</form>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
