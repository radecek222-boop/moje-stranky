<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file
 */

/**
 * LoadEnvFile
 *
 * @param mixed $path Path
 */
if (!function_exists('loadEnvFile')) {
    function loadEnvFile($path) {
        if (!file_exists($path)) {
            // BEZPEČNOST: Neodhalovat absolutní cestu, ale zaznamenat problém
            error_log('WARNING: .env file not found at expected path: ' . $path);
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                $value = trim($value, '"\'');

                // Set as environment variable and define constant
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        return true;
    }
}

// Load .env file from www directory
$envPath = __DIR__ . '/../.env';
loadEnvFile($envPath);

/**
 * Get environment value with fallback
 *
 * @param string $key Environment variable key
 * @param mixed $default Default value if not found
 * @return mixed
 */
if (!function_exists('getEnvValue')) {
    function getEnvValue($key, $default = null) {
        // Check $_SERVER first (most reliable for web)
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        // Check $_ENV
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        // Check getenv()
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return $default;
    }
}

/**
 * Require environment value (throw error if not found)
 *
 * @param string $key Environment variable key
 * @param string $errorMsg Error message if not found
 * @return mixed
 */
if (!function_exists('requireEnvValue')) {
    function requireEnvValue($key, $errorMsg = null) {
        $value = getEnvValue($key);

        if ($value === null || $value === false || $value === '') {
            if ($errorMsg) {
                error_log("MISSING ENV VAR: {$key} - {$errorMsg}");
                die($errorMsg);
            }
            error_log("MISSING ENV VAR: {$key}");
            die("Required environment variable {$key} is not set!");
        }

        return $value;
    }
}

// Define DB constants if not already defined (fallback to $_SERVER or defaults)
if (!defined('DB_HOST')) {
    define('DB_HOST', getEnvValue('DB_HOST', 'localhost'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getEnvValue('DB_NAME', 'wgs-servicecz01'));
}
if (!defined('DB_USER')) {
    define('DB_USER', getEnvValue('DB_USER', 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getEnvValue('DB_PASS', ''));
}
?>
