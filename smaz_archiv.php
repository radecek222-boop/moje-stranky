<?php
/**
 * Smazání složky archiv/
 *
 * Tento skript smaže celou složku archiv/ a vytvoří zálohu.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Neplatný požadavek");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Mazání složky archiv/</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .info { color: #0ff; }
        h1 { text-shadow: 0 0 10px #0f0; }
        .btn { display: inline-block; padding: 10px 20px; background: #0f0;
               color: #000; text-decoration: none; border-radius: 5px; margin: 10px;
               font-weight: bold; }
    </style>
</head>
<body>";

echo "<h1>🔥 Mazání složky archiv/</h1>";

$archivDir = __DIR__ . '/archiv';
$backupDir = __DIR__ . '/backups/archiv_backup_' . date('Y-m-d_H-i-s');

try {
    // Zkontrolovat, že archiv existuje
    if (!file_exists($archivDir) || !is_dir($archivDir)) {
        throw new Exception("Složka archiv/ neexistuje");
    }

    echo "<div class='info'>📦 Vytváření zálohy do: {$backupDir}</div>";

    // Vytvořit záložní složku
    if (!mkdir($backupDir, 0755, true)) {
        throw new Exception("Nepodařilo se vytvořit záložní složku");
    }

    // Rekurzivně zkopírovat archiv do zálohy
    function kopirovatSlozku($zdroj, $cil) {
        if (!file_exists($cil)) {
            mkdir($cil, 0755, true);
        }

        $dir = opendir($zdroj);
        $pocet = 0;

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $zdrojCesta = $zdroj . '/' . $file;
            $cilovaCesta = $cil . '/' . $file;

            if (is_dir($zdrojCesta)) {
                $pocet += kopirovatSlozku($zdrojCesta, $cilovaCesta);
            } else {
                if (copy($zdrojCesta, $cilovaCesta)) {
                    $pocet++;
                    if ($pocet % 10 === 0) {
                        echo "<div class='success'>✓ Zkopírováno {$pocet} souborů...</div>";
                        flush();
                        ob_flush();
                    }
                }
            }
        }
        closedir($dir);

        return $pocet;
    }

    $zkopirovanychSouboru = kopirovatSlozku($archivDir, $backupDir);
    echo "<div class='success'>✅ Záloha vytvořena: {$zkopirovanychSouboru} souborů</div>";

    echo "<div class='info'>🗑️ Mazání složky archiv/...</div>";

    // Rekurzivně smazat složku
    function smazatSlozku($cesta) {
        if (!file_exists($cesta)) return true;

        if (is_dir($cesta)) {
            $files = array_diff(scandir($cesta), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $cesta . '/' . $file;
                if (is_dir($filePath)) {
                    smazatSlozku($filePath);
                } else {
                    unlink($filePath);
                }
            }
            return rmdir($cesta);
        }

        return unlink($cesta);
    }

    if (smazatSlozku($archivDir)) {
        echo "<div class='success'>";
        echo "<h2>🎉 HOTOVO!</h2>";
        echo "<p><strong>Smazáno:</strong> Celá složka archiv/</p>";
        echo "<p><strong>Zkopírováno do zálohy:</strong> {$zkopirovanychSouboru} souborů</p>";
        echo "<p><strong>Umístění zálohy:</strong> {$backupDir}</p>";
        echo "</div>";

        echo "<div class='info'>";
        echo "<strong>💡 Obnovení ze zálohy:</strong><br>";
        echo "Pokud byste potřebovali obnovit archiv, použijte:<br>";
        echo "<code>cp -r {$backupDir} {$archivDir}</code>";
        echo "</div>";
    } else {
        throw new Exception("Nepodařilo se smazat složku archiv/");
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><a href='/admin.php' class='btn'>← Zpět do Admin panelu</a>";
echo "</body></html>";
?>
