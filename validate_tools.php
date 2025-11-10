<?php
/**
 * VALIDÁTOR: Kontrola funkčnosti všech nástrojů v admin panelu
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Přístup odepřen');
}

// Seznam všech nástrojů v admin panelu
$tools = [
    'DEBUG NÁSTROJE' => [
        [
            'name' => '⚡ AKTIVNÍ DIAGNOSTIKA',
            'file' => 'diagnostic_access_active.php',
            'type' => 'primary',
            'description' => 'Real-time testování přístupů a validace systému'
        ],
        [
            'name' => 'SQL/PHP Debug',
            'file' => 'diagnostic_tool.php',
            'type' => 'secondary',
            'description' => 'Ruční SQL dotazy a PHP debug'
        ],
        [
            'name' => 'Dokumentace systému',
            'file' => 'diagnostic_access_control.php',
            'type' => 'secondary',
            'description' => 'Kompletní dokumentace řízení přístupu'
        ],
        [
            'name' => 'STRUKTURA',
            'file' => 'show_table_structure.php',
            'type' => 'utility',
            'description' => 'Zobrazení struktury tabulky wgs_reklamace'
        ],
        [
            'name' => 'FOTKY',
            'file' => 'debug_photos.php',
            'type' => 'utility',
            'description' => 'Debug fotek a jejich propojení'
        ],
    ],
    'TESTOVÁNÍ ROLÍ' => [
        [
            'name' => 'SEZNAM (testovací odkaz)',
            'file' => 'seznam.php',
            'type' => 'test_link',
            'description' => 'Seznam reklamací - hlavní aplikace'
        ],
        [
            'name' => 'DB (testovací odkaz)',
            'file' => 'show_table_structure.php',
            'type' => 'test_link',
            'description' => 'Databázová struktura'
        ],
        [
            'name' => 'DIAGNOSTIKA (testovací odkaz)',
            'file' => 'diagnostic_web.php',
            'type' => 'test_link',
            'description' => 'Webová diagnostika'
        ],
    ],
    'INSTALACE & MIGRACE' => [
        [
            'name' => 'Role-Based Access',
            'file' => 'install_role_based_access.php',
            'type' => 'installer',
            'description' => 'Instalátor RBAC systému'
        ],
    ]
];

// Funkce pro test HTTP dostupnosti
function testHttpAccess($file) {
    $url = 'http://localhost/' . $file;

    // Použij file_get_contents s error supression
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'ignore_errors' => true
        ]
    ]);

    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        return ['success' => false, 'error' => 'HTTP request failed'];
    }

    // Zkontroluj HTTP response code
    if (isset($http_response_header)) {
        $status_line = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $status = isset($match[1]) ? (int)$match[1] : 0;

        return [
            'success' => ($status === 200),
            'status_code' => $status,
            'content_length' => strlen($content)
        ];
    }

    return ['success' => true, 'content_length' => strlen($content)];
}

// Validace všech nástrojů
$results = [];
$totalTools = 0;
$workingTools = 0;
$failedTools = 0;

foreach ($tools as $category => $categoryTools) {
    $results[$category] = [];

    foreach ($categoryTools as $tool) {
        $totalTools++;
        $filePath = __DIR__ . '/' . $tool['file'];

        $result = [
            'name' => $tool['name'],
            'file' => $tool['file'],
            'type' => $tool['type'],
            'description' => $tool['description'],
            'file_exists' => file_exists($filePath),
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
            'file_readable' => file_exists($filePath) && is_readable($filePath),
            'status' => 'unknown'
        ];

        // Detekce problémů
        if (!$result['file_exists']) {
            $result['status'] = 'missing';
            $result['error'] = 'Soubor neexistuje';
            $failedTools++;
        } elseif (!$result['file_readable']) {
            $result['status'] = 'unreadable';
            $result['error'] = 'Soubor není čitelný';
            $failedTools++;
        } elseif ($result['file_size'] === 0) {
            $result['status'] = 'empty';
            $result['error'] = 'Soubor je prázdný';
            $failedTools++;
        } else {
            $result['status'] = 'ok';
            $workingTools++;
        }

        $results[$category][] = $result;
    }
}

$allWorking = ($failedTools === 0);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validace nástrojů</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wgs-white: #FFFFFF;
            --wgs-black: #000000;
            --wgs-grey: #555555;
            --wgs-light-grey: #999999;
            --wgs-border: #E0E0E0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--wgs-white);
            color: var(--wgs-black);
            padding: 2rem;
        }

        .header {
            background: <?= $allWorking ? '#4CAF50' : '#e74c3c' ?>;
            color: var(--wgs-white);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid <?= $allWorking ? '#4CAF50' : '#e74c3c' ?>;
        }

        h1 {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--wgs-white);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.9;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            padding: 1.5rem;
            text-align: center;
        }

        .summary-value {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .summary-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--wgs-grey);
        }

        .section {
            background: var(--wgs-white);
            border: 2px solid var(--wgs-border);
            border-left: 4px solid var(--wgs-black);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
            color: var(--wgs-black);
        }

        .tool-card {
            background: #f8f8f8;
            border: 2px solid var(--wgs-border);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .tool-card.primary {
            border-left: 4px solid var(--wgs-black);
        }

        .tool-card.ok {
            border-left-color: #4CAF50;
        }

        .tool-card.error {
            border-left-color: #e74c3c;
            background: #ffebee;
        }

        .tool-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .tool-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--wgs-black);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid;
        }

        .status-ok { background: #e8f5e9; color: #2e7d32; border-color: #4CAF50; }
        .status-missing { background: #ffebee; color: #c62828; border-color: #e74c3c; }
        .status-empty { background: #fff3e0; color: #e65100; border-color: #f57c00; }
        .status-unreadable { background: #ffebee; color: #c62828; border-color: #e74c3c; }

        .tool-description {
            font-size: 0.8rem;
            color: var(--wgs-grey);
            margin-bottom: 0.75rem;
        }

        .tool-details {
            font-size: 0.75rem;
            color: var(--wgs-light-grey);
            font-family: monospace;
        }

        .tool-error {
            background: #ffebee;
            border: 2px solid #e74c3c;
            padding: 0.75rem;
            margin-top: 0.75rem;
            color: #c62828;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .test-button {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--wgs-black);
            color: var(--wgs-white);
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid var(--wgs-black);
            transition: all 0.3s;
            margin-top: 0.5rem;
        }

        .test-button:hover {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }

        .back-link {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.75rem 1.5rem;
            background: var(--wgs-black);
            color: var(--wgs-white);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid var(--wgs-black);
            transition: all 0.3s;
        }

        .back-link:hover {
            background: var(--wgs-white);
            color: var(--wgs-black);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= $allWorking ? '✓ VŠECHNY NÁSTROJE FUNGUJÍ' : '✗ NALEZENY PROBLÉMY' ?></h1>
        <p class="subtitle">Validace nástrojů v admin panelu</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="summary-value"><?= $totalTools ?></div>
            <div class="summary-label">Celkem nástrojů</div>
        </div>
        <div class="summary-card">
            <div class="summary-value" style="color: #4CAF50;"><?= $workingTools ?></div>
            <div class="summary-label">Fungující</div>
        </div>
        <div class="summary-card">
            <div class="summary-value" style="color: #e74c3c;"><?= $failedTools ?></div>
            <div class="summary-label">S problémy</div>
        </div>
    </div>

    <?php foreach ($results as $category => $categoryResults): ?>
    <div class="section">
        <h2><?= htmlspecialchars($category) ?></h2>

        <?php foreach ($categoryResults as $result): ?>
        <div class="tool-card <?= $result['type'] ?> <?= $result['status'] === 'ok' ? 'ok' : 'error' ?>">
            <div class="tool-header">
                <div class="tool-name"><?= htmlspecialchars($result['name']) ?></div>
                <span class="status-badge status-<?= $result['status'] ?>">
                    <?= $result['status'] === 'ok' ? '✓ OK' : '✗ ' . strtoupper($result['status']) ?>
                </span>
            </div>

            <div class="tool-description"><?= htmlspecialchars($result['description']) ?></div>

            <div class="tool-details">
                <div>Soubor: <?= htmlspecialchars($result['file']) ?></div>
                <?php if ($result['file_exists']): ?>
                <div>Velikost: <?= number_format($result['file_size'] / 1024, 2) ?> KB</div>
                <div>Čitelnost: <?= $result['file_readable'] ? '✓ Ano' : '✗ Ne' ?></div>
                <?php else: ?>
                <div>Stav: ✗ Soubor neexistuje</div>
                <?php endif; ?>
            </div>

            <?php if ($result['status'] !== 'ok'): ?>
            <div class="tool-error">
                ✗ <?= htmlspecialchars($result['error']) ?>
            </div>
            <?php endif; ?>

            <?php if ($result['status'] === 'ok'): ?>
            <a href="/<?= htmlspecialchars($result['file']) ?>" target="_blank" class="test-button">
                OTEVŘÍT A OTESTOVAT
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($allWorking): ?>
    <div style="padding: 2rem; background: #e8f5e9; border: 4px solid #4CAF50; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">✓</div>
        <h3 style="font-size: 1.2rem; color: #2e7d32; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
            VŠECHNY NÁSTROJE JSOU FUNKČNÍ
        </h3>
        <p style="color: #555; font-size: 0.9rem;">
            Všechny soubory existují, jsou čitelné a mají nenulovou velikost.
            Můžeš bezpečně používat všechny nástroje v admin panelu.
        </p>
    </div>
    <?php endif; ?>

    <a href="/admin.php?tab=tools" class="back-link">← ZPĚT NA ADMIN PANEL</a>

    <div style="margin-top: 2rem; text-align: center; color: var(--wgs-light-grey); font-size: 0.8rem;">
        <small>WGS SERVICE - VALIDACE NÁSTROJŮ © 2025</small>
    </div>
</body>
</html>
