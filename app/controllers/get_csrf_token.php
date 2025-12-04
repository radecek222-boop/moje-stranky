<?php
/**
 * CSRF Token Generator
 * Generuje CSRF token pro formuláře
 *
 * FIX PWA: Přidány anti-cache headers pro PWA kompatibilitu
 */

require_once __DIR__ . '/../../init.php';

// KRITICKÉ: Anti-cache headers pro PWA
// PWA může cachovat odpovědi, což způsobí CSRF mismatch
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

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
