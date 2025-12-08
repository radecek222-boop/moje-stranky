<?php
/**
 * FINGERPRINT STORE API ENDPOINT
 *
 * Receives device fingerprint components from client and stores them in database.
 * Links fingerprints to sessions for cross-session tracking.
 *
 * Module #1 of Enterprise Analytics System
 *
 * Endpoint: POST /api/fingerprint_store.php
 *
 * Security:
 * - CSRF token validation
 * - Rate limiting (100 requests/hour per session)
 * - Input validation and sanitization
 * - JSON-only responses
 *
 * @package WGS_Analytics
 * @version 1.0.0
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/FingerprintEngine.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Use POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send JSON error response
 */
function sendError(string $message, int $statusCode = 400, array $errors = []): void {
    http_response_code($statusCode);
    $response = [
        'status' => 'error',
        'message' => $message
    ];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send JSON success response
 */
function sendSuccess(array $data): void {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        ...$data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Get database connection
    $pdo = getDbConnection();

    // Parse JSON body
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON payload', 400);
    }

    // CSRF token validation
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

    if (!validateCSRFToken($csrfToken)) {
        error_log('Fingerprint API: Invalid CSRF token from IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        sendError('Invalid CSRF token', 403);
    }

    // Rate limiting
    $sessionId = $data['session_id'] ?? '';

    if (empty($sessionId)) {
        sendError('Missing required field: session_id', 400);
    }

    // Check rate limit (100 requests per hour per session)
    $rateLimitKey = 'fingerprint_api_' . $sessionId;
    $cacheFile = __DIR__ . '/../logs/rate_limit_' . md5($rateLimitKey) . '.txt';

    if (file_exists($cacheFile)) {
        $rateData = json_decode(file_get_contents($cacheFile), true);
        $requestCount = $rateData['count'] ?? 0;
        $windowStart = $rateData['window_start'] ?? 0;

        // Check if window expired (1 hour = 3600 seconds)
        if (time() - $windowStart < 3600) {
            if ($requestCount >= 100) {
                sendError('Rate limit exceeded. Try again in ' . (3600 - (time() - $windowStart)) . ' seconds.', 429);
            }
            $rateData['count']++;
        } else {
            // Reset window
            $rateData = ['count' => 1, 'window_start' => time()];
        }
    } else {
        $rateData = ['count' => 1, 'window_start' => time()];
    }

    file_put_contents($cacheFile, json_encode($rateData));

    // Validate required field: fingerprint_components
    if (!isset($data['fingerprint_components']) || !is_array($data['fingerprint_components'])) {
        sendError('Missing required field: fingerprint_components', 400);
    }

    $components = $data['fingerprint_components'];
    $userAgent = $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Add user agent to components
    $components['user_agent'] = $userAgent;

    // Validate components structure
    $validationErrors = [];

    // At least one fingerprint method must be present
    if (empty($components['canvas_hash']) &&
        empty($components['webgl_vendor']) &&
        empty($components['audio_hash'])) {
        $validationErrors[] = 'At least one fingerprint component (canvas, webgl, or audio) is required';
    }

    // Validate numeric fields
    if (isset($components['screen_width']) && (!is_numeric($components['screen_width']) || $components['screen_width'] <= 0)) {
        $validationErrors[] = 'Invalid screen_width';
    }

    if (isset($components['screen_height']) && (!is_numeric($components['screen_height']) || $components['screen_height'] <= 0)) {
        $validationErrors[] = 'Invalid screen_height';
    }

    if (isset($components['pixel_ratio']) && (!is_numeric($components['pixel_ratio']) || $components['pixel_ratio'] < 1.0)) {
        $validationErrors[] = 'Invalid pixel_ratio';
    }

    if (!empty($validationErrors)) {
        sendError('Invalid fingerprint components', 400, $validationErrors);
    }

    // Initialize Fingerprint Engine
    $fingerprintEngine = new FingerprintEngine($pdo);

    // Store fingerprint
    $result = $fingerprintEngine->storeFingerprint($components);

    // Link to session
    $fingerprintEngine->linkToSession($result['fingerprint_id'], $sessionId);

    // Log successful fingerprint storage
    error_log(sprintf(
        'Fingerprint stored: %s (is_new: %s, session_count: %d) for session %s',
        $result['fingerprint_id'],
        $result['is_new'] ? 'true' : 'false',
        $result['session_count'],
        $sessionId
    ));

    // Send success response
    sendSuccess([
        'fingerprint_id' => $result['fingerprint_id'],
        'is_new' => $result['is_new'],
        'session_count' => $result['session_count'],
        'first_seen' => $result['first_seen'],
        'last_seen' => $result['last_seen']
    ]);

} catch (InvalidArgumentException $e) {
    // Validation error from FingerprintEngine
    error_log('Fingerprint API validation error: ' . $e->getMessage());
    sendError($e->getMessage(), 400);

} catch (PDOException $e) {
    // Database error
    error_log('Fingerprint API database error: ' . $e->getMessage());
    sendError('Database error occurred. Please try again later.', 500);

} catch (Exception $e) {
    // General error
    error_log('Fingerprint API error: ' . $e->getMessage());
    sendError('An unexpected error occurred. Please try again later.', 500);
}
