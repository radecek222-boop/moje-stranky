<?php
/**
 * Test: Ověření načtení telefonu technika Milan Kolín
 *
 * Tento skript simuluje přesně to, co dělá send_contact_attempt_email.php
 * a ověří, že se správně načte telefon Milana Kolína.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test telefonu Milan Kolín</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 900px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 15px; border-radius: 5px;
                   margin: 15px 0; font-weight: bold; font-size: 1.1em; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 15px; border-radius: 5px;
                 margin: 15px 0; font-weight: bold; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px;
              overflow-x: auto; border-left: 4px solid #333; }
        .step { background: #f9f9f9; padding: 15px; margin: 10px 0;
                border-radius: 5px; border-left: 4px solid #333; }
        .result { font-size: 1.3em; padding: 20px; background: #000;
                  color: #0f0; border-radius: 5px; font-family: monospace;
                  text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🧪 Test načtení telefonu - Milan Kolín</h1>";

    echo "<div class='info'><strong>Tento test simuluje přesně to, co dělá API při odesílání SMS.</strong></div>";

    // KROK 1: Načíst Milana Kolína z DB
    echo "<div class='step'>";
    echo "<h3>KROK 1: Načíst údaje Milana Kolína</h3>";

    $userId = 'TCH20250001'; // Milan Kolín user_id

    $stmtUser = $pdo->prepare("
        SELECT id, user_id, name, email, phone
        FROM wgs_users
        WHERE id = :user_id OR user_id = :user_id
        LIMIT 1
    ");
    $stmtUser->execute(['user_id' => $userId]);
    $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userInfo) {
        echo "<div class='error'>❌ Milan Kolín (user_id: {$userId}) nenalezen v databázi!</div>";
        echo "</div></div></body></html>";
        exit;
    }

    echo "<pre>";
    echo "SQL dotaz:\n";
    echo "SELECT id, user_id, name, email, phone FROM wgs_users WHERE user_id = 'TCH20250001'\n\n";
    echo "Výsledek:\n";
    print_r($userInfo);
    echo "</pre>";
    echo "</div>";

    // KROK 2: Zpracovat telefon (stejně jako v opravené verzi API)
    echo "<div class='step'>";
    echo "<h3>KROK 2: Zpracování telefonu (opravená logika)</h3>";

    $technicianPhone = '+420 725 965 826'; // Výchozí firemní telefon
    echo "<p>Výchozí fallback telefon: <code>{$technicianPhone}</code></p>";

    if ($userInfo && !empty($userInfo['phone'])) {
        echo "<p>✅ Pole 'phone' má hodnotu: <code>" . htmlspecialchars($userInfo['phone']) . "</code></p>";

        $technicianPhone = $userInfo['phone'];
        echo "<p>Načteno z DB: <code>{$technicianPhone}</code></p>";

        // Přidat předvolbu +420 pokud tam není
        $technicianPhone = trim($technicianPhone);
        echo "<p>Po trim(): <code>{$technicianPhone}</code></p>";

        if (!preg_match('/^\+/', $technicianPhone)) {
            echo "<p>⚠️ Telefon nezačíná na '+', přidávám předvolbu +420...</p>";
            $technicianPhone = '+420 ' . ltrim($technicianPhone, '0');
            echo "<p>Po přidání předvolby: <code>{$technicianPhone}</code></p>";
        } else {
            echo "<p>✅ Telefon již má předvolbu</p>";
        }
    } else {
        echo "<p>❌ Pole 'phone' je prázdné, použije se fallback</p>";
    }

    echo "</div>";

    // KROK 3: Výsledek
    echo "<div class='step'>";
    echo "<h3>KROK 3: Finální výsledek</h3>";

    echo "<div class='result'>";
    echo "TELEFON PRO SMS: {$technicianPhone}";
    echo "</div>";

    if ($technicianPhone === '+420 725 965 826') {
        echo "<div class='error'>";
        echo "❌ PROBLÉM: Používá se fallback firemní telefon místo telefonu Milana Kolína!";
        echo "</div>";
    } else if ($technicianPhone === '+420 735084519') {
        echo "<div class='success'>";
        echo "✅ SPRÁVNĚ! Telefon Milana Kolína byl načten a správně zformátován!<br>";
        echo "Milan Kolín bude dostávat SMS na své vlastní číslo.";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "Načtený telefon: {$technicianPhone}<br>";
        echo "Očekávaný telefon: +420 735084519";
        echo "</div>";
    }

    echo "</div>";

    // KROK 4: Porovnání PŘED a PO opravě
    echo "<div class='step'>";
    echo "<h3>KROK 4: Porovnání před a po opravě</h3>";

    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #333; color: white;'>";
    echo "<th style='padding: 10px; border: 1px solid #ddd;'>Stav</th>";
    echo "<th style='padding: 10px; border: 1px solid #ddd;'>SQL dotaz</th>";
    echo "<th style='padding: 10px; border: 1px solid #ddd;'>Výsledek</th>";
    echo "</tr>";

    echo "<tr>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>PŘED opravou</strong></td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'><code>SELECT telefon, phone FROM wgs_users</code></td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd; background: #f8d7da; color: #721c24;'>❌ Chyba: sloupec 'telefon' neexistuje → použije se fallback +420 725 965 826</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>PO opravě</strong></td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'><code>SELECT phone FROM wgs_users</code></td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd; background: #d4edda; color: #155724;'>✅ Úspěch: načte 735084519 → zformátuje na +420 735084519</td>";
    echo "</tr>";

    echo "</table>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><a href='/admin.php' style='display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px;'>← Zpět do Admin panelu</a>";
echo "</div></body></html>";
?>
