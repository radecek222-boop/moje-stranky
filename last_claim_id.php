<?php
require __DIR__ . '/init.php';
$pdo = getDbConnection();
$row = $pdo->query("SELECT id, cislo FROM wgs_reklamace ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($row);
