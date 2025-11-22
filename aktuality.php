<?php
/**
 * Aktuality o značce Natuzzi
 * Automaticky generované denní novinky ve třech jazycích
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

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
      background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
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

    .content-section {
      padding: 0;
      background: #ffffff;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0;
    }

    .datum-badge {
      background: #1a1a1a;
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
      padding: 15px 30px;
      border-bottom: 1px solid #e0e0e0;
      margin-bottom: 0;
      column-count: 2;
      column-gap: 35px;
      text-align: justify;
    }

    @media (max-width: 768px) {
      .aktualita-card {
        column-count: 1;
        padding: 12px 18px;
      }
    }

    .aktualita-obsah {
      font-size: 0.95em;
      line-height: 1.6;
      color: #333;
      font-family: Georgia, 'Times New Roman', serif;
    }

    .aktualita-obsah h1 {
      color: #1a1a1a;
      font-size: 2.8em;
      margin: 0 0 10px 0;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: -1px;
      column-span: all;
      font-family: 'Poppins', sans-serif;
      line-height: 1.1;
    }

    .aktualita-obsah h2 {
      color: #1a1a1a;
      font-size: 1.4em;
      margin: 25px 0 10px 0;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      border-top: 3px solid #000;
      padding-top: 10px;
      column-span: all;
      font-family: 'Poppins', sans-serif;
    }

    .aktualita-obsah h3 {
      color: #333333;
      font-size: 1.3em;
      margin: 30px 0 15px 0;
      font-weight: 600;
    }

    .aktualita-obsah p {
      margin: 0 0 15px 0;
      text-align: justify;
      text-indent: 20px;
    }

    .aktualita-obsah p:first-of-type {
      font-weight: 500;
      font-size: 1.1em;
      text-indent: 0;
    }

    .aktualita-obsah strong {
      color: #1a1a1a;
      font-weight: 700;
    }

    .aktualita-obsah a {
      color: #1a1a1a;
      text-decoration: underline;
      font-weight: 600;
      transition: all 0.2s;
    }

    .aktualita-obsah a:hover {
      color: #666666;
    }

    .aktualita-obsah img {
      max-width: 100%;
      height: auto;
      display: block;
      margin: 15px 0;
      column-span: all;
      border: 1px solid #ddd;
    }

    .news-image {
      width: 100%;
      height: 300px;
      object-fit: cover;
      margin: 20px 0;
      border: 1px solid #e0e0e0;
      column-span: all;
    }

    .info-box {
      background: linear-gradient(135deg, #f0f0f0 0%, #e8e8e8 100%);
      border-left: 5px solid #333333;
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
      .hero-title {
        font-size: 2em;
      }

      .aktualita-card {
        padding: 25px;
      }

      .aktualita-obsah h1 {
        font-size: 1.8em;
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

<!-- OBSAH AKTUALITY -->
<section class="content-section">
  <div class="container">

    <?php if ($aktualita && !empty($obsah)): ?>

      <div class="datum-badge">
        <?php
        // Překlad "Datum:"
        echo $jazyk === 'en' ? 'Date: ' : ($jazyk === 'it' ? 'Data: ' : 'Datum: ');
        echo date('d.m.Y', strtotime($aktualita['datum']));
        ?>
        <?php if ($aktualita['svatek_cz']): ?>
          | <?php
          echo $jazyk === 'en' ? 'Name Day' : ($jazyk === 'it' ? 'Onomastico' : 'Svátek');
          ?>: <?php echo htmlspecialchars($aktualita['svatek_cz']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
          <button id="upravitClanekBtn" style="
            margin-left: 20px;
            padding: 8px 16px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9em;
            transition: all 0.3s;
          " onmouseover="this.style.background='#333'" onmouseout="this.style.background='#1a1a1a'">
            ✏️ Upravit článek
          </button>
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
            📚 <?php
            echo $jazyk === 'en' ? 'News Archive' : ($jazyk === 'it' ? 'Archivio Notizie' : 'Archiv aktualit');
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

<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true && $aktualita): ?>
<!-- ADMIN EDITOR ODKAZŮ -->
<script>
(function() {
  'use strict';

  // Přidat CSRF token
  const csrfToken = '<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>';
  const aktualitaId = <?php echo intval($aktualita['id'] ?? 0); ?>;
  const jazyk = '<?php echo htmlspecialchars($jazyk, ENT_QUOTES, 'UTF-8'); ?>';

  // Když je stránka načtena
  document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 ADMIN MODE: Editor odkazů aktivován');

    // Najít všechny odkazy v obsahu aktuality
    const odkazy = document.querySelectorAll('.aktualita-obsah a');

    odkazy.forEach(function(link) {
      // Přidat vizuální indikaci že je odkaz editovatelný
      link.style.cursor = 'pointer';
      link.style.position = 'relative';
      link.title = 'Admin: Klikněte pro úpravu URL';

      // Přidat malou ikonku
      const editIcon = document.createElement('span');
      editIcon.innerHTML = ' ✏️';
      editIcon.style.fontSize = '0.8em';
      editIcon.style.opacity = '0.6';
      link.appendChild(editIcon);

      // Při kliknutí zobrazit editor
      link.addEventListener('click', function(e) {
        e.preventDefault();
        upravitOdkaz(link);
      });
    });
  });

  function upravitOdkaz(linkElement) {
    const puvodniUrl = linkElement.href;
    const text = linkElement.textContent.replace(' ✏️', '').trim();

    // Vytvořit dialog
    const dialog = document.createElement('div');
    dialog.style.cssText = `
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
      z-index: 10000;
      min-width: 500px;
      max-width: 90%;
    `;

    dialog.innerHTML = `
      <h3 style="margin: 0 0 20px 0; color: #1a1a1a;">✏️ Upravit odkaz</h3>
      <p style="margin: 0 0 10px 0; color: #666;">
        <strong>Text odkazu:</strong> ${escapeHtml(text)}
      </p>
      <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Aktuální URL:</label>
        <input type="text" id="currentUrl" value="${escapeHtml(puvodniUrl)}"
               style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-family: monospace; background: #f5f5f5;"
               readonly>
      </div>
      <div style="margin-bottom: 20px;">
        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Nová URL:</label>
        <input type="text" id="newUrl" value="${escapeHtml(puvodniUrl)}"
               style="width: 100%; padding: 10px; border: 2px solid #333; border-radius: 5px; font-family: monospace;"
               placeholder="https://example.com">
      </div>
      <div style="display: flex; gap: 10px; justify-content: flex-end;">
        <button id="cancelBtn" style="padding: 10px 20px; background: #999; color: white; border: none; border-radius: 5px; cursor: pointer;">
          Zrušit
        </button>
        <button id="saveBtn" style="padding: 10px 20px; background: #1a1a1a; color: white; border: none; border-radius: 5px; cursor: pointer;">
          💾 Uložit změnu
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
      background: rgba(0,0,0,0.5);
      z-index: 9999;
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(dialog);

    // Focus na input
    const newUrlInput = document.getElementById('newUrl');
    newUrlInput.focus();
    newUrlInput.select();

    // Zavřít dialog
    function zavritDialog() {
      overlay.remove();
      dialog.remove();
    }

    // Tlačítka
    document.getElementById('cancelBtn').addEventListener('click', zavritDialog);
    overlay.addEventListener('click', zavritDialog);

    document.getElementById('saveBtn').addEventListener('click', async function() {
      const novaUrl = newUrlInput.value.trim();

      if (!novaUrl) {
        alert('❌ URL nesmí být prázdná!');
        return;
      }

      // Validace URL
      try {
        new URL(novaUrl);
      } catch (e) {
        alert('❌ Neplatný formát URL! Použijte formát: https://example.com');
        return;
      }

      // Uložit změnu
      this.disabled = true;
      this.textContent = '⏳ Ukládám...';

      try {
        const response = await ulozitZmenuOdkazu(puvodniUrl, novaUrl);

        if (response.status === 'success') {
          alert('✅ Odkaz byl úspěšně změněn!\n\n' +
                'Stará URL: ' + puvodniUrl + '\n' +
                'Nová URL: ' + novaUrl);

          // Obnovit stránku
          window.location.reload();
        } else {
          alert('❌ Chyba: ' + response.message);
          this.disabled = false;
          this.textContent = '💾 Uložit změnu';
        }
      } catch (error) {
        alert('❌ Síťová chyba: ' + error.message);
        this.disabled = false;
        this.textContent = '💾 Uložit změnu';
      }
    });

    // Enter pro uložení
    newUrlInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        document.getElementById('saveBtn').click();
      }
    });
  }

  async function ulozitZmenuOdkazu(staraUrl, novaUrl) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('aktualita_id', aktualitaId);
    formData.append('jazyk', jazyk);
    formData.append('stara_url', staraUrl);
    formData.append('nova_url', novaUrl);

    const response = await fetch('/api/uprav_odkaz_aktuality.php', {
      method: 'POST',
      body: formData
    });

    return await response.json();
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ========================================
  // EDITOR CELÉHO ČLÁNKU
  // ========================================
  const upravitBtn = document.getElementById('upravitClanekBtn');
  if (upravitBtn) {
    upravitBtn.addEventListener('click', function() {
      otevritEditorClanku();
    });
  }

  function otevritEditorClanku() {
    // Získat aktuální markdown obsah
    const aktualniObsah = `<?php echo addslashes(str_replace(["\r\n", "\n", "\r"], "\\n", $obsah)); ?>`;

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
      max-width: 1200px;
      max-height: 90vh;
      overflow-y: auto;
    `;

    editorDialog.innerHTML = `
      <h2 style="margin: 0 0 20px 0; color: #1a1a1a;">
        📝 Upravit článek - <?php echo strtoupper($jazyk); ?>
      </h2>
      <p style="margin: 0 0 15px 0; color: #666;">
        Editujte obsah článku v Markdown formátu. Změny se uloží do databáze a budou okamžitě viditelné.
      </p>
      <div style="margin-bottom: 15px; padding: 15px; background: #f0f8ff; border-left: 4px solid #1a1a1a; border-radius: 5px;">
        <strong>💡 Markdown formát:</strong><br>
        <code># Nadpis</code> = H1 | <code>## Nadpis</code> = H2 | <code>**tučně**</code> = <strong>tučně</strong><br>
        <code>[text](url)</code> = odkaz | <code>![popis](url)</code> = obrázek
      </div>
      <textarea id="editorTextarea" style="
        width: 100%;
        min-height: 500px;
        padding: 15px;
        border: 2px solid #333;
        border-radius: 5px;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.6;
        resize: vertical;
      "></textarea>
      <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px;">
        <strong>⚠️ Pozor:</strong> Tato změna přepíše celý obsah článku v jazyce <strong><?php echo strtoupper($jazyk); ?></strong>.
        Ostatní jazyky zůstanou nezměněny.
      </div>
      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
        <button id="cancelEditorBtn" style="
          padding: 12px 24px;
          background: #6c757d;
          color: white;
          border: none;
          border-radius: 5px;
          cursor: pointer;
          font-weight: 600;
        ">
          ❌ Zrušit
        </button>
        <button id="saveEditorBtn" style="
          padding: 12px 24px;
          background: #28a745;
          color: white;
          border: none;
          border-radius: 5px;
          cursor: pointer;
          font-weight: 600;
        ">
          💾 Uložit změny
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

    // Nastavit obsah textarea
    const textarea = document.getElementById('editorTextarea');
    textarea.value = aktualniObsah;
    textarea.focus();

    // Zavřít editor
    function zavritEditor() {
      overlay.remove();
      editorDialog.remove();
    }

    document.getElementById('cancelEditorBtn').addEventListener('click', zavritEditor);
    overlay.addEventListener('click', zavritEditor);

    // Uložit změny
    document.getElementById('saveEditorBtn').addEventListener('click', async function() {
      const novyObsah = textarea.value.trim();

      if (!novyObsah) {
        alert('❌ Obsah článku nesmí být prázdný!');
        return;
      }

      if (!confirm(`Opravdu chcete uložit změny?\n\nPřepíše se celý obsah článku v jazyce <?php echo strtoupper($jazyk); ?>.`)) {
        return;
      }

      this.disabled = true;
      this.textContent = '⏳ Ukládám...';

      try {
        const response = await ulozitCelyClanek(novyObsah);

        if (response.status === 'success') {
          alert('✅ Článek byl úspěšně uložen!\n\nStránka se nyní obnoví.');
          window.location.reload();
        } else {
          alert('❌ Chyba při ukládání: ' + response.message);
          this.disabled = false;
          this.textContent = '💾 Uložit změny';
        }
      } catch (error) {
        alert('❌ Síťová chyba: ' + error.message);
        this.disabled = false;
        this.textContent = '💾 Uložit změny';
      }
    });
  }

  async function ulozitCelyClanek(novyObsah) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('aktualita_id', aktualitaId);
    formData.append('jazyk', jazyk);
    formData.append('novy_obsah', novyObsah);

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
    // Obrázky (před odkazy!)
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" class="news-image" loading="lazy">', $text);

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
