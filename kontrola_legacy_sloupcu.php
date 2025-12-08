<?php
/**
 * Kontrola legacy sloupců - zjištění zda obsahují data
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
    <title>Kontrola legacy sloupcu</title>
    <style>
        body { font-family: 'Poppins', sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        h1, h2 { color: #39ff14; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; background: #222; }
        th, td { padding: 0.5rem; text-align: left; border: 1px solid #444; }
        th { background: #333; color: #39ff14; }
        .safe { color: #39ff14; }
        .warning { color: #ff8800; }
        .danger { color: #ff4444; }
        .info { background: #333; padding: 1rem; border-radius: 5px; margin: 1rem 0; }
        pre { background: #111; padding: 0.5rem; overflow-x: auto; font-size: 0.8rem; max-height: 200px; }
    </style>
</head>
<body>
<h1>Kontrola legacy sloupcu</h1>";

try {
    $pdo = getDbConnection();

    // Sloupce k prověření
    $legacyColumns = [
        'original_reklamace_id' => 'Reference na původní zakázku (funkce znovuotevření odstraněna)',
        'castka' => 'Legacy částka (máme cena_celkem)',
        'cena' => 'Legacy cena (máme cena_celkem)',
        'poznamky' => 'Legacy poznámky (máme tabulku wgs_notes)',
        'adresa' => 'Legacy adresa (máme ulice, mesto, psc)'
    ];

    echo "<h2>1. Pocet zaznamu s daty v legacy sloupcich</h2>";
    echo "<table>
        <tr><th>Sloupec</th><th>Popis</th><th>Neprázdných</th><th>Celkem</th><th>Status</th></tr>";

    $total = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace")->fetchColumn();

    foreach ($legacyColumns as $column => $description) {
        // Zkontrolovat jestli sloupec existuje
        $checkCol = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE '$column'");
        if (!$checkCol->fetch()) {
            echo "<tr>
                <td><strong>$column</strong></td>
                <td>$description</td>
                <td colspan='2'>SLOUPEC NEEXISTUJE</td>
                <td class='safe'>OK</td>
            </tr>";
            continue;
        }

        $count = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE $column IS NOT NULL AND $column != '' AND $column != '0' AND $column != '0.00'")->fetchColumn();

        $status = $count == 0 ? 'safe' : ($count < 10 ? 'warning' : 'danger');
        $statusText = $count == 0 ? 'PRAZDNY - lze smazat' : ($count < 10 ? 'MALO DAT' : 'OBSAHUJE DATA');

        echo "<tr>
            <td><strong>$column</strong></td>
            <td>$description</td>
            <td>$count</td>
            <td>$total</td>
            <td class='$status'>$statusText</td>
        </tr>";
    }
    echo "</table>";

    // Detailní pohled na data
    echo "<h2>2. Ukazka dat v legacy sloupcich</h2>";

    foreach ($legacyColumns as $column => $description) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE '$column'");
        if (!$checkCol->fetch()) continue;

        $stmt = $pdo->query("SELECT reklamace_id, $column FROM wgs_reklamace WHERE $column IS NOT NULL AND $column != '' AND $column != '0' AND $column != '0.00' LIMIT 5");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($samples)) {
            echo "<div class='info'><strong>$column:</strong> Žádná data</div>";
        } else {
            echo "<div class='info'><strong>$column</strong> - ukázka dat:";
            echo "<table><tr><th>Reklamace ID</th><th>Hodnota</th></tr>";
            foreach ($samples as $row) {
                $value = htmlspecialchars(substr($row[$column] ?? '', 0, 100));
                if (strlen($row[$column] ?? '') > 100) $value .= '...';
                echo "<tr><td>{$row['reklamace_id']}</td><td><pre>$value</pre></td></tr>";
            }
            echo "</table></div>";
        }
    }

    // Kontrola konzistence adres
    echo "<h2>3. Konzistence adres</h2>";

    $addrStats = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN adresa IS NOT NULL AND adresa != '' THEN 1 ELSE 0 END) as has_adresa,
            SUM(CASE WHEN ulice IS NOT NULL AND ulice != '' THEN 1 ELSE 0 END) as has_ulice,
            SUM(CASE WHEN mesto IS NOT NULL AND mesto != '' THEN 1 ELSE 0 END) as has_mesto,
            SUM(CASE WHEN (adresa IS NOT NULL AND adresa != '') AND (ulice IS NULL OR ulice = '') THEN 1 ELSE 0 END) as only_adresa,
            SUM(CASE WHEN (ulice IS NOT NULL AND ulice != '') AND (adresa IS NULL OR adresa = '') THEN 1 ELSE 0 END) as only_ulice
        FROM wgs_reklamace
    ")->fetch(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>Metrika</th><th>Počet</th></tr>
        <tr><td>Celkem záznamů</td><td>{$addrStats['total']}</td></tr>
        <tr><td>Má ADRESA (legacy)</td><td>{$addrStats['has_adresa']}</td></tr>
        <tr><td>Má ULICE (nový)</td><td>{$addrStats['has_ulice']}</td></tr>
        <tr><td>Má MĚSTO</td><td>{$addrStats['has_mesto']}</td></tr>
        <tr class='warning'><td>Má POUZE adresa (ne ulice)</td><td>{$addrStats['only_adresa']}</td></tr>
        <tr><td>Má POUZE ulice (ne adresa)</td><td>{$addrStats['only_ulice']}</td></tr>
    </table>";

    // Kontrola cen
    echo "<h2>4. Konzistence cen</h2>";

    $priceStats = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN castka > 0 THEN 1 ELSE 0 END) as has_castka,
            SUM(CASE WHEN cena > 0 THEN 1 ELSE 0 END) as has_cena,
            SUM(CASE WHEN cena_celkem > 0 THEN 1 ELSE 0 END) as has_cena_celkem,
            SUM(CASE WHEN (castka > 0 OR cena > 0) AND cena_celkem = 0 THEN 1 ELSE 0 END) as legacy_only
        FROM wgs_reklamace
    ")->fetch(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>Metrika</th><th>Počet</th></tr>
        <tr><td>Celkem záznamů</td><td>{$priceStats['total']}</td></tr>
        <tr><td>Má CASTKA (legacy)</td><td>{$priceStats['has_castka']}</td></tr>
        <tr><td>Má CENA (legacy)</td><td>{$priceStats['has_cena']}</td></tr>
        <tr><td>Má CENA_CELKEM (aktuální)</td><td>{$priceStats['has_cena_celkem']}</td></tr>
        <tr class='warning'><td>Má POUZE legacy cenu (ne cena_celkem)</td><td>{$priceStats['legacy_only']}</td></tr>
    </table>";

    // Kontrola original_reklamace_id
    echo "<h2>5. Klonované zakázky (original_reklamace_id)</h2>";

    $checkCol = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'original_reklamace_id'");
    if ($checkCol->fetch()) {
        $cloneCount = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE original_reklamace_id IS NOT NULL AND original_reklamace_id != ''")->fetchColumn();

        if ($cloneCount > 0) {
            echo "<div class='info warning'>Nalezeno <strong>$cloneCount</strong> klonovaných zakázek.</div>";

            $clones = $pdo->query("SELECT reklamace_id, original_reklamace_id, jmeno, stav FROM wgs_reklamace WHERE original_reklamace_id IS NOT NULL AND original_reklamace_id != '' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            echo "<table><tr><th>ID</th><th>Původní ID</th><th>Zákazník</th><th>Stav</th></tr>";
            foreach ($clones as $c) {
                echo "<tr><td>{$c['reklamace_id']}</td><td>{$c['original_reklamace_id']}</td><td>" . htmlspecialchars($c['jmeno']) . "</td><td>{$c['stav']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info safe'>Žádné klonované zakázky - sloupec lze bezpečně odstranit.</div>";
        }
    }

    // Doporučení
    echo "<h2>6. Doporučení</h2>";
    echo "<div class='info'>
        <p>Na základě analýzy:</p>
        <ul>
            <li><strong>original_reklamace_id</strong> - " . ($cloneCount ?? 0) . " záznamů → " . (($cloneCount ?? 0) == 0 ? '<span class="safe">SMAZAT</span>' : '<span class="warning">NEJDŘÍV SMAZAT KLONY</span>') . "</li>
            <li><strong>castka</strong> - {$priceStats['has_castka']} záznamů → " . ($priceStats['has_castka'] == 0 ? '<span class="safe">SMAZAT</span>' : '<span class="warning">MIGROVAT DO cena_celkem</span>') . "</li>
            <li><strong>cena</strong> - {$priceStats['has_cena']} záznamů → " . ($priceStats['has_cena'] == 0 ? '<span class="safe">SMAZAT</span>' : '<span class="warning">MIGROVAT DO cena_celkem</span>') . "</li>
            <li><strong>adresa</strong> - {$addrStats['has_adresa']} záznamů → " . ($addrStats['only_adresa'] == 0 ? '<span class="safe">SMAZAT (data v ulice/mesto/psc)</span>' : '<span class="warning">MIGROVAT ' . $addrStats['only_adresa'] . ' záznamů</span>') . "</li>
        </ul>
    </div>";

    echo "<p><a href='/admin.php' style='color: #39ff14;'>Zpět do admin</a></p>";

} catch (Exception $e) {
    echo "<div class='danger'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
