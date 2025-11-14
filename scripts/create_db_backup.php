<?php
/**
 * Database Backup System
 * Vytvoří plnou zálohu databáze před kritickými změnami
 *
 * Použití:
 * - Z příkazové řádky: php scripts/create_db_backup.php
 * - Z Control Center: přes akci "Vytvořit zálohu DB"
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class DatabaseBackup {
    private $pdo;
    private $backupDir;

    public function __construct() {
        $db = Database::getInstance();
        $this->pdo = $db->getConnection();
        $this->backupDir = ROOT_PATH . '/backups/database';

        // Vytvořit backup adresář pokud neexistuje
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0750, true);
        }
    }

    /**
     * Vytvoří plnou zálohu databáze
     *
     * @param string $reason Důvod zálohy (pro logging)
     * @return array ['success' => bool, 'file' => string, 'size' => int, 'message' => string]
     */
    public function createBackup($reason = 'manual') {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}_{$reason}.sql";
            $filepath = $this->backupDir . '/' . $filename;

            // Získat seznam všech tabulek
            $tables = [];
            $result = $this->pdo->query('SHOW TABLES');
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            if (empty($tables)) {
                return [
                    'success' => false,
                    'message' => 'Žádné tabulky k zálohování'
                ];
            }

            $output = "-- Database Backup\n";
            $output .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Reason: $reason\n";
            $output .= "-- Database: " . DB_NAME . "\n";
            $output .= "-- Tables: " . count($tables) . "\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n";
            $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
            $output .= "SET AUTOCOMMIT = 0;\n";
            $output .= "START TRANSACTION;\n\n";

            // Pro každou tabulku
            foreach ($tables as $table) {
                $output .= "-- --------------------------------------------------------\n";
                $output .= "-- Table: $table\n";
                $output .= "-- --------------------------------------------------------\n\n";

                // DROP TABLE IF EXISTS
                $output .= "DROP TABLE IF EXISTS `$table`;\n";

                // CREATE TABLE
                $createTableStmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
                $createTable = $createTableStmt->fetch(PDO::FETCH_ASSOC);
                $output .= $createTable['Create Table'] . ";\n\n";

                // INSERT DATA (po dávkách pro velké tabulky)
                $rowCount = $this->pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();

                if ($rowCount > 0) {
                    $output .= "-- Data for table $table ($rowCount rows)\n";

                    $limit = 1000; // Dávky po 1000 řádcích
                    $offset = 0;

                    while ($offset < $rowCount) {
                        $dataStmt = $this->pdo->query("SELECT * FROM `$table` LIMIT $limit OFFSET $offset");
                        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($rows)) {
                            $output .= "INSERT INTO `$table` VALUES\n";
                            $values = [];

                            foreach ($rows as $row) {
                                $rowValues = [];
                                foreach ($row as $value) {
                                    if ($value === null) {
                                        $rowValues[] = 'NULL';
                                    } else {
                                        $rowValues[] = $this->pdo->quote($value);
                                    }
                                }
                                $values[] = '(' . implode(', ', $rowValues) . ')';
                            }

                            $output .= implode(",\n", $values) . ";\n";
                        }

                        $offset += $limit;
                    }

                    $output .= "\n";
                }
            }

            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
            $output .= "COMMIT;\n";

            // Zapsat do souboru
            file_put_contents($filepath, $output);

            // Zkomprimovat (gzip) pro úsporu místa
            $gzFilepath = $filepath . '.gz';
            $gz = gzopen($gzFilepath, 'w9');
            gzwrite($gz, $output);
            gzclose($gz);

            // Smazat nekomprimovaný soubor
            unlink($filepath);

            $size = filesize($gzFilepath);
            $sizeHuman = $this->formatBytes($size);

            // Logovat
            error_log("DB Backup created: $filename.gz ($sizeHuman) - Reason: $reason");

            // Vyčistit staré zálohy (starší než 30 dní)
            $this->cleanOldBackups(30);

            return [
                'success' => true,
                'file' => $filename . '.gz',
                'filepath' => $gzFilepath,
                'size' => $size,
                'size_human' => $sizeHuman,
                'tables' => count($tables),
                'message' => "Záloha databáze úspěšně vytvořena ($sizeHuman, " . count($tables) . " tabulek)"
            ];

        } catch (Exception $e) {
            error_log("DB Backup failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Chyba při vytváření zálohy: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vyčistí staré zálohy
     */
    private function cleanOldBackups($daysToKeep = 30) {
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        $now = time();
        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $daysToKeep) {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            error_log("Cleaned $deleted old database backups");
        }

        return $deleted;
    }

    /**
     * Formátuje velikost v bytech na lidsky čitelný formát
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Vrátí seznam existujících záloh
     */
    public function listBackups() {
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'size_human' => $this->formatBytes(filesize($file)),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'age_days' => floor((time() - filemtime($file)) / (60 * 60 * 24))
            ];
        }

        // Seřadit od nejnovějšího
        usort($backups, function($a, $b) {
            return filemtime($b['filepath']) - filemtime($a['filepath']);
        });

        return $backups;
    }
}

// Pokud je skript spuštěn z příkazové řádky
if (php_sapi_name() === 'cli') {
    $reason = $argv[1] ?? 'manual';
    $backup = new DatabaseBackup();
    $result = $backup->createBackup($reason);

    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        echo "  Soubor: " . $result['file'] . "\n";
        exit(0);
    } else {
        echo "✗ " . $result['message'] . "\n";
        exit(1);
    }
}
