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
            r.id,
            r.reklamace_id,
            r.cislo,
            r.jmeno,
            r.created_by,
            r.created_by_role,
            r.assigned_to,
            r.technik,
            zadavatel.name as zadavatel_jmeno,
            zadavatel.email as zadavatel_email,
            technik_user.name as technik_jmeno,
            technik_user.email as technik_email
        FROM wgs_reklamace r
        LEFT JOIN wgs_users zadavatel ON r.created_by = zadavatel.id
        LEFT JOIN wgs_users technik_user ON r.assigned_to = technik_user.id
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
        echo "\n=== ZADAVATEL (kdo vytvořil zakázku) ===\n";
        echo "created_by (ID):     " . ($record['created_by'] ?? 'NULL') . "\n";
        echo "created_by_role:     " . ($record['created_by_role'] ?? 'NULL') . "\n";
        echo "JMÉNO:               " . ($record['zadavatel_jmeno'] ?? 'NULL') . "\n";
        echo "EMAIL:               " . ($record['zadavatel_email'] ?? 'NULL') . "\n";
        echo "\n=== TECHNIK (kdo pracuje se zakázkou) ===\n";
        echo "assigned_to (ID):    " . ($record['assigned_to'] ?? 'NULL') . "\n";
        echo "JMÉNO:               " . ($record['technik_jmeno'] ?? $record['technik'] ?? 'NULL') . "\n";
        echo "EMAIL:               " . ($record['technik_email'] ?? 'NULL') . "\n";
        echo "technik (legacy):    " . ($record['technik'] ?? 'NULL') . "\n";
        echo "─────────────────────────────────────────\n\n";

        // Shrnutí
        echo "=== SHRNUTÍ PRO PROTOKOL ===\n";
        echo "Zadavatel: " . ($record['zadavatel_jmeno'] ?? '(NENÍ VYPLNĚNO)') . "\n";
        echo "Technik:   " . ($record['technik_jmeno'] ?? $record['technik'] ?? '(NENÍ VYPLNĚNO)') . "\n";

        // Pokud created_by je NULL nebo 0, ukázat info
        if (empty($record['created_by']) || $record['created_by'] == 0) {
            echo "\n⚠️  POZOR: created_by je prázdný/nulový!\n";
            echo "   Tato reklamace byla pravděpodobně vytvořena před implementací RBAC.\n";
        }

    } else {
        echo "NENALEZENO: Žádná reklamace s ID '$searchId'\n";
    }

} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}

echo "</pre>";
