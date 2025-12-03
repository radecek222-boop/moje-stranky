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
<?php endif; ?>
