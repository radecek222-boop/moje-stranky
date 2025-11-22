<?php
/**
 * Nová aktualita - Formulář pro vytvoření vlastní aktuality
 * Pouze pro administrátory
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Kontrola admin oprávnění
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nová aktualita | WGS Admin</title>

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

  <style>
    body {
      background: #f5f5f5;
      font-family: 'Poppins', sans-serif;
    }

    .container {
      max-width: 1400px;
      margin: 40px auto;
      padding: 20px;
    }

    .header {
      background: white;
      padding: 30px;
      border-radius: 10px;
      margin-bottom: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .header h1 {
      margin: 0 0 10px 0;
      color: #1a1a1a;
      font-size: 2em;
    }

    .form-card {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #333;
      font-size: 0.95em;
    }

    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group textarea {
      width: 100%;
      padding: 12px;
      border: 2px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
      font-family: inherit;
      transition: border-color 0.3s;
    }

    .form-group textarea {
      font-family: 'Courier New', monospace;
      min-height: 300px;
      resize: vertical;
    }

    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #1a1a1a;
    }

    .language-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      border-bottom: 2px solid #e0e0e0;
    }

    .tab-btn {
      padding: 12px 24px;
      background: transparent;
      border: none;
      border-bottom: 3px solid transparent;
      cursor: pointer;
      font-weight: 600;
      font-size: 1em;
      transition: all 0.3s;
      color: #666;
    }

    .tab-btn:hover {
      color: #1a1a1a;
      background: #f5f5f5;
    }

    .tab-btn.active {
      color: #1a1a1a;
      border-bottom-color: #1a1a1a;
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    .markdown-help {
      background: #f0f8ff;
      padding: 15px;
      border-left: 4px solid #1a1a1a;
      border-radius: 5px;
      margin-bottom: 15px;
      font-size: 0.9em;
    }

    .markdown-help code {
      background: #e8e8e8;
      padding: 2px 6px;
      border-radius: 3px;
      font-family: 'Courier New', monospace;
    }

    .photo-upload {
      border: 2px dashed #ddd;
      padding: 30px;
      text-align: center;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .photo-upload:hover {
      border-color: #1a1a1a;
      background: #f9f9f9;
    }

    .photo-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-top: 15px;
    }

    .photo-item {
      position: relative;
      width: 200px;
      height: 150px;
      border-radius: 5px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .photo-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .photo-item .remove-btn {
      position: absolute;
      top: 5px;
      right: 5px;
      background: #dc3545;
      color: white;
      border: none;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      cursor: pointer;
      font-weight: bold;
    }

    .submit-section {
      display: flex;
      gap: 15px;
      justify-content: flex-end;
      padding-top: 20px;
      border-top: 2px solid #e0e0e0;
    }

    .btn {
      padding: 14px 28px;
      border: none;
      border-radius: 5px;
      font-weight: 600;
      font-size: 1em;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-cancel {
      background: #6c757d;
      color: white;
    }

    .btn-cancel:hover {
      background: #5a6268;
    }

    .btn-submit {
      background: #28a745;
      color: white;
    }

    .btn-submit:hover {
      background: #218838;
    }

    .required {
      color: #dc3545;
    }

    .info-box {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }

    @media (max-width: 768px) {
      .container {
        padding: 10px;
      }

      .form-card {
        padding: 20px;
      }

      .language-tabs {
        flex-direction: column;
      }

      .tab-btn {
        width: 100%;
      }
    }
  </style>

  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>
<body>

<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<div class="container">
  <div class="header">
    <h1>📝 Vytvořit novou aktualitu</h1>
    <p style="margin: 0; color: #666;">Vyplňte formulář ve všech třech jazycích a přidejte fotografie.</p>
  </div>

  <form id="novaAktualitaForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Základní informace -->
    <div class="form-card">
      <h2 style="margin: 0 0 20px 0;">📅 Základní informace</h2>

      <div class="form-group">
        <label for="datum">Datum <span class="required">*</span></label>
        <input type="date" id="datum" name="datum" required value="<?php echo date('Y-m-d'); ?>">
      </div>

      <div class="form-group">
        <label for="svatek">Svátek (české jméno)</label>
        <input type="text" id="svatek" name="svatek" placeholder="např. Cecílie">
      </div>

      <div class="form-group">
        <label for="komentar">Komentář dne (volitelné)</label>
        <input type="text" id="komentar" name="komentar" placeholder="např. Dnes si připomínáme svátek...">
      </div>
    </div>

    <!-- Jazykové verze -->
    <div class="form-card">
      <h2 style="margin: 0 0 20px 0;">🌍 Obsah ve třech jazycích</h2>

      <div class="info-box">
        <strong>ℹ️ Důležité:</strong> Vyplňte obsah ve všech třech jazycích. Použijte Markdown formát pro formátování textu.
      </div>

      <div class="language-tabs">
        <button type="button" class="tab-btn active" data-tab="cz">🇨🇿 Čeština</button>
        <button type="button" class="tab-btn" data-tab="en">🇬🇧 English</button>
        <button type="button" class="tab-btn" data-tab="it">🇮🇹 Italiano</button>
      </div>

      <!-- Česká verze -->
      <div id="tab-cz" class="tab-content active">
        <div class="markdown-help">
          <strong>💡 Markdown formát:</strong>
          <code># Nadpis</code> = H1 |
          <code>## Nadpis</code> = H2 |
          <code>**tučně**</code> = <strong>tučně</strong> |
          <code>[text](url)</code> = odkaz |
          <code>![popis](url)</code> = obrázek
        </div>
        <div class="form-group">
          <label for="obsah_cz">Obsah v češtině <span class="required">*</span></label>
          <textarea id="obsah_cz" name="obsah_cz" required placeholder="# Denní aktuality Natuzzi

**Datum:** <?php echo date('d.m.Y'); ?> | **Svátek má:** ...

Vítejte u dnešních aktualit o luxusním italském nábytku Natuzzi..."></textarea>
        </div>
      </div>

      <!-- Anglická verze -->
      <div id="tab-en" class="tab-content">
        <div class="markdown-help">
          <strong>💡 Markdown format:</strong>
          <code># Heading</code> = H1 |
          <code>## Heading</code> = H2 |
          <code>**bold**</code> = <strong>bold</strong> |
          <code>[text](url)</code> = link |
          <code>![desc](url)</code> = image
        </div>
        <div class="form-group">
          <label for="obsah_en">Content in English <span class="required">*</span></label>
          <textarea id="obsah_en" name="obsah_en" required placeholder="# Natuzzi Daily News

**Date:** <?php echo date('m/d/Y'); ?> | **Name Day:** ...

Welcome to today's news about luxury Italian furniture Natuzzi..."></textarea>
        </div>
      </div>

      <!-- Italská verze -->
      <div id="tab-it" class="tab-content">
        <div class="markdown-help">
          <strong>💡 Formato Markdown:</strong>
          <code># Titolo</code> = H1 |
          <code>## Titolo</code> = H2 |
          <code>**grassetto**</code> = <strong>grassetto</strong> |
          <code>[testo](url)</code> = link |
          <code>![desc](url)</code> = immagine
        </div>
        <div class="form-group">
          <label for="obsah_it">Contenuto in Italiano <span class="required">*</span></label>
          <textarea id="obsah_it" name="obsah_it" required placeholder="# Notizie Quotidiane Natuzzi

**Data:** <?php echo date('d.m.Y'); ?> | **Onomastico:** ...

Benvenuti alle notizie di oggi sui mobili italiani di lusso Natuzzi..."></textarea>
        </div>
      </div>
    </div>

    <!-- Fotografie -->
    <div class="form-card">
      <h2 style="margin: 0 0 20px 0;">📷 Fotografie</h2>
      <p style="margin: 0 0 15px 0; color: #666;">
        Přidejte fotografie k článku. Fotky budou automaticky přizpůsobeny velikosti.
      </p>

      <div class="photo-upload" id="photoUploadArea">
        <p style="margin: 0 0 10px 0; font-size: 3em;">📸</p>
        <p style="margin: 0; font-weight: 600;">Klikněte nebo přetáhněte fotografie</p>
        <p style="margin: 5px 0 0 0; font-size: 0.9em; color: #666;">Podporované formáty: JPG, PNG, WebP (max 5 MB)</p>
        <input type="file" id="fotoInput" name="fotografie[]" multiple accept="image/*" style="display: none;">
      </div>

      <div class="photo-preview" id="photoPreview"></div>
    </div>

    <!-- Tlačítka -->
    <div class="form-card">
      <div class="submit-section">
        <button type="button" class="btn btn-cancel" onclick="window.location.href='admin.php'">
          ❌ Zrušit
        </button>
        <button type="submit" class="btn btn-submit">
          💾 Vytvořit aktualitu
        </button>
      </div>
    </div>
  </form>
</div>

<script>
(function() {
  'use strict';

  // Přepínání záložek
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const tabId = this.dataset.tab;

      // Deaktivovat všechny
      tabBtns.forEach(b => b.classList.remove('active'));
      tabContents.forEach(c => c.classList.remove('active'));

      // Aktivovat vybranou
      this.classList.add('active');
      document.getElementById('tab-' + tabId).classList.add('active');
    });
  });

  // Upload fotografií
  const photoUploadArea = document.getElementById('photoUploadArea');
  const fotoInput = document.getElementById('fotoInput');
  const photoPreview = document.getElementById('photoPreview');
  let selectedFiles = [];

  photoUploadArea.addEventListener('click', () => fotoInput.click());

  photoUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    photoUploadArea.style.borderColor = '#1a1a1a';
    photoUploadArea.style.background = '#f0f0f0';
  });

  photoUploadArea.addEventListener('dragleave', () => {
    photoUploadArea.style.borderColor = '#ddd';
    photoUploadArea.style.background = 'transparent';
  });

  photoUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    photoUploadArea.style.borderColor = '#ddd';
    photoUploadArea.style.background = 'transparent';

    const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
    handleFiles(files);
  });

  fotoInput.addEventListener('change', (e) => {
    handleFiles(Array.from(e.target.files));
  });

  function handleFiles(files) {
    files.forEach(file => {
      if (file.size > 5 * 1024 * 1024) {
        alert(`Soubor ${file.name} je příliš velký (max 5 MB)`);
        return;
      }

      selectedFiles.push(file);

      const reader = new FileReader();
      reader.onload = (e) => {
        const photoItem = document.createElement('div');
        photoItem.className = 'photo-item';
        photoItem.innerHTML = `
          <img src="${e.target.result}" alt="${file.name}">
          <button type="button" class="remove-btn" data-filename="${file.name}">×</button>
        `;

        photoPreview.appendChild(photoItem);

        // Odstranění fotky
        photoItem.querySelector('.remove-btn').addEventListener('click', function() {
          const filename = this.dataset.filename;
          selectedFiles = selectedFiles.filter(f => f.name !== filename);
          photoItem.remove();
        });
      };
      reader.readAsDataURL(file);
    });
  }

  // Odeslání formuláře
  const form = document.getElementById('novaAktualitaForm');
  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitBtn = form.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳ Vytvářím...';

    try {
      const formData = new FormData(form);

      // Přidat vybrané fotografie
      selectedFiles.forEach((file, index) => {
        formData.append(`foto_${index}`, file);
      });

      const response = await fetch('/api/vytvor_aktualitu.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.status === 'success') {
        alert('✅ Aktualita byla úspěšně vytvořena!\n\nBudete přesměrováni na stránku s aktualitami.');
        window.location.href = 'aktuality.php?datum=' + formData.get('datum');
      } else {
        alert('❌ Chyba: ' + result.message);
        submitBtn.disabled = false;
        submitBtn.textContent = '💾 Vytvořit aktualitu';
      }
    } catch (error) {
      alert('❌ Síťová chyba: ' + error.message);
      submitBtn.disabled = false;
      submitBtn.textContent = '💾 Vytvořit aktualitu';
    }
  });
})();
</script>

<script src="assets/js/hamburger-menu.js" defer></script>

</body>
</html>
