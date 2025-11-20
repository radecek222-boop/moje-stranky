<?php
/**
 * OPRAVA patterns podle SKUTEČNÉHO textu z PDF.js
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$skutecnyText = 'Čislo reklamace:  NCE25-00002444-39  NCE25-00002444-39/CZ785-2025  12.11.2025 Datum podání:  Číslo objednávky:  Číslo faktury:  Datum vyhotovení:  25250206  12.11.2025  0  Jméno a příjmení:  Česko Stát:  25242 PSČ:  Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch  Poschodí:  Rodinný dům   Panelový dům  Místo reklamace  kmochova@petrisk.cz  725 387 868 Telefon:  Česko Stát:  25242  Email:  Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch Jméno a příjmení:  PSČ:  Zákazník  Vyjádření prodávajícího: reklamace bude vyřešena do 30 dní od obhlídky servisního technika, který určí způsob odstránění závady reklamovaného zboží  Závada:   Tak odstáté polštáře, že se na posteli nedá spát. Prosím o rychlé řešení. Děkuji a fotky přikládám. Na webových stránkách nic takového není.  Model:   C157 Intenso; LE02 Orbitale; Matrace  Složení:   450 1,5 sed Ľ s područkou a elektr. výsuvem (1); 338 1,5 sed BP s výsuvem eletrickým (1); 011 Roh (1); 291 1,5 sed BP (1); 274 1,5 sed P s področkou (1); 830 Battery Bank " LIB " (2); C04 posteľ s úložným priestorom, rošt 193 x 200 cm (1); Matrac Capri 193x200x25 cm tvrdší (1)  Látka:   TG 20JJ Light Beige; INÉ; 70.0077.02 Rose';

// NOVÉ PATTERNS - založené na SKUTEČNÉM textu!
$natuzziPatterns = [
    // Číslo reklamace - FUNGUJE
    'cislo_reklamace' => '/Čislo\s+reklamace:\s+([A-Z0-9\-\/]+)/i',

    // Datum prodeje - hledá DRUHÝ výskyt data (za "Datum vyhotovení:")
    'datum_prodeje' => '/Datum\s+vyhotovení:.*?(\d{1,2}\.\d{1,2}\.\d{4})/s',

    // Datum reklamace - datum je PŘED "Datum podání:"!
    'datum_reklamace' => '/(\d{1,2}\.\d{1,2}\.\d{4})\s+Datum\s+podání:/i',

    // Jméno - mezi "Jméno společnosti:" a "Poschodí:"
    'jmeno' => '/Jméno\s+společnosti:\s+([A-Z][a-záčďéěíňóřšťúůýž\s]+?)\s+Poschodí:/iu',

    // Email - univerzální
    'email' => '/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',

    // Telefon - číslo je PŘED "Telefon:"!
    'telefon' => '/(\d{3}\s+\d{3}\s+\d{3})\s+Telefon:/i',

    // Ulice - mezi "Adresa:" a "Jméno společnosti:" (PRVNÍ výskyt!)
    'ulice' => '/Adresa:\s+([^A-Z]+?)\s+Jméno\s+společnosti:/s',

    // Město - mezi "Město:" a "Adresa:" (PRVNÍ výskyt!)
    'mesto' => '/Město:\s+([A-Z][a-záčďéěíňóřšťúůýž\s]+?)\s+Adresa:/iu',

    // PSČ - PRVNÍ výskyt
    'psc' => '/PSČ:\s+(\d{3}\s?\d{2})/i',

    // Model - mezi "Model:" a "Složení:"
    'model' => '/Model:\s+(.+?)\s+Složení:/s',

    // Provedení/Složení - mezi "Složení:" a "Látka:"
    'provedeni' => '/Složení:\s+(.+?)\s+Látka:/s',

    // Barva/Látka - mezi "Látka:" a "Nohy:"
    'barva' => '/Látka:\s+(.+?)\s+Nohy:/s',

    // Popis problému - mezi "Závada:" a "Model:" (nebo konec)
    'popis_problemu' => '/Závada:\s+(.+?)\s+Model:/s'
];

$mapping = [
    'cislo_reklamace' => 'cislo',
    'datum_prodeje' => 'datum_prodeje',
    'datum_reklamace' => 'datum_reklamace',
    'jmeno' => 'jmeno',
    'email' => 'email',
    'telefon' => 'telefon',
    'ulice' => 'ulice',
    'mesto' => 'mesto',
    'psc' => 'psc',
    'model' => 'model',
    'provedeni' => 'provedeni',
    'barva' => 'barva',
    'popis_problemu' => 'popis_problemu'
];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>TEST NOVÝCH PATTERNS</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
h1 { color: #4ec9b0; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px; border: 1px solid #3e3e3e; text-align: left; }
th { background: #264f78; }
.ok { color: #4ec9b0; }
.err { color: #f48771; }
</style></head><body>";

echo "<h1>🔍 TEST PATTERNS NA SKUTEČNÉM TEXTU</h1>";

echo "<table><tr><th>Pole</th><th>Pattern</th><th>Výsledek</th></tr>";

foreach ($natuzziPatterns as $klic => $pattern) {
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($klic) . "</strong></td>";
    echo "<td><code>" . htmlspecialchars(substr($pattern, 0, 50)) . "...</code></td>";

    if (preg_match($pattern, $skutecnyText, $matches)) {
        $hodnota = htmlspecialchars(trim($matches[1]));
        echo "<td class='ok'>✅ " . substr($hodnota, 0, 80) . "</td>";
    } else {
        echo "<td class='err'>❌ NENALEZENO</td>";
    }

    echo "</tr>";
}

echo "</table>";

if (isset($_GET['execute'])) {
    echo "<h2>SPOUŠTÍM MIGRACI...</h2>";

    $pdo = getDbConnection();

    // NATUZZI
    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'natuzzi'");
    $stmt->execute([
        json_encode($natuzziPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($mapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:#4ec9b0;'>✅ NATUZZI aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    // PHASE SK (použít slovenské termíny)
    $phaseSkPatterns = $natuzziPatterns;
    $phaseSkPatterns['cislo_reklamace'] = '/Číslo\s+reklamácie:\s+([A-Z0-9\-\/]+)/i';
    $phaseSkPatterns['datum_prodeje'] = '/Dátum\s+vyhotovenia:.*?(\d{1,2}\.\d{1,2}\.\d{4})/s';
    $phaseSkPatterns['datum_reklamace'] = '/(\d{1,2}\.\d{1,2}\.\d{4})\s+Dátum\s+podania:/i';
    $phaseSkPatterns['jmeno'] = '/Meno\s+spoločnosti:\s+([A-Z][a-záčďéěíňóřšťúůýž\s]+?)\s+Poschodie:/iu';
    $phaseSkPatterns['telefon'] = '/(\d{3}\s+\d{3}\s+\d{3,4})\s+Telefón:/i';
    $phaseSkPatterns['mesto'] = '/Mesto:\s+([A-Z][a-záčďéěíňóřšťúůýž\s]+?)\s+Adresa:/iu';
    $phaseSkPatterns['provedeni'] = '/Zloženie:\s+(.+?)\s+Látka:/s';
    $phaseSkPatterns['barva'] = '/Látka:\s+(.+?)\s+Nohy:/s';

    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'phase' AND nazev LIKE '%SK%'");
    $stmt->execute([
        json_encode($phaseSkPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($mapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:#4ec9b0;'>✅ PHASE SK aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    // PHASE CZ
    $phaseCzPatterns = $natuzziPatterns;
    $phaseCzPatterns['cislo_reklamace'] = '/Číslo\s+serv\.\s+opravy:\s+([A-Z0-9\-\/]+)/i';

    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'phase_cz'");
    $stmt->execute([
        json_encode($phaseCzPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($mapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:#4ec9b0;'>✅ PHASE CZ aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    echo "<h2 style='color:#4ec9b0;'>🎉 HOTOVO!</h2>";
    echo "<p><a href='novareklamace.php'>→ OTESTOVAT V PRODUKCI</a></p>";
} else {
    echo "<p><a href='?execute=1' style='background:#4ec9b0; color:#1e1e1e; padding:10px 20px; text-decoration:none; display:inline-block; margin-top:20px; border-radius:5px;'>✅ VYPADÁ DOBŘE - SPUSTIT MIGRACI</a></p>";
}

echo "</body></html>";
?>
