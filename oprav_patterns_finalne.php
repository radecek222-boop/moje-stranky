<?php
/**
 * FINÁLNÍ OPRAVA: Regex patterns podle skutečného PDF formátu
 *
 * Tento skript opraví patterns podle zjištění z test_pdf_extrakce.php:
 * - Text je na jednom řádku (bez \n)
 * - "Čislo" je bez háčku
 * - Dvojité mezery mezi hodnotami
 * - Data se opakují (použít lookahead)
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>FINÁLNÍ OPRAVA: Regex Patterns</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px;
               margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
        .highlight { background: #ffeb3b; font-weight: 600; padding: 2px 4px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              border: 1px solid #dee2e6; overflow-x: auto; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 FINÁLNÍ OPRAVA: Regex Patterns</h1>

    <div class='warning'>
        <strong>⚠️ CO BYLO ZJIŠTĚNO Z test_pdf_extrakce.php:</strong><br>
        1. Text z PDF je <span class='highlight'>na jednom řádku</span> (ne na více řádcích)<br>
        2. <span class='highlight'>"Čislo"</span> je bez háčku (ne "Číslo")<br>
        3. <span class='highlight'>Dvojité mezery</span> mezi hodnotami<br>
        4. Data se <span class='highlight'>opakují</span> v textu (např. jméno 2x)<br>
        5. Patterns musí používat <code>\\s+</code> místo <code>\\n</code>
    </div>

<?php
try {
    $pdo = getDbConnection();

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>🔧 SPOUŠTÍM FINÁLNÍ OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            // ========================================
            // NATUZZI - Nové patterns
            // ========================================
            $natuzziPatterns = [
                // Číslo reklamace - toleruje "Čislo" i "Číslo"
                'cislo_reklamace' => '/(?:Č[ií]slo|[CčČ]islo)\s+reklamace:\s+([A-Z0-9\-\/]+)/ui',

                // Datum podání
                'datum_podani' => '/Datum\s+podání:\s+(\d{1,2}\.\d{1,2}\.\d{4})/ui',

                // Číslo objednávky
                'cislo_objednavky' => '/Č[ií]slo\s+objednávky:\s+(\d+)/ui',

                // Číslo faktury
                'cislo_faktury' => '/Č[ií]slo\s+faktury:\s+(\d+)/ui',

                // Datum vyhotovení
                'datum_vyhotoveni' => '/Datum\s+vyhotovení:\s+(\d{1,2}\.\d{1,2}\.\d{4})/ui',

                // Jméno - první výskyt "Jméno a příjmení:" následovaný dvěma slovy
                'jmeno' => '/Jméno\s+a\s+příjmení:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)(?=\s+Poschodí|\s+Místo)/ui',

                // Email
                'email' => '/Email:\s+([\w._%+-]+@[\w.-]+\.[a-zA-Z]{2,})/ui',

                // Telefon - ukončený pomocí lookahead
                'telefon' => '/Telefon:\s+([\d\s]+?)(?=\s+(?:Česko|Stát|Email))/ui',

                // Adresa z "Místo reklamace" sekce
                'adresa' => '/Místo\s+reklamace.*?Adresa:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][^,]+\d+[a-z]?)/uis',

                // Město z "Místo reklamace" sekce
                'mesto' => '/Místo\s+reklamace.*?Město:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)(?=\s+Adresa)/uis',

                // PSČ z "Místo reklamace" sekce
                'psc' => '/Místo\s+reklamace.*?PSČ:\s+(\d{3}\s?\d{2})/uis',

                // Model - ukončený "Složení:"
                'model' => '/Model:\s+([^\n]+?)(?=\s+Složení:)/ui',

                // Složení - ukončené "Látka:"
                'slozeni' => '/Složení:\s+([^\n]+?)(?=\s+Látka:)/ui',

                // Látka - ukončená "Nohy:"
                'latka' => '/Látka:\s+([^\n]+?)(?=\s+Nohy:)/ui',

                // Látka pro barvu (stejné)
                'latka_barva' => '/Látka:\s+([^\n]+?)(?=\s+Nohy:)/ui',

                // Závada - ukončená "Model:"
                'zavada' => '/Závada:\s+([^\n]+?)(?=\s+Model:)/ui',

                // Typ objektu
                'typ_objektu' => '/(Rodinný\s+dům|Panelový\s+dům)/ui',

                // Poschodí
                'poschodie' => '/Poschodí:\s+(\d+)/ui'
            ];

            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET regex_patterns = :patterns
                WHERE zdroj = 'natuzzi'
            ");

            $stmt->execute([
                'patterns' => json_encode($natuzziPatterns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

            echo "<div class='success'>✅ <strong>NATUZZI</strong> - Patterns opraveny</div>";

            // ========================================
            // PHASE - Nové patterns
            // ========================================
            $phasePatterns = [
                // Číslo reklamácie
                'cislo_reklamace' => '/Č[ií]slo\s+reklamácie:\s+([A-Z0-9\-\/]+)/ui',

                // Dátum podania
                'datum_podania' => '/Dátum\s+podania:\s+(\d{1,2}\.\d{1,2}\.\d{4})/ui',

                // Číslo objednávky
                'cislo_objednavky' => '/Č[ií]slo\s+objednávky:\s+(\d+)/ui',

                // Číslo faktúry
                'cislo_faktury' => '/Č[ií]slo\s+faktúry:\s+(\d+)/ui',

                // Dátum vyhotovenia
                'datum_vyhotovenia' => '/Dátum\s+vyhotovenia:\s+(\d{1,2}\.\d{1,2}\.\d{4})/ui',

                // Meno z "Miesto reklamácie" sekce
                'jmeno' => '/Miesto\s+reklamácie.*?Meno\s+a\s+priezvisko:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/uis',

                // Email
                'email' => '/Email:\s+([\w._%+-]+@[\w.-]+\.[a-zA-Z]{2,})/ui',

                // Telefón
                'telefon' => '/Telefón:\s+([\d\s]+?)(?=\s+(?:Krajina|Email))/ui',

                // Adresa z "Miesto reklamácie" sekce
                'adresa' => '/Miesto\s+reklamácie.*?Adresa:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][^,]+\d+[a-z]?)/uis',

                // Mesto z "Miesto reklamácie" sekce
                'mesto' => '/Miesto\s+reklamácie.*?Mesto:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)(?=\s+Adresa)/uis',

                // PSČ z "Miesto reklamácie" sekce
                'psc' => '/Miesto\s+reklamácie.*?PSČ:\s+(\d{3}\s?\d{2})/uis',

                // Krajina
                'krajina' => '/Krajina:\s+([^\n]+?)(?=\s)/ui',

                // Model
                'model' => '/Model:\s+([^\n]+?)(?=\s+Zloženie:|\s+Látka:)/ui',

                // Zloženie
                'zlozenie' => '/Zloženie:\s+([^\n]+?)(?=\s+Látka:)/ui',

                // Látka
                'latka' => '/Látka:\s+([^\n]+?)(?=\s+(?:Nohy:|Kategória:))/ui',

                // Látka pre barvu
                'latka_barva' => '/Látka:\s+([^\n]+?)(?=\s+(?:Nohy:|Kategória:))/ui',

                // Kategória
                'kategoria' => '/Kategória:\s+([^\n]+?)(?=\s)/ui',

                // Závada
                'zavada' => '/Závada:\s+([^\n]+?)(?=\s+Vyjadrenie)/ui',

                // Typ objektu
                'typ_objektu' => '/(Rodinný\s+dom|Panelák)/ui',

                // Poschodie
                'poschodie' => '/Poschodie:\s+(\d+)/ui'
            ];

            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET regex_patterns = :patterns
                WHERE zdroj = 'phase'
            ");

            $stmt->execute([
                'patterns' => json_encode($phasePatterns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

            echo "<div class='success'>✅ <strong>PHASE</strong> - Patterns opraveny</div>";

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>🎉 FINÁLNÍ OPRAVA DOKONČENA!</strong><br><br>";
            echo "<strong>Klíčové změny:</strong><br>";
            echo "• Odstraněno <code>\\n</code> z patterns (text je na jednom řádku)<br>";
            echo "• Použito <code>\\s+</code> místo <code>\\s*\\n?\\s*</code><br>";
            echo "• Toleruje \"Čislo\" i \"Číslo\"<br>";
            echo "• Použit lookahead <code>(?=...)</code> pro ukončení záchytu<br>";
            echo "• Jméno/Adresa se hledá ve správné sekci (Místo reklamace)<br>";
            echo "</div>";

            echo "<h2>📊 Testovací data:</h2>";
            echo "<div class='info'>";
            echo "<strong>Z NATUZZI PROTOKOL.pdf by mělo být extrahováno:</strong><br>";
            echo "• Číslo: <code>NCE25-00002444-39</code><br>";
            echo "• Datum prodeje: <code>12.11.2025</code> (z \"Datum vyhotovení\")<br>";
            echo "• Jméno: <code>Petr Kmoch</code><br>";
            echo "• Email: <code>kmochova@petrisk.cz</code><br>";
            echo "• Telefon: <code>725 387 868</code><br>";
            echo "• Adresa: <code>Na Blatech 396</code><br>";
            echo "• Město: <code>Osnice</code><br>";
            echo "• PSČ: <code>25242</code><br>";
            echo "• Model: <code>C157 Intenso; LE02 Orbitale; Matrace</code><br>";
            echo "• Provedení/Barva: <code>TG 20JJ Light Beige; INÉ; 70.0077.02 Rose</code>";
            echo "</div>";

            echo "<a href='novareklamace.php' class='btn'>📄 Vyzkoušet nahrání PDF</a>";
            echo "<a href='test_pdf_extrakce.php' class='btn' style='background:#666;margin-left:10px;'>🔍 Znovu otestovat</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<div class='info'>";
        echo "<strong>📝 Co se opraví:</strong><br>";
        echo "• Patterns budou fungovat s textem <strong>na jednom řádku</strong><br>";
        echo "• Tolerance pro \"Čislo\" i \"Číslo\"<br>";
        echo "• Správné zachycení dat z opakujících se sekcí<br>";
        echo "• Použití lookahead pro přesné ukončení záchytu";
        echo "</div>";

        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>▶️ SPUSTIT FINÁLNÍ OPRAVU</button>";
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
