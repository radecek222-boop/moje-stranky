<?php
/**
 * Debug skript pro ověření zadavatele reklamace
 * Použití: /debug_zadavatel.php?id=NCM23-00000212-43
 */

require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze admin");
}

$searchId = $_GET['id'] ?? 'NCM23-00000212-43';

echo "<pre style='font-family: monospace; background: #1a1a1a; color: #39ff14; padding: 20px;'>";
echo "=== DEBUG: Zadavatel pro reklamaci $searchId ===\n\n";

try {
    $pdo = getDbConnection();

    // Nejprve zjistit jaké sloupce existují
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $existingColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

    echo "EXISTUJÍCÍ SLOUPCE v wgs_reklamace:\n";
    echo implode(", ", $existingColumns) . "\n\n";

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            u.name as created_by_name,
            u.email as created_by_email
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.id
        WHERE r.reklamace_id = :val1 OR r.cislo = :val2 OR r.id = :val3
        LIMIT 1
    ");

    $stmt->execute([
        'val1' => $searchId,
        'val2' => $searchId,
        'val3' => is_numeric($searchId) ? (int)$searchId : 0
    ]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        echo "NALEZENO:\n";
        echo "─────────────────────────────────────────\n";
        echo "ID:                  " . ($record['id'] ?? 'NULL') . "\n";
        echo "Reklamace ID:        " . ($record['reklamace_id'] ?? 'NULL') . "\n";
        echo "Číslo:               " . ($record['cislo'] ?? 'NULL') . "\n";
        echo "Zákazník:            " . ($record['jmeno'] ?? 'NULL') . "\n";
        echo "─────────────────────────────────────────\n";
        echo "created_by (user_id): " . ($record['created_by'] ?? 'NULL') . "\n";
        echo "created_by_role:      " . ($record['created_by_role'] ?? 'NULL') . "\n";
        echo "created_by_name:      " . ($record['created_by_name'] ?? 'NULL (žádný JOIN)') . "\n";
        echo "created_by_email:     " . ($record['created_by_email'] ?? 'NULL') . "\n";
        echo "─────────────────────────────────────────\n";
        echo "zpracoval:            " . ($record['zpracoval'] ?? 'NULL') . "\n";
        echo "technik:              " . ($record['technik'] ?? 'NULL') . "\n";
        echo "─────────────────────────────────────────\n\n";

        // Co se zobrazí v protokolu
        $zobrazeno = $record['created_by_name'] ?? $record['zpracoval'] ?? '(prázdné)';
        echo "V PROTOKOLU SE ZOBRAZÍ: $zobrazeno\n";

        echo "\nMOŽNÉ KANDIDÁTY PRO ZADAVATELE:\n";
        foreach (['created_by_name', 'zpracoval', 'technik'] as $col) {
            if (isset($record[$col]) && !empty($record[$col])) {
                echo "  - $col: " . $record[$col] . "\n";
            }
        }

    } else {
        echo "NENALEZENO: Žádná reklamace s ID '$searchId'\n";
    }

} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}

echo "</pre>";
