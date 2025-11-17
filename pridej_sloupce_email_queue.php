<?php
/**
 * Migrace: Oprava sloupců v tabulce wgs_email_queue
 *
 * Tento skript BEZPEČNĚ přidá chybějící sloupce do tabulky wgs_email_queue.
 * Problém: Kód používá názvy to_email, retry_count, last_error, updated_at
 *          ale tabulka má recipient_email, attempts, error_message (a chybí updated_at).
 *
 * Řešení: Přidá nové sloupce a zkopíruje data z původních sloupců.
 *
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
    <title>Migrace: Oprava sloupců wgs_email_queue</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
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
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; font-size: 1em; cursor: pointer; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa;
                border-left: 4px solid #2D5016; }
        .step-title { font-weight: bold; font-size: 1.1em; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Migrace: Oprava sloupců v tabulce wgs_email_queue</h1>";

    // 1. Kontrolní fáze
    echo "<div class='info'><strong>FÁZE 1: KONTROLA STÁVAJÍCÍ STRUKTURY...</strong></div>";

    // Zjistit existující sloupce
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_email_queue");
    $existujiciSloupce = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existujiciSloupce[] = $row['Field'];
    }

    echo "<div class='step'>";
    echo "<div class='step-title'>📋 Existující sloupce v tabulce:</div>";
    echo "<code>" . implode(", ", $existujiciSloupce) . "</code>";
    echo "</div>";

    // Zjistit které sloupce chybí
    $pozadovaneSloupce = ['to_email', 'retry_count', 'last_error', 'updated_at'];
    $chybejiciSloupce = [];

    foreach ($pozadovaneSloupce as $sloupec) {
        if (!in_array($sloupec, $existujiciSloupce)) {
            $chybejiciSloupce[] = $sloupec;
        }
    }

    if (empty($chybejiciSloupce)) {
        echo "<div class='success'>";
        echo "<strong>✅ VŠECHNY SLOUPCE JIŽ EXISTUJÍ</strong><br>";
        echo "Tabulka má všechny požadované sloupce. Migrace není potřeba.";
        echo "</div>";
        echo "<a href='admin.php?tab=control_center_sql' class='btn'>← Zpět do Admin Panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='warning'>";
    echo "<strong>⚠️ NALEZENY CHYBĚJÍCÍ SLOUPCE:</strong><br>";
    echo "<ul>";
    foreach ($chybejiciSloupce as $sloupec) {
        echo "<li><code>{$sloupec}</code></li>";
    }
    echo "</ul>";
    echo "</div>";

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>FÁZE 2: SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            $provedeneOperace = [];

            // Přidat chybějící sloupce
            if (in_array('to_email', $chybejiciSloupce)) {
                $pdo->exec("ALTER TABLE wgs_email_queue ADD COLUMN to_email VARCHAR(255) NULL AFTER notification_id");

                // Zkopírovat data z recipient_email do to_email
                if (in_array('recipient_email', $existujiciSloupce)) {
                    $pdo->exec("UPDATE wgs_email_queue SET to_email = recipient_email WHERE to_email IS NULL");
                    $provedeneOperace[] = "Přidán sloupec <code>to_email</code> a zkopírována data z <code>recipient_email</code>";
                } else {
                    $provedeneOperace[] = "Přidán sloupec <code>to_email</code>";
                }
            }

            if (in_array('retry_count', $chybejiciSloupce)) {
                $pdo->exec("ALTER TABLE wgs_email_queue ADD COLUMN retry_count INT DEFAULT 0 AFTER status");

                // Zkopírovat data z attempts do retry_count
                if (in_array('attempts', $existujiciSloupce)) {
                    $pdo->exec("UPDATE wgs_email_queue SET retry_count = attempts WHERE retry_count = 0");
                    $provedeneOperace[] = "Přidán sloupec <code>retry_count</code> a zkopírována data z <code>attempts</code>";
                } else {
                    $provedeneOperace[] = "Přidán sloupec <code>retry_count</code>";
                }
            }

            if (in_array('last_error', $chybejiciSloupce)) {
                $pdo->exec("ALTER TABLE wgs_email_queue ADD COLUMN last_error TEXT NULL AFTER retry_count");

                // Zkopírovat data z error_message do last_error
                if (in_array('error_message', $existujiciSloupce)) {
                    $pdo->exec("UPDATE wgs_email_queue SET last_error = error_message WHERE last_error IS NULL");
                    $provedeneOperace[] = "Přidán sloupec <code>last_error</code> a zkopírována data z <code>error_message</code>";
                } else {
                    $provedeneOperace[] = "Přidán sloupec <code>last_error</code>";
                }
            }

            if (in_array('updated_at', $chybejiciSloupce)) {
                $pdo->exec("ALTER TABLE wgs_email_queue ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

                // Nastavit updated_at na created_at pro existující záznamy
                $pdo->exec("UPDATE wgs_email_queue SET updated_at = created_at WHERE updated_at IS NULL");
                $provedeneOperace[] = "Přidán sloupec <code>updated_at</code> a nastaven na <code>created_at</code> pro existující záznamy";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
            echo "<div class='step-title'>Provedené operace:</div>";
            echo "<ul>";
            foreach ($provedeneOperace as $operace) {
                echo "<li>{$operace}</li>";
            }
            echo "</ul>";
            echo "</div>";

            // Zobrazit novou strukturu
            echo "<div class='step'>";
            echo "<div class='step-title'>📊 Nová struktura tabulky:</div>";
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_email_queue");
            echo "<table>";
            echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Výchozí</th></tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td><code>{$row['Field']}</code></td>";
                echo "<td>{$row['Type']}</td>";
                echo "<td>{$row['Null']}</td>";
                echo "<td>" . ($row['Default'] ?? '<em>NULL</em>') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";

            // Počet záznamů
            $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_email_queue");
            $pocet = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

            echo "<div class='info'>";
            echo "<strong>📦 Ovlivněno záznamů:</strong> {$pocet}";
            echo "</div>";

            echo "<a href='admin.php?tab=control_center_sql' class='btn'>← Zpět do Admin Panelu</a>";
            echo "<a href='vsechny_tabulky.php' class='btn'>📋 Zobrazit všechny tabulky</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI MIGRACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
            echo "<a href='pridej_sloupce_email_queue.php' class='btn'>🔄 Zkusit znovu</a>";
        }
    } else {
        // Náhled co bude provedeno
        echo "<div class='step'>";
        echo "<div class='step-title'>📝 CO BUDE PROVEDENO:</div>";
        echo "<table>";
        echo "<tr><th>Nový sloupec</th><th>Typ</th><th>Popis</th></tr>";

        if (in_array('to_email', $chybejiciSloupce)) {
            echo "<tr>";
            echo "<td><code>to_email</code></td>";
            echo "<td>VARCHAR(255)</td>";
            echo "<td>Email příjemce (zkopírováno z recipient_email)</td>";
            echo "</tr>";
        }

        if (in_array('retry_count', $chybejiciSloupce)) {
            echo "<tr>";
            echo "<td><code>retry_count</code></td>";
            echo "<td>INT DEFAULT 0</td>";
            echo "<td>Počet pokusů o odeslání (zkopírováno z attempts)</td>";
            echo "</tr>";
        }

        if (in_array('last_error', $chybejiciSloupce)) {
            echo "<tr>";
            echo "<td><code>last_error</code></td>";
            echo "<td>TEXT NULL</td>";
            echo "<td>Poslední chybová zpráva (zkopírováno z error_message)</td>";
            echo "</tr>";
        }

        if (in_array('updated_at', $chybejiciSloupce)) {
            echo "<tr>";
            echo "<td><code>updated_at</code></td>";
            echo "<td>TIMESTAMP NULL</td>";
            echo "<td>Datum poslední aktualizace (auto-update)</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "</div>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
        echo "<ul>";
        echo "<li>Migrace přidá nové sloupce a zkopíruje data z původních sloupců</li>";
        echo "<li>Původní sloupce (recipient_email, attempts, error_message) ZŮSTANOU zachovány</li>";
        echo "<li>Pokud budete chtít původní sloupce odstranit, můžete to udělat později ručně</li>";
        echo "<li>Tato operace je bezpečná a NEVRATNÁ (doporučujeme nejprve vytvořit zálohu)</li>";
        echo "</ul>";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>✅ SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php?tab=control_center_sql' class='btn' style='background: #6c757d;'>← Zpět bez změn</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ KRITICKÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
    echo "<a href='admin.php?tab=control_center_sql' class='btn'>← Zpět do Admin Panelu</a>";
}

echo "</div></body></html>";
?>