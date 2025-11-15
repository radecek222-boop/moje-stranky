<?php
/**
 * Database Connection Wrapper
 * Singleton pattern pro PDO připojení
 */

// Načíst config (konstanty DB_HOST, DB_NAME, atd.)
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Nepodařilo se připojit k databázi");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Zabránit klonování
    private function __clone() {}
    
    // Zabránit unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}