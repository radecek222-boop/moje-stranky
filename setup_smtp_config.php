<?php
/**
 * Setup SMTP Configuration
 * Nastavení SMTP údajů do wgs_system_config
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - admin login required');
}

$pdo = getDbConnection();

echo "<h1>⚙️ Nastavení SMTP konfigurace</h1>";
echo "<style>body { font-family: sans-serif; padding: 20px; } .success { color: green; } .error { color: red; } pre { background: #f0f0f0; padding: 10px; border-radius: 4px; }</style>";

// SMTP údaje
$smtpConfig = [
    'smtp_host' => 'smtp.ceskyhosting.cz',
    'smtp_port' => '587',
    'smtp_username' => 'reklamace@wgs-service.cz',
    'smtp_password' => 'O7cw+hkbKSrg/Eew',
    'smtp_from' => 'reklamace@wgs-service.cz',
    'smtp_from_name' => 'White Glove Service',
    'smtp_encryption' => 'tls'
];

echo "<h2>📧 Konfigurace která bude nastavena:</h2>";
echo "<pre>";
foreach ($smtpConfig as $key => $value) {
    if ($key === 'smtp_password') {
        echo "$key: ***" . substr($value, -4) . "\n";
    } else {
        echo "$key: $value\n";
    }
}
echo "</pre>";

try {
    $pdo->beginTransaction();

    $updated = 0;
    $inserted = 0;

    foreach ($smtpConfig as $key => $value) {
        // Zjistit jestli konfigurace už existuje
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_system_config WHERE config_key = ?");
        $stmt->execute([$key]);
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE wgs_system_config
                SET config_value = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE config_key = ?
            ");
            $stmt->execute([$value, $key]);
            $updated++;
            echo "<p class='success'>✅ Aktualizováno: $key</p>";
        } else {
            // Insert
            $isSensitive = ($key === 'smtp_password') ? 1 : 0;
            $stmt = $pdo->prepare("
                INSERT INTO wgs_system_config
                (config_key, config_value, config_group, is_sensitive, requires_restart, description)
                VALUES (?, ?, 'email', ?, TRUE, ?)
            ");
            $description = match($key) {
                'smtp_host' => 'SMTP server hostname',
                'smtp_port' => 'SMTP port (usually 587 or 465)',
                'smtp_username' => 'SMTP authentication username',
                'smtp_password' => 'SMTP authentication password',
                'smtp_from' => 'Default FROM email address',
                'smtp_from_name' => 'FROM name for emails',
                'smtp_encryption' => 'Encryption method (tls or ssl)',
                default => ''
            };
            $stmt->execute([$key, $value, $isSensitive, $description]);
            $inserted++;
            echo "<p class='success'>✅ Přidáno: $key</p>";
        }
    }

    $pdo->commit();

    echo "<hr>";
    echo "<h2 class='success'>🎉 SMTP konfigurace úspěšně nastavena!</h2>";
    echo "<p><strong>Statistiky:</strong></p>";
    echo "<ul>";
    echo "<li>Aktualizováno: $updated záznamů</li>";
    echo "<li>Přidáno: $inserted záznamů</li>";
    echo "</ul>";

    echo "<hr>";
    echo "<h2>🧪 Další kroky:</h2>";
    echo "<ol>";
    echo "<li>Přejít do <a href='/admin.php?tab=control_center'>Admin Control Center</a></li>";
    echo "<li>Otevřít kartu 'Konfigurace' nebo 'SMTP'</li>";
    echo "<li>Kliknout na 'Test SMTP Connection'</li>";
    echo "<li>Odeslat testovací email</li>";
    echo "</ol>";

    echo "<p><a href='/admin.php?tab=control_center' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 20px;'>✅ Přejít do Control Center</a></p>";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2 class='error'>❌ Chyba při nastavení SMTP:</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
