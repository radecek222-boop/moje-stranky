<?php
require __DIR__ . '/init.php';
$pdo = getDbConnection();

echo "✅ Připojení OK\n\n";

echo "📦 Tabulky s názvem wgs_reklamace:\n";
foreach($pdo->query("SHOW TABLES LIKE 'wgs_reklamace'") as $r){
    print_r($r);
}

echo "\n\n📑 Sloupce v tabulce wgs_reklamace:\n";
foreach($pdo->query("DESCRIBE wgs_reklamace") as $r){
    echo $r['Field'] . PHP_EOL;
}
