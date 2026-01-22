<?php
/**
 * DIAGNOSTICKÝ SKRIPT - Úprava zakázky ve statistikách
 *
 * Tento skript testuje celý flow editace zakázky
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: Úprava zakázky</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; border-left: 4px solid #2D5016;
             padding-left: 15px; }
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
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; cursor: pointer;
               border: none; }
        .btn:hover { background: #1a300d; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Diagnostika: Úprava zakázky ve statistikách</h1>";

    // ========================================
    // 1. KONTROLA STRUKTURY TABULKY
    // ========================================
    echo "<h2>1️⃣ Kontrola struktury tabulky wgs_reklamace</h2>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $requiredColumns = ['id', 'cislo', 'assigned_to', 'created_by', 'fakturace_firma', 'updated_at'];
    $foundColumns = array_column($columns, 'Field');

    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Typ</th><th>Status</th></tr>";

    foreach ($requiredColumns as $col) {
        $found = in_array($col, $foundColumns);
        $status = $found ? "✅ OK" : "❌ CHYBÍ";
        $class = $found ? 'success' : 'error';

        $type = '';
        foreach ($columns as $c) {
            if ($c['Field'] === $col) {
                $type = $c['Type'];
                break;
            }
        }

        echo "<tr><td><code>$col</code></td><td>$type</td><td class='$class'>$status</td></tr>";
    }
    echo "</table>";

    // ========================================
    // 2. KONTROLA EXISTUJÍCÍ ZAKÁZKY
    // ========================================
    echo "<h2>2️⃣ Kontrola testovací zakázky</h2>";

    $stmt = $pdo->query("
        SELECT id, cislo, jmeno, assigned_to, created_by, fakturace_firma, updated_at
        FROM wgs_reklamace
        ORDER BY id DESC
        LIMIT 1
    ");
    $testZakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($testZakazka) {
        echo "<div class='success'>";
        echo "<strong>Testovací zakázka nalezena:</strong><br>";
        echo "<pre>" . print_r($testZakazka, true) . "</pre>";
        echo "</div>";

        $testId = $testZakazka['id'];
    } else {
        echo "<div class='error'><strong>CHYBA:</strong> Žádná zakázka v databázi!</div>";
        $testId = null;
    }

    // ========================================
    // 3. TEST GET ENDPOINTŮ
    // ========================================
    echo "<h2>3️⃣ Test GET endpointů (detail_zakazky, seznam_techniku, seznam_prodejcu)</h2>";

    if ($testId) {
        // Detail zakázky
        $_GET['action'] = 'detail_zakazky';
        $_GET['id'] = $testId;

        ob_start();
        include __DIR__ . '/api/statistiky_api.php';
        $output = ob_get_clean();

        $result = json_decode($output, true);

        if ($result && $result['status'] === 'success') {
            echo "<div class='success'>";
            echo "<strong>✅ detail_zakazky endpoint OK</strong><br>";
            echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<strong>❌ detail_zakazky endpoint CHYBA</strong><br>";
            echo "<pre>$output</pre>";
            echo "</div>";
        }
    }

    // ========================================
    // 4. TEST TECHNIKŮ A PRODEJCŮ
    // ========================================
    echo "<h2>4️⃣ Načtení seznamu techniků a prodejců</h2>";

    $stmtTech = $pdo->query("
        SELECT id, user_id, name, email, role
        FROM wgs_users
        WHERE role LIKE '%technik%' OR role LIKE '%technician%'
        ORDER BY name ASC
        LIMIT 5
    ");
    $technici = $stmtTech->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>Technici v databázi:</strong> " . count($technici) . " záznamů<br>";
    if (count($technici) > 0) {
        echo "<pre>" . json_encode($technici, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    echo "</div>";

    $stmtProd = $pdo->query("
        SELECT id, user_id, name, email, role
        FROM wgs_users
        WHERE role = 'prodejce'
        ORDER BY name ASC
        LIMIT 5
    ");
    $prodejci = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>Prodejci v databázi:</strong> " . count($prodejci) . " záznamů<br>";
    if (count($prodejci) > 0) {
        echo "<pre>" . json_encode($prodejci, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    echo "</div>";

    // ========================================
    // 5. SIMULACE UPDATE DOTAZU
    // ========================================
    echo "<h2>5️⃣ Simulace UPDATE dotazu (DRY RUN)</h2>";

    if ($testId && count($technici) > 0 && count($prodejci) > 0) {
        // DŮLEŽITÉ: assigned_to je INT(11), musíme použít numeric id, ne user_id!
        $testTechnikId = $technici[0]['id'] ?? $technici[0]['user_id'];
        // created_by je VARCHAR(50), můžeme použít user_id
        $testProdejceId = $prodejci[0]['user_id'];
        $testZeme = 'CZ';

        echo "<div class='info'>";
        echo "<strong>Test parametry:</strong><br>";
        echo "• ID zakázky: <code>$testId</code><br>";
        echo "• Technik ID (numeric): <code>$testTechnikId</code><br>";
        echo "• Technik user_id (string): <code>{$technici[0]['user_id']}</code><br>";
        echo "• Technik jméno: {$technici[0]['name']}<br>";
        echo "• Prodejce ID: <code>$testProdejceId</code><br>";
        echo "• Prodejce jméno: {$prodejci[0]['name']}<br>";
        echo "• Země: <code>$testZeme</code><br>";
        echo "</div>";

        // SQL dotaz který se spustí
        $sql = "
            UPDATE wgs_reklamace
            SET
                assigned_to = :assigned_to,
                created_by = :created_by,
                fakturace_firma = :faktura_zeme,
                updated_at = NOW()
            WHERE id = :id
        ";

        echo "<div class='info'>";
        echo "<strong>SQL dotaz:</strong>";
        echo "<pre>$sql</pre>";
        echo "</div>";

        // Spustit UPDATE v transakci a rollback
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'assigned_to' => $testTechnikId,
                'created_by' => $testProdejceId,
                'faktura_zeme' => $testZeme,
                'id' => $testId
            ]);

            $affected = $stmt->rowCount();

            $pdo->rollBack(); // ROLLBACK - neukládáme změny

            echo "<div class='success'>";
            echo "<strong>✅ UPDATE dotaz proběhl úspěšně!</strong><br>";
            echo "Počet ovlivněných řádků: <strong>$affected</strong><br>";
            echo "<em>(Transakce byla rollbacknuta - žádné změny v DB)</em>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA při UPDATE:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    }

    // ========================================
    // 6. CSRF TOKEN
    // ========================================
    echo "<h2>6️⃣ CSRF Token</h2>";

    require_once __DIR__ . '/includes/csrf_helper.php';
    $testToken = generateCSRFToken();

    echo "<div class='info'>";
    echo "<strong>Vygenerovaný CSRF token:</strong><br>";
    echo "<code>$testToken</code>";
    echo "</div>";

    $isValid = validateCSRFToken($testToken);
    if ($isValid) {
        echo "<div class='success'>✅ CSRF token validace OK</div>";
    } else {
        echo "<div class='error'>❌ CSRF token validace SELHALA</div>";
    }

    // ========================================
    // 7. VÝSLEDEK
    // ========================================
    echo "<h2>7️⃣ Výsledek diagnostiky</h2>";

    $allOk = true;
    $errors = [];

    foreach ($requiredColumns as $col) {
        if (!in_array($col, $foundColumns)) {
            $allOk = false;
            $errors[] = "Chybí sloupec: $col";
        }
    }

    if (!$testId) {
        $allOk = false;
        $errors[] = "Žádná zakázka v databázi";
    }

    if (count($technici) === 0) {
        $allOk = false;
        $errors[] = "Žádní technici v databázi";
    }

    if (count($prodejci) === 0) {
        $allOk = false;
        $errors[] = "Žádní prodejci v databázi";
    }

    if ($allOk) {
        echo "<div class='success'>";
        echo "<h3>✅ VŠECHNY TESTY PROŠLY</h3>";
        echo "<p>Systém by měl fungovat správně. Pokud stále máte problémy, zkontrolujte JavaScript konzoli v prohlížeči.</p>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ NALEZENY PROBLÉMY:</h3>";
        echo "<ul>";
        foreach ($errors as $err) {
            echo "<li>$err</li>";
        }
        echo "</ul>";
        echo "</div>";
    }

    echo "<div style='margin-top: 30px;'>";
    echo "<a href='statistiky.php' class='btn'>← Zpět na Statistiky</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>FATÁLNÍ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
