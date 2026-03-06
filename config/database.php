<?php
/**
 * Database – zpětná kompatibilita
 * Skutečné připojení spravuje getDbConnection() v config.php.
 * Tato třída pouze deleguje, aby nevznikalo druhé PDO spojení.
 */

require_once __DIR__ . '/config.php';

class Database {
    public static function getInstance(): self {
        static $inst = null;
        if ($inst === null) {
            $inst = new self();
        }
        return $inst;
    }

    public function getConnection(): PDO {
        return getDbConnection();
    }

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton"); }
}
