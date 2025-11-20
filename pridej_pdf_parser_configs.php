<?php
/**
 * Migrace: Vytvoření tabulky pro konfigurace PDF parserů
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku wgs_pdf_parser_configs
 * pro správu různých typů PDF protokolů (NATUZZI, PHASE, atd.)
 * Můžete jej spustit vícekrát - kontroluje existenci.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: PDF Parser Konfigurace</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
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
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #dee2e6; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>📄 Migrace: PDF Parser Konfigurace</h1>";

    // Kontrola existence tabulky
    echo "<div class='info'><strong>KONTROLA EXISTENCE TABULKY...</strong></div>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pdf_parser_configs'");
    $tabulkaExistuje = $stmt->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ TABULKA JIŽ EXISTUJE</strong><br>";
        echo "Tabulka <code>wgs_pdf_parser_configs</code> již existuje v databázi.";
        echo "</div>";

        // Zobrazit aktuální konfigurace
        $configs = $pdo->query("SELECT * FROM wgs_pdf_parser_configs ORDER BY priorita DESC, config_id")->fetchAll(PDO::FETCH_ASSOC);

        if (count($configs) > 0) {
            echo "<h2>📋 Aktuální konfigurace:</h2>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Název</th><th>Zdroj</th><th>Priorita</th><th>Aktivní</th><th>Vytvořeno</th></tr>";
            foreach ($configs as $cfg) {
                $aktivni = $cfg['aktivni'] ? '✅ Ano' : '❌ Ne';
                echo "<tr>";
                echo "<td>{$cfg['config_id']}</td>";
                echo "<td><strong>{$cfg['nazev']}</strong></td>";
                echo "<td>{$cfg['zdroj']}</td>";
                echo "<td>{$cfg['priorita']}</td>";
                echo "<td>{$aktivni}</td>";
                echo "<td>" . date('d.m.Y H:i', strtotime($cfg['created_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='warning'>Tabulka je prázdná - žádné konfigurace zatím nebyly přidány.</div>";
        }

    } else {
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Vytvoření tabulky
                $sql = "CREATE TABLE wgs_pdf_parser_configs (
                    config_id INT AUTO_INCREMENT PRIMARY KEY,
                    nazev VARCHAR(100) NOT NULL COMMENT 'Název konfigurace (např. NATUZZI Protokol)',
                    zdroj VARCHAR(50) NOT NULL COMMENT 'Identifikátor zdroje (natuzzi, phase)',
                    regex_patterns JSON NOT NULL COMMENT 'JSON s regex vzory pro všechna pole',
                    pole_mapping JSON NOT NULL COMMENT 'Mapování polí z PDF na databázové sloupce',
                    detekce_pattern VARCHAR(255) NOT NULL COMMENT 'Regex pro detekci typu PDF',
                    priorita INT DEFAULT 0 COMMENT 'Priorita pokusu parsování (vyšší = dříve)',
                    aktivni TINYINT DEFAULT 1 COMMENT '1 = aktivní, 0 = neaktivní',
                    poznamka TEXT COMMENT 'Poznámka k této konfiguraci',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_aktivni (aktivni),
                    INDEX idx_priorita (priorita),
                    INDEX idx_zdroj (zdroj)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Konfigurace pro parsování různých typů PDF protokolů'";

                $pdo->exec($sql);

                echo "<div class='success'>";
                echo "<strong>✅ TABULKA VYTVOŘENA</strong><br>";
                echo "Tabulka <code>wgs_pdf_parser_configs</code> byla úspěšně vytvořena.";
                echo "</div>";

                // Přidání výchozích konfigurací
                echo "<div class='info'><strong>PŘIDÁVÁM VÝCHOZÍ KONFIGURACE...</strong></div>";

                // 1. NATUZZI Protokol
                $natuzziPatterns = json_encode([
                    'cislo_reklamace' => '/Číslo reklamace:\s*\n?\s*([A-Z0-9\-\/]+)/ui',
                    'datum_podani' => '/Datum podání:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui',
                    'cislo_objednavky' => '/Číslo objednávky:\s*\n?\s*(\d+)/ui',
                    'cislo_faktury' => '/Číslo faktury:\s*\n?\s*(\d+)/ui',
                    'datum_vyhotoveni' => '/Datum vyhotovení:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui',
                    'jmeno' => '/Jméno a příjmení:\s*\n?\s*([^\n]+)/ui',
                    'adresa' => '/Adresa:\s*\n?\s*([^\n]+)/ui',
                    'mesto' => '/Město:\s*\n?\s*([^\n]+)/ui',
                    'psc' => '/PSČ:\s*\n?\s*(\d{3}\s?\d{2})/ui',
                    'stat' => '/Stát:\s*\n?\s*([^\n]+)/ui',
                    'telefon' => '/Telefon:\s*\n?\s*([\+\d\s]+)/ui',
                    'email' => '/Email:\s*\n?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/ui',
                    'model' => '/Model:\s*\n?\s*([^\n]+)/ui',
                    'slozeni' => '/Složení:\s*\n?\s*([^\n]+(?:\n(?!Látka:)[^\n]+)*)/ui',
                    'latka' => '/Látka:\s*\n?\s*([^\n]+)/ui',
                    'zavada' => '/Závada:\s*\n?\s*([^\n]+(?:\n(?!Vyjadrenie|Vyjádření)[^\n]+)*)/ui',
                    'typ_objektu' => '/(Rodinný dům|Panelový dům)/ui',
                    'poschodie' => '/Poschodí:\s*\n?\s*(\d+)/ui'
                ], JSON_UNESCAPED_UNICODE);

                $natuzziMapping = json_encode([
                    'cislo_reklamace' => 'cislo_objednavky_reklamace',
                    'datum_podani' => 'datum_reklamace',
                    'cislo_objednavky' => 'cislo_objednavky_reklamace',
                    'datum_vyhotoveni' => 'datum_prodeje',
                    'jmeno' => 'jmeno',
                    'adresa' => 'ulice',
                    'mesto' => 'mesto',
                    'psc' => 'psc',
                    'telefon' => 'telefon',
                    'email' => 'email',
                    'model' => 'model',
                    'slozeni' => 'doplnujici_info',
                    'latka' => 'provedeni',
                    'zavada' => 'popis_problemu'
                ], JSON_UNESCAPED_UNICODE);

                $stmt = $pdo->prepare("
                    INSERT INTO wgs_pdf_parser_configs
                    (nazev, zdroj, regex_patterns, pole_mapping, detekce_pattern, priorita, aktivni, poznamka)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?)
                ");

                $stmt->execute([
                    'NATUZZI Protokol',
                    'natuzzi',
                    $natuzziPatterns,
                    $natuzziMapping,
                    '/(NATUZZI|NCE\d{2}-)/i',
                    100,
                    'Parser pro české NATUZZI reklamační protokoly s prefixem NCE'
                ]);

                echo "<div class='success'>✅ Přidána konfigurace: <strong>NATUZZI Protokol</strong></div>";

                // 2. PHASE Protokol
                $phasePatterns = json_encode([
                    'cislo_reklamace' => '/Číslo reklamácie:\s*\n?\s*([A-Z0-9\-\/]+)/ui',
                    'datum_podania' => '/Dátum podania:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui',
                    'cislo_objednavky' => '/Číslo objednávky:\s*\n?\s*(\d+)/ui',
                    'cislo_faktury' => '/Číslo faktúry:\s*\n?\s*(\d+)/ui',
                    'datum_vyhotovenia' => '/Dátum vyhotovenia:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui',
                    'jmeno' => '/Meno a priezvisko:\s*\n?\s*([^\n]+)/ui',
                    'adresa' => '/Adresa:\s*\n?\s*([^\n]+)/ui',
                    'mesto' => '/Mesto:\s*\n?\s*([^\n]+)/ui',
                    'psc' => '/PSČ:\s*\n?\s*(\d{3}\s?\d{2})/ui',
                    'krajina' => '/Krajina:\s*\n?\s*([^\n]+)/ui',
                    'telefon' => '/Telefón:\s*\n?\s*([\+\d\s]+)/ui',
                    'email' => '/Email:\s*\n?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/ui',
                    'model' => '/Model:\s*\n?\s*([^\n]+)/ui',
                    'zlozenie' => '/Zloženie:\s*\n?\s*([^\n]+)/ui',
                    'latka' => '/Látka:\s*\n?\s*([^\n]+)/ui',
                    'kategoria' => '/Kategória:\s*\n?\s*([^\n]+)/ui',
                    'zavada' => '/Závada:\s*\n?\s*([^\n]+(?:\n(?!Vyjadrenie)[^\n]+)*)/ui',
                    'typ_objektu' => '/(Rodinný dom|Panelák)/ui',
                    'poschodie' => '/Poschodie:\s*\n?\s*(\d+)/ui'
                ], JSON_UNESCAPED_UNICODE);

                $phaseMapping = json_encode([
                    'cislo_reklamace' => 'cislo_objednavky_reklamace',
                    'datum_podania' => 'datum_reklamace',
                    'cislo_objednavky' => 'cislo_objednavky_reklamace',
                    'datum_vyhotovenia' => 'datum_prodeje',
                    'jmeno' => 'jmeno',
                    'adresa' => 'ulice',
                    'mesto' => 'mesto',
                    'psc' => 'psc',
                    'telefon' => 'telefon',
                    'email' => 'email',
                    'model' => 'model',
                    'zlozenie' => 'doplnujici_info',
                    'latka' => 'provedeni',
                    'kategoria' => 'barva',
                    'zavada' => 'popis_problemu'
                ], JSON_UNESCAPED_UNICODE);

                $stmt->execute([
                    'PHASE Protokol',
                    'phase',
                    $phasePatterns,
                    $phaseMapping,
                    '/(pohodlie.*phase|ZL\d-)/i',
                    90,
                    'Parser pro slovenské PHASE reklamační protokoly s prefixem ZL'
                ]);

                echo "<div class='success'>✅ Přidána konfigurace: <strong>PHASE Protokol</strong></div>";

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
                echo "Byly přidány 2 výchozí konfigurace pro parsování PDF protokolů.";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>📋 Co dál?</strong><br>";
                echo "• Konfigurace můžete spravovat v admin panelu<br>";
                echo "• API automaticky detekuje typ PDF a použije správný parser<br>";
                echo "• Můžete přidat další konfigurace pro jiné typy protokolů";
                echo "</div>";

                echo "<a href='admin.php?tab=pdf-parsers' class='btn'>🎛️ Otevřít správu parserů</a>";
                echo "<a href='novareklamace.php' class='btn'>📄 Vyzkoušet nahrání PDF</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>❌ CHYBA PŘI VYTVÁŘENÍ TABULKY:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Náhled SQL
            echo "<h2>📋 SQL Preview:</h2>";
            echo "<pre>CREATE TABLE wgs_pdf_parser_configs (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    nazev VARCHAR(100) NOT NULL,
    zdroj VARCHAR(50) NOT NULL,
    regex_patterns JSON NOT NULL,
    pole_mapping JSON NOT NULL,
    detekce_pattern VARCHAR(255) NOT NULL,
    priorita INT DEFAULT 0,
    aktivni TINYINT DEFAULT 1,
    poznamka TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);</pre>";

            echo "<div class='info'>";
            echo "<strong>📝 Co se stane:</strong><br>";
            echo "1. Vytvoří se tabulka <code>wgs_pdf_parser_configs</code><br>";
            echo "2. Přidají se 2 výchozí konfigurace:<br>";
            echo "&nbsp;&nbsp;&nbsp;• <strong>NATUZZI Protokol</strong> (české, prefix NCE)<br>";
            echo "&nbsp;&nbsp;&nbsp;• <strong>PHASE Protokol</strong> (slovenské, prefix ZL)<br>";
            echo "3. API bude automaticky detekovat typ PDF a použije správný parser";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn' style='background:#666;'>← Zpět do admin panelu</a>";
echo "</div></body></html>";
?>
