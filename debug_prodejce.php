<?php
/**
 * Diagnostický skript pro kontrolu viditelnosti reklamací prodejce
 */
require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Nepřihlášen']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'unknown';
$userEmail = $_SESSION['user_email'] ?? '';

$pdo = getDbConnection();

// 1. Info o přihlášeném uživateli
$userInfo = [
    'session_user_id' => $userId,
    'session_role' => $userRole,
    'session_email' => $userEmail
];

// 2. Najít uživatele v DB
$stmt = $pdo->prepare("SELECT id, user_id, name, email, role FROM wgs_users WHERE user_id = :user_id OR email = :email LIMIT 1");
$stmt->execute([':user_id' => $userId, ':email' => $userEmail]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. Spočítat reklamace podle created_by
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by = :created_by");
$stmt2->execute([':created_by' => $userId]);
$countByUserId = $stmt2->fetchColumn();

// 4. Ukázat prvních 5 reklamací tohoto uživatele
$stmt3 = $pdo->prepare("SELECT id, reklamace_id, jmeno, created_by, created_by_role, stav FROM wgs_reklamace WHERE created_by = :created_by LIMIT 5");
$stmt3->execute([':created_by' => $userId]);
$sample = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// 5. Kontrola všech unikátních hodnot created_by
$stmt4 = $pdo->query("SELECT DISTINCT created_by FROM wgs_reklamace WHERE created_by IS NOT NULL AND created_by != '' LIMIT 20");
$allCreatedBy = $stmt4->fetchAll(PDO::FETCH_COLUMN);

// 6. Kontrola jestli load.php vrací něco
$isProdejce = in_array(strtolower($userRole), ['prodejce', 'user'], true);

echo json_encode([
    'diagnostika' => [
        'uzivatel' => $userInfo,
        'uzivatel_v_db' => $dbUser,
        'je_prodejce' => $isProdejce,
        'pocet_reklamaci_s_user_id' => (int)$countByUserId,
        'ukazka_reklamaci' => $sample,
        'vsechny_created_by_hodnoty' => $allCreatedBy
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
