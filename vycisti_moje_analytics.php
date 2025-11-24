<?php
/**
 * Vyčištění vlastních analytics dat
 *
 * Tento skript smaže data z heatmap tabulek.
 * POZOR: Tato akce je nevratná!
 *
 * @date 2025-11-24
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
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Vyčištění Analytics dat</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #555;
        }
        .btn-danger {
            background: #721c24;
        }
        .btn-danger:hover {
            background: #5a1a1f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #333;
            color: white;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Vyčištění Analytics dat</h1>";
    echo "<p><strong>Datum:</strong> " . date('d.m.Y H:i:s') . "</p>";

    // =======================================================
    // ZOBRAZIT AKTUÁLNÍ STAV
    // =======================================================
    echo "<h2>Aktuální stav tabulek</h2>";

    $tabulky = [
        'wgs_analytics_heatmap_clicks' => 'Heatmap Clicks',
        'wgs_analytics_heatmap_scroll' => 'Heatmap Scroll'
    ];

    echo "<table>";
    echo "<tr><th>Tabulka</th><th>Počet záznamů</th><th>Města</th></tr>";

    foreach ($tabulky as $tabulka => $nazev) {
        // Zkontrolovat, jestli tabulka existuje
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
        if (!$stmt || $stmt->rowCount() === 0) {
            echo "<tr><td>{$nazev}</td><td colspan='2'><em>Tabulka neexistuje</em></td></tr>";
            continue;
        }

        // Počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `{$tabulka}`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        // Města
        $stmt = $pdo->query("SELECT DISTINCT city FROM `{$tabulka}` WHERE city IS NOT NULL LIMIT 10");
        $mesta = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $mestaStr = !empty($mesta) ? implode(', ', $mesta) : '<em>žádná</em>';

        echo "<tr>";
        echo "<td><code>{$tabulka}</code></td>";
        echo "<td><strong>{$count}</strong></td>";
        echo "<td>{$mestaStr}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // =======================================================
    // AKCE: SMAZAT VŠE
    // =======================================================
    if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
        echo "<h2>Mazání všech dat...</h2>";

        $smazano = 0;

        foreach ($tabulky as $tabulka => $nazev) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
            if ($stmt && $stmt->rowCount() > 0) {
                $stmt = $pdo->exec("DELETE FROM `{$tabulka}`");
                $smazano += $stmt;
                echo "<div class='success'>Smazáno z <code>{$tabulka}</code>: <strong>{$stmt}</strong> záznamů</div>";
            }
        }

        echo "<div class='success'><strong>HOTOVO!</strong> Celkem smazáno: <strong>{$smazano}</strong> záznamů</div>";
        echo "<a href='vycisti_moje_analytics.php' class='btn'>Zpět</a>";
        echo "<a href='admin.php' class='btn'>Admin Panel</a>";

    // =======================================================
    // AKCE: SMAZAT PODLE MĚSTA
    // =======================================================
    } elseif (isset($_GET['action']) && $_GET['action'] === 'delete_city' && isset($_GET['city'])) {
        $city = $_GET['city'];
        echo "<h2>Mazání dat pro město: {$city}</h2>";

        $smazano = 0;

        foreach ($tabulky as $tabulka => $nazev) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
            if ($stmt && $stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("DELETE FROM `{$tabulka}` WHERE city = :city OR city IS NULL");
                $stmt->execute(['city' => $city]);
                $count = $stmt->rowCount();
                $smazano += $count;
                echo "<div class='success'>Smazáno z <code>{$tabulka}</code>: <strong>{$count}</strong> záznamů</div>";
            }
        }

        echo "<div class='success'><strong>HOTOVO!</strong> Celkem smazáno: <strong>{$smazano}</strong> záznamů</div>";
        echo "<a href='vycisti_moje_analytics.php' class='btn'>Zpět</a>";

    // =======================================================
    // ZOBRAZIT MOŽNOSTI
    // =======================================================
    } else {
        echo "<h2>Možnosti vyčištění</h2>";

        echo "<div class='warning'>";
        echo "<strong>POZOR:</strong> Mazání dat je nevratné!";
        echo "</div>";

        echo "<h3>Možnost 1: Smazat VŠECHNA data</h3>";
        echo "<p>Toto smaže všechna data z heatmap tabulek. Použij, pokud jsou to převážně tvá testovací data.</p>";
        echo "<a href='?action=delete_all' class='btn btn-danger' onclick=\"return confirm('Opravdu smazat VŠECHNA data z heatmap tabulek?')\">Smazat všechna data</a>";

        echo "<h3>Možnost 2: Smazat podle města</h3>";
        echo "<p>Smaže data pouze z konkrétního města + záznamy bez města (NULL).</p>";

        // Získat unikátní města
        $vsechnaMesta = [];
        foreach ($tabulky as $tabulka => $nazev) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
            if ($stmt && $stmt->rowCount() > 0) {
                $stmt = $pdo->query("SELECT DISTINCT city FROM `{$tabulka}` WHERE city IS NOT NULL");
                $mesta = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $vsechnaMesta = array_merge($vsechnaMesta, $mesta);
            }
        }
        $vsechnaMesta = array_unique($vsechnaMesta);

        if (!empty($vsechnaMesta)) {
            echo "<p>Nalezená města:</p>";
            foreach ($vsechnaMesta as $mesto) {
                echo "<a href='?action=delete_city&city=" . urlencode($mesto) . "' class='btn' onclick=\"return confirm('Smazat data pro město: {$mesto}?')\">{$mesto}</a> ";
            }
        } else {
            echo "<p><em>Žádná města v datech.</em></p>";
        }

        echo "<h3>Možnost 3: Smazat záznamy bez geolokace</h3>";
        echo "<p>Smaže záznamy, kde chybí město (starší data před přidáním geolokace).</p>";
        echo "<a href='?action=delete_city&city=' class='btn' onclick=\"return confirm('Smazat záznamy bez města (NULL)?')\">Smazat záznamy bez města</a>";

        echo "<hr>";
        echo "<a href='admin.php' class='btn' style='background:#666'>Zpět na Admin</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
