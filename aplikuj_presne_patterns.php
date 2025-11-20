<?php
/**
 * FINÁLNÍ APLIKACE: Přesné patterns podle analýzy
 *
 * Tento skript aplikuje patterns založené na:
 * - Skutečném textu z NATUZZI PROTOKOL.pdf
 * - Přesné analýze v ANALYZA_PDF_SQL_MAPOVANI.md
 * - Manuálně vyplněné reklamaci
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>FINÁLNÍ: Přesné Patterns</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1400px;
               margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        h2 { color: #2D5016; margin-top: 30px; }
        .success { background: #d4edda; color: #155724; padding: 15px;
                   border-radius: 5px; margin: 15px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px;
                 border-radius: 5px; margin: 15px 0; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px;
                border-radius: 5px; margin: 15px 0; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; padding: 15px;
                   border-radius: 5px; margin: 15px 0; border: 1px solid #ffeaa7; }
        .btn { display: inline-block; padding: 12px 24px; background: #2D5016;
               color: white; text-decoration: none; border-radius: 5px;
               font-weight: 600; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
        .test-table td:nth-child(1) { font-weight: 600; width: 200px; }
        .test-table td:nth-child(2) { color: #666; font-family: monospace; font-size: 0.85rem; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>
    <h1>✅ FINÁLNÍ: Přesné Patterns</h1>

    <div class='info'>
        <strong>📋 Založeno na analýze:</strong><br>
        • RAW text z <code>test_pdf_extrakce.php</code><br>
        • SQL struktura tabulky <code>wgs_reklamace</code><br>
        • Manuálně vyplněná reklamace podle PDF<br>
        • Přesná analýza v <code>ANALYZA_PDF_SQL_MAPOVANI.md</code>
    </div>

    <h2>🔍 Testovací data z NATUZZI PDF:</h2>
    <div class='warning'>
        <strong>Co MUSÍ být extrahováno z PDF:</strong>
        <table class='test-table'>
            <tr>
                <td><strong>Číslo reklamace:</strong></td>
                <td><code>NCE25-00002444-39/CZ785-2025</code> (druhý výskyt s lomítkem!)</td>
            </tr>
            <tr>
                <td><strong>Datum prodeje:</strong></td>
                <td><code>12.11.2025</code> (z "Datum vyhotovení:")</td>
            </tr>
            <tr>
                <td><strong>Datum reklamace:</strong></td>
                <td><code>12.11.2025</code> (před "Datum podání:")</td>
            </tr>
            <tr>
                <td><strong>Jméno:</strong></td>
                <td><code>Petr Kmoch</code> (mezi "Jméno společnosti:" a "Poschodí:")</td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><code>kmochova@petrisk.cz</code></td>
            </tr>
            <tr>
                <td><strong>Telefon:</strong></td>
                <td><code>725 387 868</code> (před "Telefon:")</td>
            </tr>
            <tr>
                <td><strong>Ulice:</strong></td>
                <td><code>Na Blatech 396</code> (před "Adresa: Jméno společnosti:")</td>
            </tr>
            <tr>
                <td><strong>Město:</strong></td>
                <td><code>Osnice</code> (před "Město:")</td>
            </tr>
            <tr>
                <td><strong>PSČ:</strong></td>
                <td><code>25242</code> (před "PSČ:")</td>
            </tr>
            <tr>
                <td><strong>Model:</strong></td>
                <td><code>C157 Intenso; LE02 Orbitale; Matrace</code></td>
            </tr>
            <tr>
                <td><strong>Provedení/Barva:</strong></td>
                <td><code>TG 20JJ Light Beige; INÉ; 70.0077.02 Rose</code> (do OBOU polí!)</td>
            </tr>
            <tr>
                <td><strong>Popis problému:</strong></td>
                <td><code>Tak odstáté polštáře...</code></td>
            </tr>
        </table>
    </div>

<?php
try {
    $pdo = getDbConnection();

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>🔧 APLIKUJI PŘESNÉ PATTERNS...</strong></div>";

        $pdo->beginTransaction();

        try {
            // NATUZZI - Přesné patterns podle analýzy
            $natuzziPatterns = [
                // Číslo reklamace - DRUHÝ výskyt s lomítkem
                'cislo_reklamace' => '/Čislo reklamace:\s+NCE25-\d+-\d+\s+([A-Z0-9\-\/]+)/ui',

                // Datum vyhotovení - číslo mezi číslem objednávky a datem
                'datum_vyhotoveni' => '/Datum vyhotovení:\s+\d+\s+(\d{1,2}\.\d{1,2}\.\d{4})/ui',

                // Datum podání - datum PŘED "Datum podání:"
                'datum_podani' => '/(\d{1,2}\.\d{1,2}\.\d{4})\s+Datum podání:/ui',

                // Jméno - mezi "Jméno společnosti:" a "Poschodí:"
                'jmeno' => '/Jméno společnosti:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\s+Poschodí:/ui',

                // Email - před číslicemi a "Telefon:"
                'email' => '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s+[\d\s]+Telefon:/ui',

                // Telefon - čísla před "Telefon:"
                'telefon' => '/([\d\s]+)\s+Telefon:/ui',

                // Ulice - před "Adresa: Jméno společnosti:"
                'ulice' => '/([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\w\s]+\d+)\s+Adresa:\s+Jméno společnosti:/ui',

                // Město - před "Město:"
                'mesto' => '/([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\s+Město:/ui',

                // PSČ - 5 číslic před "PSČ:"
                'psc' => '/(\d{5})\s+PSČ:/ui',

                // Model - mezi "Model:" a "Složení:"
                'model' => '/Model:\s+([^\n]+?)\s+Složení:/ui',

                // Látka - mezi "Látka:" a "Nohy:"
                'latka' => '/Látka:\s+([^\n]+?)\s+Nohy:/ui',

                // Látka pro barvu (stejné)
                'latka_barva' => '/Látka:\s+([^\n]+?)\s+Nohy:/ui',

                // Závada - mezi "Závada:" a "Model:"
                'zavada' => '/Závada:\s+([^\n]+?)\s+Model:/ui'
            ];

            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET regex_patterns = :patterns,
                    updated_at = CURRENT_TIMESTAMP
                WHERE zdroj = 'natuzzi'
            ");

            $stmt->execute([
                'patterns' => json_encode($natuzziPatterns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

            echo "<div class='success'>✅ <strong>NATUZZI</strong> - Patterns aplikovány</div>";

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>🎉 FINÁLNÍ PATTERNS APLIKOVÁNY!</strong><br><br>";
            echo "<strong>Klíčové opravy:</strong><br>";
            echo "• Číslo reklamace: <strong>druhý výskyt</strong> s lomítkem<br>";
            echo "• Jméno: mezi <code>Jméno společnosti:</code> a <code>Poschodí:</code><br>";
            echo "• Telefon: <strong>před</strong> textem \"Telefon:\" (ne za)<br>";
            echo "• PSČ: <strong>před</strong> textem \"PSČ:\" (ne za)<br>";
            echo "• Město: <strong>před</strong> textem \"Město:\" (ne za)<br>";
            echo "• Ulice: před <code>Adresa: Jméno společnosti:</code>";
            echo "</div>";

            echo "<a href='novareklamace.php' class='btn'>📄 Vyzkoušet nahrání PDF</a>";
            echo "<a href='test_pdf_extrakce.php' class='btn' style='background:#666;margin-left:10px;'>🔍 Znovu otestovat</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong> Tyto patterns byly vytvořeny na základě <strong>přesné analýzy</strong> skutečného PDF.<br>";
        echo "Byly testovány proti RAW textu z PDF a měly by extrahovat správná data.";
        echo "</div>";

        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>▶️ APLIKOVAT PŘESNÉ PATTERNS</button>";
        echo "</form>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

    <br><a href='admin.php' class='btn' style='background:#666;'>← Zpět do admin panelu</a>

</div>
</body>
</html>
