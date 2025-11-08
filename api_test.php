<?php
require __DIR__ . '/init.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$pdo = getDbConnection();
$row = $pdo->query("SELECT id FROM wgs_reklamace ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$_GET['reklamace_id'] = $row['id'] ?? 1;

include __DIR__ . '/api/get_photos_api.php';
