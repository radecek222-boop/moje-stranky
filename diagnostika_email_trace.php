<?php
/**
 * TRACE: Kde se vzal email bzikmundova@gmail.com
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$pdo = getDbConnection();
$hledanyEmail = 'bzikmundova@gmail.com';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Trace: {$hledanyEmail}</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #eee; padding: 20px; }
        .box { background: #222; border: 1px solid #444; padding: 15px; margin: 10px 0; }
        h2 { color: #ff9800; }
        pre { background: #111; padding: 10px; }
        .found { color: #39ff14; font-weight: bold; }
        .notfound { color: #666; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
    </style>
</head>
<body>
<h1>Trace: Kde je email '{$hledanyEmail}'</h1>
";

// 1. V reklamacích
echo "<h2>1. wgs_reklamace</h2><div class='box'>";
$stmt = $pdo->prepare("SELECT id, reklamace_id, jmeno, email, created_at FROM wgs_reklamace WHERE LOWER(email) LIKE ?");
$stmt->execute(['%' . strtolower($hledanyEmail) . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows) {
    echo "<p class='found'>NALEZENO " . count($rows) . " záznamů:</p>";
    echo "<table><tr><th>ID</th><th>Reklamace ID</th><th>Jméno</th><th>Email</th><th>Vytvořeno</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['reklamace_id']}</td><td>" . htmlspecialchars($r['jmeno']) . "</td><td>" . htmlspecialchars($r['email']) . "</td><td>{$r['created_at']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='notfound'>Nenalezeno v reklamacích</p>";
}
echo "</div>";

// 2. V nabídkách
echo "<h2>2. wgs_nabidky</h2><div class='box'>";
$stmt = $pdo->prepare("SELECT id, cislo_nabidky, zakaznik_jmeno, zakaznik_email, stav, vytvoreno_at FROM wgs_nabidky WHERE LOWER(zakaznik_email) LIKE ?");
$stmt->execute(['%' . strtolower($hledanyEmail) . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows) {
    echo "<p class='found'>NALEZENO " . count($rows) . " záznamů:</p>";
    echo "<table><tr><th>ID</th><th>Číslo</th><th>Jméno</th><th>Email</th><th>Stav</th><th>Vytvořeno</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['cislo_nabidky']}</td><td>" . htmlspecialchars($r['zakaznik_jmeno']) . "</td><td>" . htmlspecialchars($r['zakaznik_email']) . "</td><td>{$r['stav']}</td><td>{$r['vytvoreno_at']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='notfound'>Nenalezeno v nabídkách</p>";
}
echo "</div>";

// 3. V uživatelích
echo "<h2>3. wgs_users</h2><div class='box'>";
$stmt = $pdo->prepare("SELECT user_id, name, email, role, created_at FROM wgs_users WHERE LOWER(email) LIKE ?");
$stmt->execute(['%' . strtolower($hledanyEmail) . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows) {
    echo "<p class='found'>NALEZENO " . count($rows) . " záznamů:</p>";
    echo "<table><tr><th>ID</th><th>Jméno</th><th>Email</th><th>Role</th><th>Vytvořeno</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>{$r['user_id']}</td><td>" . htmlspecialchars($r['name']) . "</td><td>" . htmlspecialchars($r['email']) . "</td><td>{$r['role']}</td><td>{$r['created_at']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='notfound'>Nenalezeno v uživatelích</p>";
}
echo "</div>";

// 4. VŠECHNY reklamace - posledních 20
echo "<h2>4. Posledních 20 reklamací (všechny emaily)</h2><div class='box'>";
$stmt = $pdo->query("SELECT id, reklamace_id, jmeno, email, stav, created_at FROM wgs_reklamace ORDER BY created_at DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>ID</th><th>Reklamace ID</th><th>Jméno</th><th>Email</th><th>Stav</th><th>Vytvořeno</th></tr>";
foreach ($rows as $r) {
    $highlight = (stripos($r['email'], $hledanyEmail) !== false) ? 'style="background:#333;"' : '';
    echo "<tr {$highlight}><td>{$r['id']}</td><td>{$r['reklamace_id']}</td><td>" . htmlspecialchars($r['jmeno']) . "</td><td>" . htmlspecialchars($r['email']) . "</td><td>{$r['stav']}</td><td>{$r['created_at']}</td></tr>";
}
echo "</table>";
echo "</div>";

// 5. VŠECHNY nabídky
echo "<h2>5. Všechny nabídky (všechny emaily)</h2><div class='box'>";
$stmt = $pdo->query("SELECT id, cislo_nabidky, zakaznik_jmeno, zakaznik_email, stav, vytvoreno_at FROM wgs_nabidky ORDER BY vytvoreno_at DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>ID</th><th>Číslo</th><th>Jméno</th><th>Email</th><th>Stav</th><th>Vytvořeno</th></tr>";
foreach ($rows as $r) {
    $highlight = (stripos($r['zakaznik_email'], $hledanyEmail) !== false) ? 'style="background:#333;"' : '';
    echo "<tr {$highlight}><td>{$r['id']}</td><td>{$r['cislo_nabidky']}</td><td>" . htmlspecialchars($r['zakaznik_jmeno']) . "</td><td>" . htmlspecialchars($r['zakaznik_email']) . "</td><td>{$r['stav']}</td><td>{$r['vytvoreno_at']}</td></tr>";
}
echo "</table>";
echo "</div>";

// 6. Reklamace ID 87 konkrétně
echo "<h2>6. Reklamace ID 87 (konkrétně)</h2><div class='box'>";
$stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE id = 87");
$stmt->execute();
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if ($r) {
    echo "<pre>" . print_r($r, true) . "</pre>";
} else {
    echo "<p class='notfound'>Reklamace ID 87 neexistuje</p>";
}
echo "</div>";

echo "</body></html>";
?>
