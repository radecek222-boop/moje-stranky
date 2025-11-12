<?php
/**
 * Control Center - Centrální řídicí panel
 * Mod

ern card-based design s overlay systémem
 */

// Načtení dat ze session
$currentUser = $_SESSION['full_name'] ?? 'Admin';
$userId = $_SESSION['user_id'] ?? null;

// Získání statistik
$pdo = getDbConnection();

// Stats
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
$totalClaims = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_users");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM wgs_sessions WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$onlineUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_registration_keys WHERE is_active = 1");
$activeKeys = $stmt->fetchColumn();

// Pending actions count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_pending_actions WHERE status = 'pending'");
    $pendingActions = $stmt->fetchColumn();
} catch (Exception $e) {
    $pendingActions = 0;
}
?>

<style>
/* Control Center - Modern Card Design */
.control-center {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

.page-header {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--c-black);
    margin-bottom: 0.5rem;
}

.page-subtitle {
    font-size: 0.95rem;
    color: var(--c-grey);
}

/* Card Grid */
.cc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

/* Card Styles */
.cc-card {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    border-radius: 8px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.cc-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    border-color: var(--c-primary);
}

.cc-card-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--c-black);
    margin-bottom: 0.5rem;
}

.cc-card-description {
    font-size: 0.85rem;
    color: var(--c-grey);
    line-height: 1.4;
    margin-bottom: 1rem;
}

.cc-card-stats {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--c-grey);
}

.cc-card-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--c-primary);
}

.cc-card-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--c-error);
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 12px;
    min-width: 24px;
    text-align: center;
}

/* Card States */
.cc-card-disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.cc-card-disabled:hover {
    transform: none;
    box-shadow: none;
    border-color: var(--c-border);
}

.cc-coming-soon {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--c-grey);
    color: white;
    font-size: 0.7rem;
    padding: 4px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Overlay System */
.cc-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9998;
    backdrop-filter: blur(4px);
}

.cc-overlay.active {
    display: block;
    animation: fadeIn 0.2s ease;
}

.cc-modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 1200px;
    max-height: 85vh;
    background: var(--c-white);
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    z-index: 9999;
    overflow: hidden;
}

.cc-modal.active {
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translate(-50%, -45%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

.cc-modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--c-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.cc-modal-back {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--c-grey);
    font-size: 0.9rem;
    cursor: pointer;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.2s;
}

.cc-modal-back:hover {
    background: rgba(0,0,0,0.05);
    color: var(--c-black);
}

.cc-modal-title-section {
    flex: 1;
    margin-left: 1rem;
}

.cc-modal-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--c-black);
    margin: 0;
}

.cc-modal-subtitle {
    font-size: 0.85rem;
    color: var(--c-grey);
    margin-top: 0.25rem;
}

.cc-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
}

.cc-modal-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    color: var(--c-grey);
}

.cc-modal-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid var(--c-border);
    border-top-color: var(--c-primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Table Styles */
.cc-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.cc-table th {
    background: var(--c-bg);
    padding: 1rem;
    text-align: left;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-grey);
    border-bottom: 1px solid var(--c-border);
    font-weight: 600;
}

.cc-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--c-border);
    font-size: 0.9rem;
}

.cc-table tr:hover td {
    background: rgba(0,0,0,0.02);
}

/* Tabs */
.cc-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid var(--c-border);
}

.cc-tab {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--c-grey);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.cc-tab:hover {
    color: var(--c-black);
    background: rgba(0,0,0,0.02);
}

.cc-tab.active {
    color: var(--c-primary);
    border-bottom-color: var(--c-primary);
}

.cc-tab-content {
    display: none;
}

.cc-tab-content.active {
    display: block;
}

/* Iframe container */
.cc-iframe-container {
    width: 100%;
    height: 600px;
    border: 1px solid var(--c-border);
    border-radius: 8px;
    overflow: hidden;
}

.cc-iframe-container iframe {
    width: 100%;
    height: 100%;
    border: none;
}

/* Mini stats grid */
.cc-mini-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.cc-mini-stat {
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
}

.cc-mini-stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--c-primary);
}

.cc-mini-stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-grey);
    margin-top: 0.5rem;
}

/* Actions */
.cc-actions {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .cc-grid {
        grid-template-columns: 1fr;
    }

    .cc-modal {
        width: 95%;
        max-height: 90vh;
    }

    .cc-modal-body {
        padding: 1rem;
    }
}
</style>

<div class="control-center">
    <div class="page-header">
        <h1 class="page-title">Control Center</h1>
        <p class="page-subtitle">Centrální řídicí panel pro správu celé aplikace</p>
    </div>

    <!-- Card Grid -->
    <div class="cc-grid">

        <!-- Statistiky & Analytics -->
        <div class="cc-card cc-card-statistics" onclick="openCCModal('statistics')">
            <div class="cc-card-title">Statistiky & Analytics</div>
            <div class="cc-card-description">Přehledy, grafy a výkonnostní metriky</div>
        </div>

        <!-- Registrační klíče -->
        <div class="cc-card cc-card-keys" onclick="openCCModal('keys')">
            <?php if ($activeKeys > 0): ?>
                <div class="cc-card-badge"><?= $activeKeys ?></div>
            <?php endif; ?>
            <div class="cc-card-title">Registrační klíče</div>
            <div class="cc-card-description">Správa přístupových klíčů pro registraci</div>
            <div class="cc-card-number"><?= $activeKeys ?></div>
        </div>

        <!-- Správa uživatelů -->
        <div class="cc-card cc-card-users" onclick="openCCModal('users')">
            <div class="cc-card-title">Správa uživatelů</div>
            <div class="cc-card-description">Technici, prodejci, administrátoři</div>
            <div class="cc-card-number"><?= $totalUsers ?></div>
        </div>

        <!-- Online uživatelé -->
        <div class="cc-card cc-card-online" onclick="openCCModal('online')">
            <?php if ($onlineUsers > 0): ?>
                <div class="cc-card-badge" style="background: var(--c-success);"><?= $onlineUsers ?></div>
            <?php endif; ?>
            <div class="cc-card-title">Online uživatelé</div>
            <div class="cc-card-description">Aktivní v posledních 15 minutách</div>
            <div class="cc-card-number"><?= $onlineUsers ?></div>
        </div>

        <!-- Email & SMS -->
        <div class="cc-card cc-card-notifications" onclick="openCCModal('notifications')">
            <div class="cc-card-title">Email & SMS</div>
            <div class="cc-card-description">Šablony emailů a SMS notifikace</div>
        </div>

        <!-- Reklamace -->
        <div class="cc-card cc-card-claims" onclick="openCCModal('claims')">
            <div class="cc-card-title">Správa reklamací</div>
            <div class="cc-card-description">Přehled všech servisních požadavků</div>
            <div class="cc-card-number"><?= $totalClaims ?></div>
        </div>

        <!-- Akce & Úkoly -->
        <div class="cc-card cc-card-actions" onclick="openCCModal('actions')">
            <?php if ($pendingActions > 0): ?>
                <div class="cc-card-badge"><?= $pendingActions ?></div>
            <?php endif; ?>
            <div class="cc-card-title">Akce & Úkoly</div>
            <div class="cc-card-description">Nevyřešené úkoly a plánované akce</div>
            <div class="cc-card-number"><?= $pendingActions ?></div>
        </div>

        <!-- Diagnostika -->
        <div class="cc-card cc-card-diagnostics" onclick="openCCModal('diagnostics')">
            <div class="cc-card-title">Diagnostika</div>
            <div class="cc-card-description">Nástroje, logy a system health</div>
        </div>

        <!-- Testovací prostředí -->
        <div class="cc-card cc-card-testing" onclick="openCCModal('testing')">
            <div class="cc-card-title">Testovací prostředí</div>
            <div class="cc-card-description">E2E testování celého workflow</div>
        </div>

        <!-- Vzhled & Design (disabled) -->
        <div class="cc-card cc-card-appearance cc-card-disabled">
            <div class="cc-coming-soon">Soon</div>
            <div class="cc-card-title">Vzhled & Design</div>
            <div class="cc-card-description">Barvy, fonty, logo, branding</div>
        </div>

        <!-- Obsah & Texty (disabled) -->
        <div class="cc-card cc-card-content cc-card-disabled">
            <div class="cc-coming-soon">Soon</div>
            <div class="cc-card-title">Obsah & Texty</div>
            <div class="cc-card-description">Editace textů CZ/EN/SK</div>
        </div>

        <!-- Konfigurace (disabled) -->
        <div class="cc-card cc-card-config cc-card-disabled">
            <div class="cc-coming-soon">Soon</div>
            <div class="cc-card-title">Konfigurace systému</div>
            <div class="cc-card-description">SMTP, API klíče, bezpečnost</div>
        </div>

    </div>
</div>

<!-- Overlay & Modal -->
<div class="cc-overlay" id="ccOverlay" onclick="closeCCModal()"></div>
<div class="cc-modal" id="ccModal">
    <div class="cc-modal-header">
        <div class="cc-modal-back" onclick="closeCCModal()">
            <span>←</span>
            <span>Zpět</span>
        </div>
        <div class="cc-modal-title-section">
            <h2 class="cc-modal-title" id="ccModalTitle">Loading...</h2>
            <div class="cc-modal-subtitle" id="ccModalSubtitle"></div>
        </div>
    </div>
    <div class="cc-modal-body" id="ccModalBody">
        <div class="cc-modal-loading">
            <div class="cc-modal-spinner"></div>
            <div style="margin-top: 1rem;">Načítání...</div>
        </div>
    </div>
</div>

<script>
// Helper function to check if API response is successful
function isSuccess(data) {
    return (data && (data.success === true || data.status === 'success'));
}

// Helper function to get CSRF token from meta tag
function getCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (!metaTag) return null;
    return metaTag.getAttribute('content');
}

// Open modal with specific section
function openCCModal(section) {
    const overlay = document.getElementById('ccOverlay');
    const modal = document.getElementById('ccModal');
    const modalBody = document.getElementById('ccModalBody');
    const modalTitle = document.getElementById('ccModalTitle');
    const modalSubtitle = document.getElementById('ccModalSubtitle');

    // Show overlay and modal
    overlay.classList.add('active');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Show loading
    modalBody.innerHTML = '<div class="cc-modal-loading"><div class="cc-modal-spinner"></div><div style="margin-top: 1rem;">Načítání...</div></div>';

    // Load section content
    switch(section) {
        case 'statistics':
            modalTitle.textContent = 'Statistiky & Analytics';
            modalSubtitle.textContent = 'Přehledy, grafy a výkonnostní metriky';
            loadStatisticsModal();
            break;
        case 'keys':
            modalTitle.textContent = 'Registrační klíče';
            modalSubtitle.textContent = 'Správa přístupových klíčů pro registraci uživatelů';
            loadKeysModal();
            break;
        case 'users':
            modalTitle.textContent = 'Správa uživatelů';
            modalSubtitle.textContent = 'Technici, prodejci, administrátoři, partneři';
            loadUsersModal();
            break;
        case 'online':
            modalTitle.textContent = 'Online uživatelé';
            modalSubtitle.textContent = 'Aktivní uživatelé v posledních 15 minutách';
            loadOnlineModal();
            break;
        case 'notifications':
            modalTitle.textContent = 'Email & SMS notifikace';
            modalSubtitle.textContent = 'Šablony emailů, SMS, automatické notifikace';
            loadNotificationsModal();
            break;
        case 'claims':
            modalTitle.textContent = 'Správa reklamací';
            modalSubtitle.textContent = 'Přehled všech servisních požadavků a reklamací';
            loadClaimsModal();
            break;
        case 'actions':
            modalTitle.textContent = 'Akce & Úkoly';
            modalSubtitle.textContent = 'Nevyřešené úkoly, GitHub webhooks, plánované akce';
            loadActionsModal();
            break;
        case 'diagnostics':
            modalTitle.textContent = 'Diagnostika systému';
            modalSubtitle.textContent = 'Nástroje, migrace a system health';
            loadDiagnosticsModal();
            break;
        case 'testing':
            modalTitle.textContent = 'Testovací prostředí';
            modalSubtitle.textContent = 'E2E testování celého workflow aplikace';
            loadTestingModal();
            break;
    }
}

// Close modal
function closeCCModal() {
    const overlay = document.getElementById('ccOverlay');
    const modal = document.getElementById('ccModal');

    overlay.classList.remove('active');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// === MODAL LOADERS ===

function loadStatisticsModal() {
    const modalBody = document.getElementById('ccModalBody');

    modalBody.innerHTML = `
        <div class="cc-tabs">
            <button class="cc-tab active" onclick="switchStatTab('claims')">Statistiky reklamací</button>
            <button class="cc-tab" onclick="switchStatTab('web')">Web Analytics</button>
        </div>
        <div id="statTabClaims" class="cc-tab-content active">
            <div class="cc-iframe-container">
                <iframe src="statistiky.php?embed=1" sandbox="allow-scripts allow-same-origin" title="Statistiky reklamací"></iframe>
            </div>
        </div>
        <div id="statTabWeb" class="cc-tab-content">
            <div class="cc-iframe-container">
                <iframe src="analytics.php?embed=1" sandbox="allow-scripts allow-same-origin" title="Web Analytics"></iframe>
            </div>
        </div>
    `;
}

function switchStatTab(tab) {
    document.querySelectorAll('.cc-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.cc-tab-content').forEach(c => c.classList.remove('active'));

    if (tab === 'claims') {
        document.querySelectorAll('.cc-tab')[0].classList.add('active');
        document.getElementById('statTabClaims').classList.add('active');
    } else if (tab === 'web') {
        document.querySelectorAll('.cc-tab')[1].classList.add('active');
        document.getElementById('statTabWeb').classList.add('active');
    }
}

function loadKeysModal() {
    const modalBody = document.getElementById('ccModalBody');

    modalBody.innerHTML = `
        <div class="cc-actions">
            <button class="btn btn-sm btn-success" onclick="createKey()">+ Vytvořit nový klíč</button>
            <button class="btn btn-sm" onclick="loadKeysModal()">Obnovit</button>
        </div>
        <div id="keysTableContainer">Načítání klíčů...</div>
    `;

    // Load keys
    fetch('api/admin_api.php?action=list_keys')
        .then(async r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {
            const container = document.getElementById('keysTableContainer');

            if (isSuccess(data) && data.keys && data.keys.length > 0) {
                let html = '<table class="cc-table"><thead><tr>';
                html += '<th>Klíč</th><th>Typ</th><th>Použití</th><th>Status</th><th>Vytvořen</th><th>Akce</th>';
                html += '</tr></thead><tbody>';

                data.keys.forEach(key => {
                    html += '<tr>';
                    html += `<td><code>${key.key_code}</code></td>`;
                    html += `<td><span class="badge badge-${key.key_type}">${key.key_type}</span></td>`;
                    html += `<td>${key.usage_count} / ${key.max_usage || '∞'}</td>`;
                    html += `<td><span class="badge badge-${key.is_active ? 'active' : 'inactive'}">${key.is_active ? 'Aktivní' : 'Neaktivní'}</span></td>`;
                    html += `<td>${new Date(key.created_at).toLocaleDateString('cs-CZ')}</td>`;
                    html += `<td><button class="btn btn-sm btn-danger" onclick="deleteKey(${key.id})">Smazat</button></td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else if (isSuccess(data) && data.keys && data.keys.length === 0) {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádné registrační klíče<br><small>Vytvořte nový klíč pomocí tlačítka výše</small></p>';
            } else {
                container.innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
            }
        })
        .catch(err => {
            console.error('[Control Center] Keys load error:', err);
            document.getElementById('keysTableContainer').innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">⚠️ Chyba načítání</p>';
        });
}

function loadUsersModal() {
    const modalBody = document.getElementById('ccModalBody');

    modalBody.innerHTML = `
        <div class="cc-actions">
            <input type="text" class="search-box" id="ccSearchUsers" placeholder="Hledat uživatele..." style="flex: 1; max-width: 300px;">
            <button class="btn btn-sm btn-success" onclick="window.location.href='admin.php?tab=users'">+ Přidat uživatele</button>
            <button class="btn btn-sm" onclick="loadUsersModal()">Obnovit</button>
        </div>
        <div id="usersTableContainer">Načítání uživatelů...</div>
    `;

    // Load users
    fetch('api/admin_api.php?action=list_users')
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {
            const container = document.getElementById('usersTableContainer');
            const users = data.data || data.users || [];

            if (isSuccess(data) && users.length > 0) {
                let html = '<table class="cc-table"><thead><tr>';
                html += '<th>ID</th><th>Jméno</th><th>Email</th><th>Role</th><th>Status</th><th>Vytvořen</th></tr></thead><tbody>';

                users.forEach(user => {
                    html += '<tr>';
                    html += `<td>#${user.id}</td>`;
                    html += `<td>${user.name || user.full_name || ''}</td>`;
                    html += `<td>${user.email || ''}</td>`;
                    html += `<td><span class="badge badge-${user.role}">${user.role || ''}</span></td>`;
                    html += `<td><span class="badge badge-${user.is_active ? 'active' : 'inactive'}">${user.is_active ? 'Aktivní' : 'Neaktivní'}</span></td>`;
                    html += `<td>${user.created_at ? new Date(user.created_at).toLocaleDateString('cs-CZ') : '—'}</td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else if (isSuccess(data)) {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádní uživatelé</p>';
            } else {
                container.innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
            }
        })
        .catch(err => {
            console.error('[Control Center] Users load error:', err);
            document.getElementById('usersTableContainer').innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">⚠️ Chyba načítání</p>';
        });
}

function loadOnlineModal() {
    const modalBody = document.getElementById('ccModalBody');

    modalBody.innerHTML = `
        <div class="cc-actions">
            <button class="btn btn-sm" onclick="loadOnlineModal()">Obnovit</button>
        </div>
        <div id="onlineTableContainer">Načítání online uživatelů...</div>
    `;

    // Load online users
    fetch('api/admin_users_api.php?action=online')
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {
            const container = document.getElementById('onlineTableContainer');

            if (isSuccess(data) && data.users && data.users.length > 0) {
                let html = '<table class="cc-table"><thead><tr>';
                html += '<th>Jméno</th><th>Role</th><th>Email</th><th>Poslední aktivita</th>';
                html += '</tr></thead><tbody>';

                data.users.forEach(user => {
                    html += '<tr>';
                    html += `<td><span class="online-indicator" style="display: inline-block; width: 8px; height: 8px; background: var(--c-success); border-radius: 50%; margin-right: 0.5rem;"></span>${user.full_name || user.name || ''}</td>`;
                    html += `<td><span class="badge badge-${user.role}">${user.role || ''}</span></td>`;
                    html += `<td>${user.email || ''}</td>`;
                    html += `<td>${new Date(user.last_activity).toLocaleString('cs-CZ')}</td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else if (isSuccess(data)) {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádní online uživatelé</p>';
            } else {
                container.innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
            }
        })
        .catch(err => {
            console.error('[Control Center] Online users load error:', err);
            document.getElementById('onlineTableContainer').innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">⚠️ Chyba načítání</p>';
        });
}

function loadNotificationsModal() {
    const modalBody = document.getElementById('ccModalBody');
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="admin.php?tab=notifications&embed=1" sandbox="allow-scripts allow-same-origin allow-forms" title="Email & SMS notifikace"></iframe></div>';
}

function loadClaimsModal() {
    const modalBody = document.getElementById('ccModalBody');

    modalBody.innerHTML = `
        <div class="cc-mini-stats">
            <div class="cc-mini-stat">
                <div class="cc-mini-stat-value" id="ccClaimsWait">-</div>
                <div class="cc-mini-stat-label">Čekající</div>
            </div>
            <div class="cc-mini-stat">
                <div class="cc-mini-stat-value" id="ccClaimsOpen">-</div>
                <div class="cc-mini-stat-label">Otevřené</div>
            </div>
            <div class="cc-mini-stat">
                <div class="cc-mini-stat-value" id="ccClaimsDone">-</div>
                <div class="cc-mini-stat-label">Dokončené</div>
            </div>
            <div class="cc-mini-stat">
                <div class="cc-mini-stat-value" id="ccClaimsTotal"><?= $totalClaims ?></div>
                <div class="cc-mini-stat-label">Celkem</div>
            </div>
        </div>
        <div class="cc-actions">
            <a href="seznam.php" class="btn btn-sm">Otevřít seznam reklamací</a>
            <a href="novareklamace.php" class="btn btn-sm btn-success">+ Nová reklamace</a>
        </div>
    `;

    // Load claims stats
    fetch('api/admin_api.php?action=list_reklamace')
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {
            if (isSuccess(data) && data.reklamace) {
                const claims = data.reklamace;
                const wait = claims.filter(c => c.stav === 'ČEKÁ').length;
                const open = claims.filter(c => c.stav === 'DOMLUVENÁ').length;
                const done = claims.filter(c => c.stav === 'HOTOVO').length;

                document.getElementById('ccClaimsWait').textContent = wait;
                document.getElementById('ccClaimsOpen').textContent = open;
                document.getElementById('ccClaimsDone').textContent = done;
            }
        })
        .catch(err => {
            console.error('[Control Center] Claims stats load error:', err);
        });
}

function loadActionsModal() {
    const modalBody = document.getElementById('ccModalBody');

    modalBody.innerHTML = `
        <div class="cc-actions">
            <button class="btn btn-sm" onclick="loadActionsModal()">Obnovit</button>
        </div>
        <div id="actionsTableContainer">Načítání úkolů...</div>
    `;

    // Load actions
    fetch('api/control_center_api.php?action=get_pending_actions')
        .then(r => {
            if (!r.ok) {
                if (r.status === 400 || r.status === 500) {
                    return { success: true, actions: [] };
                }
                throw new Error(`HTTP ${r.status}`);
            }
            return r.json();
        })
        .then(data => {
            const container = document.getElementById('actionsTableContainer');

            if (isSuccess(data) && data.actions && data.actions.length > 0) {
                let html = '<table class="cc-table"><thead><tr>';
                html += '<th>Priorita</th><th>Název</th><th>Popis</th><th>Vytvořeno</th><th>Akce</th>';
                html += '</tr></thead><tbody>';

                data.actions.forEach(action => {
                    const priorityColors = {
                        'critical': '#DC3545',
                        'high': '#FF6B6B',
                        'medium': '#FFC107',
                        'low': '#28A745'
                    };
                    html += '<tr>';
                    html += `<td><span class="badge" style="background: ${priorityColors[action.priority]}; color: white;">${action.priority}</span></td>`;
                    html += `<td><strong>${action.action_title}</strong></td>`;
                    html += `<td>${action.action_description || '-'}</td>`;
                    html += `<td>${new Date(action.created_at).toLocaleDateString('cs-CZ')}</td>`;
                    html += `<td>`;
                    html += `<button class="btn btn-sm btn-success" onclick="completeAction(${action.id})">Dokončit</button> `;
                    html += `<button class="btn btn-sm btn-secondary" onclick="dismissAction(${action.id})">Zrušit</button>`;
                    html += `</td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else if (isSuccess(data)) {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádné nevyřízené úkoly</p>';
            } else {
                container.innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
            }
        })
        .catch(err => {
            console.error('[Control Center] Actions load error:', err);
            document.getElementById('actionsTableContainer').innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádné nevyřízené úkoly</p>';
        });
}

function loadDiagnosticsModal() {
    const modalBody = document.getElementById('ccModalBody');
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="admin.php?tab=tools&embed=1" sandbox="allow-scripts allow-same-origin allow-forms" title="Diagnostika systému"></iframe></div>';
}

function loadTestingModal() {
    const modalBody = document.getElementById('ccModalBody');
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="admin.php?tab=control_center_testing_simulator&embed=1" sandbox="allow-scripts allow-same-origin allow-forms" title="Testovací prostředí"></iframe></div>';
}

// === ACTION HANDLERS ===

function deleteKey(keyId) {
    if (!confirm('Opravdu chcete smazat tento klíč?')) return;

    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert('Chyba: CSRF token nebyl nalezen. Obnovte stránku.');
        return;
    }

    fetch('api/admin_api.php?action=delete_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            key_id: keyId,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (isSuccess(data)) {
            loadKeysModal(); // Reload
        } else {
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

function createKey() {
    const keyType = prompt('Typ klíče (admin/technik/prodejce/partner):');
    if (!keyType) return;

    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert('Chyba: CSRF token nebyl nalezen. Obnovte stránku.');
        return;
    }

    fetch('api/admin_api.php?action=create_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            key_type: keyType,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (isSuccess(data)) {
            alert('Vytvořeno: ' + data.key_code);
            loadKeysModal(); // Reload
        } else {
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

function completeAction(actionId) {
    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert('Chyba: CSRF token nebyl nalezen. Obnovte stránku.');
        return;
    }

    fetch('api/control_center_api.php?action=complete_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action_id: actionId,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (isSuccess(data)) {
            loadActionsModal();
            location.reload();
        } else {
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

function dismissAction(actionId) {
    const csrfToken = getCSRFToken();
    if (!csrfToken) {
        alert('Chyba: CSRF token nebyl nalezen. Obnovte stránku.');
        return;
    }

    fetch('api/control_center_api.php?action=dismiss_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action_id: actionId,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (isSuccess(data)) {
            loadActionsModal();
            location.reload();
        } else {
            alert('Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
        }
    })
    .catch(err => {
        alert('Chyba: ' + err.message);
    });
}

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeCCModal();
    }
});
</script>
