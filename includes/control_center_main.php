<?php
/**
 * Admin Control Center - Hlavní přehled
 * iOS-style centrální řídicí panel
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

// Získání počtu pending actions
$pdo = getDbConnection();
$pendingCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_pending_actions WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $result['count'];
} catch (PDOException $e) {
    // Tabulka ještě neexistuje
}

// Počet uživatelů
$userCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userCount = $result['count'];
} catch (PDOException $e) {}

// Počet aktivních notifikací
$notificationCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_notifications WHERE active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notificationCount = $result['count'];
} catch (PDOException $e) {}

// System health status
$systemStatus = 'healthy';
try {
    // Test DB connection
    $pdo->query("SELECT 1");
    // Test file permissions
    if (!is_writable(__DIR__ . '/../logs')) {
        $systemStatus = 'warning';
    }
} catch (Exception $e) {
    $systemStatus = 'error';
}
?>

<link rel="stylesheet" href="/assets/css/control-center.css">

<div class="control-center-container">
    <!-- Header -->
    <div class="control-center-header">
        <h1 class="control-center-title">⚙️ Control Center</h1>
        <p class="control-center-subtitle">Centrální řídicí panel pro kompletní správu aplikace</p>
    </div>

    <!-- Search -->
    <div class="control-center-search">
        <input type="text"
               id="cc-search"
               placeholder="Hledat nastavení..."
               autocomplete="off">
    </div>

    <!-- Card Grid -->
    <div class="control-center-grid" id="cc-grid">

        <!-- 1. VZHLED & DESIGN -->
        <div class="control-card" data-section="appearance" onclick="openSection('appearance')">
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">🎨</div>
                    <h3 class="control-card-title">Vzhled & Design</h3>
                    <p class="control-card-description">Barvy, fonty, logo a branding</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot green"></span>
                <span>Aktivní</span>
            </div>
        </div>

        <!-- 2. OBSAH & TEXTY -->
        <div class="control-card" data-section="content" onclick="openSection('content')">
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">📝</div>
                    <h3 class="control-card-title">Obsah & Texty</h3>
                    <p class="control-card-description">Upravit texty na stránkách</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot green"></span>
                <span>Editovatelné</span>
            </div>
        </div>

        <!-- 3. UŽIVATELÉ & OPRÁVNĚNÍ -->
        <div class="control-card" data-section="users" onclick="openSection('users')">
            <?php if ($userCount > 0): ?>
                <div class="control-card-badge"><?= $userCount ?></div>
            <?php endif; ?>
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">👥</div>
                    <h3 class="control-card-title">Uživatelé & Oprávnění</h3>
                    <p class="control-card-description">Technici, prodejci, administrátoři</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot green"></span>
                <span><?= $userCount ?> uživatelů</span>
            </div>
        </div>

        <!-- 4. NOTIFIKACE -->
        <div class="control-card" data-section="notifications" onclick="openSection('notifications')">
            <?php if ($notificationCount > 0): ?>
                <div class="control-card-badge success"><?= $notificationCount ?></div>
            <?php endif; ?>
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">📧</div>
                    <h3 class="control-card-title">Notifikace</h3>
                    <p class="control-card-description">Email & SMS šablony</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot green"></span>
                <span><?= $notificationCount ?> aktivních</span>
            </div>
        </div>

        <!-- 5. KONFIGURACE -->
        <div class="control-card" data-section="configuration" onclick="openSection('configuration')">
            <div class="control-card-badge warning">⚠️</div>
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">⚙️</div>
                    <h3 class="control-card-title">Konfigurace</h3>
                    <p class="control-card-description">SMTP, API klíče, databáze</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot yellow"></span>
                <span>Vyžaduje restart</span>
            </div>
        </div>

        <!-- 6. DIAGNOSTIKA -->
        <div class="control-card" data-section="diagnostics" onclick="openSection('diagnostics')">
            <div class="control-card-badge <?= $systemStatus === 'healthy' ? 'success' : ($systemStatus === 'warning' ? 'warning' : '') ?>">
                <?= $systemStatus === 'healthy' ? '✓' : '!' ?>
            </div>
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">🏥</div>
                    <h3 class="control-card-title">Diagnostika</h3>
                    <p class="control-card-description">Logy, chyby, výkon systému</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot <?= $systemStatus === 'healthy' ? 'green' : ($systemStatus === 'warning' ? 'yellow' : 'red') ?>"></span>
                <span><?= ucfirst($systemStatus) ?></span>
            </div>
        </div>

        <!-- 7. AKCE & ÚKOLY -->
        <div class="control-card" data-section="actions" onclick="openSection('actions')">
            <?php if ($pendingCount > 0): ?>
                <div class="control-card-badge"><?= $pendingCount ?></div>
            <?php endif; ?>
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">🚀</div>
                    <h3 class="control-card-title">Akce & Úkoly</h3>
                    <p class="control-card-description">GitHub, migrace, pending tasks</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot <?= $pendingCount > 0 ? 'red' : 'green' ?>"></span>
                <span><?= $pendingCount ?> nevyřešených</span>
            </div>
        </div>

        <!-- 8. KONZOLE -->
        <div class="control-card" data-section="console" onclick="openSection('console')">
            <div class="control-card-badge warning">⚡</div>
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">💻</div>
                    <h3 class="control-card-title">Konzole</h3>
                    <p class="control-card-description">Diagnostika HTML/PHP/JS/CSS/SQL</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot blue"></span>
                <span>Developer Tools</span>
            </div>
        </div>

        <!-- 9. STATISTIKY & REPORTY -->
        <div class="control-card" data-section="analytics" onclick="openSection('analytics')">
            <div class="control-card-header">
                <div>
                    <div class="control-card-icon">📊</div>
                    <h3 class="control-card-title">Statistiky & Reporty</h3>
                    <p class="control-card-description">Dashboard, grafy, exporty</p>
                </div>
                <div class="control-card-arrow">›</div>
            </div>
            <div class="control-card-status">
                <span class="control-card-status-dot green"></span>
                <span>Real-time data</span>
            </div>
        </div>

    </div>

    <!-- Info Box -->
    <div class="cc-alert info">
        <div class="cc-alert-icon">💡</div>
        <div class="cc-alert-content">
            <div class="cc-alert-title">Tip:</div>
            <div class="cc-alert-message">
                Použijte vyhledávání výše pro rychlý přístup k nastavení.
                Všechny změny se ukládají okamžitě a jsou verzovány v audit logu.
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('cc-search').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.control-card');

    cards.forEach(card => {
        const title = card.querySelector('.control-card-title').textContent.toLowerCase();
        const description = card.querySelector('.control-card-description').textContent.toLowerCase();

        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});

// Section navigation
/**
 * OpenSection
 */
function openSection(section) {
    // Open modal with section content
    if (typeof openCCModal === 'function') {
        openCCModal(section);
    } else {
        // Fallback to redirect if openCCModal not available
        window.location.href = `admin.php?tab=control_center&section=${section}`;
    }
}

console.log('✅ Admin Control Center loaded');
</script>
