<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file
 */

function loadEnvFile($path) {
    if (!file_exists($path)) {
        die('CHYBA: .env soubor nenalezen na cestě: ' . $path);
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
}

// Load .env file from www directory
$envPath = __DIR__ . '/../.env';
loadEnvFile($envPath);
?>
