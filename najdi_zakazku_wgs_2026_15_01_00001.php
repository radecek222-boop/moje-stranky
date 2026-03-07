<?php
/**
 * Vyhledání zakázky WGS/2026/15-01/00001
 * Kterou zadala Monika Jančová a zákazník B. Cikán ji nepoznává
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může tento skript spustit.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vyhledání zakázky WGS/2026/15-01/00001</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #333; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        .code { background: #f4f4f4; padding: 15px; border-left: 4px solid #333;
                font-family: monospace; margin: 15px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Vyhledání zakázky WGS/2026/15-01/00001</h1>";

    echo "<div class='info'>
        <strong>Kontext problému:</strong><br>
        Zákazník B. Cikán (bodac@seznam.cz) obdržel email o zakázce, kterou nezadával.<br>
        Email uvádí, že zakázku zadala <strong>Monika Jančová</strong> dne 15.01.2026 v 09:16.
    </div>";

    // ==================================================
    // 1. NAJÍT ZAKÁZKU PODLE reklamace_id
    // ==================================================
    echo "<h2>1. Údaje o zakázce</h2>";

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            u.email as creator_email,
            u.full_name as creator_name,
            u.role as creator_role
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        WHERE r.reklamace_id = :reklamace_id
        LIMIT 1
    ");
    $stmt->execute([':reklamace_id' => 'WGS/2026/15-01/00001']);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakazka) {
        echo "<div class='warning'>
            <strong>POZOR: Zakázka nebyla nalezena!</strong><br>
            Reklamace s ID <code>WGS/2026/15-01/00001</code> neexistuje v databázi.
        </div>";

        // Zkusit najít nejnovější zakázky z 15.01.2026
        echo "<h3>Nejnovější zakázky z 15.01.2026:</h3>";
        $stmt = $pdo->prepare("
            SELECT reklamace_id, jmeno, email, telefon, created_at, created_by
            FROM wgs_reklamace
            WHERE DATE(created_at) = '2026-01-15'
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $novejsiZakazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($novejsiZakazky) {
            echo "<table>
                <tr>
                    <th>Číslo zakázky</th>
                    <th>Jméno</th>
                    <th>Email</th>
                    <th>Telefon</th>
                    <th>Vytvořeno</th>
                    <th>Vytvořil (ID)</th>
                </tr>";
            foreach ($novejsiZakazky as $zak) {
                echo "<tr>
                    <td>" . htmlspecialchars($zak['reklamace_id']) . "</td>
                    <td>" . htmlspecialchars($zak['jmeno']) . "</td>
                    <td>" . htmlspecialchars($zak['email']) . "</td>
                    <td>" . htmlspecialchars($zak['telefon']) . "</td>
                    <td>" . htmlspecialchars($zak['created_at']) . "</td>
                    <td>" . htmlspecialchars($zak['created_by'] ?? 'NULL') . "</td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info'>Žádné zakázky z 15.01.2026.</div>";
        }

    } else {
        // Zakázka nalezena
        echo "<div class='success'>
            <strong>OK: Zakázka nalezena v databázi!</strong>
        </div>";

        echo "<table>
            <tr><th>Pole</th><th>Hodnota</th></tr>
            <tr><td><strong>Číslo zakázky</strong></td><td>" . htmlspecialchars($zakazka['reklamace_id']) . "</td></tr>
            <tr><td><strong>Typ</strong></td><td>" . htmlspecialchars($zakazka['typ']) . "</td></tr>
            <tr><td><strong>Číslo (cislo)</strong></td><td>" . htmlspecialchars($zakazka['cislo']) . "</td></tr>
            <tr><td><strong>Jméno zákazníka</strong></td><td>" . htmlspecialchars($zakazka['jmeno']) . "</td></tr>
            <tr><td><strong>Email</strong></td><td><strong style='color:red;'>" . htmlspecialchars($zakazka['email']) . "</strong></td></tr>
            <tr><td><strong>Telefon</strong></td><td>" . htmlspecialchars($zakazka['telefon']) . "</td></tr>
            <tr><td><strong>Adresa</strong></td><td>" . htmlspecialchars($zakazka['adresa']) . "</td></tr>
            <tr><td><strong>Model</strong></td><td>" . htmlspecialchars($zakazka['model']) . "</td></tr>
            <tr><td><strong>Popis problému</strong></td><td>" . htmlspecialchars($zakazka['popis_problemu']) . "</td></tr>
            <tr><td><strong>Datum vytvoření</strong></td><td>" . htmlspecialchars($zakazka['created_at'] ?? $zakazka['datum_vytvoreni'] ?? 'N/A') . "</td></tr>
            <tr><td><strong>Vytvořil (user_id)</strong></td><td>" . htmlspecialchars($zakazka['created_by'] ?? 'NULL') . "</td></tr>
            <tr><td><strong>Vytvořil (jméno)</strong></td><td><strong style='color:blue;'>" . htmlspecialchars($zakazka['creator_name'] ?? 'N/A') . "</strong></td></tr>
            <tr><td><strong>Vytvořil (email)</strong></td><td>" . htmlspecialchars($zakazka['creator_email'] ?? 'N/A') . "</td></tr>
            <tr><td><strong>Role tvůrce</strong></td><td>" . htmlspecialchars($zakazka['creator_role'] ?? $zakazka['created_by_role'] ?? 'N/A') . "</td></tr>
        </table>";

        // ==================================================
        // 2. KDO JE MONIKA JANČOVÁ?
        // ==================================================
        echo "<h2>2. Kdo je Monika Jančová?</h2>";

        $stmt = $pdo->prepare("
            SELECT user_id, email, full_name, role, is_active, created_at
            FROM wgs_users
            WHERE full_name LIKE :name OR email LIKE :email
        ");
        $stmt->execute([
            ':name' => '%Monika%Jan%',
            ':email' => '%monika%'
        ]);
        $monika = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($monika) {
            echo "<table>
                <tr>
                    <th>User ID</th>
                    <th>Jméno</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Aktivní</th>
                    <th>Vytvořen</th>
                </tr>";
            foreach ($monika as $user) {
                $jeToTvurce = ($zakazka['created_by'] == $user['user_id']) ? ' style="background:#ffffcc;"' : '';
                echo "<tr{$jeToTvurce}>
                    <td>" . htmlspecialchars($user['user_id']) . "</td>
                    <td><strong>" . htmlspecialchars($user['full_name']) . "</strong></td>
                    <td>" . htmlspecialchars($user['email']) . "</td>
                    <td>" . htmlspecialchars($user['role']) . "</td>
                    <td>" . ($user['is_active'] ? 'Ano' : 'Ne') . "</td>
                    <td>" . htmlspecialchars($user['created_at']) . "</td>
                </tr>";
            }
            echo "</table>";

            if ($zakazka['created_by']) {
                $tvurce = array_filter($monika, fn($u) => $u['user_id'] == $zakazka['created_by']);
                if ($tvurce) {
                    $tvurce = array_values($tvurce)[0];
                    echo "<div class='success'>
                        <strong>OK: Potvrzeno:</strong> Zakázku vytvořil/a uživatel <strong>" . htmlspecialchars($tvurce['full_name']) . "</strong> (" . htmlspecialchars($tvurce['email']) . ")
                    </div>";
                }
            }
        } else {
            echo "<div class='warning'>
                <strong>POZOR: Žádný uživatel s jménem 'Monika Jančová' nebyl nalezen v databázi!</strong>
            </div>";
        }

        // ==================================================
        // 3. AUDIT LOG - KDO A KDY ZAKÁZKU VYTVOŘIL
        // ==================================================
        echo "<h2>3. Audit log - historie akcí</h2>";

        $stmt = $pdo->prepare("
            SELECT *
            FROM wgs_audit_log
            WHERE action = 'reklamace_created'
            AND details LIKE :reklamace_id
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([':reklamace_id' => '%' . $zakazka['reklamace_id'] . '%']);
        $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($auditLogs) {
            echo "<table>
                <tr>
                    <th>Datum</th>
                    <th>User ID</th>
                    <th>IP adresa</th>
                    <th>Akce</th>
                    <th>Detaily</th>
                </tr>";
            foreach ($auditLogs as $log) {
                echo "<tr>
                    <td>" . htmlspecialchars($log['created_at']) . "</td>
                    <td>" . htmlspecialchars($log['user_id'] ?? 'N/A') . "</td>
                    <td>" . htmlspecialchars($log['ip_address'] ?? 'N/A') . "</td>
                    <td>" . htmlspecialchars($log['action']) . "</td>
                    <td><pre>" . htmlspecialchars($log['details'] ?? 'N/A') . "</pre></td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info'>Žádné záznamy v audit logu pro tuto zakázku.</div>";
        }

        // ==================================================
        // 4. EMAIL FRONTA - BYL EMAIL ODESLÁN?
        // ==================================================
        echo "<h2>4. Email fronta - byl email skutečně odeslán?</h2>";

        $stmt = $pdo->prepare("
            SELECT *
            FROM wgs_email_queue
            WHERE to_email = :email
            AND body LIKE :reklamace_id
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([
            ':email' => $zakazka['email'],
            ':reklamace_id' => '%' . $zakazka['reklamace_id'] . '%'
        ]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($emails) {
            echo "<table>
                <tr>
                    <th>Datum</th>
                    <th>Příjemce</th>
                    <th>Předmět</th>
                    <th>Stav</th>
                    <th>Odesláno</th>
                    <th>Počet pokusů</th>
                </tr>";
            foreach ($emails as $email) {
                $stavBarva = $email['status'] == 'sent' ? 'green' : 'orange';
                echo "<tr>
                    <td>" . htmlspecialchars($email['created_at']) . "</td>
                    <td><strong>" . htmlspecialchars($email['to_email']) . "</strong></td>
                    <td>" . htmlspecialchars($email['subject']) . "</td>
                    <td style='color:{$stavBarva};'><strong>" . htmlspecialchars($email['status']) . "</strong></td>
                    <td>" . htmlspecialchars($email['sent_at'] ?? 'Neodeslán') . "</td>
                    <td>" . htmlspecialchars($email['retry_count']) . "</td>
                </tr>";
            }
            echo "</table>";

            if ($emails[0]['status'] == 'sent') {
                echo "<div class='success'>
                    <strong>OK: Email byl úspěšně odeslán na " . htmlspecialchars($zakazka['email']) . "</strong>
                </div>";
            }
        } else {
            echo "<div class='info'>Žádné emaily v email frontě pro tento email a zakázku.</div>";
        }

        // ==================================================
        // 5. ZÁVĚR A DOPORUČENÍ
        // ==================================================
        echo "<h2>5. Závěr a doporučení</h2>";

        echo "<div class='warning'>
            <strong>Analýza problému:</strong><br><br>

            <strong>Co se stalo:</strong><br>
            1. Zakázka <code>" . htmlspecialchars($zakazka['reklamace_id']) . "</code> byla vytvořena v databázi<br>
            2. Email <strong>" . htmlspecialchars($zakazka['email']) . "</strong> byl zadán do formuláře<br>
            3. Systém automaticky odeslal potvrzovací email na tento email<br>
            4. Zákazník B. Cikán (vlastník emailu) zakázku nepoznává<br><br>

            <strong>Možné příčiny:</strong><br>
            a) <strong>Překlep v emailu</strong> - Monika Jančová omylem zadala špatný email<br>
            b) <strong>Špatný zákazník</strong> - Data byla zkopírována z jiné zakázky<br>
            c) <strong>Test</strong> - Někdo testoval systém s reálným emailem<br><br>

            <strong>Doporučené kroky:</strong><br>
            1. <strong>Kontaktovat Moniku Jančovou</strong> - zjistit správný email zákazníka<br>
            2. <strong>Opravit email v zakázce</strong> - změnit na správný email<br>
            3. <strong>Omluvit se B. Cikánovi</strong> - vysvětlit že šlo o chybu při zadávání<br>
            4. <strong>Odeslat správný email</strong> - zákazníkovi se správným emailem<br>
        </div>";

        echo "<h3>SQL příkaz pro opravu emailu:</h3>";
        echo "<div class='code'>
UPDATE wgs_reklamace
SET email = 'SPRAVNY_EMAIL@example.com'
WHERE reklamace_id = '" . htmlspecialchars($zakazka['reklamace_id']) . "';
        </div>";

        echo "<h3>SQL příkaz pro zobrazení kontaktů na Moniku Jančovou:</h3>";
        if ($monika) {
            $monikaUser = $monika[0];
            echo "<div class='code'>
-- Kontaktní údaje na Moniku Jančovou:<br>
-- Jméno: " . htmlspecialchars($monikaUser['full_name']) . "<br>
-- Email: " . htmlspecialchars($monikaUser['email']) . "<br>
-- Role: " . htmlspecialchars($monikaUser['role']) . "<br>
            </div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><a href='/admin.php' class='btn'>← Zpět na Admin</a>";
echo "</div></body></html>";
?>
