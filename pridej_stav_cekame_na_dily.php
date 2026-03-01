<?php
/**
 * Migrace: Přidání stavu 'cekame_na_dily' do ENUM sloupce stav
 *
 * Přidá nový stav ČEKÁME NA DÍLY do tabulky wgs_reklamace.
 * Bezpečné spuštění opakovaně - pokud stav již existuje, nic se nestane.
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Stav ČEKÁME NA DÍLY</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 800px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error   { background: #f8d7da; border: 1px solid #f5c6cb;
                   color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info    { background: #d1ecf1; border: 1px solid #bee5eb;
                   color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f8f8; border: 1px solid #ddd; padding: 10px;
              border-radius: 4px; font-size: 0.9rem; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 22px; background: #333;
               color: #fff; text-decoration: none; border-radius: 5px;
               margin: 8px 4px; border: none; cursor: pointer; font-size: 0.95rem; }
        .btn:hover { background: #000; }
        .btn-secondary { background: #666; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Migrace: Přidání stavu ČEKÁME NA DÍLY</h1>";

try {
    $pdo = getDbConnection();

    // Zjistit aktuální ENUM definici
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'stav'");
    $sloupec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sloupec) {
        echo "<div class='error'>Sloupec 'stav' v tabulce wgs_reklamace nenalezen!</div>";
        exit;
    }

    $aktualniTyp = $sloupec['Type'];
    echo "<div class='info'>
        <strong>Aktuální definice:</strong><br>
        <code>{$aktualniTyp}</code>
    </div>";

    // Zkontrolovat zda stav 'cekame_na_dily' již existuje
    if (strpos($aktualniTyp, 'cekame_na_dily') !== false) {
        echo "<div class='success'>
            <strong>Stav 'cekame_na_dily' již existuje v ENUM – migrace není potřeba.</strong>
        </div>";
        echo "<a href='seznam.php' class='btn btn-secondary'>Přejít na seznam</a>";
        echo "</div></body></html>";
        exit;
    }

    // Sestavit nový ENUM - přidat cekame_na_dily za 'done'
    // Příklad: enum('wait','open','done') → enum('wait','open','done','cekame_na_dily')
    $novyTyp = rtrim($aktualniTyp, ')') . ",'cekame_na_dily')";

    echo "<div class='info'>
        <strong>Nová definice po migraci:</strong><br>
        <code>{$novyTyp}</code>
    </div>";

    if (isset($_GET['spustit']) && $_GET['spustit'] === '1') {
        // PROVEDENÍ MIGRACE
        $sql = "ALTER TABLE wgs_reklamace MODIFY COLUMN stav {$novyTyp}";
        $pdo->exec($sql);

        // Ověřit výsledek
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'stav'");
        $novySloupec = $stmt->fetch(PDO::FETCH_ASSOC);

        if (strpos($novySloupec['Type'], 'cekame_na_dily') !== false) {
            echo "<div class='success'>
                <strong>Migrace úspěšně dokončena!</strong><br>
                Stav 'cekame_na_dily' byl přidán do ENUM.<br>
                Nová definice: <code>{$novySloupec['Type']}</code>
            </div>";
        } else {
            echo "<div class='error'>
                <strong>Migrace selhala!</strong><br>
                Aktuální stav: <code>{$novySloupec['Type']}</code>
            </div>";
        }

        echo "<a href='seznam.php' class='btn'>Přejít na seznam</a>";

    } else {
        echo "<div class='warning'>
            <strong>Tato operace přidá nový stav do databáze.</strong><br>
            Stávající záznamy nebudou dotčeny. Operace je bezpečná.
        </div>";
        echo "<a href='pridej_stav_cekame_na_dily.php?spustit=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn btn-secondary'>Zpět</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
