<?php
/**
 * PHPMailer Installer Script
 * Automaticky stáhne a nainstaluje PHPMailer
 *
 * Tento script je určen k automatickému spuštění z Control Center
 */

// Zabránit přímému přístupu (pouze přes include z Control Center API)
if (!defined('BASE_PATH')) {
    // Pokud je spuštěn přímo, načíst init.php
    require_once __DIR__ . '/../init.php';

    // Kontrola admin oprávnění
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        die('Forbidden: Admin access required');
    }
}

$logFile = __DIR__ . '/../logs/phpmailer_install.log';
$logDir = dirname($logFile);

// Vytvořit logs složku pokud neexistuje
if (!file_exists($logDir)) {
    @mkdir($logDir, 0755, true);
}

function logInstall($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    @file_put_contents($logFile, $log, FILE_APPEND);
    echo $log;
}

try {
    logInstall("====================================");
    logInstall("PHPMailer Installation - START");
    logInstall("====================================");

    // Cesta k vendor složce
    $vendorDir = __DIR__ . '/../vendor';
    $phpmailerDir = $vendorDir . '/phpmailer/phpmailer';
    $autoloadFile = $vendorDir . '/autoload.php';

    // Kontrola, zda už není nainstalovaný
    if (file_exists($phpmailerDir . '/src/PHPMailer.php')) {
        logInstall("✓ PHPMailer je již nainstalovaný");

        // Zkontrolovat autoload.php
        if (!file_exists($autoloadFile)) {
            logInstall("⚠ autoload.php chybí, vytvářím...");
            $autoloadContent = <<<'PHP'
<?php
/**
 * PHPMailer Autoloader
 */
spl_autoload_register(function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
PHP;
            @file_put_contents($autoloadFile, $autoloadContent);
            logInstall("✓ autoload.php vytvořen");
        }

        logInstall("====================================");
        return true;
    }

    // Vytvořit vendor strukturu
    logInstall("Vytvářím vendor strukturu...");
    if (!file_exists($vendorDir)) {
        @mkdir($vendorDir, 0755, true);
    }
    if (!file_exists($vendorDir . '/phpmailer')) {
        @mkdir($vendorDir . '/phpmailer', 0755, true);
    }

    // URL PHPMailer
    $version = '6.9.1';
    $url = "https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v{$version}.tar.gz";
    $tarFile = $vendorDir . '/phpmailer.tar.gz';

    logInstall("Stahuji PHPMailer v{$version}...");
    logInstall("URL: {$url}");

    // Stáhnout pomocí curl nebo file_get_contents
    $downloaded = false;

    // Pokus 1: curl
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $data !== false) {
            @file_put_contents($tarFile, $data);
            $downloaded = true;
            logInstall("✓ Staženo pomocí cURL");
        }
    }

    // Pokus 2: file_get_contents
    if (!$downloaded && ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'follow_location' => 1
            ]
        ]);

        $data = @file_get_contents($url, false, $context);
        if ($data !== false) {
            @file_put_contents($tarFile, $data);
            $downloaded = true;
            logInstall("✓ Staženo pomocí file_get_contents");
        }
    }

    if (!$downloaded) {
        throw new Exception('Nepodařilo se stáhnout PHPMailer. Zkontrolujte síťové připojení nebo stáhněte ručně.');
    }

    logInstall("Rozbaluji archiv...");

    // Rozbalit tar.gz
    $phar = new PharData($tarFile);
    $phar->extractTo($vendorDir . '/phpmailer', null, true);

    // Přejmenovat složku
    $extractedDir = $vendorDir . '/phpmailer/PHPMailer-' . $version;
    if (is_dir($extractedDir)) {
        rename($extractedDir, $phpmailerDir);
        logInstall("✓ Archiv rozbalen a přejmenován");
    }

    // Smazat tar.gz
    @unlink($tarFile);
    logInstall("✓ Dočasný archiv smazán");

    // Vytvořit autoload.php
    logInstall("Vytvářím autoload.php...");
    $autoloadContent = <<<'PHP'
<?php
/**
 * PHPMailer Autoloader
 */
spl_autoload_register(function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
PHP;
    @file_put_contents($autoloadFile, $autoloadContent);
    logInstall("✓ autoload.php vytvořen");

    // Test načtení PHPMailer
    require_once $autoloadFile;

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        throw new Exception('PHPMailer se nepodařilo načíst po instalaci');
    }

    $mailTest = new \PHPMailer\PHPMailer\PHPMailer();
    $version = $mailTest::VERSION;

    logInstall("====================================");
    logInstall("✅ PHPMailer {$version} úspěšně nainstalován!");
    logInstall("====================================");

    return true;

} catch (Exception $e) {
    logInstall("====================================");
    logInstall("❌ CHYBA: " . $e->getMessage());
    logInstall("====================================");

    // Vyhodit exception, aby Control Center API zachytil chybu
    throw $e;
}
