<?php
/**
 * Migrace: Update ceníku na verzi 2025
 *
 * Tento skript BEZPEČNĚ aktualizuje ceník služeb na nový modulový systém.
 * Starý ceník z roku 2023 bude nahrazen novým ceníkem 2025.
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
    <title>Migrace: Ceník 2025</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #2c3e50;
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
               background: #2c3e50; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a252f; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2c3e50; color: white; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Ceník 2025 – Modulový systém</h1>";

    // Kontrolní fáze
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    // Zjistit počet stávajících položek
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_pricing");
    $currentCount = $stmt->fetchColumn();
    echo "<p>Aktuální počet položek v ceníku: <strong>$currentCount</strong></p>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // 1. Nejdřív přidáme nové sloupce, pokud neexistují
            echo "<p>1. Kontrola struktury tabulky...</p>";

            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pricing LIKE 'is_calculable'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE wgs_pricing ADD COLUMN is_calculable TINYINT(1) DEFAULT 1 AFTER is_active");
                echo "<div class='success'>Přidán sloupec is_calculable</div>";
            }

            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pricing LIKE 'item_type'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE wgs_pricing ADD COLUMN item_type VARCHAR(50) DEFAULT 'service' AFTER category");
                echo "<div class='success'>Přidán sloupec item_type</div>";
            }

            // 2. Smazat stará data
            echo "<p>2. Mazání starých dat...</p>";
            $pdo->exec("DELETE FROM wgs_pricing");
            echo "<div class='success'>Stará data smazána</div>";

            // 3. Vložit nová data podle ceníku 2025
            echo "<p>3. Vkládání nového ceníku 2025...</p>";

            $newPricing = [
                // 1. Základní servisní sazby
                [
                    'service_name' => 'Opravy všeho druhu',
                    'description' => 'Platí pro veškeré opravy proveditelné do cca 1,5 hodiny na místě. Týká se všech úkonů, které nespadají pod standardní čalounické práce. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 155,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '1. Základní servisní sazby',
                    'item_type' => 'base',
                    'display_order' => 1,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],

                // 2. Profesionální čalounické práce
                [
                    'service_name' => 'První díl',
                    'description' => 'Čalounění prvního dílu včetně rozebrání konstrukce. Jeden díl = např. sedák NEBO opěrka NEBO područka. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 190,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '2. Profesionální čalounické práce',
                    'item_type' => 'first_part',
                    'display_order' => 2,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],
                [
                    'service_name' => 'Každý další díl',
                    'description' => 'Při téže opravě. Např. opěrka, područka, zadní panel, boční panel, polštář, krycí díl. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 70,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '2. Profesionální čalounické práce',
                    'item_type' => 'additional_part',
                    'display_order' => 3,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],
                [
                    'service_name' => 'Rohový díl',
                    'description' => '1 modul + 2 díly navíc. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 330,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '2. Profesionální čalounické práce',
                    'item_type' => 'module',
                    'display_order' => 4,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],
                [
                    'service_name' => 'Ottoman / lehátko',
                    'description' => 'Cena dle konstrukce. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 260,
                    'price_to' => 330,
                    'price_unit' => '€',
                    'category' => '2. Profesionální čalounické práce',
                    'item_type' => 'module',
                    'display_order' => 5,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],
                [
                    'service_name' => 'Mechanická část (relax, výsuv)',
                    'description' => 'Příplatek za mechanismus (relax, výsuv, pohyb). **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 70,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '2. Profesionální čalounické práce',
                    'item_type' => 'part',
                    'display_order' => 6,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],

                // 3. Modelové příklady (nezapočítávat do kalkulace - jsou to jen příklady)
                [
                    'service_name' => 'Příklad: Pouze sedák',
                    'description' => 'První díl 190€ = **190€**. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 190,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '3. Modelové příklady výpočtu',
                    'item_type' => 'example',
                    'display_order' => 7,
                    'is_active' => 1,
                    'is_calculable' => 0
                ],
                [
                    'service_name' => 'Příklad: Sedák + opěrka',
                    'description' => 'První díl 190€ + další díl 70€ = **260€**. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 260,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '3. Modelové příklady výpočtu',
                    'item_type' => 'example',
                    'display_order' => 8,
                    'is_active' => 1,
                    'is_calculable' => 0
                ],
                [
                    'service_name' => 'Příklad: Sedák + opěrka + područka',
                    'description' => 'První díl 190€ + 2× další díl (2×70€) = **330€**. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 330,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '3. Modelové příklady výpočtu',
                    'item_type' => 'example',
                    'display_order' => 9,
                    'is_active' => 1,
                    'is_calculable' => 0
                ],
                [
                    'service_name' => 'Příklad: Křeslo komplet (4 díly)',
                    'description' => 'První díl 190€ + 3× další díl (3×70€) = **400€**. Sedák + opěrka + 2× područka. **CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.**',
                    'price_from' => 400,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '3. Modelové příklady výpočtu',
                    'item_type' => 'example',
                    'display_order' => 10,
                    'is_active' => 1,
                    'is_calculable' => 0
                ],

                // 4. Další servisní položky
                [
                    'service_name' => 'Inspekce / diagnostika',
                    'description' => 'Návštěva zákazníka, posudek pro reklamaci, konzultace opravy. Účtováno i v případě neoprávněné reklamace nebo nezjištěné závady.',
                    'price_from' => 155,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '4. Další servisní položky',
                    'item_type' => 'service',
                    'display_order' => 11,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],
                [
                    'service_name' => 'Zmařený výjezd',
                    'description' => 'Zákazník není přítomen, neumožní přístup nebo odmítne opravu.',
                    'price_from' => 155,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '4. Další servisní položky',
                    'item_type' => 'service',
                    'display_order' => 12,
                    'is_active' => 1,
                    'is_calculable' => 0
                ],
                [
                    'service_name' => 'Doprava z dílny (do 100 km)',
                    'description' => 'Dopravné pro rozsáhlejší opravy prováděné mimo místo zákazníka.',
                    'price_from' => 50,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '4. Další servisní položky',
                    'item_type' => 'transport',
                    'display_order' => 13,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],
                [
                    'service_name' => 'Doprava z dílny (do 150 km)',
                    'description' => 'Dopravné pro rozsáhlejší opravy prováděné mimo místo zákazníka.',
                    'price_from' => 80,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '4. Další servisní položky',
                    'item_type' => 'transport',
                    'display_order' => 14,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],
                [
                    'service_name' => 'Materiál (výplně z vlastních zdrojů)',
                    'description' => 'Cena obsahuje jednu sedací jednotku (modul).',
                    'price_from' => 40,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '4. Další servisní položky',
                    'item_type' => 'material',
                    'display_order' => 15,
                    'is_active' => 1,
                    'is_calculable' => 1
                ],
                [
                    'service_name' => 'Druhá osoba (nutnost manipulace)',
                    'description' => 'V případě dílů větších než 1 sedací plocha je nutná při opravě přítomna druhá osoba.',
                    'price_from' => 40,
                    'price_to' => null,
                    'price_unit' => '€',
                    'category' => '4. Další servisní položky',
                    'item_type' => 'service',
                    'display_order' => 16,
                    'is_active' => 1,
                    'is_calculable' => 1
                ]
            ];

            $stmt = $pdo->prepare("
                INSERT INTO wgs_pricing
                (service_name, description, price_from, price_to, price_unit, category, item_type, display_order, is_active, is_calculable)
                VALUES
                (:service_name, :description, :price_from, :price_to, :price_unit, :category, :item_type, :display_order, :is_active, :is_calculable)
            ");

            $insertedCount = 0;
            foreach ($newPricing as $item) {
                $stmt->execute($item);
                $insertedCount++;
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "Vloženo <strong>$insertedCount</strong> položek nového ceníku 2025.<br>";
            echo "Ceník byl aktualizován na modulový systém.";
            echo "</div>";

            echo "<h2>Nový ceník:</h2>";
            echo "<table>";
            echo "<tr><th>Kategorie</th><th>Služba</th><th>Cena</th><th>Typ</th><th>Kalkulovatelné</th></tr>";

            $stmt = $pdo->query("SELECT * FROM wgs_pricing ORDER BY display_order");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $price = $row['price_to'] ? "{$row['price_from']}-{$row['price_to']}" : $row['price_from'];
                $calc = $row['is_calculable'] ? 'ANO' : 'NE';
                echo "<tr>";
                echo "<td>{$row['category']}</td>";
                echo "<td>{$row['service_name']}</td>";
                echo "<td>{$price} {$row['price_unit']}</td>";
                echo "<td>{$row['item_type']}</td>";
                echo "<td>{$calc}</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<div class='info'>";
            echo "<strong>Další kroky:</strong><br>";
            echo "1. Otevřít stránku <a href='cenik.php'>cenik.php</a> a zkontrolovat zobrazení<br>";
            echo "2. Vyzkoušet kalkulačku s novým ceníkem<br>";
            echo "3. Zkontrolovat admin editaci";
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
        echo "<div class='warning'>";
        echo "<strong>PŘED SPUŠTĚNÍM:</strong><br>";
        echo "• Všechna stávající data v tabulce wgs_pricing budou smazána<br>";
        echo "• Bude vloženo 16 nových položek ceníku 2025<br>";
        echo "• Budou přidány nové sloupce: is_calculable, item_type<br>";
        echo "• Ceník bude převeden na modulový systém";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='cenik.php' class='btn' style='background: #6c757d;'>Zpět na ceník</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
