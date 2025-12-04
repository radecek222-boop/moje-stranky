<?php
/**
 * PHPUnit Bootstrap
 * Step 151: Test environment setup
 *
 * Tento soubor se načte před každým testem.
 * Nastavuje autoloading a testovací prostředí.
 */

declare(strict_types=1);

// Autoload Composer dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Definovat konstanty pro testovací prostředí
define('WGS_TESTING', true);
define('WGS_ROOT', dirname(__DIR__));

// Nastavit error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Fake session pro testy (bez skutečného session_start)
if (!isset($_SESSION)) {
    $_SESSION = [];
}

// Mock funkce pro testování bez skutečné DB
if (!function_exists('getDbConnection')) {
    /**
     * Mock database connection pro unit testy
     * Integration testy mohou použít skutečnou DB
     */
    function getDbConnection(): ?PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            // SQLite in-memory pro izolované testy
            $pdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        }

        return $pdo;
    }
}

// Helper pro načtení testovacího schématu
function loadTestSchema(PDO $pdo): void
{
    // Základní tabulky pro testy
    $schema = <<<SQL
        CREATE TABLE IF NOT EXISTS wgs_users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS wgs_reklamace (
            reklamace_id INTEGER PRIMARY KEY AUTOINCREMENT,
            jmeno TEXT NOT NULL,
            telefon TEXT,
            email TEXT,
            adresa TEXT,
            popis_problemu TEXT,
            stav TEXT DEFAULT 'wait',
            datum_vytvoreni TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS wgs_registration_keys (
            key_id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_code TEXT UNIQUE NOT NULL,
            key_type TEXT DEFAULT 'standard',
            max_usage INTEGER DEFAULT 1,
            usage_count INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1
        );
SQL;

    $pdo->exec($schema);
}

// Helper pro reset testovací DB
function resetTestDatabase(): void
{
    $pdo = getDbConnection();
    $pdo->exec('DELETE FROM wgs_users');
    $pdo->exec('DELETE FROM wgs_reklamace');
    $pdo->exec('DELETE FROM wgs_registration_keys');
}
