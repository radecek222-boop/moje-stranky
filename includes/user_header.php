<?php
/**
 * USER HEADER - Navigace pro přihlášené prodejce
 * Zobrazuje: Objednat servis | Moje reklamace | Odhlášení
 *
 * @deprecated 2024-12 ORPHANED FILE - Tento soubor není nikde používán.
 * Veškerá navigace je nyní centrálně v hamburger-menu.php.
 * Soubor může být bezpečně smazán v budoucí verzi.
 * Naposledy analyzováno: grep -r "user_header" --include="*.php" . = 0 výsledků
 */

// Kontrola přihlášení - dočasně vypnuto
// if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
//     header('Location: login.php');
//     exit;
// }

// Pokud je to admin, nepoužívat user header
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    return;
}

$userName = $_SESSION['user_name'] ?? 'Uživatel';
$userRole = $_SESSION['role'] ?? 'prodejce';
?>

<!-- USER HEADER - Navigace pro přihlášené prodejce -->
<header class="top-bar">
  <a href="index.php" class="logo" style="line-height: 1.2;">WGS
    <span>WHITE GLOVE SERVICE</span>
  </a>
  
  <button class="hamburger" id="hamburger" aria-label="Toggle navigation menu" aria-expanded="false">
    <span></span>
    <span></span>
    <span></span>
  </button>
  
<?php
$current = basename($_SERVER["PHP_SELF"]);
?>
  <nav class="nav" id="nav">
    <a href="novareklamace.php" <?php if($current == "novareklamace.php") echo 'class="active" style="border-bottom: 2px solid white; padding-bottom: 3px;"'; ?>>OBJEDNAT SERVIS</a>
    <a href="seznam.php" <?php if($current == "seznam.php") echo 'class="active" style="border-bottom: 2px solid white; padding-bottom: 3px;"'; ?>>MOJE REKLAMACE</a>
    <a href="logout.php" class="logout-link">ODHLÁŠENÍ</a>
  </nav>
</header>

<div class="menu-overlay" id="menuOverlay"></div>

<style>
.logout-link {
  color: #ff6b6b !important;
  font-weight: 600;
}

.logout-link:hover {
  color: #ff4444 !important;
}
</style>

<style>
/* Override seznam.css aby header vypadal jako novareklamace */
.top-bar {
  box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
  padding: 1rem 2rem !important;
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
}

.nav {
  display: flex !important;
  gap: 2.5rem !important;
  align-items: center !important;
}

.logo {
  font-size: 1.8rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.15em !important;
}

.logo span {
  font-size: 0.7rem !important;
  letter-spacing: 0.25em !important;
  margin-top: -0.2rem !important;
}

.nav a {
  font-size: 0.9rem !important;
  font-weight: 400 !important;
  letter-spacing: 0.08em !important;
}

/* MOBILNÍ MENU STYLY */
@media (max-width: 768px) {
  .nav {
    position: fixed !important;
    top: 73px !important;
    right: -260px !important;
    width: 250px !important;
    height: calc(100vh - 73px) !important;
    background: #000000 !important;
    flex-direction: column !important;
    gap: 0 !important;
    padding: 1rem 0 !important;
    box-shadow: -2px 0 10px rgba(0,0,0,0.3) !important;
    transition: right 0.3s ease-in-out, opacity 0.3s ease-in-out !important;
    opacity: 0 !important;
    z-index: var(--z-hamburger-nav, 10000) !important;
    overflow-y: auto !important;
    pointer-events: none !important;
  }

  .nav.active {
    display: flex !important;
    right: 0 !important;
    opacity: 1 !important;
    pointer-events: auto !important;
  }

  .nav a {
    padding: 1rem 1.5rem !important;
    width: 100% !important;
    text-align: left !important;
    border-bottom: 1px solid rgba(255,255,255,0.1) !important;
  }

  .nav a:hover {
    background: rgba(255,255,255,0.05) !important;
  }

  .menu-overlay {
    z-index: var(--z-hamburger-overlay, 9999) !important;
    pointer-events: none !important;
  }

  .menu-overlay.active {
    pointer-events: auto !important;
  }
}
</style>

<style>
/* Active link styling */
.nav a.active::after,
.nav a:hover::after {
  content: '';
  position: absolute;
  bottom: -5px;
  left: 0;
  width: 100%;
  height: 1px;
  background: var(--c-white);
}

.nav a {
  position: relative !important;
}
</style>

<script>
// Zvýraznění aktivního linku v JS
document.addEventListener('DOMContentLoaded', () => {
  const links = document.querySelectorAll('.nav a');
  const current = window.location.pathname.split('/').pop();
  
  links.forEach(link => {
    const href = link.getAttribute('href');
    if (current === href) {
      link.classList.add('active');
    }
  });
});
</script><script src="assets/js/logout-handler.js"></script>
