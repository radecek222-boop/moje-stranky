<?php
/**
 * API: Správa souborů - přehled závislostí a využití
 *
 * Akce:
 *   GET ?akce=seznam          - Vrátí přehled všech souborů s závislostmi
 *   GET ?akce=seznam&nocache=1 - Vynutí čerstvý sken (obchází cache)
 *   POST akce=prepnout        - Označí/odznačí soubor ke smazání
 *   POST akce=smazatCache     - Smaže cache skenu
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'chyba', 'zprava' => 'Přístup odepřen']);
    exit;
}

$akce = $_REQUEST['akce'] ?? '';
$korenAdresar = realpath(__DIR__ . '/..');
$cacheSoubor  = $korenAdresar . '/config/soubory_cache.json';
$stavSoubor   = $korenAdresar . '/config/soubory_stav.json';

// ============================================================
// POMOCNÉ FUNKCE
// ============================================================

function nactiStavSouboru(string $cesta): array
{
    if (!file_exists($cesta)) {
        return [];
    }
    $obsah = file_get_contents($cesta);
    return json_decode($obsah, true) ?? [];
}

function ulozStavSouboru(string $cesta, array $stavy): bool
{
    $adresar = dirname($cesta);
    if (!is_dir($adresar)) {
        mkdir($adresar, 0755, true);
    }
    return file_put_contents(
        $cesta,
        json_encode($stavy, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function ziskejTypSouboru(string $nazev): string
{
    if ($nazev === '.htaccess') {
        return 'htaccess';
    }
    $pripona = strtolower(pathinfo($nazev, PATHINFO_EXTENSION));
    $mapa = [
        'php'   => 'php',
        'js'    => 'js',
        'css'   => 'css',
        'html'  => 'html',
        'htm'   => 'html',
        'json'  => 'data',
        'xml'   => 'data',
        'yml'   => 'data',
        'yaml'  => 'data',
        'sql'   => 'sql',
        'md'    => 'text',
        'txt'   => 'text',
        'log'   => 'text',
        'sh'    => 'shell',
        'jpg'   => 'obr',
        'jpeg'  => 'obr',
        'png'   => 'obr',
        'gif'   => 'obr',
        'svg'   => 'obr',
        'webp'  => 'obr',
        'ico'   => 'obr',
        'woff'  => 'font',
        'woff2' => 'font',
        'ttf'   => 'font',
        'eot'   => 'font',
        'lock'  => 'konfig',
        'env'   => 'konfig',
        'ini'   => 'konfig',
    ];
    return $mapa[$pripona] ?? 'ostatni';
}

function formatovatVelikost(int $bajty): string
{
    if ($bajty < 1024) {
        return $bajty . ' B';
    }
    if ($bajty < 1024 * 1024) {
        return round($bajty / 1024) . ' KB';
    }
    return number_format($bajty / (1024 * 1024), 1) . ' MB';
}

/**
 * Rekurzivně projde adresář a vrátí seznam souborů
 */
function scanSouboru(string $koren, array $vylouceneAdresare): array
{
    $soubory = [];

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($koren, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
    } catch (Exception $e) {
        return [];
    }

    foreach ($iterator as $polozka) {
        if (!$polozka->isFile()) {
            continue;
        }

        $absolutniCesta = $polozka->getPathname();
        $relativniCesta = ltrim(str_replace($koren, '', $absolutniCesta), DIRECTORY_SEPARATOR . '/');
        $relativniCesta = str_replace('\\', '/', $relativniCesta);

        // Zkontrolovat vyloučení adresáře
        $preskocit    = false;
        $casteCesty   = explode('/', $relativniCesta);
        foreach ($vylouceneAdresare as $vyl) {
            if (in_array($vyl, $casteCesty)) {
                $preskocit = true;
                break;
            }
        }
        if ($preskocit) {
            continue;
        }

        // Přeskočit skryté soubory začínající tečkou (kromě .htaccess)
        $nazev = $polozka->getFilename();
        if ($nazev[0] === '.' && $nazev !== '.htaccess') {
            continue;
        }

        $adresar = dirname($relativniCesta);
        if ($adresar === '.') {
            $adresar = '';
        }

        $soubory[] = [
            'nazev'         => $nazev,
            'cesta'         => $relativniCesta,
            'adresar'       => $adresar,
            'absolutniCesta' => $absolutniCesta,
            'velikost'      => $polozka->getSize(),
            'zmeneno'       => $polozka->getMTime(),
        ];
    }

    return $soubory;
}

/**
 * Extrahuje reference z obsahu souboru (require, include, src, href, import)
 */
function extrahovatReference(string $absolutniCesta): array
{
    $pripona = strtolower(pathinfo($absolutniCesta, PATHINFO_EXTENSION));
    $nazevSouboru = basename($absolutniCesta);

    if ($nazevSouboru === '.htaccess') {
        return [];
    }

    if (!in_array($pripona, ['php', 'js', 'css', 'html', 'htm'])) {
        return [];
    }

    // Omezit velikost čteného souboru na 2 MB
    $velikost = @filesize($absolutniCesta);
    if ($velikost === false || $velikost > 2 * 1024 * 1024) {
        return [];
    }

    $obsah = @file_get_contents($absolutniCesta);
    if ($obsah === false) {
        return [];
    }

    $reference = [];

    if (in_array($pripona, ['php', 'html', 'htm'])) {
        // PHP require_once / include_once / require / include
        preg_match_all(
            '/(?:require_once|require|include_once|include)\s*\(?\s*(?:__DIR__\s*\.\s*)?["\']([^"\']+)["\']\s*\)?/i',
            $obsah,
            $shody
        );
        foreach ($shody[1] as $ref) {
            $reference[] = trim($ref, '/\\ ');
        }

        // src="..." a href="..." - absolutní cesty od root (začínají /)
        preg_match_all(
            '/(?:src|href|action)\s*=\s*["\']\/([^"\'?#\s]+\.(?:php|js|css|html|htm))["\']/',
            $obsah,
            $shody
        );
        foreach ($shody[1] as $ref) {
            $reference[] = trim($ref, '/\\ ');
        }

        // src="..." relativní cesty (bez lomítka na začátku)
        preg_match_all(
            '/(?:src|href)\s*=\s*["\']((?!http|\/\/|data:|#)[^"\'?#\s]+\.(?:js|css|php))["\']/',
            $obsah,
            $shody
        );
        foreach ($shody[1] as $ref) {
            if (strpos($ref, '://') === false) {
                $reference[] = trim($ref, '/\\ ');
            }
        }
    }

    if ($pripona === 'js') {
        // ES6 import
        preg_match_all('/import\s+.*?\s+from\s+["\']([^"\']+)["\']/s', $obsah, $shody);
        foreach ($shody[1] as $ref) {
            $reference[] = ltrim($ref, './');
        }

        // CommonJS require()
        preg_match_all('/require\s*\(\s*["\']([^"\']+)["\']\s*\)/', $obsah, $shody);
        foreach ($shody[1] as $ref) {
            $reference[] = ltrim($ref, './');
        }
    }

    if ($pripona === 'css') {
        // @import
        preg_match_all('/@import\s+(?:url\s*\()?\s*["\']?([^"\'?\s)]+)["\']?\s*\)?/', $obsah, $shody);
        foreach ($shody[1] as $ref) {
            $reference[] = ltrim($ref, './');
        }
    }

    return array_unique(array_filter($reference));
}

/**
 * Extrahuje reference z obsahu souboru včetně čísla řádku a snippetu.
 * Vrací pole ['ref' => string, 'radek' => int, 'snippet' => string]
 */
function extrahovatReferenceDetailed(string $absolutniCesta): array
{
    $pripona      = strtolower(pathinfo($absolutniCesta, PATHINFO_EXTENSION));
    $nazevSouboru = basename($absolutniCesta);

    if ($nazevSouboru === '.htaccess') {
        return [];
    }
    if (!in_array($pripona, ['php', 'js', 'css', 'html', 'htm'])) {
        return [];
    }

    $velikost = @filesize($absolutniCesta);
    if ($velikost === false || $velikost > 2 * 1024 * 1024) {
        return [];
    }

    $obsah = @file_get_contents($absolutniCesta);
    if ($obsah === false) {
        return [];
    }

    $radky     = explode("\n", $obsah);
    $reference = [];

    foreach ($radky as $i => $radek) {
        $cisloRadku = $i + 1;
        $snippet    = mb_substr(trim($radek), 0, 140);

        if (in_array($pripona, ['php', 'html', 'htm'])) {
            // PHP require/include
            if (preg_match(
                '/(?:require_once|require|include_once|include)\s*\(?\s*(?:__DIR__\s*\.\s*)?["\']([^"\']+)["\']\s*\)?/i',
                $radek, $m
            )) {
                $reference[] = ['ref' => trim($m[1], '/\\ '), 'radek' => $cisloRadku, 'snippet' => $snippet];
            }
            // src/href/action – absolutní cesty
            preg_match_all(
                '/(?:src|href|action)\s*=\s*["\']\/([^"\'?#\s]+\.(?:php|js|css|html|htm))["\']/i',
                $radek, $ms
            );
            foreach ($ms[1] as $ref) {
                $reference[] = ['ref' => trim($ref, '/\\ '), 'radek' => $cisloRadku, 'snippet' => $snippet];
            }
            // src/href – relativní cesty
            preg_match_all(
                '/(?:src|href)\s*=\s*["\']((?!http|\/\/|data:|#)[^"\'?#\s]+\.(?:js|css|php))["\']/i',
                $radek, $ms
            );
            foreach ($ms[1] as $ref) {
                if (strpos($ref, '://') === false) {
                    $reference[] = ['ref' => trim($ref, '/\\ '), 'radek' => $cisloRadku, 'snippet' => $snippet];
                }
            }
        }

        if ($pripona === 'js') {
            if (preg_match('/import\s+.*?\s+from\s+["\']([^"\']+)["\']/s', $radek, $m)) {
                $reference[] = ['ref' => ltrim($m[1], './'), 'radek' => $cisloRadku, 'snippet' => $snippet];
            }
            if (preg_match('/require\s*\(\s*["\']([^"\']+)["\']\s*\)/', $radek, $m)) {
                $reference[] = ['ref' => ltrim($m[1], './'), 'radek' => $cisloRadku, 'snippet' => $snippet];
            }
        }

        if ($pripona === 'css') {
            if (preg_match('/@import\s+(?:url\s*\()?\s*["\']?([^"\'?\s)]+)["\']?\s*\)?/', $radek, $m)) {
                $reference[] = ['ref' => ltrim($m[1], './'), 'radek' => $cisloRadku, 'snippet' => $snippet];
            }
        }
    }

    // Deduplikace podle ref+radek
    $seen   = [];
    $unique = [];
    foreach ($reference as $r) {
        $klic = $r['ref'] . ':' . $r['radek'];
        if (!isset($seen[$klic])) {
            $seen[$klic] = true;
            $unique[]    = $r;
        }
    }
    return $unique;
}

/**
 * Prohledá celý projekt a najde soubory obsahující $hledanyNazev jako řetězec.
 * Vrací max. $maxVysledku výsledků s soubor+řádek+snippet.
 */
function grepSouborVProjektu(string $hledanyNazev, string $vlastniCesta, string $koren, array $vyloucene, int $maxVysledku = 60): array
{
    if (empty($hledanyNazev)) {
        return [];
    }

    $soubory         = scanSouboru($koren, $vyloucene);
    $textovePripony  = ['php', 'js', 'css', 'html', 'htm', 'json', 'yml', 'yaml', 'txt', 'md', 'sql', 'sh'];
    $vysledky        = [];
    $celkem          = 0;

    foreach ($soubory as $soubor) {
        if ($soubor['cesta'] === $vlastniCesta) {
            continue;
        }

        $pripona   = strtolower(pathinfo($soubor['nazev'], PATHINFO_EXTENSION));
        $nazevSoub = $soubor['nazev'];

        if (!in_array($pripona, $textovePripony) && $nazevSoub !== '.htaccess') {
            continue;
        }
        if ($soubor['velikost'] > 1024 * 1024) {
            continue;
        }

        $obsah = @file_get_contents($soubor['absolutniCesta']);
        if ($obsah === false || strpos($obsah, $hledanyNazev) === false) {
            continue;
        }

        $radky = explode("\n", $obsah);
        foreach ($radky as $i => $radek) {
            if (strpos($radek, $hledanyNazev) !== false) {
                $vysledky[] = [
                    'soubor'  => $soubor['cesta'],
                    'radek'   => $i + 1,
                    'snippet' => mb_substr(trim($radek), 0, 160),
                ];
                $celkem++;
                if ($celkem >= $maxVysledku) {
                    break 2;
                }
            }
        }
    }

    return $vysledky;
}

/**
 * Zkontroluje speciální soubory (sw.php, manifest.json, .github/workflows/)
 * na výskyt hledaného názvu souboru.
 */
function kontrolujSpecialniSoubory(string $nazev, string $koren): array
{
    $nalezeno = [];

    $souboryKe = [
        'sw.php'        => 'Service Worker',
        'sw.js'         => 'Service Worker JS',
        'manifest.json' => 'PWA Manifest',
    ];

    foreach ($souboryKe as $soubor => $popis) {
        $plna = $koren . '/' . $soubor;
        if (!file_exists($plna)) {
            continue;
        }
        $obsah = @file_get_contents($plna);
        if ($obsah === false || strpos($obsah, $nazev) === false) {
            continue;
        }
        foreach (explode("\n", $obsah) as $i => $radek) {
            if (strpos($radek, $nazev) !== false) {
                $nalezeno[] = [
                    'soubor'  => $soubor,
                    'typ'     => 'spec',
                    'popis'   => $popis,
                    'radek'   => $i + 1,
                    'snippet' => mb_substr(trim($radek), 0, 140),
                ];
            }
        }
    }

    // .github/workflows/
    $workflowsDir = $koren . '/.github/workflows';
    if (is_dir($workflowsDir)) {
        $yamly = glob($workflowsDir . '/*.yml') ?: [];
        foreach ($yamly as $yml) {
            $obsah = @file_get_contents($yml);
            if ($obsah === false || strpos($obsah, $nazev) === false) {
                continue;
            }
            $kratkyNazev = '.github/workflows/' . basename($yml);
            foreach (explode("\n", $obsah) as $i => $radek) {
                if (strpos($radek, $nazev) !== false) {
                    $nalezeno[] = [
                        'soubor'  => $kratkyNazev,
                        'typ'     => 'workflow',
                        'popis'   => 'CI/CD Workflow',
                        'radek'   => $i + 1,
                        'snippet' => mb_substr(trim($radek), 0, 140),
                    ];
                }
            }
        }
    }

    return $nalezeno;
}

/**
 * Normalizuje referenci na relativní cestu od root
 * Vrátí null pokud nelze normalizovat
 */
function normalizujReferenci(string $ref, string $souborovaCesta, string $koren): ?string
{
    // Odstraň __DIR__ . ' notaci (zbytek po extrakci)
    $ref = preg_replace('/^__DIR__\s*\.\s*[\'"]?\/?/', '', $ref);
    $ref = trim($ref, '"\'/ ');

    if (empty($ref)) {
        return null;
    }

    // Pokud začíná /, je root-relativní
    if (isset($ref[0]) && $ref[0] === '/') {
        $absolutni = realpath($koren . $ref);
    } else {
        // Relativní k adresáři souboru
        $souborovyAdresar = dirname($koren . '/' . $souborovaCesta);
        $absolutni = realpath($souborovyAdresar . '/' . $ref);
    }

    if ($absolutni === false) {
        return null;
    }

    // Ověřit, že je uvnitř root adresáře (bezpečnost)
    if (strpos($absolutni, $koren) !== 0) {
        return null;
    }

    $rel = ltrim(str_replace('\\', '/', str_replace($koren, '', $absolutni)), '/');
    return $rel ?: null;
}

/**
 * Sestaví kompletní mapu závislostí pro všechny soubory.
 * Navíc vrací $vyuzivaniDetaily: cesta → [{'soubor','radek','snippet'}]
 * pro přesné zobrazení "kde a na jakém řádku je soubor referencován".
 */
function sestavMapuZavislosti(array $vsechnySoubory, string $koren): array
{
    // Mapa basename -> [relativní cesty]
    $mapaJmen = [];
    foreach ($vsechnySoubory as $s) {
        $mapaJmen[$s['nazev']][] = $s['cesta'];
    }

    // Mapa relativní cesta -> true (pro rychlé vyhledávání)
    $mapaCest = [];
    foreach ($vsechnySoubory as $s) {
        $mapaCest[$s['cesta']] = true;
    }

    $zavislosti      = []; // cesta -> [závislé cesty]
    $vyuzivani       = []; // cesta -> [soubory, které tuto cestu využívají]
    $vyuzivaniDetaily = []; // cesta -> [{'soubor','radek','snippet'}]

    foreach ($vsechnySoubory as $soubor) {
        // Používáme detailní verzi – vrací radek + snippet navíc
        $referenceDetailed = extrahovatReferenceDetailed($soubor['absolutniCesta']);
        $nalezene  = [];

        foreach ($referenceDetailed as $refData) {
            $ref = $refData['ref'];

            // Pokus o přesnou normalizaci
            $normCesta = normalizujReferenci($ref, $soubor['cesta'], $koren);

            if ($normCesta && isset($mapaCest[$normCesta]) && $normCesta !== $soubor['cesta']) {
                $nalezene[] = $normCesta;
                $vyuzivani[$normCesta][]        = $soubor['cesta'];
                $vyuzivaniDetaily[$normCesta][] = [
                    'soubor'  => $soubor['cesta'],
                    'radek'   => $refData['radek'],
                    'snippet' => $refData['snippet'],
                ];
            } else {
                // Fallback: shoda pouze podle přesného jména souboru
                // (basename – zamezit false-positive shody různých souborů se stejným jménem)
                $bazJmeno = basename($ref);
                if ($bazJmeno && isset($mapaJmen[$bazJmeno]) && count($mapaJmen[$bazJmeno]) === 1) {
                    // Fallback jen pokud existuje JEDINÝ soubor s tímto jménem (jinak nejisté)
                    foreach ($mapaJmen[$bazJmeno] as $moznasCesta) {
                        if ($moznasCesta !== $soubor['cesta'] && !in_array($moznasCesta, $nalezene)) {
                            $nalezene[] = $moznasCesta;
                            $vyuzivani[$moznasCesta][]        = $soubor['cesta'];
                            $vyuzivaniDetaily[$moznasCesta][] = [
                                'soubor'  => $soubor['cesta'],
                                'radek'   => $refData['radek'],
                                'snippet' => $refData['snippet'],
                            ];
                        }
                    }
                }
            }
        }

        $zavislosti[$soubor['cesta']] = array_unique($nalezene);
    }

    // Deduplikovat vyuzivani + vyuzivaniDetaily (soubor+radek)
    foreach ($vyuzivani as $cesta => $seznam) {
        $vyuzivani[$cesta] = array_values(array_unique($seznam));
    }
    foreach ($vyuzivaniDetaily as $cesta => $seznam) {
        $seen  = [];
        $dedup = [];
        foreach ($seznam as $item) {
            $klic = $item['soubor'] . ':' . $item['radek'];
            if (!isset($seen[$klic])) {
                $seen[$klic] = true;
                $dedup[]     = $item;
            }
        }
        $vyuzivaniDetaily[$cesta] = $dedup;
    }

    return [
        'zavislosti'       => $zavislosti,
        'vyuzivani'        => $vyuzivani,
        'vyuzivaniDetaily' => $vyuzivaniDetaily,
    ];
}

// ============================================================
// KLASIFIKAČNÍ ENGINE
// ============================================================

// Status kódy
define('KL_POUZIVANO',       'USED');           // Nalezeny důkazy využívání
define('KL_BEZ_REFERENCI',   'NO_REFS_STATIC'); // Žádné statické reference, runtime chybí
define('KL_NEJISTE',         'UNCERTAIN');       // Nelze potvrdit – ověřte ručně
define('KL_BEZPECNE_SMAZAT', 'SAFE_TO_DELETE'); // Prošly VŠECHNY kontroly

// Chráněné adresáře 1. úrovně (dynamické includy, autoload, cron)
define('KL_CHRANENE_ADRESARE', [
    'includes', 'config', 'app', 'api', 'cron', 'scripts',
    'setup', 'migrations', 'lib', 'temp', 'data',
]);

// Prefixe veřejně dostupných adresářů (statické assety)
define('KL_VEREJNA_PREFIXE', ['assets/', 'uploads/', 'screen/']);

// Konkrétní soubory vždy USED – kritické systémové soubory
define('KL_KRITICKE_SOUBORY', [
    'robots.txt', 'sitemap.xml', 'manifest.json', 'CNAME',
    'icon192.png', 'icon512.png', 'sw.js', 'sw.php',
    '.htaccess', 'init.php', 'index.php', 'health.php',
]);

// Vzory artefaktů: zálohy, dočasné soubory – kandidáti na smazání po ověření
define('KL_ARTEFAKT_VZORY', [
    '/\.(bak|old|tmp|archive|backup|orig)$/i',
    '/~$/',
    '/\.bak_[a-z0-9_]+$/i',
    '/_old\.[a-z]+$/i',
]);

// Minimální stáří souboru pro SAFE_TO_DELETE (dny)
define('KL_MIN_STARI_DNI', 30);

// Okno runtime auditu (dny)
define('KL_RUNTIME_OKNO_DNI', 14);

/**
 * Načte záznamy runtime auditu z JSONL souboru
 * Formát záznamu: {"ts":1234567890,"cesta":"/assets/js/foo.js","status":200}
 *
 * @return list<array{ts:int,cesta:string,status:int}>
 */
function nactiRuntimeAudit(string $koren): array
{
    $logSoubor = $koren . '/logs/runtime_audit.jsonl';
    if (!file_exists($logSoubor) || !is_readable($logSoubor)) {
        return [];
    }

    $hranice  = time() - (KL_RUNTIME_OKNO_DNI * 86400);
    $zaznamy  = [];
    $maxRadku = 50000; // ochrana před obrovskými logy
    $pocet    = 0;

    $handle = @fopen($logSoubor, 'r');
    if (!$handle) {
        return [];
    }

    while (($radek = fgets($handle)) !== false && $pocet < $maxRadku) {
        $pocet++;
        $data = json_decode(trim($radek), true);
        if (is_array($data) && isset($data['ts'], $data['cesta'])
            && is_int($data['ts']) && $data['ts'] >= $hranice
        ) {
            $zaznamy[] = [
                'ts'     => $data['ts'],
                'cesta'  => ltrim($data['cesta'], '/'),
                'status' => (int)($data['status'] ?? 200),
            ];
        }
    }
    fclose($handle);
    return $zaznamy;
}

/**
 * Zkontroluje reference v konfiguračních souborech
 * (composer.json PSR-4 autoload, .github workflows)
 *
 * @return list<string> Nalezené konfig reference
 */
function zkontrolujKonfigReference(string $cesta, string $nazev, string $koren): array
{
    $nalezeno = [];

    // composer.json PSR-4 autoload
    $composerSoubor = $koren . '/composer.json';
    if (file_exists($composerSoubor)) {
        $composer = json_decode(@file_get_contents($composerSoubor), true);
        if (is_array($composer)) {
            $mapy = array_merge(
                $composer['autoload']['psr-4']     ?? [],
                $composer['autoload-dev']['psr-4'] ?? []
            );
            foreach ($mapy as $ns => $dir) {
                $dir = rtrim((string)$dir, '/') . '/';
                if (str_starts_with($cesta, $dir)
                    && strtolower(pathinfo($nazev, PATHINFO_EXTENSION)) === 'php'
                ) {
                    $nalezeno[] = 'composer.json (autoload ' . $ns . '→' . $dir . ')';
                }
            }
            // files[] pole v autoloadu
            foreach ($composer['autoload']['files'] ?? [] as $soubor) {
                if ($soubor === $cesta || basename($soubor) === $nazev) {
                    $nalezeno[] = 'composer.json (autoload.files: ' . $soubor . ')';
                }
            }
        }
    }

    return $nalezeno;
}

/**
 * Zjistí, zda soubor odpovídá vzorům artefaktů (zálohy, dočasné soubory)
 */
function jeArtefakt(string $nazev): bool
{
    foreach (KL_ARTEFAKT_VZORY as $vzor) {
        if (preg_match($vzor, $nazev)) {
            return true;
        }
    }
    return false;
}

/**
 * Sestaví výsledkový array klasifikace
 *
 * @param list<array> $rules
 * @param list<string> $vyuzivaniList
 */
function sestavVysledekKlasifikace(
    string $status,
    array $rules,
    int $stariDni,
    array $vyuzivaniList,
    bool $runtimeDostupny,
    int $runtimeHity
): array {
    return [
        'status'   => $status,
        'reasons'  => $rules,
        'evidence' => [
            'staticke_reference_pocet' => count($vyuzivaniList),
            'staticke_reference'       => array_slice($vyuzivaniList, 0, 10),
            'runtime_dostupny'         => $runtimeDostupny,
            'runtime_hity'             => $runtimeHity,
            'runtime_okno_dni'         => KL_RUNTIME_OKNO_DNI,
            'stari_dni'                => $stariDni,
        ],
    ];
}

/**
 * Klasifikuje soubor – vrátí status, pravidla a evidenci.
 *
 * Status kódy:
 *   USED           = nalezeny důkazy využívání
 *   NO_REFS_STATIC = žádné statické reference, runtime chybí → jen signál
 *   UNCERTAIN      = nelze potvrdit bezpečnost → výchozí stav nejistoty
 *   SAFE_TO_DELETE = prošly VŠECHNY kontroly incl. runtime
 *
 * @param string       $cesta          Relativní cesta od rootu
 * @param string       $nazev          Název souboru
 * @param string       $typ            Typ souboru (php, js, css, ...)
 * @param int          $zmeneno        Unix timestamp poslední změny
 * @param int          $pocetVyuzivani Počet souborů odkazujících na tento (statická analýza)
 * @param list<string> $vyuzivaniList  Které soubory odkazují
 * @param string       $koren          Absolutní cesta ke kořeni projektu
 * @param list<array>  $runtimeData    Runtime audit záznamy (prázdné = audit vypnutý)
 */
function klasifikovatSoubor(
    string $cesta,
    string $nazev,
    string $typ,
    int $zmeneno,
    int $pocetVyuzivani,
    array $vyuzivaniList,
    string $koren,
    array $runtimeData
): array {
    $rules    = [];
    $ted      = time();
    $stariDni = max(0, (int)(($ted - $zmeneno) / 86400));
    // Adresář 1. úrovně ('' = root)
    $adresar  = str_contains($cesta, '/') ? explode('/', $cesta)[0] : '';

    // === R01: Statické reference ===
    $r01Passed = $pocetVyuzivani === 0;
    $rules[] = [
        'rule_id' => 'R01',
        'nazev'   => 'Statické reference (include/require/src/href/import)',
        'passed'  => $r01Passed,
        'details' => $r01Passed
            ? 'Žádné statické reference nenalezeny ve skenování kódu'
            : 'Nalezeno ' . $pocetVyuzivani . ' souborů odkazujících na tento soubor: '
              . implode(', ', array_slice($vyuzivaniList, 0, 5))
              . (count($vyuzivaniList) > 5 ? ' … a ' . (count($vyuzivaniList) - 5) . ' dalších' : ''),
        'zdroj'   => 'statická analýza kódu (regex scan include/require/src/href/import)',
    ];

    if (!$r01Passed) {
        // Má reference → USED, nepotřebujeme další kontroly
        return sestavVysledekKlasifikace(
            KL_POUZIVANO, $rules, $stariDni, $vyuzivaniList, false, 0
        );
    }

    // === R02: Kritický/systémový soubor ===
    $jeKriticky = in_array($nazev, KL_KRITICKE_SOUBORY, true)
               || in_array($cesta, KL_KRITICKE_SOUBORY, true);
    $rules[] = [
        'rule_id' => 'R02',
        'nazev'   => 'Kritický/systémový soubor (allowlist)',
        'passed'  => !$jeKriticky,
        'details' => $jeKriticky
            ? 'Soubor je na allowlistu kritických souborů (robots.txt, sw.js, init.php, …)'
            : 'Soubor není na allowlistu kritických souborů',
        'zdroj'   => 'seznam KL_KRITICKE_SOUBORY',
    ];

    if ($jeKriticky) {
        return sestavVysledekKlasifikace(
            KL_POUZIVANO, $rules, $stariDni, $vyuzivaniList, false, 0
        );
    }

    // === R03: Config/build reference (composer autoload, CI) ===
    $konfigRef = zkontrolujKonfigReference($cesta, $nazev, $koren);
    $rules[] = [
        'rule_id' => 'R03',
        'nazev'   => 'Config/build reference (composer.json autoload, CI workflows)',
        'passed'  => empty($konfigRef),
        'details' => empty($konfigRef)
            ? 'Žádné config/build reference nenalezeny'
            : 'Nalezena reference v: ' . implode(', ', $konfigRef),
        'zdroj'   => 'composer.json (autoload/files), .github/workflows/',
    ];

    if (!empty($konfigRef)) {
        return sestavVysledekKlasifikace(
            KL_POUZIVANO, $rules, $stariDni, $vyuzivaniList, false, 0
        );
    }

    // === R04: Chráněný adresář ===
    $jeChraneny = $adresar !== '' && in_array($adresar, KL_CHRANENE_ADRESARE, true);
    $rules[] = [
        'rule_id' => 'R04',
        'nazev'   => 'Chráněný adresář (includes/, config/, app/, api/, cron/, …)',
        'passed'  => !$jeChraneny,
        'details' => $jeChraneny
            ? 'Soubor je v chráněném adresáři "' . $adresar . '/" — může být dynamicky include/autoload/cron, '
              . 'statická analýza nemusí zachytit všechna volání'
            : 'Soubor není v chráněném adresáři',
        'zdroj'   => 'seznam KL_CHRANENE_ADRESARE',
    ];

    // === R05: Veřejný URL entrypoint (PHP soubor v rootu) ===
    $jeRootPhp = $typ === 'php' && $adresar === '';
    $rules[] = [
        'rule_id' => 'R05',
        'nazev'   => 'Veřejný URL entrypoint (PHP soubor v rootu – .htaccess clean URL)',
        'passed'  => !$jeRootPhp,
        'details' => $jeRootPhp
            ? 'PHP soubor v rootu je přístupný jako URL (přes .htaccess RewriteRule ^(.+)$ $1.php)'
            : 'Soubor není PHP entrypoint v rootu',
        'zdroj'   => '.htaccess rewrite pravidla',
    ];

    // === R06: Veřejný asset (assets/, uploads/, screen/) ===
    $jeAsset = false;
    foreach (KL_VEREJNA_PREFIXE as $prefix) {
        if (str_starts_with($cesta, $prefix)) {
            $jeAsset = true;
            break;
        }
    }
    $rules[] = [
        'rule_id' => 'R06',
        'nazev'   => 'Veřejný asset (assets/, uploads/, screen/)',
        'passed'  => !$jeAsset,
        'details' => $jeAsset
            ? 'Soubor je pod veřejnou asset cestou — .htaccess: RewriteRule ^assets/ - [L]. '
              . 'Může být cachován klientem nebo volán dynamicky (lazy load, SW cache).'
            : 'Soubor není pod veřejnou asset cestou',
        'zdroj'   => '.htaccess RewriteRule ^assets/ - [L]',
    ];

    // Chráněný nebo asset → UNCERTAIN (ne SAFE, nelze říct ani USED jen ze statiky)
    if ($jeChraneny || $jeAsset) {
        return sestavVysledekKlasifikace(
            KL_NEJISTE, $rules, $stariDni, $vyuzivaniList, !empty($runtimeData), 0
        );
    }

    // Root PHP → UNCERTAIN (přístupný přes URL, ale bez referencí = pravděpodobně nevyužívaný)
    if ($jeRootPhp) {
        return sestavVysledekKlasifikace(
            KL_NEJISTE, $rules, $stariDni, $vyuzivaniList, !empty($runtimeData), 0
        );
    }

    // === R07: Artefakt (záloha/dočasný soubor) ===
    $artefakt = jeArtefakt($nazev);
    $rules[] = [
        'rule_id' => 'R07',
        'nazev'   => 'Artefakt (záloha/dočasný soubor: .bak, .old, .tmp, …)',
        'passed'  => $artefakt, // true = jde o artefakt = kandidát na smazání
        'details' => $artefakt
            ? 'Soubor odpovídá vzoru artefaktu — pravděpodobně záloha nebo dočasný soubor'
            : 'Soubor neodpovídá vzorům artefaktů',
        'zdroj'   => 'seznam KL_ARTEFAKT_VZORY (*.bak, *.old, *.tmp, …)',
    ];

    // === R08: Runtime audit ===
    $runtimeDostupny = !empty($runtimeData);
    $runtimeHity     = 0;

    if ($runtimeDostupny) {
        $hranice = $ted - (KL_RUNTIME_OKNO_DNI * 86400);
        foreach ($runtimeData as $hit) {
            if ($hit['ts'] < $hranice) {
                continue;
            }
            $hitCesta = $hit['cesta'];
            if ($hitCesta === $cesta
                || $hitCesta === '/' . $cesta
                || basename($hitCesta) === $nazev
                || str_ends_with($hitCesta, '/' . $nazev)
            ) {
                $runtimeHity++;
            }
        }
    }

    $rules[] = [
        'rule_id' => 'R08',
        'nazev'   => 'Runtime audit (produkční HTTP requesty, ' . KL_RUNTIME_OKNO_DNI . ' dní)',
        'passed'  => $runtimeDostupny && $runtimeHity === 0,
        'details' => !$runtimeDostupny
            ? 'Runtime audit není aktivní — nelze ověřit produkční využití. '
              . 'Aktivujte runtime audit pro spolehlivou klasifikaci (viz docs/SOUBORY_KLASIFIKACE.md).'
            : ($runtimeHity > 0
                ? 'Nalezeno ' . $runtimeHity . ' HTTP requestů v posledních ' . KL_RUNTIME_OKNO_DNI . ' dnech'
                : 'Žádné HTTP requesty v posledních ' . KL_RUNTIME_OKNO_DNI . ' dnech'),
        'zdroj'   => $runtimeDostupny ? 'logs/runtime_audit.jsonl' : 'N/A – audit není zapnutý',
    ];

    if ($runtimeHity > 0) {
        return sestavVysledekKlasifikace(
            KL_POUZIVANO, $rules, $stariDni, $vyuzivaniList, true, $runtimeHity
        );
    }

    // Bez runtime dat → jen NO_REFS_STATIC (statická analýza nestačí)
    if (!$runtimeDostupny) {
        return sestavVysledekKlasifikace(
            KL_BEZ_REFERENCI, $rules, $stariDni, $vyuzivaniList, false, 0
        );
    }

    // === R09: Stáří souboru ===
    $dostatecneStary = $stariDni >= KL_MIN_STARI_DNI;
    $rules[] = [
        'rule_id' => 'R09',
        'nazev'   => 'Stáří souboru (min. ' . KL_MIN_STARI_DNI . ' dní od poslední změny)',
        'passed'  => $dostatecneStary || $artefakt,
        'details' => ($dostatecneStary || $artefakt)
            ? 'Soubor nebyl změněn ' . $stariDni . ' dní'
              . ($artefakt ? ' (+ jde o artefakt – stáří prominuto)' : '')
            : 'Soubor byl změněn před ' . $stariDni . ' dny — příliš čerstvý pro bezpečné smazání',
        'zdroj'   => 'mtime souboru: ' . date('Y-m-d', $zmeneno),
    ];

    // === Finální rozhodnutí ===
    // SAFE_TO_DELETE pouze pokud VŠECHNA pravidla prošla
    $vseProslo = $r01Passed             // žádné statické reference
              && !$jeKriticky           // není kritický soubor
              && empty($konfigRef)      // není v config/build
              && !$jeChraneny           // není v chráněném adresáři
              && !$jeRootPhp            // není root PHP entrypoint
              && !$jeAsset              // není veřejný asset
              && $runtimeDostupny       // runtime audit dostupný
              && $runtimeHity === 0     // 0 runtime requestů v okně
              && ($dostatecneStary || $artefakt); // dostatečně starý NEBO artefakt

    return sestavVysledekKlasifikace(
        $vseProslo ? KL_BEZPECNE_SMAZAT : KL_NEJISTE,
        $rules, $stariDni, $vyuzivaniList, true, 0
    );
}

// ============================================================
// ZPRACOVÁNÍ AKCE
// ============================================================

// Soubory, které NELZE fyzicky smazat (ochrana systému)
$chranenesoubory = [
    'init.php',
    'admin.php',
    'login.php',
    'index.php',
    'config/config.php',
    'config/database.php',
    'includes/csrf_helper.php',
    'includes/error_handler.php',
    'includes/env_loader.php',
    'includes/security_headers.php',
    'includes/user_session_check.php',
    'api/soubory_api.php',
    '.htaccess',
];

// Adresáře a soubory přeskočit při skenování
$vylouceneAdresare = [
    '.git', 'node_modules', 'vendor', 'logs', 'backups',
    'uploads', '.github', 'cache', '__pycache__', 'tests',
];

switch ($akce) {

    case 'seznam':
        $nocache  = isset($_GET['nocache']) && $_GET['nocache'] === '1';
        $cacheTtl = 300; // 5 minut

        // Pokusit se načíst z cache
        if (!$nocache && file_exists($cacheSoubor)) {
            $cacheData = json_decode(file_get_contents($cacheSoubor), true);
            if ($cacheData && isset($cacheData['cas']) && (time() - $cacheData['cas']) < $cacheTtl) {
                // Aktualizovat stavy ze souboru (mohou se měnit mimo cache)
                $stavy = nactiStavSouboru($stavSoubor);
                $pocetOznacenych = 0;
                foreach ($cacheData['soubory'] as &$s) {
                    $s['aktivni']   = !isset($stavy[$s['cesta']]) || $stavy[$s['cesta']] !== 'smazat';
                    $s['oznaceno']  = isset($stavy[$s['cesta']]) && $stavy[$s['cesta']] === 'smazat';
                    if ($s['oznaceno']) {
                        $pocetOznacenych++;
                    }
                }
                unset($s);
                $cacheData['statistiky']['oznaceno'] = $pocetOznacenych;
                echo json_encode([
                    'status'      => 'success',
                    'soubory'     => $cacheData['soubory'],
                    'statistiky'  => $cacheData['statistiky'],
                    'zCache'      => true,
                    'cacheCas'    => date('H:i:s', $cacheData['cas']),
                ]);
                exit;
            }
        }

        // Čerstvý sken
        $zacatek          = microtime(true);
        $vsechnySoubory   = scanSouboru($korenAdresar, $vylouceneAdresare);
        $graf             = sestavMapuZavislosti($vsechnySoubory, $korenAdresar);
        $zavislosti       = $graf['zavislosti'];
        $vyuzivani        = $graf['vyuzivani'];
        $vyuzivaniDetaily = $graf['vyuzivaniDetaily'];
        $stavy            = nactiStavSouboru($stavSoubor);
        $runtimeData      = nactiRuntimeAudit($korenAdresar);

        $vysledek   = [];
        $statistiky = [
            'celkem'         => 0,
            'php'            => 0,
            'js'             => 0,
            'css'            => 0,
            'ostatni'        => 0,
            'bezVyuziti'     => 0, // zpětná kompatibilita – počet NO_REFS_STATIC
            'oznaceno'       => 0,
            'pocetUsed'      => 0,
            'pocetBezRef'    => 0,
            'pocetNejiste'   => 0,
            'pocetBezpecne'  => 0,
            'runtimeAktivni' => !empty($runtimeData),
        ];

        foreach ($vsechnySoubory as $soubor) {
            $cesta          = $soubor['cesta'];
            $adresar        = $soubor['adresar'];
            $typ            = ziskejTypSouboru($soubor['nazev']);
            $sobZavislosti        = $zavislosti[$cesta] ?? [];
            $sobVyuzivani         = $vyuzivani[$cesta] ?? [];
            $sobVyuzivaniDetaily  = $vyuzivaniDetaily[$cesta] ?? [];
            $pocetVyuzivani       = count($sobVyuzivani);
            $aktivni        = !isset($stavy[$cesta]) || $stavy[$cesta] !== 'smazat';
            $oznaceno       = isset($stavy[$cesta]) && $stavy[$cesta] === 'smazat';

            // Kategorie souboru (jednoduché, pro rychlé filtrování v UI)
            $jeStranka  = ($typ === 'php' && $adresar === '');
            $jeApi      = ($typ === 'php' && ($adresar === 'api' || str_starts_with($adresar, 'api/')));
            $jeMigrace  = ($typ === 'php' && $adresar === '' && (
                str_starts_with($soubor['nazev'], 'pridej_') ||
                str_starts_with($soubor['nazev'], 'migrace_') ||
                str_starts_with($soubor['nazev'], 'kontrola_') ||
                str_starts_with($soubor['nazev'], 'vycisti_') ||
                str_starts_with($soubor['nazev'], 'doplnit_') ||
                str_starts_with($soubor['nazev'], 'setup_')
            ));
            $jeMinifikace = str_contains($soubor['nazev'], '.min.');

            if ($jeStranka && $jeMigrace) {
                $kategorie = 'migrace';
            } elseif ($jeStranka) {
                $kategorie = 'stranka';
            } elseif ($jeApi) {
                $kategorie = 'api';
            } elseif ($jeMinifikace) {
                $kategorie = 'minifikace';
            } elseif ($pocetVyuzivani === 0 && in_array($typ, ['php', 'js', 'css'])) {
                $kategorie = 'neuzivane';
            } else {
                $kategorie = 'aktivni';
            }

            // Klasifikační engine – přesná analýza s důkazy
            $klasifikace = klasifikovatSoubor(
                $cesta,
                $soubor['nazev'],
                $typ,
                $soubor['zmeneno'],
                $pocetVyuzivani,
                $sobVyuzivani,
                $korenAdresar,
                $runtimeData
            );

            $polozka = [
                'nazev'           => $soubor['nazev'],
                'cesta'           => $cesta,
                'adresar'         => $adresar,
                'typ'             => $typ,
                'kategorie'       => $kategorie,
                'jeStranka'       => $jeStranka,
                'jeApi'           => $jeApi,
                'jeMigrace'       => $jeMigrace,
                'jeMinifikace'    => $jeMinifikace,
                'velikost'        => $soubor['velikost'],
                'velikostText'    => formatovatVelikost($soubor['velikost']),
                'zmeneno'         => $soubor['zmeneno'],
                'zavislosti'          => $sobZavislosti,
                'vyuzivani'           => $sobVyuzivani,
                'vyuzivaniDetaily'    => array_slice($sobVyuzivaniDetaily, 0, 15), // max 15 záznamů v cache
                'pocetZavislosti'     => count($sobZavislosti),
                'pocetVyuzivani'      => $pocetVyuzivani,
                'bezpecneSmazat'  => $klasifikace['status'] === KL_BEZPECNE_SMAZAT,
                'klasifikace'     => $klasifikace,
                'aktivni'         => $aktivni,
                'oznaceno'        => $oznaceno,
            ];

            $vysledek[] = $polozka;

            $statistiky['celkem']++;
            match ($typ) {
                'php'   => $statistiky['php']++,
                'js'    => $statistiky['js']++,
                'css'   => $statistiky['css']++,
                default => $statistiky['ostatni']++,
            };

            match ($klasifikace['status']) {
                KL_POUZIVANO       => $statistiky['pocetUsed']++,
                KL_BEZ_REFERENCI   => $statistiky['pocetBezRef']++,
                KL_NEJISTE         => $statistiky['pocetNejiste']++,
                KL_BEZPECNE_SMAZAT => $statistiky['pocetBezpecne']++,
                default            => null,
            };

            // zpětná kompatibilita
            if ($klasifikace['status'] === KL_BEZ_REFERENCI) {
                $statistiky['bezVyuziti']++;
            }
            if ($oznaceno) {
                $statistiky['oznaceno']++;
            }
        }

        $dobu = round(microtime(true) - $zacatek, 2);
        $statistiky['dobaSken'] = $dobu . 's';

        // Uložit do cache (bez dynamických polí aktivni/oznaceno)
        $cachePolozky = $vysledek;
        foreach ($cachePolozky as &$p) {
            unset($p['aktivni'], $p['oznaceno']);
        }
        unset($p);

        file_put_contents($cacheSoubor, json_encode([
            'cas'        => time(),
            'soubory'    => $cachePolozky,
            'statistiky' => $statistiky,
        ], JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'status'     => 'success',
            'soubory'    => $vysledek,
            'statistiky' => $statistiky,
            'zCache'     => false,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'prepnout':
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['status' => 'chyba', 'zprava' => 'Neplatný CSRF token']);
            exit;
        }

        $cesta       = $_POST['cesta'] ?? '';
        $akceToggle  = $_POST['akce_toggle'] ?? 'oznacit';

        if (empty($cesta)) {
            echo json_encode(['status' => 'chyba', 'zprava' => 'Chybí cesta souboru']);
            exit;
        }

        // Zabezpečení - zamezit path traversal
        $cesta = str_replace(['../', '..\\.', '..\\', '..'], '', $cesta);
        $cesta = ltrim($cesta, '/\\');

        // Ověřit, že soubor existuje uvnitř root adresáře
        $absolutniCesta = realpath($korenAdresar . '/' . $cesta);
        if ($absolutniCesta === false || strpos($absolutniCesta, $korenAdresar) !== 0) {
            echo json_encode(['status' => 'chyba', 'zprava' => 'Neplatná cesta souboru']);
            exit;
        }

        $stavy = nactiStavSouboru($stavSoubor);

        if ($akceToggle === 'odznacit') {
            unset($stavy[$cesta]);
            $novyStav = 'aktivni';
        } else {
            $stavy[$cesta] = 'smazat';
            $novyStav = 'oznaceno';
        }

        if (ulozStavSouboru($stavSoubor, $stavy)) {
            echo json_encode(['status' => 'success', 'novyStav' => $novyStav]);
        } else {
            echo json_encode(['status' => 'chyba', 'zprava' => 'Nepodařilo se uložit stav']);
        }
        break;

    case 'archivovatOznacene':
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['status' => 'chyba', 'zprava' => 'Neplatný CSRF token']);
            exit;
        }

        $stavy = nactiStavSouboru($stavSoubor);
        $oznaceneCesty = array_keys(array_filter($stavy, fn($v) => $v === 'smazat'));

        if (empty($oznaceneCesty)) {
            echo json_encode(['status' => 'chyba', 'zprava' => 'Žádné soubory nejsou označeny ke smazání']);
            exit;
        }

        // Složka archivu s časovou značkou
        $casovyRazitko = date('Y-m-d_H-i-s');
        $archivAdresar = $korenAdresar . '/_archiv/' . $casovyRazitko;

        if (!mkdir($archivAdresar, 0755, true)) {
            echo json_encode(['status' => 'chyba', 'zprava' => 'Nepodařilo se vytvořit archivní složku']);
            exit;
        }

        $uspesne = [];
        $chyby   = [];

        foreach ($oznaceneCesty as $relativniCesta) {
            // Bezpečnostní kontrola
            $relativniCesta   = str_replace(['../', '..\\.', '..\\', '..'], '', $relativniCesta);
            $relativniCesta   = ltrim($relativniCesta, '/\\');
            $absolutniZdroj   = realpath($korenAdresar . '/' . $relativniCesta);

            if ($absolutniZdroj === false || strpos($absolutniZdroj, $korenAdresar) !== 0) {
                $chyby[] = $relativniCesta . ' (neplatná cesta)';
                continue;
            }

            if (!file_exists($absolutniZdroj)) {
                // Soubor již neexistuje - odstranit ze stavů
                unset($stavy[$relativniCesta]);
                continue;
            }

            // Zachovat adresářovou strukturu v archivu
            $cilAdresar = $archivAdresar . '/' . dirname($relativniCesta);
            if (dirname($relativniCesta) !== '.' && !is_dir($cilAdresar)) {
                mkdir($cilAdresar, 0755, true);
            }

            $absolutniCil = $archivAdresar . '/' . $relativniCesta;

            if (rename($absolutniZdroj, $absolutniCil)) {
                $uspesne[] = $relativniCesta;
                unset($stavy[$relativniCesta]);
            } else {
                $chyby[] = $relativniCesta . ' (přesun selhal)';
            }
        }

        // Uložit aktualizované stavy (bez přesunutých souborů)
        ulozStavSouboru($stavSoubor, $stavy);

        // Zneplatnit cache
        if (file_exists($cacheSoubor)) {
            unlink($cacheSoubor);
        }

        $zprava = 'Archivováno ' . count($uspesne) . ' souborů do _archiv/' . $casovyRazitko;
        if (!empty($chyby)) {
            $zprava .= '. Chyby (' . count($chyby) . '): ' . implode(', ', array_slice($chyby, 0, 3));
        }

        echo json_encode([
            'status'      => 'success',
            'zprava'      => $zprava,
            'archivovano' => count($uspesne),
            'chyby'       => count($chyby),
            'archivSlozka' => '_archiv/' . $casovyRazitko,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'smazatCache':
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['status' => 'chyba', 'zprava' => 'Neplatný CSRF token']);
            exit;
        }

        if (file_exists($cacheSoubor)) {
            unlink($cacheSoubor);
        }
        echo json_encode(['status' => 'success', 'zprava' => 'Cache smazána']);
        break;

    // ----------------------------------------------------------------
    // DETAIL – hloubková analýza jednoho souboru (on-demand)
    // Spouští full-text grep v celém projektu + speciální kontroly
    // ----------------------------------------------------------------
    case 'detail':
        $cesta = $_GET['cesta'] ?? '';

        // Sanitace cesty
        $cesta = str_replace(['../', '..\\.', '..\\', '..'], '', $cesta);
        $cesta = ltrim($cesta, '/\\');

        if (empty($cesta)) {
            http_response_code(400);
            echo json_encode(['status' => 'chyba', 'zprava' => 'Chybí parametr cesta']);
            exit;
        }

        $absolutniCesta = realpath($korenAdresar . '/' . $cesta);
        if ($absolutniCesta === false || strpos($absolutniCesta, $korenAdresar) !== 0) {
            http_response_code(403);
            echo json_encode(['status' => 'chyba', 'zprava' => 'Neplatná cesta souboru']);
            exit;
        }

        if (!file_exists($absolutniCesta)) {
            http_response_code(404);
            echo json_encode(['status' => 'chyba', 'zprava' => 'Soubor neexistuje']);
            exit;
        }

        $nazev = basename($cesta);

        // 1. Full-text grep v celém projektu (max 60 výsledků)
        $grepVysledky = grepSouborVProjektu($nazev, $cesta, $korenAdresar, $vylouceneAdresare);

        // 2. Speciální soubory (sw.php, manifest.json, .github/workflows/)
        $specialniKontroly = kontrolujSpecialniSoubory($nazev, $korenAdresar);

        // 3. Náhled obsahu souboru (prvních 40 řádků)
        $nahlad  = null;
        $pripona = strtolower(pathinfo($nazev, PATHINFO_EXTENSION));
        $textovePriponyNahlad = ['php', 'js', 'css', 'html', 'htm', 'txt', 'json', 'yml', 'yaml', 'sql', 'md', 'sh'];
        $statInfo = @stat($absolutniCesta);
        $velikost = $statInfo ? $statInfo['size'] : 0;

        if (in_array($pripona, $textovePriponyNahlad) && $velikost <= 500 * 1024) {
            $obsah = @file_get_contents($absolutniCesta);
            if ($obsah !== false) {
                $vsechnyRadky = explode("\n", $obsah);
                $nahlad = [
                    'radky'       => array_slice($vsechnyRadky, 0, 40),
                    'celkemRadku' => count($vsechnyRadky),
                ];
            }
        }

        // 4. Shrnutí: celkový počet nálezů grep + speciální
        $pocetGrepVysledku     = count($grepVysledky);
        $pocetSpecialnichNalezu = count($specialniKontroly);
        $celkemNalezu          = $pocetGrepVysledku + $pocetSpecialnichNalezu;

        // Závěr analýzy
        if ($celkemNalezu === 0) {
            $zaver = 'Soubor nebyl nalezen jako řetězec v žádném jiném souboru projektu. '
                   . 'Statická analýza ani full-text grep nenašly žádné reference.';
        } else {
            $zaver = 'Soubor byl nalezen celkem ' . $celkemNalezu . '× v projektu '
                   . '(grep: ' . $pocetGrepVysledku . ', speciální soubory: ' . $pocetSpecialnichNalezu . ').';
        }

        echo json_encode([
            'status'                 => 'success',
            'cesta'                  => $cesta,
            'nazev'                  => $nazev,
            'grepVysledky'           => $grepVysledky,
            'specialniKontroly'      => $specialniKontroly,
            'nahlad'                 => $nahlad,
            'pocetGrepVysledku'      => $pocetGrepVysledku,
            'pocetSpecialnichNalezu' => $pocetSpecialnichNalezu,
            'celkemNalezu'           => $celkemNalezu,
            'zaver'                  => $zaver,
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'chyba', 'zprava' => 'Neznámá akce: ' . htmlspecialchars($akce)]);
}
