<?php
/**
 * Doplnění chybějících popisů v ceníku - přímé SQL UPDATE podle ID
 *
 * Tento skript doplní překlady popisů pro položky, které je nemají.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Doplnění popisů ceníku</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
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
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               cursor: pointer; border: none; font-size: 14px; }
        .btn:hover { background: #000; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; position: sticky; top: 0; }
        .updated { background: #d4edda; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Doplnění popisů ceníku</h1>";

    // Pole s překlady podle ID
    $updates = [
        58 => [
            'en' => 'Applies to all repairs feasible within approx. 1.5 hours on-site. Covers all tasks not falling under standard upholstery work. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Applicabile a tutte le riparazioni eseguibili in circa 1,5 ore sul posto. Riguarda tutte le operazioni che non rientrano nei lavori di tappezzeria standard. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        59 => [
            'en' => 'Upholstery of the first part including disassembly of structure. One part = e.g. seat OR backrest OR armrest. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Tappezzeria della prima parte incluso smontaggio della struttura. Una parte = ad es. sedile O schienale O bracciolo. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        60 => [
            'en' => 'During the same repair. E.g. backrest, armrest, back panel, side panel, cushion, cover piece. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Durante la stessa riparazione. Ad es. schienale, bracciolo, pannello posteriore, pannello laterale, cuscino, pezzo di copertura. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        61 => [
            'en' => '1 module + 2 extra parts. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => '1 modulo + 2 parti extra. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        62 => [
            'en' => 'Price according to construction. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prezzo secondo la costruzione. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        63 => [
            'en' => 'Surcharge for mechanism (relax, extension, movement). PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Supplemento per meccanismo (relax, estensione, movimento). PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        64 => [
            'en' => 'First part 190€ = 190€. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prima parte 190€ = 190€. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        65 => [
            'en' => 'First part 190€ + additional part 70€ = 260€. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prima parte 190€ + parte aggiuntiva 70€ = 260€. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        66 => [
            'en' => 'First part 190€ + 2× additional part (2×70€) = 330€. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prima parte 190€ + 2× parte aggiuntiva (2×70€) = 330€. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        67 => [
            'en' => 'First part 190€ + 3× additional part (3×70€) = 400€. Seat + backrest + 2× armrest. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prima parte 190€ + 3× parte aggiuntiva (3×70€) = 400€. Sedile + schienale + 2× bracciolo. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ]
    ];

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM DOPLNĚNÍ POPISŮ...</strong></div>";

        $pdo->beginTransaction();

        try {
            echo "<h2>Průběh aktualizace:</h2>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Název</th><th>Popis EN</th><th>Popis IT</th></tr>";

            $stmt = $pdo->prepare("
                UPDATE wgs_pricing
                SET description_en = :desc_en,
                    description_it = :desc_it
                WHERE id = :id
            ");

            $updated = 0;
            foreach ($updates as $id => $translations) {
                $stmt->execute([
                    'id' => $id,
                    'desc_en' => $translations['en'],
                    'desc_it' => $translations['it']
                ]);

                // Načíst název pro zobrazení
                $itemStmt = $pdo->prepare("SELECT service_name FROM wgs_pricing WHERE id = :id");
                $itemStmt->execute(['id' => $id]);
                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

                echo "<tr class='updated'>";
                echo "<td>$id</td>";
                echo "<td>" . htmlspecialchars($item['service_name']) . "</td>";
                echo "<td>✓ Doplněno</td>";
                echo "<td>✓ Doplněno</td>";
                echo "</tr>";

                $updated++;
            }

            echo "</table>";

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✓ DOPLNĚNÍ ÚSPĚŠNĚ DOKONČENO</strong><br><br>";
            echo "📊 <strong>Statistiky:</strong><br>";
            echo "• Aktualizováno položek: <strong>$updated</strong><br>";
            echo "• Doplněno EN popisů: <strong>$updated</strong><br>";
            echo "• Doplněno IT popisů: <strong>$updated</strong><br>";
            echo "<br><strong>Nyní obnov stránku ceníku a VŠECHNY popisy budou perfektně přeložené!</strong>";
            echo "</div>";

            echo "<a href='cenik.php' class='btn'>Zobrazit ceník</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "</table>";
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        echo "<div class='info'>";
        echo "<strong>📋 CO BUDE PROVEDENO:</strong><br>";
        echo "• Doplnění EN popisů pro položky ID 58-67<br>";
        echo "• Doplnění IT popisů pro položky ID 58-67<br>";
        echo "• Celkem <strong>" . count($updates) . " položek</strong> bude aktualizováno<br>";
        echo "• Po doplnění budou VŠECHNY popisy perfektně přeložené";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>ID</th><th>Co bude doplněno</th></tr>";
        foreach ($updates as $id => $translations) {
            echo "<tr>";
            echo "<td>$id</td>";
            echo "<td>EN + IT překlad popisu</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<a href='?execute=1' class='btn'>✓ SPUSTIT DOPLNĚNÍ</a>";
        echo "<a href='cenik.php' class='btn'>Zrušit a vrátit se</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
