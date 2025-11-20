<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$pdo = getDbConnection();

// Hledat reklamaci podle jména z PDF
$stmt = $pdo->prepare("
    SELECT *
    FROM wgs_reklamace
    WHERE jmeno LIKE '%Petr Kmoch%'
    OR telefon LIKE '%725 387 868%'
    OR email LIKE '%kmochova@petrisk.cz%'
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute();
$reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($reklamace, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
