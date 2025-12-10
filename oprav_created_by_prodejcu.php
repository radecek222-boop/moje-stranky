<?php
/**
 * Migrace: Oprava created_by sloupce pro reklamace prodejců
 *
 * Tento skript:
 * 1. Zobrazí strukturu tabulky wgs_reklamace
 * 2. Najde reklamace bez created_by
 * 3. Umožní propojit záznamy s uživateli
 *
 * BEZPEČNÝ: Můžete spustit vícekrát - aktualizuje jen NULL hodnoty
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Oprava created_by prodejců</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .count { font-size: 24px; font-weight: bold; color: #333; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .scroll { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava created_by prodejců</h1>";

    // 1. STRUKTURA TABULKY wgs_reklamace
    echo "<h2>1. Struktura tabulky wgs_reklamace</h2>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $reklamaceColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $columnNames = array_column($reklamaceColumns, 'Field');
    $hasCreatedBy = in_array('created_by', $columnNames);
    $hasZpracovalId = in_array('zpracoval_id', $columnNames);
    $hasProdejce = in_array('prodejce', $columnNames);
    $hasZpracoval = in_array('zpracoval', $columnNames);
    $hasEmailZadavatele = in_array('email_zadavatele', $columnNames);

    echo "<div class='info'>";
    echo "<strong>Relevantní sloupce:</strong><br>";
    echo "created_by: " . ($hasCreatedBy ? '<span style="color:green">EXISTUJE</span>' : '<span style="color:red">NEEXISTUJE</span>') . "<br>";
    echo "zpracoval_id: " . ($hasZpracovalId ? '<span style="color:green">EXISTUJE</span>' : '<span style="color:red">NEEXISTUJE</span>') . "<br>";
    echo "prodejce: " . ($hasProdejce ? '<span style="color:green">EXISTUJE</span>' : '<span style="color:red">NEEXISTUJE</span>') . "<br>";
    echo "zpracoval: " . ($hasZpracoval ? '<span style="color:green">EXISTUJE</span>' : '<span style="color:red">NEEXISTUJE</span>') . "<br>";
    echo "email_zadavatele: " . ($hasEmailZadavatele ? '<span style="color:green">EXISTUJE</span>' : '<span style="color:red">NEEXISTUJE</span>') . "<br>";
    echo "</div>";

    // Zjistit strukturu tabulky wgs_users
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
    $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasUserId = in_array('user_id', $userColumns);
    $idCol = $hasUserId ? 'user_id' : 'id';

    // 2. STATISTIKY
    echo "<h2>2. Statistiky reklamací</h2>";

    // Celkový počet reklamací
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
    $celkem = $stmt->fetchColumn();

    // Reklamace s vyplněným created_by
    $sCreatedBy = 0;
    if ($hasCreatedBy) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NOT NULL AND created_by != ''");
        $sCreatedBy = $stmt->fetchColumn();
    }

    // Reklamace BEZ created_by
    $bezCreatedBy = $celkem - $sCreatedBy;

    echo "<table>";
    echo "<tr><th>Metrika</th><th>Počet</th></tr>";
    echo "<tr><td>Celkem reklamací</td><td class='count'>{$celkem}</td></tr>";
    echo "<tr><td>S vyplněným created_by</td><td class='count'>{$sCreatedBy}</td></tr>";
    echo "<tr><td style='color:#dc3545;font-weight:bold'>BEZ created_by (potenciální problém)</td><td class='count' style='color:#dc3545'>{$bezCreatedBy}</td></tr>";
    echo "</table>";

    // 3. VŠICHNI UŽIVATELÉ V SYSTÉMU
    echo "<h2>3. Všichni uživatelé v systému (wgs_users)</h2>";

    $stmt = $pdo->query("SELECT {$idCol} as user_id, id, name, email, role FROM wgs_users ORDER BY name");
    $vsichniUzivatele = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>user_id (VARCHAR)</th><th>id (INT)</th><th>Jméno</th><th>Email</th><th>Role</th></tr>";
    foreach ($vsichniUzivatele as $u) {
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($u['user_id'] ?? '-') . "</code></td>";
        echo "<td><code>" . htmlspecialchars($u['id'] ?? '-') . "</code></td>";
        echo "<td>" . htmlspecialchars($u['name'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($u['email'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($u['role'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 4. HLEDÁNÍ SIMON STREJCEK
    echo "<h2>4. Hledání: Simon Strejcek</h2>";

    $stmt = $pdo->prepare("SELECT * FROM wgs_users WHERE name LIKE :jmeno OR email LIKE :email");
    $stmt->execute([':jmeno' => '%Simon%', ':email' => '%strejcek%']);
    $simonUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($simonUser) {
        echo "<div class='success'>";
        echo "<strong>Nalezen uživatel:</strong><br>";
        echo "user_id (VARCHAR): <code>" . htmlspecialchars($simonUser['user_id'] ?? 'N/A') . "</code><br>";
        echo "id (INT): <code>" . htmlspecialchars($simonUser['id'] ?? 'N/A') . "</code><br>";
        echo "Jméno: " . htmlspecialchars($simonUser['name'] ?? 'N/A') . "<br>";
        echo "Email: " . htmlspecialchars($simonUser['email'] ?? 'N/A') . "<br>";
        echo "Role: " . htmlspecialchars($simonUser['role'] ?? 'N/A');
        echo "</div>";

        $simonUserId = $simonUser['user_id'] ?? null;
        $simonIntId = $simonUser['id'] ?? null;

        // Kolik reklamací má tento uživatel
        if ($hasCreatedBy && $simonUserId) {
            // Test s VARCHAR user_id
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by = :id");
            $stmt->execute([':id' => $simonUserId]);
            $simonReklamaceVarchar = $stmt->fetchColumn();

            // Test s INT id
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by = :id");
            $stmt->execute([':id' => $simonIntId]);
            $simonReklamaceInt = $stmt->fetchColumn();

            echo "<div class='info'>";
            echo "Reklamace s <code>created_by = '{$simonUserId}'</code> (VARCHAR): <strong>{$simonReklamaceVarchar}</strong><br>";
            echo "Reklamace s <code>created_by = '{$simonIntId}'</code> (INT): <strong>{$simonReklamaceInt}</strong>";
            echo "</div>";

            if ($simonReklamaceVarchar == 0 && $simonReklamaceInt == 0) {
                echo "<div class='error'>";
                echo "<strong>PROBLÉM NALEZEN!</strong><br>";
                echo "Simon Strejcek nemá žádné reklamace s vyplněným created_by!";
                echo "</div>";
            }
        }
    } else {
        echo "<div class='error'>Uživatel 'Simon Strejcek' nebyl nalezen v tabulce wgs_users!</div>";

        // Hledat podobná jména
        $stmt = $pdo->query("SELECT name FROM wgs_users WHERE name LIKE '%Simon%' OR name LIKE '%Strejcek%'");
        $podobni = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($podobni)) {
            echo "<div class='info'>Podobná jména v systému:<br>";
            foreach ($podobni as $jmeno) {
                echo "- " . htmlspecialchars($jmeno) . "<br>";
            }
            echo "</div>";
        }
    }

    // 5. UKÁZKA REKLAMACÍ BEZ CREATED_BY
    echo "<h2>5. Reklamace bez created_by (ukázka)</h2>";

    if ($hasCreatedBy) {
        $stmt = $pdo->query("
            SELECT id, reklamace_id, cislo, jmeno, email, stav, created_at
            FROM wgs_reklamace
            WHERE created_by IS NULL OR created_by = ''
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $bezCreatedByUkazka = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($bezCreatedByUkazka)) {
            echo "<div class='success'>Všechny reklamace mají vyplněný created_by!</div>";
        } else {
            echo "<div class='scroll'>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Reklamace ID</th><th>Číslo</th><th>Zákazník</th><th>Email</th><th>Stav</th><th>Vytvořeno</th></tr>";
            foreach ($bezCreatedByUkazka as $r) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($r['id'] ?? '-') . "</td>";
                echo "<td><code>" . htmlspecialchars($r['reklamace_id'] ?? '-') . "</code></td>";
                echo "<td><code>" . htmlspecialchars($r['cislo'] ?? '-') . "</code></td>";
                echo "<td>" . htmlspecialchars($r['jmeno'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($r['email'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($r['stav'] ?? '-') . "</td>";
                echo "<td>" . htmlspecialchars($r['created_at'] ?? '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
        }
    }

    // 6. RUČNÍ OPRAVA PRO SIMONA
    echo "<h2>6. Opravit reklamace pro Simona Strejcka</h2>";

    if ($simonUser && $hasCreatedBy) {
        $simonUserId = $simonUser['user_id'] ?? null;

        if (isset($_GET['fix_simon']) && $_GET['fix_simon'] === '1' && $simonUserId) {
            // Aktualizovat vybrané reklamace
            $ids = $_GET['ids'] ?? '';
            $idList = array_filter(array_map('intval', explode(',', $ids)));

            if (!empty($idList)) {
                $pdo->beginTransaction();
                try {
                    $placeholders = implode(',', array_fill(0, count($idList), '?'));
                    $stmt = $pdo->prepare("
                        UPDATE wgs_reklamace
                        SET created_by = ?,
                            created_by_role = 'prodejce'
                        WHERE id IN ({$placeholders})
                        AND (created_by IS NULL OR created_by = '')
                    ");

                    $params = array_merge([$simonUserId], $idList);
                    $stmt->execute($params);

                    $aktualizovano = $stmt->rowCount();
                    $pdo->commit();

                    echo "<div class='success'>";
                    echo "<strong>OPRAVENO!</strong><br>";
                    echo "Aktualizováno <strong>{$aktualizovano}</strong> reklamací pro Simona Strejcka.";
                    echo "</div>";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            } else {
                echo "<div class='warning'>Nebyla vybrána žádná ID reklamací k opravě.</div>";
            }
        } else {
            echo "<div class='info'>";
            echo "Pro opravu reklamací pro Simona Strejcka:<br><br>";
            echo "1. Zjistěte ID reklamací, které patří Simonovi<br>";
            echo "2. Zadejte je do URL parametru: <code>?fix_simon=1&ids=1,2,3,4,5</code><br><br>";
            echo "Simon's user_id: <code>" . htmlspecialchars($simonUserId) . "</code>";
            echo "</div>";

            // Nabídnout rychlou opravu všech reklamací bez created_by
            if ($bezCreatedBy > 0) {
                echo "<div class='warning'>";
                echo "<strong>Rychlá oprava:</strong><br>";
                echo "Máte <strong>{$bezCreatedBy}</strong> reklamací bez created_by.<br><br>";
                echo "Pokud VŠECHNY tyto reklamace patří Simonovi, můžete je opravit hromadně:<br>";
                echo "<a href='?fix_all_simon=1' class='btn' onclick=\"return confirm('Opravdu přiřadit VŠECHNY reklamace bez created_by Simonovi?')\">Přiřadit všechny Simonovi</a>";
                echo "</div>";
            }
        }

        // Hromadná oprava všech
        if (isset($_GET['fix_all_simon']) && $_GET['fix_all_simon'] === '1' && $simonUserId) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    UPDATE wgs_reklamace
                    SET created_by = :user_id,
                        created_by_role = 'prodejce'
                    WHERE created_by IS NULL OR created_by = ''
                ");
                $stmt->execute([':user_id' => $simonUserId]);

                $aktualizovano = $stmt->rowCount();
                $pdo->commit();

                echo "<div class='success' style='font-size: 18px;'>";
                echo "<strong>HROMADNÁ OPRAVA DOKONČENA!</strong><br>";
                echo "Aktualizováno <strong>{$aktualizovano}</strong> reklamací - created_by nastaveno na '{$simonUserId}'.";
                echo "</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    } else {
        echo "<div class='warning'>Simon Strejcek nebyl nalezen nebo sloupec created_by neexistuje.</div>";
    }

    echo "<br><br>";
    echo "<a href='/admin.php' class='btn'>Zpět do Admin</a>";
    echo "<a href='/seznam.php' class='btn'>Seznam reklamací</a>";
    echo "<a href='?' class='btn'>Obnovit stránku</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
