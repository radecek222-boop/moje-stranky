<?php
/**
 * Přidá úkol na instalaci PHPMailer do Control Center
 * Spusť tento script jednou pro přidání úkolu
 */

require_once __DIR__ . '/../init.php';

// Kontrola admin oprávnění
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Forbidden: Admin access required');
}

$pdo = getDbConnection();

try {
    // Zkontrolovat, zda už úkol neexistuje
    $stmt = $pdo->prepare("
        SELECT id, status FROM wgs_pending_actions
        WHERE action_type = 'install_phpmailer'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['status'] === 'pending' || $existing['status'] === 'in_progress') {
            echo "OK Úkol na instalaci PHPMailer už existuje (ID: {$existing['id']})\n";
            echo "➜ Jdi do admin.php a klikni na 'Akce & Úkoly'\n";
            exit;
        } else {
            echo "⚠ Úkol existuje, ale má status: {$existing['status']}\n";
            echo "Vytvářím nový úkol...\n";
        }
    }

    // Přidat nový úkol
    $stmt = $pdo->prepare("
        INSERT INTO wgs_pending_actions (
            action_type,
            action_title,
            action_description,
            action_url,
            priority,
            status
        ) VALUES (
            'install_phpmailer',
            'Nainstalovat PHPMailer',
            'PHPMailer je potřeba pro odesílání emailů přes SMTP. Bez něj email queue používá pouze PHP mail() funkci, která často nefunguje na sdíleném hostingu.\n\nPo instalaci:\nEmaily budou odcházet spolehlivě přes SMTP\nEmail queue cron bude fungovat správně\nBudete vidět detailní chybové zprávy při problémech',
            'scripts/install_phpmailer.php',
            'high',
            'pending'
        )
    ");

    $stmt->execute();
    $taskId = $pdo->lastInsertId();

    echo "Úkol úspěšně vytvořen! (ID: {$taskId})\n";
    echo "\n";
    echo "==========================================\n";
    echo "JAK SPUSTIT INSTALACI:\n";
    echo "==========================================\n";
    echo "1. Jdi do admin.php a klikni na 'Control Center'\n";
    echo "2. Otevři sekci 'Akce & Úkoly'\n";
    echo "3. Najdi úkol 'Nainstalovat PHPMailer'\n";
    echo "4. Klikni na tlačítko '▶️ Spustit'\n";
    echo "5. Potvrd akci\n";
    echo "==========================================\n";
    echo "\n";
    echo "Nebo spusť přímo:\n";
    echo "https://www.wgs-service.cz/admin.php&detail=actions\n";

} catch (PDOException $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";

    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "\n";
        echo "Tabulka wgs_pending_actions neexistuje.\n";
        echo "Nejprve spusť migraci:\n";
        echo "mysql -u user -p database < migrations/create_actions_system.sql\n";
    }
}
