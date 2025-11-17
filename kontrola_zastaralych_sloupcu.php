<?php
/**
 * Kontrola zastaralých sloupců technik_milan_kolin a technik_radek_zikmund
 * Zjistí jestli obsahují nějaká data před jejich odstraněním
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("❌ PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola zastaralých sloupců</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        tr:hover { background: #f5f5f5; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; text-align: center; border: none; cursor: pointer; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .sql-code { background: #f4f4f4; border-left: 4px solid #2D5016; padding: 10px; margin: 10px 0; font-family: 'Courier New', monospace; font-size: 12px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Kontrola zastaralých sloupců</h1>";

try {
    $pdo = getDbConnection();

    echo "<div class='info'>";
    echo "<strong>📋 KONTROLOVANÉ SLOUPCE:</strong><br>";
    echo "- <code>technik_milan_kolin</code> (DECIMAL) - zastaralý<br>";
    echo "- <code>technik_radek_zikmund</code> (DECIMAL) - zastaralý<br><br>";
    echo "<strong>NOVÝ SPRÁVNÝ SYSTÉM:</strong><br>";
    echo "- <code>technik</code> (VARCHAR) - jméno technika<br>";
    echo "- <code>zpracoval_id</code> (INT) - FK na wgs_users.id<br>";
    echo "</div>";

    // Zjistit kolik záznamů má vyplněné staré sloupce
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as celkem,
            COUNT(technik_milan_kolin) as milan_count,
            COUNT(technik_radek_zikmund) as radek_count,
            SUM(CASE WHEN technik_milan_kolin > 0 THEN 1 ELSE 0 END) as milan_nonzero,
            SUM(CASE WHEN technik_radek_zikmund > 0 THEN 1 ELSE 0 END) as radek_nonzero,
            SUM(COALESCE(technik_milan_kolin, 0)) as milan_suma,
            SUM(COALESCE(technik_radek_zikmund, 0)) as radek_suma
        FROM wgs_reklamace
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<h2>📊 Statistika použití:</h2>";
    echo "<table>";
    echo "<thead><tr><th>Metrika</th><th>Hodnota</th></tr></thead>";
    echo "<tbody>";
    echo "<tr><td>Celkem záznamů v tabulce</td><td><strong>{$stats['celkem']}</strong></td></tr>";
    echo "<tr><td>Záznamů s Milan Kolín != NULL</td><td><strong>{$stats['milan_count']}</strong></td></tr>";
    echo "<tr><td>Záznamů s Milan Kolín > 0</td><td><strong>{$stats['milan_nonzero']}</strong></td></tr>";
    echo "<tr><td>Součet částek Milan Kolín</td><td><strong>" . number_format($stats['milan_suma'], 2) . " €</strong></td></tr>";
    echo "<tr><td>Záznamů s Radek Zikmund != NULL</td><td><strong>{$stats['radek_count']}</strong></td></tr>";
    echo "<tr><td>Záznamů s Radek Zikmund > 0</td><td><strong>{$stats['radek_nonzero']}</strong></td></tr>";
    echo "<tr><td>Součet částek Radek Zikmund</td><td><strong>" . number_format($stats['radek_suma'], 2) . " €</strong></td></tr>";
    echo "</tbody></table>";

    // Zobrazit záznamy které mají vyplněné staré sloupce
    if ($stats['milan_nonzero'] > 0 || $stats['radek_nonzero'] > 0) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ POZOR:</strong> Některé záznamy mají vyplněné částky ve starých sloupcích!";
        echo "</div>";

        echo "<h2>📋 Záznamy s vyplněnými starými sloupci:</h2>";

        $stmt = $pdo->query("
            SELECT
                id,
                reklamace_id,
                jmeno,
                technik_milan_kolin,
                technik_radek_zikmund,
                technik as technik_novy,
                zpracoval_id,
                created_at
            FROM wgs_reklamace
            WHERE technik_milan_kolin > 0 OR technik_radek_zikmund > 0
            ORDER BY created_at DESC
        ");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<thead><tr><th>ID</th><th>Reklamace ID</th><th>Zákazník</th><th>Milan Kolín</th><th>Radek Zikmund</th><th>Technik (nový)</th><th>zpracoval_id</th><th>Datum</th></tr></thead>";
        echo "<tbody>";

        foreach ($records as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['reklamace_id']}</td>";
            echo "<td>{$row['jmeno']}</td>";
            echo "<td>" . number_format($row['technik_milan_kolin'] ?? 0, 2) . " €</td>";
            echo "<td>" . number_format($row['technik_radek_zikmund'] ?? 0, 2) . " €</td>";
            echo "<td>" . ($row['technik_novy'] ?? '-') . "</td>";
            echo "<td>" . ($row['zpracoval_id'] ?? '-') . "</td>";
            echo "<td>" . date('d.m.Y H:i', strtotime($row['created_at'])) . "</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";

        echo "<div class='warning'>";
        echo "<strong>💡 DOPORUČENÍ:</strong><br>";
        echo "Před smazáním sloupců zvažte:<br>";
        echo "1. Exportovat data do CSV pro archiv<br>";
        echo "2. Migrovat částky do sloupce 'cena' pokud tam chybí<br>";
        echo "3. Nastavit správně 'technik' a 'zpracoval_id'<br>";
        echo "</div>";

    } else {
        echo "<div class='success'>";
        echo "<strong>✅ BEZPEČNÉ K ODSTRANĚNÍ!</strong><br>";
        echo "Žádný záznam nemá vyplněné částky ve starých sloupcích.<br>";
        echo "Sloupce <code>technik_milan_kolin</code> a <code>technik_radek_zikmund</code> lze bezpečně smazat.";
        echo "</div>";
    }

    // Zkontrolovat nový systém
    echo "<h2>✅ Kontrola nového systému:</h2>";

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as celkem,
            COUNT(technik) as ma_technik_jmeno,
            COUNT(zpracoval_id) as ma_zpracoval_id,
            SUM(CASE WHEN zpracoval_id IS NOT NULL THEN 1 ELSE 0 END) as zpracoval_id_nonzero
        FROM wgs_reklamace
    ");
    $newStats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<thead><tr><th>Metrika</th><th>Hodnota</th></tr></thead>";
    echo "<tbody>";
    echo "<tr><td>Celkem záznamů</td><td><strong>{$newStats['celkem']}</strong></td></tr>";
    echo "<tr><td>Má vyplněné jméno technika (VARCHAR)</td><td><strong>{$newStats['ma_technik_jmeno']}</strong></td></tr>";
    echo "<tr><td>Má vyplněné zpracoval_id (FK)</td><td><strong>{$newStats['ma_zpracoval_id']}</strong></td></tr>";
    echo "<tr><td>zpracoval_id IS NOT NULL</td><td><strong>{$newStats['zpracoval_id_nonzero']}</strong></td></tr>";
    echo "</tbody></table>";

    // Pokud jsou sloupce prázdné, nabídnout smazání
    if ($stats['milan_nonzero'] == 0 && $stats['radek_nonzero'] == 0) {
        echo "<h2>🗑️ Odstranění zastaralých sloupců:</h2>";

        if (isset($_GET['delete']) && $_GET['delete'] === '1') {
            echo "<div class='info'><strong>ODSTRAŇUJI SLOUPCE...</strong></div>";

            // POZNÁMKA: DDL příkazy (ALTER TABLE) v MySQL/MariaDB automaticky commitují transakci
            // Proto nepoužíváme BEGIN/COMMIT - každý ALTER TABLE je samostatná transakce

            try {
                $pdo->exec("ALTER TABLE wgs_reklamace DROP COLUMN technik_milan_kolin");
                echo "<div class='success'>Sloupec <code>technik_milan_kolin</code> úspěšně odstraněn</div>";

                $pdo->exec("ALTER TABLE wgs_reklamace DROP COLUMN technik_radek_zikmund");
                echo "<div class='success'>Sloupec <code>technik_radek_zikmund</code> úspěšně odstraněn</div>";

                echo "<div class='success'>";
                echo "<strong>HOTOVO!</strong><br>";
                echo "Zastaralé sloupce byly odstraněny z databáze.<br>";
                echo "Systém nyní používá pouze nový přístup: <code>technik</code> + <code>zpracoval_id</code>";
                echo "</div>";

                echo "<a href='vsechny_tabulky.php' class='btn'>Zpět na SQL přehled</a>";
                echo "<a href='admin.php' class='btn'>Zpět na admin</a>";

            } catch (PDOException $e) {
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }

        } else {
            echo "<div class='sql-code'>";
            echo "ALTER TABLE wgs_reklamace DROP COLUMN technik_milan_kolin;<br>";
            echo "ALTER TABLE wgs_reklamace DROP COLUMN technik_radek_zikmund;";
            echo "</div>";

            echo "<a href='kontrola_zastaralych_sloupcu.php?delete=1' class='btn btn-danger' onclick='return confirm(\"Opravdu chcete trvale odstranit zastaralé sloupce?\")'>🗑️ ODSTRANIT ZASTARALÉ SLOUPCE</a>";
            echo "<a href='admin.php' class='btn'>← Zpět</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
