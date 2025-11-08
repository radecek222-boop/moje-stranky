<?php
$base = __DIR__ . '/uploads/photos';
$total = 0;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

foreach ($rii as $file) {
    if ($file->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file->getFilename())) {
        $total++;
        echo $file->getPathname() . "\n";
    }
}

echo "\nCelkem obrázků: $total\n";
?>
