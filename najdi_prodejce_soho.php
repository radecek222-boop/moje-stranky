<?php
/**
 * Najít prodejce s emailem soho@natuzzi.cz
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
    <title>Hledání prodejce soho@natuzzi.cz</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: 600; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; color: #fff; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Hledání prodejce soho@natuzzi.cz</h1>";

    // Hledat podle emailu
    $stmt = $pdo->prepare("SELECT user_id, name, email, role FROM wgs_users WHERE email LIKE :email");
    $stmt->execute(['email' => '%soho@natuzzi.cz%']);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        echo "<div class='info'><strong>Nalezeni uživatelé s emailem soho@natuzzi.cz:</strong></div>";
        echo "<table>";
        echo "<tr><th>user_id</th><th>Jméno</th><th>Email</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td><code>{$user['user_id']}</code></td>";
            echo "<td>{$user['name']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Žádný uživatel s emailem soho@natuzzi.cz nebyl nalezen.</div>";
    }

    // Vypsat všechny prodejce
    echo "<h2>Všichni prodejci v databázi:</h2>";
    $stmt = $pdo->query("SELECT user_id, name, email, role FROM wgs_users WHERE role = 'prodejce' ORDER BY user_id");
    $prodejci = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($prodejci) > 0) {
        echo "<table>";
        echo "<tr><th>user_id</th><th>Jméno</th><th>Email</th><th>Role</th></tr>";
        foreach ($prodejci as $p) {
            echo "<tr>";
            echo "<td><code>{$p['user_id']}</code></td>";
            echo "<td>{$p['name']}</td>";
            echo "<td>{$p['email']}</td>";
            echo "<td>{$p['role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Žádní prodejci v databázi.</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
