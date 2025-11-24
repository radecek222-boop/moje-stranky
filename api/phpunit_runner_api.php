<?php
/**
 * PHPUnit Test Runner API
 *
 * Spouští PHPUnit testy přímo z Admin Dashboard
 * POUZE pro admin uživatele
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

// DEBUG: Logovat session stav
error_log("=== PHPUnit API DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Session is_admin: " . (isset($_SESSION['is_admin']) ? var_export($_SESSION['is_admin'], true) : 'NOT SET'));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Session role: " . ($_SESSION['role'] ?? 'NOT SET'));
error_log("========================");

// BEZPEČNOST: Kontrola admin přihlášení
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Neautorizovaný přístup. Pouze admin může spouštět testy.',
        'debug' => [
            'session_id' => session_id(),
            'is_admin_set' => isset($_SESSION['is_admin']),
            'is_admin_value' => $_SESSION['is_admin'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

// CSRF ochrana
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

// KRITICKÁ KONTROLA: Funkce exec() musí být povolená
$disabledFunctions = explode(',', ini_get('disable_functions'));
$disabledFunctions = array_map('trim', $disabledFunctions);
$execDisabled = in_array('exec', $disabledFunctions) || !function_exists('exec');

if ($execDisabled) {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'PHPUnit Test Runner není dostupný na tomto serveru.',
        'detail' => 'Funkce exec() je zakázána v php.ini (disable_functions). PHPUnit vyžaduje exec() pro spouštění testů. Kontaktujte administrátora serveru pro povolení této funkce.',
        'disabled_functions' => implode(', ', array_filter($disabledFunctions))
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$akce = $_POST['akce'] ?? $_GET['akce'] ?? '';

try {
    switch ($akce) {
        case 'spustit_testy':
            spustitTesty();
            break;

        case 'spustit_testsuite':
            $testsuite = $_POST['testsuite'] ?? '';
            spustitTestSuite($testsuite);
            break;

        case 'spustit_coverage':
            spustitCoverage();
            break;

        case 'zkontrolovat_phpunit':
            zkontrolovatPHPUnit();
            break;

        case 'nainstalovat_zavislosti':
            nainstavovatZavislosti();
            break;

        default:
            throw new Exception('Neznámá akce: ' . htmlspecialchars($akce));
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Spustí všechny PHPUnit testy
 */
function spustitTesty(): void
{
    $rootPath = dirname(__DIR__);
    $phpunitPath = $rootPath . '/vendor/bin/phpunit';

    // Kontrola že PHPUnit existuje
    if (!file_exists($phpunitPath)) {
        throw new Exception('PHPUnit není nainstalován. Spusťte nejprve "composer install".');
    }

    // Spustit PHPUnit s testdox výstupem
    $prikaz = escapeshellcmd($phpunitPath) . ' --testdox --colors=never 2>&1';

    $vystup = [];
    $navratovyKod = 0;

    exec("cd " . escapeshellarg($rootPath) . " && $prikaz", $vystup, $navratovyKod);

    $vystupText = implode("\n", $vystup);

    // Parsovat výsledky
    $vysledek = parsujVysledkyTestu($vystupText);

    echo json_encode([
        'status' => $navratovyKod === 0 ? 'success' : 'warning',
        'message' => $navratovyKod === 0 ? 'Všechny testy prošly úspěšně!' : 'Některé testy selhaly.',
        'navratovy_kod' => $navratovyKod,
        'vystup' => $vystupText,
        'vysledek' => $vysledek
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Spustí konkrétní test suite
 */
function spustitTestSuite(string $testsuite): void
{
    if (empty($testsuite)) {
        throw new Exception('Není zadán test suite.');
    }

    $povoleneTestSuites = ['Security', 'Controllers', 'Utils', 'Integration'];
    if (!in_array($testsuite, $povoleneTestSuites, true)) {
        throw new Exception('Neplatný test suite: ' . htmlspecialchars($testsuite));
    }

    $rootPath = dirname(__DIR__);
    $phpunitPath = $rootPath . '/vendor/bin/phpunit';

    if (!file_exists($phpunitPath)) {
        throw new Exception('PHPUnit není nainstalován.');
    }

    $prikaz = escapeshellcmd($phpunitPath) . ' --testsuite ' . escapeshellarg($testsuite) . ' --testdox --colors=never 2>&1';

    $vystup = [];
    $navratovyKod = 0;

    exec("cd " . escapeshellarg($rootPath) . " && $prikaz", $vystup, $navratovyKod);

    $vystupText = implode("\n", $vystup);
    $vysledek = parsujVysledkyTestu($vystupText);

    echo json_encode([
        'status' => $navratovyKod === 0 ? 'success' : 'warning',
        'message' => "Test suite '{$testsuite}' " . ($navratovyKod === 0 ? 'prošel úspěšně!' : 'selhal.'),
        'navratovy_kod' => $navratovyKod,
        'vystup' => $vystupText,
        'vysledek' => $vysledek,
        'testsuite' => $testsuite
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Spustí testy s coverage reportem
 */
function spustitCoverage(): void
{
    $rootPath = dirname(__DIR__);
    $phpunitPath = $rootPath . '/vendor/bin/phpunit';

    if (!file_exists($phpunitPath)) {
        throw new Exception('PHPUnit není nainstalován.');
    }

    // Kontrola Xdebug
    if (!extension_loaded('xdebug')) {
        throw new Exception('Xdebug extension není nainstalován. Coverage report vyžaduje Xdebug.');
    }

    $prikaz = escapeshellcmd($phpunitPath) . ' --coverage-text --colors=never 2>&1';

    $vystup = [];
    $navratovyKod = 0;

    exec("cd " . escapeshellarg($rootPath) . " && $prikaz", $vystup, $navratovyKod);

    $vystupText = implode("\n", $vystup);
    $vysledek = parsujVysledkyTestu($vystupText);
    $coverage = parsujCoverage($vystupText);

    echo json_encode([
        'status' => $navratovyKod === 0 ? 'success' : 'warning',
        'message' => 'Coverage report vygenerován.',
        'navratovy_kod' => $navratovyKod,
        'vystup' => $vystupText,
        'vysledek' => $vysledek,
        'coverage' => $coverage
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Zkontroluje dostupnost PHPUnit
 */
function zkontrolovatPHPUnit(): void
{
    $rootPath = dirname(__DIR__);
    $phpunitPath = $rootPath . '/vendor/bin/phpunit';
    $composerJson = $rootPath . '/composer.json';

    $phpunitExistuje = file_exists($phpunitPath);
    $composerExistuje = file_exists($composerJson);
    $vendorExistuje = is_dir($rootPath . '/vendor');

    $phpVerze = phpversion();
    $xdebugNainstalovano = extension_loaded('xdebug');

    $info = [
        'phpunit_existuje' => $phpunitExistuje,
        'composer_existuje' => $composerExistuje,
        'vendor_existuje' => $vendorExistuje,
        'php_verze' => $phpVerze,
        'xdebug_nainstalovano' => $xdebugNainstalovano,
        'root_path' => $rootPath
    ];

    if ($phpunitExistuje) {
        // Zjistit verzi PHPUnit
        $prikaz = escapeshellcmd($phpunitPath) . ' --version 2>&1';
        exec($prikaz, $vystup);
        $info['phpunit_verze'] = $vystup[0] ?? 'Neznámá';
    }

    echo json_encode([
        'status' => 'success',
        'message' => $phpunitExistuje ? 'PHPUnit je nainstalován.' : 'PHPUnit NENÍ nainstalován.',
        'info' => $info
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Nainstaluje composer závislosti
 */
function nainstavovatZavislosti(): void
{
    $rootPath = dirname(__DIR__);

    // Kontrola že composer.json existuje
    if (!file_exists($rootPath . '/composer.json')) {
        throw new Exception('composer.json nenalezen.');
    }

    // Spustit composer install
    $prikaz = 'composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1';

    $vystup = [];
    $navratovyKod = 0;

    exec("cd " . escapeshellarg($rootPath) . " && $prikaz", $vystup, $navratovyKod);

    $vystupText = implode("\n", $vystup);

    if ($navratovyKod !== 0) {
        throw new Exception('Composer install selhal: ' . $vystupText);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Composer závislosti úspěšně nainstalovány!',
        'vystup' => $vystupText
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Parsuje výsledky PHPUnit testů
 */
function parsujVysledkyTestu(string $vystup): array
{
    $vysledek = [
        'celkem_testu' => 0,
        'uspesnych' => 0,
        'selhanych' => 0,
        'preskoceno' => 0,
        'assertions' => 0,
        'cas' => '',
        'pamet' => ''
    ];

    // OK (50 tests, 150 assertions)
    if (preg_match('/OK \((\d+) tests?, (\d+) assertions?\)/', $vystup, $matches)) {
        $vysledek['celkem_testu'] = (int)$matches[1];
        $vysledek['uspesnych'] = (int)$matches[1];
        $vysledek['assertions'] = (int)$matches[2];
    }

    // FAILURES! Tests: 50, Assertions: 145, Failures: 5
    if (preg_match('/Tests: (\d+), Assertions: (\d+), Failures: (\d+)/', $vystup, $matches)) {
        $vysledek['celkem_testu'] = (int)$matches[1];
        $vysledek['assertions'] = (int)$matches[2];
        $vysledek['selhanych'] = (int)$matches[3];
        $vysledek['uspesnych'] = $vysledek['celkem_testu'] - $vysledek['selhanych'];
    }

    // Time: 00:01.234, Memory: 12.00 MB
    if (preg_match('/Time: ([\d:\.]+), Memory: ([\d\.]+ [A-Z]+)/', $vystup, $matches)) {
        $vysledek['cas'] = $matches[1];
        $vysledek['pamet'] = $matches[2];
    }

    return $vysledek;
}

/**
 * Parsuje coverage informace
 */
function parsujCoverage(string $vystup): array
{
    $coverage = [
        'celkove_pokryti' => 0,
        'soubory' => []
    ];

    // Summary:
    //   Lines:   85.00% ( 340/ 400)
    if (preg_match('/Lines:\s+([\d\.]+)%/', $vystup, $matches)) {
        $coverage['celkove_pokryti'] = (float)$matches[1];
    }

    return $coverage;
}
