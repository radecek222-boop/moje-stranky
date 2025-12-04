<?php
/**
 * Uklid testovacich souboru z rootu webu
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

$testSoubory = [
    'test_push_notifikace.php',
    'test_push_bez_ssl.php',
    'test_curl_apple.php',
    'test_ca_cesty.php',
    'pridej_apple_ca.php',
    'diagnostika_webpush.php',
    'AppleRootCA-G3.cer',
    'AppleWWDRCAG4.cer',
    'AppleRootCA-G3.pem',
    'AppleWWDRCAG4.pem',
];

echo "<h1>Uklid testovacich souboru</h1>";

if (isset($_GET['execute']) && $_GET['execute'] === '1') {
    $smazano = 0;
    foreach ($testSoubory as $soubor) {
        $cesta = __DIR__ . '/' . $soubor;
        if (file_exists($cesta)) {
            if (unlink($cesta)) {
                echo "<p style='color:green'>Smazano: $soubor</p>";
                $smazano++;
            } else {
                echo "<p style='color:red'>Nelze smazat: $soubor</p>";
            }
        }
    }
    echo "<p><strong>Smazano $smazano souboru.</strong></p>";
    echo "<p><a href='/admin.php'>Zpet do Admin</a></p>";
} else {
    echo "<p>Budou smazany tyto testovaci soubory:</p><ul>";
    $existuje = 0;
    foreach ($testSoubory as $soubor) {
        $cesta = __DIR__ . '/' . $soubor;
        if (file_exists($cesta)) {
            echo "<li>$soubor</li>";
            $existuje++;
        }
    }
    echo "</ul>";
    echo "<p>Celkem $existuje souboru k smazani.</p>";
    echo "<p><a href='?execute=1' style='background:#333;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Smazat vsechny</a></p>";
}
?>
