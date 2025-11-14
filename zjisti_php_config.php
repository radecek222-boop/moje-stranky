<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PHP Configuration</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        pre { background: #2d2d2d; padding: 15px; border-left: 3px solid #4ec9b0; overflow-x: auto; }
        .key { color: #ce9178; font-weight: bold; }
        .value { color: #9cdcfe; }
    </style>
</head>
<body>

<h1>🔍 PHP CONFIGURATION</h1>

<h2>auto_prepend_file:</h2>
<pre><?php echo htmlspecialchars(ini_get('auto_prepend_file') ?: 'není nastaveno'); ?></pre>

<h2>auto_append_file:</h2>
<pre><?php echo htmlspecialchars(ini_get('auto_append_file') ?: 'není nastaveno'); ?></pre>

<h2>include_path:</h2>
<pre><?php echo htmlspecialchars(ini_get('include_path')); ?></pre>

<h2>Loaded configuration file:</h2>
<pre><?php echo htmlspecialchars(php_ini_loaded_file() ?: 'není nalezen'); ?></pre>

<h2>Additional .ini files:</h2>
<pre><?php echo htmlspecialchars(php_ini_scanned_files() ?: 'žádné'); ?></pre>

<h2>Defined constants (DB_*):</h2>
<pre><?php
$constants = get_defined_constants(true)['user'] ?? [];
foreach ($constants as $name => $value) {
    if (strpos($name, 'DB_') === 0) {
        echo htmlspecialchars($name) . " = " . htmlspecialchars($value) . "\n";
    }
}
if (empty(array_filter(array_keys($constants), fn($k) => strpos($k, 'DB_') === 0))) {
    echo "Žádné DB_* konstanty nenalezeny\n";
}
?></pre>

<h2>Defined functions (get* related to DB):</h2>
<pre><?php
$functions = get_defined_functions()['user'] ?? [];
foreach ($functions as $func) {
    if (stripos($func, 'getdb') !== false || stripos($func, 'db') !== false) {
        echo htmlspecialchars($func) . "\n";
    }
}
?></pre>

<h2>Included files:</h2>
<pre><?php
$included = get_included_files();
foreach ($included as $file) {
    echo htmlspecialchars($file) . "\n";
}
?></pre>

</body>
</html>
