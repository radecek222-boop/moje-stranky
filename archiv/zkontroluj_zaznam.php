<?php
/**
 * Diagnostický skript pro kontrolu záznamu v databázi
 *
 * Použití: https://www.wgs-service.cz/zkontroluj_zaznam.php?id=NCC-04040044
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
    <title>Kontrola záznamu v databázi</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #666;
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    $hledaneId = $_GET['id'] ?? null;

    if (!$hledaneId) {
        echo "<h1>Kontrola záznamu v databázi</h1>";
        echo "<div class='info'>Zadejte ID v URL: ?id=NCC-04040044</div>";
        echo "<form method='get'>";
        echo "<input type='text' name='id' placeholder='Zadejte ID...' style='padding: 10px; width: 300px;'>";
        echo "<button type='submit' class='btn'>Hledat</button>";
        echo "</form>";
        echo "</div></body></html>";
        exit;
    }

    echo "<h1>Kontrola záznamu: " . htmlspecialchars($hledaneId) . "</h1>";

    // 1. PŘESNÁ SHODA - reklamace_id
    echo "<h2>1. Hledání v sloupci reklamace_id</h2>";
    $stmt1 = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE reklamace_id = :id LIMIT 1");
    $stmt1->execute(['id' => $hledaneId]);
    $vysledek1 = $stmt1->fetch(PDO::FETCH_ASSOC);

    if ($vysledek1) {
        echo "<div class='success'>✅ ZÁZNAM NALEZEN v sloupci <strong>reklamace_id</strong></div>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Hodnota</th></tr>";
        foreach ($vysledek1 as $sloupec => $hodnota) {
            echo "<tr><td><code>{$sloupec}</code></td><td>" . htmlspecialchars($hodnota ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ Záznam NENALEZEN v sloupci reklamace_id</div>";
    }

    // 2. PŘESNÁ SHODA - cislo
    echo "<h2>2. Hledání v sloupci cislo</h2>";
    $stmt2 = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE cislo = :id LIMIT 1");
    $stmt2->execute(['id' => $hledaneId]);
    $vysledek2 = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($vysledek2) {
        echo "<div class='success'>✅ ZÁZNAM NALEZEN v sloupci <strong>cislo</strong></div>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Hodnota</th></tr>";
        foreach ($vysledek2 as $sloupec => $hodnota) {
            echo "<tr><td><code>{$sloupec}</code></td><td>" . htmlspecialchars($hodnota ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ Záznam NENALEZEN v sloupci cislo</div>";
    }

    // 3. PŘESNÁ SHODA - id (číselné)
    echo "<h2>3. Hledání v sloupci id (primární klíč)</h2>";
    if (ctype_digit($hledaneId)) {
        $stmt3 = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE id = :id LIMIT 1");
        $stmt3->execute(['id' => (int)$hledaneId]);
        $vysledek3 = $stmt3->fetch(PDO::FETCH_ASSOC);

        if ($vysledek3) {
            echo "<div class='success'>✅ ZÁZNAM NALEZEN v sloupci <strong>id</strong></div>";
            echo "<table>";
            echo "<tr><th>Sloupec</th><th>Hodnota</th></tr>";
            foreach ($vysledek3 as $sloupec => $hodnota) {
                echo "<tr><td><code>{$sloupec}</code></td><td>" . htmlspecialchars($hodnota ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='warning'>⚠️ Záznam NENALEZEN v sloupci id</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ ID není číslo, přeskakuji hledání podle id</div>";
    }

    // 4. PODOBNÁ SHODA - LIKE dotaz
    echo "<h2>4. Hledání podobných záznamů (LIKE)</h2>";
    $stmt4 = $pdo->prepare("
        SELECT id, reklamace_id, cislo, jmeno, telefon, email, stav, created_at
        FROM wgs_reklamace
        WHERE reklamace_id LIKE :pattern
           OR cislo LIKE :pattern
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt4->execute(['pattern' => '%' . $hledaneId . '%']);
    $vysledky4 = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    if ($vysledky4) {
        echo "<div class='success'>✅ Nalezeno " . count($vysledky4) . " podobných záznamů</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>reklamace_id</th><th>cislo</th><th>Jméno</th><th>Telefon</th><th>Email</th><th>Stav</th><th>Datum</th></tr>";
        foreach ($vysledky4 as $zaznam) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($zaznam['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['reklamace_id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['cislo'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['jmeno'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['telefon'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['email'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['stav'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['created_at'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ Žádné podobné záznamy nenalezeny</div>";
    }

    // 5. POSLEDNÍCH 10 ZÁZNAMŮ
    echo "<h2>5. Posledních 10 záznamů v databázi</h2>";
    $stmt5 = $pdo->query("
        SELECT id, reklamace_id, cislo, jmeno, stav, created_at
        FROM wgs_reklamace
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $vysledky5 = $stmt5->fetchAll(PDO::FETCH_ASSOC);

    if ($vysledky5) {
        echo "<table>";
        echo "<tr><th>ID</th><th>reklamace_id</th><th>cislo</th><th>Jméno</th><th>Stav</th><th>Datum</th></tr>";
        foreach ($vysledky5 as $zaznam) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($zaznam['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['reklamace_id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['cislo'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['jmeno'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['stav'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['created_at'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // ZÁVĚR
    echo "<h2>Závěr</h2>";
    if ($vysledek1 || $vysledek2 || ($vysledek3 ?? false)) {
        echo "<div class='success'>";
        echo "<strong>✅ ZÁZNAM EXISTUJE V DATABÁZI</strong><br>";
        echo "Protokol.php by měl být schopen tento záznam načíst.<br><br>";

        if ($vysledek1) {
            $protokolUrl = "protokol.php?id=" . urlencode($vysledek1['reklamace_id']);
            echo "<a href='{$protokolUrl}' class='btn' target='_blank'>Otevřít protokol</a>";
        } elseif ($vysledek2) {
            $protokolUrl = "protokol.php?id=" . urlencode($vysledek2['cislo']);
            echo "<a href='{$protokolUrl}' class='btn' target='_blank'>Otevřít protokol</a>";
        } elseif ($vysledek3 ?? false) {
            $protokolUrl = "protokol.php?id=" . urlencode($vysledek3['id']);
            echo "<a href='{$protokolUrl}' class='btn' target='_blank'>Otevřít protokol</a>";
        }

        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<strong>❌ ZÁZNAM NEEXISTUJE V DATABÁZI</strong><br>";
        echo "ID <code>" . htmlspecialchars($hledaneId) . "</code> nebylo nalezeno v žádném sloupci.<br>";
        echo "Možné příčiny:<br>";
        echo "<ul>";
        echo "<li>Reklamace nebyla nikdy uložena (chyba při vytváření)</li>";
        echo "<li>ID je napsáno jinak (kontrolujte mezery, velká/malá písmena)</li>";
        echo "<li>Záznam byl smazán</li>";
        echo "</ul>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
