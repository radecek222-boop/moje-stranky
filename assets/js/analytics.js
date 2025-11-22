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
    checkAuth();
    nactiData();

    // Event listeners pro time period
    document.querySelectorAll('.time-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const period = btn.getAttribute('data-timeperiod');
            nastavCasoveObdobi(period);
        });
    });
});

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
    ANALYTICS.timePeriod = period;

    // Aktualizovat aktivní tlačítko
    document.querySelectorAll('.time-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-timeperiod') === period) {
            btn.classList.add('active');
        }
    });

    // Znovu načíst data
    nactiData();
}

// === LOAD DATA ===
async function nactiData() {
    logger.log('📡 Načítám analytics data pro období:', ANALYTICS.timePeriod);

    try {
        const response = await fetch(`/api/analytics_api.php?period=${ANALYTICS.timePeriod}`);

        logger.log('📊 Response status:', response.status);

        if (!response.ok) {
            logger.error('❌ Response není OK!');
            return;
        }

        const data = await response.json();
        logger.log('📦 Přijatá data:', data);

        if (data.status === 'success') {
            ANALYTICS.data.stats = data.data.stats || {};
            ANALYTICS.data.visits = data.data.visits || [];
            ANALYTICS.data.events = data.data.events || [];

            logger.log('✅ Data úspěšně načtena');
            aktualizovatUI();
        } else {
            logger.error('❌ API error:', data.message);
        }

    } catch (error) {
        logger.error('❌ Fetch error:', error);
    }
}

// === UPDATE UI ===
function aktualizovatUI() {
    const stats = ANALYTICS.data.stats;

    if (!stats) {
        logger.error('⚠️ Žádná stats data');
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

    // Změny (placeholder)
    document.getElementById('visits-change').innerHTML = '↑ +15% od minulého období';
    document.getElementById('visits-change').className = 'stat-change positive';

    document.getElementById('unique-change').innerHTML = '↑ +8% od minulého období';
    document.getElementById('unique-change').className = 'stat-change positive';

    document.getElementById('duration-change').innerHTML = '↓ -5% od minulého období';
    document.getElementById('duration-change').className = 'stat-change negative';

    document.getElementById('bounce-change').innerHTML = '↓ -3% od minulého období';
    document.getElementById('bounce-change').className = 'stat-change positive';

    document.getElementById('conversion-change').innerHTML = '↑ +1.2% od minulého období';
    document.getElementById('conversion-change').className = 'stat-change positive';

    logger.log('✅ UI úspěšně aktualizováno');
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

    // Prevent body scroll when menu is open
    if (mobileMenu && mobileMenu.classList.contains('show')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
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
