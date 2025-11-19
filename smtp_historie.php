<?php
/**
 * Historie SMTP konfigurace
 * Zjistíme, jaká konfigurace byla 18.11.2025 (kdy to fungovalo)
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>SMTP Historie</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:1200px;margin:50px auto;padding:20px;background:#f5f5f5;}
.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:5px;margin:15px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:5px;margin:15px 0;}
table{width:100%;border-collapse:collapse;margin:15px 0;}
th,td{border:1px solid #ddd;padding:10px;text-align:left;font-size:13px;}
th{background:#2D5016;color:white;}
code{background:#f4f4f4;padding:3px 8px;border-radius:3px;font-family:monospace;}
</style></head><body><div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 SMTP Konfigurace - Historie změn</h1>";

    // Všechny verze konfigurace
    echo "<h2>📊 Všechny změny SMTP konfigurace:</h2>";
    $stmt = $pdo->query("
        SELECT
            id,
            smtp_host,
            smtp_port,
            smtp_encryption,
            smtp_username,
            LENGTH(smtp_password) as password_length,
            smtp_from_email,
            is_active,
            created_at,
            updated_at
        FROM wgs_smtp_settings
        ORDER BY updated_at DESC
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Host</th>";
    echo "<th>Port</th>";
    echo "<th>Encryption</th>";
    echo "<th>Username</th>";
    echo "<th>Password</th>";
    echo "<th>Active</th>";
    echo "<th>Created</th>";
    echo "<th>Updated</th>";
    echo "</tr>";

    foreach ($configs as $cfg) {
        $isActive = $cfg['is_active'] ? '✅' : '❌';
        $updatedDate = date('d.m.Y H:i', strtotime($cfg['updated_at']));

        echo "<tr>";
        echo "<td><code>#{$cfg['id']}</code></td>";
        echo "<td><code>{$cfg['smtp_host']}</code></td>";
        echo "<td><code>{$cfg['smtp_port']}</code></td>";
        echo "<td><code>{$cfg['smtp_encryption']}</code></td>";
        echo "<td><code>{$cfg['smtp_username']}</code></td>";
        echo "<td><code>" . ($cfg['password_length'] > 0 ? "{$cfg['password_length']} znaků" : 'PRÁZDNÉ') . "</code></td>";
        echo "<td>{$isActive}</td>";
        echo "<td>" . date('d.m.Y H:i', strtotime($cfg['created_at'])) . "</td>";
        echo "<td><strong>{$updatedDate}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";

    // Úspěšně odeslané emaily
    echo "<h2>✅ Úspěšně odeslané emaily (18.11.2025):</h2>";
    $stmt = $pdo->query("
        SELECT id, recipient_email, subject, sent_at
        FROM wgs_email_queue
        WHERE status = 'sent'
          AND DATE(sent_at) = '2025-11-18'
        ORDER BY sent_at DESC
    ");
    $sentEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>ID</th><th>Příjemce</th><th>Předmět</th><th>Odesláno</th></tr>";
    foreach ($sentEmails as $email) {
        echo "<tr>";
        echo "<td><code>#{$email['id']}</code></td>";
        echo "<td><code>{$email['recipient_email']}</code></td>";
        echo "<td>" . htmlspecialchars(substr($email['subject'], 0, 50)) . "</td>";
        echo "<td>" . date('d.m.Y H:i:s', strtotime($email['sent_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div class='info'>";
    echo "<h3>💡 ANALÝZA:</h3>";
    echo "<p>Podívejte se na sloupec <strong>\"Updated\"</strong> v první tabulce.</p>";
    echo "<p>Konfigurace, která byla aktivní <strong>18.11.2025 kolem 12:00-23:00</strong> (kdy byly emaily odeslány), je pravděpodobně ta SPRÁVNÁ.</p>";
    echo "</div>";

    echo "<div class='error'>";
    echo "<h3>🚨 PROBLÉM:</h3>";
    echo "<p>Heslo <code>p7u.s13mR2018</code> může být:</p>";
    echo "<ul>";
    echo "<li>❌ Špatné pro SMTP (možná je to heslo pro webmail nebo něco jiného)</li>";
    echo "<li>❌ Neplatné pro websmtp.cesky-hosting.cz</li>";
    echo "<li>✅ Správné, ale vyžaduje jiný username (např. jen doména: <code>wgs-service.cz</code>)</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>📞 DOPORUČENÍ:</h3>";
    echo "<p><strong>Kontaktujte Český hosting support</strong> a zeptejte se:</p>";
    echo "<ol>";
    echo "<li>Jaké jsou správné SMTP přihlašovací údaje pro doménu <code>wgs-service.cz</code>?</li>";
    echo "<li>Měl by se používat <code>smtp.cesky-hosting.cz</code> nebo <code>websmtp.cesky-hosting.cz</code>?</li>";
    echo "<li>Jaký je správný username a heslo pro SMTP autentizaci?</li>";
    echo "<li>Změnilo se něco na serveru 19.11.2025?</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr><a href='/admin.php' class='btn' style='display:inline-block;padding:12px 24px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;'>← Admin panel</a>";
echo "</div></body></html>";
?>
