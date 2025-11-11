<?php
/**
 * Control Center - Testovací prostředí
 * E2E testing simulator pro celý workflow reklamací
 */

$pdo = getDbConnection();
?>

<style>
/* Testing Environment Styles */
.testing-env {
    max-width: 1200px;
    margin: 0 auto;
}

.test-wizard {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    padding: 2rem;
}

.test-step {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 1.5rem;
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    border-radius: 4px;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.test-step.running {
    border-color: #FFC107;
    background: rgba(255, 193, 7, 0.05);
}

.test-step.success {
    border-color: var(--c-success);
    background: rgba(45, 80, 22, 0.05);
}

.test-step.failed {
    border-color: var(--c-error);
    background: rgba(139, 0, 0, 0.05);
}

.test-step-icon {
    font-size: 2rem;
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--c-white);
}

.test-step.running .test-step-icon {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.test-step-content {
    flex: 1;
}

.test-step-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--c-black);
    margin-bottom: 0.5rem;
}

.test-step-description {
    font-size: 0.85rem;
    color: var(--c-grey);
    margin-bottom: 0.5rem;
}

.test-step-details {
    font-size: 0.8rem;
    color: var(--c-light-grey);
    font-family: 'Courier New', monospace;
    background: var(--c-white);
    padding: 0.5rem;
    border-radius: 3px;
    margin-top: 0.5rem;
    display: none;
}

.test-step.failed .test-step-details,
.test-step.success .test-step-details {
    display: block;
}

.test-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.3rem 0;
    font-size: 0.85rem;
}

.test-check-icon {
    font-size: 1rem;
}

.test-check.success {
    color: var(--c-success);
}

.test-check.failed {
    color: var(--c-error);
}

.test-controls {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.test-role-selector {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.role-option {
    flex: 1;
    padding: 1rem;
    border: 2px solid var(--c-border);
    border-radius: 4px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.role-option:hover {
    border-color: var(--c-black);
    background: rgba(0,0,0,0.02);
}

.role-option.selected {
    border-color: var(--c-success);
    background: rgba(45, 80, 22, 0.05);
}

.role-option-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.role-option-title {
    font-weight: 600;
    color: var(--c-black);
    margin-bottom: 0.3rem;
}

.role-option-desc {
    font-size: 0.8rem;
    color: var(--c-grey);
}

.test-summary {
    background: var(--c-white);
    border: 2px solid var(--c-border);
    border-radius: 4px;
    padding: 2rem;
    margin-top: 2rem;
    display: none;
}

.test-summary.visible {
    display: block;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-stat {
    text-align: center;
    padding: 1rem;
    background: var(--c-bg);
    border-radius: 4px;
}

.summary-stat-value {
    font-size: 3rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.summary-stat-label {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-grey);
}

.progress-bar-container {
    width: 100%;
    height: 30px;
    background: var(--c-bg);
    border-radius: 4px;
    overflow: hidden;
    margin: 1rem 0;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--c-success) 0%, #38ef7d 100%);
    transition: width 0.5s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
}
</style>

<div class="testing-env">
    <h1 class="page-title">🧪 Testovací prostředí</h1>
    <p class="page-subtitle">E2E simulace celého workflow reklamací s kontrolou všech komponent</p>

    <!-- Role Selector -->
    <div class="test-wizard">
        <h2 style="margin-bottom: 1rem;">1️⃣ Výběr role pro simulaci</h2>
        <div class="test-role-selector">
            <div class="role-option" data-role="admin">
                <div class="role-option-icon">👨‍💼</div>
                <div class="role-option-title">Admin</div>
                <div class="role-option-desc">Plný přístup ke všemu</div>
            </div>
            <div class="role-option" data-role="prodejce">
                <div class="role-option-icon">💼</div>
                <div class="role-option-title">Prodejce</div>
                <div class="role-option-desc">Vytváří reklamace</div>
            </div>
            <div class="role-option" data-role="technik">
                <div class="role-option-icon">🔧</div>
                <div class="role-option-title">Technik</div>
                <div class="role-option-desc">Vyřizuje reklamace</div>
            </div>
            <div class="role-option" data-role="guest">
                <div class="role-option-icon">👤</div>
                <div class="role-option-title">Neregistrovaný</div>
                <div class="role-option-desc">Veřejný přístup</div>
            </div>
        </div>

        <h2 style="margin-bottom: 1rem; margin-top: 2rem;">2️⃣ Spuštění testu</h2>
        <div class="test-controls">
            <button class="btn btn-success" id="startTest" disabled>
                ▶️ Spustit kompletní test
            </button>
            <button class="btn btn-secondary" id="resetTest">
                🔄 Reset
            </button>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar-container" id="testProgress" style="display: none;">
            <div class="progress-bar-fill" style="width: 0%;">0%</div>
        </div>
    </div>

    <!-- Test Steps -->
    <div style="margin-top: 2rem;">
        <h2 style="margin-bottom: 1rem;">📋 Test Steps - Workflow reklamace</h2>

        <!-- Step 1: Login Simulation -->
        <div class="test-step" data-step="login">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">1. Login simulace</div>
                <div class="test-step-description">Kontrola přihlášení a přístupu podle role</div>
                <div class="test-step-details">
                    <!-- Dynamically filled -->
                </div>
            </div>
        </div>

        <!-- Step 2: New Claim Page -->
        <div class="test-step" data-step="claim_page">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">2. Stránka nové reklamace</div>
                <div class="test-step-description">Kontrola novareklamace.php a formuláře</div>
                <div class="test-step-details"></div>
            </div>
        </div>

        <!-- Step 3: Photo Upload -->
        <div class="test-step" data-step="photo_upload">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">3. Nahrání fotky</div>
                <div class="test-step-description">Kontrola upload API a save_photos.php</div>
                <div class="test-step-details"></div>
            </div>
        </div>

        <!-- Step 4: Save Claim -->
        <div class="test-step" data-step="save_claim">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">4. Uložení reklamace</div>
                <div class="test-step-description">Kontrola databázového zápisu a validace</div>
                <div class="test-step-details"></div>
            </div>
        </div>

        <!-- Step 5: List View -->
        <div class="test-step" data-step="list_view">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">5. Zobrazení v seznamu</div>
                <div class="test-step-description">Kontrola seznam.php a load.php API</div>
                <div class="test-step-details"></div>
            </div>
        </div>

        <!-- Step 6: Set Date -->
        <div class="test-step" data-step="set_date">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">6. Nastavení termínu návštěvy</div>
                <div class="test-step-description">Kontrola aktualizace data v DB</div>
                <div class="test-step-details"></div>
            </div>
        </div>

        <!-- Step 7: Customer Photos -->
        <div class="test-step" data-step="customer_photos">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">7. PhotoCustomer - nahrání fotek zákazníkem</div>
                <div class="test-step-description">Kontrola photocustomer.php a upload workflow</div>
                <div class="test-step-details"></div>
            </div>
        </div>

        <!-- Step 8: Protocol -->
        <div class="test-step" data-step="protocol">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">8. Protokol - vyplnění a odeslání</div>
                <div class="test-step-description">Kontrola protokol.php a PDF generování</div>
                <div class="test-step-details"></div>
            </div>
        </div>

        <!-- Step 9: Final Detail View -->
        <div class="test-step" data-step="detail_view">
            <div class="test-step-icon">⏳</div>
            <div class="test-step-content">
                <div class="test-step-title">9. Finální detail zákazníka</div>
                <div class="test-step-description">Kontrola kompletního zobrazení všech dat</div>
                <div class="test-step-details"></div>
            </div>
        </div>
    </div>

    <!-- Test Summary -->
    <div class="test-summary" id="testSummary">
        <h2 style="margin-bottom: 1rem;">📊 Výsledky testu</h2>
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="summary-stat-value" style="color: var(--c-success);" id="passedCount">0</div>
                <div class="summary-stat-label">✅ Úspěšné</div>
            </div>
            <div class="summary-stat">
                <div class="summary-stat-value" style="color: var(--c-error);" id="failedCount">0</div>
                <div class="summary-stat-label">❌ Selhání</div>
            </div>
            <div class="summary-stat">
                <div class="summary-stat-value" style="color: var(--c-black);" id="totalCount">9</div>
                <div class="summary-stat-label">📋 Celkem kroků</div>
            </div>
            <div class="summary-stat">
                <div class="summary-stat-value" style="color: var(--c-black);" id="durationTime">0s</div>
                <div class="summary-stat-label">⏱️ Doba trvání</div>
            </div>
        </div>
        <div style="text-align: center;">
            <button class="btn btn-success" onclick="copyTestResults()">
                📋 Kopírovat výsledky pro Claude Code
            </button>
        </div>
    </div>
</div>

<script>
let selectedRole = null;
let testResults = [];
let startTime = null;

// Role selection
document.querySelectorAll('.role-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.role-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        selectedRole = this.dataset.role;
        document.getElementById('startTest').disabled = false;
    });
});

// Start test
document.getElementById('startTest').addEventListener('click', async function() {
    if (!selectedRole) {
        alert('Nejdřív vyberte roli!');
        return;
    }

    // Disable button
    this.disabled = true;
    document.getElementById('resetTest').disabled = true;

    // Reset UI
    document.querySelectorAll('.test-step').forEach(step => {
        step.classList.remove('success', 'failed', 'running');
        step.querySelector('.test-step-icon').textContent = '⏳';
        step.querySelector('.test-step-details').innerHTML = '';
    });

    document.getElementById('testProgress').style.display = 'block';
    document.getElementById('testSummary').classList.remove('visible');

    testResults = [];
    startTime = Date.now();

    // Run tests
    await runTests();
});

// Reset test
document.getElementById('resetTest').addEventListener('click', function() {
    location.reload();
});

async function runTests() {
    const steps = [
        'login',
        'claim_page',
        'photo_upload',
        'save_claim',
        'list_view',
        'set_date',
        'customer_photos',
        'protocol',
        'detail_view'
    ];

    let passed = 0;
    let failed = 0;

    for (let i = 0; i < steps.length; i++) {
        const step = steps[i];
        const progress = Math.round(((i + 1) / steps.length) * 100);

        updateProgress(progress);

        const result = await runTestStep(step, selectedRole);
        testResults.push(result);

        if (result.success) {
            passed++;
        } else {
            failed++;
        }

        // Small delay for visual effect
        await sleep(500);
    }

    // Show summary
    const duration = ((Date.now() - startTime) / 1000).toFixed(1);
    document.getElementById('passedCount').textContent = passed;
    document.getElementById('failedCount').textContent = failed;
    document.getElementById('durationTime').textContent = duration + 's';
    document.getElementById('testSummary').classList.add('visible');

    // Enable buttons
    document.getElementById('startTest').disabled = false;
    document.getElementById('resetTest').disabled = false;
}

async function runTestStep(step, role) {
    const stepElement = document.querySelector(`[data-step="${step}"]`);
    stepElement.classList.add('running');
    stepElement.querySelector('.test-step-icon').textContent = '⚙️';

    try {
        const response = await fetch(`api/test_environment.php?step=${step}&role=${role}`);
        const result = await response.json();

        if (result.success) {
            stepElement.classList.remove('running');
            stepElement.classList.add('success');
            stepElement.querySelector('.test-step-icon').textContent = '✅';
        } else {
            stepElement.classList.remove('running');
            stepElement.classList.add('failed');
            stepElement.querySelector('.test-step-icon').textContent = '❌';
        }

        // Fill details
        const detailsHtml = formatTestDetails(result);
        stepElement.querySelector('.test-step-details').innerHTML = detailsHtml;

        return result;

    } catch (error) {
        stepElement.classList.remove('running');
        stepElement.classList.add('failed');
        stepElement.querySelector('.test-step-icon').textContent = '❌';
        stepElement.querySelector('.test-step-details').innerHTML = `
            <div class="test-check failed">
                <span class="test-check-icon">❌</span>
                <span>API Error: ${error.message}</span>
            </div>
        `;

        return {
            success: false,
            step: step,
            error: error.message
        };
    }
}

function formatTestDetails(result) {
    let html = '';

    if (result.checks && Array.isArray(result.checks)) {
        result.checks.forEach(check => {
            const status = check.passed ? 'success' : 'failed';
            const icon = check.passed ? '✅' : '❌';
            html += `
                <div class="test-check ${status}">
                    <span class="test-check-icon">${icon}</span>
                    <span>${check.message}</span>
                </div>
            `;
        });
    }

    if (result.error) {
        html += `
            <div class="test-check failed">
                <span class="test-check-icon">❌</span>
                <span><strong>Error:</strong> ${result.error}</span>
            </div>
        `;
    }

    if (result.details) {
        html += `
            <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--c-border);">
                <small>${result.details}</small>
            </div>
        `;
    }

    return html;
}

function updateProgress(percent) {
    const bar = document.querySelector('.progress-bar-fill');
    bar.style.width = percent + '%';
    bar.textContent = percent + '%';
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function copyTestResults() {
    const passed = document.getElementById('passedCount').textContent;
    const failed = document.getElementById('failedCount').textContent;
    const duration = document.getElementById('durationTime').textContent;

    let report = `
🧪 WGS E2E TEST REPORT
${'='.repeat(80)}
Role: ${selectedRole}
Datum: ${new Date().toLocaleString('cs-CZ')}
Doba trvání: ${duration}

VÝSLEDKY:
✅ Úspěšné: ${passed}
❌ Selhání: ${failed}
📋 Celkem: 9 kroků

DETAILY KROKŮ:
${'-'.repeat(80)}
`;

    testResults.forEach((result, i) => {
        report += `\n${i + 1}. ${result.step}: ${result.success ? '✅ PASS' : '❌ FAIL'}\n`;
        if (result.checks) {
            result.checks.forEach(check => {
                report += `   ${check.passed ? '✅' : '❌'} ${check.message}\n`;
            });
        }
        if (result.error) {
            report += `   ❌ Error: ${result.error}\n`;
        }
    });

    report += `\n${'='.repeat(80)}\n`;

    navigator.clipboard.writeText(report.trim()).then(() => {
        alert('✅ Výsledky zkopírovány! Vložte CTRL+V do zprávy pro Claude Code');
    });
}
</script>
