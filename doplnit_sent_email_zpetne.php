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

    // Kontrola existence tabulek
    $maAuditLog = false;
    $maEmailQueue = false;

    try {
        $pdo->query("SELECT 1 FROM wgs_audit_log LIMIT 1");
        $maAuditLog = true;
    } catch (PDOException $e) {}

    try {
        $pdo->query("SELECT 1 FROM wgs_email_queue LIMIT 1");
        $maEmailQueue = true;
    } catch (PDOException $e) {}

    echo "<div class='info'>";
    echo "<strong>Dostupne zdroje dat:</strong><br>";
    echo "- wgs_email_queue: " . ($maEmailQueue ? "ANO" : "NE") . "<br>";
    echo "- wgs_audit_log: " . ($maAuditLog ? "ANO" : "NE");
    echo "</div>";

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

    // 2. Pro kazdy klic hledat v dostupnych zdrojich
    $nalezeno = [];
    $nenalezeno = [];

    foreach ($kliceBezEmailu as $klic) {
        $keyCode = $klic['key_code'];
        $nalezenEmail = null;
        $nalezenCas = null;
        $zdroj = null;

        // 2a. Hledat v email_queue
        if ($maEmailQueue && !$nalezenEmail) {
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
                $unikatniEmaily = array_unique(array_column($emaily, 'recipient_email'));
                $nalezenEmail = implode(', ', $unikatniEmaily);
                $nalezenCas = $emaily[0]['sent_at'] ?? $emaily[0]['created_at'];
                $zdroj = 'email_queue';
            }
        }

        // 2b. Hledat v audit_log
        if ($maAuditLog && !$nalezenEmail) {
            $stmt = $pdo->prepare("
                SELECT details, created_at
                FROM wgs_audit_log
                WHERE (details LIKE :klic1 OR action LIKE :klic2)
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([
                ':klic1' => '%' . $keyCode . '%',
                ':klic2' => '%invitation%'
            ]);
            $auditZaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($auditZaznamy as $zaznam) {
                // Zkusit najit email v details (JSON nebo text)
                $details = $zaznam['details'];
                if (strpos($details, $keyCode) !== false) {
                    // Zkusit extrahovat email z JSON
                    $decoded = json_decode($details, true);
                    if ($decoded && isset($decoded['email'])) {
                        $nalezenEmail = $decoded['email'];
                        $nalezenCas = $zaznam['created_at'];
                        $zdroj = 'audit_log';
                        break;
                    }
                    // Zkusit regex pro email
                    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $details, $matches)) {
                        $nalezenEmail = $matches[0];
                        $nalezenCas = $zaznam['created_at'];
                        $zdroj = 'audit_log';
                        break;
                    }
                }
            }
        }

        if ($nalezenEmail) {
            $nalezeno[$keyCode] = [
                'emaily' => $nalezenEmail,
                'sent_at' => $nalezenCas,
                'key_type' => $klic['key_type'],
                'zdroj' => $zdroj
            ];
        } else {
            $nenalezeno[] = $klic;
        }
    }

    // 3. Zobrazit vysledky
    echo "<h2>Nalezene emaily v historii: " . count($nalezeno) . "</h2>";

    if (count($nalezeno) > 0) {
        echo "<table>";
        echo "<tr><th>Typ</th><th>Klic</th><th>Nalezeny email</th><th>Odeslano</th><th>Zdroj</th></tr>";
        foreach ($nalezeno as $keyCode => $data) {
            echo "<tr class='found'>";
            echo "<td>" . htmlspecialchars(strtoupper($data['key_type'])) . "</td>";
            echo "<td><code>" . htmlspecialchars($keyCode) . "</code></td>";
            echo "<td>" . htmlspecialchars($data['emaily']) . "</td>";
            echo "<td>" . htmlspecialchars($data['sent_at'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($data['zdroj'] ?? '-') . "</td>";
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
