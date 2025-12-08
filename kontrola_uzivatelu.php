<?php
/**
 * Kontrola uživatelů a jejich dat v databázi
 * Pro admin použití
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze admin");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Kontrola uživatelů</title>";
echo "<style>
body { font-family: 'Poppins', sans-serif; background: #1a1a1a; color: #ccc; padding: 20px; }
h1, h2 { color: #fff; }
table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
th, td { border: 1px solid #333; padding: 8px; text-align: left; }
th { background: #333; color: #39ff14; }
tr:hover { background: #2a2a2a; }
.btn { background: #dc3545; color: #fff; padding: 5px 10px; border: none; cursor: pointer; margin: 2px; }
.btn:hover { background: #c82333; }
.info { background: #222; padding: 15px; border-left: 4px solid #39ff14; margin: 10px 0; }
</style></head><body>";

try {
    $pdo = getDbConnection();

    // 1. Všichni uživatelé
    echo "<h1>KONTROLA UŽIVATELŮ</h1>";
    echo "<h2>1. Seznam uživatelů (wgs_users)</h2>";
    $stmt = $pdo->query("SELECT * FROM wgs_users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table><tr><th>user_id</th><th>name</th><th>email</th><th>role</th><th>is_active</th><th>created_at</th></tr>";
    foreach ($users as $u) {
        echo "<tr>";
        echo "<td>{$u['user_id']}</td>";
        echo "<td>{$u['name']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['role']}</td>";
        echo "<td>{$u['is_active']}</td>";
        echo "<td>{$u['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. Reklamace podle created_by
    echo "<h2>2. Reklamace podle zadavatele (created_by)</h2>";
    $stmt = $pdo->query("
        SELECT r.created_by, u.name as zadavatel_jmeno, COUNT(*) as pocet
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        GROUP BY r.created_by
        ORDER BY pocet DESC
    ");
    $byCreator = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table><tr><th>created_by</th><th>Jméno zadavatele</th><th>Počet reklamací</th></tr>";
    foreach ($byCreator as $row) {
        $createdBy = $row['created_by'] ?: '(prázdné/NULL)';
        $name = $row['zadavatel_jmeno'] ?: '(není v wgs_users)';
        echo "<tr><td>{$createdBy}</td><td>{$name}</td><td>{$row['pocet']}</td></tr>";
    }
    echo "</table>";

    // 3. Reklamace podle technika
    echo "<h2>3. Reklamace podle technika (technik sloupec)</h2>";
    $stmt = $pdo->query("
        SELECT technik, COUNT(*) as pocet
        FROM wgs_reklamace
        GROUP BY technik
        ORDER BY pocet DESC
    ");
    $byTechnik = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table><tr><th>technik</th><th>Počet reklamací</th></tr>";
    foreach ($byTechnik as $row) {
        $technik = $row['technik'] ?: '(prázdné/NULL)';
        echo "<tr><td>{$technik}</td><td>{$row['pocet']}</td></tr>";
    }
    echo "</table>";

    // 4. Nekonzistentní data
    echo "<h2>4. Nekonzistentní data - created_by bez odpovídajícího uživatele</h2>";
    $stmt = $pdo->query("
        SELECT DISTINCT r.created_by
        FROM wgs_reklamace r
        WHERE r.created_by IS NOT NULL
        AND r.created_by != ''
        AND r.created_by NOT IN (SELECT user_id FROM wgs_users)
    ");
    $orphans = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($orphans) > 0) {
        echo "<div class='info'>Nalezeny neplatné hodnoty created_by: " . implode(', ', $orphans) . "</div>";
    } else {
        echo "<div class='info'>Všechny hodnoty created_by mají odpovídajícího uživatele.</div>";
    }

    // 5. Duplicitní emaily
    echo "<h2>5. Duplicitní emaily v wgs_users</h2>";
    $stmt = $pdo->query("
        SELECT email, COUNT(*) as pocet
        FROM wgs_users
        GROUP BY email
        HAVING COUNT(*) > 1
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($duplicates) > 0) {
        echo "<table><tr><th>Email</th><th>Počet</th></tr>";
        foreach ($duplicates as $d) {
            echo "<tr><td>{$d['email']}</td><td>{$d['pocet']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Žádné duplicitní emaily.</div>";
    }

} catch (Exception $e) {
    echo "<div style='color:red;'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
