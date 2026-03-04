<?php
/**
 * Čištění: Odstranění všech externích (Unsplash) obrázků z aktualit
 *
 * Odstraní markdown obrázky ve tvaru ![alt](https://images.unsplash.com/...)
 * z tabulky wgs_natuzzi_aktuality ve sloupcích obsah_cz, obsah_en, obsah_it.
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('PŘÍSTUP ODEPŘEN: Pouze administrátor.');
}

$pdo = getDbConnection();

// Načíst všechny záznamy
$zaznamy = $pdo->query("SELECT id, obsah_cz, obsah_en, obsah_it FROM wgs_natuzzi_aktuality")->fetchAll(PDO::FETCH_ASSOC);

$celkovyPocetNahrad = 0;
$nahledZmen = [];

// Regex: odstraní řádek s markdown obrázkem odkazujícím na unsplash
$vzor = '/!\[[^\]]*\]\(https:\/\/images\.unsplash\.com\/[^\)]*\)\n?/';

foreach ($zaznamy as $zaznam) {
    $zmeny = [];
    foreach (['obsah_cz', 'obsah_en', 'obsah_it'] as $sloupec) {
        $puvodni = $zaznam[$sloupec] ?? '';
        $pocet = 0;
        $nove = preg_replace($vzor, '', $puvodni, -1, $pocet);
        if ($pocet > 0) {
            $zmeny[$sloupec] = ['puvodni' => $puvodni, 'nove' => $nove, 'pocet' => $pocet];
            $celkovyPocetNahrad += $pocet;
        }
    }
    if (!empty($zmeny)) {
        $nahledZmen[$zaznam['id']] = $zmeny;
    }
}

$provest = isset($_GET['provest']) && $_GET['provest'] === '1';

if ($provest && !empty($nahledZmen)) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE wgs_natuzzi_aktuality SET obsah_cz = :cz, obsah_en = :en, obsah_it = :it WHERE id = :id");
        foreach ($nahledZmen as $id => $zmeny) {
            // Načíst aktuální hodnoty znovu (pro sloupce bez změn)
            $radek = $pdo->query("SELECT obsah_cz, obsah_en, obsah_it FROM wgs_natuzzi_aktuality WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            $stmt->execute([
                'cz' => $zmeny['obsah_cz']['nove'] ?? $radek['obsah_cz'],
                'en' => $zmeny['obsah_en']['nove'] ?? $radek['obsah_en'],
                'it' => $zmeny['obsah_it']['nove'] ?? $radek['obsah_it'],
                'id' => $id,
            ]);
        }
        $pdo->commit();
        $uspech = true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $chyba = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Čištění Unsplash obrázků</title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
    .box { background: #fff; padding: 24px; margin-bottom: 16px; border: 1px solid #ddd; }
    h1 { font-size: 1.2rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 1rem; }
    .ok   { background: #f0f8f0; border-color: #aaa; padding: 12px; margin-bottom: 8px; }
    .warn { background: #fff8e0; border-color: #ccc; padding: 12px; margin-bottom: 8px; }
    .btn  { display: inline-block; padding: 10px 28px; background: #333; color: #fff; text-decoration: none; font-size: .9rem; letter-spacing: .05em; cursor: pointer; border: none; }
    .btn:hover { background: #000; }
    pre { font-size: .78rem; background: #f5f5f5; padding: 8px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
    .pocet { font-weight: 700; }
  </style>
</head>
<body>
<div class="box">
  <h1>Čištění Unsplash obrázků z aktualit</h1>

  <?php if (isset($uspech)): ?>
    <div class="ok">
      <strong>Hotovo.</strong> Odstraněno <?php echo $celkovyPocetNahrad; ?> Unsplash obrázků
      z <?php echo count($nahledZmen); ?> záznamů.
    </div>
    <a href="aktuality.php" class="btn">Zpět na aktuality</a>

  <?php elseif (isset($chyba)): ?>
    <div class="warn"><strong>Chyba:</strong> <?php echo htmlspecialchars($chyba); ?></div>

  <?php elseif (empty($nahledZmen)): ?>
    <div class="ok">Žádné Unsplash obrázky nenalezeny. Databáze je čistá.</div>

  <?php else: ?>
    <p>Nalezeno <span class="pocet"><?php echo $celkovyPocetNahrad; ?> Unsplash obrázků</span>
       v <?php echo count($nahledZmen); ?> záznamech.</p>

    <?php foreach ($nahledZmen as $id => $zmeny): ?>
      <div class="warn">
        <strong>Záznam ID <?php echo $id; ?></strong>
        <?php foreach ($zmeny as $sloupec => $data): ?>
          <br>Sloupec <em><?php echo $sloupec; ?></em>:
          odstraněno <strong><?php echo $data['pocet']; ?></strong> obrázků
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <a href="?provest=1" class="btn">Spustit čištění</a>
  <?php endif; ?>
</div>
</body>
</html>
