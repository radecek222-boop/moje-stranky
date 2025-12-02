<?php
/**
 * Analytics Real-time Dashboard - Admin UI
 *
 * Real-time přehled aktivních návštěvníků s 5-sekundovým auto-refresh.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #11 - Real-time Dashboard
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Dashboard - WGS Analytics</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

    <!-- Leaflet.js pro mapu -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        h1 {
            color: #333333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 20px;
        }

        /* Live Indicator */
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #d4edda;
            color: #155724;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            background: #155724;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #333333;
        }
        .stat-card .label {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .stat-card.humans .value {
            color: #333333;
        }
        .stat-card.bots .value {
            color: #999;
        }

        /* Map */
        .map-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .map-container h3 {
            margin-bottom: 15px;
            color: #333;
        }
        #map {
            height: 400px;
            border-radius: 8px;
        }

        /* Grid layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Active Sessions */
        .sessions-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sessions-container h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .session-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .session-item:hover {
            background: #f9f9f9;
        }
        .session-flag {
            font-size: 24px;
        }
        .session-info {
            flex: 1;
        }
        .session-info .page {
            font-size: 14px;
            color: #333;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .session-info .meta {
            font-size: 12px;
            color: #999;
        }
        .session-duration {
            font-size: 13px;
            color: #666;
            font-weight: bold;
        }
        .session-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            background: #e9ecef;
            color: #495057;
        }
        .session-badge.bot {
            background: #f8d7da;
            color: #721c24;
        }

        /* Live Events */
        .events-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-height: 600px;
            overflow-y: auto;
        }
        .events-container h3 {
            margin-bottom: 15px;
            color: #333;
            position: sticky;
            top: 0;
            background: white;
            padding-bottom: 10px;
            z-index: 10;
        }
        .event-item {
            padding: 10px;
            border-left: 3px solid #333333;
            background: #f9f9f9;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .event-item .event-type {
            font-size: 13px;
            font-weight: bold;
            color: #333333;
            margin-bottom: 4px;
        }
        .event-item .event-meta {
            font-size: 12px;
            color: #666;
        }
        .event-item .event-time {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        /* No data */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ANALYTICS TABS NAVIGACE -->
        <?php require_once __DIR__ . '/includes/analytics_tabs.php'; ?>

        <h1>Real-time Dashboard</h1>
        <p class="subtitle">Živý přehled aktivních návštěvníků a jejich aktivit</p>

        <div class="live-indicator">
            <div class="live-dot"></div>
            <span>ŽIVĚ</span>
            <span id="last-update">Načítání...</span>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card humans">
                <h3>Aktivní Lidé</h3>
                <div class="value" id="humans-count">0</div>
                <div class="label">živých návštěvníků</div>
            </div>
            <div class="stat-card bots">
                <h3>Aktivní Boti</h3>
                <div class="value" id="bots-count">0</div>
                <div class="label">robotů a crawlerů</div>
            </div>
            <div class="stat-card">
                <h3>Země</h3>
                <div class="value" id="countries-count">0</div>
                <div class="label">různých zemí</div>
            </div>
            <div class="stat-card">
                <h3>Průměrná doba</h3>
                <div class="value" id="avg-duration">0s</div>
                <div class="label">trvání session</div>
            </div>
        </div>

        <!-- Map -->
        <div class="map-container">
            <h3>Mapa aktivních návštěvníků</h3>
            <div id="map"></div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Active Sessions -->
            <div class="sessions-container">
                <h3>Aktivní sessions (<span id="sessions-count">0</span>)</h3>
                <div id="sessions-list" class="loading" role="status" aria-live="polite">Načítám sessions...</div>
            </div>

            <!-- Live Events -->
            <div class="events-container">
                <h3>Živé eventy</h3>
                <div id="events-list" class="loading" role="status" aria-live="polite">Načítám eventy...</div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('[name="csrf_token"]').value;
        let map = null;
        let markers = [];
        let autoRefreshInterval = null;

        // Initialize map
        function initMap() {
            map = L.map('map').setView([50, 15], 4);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
        }

        // Load active visitors stats
        async function loadActiveVisitors() {
            try {
                const params = new URLSearchParams({
                    action: 'active_visitors',
                    csrf_token: csrfToken
                });

                const response = await fetch(`/api/analytics_realtime.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success') {
                    document.getElementById('humans-count').textContent = result.humans || 0;
                    document.getElementById('bots-count').textContent = result.bots || 0;
                    document.getElementById('countries-count').textContent = result.countries || 0;
                    document.getElementById('avg-duration').textContent = (result.avg_duration || 0) + 's';

                    const now = new Date();
                    document.getElementById('last-update').textContent = 'Aktualizováno ' + now.toLocaleTimeString('cs-CZ');
                }
            } catch (error) {
                console.error('[Active Visitors] Chyba:', error);
            }
        }

        // Load active sessions
        async function loadActiveSessions() {
            try {
                const params = new URLSearchParams({
                    action: 'active_sessions',
                    limit: 20,
                    csrf_token: csrfToken
                });

                const response = await fetch(`/api/analytics_realtime.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success') {
                    displaySessions(result.sessions, result.total);
                }
            } catch (error) {
                console.error('[Active Sessions] Chyba:', error);
                document.getElementById('sessions-list').innerHTML = '<div class="no-data">Chyba při načítání sessions</div>';
            }
        }

        // Display sessions
        function displaySessions(sessions, total) {
            const container = document.getElementById('sessions-list');
            document.getElementById('sessions-count').textContent = total || 0;

            if (!sessions || sessions.length === 0) {
                container.innerHTML = '<div class="no-data">Žádné aktivní sessions</div>';
                clearMapMarkers();
                return;
            }

            container.innerHTML = '';
            clearMapMarkers();

            sessions.forEach(session => {
                const item = document.createElement('div');
                item.className = 'session-item';

                const flag = getCountryFlag(session.country_code);
                const page = session.current_page_title || session.current_page || 'Neznámá stránka';
                const device = session.device_type || 'unknown';
                const city = session.city || 'Neznámé město';
                const duration = formatDuration(session.session_duration || 0);
                const botBadge = session.is_bot ? '<span class="session-badge bot">BOT</span>' : '';

                item.innerHTML = `
                    <div class="session-flag">${flag}</div>
                    <div class="session-info">
                        <div class="page">${escapeHtml(page)}</div>
                        <div class="meta">${escapeHtml(city)} • ${device} ${botBadge}</div>
                    </div>
                    <div class="session-duration">${duration}</div>
                `;

                container.appendChild(item);

                // Add marker to map if has coordinates
                if (session.latitude && session.longitude) {
                    addMapMarker(session.latitude, session.longitude, page, city);
                }
            });
        }

        // Load live events
        async function loadLiveEvents() {
            try {
                const params = new URLSearchParams({
                    action: 'live_events',
                    limit: 50,
                    csrf_token: csrfToken
                });

                const response = await fetch(`/api/analytics_realtime.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success') {
                    displayEvents(result.events);
                }
            } catch (error) {
                console.error('[Live Events] Chyba:', error);
                document.getElementById('events-list').innerHTML = '<div class="no-data">Chyba při načítání eventů</div>';
            }
        }

        // Display events
        function displayEvents(events) {
            const container = document.getElementById('events-list');

            if (!events || events.length === 0) {
                container.innerHTML = '<div class="no-data">Žádné eventy za posledních 5 minut</div>';
                return;
            }

            container.innerHTML = '';

            events.forEach(event => {
                const item = document.createElement('div');
                item.className = 'event-item';

                const eventType = event.event_type || 'unknown';
                const pageTitle = event.current_page_title || 'Neznámá stránka';
                const city = event.city || 'Neznámé';
                const country = getCountryFlag(event.country_code);
                const time = new Date(event.event_timestamp).toLocaleTimeString('cs-CZ');

                item.innerHTML = `
                    <div class="event-type">${eventType}</div>
                    <div class="event-meta">${escapeHtml(pageTitle)} • ${country} ${escapeHtml(city)}</div>
                    <div class="event-time">${time}</div>
                `;

                container.appendChild(item);
            });
        }

        // Map utilities
        function clearMapMarkers() {
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];
        }

        function addMapMarker(lat, lng, title, city) {
            const marker = L.marker([lat, lng]).addTo(map);
            marker.bindPopup(`<strong>${escapeHtml(title)}</strong><br>${escapeHtml(city)}`);
            markers.push(marker);
        }

        // Utilities
        function getCountryFlag(countryCode) {
            if (!countryCode || countryCode.length !== 2) return '🌍';
            const codePoints = countryCode
                .toUpperCase()
                .split('')
                .map(char => 127397 + char.charCodeAt());
            return String.fromCodePoint(...codePoints);
        }

        function formatDuration(seconds) {
            if (seconds < 60) return seconds + 's';
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${minutes}m ${secs}s`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-refresh every 5 seconds
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                loadActiveVisitors();
                loadActiveSessions();
                loadLiveEvents();
            }, 5000);
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }

        // Initialize on page load
        window.addEventListener('DOMContentLoaded', () => {
            initMap();
            loadActiveVisitors();
            loadActiveSessions();
            loadLiveEvents();
            startAutoRefresh();
        });

        // Stop refresh when page is hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });
    </script>
</body>
</html>
