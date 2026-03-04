<?php
/**
 * Debug nástroj pro frontend distance cache
 * Zobrazuje obsah localStorage cache a umožňuje její vymazání
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Přístup odepřen - pouze admin');
}

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug Distance Cache</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2D5016;
            border-bottom: 3px solid #2D5016;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2D5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn:hover {
            background: #1a300d;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .cache-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 0.9rem;
        }
        .cache-table th,
        .cache-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .cache-table th {
            background: #2D5016;
            color: white;
            font-weight: bold;
        }
        .cache-table tr:nth-child(even) {
            background: #f8f8f8;
        }
        .cache-table tr:hover {
            background: #e8f4e8;
        }
        .cache-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .cache-stat {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2D5016;
        }
        .cache-stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        .cache-stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2D5016;
        }
        .highlight-bad {
            background: #ffcccc !important;
            font-weight: bold;
        }
        pre {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Debug Distance Cache</h1>

    <div class='info'>
        <strong>ℹ️ Co je distance cache?</strong><br>
        Frontend ukládá vypočítané vzdálenosti do <code>localStorage</code> v prohlížeči pod klíčem <code>wgs_distance_cache</code>.
        Pokud jsou tam staré hodnoty (např. 59 km místo 407 km), musíš cache vymazat.
    </div>

    <div class='cache-stats' id='cacheStats'>
        <div class='cache-stat'>
            <div class='cache-stat-label'>Celkem záznamů</div>
            <div class='cache-stat-value' id='statTotal'>—</div>
        </div>
        <div class='cache-stat'>
            <div class='cache-stat-label'>Velikost cache</div>
            <div class='cache-stat-value' id='statSize'>—</div>
        </div>
        <div class='cache-stat'>
            <div class='cache-stat-label'>Poslední aktualizace</div>
            <div class='cache-stat-value' id='statUpdate'>—</div>
        </div>
    </div>

    <div style='margin: 20px 0;'>
        <button class='btn' onclick='refreshCache()'>🔄 Aktualizovat zobrazení</button>
        <button class='btn btn-danger' onclick='clearCache()'>🗑️ Vymazat celou cache</button>
    </div>

    <h2>Obsah cache:</h2>
    <div id='cacheContent'></div>

    <div style='margin-top: 2rem;'>
        <a href='debug_geocoding.php' class='btn'>🔍 Otestovat Geocoding</a>
        <a href='vymaz_geocoding_cache.php' class='btn'>🧹 Vymazat Backend Cache</a>
        <a href='admin.php' class='btn'>← Zpět na Admin</a>
    </div>
</div>

<script>
function loadCache() {
    try {
        const cacheStr = localStorage.getItem('wgs_distance_cache');

        if (!cacheStr) {
            document.getElementById('cacheContent').innerHTML = '<div class="info">Cache je prázdná</div>';
            document.getElementById('statTotal').textContent = '0';
            document.getElementById('statSize').textContent = '0 B';
            document.getElementById('statUpdate').textContent = '—';
            return;
        }

        const cache = JSON.parse(cacheStr);
        const entries = Object.entries(cache);

        // Statistiky
        document.getElementById('statTotal').textContent = entries.length;
        document.getElementById('statSize').textContent = formatBytes(cacheStr.length);
        document.getElementById('statUpdate').textContent = new Date().toLocaleString('cs-CZ');

        // Tabulka
        let html = '<table class="cache-table">';
        html += '<thead><tr>';
        html += '<th>Z (From)</th>';
        html += '<th>Do (To)</th>';
        html += '<th>Vzdálenost</th>';
        html += '<th>Čas jízdy</th>';
        html += '</tr></thead>';
        html += '<tbody>';

        entries.forEach(([key, value]) => {
            const [from, to] = key.split('|');
            const km = parseFloat(value.km);

            // Zvýraznit podezřelé hodnoty (méně než 100 km pro dlouhé trasy)
            const isSuspicious = (
                (to.includes('Žilina') && km < 300) ||
                (to.includes('Návsí') && km < 300) ||
                (to.includes('Brno') && km < 100)
            );

            const rowClass = isSuspicious ? ' class="highlight-bad"' : '';

            html += `<tr${rowClass}>`;
            html += `<td>${escapeHtml(from)}</td>`;
            html += `<td>${escapeHtml(to)}</td>`;
            html += `<td><strong>${value.km} km</strong></td>`;
            html += `<td>${value.duration || '—'}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table>';

        // Raw JSON
        html += '<h3>Raw JSON:</h3>';
        html += '<pre>' + JSON.stringify(cache, null, 2) + '</pre>';

        document.getElementById('cacheContent').innerHTML = html;

    } catch (e) {
        document.getElementById('cacheContent').innerHTML = '<div class="error">Chyba při čtení cache: ' + escapeHtml(e.message) + '</div>';
    }
}

function refreshCache() {
    loadCache();
}

function clearCache() {
    if (!confirm('Opravdu chceš vymazat celou distance cache?\n\nVšechny vzdálenosti se budou muset znovu vypočítat.')) {
        return;
    }

    try {
        localStorage.removeItem('wgs_distance_cache');
        alert('✅ Cache vymazána!');
        loadCache();
    } catch (e) {
        alert('❌ Chyba: ' + e.message);
    }
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Načíst cache při načtení stránky
window.addEventListener('DOMContentLoaded', loadCache);
</script>

</body>
</html>
