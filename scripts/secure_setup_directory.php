<?php
/**
 * Zabezpečení setup/ adresáře
 * Přejmenuje setup/ na setup.bak/ a vytvoří .htaccess pro ochranu
 */

echo "=== ZABEZPEČENÍ SETUP/ ADRESÁŘE ===\n\n";

$setupDir = __DIR__ . '/../setup';
$backupDir = __DIR__ . '/../setup.bak';

try {
    // Kontrola jestli setup/ adresář existuje
    if (!is_dir($setupDir)) {
        echo "ℹ️  Adresář setup/ neexistuje - není co zabezpečovat\n";
        echo "OK\n";
        exit(0);
    }

    echo "📁 Nalezen adresář: setup/\n";

    // Přejmenovat setup/ na setup.bak/
    if (is_dir($backupDir)) {
        echo "⚠️  Adresář setup.bak/ již existuje\n";
        echo "🗑️  Mažu starý setup.bak/...\n";

        // Rekurzivně smazat setup.bak/
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $func = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $func($fileinfo->getRealPath());
        }
        rmdir($backupDir);
        echo "Smazán starý setup.bak/\n";
    }

    echo "🔄 Přejmenovávám setup/ → setup.bak/...\n";
    rename($setupDir, $backupDir);
    echo "Přejmenováno\n";

    // Vytvoř .htaccess v setup.bak/ pro extra ochranu
    $htaccess = $backupDir . '/.htaccess';
    $htaccessContent = "# Deny all access to setup backup directory\nOrder deny,allow\nDeny from all\n";
    file_put_contents($htaccess, $htaccessContent);
    echo "Vytvořen .htaccess ochrana v setup.bak/\n";

    echo "\nSETUP ADRESÁŘ ZABEZPEČEN!\n";
    echo "   • setup/ přejmenován na setup.bak/\n";
    echo "   • Přidán .htaccess deny all\n";
    echo "\n💡 TIP: Pokud potřebujete setup znovu spustit, přejmenujte setup.bak/ zpět na setup/\n";

} catch (Exception $e) {
    echo "KRITICKÁ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
