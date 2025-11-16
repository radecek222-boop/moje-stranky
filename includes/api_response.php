<?php

if (!class_exists('ApiResponse')) {
    class ApiResponse
    {
        /**
         * Ensure JSON header is sent exactly once.
         */
        private static function ensureJsonHeader(): void
        {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
        }

        /**
         * Normalize payloads so controllers can continue providing associative arrays
         * without having to care about a strict schema.
         *
         * @param mixed $data
         * @return array
         */
        private static function normalizeData($data): array
        {
            if ($data === null) {
                return [];
            }

            if (is_array($data)) {
                return $data;
            }

            return ['data' => $data];
        }

        /**
         * Output a success response and terminate.
         */
        public static function success($data = [], ?string $message = null, ?array $meta = null, int $httpCode = 200): void
        {
            self::ensureJsonHeader();
            http_response_code($httpCode);

            $payload = array_merge(['status' => 'success'], self::normalizeData($data));

            if ($message !== null) {
                $payload['message'] = $message;
            }

            if (!empty($meta)) {
                $payload['meta'] = $meta;
            }

            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }

        /**
         * Output an error response and terminate.
         */
        public static function error(string $message, int $httpCode = 400, $details = null): void
        {
            self::ensureJsonHeader();
            http_response_code($httpCode);

            $payload = [
                'status' => 'error',
                'message' => $message,
            ];

            if ($details !== null) {
                $payload['details'] = $details;
            }

            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!function_exists('respondSuccess')) {
    function respondSuccess($data = [], ?string $message = null, int $httpCode = 200): void
    {
        ApiResponse::success($data, $message, null, $httpCode);
    }
}

if (!function_exists('respondError')) {
    function respondError($message, int $httpCode = 400, $details = null): void
    {
        ApiResponse::error($message, $httpCode, $details);
    }
}
