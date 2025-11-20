<?php
/**
 * FINÁLNÍ OPRAVA - Patterns založené na 100% ověřených datech z PDF
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$pdo = getDbConnection();

// ============================================
// TESTOVACÍ TEXT Z NATUZZI PDF (ověřený!)
// ============================================
$natuzziText = <<<'TEXT'
Čislo reklamace: NCE25-00002444-39 NCE25-00002444-39/CZ785-2025
Datum podání: 12.11.2025
Číslo objednávky:
Číslo faktury:
Datum vyhotovení: 25250206 12.11.2025 0
Jméno a příjmení:
Stát: Česko
PSČ: 25242
Město: Osnice
Adresa: Na Blatech 396
Jméno společnosti: Petr Kmoch
Poschodí: Rodinný dům Panelový dům
Místo reklamace
kmochova@petrisk.cz
Telefon: 725 387 868
Stát: Česko
PSČ: 25242
Email:
Osnice Město:
Na Blatech 396 Adresa:
Jméno společnosti: Petr Kmoch
TEXT;

// ============================================
// NATUZZI PATTERNS - ZALOŽENO NA SKUTEČNÉM TEXTU
// ============================================
$natuzziPatterns = [
    // Číslo reklamace - FUNGUJE
    'cislo_reklamace' => '/Čislo\s+reklamace:\s*([A-Z0-9\-\/]+)/i',

    // Datum prodeje - OPRAVA: Přeskočí číslo 25250206
    'datum_prodeje' => '/Datum\s+vyhotovení:.*?(\d{1,2}\.\d{1,2}\.\d{4})/s',

    // Datum reklamace - FUNGUJE
    'datum_reklamace' => '/Datum\s+podání:\s*(\d{1,2}\.\d{1,2}\.\d{4})/i',

    // Jméno - OPRAVA: Adresa je PŘED "Jméno společnosti"
    'jmeno' => '/Jméno\s+společnosti:\s*([^\r\n]+?)(?:\s+Poschodí|\s*\r?\n)/s',

    // Email - FUNGUJE
    'email' => '/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',

    // Telefon - FUNGUJE
    'telefon' => '/Telefon:\s*(\d{3}\s*\d{3}\s*\d{3})/i',

    // ULICE - PRVNÍ výskyt "Adresa:" (ten správný!)
    'ulice' => '/Adresa:\s*([^\r\n]+?)(?:\s+Jméno\s+společnosti|\s*\r?\n)/s',

    // Město - PRVNÍ výskyt "Město:" (ten správný!)
    'mesto' => '/Město:\s*([^\r\n]+?)(?:\s+Adresa|\s*\r?\n)/s',

    // PSČ - FUNGUJE
    'psc' => '/PSČ:\s*(\d{3}\s?\d{2})/i',

    // Model - jen pokud existuje
    'model' => '/Model:\s*([^\r\n]+?)(?:\s+Složení|\s*\r?\n)/is',

    // Provedení/Složení - jen pokud existuje
    'provedeni' => '/(?:Složení|Provedení):\s*([^\r\n]+?)(?:\s+Látka|\s*\r?\n)/is',

    // Barva/Látka - jen pokud existuje
    'barva' => '/(?:Látka|Barva):\s*([^\r\n]+?)(?:\s+Závada|\s+Nohy:|\s*\r?\n)/is',

    // Popis problému - OPRAVA: Zastaví se PŘED "Model:"
    'popis_problemu' => '/(?:Závada|Popis\s+problému):\s*(.+?)(?=\s*Model:|\s*Složení:|\s*Kategorie:|\s*Reklamované\s+zboží|$)/is'
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

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>TEST</title></head><body>";
echo "<h1>TEST PATTERNS</h1>";

// Otestovat každý pattern
echo "<table border='1' style='width:100%; border-collapse:collapse;'>";
echo "<tr><th>Pole</th><th>Výsledek</th></tr>";

foreach ($natuzziPatterns as $klic => $pattern) {
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($klic) . "</strong></td>";

    if (preg_match($pattern, $natuzziText, $matches)) {
        $hodnota = htmlspecialchars(trim($matches[1]));
        echo "<td style='color:green;'>✅ " . $hodnota . "</td>";
    } else {
        echo "<td style='color:red;'>❌ NENALEZENO</td>";
    }

    echo "</tr>";
}

echo "</table>";

// SPUSTIT MIGRACI?
if (isset($_GET['execute'])) {
    echo "<h2>SPOUŠTÍM MIGRACI...</h2>";

    // NATUZZI
    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'natuzzi'");
    $stmt->execute([
        json_encode($natuzziPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($mapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:green;'>✅ NATUZZI aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    // PHASE SK (použít slovenské termíny)
    $phaseSkPatterns = $natuzziPatterns;
    $phaseSkPatterns['cislo_reklamace'] = '/Číslo\s+reklamácie:\s*([A-Z0-9\-\/]+)/i';
    $phaseSkPatterns['datum_prodeje'] = '/Dátum\s+vyhotovenia:.*?(\d{1,2}\.\d{1,2}\.\d{4})/s';
    $phaseSkPatterns['datum_reklamace'] = '/Dátum\s+podania:\s*(\d{1,2}\.\d{1,2}\.\d{4})/i';
    $phaseSkPatterns['jmeno'] = '/Meno\s+spoločnosti:\s*([^\r\n]+?)(?:\s+Poschodie|\s*\r?\n)/s';
    $phaseSkPatterns['telefon'] = '/Telefón:\s*(\d{3}\s*\d{3}\s*\d{3,4})/i';
    $phaseSkPatterns['ulice'] = '/Adresa:\s*([^\r\n]+?)(?:\s+Meno\s+spoločnosti|\s*\r?\n)/s';
    $phaseSkPatterns['mesto'] = '/Mesto:\s*([^\r\n]+?)(?:\s+Adresa|\s*\r?\n)/s';
    $phaseSkPatterns['provedeni'] = '/(?:Zloženie|Provedenie):\s*([^\r\n]+?)(?:\s+Látka|\s*\r?\n)/is';
    $phaseSkPatterns['barva'] = '/(?:Látka|Farba):\s*([^\r\n]+?)(?:\s+Závada|\s*\r?\n)/is';

    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'phase' AND nazev LIKE '%SK%'");
    $stmt->execute([
        json_encode($phaseSkPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($mapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:green;'>✅ PHASE SK aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    // PHASE CZ
    $phaseCzPatterns = $natuzziPatterns;
    $phaseCzPatterns['cislo_reklamace'] = '/Číslo\s+serv\.\s+opravy:\s*([A-Z0-9\-\/]+)/i';

    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET regex_patterns = ?, pole_mapping = ? WHERE zdroj = 'phase_cz'");
    $stmt->execute([
        json_encode($phaseCzPatterns, JSON_UNESCAPED_UNICODE),
        json_encode($mapping, JSON_UNESCAPED_UNICODE)
    ]);
    echo "<p style='color:green;'>✅ PHASE CZ aktualizováno: " . $stmt->rowCount() . " řádků</p>";

    echo "<h2 style='color:green;'>🎉 HOTOVO!</h2>";
    echo "<p><a href='test_pdf_parsing.php'>→ OTESTOVAT NYNÍ</a></p>";
} else {
    echo "<p><a href='?execute=1' style='background:green; color:white; padding:10px 20px; text-decoration:none; display:inline-block; margin-top:20px;'>✅ PATTERNS VYPADAJÍ DOBŘE - SPUSTIT MIGRACI</a></p>";
}

echo "</body></html>";
?>
