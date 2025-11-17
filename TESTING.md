# 🧪 Testování WGS Service

Quick start guide pro spuštění testů lokálně.

## ⚡ Quick Start

```bash
# 1. Nainstalovat závislosti
composer install

# 2. Spustit všechny testy
composer test

# 3. Vygenerovat coverage report
composer test-coverage
```

## 📊 Výstup

```
PHPUnit 11.0
.................................   35 / 100 ( 35%)
.................................   70 / 100 ( 70%)
..............................      100 / 100 (100%)

✅ All tests passed!

Time: 00:02.456, Memory: 12.00 MB

OK (100 tests, 300 assertions)
```

## 🎯 Spuštění konkrétních testů

```bash
# Pouze security testy (CSRF, Rate Limiter)
vendor/bin/phpunit --testsuite Security

# Pouze controller testy
vendor/bin/phpunit --testsuite Controllers

# Konkrétní test soubor
vendor/bin/phpunit tests/Unit/Security/CsrfHelperTest.php

# Konkrétní test metoda
vendor/bin/phpunit --filter testValidujeSprávnyToken
```

## 🐛 Debug mode

```bash
# Verbose output
vendor/bin/phpunit --verbose

# Testdox format (čitelný výpis)
vendor/bin/phpunit --testdox

# Stop on first failure
vendor/bin/phpunit --stop-on-failure
```

## 📈 Coverage Report

```bash
# HTML report
composer test-coverage
# Otevřít: coverage/html/index.html

# Text report (do konzole)
vendor/bin/phpunit --coverage-text
```

## ⚙️ Požadavky

- PHP 8.4+
- Composer
- Extensions: pdo, pdo_sqlite, mbstring, json
- (Volitelně) Xdebug pro coverage

## 🔧 Instalace Xdebug (pro coverage)

```bash
# Ubuntu/Debian
sudo apt-get install php8.4-xdebug

# Ověření
php -m | grep xdebug
```

## 📝 Psaní vlastních testů

Viz detailní guide: [tests/README.md](tests/README.md)

Quick template:

```php
<?php
namespace Tests\Unit\YourModule;

use PHPUnit\Framework\TestCase;

class YourTest extends TestCase
{
    public function testVasePripady(): void
    {
        $this->assertTrue(true);
    }
}
```

## 🚀 CI/CD

Testy se spouští automaticky při každém push do `main`.

GitHub Actions workflow: `.github/workflows/deploy.yml`

**Deploy probíhá POUZE pokud projdou všechny testy!** ✅

## 📞 Problémy?

1. Zkontrolujte PHP verzi: `php -v`
2. Zkontrolujte kompozer: `composer diagnose`
3. Zkontrolujte extensions: `php -m`

Pokud problém přetrvává, kontaktujte: radek@wgs-service.cz
