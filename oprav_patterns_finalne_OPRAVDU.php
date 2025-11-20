<?php
/**
 * FINÁLNÍ OPRAVA patterns - založeno na SKUTEČNÉM PDF textu
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Finální Oprava Patterns</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #dee2e6; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Finální Oprava Patterns</h1>";

    if (!isset($_GET['execute'])) {
        echo "<div class='info'><strong>OPRAVY KTERÉ BUDOU PROVEDENY:</strong><br><br>";
        echo "1. <strong>datum_prodeje</strong> - Přeskočí číslo před datem (25250206)<br>";
        echo "2. <strong>ulice</strong> - Opraví pattern aby nebral 'Jméno společnosti'<br>";
        echo "3. <strong>model, provedeni, barva, popis_problemu</strong> - Flexibilnější patterns<br>";
        echo "</div>";
        echo "<a href='?execute=1' class='btn'>✅ SPUSTIT OPRAVU</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

    $pdo->beginTransaction();

    // ============================================
    // NATUZZI - OPRAVENÉ PATTERNS
    // ============================================
    echo "<div class='info'><strong>1️⃣ Opravuji NATUZZI patterns...</strong></div>";

    $natuzziPatterns = [
        'cislo_reklamace' => '/(?:Čislo|Číslo)\s+reklamace:\s*([A-Z0-9\-\/]+)/i',
        // OPRAVA: Přeskočit číslo před datem pomocí .*?
        'datum_prodeje' => '/Datum\s+vyhotovení:.*?(\d{1,2}\.\d{1,2}\.\d{4})/is',
        'datum_reklamace' => '/Datum\s+podání:\s*(\d{1,2}\.\d{1,2}\.\d{4})/i',
        'jmeno' => '/Jméno\s+společnosti:\s*([^\n]+?)(?:\s+Poschodí|\s+Jméno\s+a\s+příjmení)/s',
        'email' => '/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
        'telefon' => '/Telefon:\s*(\d{3}\s*\d{3}\s*\d{3})/i',
        // OPRAVA: Adresa je VE DVOJICI s Městem, hledám ji před "Jméno společnosti" (druhý výskyt)
        'ulice' => '/Adresa:\s*([^\n]+?)\s+Jméno\s+společnosti:/s',
        'mesto' => '/Město:\s*([^\n]+?)\s+Adresa:/s',
        'psc' => '/PSČ:\s*(\d{3}\s?\d{2})/i',
        // Flexibilnější patterns pro nepovinná pole
        'model' => '/Model:\s*([^\n]+?)(?:\s+(?:Složení|Provedení|Látka|Barva|Závada|Popis)|$)/is',
        'provedeni' => '/(?:Složení|Provedení):\s*([^\n]+?)(?:\s+(?:Látka|Barva|Závada|Popis)|$)/is',
        'barva' => '/(?:Látka|Barva):\s*([^\n]+?)(?:\s+(?:Závada|Popis)|$)/is',
        'popis_problemu' => '/(?:Závada|Popis\s+problému):\s*([^\n]+?)(?:\s+(?:Poznámky|Datum\s+opravy|Cena)|$)/is'
    ];

    $natuzziMapping = [
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

    $stmt = $pdo->prepare("
        UPDATE wgs_pdf_parser_configs
        SET regex_patterns = :patterns,
            pole_mapping = :mapping,
            aktivni = 1
        WHERE zdroj = 'natuzzi'
    ");

    $stmt->execute([
        'patterns' => json_encode($natuzziPatterns, JSON_UNESCAPED_UNICODE),
        'mapping' => json_encode($natuzziMapping, JSON_UNESCAPED_UNICODE)
    ]);

    echo "<div class='success'>✅ NATUZZI: Aktualizováno {$stmt->rowCount()} konfigurací</div>";

    // ============================================
    // PHASE SK - OPRAVENÉ PATTERNS
    // ============================================
    echo "<div class='info'><strong>2️⃣ Opravuji PHASE SK patterns...</strong></div>";

    $phaseSkPatterns = [
        'cislo_reklamace' => '/Číslo\s+reklamácie:\s*([A-Z0-9\-\/]+)/i',
        'datum_prodeje' => '/Dátum\s+vyhotovenia:.*?(\d{1,2}\.\d{1,2}\.\d{4})/is',
        'datum_reklamace' => '/Dátum\s+podania:\s*(\d{1,2}\.\d{1,2}\.\d{4})/i',
        'jmeno' => '/Meno\s+spoločnosti:\s*([^\n]+?)(?:\s+Poschodie|\s+Meno\s+a\s+priezvisko)/s',
        'email' => '/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
        'telefon' => '/Telefón:\s*(\d{3}\s*\d{3}\s*\d{3,4})/i',
        'ulice' => '/Adresa:\s*([^\n]+?)\s+Meno\s+spoločnosti:/s',
        'mesto' => '/Mesto:\s*([^\n]+?)\s+Adresa:/s',
        'psc' => '/PSČ:\s*(\d{3}\s?\d{2})/i',
        'model' => '/Model:\s*([^\n]+?)(?:\s+(?:Zloženie|Provedenie|Látka|Farba|Závada)|$)/is',
        'provedeni' => '/(?:Zloženie|Provedenie):\s*([^\n]+?)(?:\s+(?:Látka|Farba|Závada)|$)/is',
        'barva' => '/(?:Látka|Farba):\s*([^\n]+?)(?:\s+Závada|$)/is',
        'popis_problemu' => '/Závada:\s*([^\n]+?)(?:\s+(?:Poznámky|Dátum\s+opravy|Cena)|$)/is'
    ];

    $phaseSkMapping = [
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

    $stmt = $pdo->prepare("
        UPDATE wgs_pdf_parser_configs
        SET regex_patterns = :patterns,
            pole_mapping = :mapping,
            aktivni = 1
        WHERE zdroj = 'phase' AND nazev LIKE '%SK%'
    ");

    $stmt->execute([
        'patterns' => json_encode($phaseSkPatterns, JSON_UNESCAPED_UNICODE),
        'mapping' => json_encode($phaseSkMapping, JSON_UNESCAPED_UNICODE)
    ]);

    echo "<div class='success'>✅ PHASE SK: Aktualizováno {$stmt->rowCount()} konfigurací</div>";

    // ============================================
    // PHASE CZ - OPRAVENÉ PATTERNS
    // ============================================
    echo "<div class='info'><strong>3️⃣ Opravuji PHASE CZ patterns...</strong></div>";

    $phaseCzPatterns = [
        'cislo_reklamace' => '/Číslo\s+serv\.\s+opravy:\s*([A-Z0-9\-\/]+)/i',
        'datum_prodeje' => '/Datum\s+vyhotovení:.*?(\d{1,2}\.\d{1,2}\.\d{4})/is',
        'datum_reklamace' => '/Datum\s+podání:\s*(\d{1,2}\.\d{1,2}\.\d{4})/i',
        'jmeno' => '/Jméno\s+společnosti:\s*([^\n]+?)(?:\s+Poschodí|\s+Jméno\s+a\s+příjmení)/s',
        'email' => '/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
        'telefon' => '/Telefon:\s*(\d{3}\s*\d{3}\s*\d{3})/i',
        'ulice' => '/Adresa:\s*([^\n]+?)\s+Jméno\s+společnosti:/s',
        'mesto' => '/Město:\s*([^\n]+?)\s+Adresa:/s',
        'psc' => '/PSČ:\s*(\d{3}\s?\d{2})/i',
        'model' => '/Model:\s*([^\n]+?)(?:\s+(?:Složení|Provedení|Látka|Barva|Závada)|$)/is',
        'provedeni' => '/(?:Složení|Provedení):\s*([^\n]+?)(?:\s+(?:Látka|Barva|Závada)|$)/is',
        'barva' => '/(?:Látka|Barva):\s*([^\n]+?)(?:\s+Závada|$)/is',
        'popis_problemu' => '/Závada:\s*([^\n]+?)(?:\s+(?:Poznámky|Datum\s+opravy|Cena)|$)/is'
    ];

    $phaseCzMapping = [
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

    $stmt = $pdo->prepare("
        UPDATE wgs_pdf_parser_configs
        SET regex_patterns = :patterns,
            pole_mapping = :mapping,
            aktivni = 1
        WHERE zdroj = 'phase_cz'
    ");

    $stmt->execute([
        'patterns' => json_encode($phaseCzPatterns, JSON_UNESCAPED_UNICODE),
        'mapping' => json_encode($phaseCzMapping, JSON_UNESCAPED_UNICODE)
    ]);

    echo "<div class='success'>✅ PHASE CZ: Aktualizováno {$stmt->rowCount()} konfigurací</div>";

    $pdo->commit();

    echo "<div class='success'><strong>🎉 OPRAVA DOKONČENA!</strong><br><br>";
    echo "Klíčové opravy:<br>";
    echo "• datum_prodeje nyní přeskakuje číslo před datem (.*?)<br>";
    echo "• ulice hledá správný výskyt (druhý blok Adresa+Město)<br>";
    echo "• Flexibilnější patterns pro nepovinná pole<br>";
    echo "</div>";

    echo "<a href='test_pdf_parsing.php' class='btn'>🧪 OTESTOVAT NYNÍ</a>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<div class='error'>❌ CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
