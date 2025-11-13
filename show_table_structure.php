<?php
/**
 * Zobrazení struktury tabulky wgs_reklamace
 * BEZPEČNOST: Pouze pro přihlášené uživatele
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Kontrola přihlášení
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    http_response_code(401);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center; background: #fff;"><h2 style="color: #000; text-transform: uppercase; letter-spacing: 0.1em;">PŘÍSTUP ODEPŘEN</h2><p style="color: #555;">Musíte být přihlášeni pro zobrazení této stránky.</p><p><a href="/login" style="color: #000; border-bottom: 2px solid #000; text-decoration: none;">Přihlásit se</a></p></body></html>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Struktura tabulky wgs_reklamace</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wgs-white: #FFFFFF;
            --wgs-black: #000000;
            --wgs-grey: #555555;
            --wgs-light-grey: #999999;
            --wgs-border: #E0E0E0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            padding: 2rem;
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--wgs-white);
            border: 2px solid var(--wgs-black);
        }

        .header {
            background: var(--wgs-black);
            color: var(--wgs-white);
            padding: 2rem;
            border-bottom: 2px solid var(--wgs-black);
        }

        h1 {
            color: inherit;
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0;
        }

        .content {
            padding: 2rem;
        }

        h2 {
            color: var(--wgs-black);
            margin-top: 2rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--wgs-black);
            padding-bottom: 0.75rem;
            font-size: 1.125rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        h2:first-child {
            margin-top: 0;
        }

        h3 {
            color: var(--wgs-black);
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.75rem;
            border: 2px solid var(--wgs-black);
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid var(--wgs-border);
        }

        th {
            background: var(--wgs-black);
            color: var(--wgs-white);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tr:hover {
            background: var(--wgs-border);
        }

        pre {
            background: var(--wgs-border);
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.75rem;
            border: 1px solid var(--wgs-black);
            margin: 1rem 0;
        }

        code {
            background: var(--wgs-border);
            padding: 0.25rem 0.5rem;
            font-family: monospace;
            color: var(--wgs-black);
            border: 1px solid var(--wgs-black);
            font-size: 0.75rem;
        }

        .solution {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .solution h2, .solution h3 {
            border: none;
            margin-top: 0;
            padding: 0;
        }

        .solution h2 {
            margin-bottom: 1rem;
        }

        .solution h3 {
            margin-top: 1rem;
            margin-bottom: 0.75rem;
        }

        .solution p {
            color: var(--wgs-grey);
            font-size: 0.875rem;
            margin: 0.75rem 0;
        }

        .solution ul {
            margin: 0.75rem 0 0.75rem 1.5rem;
            color: var(--wgs-grey);
            font-size: 0.875rem;
        }

        .solution li {
            margin: 0.5rem 0;
        }

        .ok {
            color: var(--wgs-black);
            font-weight: 600;
        }

        .error {
            color: var(--wgs-grey);
            font-weight: 600;
        }

        .error-box {
            color: var(--wgs-black);
            padding: 1.5rem;
            background: var(--wgs-white);
            border: 2px solid var(--wgs-black);
            margin: 1.5rem 0;
        }

        .error-box h2 {
            border: none;
            margin: 0 0 1rem 0;
            padding: 0;
        }

        .footer {
            margin-top: 2rem;
            padding: 1.5rem 2rem;
            border-top: 2px solid var(--wgs-border);
            text-align: center;
            color: var(--wgs-light-grey);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>STRUKTURA TABULKY WGS_REKLAMACE</h1>
        </div>

        <div class="content">
            <?php
            try {
                $pdo = getDbConnection();

                // Získat strukturu tabulky
                $stmt = $pdo->query("DESCRIBE wgs_reklamace");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo '<h2>SLOUPCE V TABULCE WGS_REKLAMACE</h2>';
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
                echo '<h2>ANALÝZA</h2>';

                if ($hasCreatedBy) {
                    echo '<p class="ok">[OK] Sloupec <code>created_by</code> existuje!</p>';
                } else {
                    echo '<p class="error">[X] Sloupec <code>created_by</code> NEEXISTUJE!</p>';
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
                    echo '<h3>Sloupce související s uživateli:</h3>';
                    echo '<ul>';
                    foreach ($userRelatedColumns as $col) {
                        echo '<li><code>' . htmlspecialchars($col) . '</code></li>';
                    }
                    echo '</ul>';
                }

                echo '</div>';

                // Řešení
                echo '<div class="solution">';
                echo '<h2>ŘEŠENÍ</h2>';

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
                echo '<h2>UKÁZKA DAT V TABULCE (PRVNÍ 3 ZÁZNAMY)</h2>';
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
                echo '<div class="error-box">';
                echo '<h2>CHYBA</h2>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
        </div>

        <div class="footer">
            <small>WGS SERVICE DEBUG © 2025</small>
        </div>
    </div>
</body>
</html>
