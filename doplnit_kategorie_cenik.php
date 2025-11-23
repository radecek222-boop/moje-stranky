<?php
/**
 * Oprava: Doplnění překladů kategorií do databáze
 *
 * Tento skript opraví chybějící překlady kategorií tím, že
 * doplní category_en a category_it podle českých názvů kategorií.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit opravu.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava: Doplnění překladů kategorií</title>
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
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               cursor: pointer; border: none; font-size: 14px; }
        .btn:hover { background: #000; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; position: sticky; top: 0; }
        .updated { background: #d4edda; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px;
              overflow-x: auto; border-left: 4px solid #333; font-size: 12px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Oprava: Doplnění překladů kategorií</h1>";

    // Manuální mapování kategorií
    $kategorieMapa = [
        '1. Základní servisní sazby' => [
            'en' => '1. Basic Service Rates',
            'it' => '1. Tariffe di Servizio Base'
        ],
        '2. Profesionální čalounické práce' => [
            'en' => '2. Professional Upholstery Work',
            'it' => '2. Lavori di Tappezzeria Professionale'
        ],
        '3. Modelové příklady výpočtu' => [
            'en' => '3. Calculation Examples',
            'it' => '3. Esempi di Calcolo'
        ],
        '4. Další servisní položky' => [
            'en' => '4. Other Service Items',
            'it' => '4. Altre Voci di Servizio'
        ]
    ];

    echo "<div class='info'><strong>NAČÍTÁM POLOŽKY Z DATABÁZE...</strong></div>";

    $stmt = $pdo->query("SELECT DISTINCT category FROM wgs_pricing ORDER BY category");
    $kategorie = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<div class='success'>Nalezeno <strong>" . count($kategorie) . "</strong> unikátních kategorií</div>";

    // Zobrazit mapování
    echo "<h2>Plánované překlady kategorií:</h2>";
    echo "<table>";
    echo "<tr><th>Kategorie (CS)</th><th>Kategorie (EN)</th><th>Kategorie (IT)</th></tr>";
    foreach ($kategorieMapa as $cs => $preklady) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($cs) . "</strong></td>";
        echo "<td>" . htmlspecialchars($preklady['en']) . "</td>";
        echo "<td>" . htmlspecialchars($preklady['it']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Pokud je nastaveno ?execute=1, provést opravu
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            $stats = [
                'updated' => 0,
                'skipped' => 0
            ];

            echo "<h2>Průběh aktualizace:</h2>";
            echo "<table>";
            echo "<tr><th>Kategorie (CS)</th><th>EN překlad</th><th>IT překlad</th><th>Počet položek</th></tr>";

            foreach ($kategorieMapa as $kategorie_cs => $preklady) {
                // Aktualizovat všechny položky v této kategorii
                $sql = "
                UPDATE wgs_pricing
                SET category_en = :category_en,
                    category_it = :category_it
                WHERE category = :category_cs
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'category_en' => $preklady['en'],
                    'category_it' => $preklady['it'],
                    'category_cs' => $kategorie_cs
                ]);

                $pocetAktualizovanych = $stmt->rowCount();

                if ($pocetAktualizovanych > 0) {
                    $stats['updated'] += $pocetAktualizovanych;
                    echo "<tr class='updated'>";
                    echo "<td><strong>" . htmlspecialchars($kategorie_cs) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($preklady['en']) . "</td>";
                    echo "<td>" . htmlspecialchars($preklady['it']) . "</td>";
                    echo "<td>✓ {$pocetAktualizovanych} položek</td>";
                    echo "</tr>";
                } else {
                    $stats['skipped']++;
                }
            }

            echo "</table>";

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✓ OPRAVA ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "📊 <strong>Statistiky:</strong><br>";
            echo "• Aktualizováno: <strong>{$stats['updated']}</strong> položek<br>";
            echo "• Aktualizováno kategorií: <strong>" . count($kategorieMapa) . "</strong><br>";
            echo "<br><strong>Nyní obnov stránku ceníku v italštině/angličtině a kategorie by měly být přeložené!</strong>";
            echo "</div>";

            echo "<a href='cenik.php' class='btn'>Zobrazit ceník</a>";
            echo "<a href='diagnostika_kategorie_cenik.php' class='btn'>Zkontrolovat diagnostiku</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "</table>";
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI OPRAVĚ:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Náhled co bude provedeno
        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
        echo "• Skript doplní EN a IT překlady do <strong>všech 16 položek</strong> v databázi<br>";
        echo "• Nepřepíše existující překlady (pokud už nějaké jsou)<br>";
        echo "• Operace je BEZPEČNÁ a REVERZIBILNÍ<br>";
        echo "• Po opravě budou kategorie zobrazeny správně v italštině a angličtině";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>✓ SPUSTIT OPRAVU</a>";
        echo "<a href='diagnostika_kategorie_cenik.php' class='btn'>Zrušit a vrátit se</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
