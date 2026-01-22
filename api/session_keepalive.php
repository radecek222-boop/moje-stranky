<?php
/**
 * Session Keep-Alive Endpoint
 *
 * Udržuje session aktivní pro techniky vyplňující protokol.
 * Obnovuje last_activity timestamp aby session nevypršela po 30 min.
 *
 * POUŽITÍ:
 * - Protokol.php volá tento endpoint každých 5 minut pomocí JavaScript
 * - Brání ztrátě dat při dlouhém vyplňování protokolu
 * - CSRF token NENÍ vyžadován (read-only operace)
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Nepřihlášený uživatel',
        'logged_in' => false
    ]);
    exit;
}

// Obnovit last_activity timestamp
$_SESSION['last_activity'] = time();

// Log pro debugging
if (isset($_SESSION['user_email'])) {
    error_log("[Session Keep-Alive] User: {$_SESSION['user_email']}, Last activity updated: " . date('Y-m-d H:i:s'));
}

// Vrátit success response s užitečnými informacemi
echo json_encode([
    'status' => 'success',
    'message' => 'Session obnovena',
    'logged_in' => true,
    'user_id' => $_SESSION['user_id'],
    'user_email' => $_SESSION['user_email'] ?? '',
    'last_activity' => date('Y-m-d H:i:s', $_SESSION['last_activity']),
    'session_timeout' => ini_get('session.gc_maxlifetime') . ' sekund'
]);
