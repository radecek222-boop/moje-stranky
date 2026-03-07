<?php
/**
 * Migrace: Přidání chybějících indexů pro optimalizaci výkonu
 *
 * Tento skript BEZPEČNĚ přidá chybějící indexy.
 * Lze spustit vícekrát - kontroluje existenci před přidáním.
 *
 * Indexy:
 * 1. wgs_users.user_id (VARCHAR) - používá se v JOIN dotazech (created_by = user_id)
 * 2. wgs_email_queue.notification_id - používá se v JOIN dotazech
 * 3. wgs_reklamace.updated_at - pro třídění a filtrování
 * 4. wgs_push_subscriptions.user_id - pro rychlé vyhledávání subscriptions uživatele
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Chybějící indexy 2026</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 900px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .uspech { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .chyba { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .preskoceno { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #111; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // Definice indexů k přidání
    $indexy = [
        [
            'tabulka'  => 'wgs_users',
            'index'    => 'idx_user_id_varchar',
            'sloupce'  => 'user_id',
            'duvod'    => 'Sloupec user_id (VARCHAR) se používá v JOIN dotazech (created_by = user_id) a LEFT JOIN wgs_users ON r.created_by = prodejce.user_id. Bez indexu je každý JOIN full table scan.',
            'sql'      => 'ALTER TABLE wgs_users ADD INDEX idx_user_id_varchar (user_id)',
        ],
        [
            'tabulka'  => 'wgs_email_queue',
            'index'    => 'idx_notification_id',
            'sloupce'  => 'notification_id',
            'duvod'    => 'Sloupec notification_id se používá v JOIN dotazech na wgs_notifications. Bez indexu je JOIN pomalý.',
            'sql'      => 'ALTER TABLE wgs_email_queue ADD INDEX idx_notification_id (notification_id)',
        ],
        [
            'tabulka'  => 'wgs_reklamace',
            'index'    => 'idx_updated_at',
            'sloupce'  => 'updated_at',
            'duvod'    => 'Sloupec updated_at se používá pro třídění v admin pohledech. Index zrychlí ORDER BY updated_at DESC dotazy.',
            'sql'      => 'ALTER TABLE wgs_reklamace ADD INDEX idx_updated_at (updated_at)',
        ],
        [
            'tabulka'  => 'wgs_push_subscriptions',
            'index'    => 'idx_push_user_id',
            'sloupce'  => 'user_id',
            'duvod'    => 'Sloupec user_id se používá při vyhledávání subscriptions konkrétního uživatele pro push notifikace.',
            'sql'      => 'ALTER TABLE wgs_push_subscriptions ADD INDEX idx_push_user_id (user_id)',
        ],
    ];

    echo "<h1>Migrace: Přidání chybějících indexů (2026-03-07)</h1>";

    // Pomocná funkce - zkontroluj existenci indexu
    $zkontrolujIndex = function($pdo, $tabulka, $index) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE table_schema = DATABASE()
            AND table_name = :tabulka
            AND index_name = :index
        ");
        $stmt->execute(['tabulka' => $tabulka, 'index' => $index]);
        return (int)$stmt->fetchColumn() > 0;
    };

    // Zkontroluj existenci tabulek
    $zkontrolujTabulku = function($pdo, $tabulka) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
            AND table_name = :tabulka
        ");
        $stmt->execute(['tabulka' => $tabulka]);
        return (int)$stmt->fetchColumn() > 0;
    };

    if (!isset($_GET['execute']) || $_GET['execute'] !== '1') {

        // Náhled - zobrazit co bude provedeno
        echo "<div class='info'><strong>NÁHLED ZMĚN</strong> - Zkontrolujte plán před spuštěním.</div>";

        echo "<table>
            <thead>
                <tr>
                    <th>Tabulka</th>
                    <th>Název indexu</th>
                    <th>Sloupce</th>
                    <th>Stav</th>
                    <th>Důvod</th>
                </tr>
            </thead>
            <tbody>";

        foreach ($indexy as $idx) {
            $tabulkaExistuje = $zkontrolujTabulku($pdo, $idx['tabulka']);
            $indexExistuje = $tabulkaExistuje && $zkontrolujIndex($pdo, $idx['tabulka'], $idx['index']);

            $stav = !$tabulkaExistuje
                ? "<span style='color:#dc3545'>Tabulka neexistuje</span>"
                : ($indexExistuje
                    ? "<span style='color:#856404'>Index již existuje</span>"
                    : "<span style='color:#155724'>Bude přidán</span>");

            echo "<tr>
                <td><code>{$idx['tabulka']}</code></td>
                <td><code>{$idx['index']}</code></td>
                <td><code>{$idx['sloupce']}</code></td>
                <td>{$stav}</td>
                <td style='font-size:0.9em'>{$idx['duvod']}</td>
            </tr>";
        }

        echo "</tbody></table>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='/admin.php' class='btn' style='background:#666'>Zpět do admin</a>";

    } else {

        // Spustit migraci
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $celkem = 0;
        $pridano = 0;
        $preskoceno = 0;
        $chyby = 0;

        foreach ($indexy as $idx) {
            $celkem++;
            $tabulkaExistuje = $zkontrolujTabulku($pdo, $idx['tabulka']);

            if (!$tabulkaExistuje) {
                echo "<div class='preskoceno'><strong>PŘESKOČENO:</strong> Tabulka <code>{$idx['tabulka']}</code> neexistuje v aktuální databázi.</div>";
                $preskoceno++;
                continue;
            }

            $indexExistuje = $zkontrolujIndex($pdo, $idx['tabulka'], $idx['index']);

            if ($indexExistuje) {
                echo "<div class='preskoceno'><strong>PŘESKOČENO:</strong> Index <code>{$idx['index']}</code> na tabulce <code>{$idx['tabulka']}</code> již existuje.</div>";
                $preskoceno++;
                continue;
            }

            try {
                $pdo->exec($idx['sql']);
                echo "<div class='uspech'><strong>PŘIDÁN:</strong> Index <code>{$idx['index']}</code> na tabulce <code>{$idx['tabulka']}</code> ({$idx['sloupce']}).</div>";
                $pridano++;
            } catch (PDOException $e) {
                echo "<div class='chyba'><strong>CHYBA:</strong> Nepodařilo se přidat index <code>{$idx['index']}</code> na <code>{$idx['tabulka']}</code>:<br>" . htmlspecialchars($e->getMessage()) . "</div>";
                $chyby++;
            }
        }

        echo "<hr>
        <div class='info'>
            <strong>VÝSLEDEK MIGRACE:</strong><br>
            Celkem indexů: {$celkem}<br>
            Přidáno: {$pridano}<br>
            Přeskočeno (existuje nebo tabulka chybí): {$preskoceno}<br>
            Chyby: {$chyby}
        </div>";

        echo "<a href='/admin.php' class='btn'>Zpět do admin panelu</a>";
        echo "<a href='?' class='btn' style='background:#666'>Znovu zkontrolovat</a>";
    }

} catch (Exception $e) {
    echo "<div class='chyba'><strong>KRITICKÁ CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
