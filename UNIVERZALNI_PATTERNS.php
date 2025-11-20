<?php
/**
 * UNIVERZÁLNÍ PATTERNS - fungují i když něco chybí!
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$skutecnyText = 'Čislo reklamace:  NCE25-00002444-39  NCE25-00002444-39/CZ785-2025  12.11.2025 Datum podání:  Číslo objednávky:  Číslo faktury:  Datum vyhotovení:  25250206  12.11.2025  0  Jméno a příjmení:  Česko Stát:  25242 PSČ:  Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch  Poschodí:  Rodinný dům   Panelový dům  Místo reklamace  kmochova@petrisk.cz  725 387 868 Telefon:  Česko Stát:  25242  Email:  Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch Jméno a příjmení:  PSČ:  Zákazník  Vyjádření prodávajícího: reklamace bude vyřešena do 30 dní od obhlídky servisního technika, který určí způsob odstránění závady reklamovaného zboží  Závada:   Tak odstáté polštáře, že se na posteli nedá spát. Prosím o rychlé řešení. Děkuji a fotky přikládám. Na webových stránkách nic takového není.  Model:   C157 Intenso; LE02 Orbitale; Matrace  Složení:   450 1,5 sed Ľ s područkou a elektr. výsuvem (1); 338 1,5 sed BP s výsuvem eletrickým (1); 011 Roh (1); 291 1,5 sed BP (1); 274 1,5 sed P s područkou (1); 830 Battery Bank " LIB " (2); C04 posteľ s úložným priestorom, rošt 193 x 200 cm (1); Matrac Capri 193x200x25 cm tvrdší (1)  Látka:   TG 20JJ Light Beige; INÉ; 70.0077.02 Rose';

// UNIVERZÁLNÍ PATTERNS - nezávislé na sobě!
$natuzziPatterns = [
    // Číslo - hledá PRVNÍ výskyt NCE/NCM čísla
    'cislo_reklamace' => '/([A-Z]{2,3}\d{2}-\d{8}-\d{2})/i',

    // Datum reklamace - datum PŘED "Datum podání:"
    'datum_reklamace' => '/(\d{1,2}\.\d{1,2}\.\d{4})\s+Datum\s+podání/i',

    // Datum prodeje - DRUHÝ výskyt data (má číslo před sebou)
    'datum_prodeje' => '/\d{6,8}\s+(\d{1,2}\.\d{1,2}\.\d{4})/i',

    // Email - univerzální
    'email' => '/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',

    // Telefon - 9 číslic před "Telefon:"
    'telefon' => '/(\d{3}\s+\d{3}\s+\d{3})\s+Telefon/i',

    // PSČ - PRVNÍ výskyt 5 číslic (s/bez mezery)
    'psc' => '/\b(\d{3}\s?\d{2})\b.*?PSČ/i',

    // Město - slovo velkým písmenem před "Město:"
    'mesto' => '/\b([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\s+Město/iu',

    // Ulice - text mezi "Adresa:" a dalším velkým slovem/labelem
    'ulice' => '/Adresa:\s+([^A-Z]{5,50}?)\s+(?:[A-Z]|Jméno)/s',

    // Jméno - text mezi "Jméno společnosti:" a dalším labelem (max 50 znaků)
    'jmeno' => '/Jméno\s+společnosti:\s+([^Pp]{3,50}?)\s+(?:Poschodí|PSČ|Stát)/iu',

    // Popis problému - text mezi "Závada:" a "Model:"
    'popis_problemu' => '/Závada:\s+(.+?)\s+Model:/s',

    // Model - text mezi "Model:" a "Složení:"
    'model' => '/Model:\s+(.+?)\s+Složení:/s',

    // Složení - text mezi "Složení:" a "Látka:"
    'provedeni' => '/Složení:\s+(.+?)\s+Látka:/s',

    // Látka - text mezi "Látka:" a dalším labelem
    'barva' => '/Látka:\s+(.+?)\s+(?:Nohy|Doplňky|Reklamované)/s'
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

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>UNIVERZÁLNÍ PATTERNS</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; max-width: 1400px; margin: 0 auto; }
h1 { color: #4ec9b0; }
h2 { color: #dcdcaa; margin-top: 30px; }
.info { background: #264f78; padding: 15px; border-radius: 5px; margin: 20px 0; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { padding: 10px; border: 1px solid #3e3e3e; text-align: left; vertical-align: top; }
th { background: #264f78; }
.ok { color: #4ec9b0; }
.err { color: #f48771; }
.pattern { font-size: 0.9em; color: #dcdcaa; }
</style></head><body>";

echo "<h1>🎯 UNIVERZÁLNÍ PATTERNS</h1>";

echo "<div class='info'><strong>Princip:</strong><br>";
echo "• Každý pattern je NEZÁVISLÝ - funguje i když ostatní pole chybí<br>";
echo "• Hledají se obecné formáty (např. 9 číslic před 'Telefon:')<br>";
echo "• Bez závislosti na přesné struktuře okolních polí</div>";

echo "<h2>TEST NA NATUZZI PROTOKOL (Petr Kmoch, Osnice):</h2>";

echo "<table><tr><th style='width:150px;'>Pole</th><th>Pattern</th><th style='width:250px;'>Výsledek</th></tr>";

$uspech = 0;
$celkem = count($natuzziPatterns);

foreach ($natuzziPatterns as $klic => $pattern) {
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($klic) . "</strong></td>";
    echo "<td class='pattern'>" . htmlspecialchars($pattern) . "</td>";

    if (preg_match($pattern, $skutecnyText, $matches)) {
        $hodnota = trim($matches[1]);
        // Zkrátit dlouhé hodnoty
        if (strlen($hodnota) > 60) {
            $hodnota = substr($hodnota, 0, 60) . '...';
        }
        echo "<td class='ok'>✅ " . htmlspecialchars($hodnota) . "</td>";
        $uspech++;
    } else {
        echo "<td class='err'>❌ NENALEZENO</td>";
    }

    echo "</tr>";
}

echo "</table>";

echo "<h2>VÝSLEDEK: $uspech/$celkem polí nalezeno (" . round(($uspech/$celkem)*100) . "%)</h2>";

if ($uspech >= 9) {
    echo "<div class='info' style='background: #2d5016;'>✅ Alespoň 9 povinných polí funguje! Můžeme spustit migraci.</div>";
} else {
    echo "<div class='info' style='background: #722c24;'>⚠️ Méně než 9 polí - možná potřebují úpravy.</div>";
}

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

    // PHASE SK
    $phaseSkPatterns = $natuzziPatterns;
    $phaseSkPatterns['cislo_reklamace'] = '/([A-Z]{2,3}\d+-\d{8}-\d{2})/i';
    $phaseSkPatterns['datum_reklamace'] = '/(\d{1,2}\.\d{1,2}\.\d{4})\s+Dátum\s+podania/i';
    $phaseSkPatterns['telefon'] = '/(\d{3}\s+\d{3}\s+\d{3,4})\s+Telefón/i';
    $phaseSkPatterns['mesto'] = '/\b([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\s+Mesto/iu';
    $phaseSkPatterns['jmeno'] = '/Meno\s+spoločnosti:\s+([^Pp]{3,50}?)\s+(?:Poschodie|PSČ)/iu';
    $phaseSkPatterns['provedeni'] = '/Zloženie:\s+(.+?)\s+Látka:/s';

    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'phase' AND nazev LIKE '%SK%'");
    $stmt->execute([
        json_encode($phaseSkPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($mapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:#4ec9b0;'>✅ PHASE SK aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    // PHASE CZ
    $phaseCzPatterns = $natuzziPatterns;
    $phaseCzPatterns['cislo_reklamace'] = '/([A-Z]{2,3}\d+-\d{8}-\d{2})/i';

    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'phase_cz'");
    $stmt->execute([
        json_encode($phaseCzPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($mapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:#4ec9b0;'>✅ PHASE CZ aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    echo "<h2 style='color:#4ec9b0;'>🎉 HOTOVO!</h2>";
    echo "<p><a href='novareklamace.php' style='background:#4ec9b0; color:#1e1e1e; padding:10px 20px; text-decoration:none; border-radius:5px;'>→ ZKUSIT V PRODUKCI</a></p>";
} else {
    echo "<p><a href='?execute=1' style='background:#4ec9b0; color:#1e1e1e; padding:10px 20px; text-decoration:none; display:inline-block; margin-top:20px; border-radius:5px;'>✅ SPUSTIT MIGRACI</a></p>";
}

echo "</body></html>";
?>
