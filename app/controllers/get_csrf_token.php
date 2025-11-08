<?php
/**
 * CSRF Token Generator
 * Generuje CSRF token pro formuláře
 */

require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

try {
    $token = generateCSRFToken();

    echo json_encode([
        'status' => 'success',
        'token' => $token
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
