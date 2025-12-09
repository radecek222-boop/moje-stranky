<?php require_once "init.php";
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="description" content="Žádost o výmaz nebo export osobních údajů podle GDPR. White Glove Service.">
  <title>GDPR Žádost | White Glove Service</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <style>
    .gdpr-zadost-hero {
      background: linear-gradient(135deg, #000 0%, #1f2937 100%);
      color: #fff;
      padding: 6rem 2rem 4rem;
      text-align: center;
    }

    .gdpr-zadost-hero h1 {
      font-size: clamp(1.8rem, 3.2vw, 2.6rem);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 1rem;
      color: #fff !important;
    }

    .gdpr-zadost-hero p {
      font-size: 1.1rem;
      max-width: 720px;
      margin: 0 auto;
      opacity: 0.85;
      color: #fff !important;
    }

    .gdpr-zadost-content {
      padding: 3rem 1.5rem 4rem;
      background: #f9fafb;
    }

    .gdpr-zadost-container {
      max-width: 600px;
      margin: 0 auto;
    }

    .gdpr-zadost-card {
      background: #fff;
      border-radius: 16px;
      padding: 2.5rem;
      box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.15);
    }

    .gdpr-zadost-card h2 {
      font-size: 1.3rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 1.5rem;
      color: #111827;
      text-align: center;
    }

    .gdpr-form-group {
      margin-bottom: 1.25rem;
    }

    .gdpr-form-group label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 0.5rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .gdpr-form-group input,
    .gdpr-form-group select,
    .gdpr-form-group textarea {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 0.95rem;
      font-family: 'Poppins', sans-serif;
      transition: border-color 0.2s, box-shadow 0.2s;
      background: #fff;
    }

    .gdpr-form-group input:focus,
    .gdpr-form-group select:focus,
    .gdpr-form-group textarea:focus {
      outline: none;
      border-color: #111827;
      box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.1);
    }

    .gdpr-form-group textarea {
      min-height: 100px;
      resize: vertical;
    }

    .gdpr-form-group .required {
      color: #dc3545;
    }

    .gdpr-radio-group {
      display: flex;
      gap: 1rem;
      margin-top: 0.5rem;
    }

    .gdpr-radio-option {
      flex: 1;
      position: relative;
    }

    .gdpr-radio-option input {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }

    .gdpr-radio-option label {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 1.25rem 1rem;
      border: 2px solid #d1d5db;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s;
      text-transform: none;
    }

    .gdpr-radio-option input:checked + label {
      border-color: #111827;
      background: rgba(17, 24, 39, 0.05);
    }

    .gdpr-radio-option label strong {
      display: block;
      font-size: 1rem;
      color: #111827;
      margin-bottom: 0.25rem;
    }

    .gdpr-radio-option label span {
      font-size: 0.8rem;
      color: #6b7280;
      text-align: center;
    }

    .gdpr-submit-btn {
      width: 100%;
      padding: 14px 20px;
      background: #111827;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      font-family: 'Poppins', sans-serif;
      cursor: pointer;
      transition: background 0.2s, transform 0.1s;
      margin-top: 1rem;
    }

    .gdpr-submit-btn:hover {
      background: #000;
    }

    .gdpr-submit-btn:active {
      transform: scale(0.98);
    }

    .gdpr-submit-btn:disabled {
      background: #9ca3af;
      cursor: not-allowed;
    }

    .gdpr-info-box {
      background: rgba(17, 24, 39, 0.05);
      border-left: 4px solid #111827;
      padding: 1rem 1.25rem;
      border-radius: 0 8px 8px 0;
      margin-bottom: 1.5rem;
      font-size: 0.85rem;
      color: #374151;
      line-height: 1.6;
    }

    .gdpr-result {
      padding: 1.5rem;
      border-radius: 12px;
      margin-top: 1.5rem;
      display: none;
    }

    .gdpr-result.success {
      background: rgba(40, 167, 69, 0.1);
      border: 1px solid #28a745;
      color: #155724;
      display: block;
    }

    .gdpr-result.error {
      background: rgba(220, 53, 69, 0.1);
      border: 1px solid #dc3545;
      color: #721c24;
      display: block;
    }

    .gdpr-result h3 {
      font-size: 1rem;
      margin: 0 0 0.5rem 0;
    }

    .gdpr-result p {
      margin: 0;
      font-size: 0.9rem;
    }

    .gdpr-result .zadost-id {
      font-family: monospace;
      background: rgba(0,0,0,0.1);
      padding: 2px 6px;
      border-radius: 4px;
    }

    .gdpr-link {
      color: #111827;
      text-decoration: underline;
      text-underline-offset: 2px;
    }

    @media (max-width: 480px) {
      .gdpr-radio-group {
        flex-direction: column;
      }

      .gdpr-zadost-card {
        padding: 1.75rem;
      }
    }
  </style>

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main id="main-content">
  <section class="gdpr-zadost-hero">
    <h1>GDPR Žádost</h1>
    <p>Uplatněte své právo na výmaz nebo export osobních údajů podle nařízení GDPR.</p>
  </section>

  <section class="gdpr-zadost-content">
    <div class="gdpr-zadost-container">
      <div class="gdpr-zadost-card">
        <h2>Formulář žádosti</h2>

        <div class="gdpr-info-box">
          Podle GDPR máte právo požádat o výmaz nebo export svých osobních údajů.
          Vaši žádost zpracujeme do 30 dnů. Pro ověření identity potřebujeme údaje,
          které jste použili při komunikaci s námi.
        </div>

        <form id="gdprZadostForm">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

          <div class="gdpr-form-group">
            <label>Typ žádosti <span class="required">*</span></label>
            <div class="gdpr-radio-group">
              <div class="gdpr-radio-option">
                <input type="radio" name="typ" id="typVymazat" value="vymazat" required>
                <label for="typVymazat">
                  <strong>Výmaz údajů</strong>
                  <span>Smazání všech osobních údajů</span>
                </label>
              </div>
              <div class="gdpr-radio-option">
                <input type="radio" name="typ" id="typExportovat" value="exportovat">
                <label for="typExportovat">
                  <strong>Export údajů</strong>
                  <span>Kopie všech údajů v JSON</span>
                </label>
              </div>
            </div>
          </div>

          <div class="gdpr-form-group">
            <label for="email">Email <span class="required">*</span></label>
            <input type="email" id="email" name="email" required placeholder="vas@email.cz">
          </div>

          <div class="gdpr-form-group">
            <label for="jmeno">Jméno a příjmení <span class="required">*</span></label>
            <input type="text" id="jmeno" name="jmeno" required placeholder="Jan Novák">
          </div>

          <div class="gdpr-form-group">
            <label for="telefon">Telefon (volitelné)</label>
            <input type="tel" id="telefon" name="telefon" placeholder="+420 123 456 789">
          </div>

          <div class="gdpr-form-group">
            <label for="duvod">Důvod žádosti (volitelné)</label>
            <textarea id="duvod" name="duvod" placeholder="Proč žádáte o výmaz/export..."></textarea>
          </div>

          <button type="submit" class="gdpr-submit-btn" id="submitBtn">Odeslat žádost</button>
        </form>

        <div id="gdprResult" class="gdpr-result"></div>

        <p style="margin-top: 1.5rem; font-size: 0.85rem; color: #6b7280; text-align: center;">
          Více informací najdete v
          <a href="gdpr.php" class="gdpr-link">Zásadách ochrany osobních údajů</a>.
        </p>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="footer-container">
    <div class="footer-grid">
      <div class="footer-column">
        <h2 class="footer-title">White Glove Service</h2>
        <p class="footer-text">Specializovaný servis Natuzzi.</p>
      </div>
      <div class="footer-column">
        <h2 class="footer-title">Kontakt</h2>
        <p class="footer-text">
          <strong>Tel:</strong> <a href="tel:+420725965826" class="footer-link">+420 725 965 826</a><br>
          <strong>Email:</strong> <a href="mailto:reklamace@wgs-service.cz" class="footer-link">reklamace@wgs-service.cz</a>
        </p>
      </div>
      <div class="footer-column">
        <h2 class="footer-title">Adresa</h2>
        <p class="footer-text">Do Dubče 364, Běchovice 190 11 CZ</p>
      </div>
    </div>
    <div class="footer-bottom">
      <p>
        &copy; 2025 White Glove Service. Všechna práva vyhrazena.
        <span aria-hidden="true"> • </span>
        <a href="gdpr.php" class="footer-link">GDPR</a>
        <span aria-hidden="true"> • </span>
        <a href="cookies.php" class="footer-link">Cookies</a>
        <span aria-hidden="true"> • </span>
        <a href="podminky.php" class="footer-link">Obchodní podmínky</a>
      </p>
    </div>
  </div>
</footer>

<script src="assets/js/logger.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('gdprZadostForm');
  const resultDiv = document.getElementById('gdprResult');
  const submitBtn = document.getElementById('submitBtn');

  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    submitBtn.disabled = true;
    submitBtn.textContent = 'Odesílám...';
    resultDiv.className = 'gdpr-result';
    resultDiv.style.display = 'none';

    try {
      const formData = new FormData(form);
      const response = await fetch('/api/gdpr_zadost.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.status === 'success') {
        resultDiv.className = 'gdpr-result success';
        let html = '<h3>Žádost přijata</h3>';
        html += '<p>' + data.message + '</p>';
        if (data.data && data.data.zadost_id) {
          html += '<p style="margin-top: 0.5rem;">ID žádosti: <span class="zadost-id">' + data.data.zadost_id + '</span></p>';
        }
        if (data.data && data.data.zaznamy) {
          html += '<p style="margin-top: 0.5rem;">Nalezené záznamy: ';
          const zaznamy = Object.entries(data.data.zaznamy).map(([k, v]) => k + ': ' + v).join(', ');
          html += zaznamy + '</p>';
        }
        resultDiv.innerHTML = html;
        form.reset();
      } else {
        resultDiv.className = 'gdpr-result error';
        resultDiv.innerHTML = '<h3>Chyba</h3><p>' + (data.message || 'Nepodařilo se odeslat žádost') + '</p>';
      }
    } catch (error) {
      resultDiv.className = 'gdpr-result error';
      resultDiv.innerHTML = '<h3>Chyba</h3><p>Nepodařilo se odeslat žádost. Zkuste to prosím později.</p>';
      console.error('GDPR form error:', error);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Odeslat žádost';
    }
  });
});
</script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
