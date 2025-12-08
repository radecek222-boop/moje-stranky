<?php
/**
 * Kontrola duplicitních fotek v databázi
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola duplicitnich fotek</title>
    <style>
        body { font-family: 'Poppins', sans-serif; max-width: 1400px; margin: 20px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        h1, h2 { color: #39ff14; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.8rem; }
        th, td { padding: 0.5rem; text-align: left; border: 1px solid #444; }
        th { background: #333; color: #39ff14; }
        .duplicate { background: #3a1a1a; }
        .success { background: #1a3a1a; border: 1px solid #39ff14; color: #39ff14; padding: 12px; margin: 10px 0; }
        .warning { background: #3a3a1a; border: 1px solid #ff8800; color: #ff8800; padding: 12px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #c82333; }
        .btn-secondary { background: #666; }
        code { background: #333; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
<h1>Kontrola duplicitnich fotek</h1>";

try {
    $pdo = getDbConnection();

    // 1. Najít duplicitní cesty (stejný photo_path)
    echo "<h2>1. Duplicitni cesty (photo_path)</h2>";
    $stmt = $pdo->query("
        SELECT photo_path, COUNT(*) as pocet, GROUP_CONCAT(id) as ids
        FROM wgs_photos
        GROUP BY photo_path
        HAVING COUNT(*) > 1
        ORDER BY pocet DESC
    ");
    $duplicity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($duplicity)) {
        echo "<div class='success'>Zadne duplicitni cesty nenalezeny.</div>";
    } else {
        echo "<div class='warning'>Nalezeno " . count($duplicity) . " duplicitnich cest!</div>";
        echo "<table>
            <tr><th>Cesta</th><th>Pocet</th><th>ID zaznamu</th></tr>";
        foreach ($duplicity as $d) {
            echo "<tr class='duplicate'>
                <td><code>" . htmlspecialchars($d['photo_path']) . "</code></td>
                <td>{$d['pocet']}</td>
                <td>{$d['ids']}</td>
            </tr>";
        }
        echo "</table>";
    }

    // 2. Fotky pro konkrétní reklamaci
    echo "<h2>2. Vsechny fotky v databazi (posledni 50)</h2>";
    $stmt = $pdo->query("
        SELECT id, reklamace_id, section_name, photo_path, created_at
        FROM wgs_photos
        ORDER BY id DESC
        LIMIT 50
    ");
    $fotky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>ID</th><th>Reklamace</th><th>Sekce</th><th>Cesta</th><th>Vytvoreno</th></tr>";
    foreach ($fotky as $f) {
        echo "<tr>
            <td>{$f['id']}</td>
            <td><code>" . htmlspecialchars($f['reklamace_id']) . "</code></td>
            <td>{$f['section_name']}</td>
            <td><code>" . htmlspecialchars($f['photo_path']) . "</code></td>
            <td>{$f['created_at']}</td>
        </tr>";
    }
    echo "</table>";

    // 3. Počet fotek podle reklamace
    echo "<h2>3. Pocet fotek podle reklamace</h2>";
    $stmt = $pdo->query("
        SELECT reklamace_id, COUNT(*) as pocet
        FROM wgs_photos
        GROUP BY reklamace_id
        ORDER BY pocet DESC
        LIMIT 20
    ");
    $pocty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>Reklamace</th><th>Pocet fotek</th></tr>";
    foreach ($pocty as $p) {
        echo "<tr>
            <td><code>" . htmlspecialchars($p['reklamace_id']) . "</code></td>
            <td>{$p['pocet']}</td>
        </tr>";
    }
    echo "</table>";

    // 4. Tlačítko pro smazání duplicit
    if (!empty($duplicity)) {
        if (isset($_GET['delete']) && $_GET['delete'] === '1') {
            echo "<h2>4. Mazani duplicit...</h2>";
            $smazano = 0;

            foreach ($duplicity as $d) {
                $ids = explode(',', $d['ids']);
                // Ponechat první, smazat ostatní
                array_shift($ids); // Odstranit první ID

                foreach ($ids as $idToDelete) {
                    $stmt = $pdo->prepare("DELETE FROM wgs_photos WHERE id = :id");
                    $stmt->execute([':id' => $idToDelete]);
                    $smazano++;
                    echo "<div>Smazano ID: $idToDelete</div>";
                }
            }

            echo "<div class='success'>Smazano $smazano duplicitnich zaznamu.</div>";
        } else {
            echo "<h2>4. Smazat duplicity</h2>";
            echo "<div class='warning'>Budou ponechany nejstarsi zaznamy, duplicity budou smazany.</div>";
            echo "<a href='?delete=1' class='btn' onclick=\"return confirm('Smazat duplicitni zaznamy?');\">Smazat duplicity</a>";
        }
    }

    echo "<p><a href='/admin.php' style='color: #39ff14;'>Zpet do admin</a></p>";

} catch (Exception $e) {
    echo "<div style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
