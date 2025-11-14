<?php
/**
 * CRITICAL PRIORITY: Zabezpečení setup/ adresáře
 *
 * Zkopíruje .htaccess.production → .htaccess pro zablokování přístupu
 * v produkci. Po spuštění už nebudeš moci přistupovat k setup scriptům!
 *
 * Použití:
 * - CLI: php scripts/secure_setup_directory.php
 * - Web: Spustit z admin panelu (vyžaduje admin oprávnění)
 */

require_once __DIR__ . '/../init.php';

// SECURITY: Admin check
if (php_sapi_name() !== 'cli') {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Admin access required']));
    }
}

// Detect if this is API call (non-CLI)
$isApiCall = (php_sapi_name() !== 'cli');

$setupDir = __DIR__ . '/../setup';
$sourceFile = $setupDir . '/.htaccess.production';
$targetFile = $setupDir . '/.htaccess';
$backupFile = $setupDir . '/.htaccess.backup';

try {
    // Verify source file exists
    if (!file_exists($sourceFile)) {
        throw new Exception("Source file not found: .htaccess.production");
    }

    // Backup existing .htaccess if it exists
    if (file_exists($targetFile)) {
        if (!$isApiCall) {
            echo "📦 Backing up existing .htaccess...\n";
        }
        if (!copy($targetFile, $backupFile)) {
            throw new Exception("Failed to backup existing .htaccess");
        }
    }

    // Copy production .htaccess
    if (!$isApiCall) {
        echo "🔐 Copying .htaccess.production → .htaccess...\n";
    }

    if (!copy($sourceFile, $targetFile)) {
        throw new Exception("Failed to copy .htaccess.production to .htaccess");
    }

    // Verify the copy
    if (!file_exists($targetFile)) {
        throw new Exception(".htaccess file was not created");
    }

    $result = [
        'status' => 'success',
        'message' => 'Setup adresář zabezpečen! Přístup k /setup/ je nyní blokován.',
        'details' => [
            'source' => '.htaccess.production',
            'target' => '.htaccess',
            'backup' => file_exists($backupFile) ? '.htaccess.backup' : null
        ]
    ];

    // Return JSON for API calls
    if ($isApiCall) {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }

    // CLI output
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✅ HOTOVO!\n";
    echo str_repeat("=", 70) . "\n\n";
    echo "🔐 Setup adresář je nyní zabezpečen!\n";
    echo "✅ Soubor .htaccess.production zkopírován do .htaccess\n";

    if (file_exists($backupFile)) {
        echo "📦 Záloha uložena do .htaccess.backup\n";
    }

    echo "\n⚠️  POZOR:\n";
    echo "  - Přístup k /setup/ je nyní BLOKOVÁN\n";
    echo "  - Setup scripty už nepůjde spustit z prohlížeče\n";
    echo "  - Pro odblokování zkopíruj .htaccess.localhost → .htaccess\n";

} catch (Exception $e) {
    $errorResult = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];

    // Return JSON for API calls
    if ($isApiCall) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode($errorResult, JSON_PRETTY_PRINT);
        exit;
    }

    // CLI output
    echo "\n❌ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
