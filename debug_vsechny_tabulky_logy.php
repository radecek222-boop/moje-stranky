<?php
/**
 * Debug: Prohledat VSECHNY tabulky ktere by mohly obsahovat zaznamy o pozvankach
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Hledani zaznamu o pozvankach ve VSECH tabulkach</h1>";
echo "<style>
    body { font-family: sans-serif; margin: 20px; }
    table { border-collapse: collapse; margin: 10px 0; width: 100%; font-size: 11px; }
    th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
    th { background: #333; color: white; }
    .found { background: #d4edda; }
    h2 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-top: 30px; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 10px; }
</style>";

try {
    $pdo = getDbConnection();

    // Klice ktere hledame
    $klice = [
        'PRO2025BAFC529E', 'PRO20250E43FFE7', 'PRO20258657B1A0', 'PRO2025A9AB6428',
        'PRO2025E44E1872', 'PRO20251B095200', 'PRO20251F89F668', 'PRO2025DC3674C1',
        'PRO2025212905F4', 'PRO2025E4E3250C', 'PRO202555A65DF4', 'TEC202557455F86'
    ];

    // 1. Vsechny tabulky v databazi
    echo "<h2>1. Seznam vsech tabulek</h2>";
    $stmt = $pdo->query("SHOW TABLES");
    $tabulky = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Celkem tabulek: " . count($tabulky) . "</p>";
    echo "<p>" . implode(', ', $tabulky) . "</p>";

    // 2. Prohledat wgs_pending_actions
    echo "<h2>2. wgs_pending_actions</h2>";
    if (in_array('wgs_pending_actions', $tabulky)) {
        $stmt = $pdo->query("SELECT * FROM wgs_pending_actions ORDER BY created_at DESC LIMIT 50");
        $akce = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Zaznamu: " . count($akce) . "</p>";

        if (count($akce) > 0) {
            echo "<table><tr>";
            foreach (array_keys($akce[0]) as $col) {
                echo "<th>" . htmlspecialchars($col) . "</th>";
            }
            echo "</tr>";
            foreach ($akce as $row) {
                $rowClass = '';
                foreach ($klice as $k) {
                    if (stripos(json_encode($row), $k) !== false) {
                        $rowClass = 'found';
                        break;
                    }
                }
                echo "<tr class='{$rowClass}'>";
                foreach ($row as $val) {
                    $zobraz = htmlspecialchars(substr($val ?? '', 0, 100));
                    echo "<td>" . $zobraz . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p>Tabulka neexistuje</p>";
    }

    // 3. Prohledat wgs_notifications (sablony)
    echo "<h2>3. wgs_notifications (sablony)</h2>";
    if (in_array('wgs_notifications', $tabulky)) {
        $stmt = $pdo->query("SELECT id, subject, active, created_at FROM wgs_notifications WHERE id LIKE '%invitation%' OR subject LIKE '%pozv%'");
        $notif = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Nalezeno sablon s 'invitation' nebo 'pozv': " . count($notif) . "</p>";
        if (count($notif) > 0) {
            echo "<table><tr><th>ID</th><th>Subject</th><th>Active</th><th>Created</th></tr>";
            foreach ($notif as $n) {
                echo "<tr><td>" . htmlspecialchars($n['id']) . "</td>";
                echo "<td>" . htmlspecialchars($n['subject']) . "</td>";
                echo "<td>" . $n['active'] . "</td>";
                echo "<td>" . htmlspecialchars($n['created_at']) . "</td></tr>";
            }
            echo "</table>";
        }
    }

    // 4. Hledat v libovolne tabulce texty klicu
    echo "<h2>4. Fulltextove hledani klicu ve vsech tabulkach</h2>";

    $nalezeno = [];

    foreach ($tabulky as $tabulka) {
        // Ziskat sloupce tabulky
        try {
            $stmt = $pdo->query("DESCRIBE `{$tabulka}`");
            $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $textSloupce = [];
            foreach ($sloupce as $sl) {
                $typ = strtolower($sl['Type']);
                if (strpos($typ, 'char') !== false || strpos($typ, 'text') !== false || strpos($typ, 'json') !== false) {
                    $textSloupce[] = $sl['Field'];
                }
            }

            if (count($textSloupce) > 0) {
                // Pro kazdy klic hledat v textovych sloupcich
                foreach ($klice as $klic) {
                    $conditions = [];
                    foreach ($textSloupce as $col) {
                        $conditions[] = "`{$col}` LIKE :klic_{$col}";
                    }

                    $sql = "SELECT * FROM `{$tabulka}` WHERE " . implode(' OR ', $conditions) . " LIMIT 5";
                    $stmt = $pdo->prepare($sql);

                    $params = [];
                    foreach ($textSloupce as $col) {
                        $params[":klic_{$col}"] = '%' . $klic . '%';
                    }

                    $stmt->execute($params);
                    $vysledky = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($vysledky) > 0) {
                        $nalezeno[] = [
                            'tabulka' => $tabulka,
                            'klic' => $klic,
                            'pocet' => count($vysledky),
                            'data' => $vysledky
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Ignorovat chyby u nekterych tabulek
        }
    }

    if (count($nalezeno) > 0) {
        echo "<div style='background:#d4edda;padding:15px;border-radius:5px;'>";
        echo "<strong>NALEZENO!</strong> Klice byly nalezeny v techto tabulkach:";
        echo "</div>";

        foreach ($nalezeno as $n) {
            echo "<h3>" . htmlspecialchars($n['tabulka']) . " - klic: " . htmlspecialchars($n['klic']) . "</h3>";
            echo "<pre>" . htmlspecialchars(json_encode($n['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        }
    } else {
        echo "<div style='background:#f8d7da;padding:15px;'>";
        echo "<strong>Klice nebyly nalezeny v zadne tabulce.</strong>";
        echo "</div>";
    }

    // 5. Posledni moznost - serverove logy (pokud existuji)
    echo "<h2>5. PHP error log (posledni radky)</h2>";
    $logPaths = [
        '/var/log/php_errors.log',
        '/var/log/apache2/error.log',
        '/var/log/nginx/error.log',
        __DIR__ . '/logs/php_errors.log',
        ini_get('error_log')
    ];

    foreach ($logPaths as $logPath) {
        if ($logPath && file_exists($logPath) && is_readable($logPath)) {
            echo "<p>Nalezen log: " . htmlspecialchars($logPath) . "</p>";
            $obsah = file_get_contents($logPath);
            $radky = explode("\n", $obsah);
            $posledni = array_slice($radky, -50);

            $relevantni = [];
            foreach ($posledni as $radek) {
                if (stripos($radek, 'invitation') !== false ||
                    stripos($radek, 'pozvank') !== false ||
                    stripos($radek, 'PRO2025') !== false ||
                    stripos($radek, 'TEC2025') !== false) {
                    $relevantni[] = $radek;
                }
            }

            if (count($relevantni) > 0) {
                echo "<pre>" . htmlspecialchars(implode("\n", $relevantni)) . "</pre>";
            } else {
                echo "<p>Zadne relevantni zaznamy</p>";
            }
        }
    }

    // 6. Zkontrolovat wgs_users - kdy se zaregistrovali uzivatele
    echo "<h2>6. Posledni registrace uzivatelu</h2>";
    if (in_array('wgs_users', $tabulky)) {
        $stmt = $pdo->query("
            SELECT user_id, name, email, role, registration_key, created_at
            FROM wgs_users
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            echo "<table><tr><th>ID</th><th>Jmeno</th><th>Email</th><th>Role</th><th>Registracni klic</th><th>Vytvoren</th></tr>";
            foreach ($users as $u) {
                $rowClass = '';
                foreach ($klice as $k) {
                    if ($u['registration_key'] === $k) {
                        $rowClass = 'found';
                        break;
                    }
                }
                echo "<tr class='{$rowClass}'>";
                echo "<td>" . $u['user_id'] . "</td>";
                echo "<td>" . htmlspecialchars($u['name'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($u['email'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($u['role'] ?? '') . "</td>";
                echo "<td><code>" . htmlspecialchars($u['registration_key'] ?? '-') . "</code></td>";
                echo "<td>" . htmlspecialchars($u['created_at'] ?? '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<p><strong>Tip:</strong> Pokud nekdo pouzil jeden z hledanych klicu k registraci, jeho email bude v tabulce wgs_users!</p>";
        }
    }

} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:10px;'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php?tab=keys' style='padding:10px 20px;background:#000;color:#fff;text-decoration:none;border-radius:5px;'>Zpet</a>";
?>
