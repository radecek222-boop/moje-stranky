<?php
/**
 * Migrace v2: Přidání 2 chybějících indexů s ověřeným přínosem
 *
 * Indexy:
 *
 * 1. wgs_users.user_id  (idx_user_id_varchar)
 *    Cíl: JOIN dotazy LEFT JOIN wgs_users ON r.created_by = u.user_id
 *    Použití: api/documents_api.php, protokol.php, includes/notifikace_helper.php,
 *             api/hry_api.php, cron/send-reminders.php (5+ souborů)
 *    Bez indexu: každý JOIN = full table scan wgs_users
 *
 * 2. wgs_email_queue.notification_id  (idx_notification_id)
 *    Cíl: JOIN dotazy LEFT JOIN wgs_notifications ON eq.notification_id = n.id
 *    Použití: scripts/add_foreign_keys.php, includes/admin_email_sms.php,
 *             tools/test_pagination_debug.php (3 soubory)
 *    Bez indexu: každý JOIN = full table scan wgs_email_queue
 *
 * ZÁMĚRNĚ VYNECHÁNO:
 * - wgs_reklamace.updated_at: pouze dev/diagnostické soubory, nízká priorita
 * - wgs_push_subscriptions.user_id: struktura tabulky není v SQL migracích,
 *   přidáno do skriptu v1 jako přeskočení pokud tabulka neexistuje
 *
 * Bezpečnost: skript kontroluje existenci indexu před přidáním — idempotentní.
 * Lze spustit opakovaně bez rizika.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.');
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace v2: Indexy wgs_users + wgs_email_queue</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 860px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 30px; }
        .uspech  { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 12px; border-radius: 5px; margin: 8px 0; }
        .chyba   { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
                   padding: 12px; border-radius: 5px; margin: 8px 0; }
        .info    { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;
                   padding: 12px; border-radius: 5px; margin: 8px 0; }
        .skip    { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;
                   padding: 12px; border-radius: 5px; margin: 8px 0; }
        .btn     { display: inline-block; padding: 12px 26px; background: #333; color: white;
                   text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0;
                   border: none; cursor: pointer; font-size: 15px; }
        .btn:hover { background: #111; }
        .btn-back { background: #666; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; font-size: 0.9em; }
        .query-box { background: #f0f0f0; padding: 12px; border-radius: 5px;
                     font-family: 'Courier New', monospace; font-size: 0.85em;
                     white-space: pre-wrap; margin: 8px 0; }
    </style>
</head>
<body>
<div class='container'>
<h1>Migrace v2 — Chybějící indexy (2026-03-07)</h1>";

// ---- Pomocné funkce ----
try {
    $pdo = getDbConnection();

    $existujeIndex = function (string $tabulka, string $index) use ($pdo): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE table_schema = DATABASE()
              AND table_name   = :tabulka
              AND index_name   = :index
        ");
        $stmt->execute(['tabulka' => $tabulka, 'index' => $index]);
        return (int)$stmt->fetchColumn() > 0;
    };

    $existujeTabulka = function (string $tabulka) use ($pdo): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
              AND table_name   = :tabulka
        ");
        $stmt->execute(['tabulka' => $tabulka]);
        return (int)$stmt->fetchColumn() > 0;
    };

    // ---- Definice indexů ----
    $indexy = [
        [
            'tabulka'       => 'wgs_users',
            'index'         => 'idx_user_id_varchar',
            'sloupce'       => 'user_id',
            'sql'           => 'ALTER TABLE wgs_users ADD INDEX idx_user_id_varchar (user_id)',
            'query_vzor'    => "LEFT JOIN wgs_users u ON r.created_by = u.user_id",
            'soubory'       => 'api/documents_api.php, protokol.php, includes/notifikace_helper.php, api/hry_api.php, cron/send-reminders.php',
            'prekryvajici'  => 'Žádný — idx_email a idx_role jsou na jiných sloupcích',
            'write_overhead'=> 'Minimální — wgs_users má nízký počet zápisů (registrace, update profilu)',
        ],
        [
            'tabulka'       => 'wgs_email_queue',
            'index'         => 'idx_notification_id',
            'sloupce'       => 'notification_id',
            'sql'           => 'ALTER TABLE wgs_email_queue ADD INDEX idx_notification_id (notification_id)',
            'query_vzor'    => "LEFT JOIN wgs_notifications n ON eq.notification_id = n.id",
            'soubory'       => 'scripts/add_foreign_keys.php, includes/admin_email_sms.php, tools/test_pagination_debug.php',
            'prekryvajici'  => 'Žádný — existující indexy: idx_status, idx_scheduled, idx_priority (jiné sloupce)',
            'write_overhead'=> 'Akceptovatelný — cca 5-10% overhead na INSERT do email fronty; bez indexu je JOIN pomalejší',
        ],
    ];

    // ---- Náhled nebo spuštění ----
    if (!isset($_GET['execute']) || $_GET['execute'] !== '1') {

        echo "<div class='info'><strong>NÁHLED</strong> — Zkontrolujte plán, pak klikněte na tlačítko níže.</div>";

        foreach ($indexy as $idx) {
            $tabExistuje   = $existujeTabulka($idx['tabulka']);
            $idxExistuje   = $tabExistuje && $existujeIndex($idx['tabulka'], $idx['index']);
            $stavText      = !$tabExistuje
                ? '<span style="color:#dc3545">Tabulka neexistuje</span>'
                : ($idxExistuje
                    ? '<span style="color:#856404">Index již existuje — přeskočí se</span>'
                    : '<span style="color:#155724">Bude přidán</span>');

            echo "
            <h2>Index: <code>{$idx['index']}</code> na <code>{$idx['tabulka']}</code></h2>
            <table>
              <tr><th>Sloupce</th><td><code>{$idx['sloupce']}</code></td></tr>
              <tr><th>Stav</th><td>{$stavText}</td></tr>
              <tr><th>Cílový query vzor</th><td class='query-box'>{$idx['query_vzor']}</td></tr>
              <tr><th>Soubory s tímto vzorem</th><td>{$idx['soubory']}</td></tr>
              <tr><th>Překrývající index?</th><td>{$idx['prekryvajici']}</td></tr>
              <tr><th>Write overhead</th><td>{$idx['write_overhead']}</td></tr>
              <tr><th>SQL příkaz</th><td class='query-box'>{$idx['sql']}</td></tr>
            </table>";
        }

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>
              <a href='/admin.php' class='btn btn-back'>Zpět do admin</a>";

    } else {

        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pridano     = 0;
        $preskoceno  = 0;
        $chyby       = 0;

        foreach ($indexy as $idx) {

            if (!$existujeTabulka($idx['tabulka'])) {
                echo "<div class='skip'>PŘESKOČENO: Tabulka <code>{$idx['tabulka']}</code> neexistuje v aktuální DB.</div>";
                $preskoceno++;
                continue;
            }

            if ($existujeIndex($idx['tabulka'], $idx['index'])) {
                echo "<div class='skip'>PŘESKOČENO: Index <code>{$idx['index']}</code> na <code>{$idx['tabulka']}</code> již existuje.</div>";
                $preskoceno++;
                continue;
            }

            try {
                $pdo->exec($idx['sql']);
                echo "<div class='uspech'>PŘIDÁN: Index <code>{$idx['index']}</code> na <code>{$idx['tabulka']}.{$idx['sloupce']}</code>.</div>";
                $pridano++;
            } catch (PDOException $e) {
                echo "<div class='chyba'>CHYBA při přidávání <code>{$idx['index']}</code>: "
                    . htmlspecialchars($e->getMessage()) . "</div>";
                $chyby++;
            }
        }

        echo "<hr><div class='info'>
            <strong>VÝSLEDEK:</strong><br>
            Přidáno: {$pridano} | Přeskočeno: {$preskoceno} | Chyby: {$chyby}
        </div>";

        echo "<a href='/admin.php' class='btn'>Zpět do admin panelu</a>
              <a href='?' class='btn btn-back'>Znovu zkontrolovat</a>";
    }

} catch (Exception $e) {
    echo "<div class='chyba'><strong>KRITICKÁ CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
