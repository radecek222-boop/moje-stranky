<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SERVER Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        table { background: #2d2d2d; border-collapse: collapse; width: 100%; margin: 20px 0; }
        th { background: #3c3c3c; color: #4ec9b0; padding: 10px; text-align: left; border: 1px solid #555; }
        td { padding: 8px; border: 1px solid #555; word-break: break-all; }
        .key { color: #ce9178; font-weight: bold; }
        .value { color: #9cdcfe; }
        .missing { color: #f48771; }
    </style>
</head>
<body>

<h1>🔍 $_SERVER DIAGNOSTIC</h1>

<?php
// Zobrazit jen DB_ hodnoty
echo "<h2>DB_* values in \$_SERVER:</h2>";
echo "<table>";
echo "<tr><th>Key</th><th>Value</th><th>Status</th></tr>";

$dbKeys = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'ADMIN_KEY_HASH', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_FROM', 'SMTP_USER', 'SMTP_PASS'];
foreach ($dbKeys as $key) {
    $value = $_SERVER[$key] ?? null;
    $status = $value !== null ? '✅' : '❌ MISSING';

    // Maskovat hesla
    if (in_array($key, ['DB_PASS', 'SMTP_PASS', 'ADMIN_KEY_HASH']) && $value !== null) {
        $value = str_repeat('*', min(20, strlen($value)));
    }

    echo "<tr>";
    echo "<td class='key'>{$key}</td>";
    echo "<td class='" . ($value !== null ? "value" : "missing") . "'>" . htmlspecialchars($value ?? 'NOT SET') . "</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Server Info:</h2>";
echo "<table>";
echo "<tr><td class='key'>SERVER_NAME</td><td class='value'>" . htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'N/A') . "</td></tr>";
echo "<tr><td class='key'>DOCUMENT_ROOT</td><td class='value'>" . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</td></tr>";
echo "<tr><td class='key'>PHP_VERSION</td><td class='value'>" . PHP_VERSION . "</td></tr>";
echo "</table>";
?>

</body>
</html>
