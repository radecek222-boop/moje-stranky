<?php
/**
 * Admin Users API
 * API pro správu uživatelů (seznam, přidání, úprava, smazání)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Pouze admin
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]);
        exit;
    }

    $pdo = getDbConnection();

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    // BEZPEČNOST: CSRF ochrana pro POST operace
    if ($method === 'POST') {
        requireCSRF();
    }

    if ($method === 'GET' && $action === 'list') {
        // Seznam všech uživatelů
        $stmt = $pdo->query("
            SELECT
                id,
                name,
                email,
                phone,
                address,
                role,
                status,
                created_at
            FROM wgs_users
            ORDER BY created_at DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'users' => $users
        ]);

    } elseif ($method === 'GET' && $action === 'online') {
        // Online uživatelé (aktivity za posledních 15 minut)
        $stmt = $pdo->query("
            SELECT DISTINCT
                u.id,
                u.name,
                u.email,
                u.role,
                s.last_activity
            FROM wgs_sessions s
            JOIN wgs_users u ON s.user_id = u.id
            WHERE s.last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ORDER BY s.last_activity DESC
        ");
        $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'users' => $onlineUsers
        ]);

    } elseif ($method === 'POST' && $action === 'add') {
        // Přidání nového uživatele
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);

        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $address = $data['address'] ?? '';
        $role = $data['role'] ?? 'prodejce';
        $password = $data['password'] ?? '';

        // Validace
        if (!$name || !$email || !$password) {
            throw new Exception('Jméno, email a heslo jsou povinné');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Neplatný formát emailu');
        }

        if (strlen($password) < 8) {
            throw new Exception('Heslo musí mít alespoň 8 znaků');
        }

        $allowedRoles = ['prodejce', 'technik', 'admin'];
        if (!in_array($role, $allowedRoles)) {
            throw new Exception('Neplatná role');
        }

        // Kontrola zda email už neexistuje
        $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            throw new Exception('Email již existuje');
        }

        // Vložení uživatele
        $stmt = $pdo->prepare("
            INSERT INTO wgs_users (
                name, email, phone, address, role, password_hash, status, created_at
            ) VALUES (
                :name, :email, :phone, :address, :role, :password_hash, 'active', NOW()
            )
        ");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':role' => $role,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT)
        ]);

        $userId = $pdo->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => 'Uživatel vytvořen',
            'user_id' => $userId
        ]);

    } elseif ($method === 'POST' && $action === 'delete') {
        // Smazání uživatele
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);

        $userId = $data['user_id'] ?? null;

        if (!$userId || !is_numeric($userId)) {
            throw new Exception('Neplatné ID uživatele');
        }

        $stmt = $pdo->prepare("DELETE FROM wgs_users WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Uživatel smazán'
        ]);

    } elseif ($method === 'POST' && $action === 'update_status') {
        // Změna statusu uživatele (active/inactive)
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);

        $userId = $data['user_id'] ?? null;
        $status = $data['status'] ?? '';

        if (!$userId || !is_numeric($userId)) {
            throw new Exception('Neplatné ID uživatele');
        }

        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception('Neplatný status');
        }

        $stmt = $pdo->prepare("
            UPDATE wgs_users
            SET status = :status
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':id' => $userId
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Status změněn'
        ]);

    } else {
        throw new Exception('Neplatná akce nebo metoda');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
