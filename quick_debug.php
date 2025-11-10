<?php
/**
 * QUICK DEBUG - Proč naty@naty.cz nevidí obě reklamace?
 */

require_once __DIR__ . '/init.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Quick Debug - Viditelnost reklamací</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:hover { background: #f9f9f9; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .null { color: #999; font-style: italic; }
        .highlight { background: #fff3cd; font-weight: bold; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge.success { background: #4CAF50; color: white; }
        .badge.error { background: #f44336; color: white; }
        .solution { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Quick Debug - Proč naty@naty.cz nevidí obě reklamace?</h1>

        <?php
        try {
            $pdo = getDbConnection();

            // 1. SESSION info pro naty@naty.cz
            echo '<h2>🔐 SESSION Info</h2>';
            $userIdInSession = $_SESSION['user_id'] ?? 'NOT SET';
            $userEmailInSession = $_SESSION['user_email'] ?? 'NOT SET';
            $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

            echo "<p><strong>user_id v SESSION:</strong> <span class='highlight'>" . htmlspecialchars($userIdInSession) . "</span></p>";
            echo "<p><strong>user_email v SESSION:</strong> <span class='highlight'>" . htmlspecialchars($userEmailInSession) . "</span></p>";
            echo "<p><strong>Je admin:</strong> " . ($isAdmin ? "<span class='ok'>ANO ✅</span>" : "<span class='error'>NE ❌</span>") . "</p>";

            // 2. Reklamace v databázi
            echo '<h2>📋 Obě reklamace v databázi</h2>';
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
                    $createdByDisplay = '<span class="null">NULL ⚠️</span>';
                    $createdByClass = 'error';
                } elseif ($createdBy == $userIdInSession) {
                    $createdByDisplay = '<span class="ok">' . htmlspecialchars($createdBy) . ' ✅ MATCH</span>';
                    $createdByClass = 'ok';
                } else {
                    $createdByDisplay = '<span class="error">' . htmlspecialchars($createdBy) . ' ❌ NO MATCH</span>';
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
            echo '<h2>🎯 Simulace load.php - Co by měl vidět naty@naty.cz?</h2>';

            if ($isAdmin) {
                echo '<p class="ok">✅ Jsi admin - měl by vidět VŠECHNY reklamace (' . count($reklamace) . ')</p>';
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
                        echo '<p class="ok">✅ Viditelné reklamace: ' . $visibleCount . ' z ' . $totalCount . ' (VŠE OK)</p>';
                    } else {
                        echo '<p class="error">❌ Viditelné reklamace: ' . $visibleCount . ' z ' . $totalCount . ' (PROBLÉM!)</p>';
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

                        echo '<h3 class="error">❌ NEVIDITELNÉ reklamace (PROBLÉM):</h3>';
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
                        echo '<h3>💡 ŘEŠENÍ</h3>';
                        echo '<p>Problémové reklamace mají <strong>špatné created_by</strong>. Řešení:</p>';
                        echo '<ol>';
                        echo '<li><strong>Oprav created_by v databázi</strong> - nastav správný user_id pro naty@naty.cz</li>';
                        echo '<li>NEBO <strong>změň logiku load.php</strong> - přidej jiný způsob filtrování (např. podle role nebo všechny pro zaměstnance)</li>';
                        echo '</ol>';

                        echo '<h4>🔧 SQL příkaz pro opravu:</h4>';
                        foreach ($invisible as $inv) {
                            echo '<pre>UPDATE wgs_reklamace SET created_by = ' . htmlspecialchars($userId) . ' WHERE id = ' . htmlspecialchars($inv['id']) . ';</pre>';
                        }

                        echo '</div>';
                    }
                } else {
                    echo '<p class="error">❌ CHYBA: Nemáš nastavený user_id ani email v SESSION!</p>';
                }
            }

        } catch (Exception $e) {
            echo '<div style="color: red; padding: 20px; background: #ffebee; border-radius: 4px;">';
            echo '<h2>❌ CHYBA</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #999;">
            <small>WGS Service Quick Debug © 2025</small>
        </div>
    </div>
</body>
</html>
