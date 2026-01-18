<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDbConnection();

    echo "=== UŽIVATEL TCH20250002 ===\n\n";

    $stmt = $pdo->prepare("SELECT * FROM wgs_users WHERE user_id = 'TCH20250002'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        foreach ($user as $key => $value) {
            if ($key === 'password_hash') continue;
            echo str_pad($key . ':', 20) . ($value ?? 'NULL') . "\n";
        }

        echo "\n=== NEDÁVNÁ AKTIVITA ===\n\n";

        $stmt = $pdo->prepare("
            SELECT action_type, ip_address, created_at
            FROM wgs_audit_log
            WHERE user_id = 'TCH20250002'
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as $log) {
            echo $log['created_at'] . " | " . $log['action_type'] . " | " . $log['ip_address'] . "\n";
        }

    } else {
        echo "Uživatel nenalezen.\n";
    }

} catch (Exception $e) {
    echo "Chyba: " . $e->getMessage() . "\n";
}
?>
