<?php
/**
 * Control Center - Centrální řídicí panel
 * Obsahuje VŠECHNY admin funkce v jednom místě
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
/* Control Center Accordion Styles */
.control-center {
    max-width: 1400px;
    margin: 0 auto;
}

.cc-section {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.cc-section:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.cc-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--c-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.cc-header:hover {
    background: rgba(0,0,0,0.02);
}

.cc-title-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.cc-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--c-black);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.cc-subtitle {
    font-size: 0.85rem;
    color: var(--c-grey);
    margin-top: 0.3rem;
}

.cc-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    background: var(--c-error);
    color: white;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.cc-chevron {
    font-size: 1.2rem;
    color: var(--c-grey);
    transition: transform 0.3s ease;
}

.cc-section.expanded .cc-chevron {
    transform: rotate(180deg);
}

.cc-body {
    display: none;
    padding: 0;
    border-top: 1px solid var(--c-border);
}

.cc-section.expanded .cc-body {
    display: block;
}

.cc-content {
    padding: 1.5rem;
}

/* Mini stats in sections */
.mini-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.mini-stat {
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    padding: 1rem;
    text-align: center;
}

.mini-stat-value {
    font-size: 2rem;
    font-weight: 600;
    color: var(--c-black);
}

.mini-stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-grey);
    margin-top: 0.5rem;
}

/* Data tables in accordion */
.cc-table {
    width: 100%;
    border-collapse: collapse;
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
}

.cc-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--c-border);
}

.cc-table tr:hover td {
    background: rgba(0,0,0,0.02);
}

/* Action buttons in sections */
.cc-actions {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}
</style>

<div class="control-center">
    <h1 class="page-title">Control Center</h1>
    <p class="page-subtitle">Centrální řídicí panel pro správu celé aplikace</p>

    <!-- Quick Stats -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-label">Reklamace celkem</div>
            <div class="stat-value"><?= $totalClaims ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Uživatelé</div>
            <div class="stat-value"><?= $totalUsers ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Online nyní</div>
            <div class="stat-value"><?= $onlineUsers ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Nevyřízené úkoly</div>
            <div class="stat-value"><?= $pendingActions ?></div>
        </div>
    </div>

    <!-- SEKCE 1: STATISTIKY & ANALYTICS -->
    <div class="cc-section" data-section="statistics">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Statistiky & Analytics</div>
                    <div class="cc-subtitle">Přehledy, grafy, výkonnostní metriky</div>
                </div>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <p style="color: var(--c-grey); margin-bottom: 1rem;">
                    <strong>COMING SOON:</strong> Zde budou kompletní statistiky z statistiky.php - filtry, grafy, export do Excel/PDF
                </p>
                <div class="cc-actions">
                    <a href="statistiky.php" class="btn btn-sm">Otevřít statistiky (dočasně)</a>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 2: REGISTRAČNÍ KLÍČE -->
    <div class="cc-section" data-section="keys">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Registrační klíče</div>
                    <div class="cc-subtitle">Správa přístupových klíčů pro registraci uživatelů</div>
                </div>
                <?php if ($activeKeys > 0): ?>
                    <span class="cc-badge"><?= $activeKeys ?></span>
                <?php endif; ?>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <div class="cc-actions">
                    <button class="btn btn-sm btn-success" id="ccCreateKey">+ Vytvořit nový klíč</button>
                    <button class="btn btn-sm" id="ccRefreshKeys">Obnovit seznam</button>
                </div>
                <div id="ccKeysTable">
                    <div class="loading">Načítání klíčů...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 3: UŽIVATELÉ -->
    <div class="cc-section" data-section="users">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Správa uživatelů</div>
                    <div class="cc-subtitle">Technici, prodejci, administrátoři, partneři</div>
                </div>
                <span class="cc-badge"><?= $totalUsers ?></span>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <div class="cc-actions">
                    <input type="text" class="search-box" id="ccSearchUsers" placeholder="Hledat uživatele...">
                    <button class="btn btn-sm btn-success" id="ccAddUser">+ Přidat uživatele</button>
                    <button class="btn btn-sm" id="ccRefreshUsers">Obnovit</button>
                </div>
                <div id="ccUsersTable">
                    <div class="loading">Načítání uživatelů...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 4: ONLINE UŽIVATELÉ -->
    <div class="cc-section" data-section="online">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Online uživatelé</div>
                    <div class="cc-subtitle">Aktivní uživatelé v posledních 15 minutách</div>
                </div>
                <?php if ($onlineUsers > 0): ?>
                    <span class="cc-badge" style="background: var(--c-success);"><?= $onlineUsers ?></span>
                <?php endif; ?>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <div class="cc-actions">
                    <button class="btn btn-sm" id="ccRefreshOnline">Obnovit</button>
                </div>
                <div id="ccOnlineTable">
                    <div class="loading">Načítání online uživatelů...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 5: EMAIL & SMS NOTIFIKACE -->
    <div class="cc-section" data-section="notifications">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Email & SMS notifikace</div>
                    <div class="cc-subtitle">Šablony emailů, SMS, automatické notifikace</div>
                </div>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <p style="color: var(--c-grey); margin-bottom: 1rem;">
                    <strong>INFO:</strong> Šablony emailů a SMS se spravují v samostatné sekci
                </p>
                <div class="cc-actions">
                    <a href="admin.php?tab=notifications" class="btn btn-sm">Otevřít notifikace</a>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 6: REKLAMACE -->
    <div class="cc-section" data-section="claims">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Správa reklamací</div>
                    <div class="cc-subtitle">Přehled všech servisních požadavků a reklamací</div>
                </div>
                <span class="cc-badge"><?= $totalClaims ?></span>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <div class="mini-stats">
                    <div class="mini-stat">
                        <div class="mini-stat-value" id="ccClaimsWait">-</div>
                        <div class="mini-stat-label">Čekající</div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-value" id="ccClaimsOpen">-</div>
                        <div class="mini-stat-label">Otevřené</div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-value" id="ccClaimsDone">-</div>
                        <div class="mini-stat-label">Dokončené</div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-value" id="ccClaimsTotal"><?= $totalClaims ?></div>
                        <div class="mini-stat-label">Celkem</div>
                    </div>
                </div>
                <div class="cc-actions">
                    <a href="seznam.php" class="btn btn-sm">Otevřít seznam reklamací</a>
                    <a href="novareklamace.php" class="btn btn-sm btn-success">+ Nová reklamace</a>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 7: VZHLED & DESIGN -->
    <div class="cc-section" data-section="appearance">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Vzhled & Design</div>
                    <div class="cc-subtitle">Barvy, fonty, logo, branding</div>
                </div>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <p style="color: var(--c-grey); margin-bottom: 1rem;">
                    <strong>COMING SOON:</strong> Editace barev, fontů a designu aplikace
                </p>
                <div class="cc-actions">
                    <button class="btn btn-sm" disabled>Upravit barvy</button>
                    <button class="btn btn-sm" disabled>Upravit fonty</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 8: OBSAH & TEXTY -->
    <div class="cc-section" data-section="content">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Obsah & Texty</div>
                    <div class="cc-subtitle">Editace textů na stránkách (CZ/EN/SK)</div>
                </div>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <p style="color: var(--c-grey); margin-bottom: 1rem;">
                    <strong>COMING SOON:</strong> Multi-jazyčný editor textů
                </p>
                <div class="cc-actions">
                    <button class="btn btn-sm" disabled>Upravit texty</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 9: KONFIGURACE SYSTÉMU -->
    <div class="cc-section" data-section="configuration">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Konfigurace systému</div>
                    <div class="cc-subtitle">SMTP, API klíče, bezpečnost, maintenance</div>
                </div>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <p style="color: var(--c-grey); margin-bottom: 1rem;">
                    <strong>COMING SOON:</strong> Kompletní systémová konfigurace
                </p>
                <div class="cc-actions">
                    <button class="btn btn-sm" disabled>SMTP nastavení</button>
                    <button class="btn btn-sm" disabled>API klíče</button>
                    <button class="btn btn-sm" disabled>Bezpečnost</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 10: DIAGNOSTIKA -->
    <div class="cc-section" data-section="diagnostics">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Diagnostika systému</div>
                    <div class="cc-subtitle">Zdraví systému, logy, údržba</div>
                </div>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <p style="color: var(--c-grey); margin-bottom: 1rem;">
                    <strong>COMING SOON:</strong> System health monitoring, logy, maintenance akce
                </p>
                <div class="cc-actions">
                    <button class="btn btn-sm" disabled>System Health</button>
                    <button class="btn btn-sm" disabled>Prohlížeč logů</button>
                    <button class="btn btn-sm btn-danger" disabled>Vymazat cache</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCE 11: AKCE & ÚKOLY -->
    <div class="cc-section" data-section="actions">
        <div class="cc-header">
            <div class="cc-title-wrapper">
                <div>
                    <div class="cc-title">Akce & Úkoly</div>
                    <div class="cc-subtitle">Nevyřešené úkoly, GitHub webhooks, plánované akce</div>
                </div>
                <?php if ($pendingActions > 0): ?>
                    <span class="cc-badge"><?= $pendingActions ?></span>
                <?php endif; ?>
            </div>
            <span class="cc-chevron">▼</span>
        </div>
        <div class="cc-body">
            <div class="cc-content">
                <div class="cc-actions">
                    <button class="btn btn-sm" id="ccRefreshActions">Obnovit</button>
                </div>
                <div id="ccActionsTable">
                    <div class="loading">Načítání úkolů...</div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// Accordion functionality
document.querySelectorAll('.cc-header').forEach(header => {
    header.addEventListener('click', function() {
        const section = this.closest('.cc-section');
        const isExpanded = section.classList.contains('expanded');

        // Collapse all other sections (optional - remove if you want multiple open)
        // document.querySelectorAll('.cc-section').forEach(s => s.classList.remove('expanded'));

        // Toggle current section
        if (isExpanded) {
            section.classList.remove('expanded');
        } else {
            section.classList.add('expanded');

            // Load data when section is opened
            const sectionName = section.dataset.section;
            loadSectionData(sectionName);
        }
    });
});

function loadSectionData(section) {
    switch(section) {
        case 'keys':
            loadKeys();
            break;
        case 'users':
            loadUsers();
            break;
        case 'online':
            loadOnlineUsers();
            break;
        case 'claims':
            loadClaimsStats();
            break;
        case 'actions':
            loadActions();
            break;
    }
}

// Load registration keys
function loadKeys() {
    const container = document.getElementById('ccKeysTable');
    if (!container) return;

    fetch('api/admin_api.php?action=list_keys')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.keys.length > 0) {
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
            } else {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádné klíče</p>';
            }
        })
        .catch(err => {
            container.innerHTML = '<p class="error-message">Chyba načítání: ' + err.message + '</p>';
        });
}

// Load users
function loadUsers() {
    const container = document.getElementById('ccUsersTable');
    if (!container) return;

    fetch('api/admin_api.php?action=list_users')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.users.length > 0) {
                let html = '<table class="cc-table"><thead><tr>';
                html += '<th>ID</th><th>Jméno</th><th>Email</th><th>Role</th><th>Status</th><th>Akce</th>';
                html += '</tr></thead><tbody>';

                data.users.forEach(user => {
                    html += '<tr>';
                    html += `<td>#${user.id}</td>`;
                    html += `<td>${user.full_name}</td>`;
                    html += `<td>${user.email}</td>`;
                    html += `<td><span class="badge badge-${user.role}">${user.role}</span></td>`;
                    html += `<td><span class="badge badge-${user.is_active ? 'active' : 'inactive'}">${user.is_active ? 'Aktivní' : 'Neaktivní'}</span></td>`;
                    html += `<td><button class="btn btn-sm">Upravit</button></td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádní uživatelé</p>';
            }
        });
}

// Load online users
function loadOnlineUsers() {
    const container = document.getElementById('ccOnlineTable');
    if (!container) return;

    fetch('api/admin_users_api.php?action=online')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.users.length > 0) {
                let html = '<table class="cc-table"><thead><tr>';
                html += '<th>Jméno</th><th>Role</th><th>Email</th><th>Poslední aktivita</th>';
                html += '</tr></thead><tbody>';

                data.users.forEach(user => {
                    html += '<tr>';
                    html += `<td><span class="online-indicator"></span>${user.full_name}</td>`;
                    html += `<td><span class="badge badge-${user.role}">${user.role}</span></td>`;
                    html += `<td>${user.email}</td>`;
                    html += `<td>${new Date(user.last_activity).toLocaleString('cs-CZ')}</td>`;
                    html += '</tr>';
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">Žádní online uživatelé</p>';
            }
        });
}

// Load claims stats
function loadClaimsStats() {
    fetch('api/admin_api.php?action=list_reklamace')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const claims = data.reklamace;
                const wait = claims.filter(c => c.stav === 'ČEKÁ').length;
                const open = claims.filter(c => c.stav === 'DOMLUVENÁ').length;
                const done = claims.filter(c => c.stav === 'HOTOVO').length;

                document.getElementById('ccClaimsWait').textContent = wait;
                document.getElementById('ccClaimsOpen').textContent = open;
                document.getElementById('ccClaimsDone').textContent = done;
            }
        });
}

// Load pending actions
function loadActions() {
    const container = document.getElementById('ccActionsTable');
    if (!container) return;

    fetch('api/control_center_api.php?action=get_pending_actions')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.actions.length > 0) {
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
            } else {
                container.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 2rem;">✅ Žádné nevyřízené úkoly</p>';
            }
        });
}

// Action handlers
function deleteKey(keyId) {
    if (!confirm('Opravdu chcete smazat tento klíč?')) return;

    fetch('api/admin_api.php?action=delete_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key_id: keyId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadKeys();
        } else {
            alert('Chyba: ' + data.message);
        }
    });
}

function completeAction(actionId) {
    fetch('api/control_center_api.php?action=complete_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action_id: actionId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadActions();
            // Refresh badge count
            location.reload();
        } else {
            alert('Chyba: ' + data.message);
        }
    });
}

function dismissAction(actionId) {
    fetch('api/control_center_api.php?action=dismiss_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action_id: actionId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadActions();
            location.reload();
        } else {
            alert('Chyba: ' + data.message);
        }
    });
}

// Button handlers
document.getElementById('ccCreateKey')?.addEventListener('click', () => {
    window.location.href = 'admin.php?tab=keys';
});

document.getElementById('ccRefreshKeys')?.addEventListener('click', loadKeys);
document.getElementById('ccRefreshUsers')?.addEventListener('click', loadUsers);
document.getElementById('ccRefreshOnline')?.addEventListener('click', loadOnlineUsers);
document.getElementById('ccRefreshActions')?.addEventListener('click', loadActions);

document.getElementById('ccAddUser')?.addEventListener('click', () => {
    window.location.href = 'admin.php?tab=users';
});
</script>
