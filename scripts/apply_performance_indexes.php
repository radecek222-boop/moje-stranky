<?php
/**
 * Apply Performance Indexes Script
 * Automatické vytvoření 21 DB indexů pro zrychlení webu
 *
 * SPOUŠTÍ SE: Přes Control Center → Akce a úkoly
 */

require_once __DIR__ . '/../init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]));
}

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $results = [
        'created' => [],
        'already_exists' => [],
        'errors' => []
    ];

    // Seznam všech indexů k vytvoření
    $indexes = [
        // wgs_reklamace
        ['table' => 'wgs_reklamace', 'index' => 'idx_reklamace_id', 'column' => 'reklamace_id'],
        ['table' => 'wgs_reklamace', 'index' => 'idx_cislo', 'column' => 'cislo'],
        ['table' => 'wgs_reklamace', 'index' => 'idx_stav', 'column' => 'stav'],
        ['table' => 'wgs_reklamace', 'index' => 'idx_created_by', 'column' => 'created_by'],
        ['table' => 'wgs_reklamace', 'index' => 'idx_created_at_desc', 'column' => 'created_at', 'order' => 'DESC'],
        ['table' => 'wgs_reklamace', 'index' => 'idx_assigned_to', 'column' => 'assigned_to'],
        ['table' => 'wgs_reklamace', 'index' => 'idx_stav_created', 'columns' => ['stav', 'created_at DESC']],

        // wgs_photos
        ['table' => 'wgs_photos', 'index' => 'idx_reklamace_id', 'column' => 'reklamace_id'],
        ['table' => 'wgs_photos', 'index' => 'idx_section_name', 'column' => 'section_name'],
        ['table' => 'wgs_photos', 'index' => 'idx_reklamace_section_order', 'columns' => ['reklamace_id', 'section_name', 'photo_order']],
        ['table' => 'wgs_photos', 'index' => 'idx_uploaded_at', 'column' => 'uploaded_at', 'order' => 'DESC'],

        // wgs_documents
        ['table' => 'wgs_documents', 'index' => 'idx_claim_id', 'column' => 'claim_id'],
        ['table' => 'wgs_documents', 'index' => 'idx_reklamace_id', 'column' => 'reklamace_id'],
        ['table' => 'wgs_documents', 'index' => 'idx_created_at', 'column' => 'created_at', 'order' => 'DESC'],

        // wgs_users
        ['table' => 'wgs_users', 'index' => 'idx_email', 'column' => 'email'],
        ['table' => 'wgs_users', 'index' => 'idx_role', 'column' => 'role'],

        // wgs_email_queue
        ['table' => 'wgs_email_queue', 'index' => 'idx_status', 'column' => 'status'],
        ['table' => 'wgs_email_queue', 'index' => 'idx_scheduled_at', 'column' => 'scheduled_at'],
        ['table' => 'wgs_email_queue', 'index' => 'idx_priority', 'column' => 'priority', 'order' => 'DESC'],
        ['table' => 'wgs_email_queue', 'index' => 'idx_queue_processing', 'columns' => ['status', 'scheduled_at', 'priority DESC']],

        // wgs_notes
        ['table' => 'wgs_notes', 'index' => 'idx_claim_id', 'column' => 'claim_id'],
    ];

    foreach ($indexes as $indexDef) {
        $table = $indexDef['table'];
        $indexName = $indexDef['index'];

        // Zkontrolovat zda tabulka existuje
        $tableCheck = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->rowCount() === 0) {
            $results['errors'][] = "Tabulka $table neexistuje - přeskakuji";
            continue;
        }

        // Zkontrolovat zda index již existuje
        $indexCheck = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
        if ($indexCheck->rowCount() > 0) {
            $results['already_exists'][] = "$table.$indexName";
            continue;
        }

        // Vytvořit index
        try {
            if (isset($indexDef['columns'])) {
                // Kompozitní index
                $columns = implode(', ', array_map(function($col) {
                    return "`" . trim(str_replace('DESC', '', $col)) . "`" . (strpos($col, 'DESC') !== false ? ' DESC' : '');
                }, $indexDef['columns']));
                $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` ($columns)";
            } else {
                // Jednoduchý index
                $column = $indexDef['column'];
                $order = $indexDef['order'] ?? '';
                $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` (`$column`" . ($order ? " $order" : '') . ")";
            }

            $pdo->exec($sql);
            $results['created'][] = "$table.$indexName";
        } catch (PDOException $e) {
            // Možná chyba pokud sloupec neexistuje
            $results['errors'][] = "$table.$indexName: " . $e->getMessage();
        }
    }

    // Statistiky
    $totalCreated = count($results['created']);
    $totalSkipped = count($results['already_exists']);
    $totalErrors = count($results['errors']);
    $totalAttempted = count($indexes);

    echo json_encode([
        'success' => true,
        'message' => "✅ Hotovo! Vytvořeno $totalCreated indexů, přeskočeno $totalSkipped (již existují), chyb: $totalErrors",
        'details' => [
            'total_attempted' => $totalAttempted,
            'created' => $totalCreated,
            'already_exists' => $totalSkipped,
            'errors' => $totalErrors
        ],
        'created_indexes' => $results['created'],
        'skipped_indexes' => $results['already_exists'],
        'error_messages' => $results['errors']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Chyba při vytváření indexů: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
