<?php
/**
 * Diagnostika online uzivatelu
 */
require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pristup odepren");
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnostika Online Uzivatelu</h1>";

try {
    $pdo = getDbConnection();

    // 1. Zobrazit vsechny uzivatele s last_activity
    echo "<h2>Vsichni uzivatele (serazeno dle last_activity)</h2>";
    $stmt = $pdo->query("
        SELECT user_id, name, email, role, last_login, last_activity,
               TIMESTAMPDIFF(MINUTE, last_activity, NOW()) as minuty_od_aktivity
        FROM wgs_users
        ORDER BY last_activity DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>user_id</th><th>name</th><th>email</th><th>role</th><th>last_login</th><th>last_activity</th><th>Minuty od aktivity</th><th>Online?</th></tr>";

    foreach ($users as $u) {
        $online = ($u['minuty_od_aktivity'] !== null && $u['minuty_od_aktivity'] <= 5) ? '<b style="color:green">ANO</b>' : 'NE';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($u['user_id'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($u['name'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($u['email'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($u['role'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($u['last_login'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($u['last_activity'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($u['minuty_od_aktivity'] ?? 'NULL') . "</td>";
        echo "<td>{$online}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. Aktualni cas serveru
    $stmt = $pdo->query("SELECT NOW() as cas");
    $cas = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><b>Aktualni cas serveru:</b> " . $cas['cas'] . "</p>";

    // 3. Session info
    echo "<h2>Aktualni session</h2>";
    echo "<pre>";
    echo "user_id: " . ($_SESSION['user_id'] ?? 'N/A') . "\n";
    echo "user_name: " . ($_SESSION['user_name'] ?? 'N/A') . "\n";
    echo "is_admin: " . (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? 'true' : 'false') . "\n";
    echo "last_activity (session): " . ($_SESSION['last_activity'] ?? 'N/A') . " (" . (isset($_SESSION['last_activity']) ? date('Y-m-d H:i:s', $_SESSION['last_activity']) : '-') . ")\n";
    echo "last_db_activity_update: " . ($_SESSION['last_db_activity_update'] ?? 'N/A') . " (" . (isset($_SESSION['last_db_activity_update']) ? date('Y-m-d H:i:s', $_SESSION['last_db_activity_update']) : '-') . ")\n";
    echo "</pre>";

    // 4. Test dotazu pro online uzivatele
    echo "<h2>Vysledek dotazu pro online uzivatele (posledních 5 minut)</h2>";
    $stmt = $pdo->query("
        SELECT user_id as id, name, email, role, last_activity
        FROM wgs_users
        WHERE last_activity IS NOT NULL
        AND last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY last_activity DESC
    ");
    $online = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($online)) {
        echo "<p style='color:red'><b>Zadni online uzivatele!</b></p>";
    } else {
        echo "<pre>" . print_r($online, true) . "</pre>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Chyba: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
