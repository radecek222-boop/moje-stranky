<?php require_once "init.php"; ?>
<?php
// Detect embed mode
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Kontrola přihlášení a role
$isLoggedIn = isset($_SESSION["user_id"]);
$isAdmin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;

// Export user data pro JavaScript
$currentUserData = [
    "id" => $_SESSION["user_id"] ?? $_SESSION["admin_id"] ?? null,
    "name" => $_SESSION["user_name"] ?? "Admin",
    "email" => $_SESSION["user_email"] ?? "admin@wgs-service.cz",
    "role" => $_SESSION["role"] ?? "admin",
    "is_admin" => $isAdmin
];

// Redirect nepřihlášené na login
if (!$isLoggedIn && !$isAdmin) {
    header('Location: login.php?redirect=seznam.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1a1a1a">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
  <title>Přehled reklamací | White Glove Service</title>
  <meta name="description" content="Seznam reklamací a servisních zakázek White Glove Service. Správa, filtrování a přehledy všech servisních požadavků.">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.js" defer></script>
<script src="assets/js/utils.js" defer></script>


<!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/seznam.min.css" as="style">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/seznam.min.css">
<?php if ($isAdmin): ?>
<link rel="stylesheet" href="assets/css/admin-header.css">
<?php endif; ?>
<style>
.search-bar {
  margin-top: 2rem !important;
}

/* Loading Overlay */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.8);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 10000;
  flex-direction: column;
}

.loading-overlay.show {
  display: flex;
}

.loading-spinner {
  border: 5px solid rgba(255, 255, 255, 0.2);
  border-top: 5px solid #00FF88;
  border-radius: 50%;
  width: 60px;
  height: 60px;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1.5rem;
}

.loading-text {
  color: white;
  font-size: 1.1rem;
  font-weight: 500;
  text-align: center;
  max-width: 80%;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* ✅ PAGINATION FIX: Load More Button */
.load-more-btn {
  display: block;
  margin: 2rem auto;
  padding: 1rem 2rem;
  background: #2D5016;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 500;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.load-more-btn:hover {
  background: #3d6b1f;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.load-more-btn:active {
  transform: translateY(0);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.load-more-btn:disabled {
  background: #666;
  cursor: not-allowed;
  opacity: 0.6;
}

/* ✅ MAZÁNÍ FOTEK: Křížek na miniatuře */
.foto-wrapper {
  position: relative;
}

.foto-delete-btn {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 28px;
  height: 28px;
  background: rgba(220, 38, 38, 0.95);
  color: white;
  border: 2px solid white;
  border-radius: 50%;
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
  z-index: 10;
  padding: 0;
  font-weight: bold;
}

.foto-delete-btn:hover {
  background: rgba(185, 28, 28, 1);
  transform: scale(1.1);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.5);
}

.foto-delete-btn:active {
  transform: scale(0.95);
}

.foto-wrapper:hover .foto-delete-btn {
  opacity: 1;
}

/* ✅ MINIMALISTICKÝ REDESIGN: Zmenšení info panelů a nadpisů */
.info-grid {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.5rem 1rem;
  font-size: 0.85rem;
}

.info-label {
  font-weight: 600;
  color: #666;
  font-size: 0.8rem;
}

.info-value {
  color: #1a1a1a;
  font-size: 0.85rem;
}

.section-title {
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
  color: #1a1a1a;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.modal-section {
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: #f8f9fa;
  border-radius: 6px;
}

.modal-actions .btn {
  padding: 0.4rem 0.75rem !important;
  min-height: 32px !important;
  font-size: 0.85rem !important;
}

.modal-actions {
  gap: 0.75rem !important;
  padding: 0.75rem 1rem !important;
}

/* ✅ POSUN MODALU OD HEADERU */
.modal-overlay {
  padding-top: 3rem !important;
}

.modal-content {
  margin-top: 2rem !important;
}

/* Barevné nádechy karet podle stavu (velmi světlé) */
.order-box.status-bg-wait {
  background: rgba(255, 235, 59, 0.08) !important; /* Žlutá nádech - ČEKÁ */
}

.order-box.status-bg-open {
  background: rgba(33, 150, 243, 0.08) !important; /* Modrá nádech - DOMLUVENÁ */
}

.order-box.status-bg-done {
  background: rgba(76, 175, 80, 0.08) !important; /* Zelená nádech - HOTOVO */
}

/* Zachovat original při hover */
.order-box.status-bg-wait:hover {
  background: rgba(255, 235, 59, 0.12) !important; /* Trochu více na hover */
}

.order-box.status-bg-open:hover {
  background: rgba(33, 150, 243, 0.12) !important;
}

.order-box.status-bg-done:hover {
  background: rgba(76, 175, 80, 0.12) !important;
}

/* 📱 MOBILNÍ OPTIMALIZACE KALENDÁŘE A PANELŮ */
@media (max-width: 768px) {
  /* Modal title - "Vyberte termín návštěvy" menší pro mobil */
  .modal-title {
    font-size: 0.9rem !important;
    line-height: 1.3;
    padding-right: 2.5rem;
  }

  /* Červený obdélník s termíny - KOMPAKTNÍ */
  .day-bookings {
    margin-top: 0.8rem !important;
    padding: 0.5rem !important;
  }

  .day-bookings h4 {
    font-size: 0.75rem !important;
    margin-bottom: 0.5rem !important;
    letter-spacing: 0.03em;
  }

  .booking-item {
    padding: 0.4rem !important;
    margin-bottom: 0.3rem !important;
    font-size: 0.7rem !important;
  }

  .booking-item:last-child {
    margin-bottom: 0 !important;
  }

  /* Panel trasy - KOMPAKTNÍ */
  .distance-info-panel {
    padding: 0.5rem !important;
    margin: 0.5rem 0 !important;
  }

  .distance-info-title {
    font-size: 0.65rem !important;
    margin-bottom: 0.5rem !important;
  }

  .distance-stats {
    gap: 0.4rem !important;
  }

  .distance-stat {
    padding: 0.4rem !important;
  }

  .distance-stat-label {
    font-size: 0.55rem !important;
    margin-bottom: 0.2rem !important;
  }

  .distance-stat-value {
    font-size: 0.85rem !important;
  }

  .distance-stat-unit {
    font-size: 0.65rem !important;
  }

  /* Položky trasy - KOMPAKTNÍ */
  .route-info {
    margin-top: 0.5rem !important;
    padding-top: 0.5rem !important;
  }

  .route-item {
    padding: 0.3rem !important;
    margin-bottom: 0.3rem !important;
    font-size: 0.65rem !important;
  }

  .route-item.new-customer {
    padding: 0.4rem !important;
    margin: 0.3rem 0 !important;
  }

  .route-distance {
    padding: 0.2rem 0.4rem !important;
    font-size: 0.65rem !important;
    min-width: 60px !important;
  }

  .route-arrow {
    font-size: 0.65rem !important;
  }
}

/* 📱 Ještě menší displeje - ultra kompaktní */
@media (max-width: 480px) {
  .modal-title {
    font-size: 0.8rem !important;
  }

  .day-bookings h4 {
    font-size: 0.7rem !important;
  }

  .booking-item {
    padding: 0.3rem !important;
    font-size: 0.65rem !important;
  }

  .distance-info-title {
    font-size: 0.6rem !important;
  }

  .distance-stat-value {
    font-size: 0.75rem !important;
  }
}

/* 📱 DETAIL ZÁKAZNÍKA - MOBILNÍ OPTIMALIZACE */
@media (max-width: 768px) {
  /* Grid v detailu zákazníka - jednoduchý layout na mobilu */
  .modal-body > div[style*="grid-template-columns"] {
    display: block !important;
  }

  .modal-body > div[style*="grid-template-columns"] > div > div[style*="display: grid"] {
    display: block !important;
  }

  /* Labels a inputy v detailu zákazníka */
  .modal-body input[type="text"],
  .modal-body input[type="tel"],
  .modal-body input[type="email"] {
    width: 100% !important;
    margin-bottom: 0.75rem !important;
    box-sizing: border-box !important;
  }

  .modal-body span[style*="font-weight: 600"] {
    display: block !important;
    margin-bottom: 0.25rem !important;
    font-size: 0.75rem !important;
  }
}
</style>

<!-- Current User Data for JavaScript -->
<script>
const CURRENT_USER = <?php echo json_encode($currentUserData ?? [
  "id" => null,
  "name" => "Guest",
  "email" => "",
  "role" => "guest",
  "is_admin" => false
]); ?>;
</script>
</head>

<body>
<?php if (!$embedMode): ?>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<?php endif; ?>
<?php if ($isAdmin && !$embedMode): ?>
<?php endif; ?>

  <!-- SEARCH RESULTS INFO -->
  <div id="searchResultsInfo" style="display: none;"></div>
  <!-- SEARCH BAR -->
<div class="container"<?php if ($embedMode) echo ' style="margin-top: 0; padding-top: 1rem;"'; ?>>
  <div class="search-bar">
    <span class="search-icon"></span>
    <input type="text" class="search-input" id="searchInput" placeholder="Hledat v reklamacích...">
    <button class="search-clear" id="searchClear">×</button>
  </div>
  
  <!-- FILTERS -->
  <div class="filter-bar">
    <button class="filter-btn active" data-filter="all">
      Všechny <span id="count-all" style="opacity: 0.7;"></span>
    </button>
    <button class="filter-btn" data-filter="wait">
      Čekající <span id="count-wait" style="opacity: 0.7;"></span>
    </button>
    <button class="filter-btn" data-filter="open">
      V řešení <span id="count-open" style="opacity: 0.7;"></span>
    </button>
    <button class="filter-btn" data-filter="done">
      Vyřízené <span id="count-done" style="opacity: 0.7;"></span>
    </button>
  </div>
  
  <!-- GRID -->
  <div class="order-grid" id="orderGrid">
    <div class="loading">Načítání reklamací...</div>
  </div>
  
</div>

<!-- MODAL DETAIL -->
<div class="modal-overlay" id="detailOverlay">
  <div class="modal-content">
    <button class="modal-close" data-action="closeDetail">×</button>
    <div id="modalContent"></div>
  </div>
</div>

<!-- LOADING OVERLAY -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="loading-spinner"></div>
  <div class="loading-text" id="loadingText">Ukládám termín...</div>
</div>

<!-- External JavaScript -->
<script src="assets/js/seznam.js" defer></script>
    <script src="assets/js/seznam-delete-patch.js" defer></script>
</body>
</html>
