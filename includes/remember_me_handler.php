<?php
/**
 * Remember Me Handler
 * Zpracování automatického přihlášení z remember_me tokenu
 */

function handleRememberMeLogin(): void
{
    try {
        $cookieValue = $_COOKIE['remember_me'] ?? '';

        if (empty($cookieValue) || strpos($cookieValue, ':') === false) {
            return; // Neplatný formát
        }

        [$selector, $validator] = explode(':', $cookieValue, 2);

        if (empty($selector) || empty($validator)) {
            return;
        }

        $pdo = getDbConnection();

        // Načíst token z databáze
        $stmt = $pdo->prepare("
            SELECT * FROM wgs_remember_tokens
            WHERE selector = :selector
              AND expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([':selector' => $selector]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$token) {
            // Token nenalezen nebo expired
            setcookie('remember_me', '', time() - 3600, '/');
            return;
        }

        // Ověřit validator
        $hashedValidator = hash('sha256', $validator);

        if (!hash_equals($token['hashed_validator'], $hashedValidator)) {
            // Neplatný token - možný útok!
            // Smazat VŠECHNY tokeny tohoto uživatele
            $deleteStmt = $pdo->prepare("DELETE FROM wgs_remember_tokens WHERE user_id = :user_id");
            $deleteStmt->execute([':user_id' => $token['user_id']]);

            setcookie('remember_me', '', time() - 3600, '/');

            error_log("SECURITY: Invalid remember_me token for user {$token['user_id']}");
            return;
        }

        // Token je validní - načíst uživatele
        $userStmt = $pdo->prepare("SELECT * FROM wgs_users WHERE user_id = :user_id AND is_active = 1 LIMIT 1");
        $userStmt->execute([':user_id' => $token['user_id']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Uživatel neexistuje nebo je neaktivní
            setcookie('remember_me', '', time() - 3600, '/');
            return;
        }

        // Přihlásit uživatele
        session_regenerate_id(true);

        // SECURITY FIX: CSRF token rotation po auto-login
        unset($_SESSION['csrf_token']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['name'] ?? $user['email'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['is_admin'] = ($user['is_admin'] ?? 0) == 1;

        // FIX 6: Inactivity timeout - nastavit initial timestamps při auto-login
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        $_SESSION['login_method'] = 'remember_me';

        // Audit log
        require_once __DIR__ . '/audit_logger.php';
        auditLog('auto_login_remember_me', [
            'user_id' => $user['user_id'],
            'token_id' => $token['id']
        ], $user['user_id']);

        // Prodloužit token o dalších 30 dní
        $newExpiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $updateStmt = $pdo->prepare("UPDATE wgs_remember_tokens SET expires_at = :expires WHERE id = :id");
        $updateStmt->execute([
            ':expires' => $newExpiresAt,
            ':id' => $token['id']
        ]);

    } catch (Exception $e) {
        error_log("Remember Me error: " . $e->getMessage());
        // V případě chyby pouze logovat, nepřerušovat loading stránky
    }
}
