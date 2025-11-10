<?php
/**
 * WEB Diagnostika reklamací
 * Otevři v prohlížeči: https://wgs-service.cz/diagnostic_web.php
 */

require_once __DIR__ . '/init.php';

// Pouze pro adminy nebo přihlášené uživatele
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center; background: #fff;"><h1 style="color: #000; text-transform: uppercase; letter-spacing: 0.1em;">PŘÍSTUP ODEPŘEN</h1><p style="color: #555;">Musíte být přihlášeni pro zobrazení této stránky.</p><p><a href="/login" style="color: #000; border-bottom: 2px solid #000; text-decoration: none;">Přihlásit se</a></p></body></html>');
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostika reklamací - WGS Service</title>
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
            background: var(--wgs-white);
            padding: 2rem;
            line-height: 1.6;
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
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .subtitle {
            color: var(--wgs-light-grey);
            margin-bottom: 0;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .content {
            padding: 2rem;
        }

        .section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
        }

        .section.warning {
            border-left-color: var(--wgs-grey);
        }

        .section.error {
            border-left-color: var(--wgs-black);
            border-width: 2px;
            border-left-width: 4px;
        }

        h2 {
            color: var(--wgs-black);
            margin-bottom: 1rem;
            font-size: 1.125rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
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

        p {
            margin: 0.75rem 0;
            color: var(--wgs-grey);
            font-size: 0.875rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
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

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.625rem;
            font-weight: 600;
            border: 2px solid var(--wgs-black);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge.success {
            background: var(--wgs-black);
            color: var(--wgs-white);
        }

        .badge.warning {
            background: var(--wgs-white);
            color: var(--wgs-black);
            border-color: var(--wgs-grey);
        }

        .badge.error {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        .badge.info {
            background: var(--wgs-grey);
            color: var(--wgs-white);
        }

        .stat {
            display: inline-block;
            margin: 0.75rem 1.5rem 0.75rem 0;
            font-size: 0.875rem;
            color: var(--wgs-grey);
        }

        .stat strong {
            color: var(--wgs-black);
            font-size: 1.25rem;
            font-weight: 700;
        }

        code {
            background: var(--wgs-border);
            padding: 0.25rem 0.5rem;
            font-family: monospace;
            font-size: 0.75rem;
            border: 1px solid var(--wgs-black);
        }

        .null {
            color: var(--wgs-light-grey);
            font-style: italic;
        }

        ol {
            margin: 1rem 0 1rem 1.5rem;
            color: var(--wgs-grey);
            font-size: 0.875rem;
        }

        ol li {
            margin: 0.5rem 0;
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
            <h1>DIAGNOSTIKA REKLAMACÍ</h1>
            <div class="subtitle">White Glove Service - Kontrola databáze a viditelnosti reklamací</div>
        </div>

        <div class="content">
            <?php
            try {
                $pdo = getDbConnection();

                // 1. Statistiky
                echo '<div class="section">';
                echo '<h2>CELKOVÉ STATISTIKY</h2>';

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
                echo '<h2>AKTUÁLNÍ UŽIVATEL (SESSION)</h2>';
                echo '<div class="stat">User ID: <strong>' . ($_SESSION['user_id'] ?? '<span class="null">není nastaveno</span>') . '</strong></div>';
                echo '<div class="stat">Email: <strong>' . ($_SESSION['user_email'] ?? '<span class="null">není nastaveno</span>') . '</strong></div>';
                echo '<div class="stat">Admin: <strong>' . ((isset($_SESSION['is_admin']) && $_SESSION['is_admin']) ? '[OK] ANO' : '[X] NE') . '</strong></div>';
                echo '</div>';

                // 3. Seznam všech reklamací
                echo '<div class="section">';
                echo '<h2>SEZNAM VŠECH REKLAMACÍ V DATABÁZI</h2>';

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
                    echo '<p><span class="badge warning">[VAROVÁNÍ] Žádné reklamace v databázi!</span></p>';
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
                    echo '<h2>[ADMIN] Jsi admin - vidíš VŠECHNY reklamace (' . $total . ')</h2>';
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
                        echo '<h2>[USER] Jako přihlášený uživatel vidíš: ' . $visible . ' z ' . $total . ' reklamací</h2>';
                        echo '<p><code>Filtr: created_by=' . $userId . ' OR email=' . $userEmail . '</code></p>';

                        if ($visible < $total) {
                            echo '<p><span class="badge warning">[VAROVÁNÍ] NEVIDÍŠ VŠECHNY REKLAMACE!</span></p>';
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
                            echo '<h3>Viditelné reklamace:</h3>';
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
                        echo '<h2>[PROBLÉM] Nemáš nastavený user_id ani email</h2>';
                        echo '<p><span class="badge error">NEUVIDÍŠ ŽÁDNÉ REKLAMACE!</span></p>';
                        echo '</div>';
                    }
                }

                // 5. Problematické reklamace
                if ($orphaned > 0) {
                    echo '<div class="section warning">';
                    echo '<h2>[PROBLÉM] ' . $orphaned . ' reklamací má created_by = NULL</h2>';
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

                    echo '<h3>ŘEŠENÍ:</h3>';
                    echo '<ol>';
                    echo '<li>Uživatel se musí přihlásit emailem, který použil v reklamaci</li>';
                    echo '<li>Nebo admin musí přiřadit <code>created_by</code> ručně</li>';
                    echo '<li>Nebo upravit <code>load.php</code> aby zobrazoval i reklamace bez <code>created_by</code></li>';
                    echo '</ol>';

                    echo '</div>';
                }

            } catch (Exception $e) {
                echo '<div class="section error">';
                echo '<h2>CHYBA</h2>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
        </div>

        <div class="footer">
            <small>WGS SERVICE DIAGNOSTIKA © 2025</small>
        </div>
    </div>
</body>
</html>
