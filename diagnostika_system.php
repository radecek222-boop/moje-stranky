<?php
/**
 * Diagnostika celého systému - Session keep-alive + Auto-save fotek
 *
 * Zobrazí LIVE status všech kritických komponent
 */
require_once __DIR__ . '/init.php';

// Pouze pro přihlášené
if (!isset($_SESSION['user_id'])) {
    die('Musíte být přihlášeni');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Diagnostika systému - WGS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #000;
            color: #0f0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            font-size: 2em;
            margin-bottom: 30px;
            text-shadow: 0 0 10px #0f0;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            border: 2px solid #0f0;
            padding: 20px;
            border-radius: 10px;
            background: rgba(0, 255, 0, 0.05);
        }

        .status-card h2 {
            margin-bottom: 15px;
            font-size: 1.2em;
            border-bottom: 1px solid #0f0;
            padding-bottom: 10px;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 10px;
            background: rgba(0, 255, 0, 0.1);
            border-radius: 5px;
        }

        .status-ok {
            color: #0f0;
            font-weight: bold;
        }

        .status-error {
            color: #f00;
            font-weight: bold;
        }

        .status-warning {
            color: #ff0;
            font-weight: bold;
        }

        .log-box {
            border: 2px solid #0f0;
            padding: 20px;
            border-radius: 10px;
            background: rgba(0, 255, 0, 0.05);
            max-height: 400px;
            overflow-y: auto;
        }

        .log-entry {
            padding: 5px 0;
            border-bottom: 1px solid rgba(0, 255, 0, 0.2);
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        .timestamp {
            color: #888;
            font-size: 0.9em;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0f0;
            color: #000;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
            text-decoration: none;
        }

        .btn:hover {
            background: #0c0;
        }

        .btn-test {
            background: #ff0;
        }

        .btn-test:hover {
            background: #cc0;
        }

        #liveStatus {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .big-status {
            text-align: center;
            font-size: 3em;
            margin: 20px 0;
            text-shadow: 0 0 20px currentColor;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 DIAGNOSTIKA SYSTÉMU WGS</h1>

    <div id="liveStatus" class="big-status status-ok">● SYSTÉM BĚŽÍ</div>

    <div class="status-grid">
        <!-- Session Keep-Alive Status -->
        <div class="status-card">
            <h2>⏰ SESSION KEEP-ALIVE</h2>
            <div class="status-item">
                <span>Status:</span>
                <span id="keepaliveStatus" class="status-ok">Načítám...</span>
            </div>
            <div class="status-item">
                <span>Poslední ping:</span>
                <span id="lastPing">-</span>
            </div>
            <div class="status-item">
                <span>Session timeout:</span>
                <span id="sessionTimeout">-</span>
            </div>
            <div class="status-item">
                <span>Interval:</span>
                <span>5 minut</span>
            </div>
            <button class="btn btn-test" onclick="testSessionPing()">🧪 Test Ping</button>
        </div>

        <!-- IndexedDB Status -->
        <div class="status-card">
            <h2>💾 INDEXEDDB</h2>
            <div class="status-item">
                <span>Podpora:</span>
                <span id="indexedDBSupport" class="status-ok">Načítám...</span>
            </div>
            <div class="status-item">
                <span>Databáze:</span>
                <span id="indexedDBName">-</span>
            </div>
            <div class="status-item">
                <span>Uložených reklamací:</span>
                <span id="indexedDBCount">-</span>
            </div>
            <button class="btn btn-test" onclick="testIndexedDB()">🧪 Test IndexedDB</button>
        </div>

        <!-- Auto-Download Status -->
        <div class="status-card">
            <h2>📥 AUTO-DOWNLOAD FOTEK</h2>
            <div class="status-item">
                <span>Status:</span>
                <span id="autoDownloadStatus" class="status-ok">Načítám...</span>
            </div>
            <div class="status-item">
                <span>JavaScript:</span>
                <span id="jsLoaded">-</span>
            </div>
            <div class="status-item">
                <span>Funkce:</span>
                <span id="downloadFunction">-</span>
            </div>
            <button class="btn btn-test" onclick="testAutoDownload()">🧪 Test Download</button>
        </div>

        <!-- API Endpoints -->
        <div class="status-card">
            <h2>🌐 API ENDPOINTS</h2>
            <div class="status-item">
                <span>/api/session_keepalive.php:</span>
                <span id="apiKeepalive" class="status-ok">Načítám...</span>
            </div>
            <div class="status-item">
                <span>protokol.php:</span>
                <span id="pageProtokol">-</span>
            </div>
            <div class="status-item">
                <span>photocustomer.php:</span>
                <span id="pagePhotocustomer">-</span>
            </div>
            <button class="btn btn-test" onclick="testAPIs()">🧪 Test APIs</button>
        </div>
    </div>

    <!-- Live Log -->
    <div class="log-box">
        <h2 style="margin-bottom: 15px;">📋 LIVE LOG</h2>
        <div id="liveLog"></div>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <a href="protokol.php" class="btn">← Zpět na Protokol</a>
        <a href="photocustomer.php" class="btn">📷 Photocustomer</a>
        <a href="diagnostika_indexeddb.php" class="btn">💾 IndexedDB Viewer</a>
        <button class="btn" onclick="location.reload()">🔄 Refresh</button>
    </div>
</div>

<!-- Load dependencies -->
<script src="assets/js/photo-storage-db.js"></script>
<script src="assets/js/session-keepalive.js"></script>

<script>
// ============================================
// DIAGNOSTIKA FUNKCÍ
// ============================================

const log = [];

function addLog(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString('cs-CZ');
    const entry = { timestamp, message, type };
    log.push(entry);

    const logDiv = document.getElementById('liveLog');
    const entryDiv = document.createElement('div');
    entryDiv.className = 'log-entry';

    let color = '#0f0';
    if (type === 'error') color = '#f00';
    if (type === 'warning') color = '#ff0';

    entryDiv.innerHTML = `<span class="timestamp">[${timestamp}]</span> <span style="color: ${color}">${message}</span>`;
    logDiv.insertBefore(entryDiv, logDiv.firstChild);
}

// ============================================
// TEST SESSION KEEP-ALIVE
// ============================================
async function testSessionPing() {
    addLog('🧪 Testuji session keep-alive...');

    try {
        const response = await fetch('/api/session_keepalive.php');
        const data = await response.json();

        if (data.status === 'success') {
            document.getElementById('keepaliveStatus').textContent = '✅ AKTIVNÍ';
            document.getElementById('keepaliveStatus').className = 'status-ok';
            document.getElementById('lastPing').textContent = data.last_activity;
            document.getElementById('sessionTimeout').textContent = data.session_timeout;
            addLog('✅ Session keep-alive: FUNGUJE', 'info');
        } else {
            document.getElementById('keepaliveStatus').textContent = '❌ CHYBA';
            document.getElementById('keepaliveStatus').className = 'status-error';
            addLog('❌ Session keep-alive: SELHALO - ' + data.message, 'error');
        }
    } catch (error) {
        document.getElementById('keepaliveStatus').textContent = '❌ OFFLINE';
        document.getElementById('keepaliveStatus').className = 'status-error';
        addLog('❌ Session keep-alive: NEDOSTUPNÉ - ' + error.message, 'error');
    }
}

// ============================================
// TEST INDEXEDDB
// ============================================
async function testIndexedDB() {
    addLog('🧪 Testuji IndexedDB...');

    try {
        // Check support
        if (!window.indexedDB) {
            document.getElementById('indexedDBSupport').textContent = '❌ NENÍ PODPOROVÁNO';
            document.getElementById('indexedDBSupport').className = 'status-error';
            addLog('❌ IndexedDB není podporováno v tomto browseru', 'error');
            return;
        }

        document.getElementById('indexedDBSupport').textContent = '✅ PODPOROVÁNO';
        document.getElementById('indexedDBSupport').className = 'status-ok';

        // Check PhotoStorageDB
        if (typeof window.PhotoStorageDB !== 'undefined') {
            document.getElementById('indexedDBName').textContent = 'WGS_PhotoStorage';
            addLog('✅ PhotoStorageDB modul načten', 'info');

            // Try to open DB and count records
            const db = await initPhotoStorageDB();
            const transaction = db.transaction(['photoSections'], 'readonly');
            const objectStore = transaction.objectStore('photoSections');
            const countRequest = objectStore.count();

            countRequest.onsuccess = () => {
                const count = countRequest.result;
                document.getElementById('indexedDBCount').textContent = count;
                addLog(`✅ IndexedDB: ${count} uložených reklamací`, 'info');
                db.close();
            };

            countRequest.onerror = () => {
                addLog('⚠️ Nepodařilo se spočítat záznamy v IndexedDB', 'warning');
                db.close();
            };

        } else {
            document.getElementById('indexedDBName').textContent = '❌ PhotoStorageDB nenačten';
            addLog('❌ PhotoStorageDB modul není dostupný', 'error');
        }

    } catch (error) {
        addLog('❌ IndexedDB test selhal: ' + error.message, 'error');
    }
}

// ============================================
// TEST AUTO-DOWNLOAD
// ============================================
async function testAutoDownload() {
    addLog('🧪 Testuji auto-download funkcionalitu...');

    // Check if function exists (načte se až v photocustomer.php)
    const hasDownloadFunction = typeof downloadToGallery === 'function';

    if (hasDownloadFunction) {
        document.getElementById('autoDownloadStatus').textContent = '✅ AKTIVNÍ';
        document.getElementById('autoDownloadStatus').className = 'status-ok';
        document.getElementById('downloadFunction').textContent = '✅ Funkce existuje';
        addLog('✅ Auto-download: Funkce downloadToGallery() je dostupná', 'info');
    } else {
        document.getElementById('autoDownloadStatus').textContent = '⚠️ PENDING';
        document.getElementById('autoDownloadStatus').className = 'status-warning';
        document.getElementById('downloadFunction').textContent = '⚠️ Načte se v photocustomer.php';
        addLog('⚠️ Auto-download: Funkce se načte až při pořizování fotek', 'warning');
    }

    // Check if script is loaded
    const scriptLoaded = document.querySelector('script[src*="photocustomer"]') !== null;
    document.getElementById('jsLoaded').textContent = scriptLoaded ? '✅ Načten' : '⚠️ Načte se v photocustomer.php';

    addLog('ℹ️ Auto-download bude automaticky aktivní při pořizování fotek', 'info');
}

// ============================================
// TEST APIs
// ============================================
async function testAPIs() {
    addLog('🧪 Testuji API endpoints...');

    // Test session keepalive
    try {
        const response = await fetch('/api/session_keepalive.php');
        if (response.ok) {
            document.getElementById('apiKeepalive').textContent = '✅ ONLINE';
            document.getElementById('apiKeepalive').className = 'status-ok';
            addLog('✅ API: /api/session_keepalive.php je dostupné', 'info');
        } else {
            throw new Error('HTTP ' + response.status);
        }
    } catch (error) {
        document.getElementById('apiKeepalive').textContent = '❌ OFFLINE';
        document.getElementById('apiKeepalive').className = 'status-error';
        addLog('❌ API: /api/session_keepalive.php není dostupné', 'error');
    }

    // Test protokol.php
    try {
        const response = await fetch('/protokol.php', { method: 'HEAD' });
        document.getElementById('pageProtokol').textContent = response.ok ? '✅ ONLINE' : '❌ OFFLINE';
        document.getElementById('pageProtokol').className = response.ok ? 'status-ok' : 'status-error';
    } catch (error) {
        document.getElementById('pageProtokol').textContent = '❌ OFFLINE';
        document.getElementById('pageProtokol').className = 'status-error';
    }

    // Test photocustomer.php
    try {
        const response = await fetch('/photocustomer.php', { method: 'HEAD' });
        document.getElementById('pagePhotocustomer').textContent = response.ok ? '✅ ONLINE' : '❌ OFFLINE';
        document.getElementById('pagePhotocustomer').className = response.ok ? 'status-ok' : 'status-error';
    } catch (error) {
        document.getElementById('pagePhotocustomer').textContent = '❌ OFFLINE';
        document.getElementById('pagePhotocustomer').className = 'status-error';
    }
}

// ============================================
// AUTO-RUN DIAGNOSTICS ON LOAD
// ============================================
window.addEventListener('DOMContentLoaded', async () => {
    addLog('🚀 Spouštím diagnostiku systému...', 'info');

    // Wait a bit for scripts to load
    await new Promise(resolve => setTimeout(resolve, 1000));

    await testSessionPing();
    await testIndexedDB();
    await testAutoDownload();
    await testAPIs();

    addLog('✅ Diagnostika dokončena', 'info');

    // Monitor session keep-alive
    setInterval(() => {
        testSessionPing();
    }, 60000); // Check every minute
});
</script>
</body>
</html>
