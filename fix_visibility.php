<?php
/**
 * OPRAVA VIDITELNOSTI REKLAMACÍ
 * Rychlá oprava created_by pro všechny reklamace
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/db_metadata.php';

// Admin check
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$userId = $_SESSION['user_id'] ?? null;

if (!$isAdmin && !$userId) {
    die('<h1 style="color: red;">CHYBA: Musíte být přihlášeni</h1>');
}

$pdo = getDbConnection();

// ZPRACOVÁNÍ OPRAVY
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_email'])) {
    $emailToFix = trim($_POST['fix_email']);
    $userIdToSet = (int)$_POST['fix_user_id'];

    try {
        // Načíst SKUTEČNOU roli uživatele z databáze
        $stmt = $pdo->prepare("SELECT role FROM wgs_users WHERE id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $userIdToSet]);
        $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        $userRole = strtolower(trim($userRecord['role'] ?? 'user'));

        // Aktualizace s SKUTEČNOU rolí
        $sql = "UPDATE wgs_reklamace
                SET created_by = :user_id,
                    created_by_role = :role
                WHERE LOWER(TRIM(email)) = LOWER(:email)
                AND (created_by IS NULL OR created_by = 0)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userIdToSet,
            ':email' => $emailToFix,
            ':role' => $userRole
        ]);

        $affected = $stmt->rowCount();
        $message = "<div style='background: #0a0; color: white; padding: 2rem; margin: 2rem 0; font-size: 1.2rem; font-weight: bold;'>
            ✓ ÚSPĚCH! Opraveno $affected reklamací pro $emailToFix<br>
            (created_by = $userIdToSet, role = $userRole)
        </div>";
    } catch (Exception $e) {
        $message = "<div style='background: #d00; color: white; padding: 2rem; margin: 2rem 0;'>
            ✗ CHYBA: " . $e->getMessage() . "
        </div>";
    }
}

// AUTO-FIX ALL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_fix_all'])) {
    try {
        // Najít všechny uživatele s emailem A ROLÍ
        $sql = "SELECT id, email, role FROM wgs_users WHERE email IS NOT NULL AND email != ''";
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalFixed = 0;
        $details = [];

        foreach ($users as $user) {
            // Použít SKUTEČNOU roli uživatele
            $userRole = strtolower(trim($user['role'] ?? 'user'));

            $sql = "UPDATE wgs_reklamace
                    SET created_by = :user_id,
                        created_by_role = :role
                    WHERE LOWER(TRIM(email)) = LOWER(:email)
                    AND (created_by IS NULL OR created_by = 0)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $user['id'],
                ':email' => $user['email'],
                ':role' => $userRole
            ]);

            $affected = $stmt->rowCount();
            if ($affected > 0) {
                $totalFixed += $affected;
                $details[] = "{$user['email']} ({$userRole}): $affected reklamací";
            }
        }

        $detailsList = implode('<br>', $details);
        $message = "<div style='background: #0a0; color: white; padding: 2rem; margin: 2rem 0; font-size: 1.2rem;'>
            ✓ AUTO-OPRAVA DOKONČENA!<br>
            Celkem opraveno: <strong>$totalFixed reklamací</strong><br><br>
            Detail:<br>$detailsList
        </div>";
    } catch (Exception $e) {
        $message = "<div style='background: #d00; color: white; padding: 2rem; margin: 2rem 0;'>
            ✗ CHYBA: " . $e->getMessage() . "
        </div>";
    }
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oprava viditelnosti</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: white;
            color: black;
            padding: 2rem;
            line-height: 1.6;
        }
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }
        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid black;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background: white;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border: 2px solid black;
        }
        th {
            background: black;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
        }
        button {
            background: black;
            color: white;
            border: none;
            padding: 1rem 2rem;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 1rem;
            margin: 0.5rem 0.5rem 0.5rem 0;
        }
        button:hover { background: #333; }
        button.danger {
            background: #d00;
        }
        button.danger:hover { background: #f00; }
        button.success {
            background: #0a0;
        }
        button.success:hover { background: #0c0; }
        .warning {
            background: #ff0;
            color: #000;
            padding: 1rem;
            margin: 1rem 0;
            font-weight: 600;
            border: 3px solid #000;
        }
        .info {
            background: #e0e0e0;
            padding: 1.5rem;
            margin: 1rem 0;
            border-left: 5px solid #000;
        }
        .null { color: #999; font-style: italic; }
        .box {
            background: #f5f5f5;
            border: 2px solid black;
            padding: 1.5rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>

<h1>🔧 OPRAVA VIDITELNOSTI REKLAMACÍ</h1>

<?php echo $message; ?>

<?php
// STATISTIKY
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
$totalClaims = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NULL OR created_by = 0");
$nullCreatedBy = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NOT NULL AND created_by > 0");
$hasCreatedBy = $stmt->fetchColumn();

if ($nullCreatedBy > 0) {
    echo '<div class="warning">';
    echo "⚠️ PROBLÉM NALEZEN!<br>";
    echo "<strong>$nullCreatedBy reklamací</strong> z celkových <strong>$totalClaims</strong> nemá vyplněné created_by<br>";
    echo "→ Prodejci tyto reklamace NEVIDÍ v seznam.php";
    echo '</div>';
} else {
    echo '<div style="background: #0a0; color: white; padding: 1rem; margin: 1rem 0; font-weight: 600;">';
    echo "✓ V POŘÁDKU: Všechny reklamace mají vyplněné created_by";
    echo '</div>';
}
?>

<div class="info">
    <strong>Statistika:</strong><br>
    Celkem reklamací: <?php echo $totalClaims; ?><br>
    created_by = NULL: <span style="color: <?php echo $nullCreatedBy > 0 ? 'red' : 'green'; ?>; font-weight: bold;"><?php echo $nullCreatedBy; ?></span><br>
    created_by vyplněno: <span style="color: green; font-weight: bold;"><?php echo $hasCreatedBy; ?></span>
</div>

<?php if ($nullCreatedBy > 0): ?>

<div class="box">
    <h2>⚡ RYCHLÁ OPRAVA - AUTO FIX</h2>
    <p style="margin: 1rem 0;">
        Toto automaticky opraví <strong>VŠECHNY</strong> reklamace s NULL created_by.<br>
        Pro každý email v databázi najde odpovídající user_id a nastaví ho jako created_by.
    </p>
    <form method="POST">
        <button type="submit" name="auto_fix_all" class="success" onclick="return confirm('OPRAVIT všechny reklamace s NULL created_by?')">
            ⚡ AUTO-OPRAVA VŠECH
        </button>
    </form>
</div>

<h2>📋 REKLAMACE PODLE EMAILU</h2>

<?php
$sql = "SELECT
    r.email,
    COUNT(*) as total,
    SUM(CASE WHEN r.created_by IS NULL OR r.created_by = 0 THEN 1 ELSE 0 END) as null_count,
    u.id as user_id,
    u.role
FROM wgs_reklamace r
LEFT JOIN wgs_users u ON LOWER(TRIM(u.email)) = LOWER(TRIM(r.email))
WHERE r.email IS NOT NULL AND r.email != ''
GROUP BY r.email, u.id, u.role
HAVING null_count > 0
ORDER BY null_count DESC, total DESC";

$stmt = $pdo->query($sql);
$emailGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($emailGroups)) {
    echo '<p style="color: green; font-weight: 600;">✓ Žádné reklamace s NULL created_by</p>';
} else {
    echo '<table>';
    echo '<tr>
        <th>Email</th>
        <th>Celkem reklamací</th>
        <th>created_by = NULL</th>
        <th>User ID</th>
        <th>Role</th>
        <th>Akce</th>
    </tr>';

    foreach ($emailGroups as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
        echo '<td>' . $row['total'] . '</td>';
        echo '<td style="color: red; font-weight: bold;">' . $row['null_count'] . '</td>';

        if ($row['user_id']) {
            echo '<td>' . $row['user_id'] . '</td>';
            echo '<td>' . ($row['role'] ?? 'N/A') . '</td>';
            echo '<td>';
            echo '<form method="POST" style="display: inline;">';
            echo '<input type="hidden" name="fix_email" value="' . htmlspecialchars($row['email']) . '">';
            echo '<input type="hidden" name="fix_user_id" value="' . $row['user_id'] . '">';
            echo '<button type="submit" class="success">OPRAVIT (' . $row['null_count'] . ')</button>';
            echo '</form>';
            echo '</td>';
        } else {
            echo '<td class="null">NULL</td>';
            echo '<td class="null">-</td>';
            echo '<td style="color: red;">⚠️ User neexistuje</td>';
        }

        echo '</tr>';
    }

    echo '</table>';
}
?>

<?php endif; ?>

<h2>📊 VŠECHNY REKLAMACE (poslední 50)</h2>

<?php
$sql = "SELECT
    id,
    reklamace_id,
    email,
    jmeno,
    created_by,
    created_by_role,
    created_at
FROM wgs_reklamace
ORDER BY created_at DESC
LIMIT 50";

$stmt = $pdo->query($sql);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<table>';
echo '<tr>
    <th>ID</th>
    <th>Reklamace ID</th>
    <th>Email</th>
    <th>Jméno</th>
    <th>created_by</th>
    <th>Role</th>
    <th>Vytvořeno</th>
</tr>';

foreach ($claims as $row) {
    $isNull = ($row['created_by'] === null || $row['created_by'] == 0);
    echo '<tr' . ($isNull ? ' style="background: #fdd;"' : '') . '>';
    echo '<td>' . $row['id'] . '</td>';
    echo '<td>' . ($row['reklamace_id'] ?? '-') . '</td>';
    echo '<td>' . ($row['email'] ?? '-') . '</td>';
    echo '<td>' . ($row['jmeno'] ?? '-') . '</td>';
    echo '<td class="' . ($isNull ? 'null' : '') . '">' . ($isNull ? 'NULL ⚠️' : $row['created_by']) . '</td>';
    echo '<td>' . ($row['created_by_role'] ?? '-') . '</td>';
    echo '<td>' . ($row['created_at'] ?? '-') . '</td>';
    echo '</tr>';
}

echo '</table>';
?>

<div style="margin-top: 2rem;">
    <button onclick="window.location.href='admin.php?tab=tools'">← Zpět na Admin</button>
    <button onclick="window.location.reload()">🔄 Obnovit</button>
</div>

</body>
</html>
