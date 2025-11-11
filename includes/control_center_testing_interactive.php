<?php
/**
 * Interactive Testing Environment
 * Vizuální průchod workflow s real-time diagnostikou
 */
$pdo = getDbConnection();
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';
?>

<style>
.testing-interactive {
    max-width: 1000px;
    margin: <?= $embedMode ? '0' : '2rem' ?> auto;
    padding: 2rem;
}

.workflow-path {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: var(--c-bg);
    border: 1px solid var(--c-border);
}

.path-step {
    flex: 1;
    text-align: center;
    padding: 1rem;
    position: relative;
}

.path-step:not(:last-child)::after {
    content: '→';
    position: absolute;
    right: -15px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.5rem;
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

.test-panel {
    background: var(--c-white);
    border: 2px solid var(--c-border);
    padding: 2rem;
    margin-bottom: 2rem;
    min-height: 400px;
}

.test-panel h3 {
    margin: 0 0 1.5rem 0;
    font-size: 1.3rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-black);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.05em;
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

.diagnostic-output {
    background: #f5f5f5;
    border: 1px solid var(--c-border);
    padding: 1rem;
    margin-top: 1rem;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    max-height: 200px;
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

.iframe-container {
    border: 2px solid var(--c-border);
    height: 500px;
    background: var(--c-white);
    position: relative;
}

.iframe-container iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.status-indicator {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
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
</style>

<div class="testing-interactive">
    <?php if (!$embedMode): ?>
    <h1 class="page-title">Interaktivní testovací prostředí</h1>
    <p class="page-subtitle">Vizuální průchod celým workflow s diagnostikou</p>
    <?php endif; ?>

    <!-- Workflow Path -->
    <div class="workflow-path">
        <div class="path-step active" id="step1">
            <div style="font-size: 0.8rem; margin-bottom: 0.25rem;">KROK 1</div>
            <div style="font-weight: 600;">Vytvoření reklamace</div>
        </div>
        <div class="path-step pending" id="step2">
            <div style="font-size: 0.8rem; margin-bottom: 0.25rem;">KROK 2</div>
            <div style="font-weight: 600;">Seznam reklamací</div>
        </div>
        <div class="path-step pending" id="step3">
            <div style="font-size: 0.8rem; margin-bottom: 0.25rem;">KROK 3</div>
            <div style="font-weight: 600;">Detail reklamace</div>
        </div>
        <div class="path-step pending" id="step4">
            <div style="font-size: 0.8rem; margin-bottom: 0.25rem;">KROK 4</div>
            <div style="font-weight: 600;">Výsledek</div>
        </div>
    </div>

    <!-- Status -->
    <div style="text-align: center; margin-bottom: 2rem;">
        <span class="status-indicator waiting" id="statusIndicator">Čekám na zadání dat</span>
    </div>

    <!-- Test Panel -->
    <div class="test-panel" id="testPanel">
        <!-- KROK 1: Vytvoření reklamace -->
        <div id="panel-step1">
            <h3>Krok 1: Vytvoření testovací reklamace</h3>

            <form id="createClaimForm" onsubmit="return false;">
                <div class="form-group">
                    <label for="testJmeno">Jméno zákazníka *</label>
                    <input type="text" id="testJmeno" name="jmeno" required placeholder="Test Zákazník">
                </div>

                <div class="form-group">
                    <label for="testEmail">Email *</label>
                    <input type="email" id="testEmail" name="email" required placeholder="test@example.com">
                </div>

                <div class="form-group">
                    <label for="testTelefon">Telefon *</label>
                    <input type="tel" id="testTelefon" name="telefon" required placeholder="+420 123 456 789">
                </div>

                <div class="form-group">
                    <label for="testPopis">Popis problému *</label>
                    <textarea id="testPopis" name="popis" rows="3" required placeholder="Test popis problému..."></textarea>
                </div>

                <div class="form-group">
                    <label for="testPhoto">Fotografie (volitelné)</label>
                    <input type="file" id="testPhoto" name="photo" accept="image/*">
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-success" onclick="createTestClaim()" id="createBtn">
                        Vytvořit a pokračovat →
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetTest()">
                        Reset
                    </button>
                </div>
            </form>

            <!-- Diagnostika -->
            <div class="diagnostic-output" id="diagnosticOutput" style="display: none;">
                <div style="font-weight: 600; margin-bottom: 0.5rem; color: var(--c-black);">DIAGNOSTIKA:</div>
                <div id="diagnosticLines"></div>
            </div>
        </div>

        <!-- KROK 2: Seznam reklamací (iframe) -->
        <div id="panel-step2" style="display: none;">
            <h3>Krok 2: Seznam reklamací</h3>
            <p style="color: var(--c-grey); margin-bottom: 1rem;">
                Zkontrolujte, zda se vaše testovací reklamace zobrazuje v seznamu.
                Klikněte na ni pro otevření detailu.
            </p>

            <div class="iframe-container">
                <iframe id="seznamFrame" src=""></iframe>
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-success" onclick="goToStep(3)">
                    Reklamace nalezena → Detail
                </button>
                <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                    ← Zpět
                </button>
            </div>
        </div>

        <!-- KROK 3: Detail reklamace (iframe) -->
        <div id="panel-step3" style="display: none;">
            <h3>Krok 3: Detail reklamace</h3>
            <p style="color: var(--c-grey); margin-bottom: 1rem;">
                Prohlédněte si detail reklamace. Zkontrolujte, zda se zobrazují všechny údaje správně.
            </p>

            <div class="iframe-container">
                <iframe id="detailFrame" src=""></iframe>
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-success" onclick="goToStep(4)">
                    Zkontrolováno → Výsledek
                </button>
                <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                    ← Zpět na seznam
                </button>
            </div>
        </div>

        <!-- KROK 4: Výsledek -->
        <div id="panel-step4" style="display: none;">
            <h3>Krok 4: Výsledek testu</h3>

            <div style="background: #e8f5e9; border: 2px solid var(--c-success); padding: 2rem; text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <div style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem;">
                    Test dokončen úspěšně!
                </div>
                <div style="color: var(--c-grey);">
                    Prošli jste celým workflow bez chyb.
                </div>
            </div>

            <div class="diagnostic-output">
                <div style="font-weight: 600; margin-bottom: 0.5rem; color: var(--c-black);">KOMPLETNÍ DIAGNOSTIKA:</div>
                <div id="finalDiagnostic"></div>
            </div>

            <div class="btn-group" style="justify-content: center;">
                <button type="button" class="btn btn-success" onclick="cleanupTestData()">
                    ✓ Smazat test data
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetTest()">
                    🔄 Nový test
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let testData = {
    claimId: null,
    reklamaceId: null,
    diagnosticLog: []
};

function addDiagnostic(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString('cs-CZ');
    const line = {
        time: timestamp,
        message: message,
        type: type
    };
    testData.diagnosticLog.push(line);

    const diagnosticLines = document.getElementById('diagnosticLines');
    if (diagnosticLines) {
        const lineDiv = document.createElement('div');
        lineDiv.className = `diagnostic-line ${type}`;
        lineDiv.textContent = `[${timestamp}] ${message}`;
        diagnosticLines.appendChild(lineDiv);
        diagnosticLines.scrollTop = diagnosticLines.scrollHeight;
    }
}

async function createTestClaim() {
    const form = document.getElementById('createClaimForm');
    const formData = new FormData(form);

    const diagnosticOutput = document.getElementById('diagnosticOutput');
    const createBtn = document.getElementById('createBtn');
    const statusIndicator = document.getElementById('statusIndicator');

    // Show diagnostic
    diagnosticOutput.style.display = 'block';
    document.getElementById('diagnosticLines').innerHTML = '';

    // Update status
    statusIndicator.className = 'status-indicator testing';
    statusIndicator.textContent = 'Vytvářím reklamaci...';
    createBtn.disabled = true;

    addDiagnostic('Start vytváření reklamace', 'info');

    try {
        // Validate data
        addDiagnostic('Validace vstupních dat...', 'info');
        const jmeno = formData.get('jmeno');
        const email = formData.get('email');

        if (!jmeno || !email) {
            throw new Error('Chybí povinná pole');
        }
        addDiagnostic('✓ Validace OK', 'success');

        // Create claim via API
        addDiagnostic('Volání API pro vytvoření reklamace...', 'info');
        const response = await fetch('api/create_test_claim.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();
        addDiagnostic(`✓ API Response: ${JSON.stringify(result)}`, 'success');

        if (!result.success) {
            throw new Error(result.error || 'Unknown error');
        }

        testData.claimId = result.claim_id;
        testData.reklamaceId = result.reklamace_id;

        addDiagnostic(`✓ Reklamace vytvořena: ID=${result.claim_id}`, 'success');
        addDiagnostic(`✓ Přechod na seznam reklamací`, 'success');

        // Update status
        statusIndicator.className = 'status-indicator success';
        statusIndicator.textContent = 'Reklamace vytvořena';

        // Move to next step after short delay
        setTimeout(() => {
            goToStep(2);
        }, 1000);

    } catch (error) {
        console.error('Error creating test claim:', error);
        addDiagnostic(`✗ CHYBA: ${error.message}`, 'error');

        statusIndicator.className = 'status-indicator error';
        statusIndicator.textContent = 'Chyba při vytváření';
        createBtn.disabled = false;
    }
}

function goToStep(stepNumber) {
    // Hide all panels
    for (let i = 1; i <= 4; i++) {
        document.getElementById(`panel-step${i}`).style.display = 'none';
        const pathStep = document.getElementById(`step${i}`);
        pathStep.classList.remove('active', 'pending');
        if (i < stepNumber) {
            pathStep.classList.add('completed');
        } else if (i === stepNumber) {
            pathStep.classList.add('active');
        } else {
            pathStep.classList.add('pending');
        }
    }

    // Show current panel
    document.getElementById(`panel-step${stepNumber}`).style.display = 'block';

    // Load content for step
    if (stepNumber === 2) {
        // Load seznam.php in iframe
        document.getElementById('seznamFrame').src = 'seznam.php?embed=1&test=1';
        document.getElementById('statusIndicator').className = 'status-indicator waiting';
        document.getElementById('statusIndicator').textContent = 'Prohlížím seznam...';
    } else if (stepNumber === 3) {
        // Load detail in iframe (you'd need to pass the claim ID)
        if (testData.claimId) {
            // Assuming there's a detail page
            document.getElementById('detailFrame').src = `detail.php?id=${testData.claimId}&embed=1`;
        }
        document.getElementById('statusIndicator').className = 'status-indicator waiting';
        document.getElementById('statusIndicator').textContent = 'Prohlížím detail...';
    } else if (stepNumber === 4) {
        // Show final diagnostic
        const finalDiagnostic = document.getElementById('finalDiagnostic');
        finalDiagnostic.innerHTML = testData.diagnosticLog.map(log =>
            `<div class="diagnostic-line ${log.type}">[${log.time}] ${log.message}</div>`
        ).join('');
        document.getElementById('statusIndicator').className = 'status-indicator success';
        document.getElementById('statusIndicator').textContent = 'Test dokončen';
    }
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
            alert('✅ Test data smazána!');
            resetTest();
        } else {
            alert('❌ Chyba: ' + result.error);
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    }
}

function resetTest() {
    location.reload();
}
</script>
