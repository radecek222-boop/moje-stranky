<?php
/**
 * Debug nástroj pro diagnostiku viditelnosti reklamací
 * Ukáže proč se uživateli zobrazuje jen jedna reklamace místo všech
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/db_metadata.php';

// Pouze admin má přístup
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('Přístup pouze pro administrátory');
}

$pdo = getDbConnection();

// Akce pro opravu created_by
$action = $_GET['action'] ?? '';
$fixResult = '';

if ($action === 'fix_created_by' && isset($_POST['email']) && isset($_POST['user_id'])) {
    $email = trim($_POST['email']);
    $userId = (int)$_POST['user_id'];

    // Aktualizace reklamací podle emailu
    $sql = "UPDATE wgs_reklamace
            SET created_by = :user_id,
                created_by_role = 'prodejce'
            WHERE LOWER(TRIM(email)) = LOWER(:email)
            AND (created_by IS NULL OR created_by = 0)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId, ':email' => $email]);
    $affected = $stmt->rowCount();

    $fixResult = "<div style='background: #000; color: #fff; padding: 1rem; margin: 1rem 0;'>
        ✓ Opraveno: $affected reklamací pro email $email nastaveno created_by = $userId
    </div>";
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug viditelnosti reklamací</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #000;
            padding: 2rem;
            line-height: 1.6;
        }
        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        h2 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #000;
        }
        h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 1.5rem 0 0.5rem;
        }
        .section {
            background: #f5f5f5;
            padding: 1.5rem;
            margin: 1rem 0;
            border: 2px solid #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background: white;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #000;
            font-size: 0.9rem;
        }
        th {
            background: #000;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .null { color: #999; font-style: italic; }
        .error { color: #d00; font-weight: 600; }
        .ok { color: #0a0; font-weight: 600; }
        .warning { color: #f80; font-weight: 600; }
        code {
            background: #000;
            color: #0f0;
            padding: 0.2rem 0.5rem;
            font-family: monospace;
            font-size: 0.85rem;
        }
        pre {
            background: #000;
            color: #0f0;
            padding: 1rem;
            overflow-x: auto;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.85rem;
        }
        .stat {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 0.5rem 1rem;
            margin: 0.5rem 0.5rem 0.5rem 0;
            font-weight: 600;
        }
        button {
            background: #000;
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0.5rem 0.5rem 0.5rem 0;
        }
        button:hover {
            background: #333;
        }
        input[type="text"], input[type="number"] {
            padding: 0.5rem;
            border: 2px solid #000;
            font-family: 'Poppins', sans-serif;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>

<h1>🔍 Debug viditelnosti reklamací</h1>

<?php echo $fixResult; ?>

<?php
// 1. AKTUÁLNÍ SESSION
echo '<div class="section">';
echo '<h2>1. AKTUÁLNÍ SESSION DATA</h2>';
echo '<table>';
echo '<tr><th>Klíč</th><th>Hodnota</th></tr>';
echo '<tr><td>user_id</td><td>' . ($_SESSION['user_id'] ?? '<span class="null">NULL</span>') . '</td></tr>';
echo '<tr><td>user_email</td><td>' . ($_SESSION['user_email'] ?? '<span class="null">NULL</span>') . '</td></tr>';
echo '<tr><td>user_name</td><td>' . ($_SESSION['user_name'] ?? '<span class="null">NULL</span>') . '</td></tr>';
echo '<tr><td>role</td><td>' . ($_SESSION['role'] ?? '<span class="null">NULL</span>') . '</td></tr>';
echo '<tr><td>is_admin</td><td>' . (($_SESSION['is_admin'] ?? false) ? 'TRUE' : 'FALSE') . '</td></tr>';
echo '<tr><td>_simulating</td><td>' . ($_SESSION['_simulating'] ?? '<span class="null">NONE</span>') . '</td></tr>';
echo '</table>';
echo '</div>';

// 2. STATISTIKA REKLAMACÍ
echo '<div class="section">';
echo '<h2>2. STATISTIKA REKLAMACÍ V DATABÁZI</h2>';

$stats = [];

// Celkový počet
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
$stats['total'] = $stmt->fetchColumn();

// Počet s created_by NULL
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NULL");
$stats['null_created_by'] = $stmt->fetchColumn();

// Počet s vyplněným created_by
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NOT NULL");
$stats['has_created_by'] = $stmt->fetchColumn();

echo '<div>';
echo '<span class="stat">Celkem reklamací: ' . $stats['total'] . '</span>';
echo '<span class="stat ' . ($stats['null_created_by'] > 0 ? 'warning' : 'ok') . '">created_by = NULL: ' . $stats['null_created_by'] . '</span>';
echo '<span class="stat ok">created_by vyplněno: ' . $stats['has_created_by'] . '</span>';
echo '</div>';

if ($stats['null_created_by'] > 0) {
    echo '<p class="warning" style="margin-top: 1rem;">⚠️ PROBLÉM: ' . $stats['null_created_by'] . ' reklamací nemá vyplněné created_by - prodejce je neuvidí!</p>';
}

echo '</div>';

// 3. VŠECHNY REKLAMACE
echo '<div class="section">';
echo '<h2>3. VŠECHNY REKLAMACE (created_by status)</h2>';

$sql = "SELECT
    id,
    reklamace_id,
    cislo,
    email,
    jmeno,
    created_by,
    created_by_role,
    created_at
FROM wgs_reklamace
ORDER BY created_at DESC
LIMIT 50";

$stmt = $pdo->query($sql);
$reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<table>';
echo '<tr>
    <th>ID</th>
    <th>Reklamace ID</th>
    <th>Číslo</th>
    <th>Email zákazníka</th>
    <th>Jméno</th>
    <th>created_by</th>
    <th>created_by_role</th>
    <th>Vytvořeno</th>
</tr>';

foreach ($reklamace as $row) {
    $createdByClass = ($row['created_by'] === null) ? 'null' : '';
    echo '<tr>';
    echo '<td>' . $row['id'] . '</td>';
    echo '<td>' . ($row['reklamace_id'] ?? '<span class="null">-</span>') . '</td>';
    echo '<td>' . ($row['cislo'] ?? '<span class="null">-</span>') . '</td>';
    echo '<td>' . ($row['email'] ?? '<span class="null">-</span>') . '</td>';
    echo '<td>' . ($row['jmeno'] ?? '<span class="null">-</span>') . '</td>';
    echo '<td class="' . $createdByClass . '">' . ($row['created_by'] ?? '<span class="null">NULL</span>') . '</td>';
    echo '<td>' . ($row['created_by_role'] ?? '<span class="null">NULL</span>') . '</td>';
    echo '<td>' . ($row['created_at'] ?? '<span class="null">-</span>') . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</div>';

// 4. REKLAMACE PODLE EMAILU
echo '<div class="section">';
echo '<h2>4. REKLAMACE PODLE EMAILU (pro prodejce)</h2>';

$sql = "SELECT
    email,
    COUNT(*) as pocet,
    SUM(CASE WHEN created_by IS NULL THEN 1 ELSE 0 END) as null_count,
    SUM(CASE WHEN created_by IS NOT NULL THEN 1 ELSE 0 END) as has_created_by
FROM wgs_reklamace
WHERE email IS NOT NULL AND email != ''
GROUP BY email
ORDER BY pocet DESC";

$stmt = $pdo->query($sql);
$emailStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<table>';
echo '<tr>
    <th>Email</th>
    <th>Celkem</th>
    <th>created_by = NULL</th>
    <th>created_by vyplněno</th>
    <th>Akce</th>
</tr>';

foreach ($emailStats as $row) {
    echo '<tr>';
    echo '<td>' . $row['email'] . '</td>';
    echo '<td>' . $row['pocet'] . '</td>';
    echo '<td class="' . ($row['null_count'] > 0 ? 'warning' : '') . '">' . $row['null_count'] . '</td>';
    echo '<td class="' . ($row['has_created_by'] > 0 ? 'ok' : '') . '">' . $row['has_created_by'] . '</td>';
    echo '<td>';

    if ($row['null_count'] > 0) {
        // Zjistit user_id pro tento email
        $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE LOWER(TRIM(email)) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $row['email']]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            echo '<form method="POST" action="?action=fix_created_by" style="display: inline;">';
            echo '<input type="hidden" name="email" value="' . htmlspecialchars($row['email']) . '">';
            echo '<input type="hidden" name="user_id" value="' . $userId . '">';
            echo '<button type="submit">OPRAVIT (nastavit created_by=' . $userId . ')</button>';
            echo '</form>';
        } else {
            echo '<span class="error">User nenalezen v databázi</span>';
        }
    }

    echo '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</div>';

// 5. SIMULACE LOAD.PHP LOGIKY
if (isset($_GET['simulate_user_id'])) {
    $simulateUserId = (int)$_GET['simulate_user_id'];
    $simulateRole = $_GET['simulate_role'] ?? 'prodejce';

    echo '<div class="section">';
    echo '<h2>5. SIMULACE LOAD.PHP PRO user_id=' . $simulateUserId . ' role=' . $simulateRole . '</h2>';

    // Kopie logiky z load.php
    $whereParts = [];
    $params = [];

    $isProdejce = in_array($simulateRole, ['prodejce', 'user'], true);
    $isTechnik = in_array($simulateRole, ['technik', 'technician'], true);

    if ($isProdejce) {
        $whereParts[] = 'r.created_by = :created_by';
        $params[':created_by'] = $simulateUserId;
    } elseif ($isTechnik) {
        // Žádný filtr
    }

    $whereClause = '';
    if (!empty($whereParts)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
    }

    $sql = "SELECT
        r.id,
        r.reklamace_id,
        r.cislo,
        r.email,
        r.jmeno,
        r.created_by,
        r.created_by_role,
        r.created_at
    FROM wgs_reklamace r
    $whereClause
    ORDER BY r.created_at DESC";

    echo '<h3>SQL dotaz:</h3>';
    echo '<pre>' . $sql . '</pre>';

    echo '<h3>Parametry:</h3>';
    echo '<pre>' . print_r($params, true) . '</pre>';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<h3>Výsledky (' . count($results) . ' reklamací):</h3>';

    if (empty($results)) {
        echo '<p class="error">❌ ŽÁDNÉ REKLAMACE! Proto uživatel nevidí nic.</p>';
    } else {
        echo '<table>';
        echo '<tr>
            <th>ID</th>
            <th>Reklamace ID</th>
            <th>Email</th>
            <th>Jméno</th>
            <th>created_by</th>
            <th>Vytvořeno</th>
        </tr>';

        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . ($row['reklamace_id'] ?? '-') . '</td>';
            echo '<td>' . ($row['email'] ?? '-') . '</td>';
            echo '<td>' . ($row['jmeno'] ?? '-') . '</td>';
            echo '<td>' . ($row['created_by'] ?? '<span class="null">NULL</span>') . '</td>';
            echo '<td>' . ($row['created_at'] ?? '-') . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    echo '</div>';
}

// Formulář pro simulaci - načtení skutečných uživatelů
echo '<div class="section">';
echo '<h2>SIMULOVAT LOAD.PHP - VÝBĚR KONKRÉTNÍHO UŽIVATELE</h2>';

// Načíst všechny uživatele podle rolí
$sql = "SELECT id, email, name, role FROM wgs_users ORDER BY role, name";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Seskupit podle rolí
$usersByRole = [
    'prodejce' => [],
    'technik' => [],
    'admin' => []
];

foreach ($users as $user) {
    $userRole = strtolower(trim($user['role'] ?? 'user'));
    if (in_array($userRole, ['prodejce', 'user'])) {
        $usersByRole['prodejce'][] = $user;
    } elseif (in_array($userRole, ['technik', 'technician'])) {
        $usersByRole['technik'][] = $user;
    } elseif ($userRole === 'admin') {
        $usersByRole['admin'][] = $user;
    }
}

echo '<p style="margin-bottom: 1rem;">Vyber konkrétního uživatele a zjisti co uvidí v seznam.php:</p>';

echo '<form method="GET">';
echo '<select name="simulate_user_id" required style="padding: 0.75rem; border: 2px solid #000; font-family: \'Poppins\', sans-serif; font-size: 1rem; margin-right: 0.5rem; min-width: 300px;">';
echo '<option value="">-- Vyber uživatele --</option>';

// PRODEJCI
if (!empty($usersByRole['prodejce'])) {
    echo '<optgroup label="👤 PRODEJCI">';
    foreach ($usersByRole['prodejce'] as $user) {
        $userName = $user['name'] ?? $user['email'];
        echo '<option value="' . $user['id'] . '" data-role="prodejce">';
        echo htmlspecialchars($userName) . ' (' . $user['email'] . ') [ID:' . $user['id'] . ']';
        echo '</option>';
    }
    echo '</optgroup>';
}

// TECHNICI
if (!empty($usersByRole['technik'])) {
    echo '<optgroup label="🔧 TECHNICI">';
    foreach ($usersByRole['technik'] as $user) {
        $userName = $user['name'] ?? $user['email'];
        echo '<option value="' . $user['id'] . '" data-role="technik">';
        echo htmlspecialchars($userName) . ' (' . $user['email'] . ') [ID:' . $user['id'] . ']';
        echo '</option>';
    }
    echo '</optgroup>';
}

// ADMINI
if (!empty($usersByRole['admin'])) {
    echo '<optgroup label="⚙️ ADMINISTRÁTOŘI">';
    foreach ($usersByRole['admin'] as $user) {
        $userName = $user['name'] ?? $user['email'];
        echo '<option value="' . $user['id'] . '" data-role="admin">';
        echo htmlspecialchars($userName) . ' (' . $user['email'] . ') [ID:' . $user['id'] . ']';
        echo '</option>';
    }
    echo '</optgroup>';
}

echo '</select>';

echo '<select name="simulate_role" style="padding: 0.75rem; border: 2px solid #000; font-family: \'Poppins\', sans-serif; font-size: 1rem; margin-right: 0.5rem;">';
echo '<option value="prodejce">Jako PRODEJCE</option>';
echo '<option value="technik">Jako TECHNIK</option>';
echo '<option value="admin">Jako ADMIN</option>';
echo '</select>';

echo '<button type="submit">SIMULOVAT</button>';
echo '</form>';

// Pokud jsou data z databáze prázdná
if (empty($users)) {
    echo '<p style="color: red; margin-top: 1rem;">⚠️ Žádní uživatelé nenalezeni v databázi wgs_users</p>';
}

echo '</div>';

?>

<div style="margin-top: 2rem; padding: 1rem; background: #000; color: #fff;">
    <strong>NÁVOD:</strong>
    <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
        <li>Zkontroluj STATISTIKU - kolik reklamací má created_by = NULL</li>
        <li>Podívej se na REKLAMACE PODLE EMAILU - najdi emailového prodejce</li>
        <li>Klikni na tlačítko OPRAVIT u emailu prodejce (nastaví created_by)</li>
        <li>Použij SIMULOVAT LOAD.PHP s user_id prodejce - uvidíš co vrátí dotaz</li>
        <li>Po opravě by měl prodejce vidět všechny své reklamace</li>
    </ol>
</div>

<div style="margin-top: 1rem;">
    <button onclick="window.location.href='admin.php?tab=tools'">← Zpět na admin</button>
</div>

</body>
</html>
