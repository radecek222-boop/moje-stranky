<?php
/**
 * Aktuality o značce Natuzzi
 * Automaticky generované denní novinky ve třech jazycích
 */

require_once __DIR__ . '/init.php';

// Získat dnešní aktualitu nebo poslední dostupnou
try {
    $pdo = getDbConnection();

    // Zkusit získat aktualitu podle parametru ?datum=
    $zobrazitDatum = $_GET['datum'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT * FROM wgs_natuzzi_aktuality
        WHERE datum = :datum
        LIMIT 1
    ");
    $stmt->execute(['datum' => $zobrazitDatum]);
    $aktualita = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pokud neexistuje, vzít poslední dostupnou
    if (!$aktualita) {
        $stmt = $pdo->query("
            SELECT * FROM wgs_natuzzi_aktuality
            ORDER BY datum DESC
            LIMIT 1
        ");
        $aktualita = $stmt->fetch(PDO::FETCH_ASSOC);
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
    $aktualita = null;
    $archiv = [];
}

// Určit jazyk z URL parametru (default CZ)
$jazyk = $_GET['lang'] ?? 'cz';
$jazyk = in_array($jazyk, ['cz', 'en', 'it']) ? $jazyk : 'cz';

$obsahSloupec = 'obsah_' . $jazyk;
$obsah = $aktualita[$obsahSloupec] ?? '';
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

  <!-- Google Fonts - Natuzzi style -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.css">

  <style>
    /* Aktuality specifické styly */
    .hero {
      background: linear-gradient(135deg, #1a1a1a 0%, #2D5016 100%);
      color: white;
      padding: 80px 20px;
      text-align: center;
      margin-bottom: 0;
    }

    .hero-title {
      font-size: 3em;
      font-weight: 700;
      margin: 0 0 10px 0;
      letter-spacing: -1px;
    }

    .hero-subtitle {
      font-size: 1.2em;
      opacity: 0.9;
      font-weight: 300;
    }

    .lang-switcher {
      background: white;
      padding: 20px;
      text-align: center;
      border-bottom: 1px solid #e0e0e0;
    }

    .lang-btn {
      display: inline-block;
      padding: 10px 25px;
      margin: 0 5px;
      background: white;
      color: #2D5016;
      border: 2px solid #2D5016;
      border-radius: 25px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9em;
      transition: all 0.3s;
      cursor: pointer;
    }

    .lang-btn:hover {
      background: #2D5016;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(45, 80, 22, 0.2);
    }

    .lang-btn.active {
      background: #2D5016;
      color: white;
    }

    .content-section {
      padding: 60px 20px;
      background: #f5f5f7;
    }

    .container {
      max-width: 900px;
      margin: 0 auto;
    }

    .datum-badge {
      background: #2D5016;
      color: white;
      padding: 12px 30px;
      border-radius: 30px;
      display: inline-block;
      margin-bottom: 30px;
      font-weight: 600;
      font-size: 1em;
    }

    .aktualita-card {
      background: white;
      padding: 50px;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-bottom: 40px;
    }

    .aktualita-obsah {
      font-size: 1.05em;
      line-height: 1.8;
      color: #333;
    }

    .aktualita-obsah h1 {
      color: #2D5016;
      font-size: 2.5em;
      margin: 0 0 30px 0;
      font-weight: 700;
      border-bottom: 3px solid #2D5016;
      padding-bottom: 15px;
    }

    .aktualita-obsah h2 {
      color: #2D5016;
      font-size: 1.8em;
      margin: 40px 0 20px 0;
      font-weight: 600;
    }

    .aktualita-obsah h3 {
      color: #1a1a1a;
      font-size: 1.3em;
      margin: 30px 0 15px 0;
      font-weight: 600;
    }

    .aktualita-obsah p {
      margin: 20px 0;
      text-align: justify;
    }

    .aktualita-obsah strong {
      color: #2D5016;
      font-weight: 600;
    }

    .aktualita-obsah a {
      color: #2D5016;
      text-decoration: none;
      font-weight: 500;
      border-bottom: 1px dotted #2D5016;
      transition: all 0.3s;
    }

    .aktualita-obsah a:hover {
      border-bottom-style: solid;
      color: #1a300d;
    }

    .info-box {
      background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);
      border-left: 5px solid #2D5016;
      padding: 20px;
      margin: 30px 0;
      border-radius: 8px;
      font-size: 0.95em;
    }

    .archiv-section {
      background: white;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    .archiv-section h3 {
      color: #2D5016;
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
      background: #2D5016;
      color: white;
      border-left-color: #1a300d;
      transform: translateX(5px);
    }

    .archiv-link.active {
      background: #2D5016;
      color: white;
      border-left-color: #1a300d;
      font-weight: 600;
    }

    @media (max-width: 768px) {
      .hero-title {
        font-size: 2em;
      }

      .aktualita-card {
        padding: 25px;
      }

      .aktualita-obsah h1 {
        font-size: 1.8em;
      }

      .lang-btn {
        display: block;
        margin: 5px 0;
      }
    }
  </style>

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>
<body>

<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<!-- HERO SEKCE -->
<main>
<section class="hero">
  <div class="hero-content">
    <h1 class="hero-title"
        data-lang-cs="Aktuality Natuzzi"
        data-lang-en="Natuzzi News"
        data-lang-it="Notizie Natuzzi">
        <?php
        echo $jazyk === 'en' ? 'Natuzzi News' : ($jazyk === 'it' ? 'Notizie Natuzzi' : 'Aktuality Natuzzi');
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

<!-- PŘEPÍNAČ JAZYKŮ -->
<div class="lang-switcher">
  <a href="?lang=cz<?php echo isset($_GET['datum']) ? '&datum=' . $_GET['datum'] : ''; ?>"
     class="lang-btn <?php echo $jazyk === 'cz' ? 'active' : ''; ?>">
    🇨🇿 Čeština
  </a>
  <a href="?lang=en<?php echo isset($_GET['datum']) ? '&datum=' . $_GET['datum'] : ''; ?>"
     class="lang-btn <?php echo $jazyk === 'en' ? 'active' : ''; ?>">
    🇬🇧 English
  </a>
  <a href="?lang=it<?php echo isset($_GET['datum']) ? '&datum=' . $_GET['datum'] : ''; ?>"
     class="lang-btn <?php echo $jazyk === 'it' ? 'active' : ''; ?>">
    🇮🇹 Italiano
  </a>
</div>

<!-- OBSAH AKTUALITY -->
<section class="content-section">
  <div class="container">

    <?php if ($aktualita && !empty($obsah)): ?>

      <div class="datum-badge">
        📅 <?php echo date('d.m.Y', strtotime($aktualita['datum'])); ?>
        <?php if ($aktualita['svatek_cz']): ?>
          | <?php
          echo $jazyk === 'en' ? 'Name Day' : ($jazyk === 'it' ? 'Onomastico' : 'Svátek');
          ?>: <?php echo htmlspecialchars($aktualita['svatek_cz']); ?>
        <?php endif; ?>
      </div>

      <div class="aktualita-card">
        <div class="aktualita-obsah">
          <?php
          // Převést Markdown na HTML
          echo parseMarkdownToHTML($obsah);
          ?>
        </div>

        <?php if ($aktualita['vygenerovano_ai']): ?>
          <div class="info-box">
            <strong>ℹ️ <?php
            echo $jazyk === 'en' ? 'Information' : ($jazyk === 'it' ? 'Informazione' : 'Informace');
            ?>:</strong>
            <?php
            echo $jazyk === 'en' ?
              'This content was automatically generated from current sources on the internet.' :
              ($jazyk === 'it' ?
                'Questo contenuto è stato generato automaticamente da fonti attuali su Internet.' :
                'Tento obsah byl automaticky vygenerován z aktuálních zdrojů na internetu.');
            ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($archiv) && count($archiv) > 1): ?>
        <div class="archiv-section">
          <h3>
            <?php
            echo $jazyk === 'en' ? '📚 News Archive' : ($jazyk === 'it' ? '📚 Archivio Notizie' : '📚 Archiv aktualit');
            ?>
          </h3>
          <?php foreach (array_slice($archiv, 0, 10) as $polozka): ?>
            <a href="?datum=<?php echo $polozka['datum']; ?>&lang=<?php echo $jazyk; ?>"
               class="archiv-link <?php echo $polozka['datum'] === $aktualita['datum'] ? 'active' : ''; ?>">
              <?php echo date('d.m.Y', strtotime($polozka['datum'])); ?>
              <?php if ($polozka['svatek_cz']): ?>
                - <?php echo htmlspecialchars($polozka['svatek_cz']); ?>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>

      <div class="aktualita-card">
        <h2>⚠️ <?php
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

<script src="assets/js/hamburger-menu.js" defer></script>

</body>
</html>

<?php
/**
 * Převede Markdown na HTML
 */
function parseMarkdownToHTML(string $text): string
{
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

        // Pokud je to nadpis, nepřidávat <p>
        if (preg_match('/^<h[1-6]>/', $line)) {
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
