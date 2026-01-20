<?php
/**
 * Obnovení fotek do databáze - pro fotky které jsou na disku ale chybí v DB
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
    <title>Obnovení fotek do databáze</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
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
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a1a1a; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Obnovení fotek do databáze</h1>";

    // Parametr pro zakázku
    $reklamaceId = $_GET['reklamace_id'] ?? null;

    if (!$reklamaceId) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ POUŽITÍ:</strong><br>";
        echo "Tento skript obnoví fotky, které jsou na disku ale chybí v databázi.<br><br>";
        echo "URL: <code>obnov_fotky_do_db.php?reklamace_id=XXX&execute=1</code>";
        echo "</div>";
        die();
    }

    // Ověření existence zakázky
    $stmt = $pdo->prepare("SELECT reklamace_id, cislo, jmeno FROM wgs_reklamace WHERE reklamace_id = :id OR cislo = :cislo LIMIT 1");
    $stmt->execute([':id' => $reklamaceId, ':cislo' => $reklamaceId]);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakazka) {
        echo "<div class='error'>Zakázka s ID <strong>" . htmlspecialchars($reklamaceId) . "</strong> nebyla nalezena.</div>";
        die();
    }

    echo "<div class='info'>";
    echo "<strong>📋 Zakázka:</strong> " . htmlspecialchars($zakazka['cislo'] ?? $zakazka['reklamace_id']) . "<br>";
    echo "<strong>Zákazník:</strong> " . htmlspecialchars($zakazka['jmeno']);
    echo "</div>";

    // Kontrola fotek na disku
    $reklamaceDir = __DIR__ . '/uploads/reklamace_' . $zakazka['reklamace_id'];

    if (!is_dir($reklamaceDir)) {
        echo "<div class='error'>Složka s fotkami neexistuje: <code>" . htmlspecialchars($reklamaceDir) . "</code></div>";
        die();
    }

    $files = glob($reklamaceDir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);

    if (empty($files)) {
        echo "<div class='warning'>Ve složce nejsou žádné fotky.</div>";
        die();
    }

    // Najít fotky bez záznamu v DB
    $orphanedPhotos = [];
    foreach ($files as $file) {
        $filename = basename($file);
        $relativePath = "uploads/reklamace_{$zakazka['reklamace_id']}/{$filename}";

        $stmtCheck = $pdo->prepare("SELECT id FROM wgs_photos WHERE file_name = :filename OR photo_path LIKE :path");
        $stmtCheck->execute([
            ':filename' => $filename,
            ':path' => "%{$filename}%"
        ]);

        if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
            // Extrahovat section_name z názvu souboru (format: before_XXX_0_timestamp_random.jpg)
            $sectionName = 'unknown';
            if (preg_match('/^(before|id|problem|repair|after)_/', $filename, $matches)) {
                $sectionName = $matches[1];
            }

            $orphanedPhotos[] = [
                'file' => $file,
                'filename' => $filename,
                'path' => $relativePath,
                'section' => $sectionName,
                'size' => filesize($file),
                'mtime' => filemtime($file)
            ];
        }
    }

    if (empty($orphanedPhotos)) {
        echo "<div class='success'>✅ Všechny fotky na disku mají záznam v databázi. Není co obnovovat.</div>";
        die();
    }

    echo "<div class='warning'>";
    echo "<strong>⚠️ Nalezeno fotek BEZ záznamu v DB:</strong> " . count($orphanedPhotos);
    echo "</div>";

    // Zobrazit tabulku fotek
    echo "<table>";
    echo "<tr><th>Název souboru</th><th>Sekce</th><th>Velikost</th><th>Čas nahrání</th></tr>";
    foreach ($orphanedPhotos as $photo) {
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($photo['filename']) . "</code></td>";
        echo "<td><strong>" . htmlspecialchars($photo['section']) . "</strong></td>";
        echo "<td>" . round($photo['size'] / 1024, 1) . " KB</td>";
        echo "<td>" . date('Y-m-d H:i:s', $photo['mtime']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Provést obnovení?
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>🔄 SPOUŠTÍM OBNOVENÍ...</strong></div>";

        $pdo->beginTransaction();

        try {
            $photoOrder = 0;
            $restored = 0;

            foreach ($orphanedPhotos as $photo) {
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_photos (
                        reklamace_id, section_name, photo_path, file_path, file_name,
                        photo_type, photo_order, uploaded_at, created_at
                    ) VALUES (
                        :reklamace_id, :section_name, :photo_path, :file_path, :file_name,
                        :photo_type, :photo_order, NOW(), NOW()
                    )
                ");

                $stmt->execute([
                    ':reklamace_id' => $zakazka['reklamace_id'],
                    ':section_name' => $photo['section'],
                    ':photo_path' => $photo['path'],
                    ':file_path' => $photo['path'],
                    ':file_name' => $photo['filename'],
                    ':photo_type' => 'image',
                    ':photo_order' => $photoOrder
                ]);

                $photoOrder++;
                $restored++;
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ ÚSPĚŠNĚ OBNOVENO:</strong> {$restored} fotek<br><br>";
            echo "Fotky jsou nyní viditelné v aplikaci pro zakázku <strong>" . htmlspecialchars($zakazka['cislo'] ?? $zakazka['reklamace_id']) . "</strong>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI OBNOVOVÁNÍ:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Zobrazit tlačítko pro potvrzení
        echo "<div class='warning'>";
        echo "<strong>⚠️ POZOR:</strong><br>";
        echo "Tímto obnovíte " . count($orphanedPhotos) . " fotek do databáze pro zakázku <strong>" . htmlspecialchars($zakazka['cislo'] ?? $zakazka['reklamace_id']) . "</strong>.<br>";
        echo "Fotky budou přiřazeny podle názvů souborů k příslušným sekcím.";
        echo "</div>";

        $currentUrl = $_SERVER['REQUEST_URI'];
        $separator = (strpos($currentUrl, '?') !== false) ? '&' : '?';

        echo "<a href='{$currentUrl}{$separator}execute=1' class='btn btn-danger'>✅ OBNOVIT FOTKY DO DATABÁZE</a>";
        echo "<a href='/admin.php' class='btn' style='background: #6c757d;'>❌ Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
