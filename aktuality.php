<?php
/**
 * Aktuality o značce Natuzzi
 * Zobrazení všech článků ve 2 sloupcích v náhodném pořadí
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Získat aktuality z databáze
try {
    $pdo = getDbConnection();

    // Získat datum které má uživatel zobrazit (default = nejnovější)
    $vybraneDatum = $_GET['datum'] ?? null;

    if ($vybraneDatum) {
        // Pokud je vybrané konkrétní datum, zobrazit články z toho dne
        $stmt = $pdo->prepare("
            SELECT * FROM wgs_natuzzi_aktuality
            WHERE datum = :datum
            LIMIT 1
        ");
        $stmt->execute(['datum' => $vybraneDatum]);
        $hlavniAktualita = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hlavniAktualita) {
            // Pokud datum neexistuje, zobrazit nejnovější
            $stmt = $pdo->query("
                SELECT * FROM wgs_natuzzi_aktuality
                ORDER BY datum DESC
                LIMIT 1
            ");
            $hlavniAktualita = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        // Zobrazit nejnovější aktualitu
        $stmt = $pdo->query("
            SELECT * FROM wgs_natuzzi_aktuality
            ORDER BY datum DESC
            LIMIT 1
        ");
        $hlavniAktualita = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Pokud existuje hlavní aktualita, načíst její obsah
    if ($hlavniAktualita) {
        $datumAktuality = $hlavniAktualita['datum'];

        // Rozdělit obsah na jednotlivé články podle ## nadpisů
        $jazyk = $_GET['lang'] ?? 'cz';
        $jazyk = in_array($jazyk, ['cz', 'en', 'it']) ? $jazyk : 'cz';

        $obsahSloupec = 'obsah_' . $jazyk;
        $celyObsah = $hlavniAktualita[$obsahSloupec] ?? '';

        // Parse článků z markdown obsahu
        $articles = parseClankyzObsahu($celyObsah, $hlavniAktualita['id'], $jazyk);

        // Články se nemíchají při každém načtení - pořadí je dané obsahem z databáze
        // Inteligentní rozdělení do sloupců podle délky se provede níže při zobrazování
    } else {
        $articles = [];
        $datumAktuality = null;
    }

    // Získat seznam posledních 30 aktualit pro archiv
    $stmtArchiv = $pdo->query("
        SELECT datum, svatek_cz
        FROM wgs_natuzzi_aktuality
        ORDER BY datum DESC
        LIMIT 30
    ");
    $archiv = $stmtArchiv->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Chyba při načítání aktualit: " . $e->getMessage());
    $articles = [];
    $archiv = [];
    $datumAktuality = null;
}

// Funkce pro rozdělení obsahu na články (všechny ve 2 sloupcích)
function parseClankyzObsahu($obsah, $aktualitaId, $jazyk) {
    $articles = [];

    // Rozdělit podle ## nadpisů (každý článek začíná ##)
    $parts = preg_split('/(?=^## )/m', $obsah);

    // Počítadlo indexu pouze pro články s ##
    $articleIndex = 0;

    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;

        // První část je hlavní nadpis + úvodní text - přeskočit (nemá ##)
        if (!preg_match('/^## /', $part)) {
            continue;
        }

        // Přidat článek (všechny budou ve 2 sloupcích)
        $articles[] = [
            'obsah' => $part,
            'aktualita_id' => $aktualitaId,
            'jazyk' => $jazyk,
            'index' => $articleIndex
        ];

        $articleIndex++;
    }

    return $articles;
}

$jazyk = $_GET['lang'] ?? 'cz';
$jazyk = in_array($jazyk, ['cz', 'en', 'it']) ? $jazyk : 'cz';
?>
<!DOCTYPE html>
<html lang="<?php echo $jazyk; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="WGS">

  <!-- SEO Meta Tags -->
  <meta name="description" content="Denní aktuality o značce Natuzzi - novinky, tipy na péči o luxusní nábytek, showroomy v ČR. White Glove Service - autorizovaný servisní partner.">
  <meta name="keywords" content="Natuzzi, aktuality, novinky, luxusní nábytek, kožené sedačky, péče o nábytek, White Glove Service">

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title>Aktuality Natuzzi | White Glove Service</title>

  <!-- Preload critical resources -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1920&h=500&fit=crop" as="image" fetchpriority="high">

  <!-- Google Fonts - Natuzzi style -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet"></noscript>

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="preload" href="assets/css/mobile-responsive.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="assets/css/mobile-responsive.min.css"></noscript>

  <style>
    /* Aktuality specifické styly */
    .hero {
      background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1920&h=500&fit=crop');
      background-size: cover;
      background-position: center;
      color: white;
      padding: 100px 20px;
      text-align: center;
      margin-bottom: 40px;
    }

    .hero-title {
      font-size: 3.5em;
      font-weight: 700;
      margin: 0 0 15px 0;
      letter-spacing: -1px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
      text-transform: uppercase;
    }

    .hero-subtitle {
      font-size: 1.4em;
      opacity: 0.95;
      font-weight: 300;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 20px 40px 20px;
    }

    .datum-badge {
      background: #1a1a1a;
      color: white;
      padding: 12px 30px;
      border-radius: 30px;
      display: inline-block;
      font-weight: 600;
      font-size: 1em;
    }

    .datum-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      gap: 20px;
      flex-wrap: wrap;
    }

    .pridat-clanek-btn {
      padding: 12px 30px;
      background: #2D5016;
      color: white;
      border: none;
      border-radius: 30px;
      cursor: pointer;
      font-weight: 600;
      font-size: 1em;
      transition: all 0.3s;
      white-space: nowrap;
    }

    .pridat-clanek-btn:hover {
      background: #1a300d;
      transform: scale(1.05);
    }

    /* ŠIROKÝ ČLÁNEK */
    .siroky-clanek {
      background: white;
      padding: 35px 40px;
      margin-bottom: 40px;
      border: 2px solid #1a1a1a;
      border-radius: 10px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.1);
      position: relative;
      height: auto;  /* Výška podle obsahu */
    }

    .siroky-clanek h2 {
      color: #1a1a1a;
      font-size: 2em;
      margin: 0 0 20px 0;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      border-bottom: 4px solid #1a1a1a;
      padding-bottom: 15px;
      font-family: 'Poppins', sans-serif;
    }

    .siroky-clanek p {
      font-size: 1.1em;
      line-height: 1.8;
      color: #333;
      margin: 0 0 15px 0;
    }

    .siroky-clanek a {
      color: #1a1a1a;
      text-decoration: underline;
      font-weight: 600;
      transition: all 0.2s;
      margin-right: 15px;
    }

    .siroky-clanek a:hover {
      color: #666666;
    }

    /* DVA SLOUPCE S ODSKOČENÝM PRAVÝM SLOUPCEM */
    .clanky-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      margin-bottom: 40px;
      align-items: start;
    }

    /* Levý sloupec - články těsně pod sebou */
    .column-left {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    /* Pravý sloupec - články těsně pod sebou s odskočeným začátkem */
    .column-right {
      display: flex;
      flex-direction: column;
      gap: 0;
      margin-top: 250px; /* Odskok - první článek začíná u 2/3 prvního článku vlevo */
    }

    @media (max-width: 968px) {
      .clanky-grid {
        grid-template-columns: 1fr;
        gap: 0;
      }

      .column-right {
        margin-top: 0; /* Na mobilu bez odskoku */
      }
    }

    /* Každý normální článek je samostatný blok */
    .clanek-card {
      background: white;
      padding: 10px;
      border: none;
      position: relative;
      transition: all 0.3s;
      height: auto;
      margin: 0;
      border-bottom: none;
    }

    /* První článek v levém sloupci */
    .column-left .clanek-card:first-child {
      border-top-left-radius: 8px;
      border-top-right-radius: 8px;
    }

    /* Poslední článek v levém sloupci */
    .column-left .clanek-card:last-child {
      border-bottom-left-radius: 8px;
      border-bottom-right-radius: 8px;
      border-bottom: 1px solid #e0e0e0;
    }

    /* První článek v pravém sloupci */
    .column-right .clanek-card:first-child {
      border-top-left-radius: 8px;
      border-top-right-radius: 8px;
    }

    /* Poslední článek v pravém sloupci */
    .column-right .clanek-card:last-child {
      border-bottom-left-radius: 8px;
      border-bottom-right-radius: 8px;
      border-bottom: 1px solid #e0e0e0;
    }

    .clanek-card:hover {
      background: #fafafa;
    }

    .clanek-obsah {
      font-size: 0.95em;
      line-height: 1.6;
      color: #333;
      font-family: Georgia, 'Times New Roman', serif;
    }

    .clanek-obsah h2 {
      color: #1a1a1a;
      font-size: 1.4em;
      margin: 0 0 15px 0;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      border-bottom: 3px solid #000;
      padding-bottom: 10px;
      font-family: 'Poppins', sans-serif;
    }

    .clanek-obsah h3 {
      color: #333333;
      font-size: 1.2em;
      margin: 20px 0 12px 0;
      font-weight: 600;
    }

    .clanek-obsah p {
      margin: 0 0 12px 0;
      text-align: justify;
    }

    .clanek-obsah strong {
      color: #1a1a1a;
      font-weight: 700;
    }

    .clanek-obsah a {
      color: #1a1a1a;
      text-decoration: underline;
      font-weight: 600;
      transition: all 0.2s;
    }

    .clanek-obsah a:hover {
      color: #666666;
    }

    .clanek-obsah img {
      width: 100%;
      height: auto;
      display: block;
      margin: 15px 0;
      border: 1px solid #ddd;
      border-radius: 5px;
      aspect-ratio: 4 / 3;
      object-fit: cover;
    }

    /* Admin tlačítko pro každý článek */
    .admin-edit-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      padding: 8px 16px;
      background: #1a1a1a;
      color: white;
      border: none;
      border-radius: 20px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.85em;
      transition: all 0.3s;
      z-index: 10;
    }

    .admin-edit-btn:hover {
      background: #333;
      transform: scale(1.05);
    }

    .archiv-section {
      background: white;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-top: 40px;
    }

    .archiv-section h3 {
      color: #1a1a1a;
      font-size: 1.5em;
      margin: 0 0 20px 0;
      font-weight: 600;
    }

    .archiv-link {
      display: block;
      padding: 12px 15px;
      margin: 8px 0;
      background: #f5f5f7;
      border-radius: 8px;
      text-decoration: none;
      color: #333;
      transition: all 0.3s;
      border-left: 4px solid transparent;
    }

    .archiv-link:hover {
      background: #333333;
      color: white;
      border-left-color: #1a1a1a;
      transform: translateX(5px);
    }

    .archiv-link.active {
      background: #1a1a1a;
      color: white;
      border-left-color: #000000;
      font-weight: 600;
    }

    @media (max-width: 768px) {
      .hero {
        padding: 60px 20px;
      }

      .hero-title {
        font-size: 2em;
      }

      .siroky-clanek {
        padding: 25px 20px;
      }

      .clanek-card {
        padding: 20px;
      }
    }
  </style>

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>
<body>

<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<!-- HERO SEKCE S FOTKOU -->
<main>
<section class="hero">
  <div class="hero-content">
    <h1 class="hero-title"
        data-lang-cs="Aktuality"
        data-lang-en="News"
        data-lang-it="Notizie">
        <?php
        echo $jazyk === 'en' ? 'News' : ($jazyk === 'it' ? 'Notizie' : 'Aktuality');
        ?>
    </h1>
    <div class="hero-subtitle"
         data-lang-cs="Denní novinky o luxusním italském nábytku"
         data-lang-en="Daily news about luxury Italian furniture"
         data-lang-it="Notizie quotidiane sui mobili italiani di lusso">
        <?php
        echo $jazyk === 'en' ? 'Daily news about luxury Italian furniture' :
             ($jazyk === 'it' ? 'Notizie quotidiane sui mobili italiani di lusso' :
              'Denní novinky o luxusním italském nábytku');
        ?>
    </div>
  </div>
</section>

<!-- OBSAH AKTUALIT -->
<section class="content-section">
  <div class="container">

    <?php if (!empty($articles)): ?>

      <div class="datum-bar">
        <div class="datum-badge">
          <?php
          // Překlad "Datum:"
          echo $jazyk === 'en' ? 'Date: ' : ($jazyk === 'it' ? 'Data: ' : 'Datum: ');
          echo date('d.m.Y', strtotime($datumAktuality));
          ?>
          <?php if ($hlavniAktualita && $hlavniAktualita['svatek_cz']): ?>
            | <?php
            echo $jazyk === 'en' ? 'Name Day' : ($jazyk === 'it' ? 'Onomastico' : 'Svátek');
            ?>: <?php echo htmlspecialchars($hlavniAktualita['svatek_cz']); ?>
          <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
          <button class="pridat-clanek-btn" onclick="pridatNovyClanek()">
            Přidat nový článek
          </button>
        <?php endif; ?>
      </div>

      <!-- GRID SE 2 SLOUPCI VŠECH ČLÁNKŮ S INTELIGENTNÍM VYVÁŽENÍM -->
      <div class="clanky-grid">
        <?php
        // INTELIGENTNÍ ROZDĚLENÍ ČLÁNKŮ podle délky textu
        // Spočítat délku každého článku
        foreach ($articles as $key => $article) {
            $articles[$key]['delka'] = strlen($article['obsah']);
        }

        // Seřadit podle délky (sestupně) pro lepší rozdělení
        usort($articles, function($a, $b) {
            return $b['delka'] - $a['delka'];
        });

        // Rozdělit na 2 sloupce tak, aby celková délka byla vyrovnaná
        $levySloupec = [];
        $pravySloupec = [];
        $levaSuma = 0;
        $pravaSuma = 0;

        foreach ($articles as $article) {
            // Přidat do sloupce s menší celkovou délkou
            if ($levaSuma <= $pravaSuma) {
                $levySloupec[] = $article;
                $levaSuma += $article['delka'];
            } else {
                $pravySloupec[] = $article;
                $pravaSuma += $article['delka'];
            }
        }

        // Zobrazit oba sloupce
        ?>
        <div class="column-left">
          <?php foreach ($levySloupec as $clanek): ?>
            <div class="clanek-card" data-aktualita-id="<?php echo $clanek['aktualita_id']; ?>" data-jazyk="<?php echo $clanek['jazyk']; ?>" data-index="<?php echo $clanek['index']; ?>">
              <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
                <button class="admin-edit-btn" onclick="upravitClanek(<?php echo $clanek['aktualita_id']; ?>, '<?php echo $clanek['jazyk']; ?>', <?php echo $clanek['index']; ?>)">
                  Upravit článek
                </button>
              <?php endif; ?>
              <div class="clanek-obsah">
                <?php echo parseMarkdownToHTML($clanek['obsah']); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="column-right">
          <?php foreach ($pravySloupec as $clanek): ?>
            <div class="clanek-card" data-aktualita-id="<?php echo $clanek['aktualita_id']; ?>" data-jazyk="<?php echo $clanek['jazyk']; ?>" data-index="<?php echo $clanek['index']; ?>">
              <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
                <button class="admin-edit-btn" onclick="upravitClanek(<?php echo $clanek['aktualita_id']; ?>, '<?php echo $clanek['jazyk']; ?>', <?php echo $clanek['index']; ?>)">
                  Upravit článek
                </button>
              <?php endif; ?>
              <div class="clanek-obsah">
                <?php echo parseMarkdownToHTML($clanek['obsah']); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (!empty($archiv) && count($archiv) > 1): ?>
        <div class="archiv-section">
          <h3><?php
            echo $jazyk === 'en' ? 'News Archive' : ($jazyk === 'it' ? 'Archivio Notizie' : 'Archiv aktualit');
            ?>
          </h3>
          <?php foreach (array_slice($archiv, 0, 10) as $polozka): ?>
            <a href="?datum=<?php echo $polozka['datum']; ?>&lang=<?php echo $jazyk; ?>"
               class="archiv-link <?php echo $polozka['datum'] === $datumAktuality ? 'active' : ''; ?>">
              <?php echo date('d.m.Y', strtotime($polozka['datum'])); ?>
              <?php if ($polozka['svatek_cz']): ?>
                - <?php echo htmlspecialchars($polozka['svatek_cz']); ?>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>

      <div style="background: white; padding: 40px; border-radius: 10px; text-align: center;">
        <h2><?php
        echo $jazyk === 'en' ? 'No news available' : ($jazyk === 'it' ? 'Nessuna notizia disponibile' : 'Žádné aktuality');
        ?></h2>
        <p>
          <?php
          echo $jazyk === 'en' ?
            'Currently, no news is available. The system will automatically generate the first news tomorrow at 6:00 AM.' :
            ($jazyk === 'it' ?
              'Attualmente non ci sono notizie disponibili. Il sistema genererà automaticamente le prime notizie domani alle 6:00.' :
              'Momentálně nejsou k dispozici žádné aktuality. Systém automaticky vygeneruje první aktualitu zítra v 6:00 ráno.');
          ?>
        </p>
      </div>

    <?php endif; ?>

  </div>
</section>
</main>

<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
<!-- ADMIN EDITOR CELÝCH ČLÁNKŮ -->
<script>
(function() {
  'use strict';

  // CSRF token
  const csrfToken = '<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>';

  // Globální funkce pro editaci článku
  window.upravitClanek = function(aktualitaId, jazyk, index) {
    otevritEditorClanku(aktualitaId, jazyk, index);
  };

  // Globální funkce pro přidání nového článku
  window.pridatNovyClanek = function() {
    // Získat ID aktuální aktuality (nejnovější)
    const sirokyArticle = document.querySelector('[data-aktualita-id]');
    if (!sirokyArticle) {
      alert('Nepodařilo se najít ID aktuality');
      return;
    }
    const aktualitaId = sirokyArticle.dataset.aktualitaId;
    const jazyk = 'cz';

    // Otevřít editor s prázdnými poli, index -1 znamená nový článek
    zobrazitEditor(aktualitaId, jazyk, -1, '');
  };

  function otevritEditorClanku(aktualitaId, jazyk, index) {
    // Získat aktuální markdown obsah z databáze (celý obsah všech článků)
    fetch(`/api/nacti_aktualitu.php?id=${aktualitaId}&jazyk=${jazyk}&index=${index}`)
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          zobrazitEditor(aktualitaId, jazyk, index, data.obsah);
        } else {
          alert('Chyba při načítání: ' + data.message);
        }
      })
      .catch(e => alert('Síťová chyba: ' + e.message));
  }

  function zobrazitEditor(aktualitaId, jazyk, index, aktualniObsah) {
    // Parsovat markdown do polí formuláře
    const parsovanaData = parseMarkdownDoFormulare(aktualniObsah);

    // Vytvořit velký editor dialog
    const editorDialog = document.createElement('div');
    editorDialog.style.cssText = `
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
      z-index: 10000;
      width: 90%;
      max-width: 900px;
      max-height: 90vh;
      overflow-y: auto;
    `;

    const jeNovyClanek = (index === -1);
    const nadpisEditoru = jeNovyClanek ? 'Přidat nový článek' : 'Upravit článek';

    editorDialog.innerHTML = `
      <h2 style="margin: 0 0 20px 0; color: #1a1a1a;">
        ${nadpisEditoru} - ${jazyk.toUpperCase()}
      </h2>
      <p style="margin: 0 0 20px 0; color: #666; background: #e8f4fd; padding: 12px; border-radius: 5px; border-left: 4px solid #0066cc;">
        Jednoduše vyplňte pole níže. Nemusíte nic formátovat - prostě napište text. Všechny články jsou zobrazeny ve 2 sloupcích.
      </p>

      <!-- NADPIS -->
      <div style="margin-bottom: 20px;">
        <label style="display: block; font-weight: bold; color: #333; margin-bottom: 8px; font-size: 14px;">
          Hlavní nadpis článku:
        </label>
        <input type="text" id="nadpisArticle" placeholder="např. NOVINKY O ZNAČCE NATUZZI" style="
          width: 100%;
          padding: 12px;
          border: 2px solid #ddd;
          border-radius: 5px;
          font-size: 14px;
          box-sizing: border-box;
        ">
      </div>

      <!-- TEXT ČLÁNKU -->
      <div style="margin-bottom: 20px;">
        <label style="display: block; font-weight: bold; color: #333; margin-bottom: 8px; font-size: 14px;">
          Hlavní text článku (napište normálně, jako do Wordu):
        </label>
        <textarea id="textArticle" placeholder="Napište text vašeho článku... Prostě pište normálně, nemusíte nic formátovat." style="
          width: 100%;
          padding: 12px;
          border: 2px solid #ddd;
          border-radius: 5px;
          font-size: 14px;
          box-sizing: border-box;
          min-height: 200px;
          resize: vertical;
          font-family: Arial, sans-serif;
          line-height: 1.6;
        "></textarea>
      </div>

      <!-- SEKCE NADPIS -->
      <div style="background: #1a1a1a; color: white; padding: 12px 20px; margin: 25px 0 15px 0; border-radius: 5px; font-weight: bold;">
        ODKAZY (volitelné)
      </div>

      <!-- ODKAZ 1 -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
        <div>
          <label style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">ODKAZ 1 - Text odkazu:</label>
          <input type="text" id="odkaz1Text" placeholder="např. Více informací" style="
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
          ">
        </div>
        <div>
          <label style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">ODKAZ 1 - URL adresa:</label>
          <input type="text" id="odkaz1Url" placeholder="např. https://www.natuzzi.cz/info" style="
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
          ">
        </div>
      </div>

      <!-- ODKAZ 2 -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
        <div>
          <label style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">ODKAZ 2 - Text odkazu:</label>
          <input type="text" id="odkaz2Text" placeholder="např. Objednat katalog" style="
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
          ">
        </div>
        <div>
          <label style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">ODKAZ 2 - URL adresa:</label>
          <input type="text" id="odkaz2Url" placeholder="např. https://www.natuzzi.cz/katalog" style="
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
          ">
        </div>
      </div>

      <!-- ODKAZ 3 -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <div>
          <label style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">ODKAZ 3 - Text odkazu:</label>
          <input type="text" id="odkaz3Text" placeholder="např. Kontakt" style="
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
          ">
        </div>
        <div>
          <label style="display: block; font-size: 13px; color: #666; margin-bottom: 5px;">ODKAZ 3 - URL adresa:</label>
          <input type="text" id="odkaz3Url" placeholder="např. https://www.natuzzi.cz/kontakt" style="
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
          ">
        </div>
      </div>

      <!-- SEKCE NADPIS -->
      <div style="background: #1a1a1a; color: white; padding: 12px 20px; margin: 25px 0 15px 0; border-radius: 5px; font-weight: bold;">
        FOTOGRAFIE (volitelná)
      </div>

      <!-- FOTOGRAFIE -->
      <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
        <label style="display: block; font-size: 13px; color: #666; margin-bottom: 8px;">
          Vyberte fotografii:
        </label>
        <input type="file" id="fotkaArticle" accept="image/*" style="
          width: 100%;
          padding: 10px;
          border: 2px solid #ddd;
          border-radius: 5px;
          font-size: 14px;
          box-sizing: border-box;
          background: white;
        ">

        <div id="fotkaPreview" style="margin-top: 10px; display: none;">
          <img id="fotkaPreviewImg" style="max-width: 100%; max-height: 200px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        <div id="fotkaExisting" style="margin-top: 10px; display: none;">
          <p style="font-size: 13px; color: #666; margin-bottom: 5px;">Stávající fotka:</p>
          <img id="fotkaExistingImg" style="max-width: 100%; max-height: 200px; border: 1px solid #ddd; border-radius: 5px;">
          <p style="font-size: 12px; color: #999; margin-top: 5px;">Pokud vyberete novou fotku, nahradí tuto stávající.</p>
        </div>

        <!-- POZICE FOTKY -->
        <div id="fotkaPoziceSection" style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #e0e0e0; display: none;">
          <label style="display: block; font-size: 13px; color: #333; margin-bottom: 10px; font-weight: 600;">
            Kde má být fotka umístěna?
          </label>
          <div style="display: flex; flex-direction: column; gap: 8px;">
            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; background: white; border-radius: 5px; border: 2px solid #ddd; transition: all 0.2s;">
              <input type="radio" name="fotkaPozice" value="nahore" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
              <div>
                <div style="font-weight: 600; color: #333;">Nahoře (pod nadpisem)</div>
                <div style="font-size: 12px; color: #666;">Fotka se zobrazí hned pod nadpisem článku</div>
              </div>
            </label>
            <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; background: white; border-radius: 5px; border: 2px solid #ddd; transition: all 0.2s;">
              <input type="radio" name="fotkaPozice" value="dole" checked style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
              <div>
                <div style="font-weight: 600; color: #333;">Dole (na konci)</div>
                <div style="font-size: 12px; color: #666;">Fotka se zobrazí na konci článku (výchozí)</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <div style="margin-top: 15px; padding: 10px; background: ${jeNovyClanek ? '#d4edda' : '#fff3cd'}; border-left: 4px solid ${jeNovyClanek ? '#28a745' : '#ffc107'}; border-radius: 5px;">
        <strong>${jeNovyClanek ? 'Info:' : 'Pozor:'}</strong> ${jeNovyClanek ? 'Nový článek bude přidán na konec seznamu článků.' : 'Tato změna přepíše celý obsah článku v jazyce <strong>' + jazyk.toUpperCase() + '</strong>.'}
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
        <button id="cancelEditorBtn" style="
          padding: 14px 28px;
          background: #6c757d;
          color: white;
          border: none;
          border-radius: 5px;
          cursor: pointer;
          font-weight: 600;
          font-size: 15px;
        ">
          Zrušit
        </button>
        <button id="saveEditorBtn" style="
          padding: 14px 28px;
          background: #28a745;
          color: white;
          border: none;
          border-radius: 5px;
          cursor: pointer;
          font-weight: 600;
          font-size: 15px;
        ">
          ${jeNovyClanek ? 'Přidat článek' : 'Uložit článek'}
        </button>
      </div>
    `;

    // Overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      z-index: 9999;
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(editorDialog);

    // Vyplnit pole z parsovaných dat
    document.getElementById('nadpisArticle').value = parsovanaData.nadpis;
    document.getElementById('textArticle').value = parsovanaData.text;
    document.getElementById('odkaz1Text').value = parsovanaData.odkazy[0]?.text || '';
    document.getElementById('odkaz1Url').value = parsovanaData.odkazy[0]?.url || '';
    document.getElementById('odkaz2Text').value = parsovanaData.odkazy[1]?.text || '';
    document.getElementById('odkaz2Url').value = parsovanaData.odkazy[1]?.url || '';
    document.getElementById('odkaz3Text').value = parsovanaData.odkazy[2]?.text || '';
    document.getElementById('odkaz3Url').value = parsovanaData.odkazy[2]?.url || '';

    // Pokud má stávající fotku, zobrazit ji a sekci s pozicí
    if (parsovanaData.fotka) {
      document.getElementById('fotkaExisting').style.display = 'block';
      document.getElementById('fotkaExistingImg').src = parsovanaData.fotka;
      document.getElementById('fotkaPoziceSection').style.display = 'block';

      // Nastavit správnou pozici fotky z parsovaných dat
      const poziceRadio = document.querySelector(`input[name="fotkaPozice"][value="${parsovanaData.fotkaPozice}"]`);
      if (poziceRadio) {
        poziceRadio.checked = true;
      }
    }

    // Náhled nové fotky při výběru
    document.getElementById('fotkaArticle').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
          document.getElementById('fotkaPreview').style.display = 'block';
          document.getElementById('fotkaPreviewImg').src = event.target.result;
          // Zobrazit sekci s pozicí fotky
          document.getElementById('fotkaPoziceSection').style.display = 'block';
        };
        reader.readAsDataURL(file);
      } else {
        document.getElementById('fotkaPreview').style.display = 'none';
        // Skrýt sekci s pozicí pokud není žádná fotka
        if (!parsovanaData.fotka) {
          document.getElementById('fotkaPoziceSection').style.display = 'none';
        }
      }
    });

    // Focus na nadpis
    document.getElementById('nadpisArticle').focus();

    // Zavřít editor
    function zavritEditor() {
      overlay.remove();
      editorDialog.remove();
    }

    document.getElementById('cancelEditorBtn').addEventListener('click', zavritEditor);
    overlay.addEventListener('click', zavritEditor);

    // Uložit změny
    document.getElementById('saveEditorBtn').addEventListener('click', async function() {
      const nadpis = document.getElementById('nadpisArticle').value.trim();
      const text = document.getElementById('textArticle').value.trim();

      if (!nadpis) {
        alert('Musíte vyplnit nadpis článku!');
        return;
      }

      if (!text) {
        alert('Musíte vyplnit text článku!');
        return;
      }

      // Zjistit zda má novou fotku nebo ponechat stávající
      const fotkaInput = document.getElementById('fotkaArticle');
      const maNovoufotku = fotkaInput.files.length > 0;

      // Pokud nemá novou fotku, ponechat URL stávající (pokud existuje)
      let fotkaUrl = parsovanaData.fotka || null;

      // Pokud má novou fotku, použít placeholder - nahradí se po uploadu
      if (maNovoufotku) {
        fotkaUrl = 'PLACEHOLDER_NEW_PHOTO';
      }

      // Získat vybranou pozici fotky (výchozí = dole)
      const fotkaPoziceRadio = document.querySelector('input[name="fotkaPozice"]:checked');
      const fotkaPozice = fotkaPoziceRadio ? fotkaPoziceRadio.value : 'dole';

      // Sestavit markdown z polí formuláře
      const novyObsah = parseFormularDoMarkdown({
        nadpis: nadpis,
        text: text,
        odkazy: [
          {
            text: document.getElementById('odkaz1Text').value.trim(),
            url: document.getElementById('odkaz1Url').value.trim()
          },
          {
            text: document.getElementById('odkaz2Text').value.trim(),
            url: document.getElementById('odkaz2Url').value.trim()
          },
          {
            text: document.getElementById('odkaz3Text').value.trim(),
            url: document.getElementById('odkaz3Url').value.trim()
          }
        ],
        fotka: fotkaUrl,
        fotkaPozice: fotkaPozice
      });

      const confirmMessage = jeNovyClanek
        ? 'Opravdu chcete přidat tento nový článek?'
        : `Opravdu chcete uložit změny?\n\nPřepíše se celý obsah článku v jazyce ${jazyk.toUpperCase()}.`;

      if (!confirm(confirmMessage)) {
        return;
      }

      this.disabled = true;
      this.textContent = jeNovyClanek ? 'Přidávám...' : 'Ukládám...';

      try {
        const response = await ulozitCelyClanek(aktualitaId, jazyk, index, novyObsah, maNovoufotku ? fotkaInput.files[0] : null);

        if (response.status === 'success') {
          const successMsg = jeNovyClanek
            ? 'Nový článek byl úspěšně přidán!\n\nStránka se nyní obnoví.'
            : 'Článek byl úspěšně uložen!\n\nStránka se nyní obnoví.';
          alert(successMsg);
          window.location.reload();
        } else {
          alert('Chyba při ukládání: ' + response.message);
          this.disabled = false;
          this.textContent = jeNovyClanek ? 'Přidat článek' : 'Uložit článek';
        }
      } catch (error) {
        alert('Síťová chyba: ' + error.message);
        this.disabled = false;
        this.textContent = jeNovyClanek ? 'Přidat článek' : 'Uložit článek';
      }
    });
  }

  // Parsovat markdown do objektu s poli formuláře
  function parseMarkdownDoFormulare(markdown) {
    const result = {
      nadpis: '',
      text: '',
      odkazy: [],
      fotka: null,
      fotkaPozice: 'dole'  // Výchozí pozice
    };

    // Kontrola zda je široký článek
    const jeSiroky = /^## ŠIROKÝ:/im.test(markdown);
    result.jeSiroky = jeSiroky;

    // Získat nadpis (po ##, odstranit ŠIROKÝ: pokud existuje)
    const nadpisMatch = markdown.match(/^## (?:ŠIROKÝ:\s*)?(.+)$/m);
    if (nadpisMatch) {
      result.nadpis = nadpisMatch[1].trim();
    }

    // Odstranit nadpis z textu pro další zpracování
    let zbyvajiciText = markdown.replace(/^## (?:ŠIROKÝ:\s*)?(.+)$/m, '').trim();

    // Parsovat fotku (pokud existuje) a detekovat její pozici - ![alt](url)
    const fotkaMatch = zbyvajiciText.match(/!\[([^\]]*)\]\(([^)]+)\)/);
    if (fotkaMatch) {
      result.fotka = fotkaMatch[2]; // URL fotky

      // Zjistit pozici fotky v textu
      const fotkaIndex = zbyvajiciText.indexOf(fotkaMatch[0]);
      const textPredFotkou = zbyvajiciText.substring(0, fotkaIndex).trim();
      const textPoFotce = zbyvajiciText.substring(fotkaIndex + fotkaMatch[0].length).trim();

      // Pokud je fotka na začátku (před hlavním textem), nastavit pozici "nahore"
      // Pokud je fotka na konci (za textem), nastavit pozici "dole"
      if (textPredFotkou.length < 50 && textPoFotce.length > textPredFotkou.length) {
        result.fotkaPozice = 'nahore';
      } else {
        result.fotkaPozice = 'dole';
      }

      // Odstranit fotku z markdownu pro další zpracování
      zbyvajiciText = zbyvajiciText.replace(fotkaMatch[0], '').trim();
    }

    // Extrahovat odkazy (na konci textu)
    const odkazyPattern = /\[([^\]]+)\]\(([^)]+)\)/g;
    const nalezeneOdkazy = [];
    let odkazMatch;

    while ((odkazMatch = odkazyPattern.exec(zbyvajiciText)) !== null) {
      nalezeneOdkazy.push({
        text: odkazMatch[1],
        url: odkazMatch[2],
        fullMatch: odkazMatch[0]
      });
    }

    // Odstranit odkazy a oddělovače z textu
    nalezeneOdkazy.forEach(odkaz => {
      zbyvajiciText = zbyvajiciText.replace(odkaz.fullMatch, '');
    });
    zbyvajiciText = zbyvajiciText.replace(/\s*\|\s*/g, '').trim();

    result.text = zbyvajiciText;
    result.odkazy = nalezeneOdkazy.map(o => ({ text: o.text, url: o.url }));

    return result;
  }

  // Převést data z formuláře na markdown
  function parseFormularDoMarkdown(data) {
    let markdown = '';

    // Nadpis (všechny články normální, bez ŠIROKÝ:)
    markdown = '## ' + data.nadpis + '\n\n';

    // Pokud má být fotka NAHOŘE (pod nadpisem)
    if (data.fotka && data.fotkaPozice === 'nahore') {
      markdown += `![Fotka článku](${data.fotka})\n\n`;
    }

    // Text
    markdown += data.text + '\n\n';

    // Přidat odkazy pokud existují
    const platneOdkazy = data.odkazy.filter(o => o.text && o.url);
    if (platneOdkazy.length > 0) {
      const odkazyText = platneOdkazy
        .map(o => `[${o.text}](${o.url})`)
        .join(' | ');
      markdown += odkazyText + '\n\n';
    }

    // Pokud má být fotka DOLE (na konci článku)
    if (data.fotka && data.fotkaPozice === 'dole') {
      markdown += `![Fotka článku](${data.fotka})`;
    }

    return markdown.trim();
  }

  async function ulozitCelyClanek(aktualitaId, jazyk, index, novyObsah, fotkaFile) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('aktualita_id', aktualitaId);
    formData.append('jazyk', jazyk);
    formData.append('index', index);
    formData.append('novy_obsah', novyObsah);

    // Přidat fotku pokud byla vybrána
    if (fotkaFile) {
      formData.append('fotka', fotkaFile);
    }

    const response = await fetch('/api/uprav_celou_aktualitu.php', {
      method: 'POST',
      body: formData
    });

    return await response.json();
  }
})();
</script>
<?php endif; ?>

<script src="assets/js/hamburger-menu.js" defer></script>

</body>
</html>

<?php
/**
 * Převede Markdown na HTML
 */
function parseMarkdownToHTML(string $text): string
{
    // Odstranit "ŠIROKÝ:" z nadpisu
    $text = preg_replace('/^## ŠIROKÝ:\s*/m', '## ', $text);

    // Obrázky (před odkazy!) - přidat width/height pro CLS
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" width="800" height="600" loading="lazy">', $text);

    // Nadpisy
    $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);

    // Tučný text
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

    // Odkazy
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);

    // Odstavce (dvojitý enter = nový odstavec)
    $lines = explode("\n", $text);
    $html = '';
    $inParagraph = false;

    foreach ($lines as $line) {
        $line = trim($line);

        // Přeskočit prázdné řádky
        if (empty($line)) {
            if ($inParagraph) {
                $html .= '</p>';
                $inParagraph = false;
            }
            continue;
        }

        // Pokud je to nadpis nebo obrázek, nepřidávat <p>
        if (preg_match('/^<(h[1-6]|img)/', $line)) {
            if ($inParagraph) {
                $html .= '</p>';
                $inParagraph = false;
            }
            $html .= $line . "\n";
        } else {
            if (!$inParagraph) {
                $html .= '<p>';
                $inParagraph = true;
            } else {
                $html .= ' ';
            }
            $html .= $line;
        }
    }

    if ($inParagraph) {
        $html .= '</p>';
    }

    return $html;
}
?>
