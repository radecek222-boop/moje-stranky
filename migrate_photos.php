<?php
$srcDir = '/wgs-service.cz/www/uploads/photos/';
$dstDir = '/home/www/wgs-service.cz/www/uploads/photos/';

if (!is_dir($srcDir)) {
    die("❌ Zdrojový adresář neexistuje: $srcDir\n");
}
if (!is_dir($dstDir)) {
    die("❌ Cílový adresář neexistuje: $dstDir\n");
}

$files = scandir($srcDir);
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $src = $srcDir . $file;
    $dst = $dstDir . $file;

    $cmd = "php -r 'copy(\"$src\", \"$dst\");'";
    echo "🔄 Kopíruji: $src → $dst\n";
    exec($cmd, $out, $ret);
    if ($ret === 0) {
        echo "✅ OK: $file\n";
    } else {
        echo "❌ Selhalo: $file\n";
    }
}
