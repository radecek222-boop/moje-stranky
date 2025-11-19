#!/usr/bin/env php
<?php
/**
 * Audit produkčního serveru přes FTP
 * Připojí se k FTP a získá seznam souborů
 */

$ftpHost = 'www.wgs-service.cz';
$ftpPort = 21;
$ftpUser = 'wgs-service_cz';
$ftpPass = 'p7u.s13mR2018';

echo "=== AUDIT PRODUKČNÍHO SERVERU ===\n";
echo "Připojuji k FTP: {$ftpHost}:{$ftpPort}\n";

// Připojení k FTP
$conn = ftp_connect($ftpHost, $ftpPort, 30);
if (!$conn) {
    die("✗ Chyba: Nelze se připojit k FTP serveru\n");
}

echo "✓ Připojeno k FTP\n";

// Přihlášení
if (!ftp_login($conn, $ftpUser, $ftpPass)) {
    ftp_close($conn);
    die("✗ Chyba: Neplatné přihlašovací údaje\n");
}

echo "✓ Přihlášeno jako {$ftpUser}\n";

// Nastavit pasivní mód
ftp_pasv($conn, true);

// Získat aktuální adresář
$currentDir = ftp_pwd($conn);
echo "Aktuální adresář: {$currentDir}\n\n";

// Funkce pro rekurzivní listing
function ftpListRecursive($conn, $dir = '.', $depth = 0, $maxDepth = 2) {
    if ($depth > $maxDepth) {
        return [];
    }

    $files = [];
    $list = ftp_nlist($conn, $dir);

    if ($list === false) {
        return [];
    }

    foreach ($list as $item) {
        $basename = basename($item);

        // Přeskočit . a ..
        if ($basename === '.' || $basename === '..') {
            continue;
        }

        // Přeskočit skryté soubory kromě důležitých
        if ($basename[0] === '.' && !in_array($basename, ['.env', '.htaccess', '.gitignore'])) {
            continue;
        }

        $files[] = $item;

        // Zkusit zjistit, zda je to adresář
        $size = ftp_size($conn, $item);
        if ($size === -1) {
            // Pravděpodobně adresář
            if ($depth < $maxDepth) {
                $subdirFiles = ftpListRecursive($conn, $item, $depth + 1, $maxDepth);
                $files = array_merge($files, $subdirFiles);
            }
        }
    }

    return $files;
}

echo "=== STRUKTURA PRODUKČNÍHO WEBU ===\n";
echo "Načítám soubory (max 2 úrovně)...\n\n";

$allFiles = ftpListRecursive($conn, '.', 0, 1);

// Seřadit soubory
sort($allFiles);

// Rozdělit na adresáře a soubory
$directories = [];
$files = [];

foreach ($allFiles as $item) {
    $size = ftp_size($conn, $item);
    if ($size === -1) {
        $directories[] = $item;
    } else {
        $files[] = ['path' => $item, 'size' => $size];
    }
}

echo "Počet složek: " . count($directories) . "\n";
echo "Počet souborů: " . count($files) . "\n\n";

// Klíčové soubory k ověření
$keyFiles = [
    '.env',
    'vendor/autoload.php',
    'composer.json',
    'composer.lock',
    'init.php',
    'config/config.php',
    'includes/EmailQueue.php',
    'app/notification_sender.php',
    'cron/process-email-queue.php',
    'api/protokol_api.php'
];

echo "=== KONTROLA KLÍČOVÝCH SOUBORŮ ===\n";
foreach ($keyFiles as $file) {
    $size = ftp_size($conn, $file);
    if ($size !== -1) {
        echo "✓ {$file} ({$size} bytes)\n";
    } else {
        echo "✗ {$file} - NEEXISTUJE!\n";
    }
}

echo "\n=== KONTROLA VENDOR SLOŽKY ===\n";
$vendorExists = ftp_size($conn, 'vendor/autoload.php');
if ($vendorExists !== -1) {
    echo "✓ vendor/autoload.php existuje ({$vendorExists} bytes)\n";

    // Zkusit najít PHPMailer
    $phpmailerPath = 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
    $phpmailerSize = ftp_size($conn, $phpmailerPath);
    if ($phpmailerSize !== -1) {
        echo "✓ PHPMailer je nainstalován ({$phpmailerSize} bytes)\n";
    } else {
        echo "✗ PHPMailer NENÍ nainstalován!\n";
    }
} else {
    echo "✗ vendor/ složka NEEXISTUJE - Composer balíčky nejsou nainstalovány!\n";
}

echo "\n=== KONTROLA .ENV SOUBORU ===\n";
$envSize = ftp_size($conn, '.env');
if ($envSize !== -1) {
    echo "✓ .env existuje ({$envSize} bytes)\n";

    // Stáhnout .env soubor (pro analýzu - nebude commitován)
    $localEnvPath = '/tmp/production.env';
    if (ftp_get($conn, $localEnvPath, '.env', FTP_BINARY)) {
        echo "✓ .env stažen pro analýzu\n";

        $envContent = file_get_contents($localEnvPath);
        $envLines = explode("\n", $envContent);

        echo "\nKlíče v .env:\n";
        foreach ($envLines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);

                // Nezobrazovat hodnoty hesel
                if (strpos($key, 'PASS') !== false || strpos($key, 'KEY') !== false || strpos($key, 'SECRET') !== false) {
                    echo "  {$key}=***HIDDEN***\n";
                } else {
                    echo "  {$key}={$value}\n";
                }
            }
        }

        // Smazat dočasný soubor
        unlink($localEnvPath);
    }
} else {
    echo "✗ .env NEEXISTUJE!\n";
}

echo "\n=== TOP 20 NEJVĚTŠÍCH SOUBORŮ ===\n";
usort($files, function($a, $b) {
    return $b['size'] - $a['size'];
});

foreach (array_slice($files, 0, 20) as $file) {
    $sizeMB = round($file['size'] / 1024 / 1024, 2);
    echo sprintf("%.2f MB - %s\n", $sizeMB, $file['path']);
}

// Ukončit připojení
ftp_close($conn);

echo "\n=== AUDIT DOKONČEN ===\n";
