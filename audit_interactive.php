<?php
/**
 * Interaktivní audit souborů s třídním podle použití
 * 
 * Features:
 * - Třídění podle posledního použití (access time)
 * - Klikatelné řádky zobrazí závislosti
 * - Grep analýza - kde se soubor používá
 * - Statistiky využití
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

// AJAX endpoint pro závislosti
if (isset($_GET['ajax']) && $_GET['ajax'] === 'dependencies') {
    header('Content-Type: application/json');
    
    $file = $_GET['file'] ?? '';
    if (empty($file) || !file_exists(__DIR__ . '/' . $file)) {
        echo json_encode(['error' => 'Soubor nenalezen']);
        exit;
    }
    
    $dependencies = [
        'includes_this' => [],  // Kdo tento soubor includuje
        'this_includes' => [],  // Co tento soubor includuje
        'references' => []      // Kde se na něj odkazuje
    ];
    
    // 1. Najít kdo tento soubor includuje
    $grepCmd = sprintf(
        "grep -r %s %s --include='*.php' --exclude-dir=vendor --exclude-dir=node_modules 2>/dev/null || true",
        escapeshellarg("require.*$file"),
        escapeshellarg(__DIR__)
    );
    exec($grepCmd, $includesThis);
    foreach ($includesThis as $line) {
        if (preg_match('/^([^:]+):(.+)$/', $line, $matches)) {
            $dependencies['includes_this'][] = [
                'file' => basename($matches[1]),
                'line' => trim($matches[2])
            ];
        }
    }
    
    // 2. Najít co tento soubor includuje
    $content = file_get_contents(__DIR__ . '/' . $file);
    preg_match_all('/(require|require_once|include|include_once)\\s+[\'"]([^\'"]+)[\'"]/', $content, $matches);
    if (!empty($matches[2])) {
        foreach ($matches[2] as $included) {
            $dependencies['this_includes'][] = basename($included);
        }
    }
    
    // 3. Najít odkazy (kde se jmenuje)
    $baseName = pathinfo($file, PATHINFO_FILENAME);
    $grepCmd = sprintf(
        "grep -r %s %s --include='*.php' --include='*.js' --exclude-dir=vendor 2>/dev/null | head -20 || true",
        escapeshellarg($baseName),
        escapeshellarg(__DIR__)
    );
    exec($grepCmd, $references);
    foreach ($references as $line) {
        if (preg_match('/^([^:]+):(.+)$/', $line, $matches)) {
            $dependencies['references'][] = [
                'file' => basename($matches[1]),
                'line' => trim(substr($matches[2], 0, 100))
            ];
        }
    }
    
    echo json_encode($dependencies);
    exit;
}

// Načíst všechny PHP soubory v root
$rootPhpFiles = glob(__DIR__ . '/*.php');
$fileStats = [];

foreach ($rootPhpFiles as $filePath) {
    if (is_dir($filePath)) continue;
    
    $fileName = basename($filePath);
    $lastAccess = fileatime($filePath);
    $lastModified = filemtime($filePath);
    $size = filesize($filePath);
    $daysSinceAccess = (time() - $lastAccess) / 86400;
    
    // Kategorizace (zkrácená verze)
    $category = 'UNKNOWN';
    if (in_array($fileName, ['init.php', 'index.php', 'login.php', 'admin.php', 'seznam.php'])) {
        $category = 'CRITICAL';
    } elseif (preg_match('/^test_/', $fileName)) {
        $category = 'TEST';
    } elseif (preg_match('/^(pridej|kontrola)_/', $fileName)) {
        $category = 'MIGRATION';
    } elseif (preg_match('/^debug_/', $fileName)) {
        $category = 'DIAGNOSTIC';
    } elseif (preg_match('/^oprav_/', $fileName)) {
        $category = 'FIX';
    } elseif (preg_match('/^odeslat_/', $fileName)) {
        $category = 'EMAIL';
    } elseif (preg_match('/\.php$/', $fileName) && in_array($fileName, ['aktuality.php', 'onas.php', 'gdpr.php'])) {
        $category = 'LANDING';
    }
    
    $fileStats[] = [
        'name' => $fileName,
        'category' => $category,
        'last_access' => $lastAccess,
        'last_modified' => $lastModified,
        'days_since_access' => round($daysSinceAccess, 1),
        'size' => $size,
        'size_kb' => round($size / 1024, 1)
    ];
}

// Třídění podle posledního přístupu (nejdéle nepoužívané nahoře)
usort($fileStats, function($a, $b) {
    return $a['last_access'] - $b['last_access'];
});
?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Interaktivní audit souborů</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1800px; margin: 20px auto; padding: 20px;
            background: #f5f5f5; font-size: 14px; 
        }
        .container { 
            background: white; padding: 30px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #333; border-bottom: 3px solid #333;
            padding-bottom: 10px; font-size: 1.8rem; margin-top: 0;
        }
        .info {
            background: #d1ecf1; border: 1px solid #bee5eb;
            color: #0c5460; padding: 15px; border-radius: 5px;
            margin: 15px 0; line-height: 1.6;
        }
        .controls {
            background: #f8f9fa; padding: 15px; border-radius: 5px;
            margin: 15px 0; display: flex; gap: 15px; flex-wrap: wrap;
            align-items: center;
        }
        .controls label { font-weight: 600; }
        .controls select {
            padding: 8px 12px; border: 1px solid #ddd;
            border-radius: 5px; font-size: 14px;
        }
        table {
            width: 100%; border-collapse: collapse; margin: 20px 0;
            font-size: 13px;
        }
        th, td {
            padding: 12px 8px; text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #333; color: white; font-weight: 600;
            position: sticky; top: 0; z-index: 10;
            cursor: pointer; user-select: none;
        }
        th:hover { background: #000; }
        tr.clickable {
            cursor: pointer; transition: background 0.2s;
        }
        tr.clickable:hover {
            background: #f0f0f0;
        }
        tr.expanded {
            background: #e3f2fd;
        }
        .badge {
            display: inline-block; padding: 4px 8px;
            border-radius: 3px; font-size: 11px;
            font-weight: 600; text-transform: uppercase;
        }
        .badge-critical { background: #dc3545; color: white; }
        .badge-test { background: #17a2b8; color: white; }
        .badge-migration { background: #6c757d; color: white; }
        .badge-diagnostic { background: #ffc107; color: #000; }
        .badge-fix { background: #fd7e14; color: white; }
        .badge-email { background: #20c997; color: white; }
        .badge-landing { background: #28a745; color: white; }
        .badge-unknown { background: #6c757d; color: white; }
        .days-old {
            font-weight: 600;
        }
        .days-old.ancient { color: #dc3545; }
        .days-old.old { color: #ffc107; }
        .days-old.recent { color: #28a745; }
        .dependency-row {
            display: none;
        }
        .dependency-row.show {
            display: table-row;
        }
        .dependency-content {
            padding: 20px; background: #f8f9fa;
            border-left: 4px solid #17a2b8;
        }
        .dependency-section {
            margin: 15px 0;
        }
        .dependency-section h4 {
            margin: 0 0 10px 0; color: #333;
            font-size: 14px;
        }
        .dependency-list {
            background: white; padding: 10px;
            border-radius: 5px; border: 1px solid #dee2e6;
        }
        .dependency-item {
            padding: 5px 0; font-size: 12px;
            border-bottom: 1px solid #eee;
        }
        .dependency-item:last-child {
            border-bottom: none;
        }
        .loading {
            text-align: center; padding: 20px;
            color: #666;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px; margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 20px; border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0; font-size: 12px;
            opacity: 0.9; text-transform: uppercase;
        }
        .stat-card .number {
            font-size: 2.5rem; font-weight: bold; margin: 0;
        }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Interaktivní audit souborů - Třídění podle použití</h1>

    <div class='info'>
        <strong>ℹ️ Jak to funguje:</strong><br>
        📊 Soubory seřazené podle posledního přístupu (nejdéle nepoužívané nahoře)<br>
        🖱️ Klikni na řádek pro zobrazení závislostí<br>
        🔗 Uvidíš: kdo soubor includuje, co on includuje, kde se na něj odkazuje<br>
        🎨 Barvy: <span style="color: #dc3545; font-weight: 600;">Červená = 90+ dní</span>, 
        <span style="color: #ffc107; font-weight: 600;">Žlutá = 30-90 dní</span>, 
        <span style="color: #28a745; font-weight: 600;">Zelená = < 30 dní</span>
    </div>

    <!-- Statistiky -->
    <div class='stats'>
        <div class='stat-card' style='background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);'>
            <h3>Nepoužívané 90+ dní</h3>
            <p class='number'><?php echo count(array_filter($fileStats, fn($f) => $f['days_since_access'] >= 90)); ?></p>
        </div>
        <div class='stat-card' style='background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);'>
            <h3>Nepoužívané 30-90 dní</h3>
            <p class='number'><?php echo count(array_filter($fileStats, fn($f) => $f['days_since_access'] >= 30 && $f['days_since_access'] < 90)); ?></p>
        </div>
        <div class='stat-card' style='background: linear-gradient(135deg, #28a745 0%, #218838 100%);'>
            <h3>Použité < 30 dní</h3>
            <p class='number'><?php echo count(array_filter($fileStats, fn($f) => $f['days_since_access'] < 30)); ?></p>
        </div>
        <div class='stat-card' style='background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%);'>
            <h3>Celkem souborů</h3>
            <p class='number'><?php echo count($fileStats); ?></p>
        </div>
    </div>

    <!-- Ovládání -->
    <div class='controls'>
        <label>Filtr kategorie:</label>
        <select id='filterCategory' onchange='filterTable()'>
            <option value=''>Vše</option>
            <option value='CRITICAL'>CRITICAL</option>
            <option value='TEST'>TEST</option>
            <option value='MIGRATION'>MIGRATION</option>
            <option value='DIAGNOSTIC'>DIAGNOSTIC</option>
            <option value='FIX'>FIX</option>
            <option value='EMAIL'>EMAIL</option>
            <option value='LANDING'>LANDING</option>
            <option value='UNKNOWN'>UNKNOWN</option>
        </select>

        <label>Filtr stáří:</label>
        <select id='filterAge' onchange='filterTable()'>
            <option value=''>Vše</option>
            <option value='ancient'>90+ dní (archivovat!)</option>
            <option value='old'>30-90 dní</option>
            <option value='recent'>< 30 dní</option>
        </select>

        <label>Řazení:</label>
        <select id='sortBy' onchange='sortTable()'>
            <option value='access'>Poslední přístup (nejstarší)</option>
            <option value='access-new'>Poslední přístup (nejnovější)</option>
            <option value='size'>Velikost (největší)</option>
            <option value='name'>Název (A-Z)</option>
        </select>
    </div>

    <!-- Tabulka -->
    <table id='filesTable'>
        <thead>
            <tr>
                <th>Soubor</th>
                <th>Kategorie</th>
                <th>Poslední přístup</th>
                <th>Dní nepoužíváno</th>
                <th>Velikost</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fileStats as $file): 
                $daysClass = 'recent';
                if ($file['days_since_access'] >= 90) $daysClass = 'ancient';
                elseif ($file['days_since_access'] >= 30) $daysClass = 'old';
                
                $badgeClass = 'badge-' . strtolower($file['category']);
            ?>
            <tr class='clickable' onclick='toggleDependencies("<?php echo htmlspecialchars($file['name']); ?>")' 
                data-category='<?php echo $file['category']; ?>' 
                data-age='<?php echo $daysClass; ?>'
                data-access='<?php echo $file['last_access']; ?>'
                data-size='<?php echo $file['size']; ?>'
                data-name='<?php echo $file['name']; ?>'>
                <td><code><?php echo htmlspecialchars($file['name']); ?></code></td>
                <td><span class='badge <?php echo $badgeClass; ?>'><?php echo $file['category']; ?></span></td>
                <td><?php echo date('Y-m-d H:i', $file['last_access']); ?></td>
                <td class='days-old <?php echo $daysClass; ?>'><?php echo $file['days_since_access']; ?> dní</td>
                <td><?php echo $file['size_kb']; ?> KB</td>
                <td>
                    <button onclick='event.stopPropagation(); archiveFile("<?php echo htmlspecialchars($file['name']); ?>")' 
                            style='background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;'>
                        Archivovat
                    </button>
                </td>
            </tr>
            <tr class='dependency-row' id='dep-<?php echo htmlspecialchars($file['name']); ?>'>
                <td colspan='6'>
                    <div class='dependency-content'>
                        <div class='loading'>Načítání závislostí...</div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
let expandedRow = null;

function toggleDependencies(fileName) {
    const depRow = document.getElementById('dep-' + fileName);
    const clickedRow = depRow.previousElementSibling;
    
    // Zavřít předchozí otevřený řádek
    if (expandedRow && expandedRow !== depRow) {
        expandedRow.classList.remove('show');
        expandedRow.previousElementSibling.classList.remove('expanded');
    }
    
    // Toggle current
    if (depRow.classList.contains('show')) {
        depRow.classList.remove('show');
        clickedRow.classList.remove('expanded');
        expandedRow = null;
    } else {
        depRow.classList.add('show');
        clickedRow.classList.add('expanded');
        expandedRow = depRow;
        
        // Načíst závislosti přes AJAX
        loadDependencies(fileName, depRow);
    }
}

async function loadDependencies(fileName, depRow) {
    const contentDiv = depRow.querySelector('.dependency-content');
    contentDiv.innerHTML = '<div class="loading">Načítání závislostí...</div>';
    
    try {
        const response = await fetch(`?ajax=dependencies&file=${encodeURIComponent(fileName)}`);
        const data = await response.json();
        
        let html = '';
        
        // Kdo tento soubor includuje
        html += '<div class="dependency-section">';
        html += '<h4>🔗 Kdo tento soubor includuje/requireuje:</h4>';
        if (data.includes_this.length > 0) {
            html += '<div class="dependency-list">';
            data.includes_this.forEach(item => {
                html += `<div class="dependency-item"><strong>${item.file}:</strong> ${item.line}</div>`;
            });
            html += '</div>';
        } else {
            html += '<div class="dependency-list"><em>Žádné soubory tento soubor neincludují</em></div>';
        }
        html += '</div>';
        
        // Co tento soubor includuje
        html += '<div class="dependency-section">';
        html += '<h4>📦 Co tento soubor includuje/requireuje:</h4>';
        if (data.this_includes.length > 0) {
            html += '<div class="dependency-list">';
            data.this_includes.forEach(file => {
                html += `<div class="dependency-item">${file}</div>`;
            });
            html += '</div>';
        } else {
            html += '<div class="dependency-list"><em>Tento soubor nic neincluduje</em></div>';
        }
        html += '</div>';
        
        // Odkazy (kde se jmenuje)
        html += '<div class="dependency-section">';
        html += '<h4>🔍 Odkazy v kódu (prvních 20):</h4>';
        if (data.references.length > 0) {
            html += '<div class="dependency-list">';
            data.references.forEach(ref => {
                html += `<div class="dependency-item"><strong>${ref.file}:</strong> ${ref.line}</div>`;
            });
            html += '</div>';
        } else {
            html += '<div class="dependency-list"><em>Žádné odkazy nenalezeny</em></div>';
        }
        html += '</div>';
        
        contentDiv.innerHTML = html;
        
    } catch (error) {
        contentDiv.innerHTML = '<div class="loading" style="color: red;">Chyba při načítání: ' + error.message + '</div>';
    }
}

function filterTable() {
    const category = document.getElementById('filterCategory').value;
    const age = document.getElementById('filterAge').value;
    const rows = document.querySelectorAll('#filesTable tbody tr.clickable');
    
    rows.forEach(row => {
        let show = true;
        
        if (category && row.dataset.category !== category) {
            show = false;
        }
        
        if (age && row.dataset.age !== age) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
        const depRow = row.nextElementSibling;
        if (depRow && depRow.classList.contains('dependency-row')) {
            depRow.style.display = show ? '' : 'none';
        }
    });
}

function sortTable() {
    const sortBy = document.getElementById('sortBy').value;
    const tbody = document.querySelector('#filesTable tbody');
    const rows = Array.from(tbody.querySelectorAll('tr.clickable'));
    
    rows.sort((a, b) => {
        switch(sortBy) {
            case 'access':
                return parseInt(a.dataset.access) - parseInt(b.dataset.access);
            case 'access-new':
                return parseInt(b.dataset.access) - parseInt(a.dataset.access);
            case 'size':
                return parseInt(b.dataset.size) - parseInt(a.dataset.size);
            case 'name':
                return a.dataset.name.localeCompare(b.dataset.name);
        }
    });
    
    // Přeuspořádat řádky
    rows.forEach(row => {
        const depRow = row.nextElementSibling;
        tbody.appendChild(row);
        if (depRow && depRow.classList.contains('dependency-row')) {
            tbody.appendChild(depRow);
        }
    });
}

function archiveFile(fileName) {
    if (confirm(`Archivovat soubor ${fileName}?\n\nSoubor bude přesunut do _archive/`)) {
        alert('Funkce archivace bude implementována v bezpečném archivačním skriptu.');
        // TODO: Implementovat přesun do _archive/
    }
}
</script>
</body>
</html>
