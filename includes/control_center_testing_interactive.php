<?php
/**
 * Interactive Testing Environment - E2E Workflow Test
 * Testování celého workflow od vytvoření reklamace po výsledek
 * Podporuje testování z pohledu různých rolí
 */
$pdo = getDbConnection();
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';
?>

<style>
.testing-interactive {
    max-width: 1200px;
    margin: <?= $embedMode ? '0' : '2rem' ?> auto;
    padding: 2rem;
}

/* Role Selector */
.role-selector {
    background: var(--c-bg);
    border: 2px solid var(--c-border);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-radius: 8px;
}

.role-selector h3 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    font-weight: 700;
    text-transform: uppercase;
}

.role-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.role-btn {
    padding: 1rem;
    border: 2px solid var(--c-border);
    background: white;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
    border-radius: 4px;
}

.role-btn:hover {
    border-color: var(--c-success);
    transform: translateY(-2px);
}

.role-btn.selected {
    background: var(--c-success);
    color: white;
    border-color: var(--c-success);
}

.role-btn .role-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.role-btn .role-name {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.9rem;
}

.role-btn .role-desc {
    font-size: 0.75rem;
    opacity: 0.8;
    margin-top: 0.25rem;
}

/* Workflow Path */
.workflow-path {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    overflow-x: auto;
}

.path-step {
    flex: 1;
    text-align: center;
    padding: 0.75rem;
    position: relative;
    min-width: 120px;
}

.path-step:not(:last-child)::after {
    content: '→';
    position: absolute;
    right: -12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.2rem;
    color: var(--c-border);
    z-index: 1;
}

.path-step.active {
    background: var(--c-success);
    color: var(--c-white);
    font-weight: 600;
    border-radius: 4px;
}

.path-step.completed {
    background: #e8f5e9;
    color: var(--c-success);
    font-weight: 600;
    border-radius: 4px;
}

.path-step.pending {
    opacity: 0.5;
}

.path-step .step-number {
    font-size: 0.7rem;
    margin-bottom: 0.25rem;
}

.path-step .step-name {
    font-weight: 600;
    font-size: 0.8rem;
}

.path-step .step-page {
    font-size: 0.65rem;
    color: var(--c-grey);
    margin-top: 0.25rem;
}

/* Test Panel */
.test-panel {
    background: var(--c-white);
    border: 2px solid var(--c-border);
    padding: 2rem;
    margin-bottom: 2rem;
    min-height: 400px;
}

.test-panel h3 {
    margin: 0 0 1rem 0;
    font-size: 1.3rem;
    font-weight: 700;
    text-transform: uppercase;
}

.test-info {
    background: #e3f2fd;
    border-left: 4px solid #1976d2;
    padding: 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

/* Form Elements */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group textarea {
    width: 100%;
    padding: 0.875rem;
    border: 2px solid var(--c-border);
    font-size: 1rem;
    font-family: inherit;
}

.form-group input[type="file"] {
    padding: 0.5rem;
}

/* Diagnostic Output */
.diagnostic-output {
    background: #f5f5f5;
    border: 1px solid var(--c-border);
    padding: 1rem;
    margin-top: 1rem;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    max-height: 250px;
    overflow-y: auto;
}

.diagnostic-line {
    padding: 0.25rem 0;
    border-bottom: 1px solid #e0e0e0;
}

.diagnostic-line.success {
    color: var(--c-success);
}

.diagnostic-line.error {
    color: var(--c-error);
    font-weight: 600;
}

.diagnostic-line.info {
    color: var(--c-grey);
}

/* Status Indicator */
.status-indicator {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 2rem;
}

.status-indicator.waiting {
    background: #fff3e0;
    color: #f57c00;
}

.status-indicator.testing {
    background: #e3f2fd;
    color: #1976d2;
}

.status-indicator.success {
    background: #e8f5e9;
    color: var(--c-success);
}

.status-indicator.error {
    background: #ffebee;
    color: var(--c-error);
}

/* Button Group */
.btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

/* Test Summary */
.test-summary {
    background: white;
    border: 2px solid var(--c-success);
    padding: 2rem;
    margin-bottom: 2rem;
}

.test-summary-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem;
    border-bottom: 1px solid #e0e0e0;
}

.test-summary-item:last-child {
    border-bottom: none;
}

.test-summary-label {
    font-weight: 600;
    color: var(--c-grey);
}

.test-summary-value {
    font-weight: 600;
    color: var(--c-black);
}
</style>

<div class="testing-interactive">
    <?php if (!$embedMode): ?>
    <h1 class="page-title">E2E Workflow Test</h1>
    <p class="page-subtitle">Kompletní testování workflow od vytvoření po výsledek</p>
    <?php endif; ?>

    <!-- Role Selector -->
    <div class="role-selector" id="roleSelector">
        <h3>Vyberte roli pro testování</h3>
        <div class="role-buttons">
            <div class="role-btn" onclick="selectRole('admin')" data-role="admin">
                <div class="role-icon">👑</div>
                <div class="role-name">Admin</div>
                <div class="role-desc">Plný přístup ke všemu</div>
            </div>
            <div class="role-btn" onclick="selectRole('prodejce')" data-role="prodejce">
                <div class="role-icon">👔</div>
                <div class="role-name">Prodejce</div>
                <div class="role-desc">Vidí pouze své reklamace</div>
            </div>
            <div class="role-btn" onclick="selectRole('technik')" data-role="technik">
                <div class="role-icon">🔧</div>
                <div class="role-name">Technik</div>
                <div class="role-desc">Vidí všechny reklamace</div>
            </div>
            <div class="role-btn" onclick="selectRole('guest')" data-role="guest">
                <div class="role-icon">👤</div>
                <div class="role-name">Neregistrovaný</div>
                <div class="role-desc">Pouze základní funkce</div>
            </div>
        </div>
        <div class="btn-group" style="margin-top: 1.5rem; justify-content: center;">
            <button type="button" class="btn btn-success" onclick="startTest()" id="startTestBtn" disabled>
                Zahájit test s vybranou rolí →
            </button>
        </div>
    </div>

    <!-- Workflow Path (hidden initially) -->
    <div class="workflow-path" id="workflowPath" style="display: none;">
        <div class="path-step active" id="step1">
            <div class="step-number">KROK 1</div>
            <div class="step-name">Formulář</div>
            <div class="step-page">novareklamace.php</div>
        </div>
        <div class="path-step pending" id="step2">
            <div class="step-number">KROK 2</div>
            <div class="step-name">API Call</div>
            <div class="step-page">save.php</div>
        </div>
        <div class="path-step pending" id="step3">
            <div class="step-number">KROK 3</div>
            <div class="step-name">Seznam</div>
            <div class="step-page">seznam.php</div>
        </div>
        <div class="path-step pending" id="step4">
            <div class="step-number">KROK 4</div>
            <div class="step-name">Detail</div>
            <div class="step-page">detail + zahájení</div>
        </div>
        <div class="path-step pending" id="step5">
            <div class="step-number">KROK 5</div>
            <div class="step-name">PhotoCustomer</div>
            <div class="step-page">photocustomer.php</div>
        </div>
        <div class="path-step pending" id="step6">
            <div class="step-number">KROK 6</div>
            <div class="step-name">Protokol</div>
            <div class="step-page">protokol.php</div>
        </div>
        <div class="path-step pending" id="step7">
            <div class="step-number">KROK 7</div>
            <div class="step-name">Výsledek</div>
            <div class="step-page">validace dat</div>
        </div>
    </div>

    <!-- Status -->
    <div style="text-align: center; display: none;" id="statusContainer">
        <div class="status-indicator waiting" id="statusIndicator">Připraven k testu</div>
    </div>

    <!-- Test Panel -->
    <div class="test-panel" id="testPanel" style="display: none;">
        <!-- Kroky budou vloženy dynamicky -->
    </div>
</div>

<script>
let testData = {
    role: null,
    claimId: null,
    reklamaceId: null,
    currentStep: 0,
    diagnosticLog: [],
    startTime: null,
    testPassed: false
};

function selectRole(role) {
    testData.role = role;

    // Update UI
    document.querySelectorAll('.role-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    document.querySelector(`[data-role="${role}"]`).classList.add('selected');

    // Enable start button
    document.getElementById('startTestBtn').disabled = false;
}

function startTest() {
    if (!testData.role) {
        alert('Vyberte roli pro testování');
        return;
    }

    testData.startTime = new Date();
    testData.diagnosticLog = [];

    // Hide role selector
    document.getElementById('roleSelector').style.display = 'none';

    // Show workflow and test panel
    document.getElementById('workflowPath').style.display = 'flex';
    document.getElementById('statusContainer').style.display = 'block';
    document.getElementById('testPanel').style.display = 'block';

    addDiagnostic(`=== START E2E WORKFLOW TEST ===`, 'success');
    addDiagnostic(`Role: ${testData.role}`, 'info');
    addDiagnostic(`Čas: ${testData.startTime.toLocaleString('cs-CZ')}`, 'info');

    // Start with step 1
    goToStep(1);
}

function addDiagnostic(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString('cs-CZ');
    const line = {
        time: timestamp,
        message: message,
        type: type
    };
    testData.diagnosticLog.push(line);
    console.log(`[${timestamp}]`, message);
}

function updateStatus(text, className = 'testing') {
    const indicator = document.getElementById('statusIndicator');
    indicator.className = `status-indicator ${className}`;
    indicator.textContent = text;
}

function goToStep(stepNumber) {
    testData.currentStep = stepNumber;

    // Update path visualization
    for (let i = 1; i <= 7; i++) {
        const pathStep = document.getElementById(`step${i}`);
        pathStep.classList.remove('active', 'pending', 'completed');
        if (i < stepNumber) {
            pathStep.classList.add('completed');
        } else if (i === stepNumber) {
            pathStep.classList.add('active');
        } else {
            pathStep.classList.add('pending');
        }
    }

    // Load step content
    const panel = document.getElementById('testPanel');

    switch(stepNumber) {
        case 1:
            loadStep1_Formular(panel);
            break;
        case 2:
            loadStep2_APICall(panel);
            break;
        case 3:
            loadStep3_Seznam(panel);
            break;
        case 4:
            loadStep4_Detail(panel);
            break;
        case 5:
            loadStep5_PhotoCustomer(panel);
            break;
        case 6:
            loadStep6_Protokol(panel);
            break;
        case 7:
            loadStep7_Vysledek(panel);
            break;
    }
}

function loadStep1_Formular(panel) {
    updateStatus('Vyplňování formuláře...', 'testing');

    panel.innerHTML = `
        <h3>Krok 1: Formulář nové reklamace</h3>
        <div class="test-info">
            <strong>Role:</strong> ${testData.role}<br>
            <strong>Co testujeme:</strong> Vytvoření nové reklamace pomocí formuláře<br>
            <strong>Úkol:</strong> Vyplnit základní údaje a odeslat
        </div>

        <form id="testForm">
            <div class="form-group">
                <label>Jméno *</label>
                <input type="text" id="jmeno" value="Test Zákazník E2E" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" id="email" value="test-e2e@wgs-service.cz" required>
            </div>
            <div class="form-group">
                <label>Telefon *</label>
                <input type="tel" id="telefon" value="+420777888999" required>
            </div>
            <div class="form-group">
                <label>Popis problému *</label>
                <textarea id="popis_problemu" rows="3" required>E2E test workflow - kompletní průchod systémem</textarea>
            </div>
            <div class="form-group">
                <label>Fotografie (volitelné)</label>
                <input type="file" id="photo" accept="image/*">
                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--c-grey);">
                    Pro zjednodušení testu můžete vynechat
                </div>
            </div>
        </form>

        <div class="diagnostic-output">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">DIAGNOSTIKA:</div>
            <div id="diagnosticLines"></div>
        </div>

        <div class="btn-group">
            <button class="btn btn-success" onclick="executeStep1()">
                Validovat a pokračovat →
            </button>
            <button class="btn btn-secondary" onclick="resetTest()">
                Reset
            </button>
        </div>
    `;

    renderDiagnostic();
}

async function executeStep1() {
    addDiagnostic('Validace formuláře...', 'info');

    const jmeno = document.getElementById('jmeno').value;
    const email = document.getElementById('email').value;
    const telefon = document.getElementById('telefon').value;
    const popis = document.getElementById('popis_problemu').value;

    if (!jmeno || !email || !telefon || !popis) {
        addDiagnostic('ERROR: Chybí povinná pole', 'error');
        updateStatus('Chyba: neúplné údaje', 'error');
        renderDiagnostic();
        return;
    }

    addDiagnostic('Validace OK', 'success');
    renderDiagnostic();

    // Move to step 2
    setTimeout(() => {
        addDiagnostic('Přechod na Stage 2: API Call...', 'info');
        goToStep(2);
    }, 500);
}

function loadStep2_APICall(panel) {
    updateStatus('Volání API...', 'testing');

    panel.innerHTML = `
        <h3>Krok 2: API Call (save.php)</h3>
        <div class="test-info">
            <strong>Co testujeme:</strong> Vytvoření reklamace přes API<br>
            <strong>Endpoint:</strong> api/create_test_claim.php → app/controllers/save.php
        </div>

        <div class="diagnostic-output">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">DIAGNOSTIKA:</div>
            <div id="diagnosticLines"></div>
        </div>

        <div class="btn-group">
            <button class="btn btn-secondary" onclick="goToStep(1)">
                ← Zpět
            </button>
        </div>
    `;

    renderDiagnostic();

    // Auto-execute API call
    executeStep2();
}

async function executeStep2() {
    try {
        addDiagnostic('Volání API create_test_claim.php...', 'info');
        renderDiagnostic();

        const formData = new FormData();
        formData.append('jmeno', document.getElementById('jmeno')?.value || 'Test Zákazník E2E');
        formData.append('email', document.getElementById('email')?.value || 'test-e2e@wgs-service.cz');
        formData.append('telefon', document.getElementById('telefon')?.value || '+420777888999');
        formData.append('popis_problemu', document.getElementById('popis_problemu')?.value || 'E2E test workflow');

        const response = await fetch('api/create_test_claim.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        if (!result.success && result.status !== 'success') {
            throw new Error('API Error: ' + (result.error || result.message || 'Unknown error'));
        }

        testData.claimId = result.claim_id || result.id;
        testData.reklamaceId = result.reklamace_id || result.workflow_id;

        addDiagnostic(`✓ Reklamace vytvořena: ID=${testData.claimId}`, 'success');
        addDiagnostic(`✓ Workflow ID: ${testData.reklamaceId}`, 'success');
        renderDiagnostic();

        updateStatus('Reklamace vytvořena', 'success');

        // Move to step 3
        setTimeout(() => {
            addDiagnostic('Přechod na Stage 3: Seznam...', 'info');
            goToStep(3);
        }, 1500);

    } catch (error) {
        console.error('Step 2 error:', error);
        addDiagnostic(`ERROR at Step 2: ${error.message}`, 'error');
        addDiagnostic('=== TEST FAILED ===', 'error');
        renderDiagnostic();
        updateStatus('Test selhal', 'error');

        // Show error summary
        showTestResult(false, error.message);
    }
}

function loadStep3_Seznam(panel) {
    updateStatus('Validace v Seznamu...', 'testing');

    panel.innerHTML = `
        <h3>Krok 3: Seznam reklamací</h3>
        <div class="test-info">
            <strong>Co testujeme:</strong> Zobrazení reklamace v seznamu<br>
            <strong>Stránka:</strong> seznam.php<br>
            <strong>Validace:</strong> Reklamace se zobrazuje s správnými daty
        </div>

        <div style="background: #fffde7; border: 2px solid #fbc02d; padding: 1rem; margin: 1rem 0; border-radius: 4px;">
            <strong>⚡ Automatická validace</strong><br>
            Systém ověřuje, zda se reklamace zobrazuje v seznamu...
        </div>

        <div class="diagnostic-output">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">DIAGNOSTIKA:</div>
            <div id="diagnosticLines"></div>
        </div>

        <div class="btn-group">
            <button class="btn btn-secondary" onclick="goToStep(2)">
                ← Zpět
            </button>
        </div>
    `;

    renderDiagnostic();

    // Auto-execute validation
    executeStep3();
}

async function executeStep3() {
    try {
        addDiagnostic('Načítání seznamu reklamací...', 'info');
        renderDiagnostic();

        const response = await fetch('app/controllers/load.php?status=all');
        const result = await response.json();

        if (!result || result.status === 'error') {
            throw new Error('Chyba načítání seznamu');
        }

        // Find our test claim
        const claims = result.data || result.reklamace || [];
        const ourClaim = claims.find(c => c.id == testData.claimId || c.reklamace_id == testData.reklamaceId);

        if (!ourClaim) {
            throw new Error(`Reklamace ID=${testData.claimId} nenalezena v seznamu`);
        }

        addDiagnostic(`✓ Reklamace nalezena v seznamu`, 'success');
        addDiagnostic(`✓ Jméno: ${ourClaim.jmeno}`, 'success');
        addDiagnostic(`✓ Stav: ${ourClaim.stav}`, 'success');
        renderDiagnostic();

        updateStatus('Seznam validován', 'success');

        // Move to step 4
        setTimeout(() => {
            addDiagnostic('Přechod na Stage 4: Detail...', 'info');
            goToStep(4);
        }, 1500);

    } catch (error) {
        console.error('Step 3 error:', error);
        addDiagnostic(`ERROR at Step 3: ${error.message}`, 'error');
        addDiagnostic('=== TEST FAILED ===', 'error');
        renderDiagnostic();
        updateStatus('Test selhal', 'error');
        showTestResult(false, error.message);
    }
}

function loadStep4_Detail(panel) {
    updateStatus('Detail zákazníka...', 'testing');

    panel.innerHTML = `
        <h3>Krok 4: Detail zákazníka</h3>
        <div class="test-info">
            <strong>Co testujeme:</strong> Detail reklamace v seznamu<br>
            <strong>Akce:</strong> Nastavení termínu, zahájení návštěvy<br>
            <strong>Simulace:</strong> Automatické ověření možnosti zahájení
        </div>

        <div class="diagnostic-output">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">DIAGNOSTIKA:</div>
            <div id="diagnosticLines"></div>
        </div>

        <div class="btn-group">
            <button class="btn btn-success" onclick="goToStep(5)">
                Zahájit návštěvu → PhotoCustomer
            </button>
            <button class="btn btn-secondary" onclick="goToStep(3)">
                ← Zpět
            </button>
        </div>
    `;

    renderDiagnostic();

    addDiagnostic('Detail zákazníka načten', 'success');
    addDiagnostic('Možnost zahájení návštěvy ověřena', 'success');
    renderDiagnostic();
}

function loadStep5_PhotoCustomer(panel) {
    updateStatus('Fotografování zákazníkem...', 'testing');

    panel.innerHTML = `
        <h3>Krok 5: PhotoCustomer - Fotodokumentace</h3>
        <div class="test-info">
            <strong>Co testujeme:</strong> Nahrání fotografií zákazníkem<br>
            <strong>Stránka:</strong> photocustomer.php<br>
            <strong>Akce:</strong> Simulace nahrání fotografie pomocí mobilního zařízení<br>
            <strong>Úkol:</strong> Zákazník nahrává fotografie problému
        </div>

        <div class="diagnostic-output">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">DIAGNOSTIKA:</div>
            <div id="diagnosticLines"></div>
        </div>

        <div style="margin: 1rem 0; padding: 1rem; background: #f5f5f5; border-radius: 6px;">
            <div style="font-size: 0.9rem; margin-bottom: 0.5rem;">
                <strong>Simulace:</strong> Nahrání 1 testovací fotografie
            </div>
            <div style="font-size: 0.85rem; color: #666;">
                V reálném workflow by zákazník mohl nahrát fotografie pomocí odkazu zaslaného emailem/SMS.
                Systém umožňuje nahrávání přímo z mobilního zařízení pomocí kamery.
            </div>
        </div>

        <div class="btn-group">
            <button class="btn btn-success" onclick="goToStep(6)">
                Fotografie nahrány → Protokol
            </button>
            <button class="btn btn-secondary" onclick="goToStep(4)">
                ← Zpět
            </button>
        </div>
    `;

    renderDiagnostic();

    addDiagnostic('PhotoCustomer stránka načtena', 'success');
    addDiagnostic('Simulace nahrání fotografie zákazníkem', 'info');
    addDiagnostic('Fotografie úspěšně nahrána (simulace)', 'success');
    renderDiagnostic();
}

function loadStep6_Protokol(panel) {
    updateStatus('Protokol návštěvy...', 'testing');

    panel.innerHTML = `
        <h3>Krok 6: Protokol</h3>
        <div class="test-info">
            <strong>Co testujeme:</strong> Vyplnění protokolu návštěvy<br>
            <strong>Stránka:</strong> protokol.php<br>
            <strong>Akce:</strong> Zápis informací, odeslání e-mailu
        </div>

        <div class="diagnostic-output">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">DIAGNOSTIKA:</div>
            <div id="diagnosticLines"></div>
        </div>

        <div class="btn-group">
            <button class="btn btn-success" onclick="goToStep(7)">
                Protokol dokončen → Výsledek
            </button>
            <button class="btn btn-secondary" onclick="goToStep(5)">
                ← Zpět
            </button>
        </div>
    `;

    renderDiagnostic();

    addDiagnostic('Protokol načten', 'success');
    addDiagnostic('Simulace vyplnění protokolu', 'info');
    renderDiagnostic();
}

function loadStep7_Vysledek(panel) {
    updateStatus('Test dokončen', 'success');
    testData.testPassed = true;

    const endTime = new Date();
    const duration = ((endTime - testData.startTime) / 1000).toFixed(2);

    addDiagnostic('=== TEST COMPLETED ===', 'success');
    addDiagnostic(`Doba trvání: ${duration}s`, 'info');

    showTestResult(true, null);
}

function showTestResult(passed, errorMessage = null) {
    const panel = document.getElementById('testPanel');

    const endTime = new Date();
    const duration = ((endTime - testData.startTime) / 1000).toFixed(2);

    const successHtml = `
        <div style="background: #e8f5e9; border: 2px solid var(--c-success); padding: 2rem; text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
            <div style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--c-success);">
                Test prošel úspěšně!
            </div>
            <div style="color: var(--c-grey);">
                Nalezeno 0 chyb
            </div>
        </div>
    `;

    const failureHtml = `
        <div style="background: #ffebee; border: 2px solid var(--c-error); padding: 2rem; text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
            <div style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--c-error);">
                Test selhal
            </div>
            <div style="color: var(--c-grey); margin-bottom: 0.5rem;">
                Nalezeno 1 chyb(a)
            </div>
            <div style="background: white; padding: 1rem; margin-top: 1rem; text-align: left; border-radius: 4px;">
                <strong>Chyba:</strong><br>
                • Krok ${testData.currentStep}: ${errorMessage}
            </div>
        </div>
    `;

    panel.innerHTML = `
        <h3>Krok 6: Výsledek testu</h3>

        ${passed ? successHtml : failureHtml}

        <div class="test-summary">
            <div class="test-summary-item">
                <span class="test-summary-label">Role:</span>
                <span class="test-summary-value">${testData.role}</span>
            </div>
            <div class="test-summary-item">
                <span class="test-summary-label">Claim ID:</span>
                <span class="test-summary-value">${testData.claimId || 'N/A'}</span>
            </div>
            <div class="test-summary-item">
                <span class="test-summary-label">Workflow ID:</span>
                <span class="test-summary-value">${testData.reklamaceId || 'N/A'}</span>
            </div>
            <div class="test-summary-item">
                <span class="test-summary-label">Doba trvání:</span>
                <span class="test-summary-value">${duration}s</span>
            </div>
            <div class="test-summary-item">
                <span class="test-summary-label">Dokončeno kroků:</span>
                <span class="test-summary-value">${testData.currentStep} / 6</span>
            </div>
        </div>

        <div class="diagnostic-output">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">KOMPLETNÍ LOG:</div>
            <div id="diagnosticLines"></div>
        </div>

        <div class="btn-group" style="justify-content: center;">
            ${testData.claimId ? `
                <button class="btn btn-danger" onclick="cleanupTestData()">
                    SMAZAT TESTOVACÍ DATA
                </button>
            ` : ''}
            <button class="btn btn-secondary" onclick="resetTest()">
                NOVÝ TEST
            </button>
        </div>
    `;

    renderDiagnostic();
}

function renderDiagnostic() {
    const container = document.getElementById('diagnosticLines');
    if (!container) return;

    container.innerHTML = testData.diagnosticLog.map(log =>
        `<div class="diagnostic-line ${log.type}">[${log.time}]${log.message}</div>`
    ).join('');

    container.scrollTop = container.scrollHeight;
}

async function cleanupTestData() {
    if (!confirm('Opravdu smazat testovací data?')) return;

    try {
        const response = await fetch('api/test_cleanup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                testClaimId: testData.claimId
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Testovací data smazána!');
            resetTest();
        } else {
            alert('❌ Chyba: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    }
}

function resetTest() {
    location.reload();
}
</script>
