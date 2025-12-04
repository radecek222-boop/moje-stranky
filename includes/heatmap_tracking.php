<?php
/**
 * Heatmap Tracking Include
 * Přidá tracking script na veřejné stránky
 */

// Pouze pro veřejné stránky (ne admin, ne API)
$isAdminPage = (strpos($_SERVER['REQUEST_URI'], '/admin') !== false);
$isApiPage = (strpos($_SERVER['REQUEST_URI'], '/api/') !== false);

if ($isAdminPage || $isApiPage) {
    return; // Nepřidávat tracking na admin a API stránky
}

// Vygenerovat CSRF token pokud ještě není
if (!isset($_SESSION['csrf_token'])) {
    require_once __DIR__ . '/csrf_helper.php';
    generateCSRFToken();
}
?>
<!-- Heatmap Tracker -->
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
<script src="/assets/js/heatmap-tracker.min.js" defer></script>
