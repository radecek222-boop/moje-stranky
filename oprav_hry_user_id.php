<?php
/**
 * Migrace: Oprava typu user_id v hernich tabulkach
 *
 * Tento skript zmeni user_id z INT na VARCHAR(50),
 * protoze v projektu se pouzivaji stringove ID (ADMIN001, TCH2025001, atd.)
 */
require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Oprava user_id v hernich tabulkach</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #2d2d2d; padding: 30px; border-radius: 10px; }
        h1 { color: #0099ff; border-bottom: 2px solid #0099ff; padding-bottom: 10px; }
        .success { background: #1a3d1a; border: 1px solid #28a745; color: #90EE90; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3d1a1a; border: 1px solid #dc3545; color: #ff8888; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2d3d; border: 1px solid #17a2b8; color: #87CEEB; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3d3d1a; border: 1px solid #ffc107; color: #ffe066; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #0099ff; color: #fff; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #0077cc; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        pre { background: #111; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.85em; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #444; text-align: left; }
        th { background: #333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Oprava user_id v hernich tabulkach</h1>";

$opravy = [
    [
        'tabulka' => 'wgs_hry_online',
        'sloupec' => 'user_id',
        'sql' => "ALTER TABLE `wgs_hry_online` MODIFY COLUMN `user_id` VARCHAR(50) NOT NULL COMMENT 'ID uzivatele (ADMIN001, TCH2025001, PRT2025001)'"
    ],
    [
        'tabulka' => 'wgs_hry_mistnosti',
        'sloupec' => 'vytvoril_user_id',
        'sql' => "ALTER TABLE `wgs_hry_mistnosti` MODIFY COLUMN `vytvoril_user_id` VARCHAR(50) NOT NULL COMMENT 'ID uzivatele'"
    ],
    [
        'tabulka' => 'wgs_hry_hraci_mistnosti',
        'sloupec' => 'user_id',
        'sql' => "ALTER TABLE `wgs_hry_hraci_mistnosti` MODIFY COLUMN `user_id` VARCHAR(50) NOT NULL COMMENT 'ID uzivatele'"
    ],
    [
        'tabulka' => 'wgs_hry_chat',
        'sloupec' => 'user_id',
        'sql' => "ALTER TABLE `wgs_hry_chat` MODIFY COLUMN `user_id` VARCHAR(50) NOT NULL COMMENT 'ID uzivatele'"
    ],
    [
        'tabulka' => 'wgs_hry_prsi_partie',
        'sloupec' => 'hrac1_id',
        'sql' => "ALTER TABLE `wgs_hry_prsi_partie` MODIFY COLUMN `hrac1_id` VARCHAR(50) NOT NULL COMMENT 'ID hrace 1'"
    ],
    [
        'tabulka' => 'wgs_hry_prsi_partie',
        'sloupec' => 'hrac2_id',
        'sql' => "ALTER TABLE `wgs_hry_prsi_partie` MODIFY COLUMN `hrac2_id` VARCHAR(50) DEFAULT NULL COMMENT 'NULL = pocitac'"
    ]
];

// Pridat sloupec herni_stav do wgs_hry_mistnosti pokud neexistuje
$pridatSloupce = [
    [
        'tabulka' => 'wgs_hry_mistnosti',
        'sloupec' => 'herni_stav',
        'sql' => "ALTER TABLE `wgs_hry_mistnosti` ADD COLUMN `herni_stav` LONGTEXT DEFAULT NULL COMMENT 'JSON stav hry' AFTER `vytvoril_user_id`"
    ]
];

try {
    $pdo = getDbConnection();

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>PROVADIM OPRAVY...</strong></div>";

        // Nejdrive smazat data z tabulek (protoze obsahuji neplatne hodnoty)
        echo "<div class='warning'><strong>Mazu stara data z hernich tabulek (obsahuji neplatne user_id=0)...</strong></div>";

        $tabulkyKVycisteni = ['wgs_hry_online', 'wgs_hry_chat', 'wgs_hry_hraci_mistnosti', 'wgs_hry_mistnosti', 'wgs_hry_prsi_partie'];
        foreach ($tabulkyKVycisteni as $tabulka) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `{$tabulka}`");
                $pocet = $stmt->fetchColumn();
                if ($pocet > 0) {
                    $pdo->exec("TRUNCATE TABLE `{$tabulka}`");
                    echo "<div class='info'>Tabulka {$tabulka}: smazano {$pocet} zaznamu</div>";
                }
            } catch (PDOException $e) {
                // Tabulka mozna neexistuje
            }
        }

        // Opravit typy sloupcu
        foreach ($opravy as $oprava) {
            try {
                // Zkontrolovat zda tabulka existuje
                $stmt = $pdo->query("SHOW TABLES LIKE '{$oprava['tabulka']}'");
                if (!$stmt->fetch()) {
                    echo "<div class='warning'>Tabulka {$oprava['tabulka']} neexistuje - preskakuji</div>";
                    continue;
                }

                // Zkontrolovat aktualni typ
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$oprava['tabulka']}` LIKE '{$oprava['sloupec']}'");
                $sloupec = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$sloupec) {
                    echo "<div class='warning'>Sloupec {$oprava['sloupec']} v {$oprava['tabulka']} neexistuje - preskakuji</div>";
                    continue;
                }

                $aktualniTyp = strtoupper($sloupec['Type']);

                if (strpos($aktualniTyp, 'VARCHAR') !== false) {
                    echo "<div class='success'>Sloupec {$oprava['tabulka']}.{$oprava['sloupec']} uz je VARCHAR - OK</div>";
                    continue;
                }

                // Provest zmenu
                $pdo->exec($oprava['sql']);
                echo "<div class='success'>Opraveno: {$oprava['tabulka']}.{$oprava['sloupec']} -> VARCHAR(50)</div>";

            } catch (PDOException $e) {
                echo "<div class='error'>Chyba u {$oprava['tabulka']}.{$oprava['sloupec']}: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // Pridat chybejici sloupce
        echo "<div class='info'><strong>Pridavam chybejici sloupce...</strong></div>";
        foreach ($pridatSloupce as $pridani) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$pridani['tabulka']}` LIKE '{$pridani['sloupec']}'");
                if ($stmt->fetch()) {
                    echo "<div class='success'>Sloupec {$pridani['tabulka']}.{$pridani['sloupec']} uz existuje - OK</div>";
                    continue;
                }

                $pdo->exec($pridani['sql']);
                echo "<div class='success'>Pridan sloupec: {$pridani['tabulka']}.{$pridani['sloupec']}</div>";

            } catch (PDOException $e) {
                echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // Opravit ENUM hodnotu 'dokonceno' -> 'dokoncena'
        try {
            $pdo->exec("ALTER TABLE `wgs_hry_mistnosti` MODIFY COLUMN `stav` ENUM('cekani', 'hra', 'dokoncena') DEFAULT 'cekani'");
            echo "<div class='success'>Opraven ENUM stav v wgs_hry_mistnosti</div>";
        } catch (PDOException $e) {
            // Mozna uz je spravne
        }

        echo "<div class='success' style='margin-top: 20px; font-size: 1.2em;'><strong>MIGRACE DOKONCENA!</strong></div>";
        echo "<p>Nyni by melo sledovani online hracu fungovat spravne.</p>";

    } else {
        // Nahled
        echo "<div class='info'><strong>Problem:</strong> Sloupce user_id v hernich tabulkach jsou typu INT, ale v projektu se pouzivaji stringove ID (ADMIN001, TCH2025001, atd.).</div>";

        echo "<h2>Co bude provedeno:</h2>";

        echo "<table>";
        echo "<tr><th>Tabulka</th><th>Sloupec</th><th>Zmena</th></tr>";
        foreach ($opravy as $oprava) {
            echo "<tr>";
            echo "<td>{$oprava['tabulka']}</td>";
            echo "<td>{$oprava['sloupec']}</td>";
            echo "<td>INT -> VARCHAR(50)</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<div class='warning'><strong>POZOR:</strong> Migrace smaze existujici data z hernich tabulek (online hraci, chat, mistnosti), protoze obsahuji neplatne hodnoty (user_id=0).</div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT OPRAVU</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='hry.php' class='btn' style='background:#666;'>Zpet do Herni zony</a>";
echo " <a href='admin.php' class='btn' style='background:#666;'>Zpet do Adminu</a>";
echo "</div></body></html>";
?>
