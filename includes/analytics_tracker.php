<?php
/**
 * Analytics Tracker Snippet
 * Vložte tento soubor do <head> sekce všech stránek pomocí:
 * <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
 *
 * Tracker se načte pouze pokud:
 * - Uživatel je přihlášen (právní základ: smlouva)
 * - Nebo uživatel dal souhlas s analytickými cookies
 */

// Kontrola, zda je uživatel přihlášen
$jePrihlasen = isset($_SESSION['user_id']) || isset($_SESSION['is_admin']);

// Kontrola souhlasu s analytickými cookies
$maAnalytickySouhlas = isset($_COOKIE['wgs_analytics_consent']) && $_COOKIE['wgs_analytics_consent'] === '1';

// Načíst tracker pouze se souhlasem nebo pro přihlášené
if ($jePrihlasen || $maAnalytickySouhlas):
?>
<!-- WGS Analytics Tracker -->
<script src="/assets/js/tracker.min.js" defer></script>
<?php endif; ?>
