<?php
/**
 * Diagnostika Tech Provize API
 * Zkontroluje strukturu databáze a najde přesnou chybu
 */

require_once __DIR__ . '/init.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    die('<h1>Nejsi přihlášen</h1>');
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'N/A';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostika Provize</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #000; color: #0f0; }
        h1, h2 { color: #39ff14; }
        .ok { color: #39ff14; }
        .error { color: #ff4444; }
        .warning { color: #ffaa00; }
        pre { background: #111; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #222; color: #39ff14; }
    </style>
</head>
<body>
<h1>DIAGNOSTIKA TECH PROVIZE</h1>";

echo "<h2>1. Session Info</h2>";
echo "<div>User ID: <span class='ok'>{$userId}</span></div>";
echo "<div>Role: <span class='ok'>{$userRole}</span></div>";

try {
    $pdo = getDbConnection();

    // Krok 1: Zkontrolovat wgs_users strukturu
    echo "<h2>2. Struktura wgs_users</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Zjistit zda existují sloupce provize
    $hasProvizeProcent = false;
    $hasProvizePozProcent = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'provize_procent') $hasProvizeProcent = true;
        if ($col['Field'] === 'provize_poz_procent') $hasProvizePozProcent = true;
    }

    echo "<div>Sloupec provize_procent: " . ($hasProvizeProcent ? "<span class='ok'>✅ EXISTUJE</span>" : "<span class='error'>❌ CHYBÍ</span>") . "</div>";
    echo "<div>Sloupec provize_poz_procent: " . ($hasProvizePozProcent ? "<span class='ok'>✅ EXISTUJE</span>" : "<span class='error'>❌ CHYBÍ</span>") . "</div>";

    // Krok 2: Najít uživatele
    echo "<h2>3. Údaje technika</h2>";
    $stmtUser = $pdo->prepare("SELECT id, user_id, name as full_name, role, COALESCE(provize_procent, 33) as provize_procent, COALESCE(provize_poz_procent, 50) as provize_poz_procent FROM wgs_users WHERE user_id = :user_id LIMIT 1");
    $stmtUser->execute([':user_id' => $userId]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        echo "<div class='error'>❌ Uživatel nenalezen v databázi!</div>";
    } else {
        echo "<table>";
        foreach ($userRow as $key => $value) {
            echo "<tr><th>{$key}</th><td>{$value}</td></tr>";
        }
        echo "</table>";

        $numericUserId = $userRow['id'];
        $userName = trim($userRow['full_name']);
        $provizeProcent = (float)($userRow['provize_procent']);
        $provizePozProcent = (float)($userRow['provize_poz_procent']);

        echo "<div>Numeric User ID: <span class='ok'>{$numericUserId}</span></div>";
        echo "<div>Jméno: <span class='ok'>{$userName}</span></div>";
        echo "<div>Provize REKLAMACE: <span class='ok'>{$provizeProcent}%</span></div>";
        echo "<div>Provize POZ: <span class='ok'>{$provizePozProcent}%</span></div>";

        // Krok 3: Zkontrolovat wgs_reklamace strukturu
        echo "<h2>4. Struktura wgs_reklamace</h2>";
        $stmtColumns = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'dokonceno_kym'");
        $hasDokoncenokym = $stmtColumns->rowCount() > 0;

        $stmtColumns2 = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'datum_dokonceni'");
        $hasDatumDokonceni = $stmtColumns2->rowCount() > 0;

        echo "<div>Sloupec dokonceno_kym: " . ($hasDokoncenokym ? "<span class='ok'>✅ EXISTUJE</span>" : "<span class='error'>❌ CHYBÍ</span>") . "</div>";
        echo "<div>Sloupec datum_dokonceni: " . ($hasDatumDokonceni ? "<span class='ok'>✅ EXISTUJE</span>" : "<span class='error'>❌ CHYBÍ</span>") . "</div>";

        // Krok 4: Zkusit SQL dotaz REKLAMACE
        echo "<h2>5. Test SQL dotazu REKLAMACE</h2>";
        $datumSloupec = $hasDatumDokonceni ? 'COALESCE(r.datum_dokonceni, r.updated_at)' : 'r.updated_at';

        if ($hasDokoncenokym) {
            $whereCondition = "(r.dokonceno_kym = :numeric_id OR (r.dokonceno_kym IS NULL AND (r.assigned_to = :numeric_id2 OR r.technik LIKE :user_name)))";
        } else {
            $whereCondition = "(r.assigned_to = :numeric_id OR r.technik LIKE :user_name)";
        }

        $aktualniRok = date('Y');
        $aktualniMesic = date('m');
        $provizeKoeficient = $provizeProcent / 100;

        $sql = "
            SELECT
                COUNT(*) as pocet_zakazek,
                SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) as celkem_castka,
                SUM(CAST(COALESCE(r.cena_celkem, r.cena, 0) AS DECIMAL(10,2))) * {$provizeKoeficient} as provize_celkem
            FROM wgs_reklamace r
            WHERE {$whereCondition}
              AND YEAR({$datumSloupec}) = :rok
              AND MONTH({$datumSloupec}) = :mesic
              AND r.stav = 'done'
              AND (r.created_by IS NOT NULL AND r.created_by != '')
        ";

        echo "<pre>" . htmlspecialchars($sql) . "</pre>";

        try {
            $stmtTest = $pdo->prepare($sql);
            $params = [
                'numeric_id' => $numericUserId,
                'rok' => $aktualniRok,
                'mesic' => $aktualniMesic
            ];
            if ($hasDokoncenokym) {
                $params['numeric_id2'] = $numericUserId;
                $params['user_name'] = '%' . $userName . '%';
            } else {
                $params['user_name'] = '%' . $userName . '%';
            }

            $stmtTest->execute($params);
            $result = $stmtTest->fetch(PDO::FETCH_ASSOC);

            echo "<div class='ok'>✅ SQL dotaz úspěšný</div>";
            echo "<table>";
            foreach ($result as $key => $value) {
                echo "<tr><th>{$key}</th><td>{$value}</td></tr>";
            }
            echo "</table>";

        } catch (PDOException $e) {
            echo "<div class='error'>❌ SQL chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ EXCEPTION: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>
