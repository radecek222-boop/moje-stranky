<?php
/**
 * Control Center - Akce & Úkoly
 * Pending actions, GitHub webhooks, scheduled tasks
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

// Detect embed mode for iframe contexts
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Kontrola, zda je Admin Control Center nainstalovaný
$adminCenterInstalled = true;
try {
    $pdo->query("SELECT 1 FROM wgs_system_config LIMIT 1");
} catch (PDOException $e) {
    $adminCenterInstalled = false;
}

// Načtení pending actions
$pendingActions = [];
$tableExists = true;
try {
    $stmt = $pdo->query("
        SELECT * FROM wgs_pending_actions
        WHERE status IN ('pending', 'in_progress')
        ORDER BY
            FIELD(priority, 'critical', 'high', 'medium', 'low'),
            created_at DESC
    ");
    $pendingActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pendingActions = [];
    // Zkontrolovat, jestli tabulka existuje
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'Table') !== false) {
        $tableExists = false;
    }
}

// Počty podle priority
$criticalCount = count(array_filter($pendingActions, function($a) { return $a['priority'] === 'critical'; }));
$highCount = count(array_filter($pendingActions, function($a) { return $a['priority'] === 'high'; }));
$mediumCount = count(array_filter($pendingActions, function($a) { return $a['priority'] === 'medium'; }));
$lowCount = count(array_filter($pendingActions, function($a) { return $a['priority'] === 'low'; }));

// Recent completed actions
$completedActions = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM wgs_pending_actions
        WHERE status = 'completed'
        ORDER BY completed_at DESC
        LIMIT 10
    ");
    $completedActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $completedActions = [];
}

// GitHub webhooks (last 10)
$githubWebhooks = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM wgs_github_webhooks
        ORDER BY received_at DESC
        LIMIT 10
    ");
    $githubWebhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $githubWebhooks = [];
}

// Priority badge colors
function getPriorityBadge($priority) {
    $badges = [
        'critical' => ['color' => '#DC3545', 'icon' => '🔴', 'text' => 'Kritické'],
        'high' => ['color' => '#FF6B6B', 'icon' => '🟠', 'text' => 'Vysoká'],
        'medium' => ['color' => '#FFC107', 'icon' => '🟡', 'text' => 'Střední'],
        'low' => ['color' => '#28A745', 'icon' => '🟢', 'text' => 'Nízká']
    ];
    return $badges[$priority] ?? $badges['medium'];
}
?>

<link rel="stylesheet" href="/assets/css/control-center.css">

<div class="control-detail active">
    <!-- Header -->
    <?php if (!$embedMode): ?>
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php?tab=control_center'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">🚀 Akce & Úkoly</h2>
    </div>
    <?php endif; ?>

    <div class="control-detail-content" style="<?= $embedMode ? 'padding-top: 1rem;' : '' ?>">

        <!-- Admin Control Center Installation -->
        <?php if (!$adminCenterInstalled): ?>
            <div class="cc-alert danger">
                <div class="cc-alert-icon">🚀</div>
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Admin Control Center není nainstalován</div>
                    <div class="cc-alert-message">
                        Databázové tabulky pro Admin Control Center neexistují. Klikněte na tlačítko níže pro automatickou instalaci.
                        <br><small>Bude vytvořeno 6 tabulek: theme_settings, content_texts, system_config, pending_actions, action_history, github_webhooks</small>
                    </div>
                    <div style="margin-top: 1rem;">
                        <a href="/install_admin_control_center.php" class="cc-btn cc-btn-success" style="display: inline-block; text-decoration: none;">
                            🚀 Spustit instalaci Admin Control Center
                        </a>
                        <p style="font-size: 0.7rem; color: #666; margin-top: 0.5rem;">Po dokončení instalace se vrátíte zpět na tento panel</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Summary -->
        <?php if (!$tableExists): ?>
            <!-- Tabulka neexistuje - zobrazit setup button -->
            <div class="cc-alert warning">
                <div class="cc-alert-icon">⚠️</div>
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Actions System není nastavený</div>
                    <div class="cc-alert-message">
                        Tabulka <code>wgs_pending_actions</code> neexistuje. Klikněte na tlačítko níže pro automatické nastavení systému akcí a úkolů.
                    </div>
                    <div style="margin-top: 1rem;">
                        <button class="cc-btn cc-btn-success" onclick="window.open('/setup_actions_system.php', '_blank', 'width=900,height=700')">
                            🚀 Spustit setup Actions System
                        </button>
                    </div>
                </div>
            </div>
        <?php elseif (count($pendingActions) > 0): ?>
            <div class="cc-alert warning">
                <div class="cc-alert-icon">⚠️</div>
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Máte <?= count($pendingActions) ?> nevyřešených úkolů</div>
                    <div class="cc-alert-message">
                        <?php if ($criticalCount > 0): ?>
                            🔴 <?= $criticalCount ?> kritických |
                        <?php endif; ?>
                        <?php if ($highCount > 0): ?>
                            🟠 <?= $highCount ?> vysoká priorita |
                        <?php endif; ?>
                        <?= $mediumCount ?> střední | <?= $lowCount ?> nízká
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="cc-alert success">
                <div class="cc-alert-icon">✅</div>
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Žádné nevyřešené úkoly!</div>
                    <div class="cc-alert-message">Výborná práce! Všechny úkoly jsou dokončené.</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- PENDING ACTIONS -->
        <?php if (count($pendingActions) > 0): ?>
            <div class="setting-group">
                <h3 class="setting-group-title">Nevyřešené úkoly</h3>

                <?php foreach ($pendingActions as $action):
                    $badge = getPriorityBadge($action['priority']);
                ?>
                    <div class="setting-item" style="align-items: flex-start;">
                        <div class="setting-item-left">
                            <div class="setting-item-label">
                                <span style="font-size: 1.2rem; margin-right: 0.5rem;"><?= $badge['icon'] ?></span>
                                <?= htmlspecialchars($action['action_title']) ?>
                            </div>
                            <div class="setting-item-description">
                                <?= htmlspecialchars($action['action_description']) ?>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #666;">
                                <span style="background: <?= $badge['color'] ?>; color: white; padding: 2px 8px; border-radius: 12px; font-weight: 600;">
                                    <?= $badge['text'] ?>
                                </span>
                                <span style="margin-left: 1rem;">
                                    📅 <?= date('d.m.Y H:i', strtotime($action['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="setting-item-right" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button class="cc-btn cc-btn-sm cc-btn-primary"
                                    onclick="executeAction(event, <?= $action['id'] ?>)">
                                ▶️ Spustit
                            </button>
                            <button class="cc-btn cc-btn-sm cc-btn-success"
                                    onclick="completeAction(<?= $action['id'] ?>)">
                                ✓ Hotovo
                            </button>
                            <button class="cc-btn cc-btn-sm cc-btn-secondary"
                                    onclick="dismissAction(<?= $action['id'] ?>)">
                                ✕ Zrušit
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- COMPLETED ACTIONS -->
        <?php if (count($completedActions) > 0): ?>
            <div class="setting-group">
                <h3 class="setting-group-title">Nedávno dokončené</h3>

                <?php foreach ($completedActions as $action): ?>
                    <div class="setting-item">
                        <div class="setting-item-left">
                            <div class="setting-item-label" style="opacity: 0.7;">
                                ✅ <?= htmlspecialchars($action['action_title']) ?>
                            </div>
                            <div class="setting-item-description">
                                Dokončeno: <?= date('d.m.Y H:i', strtotime($action['completed_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- GITHUB WEBHOOKS -->
        <?php if (count($githubWebhooks) > 0): ?>
            <div class="setting-group">
                <h3 class="setting-group-title">GitHub Activity</h3>

                <?php foreach ($githubWebhooks as $webhook):
                    $payload = json_decode($webhook['payload'], true);
                ?>
                    <div class="setting-item">
                        <div class="setting-item-left">
                            <div class="setting-item-label">
                                🔗 <?= htmlspecialchars($webhook['event_type']) ?>
                            </div>
                            <div class="setting-item-description">
                                <strong><?= htmlspecialchars($webhook['repository']) ?></strong>
                                <?php if ($webhook['branch']): ?>
                                    › <?= htmlspecialchars($webhook['branch']) ?>
                                <?php endif; ?>
                                <?php if ($webhook['commit_message']): ?>
                                    <br><?= htmlspecialchars(substr($webhook['commit_message'], 0, 100)) ?>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #666;">
                                👤 <?= htmlspecialchars($webhook['author'] ?? 'Unknown') ?> •
                                📅 <?= date('d.m.Y H:i', strtotime($webhook['received_at'])) ?>
                            </div>
                        </div>
                        <div class="setting-item-right">
                            <div class="control-card-status">
                                <span class="control-card-status-dot <?= $webhook['processed'] ? 'green' : 'yellow' ?>"></span>
                                <span><?= $webhook['processed'] ? 'Zpracováno' : 'Čeká' ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="text-align: center; margin-top: 1rem;">
                    <button class="cc-btn cc-btn-sm cc-btn-secondary" onclick="viewAllWebhooks()">
                        Zobrazit všechny události
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="setting-group">
                <h3 class="setting-group-title">GitHub Webhooks</h3>
                <div class="setting-item">
                    <div class="setting-item-left">
                        <div class="setting-item-description">
                            Žádné nedávné GitHub události. Nastavte webhook v repozitáři.
                        </div>
                    </div>
                    <div class="setting-item-right">
                        <button class="cc-btn cc-btn-sm cc-btn-primary" onclick="setupGitHubWebhook()">
                            Nastavit
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SCHEDULED TASKS -->
        <div class="setting-group">
            <h3 class="setting-group-title">Naplánované úlohy (Cron)</h3>

            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">🧹 Session Cleanup</div>
                    <div class="setting-item-description">Vymazání starých sessions (každých 24 hodin)</div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot green"></span>
                        <span>Aktivní</span>
                    </div>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">📧 Email Reminders</div>
                    <div class="setting-item-description">Připomenutí termínů (denně v 8:00)</div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot green"></span>
                        <span>Aktivní</span>
                    </div>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">📊 Statistics Generation</div>
                    <div class="setting-item-description">Generování reportů (týdně)</div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot green"></span>
                        <span>Aktivní</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="setting-group">
            <h3 class="setting-group-title">Rychlé akce</h3>

            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">🔄 Obnovit seznam</div>
                    <div class="setting-item-description">Načíst aktuální stav</div>
                </div>
                <div class="setting-item-right">
                    <button class="cc-btn cc-btn-sm cc-btn-secondary" onclick="location.reload()">
                        Obnovit
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="/assets/js/csrf-auto-inject.js"></script>
<script>
// Debug mode - set to false in production
const DEBUG_MODE = false;

// Helper function to check API response success
function isSuccess(data) {
    return (data && (data.success === true || data.status === 'success'));
}

async function executeAction(event, actionId) {
    if (DEBUG_MODE) console.log('[executeAction] Starting with actionId:', actionId);

    // Capture button reference BEFORE any await
    const btn = event.target;
    const originalText = btn.textContent;

    // Await the CSRF token
    const csrfToken = await getCSRFToken();
    if (DEBUG_MODE) console.log('[executeAction] CSRF token retrieved');

    if (!csrfToken || typeof csrfToken !== 'string' || csrfToken.length === 0) {
        alert('Chyba: CSRF token nebyl nalezen nebo je neplatný. Obnovte stránku.');
        console.error('[executeAction] CSRF token is invalid');
        return;
    }

    if (!confirm('Spustit tuto akci? Bude provedena automaticky.')) {
        if (DEBUG_MODE) console.log('[executeAction] User cancelled');
        return;
    }

    // Disable button during execution
    btn.disabled = true;
    btn.textContent = 'Provádění...';

    const payload = {
        action_id: actionId,
        csrf_token: csrfToken
    };

    if (DEBUG_MODE) console.log('[executeAction] Sending request with payload:', payload);

    fetch('/api/control_center_api.php?action=execute_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(async r => {
        if (DEBUG_MODE) console.log('[executeAction] Response status:', r.status);

        // Try to parse JSON even on error
        let responseData;
        let rawResponse = '';
        try {
            rawResponse = await r.text(); // Get raw text first
            if (DEBUG_MODE) console.log('[executeAction] Raw response:', rawResponse);
            responseData = JSON.parse(rawResponse);
            if (DEBUG_MODE) console.log('[executeAction] Response data:', responseData);
        } catch (e) {
            console.error('[executeAction] Failed to parse JSON:', e);
            console.error('[executeAction] Raw response was:', rawResponse.substring(0, 500));
            responseData = null;
        }

        if (!r.ok) {
            let errorMsg = `HTTP ${r.status}`;
            if (responseData) {
                errorMsg = responseData.message || 'Unknown error';
                if (responseData.debug) {
                    errorMsg += '\n\n' + Object.entries(responseData.debug)
                        .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v, null, 2) : v}`)
                        .join('\n');
                }
            } else {
                errorMsg += '\n\nServer vrátil nevalidní odpověď. Zkontrolujte console pro detaily.';
            }
            throw new Error(errorMsg);
        }

        return responseData;
    })
    .then(data => {
        if (DEBUG_MODE) console.log('[executeAction] Success data:', data);

        if (!data) {
            throw new Error('API vrátilo prázdnou odpověď');
        }

        if (isSuccess(data)) {
            const execTime = data.execution_time || 'neznámý čas';
            alert(`✓ Akce dokončena!\n\n${data.message}\n\nČas provedení: ${execTime}`);
            location.reload();
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

async function completeAction(actionId) {
    if (!confirm('Označit tento úkol jako dokončený?')) {
        return;
    }

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() : null;

        const response = await fetch('/api/control_center_api.php?action=complete_action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action_id: actionId, csrf_token: csrfToken })
        });

        const result = await response.json();

        if (isSuccess(result)) {
            location.reload();
        } else {
            throw new Error(result.message || result.error || 'Neznámá chyba');
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    }
}

async function dismissAction(actionId) {
    if (!confirm('Opravdu chcete zrušit tento úkol?')) {
        return;
    }

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() : null;

        const response = await fetch('/api/control_center_api.php?action=dismiss_action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action_id: actionId, csrf_token: csrfToken })
        });

        const result = await response.json();

        if (isSuccess(result)) {
            location.reload();
        } else {
            throw new Error(result.message || result.error || 'Neznámá chyba');
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    }
}

function viewAllWebhooks() {
    window.open('/admin.php?tab=tools&view=github_webhooks', '_blank');
}

function setupGitHubWebhook() {
    alert('GitHub Webhook URL:\n\n' + window.location.origin + '/api/github_webhook.php\n\nPřidejte tuto URL do nastavení GitHub repozitáře.');
}

if (DEBUG_MODE) console.log('✅ Actions section loaded');
</script>
