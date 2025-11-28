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
      <a href="#" id="notif-enable-btn-admin" class="hamburger-notif-btn" style="display:none;" data-lang-cs="NOTIFY ME ON" data-lang-en="NOTIFY ME ON" data-lang-it="NOTIFY ME ON">NOTIFY ME ON</a>
      <div class="hamburger-lang-switcher">
        <span class="lang-flag active" data-lang="cs">🇨🇿</span>
        <span class="lang-flag" data-lang="en">🇬🇧</span>
        <span class="lang-flag" data-lang="it">🇮🇹</span>
      </div>
    <?php
    elseif ($isLoggedIn):
        // Kontrola role technika pro zobrazení provizí
        $userRole = $_SESSION['role'] ?? null;
        $isTechnik = ($userRole === 'technik');
    ?>
      <?php if ($isTechnik): ?>
        <!-- Provize technika jako navigační položka - úplně nahoře -->
        <a href="#" class="tech-provize-link" id="tech-provize-box" style="cursor: default; pointer-events: none;">PROVIZE / <span id="provize-mesic">...</span> / <span id="provize-castka">...</span> €</a>
      <?php endif; ?>
      <a href="novareklamace.php" <?php if($current == "novareklamace.php") echo 'class="active"'; ?> data-lang-cs="OBJEDNAT SERVIS" data-lang-en="ORDER SERVICE" data-lang-it="ORDINARE SERVIZIO">OBJEDNAT SERVIS</a>
      <a href="seznam.php" <?php if($current == "seznam.php") echo 'class="active"'; ?> data-lang-cs="MOJE REKLAMACE" data-lang-en="MY CLAIMS" data-lang-it="I MIEI RECLAMI">MOJE REKLAMACE</a>
      <a href="/logout.php" class="hamburger-logout" data-lang-cs="ODHLÁŠENÍ" data-lang-en="LOGOUT" data-lang-it="DISCONNETTERSI">ODHLÁŠENÍ</a>
      <a href="#" id="notif-enable-btn-user" class="hamburger-notif-btn" style="display:none;" data-lang-cs="NOTIFY ME ON" data-lang-en="NOTIFY ME ON" data-lang-it="NOTIFY ME ON">NOTIFY ME ON</a>
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
      <a href="cenik.php" <?php if($current == "cenik.php") echo 'class="active"'; ?> data-lang-cs="CENÍK" data-lang-en="PRICE LIST" data-lang-it="LISTINO PREZZI">CENÍK</a>
      <a href="onas.php" <?php if($current == "onas.php") echo 'class="active"'; ?> data-lang-cs="O NÁS" data-lang-en="ABOUT US" data-lang-it="CHI SIAMO">O NÁS</a>
      <a href="aktuality.php" <?php if($current == "aktuality.php") echo 'class="active"'; ?> data-lang-cs="AKTUALITY" data-lang-en="NEWS" data-lang-it="NOTIZIE">AKTUALITY</a>
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

.hamburger-notif-btn {
  color: #4a9eff !important;
  font-weight: 600 !important;
  border: none !important;
  background: transparent !important;
  transition: all 0.2s ease;
}

.hamburger-notif-btn:hover {
  color: #7bb8ff !important;
  background: transparent !important;
}

.hamburger-notif-btn.notif-active {
  color: #4a9eff !important;
}

/* Provize technika - červená barva jako odhlášení */
.tech-provize-link {
  color: #ff6b6b !important;
  font-weight: 600 !important;
  opacity: 1 !important;
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
        // Otevirani - zamknout scroll (iOS fix)
        window.mainMenuScrollPosition = window.pageYOffset;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${window.mainMenuScrollPosition}px`;
        document.body.style.width = '100%';
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.classList.add('hamburger-menu-open');
      } else {
        // Zavirani - obnovit scroll
        document.body.classList.remove('hamburger-menu-open');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        document.body.style.left = '';
        document.body.style.right = '';
        window.scrollTo(0, window.mainMenuScrollPosition);
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

      // Obnovit scroll (iOS fix)
      document.body.style.position = '';
      document.body.style.top = '';
      document.body.style.width = '';
      document.body.style.left = '';
      document.body.style.right = '';
      if (typeof window.mainMenuScrollPosition !== 'undefined') {
        window.scrollTo(0, window.mainMenuScrollPosition);
      }
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

    if (Notification.permission === 'granted') {
      btn.textContent = 'NOTIFY ME OFF';
      btn.classList.add('notif-active');
      console.log('Notifikace: Tlačítko zobrazeno (permission = granted)');
    } else if (Notification.permission === 'denied') {
      btn.textContent = 'NOTIFY ME OFF';
      btn.style.opacity = '0.5';
      btn.style.cursor = 'not-allowed';
      btn.title = 'Notifikace jsou zablokovány v nastavení prohlížeče';
      console.log('Notifikace: Tlačítko zobrazeno (permission = denied)');
    } else {
      btn.textContent = 'NOTIFY ME ON';
      console.log('Notifikace: Tlačítko zobrazeno (permission = default)');
    }

    // Handler pro kliknutí
    btn.addEventListener('click', async function(e) {
      e.preventDefault();
      e.stopPropagation();

      // Pokud jsou notifikace povoleny - vysvětlit jak vypnout
      if (Notification.permission === 'granted') {
        alert('Notifikace jsou aktivní.\n\nPro vypnutí:\n• iOS: Nastavení > Notifikace > WGS\n• Android: Nastavení > Aplikace > WGS > Notifikace\n• Desktop: Klikněte na ikonu zámku v adresním řádku');
        return;
      }

      // Pokud jsou zablokovány - vysvětlit jak povolit
      if (Notification.permission === 'denied') {
        alert('Notifikace jsou zablokovány.\n\nPro povolení:\n• iOS: Nastavení > Notifikace > WGS\n• Android: Nastavení > Aplikace > WGS > Notifikace\n• Desktop: Klikněte na ikonu zámku v adresním řádku');
        return;
      }

      // Permission = default - požádat o povolení
      try {
        // Použít WGSNotifikace pokud existuje
        if (window.WGSNotifikace && typeof window.WGSNotifikace.pozadatOPovoleni === 'function') {
          const vysledek = await window.WGSNotifikace.pozadatOPovoleni();
          if (vysledek) {
            btn.textContent = 'NOTIFY ME OFF';
            btn.classList.add('notif-active');
            console.log('Notifikace: Úspěšně povoleny přes WGSNotifikace');
          }
        } else {
          // Fallback - přímé povolení
          const permission = await Notification.requestPermission();
          if (permission === 'granted') {
            btn.textContent = 'NOTIFY ME OFF';
            btn.classList.add('notif-active');
            console.log('Notifikace: Úspěšně povoleny');

            // Registrovat subscription pokud je k dispozici service worker
            if ('serviceWorker' in navigator && 'PushManager' in window) {
              const registration = await navigator.serviceWorker.ready;
              const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: window.VAPID_PUBLIC_KEY || null
              });

              // Odeslat na server
              if (subscription) {
                const response = await fetch('/api/push_subscribe.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify(subscription)
                });
                console.log('Subscription odeslána na server');
              }
            }
          } else if (permission === 'denied') {
            btn.textContent = 'NOTIFY ME OFF';
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
          }
        }
      } catch (error) {
        console.error('Chyba při povolování notifikací:', error);
        alert('Nepodařilo se povolit notifikace. Zkuste to znovu.');
      }
    });
  }

  /**
   * InitTechProvize - Načtení provizí pro techniky
   */
  async function initTechProvize() {
    const provizeBox = document.getElementById('tech-provize-box');

    if (!provizeBox) {
      // Není technik nebo není prvek
      return;
    }

    try {
      const response = await fetch('/api/tech_provize_api.php');
      const result = await response.json();

      if (result.status === 'success') {
        const mesicEl = document.getElementById('provize-mesic');
        const castkaEl = document.getElementById('provize-castka');

        if (mesicEl) {
          mesicEl.textContent = result.mesic || '---';
        }

        if (castkaEl) {
          castkaEl.textContent = result.provize_celkem || '0.00';
        }

        console.log('[Tech Provize] Načteno:', result);
      } else {
        console.warn('[Tech Provize] Chyba:', result.message);
      }
    } catch (error) {
      console.error('[Tech Provize] Chyba při načítání:', error);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHamburgerMenu);
    document.addEventListener('DOMContentLoaded', initNotifButton);
    document.addEventListener('DOMContentLoaded', initTechProvize);
  } else {
    initHamburgerMenu();
    initNotifButton();
    initTechProvize();
  }
})();
</script>

<!-- Translations - překladový slovník pro dynamický obsah -->
<script src="/assets/js/translations.js"></script>
<!-- Language Switcher - centralizovaný jazykový přepínač -->
<script src="/assets/js/language-switcher.js" defer></script>
<!-- KRITICKA OPRAVA: logout-handler.js MUSI byt zde, protoze hamburger-menu se nacita VSUDE! -->
<script src="/assets/js/logout-handler.js"></script>
