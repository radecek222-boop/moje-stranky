<?php
/**
 * MEDIUM PRIORITY: Organizace Setup/Install Souborů
 *
 * Přesouvá install_*.php a migration_*.sql do /setup adresáře
 * Čistí root adresář projektu
 *
 * BEZPEČNÉ: Vytváří setup adresář a přesouvá soubory
 *
 * Použití: php scripts/organize_setup_files.php
 */

// SECURITY: Admin check (pouze pro web, ne CLI)
if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Admin přístup vyžadován']));
    }
}

echo "📁 Organizace Setup/Install Souborů\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$setupDir = $projectRoot . '/setup';

// Vytvořit setup adresář pokud neexistuje
if (!is_dir($setupDir)) {
    if (!mkdir($setupDir, 0755, true)) {
        die("Chyba: Nepodařilo se vytvořit {$setupDir}\n");
    }
    echo "Vytvořen adresář: setup/\n\n";
} else {
    echo "ℹ️  Adresář setup/ již existuje\n\n";
}

// Soubory k přesunutí
$filesToMove = [
    // Install skripty
    'install_*.php',
    // Migration skripty
    'migration_*.sql',
    // Update skripty
    'update_*.sql',
    // Další setup soubory
    'add_*.sql'
];

$moved = [];
$skipped = [];
$errors = [];

echo "🔍 Hledání souborů k přesunutí...\n";
echo str_repeat("-", 70) . "\n\n";

foreach ($filesToMove as $pattern) {
    $files = glob($projectRoot . '/' . $pattern);

    foreach ($files as $file) {
        $filename = basename($file);
        $targetPath = $setupDir . '/' . $filename;

        // Přeskočit pokud už existuje v setup/
        if (file_exists($targetPath)) {
            $skipped[] = $filename . " (již existuje v setup/)";
            echo "⏭️  {$filename} - již v setup/\n";
            continue;
        }

        // Přesunout soubor
        if (rename($file, $targetPath)) {
            $moved[] = $filename;
            echo "{$filename} → setup/\n";
        } else {
            $errors[] = $filename . " (chyba při přesouvání)";
            echo "{$filename} - chyba!\n";
        }
    }
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "📊 VÝSLEDKY:\n";
echo str_repeat("=", 70) . "\n\n";

echo "Přesunuto: " . count($moved) . " souborů\n";
echo "⏭️  Přeskočeno: " . count($skipped) . "\n";
echo "Chyby: " . count($errors) . "\n\n";

if (!empty($moved)) {
    echo "Přesunuté soubory:\n";
    foreach ($moved as $file) {
        echo "  - {$file}\n";
    }
    echo "\n";
}

if (!empty($skipped)) {
    echo "Přeskočené soubory:\n";
    foreach ($skipped as $file) {
        echo "  - {$file}\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "⚠️  CHYBY:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
}

// Vytvořit README v setup/
$readmePath = $setupDir . '/README.md';
if (!file_exists($readmePath)) {
    $readme = <<<'README'
# Setup & Installation Files

Tento adresář obsahuje instalační skripty a databázové migrace pro WGS Service.

## 📁 Struktura

### Install Skripty (PHP)
- `install_*.php` - Instalační skripty pro různé moduly
- Spouštět přes web (vyžaduje admin přihlášení)

### Database Migrace (SQL)
- `migration_*.sql` - Databázové migrace
- `update_*.sql` - Update skripty
- `add_*.sql` - Přidání nových struktur

## 🚀 Jak Používat

### Install Skripty
```bash
# Web přístup (doporučeno)
https://your-domain.com/setup/install_admin_control_center.php

# Nebo CLI
php setup/install_admin_control_center.php
```

### Database Migrace
```bash
# Import do MySQL
mysql -u username -p database_name < setup/migration_name.sql

# Nebo přes PHPMyAdmin
```

## ⚠️  Bezpečnost

1. **PROD Warning**: V produkci ODSTRANIT nebo ZABEZPEČIT tento adresář!
2. Přidat do `.htaccess`:
   ```apache
   <Directory "setup">
       Require all denied
   </Directory>
   ```
3. Nebo přesunout mimo web root po instalaci

## 📋 Checklist Po Instalaci

- [ ] Spustit všechny install_*.php skripty
- [ ] Aplikovat potřebné migrace
- [ ] Otestovat funkcionalitu
- [ ] Zabezpečit nebo odstranit setup/ adresář
- [ ] Zkontrolovat logy

## 📝 Historie

- 2025-11-14: Organizace setup souborů (MEDIUM priority cleanup)
README;

    file_put_contents($readmePath, $readme);
    echo "Vytvořen README.md v setup/\n\n";
}

// Vytvořit .htaccess pro zabezpečení
$htaccessPath = $setupDir . '/.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccess = <<<'HTACCESS'
# SECURITY: Zabezpečení setup adresáře v produkci
# Po instalaci odkomentovat následující řádky:

# Require all denied
# Deny from all

# Nebo povolit pouze z localhost:
# Order Deny,Allow
# Deny from all
# Allow from 127.0.0.1
# Allow from ::1
HTACCESS;

    file_put_contents($htaccessPath, $htaccess);
    echo "Vytvořen .htaccess v setup/ (security připraven)\n\n";
}

echo str_repeat("=", 70) . "\n";
echo "💡 DOPORUČENÍ:\n";
echo str_repeat("=", 70) . "\n\n";

echo "1. Zkontrolovat že všechny install skripty fungují ze setup/\n";
echo "2. Update odkazy v dokumentaci (pokud existují)\n";
echo "3. V PRODUKCI: Zabezpečit setup/ adresář (uncomment .htaccess)\n";
echo "4. Nebo přesunout setup/ MIMO web root\n\n";

echo "HOTOVO - root adresář je nyní čistší!\n";

// Return JSON pro API
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'moved' => count($moved),
            'skipped' => count($skipped),
            'errors' => count($errors)
        ],
        'files' => [
            'moved' => $moved,
            'skipped' => $skipped,
            'errors' => $errors
        ]
    ], JSON_PRETTY_PRINT);
}
