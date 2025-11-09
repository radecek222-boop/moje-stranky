<?php require_once "init.php"; ?>
<?php
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
  <title>Přehled reklamací | White Glove Service</title>
  <meta name="description" content="Seznam reklamací a servisních zakázek White Glove Service. Správa, filtrování a přehledy všech servisních požadavků.">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.js" defer></script>


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
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<?php if ($isAdmin): ?>
<?php endif; ?>
  
  <!-- SEARCH RESULTS INFO -->
  <div id="searchResultsInfo" style="display: none;"></div>
  <!-- SEARCH BAR -->
<div class="container">
  <div class="search-bar">
    <span class="search-icon">🔍</span>
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

<!-- External JavaScript -->
<script src="assets/js/seznam.js" defer></script>
    <script src="assets/js/seznam-delete-patch.js" defer></script>
</body>
</html>
