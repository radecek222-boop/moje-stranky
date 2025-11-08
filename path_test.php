<?php
define("BASE_PATH", "/wgs-service.cz/www");
$dirs = [
    "controllers" => BASE_PATH . "/app/controllers/save_photos.php",
    "uploads" => BASE_PATH . "/uploads/photos",
    "logs" => BASE_PATH . "/logs"
];
foreach ($dirs as $name => $path) {
    echo "$name: $path => ";
    echo file_exists($path) ? "✅ exists\n" : "❌ missing\n";
    if (is_dir($path)) {
        echo "   writable? " . (is_writable($path) ? "✅ yes\n" : "❌ no\n");
    }
}
