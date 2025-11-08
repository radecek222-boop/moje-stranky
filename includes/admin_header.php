<?php
/**
 * ADMIN HEADER - Shared across all admin pages
 */
?>

<header class="admin-header">
    <div class="header-container">
        <div class="logo">
            <h1>WGS</h1>
            <p>ADMINISTRACE</p>
        </div>
        
        <nav class="admin-nav">
            <a href="admin.php" class="nav-link" data-page="dashboard">DASHBOARD</a>
            <a href="statistiky.php" class="nav-link" data-page="statistics">STATISTIKY</a>
            <a href="analytics.php" class="nav-link" data-page="analytics">ANALYTICS</a>
            <a href="admin.php?tab=notifications" class="nav-link" data-page="notifications">EMAILY & SMS</a>
            <a href="admin.php?tab=keys" class="nav-link" data-page="keys">REGISTRAČNÍ KLÍČE</a>
            <a href="admin.php?tab=users" class="nav-link" data-page="users">UŽIVATELÉ</a>
            <a href="admin.php?tab=online" class="nav-link" data-page="online">ONLINE</a>
            <a href="seznam.php" class="nav-link" data-page="complaints">REKLAMACE</a>
            <a href="psa.php" class="nav-link" data-page="psa">PSA</a>
        </nav>
        
        <div class="header-actions">
            <button id="logoutBtn" class="btn-logout" title="Odhlásit se">Odhlásit</button>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Zjisti aktuální stránku z URL
    const path = window.location.pathname;
    const params = new URLSearchParams(window.location.search);
    
    // Zvýraznění aktivního linku
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        
        // Pokud je aktuální URL rovna href, zvýrazni
        if (href === path || href === path.split('/').pop()) {
            link.classList.add('active');
        }
        // Speciální případ pro ?tab=XXX
        else if (href.includes('?tab=')) {
            const tabParam = params.get('tab');
            if (href.includes('tab=' + tabParam)) {
                link.classList.add('active');
            }
        }
    });
    
    // Logout button
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
