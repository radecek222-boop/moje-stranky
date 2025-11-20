<?php
/**
 * Admin Panel - PHPUnit Test Runner
 *
 * UI pro spouštění automatizovaných testů přímo z Admin Dashboard
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Neautorizovaný přístup.');
}
?>

<style>
.phpunit-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.phpunit-header {
    background: linear-gradient(135deg, #2D5016 0%, #1a300d 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(45, 80, 22, 0.3);
}

.phpunit-header h1 {
    margin: 0 0 10px 0;
    font-size: 2em;
}

.phpunit-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.phpunit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.phpunit-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.phpunit-card:hover {
    border-color: #2D5016;
    box-shadow: 0 4px 20px rgba(45, 80, 22, 0.15);
    transform: translateY(-2px);
}

.phpunit-card h3 {
    margin: 0 0 15px 0;
    color: #2D5016;
    font-size: 1.3em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.phpunit-card p {
    margin: 0 0 20px 0;
    color: #666;
    line-height: 1.6;
}

.phpunit-btn {
    display: inline-block;
    padding: 12px 24px;
    background: #2D5016;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1em;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    width: 100%;
    text-align: center;
}

.phpunit-btn:hover {
    background: #1a300d;
    box-shadow: 0 4px 12px rgba(45, 80, 22, 0.3);
}

.phpunit-btn:disabled {
    background: #cccccc;
    cursor: not-allowed;
}

.phpunit-btn.secondary {
    background: #6c757d;
}

.phpunit-btn.secondary:hover {
    background: #5a6268;
}

.phpunit-btn.success {
    background: #28a745;
}

.phpunit-btn.success:hover {
    background: #218838;
}

.phpunit-output {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 25px;
    border-radius: 12px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 600px;
    overflow-y: auto;
    margin-top: 20px;
    box-shadow: inset 0 2px 10px rgba(0,0,0,0.3);
    display: none;
}

.phpunit-output.show {
    display: block;
}

.phpunit-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.phpunit-stat {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border-left: 4px solid #2D5016;
}

.phpunit-stat-value {
    font-size: 2em;
    font-weight: bold;
    color: #2D5016;
}

.phpunit-stat-label {
    font-size: 0.9em;
    color: #666;
    margin-top: 5px;
}

.phpunit-loading {
    display: none;
    text-align: center;
    padding: 30px;
}

.phpunit-loading.show {
    display: block;
}

.phpunit-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #2D5016;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.progress-container {
    width: 100%;
    max-width: 500px;
    margin: 20px auto;
}

.progress-bar-wrapper {
    width: 100%;
    height: 30px;
    background: #e0e0e0;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #2D5016 0%, #4a7f29 100%);
    width: 0%;
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.85rem;
}

.progress-text {
    margin-top: 10px;
    color: #666;
    font-size: 0.9rem;
}

.phpunit-status {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: none;
}

.phpunit-status.show {
    display: block;
}

.phpunit-status.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.phpunit-status.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.phpunit-status.info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.icon {
    font-size: 1.2em;
}

.phpunit-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.phpunit-info-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.phpunit-info-item strong {
    display: block;
    color: #2D5016;
    margin-bottom: 5px;
}

.phpunit-coverage {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 25px;
    margin-top: 20px;
}

.phpunit-coverage h3 {
    margin-top: 0;
    color: #2D5016;
}

.coverage-bar {
    width: 100%;
    height: 30px;
    background: #e0e0e0;
    border-radius: 15px;
    overflow: hidden;
    margin-top: 10px;
}

.coverage-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
    transition: width 0.5s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}
</style>

<div class="phpunit-container">
    <!-- Header -->
    <div class="phpunit-header">
        <h1>🧪 PHPUnit Test Runner</h1>
        <p>Automatizované testování WGS Service - spouštějte testy přímo z Admin Dashboardu</p>
    </div>

    <!-- Status Messages -->
    <div id="phpunit-status" class="phpunit-status"></div>

    <!-- System Info -->
    <div class="phpunit-card" id="system-info-card">
        <h3><span class="icon">ℹ️</span> Systémové informace</h3>
        <p>Kontrola dostupnosti PHPUnit a závislostí</p>
        <button class="phpunit-btn secondary" onclick="zkontrolovatSystem()">
            Zkontrolovat systém
        </button>
        <div id="system-info" class="phpunit-info-grid"></div>
    </div>

    <!-- Test Suites -->
    <div class="phpunit-grid">
        <!-- All Tests -->
        <div class="phpunit-card">
            <h3><span class="icon">🎯</span> Všechny testy</h3>
            <p>Spustí kompletní test suite (100+ testů)</p>
            <button class="phpunit-btn" onclick="spustitVsechnyTesty()">
                Spustit všechny testy
            </button>
        </div>

        <!-- Security Tests -->
        <div class="phpunit-card">
            <h3><span class="icon">🔒</span> Security</h3>
            <p>CSRF, Rate Limiter (15+ testů)</p>
            <button class="phpunit-btn" onclick="spustitTestSuite('Security')">
                Spustit Security testy
            </button>
        </div>

        <!-- Controller Tests -->
        <div class="phpunit-card">
            <h3><span class="icon">⚙️</span> Controllers</h3>
            <p>Save Controller, validace (25+ testů)</p>
            <button class="phpunit-btn" onclick="spustitTestSuite('Controllers')">
                Spustit Controller testy
            </button>
        </div>

        <!-- Utils Tests -->
        <div class="phpunit-card">
            <h3><span class="icon">🛠️</span> Utils</h3>
            <p>Email Queue, pomocné funkce (20+ testů)</p>
            <button class="phpunit-btn" onclick="spustitTestSuite('Utils')">
                Spustit Utils testy
            </button>
        </div>

        <!-- Integration Tests -->
        <div class="phpunit-card">
            <h3><span class="icon">🔗</span> Integration</h3>
            <p>API Security, integrace (10+ testů)</p>
            <button class="phpunit-btn" onclick="spustitTestSuite('Integration')">
                Spustit Integration testy
            </button>
        </div>

        <!-- Coverage Report -->
        <div class="phpunit-card">
            <h3><span class="icon">📊</span> Coverage Report</h3>
            <p>Test coverage s Xdebug</p>
            <button class="phpunit-btn success" onclick="spustitCoverage()">
                Vygenerovat Coverage
            </button>
        </div>
    </div>

    <!-- Install Dependencies -->
    <div class="phpunit-card">
        <h3><span class="icon">📦</span> Composer závislosti</h3>
        <p>Pokud PHPUnit není nainstalován, klikněte zde pro instalaci závislostí</p>
        <button class="phpunit-btn secondary" onclick="nainstavovatZavislosti()">
            Spustit composer install
        </button>
    </div>

    <!-- Loading Indicator -->
    <div id="phpunit-loading" class="phpunit-loading">
        <div class="phpunit-spinner"></div>
        <p id="loading-message">Spouštím testy, prosím čekejte...</p>
        <div class="progress-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar-fill" id="progress-bar">0%</div>
            </div>
            <div class="progress-text" id="progress-text">Inicializace...</div>
        </div>
    </div>

    <!-- Test Results -->
    <div id="test-results" style="display: none;">
        <div class="phpunit-card">
            <h3><span class="icon">📈</span> Výsledky testů</h3>
            <div class="phpunit-stats" id="phpunit-stats"></div>
        </div>
    </div>

    <!-- Coverage Results -->
    <div id="coverage-results" class="phpunit-coverage" style="display: none;">
        <h3><span class="icon">📊</span> Code Coverage</h3>
        <div class="coverage-bar">
            <div class="coverage-bar-fill" id="coverage-bar" style="width: 0%">0%</div>
        </div>
    </div>

    <!-- Output -->
    <div id="phpunit-output" class="phpunit-output"></div>
</div>

<script>
// CSRF token
const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

/**
 * Zobrazí status zprávu
 */
function zobrazStatus(typ, zprava) {
    const statusDiv = document.getElementById('phpunit-status');
    statusDiv.className = `phpunit-status show ${typ}`;
    statusDiv.textContent = zprava;

    setTimeout(() => {
        statusDiv.classList.remove('show');
    }, 5000);
}

/**
 * Zobrazí loading indikátor
 */
function zobrazLoading(zobrazit) {
    const loadingDiv = document.getElementById('phpunit-loading');
    loadingDiv.classList.toggle('show', zobrazit);

    // Deaktivovat všechna tlačítka
    document.querySelectorAll('.phpunit-btn').forEach(btn => {
        btn.disabled = zobrazit;
    });

    // Reset progress bar při zobrazení
    if (zobrazit) {
        aktualizovatProgress(0, 'Inicializace...');
    }
}

/**
 * Aktualizuje progress bar
 */
function aktualizovatProgress(procenta, text, zprava = null) {
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const loadingMessage = document.getElementById('loading-message');

    if (progressBar) {
        progressBar.style.width = procenta + '%';
        progressBar.textContent = procenta + '%';
    }

    if (progressText && text) {
        progressText.textContent = text;
    }

    if (loadingMessage && zprava) {
        loadingMessage.textContent = zprava;
    }
}

/**
 * Zobrazí výstup testů
 */
function zobrazVystup(vystup) {
    const outputDiv = document.getElementById('phpunit-output');
    outputDiv.textContent = vystup;
    outputDiv.classList.add('show');
    outputDiv.scrollTop = outputDiv.scrollHeight;
}

/**
 * Zobrazí statistiky testů
 */
function zobrazStatistiky(vysledek) {
    const statsDiv = document.getElementById('phpunit-stats');
    const resultsDiv = document.getElementById('test-results');

    statsDiv.innerHTML = `
        <div class="phpunit-stat">
            <div class="phpunit-stat-value">${vysledek.celkem_testu}</div>
            <div class="phpunit-stat-label">Celkem testů</div>
        </div>
        <div class="phpunit-stat">
            <div class="phpunit-stat-value" style="color: #28a745;">${vysledek.uspesnych}</div>
            <div class="phpunit-stat-label">Úspěšných</div>
        </div>
        <div class="phpunit-stat">
            <div class="phpunit-stat-value" style="color: #dc3545;">${vysledek.selhanych}</div>
            <div class="phpunit-stat-label">Selhalo</div>
        </div>
        <div class="phpunit-stat">
            <div class="phpunit-stat-value">${vysledek.assertions}</div>
            <div class="phpunit-stat-label">Assertions</div>
        </div>
        <div class="phpunit-stat">
            <div class="phpunit-stat-value">${vysledek.cas || 'N/A'}</div>
            <div class="phpunit-stat-label">Čas</div>
        </div>
        <div class="phpunit-stat">
            <div class="phpunit-stat-value">${vysledek.pamet || 'N/A'}</div>
            <div class="phpunit-stat-label">Paměť</div>
        </div>
    `;

    resultsDiv.style.display = 'block';
}

/**
 * Spustí všechny testy
 */
async function spustitVsechnyTesty() {
    zobrazLoading(true);
    aktualizovatProgress(10, 'Příprava testů...', 'Spouštím všechny testy...');

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('akce', 'spustit_testy');

        // Simulace progressu během běhu testů
        setTimeout(() => aktualizovatProgress(30, 'Spouštím testy...'), 500);
        setTimeout(() => aktualizovatProgress(50, 'Probíhá testování...'), 1500);
        setTimeout(() => aktualizovatProgress(70, 'Zpracovávám výsledky...'), 3000);

        const odpoved = await fetch('/api/phpunit_runner_api.php', {
            method: 'POST',
            body: formData
        });

        aktualizovatProgress(90, 'Dokončuji...', 'Zpracovávám výsledky...');

        const data = await odpoved.json();

        aktualizovatProgress(100, 'Hotovo!', 'Testy dokončeny');

        if (data.status === 'success' || data.status === 'warning') {
            zobrazStatus(data.status, data.message);
            zobrazVystup(data.vystup);
            zobrazStatistiky(data.vysledek);
        } else {
            zobrazStatus('error', data.message);
        }
    } catch (error) {
        zobrazStatus('error', 'Chyba při spouštění testů: ' + error.message);
    } finally {
        zobrazLoading(false);
    }
}

/**
 * Spustí konkrétní test suite
 */
async function spustitTestSuite(testsuite) {
    zobrazLoading(true);
    aktualizovatProgress(10, 'Příprava test suite...', `Spouštím test suite: ${testsuite}`);

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('akce', 'spustit_testsuite');
        formData.append('testsuite', testsuite);

        setTimeout(() => aktualizovatProgress(30, 'Spouštím testy...'), 300);
        setTimeout(() => aktualizovatProgress(60, 'Probíhá testování...'), 1000);

        const odpoved = await fetch('/api/phpunit_runner_api.php', {
            method: 'POST',
            body: formData
        });

        aktualizovatProgress(90, 'Dokončuji...', 'Zpracovávám výsledky...');

        const data = await odpoved.json();

        aktualizovatProgress(100, 'Hotovo!', 'Test suite dokončen');

        if (data.status === 'success' || data.status === 'warning') {
            zobrazStatus(data.status, data.message);
            zobrazVystup(data.vystup);
            zobrazStatistiky(data.vysledek);
        } else {
            zobrazStatus('error', data.message);
        }
    } catch (error) {
        zobrazStatus('error', 'Chyba: ' + error.message);
    } finally {
        zobrazLoading(false);
    }
}

/**
 * Spustí coverage report
 */
async function spustitCoverage() {
    zobrazLoading(true);
    aktualizovatProgress(10, 'Příprava coverage analýzy...', 'Generuji coverage report...');

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('akce', 'spustit_coverage');

        setTimeout(() => aktualizovatProgress(25, 'Analyzuji kód...'), 500);
        setTimeout(() => aktualizovatProgress(50, 'Generuji report...'), 2000);
        setTimeout(() => aktualizovatProgress(75, 'Finalizuji coverage...'), 4000);

        const odpoved = await fetch('/api/phpunit_runner_api.php', {
            method: 'POST',
            body: formData
        });

        aktualizovatProgress(90, 'Zpracovávám výsledky...', 'Dokončuji...');

        const data = await odpoved.json();

        aktualizovatProgress(100, 'Hotovo!', 'Coverage report dokončen');

        if (data.status === 'success' || data.status === 'warning') {
            zobrazStatus(data.status, data.message);
            zobrazVystup(data.vystup);
            zobrazStatistiky(data.vysledek);

            if (data.coverage && data.coverage.celkove_pokryti) {
                const coverageDiv = document.getElementById('coverage-results');
                const coverageBar = document.getElementById('coverage-bar');
                coverageDiv.style.display = 'block';
                coverageBar.style.width = data.coverage.celkove_pokryti + '%';
                coverageBar.textContent = data.coverage.celkove_pokryti.toFixed(2) + '%';
            }
        } else {
            zobrazStatus('error', data.message);
        }
    } catch (error) {
        zobrazStatus('error', 'Chyba: ' + error.message);
    } finally {
        zobrazLoading(false);
    }
}

/**
 * Zkontroluje systém
 */
async function zkontrolovatSystem() {
    zobrazLoading(true);

    try {
        const odpoved = await fetch('/api/phpunit_runner_api.php?akce=zkontrolovat_phpunit');

        // Kontrola HTTP statusu
        if (!odpoved.ok) {
            const errorData = await odpoved.json().catch(() => ({ message: 'Neznámá chyba' }));

            // Speciální zpracování pro HTTP 503 (exec() zakázán)
            if (odpoved.status === 503) {
                const infoDiv = document.getElementById('system-info');
                infoDiv.innerHTML = `
                    <div style="padding: 1.5rem; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; margin: 1rem 0;">
                        <h3 style="color: #856404; margin-top: 0; font-size: 1.1rem;">⚠️ PHPUnit Test Runner není dostupný</h3>
                        <p style="margin: 0.5rem 0; color: #856404;"><strong>Důvod:</strong> ${errorData.message}</p>
                        ${errorData.detail ? `<p style="margin: 0.5rem 0; color: #856404; font-size: 0.9rem;">${errorData.detail}</p>` : ''}
                        ${errorData.disabled_functions ? `
                            <details style="margin-top: 1rem; color: #856404; font-size: 0.85rem;">
                                <summary style="cursor: pointer; font-weight: 600;">Zakázané funkce na serveru</summary>
                                <pre style="background: #fff; padding: 0.5rem; border-radius: 4px; margin-top: 0.5rem; overflow-x: auto;">${errorData.disabled_functions}</pre>
                            </details>
                        ` : ''}
                    </div>
                `;
                zobrazLoading(false);
                return;
            }

            throw new Error(`HTTP ${odpoved.status}: ${errorData.message || 'Přístup odepřen'}`);
        }

        const data = await odpoved.json();

        if (data.status === 'success') {
            zobrazStatus('info', data.message);

            const infoDiv = document.getElementById('system-info');
            const info = data.info;

            infoDiv.innerHTML = `
                <div class="phpunit-info-item">
                    <strong>PHPUnit:</strong>
                    ${info.phpunit_existuje ? '✅ Nainstalován' : '❌ Není nainstalován'}
                </div>
                <div class="phpunit-info-item">
                    <strong>Composer:</strong>
                    ${info.composer_existuje ? '✅ composer.json existuje' : '❌ Chybí composer.json'}
                </div>
                <div class="phpunit-info-item">
                    <strong>Vendor:</strong>
                    ${info.vendor_existuje ? '✅ vendor/ existuje' : '❌ Chybí vendor/'}
                </div>
                <div class="phpunit-info-item">
                    <strong>PHP Verze:</strong>
                    ${info.php_verze}
                </div>
                <div class="phpunit-info-item">
                    <strong>Xdebug:</strong>
                    ${info.xdebug_nainstalovano ? '✅ Nainstalován' : '⚠️ Není nainstalován (coverage nebude fungovat)'}
                </div>
                ${info.phpunit_verze ? `
                <div class="phpunit-info-item">
                    <strong>PHPUnit Verze:</strong>
                    ${info.phpunit_verze}
                </div>
                ` : ''}
            `;
        } else {
            zobrazStatus('error', data.message);
        }
    } catch (error) {
        zobrazStatus('error', 'Chyba: ' + error.message);
    } finally {
        zobrazLoading(false);
    }
}

/**
 * Nainstaluje závislosti
 */
async function nainstavovatZavislosti() {
    if (!confirm('Opravdu chcete spustit composer install? Může to trvat několik minut.')) {
        return;
    }

    zobrazLoading(true);
    aktualizovatProgress(5, 'Příprava instalace...', 'Spouštím composer install...');

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('akce', 'nainstalovat_zavislosti');

        // Pomalejší progress pro dlouhodobou operaci
        setTimeout(() => aktualizovatProgress(15, 'Stahuji balíčky...'), 1000);
        setTimeout(() => aktualizovatProgress(30, 'Instaluji závislosti...'), 3000);
        setTimeout(() => aktualizovatProgress(50, 'Kompiluji autoloader...'), 6000);
        setTimeout(() => aktualizovatProgress(70, 'Optimalizuji balíčky...'), 9000);
        setTimeout(() => aktualizovatProgress(85, 'Finalizuji instalaci...'), 12000);

        const odpoved = await fetch('/api/phpunit_runner_api.php', {
            method: 'POST',
            body: formData
        });

        aktualizovatProgress(95, 'Dokončuji...', 'Kontroluji instalaci...');

        const data = await odpoved.json();

        aktualizovatProgress(100, 'Hotovo!', 'Instalace dokončena');

        if (data.status === 'success') {
            zobrazStatus('success', data.message);
            zobrazVystup(data.vystup);

            // Automaticky zkontrolovat systém po instalaci
            setTimeout(() => zkontrolovatSystem(), 1000);
        } else {
            zobrazStatus('error', data.message);
        }
    } catch (error) {
        zobrazStatus('error', 'Chyba: ' + error.message);
    } finally {
        zobrazLoading(false);
    }
}

// Auto-check při načtení stránky
document.addEventListener('DOMContentLoaded', () => {
    zkontrolovatSystem();
});
</script>
