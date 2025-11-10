<?php
/**
 * Zobrazení struktury tabulky wgs_reklamace
 * BEZPEČNOST: Pouze pro přihlášené uživatele
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Kontrola přihlášení
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    http_response_code(401);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Arial; padding: 40px; text-align: center;"><h1>🔒 Přístup odepřen</h1><p>Musíte být přihlášeni pro zobrazení této stránky.</p><p><a href="/login" style="color: #2196F3;">Přihlásit se</a></p></body></html>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Struktura tabulky wgs_reklamace</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:hover { background: #f9f9f9; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .solution { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Struktura tabulky wgs_reklamace</h1>

        <?php
        try {
            $pdo = getDbConnection();

            // Získat strukturu tabulky
            $stmt = $pdo->query("DESCRIBE wgs_reklamace");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo '<h2>📋 Sloupce v tabulce wgs_reklamace</h2>';
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>';
            echo '</tr></thead><tbody>';

            $hasCreatedBy = false;
            $hasUserId = false;
            $columnNames = [];

            foreach ($columns as $col) {
                $columnNames[] = $col['Field'];

                if ($col['Field'] === 'created_by') {
                    $hasCreatedBy = true;
                }
                if (stripos($col['Field'], 'user') !== false || stripos($col['Field'], 'operator') !== false) {
                    $hasUserId = true;
                }

                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($col['Field']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($col['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Key']) . '</td>';
                echo '<td>' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($col['Extra']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            echo '<div class="solution">';
            echo '<h2>🔍 Analýza</h2>';

            if ($hasCreatedBy) {
                echo '<p style="color: green;">✅ Sloupec <code>created_by</code> existuje!</p>';
            } else {
                echo '<p style="color: red;">❌ Sloupec <code>created_by</code> NEEXISTUJE!</p>';
                echo '<p>To je důvod, proč load.php nefunguje správně.</p>';
            }

            // Hledej podobné sloupce
            $userRelatedColumns = array_filter($columnNames, function($name) {
                return stripos($name, 'user') !== false
                    || stripos($name, 'operator') !== false
                    || stripos($name, 'zpracoval') !== false
                    || stripos($name, 'vytvoril') !== false
                    || stripos($name, 'assigned') !== false;
            });

            if (!empty($userRelatedColumns)) {
                echo '<h3>📌 Sloupce související s uživateli:</h3>';
                echo '<ul>';
                foreach ($userRelatedColumns as $col) {
                    echo '<li><code>' . htmlspecialchars($col) . '</code></li>';
                }
                echo '</ul>';
            }

            echo '</div>';

            // Řešení
            echo '<div class="solution">';
            echo '<h2>💡 ŘEŠENÍ</h2>';

            if (!$hasCreatedBy) {
                echo '<h3>Možnost 1: Přidat sloupec created_by</h3>';
                echo '<pre>';
                echo "ALTER TABLE wgs_reklamace\n";
                echo "ADD COLUMN created_by INT NULL COMMENT 'ID uživatele který vytvořil reklamaci',\n";
                echo "ADD COLUMN created_by_role VARCHAR(20) NULL COMMENT 'Role uživatele (admin, user, guest)',\n";
                echo "ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Kdy byla vytvořena',\n";
                echo "ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Kdy byla aktualizována';";
                echo '</pre>';

                echo '<h3>Možnost 2: Aktualizovat existující data</h3>';
                echo '<p>Pokud přidáš sloupec, musíš nastavit <code>created_by=7</code> pro všechny reklamace vytvořené naty@naty.cz:</p>';
                echo '<pre>';
                echo "-- Nastav created_by pro všechny existující reklamace\n";
                echo "UPDATE wgs_reklamace SET created_by = 7, created_by_role = 'user';";
                echo '</pre>';

                echo '<h3>Možnost 3: Upravit load.php aby nepoužíval created_by</h3>';
                echo '<p>Pokud nechceš měnit databázi, můžeme upravit load.php aby zobrazoval všechny reklamace pro ne-admin uživatele.</p>';
            }

            echo '</div>';

            // Ukázka dat
            echo '<h2>📄 Ukázka dat v tabulce (první 3 záznamy)</h2>';
            $stmt = $pdo->query("SELECT * FROM wgs_reklamace ORDER BY id DESC LIMIT 3");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($data)) {
                echo '<table>';
                echo '<thead><tr>';
                foreach (array_keys($data[0]) as $colName) {
                    echo '<th>' . htmlspecialchars($colName) . '</th>';
                }
                echo '</tr></thead><tbody>';

                foreach ($data as $row) {
                    echo '<tr>';
                    foreach ($row as $value) {
                        echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
                    }
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }

        } catch (Exception $e) {
            echo '<div style="color: red; padding: 20px; background: #ffebee; border-radius: 4px;">';
            echo '<h2>❌ CHYBA</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999;">
            <small>WGS Service Debug © 2025</small>
        </div>
    </div>
</body>
</html>
