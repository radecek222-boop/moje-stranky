<?php
/**
 * DOČASNÝ skript pro zobrazení obsahu uploads/screens/
 * S tajným parametrem pro přístup bez autentizace
 * URL: https://www.wgs-service.cz/zobraz_screens_public.php?key=wgs2024screens
 */

// Tajný klíč pro přístup
$secretKey = 'wgs2024screens';

if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die("Přístup odepřen");
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
            max-width: 1400px;
            margin: 20px auto;
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
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .gallery-item {
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            transition: all 0.3s;
        }
        .gallery-item:hover {
            border-color: #2D5016;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .gallery-item img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .file-name {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            word-break: break-all;
            margin: 8px 0;
        }
        .file-meta {
            font-size: 12px;
            color: #666;
            margin: 4px 0;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            font-size: 12px;
        }
        .btn:hover {
            background: #1a300d;
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
    echo "Cesta: <code>{$screensDir}</code>";
    echo "</div>";
} else {
    echo "<div class='info'>";
    echo "<strong>Cesta:</strong> <code>{$screensDir}</code>";
    echo "</div>";

    // Načíst obsah složky
    $files = scandir($screensDir);
    $files = array_diff($files, ['.', '..', '.gitignore']);

    // Seřadit podle data (nejnovější první)
    usort($files, function($a, $b) use ($screensDir) {
        return filemtime($screensDir . '/' . $b) - filemtime($screensDir . '/' . $a);
    });

    if (empty($files)) {
        echo "<div class='info'>";
        echo "<strong>Složka je prázdná</strong>";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<strong>Celkem souborů:</strong> " . count($files);
        echo "</div>";

        echo "<div class='gallery'>";

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

                echo "<div class='gallery-item'>";

                // Náhled
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    echo "<a href='{$fileUrl}' target='_blank'>";
                    echo "<img src='{$fileUrl}' alt='{$file}'>";
                    echo "</a>";
                } else {
                    echo "<div style='padding: 50px; background: #f0f0f0; border-radius: 5px;'>";
                    echo "<em>Není obrázek (." . htmlspecialchars($ext) . ")</em>";
                    echo "</div>";
                }

                echo "<div class='file-name'>" . htmlspecialchars($file) . "</div>";
                echo "<div class='file-meta'>📏 {$fileSizeFormatted}</div>";
                echo "<div class='file-meta'>📅 {$fileDate}</div>";
                echo "<a href='{$fileUrl}' target='_blank' class='btn'>Otevřít v plné velikosti</a>";

                echo "</div>";
            }
        }

        echo "</div>";
    }
}

echo "</div></body></html>";
?>
