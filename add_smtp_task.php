<?php
/**
 * Add SMTP Installation Task
 * Tento script přidá úlohu pro instalaci SMTP konfigurace do systému akcí
 *
 * POUŽITÍ: Otevřete tento soubor v prohlížeči jako admin
 */

require_once "init.php";

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('ERROR: Přístup pouze pro administrátory. Přihlaste se prosím jako admin.');
}

try {
    $pdo = getDbConnection();

    echo "<h1>Přidání SMTP instalační úlohy</h1>";
    echo "<pre>";

    // Přidat úlohu do systému akcí
    echo "Přidávám SMTP instalační úlohu do wgs_pending_actions...\n";
    $stmt = $pdo->prepare("
        INSERT INTO wgs_pending_actions (
            action_type,
            action_title,
            action_description,
            priority,
            status,
            created_at
        )
        VALUES (
            'install_smtp',
            'Instalovat SMTP konfiguraci',
            'Přidá smtp_password a smtp_encryption klíče do system_config a vytvoří tabulku wgs_notification_history pro sledování odeslaných emailů a SMS.',
            'high',
            'pending',
            CURRENT_TIMESTAMP
        )
    ");

    $stmt->execute();
    $actionId = $pdo->lastInsertId();

    echo "   ✓ Úloha přidána (ID: {$actionId})\n\n";

    // Ověření
    echo "Ověřování...\n";
    $stmt = $pdo->prepare("SELECT * FROM wgs_pending_actions WHERE id = ?");
    $stmt->execute([$actionId]);
    $action = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($action) {
        echo "   ✓ Typ: {$action['action_type']}\n";
        echo "   ✓ Název: {$action['action_title']}\n";
        echo "   ✓ Priorita: {$action['priority']}\n";
        echo "   ✓ Status: {$action['status']}\n\n";
    }

    echo "</pre>";
    echo "<h2 style='color: green;'>✅ Úloha byla úspěšně přidána!</h2>";
    echo "<p>Nyní přejděte do <a href='admin.php'>Control Center → Akce & Úkoly</a> a klikněte na <strong>\"Spustit akci\"</strong> pro instalaci SMTP konfigurace.</p>";

} catch (Exception $e) {
    echo "</pre>";
    echo "<h2 style='color: red;'>❌ Chyba při přidávání úlohy</h2>";
    echo "<p>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
