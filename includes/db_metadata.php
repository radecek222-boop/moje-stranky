<?php
/**
 * Database metadata helper functions
 */

if (!function_exists('db_get_table_columns')) {
        /**
     * Db get table columns
     *
     * @param PDO $pdo Pdo
     * @param string $table Table
     */
function db_get_table_columns(PDO $pdo, string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $columns = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                if (isset($row['Field'])) {
                    $columns[] = $row['Field'];
                }
            }
        } catch (Throwable $e) {
            error_log('Column introspection failed for table ' . $table . ': ' . $e->getMessage());
        }

        return $cache[$table] = $columns;
    }
}

if (!function_exists('db_table_has_column')) {
        /**
     * Db table has column
     *
     * @param PDO $pdo Pdo
     * @param string $table Table
     * @param string $column Column
     */
function db_table_has_column(PDO $pdo, string $table, string $column): bool
    {
        $columns = db_get_table_columns($pdo, $table);
        return in_array($column, $columns, true);
    }
}

if (!function_exists('db_table_exists')) {
        /**
     * Db table exists
     *
     * @param PDO $pdo Pdo
     * @param string $table Table
     */
function db_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            // SHOW TABLES LIKE doesn't support placeholders - use INFORMATION_SCHEMA instead
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name = :table'
            );
            $stmt->execute([':table' => $table]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Table existence check failed for ' . $table . ': ' . $e->getMessage());
            $exists = false;
        }

        return $cache[$table] = $exists;
    }
}
