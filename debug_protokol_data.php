<?php
/**
 * Debug: Ověření dat pro protokol
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$searchId = $_GET['id'] ?? 'NCM23-00000212-43';

echo "<pre style='background:#1a1a1a;color:#39ff14;padding:20px;'>";
echo "=== DEBUG PROTOKOL DATA pro: $searchId ===\n\n";

try {
    $pdo = getDbConnection();

    // Přesně stejný dotaz jako v protokol.php
    $stmt = $pdo->prepare(
        "SELECT r.*, u.name as zadavatel_jmeno
         FROM wgs_reklamace r
         LEFT JOIN wgs_users u ON r.created_by = u.user_id
         WHERE r.reklamace_id = :val1 OR r.cislo = :val2 OR r.id = :val3
         LIMIT 1"
    );
    $stmt->execute([':val1' => $searchId, ':val2' => $searchId, ':val3' => $searchId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        echo "NALEZENO:\n";
        echo "─────────────────────────────────────────\n";
        echo "reklamace_id:        " . ($record['reklamace_id'] ?? 'NULL') . "\n";
        echo "cislo:               " . ($record['cislo'] ?? 'NULL') . "\n";
        echo "jmeno (zákazník):    " . ($record['jmeno'] ?? 'NULL') . "\n";
        echo "─────────────────────────────────────────\n";
        echo "\n=== ZADAVATEL (created_by -> wgs_users.user_id) ===\n";
        echo "created_by:          " . ($record['created_by'] ?? 'NULL') . "\n";
        echo "zadavatel_jmeno:     " . ($record['zadavatel_jmeno'] ?? 'NULL') . "\n";
        echo "\n=== TECHNIK ===\n";
        echo "technik (legacy):    " . ($record['technik'] ?? 'NULL') . "\n";
        echo "assigned_to:         " . ($record['assigned_to'] ?? 'NULL') . "\n";
        echo "─────────────────────────────────────────\n";

        // Ověřit uživatele přímo
        echo "\n=== OVĚŘENÍ UŽIVATELŮ V wgs_users ===\n";

        if (!empty($record['created_by'])) {
            $userStmt = $pdo->prepare("SELECT user_id, name, email FROM wgs_users WHERE user_id = :uid");
            $userStmt->execute(['uid' => $record['created_by']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo "created_by '{$record['created_by']}' -> {$user['name']} ({$user['email']})\n";
            } else {
                echo "created_by '{$record['created_by']}' -> NENALEZEN v wgs_users!\n";
            }
        }

        if (!empty($record['assigned_to'])) {
            $userStmt = $pdo->prepare("SELECT user_id, name, email FROM wgs_users WHERE user_id = :uid");
            $userStmt->execute(['uid' => $record['assigned_to']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo "assigned_to '{$record['assigned_to']}' -> {$user['name']} ({$user['email']})\n";
            } else {
                echo "assigned_to '{$record['assigned_to']}' -> NENALEZEN v wgs_users!\n";
            }
        }

        echo "\n=== CO BY MĚL PROTOKOL ZOBRAZIT ===\n";
        echo "Pole 'Zadavatel': " . ($record['zadavatel_jmeno'] ?? '(prázdné)') . "\n";
        echo "Pole 'Technik':   " . ($record['technik'] ?? '(prázdné)') . "\n";

    } else {
        echo "NENALEZENO!\n";
    }

} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}

echo "</pre>";
