<?php
/**
 * Debug pagination - zjistit proč se nezobrazují další stránky
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Debug Pagination</title>
    <style>
        body { font-family: monospace; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        pre { background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; }
        .section { margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ddd; }
        h2 { color: #333; border-bottom: 2px solid #333; padding-bottom: 5px; }
        .success { color: #155724; background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: #fff; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Debug Email Queue Pagination</h1>";

    // URL parametry
    echo "<div class='section'>";
    echo "<h2>URL Parametry</h2>";
    echo "<pre>";
    echo "tab: " . ($_GET['tab'] ?? 'NENÍ NASTAVENO') . "\n";
    echo "stranka: " . ($_GET['stranka'] ?? 'NENÍ NASTAVENO') . "\n";
    echo "razeni: " . ($_GET['razeni'] ?? 'NENÍ NASTAVENO') . "\n";
    echo "filter: " . ($_GET['filter'] ?? 'NENÍ NASTAVENO') . "\n";
    echo "\nCelá URL: " . $_SERVER['REQUEST_URI'] . "\n";
    echo "</pre>";
    echo "</div>";

    // Načíst parametry stejně jako admin_email_sms.php
    $filterStatus = $_GET['filter'] ?? 'all';
    $strankaAktualni = isset($_GET['stranka']) ? max(1, (int)$_GET['stranka']) : 1;
    $razeni = $_GET['razeni'] ?? 'DESC';
    $naStrankuPolozkek = 50;

    echo "<div class='section'>";
    echo "<h2>Zpracované parametry</h2>";
    echo "<pre>";
    echo "filterStatus: $filterStatus\n";
    echo "strankaAktualni: $strankaAktualni\n";
    echo "razeni: $razeni\n";
    echo "naStrankuPolozkek: $naStrankuPolozkek\n";
    echo "</pre>";
    echo "</div>";

    // Celkový počet emailů
    $whereClause = '';
    $params = [];
    if ($filterStatus !== 'all') {
        $whereClause = "WHERE eq.status = :status";
        $params['status'] = $filterStatus;
    }

    $sqlCount = "
        SELECT COUNT(*) as total
        FROM wgs_email_queue eq
        $whereClause
    ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $celkemEmailu = (int)$stmtCount->fetchColumn();
    $celkemStranek = max(1, ceil($celkemEmailu / $naStrankuPolozkek));

    echo "<div class='section'>";
    echo "<h2>Celkové statistiky</h2>";
    echo "<pre>";
    echo "Celkem emailů v databázi: $celkemEmailu\n";
    echo "Emailů na stránku: $naStrankuPolozkek\n";
    echo "Celkem stránek: $celkemStranek\n";
    echo "</pre>";
    echo "</div>";

    // Vypočítat OFFSET
    $offset = ($strankaAktualni - 1) * $naStrankuPolozkek;

    echo "<div class='section'>";
    echo "<h2>SQL dotaz</h2>";
    echo "<pre>";
    echo "OFFSET: $offset\n";
    echo "LIMIT: $naStrankuPolozkek\n";
    echo "</pre>";
    echo "</div>";

    // Načíst data
    $orderDirection = ($razeni === 'ASC') ? 'ASC' : 'DESC';
    $sql = "
        SELECT
            eq.id, eq.recipient_email, eq.subject,
            eq.status, eq.created_at, eq.sent_at,
            eq.notification_id,
            COALESCE(n.name, eq.notification_id) as template_name
        FROM wgs_email_queue eq
        LEFT JOIN wgs_notifications n ON eq.notification_id = n.id
        $whereClause
        ORDER BY eq.created_at $orderDirection
        LIMIT :limit OFFSET :offset
    ";

    echo "<div class='section'>";
    echo "<h2>Kompletní SQL</h2>";
    echo "<pre>";
    echo htmlspecialchars($sql);
    echo "\n\nParameters:\n";
    foreach ($params as $key => $value) {
        echo "  :$key = " . var_export($value, true) . "\n";
    }
    echo "  :limit = $naStrankuPolozkek\n";
    echo "  :offset = $offset\n";
    echo "</pre>";
    echo "</div>";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $naStrankuPolozkek, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $emaily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='section'>";
    echo "<h2>Výsledky dotazu</h2>";

    if (empty($emaily)) {
        echo "<div class='error'>❌ ŽÁDNÉ EMAILY NENALEZENY!</div>";
    } else {
        echo "<div class='success'>✅ Nalezeno " . count($emaily) . " emailů</div>";

        echo "<table>";
        echo "<tr><th>ID</th><th>Příjemce</th><th>Předmět</th><th>Status</th><th>Vytvořeno</th></tr>";

        foreach ($emaily as $email) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($email['id']) . "</td>";
            echo "<td>" . htmlspecialchars($email['recipient_email']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($email['subject'], 0, 40)) . "...</td>";
            echo "<td>" . htmlspecialchars($email['status']) . "</td>";
            echo "<td>" . htmlspecialchars($email['created_at']) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }
    echo "</div>";

    // Test odkazy
    echo "<div class='section'>";
    echo "<h2>Test odkazy pro pagination</h2>";
    echo "<p><a href='?stranka=1&razeni=DESC&filter=all'>Stránka 1</a></p>";
    echo "<p><a href='?stranka=2&razeni=DESC&filter=all'>Stránka 2</a></p>";
    echo "<p><a href='?stranka=3&razeni=DESC&filter=all'>Stránka 3</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "<br><br><strong>Stack trace:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
?>
