<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struktura databáze wgs-servicecz01</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        table { background: #2d2d2d; border-collapse: collapse; width: 100%; margin: 20px 0; }
        th { background: #3c3c3c; color: #4ec9b0; padding: 10px; text-align: left; border: 1px solid #555; }
        td { padding: 8px; border: 1px solid #555; }
        .key { color: #ce9178; font-weight: bold; }
        .type { color: #9cdcfe; }
        .error { color: #f48771; background: #2d1d1d; padding: 15px; border-left: 3px solid #f48771; }
    </style>
</head>
<body>

<h1>🔍 STRUKTURA DATABÁZE wgs-servicecz01</h1>

<?php
try {
    // Přímé připojení k databázi wgs-servicecz01
    $pdo = new PDO(
        'mysql:host=localhost;dbname=wgs-servicecz01;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<p style='color:#4ec9b0;'>✅ Úspěšně připojeno k databázi: <strong>wgs-servicecz01</strong></p>";

    // Zobrazit strukturu wgs_reklamace
    echo "<h2>TABULKA: wgs_reklamace</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        $keyClass = $col['Key'] ? 'key' : '';
        echo "<tr>";
        echo "<td class='key'>{$col['Field']}</td>";
        echo "<td class='type'>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td class='{$keyClass}'>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Zobrazit strukturu wgs_photos
    echo "<h2>TABULKA: wgs_photos</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_photos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        $keyClass = $col['Key'] ? 'key' : '';
        echo "<tr>";
        echo "<td class='key'>{$col['Field']}</td>";
        echo "<td class='type'>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td class='{$keyClass}'>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Zobrazit strukturu wgs_documents
    echo "<h2>TABULKA: wgs_documents</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_documents");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        $keyClass = $col['Key'] ? 'key' : '';
        echo "<tr>";
        echo "<td class='key'>{$col['Field']}</td>";
        echo "<td class='type'>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td class='{$keyClass}'>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Počty záznamů
    echo "<h2>POČTY ZÁZNAMŮ</h2>";
    $tables = ['wgs_reklamace', 'wgs_photos', 'wgs_documents', 'wgs_users', 'wgs_email_queue', 'wgs_notes'];
    echo "<table>";
    echo "<tr><th>Tabulka</th><th>Počet záznamů</th></tr>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<tr><td class='key'>{$table}</td><td style='color:#4ec9b0;'>{$count}</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td class='key'>{$table}</td><td style='color:#f48771;'>Chyba</td></tr>";
        }
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA PŘIPOJENÍ:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

</body>
</html>
