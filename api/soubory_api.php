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
 * Sestaví kompletní mapu závislostí pro všechny soubory
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

    $zavislosti = []; // cesta -> [závislé cesty]
    $vyuzivani  = []; // cesta -> [soubory, které tuto cestu využívají]

    foreach ($vsechnySoubory as $soubor) {
        $reference = extrahovatReference($soubor['absolutniCesta']);
        $nalezene  = [];

        foreach ($reference as $ref) {
            // Pokus o přesnou normalizaci
            $normCesta = normalizujReferenci($ref, $soubor['cesta'], $koren);

            if ($normCesta && isset($mapaCest[$normCesta]) && $normCesta !== $soubor['cesta']) {
                $nalezene[] = $normCesta;
                $vyuzivani[$normCesta][] = $soubor['cesta'];
            } else {
                // Fallback: shoda podle jména souboru
                $bazJmeno = basename($ref);
                if ($bazJmeno && isset($mapaJmen[$bazJmeno])) {
                    foreach ($mapaJmen[$bazJmeno] as $moznasCesta) {
                        if ($moznasCesta !== $soubor['cesta'] && !in_array($moznasCesta, $nalezene)) {
                            $nalezene[] = $moznasCesta;
                            $vyuzivani[$moznasCesta][] = $soubor['cesta'];
                        }
                    }
                }
            }
        }

        $zavislosti[$soubor['cesta']] = array_unique($nalezene);
    }

    // Deduplikovat vyuzivani
    foreach ($vyuzivani as $cesta => $seznam) {
        $vyuzivani[$cesta] = array_values(array_unique($seznam));
    }

    return ['zavislosti' => $zavislosti, 'vyuzivani' => $vyuzivani];
}

// ============================================================
// ZPRACOVÁNÍ AKCE
// ============================================================

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
        $zacatek      = microtime(true);
        $vsechnySoubory = scanSouboru($korenAdresar, $vylouceneAdresare);
        $graf         = sestavMapuZavislosti($vsechnySoubory, $korenAdresar);
        $zavislosti   = $graf['zavislosti'];
        $vyuzivani    = $graf['vyuzivani'];
        $stavy        = nactiStavSouboru($stavSoubor);

        $vysledek   = [];
        $statistiky = [
            'celkem'     => 0,
            'php'        => 0,
            'js'         => 0,
            'css'        => 0,
            'ostatni'    => 0,
            'bezVyuziti' => 0,
            'oznaceno'   => 0,
        ];

        foreach ($vsechnySoubory as $soubor) {
            $cesta         = $soubor['cesta'];
            $typ           = ziskejTypSouboru($soubor['nazev']);
            $sobZavislosti = $zavislosti[$cesta] ?? [];
            $sobVyuzivani  = $vyuzivani[$cesta] ?? [];
            $pocetVyuzivani = count($sobVyuzivani);
            $aktivni       = !isset($stavy[$cesta]) || $stavy[$cesta] !== 'smazat';
            $oznaceno      = isset($stavy[$cesta]) && $stavy[$cesta] === 'smazat';
            $bezpecneSmazat = $pocetVyuzivani === 0;

            $polozka = [
                'nazev'          => $soubor['nazev'],
                'cesta'          => $cesta,
                'adresar'        => $soubor['adresar'],
                'typ'            => $typ,
                'velikost'       => $soubor['velikost'],
                'velikostText'   => formatovatVelikost($soubor['velikost']),
                'zmeneno'        => $soubor['zmeneno'],
                'zavislosti'     => $sobZavislosti,
                'vyuzivani'      => $sobVyuzivani,
                'pocetZavislosti' => count($sobZavislosti),
                'pocetVyuzivani' => $pocetVyuzivani,
                'bezpecneSmazat' => $bezpecneSmazat,
                'aktivni'        => $aktivni,
                'oznaceno'       => $oznaceno,
            ];

            $vysledek[] = $polozka;

            $statistiky['celkem']++;
            if ($typ === 'php') {
                $statistiky['php']++;
            } elseif ($typ === 'js') {
                $statistiky['js']++;
            } elseif ($typ === 'css') {
                $statistiky['css']++;
            } else {
                $statistiky['ostatni']++;
            }

            if ($bezpecneSmazat && in_array($typ, ['php', 'js', 'css'])) {
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

    default:
        http_response_code(400);
        echo json_encode(['status' => 'chyba', 'zprava' => 'Neznámá akce: ' . htmlspecialchars($akce)]);
}
