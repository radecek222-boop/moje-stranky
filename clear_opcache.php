<?php
/**
 * Clear PHP OPcache
 */

echo "<pre>";
echo "=== PHP OPCACHE CLEAR ===\n\n";

if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✓ OPcache successfully cleared!\n\n";
        
        $status = opcache_get_status();
        if ($status) {
            echo "OPcache statistics:\n";
            echo "- Memory used: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
            echo "- Memory free: " . round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
            echo "- Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
            echo "- Hits: " . number_format($status['opcache_statistics']['hits']) . "\n";
            echo "- Misses: " . number_format($status['opcache_statistics']['misses']) . "\n";
        }
        
        echo "\n✓ Zkus nyní znovu zavolat track_heatmap.php API\n";
    } else {
        echo "❌ Failed to clear OPcache\n";
    }
} else {
    echo "⚠ OPcache is not enabled or not available\n";
    echo "This is OK - no cache to clear.\n";
}

echo "</pre>";
?>
