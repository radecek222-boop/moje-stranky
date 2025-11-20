<?php
/**
 * Analýza struktury PDF z Base64 souborů
 *
 * Tento skript dekóduje Base64 PDF a zobrazí jejich textovou strukturu
 * pro účely vytvoření přesných regex patterns
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Analýza: Struktura PDF</title>
    <style>
        body { font-family: 'Segoe UI', monospace; max-width: 1400px;
               margin: 20px auto; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .container { background: #2d2d30; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; margin-top: 30px; border-bottom: 2px solid #569cd6;
             padding-bottom: 5px; }
        .pdf-box { background: #252526; padding: 20px; margin: 20px 0;
                   border-radius: 5px; border-left: 4px solid #007acc; }
        pre { background: #1e1e1e; color: #ce9178; padding: 15px;
              border-radius: 5px; overflow-x: auto; font-size: 0.9em;
              white-space: pre-wrap; word-wrap: break-word; }
        .highlight { background: yellow; color: black; font-weight: bold; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Analýza Struktury PDF z Base64 Souborů</h1>";

// Seznam souborů k analýze
$soubory = [
    'uploads/base64.txt' => 'NATUZZI PROTOKOL.pdf',
    'uploads/base64-2.txt' => 'NCM-NATUZZI.pdf',
    'uploads/base64-3.txt' => 'PHASE CZ.pdf',
    'uploads/base64-4.txt' => 'PHASE PROTOKOL SK.pdf'
];

foreach ($soubory as $cesta => $nazev) {
    echo "<div class='pdf-box'>";
    echo "<h2>📄 {$nazev}</h2>";

    $uplnaCesta = __DIR__ . '/' . $cesta;

    if (!file_exists($uplnaCesta)) {
        echo "<div class='error'>❌ Soubor neexistuje: {$cesta}</div>";
        echo "</div>";
        continue;
    }

    // Načíst Base64
    $base64 = file_get_contents($uplnaCesta);

    // Dekódovat
    $pdfData = base64_decode($base64);

    if ($pdfData === false) {
        echo "<div class='error'>❌ Chyba při dekódování Base64</div>";
        echo "</div>";
        continue;
    }

    // Extrahovat text z PDF (jednoduchý způsob - hledání textu mezi stream objekty)
    $text = '';

    // Metoda 1: Extrakce textu pomocí regex (jednoduchá metoda)
    // PDF obsahuje text v BT...ET blocích
    if (preg_match_all('/BT\s*(.*?)\s*ET/s', $pdfData, $matches)) {
        foreach ($matches[1] as $textBlock) {
            // Extrahovat text v závorkách () nebo <>
            if (preg_match_all('/\((.*?)\)/s', $textBlock, $textMatches)) {
                foreach ($textMatches[1] as $txt) {
                    $text .= $txt . ' ';
                }
            }
        }
    }

    // Metoda 2: Fallback - prostě najít všechny tisknutelné texty
    if (empty($text)) {
        // Extrahovat všechny tisknutelné znaky
        $text = preg_replace('/[^\x20-\x7E\x80-\xFF]+/', ' ', $pdfData);
        // Odstranit PDF specifické části
        $text = preg_replace('/(\/[A-Za-z]+|<<|>>|\[|\])/s', ' ', $text);
    }

    // Vyčistit text
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    echo "<p><strong>Délka PDF:</strong> " . number_format(strlen($pdfData)) . " bytů</p>";
    echo "<p><strong>Délka textu:</strong> " . number_format(strlen($text)) . " znaků</p>";

    // Zobrazit prvních 2000 znaků s highlighty
    $zobrazenyText = substr($text, 0, 2000);

    // Highlighty podle typu PDF
    if (strpos($nazev, 'NATUZZI') !== false) {
        $zobrazenyText = preg_replace('/(Místo reklamace)/', '<span class="highlight">$1</span>', $zobrazenyText);
        $zobrazenyText = preg_replace('/(PSČ:)/', '<span class="highlight">$1</span>', $zobrazenyText);
        $zobrazenyText = preg_replace('/(Adresa:)/', '<span class="highlight">$1</span>', $zobrazenyText);
    } elseif (strpos($nazev, 'PHASE CZ') !== false) {
        $zobrazenyText = preg_replace('/(Místo servisní opravy)/', '<span class="highlight">$1</span>', $zobrazenyText);
        $zobrazenyText = preg_replace('/(Číslo serv\. opravy)/', '<span class="highlight">$1</span>', $zobrazenyText);
    } elseif (strpos($nazev, 'PHASE') !== false && strpos($nazev, 'SK') !== false) {
        $zobrazenyText = preg_replace('/(Miesto reklamácie)/', '<span class="highlight">$1</span>', $zobrazenyText);
        $zobrazenyText = preg_replace('/(Meno a priezvisko)/', '<span class="highlight">$1</span>', $zobrazenyText);
        $zobrazenyText = preg_replace('/(Dátum podania)/', '<span class="highlight">$1</span>', $zobrazenyText);
    }

    echo "<h3>📋 Text (prvních 2000 znaků):</h3>";
    echo "<pre>" . htmlspecialchars($zobrazenyText) . "</pre>";

    // Zobrazit klíčové patterny
    echo "<h3>🔍 Detekované klíčové fráze:</h3>";
    echo "<ul>";

    $klicovaSlova = [
        'Místo reklamace',
        'Místo servisní opravy',
        'Miesto reklamácie',
        'Meno a priezvisko',
        'Jméno a příjmení',
        'Dátum podania',
        'Datum podání',
        'Číslo reklamace',
        'Číslo serv. opravy',
        'Číslo reklamácie'
    ];

    foreach ($klicovaSlova as $slovo) {
        if (stripos($text, $slovo) !== false) {
            echo "<li class='success'>✅ Nalezeno: <strong>" . htmlspecialchars($slovo) . "</strong></li>";
        }
    }
    echo "</ul>";

    echo "</div>";
}

echo "<h2>📊 Závěry pro Regex Patterns:</h2>";
echo "<div class='pdf-box'>";
echo "<h3>1. NATUZZI Protokol - Detekce:</h3>";
echo "<pre>Pattern: /(Místo reklamace|Panelový dům|NCE\d+|NCM\d+)/</pre>";
echo "<p><strong>Důvod:</strong> Typické fráze v NATUZZI protokolech</p>";

echo "<h3>2. PHASE CZ - Detekce (VYSOKÁ PRIORITA!):</h3>";
echo "<pre>Pattern: /(Místo servisní opravy|Číslo serv\\. opravy)/</pre>";
echo "<p><strong>Důvod:</strong> POUZE české PHASE protokoly obsahují 'Místo servisní opravy'</p>";

echo "<h3>3. PHASE SK - Detekce:</h3>";
echo "<pre>Pattern: /(Miesto reklamácie|Meno a priezvisko|Dátum podania)/</pre>";
echo "<p><strong>Důvod:</strong> Slovenské texty jsou jedinečné</p>";

echo "<h3>4. Priorita:</h3>";
echo "<pre>
PHASE CZ: 95 (vyšší než PHASE SK!)
PHASE SK: 90
NATUZZI:  100 (nejvyšší - default)
</pre>";
echo "</div>";

echo "<h2>🛠️ Doporučené akce:</h2>";
echo "<div class='warning'>";
echo "<p><strong>1.</strong> Vytvořit migrační skript s těmito PŘESNÝMI patterns</p>";
echo "<p><strong>2.</strong> Nastavit správné priority (PHASE CZ > PHASE SK)</p>";
echo "<p><strong>3.</strong> Opravit field extraction patterns pro PSČ, ulici, email, telefon</p>";
echo "</div>";

echo "</div></body></html>";
?>
