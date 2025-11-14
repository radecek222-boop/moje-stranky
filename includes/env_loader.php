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

// Load .env file from www directory
$envPath = __DIR__ . '/../.env';
loadEnvFile($envPath);
?>
