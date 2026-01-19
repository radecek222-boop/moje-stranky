<?php
/**
 * Migrace: Individuální provize POZ
 *
 * Tento skript BEZPEČNĚ přidá sloupec provize_poz_procent do tabulky wgs_users.
 * Můžete jej spustit vícekrát - neprovede se duplicitní přidání sloupce.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Individuální provize POZ</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
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
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; cursor: pointer;
               border: none; }
        .btn:hover { background: #1a1a1a; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Individuální provize POZ</h1>";
    echo "<p>Tento skript přidá sloupec <code>provize_poz_procent</code> do tabulky <code>wgs_users</code>.</p>";

    // Kontrola existence sloupce
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users LIKE 'provize_poz_procent'");
    $sloupecExistuje = $stmt->rowCount() > 0;

    if ($sloupecExistuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ SLOUPEC JIŽ EXISTUJE</strong><br>";
        echo "Sloupec <code>provize_poz_procent</code> již byl přidán do tabulky <code>wgs_users</code>.<br>";
        echo "Migrace není potřeba.";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'><strong>KONTROLA...</strong></div>";
    echo "<div class='info'>";
    echo "Sloupec <code>provize_poz_procent</code> neexistuje - migrace je potřeba.<br>";
    echo "Po přidání budou techniky moci mít individuální provizi z POZ servisů (výchozí 50%).";
    echo "</div>";

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Přidat sloupec provize_poz_procent
            $pdo->exec("
                ALTER TABLE wgs_users
                ADD COLUMN provize_poz_procent DECIMAL(5,2) DEFAULT 50.00
                COMMENT 'Procentuální odměna technika z POZ servisů (výchozí 50%)'
                AFTER provize_procent
            ");

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✓ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Sloupec <code>provize_poz_procent</code> byl přidán do tabulky <code>wgs_users</code>.<br>";
            echo "Výchozí hodnota: <strong>50.00%</strong>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>CO DÁLE?</strong><br>";
            echo "1. Nyní můžete v Admin panelu upravit provize POZ pro jednotlivé techniky<br>";
            echo "2. Výdělek z POZ se bude počítat s individuální provizí (ne fixní 50%)";
            echo "</div>";

            echo "<p><a href='/admin.php?tab=keys&section=uzivatele' class='btn'>Otevřít správu uživatelů</a></p>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Náhled co bude provedeno
        echo "<h2>Náhled změn:</h2>";
        echo "<pre>";
        echo "ALTER TABLE wgs_users\n";
        echo "ADD COLUMN provize_poz_procent DECIMAL(5,2) DEFAULT 50.00\n";
        echo "COMMENT 'Procentuální odměna technika z POZ servisů (výchozí 50%)'\n";
        echo "AFTER provize_procent;";
        echo "</pre>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
