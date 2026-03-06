<?php
/**
 * Diagnostika fotek - kontrola DB záznamy vs soubory na disku
 */

if (file_exists(__DIR__ . '/init.php')) {
    require_once __DIR__ . '/init.php';
} else {
    require_once __DIR__ . '/../init.php';
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('PŘÍSTUP ODEPŘEN - musíte být přihlášen jako administrátor');
}

$pdo = getDbConnection();

// Zjistit strukturu wgs_photos - typ sloupce reklamace_id
$stmtStruktura = $pdo->query("DESCRIBE wgs_photos");
$strukturaFotek = $stmtStruktura->fetchAll(PDO::FETCH_ASSOC);
$celkemVsechFotek = $pdo->query("SELECT COUNT(*) FROM wgs_photos")->fetchColumn();

// Ukázka prvních 5 záznamů RAW (bez joinu) pro diagnostiku
$stmtRaw = $pdo->query("SELECT * FROM wgs_photos ORDER BY id DESC LIMIT 5");
$rawFotky = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);

// Kořenový adresář projektu
$projektDir = dirname(__DIR__);

// Parametry
$reklamaceFilter = $_GET['reklamace_id'] ?? null;
$limitFotek = (int)($_GET['limit'] ?? 50);

// Načíst fotky - zkusíme oba typy joinu (numeric i string reklamace_id)
if ($reklamaceFilter) {
    $stmtR = $pdo->prepare("SELECT id, reklamace_id, cislo, jmeno FROM wgs_reklamace WHERE reklamace_id = :rid OR cislo = :cislo OR id = :nid LIMIT 1");
    $stmtR->execute([':rid' => $reklamaceFilter, ':cislo' => $reklamaceFilter, ':nid' => (int)$reklamaceFilter]);
    $reklamace = $stmtR->fetch(PDO::FETCH_ASSOC);

    if ($reklamace) {
        // Zkusit oba způsoby napojení
        $stmt = $pdo->prepare("SELECT p.* FROM wgs_photos p WHERE p.reklamace_id = :nid OR p.reklamace_id = :sid ORDER BY p.id ASC");
        $stmt->execute([':nid' => $reklamace['id'], ':sid' => $reklamace['reklamace_id']]);
    } else {
        $stmt = $pdo->prepare("SELECT 1 WHERE 0=1");
        $stmt->execute();
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM wgs_photos ORDER BY id DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limitFotek, PDO::PARAM_INT);
    $stmt->execute();
}
$fotky = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiky
$celkem = count($fotky);
$existujici = 0;
$chybejici = 0;

foreach ($fotky as $f) {
    $plnaCesta = $projektDir . '/' . ltrim($f['photo_path'], '/');
    if (file_exists($plnaCesta)) {
        $existujici++;
    } else {
        $chybejici++;
    }
}

// Přehled reklamací - zkusit oba typy joinu
$prehled = [];
try {
    // Varianta A: numeric join
    $stmtA = $pdo->query("SELECT r.id, r.reklamace_id, r.cislo, r.jmeno, COUNT(p.id) as pocet FROM wgs_reklamace r LEFT JOIN wgs_photos p ON CAST(p.reklamace_id AS UNSIGNED) = r.id GROUP BY r.id HAVING pocet > 0 ORDER BY r.id DESC LIMIT 30");
    $prehled = $stmtA->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prehled = [];
}
if (empty($prehled)) {
    // Varianta B: string join
    try {
        $stmtB = $pdo->query("SELECT r.id, r.reklamace_id, r.cislo, r.jmeno, COUNT(p.id) as pocet FROM wgs_reklamace r LEFT JOIN wgs_photos p ON p.reklamace_id = r.reklamace_id GROUP BY r.id HAVING pocet > 0 ORDER BY r.id DESC LIMIT 30");
        $prehled = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $prehled = [];
    }
}

?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Diagnostika fotek</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1100px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #222; }
        h2 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 8px; }
        .stat { display: inline-block; background: #222; color: #fff; padding: 8px 16px; border-radius: 4px; margin: 4px; font-weight: 700; }
        .stat.ok { background: #28a745; }
        .stat.chyba { background: #dc3545; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #333; color: #fff; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        tr.chybi { background: #fff0f0; }
        tr.ok { background: #f0fff0; }
        .cesta { font-family: monospace; font-size: 0.75rem; color: #555; word-break: break-all; }
        .btn { display: inline-block; padding: 8px 16px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; margin: 4px; font-size: 0.85rem; }
        .btn:hover { background: #555; }
        input[type=text] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; width: 300px; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 4px; margin-bottom: 16px; }
        .uploads-info { font-family: monospace; background: #f0f0f0; padding: 10px; border-radius: 4px; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="box">
    <h1>Diagnostika fotek</h1>

    <div class="uploads-info">
        Projekt root: <?= $projektDir ?><br>
        Uploads složka: <?= realpath($projektDir . '/uploads') ?: ($projektDir . '/uploads — NEEXISTUJE') ?><br>
        Existuje: <?= is_dir($projektDir . '/uploads') ? 'ANO' : 'NE — PROBLÉM!' ?><br>
        Obsah uploads: <?= implode(', ', array_diff(scandir($projektDir . '/uploads') ?: [], ['.', '..'])) ?: '(prázdná)' ?>
    </div>

    <div class="uploads-info" style="margin-top:10px;">
        <strong>wgs_photos — celkem záznamů: <?= $celkemVsechFotek ?></strong><br>
        Struktura sloupců: <?= implode(', ', array_column($strukturaFotek, 'Field')) ?>
    </div>

    <?php if (!empty($rawFotky)): ?>
    <div class="uploads-info" style="margin-top:10px;">
        <strong>Ukázka posledních záznamů v wgs_photos:</strong><br>
        <?php foreach ($rawFotky as $r): ?>
        ID: <?= $r['id'] ?> | reklamace_id: "<?= htmlspecialchars((string)$r['reklamace_id']) ?>" | photo_path: <?= htmlspecialchars($r['photo_path'] ?? '—') ?><br>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- FILTR -->
<div class="box">
    <h2>Filtrovat podle zakázky</h2>
    <form method="get">
        <input type="text" name="reklamace_id" value="<?= htmlspecialchars($reklamaceFilter ?? '') ?>" placeholder="WGS číslo, číslo prodejce nebo ID">
        <button type="submit" class="btn">Hledat</button>
        <a href="diagnostika_fotek.php" class="btn">Zobrazit vše</a>
    </form>
</div>

<!-- PŘEHLED ZAKÁZEK S FOTKAMI -->
<div class="box">
    <h2>Zakázky s fotkami v DB (posledních 30)</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>WGS číslo</th>
            <th>Číslo prodejce</th>
            <th>Zákazník</th>
            <th>Fotek v DB</th>
            <th>Akce</th>
        </tr>
        <?php foreach ($prehled as $r): ?>
        <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['reklamace_id'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['cislo'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['jmeno'] ?? '—') ?></td>
            <td><strong><?= $r['pocet'] ?? $r['pocet_fotek_db'] ?></strong></td>
            <td>
                <a href="?reklamace_id=<?= $r['id'] ?>" class="btn">Zkontrolovat</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($prehled)): ?>
        <tr><td colspan="6" style="color:#999;padding:16px;">Žádné záznamy s fotkami v DB</td></tr>
        <?php endif; ?>
    </table>
</div>

<!-- STATISTIKY -->
<?php if ($reklamaceFilter && isset($reklamace)): ?>
<div class="box">
    <h2>Zakázka: <?= htmlspecialchars($reklamace['jmeno'] ?? '') ?> (ID <?= $reklamace['id'] ?>)</h2>
    <p>WGS: <?= htmlspecialchars($reklamace['reklamace_id'] ?? '—') ?> | Prodejce: <?= htmlspecialchars($reklamace['cislo'] ?? '—') ?></p>
</div>
<?php endif; ?>

<div class="box">
    <h2>Statistiky</h2>
    <span class="stat">Celkem fotek v DB: <?= $celkem ?></span>
    <span class="stat ok">Soubory existují: <?= $existujici ?></span>
    <span class="stat chyba">Soubory chybí na disku: <?= $chybejici ?></span>

    <?php if ($chybejici > 0): ?>
    <div class="warning" style="margin-top:16px;">
        <strong>Problém:</strong> <?= $chybejici ?> fotek existuje v databázi, ale fyzický soubor na disku nenalezen.
        <?php if ($chybejici === $celkem): ?>
        <br><strong>Všechny fotky chybí</strong> — pravděpodobně špatná cesta nebo fotky nebyly nahrány na tento server.
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- DETAIL FOTEK -->
<?php if ($celkem > 0): ?>
<div class="box">
    <h2>Detail fotek (<?= $celkem ?>)</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Zakázka</th>
            <th>Sekce</th>
            <th>Cesta v DB</th>
            <th>Soubor na disku</th>
        </tr>
        <?php foreach ($fotky as $f):
            $plnaCesta = $projektDir . '/' . ltrim($f['photo_path'], '/');
            $existuje = file_exists($plnaCesta);
        ?>
        <tr class="<?= $existuje ? 'ok' : 'chybi' ?>">
            <td><?= $f['id'] ?></td>
            <td style="font-size:0.75rem;"><?= htmlspecialchars($f['prodejce_cislo'] ?? $f['wgs_cislo'] ?? $f['reklamace_id']) ?></td>
            <td><?= htmlspecialchars($f['section_name']) ?></td>
            <td class="cesta"><?= htmlspecialchars($f['photo_path']) ?></td>
            <td><?= $existuje ? '<span style="color:#28a745;font-weight:700;">ANO</span>' : '<span style="color:#dc3545;font-weight:700;">CHYBI</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

</body>
</html>
