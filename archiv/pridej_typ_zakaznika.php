<?php
/**
 * Migrace: Přidání sloupce typ_zakaznika do wgs_reklamace
 *
 * Tento skript BEZPEČNĚ přidá sloupec pro typ zákazníka (IČO/Fyzická osoba).
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
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
    <title>Migrace: typ_zakaznika</title>
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
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Sloupec typ_zakaznika</h1>";

    // Kontrola zda sloupec již existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'typ_zakaznika'");
    $existuje = $stmt->fetch();

    if ($existuje) {
        echo "<div class='warning'>";
        echo "<strong>SLOUPEC JIŽ EXISTUJE</strong><br>";
        echo "Sloupec <code>typ_zakaznika</code> již v tabulce <code>wgs_reklamace</code> existuje.";
        echo "</div>";

        // Zobrazit aktuální strukturu
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace WHERE Field = 'typ_zakaznika'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<div class='info'>";
        echo "<strong>Aktuální struktura:</strong><br>";
        echo "Typ: <code>{$col['Type']}</code><br>";
        echo "Null: <code>{$col['Null']}</code><br>";
        echo "Default: <code>" . ($col['Default'] ?? 'NULL') . "</code>";
        echo "</div>";
    } else {
        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Přidat sloupec typ_zakaznika za email
                $sql = "ALTER TABLE wgs_reklamace
                        ADD COLUMN typ_zakaznika VARCHAR(50) DEFAULT NULL
                        COMMENT 'Typ zákazníka: IČO nebo Fyzická osoba'
                        AFTER email";

                $pdo->exec($sql);

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
                echo "Sloupec <code>typ_zakaznika</code> byl přidán do tabulky <code>wgs_reklamace</code>.";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>Nový sloupec:</strong><br>";
                echo "Název: <code>typ_zakaznika</code><br>";
                echo "Typ: <code>VARCHAR(50)</code><br>";
                echo "Hodnoty: <code>IČO</code> nebo <code>Fyzická osoba</code> nebo <code>NULL</code>";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Náhled co bude provedeno
            echo "<div class='info'>";
            echo "<strong>CO BUDE PROVEDENO:</strong><br>";
            echo "Přidání sloupce <code>typ_zakaznika</code> (VARCHAR(50)) do tabulky <code>wgs_reklamace</code><br>";
            echo "Sloupec bude umístěn za sloupec <code>email</code><br>";
            echo "Možné hodnoty: <code>IČO</code>, <code>Fyzická osoba</code>, nebo prázdné";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='admin.php' class='btn' style='background:#666;'>Zpět do administrace</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><br><a href='admin.php' class='btn' style='background:#666;'>Zpět do administrace</a>";
echo "</div></body></html>";
?>
