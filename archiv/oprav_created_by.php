<?php
/**
 * Migrace: Oprava sloupcu user_id na VARCHAR
 *
 * Sloupce user_id/created_by musi byt VARCHAR, protoze user_id muze byt:
 * - 'ADMIN001' pro admina
 * - 'TCH20250001' pro technika
 * - 'PRO20250001' pro prodejce
 *
 * Opravuje tabulky:
 * - wgs_reklamace (created_by, zpracoval_id)
 * - wgs_push_subscriptions (user_id)
 * - wgs_notes (created_by)
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Oprava user_id sloupcu</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h3 { margin-top: 25px; color: #444; }
        .success { background: #d4edda; color: #155724; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 12px;
                 border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px;
                border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 15px 5px 10px 0; border: none; cursor: pointer;
               font-size: 1rem; }
        .btn:hover { background: #555; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<div class='container'>";

// Definice sloupcu k oprave
$opravy = [
    ['tabulka' => 'wgs_reklamace', 'sloupec' => 'created_by', 'typ' => 'VARCHAR(50) NULL'],
    ['tabulka' => 'wgs_reklamace', 'sloupec' => 'zpracoval_id', 'typ' => 'VARCHAR(50) NULL'],
    ['tabulka' => 'wgs_push_subscriptions', 'sloupec' => 'user_id', 'typ' => 'VARCHAR(50) NULL'],
    ['tabulka' => 'wgs_notes', 'sloupec' => 'created_by', 'typ' => 'VARCHAR(50) NULL'],
];

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava user_id sloupcu</h1>";
    echo "<p>Zmeni INT sloupce na VARCHAR(50) pro podporu user_id jako 'ADMIN001', 'TCH2025xxxx', 'PRO2025xxxx'.</p>";

    // Kontrola stavu
    echo "<h3>Aktualni stav:</h3>";
    echo "<table><tr><th>Tabulka</th><th>Sloupec</th><th>Aktualni typ</th><th>Stav</th></tr>";

    $potrebaOpravy = false;

    foreach ($opravy as &$oprava) {
        $tabulka = $oprava['tabulka'];
        $sloupec = $oprava['sloupec'];

        // Zkontrolovat jestli tabulka existuje
        $stmtTable = $pdo->query("SHOW TABLES LIKE '{$tabulka}'");
        if ($stmtTable->rowCount() === 0) {
            echo "<tr><td>{$tabulka}</td><td>{$sloupec}</td><td>-</td><td style='color:#999;'>Tabulka neexistuje</td></tr>";
            $oprava['existuje'] = false;
            continue;
        }

        // Zkontrolovat sloupec
        $stmt = $pdo->query("SHOW COLUMNS FROM {$tabulka} WHERE Field = '{$sloupec}'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$column) {
            echo "<tr><td>{$tabulka}</td><td>{$sloupec}</td><td>-</td><td style='color:#999;'>Sloupec neexistuje</td></tr>";
            $oprava['existuje'] = false;
            continue;
        }

        $oprava['existuje'] = true;
        $oprava['aktualniTyp'] = $column['Type'];
        $jeInt = (stripos($column['Type'], 'int') !== false);
        $oprava['jeInt'] = $jeInt;

        if ($jeInt) {
            echo "<tr><td>{$tabulka}</td><td>{$sloupec}</td><td>{$column['Type']}</td><td style='color:#c00;font-weight:bold;'>POTREBUJE OPRAVU</td></tr>";
            $potrebaOpravy = true;
        } else {
            echo "<tr><td>{$tabulka}</td><td>{$sloupec}</td><td>{$column['Type']}</td><td style='color:#080;'>OK</td></tr>";
        }
    }
    unset($oprava);

    echo "</table>";

    // Spustit migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        if (!$potrebaOpravy) {
            echo "<div class='success'>Vsechny sloupce jsou jiz spravne nastaveny - neni co opravovat.</div>";
        } else {
            echo "<h3>Provadim migraci:</h3>";

            foreach ($opravy as $oprava) {
                if (!($oprava['existuje'] ?? false) || !($oprava['jeInt'] ?? false)) {
                    continue;
                }

                $tabulka = $oprava['tabulka'];
                $sloupec = $oprava['sloupec'];
                $novyTyp = $oprava['typ'];

                $sql = "ALTER TABLE {$tabulka} MODIFY COLUMN {$sloupec} {$novyTyp}";
                echo "<pre>{$sql}</pre>";

                try {
                    $pdo->exec($sql);
                    echo "<div class='success'>OK: {$tabulka}.{$sloupec} zmenen na {$novyTyp}</div>";
                } catch (Exception $e) {
                    echo "<div class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }

            echo "<div class='success' style='margin-top:20px;'><strong>MIGRACE DOKONCENA!</strong></div>";
        }

    } else {
        // Zobrazit co bude provedeno
        if ($potrebaOpravy) {
            echo "<div class='warning'><strong>Nalezeny sloupce k oprave!</strong> Kliknete na tlacitko pro spusteni migrace.</div>";
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        } else {
            echo "<div class='success'>Vsechny sloupce jsou v poradku - neni potreba migrace.</div>";
        }
    }

    echo "<br><a href='admin.php' class='btn' style='background:#666;'>Zpet do Admin</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
