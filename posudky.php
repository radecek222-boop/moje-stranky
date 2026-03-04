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
        'nazev'  => 'Schválené',
        'trida'  => 'uznavaji',
        'vety'   => [
            'Při kontrole výrobku byla zjištěna závada, která neodpovídá běžným vlastnostem výrobku při standardním používání.',
            'Zjištěný stav výrobku nasvědčuje výrobní nebo materiálové vadě, která ovlivňuje jeho funkčnost nebo komfort užívání.',
            'Při prohlídce byla zaznamenána deformace sedací části, která přesahuje běžné opotřebení vznikající standardním používáním.',
            'Byla zjištěna závada na mechanismu výrobku, která nebyla způsobena zjevně nesprávným používáním.',
            'Kontrolou bylo potvrzeno, že konstrukční část výrobku nevykazuje odpovídající stabilitu při běžném zatížení.',
            'Byla zaznamenána nedostatečná pevnost šicího spoje, která vedla k rozvolnění nebo rozpárání švu.',
            'Při kontrole bylo zjištěno nerovnoměrné nebo nadměrné změknutí sedacích nebo opěrných částí výrobku.',
            'Na výrobku byla zjištěna závada funkčního mechanismu, která omezuje nebo znemožňuje jeho správné používání.',
            'Závada se projevila v relativně krátké době od dodání výrobku a neodpovídá běžnému opotřebení.',
            'Zjištěný stav výrobku vyžaduje další řešení v rámci reklamačního řízení.',
        ],
    ],

    'zamitnute' => [
        'nazev'  => 'Zamítnuté',
        'trida'  => 'zamitnute',
        'vety'   => [
            'Při kontrole výrobku nebyla zjištěna výrobní ani materiálová vada.',
            'Zjištěný stav odpovídá běžnému opotřebení vznikajícímu při standardním používání výrobku.',
            'Zaznamenané změny vlastností materiálu jsou přirozeným důsledkem používání výrobku.',
            'Mírné změny tuhosti sedacích nebo opěrných částí odpovídají přirozeným vlastnostem použitých materiálů.',
            'Zjištěné vrásnění nebo přirozené deformace materiálu odpovídají charakteru použitých potahových materiálů.',
            'Při kontrole nebyla zjištěna závada funkčních mechanismů výrobku.',
            'Zjištěné drobné zvuky konstrukce nebo mechanismu odpovídají běžnému provozu výrobku.',
            'Zaznamenané odchylky konstrukce jsou v rámci výrobních tolerancí výrobce.',
            'Zjištěné poškození mohlo vzniknout vnějšími vlivy nebo způsobem používání výrobku.',
            'Zjištěný stav výrobku neomezuje jeho funkčnost ani běžné užívání.',
        ],
    ],

    'neutralni' => [
        'nazev'  => 'Neutrální',
        'trida'  => 'neutralni',
        'vety'   => [
            'Stav výrobku byl zdokumentován a předán k dalšímu odbornému posouzení výrobci.',
            'Při kontrole byla zaznamenána reklamovaná závada, jejíž příčina bude dále posouzena výrobcem.',
            'Výrobek byl převzat k odborné kontrole za účelem posouzení příčiny vzniku závady.',
            'Na základě vizuální kontroly nelze jednoznačně určit příčinu vzniku závady bez dalšího posouzení výrobce.',
            'Reklamovaný stav výrobku byl zaznamenán a bude předán výrobci k vyjádření.',
            'Výrobek vykazuje změny odpovídající době a způsobu jeho používání, jejich přesná příčina bude dále posouzena.',
            'Pro stanovení příčiny závady je nutné další technické posouzení výrobce.',
            'Stav výrobku byl zdokumentován pro účely reklamačního řízení.',
            'Reklamace byla přijata a výrobek bude dále posouzen v rámci standardního reklamačního procesu.',
            'Závada byla zaznamenána při kontrole výrobku a bude dále řešena v rámci reklamačního řízení.',
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
