<?php
/**
 * RYCHLÁ KONTROLA - jaká verze protokol_api.php je NA SERVERU?
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html><head><meta charset='UTF-8'><title>Kontrola verze</title>
<style>
body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:13px;}
.box{background:#252526;padding:15px;margin:10px 0;border-left:4px solid #007acc;}
.error{border-left-color:#f44747;}
.success{border-left-color:#4ec9b0;}
h1{color:#4ec9b0;margin:0 0 15px 0;font-size:18px;}
h2{color:#dcdcaa;margin:15px 0 8px 0;font-size:14px;}
pre{background:#1e1e1e;padding:10px;border-radius:3px;overflow-x:auto;font-size:11px;line-height:1.4;}
code{color:#ce9178;}
table{border-collapse:collapse;width:100%;font-size:12px;}
td{padding:6px;border:1px solid #3e3e42;}
td:first-child{color:#9cdcfe;width:180px;font-weight:bold;}
</style>
</head><body>

<h1>🔍 KONTROLA VERZE protokol_api.php</h1>

<?php
$file = __DIR__ . '/api/protokol_api.php';

if (!file_exists($file)) {
    echo "<div class='box error'><h2>❌ CHYBA: Soubor neexistuje!</h2></div>";
    exit;
}

$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "<div class='box success'>";
echo "<h2>📊 ZÁKLADNÍ INFO:</h2>";
echo "<table>";
echo "<tr><td>Soubor</td><td><code>api/protokol_api.php</code></td></tr>";
echo "<tr><td>Velikost</td><td>" . number_format(strlen($content)) . " bytů (" . number_format(strlen($content) / 1024, 1) . " KB)</td></tr>";
echo "<tr><td>Řádků</td><td>" . count($lines) . "</td></tr>";
echo "<tr><td>SHA256 hash</td><td><code style='font-size:10px;'>" . hash('sha256', $content) . "</code></td></tr>";
echo "<tr><td>Poslední úprava</td><td>" . date('Y-m-d H:i:s', filemtime($file)) . "</td></tr>";
echo "</table>";
echo "</div>";

// Řádek 464 - kde je "Reklamace nebyla nalezena"
echo "<div class='box'>";
echo "<h2>🔍 ŘÁDEK 464 (kde se stala chyba):</h2>";
if (isset($lines[463])) {
    echo "<pre>" . htmlspecialchars($lines[463]) . "</pre>";
} else {
    echo "<p>❌ Řádek 464 neexistuje!</p>";
}
echo "</div>";

// Kontext kolem řádku 464
echo "<div class='box'>";
echo "<h2>📝 KONTEXT (řádky 460-470):</h2>";
echo "<pre>";
for ($i = 459; $i < 470 && $i < count($lines); $i++) {
    $lineNum = $i + 1;
    $highlight = ($lineNum == 464) ? ' ← CHYBA' : '';
    echo sprintf("%3d: %s%s\n", $lineNum, htmlspecialchars($lines[$i]), $highlight);
}
echo "</pre>";
echo "</div>";

// Hledání SMTPAuth opravy
echo "<div class='box'>";
echo "<h2>🔧 KONTROLA OPRAVY SMTPAuth:</h2>";
$found = false;
foreach ($lines as $num => $line) {
    if (strpos($line, 'SMTP Authentication - pouze pokud jsou zadány credentials') !== false) {
        $found = true;
        echo "<div style='background:#4ec9b0;color:black;padding:10px;margin:10px 0;'>";
        echo "✅ OPRAVA NALEZENA na řádku " . ($num + 1);
        echo "</div>";
        echo "<pre>";
        for ($i = $num - 2; $i < $num + 12 && $i < count($lines); $i++) {
            $lineNum = $i + 1;
            echo sprintf("%3d: %s\n", $lineNum, htmlspecialchars($lines[$i]));
        }
        echo "</pre>";
        break;
    }
}
if (!$found) {
    echo "<div style='background:#f44747;color:white;padding:10px;margin:10px 0;'>";
    echo "❌ OPRAVA NEBYLA NALEZENA! Deployment možná ještě neproběhl.";
    echo "</div>";
}
echo "</div>";

// Git info
echo "<div class='box'>";
echo "<h2>📦 GIT INFO:</h2>";
$gitLog = shell_exec('cd ' . __DIR__ . ' && git log --oneline -1 api/protokol_api.php 2>&1');
echo "<pre>" . htmlspecialchars($gitLog) . "</pre>";
echo "</div>";

// Jaké commity čekají na deployment?
echo "<div class='box'>";
echo "<h2>🚀 POSLEDNÍ COMMITY:</h2>";
$gitLog = shell_exec('cd ' . __DIR__ . ' && git log --oneline -5 2>&1');
echo "<pre>" . htmlspecialchars($gitLog) . "</pre>";
echo "</div>";

?>

</body></html>
