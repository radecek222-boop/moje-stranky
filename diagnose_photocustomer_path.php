<?php
/**
 * Diagnostika - Kde webserver čte photocustomer.php?
 */
require_once "init.php";

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) die('403 - Admin only');

?>
<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"><title>File Path Diagnostic</title>
<style>body{font-family:monospace;background:#1a1a1a;color:#00ff88;padding:20px;}
table{border-collapse:collapse;background:#2a2a2a;margin:20px 0;}
th,td{border:1px solid #444;padding:10px;text-align:left;}
th{background:#2D5016;color:white;}.error{color:#ff6b6b;}.success{color:#00ff88;}
</style></head><body>
<h1>🔍 File Path Diagnostic</h1>

<h2>1. PHP Paths</h2>
<table>
<tr><th>Proměnná</th><th>Hodnota</th></tr>
<tr><td>__DIR__</td><td><code><?php echo __DIR__; ?></code></td></tr>
<tr><td>__FILE__</td><td><code><?php echo __FILE__; ?></code></td></tr>
<tr><td>$_SERVER['DOCUMENT_ROOT']</td><td><code><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></code></td></tr>
<tr><td>getcwd()</td><td><code><?php echo getcwd(); ?></code></td></tr>
<tr><td>$_SERVER['SCRIPT_FILENAME']</td><td><code><?php echo $_SERVER['SCRIPT_FILENAME'] ?? 'N/A'; ?></code></td></tr>
</table>

<h2>2. photocustomer.php Lokace</h2>
<?php
$paths = [
    'Relativní' => __DIR__ . '/photocustomer.php',
    'Document Root' => ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/photocustomer.php',
    'getcwd()' => getcwd() . '/photocustomer.php',
];

echo '<table><tr><th>Typ</th><th>Path</th><th>Existuje?</th><th>Velikost</th><th>MD5</th><th>Upraveno</th></tr>';
foreach ($paths as $label => $path) {
    $exists = file_exists($path);
    $size = $exists ? filesize($path) : 'N/A';
    $md5 = $exists ? md5_file($path) : 'N/A';
    $mtime = $exists ? date('Y-m-d H:i:s', filemtime($path)) : 'N/A';

    echo "<tr>";
    echo "<td><strong>{$label}</strong></td>";
    echo "<td><code>" . htmlspecialchars($path) . "</code></td>";
    echo "<td>" . ($exists ? '<span class="success">✓ ANO</span>' : '<span class="error">✗ NE</span>') . "</td>";
    echo "<td>{$size}</td>";
    echo "<td><small>{$md5}</small></td>";
    echo "<td>{$mtime}</td>";
    echo "</tr>";
}
echo '</table>';
?>

<h2>3. Obsah photocustomer.php (řádky 14-24)</h2>
<?php
$file = __DIR__ . '/photocustomer.php';
if (file_exists($file)) {
    $lines = file($file);
    echo '<pre>';
    for ($i = 13; $i < min(24, count($lines)); $i++) {
        echo sprintf("%3d: %s", $i + 1, htmlspecialchars($lines[$i]));
    }
    echo '</pre>';

    echo '<p><strong>MD5 hash:</strong> <code>' . md5_file($file) . '</code></p>';
    echo '<p><strong>Velikost:</strong> ' . filesize($file) . ' bytů</p>';
    echo '<p><strong>Upraveno:</strong> ' . date('Y-m-d H:i:s', filemtime($file)) . '</p>';
} else {
    echo '<p class="error">❌ Soubor nenalezen!</p>';
}
?>

<h2>4. Akce</h2>
<form method="POST" action="invalidate_photocustomer.php">
    <button type="submit" style="padding:10px 20px;background:#dc3545;color:white;border:none;border-radius:5px;cursor:pointer;">
        🔥 FORCE RELOAD photocustomer.php
    </button>
</form>

</body></html>
