<?php
/**
 * Migrace: Vytvoření tabulky wgs_pricing pro ceník služeb
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku pro ukládání položek ceníku.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Tabulka wgs_pricing</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333333; border-bottom: 3px solid #333333;
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
               background: #333333; color: white; text-decoration: none;
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

    echo "<h1>Migrace: Vytvoření tabulky wgs_pricing</h1>";

    // Kontrola existence tabulky
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pricing'");
    $exists = $stmt->rowCount() > 0;

    if ($exists) {
        echo "<div class='warning'>";
        echo "<strong>TABULKA JIŽ EXISTUJE</strong><br>";
        echo "Tabulka <code>wgs_pricing</code> již existuje v databázi.";
        echo "</div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_pricing");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div class='info'>";
        echo "<strong>AKTUÁLNÍ STRUKTURA:</strong><br><br>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";

    } else {
        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Vytvořit tabulku
                $sql = "
                CREATE TABLE wgs_pricing (
                    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    service_name VARCHAR(255) NOT NULL,
                    description TEXT,
                    price_from DECIMAL(10,2),
                    price_to DECIMAL(10,2),
                    price_unit VARCHAR(50) DEFAULT 'Kč',
                    category VARCHAR(100),
                    display_order INT(11) DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX idx_category (category),
                    INDEX idx_display_order (display_order),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";

                $pdo->exec($sql);

                // Přidat reálná data z ceníku
                $sampleData = [
                    ['Opravy všeho druhu', 'Až cca 1,5 pracovní hodiny na místě. Platí pro všechny opravy nebo jiné zpracování, které není uvedených v bodě Čalounické práce', 145, null, '€', 'Základní služby', 1],
                    ['Čalounické práce - 1 sedací jednotka', 'Nové potahy, částečné nové potahy, úprava potahů, nové výplně, částečné nové výplně, úprava výplní, oprava popruhů. Hotové ušité potahy poskytuje výrobce zdarma.', 180, null, '€', 'Čalounění', 2],
                    ['Čalounické práce - každá další sedací jednotka', 'Paušální sazba za každý další kus (např. 1 křeslo = 1 ks, 1 dvoused = 2 ks, 1 zaoblený roh = 2 ks, 1 otoman = 2 ks, 1 manželská postel = 2 ks, 1 trojsedák = 3 ks)', 60, null, '€', 'Čalounění', 3],
                    ['Inspekce', 'Návštěva zákazníka, posudek pro reklamaci, konzultace opravy. Účtováno i v případě, že neexistuje žádný důvod pro reklamaci.', 95, null, '€', 'Diagnostika', 4],
                    ['Zmařený výjezd technika', 'Zákazník nebyl v domluvený termín nalezen, nebo neumožnil přístup. Zákazník nepovolil opravu navzdory odpovídajícím upřesňujícím informacím. Reklamace již byla vyřešena. Zákazníkovi již byla v průběhu řešení reklamace přislíbena výměna. Zboží již bylo vyměněno. Nesprávně dodaný náhradní díl - nutné opětovné dodání.', 95, null, '€', 'Poplatky', 5],
                    ['Oprava větších dílů mimo místo zákazníka (do 100km)', 'Rozsáhlejší oprava větších dílů sedacích souprav neděláme na místě. Cena se zvyšuje v závislosti na vzdálenosti z dílny.', 50, null, '€', 'Doplňkové služby', 6],
                    ['Oprava větších dílů mimo místo zákazníka (do 150km)', 'Rozsáhlejší oprava větších dílů sedacích souprav neděláme na místě. Cena se zvyšuje v závislosti na vzdálenosti z dílny.', 80, null, '€', 'Doplňkové služby', 7],
                    ['Materiál na úpravu nebo doplnění výplně', 'Materiál z vlastních zdrojů. Cena obsahuje jednu sedací jednotku.', 40, null, '€', 'Doplňkové služby', 8],
                    ['Druhá osoba při opravě velkých dílů', 'V případě dílů větších než 1 sedací plocha je nutná při opravě přítomna druhá osoba', 40, null, '€', 'Doplňkové služby', 9]
                ];

                $insertSql = "
                INSERT INTO wgs_pricing (service_name, description, price_from, price_to, price_unit, category, display_order)
                VALUES (:name, :desc, :from, :to, :unit, :cat, :order)
                ";

                $stmt = $pdo->prepare($insertSql);

                foreach ($sampleData as $data) {
                    $stmt->execute([
                        'name' => $data[0],
                        'desc' => $data[1],
                        'from' => $data[2],
                        'to' => $data[3],
                        'unit' => $data[4],
                        'cat' => $data[5],
                        'order' => $data[6]
                    ]);
                }

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "Tabulka <code>wgs_pricing</code> byla vytvořena.<br>";
                echo "Přidáno 9 položek ceníku dle aktuálního ceníku platného od 1.1.2023.";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>DALŠÍ KROKY:</strong><br>";
                echo "1. Vytvořit stránku <code>cenik.php</code><br>";
                echo "2. Vytvořit API <code>api/pricing_api.php</code><br>";
                echo "3. Přidat odkaz do menu";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Náhled co bude provedeno
            echo "<div class='info'>";
            echo "<strong>CO BUDE PROVEDENO:</strong><br><br>";
            echo "1. Vytvoření tabulky <code>wgs_pricing</code> s následujícími sloupci:<br>";
            echo "   - <code>id</code> (INT, AUTO_INCREMENT, PRIMARY KEY)<br>";
            echo "   - <code>service_name</code> (VARCHAR 255) - název služby<br>";
            echo "   - <code>description</code> (TEXT) - popis služby<br>";
            echo "   - <code>price_from</code> (DECIMAL) - cena od<br>";
            echo "   - <code>price_to</code> (DECIMAL) - cena do<br>";
            echo "   - <code>price_unit</code> (VARCHAR 50) - měna<br>";
            echo "   - <code>category</code> (VARCHAR 100) - kategorie<br>";
            echo "   - <code>display_order</code> (INT) - pořadí zobrazení<br>";
            echo "   - <code>is_active</code> (TINYINT) - aktivní/neaktivní<br>";
            echo "   - <code>created_at</code>, <code>updated_at</code> (TIMESTAMP)<br><br>";
            echo "2. Přidání 9 položek ceníku dle aktuálního ceníku";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='vsechny_tabulky.php' class='btn' style='background: #666;'>← Zpět na SQL kartu</a>";
echo "</div></body></html>";
?>
