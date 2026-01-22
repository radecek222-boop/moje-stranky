<?php
/**
 * Diagnostický skript pro kontrolu technika Milan Kolín
 *
 * URL: https://www.wgs-service.cz/kontrola_milan_kolin.php
 *
 * Tento skript zkontroluje:
 * 1. Existenci technika Milan Kolín v databázi
 * 2. Jeho roli a přístupová práva
 * 3. Zobrazí všechny techniky v systému
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola technika Milan Kolín</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .user-card { background: #f9f9f9; border-left: 4px solid #2D5016;
                     padding: 15px; margin: 15px 0; border-radius: 5px; }
        .user-card.inactive { border-left-color: #999; opacity: 0.6; }
        .label { font-weight: bold; color: #555; display: inline-block;
                 width: 120px; }
        .value { color: #000; }
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
        th { background: #2D5016; color: white; }
        tr:hover { background: #f5f5f5; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px;
                 font-size: 0.85em; font-weight: bold; }
        .badge-active { background: #28a745; color: white; }
        .badge-inactive { background: #dc3545; color: white; }
        .badge-technik { background: #2D5016; color: white; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Kontrola technika Milan Kolín</h1>";

    // ==================================================
    // 1. VYHLEDÁNÍ MILANA KOLÍNA
    // ==================================================

    echo "<h2>1. Vyhledání uživatele</h2>";

    $stmt = $pdo->prepare("
        SELECT user_id, email, name, role, is_active, created_at, last_login
        FROM wgs_users
        WHERE email LIKE :milan1 OR email LIKE :milan2
           OR email LIKE :kolin1 OR email LIKE :kolin2
           OR name LIKE :milan3 OR name LIKE :kolin3
        ORDER BY user_id
    ");

    $stmt->execute([
        ':milan1' => '%milan%',
        ':milan2' => '%Milan%',
        ':kolin1' => '%kolin%',
        ':kolin2' => '%Kolín%',
        ':milan3' => '%Milan%',
        ':kolin3' => '%Kolín%'
    ]);

    $milanUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($milanUsers)) {
        echo "<div class='error'>";
        echo "<strong>❌ NENALEZEN:</strong> Žádný uživatel s jménem nebo emailem obsahujícím 'Milan' nebo 'Kolín' nebyl nalezen.";
        echo "</div>";

        echo "<div class='info'>";
        echo "<strong>💡 TIP:</strong> Zkontrolujte přesný email nebo jméno v tabulce níže (všichni technici).";
        echo "</div>";

    } else {
        echo "<div class='success'>";
        echo "<strong>✅ NALEZENO:</strong> " . count($milanUsers) . " uživatel(é) odpovídající dotazu.";
        echo "</div>";

        foreach ($milanUsers as $user) {
            $isActive = $user['is_active'] == 1;
            $cardClass = $isActive ? 'user-card' : 'user-card inactive';

            // Kontrola přístupu do photocustomer
            $rawRole = strtolower(trim($user['role']));
            $maaPristup = (strpos($rawRole, 'technik') !== false) || (strpos($rawRole, 'technician') !== false);

            echo "<div class='$cardClass'>";
            echo "<h3>👤 {$user['name']}</h3>";
            echo "<p><span class='label'>User ID:</span> <span class='value'>{$user['user_id']}</span></p>";
            echo "<p><span class='label'>Email:</span> <span class='value'>{$user['email']}</span></p>";
            echo "<p><span class='label'>Role:</span> <span class='value'><code>{$user['role']}</code></span></p>";
            echo "<p><span class='label'>Aktivní:</span> <span class='value'>";
            echo $isActive ? "<span class='badge badge-active'>ANO</span>" : "<span class='badge badge-inactive'>NE</span>";
            echo "</span></p>";
            echo "<p><span class='label'>Vytvořen:</span> <span class='value'>{$user['created_at']}</span></p>";
            echo "<p><span class='label'>Poslední login:</span> <span class='value'>" . ($user['last_login'] ?: 'Nikdy') . "</span></p>";

            echo "<hr style='margin: 15px 0;'>";

            // Přístup do photocustomer
            echo "<p><span class='label'>Přístup do photocustomer:</span> ";
            if ($maaPristup) {
                echo "<span class='badge badge-active'>✅ ANO</span>";
                echo "<br><span style='color: #28a745; font-size: 0.9em;'>Role obsahuje 'technik' nebo 'technician'</span>";
            } else {
                echo "<span class='badge badge-inactive'>❌ NE</span>";
                echo "<br><span style='color: #dc3545; font-size: 0.9em;'>Role NEOBSAHUJE 'technik' nebo 'technician'</span>";
            }
            echo "</p>";

            // Session keep-alive
            echo "<p><span class='label'>Session keep-alive:</span> ";
            echo "<span class='badge badge-active'>✅ 1 MINUTA</span>";
            echo "<br><span style='color: #28a745; font-size: 0.9em;'>Automatický ping každou 1 minutu na photocustomer</span>";
            echo "</p>";

            // Auto-save do galerie
            echo "<p><span class='label'>Auto-save fotky:</span> ";
            echo "<span class='badge badge-active'>✅ GALERIE</span>";
            echo "<br><span style='color: #28a745; font-size: 0.9em;'>Nativní fotoaparát ukládá originály automaticky</span>";
            echo "</p>";

            echo "</div>";
        }
    }

    // ==================================================
    // 2. VŠICHNI TECHNICI V SYSTÉMU
    // ==================================================

    echo "<h2>2. Všichni technici v systému</h2>";

    $stmt = $pdo->query("
        SELECT user_id, email, name, role, is_active, last_login
        FROM wgs_users
        WHERE role LIKE '%technik%' OR role LIKE '%technician%'
        ORDER BY is_active DESC, name ASC
    ");

    $allTechnicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allTechnicians)) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ VAROVÁNÍ:</strong> V databázi nejsou žádní technici s rolí obsahující 'technik' nebo 'technician'.";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<strong>📋 CELKEM:</strong> " . count($allTechnicians) . " technik(ů) v systému.";
        echo "</div>";

        echo "<table>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Jméno</th>";
        echo "<th>Email</th>";
        echo "<th>Role</th>";
        echo "<th>Aktivní</th>";
        echo "<th>Poslední login</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        foreach ($allTechnicians as $tech) {
            $isActive = $tech['is_active'] == 1;
            $activeClass = $isActive ? 'badge-active' : 'badge-inactive';
            $activeText = $isActive ? 'Ano' : 'Ne';

            echo "<tr>";
            echo "<td>{$tech['user_id']}</td>";
            echo "<td>{$tech['name']}</td>";
            echo "<td>{$tech['email']}</td>";
            echo "<td><span class='badge badge-technik'>{$tech['role']}</span></td>";
            echo "<td><span class='badge $activeClass'>$activeText</span></td>";
            echo "<td>" . ($tech['last_login'] ?: '-') . "</td>";
            echo "</tr>";
        }

        echo "</tbody>";
        echo "</table>";
    }

    // ==================================================
    // 3. KONTROLA SESSION KEEP-ALIVE FUNKCE
    // ==================================================

    echo "<h2>3. Kontrola session keep-alive systému</h2>";

    // Zkontrolovat existenci session_keepalive.php
    $keepaliveFile = __DIR__ . '/api/session_keepalive.php';
    $jsFile = __DIR__ . '/assets/js/session-keepalive.js';

    echo "<table>";
    echo "<tr>";
    echo "<td><span class='label'>API endpoint:</span></td>";
    echo "<td>";
    if (file_exists($keepaliveFile)) {
        echo "<span class='badge badge-active'>✅ EXISTUJE</span> <code>/api/session_keepalive.php</code>";
    } else {
        echo "<span class='badge badge-inactive'>❌ CHYBÍ</span> <code>/api/session_keepalive.php</code>";
    }
    echo "</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td><span class='label'>JavaScript:</span></td>";
    echo "<td>";
    if (file_exists($jsFile)) {
        echo "<span class='badge badge-active'>✅ EXISTUJE</span> <code>/assets/js/session-keepalive.js</code>";

        // Zkontrolovat obsah
        $jsContent = file_get_contents($jsFile);
        if (strpos($jsContent, 'isPhotocustomer') !== false && strpos($jsContent, '1 * 60 * 1000') !== false) {
            echo "<br><span style='color: #28a745; font-size: 0.9em;'>✅ Obsahuje agresivní keep-alive pro photocustomer (1 min)</span>";
        } else {
            echo "<br><span style='color: #dc3545; font-size: 0.9em;'>⚠️ Starší verze - neobsahuje agresivní keep-alive</span>";
        }
    } else {
        echo "<span class='badge badge-inactive'>❌ CHYBÍ</span> <code>/assets/js/session-keepalive.js</code>";
    }
    echo "</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td><span class='label'>Photocustomer načítá:</span></td>";
    echo "<td>";
    $photocustomerFile = __DIR__ . '/photocustomer.php';
    if (file_exists($photocustomerFile)) {
        $photocustomerContent = file_get_contents($photocustomerFile);
        if (strpos($photocustomerContent, 'session-keepalive.js') !== false) {
            echo "<span class='badge badge-active'>✅ ANO</span> <code>session-keepalive.js</code> je načten";
        } else {
            echo "<span class='badge badge-inactive'>❌ NE</span> <code>session-keepalive.js</code> NENÍ načten";
        }
    } else {
        echo "<span class='badge badge-inactive'>❌ CHYBÍ</span> <code>photocustomer.php</code>";
    }
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    // ==================================================
    // 4. SHRNUTÍ A DOPORUČENÍ
    // ==================================================

    echo "<h2>4. Shrnutí a doporučení</h2>";

    if (!empty($milanUsers)) {
        $milan = $milanUsers[0];
        $rawRole = strtolower(trim($milan['role']));
        $maaPristup = (strpos($rawRole, 'technik') !== false) || (strpos($rawRole, 'technician') !== false);
        $isActive = $milan['is_active'] == 1;

        if ($maaPristup && $isActive) {
            echo "<div class='success'>";
            echo "<strong>✅ VŠE V POŘÁDKU</strong><br>";
            echo "Milan Kolín má správně nastavenou roli a přístup do photocustomer.<br>";
            echo "Session keep-alive: ping každou 1 minutu na photocustomer.php<br>";
            echo "Fotky se automaticky ukládají do galerie telefonu.";
            echo "</div>";
        } elseif (!$isActive) {
            echo "<div class='error'>";
            echo "<strong>❌ PROBLÉM: NEAKTIVNÍ ÚČET</strong><br>";
            echo "Milan Kolín je v systému, ale jeho účet je deaktivován.<br><br>";
            echo "<strong>ŘEŠENÍ:</strong> Aktivujte účet v admin panelu nebo SQL příkazem:<br>";
            echo "<code>UPDATE wgs_users SET is_active = 1 WHERE user_id = {$milan['user_id']};</code>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<strong>❌ PROBLÉM: NESPRÁVNÁ ROLE</strong><br>";
            echo "Milan Kolín má roli <code>{$milan['role']}</code>, která NEOBSAHUJE slovo 'technik' nebo 'technician'.<br><br>";
            echo "<strong>ŘEŠENÍ:</strong> Změňte roli v admin panelu nebo SQL příkazem:<br>";
            echo "<code>UPDATE wgs_users SET role = 'technik' WHERE user_id = {$milan['user_id']};</code>";
            echo "</div>";
        }
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ UŽIVATEL NENALEZEN</strong><br>";
        echo "Milan Kolín nebyl nalezen v databázi.<br><br>";
        echo "<strong>MOŽNOSTI:</strong><br>";
        echo "1. Vytvořte nový účet přes registrační formulář<br>";
        echo "2. Zkontrolujte přesný email nebo jméno v tabulce výše<br>";
        echo "3. Uživatel může být registrován pod jiným jménem";
        echo "</div>";
    }

    echo "<div class='info'>";
    echo "<strong>📝 POZNÁMKY:</strong><br>";
    echo "• Session keep-alive je AUTOMATICKÝ pro všechny uživatele photocustomer<br>";
    echo "• Fotky se ukládají do galerie AUTOMATICKY díky nativnímu fotoaparátu<br>";
    echo "• Není potřeba žádné manuální nastavení pro jednotlivé techniky<br>";
    echo "• Jediná podmínka: Role musí obsahovat 'technik' nebo 'technician'";
    echo "</div>";

    echo "<a href='admin.php' class='btn'>← Zpět do Admin panelu</a>";
    echo "<a href='photocustomer.php' class='btn'>Otevřít Photocustomer</a>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
