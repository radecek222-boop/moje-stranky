<?php
declare(strict_types=1);

/**
 * Jednotná JSON odpověď pro admin/api endpointy
 */
class ApiResponse
{
    /**
     * Vrátí úspěšnou odpověď
     */
    public static function success($data = [], ?string $message = null, $meta = null, int $httpCode = 200): void
    {
        // OPRAVA: Vyčistit output buffer PŘED odesláním odpovědi
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($httpCode);

        $payload = ['status' => 'success'];
        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        if (is_array($data)) {
            $payload = array_merge($payload, $data);
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Vrátí chybovou odpověď
     */
    public static function error(string $message = 'Došlo k chybě.', int $httpCode = 400, mixed $details = null): void
    {
        // Diagnostika - logovat vsechny chyby s kontextem
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        // Logovat pouze pro hry API (aby nezahltilo log)
        if (strpos($requestUri, 'hry_api') !== false) {
            error_log("API ERROR {$httpCode} | action='{$action}' | msg='{$message}' | method=" . ($_SERVER['REQUEST_METHOD'] ?? '') . " | GET=" . json_encode($_GET) . " | POSTkeys=" . json_encode(array_keys($_POST)));
        }

        // OPRAVA: Vyčistit output buffer PŘED odesláním odpovědi
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($httpCode);

        $payload = [
            'status' => 'error',
            'message' => $message ?? 'Došlo k chybě.'
        ];

        if ($details !== null) {
            $payload['details'] = $details;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('respondSuccess')) {
    function respondSuccess(mixed $data = [], ?string $message = null, int $httpCode = 200): void
    {
        ApiResponse::success($data, $message, null, $httpCode);
    }
}

if (!function_exists('respondError')) {
    function respondError(string $message, int $httpCode = 400, mixed $details = null): void
    {
        ApiResponse::error($message, $httpCode, $details);
    }
}

// Aliasy pro zpětnou kompatibilitu
if (!function_exists('sendJsonSuccess')) {
    function sendJsonSuccess(?string $message = null, mixed $data = [], int $httpCode = 200): void
    {
        ApiResponse::success($data, $message, null, $httpCode);
    }
}

if (!function_exists('sendJsonError')) {
    function sendJsonError(string $message, int $httpCode = 400, mixed $details = null): void
    {
        ApiResponse::error($message, $httpCode, $details);
    }
}
