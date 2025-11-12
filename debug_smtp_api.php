<?php
/**
 * Debug SMTP Config
 * Zjistit proč get_smtp_config vrací HTTP 400
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

echo "<h1>🔍 Debug SMTP Config API</h1>";
echo "<style>body { font-family: monospace; padding: 20px; } pre { background: #f0f0f0; padding: 10px; } .error { color: red; } .success { color: green; }</style>";

try {
    $pdo = getDbConnection();

    echo "<h2>1. Kontrola existence tabulky wgs_system_config</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_system_config'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ Tabulka existuje</p>";
    } else {
        echo "<p class='error'>❌ Tabulka neexistuje!</p>";
        exit;
    }

    echo "<h2>2. Struktura tabulky</h2>";
    $cols = $pdo->query("DESCRIBE wgs_system_config")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($cols, true) . "</pre>";

    echo "<h2>3. Všechny záznamy v tabulce</h2>";
    $all = $pdo->query("SELECT * FROM wgs_system_config")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Celkem záznamů: " . count($all) . "</p>";
    echo "<pre>" . print_r($all, true) . "</pre>";

    echo "<h2>4. Email group záznamy</h2>";
    $email = $pdo->query("SELECT * FROM wgs_system_config WHERE config_group = 'email'")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Email konfigurace: " . count($email) . "</p>";
    echo "<pre>" . print_r($email, true) . "</pre>";

    echo "<h2>5. Simulace get_smtp_config query</h2>";
    $stmt = $pdo->prepare("
        SELECT config_key, config_value, is_sensitive
        FROM wgs_system_config
        WHERE config_group = 'email'
        ORDER BY config_key
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p class='success'>✅ Query proběhl úspěšně</p>";
    echo "<p>Nalezeno záznamů: " . count($rows) . "</p>";
    echo "<pre>" . print_r($rows, true) . "</pre>";

    $smtpConfig = [];
    foreach ($rows as $row) {
        if ($row['is_sensitive'] && !empty($row['config_value'])) {
            $smtpConfig[$row['config_key']] = '••••••••';
        } else {
            $smtpConfig[$row['config_key']] = $row['config_value'];
        }
    }

    echo "<h2>6. Finální SMTP config (jako API vrací)</h2>";
    echo "<pre>" . print_r($smtpConfig, true) . "</pre>";
    echo "<pre>" . json_encode(['status' => 'success', 'data' => $smtpConfig], JSON_PRETTY_PRINT) . "</pre>";

} catch (Exception $e) {
    echo "<h2 class='error'>❌ Chyba:</h2>";
    echo "<pre class='error'>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr><p><a href='/admin.php?tab=notifications'>← Zpět</a></p>";
