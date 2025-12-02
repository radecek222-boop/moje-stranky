<?php
/**
 * E2E Workflow Simulator
 * Interaktivní simulace celého workflow s diagnostikou
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';
?>

<style>
.testing-simulator {
    max-width: 1200px;
    margin: <?= $embedMode ? '0' : '2rem' ?> auto;
    padding: <?= $embedMode ? '0.5rem' : '1.5rem' ?>;
}

/* Progress visualization */
.workflow-progress {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 0.5rem;
    margin-bottom: <?= $embedMode ? '1rem' : '2rem' ?>;
    padding: <?= $embedMode ? '0.75rem' : '1rem' ?>;
    background: var(--c-bg);
    border: 2px solid var(--c-border);
    border-radius: 4px;
}

.progress-step {
    text-align: center;
    padding: 0.75rem 0.5rem;
    border-radius: 4px;
    background: white;
    border: 2px solid var(--c-border);
    position: relative;
    transition: all 0.3s;
}

.progress-step::after {
    content: '→';
    position: absolute;
    right: -12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.2rem;
    color: var(--c-border);
    z-index: 1;
}

.progress-step:last-child::after {
    display: none;
}

.progress-step.pending {
    opacity: 0.4;
}

.progress-step.active {
    background: #e3f2fd;
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

.progress-step.completed {
    background: #e8f5e9;
    border-color: var(--c-success);
}

.progress-step.error {
    background: #ffebee;
    border-color: var(--c-error);
    animation: shake 0.5s;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.progress-step-number {
    font-size: 0.7rem;
    color: var(--c-grey);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.25rem;
}

.progress-step-label {
    font-size: 0.8rem;
    font-weight: 600;
}

.progress-step-icon {
    font-size: 1.5rem;
    margin-top: 0.25rem;
}

/* Test stages */
.test-stage {
    background: white;
    border: 2px solid var(--c-border);
    border-radius: 4px;
    padding: <?= $embedMode ? '0.75rem' : '1.5rem' ?>;
    margin-bottom: <?= $embedMode ? '0.75rem' : '1.5rem' ?>;
    display: none;
}

.test-stage.active {
    display: block;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stage-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--c-border);
}

.stage-title {
    font-size: <?= $embedMode ? '0.9rem' : '1.2rem' ?>;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stage-description {
    font-size: 0.9rem;
    color: var(--c-grey);
    margin-top: 0.5rem;
}

/* Compact form */
.test-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.test-form .form-group {
    margin-bottom: 0;
}

.test-form .form-group.full-width {
    grid-column: 1 / -1;
}

.test-form input,
.test-form textarea {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--c-border);
    border-radius: 4px;
    font-size: 0.9rem;
}

.test-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Mini preview */
.mini-preview {
    border: 3px solid var(--c-success);
    border-radius: 4px;
    background: white;
    overflow: hidden;
    height: 400px;
    position: relative;
}

.mini-preview-header {
    background: var(--c-success);
    color: white;
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    text-align: center;
}

.mini-preview-body {
    padding: 1rem;
    height: calc(100% - 40px);
    overflow-y: auto;
}

.mini-preview iframe {
    width: 100%;
    height: 100%;
    border: none;
}

/* Diagnostics panel */
.diagnostics-panel {
    background: #1a1a1a;
    color: #00ff00;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    padding: 1rem;
    border-radius: 4px;
    max-height: 300px;
    overflow-y: auto;
    margin-top: 1rem;
}

.diagnostic-line {
    padding: 0.25rem 0;
    border-bottom: 1px solid #333;
}

.diagnostic-line.success {
    color: #00ff00;
}

.diagnostic-line.error {
    color: #ff0000;
    font-weight: 700;
}

.diagnostic-line.warning {
    color: #ffaa00;
}

.diagnostic-line.info {
    color: #00aaff;
}

.diagnostic-timestamp {
    color: #666;
    margin-right: 0.5rem;
}

/* Test controls */
.test-controls {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid var(--c-border);
}

/* Error summary */
.error-summary {
    background: #ffebee;
    border: 3px solid var(--c-error);
    border-radius: 4px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.error-summary-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--c-error);
    margin-bottom: 0.5rem;
}

.error-details {
    font-size: 0.9rem;
    color: #666;
    margin-top: 0.5rem;
}

/* Success summary */
.success-summary {
    background: #e8f5e9;
    border: 3px solid var(--c-success);
    border-radius: 4px;
    padding: 2rem;
    text-align: center;
}

.success-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.success-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--c-success);
    margin-bottom: 0.5rem;
}

/* Loading indicator */
.test-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: var(--c-grey);
}

.test-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--c-border);
    border-top-color: var(--c-success);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="testing-simulator">
    <?php if (!$embedMode): ?>
    <h1 class="page-title">E2E Workflow Simulator</h1>
    <p class="page-subtitle">Interaktivní simulace celého workflow s pokročilou diagnostikou</p>
    <?php endif; ?>

    <!-- Progress Visualization -->
    <div class="workflow-progress" id="workflowProgress">
        <div class="progress-step active" data-step="1">
            <div class="progress-step-number">Krok 1</div>
            <div class="progress-step-label">Formulář</div>
        </div>
        <div class="progress-step pending" data-step="2">
            <div class="progress-step-number">Krok 2</div>
            <div class="progress-step-label">API Call</div>
        </div>
        <div class="progress-step pending" data-step="3">
            <div class="progress-step-number">Krok 3</div>
            <div class="progress-step-label">Seznam</div>
        </div>
        <div class="progress-step pending" data-step="4">
            <div class="progress-step-number">Krok 4</div>
            <div class="progress-step-label">Detail</div>
        </div>
        <div class="progress-step pending" data-step="5">
            <div class="progress-step-number">Krok 5</div>
            <div class="progress-step-label">Protokol</div>
        </div>
        <div class="progress-step pending" data-step="6">
            <div class="progress-step-number">Krok 6</div>
            <div class="progress-step-label">Výsledek</div>
        </div>
    </div>

    <!-- Stage 1: Form -->
    <div class="test-stage active" id="stage1">
        <div class="stage-header">
            <div>
                <div class="stage-title">Krok 1: Vytvoření testovací reklamace</div>
                <div class="stage-description">Vyplňte základní údaje pro test workflow</div>
            </div>
        </div>

        <form id="testForm" class="test-form">
            <div class="form-group">
                <label for="sim-jmeno">Jméno zákazníka *</label>
                <input type="text" id="sim-jmeno" name="jmeno" value="Test Uživatel" required>
            </div>

            <div class="form-group">
                <label for="sim-email">Email *</label>
                <input type="email" id="sim-email" name="email" value="test@example.com" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="sim-telefon">Telefon *</label>
                <input type="tel" id="sim-telefon" name="telefon" value="+420 123 456 789" autocomplete="tel" required>
            </div>

            <div class="form-group">
                <label for="sim-photo">Fotografie (volitelné)</label>
                <input type="file" id="sim-photo" name="photo" accept="image/*">
            </div>

            <div class="form-group full-width">
                <label for="sim-popis">Popis problému *</label>
                <textarea id="sim-popis" name="popis" rows="3" required>Test popis - simulace workflow</textarea>
            </div>
        </form>

        <div class="test-controls">
            <button class="btn btn-success" onclick="startTest()">
                Spustit test workflow
            </button>
            <button class="btn btn-secondary" onclick="resetSimulator()">
                Reset
            </button>
        </div>

        <!-- Diagnostics -->
        <div class="diagnostics-panel" id="diagnosticsPanel" style="display: none;">
            <div style="color: #00ff00; font-weight: 700; margin-bottom: 0.5rem;">
                === DIAGNOSTIKA ===
            </div>
            <div id="diagnosticLines"></div>
        </div>
    </div>

    <!-- Stage 2: API Test -->
    <div class="test-stage" id="stage2">
        <div class="stage-header">
            <div>
                <div class="stage-title">Krok 2: API Volání</div>
                <div class="stage-description">Testování odeslání dat na server</div>
            </div>
        </div>

        <div class="test-loading" id="apiLoading">
            <div class="test-spinner" aria-hidden="true"></div>
            <div>Odesílám data na server...</div>
        </div>

        <div id="apiResult" style="display: none;"></div>

        <div class="diagnostics-panel" id="diagnosticsPanel2"></div>
    </div>

    <!-- Stage 3: Seznam Preview -->
    <div class="test-stage" id="stage3">
        <div class="stage-header">
            <div>
                <div class="stage-title">Krok 3: Seznam reklamací</div>
                <div class="stage-description">Ověření zobrazení v seznamu</div>
            </div>
        </div>

        <div class="mini-preview">
            <div class="mini-preview-header">NÁHLED: seznam.php</div>
            <div class="mini-preview-body">
                <iframe id="seznamPreview" src="" title="Náhled seznamu reklamací"></iframe>
            </div>
        </div>

        <div class="test-controls">
            <button class="btn btn-success" onclick="goToStage(4)">
                Reklamace nalezena - Pokračovat
            </button>
            <button class="btn btn-secondary" onclick="flagError(3, 'Reklamace se nezobrazuje v seznamu')">
                Reklamace chybí
            </button>
        </div>

        <div class="diagnostics-panel" id="diagnosticsPanel3"></div>
    </div>

    <!-- Stage 4: Detail Preview -->
    <div class="test-stage" id="stage4">
        <div class="stage-header">
            <div>
                <div class="stage-title">Krok 4: Detail reklamace</div>
                <div class="stage-description">Kontrola detailních údajů</div>
            </div>
        </div>

        <div class="mini-preview">
            <div class="mini-preview-header">NÁHLED: protokol.php</div>
            <div class="mini-preview-body">
                <iframe id="detailPreview" src="" title="Náhled detailu reklamace"></iframe>
            </div>
        </div>

        <div class="test-controls">
            <button class="btn btn-success" onclick="goToStage(5)">
                Detail v pořádku - Pokračovat
            </button>
            <button class="btn btn-secondary" onclick="flagError(4, 'Detail se nenačetl správně')">
                Chyba v detailu
            </button>
        </div>

        <div class="diagnostics-panel" id="diagnosticsPanel4"></div>
    </div>

    <!-- Stage 5: Protokol Check -->
    <div class="test-stage" id="stage5">
        <div class="stage-header">
            <div>
                <div class="stage-title">Krok 5: Protokol a akce</div>
                <div class="stage-description">Ověření protokolu změn</div>
            </div>
        </div>

        <div id="protocolCheck">
            <div class="test-loading">
                <div class="test-spinner" aria-hidden="true"></div>
                <div>Kontroluji protokol...</div>
            </div>
        </div>

        <div class="diagnostics-panel" id="diagnosticsPanel5"></div>
    </div>

    <!-- Stage 6: Final Result -->
    <div class="test-stage" id="stage6">
        <div id="finalResult"></div>

        <div class="diagnostics-panel" id="diagnosticsPanel6"></div>

        <div class="test-controls">
            <button class="btn btn-success" onclick="cleanupTest()">
                Smazat testovací data
            </button>
            <button class="btn btn-secondary" onclick="resetSimulator()">
                Nový test
            </button>
        </div>
    </div>
</div>

<script>
// Test state
let testState = {
    currentStage: 1,
    claimId: null,
    reklamaceId: null,
    errors: [],
    diagnostics: [],
    startTime: null
};

/**
 * AddDiagnostic
 */
function addDiagnostic(message, type = 'info', panelId = null) {
    const timestamp = new Date().toLocaleTimeString('cs-CZ');
    const diagnostic = {
        time: timestamp,
        message: message,
        type: type
    };
    testState.diagnostics.push(diagnostic);

    const panels = panelId ? [panelId] : ['diagnosticsPanel', 'diagnosticsPanel2', 'diagnosticsPanel3', 'diagnosticsPanel4', 'diagnosticsPanel5', 'diagnosticsPanel6'];

    panels.forEach(id => {
        const panel = document.getElementById(id);
        if (panel) {
            const line = document.createElement('div');
            line.className = `diagnostic-line ${type}`;
            line.innerHTML = `<span class="diagnostic-timestamp">[${timestamp}]</span>${message}`;
            panel.appendChild(line);
            panel.scrollTop = panel.scrollHeight;
        }
    });
}

/**
 * UpdateProgress
 */
function updateProgress(step, status) {
    const progressStep = document.querySelector(`.progress-step[data-step="${step}"]`);
    if (progressStep) {
        progressStep.className = `progress-step ${status}`;
    }
}

/**
 * StartTest
 */
async function startTest() {
    testState.startTime = Date.now();
    testState.errors = [];
    testState.diagnostics = [];

    // Show diagnostics
    document.getElementById('diagnosticsPanel').style.display = 'block';

    addDiagnostic('=== START E2E WORKFLOW TEST ===', 'success');
    addDiagnostic('Validace formuláře...', 'info');

    const form = document.getElementById('testForm');
    const formData = new FormData(form);

    // Validate
    const jmeno = formData.get('jmeno');
    const email = formData.get('email');

    if (!jmeno || !email) {
        flagError(1, 'Chybí povinná pole');
        return;
    }

    addDiagnostic('Validace OK', 'success');
    addDiagnostic('Přechod na Stage 2: API Call...', 'info');

    updateProgress(1, 'completed');
    updateProgress(2, 'active');

    setTimeout(() => {
        goToStage(2);
        sendAPIRequest(formData);
    }, 500);
}

/**
 * SendAPIRequest
 */
async function sendAPIRequest(formData) {
    addDiagnostic('Volání API: api/create_test_claim.php', 'info', 'diagnosticsPanel2');

    try {
        const response = await fetch('api/create_test_claim.php', {
            method: 'POST',
            body: formData
        });

        addDiagnostic(`Response Status: ${response.status} ${response.statusText}`, 'info', 'diagnosticsPanel2');

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();
        addDiagnostic(`Response: ${JSON.stringify(result)}`, 'info', 'diagnosticsPanel2');

        if (!result.success) {
            throw new Error(result.error || 'Unknown error');
        }

        testState.claimId = result.claim_id;
        testState.reklamaceId = result.reklamace_id;

        addDiagnostic(`Reklamace vytvořena: ID=${result.claim_id}`, 'success', 'diagnosticsPanel2');

        // Show success
        document.getElementById('apiLoading').style.display = 'none';
        document.getElementById('apiResult').style.display = 'block';
        document.getElementById('apiResult').innerHTML = `
            <div class="success-summary">
                <div class="success-title">API Call úspěšný!</div>
                <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--c-grey);">
                    <strong>Reklamace ID:</strong> ${result.reklamace_id}<br>
                    <strong>Database ID:</strong> ${result.claim_id}
                </div>
            </div>
        `;

        updateProgress(2, 'completed');
        updateProgress(3, 'active');

        addDiagnostic('Přechod na Stage 3: Seznam...', 'info', 'diagnosticsPanel2');

        setTimeout(() => {
            goToStage(3);
            loadSeznamPreview();
        }, 2000);

    } catch (error) {
        addDiagnostic(`ERROR: ${error.message}`, 'error', 'diagnosticsPanel2');
        flagError(2, `API Error: ${error.message}`);
    }
}

/**
 * LoadSeznamPreview
 */
function loadSeznamPreview() {
    const iframe = document.getElementById('seznamPreview');
    iframe.src = 'seznam.php?embed=1';
    addDiagnostic('Načítám seznam.php...', 'info', 'diagnosticsPanel3');
    addDiagnostic(`Hledám reklamaci ID: ${testState.reklamaceId}`, 'info', 'diagnosticsPanel3');
}

/**
 * GoToStage
 */
function goToStage(stageNumber) {
    // Hide all stages
    document.querySelectorAll('.test-stage').forEach(stage => {
        stage.classList.remove('active');
    });

    // Show current stage
    document.getElementById(`stage${stageNumber}`).classList.add('active');
    testState.currentStage = stageNumber;

    if (stageNumber === 4) {
        loadDetailPreview();
    } else if (stageNumber === 5) {
        checkProtocol();
    }
}

/**
 * LoadDetailPreview
 */
function loadDetailPreview() {
    const iframe = document.getElementById('detailPreview');
    iframe.src = `protokol.php?id=${testState.claimId}&embed=1`;
    addDiagnostic('Načítám protokol.php...', 'info', 'diagnosticsPanel4');
    addDiagnostic(`ID reklamace: ${testState.claimId}`, 'info', 'diagnosticsPanel4');
}

/**
 * CheckProtocol
 */
async function checkProtocol() {
    addDiagnostic('Kontroluji protokol akcí...', 'info', 'diagnosticsPanel5');

    // Simulate protocol check
    setTimeout(() => {
        addDiagnostic('Protokol OK', 'success', 'diagnosticsPanel5');
        updateProgress(5, 'completed');
        updateProgress(6, 'active');

        goToStage(6);
        showFinalResult();
    }, 1500);
}

/**
 * ShowFinalResult
 */
function showFinalResult() {
    const duration = Math.round((Date.now() - testState.startTime) / 1000);
    const hasErrors = testState.errors.length > 0;

    let html = '';

    if (!hasErrors) {
        html = `
            <div class="success-summary">
                <div class="success-title">Test úspěšně dokončen!</div>
                <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--c-grey);">
                    Workflow prošel všemi ${document.querySelectorAll('.progress-step').length} kroky bez chyb<br>
                    Čas testu: ${duration}s<br>
                    Diagnostických zpráv: ${testState.diagnostics.length}
                </div>
            </div>
        `;
        updateProgress(6, 'completed');
        addDiagnostic('=== TEST COMPLETED SUCCESSFULLY ===', 'success', 'diagnosticsPanel6');
    } else {
        html = `
            <div class="error-summary">
                <div class="error-summary-title">Test selhal</div>
                <div>Nalezeno ${testState.errors.length} chyb(a)</div>
                <div class="error-details">
                    ${testState.errors.map(e => `<div>• Krok ${e.step}: ${e.message}</div>`).join('')}
                </div>
            </div>
        `;
        updateProgress(6, 'error');
        addDiagnostic('=== TEST FAILED ===', 'error', 'diagnosticsPanel6');
    }

    document.getElementById('finalResult').innerHTML = html;
}

/**
 * FlagError
 */
function flagError(step, message) {
    testState.errors.push({ step, message });
    updateProgress(step, 'error');
    addDiagnostic(`ERROR at Step ${step}: ${message}`, 'error');

    goToStage(6);
    showFinalResult();
}

/**
 * CleanupTest
 */
async function cleanupTest() {
    if (!confirm('Smazat testovací data?')) return;

    addDiagnostic('Odstraňuji testovací data...', 'info', 'diagnosticsPanel6');

    try {
        const response = await fetch('api/test_cleanup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ testClaimId: testState.claimId })
        });

        const result = await response.json();

        if (result.success) {
            addDiagnostic('Test data smazána', 'success', 'diagnosticsPanel6');
            alert('Test data smazána!');
        } else {
            addDiagnostic(`Cleanup error: ${result.error}`, 'error', 'diagnosticsPanel6');
            alert('Chyba: ' + result.error);
        }
    } catch (error) {
        addDiagnostic(`Cleanup failed: ${error.message}`, 'error', 'diagnosticsPanel6');
        alert('Chyba: ' + error.message);
    }
}

/**
 * ResetSimulator
 */
function resetSimulator() {
    location.reload();
}
</script>
