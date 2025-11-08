<?php
require __DIR__ . '/init.php';
$pdo = getDbConnection();

echo "📷 5 záznamů z tabulky wgs_photos:\n\n";
foreach ($pdo->query("SELECT id, reklamace_id, photo_path FROM wgs_photos LIMIT 5") as $r) {
    print_r($r);
}
