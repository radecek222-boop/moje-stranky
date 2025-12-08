<?php
/**
 * Migrace: Přesun fotek z uploads/photos/ do uploads/reklamace_/
 *
 * Tento skript:
 * 1. Najde všechny fotky v databázi s cestou uploads/photos/XXX/
 * 2. Přesune soubory do uploads/reklamace_XXX/
 * 3. Aktualizuje cesty v databázi
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace fotek do jednotne slozky</title>
    <style>
        body { font-family: 'Poppins', sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #222; padding: 30px; border-radius: 10px; }
        h1 { color: #39ff14; border-bottom: 3px solid #39ff14; padding-bottom: 10px; }
        .success { background: #1a3a1a; border: 1px solid #39ff14; color: #39ff14; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3a1a1a; border: 1px solid #ff4444; color: #ff4444; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3a3a1a; border: 1px solid #ff8800; color: #ff8800; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2a3a; border: 1px solid #4488ff; color: #88bbff; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.85rem; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #444; }
        th { background: #333; color: #39ff14; }
        .btn { display: inline-block; padding: 12px 24px; background: #39ff14; color: #000; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; font-weight: bold; }
        .btn:hover { background: #2dd10d; }
        .btn-secondary { background: #666; color: #fff; }
        .btn-secondary:hover { background: #555; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 0.8rem; }
        .path-old { color: #ff8800; }
        .path-new { color: #39ff14; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace fotek do jednotne slozky</h1>";

    // Najít všechny fotky s cestou uploads/photos/
    $stmt = $pdo->query("
        SELECT id, reklamace_id, photo_path, file_path, section_name
        FROM wgs_photos
        WHERE photo_path LIKE 'uploads/photos/%'
        ORDER BY reklamace_id, id
    ");
    $fotkyKMigraci = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $celkem = count($fotkyKMigraci);

    if ($celkem === 0) {
        echo "<div class='success'><strong>Zadne fotky k migraci.</strong><br>Vsechny fotky jsou jiz ve slozce uploads/reklamace_XXX/</div>";
        echo "<a href='/admin.php' class='btn btn-secondary'>Zpet do admin</a>";
    } else {
        echo "<div class='info'><strong>Nalezeno $celkem fotek</strong> v uploads/photos/ k presunuti do uploads/reklamace_/</div>";

        // Zobrazit náhled
        echo "<h2>Nahled zmen</h2>";
        echo "<table>
            <tr><th>ID</th><th>Reklamace</th><th>Sekce</th><th>Stara cesta</th><th>Nova cesta</th><th>Soubor existuje</th></tr>";

        $existujici = 0;
        $chybejici = 0;

        foreach ($fotkyKMigraci as $foto) {
            // Vypočítat novou cestu
            // uploads/photos/WGS-2025-06-12-00001/before_xxx.jpg -> uploads/reklamace_WGS-2025-06-12-00001/before_xxx.jpg
            $staraCesta = $foto['photo_path'];
            $novaCesta = preg_replace('/^uploads\/photos\//', 'uploads/reklamace_', $staraCesta);

            // Zkontrolovat jestli soubor existuje
            $fullPath = __DIR__ . '/' . $staraCesta;
            $existuje = file_exists($fullPath);

            if ($existuje) {
                $existujici++;
                $existujeText = "<span style='color: #39ff14;'>ANO</span>";
            } else {
                $chybejici++;
                $existujeText = "<span style='color: #ff4444;'>NE</span>";
            }

            echo "<tr>
                <td>{$foto['id']}</td>
                <td><code>" . htmlspecialchars($foto['reklamace_id']) . "</code></td>
                <td>{$foto['section_name']}</td>
                <td class='path-old'><code>" . htmlspecialchars($staraCesta) . "</code></td>
                <td class='path-new'><code>" . htmlspecialchars($novaCesta) . "</code></td>
                <td>$existujeText</td>
            </tr>";
        }
        echo "</table>";

        echo "<div class='info'>
            <strong>Statistika:</strong><br>
            Celkem fotek: $celkem<br>
            Existujici soubory: $existujici<br>
            Chybejici soubory: $chybejici
        </div>";

        if ($chybejici > 0) {
            echo "<div class='warning'><strong>POZOR:</strong> $chybejici souboru neexistuje na disku. Tyto zaznamy budou aktualizovany v DB, ale soubory nelze presunout.</div>";
        }

        // Spustit migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<h2>Probiha migrace...</h2>";

            $presunuto = 0;
            $aktualizovano = 0;
            $chyby = 0;

            foreach ($fotkyKMigraci as $foto) {
                $staraCesta = $foto['photo_path'];
                $novaCesta = preg_replace('/^uploads\/photos\//', 'uploads/reklamace_', $staraCesta);

                $staryFullPath = __DIR__ . '/' . $staraCesta;
                $novyFullPath = __DIR__ . '/' . $novaCesta;

                try {
                    // Krok 1: Vytvorit cilovou slozku pokud neexistuje
                    $novyDir = dirname($novyFullPath);
                    if (!is_dir($novyDir)) {
                        mkdir($novyDir, 0755, true);
                    }

                    // Krok 2: Presunout soubor (pokud existuje)
                    if (file_exists($staryFullPath)) {
                        if (rename($staryFullPath, $novyFullPath)) {
                            $presunuto++;
                        } else {
                            // Zkusit copy + delete
                            if (copy($staryFullPath, $novyFullPath)) {
                                unlink($staryFullPath);
                                $presunuto++;
                            } else {
                                throw new Exception("Nelze presunout soubor");
                            }
                        }
                    }

                    // Krok 3: Aktualizovat DB
                    $stmtUpdate = $pdo->prepare("
                        UPDATE wgs_photos
                        SET photo_path = :nova_cesta,
                            file_path = :nova_cesta2
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':nova_cesta' => $novaCesta,
                        ':nova_cesta2' => $novaCesta,
                        ':id' => $foto['id']
                    ]);
                    $aktualizovano++;

                    echo "<div class='success'>ID {$foto['id']}: <code>$staraCesta</code> → <code>$novaCesta</code></div>";

                } catch (Exception $e) {
                    $chyby++;
                    echo "<div class='error'>ID {$foto['id']}: CHYBA - " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }

            // Smazat prazdne slozky v uploads/photos/
            $photosDir = __DIR__ . '/uploads/photos';
            if (is_dir($photosDir)) {
                $subDirs = glob($photosDir . '/*', GLOB_ONLYDIR);
                foreach ($subDirs as $subDir) {
                    $files = glob($subDir . '/*');
                    if (empty($files)) {
                        rmdir($subDir);
                        echo "<div class='info'>Smazana prazdna slozka: " . basename($subDir) . "</div>";
                    }
                }
                // Smazat i hlavni photos slozku pokud je prazdna
                $remaining = glob($photosDir . '/*');
                if (empty($remaining)) {
                    rmdir($photosDir);
                    echo "<div class='info'>Smazana prazdna slozka: uploads/photos/</div>";
                }
            }

            echo "<div class='" . ($chyby === 0 ? 'success' : 'warning') . "'>";
            echo "<strong>MIGRACE DOKONCENA</strong><br>";
            echo "Presunuto souboru: $presunuto<br>";
            echo "Aktualizovano zaznamu v DB: $aktualizovano<br>";
            if ($chyby > 0) {
                echo "Chyby: $chyby";
            }
            echo "</div>";

            echo "<a href='/admin.php' class='btn btn-secondary'>Zpet do admin</a>";

        } else {
            // Zobrazit tlacitko
            echo "<div class='warning'>
                <strong>POZOR:</strong> Tato akce presune soubory a aktualizuje databazi.<br>
                Doporucujeme pred spustenim vytvorit zalohu.
            </div>";

            echo "<a href='?execute=1' class='btn' onclick=\"return confirm('Opravdu spustit migraci $celkem fotek?');\">Spustit migraci</a>";
            echo "<a href='/admin.php' class='btn btn-secondary'>Zrusit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
