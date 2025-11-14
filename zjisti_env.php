<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Environment proměnné</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        table { background: #2d2d2d; border-collapse: collapse; width: 100%; margin: 20px 0; }
        th { background: #3c3c3c; color: #4ec9b0; padding: 10px; text-align: left; border: 1px solid #555; }
        td { padding: 8px; border: 1px solid #555; word-break: break-all; }
        .key { color: #ce9178; font-weight: bold; }
        .value { color: #9cdcfe; }
    </style>
</head>
<body>

<h1>🔍 ENVIRONMENT PROMĚNNÉ</h1>

<?php
// Zobrazit DB_ proměnné
echo "<h2>$_SERVER proměnné (DB_*):</h2>";
echo "<table>";
echo "<tr><th>Klíč</th><th>Hodnota</th></tr>";

foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'DB_') === 0) {
        echo "<tr>";
        echo "<td class='key'>{$key}</td>";
        echo "<td class='value'>" . htmlspecialchars($value) . "</td>";
        echo "</tr>";
    }
}
echo "</table>";

// Zobrazit getenv DB_ proměnné
echo "<h2>getenv() proměnné (DB_*):</h2>";
echo "<table>";
echo "<tr><th>Klíč</th><th>Hodnota</th></tr>";

$dbKeys = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($dbKeys as $key) {
    $value = getenv($key);
    echo "<tr>";
    echo "<td class='key'>{$key}</td>";
    echo "<td class='value'>" . ($value !== false ? htmlspecialchars($value) : '<em>není nastaveno</em>') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Zobrazit $_ENV
echo "<h2>\$_ENV proměnné (DB_*):</h2>";
echo "<table>";
echo "<tr><th>Klíč</th><th>Hodnota</th></tr>";

if (empty($_ENV)) {
    echo "<tr><td colspan='2'><em>$_ENV je prázdné</em></td></tr>";
} else {
    foreach ($_ENV as $key => $value) {
        if (strpos($key, 'DB_') === 0) {
            echo "<tr>";
            echo "<td class='key'>{$key}</td>";
            echo "<td class='value'>" . htmlspecialchars($value) . "</td>";
            echo "</tr>";
        }
    }
}
echo "</table>";

// Zobrazit další důležité proměnné
echo "<h2>Další důležité proměnné:</h2>";
echo "<table>";
echo "<tr><th>Klíč</th><th>Hodnota</th></tr>";

$importantKeys = ['GEOAPIFY_API_KEY', 'JWT_SECRET', 'ENVIRONMENT', 'DEEPL_API_KEY'];
foreach ($importantKeys as $key) {
    $serverValue = $_SERVER[$key] ?? 'není v $_SERVER';
    $envValue = getenv($key);

    echo "<tr>";
    echo "<td class='key'>{$key} (\$_SERVER)</td>";
    echo "<td class='value'>" . htmlspecialchars($serverValue) . "</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td class='key'>{$key} (getenv)</td>";
    echo "<td class='value'>" . ($envValue !== false ? htmlspecialchars($envValue) : '<em>není nastaveno</em>') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Ukázat Document Root a cesty
echo "<h2>Systémové cesty:</h2>";
echo "<table>";
echo "<tr><th>Proměnná</th><th>Hodnota</th></tr>";
echo "<tr><td class='key'>DOCUMENT_ROOT</td><td class='value'>" . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</td></tr>";
echo "<tr><td class='key'>__DIR__</td><td class='value'>" . htmlspecialchars(__DIR__) . "</td></tr>";
echo "<tr><td class='key'>SERVER_NAME</td><td class='value'>" . htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'N/A') . "</td></tr>";
echo "</table>";

?>

</body>
</html>
