<?php
/**
 * FINÁLNÍ UNIVERZÁLNÍ PARSER
 *
 * Extrahuje VŠECHNA pole z PDF a ukládá je:
 * - Základní pole → přímo do sloupců (jméno, telefon, email, ulice, město, PSČ, datum, popis)
 * - Technické detaily → do doplnujici_info (model, složení, látka, barva, nohy, doplňky)
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$skutecnyText = 'Čislo reklamace:  NCE25-00002444-39  NCE25-00002444-39/CZ785-2025  12.11.2025 Datum podání:  Číslo objednávky:  Číslo faktury:  Datum vyhotovení:  25250206  12.11.2025  0  Jméno a příjmení:  Česko Stát:  25242 PSČ:  Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch  Poschodí:  Rodinný dům   Panelový dům  Místo reklamace  kmochova@petrisk.cz  725 387 868 Telefon:  Česko Stát:  25242  Email:  Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch Jméno a příjmení:  PSČ:  Zákazník  Vyjádření prodávajícího: reklamace bude vyřešena do 30 dní od obhlídky servisního technika, který určí způsob odstránění závady reklamovaného zboží  Závada:   Tak odstáté polštáře, že se na posteli nedá spát. Prosím o rychlé řešení. Děkuji a fotky přikládám. Na webových stránkách nic takového není.  Model:   C157 Intenso; LE02 Orbitale; Matrace  Složení:   450 1,5 sed Ľ s površkou a elektr. výsuvem (1); 338 1,5 sed BP s výsuvem eletrickým (1); 011 Roh (1); 291 1,5 sed BP (1); 274 1,5 sed P s područkou (1); 830 Battery Bank " LIB " (2); C04 posteľ s úložným priestorom, rošt 193 x 200 cm (1); Matrac Capri 193x200x25 cm tvrdší (1)  Látka:   TG 20JJ Light Beige; INÉ; 70.0077.02 Rose  Nohy:  Doplňky:  Reklamované zboží';

// VŠECHNA POLE - rozdělená na ZÁKLADNÍ a TECHNICKÉ
$vzechnyPatterns = [
    // ========== ZÁKLADNÍ POLE (mapují se přímo) ==========
    'cislo_reklamace' => '/([A-Z]{2,3}\d{2}-\d{8}-\d{2})/i',
    'datum_reklamace' => '/(\d{1,2}\.\d{1,2}\.\d{4})\s+Datum\s+podání/i',
    'datum_prodeje' => '/\d{6,8}\s+(\d{1,2}\.\d{1,2}\.\d{4})/i',
    'email' => '/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
    'telefon' => '/(\d{3}\s+\d{3}\s+\d{3})\s+Telefon/i',
    'psc' => '/\b(\d{3}\s?\d{2})\b.*?PSČ/i',
    'mesto' => '/\b([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\s+Město/iu',
    'ulice' => '/Adresa:\s+([^A-Z]{5,50}?)\s+(?:[A-Z]|Jméno)/s',
    'jmeno' => '/Jméno\s+společnosti:\s+([^Pp]{3,50}?)\s+(?:Poschodí|PSČ|Stát)/iu',
    'popis_problemu' => '/Závada:\s+(.+?)\s+Model:/s',

    // ========== TECHNICKÉ DETAILY (uloží se do doplnujici_info) ==========
    'model' => '/Model:\s+(.+?)\s+Složení:/s',
    'provedeni' => '/Složení:\s+(.+?)\s+Látka:/s',
    'barva' => '/Látka:\s+(.+?)\s+(?:Nohy|Doplňky|Reklamované)/s',
    'nohy' => '/Nohy:\s+(.+?)\s+(?:Doplňky|Reklamované)/s',
    'doplnky' => '/Doplňky:\s+(.+?)\s+(?:Reklamované|Kategorie)/s',
];

// Mapování - co jde do kterého pole
$basicMapping = [
    'cislo_reklamace' => 'cislo',
    'datum_prodeje' => 'datum_prodeje',
    'datum_reklamace' => 'datum_reklamace',
    'jmeno' => 'jmeno',
    'email' => 'email',
    'telefon' => 'telefon',
    'ulice' => 'ulice',
    'mesto' => 'mesto',
    'psc' => 'psc',
    'popis_problemu' => 'popis_problemu'
];

// Technické detaily - uloží se do doplnujici_info
$technicalFields = ['model', 'provedeni', 'barva', 'nohy', 'doplnky'];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>FINÁLNÍ UNIVERZÁLNÍ PARSER</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; max-width: 1400px; margin: 0 auto; }
h1 { color: #4ec9b0; }
h2 { color: #dcdcaa; margin-top: 30px; }
.section { background: #252526; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007acc; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { padding: 10px; border: 1px solid #3e3e3e; text-align: left; vertical-align: top; }
th { background: #264f78; }
.ok { color: #4ec9b0; }
.err { color: #f48771; }
.basic { background: #2d5016; }
.technical { background: #264f78; }
</style></head><body>";

echo "<h1>🎯 FINÁLNÍ UNIVERZÁLNÍ PARSER</h1>";

echo "<div class='section'><h3>Koncept:</h3>";
echo "<p><strong class='basic'>ZÁKLADNÍ POLE</strong> → Vyplní se přímo do formuláře (jméno, telefon, email, adresa, popis...)</p>";
echo "<p><strong class='technical'>TECHNICKÉ DETAILY</strong> → Uloží se do 'Doplňující info' (model, složení, látka...)</p>";
echo "<p>✅ Vše se ULOŽÍ, ale technické detaily nebudou editovatelné ve formuláři</p>";
echo "</div>";

echo "<h2>TEST EXTRAKCE VŠECH POLÍ:</h2>";

echo "<table>";
echo "<tr><th>Typ</th><th>Pole</th><th>Výsledek</th></tr>";

$zakladniData = [];
$technickeData = [];

foreach ($vzechnyPatterns as $klic => $pattern) {
    $jeZakladni = isset($basicMapping[$klic]);

    echo "<tr>";
    echo "<td>" . ($jeZakladni ? "<span class='basic'>ZÁKLADNÍ</span>" : "<span class='technical'>TECHNICKÉ</span>") . "</td>";
    echo "<td><strong>" . htmlspecialchars($klic) . "</strong></td>";

    if (preg_match($pattern, $skutecnyText, $matches)) {
        $hodnota = trim($matches[1]);

        // Uložit do správné kategorie
        if ($jeZakladni) {
            $zakladniData[$klic] = $hodnota;
        } else {
            $technickeData[$klic] = $hodnota;
        }

        // Zobrazit (zkrátit dlouhé)
        $zobrazeni = strlen($hodnota) > 60 ? substr($hodnota, 0, 60) . '...' : $hodnota;
        echo "<td class='ok'>✅ " . htmlspecialchars($zobrazeni) . "</td>";
    } else {
        echo "<td class='err'>❌ NENALEZENO</td>";
    }

    echo "</tr>";
}

echo "</table>";

echo "<div class='section'>";
echo "<h3>🎉 VÝSLEDEK PARSOVÁNÍ:</h3>";
echo "<p><strong>Základních polí nalezeno:</strong> " . count($zakladniData) . "/" . count($basicMapping) . "</p>";
echo "<p><strong>Technických detailů nalezeno:</strong> " . count($technickeData) . "/" . count($technicalFields) . "</p>";

if (count($zakladniData) >= 8) {
    echo "<p class='ok'>✅ Dostatek základních polí - parser je FUNKČNÍ!</p>";
} else {
    echo "<p class='err'>⚠️ Málo základních polí - možná potřebují úpravy</p>";
}
echo "</div>";

// Ukázka jak se to uloží
echo "<div class='section'>";
echo "<h3>📊 Jak se data uloží do databáze:</h3>";

echo "<h4>Základní pole (přímo do sloupců):</h4>";
echo "<pre>";
foreach ($zakladniData as $klic => $hodnota) {
    $sloupec = $basicMapping[$klic];
    echo "$sloupec = " . htmlspecialchars(substr($hodnota, 0, 50)) . "\n";
}
echo "</pre>";

echo "<h4>Technické detaily (do doplnujici_info):</h4>";
echo "<pre>";
$doplnujiciInfo = "=== TECHNICKÉ DETAILY Z PDF ===\n\n";
foreach ($technickeData as $klic => $hodnota) {
    $doplnujiciInfo .= strtoupper($klic) . ":\n" . $hodnota . "\n\n";
}
echo htmlspecialchars($doplnujiciInfo);
echo "</pre>";
echo "</div>";

if (isset($_GET['execute'])) {
    echo "<h2>SPOUŠTÍM MIGRACI...</h2>";

    $pdo = getDbConnection();

    // Uložit patterns do databáze
    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'natuzzi'");
    $stmt->execute([
        json_encode($vzechnyPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($basicMapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:#4ec9b0;'>✅ NATUZZI aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    // PHASE SK
    $phaseSkPatterns = $vzechnyPatterns;
    $phaseSkPatterns['datum_reklamace'] = '/(\d{1,2}\.\d{1,2}\.\d{4})\s+Dátum\s+podania/i';
    $phaseSkPatterns['telefon'] = '/(\d{3}\s+\d{3}\s+\d{3,4})\s+Telefón/i';
    $phaseSkPatterns['mesto'] = '/\b([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\s+Mesto/iu';
    $phaseSkPatterns['jmeno'] = '/Meno\s+spoločnosti:\s+([^Pp]{3,50}?)\s+(?:Poschodie|PSČ)/iu';
    $phaseSkPatterns['provedeni'] = '/Zloženie:\s+(.+?)\s+Látka:/s';

    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'phase' AND nazev LIKE '%SK%'");
    $stmt->execute([
        json_encode($phaseSkPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($basicMapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:#4ec9b0;'>✅ PHASE SK aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    // PHASE CZ
    $phaseCzPatterns = $vzechnyPatterns;
    $phaseCzPatterns['cislo_reklamace'] = '/([A-Z]{2,3}\d+-\d{8}-\d{2})/i';

    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'phase_cz'");
    $stmt->execute([
        json_encode($phaseCzPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($basicMapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:#4ec9b0;'>✅ PHASE CZ aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    echo "<h2 style='color:#4ec9b0;'>🎉 HOTOVO!</h2>";
    echo "<p>⚠️ <strong>DŮLEŽITÉ:</strong> Musíš ještě upravit <code>api/parse_povereni_pdf.php</code> aby technické detaily ukládal do <code>doplnujici_info</code>!</p>";
    echo "<p><a href='novareklamace.php' style='background:#4ec9b0; color:#1e1e1e; padding:10px 20px; text-decoration:none; border-radius:5px;'>→ ZKUSIT</a></p>";
} else {
    echo "<p><a href='?execute=1' style='background:#4ec9b0; color:#1e1e1e; padding:10px 20px; text-decoration:none; display:inline-block; margin-top:20px; border-radius:5px;'>✅ SPUSTIT MIGRACI</a></p>";
}

echo "</body></html>";
?>
