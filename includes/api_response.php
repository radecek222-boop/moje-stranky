<?php}

/**
 * Backward compatibility - funkce pro staré API
 * Postupně nahradit za ApiResponse třídu
 */
function respondSuccess($data, $message = null, $httpCode = 200) {
    ApiResponse::success($data, $message, null, $httpCode);
}

/**
 * RespondError
 *
 * @param mixed $message Message
 * @param mixed $httpCode HttpCode
 * @param mixed $details Details
 */
function respondError($message, $httpCode = 400, $details = null) {
    ApiResponse::error($message, $httpCode, $details);
}
