<?php require_once "init.php"; ?>
<?php
// Detect embed mode
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Kontrola přihlášení a role
$isLoggedIn = isset($_SESSION["user_id"]);
$isAdmin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;

// Export user data pro JavaScript
$currentUserId = $_SESSION["user_id"] ?? $_SESSION["admin_id"] ?? null;

// Načíst supervizované uživatele (pokud je přihlášený jako prodejce/supervizor)
$supervisedUserIds = [];
if ($currentUserId && !$isAdmin) {
    try {
        $pdo = getDbConnection();

        // Zjistit strukturu tabulky wgs_users
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $idCol = in_array('user_id', $columns) ? 'user_id' : 'id';
        $numericIdCol = 'id'; // Numeric ID pro supervisor_assignments

        // Nejdříve získat numerické ID aktuálního uživatele
        // (pokud session ukládá VARCHAR user_id, musíme najít odpovídající INT id)
        $currentNumericId = $currentUserId;
        if (!is_numeric($currentUserId)) {
            $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE user_id = :user_id LIMIT 1");
            $stmt->execute([':user_id' => $currentUserId]);
            $numericId = $stmt->fetchColumn();
            if ($numericId) {
                $currentNumericId = $numericId;
            }
        }

        // Načíst VARCHAR user_id kódy supervizovaných prodejců
        // (supervisor_assignments ukládá INT id, ale potřebujeme VARCHAR user_id pro porovnání s zpracoval_id)
        $stmt = $pdo->prepare("
            SELECT u.{$idCol}
            FROM wgs_supervisor_assignments sa
            JOIN wgs_users u ON u.{$numericIdCol} = sa.salesperson_user_id
            WHERE sa.supervisor_user_id = :user_id
        ");
        $stmt->execute([':user_id' => $currentNumericId]);
        $supervisedUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Tabulka možná ještě neexistuje - tiše ignorovat
        error_log("Supervisor table check: " . $e->getMessage());
    }
}

$currentUserData = [
    "id" => $currentUserId,
    "name" => $_SESSION["user_name"] ?? "Admin",
    "email" => $_SESSION["user_email"] ?? "",  // BEZ FALLBACKU - pokud neni, je prazdny
    "phone" => $_SESSION["user_phone"] ?? "",  // Telefon uzivatele pro notifikace
    "role" => $_SESSION["role"] ?? "admin",
    "is_admin" => $isAdmin,
    "supervised_user_ids" => $supervisedUserIds
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

  <!-- Favicon -->
  <link rel="icon" type="image/png" sizes="192x192" href="/icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="/icon512.png">
  <link rel="apple-touch-icon" href="/icon192.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js" defer></script>
<script src="assets/js/utils.min.js" defer></script>


<!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="preload" href="assets/css/styles.min.css?v=20251121-02" as="style">
  <link rel="preload" href="assets/css/seznam.min.css?v=20251202" as="style">

  <link rel="stylesheet" href="assets/css/styles.min.css?v=20251121-02">
  <link rel="stylesheet" href="assets/css/seznam.min.css?v=20251202">
  <!-- seznam-mobile-fixes.css sloučen do seznam.css (Step 50) -->
  <link rel="stylesheet" href="assets/css/button-fixes-global.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css?v=20251121-02">
<?php if ($isAdmin): ?>
<link rel="stylesheet" href="assets/css/admin-header.min.css">
<?php endif; ?>
  <!-- Univerzální tmavý styl pro všechny modály -->
  <link rel="stylesheet" href="assets/css/universal-modal-theme.min.css">
<style>
/* ============================================
   🔧 FIX: iOS/Safari/PWA Modal Scroll Lock
   ============================================ */

/* Scroll lock pro html a body když je modal otevřený */
html.modal-open {
  overflow: hidden !important;
}

/* Omezit scroll lock jen na body, aby layout headeru nezkracoval ani při otevřeném modalu */
body.modal-open {
  overflow: hidden !important;
  position: fixed !important;
  width: 100% !important;
  top: calc(var(--scroll-y, 0px) * -1) !important;
  left: 0 !important;
  right: 0 !important;
}

/* PWA Standalone mode detekce */
@media all and (display-mode: standalone) {
  body.modal-open {
    /* PWA má jiné viewport chování */
    height: 100vh !important;
    position: fixed !important;
  }
}

/* iOS Safari specifické fixy */
@supports (-webkit-touch-callout: none) {
  /* Tohle targetuje pouze iOS Safari */
  body.modal-open {
    position: fixed !important;
    overflow: hidden !important;
    -webkit-overflow-scrolling: touch !important;
  }

  #detailOverlay.active {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    -webkit-overflow-scrolling: touch !important;
  }
}

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
  z-index: var(--z-modal-top, 10000);
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
  z-index: var(--z-sticky, 10);
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

/* POSUN MODALU OD HEADERU - pro běžné modaly */
.modal-overlay:not(#detailOverlay) {
  padding-top: 3rem !important;
}

.modal-overlay:not(#detailOverlay) .modal-content {
  margin-top: 2rem !important;
}

  /* DETAIL OVERLAY - TMAVÉ POZADÍ + PŘESNÉ CENTROVÁNÍ */
  #detailOverlay {
  --c-bg: #1a1a1a;
  --c-bg-card: #222222;
  --c-text: #ffffff;
  --c-text-muted: #aaaaaa;
  --c-border: #333333;
  background: #0a0a0a !important;
    /* FIX: Začít shora aby byla vidět hlavička */
    display: none !important; /* default hidden to avoid clipping hamburger menu */
    align-items: flex-start !important;
    justify-content: center !important;
    padding: 2rem 0 0 0 !important;
  /* FIX: Z-index nad hamburger headerem (který má 10001) */
    z-index: var(--z-detail-overlay, 10002) !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    pointer-events: none !important;
  }

  /* Show overlay only when activated by JS */
  #detailOverlay.active {
    display: flex !important;
    pointer-events: auto !important;
  }

#detailOverlay .modal-content {
  background: #1a1a1a !important;
  border: none !important;
  box-shadow: none !important;
  max-width: 500px !important;
  width: 95% !important;
  color: #ffffff !important;
  margin: 0 auto !important;
  /* FIX: iOS Safari viewport - použít dvh místo vh kde je podporováno */
  max-height: 90vh !important;
  max-height: 90dvh !important; /* Dynamic viewport height - Safari 15.4+ */
  overflow-y: auto !important;
  overflow-x: hidden !important;
  border-radius: 12px !important;

  /* FIX: iOS momentum scrolling - plynulý scroll */
  -webkit-overflow-scrolling: touch !important;

  /* FIX: Safari scrollbar fix */
  overscroll-behavior: contain !important;

  /* FIX: Pozice relativní pro správné centrování */
  position: relative !important;
}

/* Hlavička modalu - větší a vše vycentrováno */
#detailOverlay .modal-header {
  background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%) !important;
  padding: 1.5rem 2rem !important;
  text-align: center !important;
  border-bottom: 1px solid #333333 !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  position: relative !important; /* Pro absolutní pozici close tlačítka */
}

/* Tlačítko zavřít (X) v hlavičce - vždy viditelné */
#detailOverlay .modal-close-btn {
  position: absolute !important;
  top: 0.75rem !important;
  right: 0.75rem !important;
  width: 36px !important;
  height: 36px !important;
  border: none !important;
  background: rgba(255, 255, 255, 0.1) !important;
  color: #cccccc !important;
  font-size: 1.5rem !important;
  font-weight: 300 !important;
  line-height: 1 !important;
  cursor: pointer !important;
  border-radius: 50% !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  transition: all 0.2s ease !important;
  z-index: 10 !important;
  padding: 0 !important;
}

#detailOverlay .modal-close-btn:hover {
  background: rgba(255, 255, 255, 0.2) !important;
  color: #ffffff !important;
}

#detailOverlay .modal-close-btn:active {
  background: rgba(255, 255, 255, 0.3) !important;
  transform: scale(0.95) !important;
}

#detailOverlay .modal-title {
  font-size: 1.4rem !important;
  font-weight: 600 !important;
  color: #ffffff !important;
  margin-bottom: 0.5rem !important;
  text-align: center !important;
  width: 100% !important;
}

#detailOverlay .modal-subtitle {
  font-size: 0.9rem !important;
  color: #888888 !important;
  text-align: center !important;
  width: 100% !important;
}

/* Tlačítka v hlavičce - centrované (kromě close button) */
#detailOverlay .modal-header .btn,
#detailOverlay .modal-header button:not(.modal-close-btn) {
  margin: 0 auto !important;
}

@media (max-width: 768px) {
  /* Mobilní - začít shora aby byla vidět hlavička */
  #detailOverlay {
    padding: 1rem 0.5rem 0.5rem 0.5rem !important;
    /* FIX: iOS Safari - zajistit že overlay je přes celou obrazovku */
    height: 100vh !important;
    height: 100dvh !important; /* Dynamic viewport height */
  }

  #detailOverlay .modal-content {
    max-width: 100% !important;
    width: 100% !important;
    /* FIX: iOS Safari viewport - dynamická výška (vráceno na 90vh, problém byl v centrování) */
    max-height: 90vh !important;
    max-height: 90dvh !important; /* Dynamic viewport height - Safari 15.4+ */
    border-radius: 8px !important;

    /* FIX: Touch scrolling pro mobil */
    -webkit-overflow-scrolling: touch !important;
    overscroll-behavior: contain !important;
  }

  #detailOverlay .modal-header {
    padding: 1rem 1.5rem !important;
  }

  #detailOverlay .modal-title {
    font-size: 1.1rem !important;
    padding: 0 !important; /* Centrovaný - bez odsazení */
  }

  #detailOverlay .modal-subtitle {
    font-size: 0.75rem !important;
  }
}

/* ============================================
   🔧 FIX: PWA Standalone Mode Specifické Styly
   ============================================ */

@media all and (display-mode: standalone) {
  /* PWA má jiný viewport než Safari browser */
  #detailOverlay {
    /* Zajistit že overlay je přes celou obrazovku v PWA */
    height: 100vh !important;
    height: 100dvh !important;
  }

  #detailOverlay .modal-content {
    /* PWA modal - optimalizace pro standalone režim (vráceno na 90vh) */
    max-height: 90vh !important;
    max-height: 90dvh !important; /* Dynamic viewport height */

    /* PWA scroll fix */
    -webkit-overflow-scrolling: touch !important;
    overscroll-behavior-y: contain !important;
  }

  /* PWA - tlačítka větší pro lepší touch */
  #detailOverlay .modal-body .btn,
  #detailOverlay .modal-actions .btn {
    min-height: 48px !important; /* Apple touch target guideline */
    padding: 0.75rem 1rem !important;
    font-size: 0.95rem !important;
  }
}

/* iOS Safari v PWA módu - kombinace obou podmínek */
@supports (-webkit-touch-callout: none) {
  @media all and (display-mode: standalone) {
    #detailOverlay {
      /* iOS PWA specifický fix */
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      height: 100dvh !important;
    }

    #detailOverlay .modal-content {
      /* iOS PWA modal (vráceno na 90vh, problém byl v align-items) */
      margin: auto !important;
      max-height: 90vh !important;
      max-height: 90dvh !important;
    }
  }
}

/* Tmavý styl pro všechny sekce v detailu */
#detailOverlay .modal-body {
  background: #1a1a1a !important;
  color: #ffffff !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  padding: 1rem !important;
}

/* Všechny prvky v body centrované */
#detailOverlay .modal-body > * {
  width: 100% !important;
  max-width: 400px !important;
}

/* Tlačítka v body - centrované */
#detailOverlay .modal-body .btn {
  display: block !important;
  margin: 0.5rem auto !important;
  /* FIX: Touch optimalizace pro mobil */
  touch-action: manipulation !important;
  -webkit-tap-highlight-color: transparent !important;
  cursor: pointer !important;
  position: relative !important;
  z-index: var(--z-background, 1) !important;
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

/* Editovatelná pole - světlá s jemným odlišením */
#detailOverlay .editable-field {
  background: #f5f5f5 !important;
  border-color: #e0e0e0 !important;
}

#detailOverlay .field-label {
  color: #666666 !important;
}

#detailOverlay .field-input,
#detailOverlay .field-textarea,
#detailOverlay input,
#detailOverlay textarea,
#detailOverlay select {
  background: #f8f8f8 !important;
  border-color: #e0e0e0 !important;
  color: #333333 !important;
}

#detailOverlay input:focus,
#detailOverlay textarea:focus,
#detailOverlay select:focus {
  background: #ffffff !important;
  border-color: #999999 !important;
  outline: none !important;
}

#detailOverlay .field-input::placeholder,
#detailOverlay .field-textarea::placeholder,
#detailOverlay input::placeholder,
#detailOverlay textarea::placeholder {
  color: #999999 !important;
}

/* Info bloky v detailu zákazníka - světlé s jemným kontrastem */
#detailOverlay .modal-body > div[style*="background: #f8f9fa"],
#detailOverlay .modal-body > div[style*="background:#f8f9fa"] {
  background: #f0f0f0 !important;
  border-color: #ddd !important;
}

/* Labely v detailu zákazníka */
#detailOverlay .modal-body label,
#detailOverlay .modal-body span[style*="color: #666"] {
  color: #555555 !important;
}

/* Div jako textarea (popisy) - světlé odlišené */
#detailOverlay .modal-body > div > div[onclick*="showTextOverlay"] {
  background: #f8f8f8 !important;
  border-color: #e0e0e0 !important;
  color: #333333 !important;
}

/* Readonly inputy - trochu jiný odstín */
#detailOverlay input[readonly] {
  background: #eeeeee !important;
  color: #666666 !important;
}

/* Grid s popisky a inputy - horizontální zarovnání */
#detailOverlay .modal-body div[style*="grid-template-columns"] {
  grid-template-columns: auto 1fr !important;
  align-items: center !important;
}

#detailOverlay .modal-body div[style*="grid-template-columns"] > span {
  display: flex !important;
  align-items: center !important;
  min-height: 32px !important;
  padding-right: 0.5rem !important;
  white-space: nowrap !important;
}

#detailOverlay .modal-body div[style*="grid-template-columns"] > input,
#detailOverlay .modal-body div[style*="grid-template-columns"] > span:not([style*="color: #666"]) {
  min-height: 32px !important;
  display: flex !important;
  align-items: center !important;
}

/* Tlačítko smazat reklamaci - červené */
#detailOverlay button[data-action="deleteReklamace"] {
  background: #dc3545 !important;
  color: #ffffff !important;
  border: none !important;
}

#detailOverlay button[data-action="deleteReklamace"]:hover {
  background: #c82333 !important;
}

/* Kalendář - bílý */
#detailOverlay .calendar-container {
  background: #ffffff !important;
  border-radius: 8px !important;
  padding: 1rem !important;
}

#detailOverlay .calendar-controls {
  background: #ffffff !important;
}

#detailOverlay .calendar-month-title {
  color: #333333 !important;
}

#detailOverlay .calendar-nav-btn {
  background: #f5f5f5 !important;
  color: #333333 !important;
  border-color: #e0e0e0 !important;
}

#detailOverlay .calendar-nav-btn:hover {
  background: #e8e8e8 !important;
}

#detailOverlay .calendar-weekdays {
  color: #666666 !important;
}

#detailOverlay .cal-day {
  background: #f5f5f5 !important;
  border-color: #e0e0e0 !important;
  color: #333333 !important;
}

#detailOverlay .cal-day:hover {
  background: #e8e8e8 !important;
  border-color: #cccccc !important;
}

#detailOverlay .cal-day.selected {
  background: #1a1a1a !important;
  color: #ffffff !important;
  border-color: #1a1a1a !important;
}

#detailOverlay .cal-day.occupied {
  background: #ffeeee !important;
  border-color: #ffcccc !important;
  color: #cc0000 !important;
}

/* Časové sloty - bílé */
#detailOverlay .time-slot {
  background: #f5f5f5 !important;
  border-color: #e0e0e0 !important;
  color: #333333 !important;
}

#detailOverlay .time-slot:hover {
  background: #e8e8e8 !important;
  border-color: #cccccc !important;
}

#detailOverlay .time-slot.selected {
  background: #1a1a1a !important;
  color: #ffffff !important;
  border-color: #1a1a1a !important;
}

#detailOverlay .time-slot.occupied {
  background: #ffeeee !important;
  border-color: #ffcccc !important;
  color: #cc0000 !important;
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
  /* Modal overlay - lepší scrollování (NE pro #detailOverlay - ten má vlastní styling) */
  .modal-overlay:not(#detailOverlay) {
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
    touch-action: pan-y !important;
    align-items: center !important;
    padding: 2rem 0.5rem !important;
  }

  .modal-overlay:not(#detailOverlay) .modal-content {
    margin: 0 auto !important;
    max-height: none !important;
  }

  /* Modal header - ultra kompaktní (NE pro #detailOverlay) */
  .modal-overlay:not(#detailOverlay) .modal-header {
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
</head>

<body>
<!-- CSRF Token pro AJAX requesty -->
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

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
    <span class="search-icon" aria-hidden="true"></span>
    <input type="search" class="search-input" id="searchInput"
           enterkeyhint="search"
           aria-label="Hledat v reklamacích"
           data-lang-cs-placeholder="Hledat v reklamacích..."
           data-lang-en-placeholder="Search in claims..."
           data-lang-it-placeholder="Cerca nei reclami..."
           placeholder="Hledat v reklamacích...">
    <button class="search-clear" id="searchClear" aria-label="Vymazat hledání">×</button>
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

<!-- MODAL DETAIL - Alpine.js (Step 43) -->
<div class="modal-overlay" id="detailOverlay" role="dialog" aria-modal="true" aria-labelledby="detailModalTitle"
     x-data="detailModal" x-init="init" @click="overlayClick">
  <div class="modal-content">
    <h2 id="detailModalTitle" class="sr-only">Detail reklamace</h2>
    <button class="modal-close" data-action="closeDetail" @click="close" aria-label="Zavřít">×</button>
    <div id="modalContent"></div>
  </div>
</div>

<!-- LOADING OVERLAY -->
<div class="loading-overlay" id="loadingOverlay" role="status" aria-live="polite" aria-label="Načítání">
  <div class="loading-spinner" aria-hidden="true"></div>
  <div class="loading-text" id="loadingText" data-lang-cs="Ukládám termín..." data-lang-en="Saving appointment..." data-lang-it="Salvataggio appuntamento...">Ukládám termín...</div>
</div>

<!-- External JavaScript -->
<script src="assets/js/seznam.js?v=20251123-03" defer></script>

<!-- EMERGENCY FIX: Event delegation pro tlačítka v detailu -->
<script>
// CACHE BUSTER: 2025-11-23-20:15:00 - PŘIDÁNO: startVisit, showCalendar
document.addEventListener('DOMContentLoaded', () => {
  console.log('[EMERGENCY] Event delegation V7 se nacita... [2025-12-04] - kompletni handlery');

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
        // Tlacitko v detailu - ukazuje kontaktni info
        if (id && typeof showCustomerDetail === 'function') {
          showCustomerDetail(id);
        }
        break;

      case 'showDetailById':
        // Kliknuti na kartu zakaznika - musi volat showDetail (ne showCustomerDetail!)
        // showDetail nastavi CURRENT_RECORD a zobrazi hlavni detail modal
        if (id && typeof showDetail === 'function') {
          showDetail(id);
        }
        break;

      case 'openPDF':
        // Podpora pro data-url i data-pdf-path
        const pdfUrl = url || button.getAttribute('data-pdf-path');
        if (!pdfUrl) {
          console.error('[EMERGENCY] PDF URL chybi!');
          break;
        }

        console.log('[EMERGENCY] Oteviram PDF v modalu:', pdfUrl);

        // Otevrit PDF v modal okne (stejne jako showHistoryPDF)
        if (typeof zobrazPDFModal === 'function') {
          zobrazPDFModal(pdfUrl, id, 'report');
        } else {
          // Fallback - otevrit v novem tabu
          console.warn('[EMERGENCY] zobrazPDFModal neni dostupna, otviram v novem tabu');
          window.open(pdfUrl, '_blank');
        }
        break;

      case 'startVisit':
        if (id && typeof startVisit === 'function') {
          console.log('[EMERGENCY] Zahajuji navstevu ID:', id);
          startVisit(id);
        }
        break;

      case 'showCalendar':
        if (id && typeof showCalendar === 'function') {
          console.log('[EMERGENCY] Oteviram kalendar pro ID:', id);
          showCalendar(id);
        }
        break;

      case 'closeDetail':
        if (typeof closeDetail === 'function') {
          closeDetail();
        }
        break;

      case 'showDetail':
        // Tlacitko "Zpet" v ruznych modalech
        if (id && typeof showDetail === 'function') {
          showDetail(id);
        } else if (typeof showDetail === 'function' && typeof CURRENT_RECORD !== 'undefined' && CURRENT_RECORD) {
          showDetail(CURRENT_RECORD);
        }
        break;

      case 'reopenOrder':
        if (id && typeof reopenOrder === 'function') {
          reopenOrder(id);
        }
        break;

      case 'saveSelectedDate':
        if (typeof saveSelectedDate === 'function') {
          saveSelectedDate();
        }
        break;

      case 'previousMonth':
        if (typeof previousMonth === 'function') {
          previousMonth();
        }
        break;

      case 'nextMonth':
        if (typeof nextMonth === 'function') {
          nextMonth();
        }
        break;

      case 'showBookingDetail':
        if (id && typeof showBookingDetail === 'function') {
          showBookingDetail(id);
        }
        break;

      case 'showCalendarBack':
        if (typeof showCalendar === 'function' && typeof CURRENT_RECORD !== 'undefined' && CURRENT_RECORD) {
          showCalendar(CURRENT_RECORD.id || CURRENT_RECORD.reklamace_id);
        }
        break;

      case 'openCalendarFromDetail':
        if (id && typeof showCalendar === 'function') {
          showCalendar(id);
        }
        break;

      case 'sendContactAttemptEmail':
        if (id && typeof sendContactAttemptEmail === 'function') {
          const phone = button.getAttribute('data-phone');
          sendContactAttemptEmail(id, phone);
        }
        break;

      case 'showPhotoFullscreen':
        const photoSrc = button.getAttribute('data-src') || button.src;
        if (photoSrc && typeof showPhotoFullscreen === 'function') {
          showPhotoFullscreen(photoSrc);
        }
        break;

      case 'smazatFotku':
        const photoIndex = button.getAttribute('data-index');
        const sectionId = button.getAttribute('data-section');
        if (typeof smazatFotku === 'function') {
          smazatFotku(photoIndex, sectionId);
        }
        break;

      case 'deleteReklamace':
        if (id && typeof deleteReklamace === 'function') {
          deleteReklamace(id);
        }
        break;

      case 'saveAllCustomerData':
        if (id && typeof saveAllCustomerData === 'function') {
          saveAllCustomerData(id);
        }
        break;

      case 'startRecording':
        if (id && typeof startRecording === 'function') {
          startRecording(id);
        }
        break;

      case 'stopRecording':
        if (typeof stopRecording === 'function') {
          stopRecording();
        }
        break;

      case 'deleteAudioPreview':
        if (typeof deleteAudioPreview === 'function') {
          deleteAudioPreview();
        }
        break;

      case 'saveNewNote':
        if (id && typeof saveNewNote === 'function') {
          saveNewNote(id);
        }
        break;

      case 'closeErrorModal':
        const errorModal = document.getElementById('errorModal');
        if (errorModal) {
          errorModal.remove();
        }
        break;

      case 'filterUnreadNotes':
        if (typeof filterUnreadNotes === 'function') {
          filterUnreadNotes();
        }
        break;

      case 'showHistoryPDF':
        const originalId = button.getAttribute('data-original-id');
        if (originalId && typeof showHistoryPDF === 'function') {
          console.log('[EMERGENCY] Nacitam historii PDF pro original ID:', originalId);
          showHistoryPDF(originalId);
        } else {
          console.error('[EMERGENCY] showHistoryPDF funkce neni dostupna nebo original ID chybi');
        }
        break;

      case 'showVideoteka':
        if (id && typeof zobrazVideotekaArchiv === 'function') {
          console.log('[EMERGENCY] Oteviram videoteku pro ID:', id);
          zobrazVideotekaArchiv(id);
        } else {
          console.error('[EMERGENCY] zobrazVideotekaArchiv funkce neni dostupna nebo ID chybi');
        }
        break;

      case 'showNotes':
        if (id && typeof showNotes === 'function') {
          console.log('[EMERGENCY] Oteviram poznamky pro ID:', id);
          showNotes(id);
        } else {
          console.error('[EMERGENCY] showNotes funkce neni dostupna nebo ID chybi');
        }
        break;

      case 'closeNotesModal':
        if (typeof closeNotesModal === 'function') {
          closeNotesModal();
        }
        break;

      case 'deleteNote':
        e.preventDefault();
        e.stopPropagation();
        const noteId = button.getAttribute('data-note-id');
        const orderId = button.getAttribute('data-order-id');
        console.log('[EMERGENCY] deleteNote - noteId:', noteId, 'orderId:', orderId, 'funkce dostupna:', typeof deleteNote === 'function');
        if (noteId && typeof deleteNote === 'function') {
          console.log('[EMERGENCY] Mazu poznamku ID:', noteId);
          deleteNote(noteId, orderId);
        } else {
          console.error('[EMERGENCY] deleteNote funkce neni dostupna nebo note ID chybi');
          alert('Chyba: Funkce pro mazání není dostupná. Zkuste obnovit stránku.');
        }
        break;

      default:
        console.warn(`[EMERGENCY] Neznámá akce: ${action}`);
    }
  });

  console.log('[EMERGENCY] Event delegation V7 nacten [2025-12-04] - vsechny handlery aktivni');
});

// DVA-KLIKOVE POTVRZENI - obchazi vsechny problemy s modaly/overlay
// 1. klik: zmeni tlacitko na "Smazat?"
// 2. klik: skutecne smaze
window.potvrditSmazaniPoznamky = function(btn) {
  const noteId = btn.getAttribute('data-note-id');
  const orderId = btn.getAttribute('data-order-id');

  // Uz je v rezimu potvrzeni?
  if (btn.classList.contains('potvrzeni')) {
    // Druhy klik - smazat
    smazatPoznamkuOkamzite(noteId, orderId, btn);
    return;
  }

  // Prvni klik - zobrazit potvrzeni
  btn.classList.add('potvrzeni');
  btn.textContent = 'Smazat?';
  btn.style.cssText = 'background:#333 !important; color:#fff !important; padding:2px 8px !important; font-size:11px !important; min-width:50px !important;';

  // Timeout - po 3s vratit zpet
  setTimeout(function() {
    if (btn.classList.contains('potvrzeni')) {
      btn.classList.remove('potvrzeni');
      btn.textContent = 'x';
      btn.style.cssText = '';
    }
  }, 3000);
};

// Skutecne smazani bez potvrzeni
async function smazatPoznamkuOkamzite(noteId, orderId, btn) {
  console.log('[smazatPoznamku] Mazu poznamku ID:', noteId);

  // Disable tlacitko
  if (btn) {
    btn.textContent = '...';
    btn.disabled = true;
  }

  try {
    const csrfToken = await getCSRFToken();

    const params = new URLSearchParams();
    params.append('action', 'delete');
    params.append('note_id', noteId);
    params.append('csrf_token', csrfToken);

    const response = await fetch('/api/notes_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params
    });

    console.log('[smazatPoznamku] Status:', response.status);
    const data = await response.json();
    console.log('[smazatPoznamku] Data:', JSON.stringify(data));

    if (data.status === 'success') {
      // Odstranit poznamku z DOM
      const noteEl = document.querySelector('.note-item[data-note-id="' + noteId + '"]');
      if (noteEl) {
        noteEl.style.opacity = '0';
        noteEl.style.transition = 'opacity 0.3s';
        setTimeout(() => noteEl.remove(), 300);
      }
      // Obnovit seznam
      if (typeof loadAll === 'function') await loadAll(window.ACTIVE_FILTER || 'all');
      if (window.WGSToast) WGSToast.zobrazit('Poznámka smazána');
    } else {
      alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
      // Vratit tlacitko
      if (btn) {
        btn.textContent = 'x';
        btn.disabled = false;
        btn.classList.remove('potvrzeni');
        btn.style.cssText = '';
      }
    }
  } catch (e) {
    console.error('[smazatPoznamku] Error:', e);
    alert('Chyba: ' + e.message);
    if (btn) {
      btn.textContent = 'x';
      btn.disabled = false;
      btn.classList.remove('potvrzeni');
      btn.style.cssText = '';
    }
  }
}
console.log('[INLINE] potvrditSmazaniPoznamky - verze 20251203-04 (dva-klikove potvrzeni)');
</script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
