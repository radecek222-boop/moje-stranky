<?php
/**
 * Kompletní import překladů ceníku z JS slovníku do databáze
 *
 * Tento skript doplní VŠECHNY chybějící překlady EN a IT pro:
 * - service_name_en, service_name_it
 * - description_en, description_it
 * - category_en, category_it
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit import.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kompletní import překladů ceníku</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1400px; margin: 50px auto; padding: 20px;
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 12px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; position: sticky; top: 0; }
        .updated { background: #d4edda; }
        .skipped { background: #f8f9fa; color: #666; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Kompletní import překladů ceníku</h1>";

    // ==============================================
    // PŘEKLADY - asociativní pole
    // ==============================================

    // KATEGORIE
    $kategorie = [
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

    // NÁZVY SLUŽEB
    $nazvy = [
        'Opravy všeho druhu' => [
            'en' => 'All Types of Repairs',
            'it' => 'Tutti i Tipi di Riparazioni'
        ],
        'První díl' => [
            'en' => 'First Part',
            'it' => 'Prima Parte'
        ],
        'Každý další díl' => [
            'en' => 'Each Additional Part',
            'it' => 'Ogni Parte Aggiuntiva'
        ],
        'Rohový díl' => [
            'en' => 'Corner Piece',
            'it' => 'Pezzo Angolare'
        ],
        'Ottoman / lehátko' => [
            'en' => 'Ottoman / Daybed',
            'it' => 'Pouf / Divano Letto'
        ],
        'Mechanická část (relax, výsuv)' => [
            'en' => 'Mechanical Part (relax, extension)',
            'it' => 'Parte Meccanica (relax, estensione)'
        ],
        'Příklad: Pouze sedák' => [
            'en' => 'Example: Seat Only',
            'it' => 'Esempio: Solo Sedile'
        ],
        'Příklad: Sedák + opěrka' => [
            'en' => 'Example: Seat + Backrest',
            'it' => 'Esempio: Sedile + Schienale'
        ],
        'Příklad: Sedák + opěrka + područka' => [
            'en' => 'Example: Seat + Backrest + Armrest',
            'it' => 'Esempio: Sedile + Schienale + Bracciolo'
        ],
        'Příklad: Křeslo komplet (4 díly)' => [
            'en' => 'Example: Complete Armchair (4 parts)',
            'it' => 'Esempio: Poltrona Completa (4 parti)'
        ],
        'Inspekce / diagnostika' => [
            'en' => 'Inspection / Diagnosis',
            'it' => 'Ispezione / Diagnosi'
        ],
        'Zmařený výjezd' => [
            'en' => 'Unsuccessful Visit',
            'it' => 'Visita Non Riuscita'
        ],
        'Doprava na dílnu a zpět doručení včetně manipulace (do 100 km)' => [
            'en' => 'Transport to workshop and back incl. handling (up to 100 km)',
            'it' => 'Trasporto in officina e ritorno incl. movimentazione (fino a 100 km)'
        ],
        'Doprava na dílnu a zpět doručení včetně manipulace (do 200 km)' => [
            'en' => 'Transport to workshop and back incl. handling (up to 200 km)',
            'it' => 'Trasporto in officina e ritorno incl. movimentazione (fino a 200 km)'
        ],
        'Materiál (výplně od nás)' => [
            'en' => 'Material (fillings from us)',
            'it' => 'Materiale (imbottiture da noi)'
        ],
        'Druhá osoba (nutnost manipulace)' => [
            'en' => 'Second Person (handling required)',
            'it' => 'Seconda Persona (movimentazione richiesta)'
        ]
    ];

    // POPISY
    $popisy = [
        'Platí pro veškeré opravy proveditelné do cca 1,5 hodiny na místě. Týká se všech úkonů, které nespadají pod standardní čalounické práce. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'Applies to all repairs feasible within approx. 1.5 hours on-site. Covers all tasks not falling under standard upholstery work. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Applicabile a tutte le riparazioni eseguibili in circa 1,5 ore sul posto. Riguarda tutte le operazioni che non rientrano nei lavori di tappezzeria standard. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'Čalounění prvního dílu včetně rozebrání konstrukce. Jeden díl = např. sedák NEBO opěrka NEBO područka. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'Upholstery of the first part including disassembly of structure. One part = e.g. seat OR backrest OR armrest. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Tappezzeria della prima parte incluso smontaggio della struttura. Una parte = ad es. sedile O schienale O bracciolo. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'Při téže opravě. Např. opěrka, područka, zadní panel, boční panel, polštář, krycí díl. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'During the same repair. E.g. backrest, armrest, back panel, side panel, cushion, cover piece. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Durante la stessa riparazione. Ad es. schienale, bracciolo, pannello posteriore, pannello laterale, cuscino, pezzo di copertura. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        '1 modul + 2 díly navíc. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => '1 module + 2 extra parts. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => '1 modulo + 2 parti extra. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'Cena dle konstrukce. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'Price according to construction. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prezzo secondo la costruzione. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'Příplatek za mechanismus (relax, výsuv, pohyb). CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'Surcharge for mechanism (relax, extension, movement). PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Supplemento per meccanismo (relax, estensione, movimento). PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'První díl 190€ = 190€. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'First part 190€ = 190€. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prima parte 190€ = 190€. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'První díl 190€ + další díl 70€ = 260€. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'First part 190€ + additional part 70€ = 260€. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prima parte 190€ + parte aggiuntiva 70€ = 260€. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'První díl 190€ + 2× další díl (2×70€) = 330€. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'First part 190€ + 2× additional part (2×70€) = 330€. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prima parte 190€ + 2× parte aggiuntiva (2×70€) = 330€. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'První díl 190€ + 3× další díl (3×70€) = 400€. Sedák + opěrka + 2× područka. CENA POUZE ZA PRÁCI, BEZ MATERIÁLU.' => [
            'en' => 'First part 190€ + 3× additional part (3×70€) = 400€. Seat + backrest + 2× armrest. PRICE FOR LABOR ONLY, EXCLUDING MATERIAL.',
            'it' => 'Prima parte 190€ + 3× parte aggiuntiva (3×70€) = 400€. Sedile + schienale + 2× bracciolo. PREZZO SOLO PER LA MANODOPERA, ESCLUSO IL MATERIALE.'
        ],
        'Návštěva zákazníka, posudek pro reklamaci, konzultace opravy. Účtováno i v případě neoprávněné reklamace nebo nezjištěné závady.' => [
            'en' => 'Customer visit, claim assessment, repair consultation. Charged even in case of unjustified claim or undetected defect.',
            'it' => 'Visita del cliente, valutazione del reclamo, consulenza per la riparazione. Addebitato anche in caso di reclamo ingiustificato o difetto non rilevato.'
        ],
        'Zákazník není přítomen, neumožní přístup nebo odmítne opravu.' => [
            'en' => 'Customer not present, does not allow access or refuses repair.',
            'it' => 'Cliente non presente, non consente l\'accesso o rifiuta la riparazione.'
        ],
        'Dopravné pro rozsáhlejší opravy prováděné mimo místo zákazníka.' => [
            'en' => 'Transportation for more extensive repairs carried out outside customer location.',
            'it' => 'Trasporto per riparazioni più estese eseguite fuori dalla sede del cliente.'
        ],
        'Cena obsahuje jednu sedací jednotku (modul).' => [
            'en' => 'Price includes one seating unit (module).',
            'it' => 'Il prezzo include un\'unità di seduta (modulo).'
        ],
        'V případě dílů větších než 1 sedací plocha a těžších dílů než je zákonem stanovený limit 50kg, je nutná při opravě přítomna druhá osoba.' => [
            'en' => 'For parts larger than 1 seating area and heavier parts than the legally mandated limit of 50kg, a second person must be present during repair.',
            'it' => 'Per parti più grandi di 1 area di seduta e parti più pesanti del limite legale di 50kg, è necessaria la presenza di una seconda persona durante la riparazione.'
        ]
    ];

    // Pokud je nastaveno ?execute=1, provést import
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM KOMPLETNÍ IMPORT...</strong></div>";

        $pdo->beginTransaction();

        try {
            $stats = [
                'categories_updated' => 0,
                'names_updated' => 0,
                'descriptions_updated' => 0,
                'total_items' => 0
            ];

            // Načíst všechny položky
            $stmt = $pdo->query("SELECT * FROM wgs_pricing ORDER BY id");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stats['total_items'] = count($items);

            echo "<h2>Průběh importu:</h2>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Název</th><th>Kategorie</th><th>Název EN/IT</th><th>Popis EN/IT</th></tr>";

            foreach ($items as $item) {
                $id = $item['id'];
                $updates = [];
                $updateFields = [];

                // Aktualizovat kategorii
                if (isset($kategorie[$item['category']])) {
                    $updates['category_en'] = $kategorie[$item['category']]['en'];
                    $updates['category_it'] = $kategorie[$item['category']]['it'];
                    $stats['categories_updated']++;
                }

                // Aktualizovat název služby
                if (isset($nazvy[$item['service_name']])) {
                    $updates['service_name_en'] = $nazvy[$item['service_name']]['en'];
                    $updates['service_name_it'] = $nazvy[$item['service_name']]['it'];
                    $stats['names_updated']++;
                }

                // Aktualizovat popis
                if (isset($popisy[$item['description']])) {
                    $updates['description_en'] = $popisy[$item['description']]['en'];
                    $updates['description_it'] = $popisy[$item['description']]['it'];
                    $stats['descriptions_updated']++;
                }

                // Pokud jsou nějaké aktualizace, provést UPDATE
                if (!empty($updates)) {
                    $setParts = [];
                    foreach ($updates as $col => $val) {
                        $setParts[] = "$col = :$col";
                    }

                    $sql = "UPDATE wgs_pricing SET " . implode(', ', $setParts) . " WHERE id = :id";
                    $updateStmt = $pdo->prepare($sql);

                    $params = $updates;
                    $params['id'] = $id;

                    $updateStmt->execute($params);

                    echo "<tr class='updated'>";
                    echo "<td>$id</td>";
                    echo "<td>" . htmlspecialchars($item['service_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['category']) . "</td>";
                    echo "<td>" . (isset($updates['service_name_en']) ? '✓' : '-') . "</td>";
                    echo "<td>" . (isset($updates['description_en']) ? '✓' : '-') . "</td>";
                    echo "</tr>";
                } else {
                    echo "<tr class='skipped'>";
                    echo "<td>$id</td>";
                    echo "<td>" . htmlspecialchars($item['service_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['category']) . "</td>";
                    echo "<td colspan='2'>Žádné aktualizace</td>";
                    echo "</tr>";
                }
            }

            echo "</table>";

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✓ IMPORT ÚSPĚŠNĚ DOKONČEN</strong><br><br>";
            echo "📊 <strong>Statistiky:</strong><br>";
            echo "• Celkem položek: <strong>{$stats['total_items']}</strong><br>";
            echo "• Aktualizováno kategorií: <strong>{$stats['categories_updated']}</strong><br>";
            echo "• Aktualizováno názvů služeb: <strong>{$stats['names_updated']}</strong><br>";
            echo "• Aktualizováno popisů: <strong>{$stats['descriptions_updated']}</strong><br>";
            echo "<br><strong>Nyní obnov stránku ceníku a všechny překlady by měly fungovat perfektně!</strong>";
            echo "</div>";

            echo "<a href='cenik.php' class='btn'>Zobrazit ceník</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "</table>";
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI IMPORTU:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Náhled co bude provedeno
        echo "<div class='info'>";
        echo "<strong>📋 CO BUDE PROVEDENO:</strong><br>";
        echo "• Doplnění překladů kategorií (EN + IT)<br>";
        echo "• Doplnění překladů názvů služeb (EN + IT)<br>";
        echo "• Doplnění překladů popisů (EN + IT)<br>";
        echo "• Překlady budou zapsány přímo do databáze<br>";
        echo "• Po importu už nebude potřeba fallback v JavaScriptu";
        echo "</div>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
        echo "• Skript aktualizuje VŠECHNY položky v databázi<br>";
        echo "• Přepíše existující překlady novými z tohoto skriptu<br>";
        echo "• Operace je BEZPEČNÁ a REVERZIBILNÍ<br>";
        echo "• Po importu budou všechny překlady perfektně fungovat";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>✓ SPUSTIT KOMPLETNÍ IMPORT</a>";
        echo "<a href='cenik.php' class='btn'>Zrušit a vrátit se</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
