<?php
/**
 * Diagnostika: Zkontrolovat telefon technika Milan Kolín
 *
 * Tento skript zobrazí, jaký telefon má Milan Kolín uložený v databázi
 * a zda se správně načítá při odesílání SMS.
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
    <title>Diagnostika telefonu - Milan Kolín</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .success { background: #e5e5e5; border: 1px solid #999;
                   color: #333; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f0f0f0; border: 1px solid #666;
                 color: #333; padding: 12px; border-radius: 5px;
                 margin: 10px 0; font-weight: bold; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #f9f9f9; border: 1px solid #ccc;
                color: #333; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Diagnostika telefonu - Milan Kolín</h1>";

    // 1. Najít Milana Kolína v databázi
    echo "<h3>🔍 Hledám Milana Kolína v databázi...</h3>";

    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            name,
            email,
            telefon,
            phone,
            role,
            is_active,
            last_login
        FROM wgs_users
        WHERE name LIKE '%Milan%' AND name LIKE '%Kolín%'
        OR name LIKE '%Milan%' AND name LIKE '%Kolin%'
        OR email LIKE '%milan%'
    ");
    $stmt->execute();
    $milani = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($milani) === 0) {
        echo "<div class='error'>❌ Milan Kolín nebyl nalezen v tabulce wgs_users!</div>";
        echo "<div class='warning'><strong>ŘEŠENÍ:</strong><br>";
        echo "1. Zkontrolujte, zda Milan Kolín má účet v systému<br>";
        echo "2. Možná je jméno zadané jinak (např. 'M. Kolín' nebo 'Milan K.')<br>";
        echo "3. Zkontrolujte email, kterým se Milan přihlašuje<br>";
        echo "</div>";

        // Zobrazit všechny uživatele pro kontrolu
        echo "<h3>📋 Všichni uživatelé v systému:</h3>";
        $stmtAll = $pdo->query("SELECT id, user_id, name, email, telefon, phone, role FROM wgs_users ORDER BY name");
        $vsichni = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>ID</th><th>User ID</th><th>Jméno</th><th>Email</th><th>Telefon</th><th>Phone</th><th>Role</th></tr>";
        foreach ($vsichni as $uzivatel) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($uzivatel['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($uzivatel['user_id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($uzivatel['name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($uzivatel['email'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($uzivatel['telefon'] ?? '<em>prázdné</em>') . "</td>";
            echo "<td>" . htmlspecialchars($uzivatel['phone'] ?? '<em>prázdné</em>') . "</td>";
            echo "<td>" . htmlspecialchars($uzivatel['role'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

    } else {
        echo "<div class='success'>✅ Nalezeno " . count($milani) . " záznamů</div>";

        foreach ($milani as $milan) {
            echo "<h3>👤 Údaje o uživateli:</h3>";
            echo "<table>";
            echo "<tr><th>Pole</th><th>Hodnota</th><th>Status</th></tr>";

            echo "<tr><td><strong>ID</strong></td><td>" . htmlspecialchars($milan['id'] ?? 'N/A') . "</td><td>—</td></tr>";
            echo "<tr><td><strong>User ID</strong></td><td>" . htmlspecialchars($milan['user_id'] ?? 'N/A') . "</td><td>—</td></tr>";
            echo "<tr><td><strong>Jméno</strong></td><td>" . htmlspecialchars($milan['name'] ?? 'N/A') . "</td><td>—</td></tr>";
            echo "<tr><td><strong>Email</strong></td><td>" . htmlspecialchars($milan['email'] ?? 'N/A') . "</td><td>—</td></tr>";

            // KRITICKÉ: Telefon a Phone sloupce
            $telefon = $milan['telefon'] ?? '';
            $phone = $milan['phone'] ?? '';

            $telefonStatus = !empty($telefon) ? '✅ Vyplněno' : '❌ PRÁZDNÉ';
            $phoneStatus = !empty($phone) ? '✅ Vyplněno' : '❌ PRÁZDNÉ';

            echo "<tr><td><strong>telefon</strong> (priorita 1)</td><td>" .
                 ($telefon ? htmlspecialchars($telefon) : '<em style="color: red;">PRÁZDNÉ</em>') .
                 "</td><td>" . $telefonStatus . "</td></tr>";

            echo "<tr><td><strong>phone</strong> (priorita 2)</td><td>" .
                 ($phone ? htmlspecialchars($phone) : '<em style="color: red;">PRÁZDNÉ</em>') .
                 "</td><td>" . $phoneStatus . "</td></tr>";

            echo "<tr><td><strong>Role</strong></td><td>" . htmlspecialchars($milan['role'] ?? 'N/A') . "</td><td>—</td></tr>";
            echo "<tr><td><strong>Aktivní</strong></td><td>" . ($milan['is_active'] ? 'Ano' : 'Ne') . "</td><td>—</td></tr>";
            echo "<tr><td><strong>Poslední login</strong></td><td>" . htmlspecialchars($milan['last_login'] ?? 'Nikdy') . "</td><td>—</td></tr>";

            echo "</table>";

            // ANALÝZA PROBLÉMU
            echo "<h3>🔍 Analýza:</h3>";

            if (empty($telefon) && empty($phone)) {
                echo "<div class='error'>";
                echo "<strong>❌ PROBLÉM IDENTIFIKOVÁN!</strong><br>";
                echo "Uživatel Milan Kolín nemá vyplněný telefon v žádném ze sloupců (telefon, phone).<br>";
                echo "Proto systém použije <strong>fallback firemní telefon: +420 725 965 826</strong>";
                echo "</div>";

                echo "<div class='warning'>";
                echo "<strong>ŘEŠENÍ:</strong><br>";
                echo "1. Otevřete Admin panel → Uživatelé<br>";
                echo "2. Najděte uživatele 'Milan Kolín'<br>";
                echo "3. Vyplňte jeho telefonní číslo do pole 'telefon' nebo 'phone'<br>";
                echo "4. Uložte změny<br>";
                echo "5. Zkuste znovu odeslat SMS<br>";
                echo "</div>";

            } else if (!empty($telefon)) {
                echo "<div class='success'>";
                echo "<strong>✅ Sloupec 'telefon' je vyplněný</strong><br>";
                echo "Tento telefon by se měl použít v SMS: <strong>" . htmlspecialchars($telefon) . "</strong>";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>📋 Ověření:</strong><br>";
                echo "• Pokud SMS obsahuje jiný telefon než výše uvedený, kontaktujte vývojáře<br>";
                echo "• Systém používá prioritu: 1) telefon → 2) phone → 3) fallback (+420 725 965 826)<br>";
                echo "</div>";

            } else if (!empty($phone)) {
                echo "<div class='success'>";
                echo "<strong>✅ Sloupec 'phone' je vyplněný</strong><br>";
                echo "Tento telefon by se měl použít v SMS: <strong>" . htmlspecialchars($phone) . "</strong>";
                echo "</div>";

                echo "<div class='warning'>";
                echo "<strong>💡 DOPORUČENÍ:</strong><br>";
                echo "Pro lepší kompatibilitu doporučujeme vyplnit telefon také do sloupce 'telefon' (má vyšší prioritu).<br>";
                echo "</div>";
            }

            // Simulace načtení telefonu (jako v send_contact_attempt_email.php)
            echo "<h3>⚙️ Simulace načtení telefonu (stejně jako v API):</h3>";
            echo "<pre>";
            echo "// Kód z send_contact_attempt_email.php:\n";
            echo "\$technicianPhone = '+420 725 965 826'; // Výchozí firemní telefon\n\n";

            $userId = $milan['user_id'] ?? $milan['id'];
            echo "// Dotaz do databáze pro user_id = {$userId}:\n";
            echo "SELECT telefon, phone FROM wgs_users WHERE id = {$userId} OR user_id = {$userId}\n\n";

            echo "// Výsledek dotazu:\n";
            echo "telefon = " . ($telefon ? "'{$telefon}'" : "NULL") . "\n";
            echo "phone = " . ($phone ? "'{$phone}'" : "NULL") . "\n\n";

            echo "// Priorita načtení:\n";
            $vyslednyTelefon = $telefon ?: ($phone ?: '+420 725 965 826');
            echo "\$technicianPhone = \$userInfo['telefon'] ?: \$userInfo['phone'] ?: \$technicianPhone;\n";
            echo "// Výsledek: \$technicianPhone = '{$vyslednyTelefon}'\n";
            echo "</pre>";

            if ($vyslednyTelefon === '+420 725 965 826') {
                echo "<div class='error'>";
                echo "<strong>❌ POTVRZENO:</strong> Systém používá fallback firemní telefon, protože sloupce 'telefon' a 'phone' jsou prázdné!";
                echo "</div>";
            } else {
                echo "<div class='success'>";
                echo "<strong>✅ SPRÁVNĚ:</strong> Systém použije telefon: <strong>{$vyslednyTelefon}</strong>";
                echo "</div>";
            }
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><a href='/admin.php' class='btn'>← Zpět do Admin panelu</a>";
echo "</div></body></html>";
?>
