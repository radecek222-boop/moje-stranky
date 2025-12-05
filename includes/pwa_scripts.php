<?php
/**
 * PWA Service Worker Scripts Include
 *
 * Tento soubor přidává registraci Service Workeru pro PWA aktualizace.
 * Zahrňte tento soubor před </body> na každé stránce.
 *
 * Příklad použití:
 * <?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
 */

// Kontrola jestli je uzivatel prihlasen (pro notifikace)
$pwaUzivatelPrihlasen = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
?>
<!-- PWA Mode Detection - nastaví cookie pro server-side detekci -->
<script>
(function() {
  // Detekce PWA standalone módu
  var isPWA = window.matchMedia('(display-mode: standalone)').matches ||
              window.navigator.standalone === true;

  if (isPWA) {
    // Nastavit cookie pro PHP detekci (7 dní platnost)
    var expires = new Date();
    expires.setTime(expires.getTime() + (7 * 24 * 60 * 60 * 1000));
    document.cookie = 'wgs_pwa_mode=1; path=/; expires=' + expires.toUTCString() + '; SameSite=Lax; Secure';

    // Přidat custom header pro fetch requesty
    var originalFetch = window.fetch;
    window.fetch = function(url, options) {
      options = options || {};
      options.headers = options.headers || {};
      if (options.headers instanceof Headers) {
        options.headers.append('X-PWA-Mode', 'standalone');
      } else {
        options.headers['X-PWA-Mode'] = 'standalone';
      }
      return originalFetch.call(this, url, options);
    };
  }
})();
</script>
<!-- PWA Service Worker Registration -->
<script src="/assets/js/sw-register.min.js"></script>
<!-- PWA Pull-to-Refresh -->
<script src="/assets/js/pull-to-refresh.min.js"></script>
<?php if ($pwaUzivatelPrihlasen): ?>
<!-- WGS Toast Notifikace (in-app) -->
<link rel="stylesheet" href="/assets/css/wgs-toast.css">
<script src="/assets/js/wgs-toast.js"></script>
<!-- PWA Notifications (Badge + Local Notifications) -->
<script src="/assets/js/pwa-notifications.min.js"></script>
<!-- Online Heartbeat - aktualizace online stavu kazdych 30 sekund -->
<script src="/assets/js/online-heartbeat.js"></script>
<?php endif; ?>
