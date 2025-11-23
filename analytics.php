<?php
require_once "init.php";

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=analytics.php');
    exit;
}

// Embed mode - skrýt navigaci
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
  <title>Analytics | White Glove Service</title>
  <meta name="description" content="Analytics dashboard White Glove Service. Pokročilá analytika servisu, trendy, výkonnost a business intelligence.">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

    <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/analytics.min.css" as="style">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/analytics.min.css">

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>

  <style>
    /* Analytics Module Cards Hover Effect */
    .analytics-module-card {
      transition: all 0.3s ease;
    }
    .analytics-module-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
    }
  </style>
</head>
<body>
<?php if (!$embedMode): ?>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<?php endif; ?>

<!-- MAIN CONTENT -->
<main<?php if ($embedMode) echo ' style="margin-top: 0; padding-top: 1rem;"'; ?>>
<div class="container">

  <div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 0.5rem; padding: 0.5rem; background: var(--c-white); border: 1px solid var(--c-border); border-radius: 6px;">
    <div class="user-info" style="font-weight: 600; letter-spacing: 0.03em; color: var(--c-text-primary, #1d1f2c); text-transform: uppercase; font-size: 0.65rem;">
      <span style="font-weight: 400; margin-right: 0.3rem; color: var(--c-text-secondary, #6b7280); text-transform: none;">Přihlášený uživatel:</span>
      <span class="user-name" id="userName" style="font-size: 0.75rem; color: var(--c-accent, #0f766e);"><?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </div>

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Web Analytics</h1>
      <p class="page-subtitle">Komplexní analýza návštěvnosti a chování na webu</p>
    </div>

    <div class="time-selector">
      <button class="time-btn" data-timeperiod="today">Dnes</button>
      <button class="time-btn active" data-timeperiod="week">Týden</button>
      <button class="time-btn" data-timeperiod="month">Měsíc</button>
      <button class="time-btn" data-timeperiod="year">Rok</button>
    </div>
  </div>

  <!-- ANALYTICS MODULY - NAVIGACE -->
  <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);">
    <h2 style="color: white; font-size: 1.3rem; margin-bottom: 1.5rem; font-weight: 600;">Analytics Moduly</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">

      <a href="analytics-heatmap.php" class="analytics-module-card" style="background: rgba(255,255,255,0.95); padding: 1.5rem; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; color: #f59e0b;">🔥</div>
        <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">Heatmapy</div>
        <div style="font-size: 0.75rem; color: #6b7280;">Click & Scroll mapy</div>
      </a>

      <a href="analytics-replay.php" class="analytics-module-card" style="background: rgba(255,255,255,0.95); padding: 1.5rem; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; color: #ef4444;">🎥</div>
        <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">Session Replay</div>
        <div style="font-size: 0.75rem; color: #6b7280;">Záznamy návštěv</div>
      </a>

      <a href="analytics-campaigns.php" class="analytics-module-card" style="background: rgba(255,255,255,0.95); padding: 1.5rem; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; color: #8b5cf6;">📢</div>
        <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">Kampaně</div>
        <div style="font-size: 0.75rem; color: #6b7280;">UTM tracking</div>
      </a>

      <a href="analytics-conversions.php" class="analytics-module-card" style="background: rgba(255,255,255,0.95); padding: 1.5rem; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; color: #10b981;">🎯</div>
        <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">Konverze</div>
        <div style="font-size: 0.75rem; color: #6b7280;">Conversion funnels</div>
      </a>

      <a href="analytics-user-scores.php" class="analytics-module-card" style="background: rgba(255,255,255,0.95); padding: 1.5rem; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; color: #3b82f6;">🧠</div>
        <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">User Scoring</div>
        <div style="font-size: 0.75rem; color: #6b7280;">AI analýza chování</div>
      </a>

      <a href="analytics-realtime.php" class="analytics-module-card" style="background: rgba(255,255,255,0.95); padding: 1.5rem; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; color: #ec4899;">⚡</div>
        <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">Real-time</div>
        <div style="font-size: 0.75rem; color: #6b7280;">Live dashboard</div>
      </a>

      <a href="analytics-reports.php" class="analytics-module-card" style="background: rgba(255,255,255,0.95); padding: 1.5rem; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; color: #14b8a6;">📊</div>
        <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">AI Reporty</div>
        <div style="font-size: 0.75rem; color: #6b7280;">Automatické reporty</div>
      </a>

      <a href="gdpr-portal.php" class="analytics-module-card" style="background: rgba(255,255,255,0.95); padding: 1.5rem; border-radius: 8px; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="font-size: 2rem; color: #64748b;">🔒</div>
        <div style="font-weight: 600; color: #1f2937; font-size: 1rem;">GDPR Portal</div>
        <div style="font-size: 0.75rem; color: #6b7280;">Compliance & Privacy</div>
      </a>

    </div>
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

  <!-- DETAILNÍ ANALYTICS -->
  <div class="analytics-details" style="margin-top: 3rem;">

    <!-- Nejnavštěvovanější stránky -->
    <div class="analytics-section" style="margin-bottom: 2rem;">
      <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1d1f2c;">Nejnavštěvovanější stránky</h2>
      <div class="table-container" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <table class="analytics-table" id="top-pages-table" style="width: 100%; border-collapse: collapse;">
          <thead style="background: #f9fafb;">
            <tr>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Stránka</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Návštěvy</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Unikátní</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Průměrná doba</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="4" style="text-align: center; padding: 2rem; color: #999;">Načítání dat...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Zdroje návštěvnosti -->
    <div class="analytics-section" style="margin-bottom: 2rem;">
      <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1d1f2c;">Zdroje návštěvnosti</h2>
      <div class="table-container" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <table class="analytics-table" id="referrers-table" style="width: 100%; border-collapse: collapse;">
          <thead style="background: #f9fafb;">
            <tr>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Zdroj</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Návštěvy</th>
              <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Unikátní návštěvníci</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="3" style="text-align: center; padding: 2rem; color: #999;">Načítání dat...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Prohlížeče a zařízení -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
      <div class="analytics-section">
        <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1d1f2c;">Top prohlížeče</h2>
        <div class="table-container" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
          <table class="analytics-table" id="browsers-table" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f9fafb;">
              <tr>
                <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Prohlížeč</th>
                <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Návštěvy</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="2" style="text-align: center; padding: 2rem; color: #999;">Načítání dat...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="analytics-section">
        <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1d1f2c;">Top rozlišení</h2>
        <div class="table-container" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
          <table class="analytics-table" id="devices-table" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f9fafb;">
              <tr>
                <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Rozlišení</th>
                <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #6b7280;">Návštěvy</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="2" style="text-align: center; padding: 2rem; color: #999;">Načítání dat...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Graf návštěvnosti -->
    <div class="analytics-section">
      <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1d1f2c;">Návštěvnost v čase</h2>
      <div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <canvas id="visits-chart" style="max-height: 400px;"></canvas>
      </div>
    </div>

  </div>

  <p style="text-align: center; color: #999; margin-top: 1.5rem; font-size: 0.7rem;">Analytics data loading...</p>

</div>
</main>

<script src="assets/js/logger.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
<script src="assets/js/analytics.js" defer></script>
</body>
</html>
