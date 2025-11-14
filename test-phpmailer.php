<?php
/**
 * Test PHPMailer instalace
 * Po nahrání na server jdi na: https://www.wgs-service.cz/test-phpmailer.php
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>PHPMailer Test</title></head><body>";
echo "<h1>PHPMailer Installation Test</h1>";

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "<p style='color: green; font-size: 20px;'>✅ PHPMailer je správně nainstalovaný!</p>";

    // Zobrazit verzi
    $mail = new \PHPMailer\PHPMailer\PHPMailer();
    echo "<p>Verze: " . $mail::VERSION . "</p>";

    echo "<h2>Další kroky:</h2>";
    echo "<ol>";
    echo "<li>Smaž tento soubor (test-phpmailer.php) ze serveru</li>";
    echo "<li>Zkontroluj SMTP nastavení v admin panelu</li>";
    echo "<li>Email queue cron nyní bude používat PHPMailer</li>";
    echo "</ol>";
} else {
    echo "<p style='color: red; font-size: 20px;'>❌ PHPMailer se nepodařilo načíst</p>";
    echo "<p>Zkontroluj, že máš správnou strukturu složek:</p>";
    echo "<pre>";
    echo "/www/wgs-service.cz/\n";
    echo "├── vendor/\n";
    echo "│   ├── autoload.php\n";
    echo "│   └── phpmailer/\n";
    echo "│       └── phpmailer/\n";
    echo "│           └── src/\n";
    echo "│               ├── PHPMailer.php\n";
    echo "│               ├── SMTP.php\n";
    echo "│               └── Exception.php\n";
    echo "</pre>";
}

echo "</body></html>";
