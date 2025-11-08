<?php
$target = '/home/www/wgs-service.cz/www/uploads/photos/';
if (!is_dir($target)) {
    mkdir($target, 0775, true);
    echo "✅ Vytvořeno: $target\n";
} else {
    echo "ℹ️ Adresář již existuje: $target\n";
}
