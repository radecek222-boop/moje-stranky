<?php
/**
 * Migrace: Přidání role-based příjemců pro šablony notifikací
 *
 * Přidává sloupce:
 * - to_recipients JSON (pole rolí pro TO: customer, technician, seller, admin)
 * - cc_recipients JSON (pole rolí pro CC)
 * - bcc_recipients JSON (pole rolí pro BCC)
 *
 * Původní recipient_type se zachová pro zpětnou kompatibilitu
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
    <title>Migrace: Role-based příjemci notifikací</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        code { background: #e9e9e9; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Role-based příjemci notifikací</h1>";

    // Kontrola existujících sloupců
    $stmt = $pdo->query("DESCRIBE wgs_notifications");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $hasToRecipients = in_array('to_recipients', $columns);
    $hasCcRecipients = in_array('cc_recipients', $columns);
    $hasBccRecipients = in_array('bcc_recipients', $columns);

    echo "<h2>Aktuální stav</h2>";
    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Stav</th></tr>";
    echo "<tr><td><code>to_recipients</code></td><td>" . ($hasToRecipients ? "Existuje" : "Chybí") . "</td></tr>";
    echo "<tr><td><code>cc_recipients</code></td><td>" . ($hasCcRecipients ? "Existuje" : "Chybí") . "</td></tr>";
    echo "<tr><td><code>bcc_recipients</code></td><td>" . ($hasBccRecipients ? "Existuje" : "Chybí") . "</td></tr>";
    echo "</table>";

    // Spuštění migrace
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // 1. Přidat sloupec to_recipients
            if (!$hasToRecipients) {
                $pdo->exec("ALTER TABLE wgs_notifications ADD COLUMN to_recipients JSON DEFAULT NULL AFTER recipient_type");
                echo "<div class='success'>Přidán sloupec <code>to_recipients</code></div>";
            }

            // 2. Přidat sloupec cc_recipients
            if (!$hasCcRecipients) {
                $pdo->exec("ALTER TABLE wgs_notifications ADD COLUMN cc_recipients JSON DEFAULT NULL AFTER to_recipients");
                echo "<div class='success'>Přidán sloupec <code>cc_recipients</code></div>";
            }

            // 3. Přidat sloupec bcc_recipients
            if (!$hasBccRecipients) {
                $pdo->exec("ALTER TABLE wgs_notifications ADD COLUMN bcc_recipients JSON DEFAULT NULL AFTER cc_recipients");
                echo "<div class='success'>Přidán sloupec <code>bcc_recipients</code></div>";
            }

            // 4. Migrace dat z recipient_type do to_recipients
            $stmt = $pdo->query("SELECT id, recipient_type FROM wgs_notifications WHERE to_recipients IS NULL");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $migratedCount = 0;
            foreach ($templates as $template) {
                $toRecipients = json_encode([$template['recipient_type']]);
                $updateStmt = $pdo->prepare("UPDATE wgs_notifications SET to_recipients = :to_recipients WHERE id = :id");
                $updateStmt->execute([
                    ':to_recipients' => $toRecipients,
                    ':id' => $template['id']
                ]);
                $migratedCount++;
            }

            if ($migratedCount > 0) {
                echo "<div class='success'>Migrace dat: {$migratedCount} šablon aktualizováno</div>";
            }

            $pdo->commit();

            echo "<div class='success' style='font-size: 1.2em; font-weight: bold;'>";
            echo "MIGRACE ÚSPĚŠNĚ DOKONČENA";
            echo "</div>";

            echo "<h2>Nová struktura příjemců</h2>";
            echo "<div class='info'>";
            echo "<p>Každá šablona nyní podporuje:</p>";
            echo "<ul>";
            echo "<li><strong>TO (Komu):</strong> Výběr rolí - zákazník, technik, prodejce, admin</li>";
            echo "<li><strong>CC (Kopie):</strong> Výběr rolí + konkrétní emaily</li>";
            echo "<li><strong>BCC (Skrytá kopie):</strong> Výběr rolí + konkrétní emaily</li>";
            echo "</ul>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Náhled
        echo "<h2>Co bude provedeno</h2>";
        echo "<div class='info'>";
        echo "<ol>";
        if (!$hasToRecipients) echo "<li>Přidat sloupec <code>to_recipients</code> (JSON pole rolí pro TO)</li>";
        if (!$hasCcRecipients) echo "<li>Přidat sloupec <code>cc_recipients</code> (JSON pole rolí pro CC)</li>";
        if (!$hasBccRecipients) echo "<li>Přidat sloupec <code>bcc_recipients</code> (JSON pole rolí pro BCC)</li>";
        echo "<li>Migrovat data z <code>recipient_type</code> do <code>to_recipients</code></li>";
        echo "</ol>";
        echo "</div>";

        if ($hasToRecipients && $hasCcRecipients && $hasBccRecipients) {
            echo "<div class='warning'>Všechny sloupce již existují. Migrace není potřeba.</div>";
        } else {
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

    // Zobrazit aktuální šablony
    echo "<h2>Přehled šablon</h2>";
    $stmt = $pdo->query("SELECT id, name, recipient_type, to_recipients, cc_recipients, bcc_recipients, active FROM wgs_notifications ORDER BY trigger_event, type");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>ID</th><th>Název</th><th>TO (role)</th><th>CC (role)</th><th>BCC (role)</th><th>Aktivní</th></tr>";
    foreach ($templates as $t) {
        $toRoles = $t['to_recipients'] ? implode(', ', json_decode($t['to_recipients'], true) ?: []) : $t['recipient_type'];
        $ccRoles = $t['cc_recipients'] ? implode(', ', json_decode($t['cc_recipients'], true) ?: []) : '-';
        $bccRoles = $t['bcc_recipients'] ? implode(', ', json_decode($t['bcc_recipients'], true) ?: []) : '-';
        $active = $t['active'] ? 'Ano' : 'Ne';
        echo "<tr>";
        echo "<td><code>{$t['id']}</code></td>";
        echo "<td>{$t['name']}</td>";
        echo "<td>{$toRoles}</td>";
        echo "<td>{$ccRoles}</td>";
        echo "<td>{$bccRoles}</td>";
        echo "<td>{$active}</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<p><a href='admin.php?tab=email' class='btn'>Zpět na Admin</a></p>";
echo "</div></body></html>";
?>
