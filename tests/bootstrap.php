<?php
/**
 * PHPUnit Bootstrap File
 * Inicializace testovacího prostředí
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test mode
define('TEST_MODE', true);

// Root path
define('ROOT_PATH', dirname(__DIR__));

// Load environment variables (test .env)
$envFile = ROOT_PATH . '/.env.testing';
if (!file_exists($envFile)) {
    // Fallback na běžný .env pokud neexistuje testing varianta
    $envFile = ROOT_PATH . '/.env';
}

if (file_exists($envFile)) {
    require_once ROOT_PATH . '/includes/env_loader.php';
}

// Start session for tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load init.php (ale bez redirect a některých security headers)
// Pro testy potřebujeme jen databázové připojení a utility funkce
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

// Helper funkce pro testy
/**
 * Vyčistí testovací databázi
 */
function cleanTestDatabase() {
    if (!defined('TEST_MODE')) {
        throw new Exception('Nelze vyčistit produkční databázi!');
    }

    // TODO: Implementovat čištění testovací DB
}

/**
 * Vytvoří mock PDO připojení pro testy
 */
function getMockPdo() {
    // Pro unit testy můžeme použít SQLite in-memory databázi
    try {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('Nelze vytvořit mock PDO: ' . $e->getMessage());
    }
}

/**
 * Sanitizace vstupu - kopie z produkční funkce
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

echo "\n✓ PHPUnit bootstrap načten\n";
echo "✓ Test mode: " . (defined('TEST_MODE') ? 'ANO' : 'NE') . "\n";
echo "✓ Root path: " . ROOT_PATH . "\n\n";
