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
<script src="/assets/js/sw-register.js"></script>
<?php if ($pwaUzivatelPrihlasen): ?>
<!-- PWA Notifications (Badge + Local Notifications) -->
<script src="/assets/js/pwa-notifications.js"></script>
<?php endif; ?>
