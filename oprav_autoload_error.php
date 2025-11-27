<?php
/**
 * Diagnostika a oprava Composer Autoload Error
 *
 * Tento skript diagnostikuje problem "Cannot redeclare function"
 * a poskytuje presne instrukce pro opravu.
 *
 * Pristup pouze pro admina.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit tento skript.");
}

// Cesty k souborum
$composerJsonPath = __DIR__ . '/composer.json';
$autoloadFilesPath = __DIR__ . '/vendor/composer/autoload_files.php';

// Nacteni composer.json
$composerJson = file_exists($composerJsonPath) ? json_decode(file_get_contents($composerJsonPath), true) : null;

// Kontrola zda existuje problematicka sekce "files" v autoload
$maFilesSection = isset($composerJson['autoload']['files']) && !empty($composerJson['autoload']['files']);

// Kontrola autoload_files.php
$autoloadFiles = [];
$maProblematickeSoubory = false;
if (file_exists($autoloadFilesPath)) {
    $obsah = file_get_contents($autoloadFilesPath);
    // Hledat nase soubory (ne vendor knihovny)
    $problematickePatterny = [
        'config/config.php',
        'includes/csrf_helper.php',
        'includes/api_response.php',
        'includes/db_metadata.php',
        'includes/env_loader.php',
        'includes/error_handler.php'
    ];
    foreach ($problematickePatterny as $pattern) {
        if (strpos($obsah, $pattern) !== false) {
            $autoloadFiles[] = $pattern;
            $maProblematickeSoubory = true;
        }
    }
}

// Test spusteni - zkusit require klicove soubory
$testVysledky = [];
$chybaNalezenena = false;

// Test funkci
$testFunkce = [
    'generateCSRFToken' => 'includes/csrf_helper.php',
    'validateCSRFToken' => 'includes/csrf_helper.php',
    'getDbConnection' => 'config/config.php',
    'sendJsonSuccess' => 'includes/api_response.php',
    'db_get_table_columns' => 'includes/db_metadata.php'
];

foreach ($testFunkce as $funkce => $soubor) {
    $testVysledky[$funkce] = [
        'existuje' => function_exists($funkce),
        'soubor' => $soubor
    ];
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostika Autoload Error - WGS</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 { color: #222; border-bottom: 3px solid #333; padding-bottom: 15px; margin-top: 0; }
        h2 { color: #444; margin-top: 30px; }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        pre {
            background: #1a1a1a;
            color: #eee;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.85rem;
            line-height: 1.5;
            max-height: 400px;
            overflow-y: auto;
        }
        code {
            background: #eee;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Monaco', 'Menlo', monospace;
        }
        .status-box {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .status-item:last-child { border-bottom: none; }
        .status-ok { color: #155724; font-weight: bold; }
        .status-ok::before { content: "[OK] "; }
        .status-fail { color: #721c24; font-weight: bold; }
        .status-fail::before { content: "[X] "; }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 15px 10px 15px 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn:hover { background: #555; }
        .instructions {
            background: #f9f9f9;
            border: 2px solid #333;
            padding: 25px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .instructions h3 { margin-top: 0; color: #222; }
        .instructions ol { padding-left: 20px; }
        .instructions li { margin: 15px 0; line-height: 1.6; }
        .copyable {
            position: relative;
            background: #2d2d2d;
            border-radius: 8px;
            padding: 5px;
        }
        .copyable pre {
            margin: 0;
        }
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #555;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .copy-btn:hover { background: #777; }
    </style>
</head>
<body>
<div class="container">
    <h1>Diagnostika: "Cannot redeclare function" Error</h1>

    <?php if ($maProblematickeSoubory): ?>
    <div class="error">
        <strong>NALEZEN PROBLEM!</strong><br>
        Soubor <code>vendor/composer/autoload_files.php</code> obsahuje projektove soubory,
        ktere jsou take nacitany pres <code>init.php</code>. To zpusobuje chybu
        "Cannot redeclare function".
    </div>
    <?php else: ?>
    <div class="success">
        <strong>AUTOLOAD SOUBOR JE V PORADKU</strong><br>
        Zadne projektove soubory nebyly nalezeny v autoload_files.php.
    </div>
    <?php endif; ?>

    <!-- DIAGNOSTIKA -->
    <div class="status-box">
        <h3>Diagnostika:</h3>

        <div class="status-item">
            <span>composer.json existuje</span>
            <span class="<?php echo $composerJson ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $composerJson ? 'Ano' : 'Ne'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>composer.json ma sekci "autoload.files"</span>
            <span class="<?php echo !$maFilesSection ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $maFilesSection ? 'ANO - PROBLEM!' : 'Ne - OK'; ?>
            </span>
        </div>

        <div class="status-item">
            <span>autoload_files.php obsahuje projektove soubory</span>
            <span class="<?php echo !$maProblematickeSoubory ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $maProblematickeSoubory ? 'ANO - PROBLEM!' : 'Ne - OK'; ?>
            </span>
        </div>
    </div>

    <?php if ($maProblematickeSoubory): ?>
    <h2>Problematicke soubory v autoload_files.php:</h2>
    <div class="warning">
        <ul>
        <?php foreach ($autoloadFiles as $soubor): ?>
            <li><code><?php echo htmlspecialchars($soubor); ?></code></li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- KONTROLA FUNKCI -->
    <h2>Test klicovych funkci:</h2>
    <div class="status-box">
        <?php foreach ($testVysledky as $funkce => $data): ?>
        <div class="status-item">
            <span><code><?php echo $funkce; ?>()</code> (<?php echo $data['soubor']; ?>)</span>
            <span class="<?php echo $data['existuje'] ? 'status-ok' : 'status-fail'; ?>">
                <?php echo $data['existuje'] ? 'Existuje' : 'Neexistuje'; ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- INSTRUKCE PRO OPRAVU -->
    <?php if ($maFilesSection || $maProblematickeSoubory): ?>
    <h2>Postup opravy (SSH)</h2>

    <div class="instructions">
        <h3>Krok 1: Upravte composer.json</h3>
        <p>Pres SSH spustte:</p>
        <div class="copyable">
            <pre>nano composer.json</pre>
            <button class="copy-btn" onclick="navigator.clipboard.writeText('nano composer.json')">Kopirovat</button>
        </div>
        <p>Zkontrolujte, ze sekce "autoload" vypada <strong>presne takto</strong> (BEZ "files"):</p>
        <div class="copyable">
            <pre>{
    "autoload": {
        "psr-4": {
            "WGS\\": "app/"
        }
    }
}</pre>
            <button class="copy-btn" onclick="navigator.clipboard.writeText('{&quot;autoload&quot;:{&quot;psr-4&quot;:{&quot;WGS\\\\&quot;:&quot;app/&quot;}}}')">Kopirovat</button>
        </div>
        <p><strong>SMAZTE</strong> jakoukoliv sekci <code>"files": [...]</code> pokud existuje!</p>
    </div>

    <div class="instructions">
        <h3>Krok 2: Regenerujte autoload</h3>
        <p>Po ulozeni composer.json spustte:</p>
        <div class="copyable">
            <pre>composer dump-autoload</pre>
            <button class="copy-btn" onclick="navigator.clipboard.writeText('composer dump-autoload')">Kopirovat</button>
        </div>
    </div>

    <div class="instructions">
        <h3>Krok 3: Overeni</h3>
        <p>Po spusteni composer dump-autoload znovu nacte tuto stranku.</p>
        <p>Vsechny polozky by mely byt zelene [OK].</p>
    </div>

    <?php else: ?>

    <div class="success">
        <h3>SYSTEM JE V PORADKU</h3>
        <p>Zadne problemy s autoload nebyly detekovany.</p>
        <p>Pokud stale vidte chyby "Cannot redeclare function", zkuste:</p>
        <ol>
            <li>Vymazat cache prohlizece</li>
            <li>Restartovat PHP-FPM (pokud mate pristup)</li>
            <li>Pockat par minut na vyprseni PHP opcode cache</li>
        </ol>
    </div>

    <?php endif; ?>

    <!-- AKTUALNI COMPOSER.JSON -->
    <h2>Aktualni obsah composer.json (autoload sekce):</h2>
    <?php if ($composerJson): ?>
    <pre><?php echo json_encode(['autoload' => $composerJson['autoload'] ?? 'CHYBI'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
    <?php else: ?>
    <div class="error">composer.json nebyl nalezen!</div>
    <?php endif; ?>

    <p style="margin-top: 30px;">
        <a href="/admin.php" class="btn">Zpet do Admin</a>
        <a href="?refresh=1" class="btn" style="background: #555;">Znovu otestovat</a>
    </p>
</div>
</body>
</html>
