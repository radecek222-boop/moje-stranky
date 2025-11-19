<?php
/**
 * Quick Install PHPMailer
 *
 * Rychlá instalace PHPMailer přes Composer nebo manuální download
 *
 * URL: https://www.wgs-service.cz/install_phpmailer_quick.php
 */

// Bezpečnostní kontrola - pouze admin
session_start();
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$isAdmin) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit instalaci PHPMailer.");
}

set_time_limit(300); // 5 minut

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Instalace PHPMailer</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 900px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #2D5016; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #1a300d; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🚀 Instalace PHPMailer</h1>";

$rootDir = __DIR__;
$vendorDir = $rootDir . '/vendor';
$composerJson = $rootDir . '/composer.json';

// ===== KROK 1: Kontrola Composer =====
echo "<div class='step'>";
echo "<h3>KROK 1: Kontrola Composer</h3>";

$composerExists = file_exists($composerJson);

if ($composerExists) {
    echo "<div class='success'>✓ composer.json existuje</div>";
} else {
    echo "<div class='error'>✗ composer.json NEEXISTUJE</div>";
    echo "<p>Vytvářím základní composer.json...</p>";

    $composerContent = json_encode([
        'name' => 'wgs-service/moje-stranky',
        'description' => 'White Glove Service - Natuzzi',
        'type' => 'project',
        'require' => [
            'php' => '>=8.0',
            'phpmailer/phpmailer' => '^6.9'
        ],
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/'
            ]
        }
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($composerJson, $composerContent)) {
        echo "<div class='success'>✓ composer.json vytvořen</div>";
    } else {
        echo "<div class='error'>✗ Nelze vytvořit composer.json (práva?)</div>";
    }
}

echo "</div>";

// ===== KROK 2: Zkontrolovat PHP exec =====
echo "<div class='step'>";
echo "<h3>KROK 2: Kontrola PHP exec()</h3>";

$execDisabled = in_array('exec', explode(',', ini_get('disable_functions')));

if ($execDisabled) {
    echo "<div class='error'>✗ PHP exec() je zakázána - nelze spustit Composer CLI</div>";
    echo "<p><strong>Řešení:</strong> Použijeme manuální download PHPMailer</p>";
    $useManualDownload = true;
} else {
    echo "<div class='success'>✓ PHP exec() je dostupná</div>";
    $useManualDownload = false;
}

echo "</div>";

// ===== KROK 3: Instalace =====
echo "<div class='step'>";
echo "<h3>KROK 3: Instalace PHPMailer</h3>";

if (!$useManualDownload) {
    // Zkusit Composer install
    echo "<p>Spouštím: <code>composer require phpmailer/phpmailer</code></p>";

    // Najít Composer
    $composerPath = trim(shell_exec('which composer 2>/dev/null'));
    if (empty($composerPath)) {
        $composerPath = 'composer'; // Zkusit jako globální příkaz
    }

    $output = [];
    $returnCode = 0;

    chdir($rootDir);
    exec("$composerPath require phpmailer/phpmailer --no-interaction 2>&1", $output, $returnCode);

    if ($returnCode === 0) {
        echo "<div class='success'>✓ Composer install úspěšný</div>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    } else {
        echo "<div class='error'>✗ Composer install selhal</div>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
        echo "<p><strong>Zkouším manuální download...</strong></p>";
        $useManualDownload = true;
    }
}

if ($useManualDownload) {
    // Manuální download PHPMailer ze ZIP
    echo "<p>Stahuji PHPMailer 6.9.1 z GitHub...</p>";

    $phpmailerZipUrl = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip';
    $zipPath = $rootDir . '/phpmailer.zip';
    $extractPath = $rootDir . '/vendor/phpmailer/phpmailer';

    // Stáhnout ZIP
    $zipContent = @file_get_contents($phpmailerZipUrl);

    if ($zipContent === false) {
        echo "<div class='error'>✗ Nelze stáhnout PHPMailer z GitHub</div>";
        echo "<p><strong>Alternativní řešení:</strong></p>";
        echo "<ol>";
        echo "<li>Přejděte na: <a href='https://github.com/PHPMailer/PHPMailer/releases' target='_blank'>https://github.com/PHPMailer/PHPMailer/releases</a></li>";
        echo "<li>Stáhněte nejnovější verzi (ZIP)</li>";
        echo "<li>Rozbalte do <code>vendor/phpmailer/phpmailer/</code></li>";
        echo "<li>Zajistěte, aby existoval soubor: <code>vendor/autoload.php</code></li>";
        echo "</ol>";
    } else {
        file_put_contents($zipPath, $zipContent);
        echo "<div class='success'>✓ PHPMailer ZIP stažen (" . filesize($zipPath) . " bytes)</div>";

        // Rozbalit ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            // Vytvořit vendor strukturu
            @mkdir($rootDir . '/vendor', 0755, true);
            @mkdir($rootDir . '/vendor/phpmailer', 0755, true);

            $zip->extractTo($rootDir . '/vendor/phpmailer/');
            $zip->close();

            // Přejmenovat složku (GitHub vytvoří PHPMailer-6.9.1)
            $extractedDir = $rootDir . '/vendor/phpmailer/PHPMailer-6.9.1';
            if (is_dir($extractedDir)) {
                rename($extractedDir, $extractPath);
            }

            echo "<div class='success'>✓ PHPMailer rozbalen do vendor/phpmailer/phpmailer/</div>";

            // Vytvořit autoload.php
            $autoloadContent = "<?php
// Autoload pro PHPMailer
spl_autoload_register(function(\$class) {
    \$prefix = 'PHPMailer\\\\PHPMailer\\\\';
    \$baseDir = __DIR__ . '/phpmailer/phpmailer/src/';

    \$len = strlen(\$prefix);
    if (strncmp(\$prefix, \$class, \$len) !== 0) {
        return;
    }

    \$relativeClass = substr(\$class, \$len);
    \$file = \$baseDir . str_replace('\\\\', '/', \$relativeClass) . '.php';

    if (file_exists(\$file)) {
        require \$file;
    }
});
";

            file_put_contents($vendorDir . '/autoload.php', $autoloadContent);
            echo "<div class='success'>✓ vendor/autoload.php vytvořen</div>";

            // Smazat ZIP
            unlink($zipPath);

        } else {
            echo "<div class='error'>✗ Nelze rozbalit ZIP soubor</div>";
        }
    }
}

echo "</div>";

// ===== KROK 4: Ověření =====
echo "<div class='step'>";
echo "<h3>KROK 4: Ověření instalace</h3>";

if (file_exists($vendorDir . '/autoload.php')) {
    require_once $vendorDir . '/autoload.php';

    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "<div class='success'>";
        echo "<h2>✓ INSTALACE ÚSPĚŠNÁ!</h2>";
        echo "<p>PHPMailer je nainstalován a dostupný.</p>";

        $version = \PHPMailer\PHPMailer\PHPMailer::VERSION;
        echo "<p><strong>Verze:</strong> {$version}</p>";
        echo "</div>";

        echo "<div class='info'>";
        echo "<h3>Další kroky:</h3>";
        echo "<ol>";
        echo "<li>Nastavte SMTP konfiguraci v databázi (<code>wgs_smtp_settings</code>)</li>";
        echo "<li>Spusťte: <a href='/oprav_smtp_ihned.php'>oprav_smtp_ihned.php</a></li>";
        echo "<li>Resetujte email frontu: <a href='/vycisti_emailovou_frontu.php'>vycisti_emailovou_frontu.php</a></li>";
        echo "<li>Otestujte odeslání emailu: <a href='/test_smtp_pripojeni.php'>test_smtp_pripojeni.php</a></li>";
        echo "</ol>";
        echo "</div>";

    } else {
        echo "<div class='error'>✗ PHPMailer class nebyla nalezena (autoload problém)</div>";
    }
} else {
    echo "<div class='error'>✗ vendor/autoload.php NEEXISTUJE</div>";
}

echo "</div>";

echo "</div></body></html>";
