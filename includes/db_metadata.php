<?php
/**
 * Database metadata helper functions
 */

if (!function_exists('db_get_table_columns')) {
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
    function db_table_has_column(PDO $pdo, string $table, string $column): bool
    {
        $columns = db_get_table_columns($pdo, $table);
        return in_array($column, $columns, true);
    }
}
