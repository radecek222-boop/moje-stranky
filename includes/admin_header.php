<?php
/**
 * ADMIN HEADER - Minimalistický header pouze s logem a odhlášením
 * Všechny funkce jsou nyní v Control Center
 */
?>

<header class="admin-header">
    <div class="header-container">
        <div class="logo">
            <h1>WGS</h1>
            <p>CONTROL CENTER</p>
        </div>

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
