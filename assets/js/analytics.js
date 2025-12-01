/**
 * Web Analytics Dashboard
 * Načítá a zobrazuje skutečná data z analytics API
 */

// === GLOBALS ===
const ANALYTICS = {
    currentUser: null,
    timePeriod: 'week',
    data: {
        stats: {},
        visits: [],
        events: []
    }
};

// === INIT ===
window.addEventListener('DOMContentLoaded', () => {
    logger.log('[Start] Analytics dashboard inicialization...');
    checkAuth();
    nactiData();
    inicializovatEventListeners();
});

// === EVENT LISTENERS ===
function inicializovatEventListeners() {
    // Time period buttons
    document.querySelectorAll('.time-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const period = btn.getAttribute('data-timeperiod');
            logger.log('⏰ Změna období na:', period);
            nastavCasoveObdobi(period);
        });
    });

    logger.log('Event listeners registrovány');
}

// === AUTH ===
async function checkAuth() {
    try {
        const response = await fetch('app/admin_session_check.php');
        if (response.ok) {
            const result = await response.json();
            if (result.authenticated) {
                ANALYTICS.currentUser = {
                    name: result.username || 'Admin',
                    email: result.email || 'admin@wgs.cz',
                    role: result.role || 'admin'
                };
                document.getElementById('userName').textContent = ANALYTICS.currentUser.name;
                return;
            }
        }
    } catch (err) {
        console.error('Session check error:', err);
    }
    window.location.href = 'login.php';
}

// === TIME PERIOD ===
function nastavCasoveObdobi(period) {
    logger.log('📅 Nastavuji časové období:', period);

    ANALYTICS.timePeriod = period;

    // Aktualizovat aktivní tlačítko
    document.querySelectorAll('.time-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-timeperiod') === period) {
            btn.classList.add('active');
        }
    });

    // Zobrazit loading state
    zobrazitNacitani();

    // Znovu načíst data
    nactiData();
}

function zobrazitNacitani() {
    document.getElementById('total-visits').textContent = '-';
    document.getElementById('unique-visitors').textContent = '-';
    document.getElementById('avg-duration').textContent = '-';
    document.getElementById('bounce-rate').textContent = '-';
    document.getElementById('conversion-rate').textContent = '-';

    document.getElementById('visits-change').textContent = 'Načítání...';
    document.getElementById('unique-change').textContent = 'Načítání...';
    document.getElementById('duration-change').textContent = 'Načítání...';
    document.getElementById('bounce-change').textContent = 'Načítání...';
    document.getElementById('conversion-change').textContent = 'Načítání...';
}

// === LOAD DATA ===
async function nactiData() {
    logger.log('📡 Načítám analytics data pro období:', ANALYTICS.timePeriod);

    try {
        const response = await fetch(`/api/analytics_api.php?period=${ANALYTICS.timePeriod}`);

        logger.log('[Stats] Response status:', response.status);

        if (!response.ok) {
            logger.error('Response není OK!');
            return;
        }

        const data = await response.json();
        logger.log('📦 Přijatá data:', data);

        if (data.status === 'success') {
            ANALYTICS.data.stats = data.data.stats || {};
            ANALYTICS.data.topPages = data.data.topPages || [];
            ANALYTICS.data.referrers = data.data.referrers || [];
            ANALYTICS.data.browsersDevices = data.data.browsersDevices || {browsers: [], devices: []};
            ANALYTICS.data.timeline = data.data.timeline || [];

            logger.log('Data úspěšně načtena');
            aktualizovatUI();
        } else {
            logger.error('API error:', data.message);
        }

    } catch (error) {
        logger.error('Fetch error:', error);
    }
}

// === UPDATE UI ===
function aktualizovatUI() {
    const stats = ANALYTICS.data.stats;

    if (!stats) {
        logger.error('Žádná stats data');
        return;
    }

    logger.log('🎨 Aktualizuji UI s daty:', stats);

    // Hlavní metriky
    document.getElementById('total-visits').textContent = formatNumber(stats.totalVisits || 0);
    document.getElementById('unique-visitors').textContent = formatNumber(stats.uniqueVisitors || 0);
    document.getElementById('avg-duration').textContent = formatDuration(stats.avgDuration || 0);
    document.getElementById('bounce-rate').textContent = (stats.bounceRate || 0) + '%';
    document.getElementById('conversion-rate').textContent = (stats.conversionRate || 0).toFixed(1) + '%';

    // Online návštěvníci (simulace)
    document.getElementById('online-now').textContent = Math.floor(Math.random() * 15) + 5;

    // Získat text období pro change labels
    const periodText = getPeriodText();

    // Změny (placeholder - v budoucnu možné porovnání s předchozím obdobím)
    document.getElementById('visits-change').innerHTML = `Za ${periodText}`;
    document.getElementById('visits-change').className = 'stat-change';

    document.getElementById('unique-change').innerHTML = `Za ${periodText}`;
    document.getElementById('unique-change').className = 'stat-change';

    document.getElementById('duration-change').innerHTML = `Za ${periodText}`;
    document.getElementById('duration-change').className = 'stat-change';

    document.getElementById('bounce-change').innerHTML = `Za ${periodText}`;
    document.getElementById('bounce-change').className = 'stat-change';

    document.getElementById('conversion-change').innerHTML = `Za ${periodText}`;
    document.getElementById('conversion-change').className = 'stat-change';

    // Detailní analytics
    zobrazitTopStranky();
    zobrazitReferrery();
    zobrazitProhlizece();
    zobrazitZarizeni();
    vykreslitGraf();

    logger.log('UI úspěšně aktualizováno');
}

function getPeriodText() {
    switch(ANALYTICS.timePeriod) {
        case 'today':
            return 'dnes';
        case 'week':
            return 'posledních 7 dní';
        case 'month':
            return 'posledních 30 dní';
        case 'year':
            return 'posledních 365 dní';
        default:
            return 'zvolené období';
    }
}

// === HELPERS ===
function formatNumber(num) {
    return new Intl.NumberFormat('cs-CZ').format(num);
}

function formatDuration(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// === DETAILNÍ ANALYTICS ===

function zobrazitTopStranky() {
    const tbody = document.querySelector('#top-pages-table tbody');
    const pages = ANALYTICS.data.topPages || [];

    if (pages.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #999;">Žádná data k zobrazení</td></tr>';
        return;
    }

    tbody.innerHTML = pages.map(page => `
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 0.75rem;">
                <div style="font-weight: 500; color: #1d1f2c;">${page.page_title || page.page_url}</div>
                <div style="font-size: 0.75rem; color: #6b7280;">${page.page_url}</div>
            </td>
            <td style="padding: 0.75rem;">${formatNumber(page.visits)}</td>
            <td style="padding: 0.75rem;">${formatNumber(page.unique_visitors)}</td>
            <td style="padding: 0.75rem;">${formatDuration(Math.round(page.avg_duration))}</td>
        </tr>
    `).join('');
}

function zobrazitReferrery() {
    const tbody = document.querySelector('#referrers-table tbody');
    const referrers = ANALYTICS.data.referrers || [];

    if (referrers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; padding: 2rem; color: #999;">Žádná data k zobrazení</td></tr>';
        return;
    }

    tbody.innerHTML = referrers.map(ref => `
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 0.75rem; font-weight: 500;">${ref.referrer_source}</td>
            <td style="padding: 0.75rem;">${formatNumber(ref.visits)}</td>
            <td style="padding: 0.75rem;">${formatNumber(ref.unique_visitors)}</td>
        </tr>
    `).join('');
}

function zobrazitProhlizece() {
    const tbody = document.querySelector('#browsers-table tbody');
    const browsers = ANALYTICS.data.browsersDevices?.browsers || [];

    if (browsers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" style="text-align: center; padding: 2rem; color: #999;">Žádná data k zobrazení</td></tr>';
        return;
    }

    tbody.innerHTML = browsers.map(browser => {
        const browserName = parsujUserAgent(browser.user_agent);
        return `
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 0.75rem; font-weight: 500;">${browserName}</td>
                <td style="padding: 0.75rem;">${formatNumber(browser.visits)}</td>
            </tr>
        `;
    }).join('');
}

function zobrazitZarizeni() {
    const tbody = document.querySelector('#devices-table tbody');
    const devices = ANALYTICS.data.browsersDevices?.devices || [];

    if (devices.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" style="text-align: center; padding: 2rem; color: #999;">Žádná data k zobrazení</td></tr>';
        return;
    }

    tbody.innerHTML = devices.map(device => `
        <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 0.75rem; font-weight: 500;">${device.screen_resolution}</td>
            <td style="padding: 0.75rem;">${formatNumber(device.visits)}</td>
        </tr>
    `).join('');
}

let visitsChart = null;

function vykreslitGraf() {
    const canvas = document.getElementById('visits-chart');
    if (!canvas) return;

    const timeline = ANALYTICS.data.timeline || [];

    if (timeline.length === 0) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.font = '14px Poppins';
        ctx.fillStyle = '#999';
        ctx.textAlign = 'center';
        ctx.fillText('Žádná data k zobrazení', canvas.width / 2, canvas.height / 2);
        return;
    }

    // Zničit předchozí graf
    if (visitsChart) {
        visitsChart.destroy();
    }

    const labels = timeline.map(t => t.time_period);
    const visits = timeline.map(t => parseInt(t.visits));
    const unique = timeline.map(t => parseInt(t.unique_visitors));

    const ctx = canvas.getContext('2d');
    visitsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Celkem návštěv',
                    data: visits,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Unikátní návštěvníci',
                    data: unique,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            family: 'Poppins',
                            size: 12
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: {
                            family: 'Poppins'
                        }
                    }
                },
                x: {
                    ticks: {
                        font: {
                            family: 'Poppins',
                            size: 11
                        },
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
}

function parsujUserAgent(ua) {
    if (!ua) return 'Neznámý';
    if (ua.includes('Chrome')) return 'Chrome';
    if (ua.includes('Firefox')) return 'Firefox';
    if (ua.includes('Safari')) return 'Safari';
    if (ua.includes('Edge')) return 'Edge';
    if (ua.includes('Opera')) return 'Opera';
    return 'Ostatní';
}

// === EXPORT ===
function exportAnalytics(format) {
    if (format === 'csv') {
        exportToCSV();
    } else if (format === 'pdf') {
        alert('PDF export - připraveno pro budoucí implementaci');
    }
}

function exportToCSV() {
    const BOM = '\uFEFF';
    const now = new Date();
    const dateStr = now.toLocaleDateString('cs-CZ');
    const timeStr = now.toLocaleTimeString('cs-CZ');

    let csv = BOM + 'WHITE GLOVE SERVICE - WEB ANALYTICS\n';
    csv += `Datum exportu: ${dateStr} ${timeStr}\n`;
    csv += `Období: ${ANALYTICS.timePeriod}\n\n`;

    csv += 'HLAVNÍ METRIKY\n';
    csv += 'Metrika;Hodnota\n';
    csv += `Celkem návštěv;${ANALYTICS.data.stats.totalVisits || 0}\n`;
    csv += `Unikátní návštěvníci;${ANALYTICS.data.stats.uniqueVisitors || 0}\n`;
    csv += `Průměrná doba;${formatDuration(ANALYTICS.data.stats.avgDuration || 0)}\n`;
    csv += `Bounce rate;${ANALYTICS.data.stats.bounceRate || 0}%\n`;
    csv += `Konverze;${ANALYTICS.data.stats.conversionRate || 0}%\n\n`;

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `WGS_Analytics_${now.toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

// === LOGOUT ===
function logout() {
    window.location.href = 'logout.php';
}

// === MOBILE MENU ===
function toggleMobileMenu() {
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');
    const backdrop = document.getElementById('mobileMenuBackdrop');

    if (hamburger) hamburger.classList.toggle('active');
    if (mobileMenu) mobileMenu.classList.toggle('show');
    if (backdrop) backdrop.classList.toggle('show');

    // Scroll-lock pres centralizovanou utilitu (iOS kompatibilni)
    if (window.scrollLock) {
        if (mobileMenu && mobileMenu.classList.contains('show')) {
            window.scrollLock.enable('analytics-menu');
        } else {
            window.scrollLock.disable('analytics-menu');
        }
    }
}

function navigateTo(url) {
    // Close mobile menu before navigation
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileMenu && mobileMenu.classList.contains('show')) {
        toggleMobileMenu();
    }

    // Small delay for smooth transition
    setTimeout(() => {
        window.location.href = url;
    }, 300);
}
