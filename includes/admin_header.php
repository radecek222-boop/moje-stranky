<?php
/**
 * ADMIN HEADER - Header s hamburger menu pro mobilní navigaci
 */
$adminNavigation = loadAdminNavigation();
$current = basename($_SERVER['REQUEST_URI']);
$currentAdminTab = $_GET['tab'] ?? 'dashboard';
?>

<header class="admin-header">
    <div class="header-container">
        <div class="logo">
            <div class="logo-text">WGS</div>
            <p>ADMIN</p>
        </div>

        <button class="hamburger-toggle" id="hamburger-toggle" aria-label="Otevřít menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="hamburger-nav" id="hamburger-nav">
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
            <a href="logout.php" class="hamburger-logout" id="logoutBtn">ODHLÁŠENÍ</a>
        </nav>

        <div class="header-actions desktop-only">
            <a href="/logout.php" id="logoutBtnDesktop" class="btn-logout" title="Odhlásit se">Odhlásit</a>
        </div>
    </div>
</header>
<div class="hamburger-overlay" id="hamburger-overlay"></div>

<script>
(function() {
  'use strict';

  function initHamburgerMenu() {
    const hamburger = document.getElementById('hamburger-toggle');
    const nav = document.getElementById('hamburger-nav');
    const overlay = document.getElementById('hamburger-overlay');

    if (!hamburger || !nav || !overlay) {
      console.warn('Hamburger menu: Chybí HTML elementy!');
      return;
    }

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

    console.log('Admin hamburger menu inicializován');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHamburgerMenu);
  } else {
    initHamburgerMenu();
  }
})();
</script>

<!-- Language Switcher - centralizovaný jazykový přepínač -->
<script src="assets/js/language-switcher.js"></script>

<!-- ✅ OPRAVA: logout-handler.js přesunut do globálních scriptů v admin.php (řádek 1113) -->
