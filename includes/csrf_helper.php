<?php
/**
 * CSRF Protection Helper
 * Requires init.php to be loaded first (which includes config.php)
 *
 * SECURITY: All users including admins must have valid CSRF tokens.
 * Previous admin bypass was a security vulnerability.
 *
 * NOTE: For JSON APIs, read php://input FIRST, then extract and validate token.
 * See admin_api.php for correct pattern.
 */
/**
 * Generate CSRF token
 *
 * @return string
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Validate CSRF token
 *
 * @param string $token Token to validate
 * @return bool
 */
if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        // SECURITY: Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * RequireCSRF
 */
if (!function_exists('requireCSRF')) {
    function requireCSRF() {
        // SECURITY FIX: Removed admin bypass - all users require CSRF tokens
        // Even if admin account is compromised, CSRF protection remains active

        // Get token from POST, GET, or HTTP header
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        // SECURITY: Ensure CSRF token is a string, not an array (array injection protection)
        if (is_array($token)) {
            $token = '';
        }

        if (!validateCSRFToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Neplatný CSRF token. Obnovte stránku a zkuste znovu.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
?>