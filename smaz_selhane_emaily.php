<?php
/**
 * Smazání selhaných emailů z fronty
 *
 * Tento skript BEZPEČNĚ smaže všechny záznamy se statusem 'failed' z wgs_email_queue.
 * Můžete jej spustit vícekrát - pokud už nejsou žádné selhané emaily, nic se nestane.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může mazat selhané emaily.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Smazání selhaných emailů</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 16px;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 16px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 16px;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 16px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 5px 15px 0;
            font-size: 16px;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #666;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .email-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .email-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-family: monospace;
            font-size: 14px;
        }
        .email-item:last-child {
            border-bottom: none;
        }
        .stat-box {
            display: inline-block;
            padding: 20px 30px;
            background: #333;
            color: white;
            border-radius: 8px;
            margin: 10px;
            font-size: 24px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.8;
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // Kontrola před smazáním
    echo "<h1>Smazání selhaných emailů</h1>";

    // Spočítat selhané emaily
    $countStmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_email_queue
        WHERE status = 'failed'
    ");
    $failedCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo "<div class='stat-box'>";
    echo "<span class='stat-label'>SELHANÉ EMAILY</span>";
    echo $failedCount;
    echo "</div>";

    if ($failedCount == 0) {
        echo "<div class='info'>";
        echo "<strong>OK Žádné selhané emaily</strong><br>";
        echo "Ve frontě nejsou žádné selhané emaily k smazání.";
        echo "</div>";

        echo "<a href='admin.php?tab=notifications&section=management' class='btn btn-secondary'>← Zpět na Email Management</a>";

    } else {

        // Načíst seznam selhaných emailů
        $emailsStmt = $pdo->query("
            SELECT id, recipient_email, recipient_name, subject, created_at, error_message
            FROM wgs_email_queue
            WHERE status = 'failed'
            ORDER BY created_at DESC
        ");
        $failedEmails = $emailsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Pokud je nastaveno ?execute=1, provést smazání
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {

            echo "<div class='info'><strong>⏳ SPOUŠTÍM MAZÁNÍ...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Smazat všechny selhané emaily
                $deleteStmt = $pdo->prepare("
                    DELETE FROM wgs_email_queue
                    WHERE status = 'failed'
                ");
                $deleteStmt->execute();

                $deletedCount = $deleteStmt->rowCount();

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>OK SMAZÁNÍ DOKONČENO</strong><br>";
                echo "Smazáno <strong>{$deletedCount}</strong> selhaných emailů z fronty.";
                echo "</div>";

                echo "<a href='admin.php?tab=notifications&section=management' class='btn btn-secondary'>← Zpět na Email Management</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA PŘI MAZÁNÍ:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";

                echo "<a href='smaz_selhane_emaily.php' class='btn btn-secondary'>← Zkusit znovu</a>";
            }

        } else {
            // Náhled - zobrazit seznam emailů které budou smazány

            echo "<div class='warning'>";
            echo "<strong>POZOR: VAROVÁNÍ</strong><br>";
            echo "Chystáte se TRVALE smazat <strong>{$failedCount} selhaných emailů</strong> z fronty.<br>";
            echo "Tato akce je <strong>NEVRATNÁ</strong>!";
            echo "</div>";

            echo "<h2>Seznam emailů k smazání:</h2>";

            echo "<div class='email-list'>";
            foreach ($failedEmails as $email) {
                echo "<div class='email-item'>";
                echo "<strong>ID {$email['id']}</strong> | ";
                echo htmlspecialchars($email['recipient_email']);
                if ($email['recipient_name']) {
                    echo " (" . htmlspecialchars($email['recipient_name']) . ")";
                }
                echo "<br>";
                echo "<small style='color: #666;'>";
                echo "Předmět: " . htmlspecialchars(mb_substr($email['subject'], 0, 60)) . "...";
                echo " | Vytvořeno: " . $email['created_at'];
                echo "</small>";
                if ($email['error_message']) {
                    echo "<br><small style='color: #999;'>Chyba: " . htmlspecialchars(mb_substr($email['error_message'], 0, 100)) . "...</small>";
                }
                echo "</div>";
            }
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>CO SE STANE:</strong><br>";
            echo "• Všech {$failedCount} záznamů se statusem 'failed' bude TRVALE smazáno z databáze<br>";
            echo "• Tato akce je NEVRATNÁ - záznamy nelze obnovit<br>";
            echo "• Statistiky v Email Management se aktualizují<br>";
            echo "• Pokud chcete emaily znovu odeslat, nemazejte je - použijte tlačítko 'ZNOVU ODESLAT'";
            echo "</div>";

            echo "<a href='?execute=1' class='btn' onclick='return confirm(\"OPRAVDU chcete smazat všech {$failedCount} selhaných emailů? Tato akce je NEVRATNÁ!\");'>ANO, SMAZAT VŠECHNY SELHANÉ EMAILY</a>";
            echo "<a href='admin.php?tab=notifications&section=management' class='btn btn-secondary'>← Zrušit a vrátit se zpět</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
