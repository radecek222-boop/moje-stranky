<?php
/**
 * Generování konfigurace pro vytvoření klonu
 *
 * Tento skript vezme výběr stránek a vygeneruje aktualizovaný
 * vytvor_cisty_klon.php s vybranými soubory.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Neplatný požadavek");
}

header('Content-Type: text/html; charset=utf-8');

$selectedFiles = $_POST['selected_files'] ?? '';
$filesArray = array_filter(explode(',', $selectedFiles));

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Konfigurace vygenerována</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white;
                     padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 15px; border-radius: 5px; margin: 20px 0; }
        pre { background: #000; color: #0f0; padding: 20px; border-radius: 5px;
              overflow-x: auto; max-height: 400px; }
        .btn { display: inline-block; padding: 12px 24px; background: #333;
               color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #555; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>✅ Konfigurace vygenerována</h1>";

if (empty($filesArray)) {
    echo "<div class='error'>Žádné soubory nebyly vybrány!</div>";
    echo "<a href='vyber_stranky_pro_klon.php' class='btn'>← Zpět</a>";
    echo "</div></body></html>";
    exit;
}

echo "<div class='success'>";
echo "<strong>Vybráno " . count($filesArray) . " souborů:</strong><br><br>";
echo "<ul style='columns: 3;'>";
foreach ($filesArray as $file) {
    echo "<li>" . htmlspecialchars($file) . "</li>";
}
echo "</ul>";
echo "</div>";

// Vygenerovat PHP array
$phpArray = "\$aktivniStranky = [\n";
foreach ($filesArray as $file) {
    $phpArray .= "    " . var_export($file, true) . ",\n";
}
$phpArray .= "];";

echo "<h2>📋 Vygenerovaný kód pro vytvor_cisty_klon.php</h2>";
echo "<p>Zkopírujte tento kód a nahraďte jím array <code>\$aktivniStranky</code> v souboru <code>vytvor_cisty_klon.php</code>:</p>";

echo "<pre>";
echo htmlspecialchars($phpArray);
echo "</pre>";

// Automaticky aktualizovat soubor
if (isset($_POST['auto_update']) && $_POST['auto_update'] === '1') {
    $sourcePath = __DIR__ . '/vytvor_cisty_klon.php';
    $source = file_get_contents($sourcePath);

    // Najít a nahradit $aktivniStranky array
    $pattern = '/\$aktivniStranky\s*=\s*\[[^\]]*\];/s';
    $updated = preg_replace($pattern, $phpArray, $source, 1);

    if ($updated && $updated !== $source) {
        file_put_contents($sourcePath, $updated);
        echo "<div class='success'>";
        echo "✅ <strong>Soubor vytvor_cisty_klon.php byl automaticky aktualizován!</strong><br>";
        echo "Nyní můžete spustit vytvoření klonu.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "⚠️ Nepodařilo se automaticky aktualizovat soubor. Zkopírujte kód ručně.";
        echo "</div>";
    }
}

echo "<br>";
echo "<form method='post'>";
echo "<input type='hidden' name='selected_files' value='" . htmlspecialchars($selectedFiles) . "'>";
echo "<input type='hidden' name='auto_update' value='1'>";
echo "<button type='submit' class='btn btn-success'>🔄 Automaticky aktualizovat vytvor_cisty_klon.php</button>";
echo "</form>";

echo "<a href='vytvor_cisty_klon.php' class='btn btn-success'>🚀 Spustit vytvoření klonu</a>";
echo "<a href='vyber_stranky_pro_klon.php' class='btn'>← Změnit výběr</a>";
echo "<a href='/admin.php' class='btn'>Admin panel</a>";

echo "</div></body></html>";
?>
