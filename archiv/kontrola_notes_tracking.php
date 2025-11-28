<?php
/**
 * Diagnostika: Kontrola notes tracking systému
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: Notes Tracking</title>
    <style>
        body { font-family: monospace; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: white; padding: 15px; border: 1px solid #ddd; overflow-x: auto; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
    </style>
</head>
<body>";

echo "<h1>🔍 Diagnostika Notes Tracking Systému</h1>";

try {
    $pdo = getDbConnection();

    // 1. Kontrola existence tabulky wgs_notes_read
    echo "<h2>1. Kontrola tabulky wgs_notes_read</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_notes_read'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "<p class='ok'>✅ Tabulka wgs_notes_read EXISTUJE</p>";

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_notes_read");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        foreach ($columns as $col) {
            echo "{$col['Field']} | {$col['Type']} | {$col['Null']} | {$col['Key']}\n";
        }
        echo "</pre>";

        // Počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_notes_read");
        $count = $stmt->fetchColumn();
        echo "<p>Počet read záznamů: <strong>{$count}</strong></p>";

    } else {
        echo "<p class='error'>❌ Tabulka wgs_notes_read NEEXISTUJE!</p>";
        echo "<p class='warning'>⚠️ MUSÍŠ SPUSTIT MIGRACI:</p>";
        echo "<p><a href='pridej_notes_read_tracking.php' style='background: red; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>SPUSTIT MIGRACI</a></p>";
    }

    // 2. Kontrola tabulky wgs_notes
    echo "<h2>2. Kontrola tabulky wgs_notes</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_notes'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='ok'>✅ Tabulka wgs_notes existuje</p>";

        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_notes");
        $count = $stmt->fetchColumn();
        echo "<p>Celkový počet poznámek: <strong>{$count}</strong></p>";

        // Ukázka poznámek
        $stmt = $pdo->query("SELECT id, claim_id, note_text, created_by, created_at FROM wgs_notes ORDER BY created_at DESC LIMIT 5");
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Posledních 5 poznámek:</h3>";
        echo "<pre>";
        foreach ($notes as $note) {
            echo "ID: {$note['id']} | Claim: {$note['claim_id']} | Autor: {$note['created_by']}\n";
            echo "Text: " . substr($note['note_text'], 0, 100) . "...\n";
            echo "Čas: {$note['created_at']}\n";
            echo "---\n";
        }
        echo "</pre>";
    } else {
        echo "<p class='error'>❌ Tabulka wgs_notes NEEXISTUJE!</p>";
    }

    // 3. Test get_unread_counts endpoint
    echo "<h2>3. Test API: get_unread_counts</h2>";
    $currentUserEmail = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? null;
    echo "<p>Přihlášen jako: <strong>{$currentUserEmail}</strong></p>";

    if ($tableExists) {
        $stmt = $pdo->prepare("
            SELECT
                n.claim_id,
                COUNT(*) as unread_count
            FROM wgs_notes n
            LEFT JOIN wgs_notes_read nr ON n.id = nr.note_id AND nr.user_email = :user_email
            WHERE nr.id IS NULL
              AND n.created_by != :user_email_author
            GROUP BY n.claim_id
        ");
        $stmt->execute([
            ':user_email' => $currentUserEmail,
            ':user_email_author' => $currentUserEmail
        ]);
        $unreadCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        echo "<pre>";
        echo "Nepřečtené poznámky podle claim_id:\n";
        print_r($unreadCounts);
        echo "</pre>";

        $total = array_sum($unreadCounts);
        if ($total > 0) {
            echo "<p class='ok'>✅ Máte {$total} nepřečtených poznámek</p>";
        } else {
            echo "<p class='warning'>⚠️ Žádné nepřečtené poznámky (nebo všechny jsou vaše vlastní)</p>";
        }
    } else {
        echo "<p class='error'>❌ Nelze testovat - tabulka wgs_notes_read neexistuje</p>";
    }

    // 4. Test mark_read
    echo "<h2>4. Instrukce pro opravu</h2>";
    if (!$tableExists) {
        echo "<div style='background: #ffe0e0; padding: 20px; border: 2px solid red; border-radius: 10px;'>";
        echo "<h3 style='color: red;'>KRITICKÁ CHYBA: Chybí databázová tabulka!</h3>";
        echo "<ol>";
        echo "<li><strong>Klikni na:</strong> <a href='pridej_notes_read_tracking.php'>pridej_notes_read_tracking.php</a></li>";
        echo "<li><strong>Klikni na tlačítko:</strong> SPUSTIT MIGRACI</li>";
        echo "<li><strong>Počkej na zelenou zprávu:</strong> MIGRACE ÚSPĚŠNĚ DOKONČENA</li>";
        echo "<li><strong>Pak se vrať sem</strong> a spusť diagnostiku znovu</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='background: #e0ffe0; padding: 20px; border: 2px solid green; border-radius: 10px;'>";
        echo "<h3 style='color: green;'>✅ Systém je funkční!</h3>";
        echo "<p>Pokud stále nevidíš pulsování:</p>";
        echo "<ol>";
        echo "<li>Vyčisti cache prohlížeče (Ctrl+F5)</li>";
        echo "<li>Zkontroluj konzoli prohlížeče (F12)</li>";
        echo "<li>Zkus vytvořit novou poznámku jako jiný uživatel</li>";
        echo "</ol>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<p class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><a href='admin.php'>← Zpět na Admin</a>";
echo "</body></html>";
?>
