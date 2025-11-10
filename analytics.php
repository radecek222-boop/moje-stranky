<?php
require_once "init.php";

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=analytics.php');
    exit;
}
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
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<!-- MAIN CONTENT -->
<main>
<div class="container">

  <div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 1.5rem; padding: 1rem; background: var(--c-white); border: 1px solid var(--c-border); border-radius: 12px;">
    <div class="user-info" style="font-weight: 600; letter-spacing: 0.03em; color: var(--c-text-primary, #1d1f2c); text-transform: uppercase; font-size: 0.85rem;">
      <span style="font-weight: 400; margin-right: 0.5rem; color: var(--c-text-secondary, #6b7280); text-transform: none;">Přihlášený uživatel:</span>
      <span class="user-name" id="userName" style="font-size: 1rem; color: var(--c-accent, #0f766e);"><?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
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
  
  <p style="text-align: center; color: #999; margin-top: 2rem;">Analytics data loading...</p>

</div>
</main>

<script src="assets/js/logger.js" defer></script>
<script src="assets/js/analytics.min.js" defer></script>
</body>
</html>
