<?php
/**
 * Opravit chybejici klice v wgs_registration_keys
 * Klice ktere jsou v wgs_users ale chybi v wgs_registration_keys
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
    <title>Oprava chybejicich klicu</title>
    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #000; border-bottom: 2px solid #000; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        .found { background: #d4edda; }
        .btn { display: inline-block; padding: 10px 20px; background: #000; color: white;
               text-decoration: none; border-radius: 5px; margin: 5px; border: none; cursor: pointer; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Oprava chybejicich klicu</h1>";

try {
    $pdo = getDbConnection();

    // Najit klice v wgs_users ktere NEJSOU v wgs_registration_keys
    $stmt = $pdo->query("
        SELECT
            u.registration_key_code,
            u.registration_key_type,
            u.email,
            u.name,
            u.created_at as registrace_datum
        FROM wgs_users u
        WHERE u.registration_key_code IS NOT NULL
        AND u.registration_key_code != ''
        AND u.registration_key_code NOT IN (
            SELECT key_code FROM wgs_registration_keys
        )
        ORDER BY u.created_at DESC
    ");
    $chybejiciKlice = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Klice chybejici v tabulce registration_keys: " . count($chybejiciKlice) . "</h2>";

    if (count($chybejiciKlice) === 0) {
        echo "<div class='success'>";
        echo "<strong>Vse v poradku!</strong><br>";
        echo "Vsechny klice z registraci uzivatelu jsou v tabulce wgs_registration_keys.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>Nalezeny klice ktere chybi!</strong><br>";
        echo "Tyto klice byly pouzity k registraci, ale nejsou v tabulce wgs_registration_keys.";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>Typ</th><th>Klic</th><th>Uzivatel</th><th>Email</th><th>Registrace</th></tr>";
        foreach ($chybejiciKlice as $k) {
            echo "<tr class='found'>";
            echo "<td>" . htmlspecialchars(strtoupper($k['registration_key_type'] ?? 'neznamy')) . "</td>";
            echo "<td><code>" . htmlspecialchars($k['registration_key_code']) . "</code></td>";
            echo "<td>" . htmlspecialchars($k['name']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($k['email']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($k['registrace_datum']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Tlacitko pro opravu
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>PRIDAVAM CHYBEJICI KLICE...</strong></div>";

            $pridano = 0;
            foreach ($chybejiciKlice as $k) {
                $typ = $k['registration_key_type'] ?? 'unknown';

                $stmt = $pdo->prepare("
                    INSERT INTO wgs_registration_keys
                    (key_code, key_type, is_active, usage_count, created_at, sent_to_email, sent_at)
                    VALUES (:key_code, :key_type, 1, 1, :created_at, :email, :sent_at)
                ");
                $stmt->execute([
                    ':key_code' => $k['registration_key_code'],
                    ':key_type' => $typ,
                    ':created_at' => $k['registrace_datum'],
                    ':email' => $k['email'],
                    ':sent_at' => $k['registrace_datum']
                ]);
                $pridano++;
            }

            echo "<div class='success'>";
            echo "<strong>HOTOVO!</strong><br>";
            echo "Pridano klicu: <strong>{$pridano}</strong>";
            echo "</div>";
        } else {
            echo "<a href='?execute=1' class='btn'>PRIDAT CHYBEJICI KLICE</a>";
        }
    }

} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:15px;'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php?tab=keys' class='btn'>Zpet do admin panelu</a>";
echo "</div></body></html>";
?>
