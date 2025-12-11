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

<!-- Skip link pro přístupnost -->
<a href="#main-content" class="skip-link">Přeskočit na hlavní obsah</a>

<!-- Centralizovaný z-index systém -->
<link rel="stylesheet" href="/assets/css/z-index-layers.min.css">

<!-- Hamburger Menu Wrapper - Alpine.js (Step 41) -->
<div x-data="hamburgerMenu" x-init="init">
<header class="hamburger-header">
  <a href="index.php" class="hamburger-logo">WGS<span>WHITE GLOVE SERVICE</span></a>
  <button class="hamburger-toggle" id="hamburger-toggle" aria-label="Otevřít menu" aria-expanded="false" @click.stop="prepnout">
    <span></span><span></span><span></span>
  </button>
  <nav class="hamburger-nav <?php echo $isAdmin ? 'admin-nav-active' : ''; ?>" id="hamburger-nav" aria-label="Hlavní navigace">
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
          <?php echo $isActiveLink ? 'class="active" aria-current="page"' : ''; ?>
        >
          <?php echo htmlspecialchars($item['header_label'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
      <?php endforeach; ?>
      <a href="/logout.php" class="hamburger-logout">ODHLÁŠENÍ</a>
      <a href="#" id="notif-enable-btn-admin" class="hamburger-notif-btn" role="button" style="display:none;" title="Notifikace">
        <svg class="notif-bell" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          <line class="notif-slash" x1="1" y1="1" x2="23" y2="23" style="display:none;"></line>
        </svg>
      </a>
      <div class="hamburger-lang-switcher">
        <span class="lang-flag active" data-lang="cs" role="button" tabindex="0" aria-label="Čeština"><img src="/assets/img/flags/cz.svg" alt="CZ" width="24" height="16"></span>
        <span class="lang-flag" data-lang="en" role="button" tabindex="0" aria-label="English"><img src="/assets/img/flags/gb.svg" alt="EN" width="24" height="16"></span>
        <span class="lang-flag" data-lang="it" role="button" tabindex="0" aria-label="Italiano"><img src="/assets/img/flags/it.svg" alt="IT" width="24" height="16"></span>
      </div>
    <?php
    elseif ($isLoggedIn):
        // Kontrola role technika pro zobrazení provizí
        $userRole = $_SESSION['role'] ?? null;
        $isTechnik = ($userRole === 'technik');
    ?>
      <?php if ($isTechnik): ?>
        <!-- Provize technika - Alpine.js komponenta (Step 34) -->
        <a
          x-data="techProvize"
          x-init="load"
          class="tech-provize-link"
          style="cursor: default; pointer-events: none;"
        >PROVIZE / <span x-text="mesic"></span> / <span x-text="castka"></span> €</a>
      <?php endif; ?>
      <a href="novareklamace.php" <?php if($current == "novareklamace.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="OBJEDNAT SERVIS" data-lang-en="ORDER SERVICE" data-lang-it="ORDINARE SERVIZIO">OBJEDNAT SERVIS</a>
      <a href="seznam.php" <?php if($current == "seznam.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="MOJE REKLAMACE" data-lang-en="MY CLAIMS" data-lang-it="I MIEI RECLAMI">MOJE REKLAMACE</a>
      <a href="hry.php" <?php if($current == "hry.php" || strpos($current, 'hry/') !== false) echo 'class="active" aria-current="page"'; ?> class="play-link" data-lang-cs="PLAY" data-lang-en="PLAY" data-lang-it="PLAY">PLAY<span class="play-badge" id="playBadge" style="display:none;">0</span></a>
      <?php if ($isTechnik): ?>
        <a href="cenik.php#kalkulacka" <?php if($current == "cenik.php" && strpos($_SERVER['REQUEST_URI'], '#kalkulacka') !== false) echo 'class="active" aria-current="page"'; ?> data-lang-cs="KALKULACE CENY SLUŽBY" data-lang-en="SERVICE PRICE CALCULATOR" data-lang-it="CALCOLATORE PREZZO SERVIZIO">KALKULACE CENY SLUŽBY</a>
      <?php endif; ?>
      <a href="/logout.php" class="hamburger-logout" data-lang-cs="ODHLÁŠENÍ" data-lang-en="LOGOUT" data-lang-it="DISCONNETTERSI">ODHLÁŠENÍ</a>
      <a href="#" id="notif-enable-btn-user" class="hamburger-notif-btn" role="button" style="display:none;" title="Notifikace">
        <svg class="notif-bell" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          <line class="notif-slash" x1="1" y1="1" x2="23" y2="23" style="display:none;"></line>
        </svg>
      </a>
      <div class="hamburger-lang-switcher">
        <span class="lang-flag active" data-lang="cs" role="button" tabindex="0" aria-label="Čeština"><img src="/assets/img/flags/cz.svg" alt="CZ" width="24" height="16"></span>
        <span class="lang-flag" data-lang="en" role="button" tabindex="0" aria-label="English"><img src="/assets/img/flags/gb.svg" alt="EN" width="24" height="16"></span>
        <span class="lang-flag" data-lang="it" role="button" tabindex="0" aria-label="Italiano"><img src="/assets/img/flags/it.svg" alt="IT" width="24" height="16"></span>
      </div>
    <?php
    else:
    ?>
      <a href="index.php" <?php if($current == "index.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="DOMŮ" data-lang-en="HOME" data-lang-it="CASA">DOMŮ</a>
      <a href="novareklamace.php" <?php if($current == "novareklamace.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="OBJEDNAT SERVIS" data-lang-en="ORDER SERVICE" data-lang-it="ORDINARE SERVIZIO">OBJEDNAT SERVIS</a>
      <a href="cenik.php" <?php if($current == "cenik.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="CENÍK" data-lang-en="PRICE LIST" data-lang-it="LISTINO PREZZI">CENÍK</a>
      <a href="nasesluzby.php" <?php if($current == "nasesluzby.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="NAŠE SLUŽBY" data-lang-en="OUR SERVICES" data-lang-it="I NOSTRI SERVIZI">NAŠE SLUŽBY</a>
      <a href="onas.php" <?php if($current == "onas.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="O NÁS" data-lang-en="ABOUT US" data-lang-it="CHI SIAMO">O NÁS</a>
      <a href="aktuality.php" <?php if($current == "aktuality.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="AKTUALITY" data-lang-en="NEWS" data-lang-it="NOTIZIE">AKTUALITY</a>
      <a href="login.php" <?php if($current == "login.php") echo 'class="active" aria-current="page"'; ?> data-lang-cs="PŘIHLÁŠENÍ" data-lang-en="LOGIN" data-lang-it="ACCESSO">PŘIHLÁŠENÍ</a>
      <div class="hamburger-lang-switcher">
        <span class="lang-flag active" data-lang="cs" role="button" tabindex="0" aria-label="Čeština"><img src="/assets/img/flags/cz.svg" alt="CZ" width="24" height="16"></span>
        <span class="lang-flag" data-lang="en" role="button" tabindex="0" aria-label="English"><img src="/assets/img/flags/gb.svg" alt="EN" width="24" height="16"></span>
        <span class="lang-flag" data-lang="it" role="button" tabindex="0" aria-label="Italiano"><img src="/assets/img/flags/it.svg" alt="IT" width="24" height="16"></span>
      </div>
    <?php endif; ?>
  </nav>
</header>
<div class="hamburger-overlay" id="hamburger-overlay" @click="zavrit"></div>
</div><!-- /Hamburger Menu Wrapper -->

<style>
.hamburger-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background: var(--c-bg-dark, #000);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: sticky;
  top: 0;
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
  z-index: var(--z-hamburger-toggle, 10001);
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

/* ADMIN - neonově zelené písmo v menu */
.hamburger-nav.admin-nav-active a {
  color: #39ff14 !important;
  text-shadow: 0 0 10px rgba(57, 255, 20, 0.5);
}

.hamburger-nav.admin-nav-active a:hover {
  color: #5fff3a !important;
  text-shadow: 0 0 15px rgba(57, 255, 20, 0.7);
}

.hamburger-nav.admin-nav-active a.active::after {
  background: #39ff14;
  box-shadow: 0 0 8px rgba(57, 255, 20, 0.6);
}

.hamburger-nav.admin-nav-active .hamburger-logout {
  color: #ff6666 !important;
  text-shadow: none;
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
  color: #999 !important;
  font-weight: 600 !important;
}

.hamburger-logout:hover {
  color: #777 !important;
}

/* VYJIMKA: Modry zvonecek notifikaci - schvaleno 2025-12-11 */
.hamburger-notif-btn {
  color: #888 !important;
  border: none !important;
  background: transparent !important;
  transition: all 0.2s ease;
  padding: 0.5rem !important;
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
}

.hamburger-notif-btn:hover {
  color: #aaa !important;
  background: transparent !important;
}

.hamburger-notif-btn .notif-bell {
  stroke: currentColor;
}

/* Notifikace zapnute - modry zvonecek bez skrtnuti */
.hamburger-notif-btn.notif-active {
  color: #0099ff !important;
  filter: drop-shadow(0 0 4px rgba(0, 153, 255, 0.5));
}

.hamburger-notif-btn.notif-active .notif-slash {
  display: none !important;
}

/* Notifikace vypnute - sedy skrtnuty zvonecek */
.hamburger-notif-btn.notif-off .notif-slash {
  display: inline !important;
  stroke: currentColor;
}

/* Provize technika - šedá barva konzistentní s UI */
.tech-provize-link {
  color: #999 !important;
  font-weight: 600 !important;
  opacity: 1 !important;
}

/* VYJIMKA: Modre tlacitko PLAY - schvaleno 2025-12-11 */
.hamburger-nav a.play-link {
  color: #0099ff !important;
  font-weight: 700 !important;
  position: relative;
  text-shadow: 0 0 10px rgba(0, 153, 255, 0.5);
}

.hamburger-nav a.play-link:hover {
  color: #33bbff !important;
  text-shadow: 0 0 15px rgba(0, 153, 255, 0.7);
}

.hamburger-nav a.play-link.active::after {
  background: #0099ff;
  box-shadow: 0 0 8px rgba(0, 153, 255, 0.6);
}

.play-badge {
  position: absolute;
  top: -8px;
  right: -12px;
  background: #0099ff;
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  border-radius: 9px;
  box-shadow: 0 0 8px rgba(0, 153, 255, 0.6);
}

@media (max-width: 768px) {
  .play-badge {
    position: static;
    margin-left: 8px;
    display: inline-block !important;
  }
}

.hamburger-lang-switcher {
  display: flex;
  gap: 1rem;
  margin-left: auto;
}

.lang-flag {
  cursor: pointer;
  opacity: 0.6;
  transition: opacity 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.lang-flag img {
  width: 24px;
  height: 16px;
  border-radius: 2px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.lang-flag.active {
  opacity: 1;
}

.lang-flag:hover {
  opacity: 1;
}

.hamburger-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: var(--z-hamburger-overlay, 9999);
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
    height: 100dvh; /* PWA fix: dynamic viewport height */
    background: var(--c-bg-dark, #000);
    flex-direction: column;
    padding: 80px 0 0 0;
    padding-bottom: env(safe-area-inset-bottom, 0); /* PWA fix: iPhone home indicator */
    gap: 0;
    margin: 0;
    z-index: var(--z-hamburger-nav, 10000);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
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
    padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0)); /* PWA fix */
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin: 0;
    margin-top: auto; /* Posunout na spodek */
    gap: 1rem;
  }

  .lang-flag img { width: 28px; height: 18px; }
  .hamburger-logo { font-size: 1.3rem; }
  .hamburger-logo span { font-size: 0.5rem; }
  body.hamburger-menu-open { overflow: hidden; }
}

/* PWA standalone mode - extra safe area handling */
@media (display-mode: standalone) {
  .hamburger-nav {
    padding-bottom: calc(20px + env(safe-area-inset-bottom, 20px));
  }
  .hamburger-lang-switcher {
    padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 20px));
  }
}

@media (max-width: 1024px) {
  .hamburger-nav { gap: 1.5rem; margin-left: 2rem; }
  .hamburger-nav a { font-size: 0.85rem; }
}
</style>

<!-- ============================================
     PHASE 2: HTMX + Alpine.js Infrastructure
     Přidáno v Step 30 pro postupnou modernizaci UI
     Step 33: Změna na @alpinejs/csp build (CSP-safe)
     ============================================ -->

<!-- HTMX 2.0.4 - Pro server-driven UI updates -->
<script src="https://unpkg.com/htmx.org@2.0.4" defer></script>

<!-- Alpine.js 3.14.3 CSP Build - Pro deklarativní UI state -->
<!-- CSP-SAFE: Používá @alpinejs/csp build bez new Function() -->
<script defer src="https://unpkg.com/@alpinejs/csp@3.14.3/dist/cdn.min.js"></script>

<!-- Alpine.js komponenty - registrace přes Alpine.data() (CSP-safe) -->
<script>
document.addEventListener('alpine:init', () => {
  /**
   * Hamburger Menu - Alpine.js komponenta (Step 41)
   * Migrace z vanilla JS na CSP-safe Alpine.js
   * Zachovává 1:1 chování: open/close, scroll-lock, ESC, overlay, resize close
   */
  Alpine.data('hamburgerMenu', () => ({
    otevreno: false,
    resizeTimer: null,

    init() {
      const nav = document.getElementById('hamburger-nav');
      const hamburger = document.getElementById('hamburger-toggle');

      // ESC zavře menu
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.otevreno) {
          this.zavrit();
        }
      });

      // Resize handler - zavřít menu při přechodu na desktop
      window.addEventListener('resize', () => {
        clearTimeout(this.resizeTimer);
        this.resizeTimer = setTimeout(() => {
          if (window.innerWidth > 768 && this.otevreno) {
            this.zavrit();
          }
        }, 250);
      });

      // Kliknutí na odkazy v nav zavře menu
      if (nav) {
        nav.querySelectorAll('a').forEach(link => {
          link.addEventListener('click', () => {
            setTimeout(() => this.zavrit(), 100);
          });
        });
      }

      // Nastavit ARIA atributy
      if (hamburger) {
        hamburger.setAttribute('aria-expanded', 'false');
      }
      if (nav) {
        nav.setAttribute('role', 'navigation');
      }

      // Exponovat metody pro vanilla JS (fallback kompatibilita)
      window.hamburgerMenu = {
        toggle: () => this.prepnout(),
        open: () => this.otevrit(),
        close: () => this.zavrit(),
        isOpen: () => this.otevreno
      };

      console.log('[hamburgerMenu] Inicializován (Alpine.js CSP-safe)');
    },

    // Přepnout menu
    prepnout() {
      if (this.otevreno) {
        this.zavrit();
      } else {
        this.otevrit();
      }
    },

    // Otevřít menu
    otevrit() {
      this.otevreno = true;
      this.aktualizovatCSS(true);
      this.aktualizovatScrollLock(true);
    },

    // Zavřít menu
    zavrit() {
      this.otevreno = false;
      this.aktualizovatCSS(false);
      this.aktualizovatScrollLock(false);
    },

    // Aktualizovat CSS třídy (classList toggle)
    aktualizovatCSS(aktivni) {
      const nav = document.getElementById('hamburger-nav');
      const overlay = document.getElementById('hamburger-overlay');
      const hamburger = document.getElementById('hamburger-toggle');

      if (nav) {
        nav.classList.toggle('active', aktivni);
      }
      if (overlay) {
        overlay.classList.toggle('active', aktivni);
      }
      if (hamburger) {
        hamburger.classList.toggle('active', aktivni);
        hamburger.setAttribute('aria-expanded', aktivni ? 'true' : 'false');
      }
      if (aktivni) {
        document.body.classList.add('hamburger-menu-open');
      } else {
        document.body.classList.remove('hamburger-menu-open');
      }
    },

    // Aktualizovat scroll lock
    aktualizovatScrollLock(aktivni) {
      if (window.scrollLock) {
        if (aktivni) {
          window.scrollLock.enable('hamburger-menu');
        } else {
          window.scrollLock.disable('hamburger-menu');
        }
      }
    }
  }));

  /**
   * Tech Provize - Alpine.js komponenta (Step 34)
   * Načítá provize technika z API a zobrazuje v navigaci
   */
  Alpine.data('techProvize', () => ({
    mesic: '...',
    castka: '...',

    async load() {
      try {
        const response = await fetch('/api/tech_provize_api.php');
        const result = await response.json();

        if (result.status === 'success') {
          this.mesic = result.mesic || '---';
          this.castka = result.provize_celkem || '0.00';
          console.log('[TechProvize] Načteno (Alpine.js):', result);
        } else {
          console.warn('[TechProvize] Chyba:', result.message);
        }
      } catch (e) {
        console.error('[TechProvize] Chyba při načítání:', e);
      }
    }
  }));

  /**
   * WGS Modal - Alpine.js komponenta (Step 35)
   * Jednotný CSP-safe modal framework pro WGS
   */
  Alpine.data('wgsModal', () => ({
    open: false,

    init() {
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });
      console.log('[wgsModal] Inicializován (Alpine.js CSP-safe)');
    },

    toggle() {
      this.open = !this.open;
      this.updateScrollLock();
    },

    openModal() {
      this.open = true;
      this.updateScrollLock();
    },

    close() {
      this.open = false;
      this.updateScrollLock();
    },

    updateScrollLock() {
      if (this.open) {
        window.scrollLock?.enable('wgs-modal');
      } else {
        window.scrollLock?.disable('wgs-modal');
      }
    }
  }));

  /**
   * Remember Me Modal - Alpine.js komponenta (Step 36)
   * Specifický modal pro potvrzení "Zapamatovat si mě" na login stránce
   * Migrace z vanilla JS na CSP-safe Alpine.js
   */
  Alpine.data('rememberMeModal', () => ({
    open: false,

    init() {
      // ESC zavře modal a zruší checkbox
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.cancel();
        }
      });
      console.log('[rememberMeModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Handler pro checkbox change event (CSP-safe)
    onCheckboxChange(event) {
      if (event.target.checked) {
        this.show();
      }
    },

    // Otevřít modal - přidá CSS class pro zachování původních animací
    show() {
      this.open = true;
      const overlay = document.getElementById('rememberMeOverlay');
      if (overlay) {
        overlay.classList.add('active');
      }
      // Scroll lock přes centralizovanou utilitu
      if (window.scrollLock) {
        window.scrollLock.enable('remember-me-modal');
      }
    },

    // Potvrdit - ponechat checkbox zaškrtnutý, zavřít modal
    confirm() {
      this.open = false;
      const overlay = document.getElementById('rememberMeOverlay');
      if (overlay) {
        overlay.classList.remove('active');
      }
      // Odemknout scroll
      if (window.scrollLock) {
        window.scrollLock.disable('remember-me-modal');
      }
    },

    // Zrušit - odškrtnout checkbox, zavřít modal
    cancel() {
      const checkbox = document.getElementById('rememberMe');
      if (checkbox) {
        checkbox.checked = false;
      }
      this.open = false;
      const overlay = document.getElementById('rememberMeOverlay');
      if (overlay) {
        overlay.classList.remove('active');
      }
      // Odemknout scroll
      if (window.scrollLock) {
        window.scrollLock.disable('remember-me-modal');
      }
    },

    // Klik na overlay - stejné jako cancel
    overlayClick(event) {
      // Pouze pokud klik byl přímo na overlay (ne na obsah uvnitř)
      if (event.target.id === 'rememberMeOverlay') {
        this.cancel();
      }
    }
  }));

  /**
   * Provedeni Modal - Alpine.js komponenta (Step 37)
   * Modal pro výběr provedení (Látka/Kůže/Kombinace) na novareklamace stránce
   * Migrace z vanilla JS na CSP-safe Alpine.js
   */
  Alpine.data('provedeniModal', () => ({
    open: false,

    init() {
      // ESC zavře modal
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });
      console.log('[provedeniModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Otevřít modal
    openModal() {
      this.open = true;
      const overlay = document.getElementById('provedeniOverlay');
      if (overlay) {
        overlay.classList.add('active');
      }
    },

    // Zavřít modal
    close() {
      this.open = false;
      const overlay = document.getElementById('provedeniOverlay');
      if (overlay) {
        overlay.classList.remove('active');
      }
    },

    // Klik na overlay pozadí
    overlayClick(event) {
      if (event.target.id === 'provedeniOverlay') {
        this.close();
      }
    },

    // Výběr provedení
    selectProvedeni(event) {
      const card = event.currentTarget;
      const value = card.dataset.value;
      const provedeniInput = document.getElementById('provedeni');
      if (provedeniInput) {
        provedeniInput.value = value;
      }
      this.close();
      // Toast notifikace - volat globální funkci pokud existuje
      if (typeof WGSFormController !== 'undefined' && WGSFormController.toast) {
        WGSFormController.toast('Provedení: ' + value, 'info');
      }
    }
  }));

  /**
   * Calendar Modal - Alpine.js komponenta (Step 38)
   * Modal pro výběr data na novareklamace stránce
   * Migrace open/close logiky z vanilla JS na CSP-safe Alpine.js
   * Renderování kalendáře zůstává v vanilla JS (initCustomCalendar)
   */
  Alpine.data('calendarModal', () => ({
    open: false,

    init() {
      // ESC zavře modal (bonus - původně nebylo)
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });

      // Exponovat metody pro vanilla JS (initCustomCalendar)
      window.calendarModal = {
        open: () => this.openModal(),
        close: () => this.close(),
        isOpen: () => this.open
      };

      console.log('[calendarModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Otevřít modal
    openModal() {
      this.open = true;
      const overlay = document.getElementById('calendarOverlay');
      if (overlay) {
        overlay.classList.add('active');
      }
    },

    // Zavřít modal
    close() {
      this.open = false;
      const overlay = document.getElementById('calendarOverlay');
      if (overlay) {
        overlay.classList.remove('active');
      }
    },

    // Klik na overlay pozadí
    overlayClick(event) {
      if (event.target.id === 'calendarOverlay') {
        this.close();
      }
    }
  }));

  /**
   * Calculator Modal - Alpine.js komponenta (Step 40)
   * Modal pro kalkulačku ceny servisu na protokol stránce
   * Migrace open/close logiky z vanilla JS na CSP-safe Alpine.js
   * Business logika (načítání kalkulačky, výpočty) zůstává v protokol-calculator-integration.js
   */
  Alpine.data('calculatorModal', () => ({
    open: false,

    init() {
      // ESC zavře modal (bonus - původně nebylo)
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });

      // Exponovat metody pro vanilla JS (protokol-calculator-integration.js)
      window.calculatorModal = {
        open: () => this.openModal(),
        close: () => this.close(),
        isOpen: () => this.open
      };

      console.log('[calculatorModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Otevřít modal - používá style.display pro zachování původního chování
    openModal() {
      this.open = true;
      const overlay = document.getElementById('calculatorModalOverlay');
      if (overlay) {
        overlay.style.display = 'flex';
      }
    },

    // Zavřít modal
    close() {
      this.open = false;
      const overlay = document.getElementById('calculatorModalOverlay');
      if (overlay) {
        overlay.style.display = 'none';
      }
    },

    // Klik na overlay pozadí
    overlayClick(event) {
      if (event.target.id === 'calculatorModalOverlay') {
        this.close();
      }
    }
  }));

  /**
   * Zákazník Schválení Modal - Alpine.js komponenta (Step 39)
   * Modal pro zobrazení souhrnu protokolu a podpis zákazníka
   * Migrace open/close logiky z vanilla JS na CSP-safe Alpine.js
   * Business logika (překlad, signature pad, souhrn) zůstává v protokol.js
   */
  Alpine.data('zakaznikSchvaleniModal', () => ({
    open: false,

    init() {
      // ESC zavře modal
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });

      // Exponovat metody pro vanilla JS (protokol.js)
      window.zakaznikSchvaleniModal = {
        open: () => this.openModal(),
        close: () => this.close(),
        isOpen: () => this.open
      };

      console.log('[zakaznikSchvaleniModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Otevřít modal - používá style.display pro zachování původního chování
    openModal() {
      this.open = true;
      const overlay = document.getElementById('zakaznikSchvaleniOverlay');
      if (overlay) {
        overlay.style.display = 'flex';
      }
      // Scroll lock přes centralizovanou utilitu
      if (window.scrollLock) {
        window.scrollLock.enable('zakaznik-schvaleni-overlay');
      }
    },

    // Zavřít modal
    close() {
      this.open = false;
      const overlay = document.getElementById('zakaznikSchvaleniOverlay');
      if (overlay) {
        overlay.style.display = 'none';
      }
      // Odemknout scroll
      if (window.scrollLock) {
        window.scrollLock.disable('zakaznik-schvaleni-overlay');
      }
    },

    // Klik na overlay pozadí
    overlayClick(event) {
      if (event.target.id === 'zakaznikSchvaleniOverlay') {
        this.close();
      }
    }
  }));

  /**
   * PDF Preview Modal - Alpine.js komponenta (Step 42)
   * Modal pro náhled vygenerovaného PDF protokolu
   * Migrace open/close/ESC/overlay logiky z vanilla JS na CSP-safe Alpine.js
   * Business logika (iframe, blob URL, share/download) zůstává v protokol-pdf-preview.js
   */
  Alpine.data('pdfPreviewModal', () => ({
    open: false,

    init() {
      // ESC zavře modal
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });

      // Exponovat metody pro vanilla JS (protokol-pdf-preview.js)
      window.pdfPreviewModal = {
        open: () => this.openModal(),
        close: () => this.close(),
        isOpen: () => this.open
      };

      console.log('[pdfPreviewModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Otevřít modal - používá classList.add('active') pro zachování původních animací
    openModal() {
      this.open = true;
      const overlay = document.getElementById('pdfPreviewOverlay');
      if (overlay) {
        overlay.classList.add('active');
      }
    },

    // Zavřít modal - volá zavritPdfPreview() z protokol-pdf-preview.js pro cleanup
    close() {
      this.open = false;
      // Volat původní funkci pro cleanup (revoke URL, vyčistit iframe)
      if (typeof zavritPdfPreview === 'function') {
        zavritPdfPreview();
      } else {
        // Fallback - jen skrýt overlay
        const overlay = document.getElementById('pdfPreviewOverlay');
        if (overlay) {
          overlay.classList.remove('active');
        }
      }
    },

    // Klik na overlay pozadí
    overlayClick(event) {
      if (event.target.id === 'pdfPreviewOverlay') {
        this.close();
      }
    }
  }));

  /**
   * Detail Modal - Alpine.js komponenta (Step 43)
   * Modal pro zobrazení detailu reklamace na seznam.php
   * Migrace open/close/ESC/overlay logiky z vanilla JS na CSP-safe Alpine.js
   * Business logika (showDetail, kalendář, editace) zůstává v seznam.js
   */
  Alpine.data('detailModal', () => ({
    open: false,

    init() {
      // ESC zavře modal (bonus - původně nebylo)
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });

      // Exponovat metody pro vanilla JS (seznam.js - ModalManager)
      window.detailModal = {
        open: () => this.openModal(),
        close: () => this.close(),
        isOpen: () => this.open
      };

      console.log('[detailModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Otevřít modal - používá classList.add('active') pro zachování původních animací
    openModal() {
      this.open = true;
      const overlay = document.getElementById('detailOverlay');
      if (overlay) {
        overlay.classList.add('active');
      }
      // Scroll lock přes centralizovanou utilitu
      if (window.scrollLock) {
        window.scrollLock.enable('detail-overlay');
      }
      document.body.classList.add('modal-open');
    },

    // Zavřít modal
    close() {
      this.open = false;
      const overlay = document.getElementById('detailOverlay');
      if (overlay) {
        overlay.classList.remove('active');
      }
      // Počkat na CSS transition než odemkneme scroll
      setTimeout(() => {
        document.body.classList.remove('modal-open');
        if (window.scrollLock) {
          window.scrollLock.disable('detail-overlay');
        }
      }, 50);
      // Volat closeDetail() z seznam.js pro cleanup (reset CURRENT_RECORD atd.)
      if (typeof closeDetail === 'function') {
        // Poznámka: closeDetail() volá ModalManager.close() který už dělá classList.remove
        // ale to je OK - double-remove class je bezpečné
      }
    },

    // Klik na overlay pozadí (bonus - původně nebylo)
    overlayClick(event) {
      if (event.target.id === 'detailOverlay') {
        this.close();
      }
    }
  }));

  /**
   * Notif Modal - Alpine.js komponenta (Step 44)
   * Modal pro notifikace na admin.php
   * Migrace open/close/ESC/overlay logiky z inline JS na CSP-safe Alpine.js
   * Business logika (loadNotifContent) zůstává v admin.php
   */
  Alpine.data('notifModal', () => ({
    open: false,

    init() {
      // ESC zavře modal (bonus - původně nebylo)
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });

      // Exponovat metody pro vanilla JS (admin.php)
      window.notifModal = {
        open: () => this.openModal(),
        close: () => this.close(),
        isOpen: () => this.open
      };

      console.log('[notifModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Otevřít modal - používá classList.add('active') pro zachování původních animací
    openModal() {
      this.open = true;
      const overlay = document.getElementById('notifModalOverlay');
      const modal = overlay?.querySelector('.cc-modal');
      if (overlay) {
        overlay.classList.add('active');
      }
      if (modal) {
        modal.classList.add('active');
      }
      document.body.style.overflow = 'hidden';
    },

    // Zavřít modal
    close() {
      this.open = false;
      const overlay = document.getElementById('notifModalOverlay');
      const modal = overlay?.querySelector('.cc-modal');
      if (overlay) {
        overlay.classList.remove('active');
      }
      if (modal) {
        modal.classList.remove('active');
      }
      document.body.style.overflow = 'auto';
    },

    // Klik na overlay pozadí
    overlayClick(event) {
      if (event.target.id === 'notifModalOverlay') {
        this.close();
      }
    }
  }));

  /**
   * Admin Modal - Alpine.js komponenta (Step 45)
   * Hlavní modal pro Control Centre na admin.php
   * Migrace open/close/ESC/overlay logiky z admin.js na CSP-safe Alpine.js
   * Business logika (loadXxxModal funkce) zůstává v admin.js
   */
  Alpine.data('adminModal', () => ({
    open: false,

    init() {
      // ESC zavře modal (bonus - původně nebylo)
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.open) {
          this.close();
        }
      });

      // Exponovat metody pro vanilla JS (admin.js)
      window.adminModal = {
        open: () => this.openModal(),
        close: () => this.close(),
        isOpen: () => this.open
      };

      console.log('[adminModal] Inicializován (Alpine.js CSP-safe)');
    },

    // Otevřít modal - přidá 'active' class na overlay i modal
    openModal() {
      this.open = true;
      const overlay = document.getElementById('adminOverlay');
      const modal = document.getElementById('adminModal');
      if (overlay) {
        overlay.classList.add('active');
      }
      if (modal) {
        modal.classList.add('active');
      }
      // Scroll lock přes centralizovanou utilitu
      if (window.scrollLock) {
        window.scrollLock.enable('admin-modal');
      }
    },

    // Zavřít modal
    close() {
      this.open = false;
      const overlay = document.getElementById('adminOverlay');
      const modal = document.getElementById('adminModal');
      if (overlay) {
        overlay.classList.remove('active');
      }
      if (modal) {
        modal.classList.remove('active');
      }
      // Odemknout scroll
      if (window.scrollLock) {
        window.scrollLock.disable('admin-modal');
      }
    },

    // Klik na overlay pozadí
    overlayClick(event) {
      if (event.target.id === 'adminOverlay') {
        this.close();
      }
    }
  }));
});
</script>

<!-- Centralizovaná utilita pro zamykání scrollu -->
<script src="/assets/js/scroll-lock.min.js" defer></script>

<!-- Step 41: Hamburger Menu migrace na Alpine.js - vanilla JS odstraněn -->

<script>
(function() {
  'use strict';

  /**
   * InitNotifButton - Inicializace tlačítka pro povolení notifikací
   */
  function initNotifButton() {
    const btnAdmin = document.getElementById('notif-enable-btn-admin');
    const btnUser = document.getElementById('notif-enable-btn-user');
    const btn = btnAdmin || btnUser;

    if (!btn) {
      return;
    }

    // Kontrola podpory notifikací
    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
      console.log('Notifikace nejsou podporovány');
      return;
    }

    // Kontrola zda je PWA (standalone mode) nebo iOS
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                         window.navigator.standalone === true;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);

    // Zobrazit tlačítko - vždy (ON/OFF toggle)
    btn.style.display = '';

    // Nastavit stav zvonecku podle permission
    if (Notification.permission === 'granted') {
      btn.classList.add('notif-active');
      btn.classList.remove('notif-off');
      btn.title = 'Notifikace zapnuty';
      console.log('Notifikace: Zvonecek zobrazen (permission = granted)');
    } else if (Notification.permission === 'denied') {
      btn.classList.add('notif-off');
      btn.classList.remove('notif-active');
      btn.style.opacity = '0.5';
      btn.style.cursor = 'not-allowed';
      btn.title = 'Notifikace zablokovany';
      console.log('Notifikace: Zvonecek zobrazen (permission = denied)');
    } else {
      btn.classList.add('notif-off');
      btn.classList.remove('notif-active');
      btn.title = 'Kliknete pro povoleni notifikaci';
      console.log('Notifikace: Zvonecek zobrazen (permission = default)');
    }

    // Handler pro kliknutí
    btn.addEventListener('click', async function(e) {
      e.preventDefault();
      e.stopPropagation();

      // Preklady alert textu
      const jazyk = window.ziskejAktualniJazyk ? window.ziskejAktualniJazyk() : 'cs';
      const texty = {
        cs: {
          aktivni: 'Notifikace jsou aktivní.\n\nPro vypnutí:\n• iOS: Nastavení > Notifikace > WGS\n• Android: Nastavení > Aplikace > WGS > Notifikace\n• Desktop: Klikněte na ikonu zámku v adresním řádku',
          blokovany: 'Notifikace jsou zablokovány.\n\nPro povolení:\n• iOS: Nastavení > Notifikace > WGS\n• Android: Nastavení > Aplikace > WGS > Notifikace\n• Desktop: Klikněte na ikonu zámku v adresním řádku',
          chyba: 'Nepodařilo se povolit notifikace. Zkuste to znovu.'
        },
        en: {
          aktivni: 'Notifications are active.\n\nTo disable:\n• iOS: Settings > Notifications > WGS\n• Android: Settings > Apps > WGS > Notifications\n• Desktop: Click the lock icon in the address bar',
          blokovany: 'Notifications are blocked.\n\nTo enable:\n• iOS: Settings > Notifications > WGS\n• Android: Settings > Apps > WGS > Notifications\n• Desktop: Click the lock icon in the address bar',
          chyba: 'Failed to enable notifications. Please try again.'
        },
        it: {
          aktivni: 'Le notifiche sono attive.\n\nPer disattivare:\n• iOS: Impostazioni > Notifiche > WGS\n• Android: Impostazioni > App > WGS > Notifiche\n• Desktop: Clicca sull\'icona del lucchetto nella barra degli indirizzi',
          blokovany: 'Le notifiche sono bloccate.\n\nPer abilitare:\n• iOS: Impostazioni > Notifiche > WGS\n• Android: Impostazioni > App > WGS > Notifiche\n• Desktop: Clicca sull\'icona del lucchetto nella barra degli indirizzi',
          chyba: 'Impossibile abilitare le notifiche. Riprova.'
        }
      };
      const t = texty[jazyk] || texty.cs;

      // Pokud jsou notifikace povoleny - vysvětlit jak vypnout
      if (Notification.permission === 'granted') {
        alert(t.aktivni);
        return;
      }

      // Pokud jsou zablokovány - vysvětlit jak povolit
      if (Notification.permission === 'denied') {
        alert(t.blokovany);
        return;
      }

      // Permission = default - požádat o povolení
      try {
        // Použít WGSNotifikace pokud existuje
        if (window.WGSNotifikace && typeof window.WGSNotifikace.pozadatOPovoleni === 'function') {
          const vysledek = await window.WGSNotifikace.pozadatOPovoleni();
          if (vysledek) {
            btn.classList.remove('notif-off');
            btn.classList.add('notif-active');
            btn.title = 'Notifikace zapnuty';
            console.log('Notifikace: Úspěšně povoleny přes WGSNotifikace');
          }
        } else {
          // Fallback - přímé povolení
          const permission = await Notification.requestPermission();
          if (permission === 'granted') {
            btn.classList.remove('notif-off');
            btn.classList.add('notif-active');
            btn.title = 'Notifikace zapnuty';
            console.log('Notifikace: Úspěšně povoleny');

            // Registrovat subscription pokud je k dispozici service worker
            if ('serviceWorker' in navigator && 'PushManager' in window) {
              try {
                // 1. Načíst VAPID public key z API
                const vapidResponse = await fetch('/api/push_subscription_api.php?action=vapid-key');
                const vapidData = await vapidResponse.json();

                if (!vapidData.vapidPublicKey) {
                  throw new Error('VAPID klíč není k dispozici');
                }

                // 2. Konvertovat VAPID key na Uint8Array
                const urlBase64ToUint8Array = (base64String) => {
                  const padding = '='.repeat((4 - base64String.length % 4) % 4);
                  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                  const rawData = window.atob(base64);
                  const outputArray = new Uint8Array(rawData.length);
                  for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                  }
                  return outputArray;
                };

                const applicationServerKey = urlBase64ToUint8Array(vapidData.vapidPublicKey);

                // 3. Registrovat push subscription
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.subscribe({
                  userVisibleOnly: true,
                  applicationServerKey: applicationServerKey
                });

                // 4. Odeslat subscription na server s CSRF tokenem
                if (subscription) {
                  const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                  const formData = new FormData();
                  formData.append('action', 'subscribe');
                  formData.append('csrf_token', csrfToken);
                  formData.append('subscription', JSON.stringify(subscription));
                  formData.append('platforma', /iPhone|iPad|iPod/.test(navigator.userAgent) ? 'ios' :
                                               /Android/.test(navigator.userAgent) ? 'android' : 'desktop');

                  const response = await fetch('/api/push_subscription_api.php', {
                    method: 'POST',
                    body: formData
                  });

                  if (response.ok) {
                    console.log('Push subscription úspěšně uložena na server');
                  } else {
                    console.warn('Subscription uložena lokálně, server vrátil chybu');
                  }
                }
              } catch (pushError) {
                console.warn('Push subscription se nepodařila:', pushError);
                // Notifikace jsou povoleny, ale push nemusí fungovat
              }
            }
          } else if (permission === 'denied') {
            btn.classList.remove('notif-active');
            btn.classList.add('notif-off');
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
            btn.title = 'Notifikace zablokovany';
          }
        }
      } catch (error) {
        console.error('Chyba při povolování notifikací:', error);
        alert(t.chyba);
      }
    });
  }

  // Tech Provize nyní řešeno přes Alpine.js komponentu (Step 34)
  // initNotifButton stále vyžaduje vanilla JS inicializaci
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotifButton);
  } else {
    initNotifButton();
  }

  /**
   * InitPlayBadge - Načíst počet online hráčů v herní zóně
   */
  function initPlayBadge() {
    const badge = document.getElementById('playBadge');
    if (!badge) return;

    async function nactiOnline() {
      try {
        const response = await fetch('/api/hry_api.php?action=stav');
        const result = await response.json();

        if (result.status === 'success' && result.data.online) {
          const pocet = result.data.online.length;
          if (pocet > 0) {
            badge.textContent = pocet;
            badge.style.display = 'inline-block';
          } else {
            badge.style.display = 'none';
          }
        }
      } catch (e) {
        // Tiše selhat - nezobrazit badge
      }
    }

    // Načíst hned a pak každých 30s
    nactiOnline();
    setInterval(nactiOnline, 30000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPlayBadge);
  } else {
    initPlayBadge();
  }
})();
</script>

<!-- Translations - překladový slovník pro dynamický obsah -->
<script src="/assets/js/translations.min.js" defer></script>
<!-- Language Switcher - centralizovaný jazykový přepínač -->
<script src="/assets/js/language-switcher.min.js" defer></script>
<!-- KRITICKA OPRAVA: logout-handler.min.js MUSI byt zde, protoze hamburger-menu se nacita VSUDE! -->
<script src="/assets/js/logout-handler.min.js" defer></script>
