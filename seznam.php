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
  <link rel="preload" href="assets/css/styles.min.css?v=20251121-02" as="style">
  <link rel="preload" href="assets/css/seznam.min.css?v=20251121-02" as="style">

  <link rel="stylesheet" href="assets/css/styles.min.css?v=20251121-02">
  <link rel="stylesheet" href="assets/css/seznam.min.css?v=20251121-02">
  <link rel="stylesheet" href="assets/css/seznam-mobile-fixes.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.css?v=20251121-02">
<?php if ($isAdmin): ?>
<link rel="stylesheet" href="assets/css/admin-header.css">
<?php endif; ?>
  <!-- Univerzální tmavý styl pro všechny modály -->
  <link rel="stylesheet" href="assets/css/universal-modal-theme.css">
<style>
.search-bar {
  margin-top: 2rem !important;
}

/* MOBILNÍ OPTIMALIZACE SEARCH BAR */
@media (max-width: 768px) {
  .search-bar {
    padding: 0.15rem 0.4rem !important;
    margin-top: 0.05rem !important;
    margin-bottom: 0.2rem !important;
    border: 2px solid #1a1a1a !important;
    line-height: 1.0 !important;
    min-height: 16px !important;
  }

  .search-input,
  .search-input:focus,
  .search-input:active,
  input.search-input,
  input#searchInput {
    font-size: 0.65rem !important;
    line-height: 1.0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-width: 0 !important;
    outline: 0 !important;
    outline-width: 0 !important;
    box-shadow: none !important;
    -webkit-box-shadow: none !important;
    background: transparent !important;
    -webkit-appearance: none !important;
    appearance: none !important;
    border-radius: 0 !important;
  }

  .search-icon {
    font-size: 0.75rem !important;
    line-height: 1.0 !important;
  }

  .search-clear {
    font-size: 0.55rem !important;
    padding: 0.15rem 0.4rem !important;
    line-height: 1.0 !important;
  }
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

/* PAGINATION FIX: Load More Button */
.load-more-btn {
  display: block;
  margin: 2rem auto;
  padding: 1rem 2rem;
  background: #333333;
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

/* MAZÁNÍ FOTEK: Křížek na miniatuře */
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

/* MINIMALISTICKÝ REDESIGN: Zmenšení info panelů a nadpisů */
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

/* POSUN MODALU OD HEADERU */
.modal-overlay {
  padding-top: 3rem !important;
}

.modal-content {
  margin-top: 2rem !important;
}

/* DETAIL OVERLAY - TMAVÉ POZADÍ KOMPLET */
#detailOverlay {
  --c-bg: #1a1a1a;  /* Tmavé pozadí pro všechny vnořené prvky */
  --c-bg-card: #222222;
  --c-text: #ffffff;
  --c-text-muted: #aaaaaa;
  --c-border: #333333;
  background: #0a0a0a !important;
}

#detailOverlay .modal-content {
  background: #1a1a1a !important;
  border: none !important;
  box-shadow: none !important;
  max-width: 100% !important;
  width: 100% !important;
  color: #ffffff !important;
}

/* Hlavička modalu - větší a vycentrovaná */
#detailOverlay .modal-header {
  background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%) !important;
  padding: 1.5rem 2rem !important;
  text-align: center !important;
  border-bottom: 1px solid #333333 !important;
}

#detailOverlay .modal-title {
  font-size: 1.4rem !important;
  font-weight: 600 !important;
  color: #ffffff !important;
  margin-bottom: 0.5rem !important;
  text-align: center !important;
}

#detailOverlay .modal-subtitle {
  font-size: 0.9rem !important;
  color: #888888 !important;
  text-align: center !important;
}

@media (max-width: 768px) {
  #detailOverlay .modal-header {
    padding: 1rem 1.5rem !important;
  }

  #detailOverlay .modal-title {
    font-size: 1.1rem !important;
    padding-right: 2.5rem !important; /* Místo pro zavírací tlačítko */
  }

  #detailOverlay .modal-subtitle {
    font-size: 0.75rem !important;
  }
}

/* Tmavý styl pro všechny sekce v detailu */
#detailOverlay .modal-body {
  background: #1a1a1a !important;
  color: #ffffff !important;
}

#detailOverlay .modal-section {
  background: #222222 !important;
  border: 1px solid #333333 !important;
  color: #ffffff !important;
}

#detailOverlay .section-title {
  color: #aaaaaa !important;
  border-bottom-color: #333333 !important;
}

#detailOverlay .info-grid {
  color: #ffffff !important;
}

#detailOverlay .info-label {
  color: #888888 !important;
}

#detailOverlay .info-value {
  color: #ffffff !important;
}

/* Editovatelná pole - tmavá */
#detailOverlay .editable-field {
  background: #2a2a2a !important;
  border-color: #444444 !important;
}

#detailOverlay .field-label {
  color: #888888 !important;
}

#detailOverlay .field-input,
#detailOverlay .field-textarea,
#detailOverlay input,
#detailOverlay textarea,
#detailOverlay select {
  background: #333333 !important;
  border-color: #444444 !important;
  color: #ffffff !important;
}

#detailOverlay .field-input::placeholder,
#detailOverlay .field-textarea::placeholder,
#detailOverlay input::placeholder,
#detailOverlay textarea::placeholder {
  color: #666666 !important;
}

/* Kalendář - tmavý */
#detailOverlay .calendar-container {
  background: #222222 !important;
}

#detailOverlay .calendar-weekdays {
  color: #888888 !important;
}

#detailOverlay .cal-day {
  background: #2a2a2a !important;
  border-color: #444444 !important;
  color: #ffffff !important;
}

#detailOverlay .cal-day:hover {
  background: #333333 !important;
  border-color: #666666 !important;
}

#detailOverlay .cal-day.selected {
  background: #ffffff !important;
  color: #000000 !important;
  border-color: #ffffff !important;
}

#detailOverlay .cal-day.occupied {
  background: #3a2020 !important;
  border-color: #8b0000 !important;
  color: #ff6666 !important;
}

/* Časové sloty - tmavé */
#detailOverlay .time-slot {
  background: #2a2a2a !important;
  border-color: #444444 !important;
  color: #ffffff !important;
}

#detailOverlay .time-slot:hover {
  background: #333333 !important;
  border-color: #666666 !important;
}

#detailOverlay .time-slot.selected {
  background: #ffffff !important;
  color: #000000 !important;
  border-color: #ffffff !important;
}

#detailOverlay .time-slot.occupied {
  background: #3a2020 !important;
  border-color: #8b0000 !important;
  color: #ff6666 !important;
}

/* Panel vzdálenosti - tmavý */
#detailOverlay .distance-info-panel {
  background: #222222 !important;
  border-color: #333333 !important;
}

#detailOverlay .distance-info-title {
  color: #888888 !important;
}

#detailOverlay .distance-stat {
  background: #2a2a2a !important;
  border-color: #444444 !important;
}

#detailOverlay .distance-stat-label {
  color: #888888 !important;
}

#detailOverlay .distance-stat-value {
  color: #ffffff !important;
}

#detailOverlay .route-item {
  color: #ffffff !important;
}

#detailOverlay .route-distance {
  background: #333333 !important;
  border-color: #444444 !important;
  color: #ffffff !important;
}

/* Rezervace na den - tmavé */
#detailOverlay .day-bookings {
  background: #2a1515 !important;
  border-color: #8b0000 !important;
}

#detailOverlay .day-bookings h4 {
  color: #ff6666 !important;
}

#detailOverlay .booking-item {
  background: #1a1a1a !important;
  border-color: #444444 !important;
  color: #ffffff !important;
}

/* Tlačítka v detailu - tmavá */
#detailOverlay .btn {
  background: #333333 !important;
  border-color: #555555 !important;
  color: #ffffff !important;
}

#detailOverlay .btn:hover {
  background: #444444 !important;
  border-color: #666666 !important;
}

#detailOverlay .btn-success {
  background: #2a4a2a !important;
  border-color: #3a6a3a !important;
}

#detailOverlay .btn-danger {
  background: #4a2a2a !important;
  border-color: #8b0000 !important;
  color: #ff6666 !important;
}

/* Modal actions - tmavé */
#detailOverlay .modal-actions {
  background: #1a1a1a !important;
  border-top-color: #333333 !important;
}

/* Zavírací tlačítko */
#detailOverlay .modal-close {
  color: #ffffff !important;
  background: #333333 !important;
}

#detailOverlay .modal-close:hover {
  background: #444444 !important;
}

/* Mapa - tmavé pozadí */
#detailOverlay .map-panel {
  background: #222222 !important;
  border-color: #333333 !important;
}

#detailOverlay .map-toggle {
  background: #333333 !important;
  color: #ffffff !important;
}

#detailOverlay .map-content {
  background: #1a1a1a !important;
}

#detailOverlay .map-stat {
  background: #2a2a2a !important;
  border-color: #444444 !important;
}

#detailOverlay .map-stat-label {
  color: #888888 !important;
}

#detailOverlay .map-stat-value {
  color: #ffffff !important;
}

/* Fotky - tmavé pozadí */
#detailOverlay .foto-wrapper {
  background: #222222 !important;
  border-color: #444444 !important;
}

/* Text overlay pro popisy */
#detailOverlay div[onclick*="showTextOverlay"] {
  background: #2a2a2a !important;
  border-color: #444444 !important;
  color: #ffffff !important;
}

/* Poznámky badge */
#detailOverlay .order-notes-badge {
  background: #444444 !important;
  color: #ffffff !important;
}

/* Skrýt pull-to-refresh když je modal otevřený */
.modal-overlay.active ~ #pull-refresh-indicator,
#detailOverlay.active ~ #pull-refresh-indicator,
body:has(.modal-overlay.active) #pull-refresh-indicator {
  display: none !important;
  height: 0 !important;
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

/* MOBILNÍ OPTIMALIZACE KARET ZÁKAZNÍKŮ */
@media (max-width: 768px) {
  /* Karty zákazníků - kompaktní pro zobrazení více karet (50% menší) */
  .order-box {
    padding: 0.3rem !important;
    margin-bottom: 0.25rem !important;
    min-height: auto !important;
  }

  .order-header {
    margin-bottom: 0.1rem !important;
    padding-bottom: 0.05rem !important;
  }

  .order-number,
  .order-detail-line {
    font-family: 'Poppins', sans-serif !important;
    font-size: 0.6rem !important;
    font-weight: 500 !important;
    line-height: 1.2 !important;
  }

  .order-status {
    width: 6px !important;
    height: 6px !important;
  }

  .order-notes-badge {
    font-size: 0.5rem !important;
    min-width: 14px !important;
    height: 14px !important;
    padding: 0 0.2rem !important;
  }

  .order-body {
    gap: 0.05rem !important;
  }

  .order-customer {
    font-family: 'Poppins', sans-serif !important;
    font-size: 0.65rem !important;
    font-weight: 600 !important;
    margin-bottom: 0.05rem !important;
  }

  .order-detail {
    gap: 0.02rem !important;
  }

  /* Grid - menší mezery mezi kartami */
  .order-grid,
  #orderGrid {
    gap: 0.25rem !important;
    padding: 0.1rem !important;
  }
}

/* Extra malé displeje - ještě kompaktnější (50% menší) */
@media (max-width: 480px) {
  .order-box {
    padding: 0.25rem !important;
    margin-bottom: 0.2rem !important;
  }

  .order-header {
    margin-bottom: 0.08rem !important;
    padding-bottom: 0.03rem !important;
  }

  .order-number,
  .order-detail-line {
    font-size: 0.55rem !important;
    line-height: 1.1 !important;
  }

  .order-body {
    gap: 0.03rem !important;
  }

  .order-customer {
    font-size: 0.6rem !important;
    margin-bottom: 0.03rem !important;
  }

  .order-detail {
    gap: 0.01rem !important;
  }

  .order-grid,
  #orderGrid {
    gap: 0.2rem !important;
    padding: 0.05rem !important;
  }
}

/* MOBILNÍ OPTIMALIZACE FILTER TLAČÍTEK (větší o 50% pro lepší klikání) */
@media (max-width: 768px) {
  /* Filter bar - 2x2 grid s mezerami */
  .filter-bar {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 0.5rem !important;
    margin-bottom: 0.5rem !important;
    padding: 0.25rem !important;
  }

  .filter-btn {
    width: 100% !important;
    padding: 0.5rem 0.6rem !important;
    font-size: 0.7rem !important;
    line-height: 1.2 !important;
    min-height: 36px !important;
    border-width: 1px !important;
    border-radius: 6px !important;
  }

  .filter-btn.active {
    border-width: 2px !important;
  }
}

@media (max-width: 375px) {
  .filter-btn {
    padding: 0.4rem 0.5rem !important;
    font-size: 0.65rem !important;
    line-height: 1.2 !important;
    min-height: 32px !important;
  }

  .filter-bar {
    gap: 0.4rem !important;
  }
}

/* MOBILNÍ OPTIMALIZACE KALENDÁŘE A PANELŮ */
@media (max-width: 768px) {
  /* Modal title - "Vyberte termín návštěvy" menší pro mobil */
  .modal-title {
    font-size: 0.9rem !important;
    line-height: 1.3;
    padding-right: 2.5rem;
  }

  /* Červený obdélník s termíny - VELMI KOMPAKTNÍ */
  .day-bookings {
    margin-top: 0.5rem !important;
    padding: 0.3rem !important;
  }

  .day-bookings h4 {
    font-size: 0.65rem !important;
    margin-bottom: 0.3rem !important;
    letter-spacing: 0.03em;
  }

  .booking-item {
    padding: 0.25rem !important;
    margin-bottom: 0.2rem !important;
    font-size: 0.6rem !important;
  }

  .booking-item:last-child {
    margin-bottom: 0 !important;
  }

  /* Panel trasy - VELMI KOMPAKTNÍ */
  .distance-info-panel {
    padding: 0.3rem !important;
    margin: 0.3rem 0 !important;
  }

  .distance-info-title {
    font-size: 0.6rem !important;
    margin-bottom: 0.3rem !important;
  }

  .distance-stats {
    gap: 0.25rem !important;
  }

  .distance-stat {
    padding: 0.25rem !important;
  }

  .distance-stat-label {
    font-size: 0.5rem !important;
    margin-bottom: 0.15rem !important;
  }

  .distance-stat-value {
    font-size: 0.75rem !important;
  }

  .distance-stat-unit {
    font-size: 0.6rem !important;
  }

  /* Položky trasy - VELMI KOMPAKTNÍ */
  .route-info {
    margin-top: 0.3rem !important;
    padding-top: 0.3rem !important;
  }

  .route-item {
    padding: 0.2rem !important;
    margin-bottom: 0.2rem !important;
    font-size: 0.6rem !important;
  }

  .route-item.new-customer {
    padding: 0.25rem !important;
    margin: 0.2rem 0 !important;
  }

  .route-distance {
    padding: 0.15rem 0.3rem !important;
    font-size: 0.6rem !important;
    min-width: 55px !important;
  }

  .route-arrow {
    font-size: 0.6rem !important;
  }
}

/* Ještě menší displeje - ultra kompaktní */
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

/* DETAIL ZÁKAZNÍKA - MOBILNÍ OPTIMALIZACE */
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

  .modal-body span[style*="font-weight: 600"],
  .modal-body label[style*="font-weight: 600"] {
    display: block !important;
    margin-bottom: 0.25rem !important;
    font-size: 0.55rem !important;
  }
}

/* KOMPLETNÍ OPTIMALIZACE DETAIL MODALU - ULTRA KOMPAKTNÍ STYL */
@media (max-width: 768px) {
  /* Modal overlay - lepší scrollování, uprostřed displeje */
  .modal-overlay {
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
    touch-action: pan-y !important;
    align-items: center !important;
    padding: 2rem 0.5rem !important;
  }

  .modal-content {
    margin: 0 auto !important;
    max-height: none !important;
  }

  /* Modal header - ultra kompaktní */
  .modal-header {
    padding: 0.2rem !important;
  }

  .modal-title {
    font-size: 0.85rem !important;
    margin-bottom: 0.08rem !important;
    line-height: 1.05 !important;
  }

  .modal-subtitle {
    font-size: 0.5rem !important;
    line-height: 1.05 !important;
  }

  .modal-close {
    width: 35px !important;
    height: 35px !important;
    font-size: 1.75rem !important;
    top: 0.3rem !important;
    right: 0.3rem !important;
    padding: 0.35rem !important;
  }

  /* Modal body - ultra kompaktní padding */
  .modal-body {
    padding: 0.2rem !important;
  }

  /* Section titles - ultra menší */
  .section-title {
    font-size: 0.55rem !important;
    margin-bottom: 0.15rem !important;
    padding-bottom: 0.08rem !important;
  }

  /* Info grid - ultra kompaktní */
  .info-grid {
    grid-template-columns: 60px 1fr !important;
    gap: 0.15rem !important;
    font-size: 0.6rem !important;
  }

  .info-label {
    font-size: 0.55rem !important;
  }

  .info-value {
    font-size: 0.6rem !important;
  }

  /* Modal sections - minimální mezery */
  .modal-section {
    margin-bottom: 0.3rem !important;
  }

  /* Editable fields - ultra kompaktní */
  .editable-field {
    padding: 0.15rem !important;
    margin-bottom: 0.2rem !important;
  }

  .field-label {
    font-size: 0.55rem !important;
    margin-bottom: 0.08rem !important;
  }

  .field-input {
    padding: 0.15rem !important;
    font-size: 0.6rem !important;
  }

  .field-textarea {
    padding: 0.15rem !important;
    font-size: 0.6rem !important;
    min-height: 30px !important;
    line-height: 1.15 !important;
  }

  /* Všechna tlačítka v modalu - decentní font */
  .modal-body .btn,
  .modal-actions .btn {
    padding: 0.375rem 0.6rem !important;
    font-size: 0.65rem !important;
    min-height: 27px !important;
    line-height: 1.2 !important;
    letter-spacing: 0.05em !important;
  }

  /* Modal actions - ultra kompaktní footer */
  .modal-actions {
    padding: 0.3rem !important;
    gap: 0.2rem !important;
  }

  /* Calendar container - velmi kompaktní */
  .calendar-container {
    gap: 0.2rem !important;
  }

  .calendar-controls {
    margin-bottom: 0.2rem !important;
  }

  .calendar-month-title {
    font-size: 0.65rem !important;
  }

  .calendar-nav-btn {
    padding: 0.375rem 0.6rem !important;
    font-size: 0.65rem !important;
    min-height: 27px !important;
  }

  .calendar-weekdays {
    font-size: 0.5rem !important;
    gap: 0.05rem !important;
    margin-bottom: 0.05rem !important;
  }

  .calendar-weekdays > div {
    padding: 0.1rem !important;
  }

  .calendar-days {
    gap: 0.05rem !important;
  }

  .cal-day {
    padding: 0.2rem !important;
    font-size: 0.6rem !important;
    min-height: 22px !important;
  }

  /* Time grid - velmi kompaktní */
  #timeGrid {
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 0.05rem !important;
    margin-top: 0.2rem !important;
  }

  .time-slot {
    padding: 0.2rem 0.1rem !important;
    font-size: 0.55rem !important;
    min-height: 20px !important;
  }

  #selectedDateDisplay {
    margin-top: 0.15rem !important;
    font-size: 0.6rem !important;
  }

  /* Photo grid - minimální mezery */
  .modal-body div[style*="display: grid"][style*="grid-template-columns"] {
    gap: 0.15rem !important;
  }

  /* PDF tlačítka - decentní font */
  .modal-body button[onclick*="PDF"],
  .modal-body button[onclick*="pdf"] {
    padding: 0.375rem 0.6rem !important;
    font-size: 0.65rem !important;
    min-height: 27px !important;
  }

  /* Textové bloky POPIS PROBLÉMU a DOPLŇUJÍCÍ INFO - decentní font */
  .modal-body div[onclick*="showTextOverlay"] {
    font-size: 0.55rem !important;
    padding: 0.15rem !important;
    min-height: 30px !important;
    line-height: 1.15 !important;
  }

  /* Input fields v inline stylu - ultra kompaktní */
  .modal-body input[style*="padding"] {
    padding: 0.15rem 0.2rem !important;
    font-size: 0.6rem !important;
  }

  .modal-body textarea[style*="padding"] {
    padding: 0.15rem 0.2rem !important;
    font-size: 0.6rem !important;
    min-height: 30px !important;
    line-height: 1.15 !important;
  }

  /* Select dropdowns - ultra kompaktní */
  .modal-body select {
    padding: 0.15rem 0.2rem !important;
    font-size: 0.6rem !important;
  }

  /* Map panel toggle - kompaktní */
  .map-toggle {
    padding: 0.3rem 0.5rem !important;
    font-size: 0.65rem !important;
  }

  .map-toggle-icon {
    font-size: 0.9rem !important;
  }

  .map-content {
    padding: 0.5rem !important;
  }

  /* Map stats - kompaktní */
  .map-stats {
    gap: 0.3rem !important;
    margin-bottom: 0.5rem !important;
  }

  .map-stat {
    padding: 0.3rem !important;
  }

  .map-stat-label {
    font-size: 0.6rem !important;
    margin-bottom: 0.2rem !important;
  }

  .map-stat-value {
    font-size: 0.95rem !important;
  }

  .map-stat-unit {
    font-size: 0.65rem !important;
  }
}

/* Extra malé displeje - ultra kompaktnější modal */
@media (max-width: 480px) {
  .modal-header {
    padding: 0.15rem !important;
  }

  .modal-title {
    font-size: 0.55rem !important;
  }

  .modal-subtitle {
    font-size: 0.48rem !important;
  }

  .modal-body {
    padding: 0.15rem !important;
  }

  .section-title {
    font-size: 0.5rem !important;
    margin-bottom: 0.1rem !important;
    padding-bottom: 0.05rem !important;
  }

  .info-grid {
    grid-template-columns: 55px 1fr !important;
    gap: 0.1rem !important;
    font-size: 0.55rem !important;
  }

  .info-label {
    font-size: 0.5rem !important;
  }

  .info-value {
    font-size: 0.55rem !important;
  }

  .field-input,
  .field-textarea {
    font-size: 0.55rem !important;
    padding: 0.1rem !important;
  }

  .field-label {
    font-size: 0.5rem !important;
    margin-bottom: 0.05rem !important;
  }

  .editable-field {
    padding: 0.1rem !important;
    margin-bottom: 0.15rem !important;
  }

  .modal-section {
    margin-bottom: 0.2rem !important;
  }

  .modal-actions {
    padding: 0.2rem !important;
    gap: 0.15rem !important;
  }

  .modal-body .btn,
  .modal-actions .btn {
    padding: 0.375rem 0.6rem !important;
    font-size: 0.65rem !important;
    min-height: 27px !important;
  }

  .cal-day {
    padding: 0.2rem !important;
    font-size: 0.6rem !important;
    min-height: 20px !important;
  }

  .time-slot {
    padding: 0.2rem 0.1rem !important;
    font-size: 0.55rem !important;
    min-height: 20px !important;
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

<!-- Analytics Tracker -->
<?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
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
    <input type="text" class="search-input" id="searchInput"
           data-lang-cs-placeholder="Hledat v reklamacích..."
           data-lang-en-placeholder="Search in claims..."
           data-lang-it-placeholder="Cerca nei reclami..."
           placeholder="Hledat v reklamacích...">
    <button class="search-clear" id="searchClear">×</button>
  </div>

  <!-- FILTERS -->
  <div class="filter-bar">
    <button class="filter-btn active" data-filter="all" data-lang-cs="Všechny" data-lang-en="All" data-lang-it="Tutti">
      Všechny <span id="count-all" style="opacity: 0.7;"></span>
    </button>
    <button class="filter-btn" data-filter="wait" data-lang-cs="Čekající" data-lang-en="Waiting" data-lang-it="In Attesa">
      Čekající <span id="count-wait" style="opacity: 0.7;"></span>
    </button>
    <button class="filter-btn" data-filter="open" data-lang-cs="V řešení" data-lang-en="In Progress" data-lang-it="In Corso">
      V řešení <span id="count-open" style="opacity: 0.7;"></span>
    </button>
    <button class="filter-btn" data-filter="done" data-lang-cs="Vyřízené" data-lang-en="Completed" data-lang-it="Completato">
      Vyřízené <span id="count-done" style="opacity: 0.7;"></span>
    </button>
  </div>

  <!-- INDIKÁTOR NOVÝCH POZNÁMEK -->
  <div id="unreadNotesIndicator" style="display: none; text-align: center; padding: 0.5rem; margin: 0.5rem 0; background: rgba(255, 0, 0, 0.05); border-radius: 5px; cursor: pointer;" data-action="filterUnreadNotes">
    <span style="color: #d32f2f; font-size: 0.85rem; font-weight: 600;">
      Máte <span id="unreadNotesCount">0</span> nových poznámek
    </span>
  </div>

  <!-- GRID -->
  <div class="order-grid" id="orderGrid">
    <div class="loading" data-lang-cs="Načítání reklamací..." data-lang-en="Loading claims..." data-lang-it="Caricamento reclami...">Načítání reklamací...</div>
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
  <div class="loading-text" id="loadingText" data-lang-cs="Ukládám termín..." data-lang-en="Saving appointment..." data-lang-it="Salvataggio appuntamento...">Ukládám termín...</div>
</div>

<!-- External JavaScript -->
<script src="assets/js/seznam.js?v=20251123-01" defer></script>
<script src="assets/js/seznam-delete-patch.js" defer></script>
<script src="assets/js/pwa-notifications.js" defer></script>

<!-- EMERGENCY FIX: Event delegation pro tlačítka v detailu -->
<script>
// CACHE BUSTER: 2025-11-23-19:35:00 - VLASTNÍ MODAL DIALOG
document.addEventListener('DOMContentLoaded', () => {
  console.log('[Seznam] EMERGENCY event delegation V5 se nacita... [2025-11-23-19:35:00 - VLASTNI MODAL]');

  document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-action]');
    if (!button) return;

    const action = button.getAttribute('data-action');
    const id = button.getAttribute('data-id');
    const url = button.getAttribute('data-url');

    console.log(`[EMERGENCY] Tlačítko kliknuto: ${action}`, { id, url });

    switch (action) {
      // case 'reopenOrder' - REMOVED: normální handler v seznam.js funguje správně

      case 'showContactMenu':
        if (id && typeof showContactMenu === 'function') {
          showContactMenu(id);
        }
        break;

      case 'showCustomerDetail':
        if (id && typeof showCustomerDetail === 'function') {
          showCustomerDetail(id);
        }
        break;

      case 'openPDF':
        if (!url) {
          console.error('[EMERGENCY] PDF URL chybi!');
          break;
        }

        console.log('[EMERGENCY] Oteviram PDF:', url);

        // Obejit pop-up blocker: Otevrit v SOUCASNEM okne misto noveho tabu
        // Uzivatel muze pouzit "Zpet" pro navrat
        window.location.href = url;
        console.log('[EMERGENCY] Presmerovani na PDF');
        break;

      case 'closeDetail':
        if (typeof closeDetail === 'function') {
          closeDetail();
        }
        break;

      case 'startVisit':
        if (id && typeof startVisit === 'function') {
          console.log('[EMERGENCY] Zahajuji navstevu ID:', id);
          startVisit(id);
        } else {
          console.error('[EMERGENCY] startVisit funkce neni dostupna nebo ID chybi');
        }
        break;

      case 'showCalendar':
        if (id && typeof showCalendar === 'function') {
          console.log('[EMERGENCY] Oteviram kalendar pro ID:', id);
          showCalendar(id);
        } else {
          console.error('[EMERGENCY] showCalendar funkce neni dostupna nebo ID chybi');
        }
        break;

      default:
        console.warn(`[EMERGENCY] Neznámá akce: ${action}`);
    }
  });

  console.log('[Seznam] Event delegation V5 nacten - VLASTNI MODAL DIALOG');
});
</script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
