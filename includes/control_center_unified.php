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
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.page-subtitle {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--c-black);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
    flex: 1;
}

.page-header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.cc-version-info {
    font-size: 0.7rem;
    color: var(--c-grey);
    padding: 0.25rem 0.5rem;
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    border-radius: 4px;
    font-family: 'Courier New', monospace;
}

.cc-clear-cache-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.cc-clear-cache-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.cc-clear-cache-btn:active {
    transform: translateY(0);
}

/* Card Grid */
.cc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.8rem;
    margin-top: 1.5rem;
}

/* Card Styles */
.cc-card {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    border-radius: 6px;
    padding: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    min-height: 60px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.cc-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    border-color: var(--c-primary);
}

.cc-card-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--c-black);
    margin-bottom: 0.25rem;
}

.cc-card-description {
    font-size: 0.7rem;
    color: var(--c-grey);
    line-height: 1.3;
    margin: 0;
}

.cc-card-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    background: var(--c-error);
    color: white;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 8px;
    min-width: 18px;
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
    top: 6px;
    right: 6px;
    background: var(--c-grey);
    color: white;
    font-size: 0.6rem;
    padding: 2px 5px;
    border-radius: 3px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
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
    width: 95%;
    max-width: 1600px;
    max-height: 96vh;
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
    padding: 0.5rem;
    border-bottom: 1px solid var(--c-border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-shrink: 0;
}

.cc-modal-close {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--c-grey);
    font-size: 1.5rem;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.2s;
    background: transparent;
    border: none;
}

.cc-modal-close:hover {
    background: rgba(0,0,0,0.05);
    color: var(--c-black);
}

.cc-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0.75rem;
}

.cc-modal-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    color: var(--c-grey);
}

.cc-modal-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid var(--c-border);
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
    margin-top: 0.3rem;
}

.cc-table th {
    background: var(--c-bg);
    padding: 0.3rem 0.5rem;
    text-align: left;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-grey);
    border-bottom: 1px solid var(--c-border);
    font-weight: 600;
}

.cc-table td {
    padding: 0.3rem 0.5rem;
    border-bottom: 1px solid var(--c-border);
    font-size: 0.75rem;
}

.cc-table tr:hover td {
    background: rgba(0,0,0,0.02);
}

/* Tabs */
.cc-tabs {
    display: flex;
    gap: 0.2rem;
    margin-bottom: 0.6rem;
    border-bottom: 1px solid var(--c-border);
}

.cc-tab {
    padding: 0.35rem 0.7rem;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--c-grey);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.7rem;
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
    height: calc(92vh - 50px);
    min-height: 500px;
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
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.6rem;
}

.cc-mini-stat {
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    padding: 0.4rem;
    border-radius: 4px;
    text-align: center;
}

.cc-mini-stat-value {
    font-size: 1rem;
    font-weight: 700;
    color: var(--c-primary);
}

.cc-mini-stat-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-grey);
    margin-top: 0.15rem;
}

/* Actions */
.cc-actions {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
    margin-bottom: 0.6rem;
}

/* Unified button styles for Control Center */
.cc-actions .btn,
.cc-actions button,
.cc-actions a.btn {
    padding: 0.3rem 0.6rem;
    font-size: 0.7rem;
    line-height: 1.2;
    border-radius: 3px;
    font-weight: 500;
    white-space: nowrap;
}

.cc-actions .btn-sm {
    padding: 0.3rem 0.6rem;
    font-size: 0.7rem;
}

.cc-actions .search-box {
    padding: 0.3rem 0.6rem;
    font-size: 0.7rem;
    line-height: 1.2;
    border-radius: 3px;
}

/* Responsive */
@media (max-width: 768px) {
    .cc-grid {
        grid-template-columns: 1fr;
    }

    .cc-modal {
        width: 95%;
        max-height: 94vh;
    }

    .cc-modal-body {
        padding: 1rem;
    }
}
</style>

<div class="control-center">
    <div class="page-header">
        <p class="page-subtitle">Centrální řídicí panel pro správu celé aplikace</p>
        <div class="page-header-actions">
            <span class="cc-version-info" id="ccVersionInfo" title="Verze Control Center - čas poslední úpravy">v<?= date('Y.m.d-Hi', filemtime(__FILE__)) ?></span>
            <button class="cc-clear-cache-btn" onclick="clearCacheAndReload()" title="Vymaže lokální cache a načte nejnovější verzi">
                🔄 Vymazat cache & Reload
            </button>
        </div>
    </div>

    <!-- Card Grid -->
    <div class="cc-grid">

        <!-- Statistiky reklamací -->
        <div class="cc-card cc-card-statistics" onclick="openCCModal('statistics')">
            <div class="cc-card-title">Statistiky</div>
            <div class="cc-card-description">Přehledy a grafy reklamací</div>
        </div>

        <!-- Web Analytics -->
        <div class="cc-card cc-card-analytics" onclick="openCCModal('analytics')">
            <div class="cc-card-title">Analytics</div>
            <div class="cc-card-description">Web analytika a metriky</div>
        </div>

        <!-- Registrační klíče -->
        <div class="cc-card cc-card-keys" onclick="openCCModal('keys')">
            <?php if ($activeKeys > 0): ?>
                <div class="cc-card-badge"><?= $activeKeys ?></div>
            <?php endif; ?>
            <div class="cc-card-title">Registrační klíče</div>
            <div class="cc-card-description">Správa přístupových klíčů pro registraci</div>
        </div>

        <!-- Správa uživatelů -->
        <div class="cc-card cc-card-users" onclick="openCCModal('users')">
            <div class="cc-card-title">Správa uživatelů</div>
            <div class="cc-card-description">Technici, prodejci, administrátoři</div>
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
        </div>

        <!-- Akce & Úkoly -->
        <div class="cc-card cc-card-actions" onclick="openCCModal('actions')">
            <?php if ($pendingActions > 0): ?>
                <div class="cc-card-badge"><?= $pendingActions ?></div>
            <?php endif; ?>
            <div class="cc-card-title">Akce & Úkoly</div>
            <div class="cc-card-description">Nevyřešené úkoly a plánované akce</div>
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

        <!-- Vzhled & Design -->
        <div class="cc-card cc-card-appearance" onclick="openCCModal('appearance')">
            <div class="cc-card-title">Vzhled & Design</div>
            <div class="cc-card-description">Barvy, fonty, logo, branding</div>
        </div>

        <!-- Obsah & Texty -->
        <div class="cc-card cc-card-content" onclick="openCCModal('content')">
            <div class="cc-card-title">Obsah & Texty</div>
            <div class="cc-card-description">Editace textů CZ/EN/SK</div>
        </div>

        <!-- Konfigurace -->
        <div class="cc-card cc-card-config" onclick="openCCModal('config')">
            <div class="cc-card-title">Konfigurace systému</div>
            <div class="cc-card-description">SMTP, API klíče, bezpečnost</div>
        </div>

    </div>
</div>

<!-- Overlay & Modal -->
<div class="cc-overlay" id="ccOverlay" onclick="closeCCModal()"></div>
<div class="cc-modal" id="ccModal">
    <div class="cc-modal-header">
        <button class="cc-modal-close" onclick="closeCCModal()" aria-label="Zavřít">×</button>
    </div>
    <div class="cc-modal-body" id="ccModalBody">
        <div class="cc-modal-loading">
            <div class="cc-modal-spinner"></div>
            <div style="margin-top: 1rem;">Načítání...</div>
        </div>
    </div>
</div>

<script>
// Control Center Unified - Version Check
console.log('%c🔧 Control Center v2025.11.12-1430 loaded', 'background: #667eea; color: white; padding: 4px 8px; border-radius: 4px;');
console.log('✅ executeAction is ASYNC + event.target captured BEFORE await');

// Helper function to check if API response is successful
function isSuccess(data) {
    return (data && (data.success === true || data.status === 'success'));
}

// Helper function to get CSRF token from meta tag
function getCSRFToken() {
    // Zkusit nejprve aktuální dokument
    let metaTag = document.querySelector('meta[name="csrf-token"]');

    // Pokud jsme v iframe, zkusit parent window
    if (!metaTag && window.parent && window.parent !== window) {
        try {
            metaTag = window.parent.document.querySelector('meta[name="csrf-token"]');
        } catch (e) {
            // Cross-origin iframe - nemůžeme přistoupit k parent
            console.error('Cannot access parent CSRF token:', e);
        }
    }

    if (!metaTag) {
        console.error('CSRF token meta tag not found in document or parent');
        return null;
    }

    const token = metaTag.getAttribute('content');

    // Ujistit se že token je string
    const tokenStr = token ? String(token).trim() : null;

    if (tokenStr) {
        console.log('CSRF token loaded:', tokenStr.substring(0, 10) + '... (length: ' + tokenStr.length + ')');
    } else {
        console.error('CSRF token is empty');
    }

    return tokenStr;
}

// Open modal with specific section
function openCCModal(section) {
    const overlay = document.getElementById('ccOverlay');
    const modal = document.getElementById('ccModal');
    const modalBody = document.getElementById('ccModalBody');

    // Show overlay and modal
    overlay.classList.add('active');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Show loading
    modalBody.innerHTML = '<div class="cc-modal-loading"><div class="cc-modal-spinner"></div><div style="margin-top: 1rem;">Načítání...</div></div>';

    // Load section content
    switch(section) {
        case 'statistics':
            loadStatisticsModal();
            break;
        case 'analytics':
            loadAnalyticsModal();
            break;
        case 'keys':
            loadKeysModal();
            break;
        case 'users':
            loadUsersModal();
            break;
        case 'notifications':
            loadNotificationsModal();
            break;
        case 'claims':
            loadClaimsModal();
            break;
        case 'actions':
            loadActionsModal();
            break;
        case 'diagnostics':
            loadDiagnosticsModal();
            break;
        case 'testing':
            loadTestingModal();
            break;
        case 'appearance':
            loadAppearanceModal();
            break;
        case 'content':
            loadContentModal();
            break;
        case 'config':
            loadConfigModal();
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
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="statistiky.php?embed=1" sandbox="allow-scripts allow-same-origin" title="Statistiky reklamací"></iframe></div>';
}

function loadAnalyticsModal() {
    const modalBody = document.getElementById('ccModalBody');
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="analytics.php?embed=1" sandbox="allow-scripts allow-same-origin" title="Web Analytics"></iframe></div>';
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
                    html += `<td><button class="btn btn-sm btn-danger" onclick="deleteKey('${key.key_code}')">Smazat</button></td>`;
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
            document.getElementById('keysTableContainer').innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
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
            document.getElementById('usersTableContainer').innerHTML = '<p style="color: var(--c-error); text-align: center; padding: 2rem;">Chyba načítání</p>';
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
                    html += `<button class="btn btn-sm btn-success" onclick="executeAction(${action.id})">Spustit akci</button> `;
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
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="admin.php?tab=control_center_testing_interactive&embed=1" sandbox="allow-scripts allow-same-origin allow-forms" title="Testovací prostředí"></iframe></div>';
}

function loadAppearanceModal() {
    const modalBody = document.getElementById('ccModalBody');
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="admin.php?tab=control_center_appearance&embed=1" title="Vzhled & Design"></iframe></div>';
}

function loadContentModal() {
    const modalBody = document.getElementById('ccModalBody');
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="admin.php?tab=control_center_content&embed=1" title="Obsah & Texty"></iframe></div>';
}

function loadConfigModal() {
    const modalBody = document.getElementById('ccModalBody');
    modalBody.innerHTML = '<div class="cc-iframe-container"><iframe src="admin.php?tab=control_center_configuration&embed=1" title="Konfigurace systému"></iframe></div>';
}

// === ACTION HANDLERS ===

function deleteKey(keyCode) {
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
            key_code: keyCode,
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

async function executeAction(actionId) {
    console.log('[executeAction] Starting with actionId:', actionId);

    // Capture button reference BEFORE any await (event becomes undefined after await in async functions)
    const btn = event.target;
    const originalText = btn.textContent;

    // Await the CSRF token (handles both sync and async getCSRFToken)
    const csrfToken = await getCSRFToken();
    console.log('[executeAction] CSRF token retrieved:', {
        type: typeof csrfToken,
        value: csrfToken && typeof csrfToken === 'string' ? csrfToken.substring(0, 10) + '...' : csrfToken,
        length: csrfToken ? csrfToken.length : 0
    });

    if (!csrfToken || typeof csrfToken !== 'string' || csrfToken.length === 0) {
        alert('Chyba: CSRF token nebyl nalezen nebo je neplatný. Obnovte stránku.');
        console.error('[executeAction] CSRF token is invalid:', {type: typeof csrfToken, value: csrfToken});
        return;
    }

    if (!confirm('Spustit tuto akci? Bude provedena automaticky.')) {
        console.log('[executeAction] User cancelled');
        return;
    }

    // Disable button during execution
    btn.disabled = true;
    btn.textContent = 'Provádění...';

    const payload = {
        action_id: actionId,
        csrf_token: csrfToken
    };

    console.log('[executeAction] Sending request with payload:', payload);

    fetch('api/control_center_api.php?action=execute_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(async r => {
        console.log('[executeAction] Response status:', r.status);

        // Zkusit načíst JSON i při chybě
        let responseData;
        try {
            responseData = await r.json();
            console.log('[executeAction] Response data:', responseData);
        } catch (e) {
            console.error('[executeAction] Failed to parse JSON:', e);
            responseData = null;
        }

        if (!r.ok) {
            const errorMsg = responseData
                ? `HTTP ${r.status}: ${responseData.message || 'Unknown error'}\n\nDebug: ${JSON.stringify(responseData.debug || {}, null, 2)}`
                : `HTTP ${r.status}`;
            throw new Error(errorMsg);
        }

        return responseData;
    })
    .then(data => {
        console.log('[executeAction] Success data:', data);

        if (isSuccess(data)) {
            const execTime = data.execution_time || 'neznámý čas';
            alert(`✓ Akce dokončena!\n\n${data.message}\n\nČas provedení: ${execTime}`);
            loadActionsModal();
        } else {
            console.error('[executeAction] Action failed:', data);
            alert('✗ Chyba: ' + (data.error || data.message || 'Neznámá chyba'));
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(err => {
        console.error('[executeAction] Error:', err);
        alert('✗ Chyba při provádění akce: ' + err.message);
        btn.disabled = false;
        btn.textContent = originalText;
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

// Clear cache and reload
function clearCacheAndReload() {
    if (!confirm('Vymazat lokální cache a načíst nejnovější verzi? Stránka se znovu načte.')) {
        return;
    }

    try {
        // Vymazat localStorage
        if (window.localStorage) {
            const itemsToKeep = ['theme', 'user_preferences']; // Ponechat důležité věci
            const storage = {};
            itemsToKeep.forEach(key => {
                const val = localStorage.getItem(key);
                if (val !== null) storage[key] = val;
            });

            localStorage.clear();

            // Vrátit důležité položky
            Object.keys(storage).forEach(key => {
                localStorage.setItem(key, storage[key]);
            });

            console.log('✓ localStorage vymazán');
        }

        // Vymazat sessionStorage
        if (window.sessionStorage) {
            sessionStorage.clear();
            console.log('✓ sessionStorage vymazán');
        }

        // Vymazat Service Worker cache (pokud existuje)
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => caches.delete(name));
                console.log('✓ Service Worker cache vymazán');
            });
        }

        console.log('🔄 Reloaduji stránku s force refresh...');

        // Force reload s timestamp pro cache busting
        const timestamp = new Date().getTime();
        const url = new URL(window.location.href);
        url.searchParams.set('_cachebust', timestamp);

        // Hard reload
        window.location.href = url.toString();

        // Fallback: pokud výše nefunguje
        setTimeout(() => {
            window.location.reload(true);
        }, 100);

    } catch (err) {
        console.error('Chyba při mazání cache:', err);
        alert('Chyba při mazání cache. Zkuste manuální refresh (Ctrl+Shift+R).');
    }
}

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeCCModal();
    }
});
</script>
