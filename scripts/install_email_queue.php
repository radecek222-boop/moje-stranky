<?php
/**
 * Email Queue Installation Script
 * Spustí se jednou pro vytvoření email queue tabulek
 */

require_once __DIR__ . '/../init.php';

echo "==========================================\n";
echo "WGS Email Queue - Installation\n";
echo "==========================================\n\n";

try {
    $pdo = getDbConnection();

    // Read SQL migration file
    $sqlFile = __DIR__ . '/../migrations/create_email_queue.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);

    // Remove comments and split by semicolons
    $statements = array_filter(
        array_map('trim', preg_split('/;(?=(?:[^\'"]|[\'"][^\'"]*[\'"])*$)/', $sql)),
        function($stmt) {
            return !empty($stmt) &&
                   !preg_match('/^--/', $stmt) &&
                   !preg_match('/^SELECT/', $stmt);
        }
    );

    echo "Executing SQL statements...\n";

    foreach ($statements as $index => $statement) {
        if (trim($statement)) {
            echo "Statement " . ($index + 1) . "... ";
            $pdo->exec($statement);
            echo "✓\n";
        }
    }

    echo "\n✅ Email Queue installation completed successfully!\n\n";

    // Verify tables
    $tables = ['wgs_email_queue', 'wgs_smtp_settings'];

    echo "Verifying tables:\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  ✓ $table exists\n";
        } else {
            echo "  ✗ $table NOT FOUND\n";
        }
    }

    echo "\n==========================================\n";
    echo "Installation complete!\n";
    echo "==========================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
