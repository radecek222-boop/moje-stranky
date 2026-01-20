<?php
/**
 * Hledání fotek Ondřeje Skoupého - kontrola zda se neuložily na server
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Hledání fotek - Ondřej Skoupý</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 15px; border-radius: 5px;
                margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 15px; border-radius: 5px;
                   margin: 15px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 15px; border-radius: 5px;
                   margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                      gap: 15px; margin: 20px 0; }
        .photo-item { border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        .photo-item img { width: 100%; height: 150px; object-fit: cover; }
        .photo-meta { font-size: 12px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Hledání fotek - Ondřej Skoupý</h1>";

    // 1. Najít všechny zakázky Ondřeje Skoupého
    $stmt = $pdo->prepare("
        SELECT
            reklamace_id,
            cislo,
            jmeno,
            adresa,
            stav,
            termin,
            cas_navstevy,
            created_at,
            updated_at
        FROM wgs_reklamace
        WHERE pridelen_technik LIKE '%Ondřej Skoupý%'
           OR pridelen_technik LIKE '%Ondrej Skoupy%'
           OR jmeno_technika LIKE '%Ondřej Skoupý%'
           OR jmeno_technika LIKE '%Ondrej Skoupy%'
        ORDER BY updated_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $zakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>📋 Nalezeno zakázek:</strong> " . count($zakazky);
    echo "</div>";

    if (empty($zakazky)) {
        echo "<div class='warning'>Nebyly nalezeny žádné zakázky pro Ondřeje Skoupého.</div>";
    } else {
        echo "<table>";
        echo "<tr>";
        echo "<th>Číslo zakázky</th>";
        echo "<th>Zákazník</th>";
        echo "<th>Stav</th>";
        echo "<th>Termín návštěvy</th>";
        echo "<th>Poslední změna</th>";
        echo "<th>Fotky v DB</th>";
        echo "<th>Fotky na disku</th>";
        echo "</tr>";

        foreach ($zakazky as $zakazka) {
            // Počet fotek v databázi
            $stmtPhotos = $pdo->prepare("SELECT COUNT(*) as count FROM wgs_photos WHERE reklamace_id = :id");
            $stmtPhotos->execute([':id' => $zakazka['reklamace_id']]);
            $photoCount = $stmtPhotos->fetch(PDO::FETCH_ASSOC)['count'];

            // Kontrola fotek na disku
            $reklamaceDir = __DIR__ . '/uploads/reklamace_' . $zakazka['reklamace_id'];
            $filesOnDisk = 0;
            if (is_dir($reklamaceDir)) {
                $files = glob($reklamaceDir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
                $filesOnDisk = count($files);
            }

            $rowStyle = '';
            if ($filesOnDisk > $photoCount) {
                $rowStyle = "background: #fff3cd;"; // Žlutá - rozdíl v počtu fotek
            }

            echo "<tr style='$rowStyle'>";
            echo "<td><strong>" . htmlspecialchars($zakazka['cislo'] ?? $zakazka['reklamace_id']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($zakazka['jmeno']) . "</td>";
            echo "<td>" . htmlspecialchars($zakazka['stav']) . "</td>";

            $termin = $zakazka['termin'] ?? null;
            $cas = $zakazka['cas_navstevy'] ?? null;
            $terminText = $termin ? ($termin . ($cas ? ' ' . $cas : '')) : '-';
            echo "<td>" . htmlspecialchars($terminText) . "</td>";

            echo "<td>" . $zakazka['updated_at'] . "</td>";
            echo "<td style='text-align: center;'><strong>" . $photoCount . "</strong></td>";
            echo "<td style='text-align: center;'><strong>" . $filesOnDisk . "</strong>";
            if ($filesOnDisk > $photoCount) {
                echo " ⚠️";
            }
            echo "</td>";
            echo "</tr>";
        }

        echo "</table>";

        // 2. Kontrola nedávných fotek v uploads složce (poslední 3 dny)
        echo "<h2>📸 Nedávné fotky na disku (poslední 3 dny)</h2>";

        $threeDaysAgo = time() - (3 * 24 * 60 * 60);
        $recentPhotos = [];

        foreach ($zakazky as $zakazka) {
            $reklamaceDir = __DIR__ . '/uploads/reklamace_' . $zakazka['reklamace_id'];
            if (is_dir($reklamaceDir)) {
                $files = glob($reklamaceDir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
                foreach ($files as $file) {
                    $mtime = filemtime($file);
                    if ($mtime >= $threeDaysAgo) {
                        $recentPhotos[] = [
                            'file' => $file,
                            'reklamace_id' => $zakazka['reklamace_id'],
                            'cislo' => $zakazka['cislo'] ?? $zakazka['reklamace_id'],
                            'zakaznik' => $zakazka['jmeno'],
                            'mtime' => $mtime,
                            'size' => filesize($file)
                        ];
                    }
                }
            }
        }

        // Seřadit podle času
        usort($recentPhotos, function($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });

        if (empty($recentPhotos)) {
            echo "<div class='info'>Žádné nedávné fotky (poslední 3 dny).</div>";
        } else {
            echo "<div class='success'>";
            echo "<strong>✅ Nalezeno nedávných fotek:</strong> " . count($recentPhotos);
            echo "</div>";

            echo "<table>";
            echo "<tr>";
            echo "<th>Čas nahrání</th>";
            echo "<th>Zakázka</th>";
            echo "<th>Zákazník</th>";
            echo "<th>Název souboru</th>";
            echo "<th>Velikost</th>";
            echo "<th>V databázi?</th>";
            echo "</tr>";

            foreach ($recentPhotos as $photo) {
                $filename = basename($photo['file']);
                $relativePath = "uploads/reklamace_{$photo['reklamace_id']}/{$filename}";

                // Kontrola v databázi
                $stmtCheck = $pdo->prepare("SELECT id FROM wgs_photos WHERE file_name = :filename OR photo_path LIKE :path");
                $stmtCheck->execute([
                    ':filename' => $filename,
                    ':path' => "%{$filename}%"
                ]);
                $inDb = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                $rowStyle = $inDb ? '' : "background: #f8d7da;"; // Červená pokud není v DB

                echo "<tr style='$rowStyle'>";
                echo "<td>" . date('Y-m-d H:i:s', $photo['mtime']) . "</td>";
                echo "<td><strong>" . htmlspecialchars($photo['cislo']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($photo['zakaznik']) . "</td>";
                echo "<td><code>" . htmlspecialchars($filename) . "</code></td>";
                echo "<td>" . round($photo['size'] / 1024, 1) . " KB</td>";
                echo "<td style='text-align: center;'>" . ($inDb ? "✅" : "❌ CHYBÍ") . "</td>";
                echo "</tr>";
            }

            echo "</table>";
        }

        // 3. Hledání orphaned fotek (na disku ale ne v DB)
        echo "<h2>🔴 Fotky bez záznamu v databázi</h2>";

        $orphanedPhotos = array_filter($recentPhotos, function($photo) use ($pdo) {
            $filename = basename($photo['file']);
            $stmtCheck = $pdo->prepare("SELECT id FROM wgs_photos WHERE file_name = :filename OR photo_path LIKE :path");
            $stmtCheck->execute([
                ':filename' => $filename,
                ':path' => "%{$filename}%"
            ]);
            return !$stmtCheck->fetch(PDO::FETCH_ASSOC);
        });

        if (empty($orphanedPhotos)) {
            echo "<div class='success'>✅ Všechny fotky mají záznam v databázi.</div>";
        } else {
            echo "<div class='warning'>";
            echo "<strong>⚠️ Nalezeno fotek BEZ záznamu v DB:</strong> " . count($orphanedPhotos);
            echo "<br><br>Tyto fotky jsou na disku, ale nebyly zapsány do databáze (pravděpodobně kvůli odhlášení).";
            echo "</div>";

            echo "<div class='photo-grid'>";
            foreach ($orphanedPhotos as $photo) {
                $relativePath = str_replace(__DIR__ . '/', '', $photo['file']);
                echo "<div class='photo-item'>";
                echo "<img src='/{$relativePath}' alt='Fotka' onerror='this.src=\"data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27200%27 height=%27150%27%3E%3Crect fill=%27%23ddd%27 width=%27200%27 height=%27150%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 fill=%27%23666%27%3EChyba načtení%3C/text%3E%3C/svg%3E\"'>";
                echo "<div class='photo-meta'>";
                echo "<strong>Zakázka:</strong> " . htmlspecialchars($photo['cislo']) . "<br>";
                echo "<strong>Čas:</strong> " . date('Y-m-d H:i', $photo['mtime']) . "<br>";
                echo "<strong>Soubor:</strong> " . basename($photo['file']);
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
