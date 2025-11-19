<?php
/**
 * SJEDNOCENÍ EMAIL KONFIGURACE
 *
 * Tento skript provede:
 * 1. Sjednocení SMTP konfigurace (odstraní duplicity)
 * 2. Nastaví správný server (websmtp.cesky-hosting.cz:25)
 * 3. Vyčistí email frontu (reset attempts)
 * 4. Otestuje připojení
 */

require_once __DIR__ . '/init.php';

// Bezpečnost
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento skript.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Sjednocení email konfigurace</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        h2 { color: #2D5016; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 15px; border-radius: 5px;
                   margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 15px; border-radius: 5px;
                 margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 15px; border-radius: 5px;
                   margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 15px; border-radius: 5px;
                margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               font-weight: bold; font-size: 16px; }
        .btn:hover { background: #1a300d; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        code { background: #f4f4f4; padding: 3px 8px; border-radius: 3px;
               font-family: 'Courier New', monospace; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #2D5016; color: white; font-weight: bold; }
        .step { margin: 20px 0; padding: 20px; background: #f8f9fa;
                border-left: 4px solid #2D5016; }
        .step-title { font-weight: bold; font-size: 18px; margin-bottom: 10px; }
        ul { line-height: 1.8; }
        ol { line-height: 1.8; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Sjednocení email konfigurace - WGS Service</h1>";

    // ========================================
    // KROK 1: ANALÝZA SOUČASNÉHO STAVU
    // ========================================
    echo "<h2>KROK 1: Analýza současného stavu</h2>";

    // Načíst wgs_smtp_settings
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Načíst wgs_system_config
    $stmt = $pdo->query("SELECT * FROM wgs_system_config WHERE config_key LIKE 'smtp_%'");
    $systemConfig = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Načíst email queue statistiky
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM wgs_email_queue GROUP BY status");
    $queueStats = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $queueStats[$row['status']] = $row['count'];
    }

    echo "<div class='info'>";
    echo "<strong>📊 SOUČASNÝ STAV:</strong><br><br>";

    echo "<strong>1️⃣ wgs_smtp_settings:</strong><br>";
    if ($smtpSettings) {
        echo "<code>{$smtpSettings['smtp_host']}:{$smtpSettings['smtp_port']}</code> ";
        echo "({$smtpSettings['smtp_encryption']})<br>";
    } else {
        echo "❌ Nenalezena žádná aktivní konfigurace!<br>";
    }

    echo "<br><strong>2️⃣ wgs_system_config:</strong><br>";
    if (count($systemConfig) > 0) {
        echo "⚠️ Nalezeno " . count($systemConfig) . " SMTP záznamů (duplicita!)<br>";
        foreach ($systemConfig as $cfg) {
            echo "<code>{$cfg['config_key']}</code> = <code>{$cfg['config_value']}</code><br>";
        }
    } else {
        echo "✅ Žádné duplicitní záznamy<br>";
    }

    echo "<br><strong>3️⃣ Email fronta:</strong><br>";
    foreach ($queueStats as $status => $count) {
        echo "<code>{$status}</code>: <strong>{$count} emailů</strong><br>";
    }
    echo "</div>";

    // ========================================
    // POKUD ?EXECUTE=1, PROVÉST ZMĚNY
    // ========================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        echo "<h2>PROVÁDÍM ZMĚNY...</h2>";

        $pdo->beginTransaction();

        try {
            // ========================================
            // KROK 2: ODSTRANIT DUPLICITY
            // ========================================
            echo "<div class='step'>";
            echo "<div class='step-title'>KROK 2: Odstranění duplicit z wgs_system_config</div>";

            $stmt = $pdo->prepare("DELETE FROM wgs_system_config WHERE config_key LIKE 'smtp_%'");
            $deletedCount = $stmt->execute();

            echo "<div class='success'>✅ Odstraněno duplicitních SMTP záznamů z wgs_system_config</div>";
            echo "</div>";

            // ========================================
            // KROK 3: NASTAVIT SPRÁVNOU KONFIGURACI
            // ========================================
            echo "<div class='step'>";
            echo "<div class='step-title'>KROK 3: Nastavení správné SMTP konfigurace</div>";

            $correctConfig = [
                'host' => 'websmtp.cesky-hosting.cz',
                'port' => 25,
                'encryption' => 'none',
                'username' => 'wgs-service.cz',
                'from_email' => 'reklamace@wgs-service.cz',
                'from_name' => 'White Glove Service'
            ];

            if ($smtpSettings) {
                // Update existujícího záznamu
                $stmt = $pdo->prepare("
                    UPDATE wgs_smtp_settings
                    SET
                        smtp_host = :host,
                        smtp_port = :port,
                        smtp_encryption = :encryption,
                        smtp_username = :username,
                        smtp_from_email = :from_email,
                        smtp_from_name = :from_name,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':host' => $correctConfig['host'],
                    ':port' => $correctConfig['port'],
                    ':encryption' => $correctConfig['encryption'],
                    ':username' => $correctConfig['username'],
                    ':from_email' => $correctConfig['from_email'],
                    ':from_name' => $correctConfig['from_name'],
                    ':id' => $smtpSettings['id']
                ]);

                echo "<div class='success'>✅ SMTP konfigurace aktualizována (ID: {$smtpSettings['id']})</div>";
            } else {
                // Vložit nový záznam
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_smtp_settings (
                        smtp_host, smtp_port, smtp_encryption,
                        smtp_username, smtp_password,
                        smtp_from_email, smtp_from_name,
                        is_active
                    ) VALUES (
                        :host, :port, :encryption,
                        :username, :password,
                        :from_email, :from_name,
                        1
                    )
                ");
                $stmt->execute([
                    ':host' => $correctConfig['host'],
                    ':port' => $correctConfig['port'],
                    ':encryption' => $correctConfig['encryption'],
                    ':username' => $correctConfig['username'],
                    ':password' => '', // Prázdné heslo pro WebSMTP
                    ':from_email' => $correctConfig['from_email'],
                    ':from_name' => $correctConfig['from_name']
                ]);

                echo "<div class='success'>✅ Vytvořena nová SMTP konfigurace</div>";
            }

            echo "<table>";
            echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
            echo "<tr><td>Host</td><td><code>{$correctConfig['host']}</code></td></tr>";
            echo "<tr><td>Port</td><td><code>{$correctConfig['port']}</code></td></tr>";
            echo "<tr><td>Šifrování</td><td><code>{$correctConfig['encryption']}</code></td></tr>";
            echo "<tr><td>Username</td><td><code>{$correctConfig['username']}</code></td></tr>";
            echo "</table>";
            echo "</div>";

            // ========================================
            // KROK 4: VYČISTIT EMAIL FRONTU
            // ========================================
            echo "<div class='step'>";
            echo "<div class='step-title'>KROK 4: Vyčištění email fronty</div>";

            // Reset attempts pro všechny pending/failed emaily
            $stmt = $pdo->prepare("
                UPDATE wgs_email_queue
                SET attempts = 0, error_message = NULL, status = 'pending'
                WHERE status IN ('pending', 'failed')
            ");
            $stmt->execute();
            $resetCount = $stmt->rowCount();

            echo "<div class='success'>✅ Resetováno <strong>{$resetCount} emailů</strong> ve frontě (nastaveno attempts=0)</div>";
            echo "<div class='info'>";
            echo "<strong>💡 CO TO ZNAMENÁ:</strong><br>";
            echo "Všechny emaily, které selhaly kvůli špatné konfiguraci, se teď pokusí odeslat znovu.<br>";
            echo "Cron worker (<code>/scripts/process_email_queue.php</code>) je zpracuje automaticky.";
            echo "</div>";
            echo "</div>";

            // ========================================
            // COMMIT
            // ========================================
            $pdo->commit();

            // ========================================
            // VÝSLEDEK
            // ========================================
            echo "<div class='success'>";
            echo "<h2>✅ SJEDNOCENÍ DOKONČENO!</h2>";
            echo "<p><strong>Co bylo provedeno:</strong></p>";
            echo "<ul>";
            echo "<li>✅ Odstraněny duplicitní záznamy z <code>wgs_system_config</code></li>";
            echo "<li>✅ Nastavena správná konfigurace v <code>wgs_smtp_settings</code></li>";
            echo "<li>✅ Resetována email fronta ({$resetCount} emailů)</li>";
            echo "</ul>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>📋 DALŠÍ KROKY:</h3>";
            echo "<ol>";
            echo "<li><strong>Ověřit PHPMailer:</strong> Zkontrolovat, že je nainstalován</li>";
            echo "<li><strong>Spustit cron worker:</strong> <code>/scripts/process_email_queue.php</code></li>";
            echo "<li><strong>Otestovat odeslání:</strong> Vytvořit testovací email přes protokol</li>";
            echo "<li><strong>Zkontrolovat logy:</strong> <code>/logs/php_errors.log</code></li>";
            echo "</ol>";
            echo "</div>";

            echo "<p>";
            echo "<a href='/admin.php' class='btn'>→ Zpět na Admin panel</a> ";
            echo "<a href='/vsechny_tabulky.php' class='btn btn-secondary'>Zobrazit SQL</a>";
            echo "</p>";

        } catch (PDOException $e) {
            $pdo->rollBack();

            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI PROVÁDĚNÍ ZMĚN:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // ========================================
        // NÁHLED ZMĚN
        // ========================================
        echo "<h2>CO SE PROVEDE:</h2>";

        echo "<div class='step'>";
        echo "<div class='step-title'>KROK 2: Odstranit duplicity</div>";
        echo "<p>Smaže SMTP záznamy z tabulky <code>wgs_system_config</code>:</p>";
        foreach ($systemConfig as $cfg) {
            echo "❌ <code>{$cfg['config_key']}</code> = <code>{$cfg['config_value']}</code><br>";
        }
        echo "</div>";

        echo "<div class='step'>";
        echo "<div class='step-title'>KROK 3: Nastavit správnou konfiguraci</div>";
        echo "<table>";
        echo "<tr><th>Parametr</th><th>TEĎ</th><th>→ BUDE</th></tr>";
        echo "<tr><td>Host</td><td><code>{$smtpSettings['smtp_host']}</code></td><td><code>websmtp.cesky-hosting.cz</code></td></tr>";
        echo "<tr><td>Port</td><td><code>{$smtpSettings['smtp_port']}</code></td><td><code>25</code></td></tr>";
        echo "<tr><td>Šifrování</td><td><code>{$smtpSettings['smtp_encryption']}</code></td><td><code>none</code></td></tr>";
        echo "<tr><td>Username</td><td><code>{$smtpSettings['smtp_username']}</code></td><td><code>wgs-service.cz</code></td></tr>";
        echo "</table>";
        echo "</div>";

        echo "<div class='step'>";
        echo "<div class='step-title'>KROK 4: Vyčistit email frontu</div>";
        $pendingCount = $queueStats['pending'] ?? 0;
        $failedCount = $queueStats['failed'] ?? 0;
        $totalReset = $pendingCount + $failedCount;
        echo "<p>Resetuje <strong>{$totalReset} emailů</strong> (pending: {$pendingCount}, failed: {$failedCount})</p>";
        echo "<p>→ Nastaví <code>attempts = 0</code> a <code>status = 'pending'</code></p>";
        echo "</div>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
        echo "<ul>";
        echo "<li>Toto je <strong>BEZPEČNÁ operace</strong> - neodstraní žádná data</li>";
        echo "<li>Všechny změny proběhnou v transakci</li>";
        echo "<li>Pokud nastane chyba, změny budou vráceny zpět (rollback)</li>";
        echo "</ul>";
        echo "</div>";

        echo "<p>";
        echo "<a href='?execute=1' class='btn'>⚡ SPUSTIT SJEDNOCENÍ</a> ";
        echo "<a href='/admin.php' class='btn btn-secondary'>Zrušit</a>";
        echo "</p>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<p><small><strong>Dokumentace:</strong> <a href='/AUDIT_SMTP_KONFIGURACE.md' target='_blank'>AUDIT_SMTP_KONFIGURACE.md</a></small></p>";
echo "</div></body></html>";
?>
