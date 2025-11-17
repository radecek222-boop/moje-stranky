<?php
/**
 * Migrace: Vytvoření tabulky wgs_documents
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku wgs_documents pro ukládání PDF protokolů.
 * Můžete jej spustit vícekrát - pokud tabulka existuje, nic se nestane.
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
    <title>Migrace: Vytvoření tabulky wgs_documents</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
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
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // Kontrola před migrací
    echo "<h1>🗄️ Migrace: Vytvoření tabulky wgs_documents</h1>";

    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    // Zkontrolovat zda tabulka existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_documents'");
    $tabulkaExistuje = $stmt->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='success'>";
        echo "<strong>✅ TABULKA JIŽ EXISTUJE</strong><br>";
        echo "Tabulka <code>wgs_documents</code> již existuje. Není potřeba nic dělat.";
        echo "</div>";

        // Zobrazit strukturu existující tabulky
        $stmt = $pdo->query("DESCRIBE wgs_documents");
        $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>📋 Aktuální struktura tabulky:</h3>";
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($sloupce as $sloupec) {
            echo "<tr>";
            echo "<td><code>{$sloupec['Field']}</code></td>";
            echo "<td>{$sloupec['Type']}</td>";
            echo "<td>{$sloupec['Null']}</td>";
            echo "<td>{$sloupec['Key']}</td>";
            echo "<td>" . ($sloupec['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ TABULKA NEEXISTUJE</strong><br>";
        echo "Tabulka <code>wgs_documents</code> nebyla nalezena. Bude vytvořena.";
        echo "</div>";

        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            try {
                // DDL příkazy automaticky commitují transakci v MySQL/MariaDB
                // Proto nepoužíváme BEGIN/COMMIT

                $createTableSQL = "
                    CREATE TABLE wgs_documents (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        claim_id INT NOT NULL COMMENT 'FK na wgs_reklamace.id',
                        document_name VARCHAR(255) NOT NULL COMMENT 'Název souboru',
                        document_path VARCHAR(500) NOT NULL COMMENT 'Cesta k souboru',
                        document_type VARCHAR(50) NOT NULL DEFAULT 'protokol_pdf' COMMENT 'Typ dokumentu',
                        file_size INT DEFAULT NULL COMMENT 'Velikost souboru v bytech',
                        uploaded_by VARCHAR(100) DEFAULT NULL COMMENT 'Kdo nahrál dokument',
                        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Kdy byl nahrán',

                        INDEX idx_claim_id (claim_id),
                        INDEX idx_document_type (document_type),
                        INDEX idx_uploaded_at (uploaded_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Ukládání dokumentů (PDF protokoly, přílohy)';
                ";

                $pdo->exec($createTableSQL);

                echo "<div class='success'>";
                echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "<strong>Vytvořená tabulka:</strong> <code>wgs_documents</code><br>";
                echo "<strong>Sloupce:</strong> id, claim_id, document_name, document_path, document_type, file_size, uploaded_by, uploaded_at<br>";
                echo "<strong>Indexy:</strong> idx_claim_id, idx_document_type, idx_uploaded_at<br><br>";
                echo "<strong>Účel:</strong> Ukládání PDF protokolů a dalších dokumentů k reklamacím";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>ℹ️ CO DĚLAT NYNÍ:</strong><br>";
                echo "1. Obnovit stránku se seznamem reklamací - měla by se načíst bez chyb<br>";
                echo "2. Vygenerovat PDF protokol pro testování<br>";
                echo "3. Zkontrolovat zda se PDF ukládá správně do databáze";
                echo "</div>";

            } catch (PDOException $e) {
                echo "<div class='error'>";
                echo "<strong>❌ CHYBA PŘI VYTVÁŘENÍ TABULKY:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }

        } else {
            // Náhled co bude provedeno
            echo "<h3>📝 Co bude vytvořeno:</h3>";
            echo "<div class='info'>";
            echo "<strong>Tabulka:</strong> <code>wgs_documents</code><br>";
            echo "<strong>Sloupce:</strong><br>";
            echo "• <code>id</code> - AUTO_INCREMENT PRIMARY KEY<br>";
            echo "• <code>claim_id</code> - INT NOT NULL (FK na wgs_reklamace.id)<br>";
            echo "• <code>document_name</code> - VARCHAR(255) (název souboru)<br>";
            echo "• <code>document_path</code> - VARCHAR(500) (cesta k souboru)<br>";
            echo "• <code>document_type</code> - VARCHAR(50) (typ dokumentu - protokol_pdf, atd.)<br>";
            echo "• <code>file_size</code> - INT (velikost v bytech)<br>";
            echo "• <code>uploaded_by</code> - VARCHAR(100) (kdo nahrál)<br>";
            echo "• <code>uploaded_at</code> - DATETIME (kdy nahrán)<br><br>";
            echo "<strong>Indexy:</strong><br>";
            echo "• <code>idx_claim_id</code> - pro rychlé vyhledávání podle reklamace<br>";
            echo "• <code>idx_document_type</code> - pro filtrování podle typu dokumentu<br>";
            echo "• <code>idx_uploaded_at</code> - pro řazení podle data nahrání<br>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>VYTVOŘIT TABULKU</a>";
        }
    }

    echo "<div style='margin-top: 20px;'>";
    echo "<a href='vsechny_tabulky.php' class='btn'>Zpět na SQL přehled</a>";
    echo "<a href='admin.php' class='btn'>Zpět na admin</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
