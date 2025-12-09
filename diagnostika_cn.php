<?php
/**
 * Diagnostika: Kontrola propojení CN s reklamacemi
 * Pouze pro adminy
 */

require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika CN</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #0a0a0a; color: #fff; }
        .container { background: #1a1a1a; padding: 30px; border-radius: 10px; }
        h1 { color: #39ff14; border-bottom: 2px solid #39ff14; padding-bottom: 10px; }
        h2 { color: #ff9800; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #333; text-align: left; }
        th { background: #222; color: #39ff14; }
        tr:nth-child(even) { background: #1a1a1a; }
        tr:nth-child(odd) { background: #222; }
        .match { color: #39ff14; font-weight: bold; }
        .no-match { color: #ff4444; }
        .info { background: #333; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #39ff14;
               color: #000; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Diagnostika: Propojení CN s reklamacemi</h1>";

    // 1. Všechny nabídky se stavem odeslana/potvrzena
    echo "<h2>1. Cenové nabídky (stav: odeslana/potvrzena)</h2>";
    $stmt = $pdo->query("
        SELECT id, cislo_nabidky, zakaznik_jmeno, zakaznik_email, stav, vytvoreno_at, odeslano_at
        FROM wgs_nabidky
        WHERE stav IN ('potvrzena', 'odeslana')
        ORDER BY vytvoreno_at DESC
        LIMIT 20
    ");
    $nabidky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($nabidky)) {
        echo "<div class='info'>Žádné nabídky se stavem 'odeslana' nebo 'potvrzena'</div>";
    } else {
        echo "<table>
            <tr><th>ID</th><th>Číslo CN</th><th>Zákazník</th><th>Email v CN</th><th>Stav</th><th>Odesláno</th></tr>";
        foreach ($nabidky as $n) {
            echo "<tr>
                <td>{$n['id']}</td>
                <td>{$n['cislo_nabidky']}</td>
                <td>" . htmlspecialchars($n['zakaznik_jmeno']) . "</td>
                <td><strong>" . htmlspecialchars($n['zakaznik_email']) . "</strong></td>
                <td>{$n['stav']}</td>
                <td>" . ($n['odeslano_at'] ?? '-') . "</td>
            </tr>";
        }
        echo "</table>";
    }

    // 2. Emaily které vrací API endpoint
    echo "<h2>2. Emaily které vrací API (emaily_s_nabidkou)</h2>";
    $stmt = $pdo->query("
        SELECT DISTINCT LOWER(zakaznik_email) as email
        FROM wgs_nabidky
        WHERE stav IN ('potvrzena', 'odeslana')
        AND zakaznik_email IS NOT NULL
        AND zakaznik_email != ''
    ");
    $emaily = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($emaily)) {
        echo "<div class='info'>API nevrací žádné emaily</div>";
    } else {
        echo "<div class='info'>API vrací tyto emaily:<br><strong>" . implode('<br>', array_map('htmlspecialchars', $emaily)) . "</strong></div>";
    }

    // 3. Reklamace a jejich emaily
    echo "<h2>3. Reklamace a jejich emaily (posledních 20)</h2>";
    $stmt = $pdo->query("
        SELECT id, reklamace_id, jmeno, email, stav, created_at
        FROM wgs_reklamace
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>ID</th><th>Číslo reklamace</th><th>Zákazník</th><th>Email v reklamaci</th><th>Stav</th><th>Má CN?</th></tr>";
    foreach ($reklamace as $r) {
        $emailLower = strtolower(trim($r['email'] ?? ''));
        $maCN = in_array($emailLower, $emaily);
        $cnClass = $maCN ? 'match' : 'no-match';
        $cnText = $maCN ? 'ANO' : 'NE';

        echo "<tr>
            <td>{$r['id']}</td>
            <td>" . htmlspecialchars($r['reklamace_id'] ?? '-') . "</td>
            <td>" . htmlspecialchars($r['jmeno']) . "</td>
            <td><strong>" . htmlspecialchars($r['email'] ?? '(prázdný)') . "</strong></td>
            <td>{$r['stav']}</td>
            <td class='{$cnClass}'>{$cnText}</td>
        </tr>";
    }
    echo "</table>";

    // 4. Konkrétní kontrola - Bohumila Zikmundová (VŠECHNY reklamace)
    echo "<h2>4. VŠECHNY reklamace pro Zikmundovou</h2>";
    $stmt = $pdo->prepare("SELECT id, reklamace_id, jmeno, email, stav FROM wgs_reklamace WHERE jmeno LIKE ? ORDER BY id DESC");
    $stmt->execute(['%Zikmundová%']);
    $bohumila = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bohumila)) {
        echo "<div class='info'>Reklamace pro 'Zikmundová' nenalezena</div>";
    } else {
        echo "<table>
            <tr><th>ID</th><th>Číslo reklamace</th><th>Jméno</th><th>Email</th><th>Stav</th><th>Má CN?</th></tr>";
        foreach ($bohumila as $b) {
            $emailLower = strtolower(trim($b['email'] ?? ''));
            $maCN = in_array($emailLower, $emaily);
            $cnClass = $maCN ? 'match' : 'no-match';
            $cnText = $maCN ? 'ANO' : 'NE';
            echo "<tr>
                <td>{$b['id']}</td>
                <td><strong>" . htmlspecialchars($b['reklamace_id'] ?? '-') . "</strong></td>
                <td>" . htmlspecialchars($b['jmeno']) . "</td>
                <td>" . htmlspecialchars($b['email'] ?? '(prázdný)') . "</td>
                <td>{$b['stav']}</td>
                <td class='{$cnClass}'>{$cnText}</td>
            </tr>";
        }
        echo "</table>";
    }

    // 5. Kontrola nabídky pro Zikmundovou
    echo "<h2>5. Nabídky pro Zikmundovou</h2>";
    $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE zakaznik_jmeno LIKE ? ORDER BY vytvoreno_at DESC");
    $stmt->execute(['%Zikmundová%']);
    $nabidkyZik = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($nabidkyZik)) {
        echo "<div class='info'>Žádná nabídka pro 'Zikmundová' nenalezena</div>";
    } else {
        echo "<table>
            <tr><th>ID</th><th>Číslo CN</th><th>Email</th><th>Stav</th><th>Vytvořeno</th><th>Odesláno</th></tr>";
        foreach ($nabidkyZik as $n) {
            echo "<tr>
                <td>{$n['id']}</td>
                <td>{$n['cislo_nabidky']}</td>
                <td><strong>" . htmlspecialchars($n['zakaznik_email']) . "</strong></td>
                <td><strong>{$n['stav']}</strong></td>
                <td>{$n['vytvoreno_at']}</td>
                <td>" . ($n['odeslano_at'] ?? '-') . "</td>
            </tr>";
        }
        echo "</table>";
    }

    // 6. Test JavaScript API odpovědi
    echo "<h2>6. Test API odpovědi (co vidí JavaScript)</h2>";

    // Simulovat API call
    $apiUrl = '/api/nabidka_api.php?action=emaily_s_nabidkou';
    echo "<div class='info'>";
    echo "API URL: <code>{$apiUrl}</code><br><br>";

    // Zobrazit přesnou JSON odpověď
    $jsonOdpoved = json_encode(['status' => 'success', 'message' => 'Emaily načteny', 'data' => ['emaily' => $emaily]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "JSON odpověď kterou API vrací:<br>";
    echo "<pre style='background: #000; padding: 10px; border-radius: 5px; overflow-x: auto;'>" . htmlspecialchars($jsonOdpoved) . "</pre>";
    echo "</div>";

    // 7. Test porovnání emailů
    echo "<h2>7. Simulace JavaScript porovnání</h2>";
    $stmt = $pdo->query("SELECT id, email FROM wgs_reklamace ORDER BY id DESC LIMIT 10");
    $testReklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>
        <tr><th>ID</th><th>Email (raw)</th><th>Email (lowercase trim)</th><th>V seznamu CN?</th><th>Výsledek</th></tr>";
    foreach ($testReklamace as $t) {
        $rawEmail = $t['email'] ?? '';
        $processedEmail = strtolower(trim($rawEmail));
        $jeVSeznamu = in_array($processedEmail, $emaily);
        $vysledek = $jeVSeznamu ? 'ORANŽOVÁ' : 'normální';
        $trClass = $jeVSeznamu ? "style='background: #ff9800; color: #000;'" : "";

        echo "<tr {$trClass}>
            <td>{$t['id']}</td>
            <td><code>" . htmlspecialchars($rawEmail) . "</code></td>
            <td><code>" . htmlspecialchars($processedEmail) . "</code></td>
            <td>" . ($jeVSeznamu ? 'ANO' : 'NE') . "</td>
            <td><strong>{$vysledek}</strong></td>
        </tr>";
    }
    echo "</table>";

    echo "<br><a href='seznam.php' class='btn'>Zpět na seznam</a>";

} catch (Exception $e) {
    echo "<div style='color: #ff4444;'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
