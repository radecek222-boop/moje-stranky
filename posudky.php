<?php
require_once "init.php";

// Přístup pouze pro přihlášené (technici + admini)
$jeLoggedIn = isset($_SESSION['user_id']);
$jeAdmin    = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$jeLoggedIn && !$jeAdmin) {
    header('Location: login.php?redirect=posudky.php');
    exit;
}

// ===== DATA VIET =====
$sekce = [

    'uznavaji' => [
        'nazev'  => 'Chyby výrobce',
        'trida'  => 'uznavaji',
        'vety'   => [
            'Na výrobku byla zjištěna výrobní vada materiálu, která se projevila při běžném používání výrobku.',
            'Zjištěná deformace polstrování přesahuje běžné opotřebení a je posouzena jako vada výrobku.',
            'Poškození mechanismu nebylo způsobeno nesprávným používáním a jedná se o technickou vadu výrobku.',
            'Zjištěné praskání povrchové úpravy materiálu neodpovídá běžnému používání výrobku a je považováno za výrobní vadu.',
            'Rozpárání švu bylo způsobeno nedostatečnou pevností šicího spoje.',
            'Zjištěná závada vznikla bez zjevného mechanického poškození ze strany uživatele.',
            'Stav výrobku neodpovídá obvyklým vlastnostem výrobku při běžném používání.',
            'Konstrukční část výrobku vykazuje nedostatečnou pevnost při standardním zatížení.',
            'Zjištěná závada se projevila v krátké době od dodání výrobku a není způsobena běžným opotřebením.',
            'Na výrobku byla identifikována vada materiálu, která ovlivňuje jeho funkčnost.',
        ],
    ],

    'zamitnute' => [
        'nazev'  => 'Zamítnuté',
        'trida'  => 'zamitnute',
        'vety'   => [
            'Na výrobku byly zjištěny změny odpovídající běžnému opotřebení při používání výrobku.',
            'Zjištěný stav odpovídá přirozeným vlastnostem použitých materiálů.',
            'Nejedná se o výrobní vadu materiálu ani konstrukce výrobku.',
            'Změny materiálu jsou přirozeným důsledkem používání výrobku.',
            'Zjištěné vrásnění materiálu je typickou vlastností pravé kůže.',
            'Změna barvy materiálu odpovídá vystavení výrobku světlu a běžnému používání.',
            'Mírné zvuky mechanismu odpovídají běžnému opotřebení pohyblivých částí.',
            'Zjištěné odchylky v zarovnání prvků jsou v rámci výrobních tolerancí.',
            'Poškození bylo způsobeno vnějšími vlivy nebo nesprávným používáním výrobku.',
            'Zjištěné změny neovlivňují funkčnost výrobku a odpovídají jeho běžnému používání.',
        ],
    ],

    'neutralni' => [
        'nazev'  => 'Neutrální',
        'trida'  => 'neutralni',
        'vety'   => [
            'Výrobek vykazuje změny odpovídající době a způsobu jeho používání.',
            'Příčinu vzniku závady nelze s jistotou přiřadit k výrobní vadě ani k nesprávnému použití.',
            'Závada vznikla v průběhu záruční doby výrobku.',
            'K výrobku nebylo přiloženo dokumentace o způsobu používání nebo údržby.',
            'Výrobek byl předán k odborné kontrole za účelem stanovení příčiny vzniku závady.',
            'Posouzení závady vyžaduje laboratorní analýzu použitého materiálu.',
            'Výrobek vykazuje znaky odpovídající délce jeho provozu a podmínkám používání.',
            'Výrobek bude odeslán výrobci k dalšímu posouzení příčiny závady.',
            'Stav výrobku byl zdokumentován k dalšímu odbornému posouzení.',
            'Na základě prohlídky nelze bez dalšího šetření jednoznačně určit příčinu závady.',
        ],
    ],

];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#111111">
  <meta name="robots" content="noindex, nofollow">
  <title>Posudky | White Glove Service</title>

  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/posudky.min.css" as="style">

  <link rel="stylesheet" href="assets/css/page-transitions.min.css">
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/posudky.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css">
  <link rel="stylesheet" href="assets/css/poppins-font.css">

  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main>
  <div class="posudky-obal">

    <h1 class="posudky-nadpis">Posudky</h1>
    <p class="posudky-popis">Kliknutím na tlačítko zkopírujete větu do schránky.</p>

    <?php foreach ($sekce as $klic => $data): ?>
    <section class="posudky-sekce">

      <div class="sekce-hlavicka">
        <span class="sekce-pruzkum <?= htmlspecialchars($data['trida']) ?>"></span>
        <span class="sekce-nazev"><?= htmlspecialchars($data['nazev']) ?></span>
        <span class="sekce-pocet"><?= count($data['vety']) ?> vět</span>
      </div>

      <div class="vety-seznam">
        <?php foreach ($data['vety'] as $i => $veta): ?>
        <div class="veta-radek">
          <span class="veta-cislo"><?= $i + 1 ?></span>
          <span class="veta-text"><?= htmlspecialchars($veta) ?></span>
          <button
            class="veta-kopirovat"
            data-veta="<?= htmlspecialchars($veta, ENT_QUOTES) ?>"
            type="button"
          >Kopírovat</button>
        </div>
        <?php endforeach; ?>
      </div>

    </section>
    <?php endforeach; ?>

  </div>
</main>

<script>
document.querySelectorAll('.veta-kopirovat').forEach(function(tlacitko) {
  tlacitko.addEventListener('click', function() {
    var veta = this.dataset.veta;
    var btn  = this;

    navigator.clipboard.writeText(veta).then(function() {
      var puvodniText = btn.textContent;
      btn.textContent = 'Skopirovano';
      btn.classList.add('skopirovano');
      setTimeout(function() {
        btn.textContent = puvodniText;
        btn.classList.remove('skopirovano');
      }, 1800);
    }).catch(function() {
      // Fallback pro starší prohlížeče
      var tmp = document.createElement('textarea');
      tmp.value = veta;
      tmp.style.position = 'fixed';
      tmp.style.opacity  = '0';
      document.body.appendChild(tmp);
      tmp.select();
      document.execCommand('copy');
      document.body.removeChild(tmp);

      var puvodniText = btn.textContent;
      btn.textContent = 'Skopirovano';
      btn.classList.add('skopirovano');
      setTimeout(function() {
        btn.textContent = puvodniText;
        btn.classList.remove('skopirovano');
      }, 1800);
    });
  });
});
</script>

</body>
</html>
