<?php
require_once 'init.php';
$pdo = getDbConnection();

echo "=== STRUKTURA wgs_photos ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM wgs_photos")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $col) {
    echo "{$col['Field']} - {$col['Type']}\n";
}

echo "\n=== POSLEDNÍ 3 FOTKY ===\n";
$photos = $pdo->query("SELECT * FROM wgs_photos ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach($photos as $p) {
    echo "ID: {$p['photo_id']}, Rek: {$p['reklamace_id']}, Path: {$p['photo_path']}\n";
}
?>
