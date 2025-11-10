<?php
/**
 * ADMIN HEADER - Shared across all admin pages
 */

require_once __DIR__ . '/admin_navigation.php';

$adminNavigation = loadAdminNavigation();
$currentPathValue = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($currentPathValue === false || $currentPathValue === null) {
    $currentPathValue = $_SERVER['PHP_SELF'] ?? '';
}
$currentPath = basename($currentPathValue);
$currentAdminTab = $_GET['tab'] ?? 'dashboard';
?>

<header class="admin-header">
    <div class="header-container">
        <div class="logo">
            <h1>WGS</h1>
            <p>ADMINISTRACE</p>
        </div>

        <nav class="admin-nav">
            <?php foreach ($adminNavigation as $item):
                if (empty($item['header_label'])) {
                    continue;
                }
                $isActive = isAdminNavigationActive($item, $currentPath, $currentAdminTab);
            ?>
                <a
                    href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="nav-link<?php echo $isActive ? ' active' : ''; ?>"
                    data-page="<?php echo htmlspecialchars($item['tab'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <?php echo htmlspecialchars($item['header_label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="header-actions">
            <button id="logoutBtn" class="btn-logout" title="Odhlásit se">Odhlásit</button>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            if (!confirm('Opravdu se chcete odhlásit?')) return;
            try {
                await fetch('logout.php');
                window.location.href = 'index.php';
            } catch (err) {
                console.error('Logout failed:', err);
                window.location.href = 'index.php';
            }
        });
    }
});
</script>
