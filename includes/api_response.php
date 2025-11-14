<?php
/**
 * HIGH PRIORITY FIX: API Response Standardization
 *
 * Jednotný formát pro všechny API odpovědi v projektu
 *
 * Standardní formát:
 * {
 *   "status": "success" | "error",
 *   "data": {...},           // pouze při success
 *   "message": "...",        // human-readable zpráva
 *   "error": {...},          // pouze při error
 *   "meta": {...}            // volitelné metadata (pagination, atd.)
 * }
 *
 * Použití:
 * - ApiResponse::success($data, $message, $meta)
 * - ApiResponse::error($message, $code, $details)
 */

class ApiResponse {
    /**
     * Success response
     *
     * @param mixed $data Data k vrácení
     * @param string|null $message Volitelná zpráva
     * @param array|null $meta Metadata (pagination, atd.)
     * @param int $httpCode HTTP status code (default: 200)
     * @return never
     */
    public static function success($data = null, $message = null, $meta = null, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = ['status' => 'success'];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Error response
     *
     * @param string $message Chybová zpráva
     * @param int $httpCode HTTP status code (default: 400)
     * @param array|null $details Detaily chyby
     * @param string|null $errorCode Machine-readable error code
     * @return never
     */
    public static function error($message, $httpCode = 400, $details = null, $errorCode = null) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if ($errorCode !== null) {
            $response['error'] = [
                'code' => $errorCode,
                'message' => $message
            ];

            if ($details !== null) {
                $response['error']['details'] = $details;
            }
        } elseif ($details !== null) {
            $response['error'] = $details;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Paginated success response
     *
     * @param array $items Items na aktuální stránce
     * @param int $total Celkový počet items
     * @param int $page Aktuální stránka
     * @param int $perPage Items na stránku
     * @param string|null $message Volitelná zpráva
     * @return never
     */
    public static function paginated($items, $total, $page, $perPage, $message = null) {
        $meta = [
            'pagination' => [
                'total' => $total,
                'count' => count($items),
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ]
        ];

        self::success($items, $message, $meta);
    }

    /**
     * Not found error (404)
     *
     * @param string $resource Resource název (např. "User", "Claim")
     * @param string|int|null $id Resource ID
     * @return never
     */
    public static function notFound($resource, $id = null) {
        $message = $id !== null
            ? "{$resource} s ID '{$id}' nebyl nalezen"
            : "{$resource} nebyl nalezen";

        self::error($message, 404, null, 'RESOURCE_NOT_FOUND');
    }

    /**
     * Validation error (422)
     *
     * @param array $errors Pole chyb ['field' => 'error message']
     * @param string|null $message Hlavní zpráva
     * @return never
     */
    public static function validationError($errors, $message = null) {
        $message = $message ?? 'Validace selhala';

        self::error($message, 422, ['validation_errors' => $errors], 'VALIDATION_ERROR');
    }

    /**
     * Unauthorized error (401)
     *
     * @param string|null $message Volitelná zpráva
     * @return never
     */
    public static function unauthorized($message = null) {
        $message = $message ?? 'Neautorizovaný přístup. Přihlaste se prosím.';
        self::error($message, 401, null, 'UNAUTHORIZED');
    }

    /**
     * Forbidden error (403)
     *
     * @param string|null $message Volitelná zpráva
     * @return never
     */
    public static function forbidden($message = null) {
        $message = $message ?? 'Přístup odepřen. Nemáte oprávnění k této akci.';
        self::error($message, 403, null, 'FORBIDDEN');
    }

    /**
     * Rate limit exceeded (429)
     *
     * @param int|null $retryAfter Sekund do resetu
     * @param string|null $message Volitelná zpráva
     * @return never
     */
    public static function rateLimitExceeded($retryAfter = null, $message = null) {
        $message = $message ?? 'Příliš mnoho požadavků. Zkuste to prosím později.';

        $details = $retryAfter !== null ? ['retry_after' => $retryAfter] : null;

        if ($retryAfter !== null) {
            header("Retry-After: {$retryAfter}");
        }

        self::error($message, 429, $details, 'RATE_LIMIT_EXCEEDED');
    }

    /**
     * Server error (500)
     *
     * @param string|null $message Volitelná zpráva
     * @param mixed $debug Debug info (pouze v development)
     * @return never
     */
    public static function serverError($message = null, $debug = null) {
        $message = $message ?? 'Interní chyba serveru. Zkuste to prosím později.';

        // Show debug only in development
        $details = null;
        if ($debug !== null && (defined('APP_ENV') && APP_ENV === 'development')) {
            $details = ['debug' => $debug];
        }

        self::error($message, 500, $details, 'SERVER_ERROR');
    }

    /**
     * Created response (201)
     *
     * @param mixed $data Vytvořená entita
     * @param string|null $message Volitelná zpráva
     * @param string|null $location URL nového resource
     * @return never
     */
    public static function created($data, $message = null, $location = null) {
        if ($location !== null) {
            header("Location: {$location}");
        }

        self::success($data, $message ?? 'Resource úspěšně vytvořen', null, 201);
    }

    /**
     * No content response (204)
     *
     * @return never
     */
    public static function noContent() {
        http_response_code(204);
        exit;
    }
}

/**
 * Backward compatibility - funkce pro staré API
 * Postupně nahradit za ApiResponse třídu
 */
function respondSuccess($data, $message = null, $httpCode = 200) {
    ApiResponse::success($data, $message, null, $httpCode);
}

function respondError($message, $httpCode = 400, $details = null) {
    ApiResponse::error($message, $httpCode, $details);
}
