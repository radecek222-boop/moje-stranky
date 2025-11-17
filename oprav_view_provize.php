<?php
/**
 * Oprava VIEW wgs_provize_technici
 * View odkazoval na smazané sloupce technik_milan_kolin a technik_radek_zikmund
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
    <title>Oprava VIEW wgs_provize_technici</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
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
        .sql-code { background: #f4f4f4; border-left: 4px solid #2D5016;
                    padding: 10px; margin: 10px 0; font-family: 'Courier New', monospace;
                    font-size: 12px; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Oprava VIEW wgs_provize_technici</h1>";

try {
    $pdo = getDbConnection();

    echo "<div class='warning'>";
    echo "<strong>PROBLÉM:</strong><br>";
    echo "VIEW <code>wgs_provize_technici</code> odkazuje na smazané sloupce:<br>";
    echo "- <code>technik_milan_kolin</code> (byl smazán)<br>";
    echo "- <code>technik_radek_zikmund</code> (byl smazán)<br><br>";
    echo "Tento VIEW je ZASTARALÝ a není potřeba (provize se nepočítají přes tento VIEW).";
    echo "</div>";

    if (isset($_GET['fix']) && $_GET['fix'] === '1') {
        echo "<div class='info'><strong>ODSTRAŇUJI ZASTARALÝ VIEW...</strong></div>";

        try {
            // Smazat VIEW
            $pdo->exec("DROP VIEW IF EXISTS wgs_provize_technici");

            echo "<div class='success'>";
            echo "<strong>HOTOVO!</strong><br>";
            echo "VIEW <code>wgs_provize_technici</code> byl úspěšně odstraněn.<br><br>";
            echo "<strong>DŮVOD:</strong> Tento VIEW není potřeba, protože:<br>";
            echo "- Provize se nepočítají automaticky<br>";
            echo "- Odkazoval na smazané sloupce technik_milan_kolin a technik_radek_zikmund<br>";
            echo "- Nový systém používá jiný přístup (zpracoval_id + wgs_users)";
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
        echo "<h2>Co bude provedeno:</h2>";

        echo "<div class='sql-code'>";
        echo "DROP VIEW IF EXISTS wgs_provize_technici;";
        echo "</div>";

        echo "<div class='info'>";
        echo "<strong>POZNÁMKA:</strong> Tabulka <code>wgs_provize_technici</code> (bez VIEW) zůstane zachována.";
        echo "</div>";

        echo "<a href='?fix=1' class='btn' onclick='return confirm(\"Opravdu chcete odstranit VIEW wgs_provize_technici?\")'>ODSTRANIT VIEW</a>";
        echo "<a href='vsechny_tabulky.php' class='btn' style='background: #6c757d;'>Zrušit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
