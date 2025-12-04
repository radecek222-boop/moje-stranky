<?php
/**
 * Migrace: Nastavení automatického CC pro prodejce
 *
 * Tento skript přidá {{seller_email}} do CC pole pro všechny šablony,
 * kde je příjemce zákazník (recipient_type = 'customer').
 *
 * Můžete jej spustit vícekrát - nepřepíše existující CC, pouze přidá prodejce.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Nastavení prodejce v CC</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #000; border-bottom: 3px solid #000;
             padding-bottom: 10px; }
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
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #000; color: #fff; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #000; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #333; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Nastavení prodejce v CC pro zákazníky</h1>";

    // 1. Najít všechny šablony pro zákazníky
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $stmt = $pdo->query("
        SELECT id, name, recipient_type, cc_emails, bcc_emails
        FROM wgs_notifications
        WHERE recipient_type = 'customer'
        ORDER BY id ASC
    ");
    $sablony = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sablony)) {
        echo "<div class='warning'>Žádné šablony pro zákazníky nenalezeny.</div>";
        echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>";
    echo "<strong>Nalezeno šablon pro zákazníky:</strong> " . count($sablony);
    echo "</div>";

    // Zobrazit náhled
    echo "<h2>Náhled změn</h2>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Název šablony</th><th>Současné CC</th><th>Nové CC</th></tr>";

    $budouZmeneny = [];
    foreach ($sablony as $sablona) {
        $ccEmails = !empty($sablona['cc_emails']) ? json_decode($sablona['cc_emails'], true) : [];

        // Kontrola, zda už obsahuje {{seller_email}}
        $maSellerEmail = in_array('{{seller_email}}', $ccEmails);

        if (!$maSellerEmail) {
            // Přidat {{seller_email}} na začátek pole
            $noveCcEmails = array_merge(['{{seller_email}}'], $ccEmails);
            $budouZmeneny[] = [
                'id' => $sablona['id'],
                'name' => $sablona['name'],
                'stare_cc' => $ccEmails,
                'nove_cc' => $noveCcEmails
            ];

            echo "<tr>";
            echo "<td>{$sablona['id']}</td>";
            echo "<td>{$sablona['name']}</td>";
            echo "<td>" . (empty($ccEmails) ? '<em>prázdné</em>' : implode(', ', $ccEmails)) . "</td>";
            echo "<td><strong>{{seller_email}}</strong>" . (!empty($ccEmails) ? ', ' . implode(', ', $ccEmails) : '') . "</td>";
            echo "</tr>";
        } else {
            echo "<tr style='background: #f0f0f0;'>";
            echo "<td>{$sablona['id']}</td>";
            echo "<td>{$sablona['name']}</td>";
            echo "<td colspan='2'>✓ Už má {{seller_email}} v CC - beze změny</td>";
            echo "</tr>";
        }
    }
    echo "</table>";

    if (empty($budouZmeneny)) {
        echo "<div class='success'>";
        echo "<strong>✓ Všechny šablony už mají {{seller_email}} v CC</strong><br>";
        echo "Žádné změny nejsou potřeba.";
        echo "</div>";
        echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='warning'>";
    echo "<strong>Změn celkem:</strong> " . count($budouZmeneny) . " šablon";
    echo "</div>";

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            $uspech = 0;
            $chyby = 0;

            foreach ($budouZmeneny as $zmena) {
                $noveCcJson = json_encode($zmena['nove_cc'], JSON_UNESCAPED_UNICODE);

                $stmt = $pdo->prepare("
                    UPDATE wgs_notifications
                    SET cc_emails = :cc_emails,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $result = $stmt->execute([
                    ':cc_emails' => $noveCcJson,
                    ':id' => $zmena['id']
                ]);

                if ($result) {
                    $uspech++;
                } else {
                    $chyby++;
                }
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✓ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Upraveno šablon: {$uspech}<br>";
            if ($chyby > 0) {
                echo "Chyb: {$chyby}<br>";
            }
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>Co se stalo:</strong><br>";
            echo "• Všechny email šablony pro zákazníky nyní mají <code>{{seller_email}}</code> v CC<br>";
            echo "• Prodejce (vytvořil zakázku) bude automaticky dostávat kopie emailů<br>";
            echo "• Změny můžete kdykoli upravit v Admin Panelu → Email & SMS → Email šablony";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Náhled - zobrazit tlačítko pro spuštění
        echo "<h2>Spuštění migrace</h2>";
        echo "<div class='warning'>";
        echo "<strong>⚠️ PŘED SPUŠTĚNÍM:</strong><br>";
        echo "• Zkontrolujte náhled změn výše<br>";
        echo "• Tato akce je reverzibilní - můžete emaily odstranit v Admin Panelu<br>";
        echo "• Migraci lze spustit vícekrát bez problémů";
        echo "</div>";
        echo "<a href='?execute=1' class='btn'>▶ SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<a href='admin.php' class='btn' style='background: #666;'>← Zpět do Admin Panelu</a>";

echo "</div></body></html>";
?>
