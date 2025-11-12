<?php
/**
 * CLI Script: Add SMTP Installation Task
 * Tento script přidá úlohu pro instalaci SMTP konfigurace
 * Spouští se z příkazové řádky bez nutnosti přihlášení
 */

require_once "init.php";

try {
    $pdo = getDbConnection();

    echo "=== Přidávání SMTP instalační úlohy ===\n";

    // Přidat úlohu
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

    echo "✓ Úloha přidána (ID: {$actionId})\n";

    // Ověření
    $stmt = $pdo->prepare("SELECT * FROM wgs_pending_actions WHERE id = ?");
    $stmt->execute([$actionId]);
    $action = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($action) {
        echo "✓ Typ: {$action['action_type']}\n";
        echo "✓ Název: {$action['action_title']}\n";
        echo "✓ Priorita: {$action['priority']}\n";
        echo "✓ Status: {$action['status']}\n";
        echo "\n✅ ÚSPĚCH! Úloha byla přidána do systému.\n";
        echo "Přejděte do Control Center → Akce & Úkoly a klikněte na 'Spustit akci'.\n";
    }

} catch (Exception $e) {
    echo "❌ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
