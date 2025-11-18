<?php
/**
 * Migrace: Smazání všech testovacích fotek
 *
 * Tento skript BEZPEČNĚ smaže všechny fotky z databáze i z disku.
 * Použijte po testování pro vyčištění testovacích dat.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tuto operaci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vyčištění testovacích fotek</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc2626; border-bottom: 3px solid #dc2626;
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
        .btn { display: inline-block; padding: 12px 24px;
               background: #dc2626; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               font-size: 1rem; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #b91c1c; }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                 gap: 1rem; margin: 1.5rem 0; }
        .stat-card { background: #f9fafb; padding: 1rem; border-radius: 8px;
                     border-left: 4px solid #dc2626; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #dc2626; }
        .stat-label { font-size: 0.9rem; color: #6b7280; margin-top: 0.5rem; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>⚠️ Vyčištění testovacích fotek</h1>";

    // 1. Kontrolní fáze - zjistit kolik fotek je v databázi
    echo "<div class='info'><strong>KONTROLA DATABÁZE...</strong></div>";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_photos");
    $totalPhotos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Zjistit unikátní reklamace s fotkami
    $stmt = $pdo->query("SELECT COUNT(DISTINCT reklamace_id) as total FROM wgs_photos");
    $totalReklamace = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Zjistit velikost fotek na disku
    $uploadsDir = __DIR__ . '/uploads/photos';
    $totalSize = 0;
    $filesOnDisk = 0;

    if (is_dir($uploadsDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
                $filesOnDisk++;
            }
        }
    }

    $totalSizeMB = round($totalSize / 1024 / 1024, 2);

    echo "<div class='stats'>";
    echo "<div class='stat-card'>";
    echo "<div class='stat-number'>{$totalPhotos}</div>";
    echo "<div class='stat-label'>Fotek v databázi</div>";
    echo "</div>";

    echo "<div class='stat-card'>";
    echo "<div class='stat-number'>{$totalReklamace}</div>";
    echo "<div class='stat-label'>Reklamací s fotkami</div>";
    echo "</div>";

    echo "<div class='stat-card'>";
    echo "<div class='stat-number'>{$filesOnDisk}</div>";
    echo "<div class='stat-label'>Souborů na disku</div>";
    echo "</div>";

    echo "<div class='stat-card'>";
    echo "<div class='stat-number'>{$totalSizeMB} MB</div>";
    echo "<div class='stat-label'>Celková velikost</div>";
    echo "</div>";
    echo "</div>";

    if ($totalPhotos === 0) {
        echo "<div class='warning'><strong>ŽÁDNÉ FOTKY K SMAZÁNÍ</strong><br>V databázi nejsou žádné fotky.</div>";
        echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na admin</a>";
        echo "</div></body></html>";
        exit;
    }

    // 2. Pokud je nastaveno ?execute=1, provést smazání
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='warning'><strong>⚠️ SPOUŠTÍM MAZÁNÍ...</strong></div>";

        $deletedFiles = 0;
        $deletedDbRecords = 0;
        $errors = [];

        // Načíst všechny fotky z DB
        $stmt = $pdo->query("SELECT id, photo_path, file_path FROM wgs_photos");
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Smazat fyzické soubory
        echo "<div class='info'>Mazání souborů z disku...</div>";

        foreach ($photos as $photo) {
            $filePath = $photo['file_path'] ?? $photo['photo_path'];

            if ($filePath) {
                // BEZPEČNOST: Path Traversal ochrana
                $uploadsRoot = realpath(__DIR__ . '/uploads');
                $normalized = str_replace(['\\', '..'], ['/', ''], $filePath);
                $normalized = ltrim($normalized, '/');

                // Odstranit prefix 'uploads/' pokud existuje
                if (strpos($normalized, 'uploads/') === 0) {
                    $normalized = substr($normalized, 8);
                }

                $fullPath = $uploadsRoot . '/' . $normalized;
                $realPath = realpath($fullPath);

                // Ověřit že realpath je stále v uploads/ (ochrana proti útokům)
                if ($realPath && strpos($realPath, $uploadsRoot) === 0 && is_file($realPath)) {
                    if (unlink($realPath)) {
                        $deletedFiles++;
                    } else {
                        $errors[] = "Nepodařilo se smazat soubor: " . basename($realPath);
                    }
                }
            }
        }

        // Smazat prázdné adresáře v uploads/photos/
        echo "<div class='info'>Mazání prázdných adresářů...</div>";

        if (is_dir($uploadsDir)) {
            $dirs = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($dirs as $dir) {
                if ($dir->isDir() && isDirEmpty($dir->getPathname())) {
                    @rmdir($dir->getPathname());
                }
            }
        }

        // Smazat všechny záznamy z databáze
        echo "<div class='info'>Mazání záznamů z databáze...</div>";

        $stmt = $pdo->prepare("DELETE FROM wgs_photos");
        $stmt->execute();
        $deletedDbRecords = $stmt->rowCount();

        // Výsledek
        echo "<div class='success'>";
        echo "<strong>✅ VYČIŠTĚNÍ DOKONČENO</strong><br><br>";
        echo "📁 Smazáno souborů: <strong>{$deletedFiles}</strong><br>";
        echo "🗄️ Smazáno DB záznamů: <strong>{$deletedDbRecords}</strong><br>";

        if (!empty($errors)) {
            echo "<br><strong>⚠️ Varování ({" . count($errors) . "}):</strong><br>";
            foreach ($errors as $error) {
                echo "• {$error}<br>";
            }
        }

        echo "</div>";

        echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na admin</a>";
        echo "<a href='vycisti_vsechny_fotky.php' class='btn btn-secondary'>🔄 Zkontrolovat znovu</a>";

    } else {
        // Náhled co bude provedeno
        echo "<div class='warning'>";
        echo "<strong>⚠️ VAROVÁNÍ!</strong><br><br>";
        echo "Tato operace TRVALE SMAŽE:<br>";
        echo "• <strong>{$totalPhotos}</strong> fotek z databáze<br>";
        echo "• <strong>{$filesOnDisk}</strong> souborů z disku<br>";
        echo "• <strong>{$totalSizeMB} MB</strong> diskového prostoru bude uvolněno<br><br>";
        echo "Tato akce je <strong>NEVRATNÁ</strong>!";
        echo "</div>";

        echo "<a href='?execute=1' class='btn' onclick='return confirm(\"Opravdu chcete SMAZAT VŠECHNY FOTKY?\\n\\nTato akce je NEVRATNÁ!\");'>🗑️ SMAZAT VŠECHNY FOTKY</a>";
        echo "<a href='admin.php' class='btn btn-secondary'>← Zrušit a vrátit se</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<a href='admin.php' class='btn btn-secondary'>← Zpět na admin</a>";
}

echo "</div></body></html>";

/**
 * Kontrola zda je adresář prázdný
 */
function isDirEmpty($dir) {
    $handle = opendir($dir);
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            closedir($handle);
            return false;
        }
    }
    closedir($handle);
    return true;
}
?>
