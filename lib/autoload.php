<?php
/**
 * Jednoduchý autoloader pro PHPMailer
 * Umístěn v /lib/ aby byl součástí git repozitáře
 */

spl_autoload_register(function ($class) {
    // PHPMailer namespace
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/PHPMailer/';

    // Kontrola jestli třída patří do PHPMailer namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Získat relativní název třídy
    $relative_class = substr($class, $len);

    // Sestavit cestu k souboru
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Pokud soubor existuje, načíst ho
    if (file_exists($file)) {
        require $file;
    }
});
