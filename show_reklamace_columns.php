<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    echo "SLOUPCE V TABULCE wgs_reklamace:\n\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage();
}
?>
