<?php
/**
 * Diagnostika prodejců ve statistikách
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika prodejců</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1400px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: #fff; font-weight: 600; }
        tr:nth-child(even) { background: #f9f9f9; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Diagnostika prodejců ve statistikách</h1>";

    // ========================================
    // 1. Zjistit všechny unikátní hodnoty created_by
    // ========================================
    echo "<div class='section'>";
    echo "<h2>1️⃣ Všechny unikátní hodnoty <code>created_by</code> v tabulce reklamací</h2>";

    $stmt = $pdo->query("
        SELECT 
            r.created_by,
            COUNT(*) as pocet_zakazek,
            u.name as prodejce_name,
            u.role as prodejce_role,
            u.is_active as prodejce_aktivni
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        GROUP BY r.created_by, u.name, u.role, u.is_active
        ORDER BY pocet_zakazek DESC
    ");

    $created_by_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>created_by</th><th>Jméno uživatele</th><th>Role</th><th>Aktivní</th><th>Počet zakázek</th><th>Problém?</th></tr>";

    foreach ($created_by_stats as $row) {
        $problem = [];
        
        if (empty($row['created_by'])) {
            $problem[] = "Mimozáruční servis";
        } elseif (empty($row['prodejce_name'])) {
            $problem[] = "User neexistuje v wgs_users!";
        } elseif ($row['prodejce_role'] !== 'prodejce') {
            $problem[] = "Role: {$row['prodejce_role']} (ne prodejce)";
        } elseif ($row['prodejce_aktivni'] != 1) {
            $problem[] = "Neaktivní uživatel";
        }

        $problemText = empty($problem) ? '✅ OK' : '⚠️ ' . implode(', ', $problem);
        $rowClass = empty($problem) ? '' : ' style="background: #fff3cd;"';

        echo "<tr{$rowClass}>";
        echo "<td>" . htmlspecialchars($row['created_by'] ?: '(prázdné)') . "</td>";
        echo "<td>" . htmlspecialchars($row['prodejce_name'] ?: '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['prodejce_role'] ?: '-') . "</td>";
        echo "<td>" . ($row['prodejce_aktivni'] == 1 ? '✅ Ano' : '❌ Ne') . "</td>";
        echo "<td><strong>" . $row['pocet_zakazek'] . "</strong></td>";
        echo "<td>{$problemText}</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";

    // ========================================
    // 2. Seznam prodejců v multi-selectu
    // ========================================
    echo "<div class='section'>";
    echo "<h2>2️⃣ Seznam prodejců zobrazený v multi-selectu (loadProdejci API)</h2>";

    $stmt = $pdo->query("
        SELECT DISTINCT u.user_id as id, u.name, u.role, u.is_active
        FROM wgs_users u
        WHERE u.is_active = 1 AND u.role = 'prodejce'
        ORDER BY u.name ASC
    ");

    $prodejci_v_filtru = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($prodejci_v_filtru)) {
        echo "<div class='warning'>⚠️ Žádní prodejci nejsou v multi-selectu! Zkontrolujte, zda existují aktivní uživatelé s rolí 'prodejce'.</div>";
    } else {
        echo "<table>";
        echo "<tr><th>user_id</th><th>Jméno</th><th>Role</th><th>Aktivní</th></tr>";

        foreach ($prodejci_v_filtru as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['role']) . "</td>";
            echo "<td>" . ($row['is_active'] == 1 ? '✅ Ano' : '❌ Ne') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    echo "</div>";

    // ========================================
    // 3. Zakázky které se nezobrazí po filtrování
    // ========================================
    echo "<div class='section'>";
    echo "<h2>3️⃣ Zakázky které se NEZOBRAZÍ po filtrování prodejcem</h2>";
    echo "<p>Tyto zakázky mají <code>created_by</code> který neodpovídá žádnému aktivnímu prodejci:</p>";

    $stmt = $pdo->query("
        SELECT 
            r.cislo,
            r.jmeno,
            r.created_by,
            u.name as prodejce_name,
            u.role as prodejce_role,
            u.is_active as prodejce_aktivni
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        WHERE 
            r.created_by IS NOT NULL 
            AND r.created_by != ''
            AND (
                u.user_id IS NULL 
                OR u.role != 'prodejce' 
                OR u.is_active != 1
            )
        ORDER BY r.created_at DESC
        LIMIT 50
    ");

    $problematicke_zakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($problematicke_zakazky)) {
        echo "<div class='success'>✅ Žádné problematické zakázky!</div>";
    } else {
        echo "<div class='error'>❌ Nalezeno <strong>" . count($problematicke_zakazky) . "</strong> zakázek s problémem!</div>";
        
        echo "<table>";
        echo "<tr><th>Číslo</th><th>Jméno zákazníka</th><th>created_by</th><th>Prodejce</th><th>Role</th><th>Aktivní</th><th>Problém</th></tr>";

        foreach ($problematicke_zakazky as $row) {
            $problem = [];
            
            if (empty($row['prodejce_name'])) {
                $problem[] = "User neexistuje";
            }
            if ($row['prodejce_role'] !== 'prodejce') {
                $problem[] = "Role: " . ($row['prodejce_role'] ?: 'žádná');
            }
            if ($row['prodejce_aktivni'] != 1) {
                $problem[] = "Neaktivní";
            }

            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['cislo']) . "</td>";
            echo "<td>" . htmlspecialchars($row['jmeno']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_by']) . "</td>";
            echo "<td>" . htmlspecialchars($row['prodejce_name'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['prodejce_role'] ?: '-') . "</td>";
            echo "<td>" . ($row['prodejce_aktivni'] == 1 ? '✅' : '❌') . "</td>";
            echo "<td>" . implode(', ', $problem) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    echo "</div>";

    // ========================================
    // 4. Doporučené řešení
    // ========================================
    echo "<div class='section'>";
    echo "<h2>4️⃣ Doporučené řešení</h2>";
    
    echo "<div class='warning'>";
    echo "<strong>Problém:</strong><br>";
    echo "Některé zakázky mají <code>created_by</code> který ukazuje na uživatele, který:<br>";
    echo "• Není aktivní (<code>is_active = 0</code>)<br>";
    echo "• Nemá roli 'prodejce'<br>";
    echo "• Nebo vůbec neexistuje v tabulce <code>wgs_users</code><br><br>";
    echo "Tyto zakázky se zobrazí v seznamu s prodejcem podle jména uživatele,<br>";
    echo "ale NEBUDOU se zobrazovat po filtrování, protože filtr hledá pouze aktivní prodejce.";
    echo "</div>";

    echo "<div class='success'>";
    echo "<strong>Řešení:</strong><br>";
    echo "1️⃣ <strong>Načítat VŠECHNY uživatele</strong> v loadProdejci() (ne jen role='prodejce' a is_active=1)<br>";
    echo "2️⃣ <strong>NEBO</strong> Zobrazit i neaktivní/ostatní uživatele v multi-selectu s poznámkou (neaktivní)<br>";
    echo "3️⃣ <strong>NEBO</strong> Změnit filtrování, aby se hledalo přímo v <code>r.created_by</code> bez ohledu na roli";
    echo "</div>";

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
