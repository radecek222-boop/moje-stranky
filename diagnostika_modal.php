<?php
/**
 * Diagnostika: Všechny CSS/JS/HTML pravidla ovlivňující detailOverlay modal
 */
require_once __DIR__ . '/init.php';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}
header('Content-Type: text/html; charset=utf-8');

$klicovaSlovaCss = [
    'detailOverlay', 'modal-content', 'modal-body', 'modal-overlay',
    'modal-header', 'detail-modal', 'detail-dvousloupce', 'detail-sloupec',
    'detail-textarea', 'rozkryvaci-detail', 'accordion-pouze-mobil'
];

$klicovaSlovaJs = [
    'detailOverlay', 'modal-content', 'modal-body', 'max-width.*modal',
    'obsahDetailZakaznika', 'detail-dvousloupce', 'detail-sloupec',
    'showCustomerDetail', 'ModalManager'
];

function hledejVSouboru(string $soubor, array $klice): array {
    if (!file_exists($soubor)) return [];
    $radky = file($soubor, FILE_IGNORE_NEW_LINES);
    $vysledky = [];
    foreach ($radky as $cislo => $radek) {
        foreach ($klice as $klic) {
            if (preg_match('/' . $klic . '/i', $radek)) {
                $vysledky[] = ['radek' => $cislo + 1, 'obsah' => trim($radek), 'klic' => $klic];
                break;
            }
        }
    }
    return $vysledky;
}

$cssFiles = [
    'assets/css/seznam.css',
    'assets/css/seznam.min.css',
    'assets/css/styles.css',
    'assets/css/styles.min.css',
    'assets/css/wgs-toast.css',
];

$jsFiles = [
    'assets/js/seznam.js',
    'assets/js/utils.js',
];

$htmlFiles = [
    'seznam.php',
];

?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Diagnostika modalu</title>
<style>
body { font-family: monospace; background: #111; color: #eee; padding: 20px; font-size: 13px; }
h1 { color: #39ff14; border-bottom: 1px solid #39ff14; padding-bottom: 8px; }
h2 { color: #0099ff; margin-top: 30px; border-bottom: 1px solid #333; }
h3 { color: #aaa; margin: 15px 0 5px; }
.blok { background: #1a1a1a; border: 1px solid #333; border-radius: 4px; margin-bottom: 8px; padding: 8px 12px; }
.radek-cislo { color: #666; min-width: 50px; display: inline-block; }
.klic { color: #39ff14; font-size: 11px; margin-left: 8px; }
.obsah { color: #fff; }
.soubor { color: #0099ff; }
.prazdny { color: #555; font-style: italic; }
</style>
</head>
<body>
<h1>Diagnostika: detailOverlay modal</h1>
<p style="color:#aaa">Vyhledávání pravidel ve všech souborech...</p>

<h2>CSS soubory</h2>
<?php foreach ($cssFiles as $soubor): ?>
<h3 class="soubor"><?= $soubor ?></h3>
<?php
$nalezene = hledejVSouboru(__DIR__ . '/' . $soubor, $klicovaCss ?? $klicovaSlovaCss);
if (empty($nalezene)): ?>
<div class="prazdny">— nic nenalezeno —</div>
<?php else: foreach ($nalezene as $n): ?>
<div class="blok">
    <span class="radek-cislo">L<?= $n['radek'] ?></span>
    <span class="obsah"><?= htmlspecialchars($n['obsah']) ?></span>
    <span class="klic">[<?= $n['klic'] ?>]</span>
</div>
<?php endforeach; endif; endforeach; ?>

<h2>JavaScript soubory</h2>
<?php foreach ($jsFiles as $soubor): ?>
<h3 class="soubor"><?= $soubor ?></h3>
<?php
$nalezene = hledejVSouboru(__DIR__ . '/' . $soubor, $klicovaSlovaJs);
if (empty($nalezene)): ?>
<div class="prazdny">— nic nenalezeno —</div>
<?php else: foreach ($nalezene as $n): ?>
<div class="blok">
    <span class="radek-cislo">L<?= $n['radek'] ?></span>
    <span class="obsah"><?= htmlspecialchars($n['obsah']) ?></span>
    <span class="klic">[<?= $n['klic'] ?>]</span>
</div>
<?php endforeach; endif; endforeach; ?>

<h2>HTML/PHP soubory (inline styly)</h2>
<?php foreach ($htmlFiles as $soubor):
$kliceHtml = array_merge($klicovaSlovaCss, ['style=', 'max-width', 'align-items']);
?>
<h3 class="soubor"><?= $soubor ?></h3>
<?php
$nalezene = hledejVSouboru(__DIR__ . '/' . $soubor, $kliceHtml);
if (empty($nalezene)): ?>
<div class="prazdny">— nic nenalezeno —</div>
<?php else: foreach ($nalezene as $n): ?>
<div class="blok">
    <span class="radek-cislo">L<?= $n['radek'] ?></span>
    <span class="obsah"><?= htmlspecialchars($n['obsah']) ?></span>
    <span class="klic">[<?= $n['klic'] ?>]</span>
</div>
<?php endforeach; endif; endforeach; ?>

<h2>Shrnutí klíčových pravidel</h2>
<?php
// Konkrétní extrakce bloků CSS pro modal-content a modal-body z seznam.php
$seznamPhp = file_get_contents(__DIR__ . '/seznam.php');
preg_match_all('/#detailOverlay[^{]*\{[^}]+\}/s', $seznamPhp, $bloky);
echo '<h3 class="soubor">seznam.php — #detailOverlay bloky</h3>';
if (!empty($bloky[0])) {
    foreach ($bloky[0] as $blok) {
        echo '<div class="blok"><span class="obsah">' . nl2br(htmlspecialchars(trim($blok))) . '</span></div>';
    }
}

// Extrakce z seznam.css
$seznamCss = file_get_contents(__DIR__ . '/assets/css/seznam.css');
preg_match_all('/#detailOverlay[^{]*\{[^}]+\}/s', $seznamCss, $bloky2);
echo '<h3 class="soubor">seznam.css — #detailOverlay bloky</h3>';
if (!empty($bloky2[0])) {
    foreach ($bloky2[0] as $blok) {
        echo '<div class="blok"><span class="obsah">' . nl2br(htmlspecialchars(trim($blok))) . '</span></div>';
    }
} else {
    echo '<div class="prazdny">— žádné bloky —</div>';
}
?>

</body>
</html>
