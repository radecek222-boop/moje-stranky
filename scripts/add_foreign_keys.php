<?php
/**
 * HIGH PRIORITY FIX: Přidání Foreign Key Constraints
 *
 * Přidává 4 kritické foreign key constraints pro data integrity
 * Zajišťuje referenční integritu na DB úrovni
 *
 * Použití:
 * - CLI: php scripts/add_foreign_keys.php
 * - Web: Spustit z admin panelu (vyžaduje admin oprávnění)
 */

require_once __DIR__ . '/../init.php';

// SECURITY: Admin check
if (php_sapi_name() !== 'cli') {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Admin access required']));
    }
}

$pdo = getDbConnection();

$results = [
    'success' => [],
    'skipped' => [],
    'failed' => []
];

echo "🔗 Starting Foreign Key Constraints creation...\n\n";

// ==========================================
// 1. wgs_photos → wgs_reklamace
// ==========================================

echo "1️⃣  wgs_photos → wgs_reklamace\n";

try {
    // Zkontrolovat jestli FK již existuje
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'wgs_photos'
          AND CONSTRAINT_NAME = 'fk_photos_reklamace'
    ");

    if ($stmt->rowCount() > 0) {
        $results['skipped'][] = 'wgs_photos.fk_photos_reklamace (již existuje)';
        echo "   ⏭️  FK fk_photos_reklamace již existuje\n";
    } else {
        // Nejdřív vyčistit orphan records (fotky bez reklamace)
        $cleanupStmt = $pdo->query("
            SELECT COUNT(*) as count FROM wgs_photos p
            LEFT JOIN wgs_reklamace r ON p.reklamace_id = r.reklamace_id
            WHERE r.id IS NULL
        ");
        $orphanCount = $cleanupStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($orphanCount > 0) {
            echo "   ⚠️  Nalezeno {$orphanCount} orphan photos, vyčišťuji...\n";
            // V produkci: NEMAZAT automaticky, jen informovat
            // $pdo->exec("DELETE p FROM wgs_photos p LEFT JOIN wgs_reklamace r ON p.reklamace_id = r.reklamace_id WHERE r.id IS NULL");
            echo "   ℹ️  POZOR: Před přidáním FK vyčistěte orphan records ručně!\n";
            $results['failed'][] = "wgs_photos.fk_photos_reklamace (orphan records: {$orphanCount})";
        } else {
            // Přidat FK constraint
            $pdo->exec("
                ALTER TABLE `wgs_photos`
                ADD CONSTRAINT `fk_photos_reklamace`
                FOREIGN KEY (`reklamace_id`)
                REFERENCES `wgs_reklamace`(`reklamace_id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ");
            $results['success'][] = 'wgs_photos.fk_photos_reklamace';
            echo "   FK fk_photos_reklamace přidán\n";
        }
    }
} catch (PDOException $e) {
    $results['failed'][] = "wgs_photos.fk_photos_reklamace: " . $e->getMessage();
    echo "   Chyba: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// 2. wgs_documents → wgs_reklamace
// ==========================================

echo "2️⃣  wgs_documents → wgs_reklamace (claim_id)\n";

try {
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'wgs_documents'
          AND CONSTRAINT_NAME = 'fk_documents_claim_id'
    ");

    if ($stmt->rowCount() > 0) {
        $results['skipped'][] = 'wgs_documents.fk_documents_claim_id (již existuje)';
        echo "   ⏭️  FK fk_documents_claim_id již existuje\n";
    } else {
        // Zkontrolovat orphan records
        $cleanupStmt = $pdo->query("
            SELECT COUNT(*) as count FROM wgs_documents d
            LEFT JOIN wgs_reklamace r ON d.claim_id = r.id
            WHERE d.claim_id IS NOT NULL AND r.id IS NULL
        ");
        $orphanCount = $cleanupStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($orphanCount > 0) {
            echo "   ⚠️  Nalezeno {$orphanCount} orphan documents\n";
            echo "   ℹ️  POZOR: Před přidáním FK vyčistěte orphan records ručně!\n";
            $results['failed'][] = "wgs_documents.fk_documents_claim_id (orphan records: {$orphanCount})";
        } else {
            $pdo->exec("
                ALTER TABLE `wgs_documents`
                ADD CONSTRAINT `fk_documents_claim_id`
                FOREIGN KEY (`claim_id`)
                REFERENCES `wgs_reklamace`(`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ");
            $results['success'][] = 'wgs_documents.fk_documents_claim_id';
            echo "   FK fk_documents_claim_id přidán\n";
        }
    }
} catch (PDOException $e) {
    $results['failed'][] = "wgs_documents.fk_documents_claim_id: " . $e->getMessage();
    echo "   Chyba: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// 3. wgs_pending_actions → wgs_github_webhooks (source_id)
// ==========================================

echo "3️⃣  wgs_pending_actions → wgs_github_webhooks (source_id)\n";

try {
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'wgs_pending_actions'
          AND CONSTRAINT_NAME = 'fk_pending_actions_webhook'
    ");

    if ($stmt->rowCount() > 0) {
        $results['skipped'][] = 'wgs_pending_actions.fk_pending_actions_webhook (již existuje)';
        echo "   ⏭️  FK fk_pending_actions_webhook již existuje\n";
    } else {
        // Zkontrolovat orphan records (pouze pro source_type='github_webhook')
        $cleanupStmt = $pdo->query("
            SELECT COUNT(*) as count FROM wgs_pending_actions pa
            LEFT JOIN wgs_github_webhooks w ON pa.source_id = w.id
            WHERE pa.source_type = 'github_webhook' AND w.id IS NULL
        ");
        $orphanCount = $cleanupStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($orphanCount > 0) {
            echo "   ⚠️  Nalezeno {$orphanCount} orphan pending_actions\n";
            echo "   ℹ️  POZOR: Vyčistěte orphan records před přidáním FK!\n";
            $results['failed'][] = "wgs_pending_actions.fk_pending_actions_webhook (orphan: {$orphanCount})";
        } else {
            // POZOR: FK lze přidat JEN pokud source_type je VŽDY 'github_webhook'
            // V opačném případě potřebujeme polymorphic vztah (nelze pomocí FK)
            echo "   ℹ️  SKIP: Polymorphic vztah (source_type variabilní) - FK nelze použít\n";
            $results['skipped'][] = 'wgs_pending_actions.fk_pending_actions_webhook (polymorphic)';
        }
    }
} catch (PDOException $e) {
    $results['failed'][] = "wgs_pending_actions.fk_pending_actions_webhook: " . $e->getMessage();
    echo "   Chyba: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// 4. wgs_email_queue → wgs_notifications (notification_id)
// ==========================================

echo "4️⃣  wgs_email_queue → wgs_notifications (notification_id)\n";

try {
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'wgs_email_queue'
          AND CONSTRAINT_NAME = 'fk_email_queue_notification'
    ");

    if ($stmt->rowCount() > 0) {
        $results['skipped'][] = 'wgs_email_queue.fk_email_queue_notification (již existuje)';
        echo "   ⏭️  FK fk_email_queue_notification již existuje\n";
    } else {
        // Zkontrolovat zda notification_id je integer nebo string
        $columnCheck = $pdo->query("
            SELECT DATA_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'wgs_email_queue'
              AND COLUMN_NAME = 'notification_id'
        ");
        $dataType = $columnCheck->fetch(PDO::FETCH_ASSOC)['DATA_TYPE'] ?? '';

        if (strpos($dataType, 'varchar') !== false || strpos($dataType, 'char') !== false) {
            echo "   ℹ️  SKIP: notification_id je STRING (varchar), ne integer - nelze použít FK\n";
            echo "   ℹ️  Hodnota může být 'custom' nebo jiný string identifier\n";
            $results['skipped'][] = 'wgs_email_queue.fk_email_queue_notification (string type)';
        } else {
            // Zkontrolovat orphan records
            $cleanupStmt = $pdo->query("
                SELECT COUNT(*) as count FROM wgs_email_queue eq
                LEFT JOIN wgs_notifications n ON eq.notification_id = n.id
                WHERE eq.notification_id IS NOT NULL AND n.id IS NULL
            ");
            $orphanCount = $cleanupStmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($orphanCount > 0) {
                echo "   ⚠️  Nalezeno {$orphanCount} orphan email_queue records\n";
                $results['failed'][] = "wgs_email_queue.fk_email_queue_notification (orphan: {$orphanCount})";
            } else {
                $pdo->exec("
                    ALTER TABLE `wgs_email_queue`
                    ADD CONSTRAINT `fk_email_queue_notification`
                    FOREIGN KEY (`notification_id`)
                    REFERENCES `wgs_notifications`(`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
                ");
                $results['success'][] = 'wgs_email_queue.fk_email_queue_notification';
                echo "   FK fk_email_queue_notification přidán\n";
            }
        }
    }
} catch (PDOException $e) {
    $results['failed'][] = "wgs_email_queue.fk_email_queue_notification: " . $e->getMessage();
    echo "   Chyba: " . $e->getMessage() . "\n";
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "HOTOVO!\n";
echo str_repeat("=", 70) . "\n\n";

echo "📊 Výsledky:\n";
echo "  Úspěšně přidáno: " . count($results['success']) . " FK constraints\n";
echo "  ⏭️  Přeskočeno: " . count($results['skipped']) . "\n";
echo "  Selhalo: " . count($results['failed']) . "\n";

if (!empty($results['failed'])) {
    echo "\n⚠️  CHYBY/VAROVÁNÍ:\n";
    foreach ($results['failed'] as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n💡 Doporučení:\n";
echo "  - Foreign keys zlepšují data integrity\n";
echo "  - CASCADE DELETE automaticky maže závislé záznamy\n";
echo "  - Zkontrolujte že FK byly vytvořeny:\n";
echo "    SHOW CREATE TABLE tabulka;\n";

// Return JSON for API calls
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'results' => $results,
        'summary' => [
            'added' => count($results['success']),
            'skipped' => count($results['skipped']),
            'failed' => count($results['failed'])
        ]
    ], JSON_PRETTY_PRINT);
}
