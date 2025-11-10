<?php
/**
 * QUICK DEBUG - Proč naty@naty.cz nevidí obě reklamace?
 * BEZPEČNOST: Pouze pro přihlášené uživatele
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Kontrola přihlášení
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    http_response_code(401);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins, sans-serif; padding: 40px; text-align: center; background: #fff;"><h1 style="color: #000; text-transform: uppercase; letter-spacing: 0.1em;">PŘÍSTUP ODEPŘEN</h1><p style="color: #555;">Musíte být přihlášeni pro zobrazení této stránky.</p><p><a href="/login" style="color: #000; border-bottom: 2px solid #000; text-decoration: none;">Přihlásit se</a></p></body></html>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Quick Debug - Viditelnost reklamací</title>
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
            font-size: 1.5rem;
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

        h4 {
            color: var(--wgs-black);
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        p {
            margin: 0.75rem 0;
            color: var(--wgs-grey);
            font-size: 0.875rem;
        }

        p strong {
            color: var(--wgs-black);
            font-weight: 600;
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

        .ok {
            color: var(--wgs-black);
            font-weight: 600;
        }

        .error {
            color: var(--wgs-grey);
            font-weight: 600;
        }

        .null {
            color: var(--wgs-light-grey);
            font-style: italic;
        }

        .highlight {
            background: var(--wgs-border);
            padding: 0.25rem 0.5rem;
            font-weight: 600;
            border: 1px solid var(--wgs-black);
        }

        pre {
            background: var(--wgs-border);
            padding: 1rem;
            overflow-x: auto;
            font-size: 0.75rem;
            border: 1px solid var(--wgs-black);
            margin: 1rem 0;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.625rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid var(--wgs-black);
        }

        .badge.success {
            background: var(--wgs-black);
            color: var(--wgs-white);
        }

        .badge.error {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        .solution {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .solution h3 {
            margin-top: 0;
        }

        .solution ol {
            margin: 1rem 0 1rem 1.5rem;
            color: var(--wgs-grey);
            font-size: 0.875rem;
        }

        .solution li {
            margin: 0.75rem 0;
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
            <h1>QUICK DEBUG - VIDITELNOST REKLAMACÍ</h1>
        </div>

        <div class="content">
            <?php
            try {
                $pdo = getDbConnection();

                // 1. SESSION info pro naty@naty.cz
                echo '<h2>SESSION INFO</h2>';
                $userIdInSession = $_SESSION['user_id'] ?? 'NOT SET';
                $userEmailInSession = $_SESSION['user_email'] ?? 'NOT SET';
                $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

                echo "<p><strong>user_id v SESSION:</strong> <span class='highlight'>" . htmlspecialchars($userIdInSession) . "</span></p>";
                echo "<p><strong>user_email v SESSION:</strong> <span class='highlight'>" . htmlspecialchars($userEmailInSession) . "</span></p>";
                echo "<p><strong>Je admin:</strong> " . ($isAdmin ? "<span class='ok'>[OK] ANO</span>" : "<span class='error'>[X] NE</span>") . "</p>";

                // 2. Reklamace v databázi
                echo '<h2>OBĚ REKLAMACE V DATABÁZI</h2>';
                $stmt = $pdo->query("
                    SELECT
                        id,
                        reklamace_id,
                        cislo,
                        jmeno,
                        email,
                        created_by,
                        created_at
                    FROM wgs_reklamace
                    ORDER BY created_at DESC
                ");
                $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo '<table>';
                echo '<thead><tr>';
                echo '<th>ID</th><th>Reklamace ID</th><th>Číslo</th><th>Jméno</th><th>Email</th><th>created_by</th><th>Vytvořeno</th>';
                echo '</tr></thead><tbody>';

                foreach ($reklamace as $r) {
                    $createdBy = $r['created_by'] ?? null;
                    $createdByClass = '';

                    if ($createdBy === null) {
                        $createdByDisplay = '<span class="null">NULL [!]</span>';
                        $createdByClass = 'error';
                    } elseif ($createdBy == $userIdInSession) {
                        $createdByDisplay = '<span class="ok">' . htmlspecialchars($createdBy) . ' [OK] MATCH</span>';
                        $createdByClass = 'ok';
                    } else {
                        $createdByDisplay = '<span class="error">' . htmlspecialchars($createdBy) . ' [X] NO MATCH</span>';
                        $createdByClass = 'error';
                    }

                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($r['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($r['reklamace_id'] ?? '-') . '</td>';
                    echo '<td>' . htmlspecialchars($r['cislo'] ?? '-') . '</td>';
                    echo '<td><strong>' . htmlspecialchars($r['jmeno']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($r['email'] ?? '-') . '</td>';
                    echo '<td class="' . $createdByClass . '">' . $createdByDisplay . '</td>';
                    echo '<td>' . htmlspecialchars($r['created_at']) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';

                // 3. Test load.php filtrování
                echo '<h2>SIMULACE LOAD.PHP - CO BY MĚL VIDĚT UŽIVATEL?</h2>';

                if ($isAdmin) {
                    echo '<p class="ok">[OK] Jsi admin - měl by vidět VŠECHNY reklamace (' . count($reklamace) . ')</p>';
                } else {
                    $userId = $_SESSION['user_id'] ?? null;
                    $userEmail = $_SESSION['user_email'] ?? null;

                    $whereParts = [];
                    $params = [];

                    if ($userId !== null) {
                        $whereParts[] = 'r.created_by = :created_by';
                        $params[':created_by'] = $userId;
                    }

                    if ($userEmail) {
                        $whereParts[] = 'LOWER(TRIM(r.email)) = LOWER(TRIM(:user_email))';
                        $params[':user_email'] = $userEmail;
                    }

                    if (!empty($whereParts)) {
                        $whereClause = 'WHERE (' . implode(' OR ', $whereParts) . ')';

                        echo '<p><strong>SQL filtr:</strong></p>';
                        echo '<pre>' . htmlspecialchars($whereClause) . '</pre>';
                        echo '<p><strong>Parametry:</strong></p>';
                        echo '<pre>' . htmlspecialchars(json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';

                        $sql = "
                            SELECT id, reklamace_id, jmeno, email, created_by
                            FROM wgs_reklamace r
                            $whereClause
                            ORDER BY created_at DESC
                        ";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $visible = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $visibleCount = count($visible);
                        $totalCount = count($reklamace);

                        if ($visibleCount == $totalCount) {
                            echo '<p class="ok">[OK] Viditelné reklamace: ' . $visibleCount . ' z ' . $totalCount . ' (VŠE OK)</p>';
                        } else {
                            echo '<p class="error">[X] Viditelné reklamace: ' . $visibleCount . ' z ' . $totalCount . ' (PROBLÉM!)</p>';
                        }

                        if (!empty($visible)) {
                            echo '<h3>Viditelné reklamace:</h3>';
                            echo '<table>';
                            echo '<thead><tr><th>ID</th><th>Jméno</th><th>Email</th><th>created_by</th></tr></thead><tbody>';
                            foreach ($visible as $v) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($v['id']) . '</td>';
                                echo '<td>' . htmlspecialchars($v['jmeno']) . '</td>';
                                echo '<td>' . htmlspecialchars($v['email'] ?? '-') . '</td>';
                                echo '<td>' . ($v['created_by'] ? htmlspecialchars($v['created_by']) : '<span class="null">NULL</span>') . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                        }

                        // Neviditelné reklamace
                        if ($visibleCount < $totalCount) {
                            $visibleIds = array_column($visible, 'id');
                            $invisible = array_filter($reklamace, function($r) use ($visibleIds) {
                                return !in_array($r['id'], $visibleIds);
                            });

                            echo '<h3 class="error">[X] NEVIDITELNÉ REKLAMACE (PROBLÉM):</h3>';
                            echo '<table>';
                            echo '<thead><tr><th>ID</th><th>Jméno</th><th>Email</th><th>created_by</th><th>Důvod</th></tr></thead><tbody>';
                            foreach ($invisible as $inv) {
                                $reason = '';
                                if ($inv['created_by'] === null) {
                                    $reason = 'created_by je NULL';
                                } elseif ($inv['created_by'] != $userId) {
                                    $reason = "created_by={$inv['created_by']} != tvé user_id={$userId}";
                                } elseif (strtolower(trim($inv['email'] ?? '')) !== strtolower(trim($userEmail ?? ''))) {
                                    $reason = "email neodpovídá";
                                }

                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($inv['id']) . '</td>';
                                echo '<td><strong>' . htmlspecialchars($inv['jmeno']) . '</strong></td>';
                                echo '<td>' . htmlspecialchars($inv['email'] ?? '-') . '</td>';
                                echo '<td>' . ($inv['created_by'] ? htmlspecialchars($inv['created_by']) : '<span class="null">NULL</span>') . '</td>';
                                echo '<td class="error">' . htmlspecialchars($reason) . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';

                            // Řešení
                            echo '<div class="solution">';
                            echo '<h3>ŘEŠENÍ</h3>';
                            echo '<p>Problémové reklamace mají <strong>špatné created_by</strong>. Řešení:</p>';
                            echo '<ol>';
                            echo '<li><strong>Oprav created_by v databázi</strong> - nastav správný user_id pro naty@naty.cz</li>';
                            echo '<li>NEBO <strong>změň logiku load.php</strong> - přidej jiný způsob filtrování (např. podle role nebo všechny pro zaměstnance)</li>';
                            echo '</ol>';

                            echo '<h4>SQL PŘÍKAZ PRO OPRAVU:</h4>';
                            foreach ($invisible as $inv) {
                                echo '<pre>UPDATE wgs_reklamace SET created_by = ' . htmlspecialchars($userId) . ' WHERE id = ' . htmlspecialchars($inv['id']) . ';</pre>';
                            }

                            echo '</div>';
                        }
                    } else {
                        echo '<p class="error">[X] CHYBA: Nemáš nastavený user_id ani email v SESSION!</p>';
                    }
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
            <small>WGS SERVICE QUICK DEBUG © 2025</small>
        </div>
    </div>
</body>
</html>
