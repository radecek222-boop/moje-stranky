<?php
/**
 * Zobrazení obsahu složky uploads/screens/
 * Spustit na produkčním serveru: https://www.wgs-service.cz/zobraz_screens.php
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může zobrazit screenshoty.");
}

$screensDir = __DIR__ . '/uploads/screens';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Screens Folder - Obsah</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #2D5016;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .screenshot {
            max-width: 200px;
            max-height: 200px;
            cursor: pointer;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .screenshot:hover {
            border-color: #2D5016;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #1a300d;
        }
        .size {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>📁 Obsah složky: uploads/screens/</h1>";

// Zkontrolovat existenci složky
if (!is_dir($screensDir)) {
    echo "<div class='error'>";
    echo "<strong>SLOŽKA NEEXISTUJE:</strong><br>";
    echo "Cesta: <code>{$screensDir}</code><br>";
    echo "Složka 'screens' nebyla nalezena v uploads adresáři.";
    echo "</div>";
} else {
    echo "<div class='info'>";
    echo "<strong>Cesta:</strong> <code>{$screensDir}</code><br>";
    echo "<strong>Práva:</strong> " . substr(sprintf('%o', fileperms($screensDir)), -4);
    echo "</div>";

    // Načíst obsah složky
    $files = scandir($screensDir);
    $files = array_diff($files, ['.', '..', '.gitignore']); // Odstranit tečky a .gitignore

    if (empty($files)) {
        echo "<div class='info'>";
        echo "<strong>Složka je prázdná</strong> - neobsahuje žádné soubory.";
        echo "</div>";
    } else {
        echo "<table>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>Náhled</th>";
        echo "<th>Název souboru</th>";
        echo "<th>Velikost</th>";
        echo "<th>Datum vytvoření</th>";
        echo "<th>Akce</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        foreach ($files as $file) {
            $filePath = $screensDir . '/' . $file;

            if (is_file($filePath)) {
                $fileSize = filesize($filePath);
                $fileSizeFormatted = $fileSize < 1024
                    ? $fileSize . ' B'
                    : ($fileSize < 1048576
                        ? round($fileSize / 1024, 2) . ' KB'
                        : round($fileSize / 1048576, 2) . ' MB');

                $fileTime = filemtime($filePath);
                $fileDate = date('d.m.Y H:i:s', $fileTime);

                $fileUrl = '/uploads/screens/' . rawurlencode($file);

                echo "<tr>";

                // Náhled (pokud je obrázek)
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    echo "<td><a href='{$fileUrl}' target='_blank'><img src='{$fileUrl}' class='screenshot' alt='{$file}'></a></td>";
                } else {
                    echo "<td><em>Není obrázek</em></td>";
                }

                // Název souboru
                echo "<td><strong>" . htmlspecialchars($file) . "</strong></td>";

                // Velikost
                echo "<td class='size'>{$fileSizeFormatted}</td>";

                // Datum
                echo "<td class='size'>{$fileDate}</td>";

                // Akce
                echo "<td>";
                echo "<a href='{$fileUrl}' target='_blank' class='btn' style='font-size:12px; padding:6px 12px;'>Otevřít</a>";
                echo "</td>";

                echo "</tr>";
            }
        }

        echo "</tbody>";
        echo "</table>";

        echo "<div class='info'>";
        echo "<strong>Celkem souborů:</strong> " . count($files);
        echo "</div>";
    }
}

echo "<br><a href='/admin.php' class='btn'>← Zpět do Admin panelu</a>";

echo "</div></body></html>";
?>
