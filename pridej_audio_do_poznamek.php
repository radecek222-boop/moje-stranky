<?php
/**
 * Migrace: Přidání podpory audio poznámek
 *
 * Tento skript BEZPEČNĚ přidá sloupec audio_path do tabulky wgs_notes
 * a vytvoří adresář pro ukládání audio souborů.
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
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migrace: Audio poznámky</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
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
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; }
        .btn:hover { background: #555; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Audio poznámky</h1>";

    // 1. Kontrola zda sloupec už existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_notes LIKE 'audio_path'");
    $columnExists = $stmt->fetch();

    if ($columnExists) {
        echo "<div class='warning'><strong>Sloupec audio_path již existuje.</strong></div>";
    }

    // 2. Kontrola adresáře
    $audioDir = __DIR__ . '/uploads/audio';
    $dirExists = is_dir($audioDir);

    echo "<div class='info'>";
    echo "<strong>Kontrola:</strong><br>";
    echo "- Sloupec audio_path: " . ($columnExists ? "existuje" : "chybí") . "<br>";
    echo "- Adresář uploads/audio: " . ($dirExists ? "existuje" : "chybí") . "<br>";
    echo "</div>";

    // 3. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $changes = 0;

        // Přidat sloupec pokud neexistuje
        if (!$columnExists) {
            $pdo->exec("
                ALTER TABLE wgs_notes
                ADD COLUMN audio_path VARCHAR(500) NULL DEFAULT NULL
                COMMENT 'Cesta k audio souboru hlasové poznámky'
                AFTER note_text
            ");
            echo "<div class='success'>Sloupec <code>audio_path</code> přidán do tabulky wgs_notes</div>";
            $changes++;
        }

        // Vytvořit adresář pokud neexistuje
        if (!$dirExists) {
            if (mkdir($audioDir, 0755, true)) {
                echo "<div class='success'>Adresář <code>uploads/audio</code> vytvořen</div>";
                $changes++;

                // Vytvořit .htaccess pro ochranu
                $htaccess = $audioDir . '/.htaccess';
                file_put_contents($htaccess, "# Povolit pouze audio soubory
<FilesMatch \"\\.(webm|mp3|ogg|wav|m4a)$\">
    Allow from all
</FilesMatch>

# Zakázat PHP
<FilesMatch \"\\.php$\">
    Deny from all
</FilesMatch>

# Zakázat listing
Options -Indexes
");
                echo "<div class='success'>Soubor <code>.htaccess</code> vytvořen pro ochranu adresáře</div>";
            } else {
                echo "<div class='error'>Nepodařilo se vytvořit adresář uploads/audio</div>";
            }
        }

        // Vytvořit index.php pro ochranu
        $indexFile = $audioDir . '/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php header('HTTP/1.0 403 Forbidden'); exit;");
            echo "<div class='success'>Soubor <code>index.php</code> vytvořen pro ochranu</div>";
        }

        if ($changes > 0) {
            echo "<div class='success'><strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>Provedeno změn: $changes</div>";
        } else {
            echo "<div class='warning'><strong>Žádné změny nebyly potřeba</strong></div>";
        }

    } else {
        // Náhled co bude provedeno
        echo "<h3>Co bude provedeno:</h3>";
        echo "<ul>";
        if (!$columnExists) {
            echo "<li>Přidání sloupce <code>audio_path</code> do tabulky <code>wgs_notes</code></li>";
        }
        if (!$dirExists) {
            echo "<li>Vytvoření adresáře <code>uploads/audio/</code></li>";
            echo "<li>Vytvoření ochranných souborů (.htaccess, index.php)</li>";
        }
        if ($columnExists && $dirExists) {
            echo "<li><em>Vše je již nastaveno, žádné změny nejsou potřeba</em></li>";
        }
        echo "</ul>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='seznam.php' class='btn' style='background: #666;'>Zpět na seznam</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
