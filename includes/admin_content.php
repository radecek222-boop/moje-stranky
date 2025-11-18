<?php
/**
 * Control Center - Obsah & Texty
 * Editace textů na stránkách (CZ/EN/SK)
 */

require_once __DIR__ . '/../init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// If embed mode, output full HTML structure
if ($embedMode):
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obsah - WGS Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="embed-mode">
<?php
endif;

// Načtení všech textů
$contentTexts = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM wgs_content_texts
        ORDER BY page, section, text_key
    ");
    $contentTexts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contentTexts = [];
}

// Seskupení podle stránek
$pages = [];
foreach ($contentTexts as $text) {
    $page = $text['page'];
    if (!isset($pages[$page])) {
        $pages[$page] = [];
    }
    $pages[$page][] = $text;
}

// Překlad názvů stránek
$pageNames = [
    'index' => 'Úvodní stránka',
    'novareklamace' => 'Nová reklamace',
    'mimozarucniceny' => 'Kalkulačka ceny',
    'onas' => 'O nás',
    'nasesluzby' => 'Naše služby',
    'email' => 'Email šablony',
    'gdpr' => 'GDPR'
];
?>

<?php if (!$embedMode): ?>
<link rel="stylesheet" href="/assets/css/admin.css">
<?php endif; ?>

<div class="control-detail active">
    <?php if (!$embedMode): ?>
    <!-- Header -->
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">Obsah & Texty</h2>
    </div>
    <?php endif; ?>

    <div class="control-detail-content">

        <!-- Alert -->
        <div class="cc-alert info">
            <div class="cc-alert-content">
                <div class="cc-alert-title">Editace obsahu</div>
                <div class="cc-alert-message">
                    Upravte texty na stránkách ve třech jazycích: Čeština, Angličtina, Slovenština.
                    Změny se projeví okamžitě po uložení.
                </div>
            </div>
        </div>

        <?php if (empty($pages)): ?>
            <!-- Prázdný stav -->
            <div class="cc-alert warning">
                <div class="cc-alert-content">
                    <div class="cc-alert-title">Žádné texty nenalezeny</div>
                    <div class="cc-alert-message">
                        Tabulka wgs_content_texts je prázdná. Spusťte migraci pro naimportování výchozích textů.
                    </div>
                </div>
            </div>
        <?php else: ?>

            <!-- Výběr stránky -->
            <div class="setting-group">
                <h3 class="setting-group-title">Vyberte stránku</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.5rem;">
                    <?php foreach ($pages as $page => $texts): ?>
                        <button class="cc-btn cc-btn-sm cc-btn-secondary page-selector"
                                data-page="<?= htmlspecialchars($page) ?>"
                                onclick="showPage('<?= htmlspecialchars($page) ?>')">
                            <?= $pageNames[$page] ?? ucfirst($page) ?>
                            <span style="background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 10px; margin-left: 0.5rem; font-size: 0.8rem;">
                                <?= count($texts) ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Stránky -->
            <?php foreach ($pages as $page => $texts): ?>
                <div class="page-content" id="page-<?= htmlspecialchars($page) ?>" style="display: none;">

                    <div class="setting-group">
                        <h3 class="setting-group-title">
                            <?= $pageNames[$page] ?? ucfirst($page) ?>
                        </h3>

                        <?php foreach ($texts as $text): ?>
                            <div class="setting-item" style="display: block; padding: 1.5rem;">
                                <div class="setting-item-label" style="margin-bottom: 1rem;">
                                    <strong><?= htmlspecialchars($text['section']) ?></strong> › <?= htmlspecialchars($text['text_key']) ?>
                                </div>

                                <!-- Taby pro jazyky -->
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--cc-border);">
                                    <button class="lang-tab active"
                                            data-text-id="<?= $text['id'] ?>"
                                            data-lang="cz"
                                            onclick="switchLang(<?= $text['id'] ?>, 'cz')">
                                        🇨🇿 Čeština
                                    </button>
                                    <button class="lang-tab"
                                            data-text-id="<?= $text['id'] ?>"
                                            data-lang="en"
                                            onclick="switchLang(<?= $text['id'] ?>, 'en')">
                                        🇬🇧 Angličtina
                                    </button>
                                    <button class="lang-tab"
                                            data-text-id="<?= $text['id'] ?>"
                                            data-lang="sk"
                                            onclick="switchLang(<?= $text['id'] ?>, 'sk')">
                                        🇸🇰 Slovenština
                                    </button>
                                </div>

                                <!-- Čeština -->
                                <div class="lang-content active" id="text-<?= $text['id'] ?>-cz">
                                    <textarea class="cc-input cc-textarea"
                                              id="input-<?= $text['id'] ?>-cz"
                                              rows="4"
                                              placeholder="České znění..."
                                              <?= !$text['editable'] ? 'disabled' : '' ?>><?= htmlspecialchars($text['value_cz']) ?></textarea>
                                </div>

                                <!-- Angličtina -->
                                <div class="lang-content" id="text-<?= $text['id'] ?>-en" style="display: none;">
                                    <textarea class="cc-input cc-textarea"
                                              id="input-<?= $text['id'] ?>-en"
                                              rows="4"
                                              placeholder="English text..."
                                              <?= !$text['editable'] ? 'disabled' : '' ?>><?= htmlspecialchars($text['value_en']) ?></textarea>
                                </div>

                                <!-- Slovenština -->
                                <div class="lang-content" id="text-<?= $text['id'] ?>-sk" style="display: none;">
                                    <textarea class="cc-input cc-textarea"
                                              id="input-<?= $text['id'] ?>-sk"
                                              rows="4"
                                              placeholder="Slovenský text..."
                                              <?= !$text['editable'] ? 'disabled' : '' ?>><?= htmlspecialchars($text['value_sk']) ?></textarea>
                                </div>

                                <?php if ($text['editable']): ?>
                                    <div style="margin-top: 1rem;">
                                        <button class="cc-btn cc-btn-sm cc-btn-primary"
                                                onclick="saveText(<?= $text['id'] ?>)">
                                            Uložit
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top: 1rem; color: #999; font-size: 0.85rem;">
                                        🔒 Tento text není editovatelný
                                    </div>
                                <?php endif; ?>

                                <div id="save-status-<?= $text['id'] ?>" style="margin-top: 0.5rem; display: none;"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>

<script src="/assets/js/csrf-auto-inject.js"></script>
<script>
// Debug mode - set to false in production
const DEBUG_MODE = false;

// Show page
/**
 * ShowPage
 */

require_once __DIR__ . '/../init.php';
function showPage(page) {
    // Hide all pages
    document.querySelectorAll('.page-content').forEach(el => {
        el.style.display = 'none';
    });

    // Deactivate all buttons
    document.querySelectorAll('.page-selector').forEach(btn => {
        btn.classList.remove('cc-btn-primary');
        btn.classList.add('cc-btn-secondary');
    });

    // Show selected page
    const pageEl = document.getElementById('page-' + page);
    if (pageEl) {
        pageEl.style.display = 'block';
    }

    // Activate button
    const btn = document.querySelector(`[data-page="${page}"]`);
    if (btn) {
        btn.classList.add('cc-btn-primary');
        btn.classList.remove('cc-btn-secondary');
    }
}

// Switch language tab
/**
 * SwitchLang
 */

require_once __DIR__ . '/../init.php';
function switchLang(textId, lang) {
    // Hide all lang contents for this text
    document.querySelectorAll(`[id^="text-${textId}-"]`).forEach(el => {
        el.style.display = 'none';
    });

    // Deactivate all tabs for this text
    document.querySelectorAll(`[data-text-id="${textId}"]`).forEach(tab => {
        tab.classList.remove('active');
    });

    // Show selected lang
    const contentEl = document.getElementById(`text-${textId}-${lang}`);
    if (contentEl) {
        contentEl.style.display = 'block';
    }

    // Activate tab
    const tab = document.querySelector(`[data-text-id="${textId}"][data-lang="${lang}"]`);
    if (tab) {
        tab.classList.add('active');
    }
}

// Save text
/**
 * SaveText
 */

require_once __DIR__ . '/../init.php';
async function saveText(textId) {
    const statusEl = document.getElementById(`save-status-${textId}`);
    statusEl.style.display = 'block';
    statusEl.innerHTML = '<span class="cc-loading"></span> Ukládám...';

    try {
        const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() : null;

        const valueCz = document.getElementById(`input-${textId}-cz`).value;
        const valueEn = document.getElementById(`input-${textId}-en`).value;
        const valueSk = document.getElementById(`input-${textId}-sk`).value;

        const response = await fetch('/api/control_center_api.php?action=save_content_text', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: textId,
                value_cz: valueCz,
                value_en: valueEn,
                value_sk: valueSk,
                csrf_token: csrfToken
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            statusEl.innerHTML = '<span style="color: #28A745;">Uloženo!</span>';
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 2000);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        statusEl.innerHTML = '<span style="color: #DC3545;">❌ Chyba: ' + error.message + '</span>';
    }
}

// Show first page on load
document.addEventListener('DOMContentLoaded', () => {
    const firstPage = document.querySelector('.page-selector');
    if (firstPage) {
        firstPage.click();
    }
});

if (DEBUG_MODE) console.log('[OK] Content section loaded');
</script>

<style>
.lang-tab {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--cc-text-secondary);
    transition: var(--cc-transition);
    border-bottom: 2px solid transparent;
}

.lang-tab:hover {
    color: var(--cc-text-primary);
}

.lang-tab.active {
    color: var(--cc-primary);
    border-bottom-color: var(--cc-primary);
}
</style>


<?php if ($embedMode): ?>
</body>
</html>
<?php endif; ?>
