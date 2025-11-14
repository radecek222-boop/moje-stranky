# Instalace PHPMailer na Český hosting (bez SSH)

## Krok 1: Stáhnout PHPMailer

1. Jdi na: https://github.com/PHPMailer/PHPMailer/releases
2. Stáhni nejnovější verzi (např. `PHPMailer-6.9.1.zip`)
3. Rozbal ZIP na svém počítači

## Krok 2: Nahrát přes FTP

1. Připoj se k FTP serveru (Český hosting panel → FTP)
2. Na serveru vytvoř složku: `/www/wgs-service.cz/vendor/phpmailer/phpmailer/`
3. Nahraj obsah rozbalené složky `PHPMailer-6.9.1/` do: `/www/wgs-service.cz/vendor/phpmailer/phpmailer/`

## Struktura má vypadat takto:

```
/www/wgs-service.cz/
├── vendor/
│   ├── autoload.php  ← tento soubor musíš vytvořit (viz níže)
│   └── phpmailer/
│       └── phpmailer/
│           ├── src/
│           │   ├── PHPMailer.php
│           │   ├── SMTP.php
│           │   └── Exception.php
│           ├── language/
│           └── ...
```

## Krok 3: Vytvořit autoload.php

V `/www/wgs-service.cz/vendor/` vytvoř soubor `autoload.php` s obsahem:

```php
<?php
// PHPMailer Autoloader
spl_autoload_register(function ($class) {
    // Prefix pro PHPMailer namespace
    $prefix = 'PHPMailer\\PHPMailer\\';

    // Base directory pro PHPMailer
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';

    // Pokud třída nepoužívá tento namespace, skip
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Získat relativní název třídy
    $relative_class = substr($class, $len);

    // Nahradit namespace separátory directory separátory
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Pokud soubor existuje, načíst ho
    if (file_exists($file)) {
        require $file;
    }
});
```

## Krok 4: Test instalace

Vytvoř testovací soubor `/www/wgs-service.cz/test-phpmailer.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "✅ PHPMailer je správně nainstalovaný!";
} else {
    echo "❌ PHPMailer se nepodařilo načíst";
}
```

Pak jdi na: `https://www.wgs-service.cz/test-phpmailer.php`

## Hotovo! 🎉
