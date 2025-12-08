<?php
/**
 * Kontrola duplicitních fotek podle názvu souboru
 * Hledá fotky se stejným file_name nebo podobným názvem pro stejnou reklamaci
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
    <title>Kontrola duplicit fotek</title>
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
        code { background: #333; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
<h1>Kontrola duplicit fotek</h1>";

try {
    $pdo = getDbConnection();

    // 1. Počet fotek na reklamaci
    echo "<h2>1. Pocet fotek podle reklamace_id</h2>";
    $stmt = $pdo->query("
        SELECT reklamace_id, COUNT(*) as pocet, GROUP_CONCAT(id ORDER BY id) as ids
        FROM wgs_photos
        GROUP BY reklamace_id
        ORDER BY pocet DESC
        LIMIT 20
    ");
    $pocty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>Reklamace ID</th><th>Pocet fotek</th><th>ID zaznamu</th></tr>";
    foreach ($pocty as $p) {
        $class = $p['pocet'] > 10 ? 'duplicate' : '';
        echo "<tr class='$class'>
            <td><code>" . htmlspecialchars($p['reklamace_id']) . "</code></td>
            <td>{$p['pocet']}</td>
            <td style='font-size: 0.7rem;'>{$p['ids']}</td>
        </tr>";
    }
    echo "</table>";

    // 2. Duplicitní file_name pro stejnou reklamaci
    echo "<h2>2. Duplicitni file_name pro stejnou reklamaci</h2>";
    $stmt = $pdo->query("
        SELECT reklamace_id, file_name, COUNT(*) as pocet, GROUP_CONCAT(id) as ids
        FROM wgs_photos
        WHERE file_name IS NOT NULL AND file_name != ''
        GROUP BY reklamace_id, file_name
        HAVING COUNT(*) > 1
        ORDER BY pocet DESC
    ");
    $dupNazvy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dupNazvy)) {
        echo "<div class='success'>Zadne duplicitni nazvy souboru.</div>";
    } else {
        echo "<div class='warning'>Nalezeno " . count($dupNazvy) . " duplicitnich nazvu!</div>";
        echo "<table>
            <tr><th>Reklamace</th><th>Nazev souboru</th><th>Pocet</th><th>IDs</th></tr>";
        foreach ($dupNazvy as $d) {
            echo "<tr class='duplicate'>
                <td><code>" . htmlspecialchars($d['reklamace_id']) . "</code></td>
                <td><code>" . htmlspecialchars($d['file_name']) . "</code></td>
                <td>{$d['pocet']}</td>
                <td>{$d['ids']}</td>
            </tr>";
        }
        echo "</table>";
    }

    // 3. Fotky s různými cestami ale podobným obsahem (section_name + timestamp v názvu)
    echo "<h2>3. Vsechny fotky pro reklamace s vice nez 4 fotkami</h2>";
    $stmt = $pdo->query("
        SELECT p.id, p.reklamace_id, p.section_name, p.photo_path, p.file_name, p.created_at
        FROM wgs_photos p
        WHERE p.reklamace_id IN (
            SELECT reklamace_id FROM wgs_photos GROUP BY reklamace_id HAVING COUNT(*) > 4
        )
        ORDER BY p.reklamace_id, p.section_name, p.id
    ");
    $detailFotky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($detailFotky)) {
        echo "<div class='success'>Zadne reklamace s vice nez 4 fotkami.</div>";
    } else {
        echo "<table>
            <tr><th>ID</th><th>Reklamace</th><th>Sekce</th><th>photo_path</th><th>file_name</th><th>Vytvoreno</th></tr>";
        $lastRekl = '';
        foreach ($detailFotky as $f) {
            $class = ($lastRekl && $lastRekl === $f['reklamace_id']) ? '' : 'style="border-top: 3px solid #39ff14;"';
            $lastRekl = $f['reklamace_id'];
            echo "<tr $class>
                <td>{$f['id']}</td>
                <td><code>" . htmlspecialchars($f['reklamace_id']) . "</code></td>
                <td>{$f['section_name']}</td>
                <td style='font-size: 0.7rem;'><code>" . htmlspecialchars($f['photo_path']) . "</code></td>
                <td style='font-size: 0.7rem;'><code>" . htmlspecialchars($f['file_name'] ?? 'NULL') . "</code></td>
                <td>{$f['created_at']}</td>
            </tr>";
        }
        echo "</table>";
    }

    // 4. Tlačítko pro smazání duplicit podle photo_path
    echo "<h2>4. Smazat duplicity podle photo_path</h2>";

    $stmt = $pdo->query("
        SELECT photo_path, COUNT(*) as pocet, GROUP_CONCAT(id ORDER BY id) as ids
        FROM wgs_photos
        GROUP BY photo_path
        HAVING COUNT(*) > 1
    ");
    $dupCesty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dupCesty)) {
        echo "<div class='success'>Zadne duplicitni cesty k smazani.</div>";
    } else {
        if (isset($_GET['delete']) && $_GET['delete'] === '1') {
            $smazano = 0;
            foreach ($dupCesty as $d) {
                $ids = explode(',', $d['ids']);
                array_shift($ids); // Ponechat první
                foreach ($ids as $idToDelete) {
                    $stmt = $pdo->prepare("DELETE FROM wgs_photos WHERE id = :id");
                    $stmt->execute([':id' => $idToDelete]);
                    $smazano++;
                    echo "<div>Smazano ID: $idToDelete</div>";
                }
            }
            echo "<div class='success'>Smazano $smazano duplicitnich zaznamu.</div>";
        } else {
            echo "<div class='warning'>Nalezeno " . count($dupCesty) . " duplicitnich cest.</div>";
            echo "<a href='?delete=1' class='btn' onclick=\"return confirm('Smazat duplicity?');\">Smazat duplicity</a>";
        }
    }

    echo "<p><a href='/admin.php' style='color: #39ff14;'>Zpet do admin</a></p>";

} catch (Exception $e) {
    echo "<div style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
