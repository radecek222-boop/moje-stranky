<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($currentPath === false || $currentPath === null) {
    $currentPath = $_SERVER['PHP_SELF'] ?? '';
}
$current = basename($currentPath);
$isLoggedIn = isset($_SESSION["user_id"]);
$isAdmin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;
$currentAdminTab = $_GET['tab'] ?? 'dashboard';

if ($isAdmin) {
    require_once __DIR__ . '/admin_navigation.php';
    $adminNavigation = loadAdminNavigation();
}
?>

<header class="hamburger-header">
  <a href="index.php" class="hamburger-logo">WGS<span>WHITE GLOVE SERVICE</span></a>
  <button class="hamburger-toggle" id="hamburger-toggle" aria-label="Otevřít menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
  <nav class="hamburger-nav" id="hamburger-nav">
    <?php
    if ($isAdmin):
    ?>
      <?php foreach ($adminNavigation as $item):
        if (empty($item['header_label'])) {
            continue;
        }
        $isActiveLink = isAdminNavigationActive($item, $current, $currentAdminTab);
      ?>
        <a
          href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
          <?php echo $isActiveLink ? 'class="active"' : ''; ?>
        >
          <?php echo htmlspecialchars($item['header_label'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
      <?php endforeach; ?>
      <a href="/logout.php" class="hamburger-logout">ODHLÁŠENÍ</a>
      <div class="hamburger-lang-switcher">
        <span class="lang-flag active" data-lang="cs">🇨🇿</span>
        <span class="lang-flag" data-lang="en">🇬🇧</span>
        <span class="lang-flag" data-lang="it">🇮🇹</span>
      </div>
    <?php
    elseif ($isLoggedIn):
    ?>
      <a href="novareklamace.php" <?php if($current == "novareklamace.php") echo 'class="active"'; ?> data-lang-cs="OBJEDNAT SERVIS" data-lang-en="ORDER SERVICE" data-lang-it="ORDINARE SERVIZIO">OBJEDNAT SERVIS</a>
      <a href="seznam.php" <?php if($current == "seznam.php") echo 'class="active"'; ?> data-lang-cs="MOJE REKLAMACE" data-lang-en="MY CLAIMS" data-lang-it="I MIEI RECLAMI">MOJE REKLAMACE</a>
      <a href="/logout.php" class="hamburger-logout" data-lang-cs="ODHLÁŠENÍ" data-lang-en="LOGOUT" data-lang-it="DISCONNETTERSI">ODHLÁŠENÍ</a>
      <div class="hamburger-lang-switcher">
        <span class="lang-flag active" data-lang="cs">🇨🇿</span>
        <span class="lang-flag" data-lang="en">🇬🇧</span>
        <span class="lang-flag" data-lang="it">🇮🇹</span>
      </div>
    <?php
    else:
    ?>
      <a href="index.php" <?php if($current == "index.php") echo 'class="active"'; ?> data-lang-cs="DOMŮ" data-lang-en="HOME" data-lang-it="CASA">DOMŮ</a>
      <a href="novareklamace.php" <?php if($current == "novareklamace.php") echo 'class="active"'; ?> data-lang-cs="OBJEDNAT SERVIS" data-lang-en="ORDER SERVICE" data-lang-it="ORDINARE SERVIZIO">OBJEDNAT SERVIS</a>
      <a href="nasesluzby.php" <?php if($current == "nasesluzby.php") echo 'class="active"'; ?> data-lang-cs="NAŠE SLUŽBY" data-lang-en="OUR SERVICES" data-lang-it="I NOSTRI SERVIZI">NAŠE SLUŽBY</a>
      <a href="onas.php" <?php if($current == "onas.php") echo 'class="active"'; ?> data-lang-cs="O NÁS" data-lang-en="ABOUT US" data-lang-it="CHI SIAMO">O NÁS</a>
      <a href="login.php" <?php if($current == "login.php") echo 'class="active"'; ?> data-lang-cs="PŘIHLÁŠENÍ" data-lang-en="LOGIN" data-lang-it="ACCESSO">PŘIHLÁŠENÍ</a>
      <div class="hamburger-lang-switcher">
        <span class="lang-flag active" data-lang="cs">🇨🇿</span>
        <span class="lang-flag" data-lang="en">🇬🇧</span>
        <span class="lang-flag" data-lang="it">🇮🇹</span>
      </div>
    <?php endif; ?>
  </nav>
</header>
<div class="hamburger-overlay" id="hamburger-overlay"></div>

<style>
.hamburger-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background: var(--c-bg-dark, #000);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: relative;
  z-index: 10001;
}

.hamburger-logo {
  font-size: 1.8rem;
  font-weight: 600;
  letter-spacing: 0.15em;
  color: var(--c-nav-text, #fff);
  text-decoration: none;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  line-height: 1.2;
}

.hamburger-logo span {
  font-size: 0.7rem;
  letter-spacing: 0.25em;
  opacity: 0.7;
}

.hamburger-toggle {
  display: none;
  background: none;
  border: none;
  color: var(--c-nav-text, #fff);
  cursor: pointer;
  padding: 0.5rem;
  width: 40px;
  height: 40px;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 5px;
  z-index: 10001;
  transition: all 0.3s ease;
}

.hamburger-toggle span {
  width: 25px;
  height: 2px;
  background: var(--c-nav-text, #fff);
  transition: all 0.3s ease;
  display: block;
}

.hamburger-nav {
  display: flex;
  gap: 2rem;
  align-items: center;
  
  
}

.hamburger-nav a {
  color: var(--c-nav-text, #fff);
  text-decoration: none;
  font-size: 0.95rem;
  font-weight: 400;
  transition: opacity 0.2s ease;
  position: relative;
  padding-bottom: 0.2rem;
  white-space: nowrap;
}

.hamburger-nav a:hover,
.hamburger-nav a.active {
  opacity: 0.7;
}

.hamburger-nav a.active::after {
  content: '';
  position: absolute;
  bottom: -0.2rem;
  left: 0;
  width: 100%;
  height: 1px;
  background: var(--c-nav-text, #fff);
}

.hamburger-logout {
  color: #ff6b6b !important;
  font-weight: 600 !important;
}

.hamburger-logout:hover {
  color: #ff4444 !important;
}

.hamburger-lang-switcher {
  display: flex;
  gap: 1rem;
  margin-left: auto;
}

.lang-flag {
  cursor: pointer;
  font-size: 1.5rem;
  opacity: 0.6;
  transition: opacity 0.2s ease;
}

.lang-flag.active {
  opacity: 1;
}

.hamburger-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 9999;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
  pointer-events: none;
}

.hamburger-overlay.active {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
}

@media (max-width: 768px) {
  .hamburger-header { padding: 1rem 1.5rem; }
  .hamburger-toggle { display: flex; }
  .hamburger-toggle.active span:nth-child(1) { transform: rotate(45deg) translate(8px, 8px); }
  .hamburger-toggle.active span:nth-child(2) { opacity: 0; }
  .hamburger-toggle.active span:nth-child(3) { transform: rotate(-45deg) translate(7px, -7px); }
  
  .hamburger-nav {
    position: fixed;
    top: 0;
    right: -100%;
    width: 90%;
    max-width: 85vw; min-width: 200px;
    height: 100vh;
    background: var(--c-bg-dark, #000);
    flex-direction: column;
    padding: 80px 0 0 0;
    gap: 0;
    margin: 0;
    z-index: 10000;
    overflow-y: auto;
    transition: right 0.3s ease;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.3);
  }
  
  .hamburger-nav.active { right: 0; }
  
  .hamburger-nav a {
    display: block;
    padding: 1rem 1.5rem;
    width: 100%;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.95rem;
    gap: 0;
    word-wrap: break-word; overflow-wrap: break-word;
  }
  
  .hamburger-nav a:hover { background: rgba(255, 255, 255, 0.05); }
  .hamburger-nav a.active { background: rgba(255, 255, 255, 0.1); }
  .hamburger-nav a.active::after { display: none; }
  
  .hamburger-lang-switcher {
    padding: 1rem 1.5rem;
    
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin: 0;
    gap: 1rem;
  }
  
  .lang-flag { font-size: 1.3rem; }
  .hamburger-logo { font-size: 1.3rem; }
  .hamburger-logo span { font-size: 0.5rem; }
  body.hamburger-menu-open { overflow: hidden; }
}

@media (max-width: 1024px) {
  .hamburger-nav { gap: 1.5rem; margin-left: 2rem; }
  .hamburger-nav a { font-size: 0.85rem; }
}
</style>

<script>
(function() {
  'use strict';
    /**
   * InitHamburgerMenu
   */
function initHamburgerMenu() {
    const hamburger = document.getElementById('hamburger-toggle');
    const nav = document.getElementById('hamburger-nav');
    const overlay = document.getElementById('hamburger-overlay');

    if (!hamburger || !nav || !overlay) {
      console.warn('Hamburger menu: Chybí HTML elementy!');
      return;
    }
    
        /**
     * ToggleMenu
     */
function toggleMenu() {
      const isActive = nav.classList.contains('active');
      nav.classList.toggle('active');
      overlay.classList.toggle('active');
      hamburger.classList.toggle('active');
      hamburger.setAttribute('aria-expanded', !isActive);
      if (!isActive) {
        document.body.classList.add('hamburger-menu-open');
      } else {
        document.body.classList.remove('hamburger-menu-open');
      }
    }
    
        /**
     * CloseMenu
     */
function closeMenu() {
      nav.classList.remove('active');
      overlay.classList.remove('active');
      hamburger.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('hamburger-menu-open');
    }
    
    hamburger.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleMenu();
    });
    
    overlay.addEventListener('click', closeMenu);
    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        setTimeout(closeMenu, 100);
      });
    });
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && nav.classList.contains('active')) {
        closeMenu();
      }
    });
    
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        if (window.innerWidth > 768 && nav.classList.contains('active')) {
          closeMenu();
        }
      }, 250);
    });
    
    document.addEventListener('click', (e) => {
      if (nav.classList.contains('active') && 
          !hamburger.contains(e.target) && 
          !nav.contains(e.target) && 
          !overlay.contains(e.target)) {
        closeMenu();
      }
    });
    
    hamburger.setAttribute('aria-expanded', 'false');
    nav.setAttribute('role', 'navigation');
    window.hamburgerMenu = {
      toggle: toggleMenu,
      open: function() { return openMenu(); },
      close: function() { return closeMenu(); },
      isOpen: () => nav.classList.contains('active')
    };
    console.log('Hamburger menu inicializován');
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHamburgerMenu);
  } else {
    initHamburgerMenu();
  }
})();
</script>

<!-- Translations - překladový slovník pro dynamický obsah -->
<script src="/assets/js/translations.js"></script>
<!-- Language Switcher - centralizovaný jazykový přepínač -->
<script src="/assets/js/language-switcher.js" defer></script>
<!-- ✅ OPRAVA: logout-handler.js přesunut do globálních scriptů v admin.php (řádek 1113) -->
