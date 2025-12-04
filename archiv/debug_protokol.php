<?php
/**
 * Debug skript pro protokol.php
 *
 * Zobrazí všechny informace potřebné pro diagnostiku problému načítání dat
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze přihlášení uživatelé
$isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
if (!$isLoggedIn) {
    die("PŘÍSTUP ODEPŘEN: Pouze přihlášení uživatelé.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Debug Protokol.php</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1400px; margin: 20px auto; padding: 20px;
               background: #1e1e1e; color: #d4d4d4; }
        .container { background: #252526; padding: 20px; border-radius: 8px; }
        h1, h2 { color: #4ec9b0; }
        .success { background: #1e5a1e; border-left: 3px solid #4ec9b0; padding: 12px; margin: 10px 0; }
        .error { background: #5a1e1e; border-left: 3px solid #f48771; padding: 12px; margin: 10px 0; }
        .warning { background: #5a4e1e; border-left: 3px solid #dcdcaa; padding: 12px; margin: 10px 0; }
        .info { background: #1e3a5a; border-left: 3px solid #4fc1ff; padding: 12px; margin: 10px 0; }
        code { background: #3c3c3c; padding: 3px 8px; border-radius: 3px; color: #ce9178; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #3e3e42; padding: 10px; text-align: left; }
        th { background: #2d2d30; color: #4ec9b0; }
        .btn { display: inline-block; padding: 10px 20px; background: #0e639c;
               color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #1177bb; }
    </style>
    <script>
        // JavaScript pro čtení localStorage
        window.addEventListener('DOMContentLoaded', function() {
            const currentCustomerData = localStorage.getItem('currentCustomer');
            const container = document.getElementById('localStorageData');

            if (currentCustomerData) {
                try {
                    const data = JSON.parse(currentCustomerData);
                    let html = '<div class=\"success\"><strong>✅ localStorage obsahuje currentCustomer</strong></div>';
                    html += '<table><tr><th>Klíč</th><th>Hodnota</th></tr>';

                    for (const [key, value] of Object.entries(data)) {
                        html += `<tr><td><code>\${key}</code></td><td>\${value ?? 'NULL'}</td></tr>`;
                    }

                    html += '</table>';
                    container.innerHTML = html;
                } catch (e) {
                    container.innerHTML = '<div class=\"error\">❌ Chyba parsování localStorage: ' + e.message + '</div>';
                }
            } else {
                container.innerHTML = '<div class=\"warning\">⚠️ localStorage neobsahuje currentCustomer</div>';
            }
        });
    </script>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Debug Protokol.php - Diagnostika načítání dat</h1>";

// 1. URL PARAMETRY
echo "<h2>1. URL Parametry</h2>";
$urlId = $_GET['id'] ?? null;

if ($urlId) {
    echo "<div class='success'><strong>✅ Parametr ?id= je přítomen</strong></div>";
    echo "<table>";
    echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
    echo "<tr><td><code>id</code></td><td><strong>" . htmlspecialchars($urlId) . "</strong></td></tr>";
    echo "<tr><td>Délka</td><td>" . strlen($urlId) . " znaků</td></tr>";
    echo "<tr><td>Typ</td><td>" . gettype($urlId) . "</td></tr>";
    echo "</table>";
} else {
    echo "<div class='warning'>⚠️ Parametr <code>?id=</code> CHYBÍ v URL</div>";
    echo "<div class='info'>";
    echo "<strong>To je normální, když:</strong><br>";
    echo "- Otevřete protokol.php přímo (bez ID) - vytváří se nový protokol<br>";
    echo "- Přejdete z menu nebo zpět z jiné stránky<br><br>";
    echo "<strong>To je problém, když:</strong><br>";
    echo "- Kliknete na 'Odeslat do protokolu' z photocustomer.php a nevidíte data";
    echo "</div>";
}

// 2. SESSION DATA
echo "<h2>2. Session Data</h2>";
echo "<table>";
echo "<tr><th>Session klíč</th><th>Hodnota</th></tr>";
$sessionKeys = ['user_id', 'user_name', 'is_admin', 'role', 'email'];
foreach ($sessionKeys as $key) {
    $value = $_SESSION[$key] ?? 'NULL';
    echo "<tr><td><code>{$key}</code></td><td>" . htmlspecialchars($value) . "</td></tr>";
}
echo "</table>";

// 3. SQL DOTAZ - POKUD JE ID
if ($urlId) {
    echo "<h2>3. SQL Dotaz</h2>";

    try {
        $pdo = getDbConnection();

        $lookupValue = trim($urlId);

        echo "<div class='info'>";
        echo "<strong>Hledám záznam pro:</strong> <code>" . htmlspecialchars($lookupValue) . "</code>";
        echo "</div>";

        $stmt = $pdo->prepare(
            "SELECT r.*, u.name as created_by_name
             FROM wgs_reklamace r
             LEFT JOIN wgs_users u ON r.created_by = u.id
             WHERE r.reklamace_id = :value OR r.cislo = :value OR r.id = :value
             LIMIT 1"
        );
        $stmt->execute([':value' => $lookupValue]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            echo "<div class='success'><strong>✅ ZÁZNAM NALEZEN V DATABÁZI!</strong></div>";
            echo "<table>";
            echo "<tr><th>Sloupec</th><th>Hodnota</th></tr>";

            $duleziteSloupce = ['id', 'reklamace_id', 'cislo', 'jmeno', 'telefon', 'email', 'adresa', 'model', 'popis_problemu', 'created_by', 'created_by_role'];

            foreach ($duleziteSloupce as $sloupec) {
                if (isset($record[$sloupec])) {
                    $hodnota = $record[$sloupec] ?? 'NULL';
                    echo "<tr><td><code>{$sloupec}</code></td><td>" . htmlspecialchars($hodnota) . "</td></tr>";
                }
            }
            echo "</table>";

            echo "<div class='success'>";
            echo "<strong>✅ PROTOKOL.PHP BY MĚL NAČÍST TATO DATA</strong><br>";
            echo "Element <code>&lt;script id=\"initialReklamaceData\"&gt;</code> by měl být vytvořen.";
            echo "</div>";

        } else {
            echo "<div class='error'><strong>❌ ZÁZNAM NENALEZEN V DATABÁZI!</strong></div>";
            echo "<div class='warning'>";
            echo "SQL dotaz nenašel žádný záznam pro hodnotu: <code>" . htmlspecialchars($lookupValue) . "</code><br><br>";
            echo "<strong>Možné příčiny:</strong><br>";
            echo "1. ID neexistuje v databázi (zkontrolujte seznam.php)<br>";
            echo "2. ID má jiný formát (mezery, speciální znaky)<br>";
            echo "3. Záznam byl smazán<br><br>";
            echo "<strong>Co udělat:</strong><br>";
            echo "Zkuste otevřít: <a href='zkontroluj_zaznam.php?id=" . urlencode($lookupValue) . "' class='btn'>Zkontrolovat záznam</a>";
            echo "</div>";
        }

    } catch (Exception $e) {
        echo "<div class='error'><strong>❌ CHYBA DATABÁZE:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// 4. LOCALSTORAGE (JavaScript)
echo "<h2>4. LocalStorage (currentCustomer)</h2>";
echo "<div id='localStorageData'><div class='info'>Načítám...</div></div>";

// 5. DOPORUČENÍ
echo "<h2>5. Doporučené kroky</h2>";

if (!$urlId) {
    echo "<div class='warning'>";
    echo "<strong>⚠️ PROTOKOL.PHP BYL OTEVŘEN BEZ ID</strong><br><br>";
    echo "<strong>Zkuste:</strong><br>";
    echo "1. Přejít na <a href='seznam.php' class='btn'>seznam.php</a><br>";
    echo "2. Kliknout na zakázku<br>";
    echo "3. Kliknout na 'Fotodokumentace'<br>";
    echo "4. Kliknout na 'Odeslat do protokolu'<br>";
    echo "5. Pak se vraťte sem a obnovte stránku";
    echo "</div>";
} else {
    echo "<div class='info'>";
    echo "<strong>ℹ️ DIAGNOSTIKA DOKONČENA</strong><br><br>";
    echo "Nyní otevřete skutečnou stránku protokol.php:<br>";
    echo "<a href='protokol.php?id=" . urlencode($urlId) . "' class='btn' target='_blank'>Otevřít protokol.php</a><br><br>";
    echo "Pak zkontrolujte JavaScript konzoli (F12) a podívejte se, jestli se zobrazí chyba 'initialReklamaceData NOT FOUND'.";
    echo "</div>";
}

// 6. LOGY
echo "<h2>6. PHP Error Log</h2>";
echo "<a href='zobraz_logy.php' class='btn' target='_blank'>Zobrazit logy</a>";

echo "</div></body></html>";
?>
