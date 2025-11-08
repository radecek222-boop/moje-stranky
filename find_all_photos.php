<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = '/home/www/wgs-service.cz/www';



echo "<pre>=== Hledám všechny obrázky od: $root ===\n\n";

$count = 0;
try {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($rii as $file) {
        if ($file->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file->getFilename())) {
            echo $file->getPathname() . "\n";
            $count++;
        }
    }
} catch (Exception $e) {
    echo "Chyba při prohledávání: " . $e->getMessage() . "\n";
}

echo "\nCelkem nalezených obrázků: $count\n</pre>";
?>
