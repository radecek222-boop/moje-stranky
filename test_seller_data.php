<?php
/**
 * Test načítání prodejce z reálné zakázky
 * 
 * Ověří, zda se správně mapuje:
 * created_by -> wgs_users -> created_by_email -> seller_email
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit test.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test prodejce v zakázkách</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        h2 { color: #555; margin-top: 2rem; }
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px;
                 font-size: 0.85rem; font-weight: 600; }
        .badge-ok { background: #28a745; color: white; }
        .badge-missing { background: #dc3545; color: white; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px;
               font-family: monospace; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #000; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Test prodejce v zakázkách</h1>";

    echo "<div class='info'><strong>Co testujeme:</strong><br>";
    echo "1. Zda se správně načítá <code>created_by</code> z <code>wgs_reklamace</code><br>";
    echo "2. Zda JOIN s <code>wgs_users</code> vrací <code>created_by_email</code><br>";
    echo "3. Zda se to správně mapuje na <code>seller_email</code> pro notifikace<br>";
    echo "</div>";

    // Načíst posledních 10 zakázek s prodejcem
    echo "<h2>1. Kontrola databáze - posledních 10 zakázek</h2>";

    $stmt = $pdo->query("
        SELECT 
            r.reklamace_id,
            r.cislo,
            r.jmeno as customer_name,
            r.email as customer_email,
            r.created_by,
            u.user_id,
            u.name as created_by_name,
            u.email as created_by_email,
            r.stav,
            r.datum_vytvoreni
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        ORDER BY r.datum_vytvoreni DESC
        LIMIT 10
    ");
    $zakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($zakazky)) {
        echo "<div class='warning'>V databázi nejsou žádné zakázky.</div>";
    } else {
        echo "<table>";
        echo "<tr>";
        echo "<th>Číslo zakázky</th>";
        echo "<th>Zákazník</th>";
        echo "<th>created_by ID</th>";
        echo "<th>Prodejce (jméno)</th>";
        echo "<th>Prodejce (email)</th>";
        echo "<th>Status</th>";
        echo "</tr>";

        $pocetOk = 0;
        $pocetChybejici = 0;

        foreach ($zakazky as $z) {
            echo "<tr>";
            echo "<td><strong>{$z['cislo']}</strong></td>";
            echo "<td>{$z['customer_name']}</td>";
            echo "<td>" . ($z['created_by'] ?: '<em>NULL</em>') . "</td>";
            
            if (!empty($z['created_by_email'])) {
                echo "<td>{$z['created_by_name']}</td>";
                echo "<td><code>{$z['created_by_email']}</code></td>";
                echo "<td><span class='badge badge-ok'>OK</span></td>";
                $pocetOk++;
            } else {
                echo "<td colspan='2'><em>Prodejce nenalezen</em></td>";
                echo "<td><span class='badge badge-missing'>CHYBÍ</span></td>";
                $pocetChybejici++;
            }
            
            echo "</tr>";
        }

        echo "</table>";

        echo "<div class='info'>";
        echo "<strong>Statistika:</strong><br>";
        echo "✅ Zakázky s prodejcem: <strong>{$pocetOk}</strong><br>";
        if ($pocetChybejici > 0) {
            echo "⚠️ Zakázky bez prodejce: <strong>{$pocetChybejici}</strong>";
        }
        echo "</div>";
    }

    // Test konkrétní zakázky - simulace jak by to fungovalo v protokol_api.php
    echo "<h2>2. Simulace načtení pro email notifikaci</h2>";

    if (!empty($zakazky)) {
        $testZakazka = $zakazky[0];
        
        echo "<div class='info'>";
        echo "<strong>Testujeme zakázku:</strong> {$testZakazka['cislo']}<br>";
        echo "<strong>Zákazník:</strong> {$testZakazka['customer_name']}<br>";
        echo "</div>";

        // Příprava dat stejně jako v protokol_api.php
        $notificationData = [
            'customer_name' => $testZakazka['customer_name'],
            'customer_email' => $testZakazka['customer_email'],
            'seller_name' => $testZakazka['created_by_name'] ?? '',
            'seller_email' => $testZakazka['created_by_email'] ?? '',
            'order_id' => $testZakazka['cislo'],
        ];

        echo "<h3>Data pro notifikaci:</h3>";
        echo "<table>";
        echo "<tr><th>Proměnná</th><th>Hodnota</th><th>Status</th></tr>";
        
        echo "<tr>";
        echo "<td><code>{{"."customer_name}}</code></td>";
        echo "<td>{$notificationData['customer_name']}</td>";
        echo "<td><span class='badge badge-ok'>OK</span></td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td><code>{{"."customer_email}}</code></td>";
        echo "<td>{$notificationData['customer_email']}</td>";
        echo "<td><span class='badge badge-ok'>OK</span></td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td><code>{{"."seller_name}}</code></td>";
        echo "<td>" . ($notificationData['seller_name'] ?: '<em>prázdné</em>') . "</td>";
        if (!empty($notificationData['seller_name'])) {
            echo "<td><span class='badge badge-ok'>OK</span></td>";
        } else {
            echo "<td><span class='badge badge-missing'>CHYBÍ</span></td>";
        }
        echo "</tr>";

        echo "<tr>";
        echo "<td><code>{{"."seller_email}}</code></td>";
        echo "<td>" . ($notificationData['seller_email'] ?: '<em>prázdné</em>') . "</td>";
        if (!empty($notificationData['seller_email'])) {
            echo "<td><span class='badge badge-ok'>OK</span></td>";
        } else {
            echo "<td><span class='badge badge-missing'>CHYBÍ</span></td>";
        }
        echo "</tr>";

        echo "</table>";

        // Simulace resolveRoleToEmail
        echo "<h3>Simulace resolveRoleToEmail('seller'):</h3>";
        
        $resolveRoleToEmail = function($role, $data) use ($pdo) {
            switch ($role) {
                case 'customer':
                    return $data['customer_email'] ?? null;
                case 'admin':
                    $stmt = $pdo->prepare("SELECT config_value FROM wgs_system_config WHERE config_key = 'admin_email' LIMIT 1");
                    $stmt->execute();
                    return $stmt->fetchColumn() ?: null;
                case 'technician':
                    return $data['technician_email'] ?? null;
                case 'seller':
                    return $data['seller_email'] ?? null;
                default:
                    return null;
            }
        };

        $sellerEmail = $resolveRoleToEmail('seller', $notificationData);

        echo "<div class='";
        if ($sellerEmail) {
            echo "success'><strong>✅ ÚSPĚCH</strong><br>";
            echo "Role 'seller' se resolvuje na: <code>{$sellerEmail}</code>";
        } else {
            echo "error'><strong>❌ CHYBA</strong><br>";
            echo "Role 'seller' vrací NULL - email by se neposlal!";
        }
        echo "</div>";

        // Závěrečné vyhodnocení
        if ($sellerEmail && !empty($notificationData['seller_name'])) {
            echo "<div class='success'>";
            echo "<strong>✅ KOMPLETNÍ TEST ÚSPĚŠNÝ</strong><br>";
            echo "Proměnné <code>{{"."seller_name}}</code> a <code>{{"."seller_email}}</code> jsou správně naplněné.<br>";
            echo "Email notifikace by obsahovala:<br>";
            echo "- Prodejce: <strong>{$notificationData['seller_name']}</strong><br>";
            echo "- Email: <strong>{$sellerEmail}</strong><br><br>";
            echo "Pokud je prodejce nastaven jako CC v šabloně, dostane kopii emailu.";
            echo "</div>";
        } else {
            echo "<div class='warning'>";
            echo "<strong>⚠️ VAROVÁNÍ</strong><br>";
            echo "Tato zakázka nemá přiřazeného prodejce.<br>";
            echo "Pokud byl vytvořen přes <code>novareklamace.php</code> bez přihlášení, ";
            echo "<code>created_by</code> je NULL.";
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='/admin.php' class='btn'>Zpět do admin</a>";
echo "<a href='/test_email_prijemci.php' class='btn'>Test email příjemců</a>";
echo "</div></body></html>";
?>
