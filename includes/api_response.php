<?php

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
    public static function error($message = 'Došlo k chybě.', int $httpCode = 400, $details = null): void
    {
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
    function respondSuccess($data = [], $message = null, $httpCode = 200)
    {
        ApiResponse::success($data, $message, null, $httpCode);
    }
}

if (!function_exists('respondError')) {
    function respondError($message, $httpCode = 400, $details = null)
    {
        ApiResponse::error($message, $httpCode, $details);
    }
}
