<?php
/**
 * Doplnit emaily ke klicum z tabulky wgs_users
 * Pokud nekdo pouzil klic k registraci, jeho email je v users
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Doplneni emailu z registraci</title>
    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #000; border-bottom: 2px solid #000; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        .found { background: #d4edda; }
        .not-found { background: #fff3cd; }
        .btn { display: inline-block; padding: 10px 20px; background: #000; color: white;
               text-decoration: none; border-radius: 5px; margin: 5px; border: none; cursor: pointer; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Doplneni emailu z registraci uzivatelu</h1>";

try {
    $pdo = getDbConnection();

    // Najit klice ktere byly pouzity k registraci
    $stmt = $pdo->query("
        SELECT
            k.key_code,
            k.key_type,
            k.usage_count,
            k.sent_to_email,
            k.created_at as klic_vytvoren,
            u.email as uzivatel_email,
            u.name as uzivatel_jmeno,
            u.created_at as registrace_datum
        FROM wgs_registration_keys k
        LEFT JOIN wgs_users u ON u.registration_key_code = k.key_code
        WHERE k.sent_to_email IS NULL
        ORDER BY k.created_at DESC
    ");
    $klice = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $kProDoplneni = [];
    $bezRegistrace = [];

    foreach ($klice as $klic) {
        if ($klic['uzivatel_email']) {
            $kProDoplneni[] = $klic;
        } else {
            $bezRegistrace[] = $klic;
        }
    }

    echo "<h2>Klice s nalezenou registraci: " . count($kProDoplneni) . "</h2>";

    if (count($kProDoplneni) > 0) {
        echo "<table>";
        echo "<tr><th>Typ</th><th>Klic</th><th>Pouzit</th><th>Uzivatel</th><th>Email</th><th>Registrace</th></tr>";
        foreach ($kProDoplneni as $k) {
            echo "<tr class='found'>";
            echo "<td>" . htmlspecialchars(strtoupper($k['key_type'])) . "</td>";
            echo "<td><code>" . htmlspecialchars($k['key_code']) . "</code></td>";
            echo "<td>" . $k['usage_count'] . "x</td>";
            echo "<td>" . htmlspecialchars($k['uzivatel_jmeno']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($k['uzivatel_email']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($k['registrace_datum']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Tlacitko pro doplneni
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>AKTUALIZUJI...</strong></div>";

            $aktualizovano = 0;
            foreach ($kProDoplneni as $k) {
                $stmt = $pdo->prepare("
                    UPDATE wgs_registration_keys
                    SET sent_to_email = :email,
                        sent_at = :datum
                    WHERE key_code = :key_code
                    AND sent_to_email IS NULL
                ");
                $stmt->execute([
                    ':email' => $k['uzivatel_email'],
                    ':datum' => $k['registrace_datum'],
                    ':key_code' => $k['key_code']
                ]);
                if ($stmt->rowCount() > 0) {
                    $aktualizovano++;
                }
            }

            echo "<div class='success'>";
            echo "<strong>HOTOVO!</strong><br>";
            echo "Aktualizovano klicu: <strong>{$aktualizovano}</strong>";
            echo "</div>";
        } else {
            echo "<a href='?execute=1' class='btn'>DOPLNIT EMAILY Z REGISTRACI</a>";
        }
    }

    echo "<h2>Klice BEZ registrace (nikdo je nepouzil): " . count($bezRegistrace) . "</h2>";

    if (count($bezRegistrace) > 0) {
        echo "<div class='info'>";
        echo "<strong>Tyto klice nebyly nikym pouzity k registraci.</strong><br>";
        echo "Bud nebyly odeslany, nebo prijemci je jeste nepouzili.";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>Typ</th><th>Klic</th><th>Vytvoren</th><th>Pouziti</th></tr>";
        foreach ($bezRegistrace as $k) {
            echo "<tr class='not-found'>";
            echo "<td>" . htmlspecialchars(strtoupper($k['key_type'])) . "</td>";
            echo "<td><code>" . htmlspecialchars($k['key_code']) . "</code></td>";
            echo "<td>" . htmlspecialchars($k['klic_vytvoren']) . "</td>";
            echo "<td>" . $k['usage_count'] . "x</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:15px;'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php?tab=keys' class='btn'>Zpet do admin panelu</a>";
echo "</div></body></html>";
?>
