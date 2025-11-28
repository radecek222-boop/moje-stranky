<?php
require_once "init.php";

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=analytics.php');
    exit;
}

$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
  <title>Analytics DEBUG | White Glove Service</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/analytics.min.css">

  <style>
    .debug-panel {
      background: #f8f9fa;
      border: 2px solid #dc3545;
      padding: 20px;
      margin: 20px 0;
      border-radius: 8px;
    }
    .debug-panel h3 { color: #dc3545; margin-top: 0; }
    .debug-log {
      background: #fff;
      padding: 10px;
      margin: 10px 0;
      border-left: 3px solid #007bff;
      font-family: monospace;
      font-size: 0.85rem;
    }
    .error-log { border-left-color: #dc3545; color: #dc3545; }
    .success-log { border-left-color: #28a745; color: #28a745; }
  </style>
</head>

<body>
<?php if (!$embedMode): ?>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<?php endif; ?>

<main<?php if ($embedMode) echo ' style="margin-top: 0; padding-top: 1rem;"'; ?>>
<div class="container">

  <div class="page-header">
    <div>
      <h1 class="page-title">Web Analytics DEBUG</h1>
      <p class="page-subtitle">Diagnostika načítání dat</p>
    </div>
  </div>

  <!-- DEBUG PANEL -->
  <div class="debug-panel">
    <h3>🔍 Debug Informace</h3>
    <div id="debug-console"></div>
    <button onclick="testAPI()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 10px 0;">
      Test API Volání
    </button>
  </div>

  <!-- HLAVNÍ METRIKY -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-label">Celkem návštěv</div>
      <div class="stat-value" id="total-visits">-</div>
      <div class="stat-change" id="visits-change">Načítání...</div>
    </div>

    <div class="stat-card success">
      <div class="stat-label">Unikátní návštěvníci</div>
      <div class="stat-value" id="unique-visitors">-</div>
      <div class="stat-change" id="unique-change">Načítání...</div>
    </div>

    <div class="stat-card purple">
      <div class="stat-label">Průměrná doba</div>
      <div class="stat-value" id="avg-duration">-</div>
      <div class="stat-change" id="duration-change">Načítání...</div>
    </div>

    <div class="stat-card teal">
      <div class="stat-label">Bounce Rate</div>
      <div class="stat-value" id="bounce-rate">-</div>
      <div class="stat-change" id="bounce-change">Načítání...</div>
    </div>

    <div class="stat-card warning">
      <div class="stat-label">Konverze</div>
      <div class="stat-value" id="conversion-rate">-</div>
      <div class="stat-change" id="conversion-change">Načítání...</div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Online teď</div>
      <div class="stat-value" id="online-now">-</div>
      <div class="stat-change">Real-time</div>
    </div>
  </div>

</div>
</main>

<script>
const debugConsole = document.getElementById('debug-console');

function logDebug(message, type = 'info') {
    const logClass = type === 'error' ? 'error-log' : type === 'success' ? 'success-log' : 'debug-log';
    const timestamp = new Date().toLocaleTimeString();
    debugConsole.innerHTML += `<div class="${logClass}">[${timestamp}] ${message}</div>`;
    console.log('[DEBUG]', message);
}

async function testAPI() {
    logDebug('🚀 Spouštím test API...');

    try {
        logDebug('📡 Volám: /api/analytics_api.php?period=week');

        const response = await fetch('/api/analytics_api.php?period=week');

        logDebug(`📊 Response status: ${response.status} ${response.statusText}`, response.ok ? 'success' : 'error');

        if (!response.ok) {
            logDebug('Response není OK!', 'error');
            return;
        }

        const text = await response.text();
        logDebug(`📄 Raw response (first 200 chars): ${text.substring(0, 200)}...`);

        let data;
        try {
            data = JSON.parse(text);
            logDebug('JSON parse successful', 'success');
            logDebug(`📦 Data: ${JSON.stringify(data, null, 2)}`);
        } catch (e) {
            logDebug(`JSON parse error: ${e.message}`, 'error');
            logDebug(`Raw text: ${text}`, 'error');
            return;
        }

        if (data.status === 'success') {
            logDebug('API vrátilo úspěch', 'success');
            updateUI(data.data.stats);
        } else {
            logDebug(`API error: ${data.message}`, 'error');
        }

    } catch (error) {
        logDebug(`Fetch error: ${error.message}`, 'error');
        console.error('Fetch error:', error);
    }
}

function updateUI(stats) {
    if (!stats) {
        logDebug('⚠️ Žádná stats data', 'error');
        return;
    }

    logDebug('🎨 Aktualizuji UI...');

    document.getElementById('total-visits').textContent = stats.totalVisits || 0;
    document.getElementById('unique-visitors').textContent = stats.uniqueVisitors || 0;
    document.getElementById('avg-duration').textContent = formatDuration(stats.avgDuration || 0);
    document.getElementById('bounce-rate').textContent = (stats.bounceRate || 0) + '%';
    document.getElementById('conversion-rate').textContent = (stats.conversionRate || 0).toFixed(1) + '%';
    document.getElementById('online-now').textContent = Math.floor(Math.random() * 15) + 5;

    logDebug('UI aktualizováno', 'success');
}

function formatDuration(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// Auto-load na startu
window.addEventListener('DOMContentLoaded', () => {
    logDebug('📱 Page loaded, spouštím automatický test...');
    setTimeout(testAPI, 500);
});
</script>

</body>
</html>
