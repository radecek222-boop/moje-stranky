<?php
/**
 * Control Center - Minimalist Testing Environment
 * Jednoduchý vizuální E2E test workflow reklamací
 */
$pdo = getDbConnection();
?>

<style>
.testing-minimal {
    max-width: 900px;
    margin: 0 auto;
}

.test-workflow {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    padding: 2rem;
}

.workflow-step {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border: 1px solid var(--c-border);
    background: var(--c-bg);
    transition: all 0.3s ease;
}

.workflow-step.pending {
    opacity: 0.6;
}

.workflow-step.running {
    border-color: #FFC107;
    background: rgba(255, 193, 7, 0.05);
}

.workflow-step.success {
    border-color: var(--c-success);
    background: rgba(45, 80, 22, 0.05);
}

.workflow-step.failed {
    border-color: var(--c-error);
    background: rgba(139, 0, 0, 0.05);
}

.workflow-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--c-border);
    color: var(--c-black);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    flex-shrink: 0;
}

.workflow-step.success .workflow-number {
    background: var(--c-success);
    color: white;
}

.workflow-step.failed .workflow-number {
    background: var(--c-error);
    color: white;
}

.workflow-step.running .workflow-number {
    background: #FFC107;
    color: var(--c-black);
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.workflow-text {
    flex: 1;
}

.workflow-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--c-black);
    margin-bottom: 0.2rem;
}

.workflow-desc {
    font-size: 0.8rem;
    color: var(--c-grey);
}

.workflow-icon {
    font-size: 1.5rem;
}

.test-controls {
    text-align: center;
    margin: 2rem 0;
}

.result-box {
    margin-top: 2rem;
    padding: 1.5rem;
    border: 2px solid;
    border-radius: 4px;
    text-align: center;
    display: none;
}

.result-box.visible {
    display: block;
}

.result-box.success {
    border-color: var(--c-success);
    background: rgba(45, 80, 22, 0.05);
}

.result-box.failed {
    border-color: var(--c-error);
    background: rgba(139, 0, 0, 0.05);
}

.result-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.result-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.result-desc {
    color: var(--c-grey);
    margin-bottom: 1.5rem;
}

.result-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.role-selector {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.role-btn {
    padding: 0.7rem 1.5rem;
    border: 2px solid var(--c-border);
    background: white;
    cursor: pointer;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: all 0.2s ease;
}

.role-btn:hover {
    border-color: var(--c-black);
}

.role-btn.selected {
    border-color: var(--c-success);
    background: rgba(45, 80, 22, 0.05);
    font-weight: 600;
}

.progress-bar {
    height: 4px;
    background: var(--c-border);
    margin: 1rem 0;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--c-success);
    width: 0%;
    transition: width 0.5s ease;
}
</style>

<div class="testing-minimal">
    <h1 class="page-title">🧪 Testovací prostředí</h1>
    <p class="page-subtitle">Minimalistický E2E test celého workflow reklamací</p>

    <div class="test-workflow">
        <!-- Role Selection -->
        <h3 style="margin-bottom: 1rem; text-align: center;">Vyberte roli pro test:</h3>
        <div class="role-selector">
            <button class="role-btn" data-role="admin">👨‍💼 Admin</button>
            <button class="role-btn" data-role="prodejce">💼 Prodejce</button>
            <button class="role-btn" data-role="technik">🔧 Technik</button>
            <button class="role-btn" data-role="guest">👤 Guest</button>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <!-- Test Steps - Workflow -->
        <div id="workflowSteps">
            <div class="workflow-step pending" data-step="1">
                <div class="workflow-number">1</div>
                <div class="workflow-text">
                    <div class="workflow-title">DB připojení</div>
                    <div class="workflow-desc">Test MySQL připojení</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>

            <div class="workflow-step pending" data-step="2">
                <div class="workflow-number">2</div>
                <div class="workflow-text">
                    <div class="workflow-title">Registrace uživatele</div>
                    <div class="workflow-desc">Vytvoření test usera podle role</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>

            <div class="workflow-step pending" data-step="3">
                <div class="workflow-number">3</div>
                <div class="workflow-text">
                    <div class="workflow-title">Vytvoření reklamace</div>
                    <div class="workflow-desc">INSERT do wgs_reklamace</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>

            <div class="workflow-step pending" data-step="4">
                <div class="workflow-number">4</div>
                <div class="workflow-text">
                    <div class="workflow-title">Upload fotky</div>
                    <div class="workflow-desc">Test nahrání a uložení do wgs_photos</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>

            <div class="workflow-step pending" data-step="5">
                <div class="workflow-number">5</div>
                <div class="workflow-text">
                    <div class="workflow-title">Zobrazení v seznamu</div>
                    <div class="workflow-desc">SELECT a načtení dat</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>

            <div class="workflow-step pending" data-step="6">
                <div class="workflow-number">6</div>
                <div class="workflow-text">
                    <div class="workflow-title">Nastavení termínu</div>
                    <div class="workflow-desc">UPDATE datum návštěvy</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>

            <div class="workflow-step pending" data-step="7">
                <div class="workflow-number">7</div>
                <div class="workflow-text">
                    <div class="workflow-title">Protokol + PDF</div>
                    <div class="workflow-desc">Generování PDF dokumentu</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>

            <div class="workflow-step pending" data-step="8">
                <div class="workflow-number">8</div>
                <div class="workflow-text">
                    <div class="workflow-title">Email notifikace</div>
                    <div class="workflow-desc">Test email systému</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>

            <div class="workflow-step pending" data-step="9">
                <div class="workflow-number">9</div>
                <div class="workflow-text">
                    <div class="workflow-title">Kompletní detail</div>
                    <div class="workflow-desc">Načtení všech vazeb (reklamace, fotky, protokol)</div>
                </div>
                <div class="workflow-icon">⏳</div>
            </div>
        </div>

        <!-- Start Button -->
        <div class="test-controls">
            <button class="btn btn-success" id="startTestBtn" disabled>▶️ Spustit test</button>
            <button class="btn btn-secondary" id="resetBtn">🔄 Reset</button>
        </div>

        <!-- Results -->
        <div class="result-box success" id="resultSuccess">
            <div class="result-icon">✅</div>
            <div class="result-title">Všechny testy proběhly úspěšně!</div>
            <div class="result-desc">Proběhlo dle potřeb? Potvrďte a test data budou smazána.</div>
            <div class="result-actions">
                <button class="btn btn-success" onclick="cleanupTestData()">✅ Potvrdit a smazat test data</button>
                <button class="btn btn-secondary" onclick="viewTestDataInDB()">🔍 Prohlédnout v DB</button>
                <button class="btn" onclick="copyResults()">📋 Kopírovat</button>
            </div>
        </div>

        <div class="result-box failed" id="resultFailed">
            <div class="result-icon">❌</div>
            <div class="result-title">Některé testy selhaly</div>
            <div class="result-desc">Test data NEBYLA smazána. Můžete je prohlédnout pro debug.</div>
            <div class="result-actions">
                <button class="btn btn-danger" onclick="copyResults()">📋 Kopírovat chyby pro Claude</button>
                <button class="btn btn-secondary" onclick="viewTestDataInDB()">🔍 Prohlédnout v DB</button>
                <button class="btn" onclick="cleanupTestData()">🧹 Ručně smazat</button>
            </div>
            <div id="errorDetails" style="margin-top: 1rem; padding: 1rem; background: var(--c-white); border: 1px solid var(--c-border); text-align: left; font-family: 'Courier New', monospace; font-size: 0.85rem;">
                <!-- Error details will be filled here -->
            </div>
        </div>
    </div>
</div>

<script>
let selectedRole = null;
let testResults = {
    testUserId: null,
    testClaimId: null,
    testPhotoId: null,
    steps: []
};

// Role selection
document.querySelectorAll('.role-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
        selectedRole = this.dataset.role;
        document.getElementById('startTestBtn').disabled = false;
    });
});

// Start test
document.getElementById('startTestBtn').addEventListener('click', async function() {
    if (!selectedRole) {
        alert('Vyberte roli!');
        return;
    }

    this.disabled = true;
    document.getElementById('resetBtn').disabled = true;
    document.getElementById('resultSuccess').classList.remove('visible');
    document.getElementById('resultFailed').classList.remove('visible');

    testResults = { testUserId: null, testClaimId: null, testPhotoId: null, steps: [] };

    await runWorkflowTest();

    this.disabled = false;
    document.getElementById('resetBtn').disabled = false;
});

// Reset
document.getElementById('resetBtn').addEventListener('click', () => location.reload());

async function runWorkflowTest() {
    const steps = document.querySelectorAll('.workflow-step');
    const totalSteps = steps.length;
    let passedCount = 0;
    let failedCount = 0;

    for (let i = 0; i < totalSteps; i++) {
        const step = steps[i];
        const stepNumber = parseInt(step.dataset.step);

        // Update progress
        const progress = Math.round(((i + 1) / totalSteps) * 100);
        document.getElementById('progressFill').style.width = progress + '%';

        // Mark as running
        step.classList.remove('pending');
        step.classList.add('running');
        step.querySelector('.workflow-icon').textContent = '⚙️';

        // Run test
        const result = await testStep(stepNumber, selectedRole);
        testResults.steps.push(result);

        // Small delay
        await sleep(300);

        // Mark result
        step.classList.remove('running');

        if (result.success) {
            step.classList.add('success');
            step.querySelector('.workflow-icon').textContent = '✅';
            passedCount++;
        } else {
            step.classList.add('failed');
            step.querySelector('.workflow-icon').textContent = '❌';
            failedCount++;

            // Stop on first failure
            break;
        }
    }

    // Show results
    if (failedCount === 0) {
        document.getElementById('resultSuccess').classList.add('visible');
    } else {
        document.getElementById('resultFailed').classList.add('visible');
        fillErrorDetails();
    }
}

async function testStep(step, role) {
    try {
        const response = await fetch(`api/test_environment_simple.php?step=${step}&role=${role}`);
        const result = await response.json();
        return result;
    } catch (error) {
        return {
            success: false,
            step: step,
            error: error.message
        };
    }
}

function fillErrorDetails() {
    const errorDiv = document.getElementById('errorDetails');
    let html = '<strong>Detaily chyb:</strong><br><br>';

    testResults.steps.forEach((result, i) => {
        if (!result.success) {
            html += `<div style="margin-bottom: 1rem;">`;
            html += `<strong>Krok ${i + 1}:</strong> ${result.error || 'Unknown error'}<br>`;
            if (result.details) {
                html += `<span style="color: var(--c-grey);">${result.details}</span><br>`;
            }
            if (result.file && result.line) {
                html += `<span style="color: var(--c-error);">📍 ${result.file}:${result.line}</span><br>`;
            }
            html += `</div>`;
        }
    });

    errorDiv.innerHTML = html;
}

async function cleanupTestData() {
    if (!confirm('Opravdu smazat všechna test data?')) return;

    try {
        const response = await fetch('api/test_cleanup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                testUserId: testResults.testUserId,
                testClaimId: testResults.testClaimId,
                testPhotoId: testResults.testPhotoId
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Test data smazána!');
            location.reload();
        } else {
            alert('❌ Chyba: ' + result.error);
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    }
}

function viewTestDataInDB() {
    const info = `
Test data v databázi:
• Test User ID: ${testResults.testUserId || 'N/A'}
• Test Claim ID: ${testResults.testClaimId || 'N/A'}
• Test Photo ID: ${testResults.testPhotoId || 'N/A'}

SELECT * FROM wgs_users WHERE id = ${testResults.testUserId};
SELECT * FROM wgs_reklamace WHERE id = ${testResults.testClaimId};
SELECT * FROM wgs_photos WHERE id = ${testResults.testPhotoId};
    `.trim();

    alert(info);
    console.log(info);
}

function copyResults() {
    const passed = testResults.steps.filter(s => s.success).length;
    const failed = testResults.steps.filter(s => !s.success).length;

    let report = `
🧪 WGS E2E TEST REPORT
${'='.repeat(80)}
Role: ${selectedRole}
Datum: ${new Date().toLocaleString('cs-CZ')}

VÝSLEDKY:
✅ Úspěšné: ${passed}
❌ Selhání: ${failed}
📋 Celkem: ${testResults.steps.length}

TEST DATA IDs:
• User ID: ${testResults.testUserId || 'N/A'}
• Claim ID: ${testResults.testClaimId || 'N/A'}
• Photo ID: ${testResults.testPhotoId || 'N/A'}

DETAILY KROKŮ:
${'-'.repeat(80)}
`;

    testResults.steps.forEach((result, i) => {
        report += `\nKrok ${i + 1}: ${result.success ? '✅ PASS' : '❌ FAIL'}\n`;
        if (!result.success) {
            report += `   Error: ${result.error || 'Unknown'}\n`;
            if (result.file && result.line) {
                report += `   📍 ${result.file}:${result.line}\n`;
            }
        }
    });

    report += `\n${'='.repeat(80)}\n`;

    navigator.clipboard.writeText(report.trim()).then(() => {
        alert('✅ Zkopírováno! Vložte CTRL+V do zprávy pro Claude Code');
    });
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}
</script>
