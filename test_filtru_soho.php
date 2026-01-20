<?php
/**
 * Test filtrování Natuzzi Soho ve statistikách
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test filtru Natuzzi Soho</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1400px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: #fff; font-weight: 600; }
        tr:nth-child(even) { background: #f9f9f9; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 5px; margin: 15px 0; font-family: 'Courier New', monospace; font-size: 11px; overflow-x: auto; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🧪 Test filtrování Natuzzi Soho</h1>";

    // Zjistit user_id pro Natuzzi Soho
    $stmt = $pdo->query("
        SELECT user_id, name, role, is_active
        FROM wgs_users
        WHERE name LIKE '%Soho%' OR name LIKE '%SOHO%'
    ");
    $soho = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$soho) {
        echo "<div class='error'>❌ Natuzzi Soho nenalezen v databázi!</div>";
        exit;
    }

    echo "<div class='section'>";
    echo "<h2>1️⃣ Informace o Natuzzi Soho</h2>";
    echo "<table>";
    echo "<tr><th>Pole</th><th>Hodnota</th></tr>";
    echo "<tr><td>user_id</td><td><strong>" . htmlspecialchars($soho['user_id']) . "</strong></td></tr>";
    echo "<tr><td>Jméno</td><td>" . htmlspecialchars($soho['name']) . "</td></tr>";
    echo "<tr><td>Role</td><td>" . htmlspecialchars($soho['role']) . "</td></tr>";
    echo "<tr><td>Aktivní</td><td>" . ($soho['is_active'] == 1 ? '✅ Ano' : '❌ Ne') . "</td></tr>";
    echo "</table>";
    echo "</div>";

    $soho_user_id = $soho['user_id'];

    // ========================================
    // Test 1: Zakázky SOHO bez filtru
    // ========================================
    echo "<div class='section'>";
    echo "<h2>2️⃣ Zakázky vytvořené Natuzzi Soho (BEZ filtru)</h2>";

    $stmt = $pdo->prepare("
        SELECT 
            r.cislo,
            r.jmeno,
            r.created_by,
            u.name as prodejce_name
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        WHERE r.created_by = :soho_id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute(['soho_id' => $soho_user_id]);
    $zakazky_bez_filtru = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($zakazky_bez_filtru)) {
        echo "<div class='warning'>⚠️ Žádné zakázky s created_by = " . htmlspecialchars($soho_user_id) . "</div>";
    } else {
        echo "<div class='success'>✅ Nalezeno <strong>" . count($zakazky_bez_filtru) . "</strong> zakázek</div>";
        echo "<table>";
        echo "<tr><th>Číslo</th><th>Jméno zákazníka</th><th>created_by</th><th>Prodejce</th></tr>";
        foreach ($zakazky_bez_filtru as $z) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($z['cislo']) . "</td>";
            echo "<td>" . htmlspecialchars($z['jmeno']) . "</td>";
            echo "<td>" . htmlspecialchars($z['created_by']) . "</td>";
            echo "<td>" . htmlspecialchars($z['prodejce_name']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // ========================================
    // Test 2: Simulace filtru - přesně jako API
    // ========================================
    echo "<div class='section'>";
    echo "<h2>3️⃣ Simulace filtru Natuzzi Soho (jako v API)</h2>";

    // Přesně stejný kod jako v buildFilterWhere()
    $conditions = [];
    $params = [];

    // Simulace $_GET['prodejci']
    $_GET['prodejci'] = [$soho_user_id];

    $prodejci = is_array($_GET['prodejci']) ? $_GET['prodejci'] : [$_GET['prodejci']];

    $prodejciConditions = [];
    foreach ($prodejci as $idx => $prodejce) {
        $key = ":prodejce_$idx";
        $prodejciConditions[] = "r.created_by = $key";
        $params[$key] = $prodejce;
    }

    if (!empty($prodejciConditions)) {
        $conditions[] = "(" . implode(" OR ", $prodejciConditions) . ")";
    }

    $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

    echo "<div class='code'>";
    echo "<strong>WHERE klauzule:</strong><br>";
    echo htmlspecialchars($where);
    echo "<br><br><strong>Parametry:</strong><br>";
    echo "<pre>" . print_r($params, true) . "</pre>";
    echo "</div>";

    // Provést dotaz
    $sql = "
        SELECT 
            r.cislo,
            r.jmeno,
            r.created_by,
            u.name as prodejce_name
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        $where
        ORDER BY r.created_at DESC
    ";

    echo "<div class='code'>";
    echo "<strong>Celý SQL dotaz:</strong><br>";
    echo htmlspecialchars($sql);
    echo "</div>";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $zakazky_s_filtrem = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($zakazky_s_filtrem)) {
        echo "<div class='error'>❌ Po aplikaci filtru <strong>ŽÁDNÉ ZAKÁZKY</strong>!</div>";
        echo "<div class='warning'>";
        echo "<strong>Problém:</strong> Filtr nezobrazil žádné výsledky!<br><br>";
        echo "Možné příčiny:<br>";
        echo "1. created_by v databázi je jiná hodnota než user_id<br>";
        echo "2. Je tam mezera nebo jiný bílý znak navíc<br>";
        echo "3. Typ sloupce created_by vs user_id se neshoduje";
        echo "</div>";
    } else {
        echo "<div class='success'>✅ Po aplikaci filtru nalezeno <strong>" . count($zakazky_s_filtrem) . "</strong> zakázek</div>";
        echo "<table>";
        echo "<tr><th>Číslo</th><th>Jméno zákazníka</th><th>created_by</th><th>Prodejce</th></tr>";
        foreach ($zakazky_s_filtrem as $z) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($z['cislo']) . "</td>";
            echo "<td>" . htmlspecialchars($z['jmeno']) . "</td>";
            echo "<td>" . htmlspecialchars($z['created_by']) . "</td>";
            echo "<td>" . htmlspecialchars($z['prodejce_name']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // ========================================
    // Test 3: Detekce bílých znaků
    // ========================================
    echo "<div class='section'>";
    echo "<h2>4️⃣ Kontrola bílých znaků a typu dat</h2>";

    $stmt = $pdo->query("
        SELECT 
            r.created_by,
            LENGTH(r.created_by) as delka_created_by,
            HEX(r.created_by) as hex_created_by,
            u.user_id,
            LENGTH(u.user_id) as delka_user_id,
            HEX(u.user_id) as hex_user_id,
            u.name
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        WHERE u.name LIKE '%Soho%' OR u.name LIKE '%SOHO%'
        LIMIT 5
    ");
    $whitespace_check = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($whitespace_check)) {
        echo "<table>";
        echo "<tr><th>created_by</th><th>Délka</th><th>HEX</th><th>user_id</th><th>Délka</th><th>HEX</th><th>Shodují se?</th></tr>";
        foreach ($whitespace_check as $row) {
            $shoda = ($row['created_by'] === $row['user_id']) ? '✅ ANO' : '❌ NE';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['created_by']) . "</td>";
            echo "<td>" . $row['delka_created_by'] . "</td>";
            echo "<td style='font-size:9px;'>" . htmlspecialchars($row['hex_created_by']) . "</td>";
            echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
            echo "<td>" . $row['delka_user_id'] . "</td>";
            echo "<td style='font-size:9px;'>" . htmlspecialchars($row['hex_user_id']) . "</td>";
            echo "<td><strong>$shoda</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>Žádné záznamy pro whitespace check</div>";
    }

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
