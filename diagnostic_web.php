<?php
/**
 * WEB Diagnostika reklamací
 * Otevři v prohlížeči: https://wgs-service.cz/diagnostic_web.php
 */

require_once __DIR__ . '/init.php';

// Pouze pro adminy nebo přihlášené uživatele
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    die('<h1>⛔ Přístup odepřen</h1><p>Musíš být přihlášený pro zobrazení diagnostiky.</p>');
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostika reklamací - WGS Service</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid #4CAF50;
        }
        .section.warning {
            border-left-color: #ff9800;
            background: #fff3e0;
        }
        .section.error {
            border-left-color: #f44336;
            background: #ffebee;
        }
        h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: 600;
            color: #555;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge.success { background: #4CAF50; color: white; }
        .badge.warning { background: #ff9800; color: white; }
        .badge.error { background: #f44336; color: white; }
        .badge.info { background: #2196F3; color: white; }
        .stat {
            display: inline-block;
            margin: 10px 20px 10px 0;
            font-size: 16px;
        }
        .stat strong {
            color: #4CAF50;
            font-size: 24px;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 13px;
        }
        .null { color: #999; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostika reklamací</h1>
        <div class="subtitle">White Glove Service - Kontrola databáze a viditelnosti reklamací</div>

        <?php
        try {
            $pdo = getDbConnection();

            // 1. Statistiky
            echo '<div class="section">';
            echo '<h2>📊 Celkové statistiky</h2>';

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM wgs_reklamace");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $pdo->query("SELECT COUNT(*) as orphaned FROM wgs_reklamace WHERE created_by IS NULL");
            $orphaned = $stmt->fetch(PDO::FETCH_ASSOC)['orphaned'];

            $stmt = $pdo->query("SELECT COUNT(*) as with_email FROM wgs_reklamace WHERE email IS NOT NULL AND email != ''");
            $withEmail = $stmt->fetch(PDO::FETCH_ASSOC)['with_email'];

            echo '<div class="stat">Celkem reklamací: <strong>' . $total . '</strong></div>';
            echo '<div class="stat">Bez created_by: <strong>' . $orphaned . '</strong></div>';
            echo '<div class="stat">S emailem: <strong>' . $withEmail . '</strong></div>';
            echo '</div>';

            // 2. Session info
            echo '<div class="section">';
            echo '<h2>🔐 Aktuální uživatel (SESSION)</h2>';
            echo '<div class="stat">User ID: <strong>' . ($_SESSION['user_id'] ?? '<span class="null">není nastaveno</span>') . '</strong></div>';
            echo '<div class="stat">Email: <strong>' . ($_SESSION['user_email'] ?? '<span class="null">není nastaveno</span>') . '</strong></div>';
            echo '<div class="stat">Admin: <strong>' . ((isset($_SESSION['is_admin']) && $_SESSION['is_admin']) ? '✅ ANO' : '❌ NE') . '</strong></div>';
            echo '</div>';

            // 3. Seznam všech reklamací
            echo '<div class="section">';
            echo '<h2>📋 Seznam všech reklamací v databázi</h2>';

            $stmt = $pdo->query("
                SELECT
                    id,
                    reklamace_id,
                    cislo,
                    jmeno,
                    email,
                    telefon,
                    created_by,
                    created_at,
                    stav
                FROM wgs_reklamace
                ORDER BY created_at DESC
            ");
            $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($reklamace)) {
                echo '<p><span class="badge warning">⚠️ Žádné reklamace v databázi!</span></p>';
            } else {
                echo '<table>';
                echo '<thead><tr>';
                echo '<th>ID</th>';
                echo '<th>Reklamace ID</th>';
                echo '<th>Číslo</th>';
                echo '<th>Jméno</th>';
                echo '<th>Email</th>';
                echo '<th>Telefon</th>';
                echo '<th>Created By</th>';
                echo '<th>Vytvořeno</th>';
                echo '<th>Stav</th>';
                echo '</tr></thead><tbody>';

                foreach ($reklamace as $r) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($r['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($r['reklamace_id'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($r['cislo'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($r['jmeno'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($r['email'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($r['telefon'] ?? '-') . '</td>';
                    echo '<td>' . ($r['created_by'] ? htmlspecialchars($r['created_by']) : '<span class="null">NULL</span>') . '</td>';
                    echo '<td>' . htmlspecialchars($r['created_at'] ?? '-') . '</td>';
                    echo '<td><span class="badge info">' . htmlspecialchars($r['stav'] ?? 'ČEKÁ') . '</span></td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            }
            echo '</div>';

            // 4. Viditelnost pro aktuálního uživatele
            $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

            if ($isAdmin) {
                echo '<div class="section">';
                echo '<h2>👑 Jsi admin - vidíš VŠECHNY reklamace (' . $total . ')</h2>';
                echo '</div>';
            } else {
                $userId = $_SESSION['user_id'] ?? null;
                $userEmail = $_SESSION['user_email'] ?? null;

                $whereParts = [];
                $params = [];

                if ($userId !== null) {
                    $whereParts[] = 'created_by = :created_by';
                    $params[':created_by'] = $userId;
                }

                if ($userEmail) {
                    $whereParts[] = 'email = :user_email';
                    $params[':user_email'] = $userEmail;
                }

                if (!empty($whereParts)) {
                    $whereClause = 'WHERE (' . implode(' OR ', $whereParts) . ')';
                    $stmt = $pdo->prepare("SELECT COUNT(*) as visible FROM wgs_reklamace $whereClause");
                    $stmt->execute($params);
                    $visible = $stmt->fetch(PDO::FETCH_ASSOC)['visible'];

                    $sectionClass = ($visible < $total) ? 'warning' : '';
                    echo '<div class="section ' . $sectionClass . '">';
                    echo '<h2>👤 Jako přihlášený uživatel vidíš: ' . $visible . ' z ' . $total . ' reklamací</h2>';
                    echo '<p><code>Filtr: created_by=' . $userId . ' OR email=' . $userEmail . '</code></p>';

                    if ($visible < $total) {
                        echo '<p><span class="badge warning">⚠️ NEVIDÍŠ VŠECHNY REKLAMACE!</span></p>';
                        echo '<p>Důvod: Reklamace nemají <code>created_by=' . $userId . '</code> nebo <code>email=' . $userEmail . '</code></p>';
                    }

                    // Detail viditelných reklamací
                    $stmt = $pdo->prepare("
                        SELECT id, reklamace_id, jmeno, email, created_by
                        FROM wgs_reklamace
                        $whereClause
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute($params);
                    $visibleRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($visibleRecords)) {
                        echo '<h3 style="margin-top: 20px;">Viditelné reklamace:</h3>';
                        echo '<table>';
                        echo '<thead><tr><th>ID</th><th>Jméno</th><th>Email</th><th>Created By</th></tr></thead><tbody>';
                        foreach ($visibleRecords as $r) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($r['id']) . '</td>';
                            echo '<td>' . htmlspecialchars($r['jmeno']) . '</td>';
                            echo '<td>' . htmlspecialchars($r['email'] ?? '-') . '</td>';
                            echo '<td>' . ($r['created_by'] ? htmlspecialchars($r['created_by']) : '<span class="null">NULL</span>') . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    }

                    echo '</div>';
                } else {
                    echo '<div class="section error">';
                    echo '<h2>⚠️ PROBLÉM: Nemáš nastavený user_id ani email</h2>';
                    echo '<p><span class="badge error">NEUVIDÍŠ ŽÁDNÉ REKLAMACE!</span></p>';
                    echo '</div>';
                }
            }

            // 5. Problematické reklamace
            if ($orphaned > 0) {
                echo '<div class="section warning">';
                echo '<h2>⚠️ PROBLÉM: ' . $orphaned . ' reklamací má created_by = NULL</h2>';
                echo '<p>Tyto reklamace se nezobrazí uživatelům, kteří je vytvořili (pokud se nepřihlásí emailem použitým v reklamaci)!</p>';

                $stmt = $pdo->query("
                    SELECT id, reklamace_id, jmeno, email, telefon, created_at
                    FROM wgs_reklamace
                    WHERE created_by IS NULL
                    ORDER BY created_at DESC
                ");
                $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo '<table>';
                echo '<thead><tr><th>ID</th><th>Jméno</th><th>Email</th><th>Telefon</th><th>Vytvořeno</th></tr></thead><tbody>';
                foreach ($orphanedRecords as $r) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($r['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($r['jmeno']) . '</td>';
                    echo '<td>' . htmlspecialchars($r['email'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($r['telefon'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($r['created_at']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';

                echo '<h3 style="margin-top: 20px;">💡 Řešení:</h3>';
                echo '<ol>';
                echo '<li>Uživatel se musí přihlásit emailem, který použil v reklamaci</li>';
                echo '<li>Nebo admin musí přiřadit <code>created_by</code> ručně</li>';
                echo '<li>Nebo upravit <code>load.php</code> aby zobrazoval i reklamace bez <code>created_by</code></li>';
                echo '</ol>';

                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<div class="section error">';
            echo '<h2>❌ CHYBA</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999;">
            <small>WGS Service Diagnostika © 2025</small>
        </div>
    </div>
</body>
</html>
