<?php
/**
 * Diagnostika: Kontrola hodnot assigned_to v reklamacích
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

$pdo = getDbConnection();

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: Technici a assigned_to</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Diagnostika: Technici a assigned_to</h1>";

// 1. Seznam techniků z wgs_users
echo "<h2>1. Technici v tabulce wgs_users</h2>";
$stmtTechnici = $pdo->query("SELECT id, user_id, name, role, is_active FROM wgs_users WHERE role = 'technik' ORDER BY name");
$technici = $stmtTechnici->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>id (numeric)</th><th>user_id (textový)</th><th>name</th><th>role</th><th>is_active</th></tr>";
foreach ($technici as $t) {
    echo "<tr><td>{$t['id']}</td><td>{$t['user_id']}</td><td>{$t['name']}</td><td>{$t['role']}</td><td>{$t['is_active']}</td></tr>";
}
echo "</table>";

// 2. Unikátní hodnoty assigned_to v reklamacích
echo "<h2>2. Unikátní hodnoty assigned_to v wgs_reklamace</h2>";
$stmtAssigned = $pdo->query("
    SELECT DISTINCT
        r.assigned_to,
        (SELECT u.name FROM wgs_users u WHERE u.id = r.assigned_to LIMIT 1) as match_by_id,
        (SELECT u.name FROM wgs_users u WHERE u.user_id = r.assigned_to LIMIT 1) as match_by_user_id,
        COUNT(*) as pocet
    FROM wgs_reklamace r
    WHERE r.assigned_to IS NOT NULL AND r.assigned_to != ''
    GROUP BY r.assigned_to
    ORDER BY pocet DESC
");
$assignedValues = $stmtAssigned->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>assigned_to hodnota</th><th>Match by id</th><th>Match by user_id</th><th>Počet záznamů</th></tr>";
foreach ($assignedValues as $av) {
    $matchId = $av['match_by_id'] ?? '<span style=\"color:red\">NENALEZENO</span>';
    $matchUserId = $av['match_by_user_id'] ?? '<span style=\"color:red\">NENALEZENO</span>';
    echo "<tr><td><code>{$av['assigned_to']}</code></td><td>{$matchId}</td><td>{$matchUserId}</td><td>{$av['pocet']}</td></tr>";
}
echo "</table>";

// 3. Test filtru pro konkrétního technika
if (!empty($technici)) {
    $prvniTechnik = $technici[0];
    echo "<h2>3. Test filtru pro technika: {$prvniTechnik['name']}</h2>";
    echo "<div class='info'>";
    echo "<strong>Testujeme:</strong><br>";
    echo "- id: <code>{$prvniTechnik['id']}</code><br>";
    echo "- user_id: <code>{$prvniTechnik['user_id']}</code>";
    echo "</div>";

    // Test s id
    $stmtTestId = $pdo->prepare("SELECT COUNT(*) as pocet FROM wgs_reklamace WHERE assigned_to = :id");
    $stmtTestId->execute([':id' => $prvniTechnik['id']]);
    $pocetId = $stmtTestId->fetch(PDO::FETCH_ASSOC)['pocet'];

    // Test s user_id
    $stmtTestUserId = $pdo->prepare("SELECT COUNT(*) as pocet FROM wgs_reklamace WHERE assigned_to = :user_id");
    $stmtTestUserId->execute([':user_id' => $prvniTechnik['user_id']]);
    $pocetUserId = $stmtTestUserId->fetch(PDO::FETCH_ASSOC)['pocet'];

    // Test s id jako string
    $stmtTestIdStr = $pdo->prepare("SELECT COUNT(*) as pocet FROM wgs_reklamace WHERE assigned_to = :id_str");
    $stmtTestIdStr->execute([':id_str' => (string)$prvniTechnik['id']]);
    $pocetIdStr = $stmtTestIdStr->fetch(PDO::FETCH_ASSOC)['pocet'];

    echo "<table>";
    echo "<tr><th>Podmínka</th><th>Počet nalezených</th></tr>";
    echo "<tr><td>assigned_to = {$prvniTechnik['id']} (jako INT)</td><td>{$pocetId}</td></tr>";
    echo "<tr><td>assigned_to = '{$prvniTechnik['id']}' (jako STRING)</td><td>{$pocetIdStr}</td></tr>";
    echo "<tr><td>assigned_to = '{$prvniTechnik['user_id']}' (user_id)</td><td>{$pocetUserId}</td></tr>";
    echo "</table>";

    if ($pocetId > 0 || $pocetIdStr > 0) {
        echo "<div class='success'><strong>ZÁVĚR:</strong> assigned_to obsahuje numerické ID</div>";
    } elseif ($pocetUserId > 0) {
        echo "<div class='warning'><strong>ZÁVĚR:</strong> assigned_to obsahuje textové user_id</div>";
    } else {
        echo "<div class='warning'><strong>ZÁVĚR:</strong> Žádné reklamace nemají přiřazeného tohoto technika</div>";
    }
}

// 4. Posledních 10 reklamací s přiřazeným technikem
echo "<h2>4. Posledních 10 reklamací s assigned_to</h2>";
$stmtLast = $pdo->query("
    SELECT
        r.cislo,
        r.assigned_to,
        r.technik as technik_text,
        u.name as technik_join,
        r.created_at
    FROM wgs_reklamace r
    LEFT JOIN wgs_users u ON r.assigned_to = u.id
    WHERE r.assigned_to IS NOT NULL AND r.assigned_to != ''
    ORDER BY r.created_at DESC
    LIMIT 10
");
$posledni = $stmtLast->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>Číslo</th><th>assigned_to</th><th>technik (textový sloupec)</th><th>JOIN na u.id</th><th>Datum</th></tr>";
foreach ($posledni as $p) {
    $joinResult = $p['technik_join'] ?? '<span style=\"color:red\">NULL (JOIN nefunguje)</span>';
    echo "<tr><td>{$p['cislo']}</td><td><code>{$p['assigned_to']}</code></td><td>{$p['technik_text']}</td><td>{$joinResult}</td><td>{$p['created_at']}</td></tr>";
}
echo "</table>";

echo "</div></body></html>";
