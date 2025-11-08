<?php
require __DIR__ . '/init.php';
$pdo = getDbConnection();

echo "📸 Struktura tabulky wgs_photos:\n\n";
foreach ($pdo->query("DESCRIBE wgs_photos") as $r) {
    echo $r['Field'] . PHP_EOL;
}
