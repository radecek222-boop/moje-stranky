<?php
/**
 * Migrace: Oprava created_by sloupce pro reklamace prodejců
 *
 * Tento skript:
 * 1. Najde reklamace kde 'prodejce' je vyplněný ale 'created_by' je NULL
 * 2. Propojí textové jméno prodejce s user_id z wgs_users
 * 3. Aktualizuje created_by na správné hodnoty
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
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava created_by prodejců</h1>";

    // Zjistit strukturu tabulky wgs_users
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
    $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasUserId = in_array('user_id', $userColumns);
    $idCol = $hasUserId ? 'user_id' : 'id';

    // 1. STATISTIKY
    echo "<h2>1. Statistiky reklamací</h2>";

    // Celkový počet reklamací
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
    $celkem = $stmt->fetchColumn();

    // Reklamace s vyplněným created_by
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NOT NULL AND created_by != ''");
    $sCreatedBy = $stmt->fetchColumn();

    // Reklamace s vyplněným prodejce ale BEZ created_by
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM wgs_reklamace
        WHERE (created_by IS NULL OR created_by = '')
        AND prodejce IS NOT NULL AND prodejce != ''
    ");
    $kOprave = $stmt->fetchColumn();

    echo "<table>";
    echo "<tr><th>Metrika</th><th>Počet</th></tr>";
    echo "<tr><td>Celkem reklamací</td><td class='count'>{$celkem}</td></tr>";
    echo "<tr><td>S vyplněným created_by</td><td class='count'>{$sCreatedBy}</td></tr>";
    echo "<tr><td style='color:#dc3545;font-weight:bold'>K OPRAVĚ (prodejce vyplněn, created_by prázdný)</td><td class='count' style='color:#dc3545'>{$kOprave}</td></tr>";
    echo "</table>";

    // 2. SEZNAM UNIKÁTNÍCH PRODEJCŮ BEZ CREATED_BY
    echo "<h2>2. Prodejci bez propojeného created_by</h2>";

    $stmt = $pdo->query("
        SELECT
            r.prodejce,
            COUNT(*) as pocet_reklamaci,
            GROUP_CONCAT(DISTINCT r.reklamace_id ORDER BY r.reklamace_id SEPARATOR ', ') as reklamace_ids
        FROM wgs_reklamace r
        WHERE (r.created_by IS NULL OR r.created_by = '')
        AND r.prodejce IS NOT NULL AND r.prodejce != ''
        GROUP BY r.prodejce
        ORDER BY pocet_reklamaci DESC
    ");
    $prodejciBezCreatedBy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($prodejciBezCreatedBy)) {
        echo "<div class='success'>Všechny reklamace mají správně vyplněný created_by!</div>";
    } else {
        echo "<table>";
        echo "<tr><th>Prodejce (text)</th><th>Počet reklamací</th><th>Reklamace IDs</th><th>Nalezené user_id</th></tr>";

        foreach ($prodejciBezCreatedBy as $prodejce) {
            $jmeno = $prodejce['prodejce'];
            $pocet = $prodejce['pocet_reklamaci'];
            $ids = strlen($prodejce['reklamace_ids']) > 50
                ? substr($prodejce['reklamace_ids'], 0, 50) . '...'
                : $prodejce['reklamace_ids'];

            // Zkusit najít odpovídající uživatele v wgs_users
            $stmtUser = $pdo->prepare("
                SELECT {$idCol}, name, email
                FROM wgs_users
                WHERE name LIKE :jmeno
                OR CONCAT(name, ' ', email) LIKE :jmeno2
                LIMIT 3
            ");
            $stmtUser->execute([
                ':jmeno' => '%' . $jmeno . '%',
                ':jmeno2' => '%' . $jmeno . '%'
            ]);
            $nalezenUsers = $stmtUser->fetchAll(PDO::FETCH_ASSOC);

            $userInfo = '';
            if (!empty($nalezenUsers)) {
                $userInfo = '<span style="color:green">';
                foreach ($nalezenUsers as $u) {
                    $userInfo .= htmlspecialchars($u[$idCol]) . ' (' . htmlspecialchars($u['name']) . ')';
                    if ($u !== end($nalezenUsers)) $userInfo .= ', ';
                }
                $userInfo .= '</span>';
            } else {
                $userInfo = '<span style="color:#dc3545">NENALEZEN</span>';
            }

            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($jmeno) . "</strong></td>";
            echo "<td>{$pocet}</td>";
            echo "<td><code>{$ids}</code></td>";
            echo "<td>{$userInfo}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // 3. SEZNAM VŠECH UŽIVATELŮ V SYSTÉMU
    echo "<h2>3. Všichni uživatelé v systému (wgs_users)</h2>";

    $stmt = $pdo->query("SELECT {$idCol} as user_id, name, email, role FROM wgs_users ORDER BY name");
    $vsichniUzivatele = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>user_id</th><th>Jméno</th><th>Email</th><th>Role</th></tr>";
    foreach ($vsichniUzivatele as $u) {
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($u['user_id']) . "</code></td>";
        echo "<td>" . htmlspecialchars($u['name']) . "</td>";
        echo "<td>" . htmlspecialchars($u['email']) . "</td>";
        echo "<td>" . htmlspecialchars($u['role'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 4. KONKRÉTNÍ INFO O SIMONU STREJCKOVI
    echo "<h2>4. Hledání: Simon Strejcek</h2>";

    $stmt = $pdo->prepare("SELECT * FROM wgs_users WHERE name LIKE :jmeno OR email LIKE :email");
    $stmt->execute([':jmeno' => '%Simon%Strejcek%', ':email' => '%strejcek%']);
    $simonUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($simonUser) {
        echo "<div class='success'>";
        echo "<strong>Nalezen uživatel:</strong><br>";
        echo "user_id: <code>" . htmlspecialchars($simonUser[$idCol] ?? $simonUser['id'] ?? 'N/A') . "</code><br>";
        echo "Jméno: " . htmlspecialchars($simonUser['name'] ?? 'N/A') . "<br>";
        echo "Email: " . htmlspecialchars($simonUser['email'] ?? 'N/A') . "<br>";
        echo "Role: " . htmlspecialchars($simonUser['role'] ?? 'N/A');
        echo "</div>";

        $simonId = $simonUser[$idCol] ?? $simonUser['id'] ?? null;

        // Kolik reklamací má tento uživatel
        if ($simonId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by = :id");
            $stmt->execute([':id' => $simonId]);
            $simonReklamace = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_reklamace WHERE prodejce LIKE :jmeno");
            $stmt->execute([':jmeno' => '%' . ($simonUser['name'] ?? 'Simon') . '%']);
            $simonProdejceText = $stmt->fetchColumn();

            echo "<div class='info'>";
            echo "Reklamace s <code>created_by = '{$simonId}'</code>: <strong>{$simonReklamace}</strong><br>";
            echo "Reklamace s <code>prodejce LIKE '{$simonUser['name']}'</code>: <strong>{$simonProdejceText}</strong>";
            echo "</div>";

            if ($simonProdejceText > $simonReklamace) {
                echo "<div class='warning'>";
                echo "<strong>PROBLÉM NALEZEN!</strong><br>";
                echo "Simon má " . ($simonProdejceText - $simonReklamace) . " reklamací kde je uveden jako prodejce (text), ";
                echo "ale created_by není správně nastaveno!";
                echo "</div>";
            }
        }
    } else {
        echo "<div class='error'>Uživatel 'Simon Strejcek' nebyl nalezen v tabulce wgs_users!</div>";

        // Zkusit najít v reklamacích
        $stmt = $pdo->query("SELECT DISTINCT prodejce FROM wgs_reklamace WHERE prodejce LIKE '%Simon%' OR prodejce LIKE '%Strejcek%'");
        $simonProdejce = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($simonProdejce)) {
            echo "<div class='info'>V reklamacích nalezeny tyto hodnoty prodejce:<br>";
            foreach ($simonProdejce as $p) {
                echo "- <code>" . htmlspecialchars($p) . "</code><br>";
            }
            echo "</div>";
        }
    }

    // 5. AKCE - OPRAVA
    echo "<h2>5. Opravit created_by</h2>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            $opraveno = 0;

            // Pro každého prodejce bez created_by zkusit najít odpovídající user_id
            foreach ($prodejciBezCreatedBy as $prodejce) {
                $jmenoProdejce = $prodejce['prodejce'];

                // Hledat přesnou shodu jména
                $stmtUser = $pdo->prepare("SELECT {$idCol} FROM wgs_users WHERE name = :jmeno LIMIT 1");
                $stmtUser->execute([':jmeno' => $jmenoProdejce]);
                $userId = $stmtUser->fetchColumn();

                // Pokud nenalezeno, zkusit částečnou shodu
                if (!$userId) {
                    $stmtUser = $pdo->prepare("SELECT {$idCol} FROM wgs_users WHERE name LIKE :jmeno LIMIT 1");
                    $stmtUser->execute([':jmeno' => '%' . $jmenoProdejce . '%']);
                    $userId = $stmtUser->fetchColumn();
                }

                if ($userId) {
                    // Aktualizovat všechny reklamace tohoto prodejce
                    $stmtUpdate = $pdo->prepare("
                        UPDATE wgs_reklamace
                        SET created_by = :user_id,
                            created_by_role = 'prodejce',
                            zpracoval_id = :user_id2
                        WHERE prodejce = :prodejce
                        AND (created_by IS NULL OR created_by = '')
                    ");
                    $stmtUpdate->execute([
                        ':user_id' => $userId,
                        ':user_id2' => $userId,
                        ':prodejce' => $jmenoProdejce
                    ]);

                    $pocetAktualizovanych = $stmtUpdate->rowCount();
                    $opraveno += $pocetAktualizovanych;

                    echo "<div class='success'>";
                    echo "Prodejce '<strong>" . htmlspecialchars($jmenoProdejce) . "</strong>' -> ";
                    echo "user_id '<code>{$userId}</code>': ";
                    echo "<strong>{$pocetAktualizovanych}</strong> reklamací opraveno";
                    echo "</div>";
                } else {
                    echo "<div class='warning'>";
                    echo "Prodejce '<strong>" . htmlspecialchars($jmenoProdejce) . "</strong>': ";
                    echo "Nenalezen odpovídající uživatel v wgs_users - PŘESKOČENO";
                    echo "</div>";
                }
            }

            $pdo->commit();

            echo "<div class='success' style='font-size: 18px; margin-top: 20px;'>";
            echo "<strong>MIGRACE DOKONČENA!</strong><br>";
            echo "Celkem opraveno: <strong>{$opraveno}</strong> reklamací";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        if ($kOprave > 0) {
            echo "<div class='warning'>";
            echo "<strong>Nalezeno {$kOprave} reklamací k opravě!</strong><br><br>";
            echo "Kliknutím na tlačítko níže se aktualizuje sloupec <code>created_by</code> ";
            echo "na základě textového jména v sloupci <code>prodejce</code>.";
            echo "</div>";

            echo "<a href='?execute=1' class='btn' onclick=\"return confirm('Opravdu spustit opravu {$kOprave} reklamací?')\">SPUSTIT OPRAVU</a>";
        } else {
            echo "<div class='success'>Není co opravovat - všechny reklamace mají správně vyplněný created_by!</div>";
        }
    }

    echo "<br><br>";
    echo "<a href='/admin.php' class='btn'>Zpět do Admin</a>";
    echo "<a href='/seznam.php' class='btn'>Seznam reklamací</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
