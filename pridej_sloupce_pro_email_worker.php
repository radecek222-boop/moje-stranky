<?php
/**
 * Migrace: Přidání sloupců pro Email Worker
 *
 * Tento skript přidá chybějící sloupce do tabulky wgs_email_queue:
 * - attempts (místo retry_count)
 * - max_attempts (maximální počet pokusů)
 * - scheduled_at (plánovaný čas odeslání)
 * - priority (priorita emailu)
 * - recipient_email (kopie to_email pro kompatibilitu)
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
    <title>Migrace: Email Worker Sloupce</title>
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
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // Kontrola před migrací
    echo "<h1>Migrace: Email Worker Sloupce</h1>";

    echo "<div class='info'><strong>KONTROLA AKTUÁLNÍ STRUKTURY...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_email_queue");
    $existujiciSloupce = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existujiciSloupce[] = $row['Field'];
    }

    echo "<div class='info'>";
    echo "<strong>Existující sloupce:</strong><br>";
    echo implode(', ', $existujiciSloupce);
    echo "</div>";

    $potrebneSloupce = [
        'attempts' => 'INT DEFAULT 0',
        'max_attempts' => 'INT DEFAULT 3',
        'scheduled_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'priority' => 'INT DEFAULT 0',
        'recipient_email' => 'VARCHAR(255)'
    ];

    $chybejiciSloupce = [];
    foreach ($potrebneSloupce as $nazev => $typ) {
        if (!in_array($nazev, $existujiciSloupce)) {
            $chybejiciSloupce[$nazev] = $typ;
        }
    }

    if (empty($chybejiciSloupce)) {
        echo "<div class='success'>";
        echo "<strong>Všechny potřebné sloupce již existují!</strong><br>";
        echo "Email worker může fungovat.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>Chybějící sloupce:</strong><br>";
        foreach ($chybejiciSloupce as $nazev => $typ) {
            echo "• <code>$nazev</code> ($typ)<br>";
        }
        echo "</div>";

        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            foreach ($chybejiciSloupce as $nazev => $typ) {
                try {
                    $sql = "ALTER TABLE wgs_email_queue ADD COLUMN $nazev $typ";
                    $pdo->exec($sql);

                    echo "<div class='success'>";
                    echo "✓ Přidán sloupec <code>$nazev</code>";
                    echo "</div>";
                } catch (PDOException $e) {
                    echo "<div class='error'>";
                    echo "✗ Chyba při přidávání sloupce <code>$nazev</code>:<br>";
                    echo htmlspecialchars($e->getMessage());
                    echo "</div>";
                }
            }

            // Zkopírovat data z retry_count do attempts
            if (in_array('retry_count', $existujiciSloupce) && in_array('attempts', $chybejiciSloupce)) {
                try {
                    $pdo->exec("UPDATE wgs_email_queue SET attempts = retry_count WHERE attempts = 0");
                    echo "<div class='success'>";
                    echo "✓ Zkopírována data z <code>retry_count</code> do <code>attempts</code>";
                    echo "</div>";
                } catch (PDOException $e) {
                    echo "<div class='error'>";
                    echo "✗ Chyba při kopírování dat: " . htmlspecialchars($e->getMessage());
                    echo "</div>";
                }
            }

            // Zkopírovat data z to_email do recipient_email
            if (in_array('to_email', $existujiciSloupce) && isset($chybejiciSloupce['recipient_email'])) {
                try {
                    $pdo->exec("UPDATE wgs_email_queue SET recipient_email = to_email WHERE recipient_email IS NULL OR recipient_email = ''");
                    echo "<div class='success'>";
                    echo "✓ Zkopírována data z <code>to_email</code> do <code>recipient_email</code>";
                    echo "</div>";
                } catch (PDOException $e) {
                    echo "<div class='error'>";
                    echo "✗ Chyba při kopírování emailů: " . htmlspecialchars($e->getMessage());
                    echo "</div>";
                }
            }

            echo "<div class='success'>";
            echo "<strong>✓ MIGRACE DOKONČENA!</strong><br>";
            echo "Email worker nyní může fungovat správně.";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>DALŠÍ KROKY:</strong><br>";
            echo "1. Otevři Security centrum → SMTP Konfigurace<br>";
            echo "2. Zkontroluj SMTP nastavení<br>";
            echo "3. Nastav Webcron na URL: <code>https://www.wgs-service.cz/cron/process-email-queue.php</code><br>";
            echo "4. Webcron by měl běžet každých 5-10 minut";
            echo "</div>";

        } else {
            // Náhled co bude provedeno
            echo "<div class='warning'>";
            echo "<strong>Pro spuštění migrace klikni na tlačítko:</strong>";
            echo "</div>";
            echo "<a href='?execute=1' class='btn'>✓ SPUSTIT MIGRACI</a>";
        }
    }

    // Kontrola Webcron nastavení
    echo "<h2 style='margin-top: 2rem; color: #2D5016;'>Webcron Nastavení</h2>";
    echo "<div class='info'>";
    echo "<strong>Pro funkční odesílání emailů je potřeba nastavit Webcron:</strong><br><br>";
    echo "1. Přihlaš se do administrace hostingu<br>";
    echo "2. Najdi sekci 'Webcron' nebo 'Plánované úlohy'<br>";
    echo "3. Přidej novou úlohu s těmito parametry:<br>";
    echo "   • <strong>URL:</strong> <code>https://www.wgs-service.cz/cron/process-email-queue.php</code><br>";
    echo "   • <strong>Interval:</strong> Každých 5-10 minut<br>";
    echo "   • <strong>Metoda:</strong> GET<br><br>";
    echo "<strong>Alternativně</strong> můžeš spustit ručně:<br>";
    echo "<a href='../cron/process-email-queue.php' target='_blank' class='btn'>Spustit Email Worker Ručně</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #ddd; text-align: center;'>";
echo "<a href='admin.php?tab=notifications&section=smtp'>← Zpět do Email & SMS</a>";
echo "</div>";

echo "</div></body></html>";
?>
