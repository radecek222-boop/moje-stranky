<?php
/**
 * Zpetne doplneni sent_to_email pro registracni klice
 *
 * Tento skript prohledava email_queue a doplni informace
 * o tom, na jake emaily byly klice odeslany.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Zpetne doplneni emailu ke klicum</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #000; border-bottom: 3px solid #000; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #000; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #333; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f5f5f5; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
        .found { background: #d4edda; }
        .not-found { background: #fff3cd; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Zpetne doplneni emailu ke klicum</h1>";

    // 1. Nacist vsechny klice bez sent_to_email
    $stmt = $pdo->query("
        SELECT key_code, key_type, created_at
        FROM wgs_registration_keys
        WHERE sent_to_email IS NULL
        ORDER BY created_at DESC
    ");
    $kliceBezEmailu = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Klice bez informace o prijemci: " . count($kliceBezEmailu) . "</h2>";

    if (count($kliceBezEmailu) === 0) {
        echo "<div class='success'>Vsechny klice maji vyplneny email prijemce!</div>";
        echo "<a href='admin.php?tab=keys' class='btn'>Zpet do admin panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    // 2. Pro kazdy klic hledat v email_queue
    $nalezeno = [];
    $nenalezeno = [];

    foreach ($kliceBezEmailu as $klic) {
        $keyCode = $klic['key_code'];

        // Hledat v email_queue - klic muze byt v subject nebo body
        $stmt = $pdo->prepare("
            SELECT recipient_email, sent_at, created_at
            FROM wgs_email_queue
            WHERE (body LIKE :klic1 OR subject LIKE :klic2)
            AND status = 'sent'
            ORDER BY sent_at DESC
            LIMIT 10
        ");
        $stmt->execute([
            ':klic1' => '%' . $keyCode . '%',
            ':klic2' => '%' . $keyCode . '%'
        ]);
        $emaily = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($emaily) > 0) {
            // Spojit vsechny unikatni emaily
            $unikatniEmaily = array_unique(array_column($emaily, 'recipient_email'));
            $nalezeno[$keyCode] = [
                'emaily' => implode(', ', $unikatniEmaily),
                'sent_at' => $emaily[0]['sent_at'] ?? $emaily[0]['created_at'],
                'key_type' => $klic['key_type']
            ];
        } else {
            $nenalezeno[] = $klic;
        }
    }

    // 3. Zobrazit vysledky
    echo "<h2>Nalezene emaily v historii: " . count($nalezeno) . "</h2>";

    if (count($nalezeno) > 0) {
        echo "<table>";
        echo "<tr><th>Typ</th><th>Klic</th><th>Nalezeny email</th><th>Odeslano</th></tr>";
        foreach ($nalezeno as $keyCode => $data) {
            echo "<tr class='found'>";
            echo "<td>" . htmlspecialchars(strtoupper($data['key_type'])) . "</td>";
            echo "<td><code>" . htmlspecialchars($keyCode) . "</code></td>";
            echo "<td>" . htmlspecialchars($data['emaily']) . "</td>";
            echo "<td>" . htmlspecialchars($data['sent_at'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // 4. Tlacitko pro aktualizaci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>AKTUALIZUJI DATABAZI...</strong></div>";

            $aktualizovano = 0;
            foreach ($nalezeno as $keyCode => $data) {
                $stmt = $pdo->prepare("
                    UPDATE wgs_registration_keys
                    SET sent_to_email = :email, sent_at = :sent_at
                    WHERE key_code = :key_code
                ");
                $stmt->execute([
                    ':email' => $data['emaily'],
                    ':sent_at' => $data['sent_at'],
                    ':key_code' => $keyCode
                ]);
                if ($stmt->rowCount() > 0) {
                    $aktualizovano++;
                }
            }

            echo "<div class='success'>";
            echo "<strong>HOTOVO!</strong><br>";
            echo "Aktualizovano klicu: <strong>" . $aktualizovano . "</strong>";
            echo "</div>";
        } else {
            echo "<a href='?execute=1' class='btn'>DOPLNIT EMAILY DO DATABAZE</a>";
        }
    }

    // 5. Zobrazit klice bez nalezenych emailu
    if (count($nenalezeno) > 0) {
        echo "<h2>Klice bez nalezeneho emailu v historii: " . count($nenalezeno) . "</h2>";
        echo "<div class='info'>Tyto klice nebyly nalezeny v historii odeslaných emailu. Mohly byt vytvoreny rucne (tlacitkem '+ Novy') bez odeslani pozvanky.</div>";
        echo "<table>";
        echo "<tr><th>Typ</th><th>Klic</th><th>Vytvoren</th></tr>";
        foreach ($nenalezeno as $klic) {
            echo "<tr class='not-found'>";
            echo "<td>" . htmlspecialchars(strtoupper($klic['key_type'])) . "</td>";
            echo "<td><code>" . htmlspecialchars($klic['key_code']) . "</code></td>";
            echo "<td>" . htmlspecialchars($klic['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<br><a href='admin.php?tab=keys' class='btn' style='background:#666;'>Zpet do admin panelu</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
