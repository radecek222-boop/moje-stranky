<?php
/**
 * Invalidace photocustomer.php cache
 */
require_once "init.php";

// Touch photocustomer.php pro reload
$file = __DIR__ . '/photocustomer.php';
touch($file);

// OPcache invalidate
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($file, true);
    echo "✅ OPcache invalidated pro photocustomer.php<br>";
}

// Clearstatcache
clearstatcache(true, $file);
echo "✅ Stat cache cleared<br>";

echo "<br>Timestamp: " . date('Y-m-d H:i:s', filemtime($file));
echo "<br><br><a href='test_photocustomer_access.php'>← Zpět na test</a>";
echo "<br><a href='photocustomer.php' style='color: green; font-weight: bold;'>→ Zkusit otevřít photocustomer.php</a>";
?>