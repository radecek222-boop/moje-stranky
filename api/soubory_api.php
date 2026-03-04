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

require_once __DIR__ . '/../includes/soubory_helpers.php';


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

    // ----------------------------------------------------------------
    // HROMADNÁ ANALÝZA – automatická kontrola všech NO_REFS_STATIC kandidátů
    // Single-pass grep přes celý projekt – vrátí dvě skupiny:
    //   bezpecneArchivovat: název souboru nenalezen nikde v projektu
    //   potrebujeKontrolu:  název nalezen – ověřit ručně
    // ----------------------------------------------------------------
    case 'hromadnaAnalyza':
        $zacatek = microtime(true);

        // Načíst ze cache (musí existovat – uživatel nejprve spustí skenování)
        if (!file_exists($cacheSoubor)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'chyba',
                'zprava' => 'Cache není dostupná. Nejprve spusťte skenování (tlačítko Znovu skenovat).',
            ]);
            exit;
        }

        $cacheData = json_decode(file_get_contents($cacheSoubor), true);
        if (!$cacheData || !isset($cacheData['soubory'])) {
            http_response_code(400);
            echo json_encode(['status' => 'chyba', 'zprava' => 'Nepodařilo se načíst cache. Spusťte znovu skenování.']);
            exit;
        }

        $stavy = nactiStavSouboru($stavSoubor);

        // Sestavit seznam kandidátů: NO_REFS_STATIC, nezarchivované, ne .map soubory
        $kandidati = [];
        foreach ($cacheData['soubory'] as $s) {
            $kStatus  = $s['klasifikace']['status'] ?? 'USED';
            $sCesta   = $s['cesta'] ?? '';
            $sNazev   = $s['nazev'] ?? '';
            $sPripona = strtolower(pathinfo($sNazev, PATHINFO_EXTENSION));

            if ($kStatus !== KL_BEZ_REFERENCI) {
                continue;
            }
            // Přeskočit soubory v _archiv/
            if (strpos($sCesta, '_archiv/') === 0) {
                continue;
            }
            // Přeskočit .map soubory (source mapy – jsou false-positive kandidáti)
            if ($sPripona === 'map') {
                continue;
            }
            // Přeskočit prázdný název
            if ($sNazev === '') {
                continue;
            }

            $kandidati[$sCesta] = [
                'cesta'        => $sCesta,
                'nazev'        => $sNazev,
                'velikostText' => $s['velikostText'] ?? '',
                'nalezeno'     => false,
                'kde'          => [],
            ];
        }

        if (empty($kandidati)) {
            echo json_encode([
                'status'             => 'success',
                'bezpecneArchivovat' => [],
                'potrebujeKontrolu'  => [],
                'celkemKandidatu'    => 0,
                'dobaSken'           => '0s',
                'zprava'             => 'Žádné kandidáty nenalezeny (žádné soubory se stavem "Bez referencí").',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Soubory vyloučené z prohledávání (zdroje false-positive)
        $vylouceneZdrojeGrep = [
            'config/soubory_cache.json',
            'config/soubory_stav.json',
        ];

        $textovePriponyGrep = [
            'php', 'js', 'css', 'html', 'htm',
            'json', 'yml', 'yaml', 'txt', 'md', 'sql', 'sh',
        ];

        // Single-pass: projít všechny soubory projektu a hledat jméno každého kandidáta
        $projektSoubory = scanSouboru($korenAdresar, $vylouceneAdresare);

        foreach ($projektSoubory as $soubor) {
            // Přeskočit vyloučené zdroje
            if (in_array($soubor['cesta'], $vylouceneZdrojeGrep)) {
                continue;
            }
            // Přeskočit _archiv/
            if (strpos($soubor['cesta'], '_archiv/') === 0) {
                continue;
            }
            // Pouze textové soubory
            $pripona = strtolower(pathinfo($soubor['nazev'], PATHINFO_EXTENSION));
            if (!in_array($pripona, $textovePriponyGrep) && $soubor['nazev'] !== '.htaccess') {
                continue;
            }
            // Přeskočit velké soubory (> 1 MB)
            if ($soubor['velikost'] > 1024 * 1024) {
                continue;
            }

            $obsah = @file_get_contents($soubor['absolutniCesta']);
            if ($obsah === false) {
                continue;
            }

            // Zkontrolovat každého kandidáta
            foreach ($kandidati as $sCesta => &$kand) {
                // Sám sebe přeskočit
                if ($soubor['cesta'] === $sCesta) {
                    continue;
                }
                // Rychlý test existence jména souboru v obsahu
                if (strpos($obsah, $kand['nazev']) === false) {
                    continue;
                }

                $kand['nalezeno'] = true;

                // Sbírat příklady (max 3)
                if (count($kand['kde']) < 3) {
                    foreach (explode("\n", $obsah) as $i => $radek) {
                        if (strpos($radek, $kand['nazev']) !== false) {
                            $kand['kde'][] = [
                                'soubor'  => $soubor['cesta'],
                                'radek'   => $i + 1,
                                'snippet' => mb_substr(trim($radek), 0, 110),
                            ];
                            if (count($kand['kde']) >= 3) {
                                break;
                            }
                        }
                    }
                }
            }
            unset($kand);
        }

        // Rozdělit do dvou skupin
        $bezpecneArchivovat = [];
        $potrebujeKontrolu  = [];

        foreach ($kandidati as $kand) {
            if ($kand['nalezeno']) {
                $potrebujeKontrolu[] = $kand;
            } else {
                $bezpecneArchivovat[] = $kand;
            }
        }

        $dobu = round(microtime(true) - $zacatek, 2);

        echo json_encode([
            'status'             => 'success',
            'bezpecneArchivovat' => $bezpecneArchivovat,
            'potrebujeKontrolu'  => $potrebujeKontrolu,
            'celkemKandidatu'    => count($kandidati),
            'dobaSken'           => $dobu . 's',
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ----------------------------------------------------------------
    // ARCHIVOVAT VYBRANÉ – archivuje explicitní seznam cest v jednom
    // timestampovaném archivu; alternativa k archivovatOznacene
    // ----------------------------------------------------------------
    case 'archivujVybrane':
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['status' => 'chyba', 'zprava' => 'Neplatný CSRF token']);
            exit;
        }

        $cetyJson = $_POST['cesty'] ?? '';
        if (empty($cetyJson)) {
            echo json_encode(['status' => 'chyba', 'zprava' => 'Chybí seznam cest']);
            exit;
        }

        $cesty = json_decode($cetyJson, true);
        if (!is_array($cesty) || empty($cesty)) {
            echo json_encode(['status' => 'chyba', 'zprava' => 'Neplatný formát seznamu cest']);
            exit;
        }

        $casovyRazitko = date('Y-m-d_H-i-s');
        $archivAdresar = $korenAdresar . '/_archiv/' . $casovyRazitko;

        if (!mkdir($archivAdresar, 0755, true)) {
            echo json_encode(['status' => 'chyba', 'zprava' => 'Nepodařilo se vytvořit archivní složku']);
            exit;
        }

        $uspesne = [];
        $chyby   = [];
        $stavy   = nactiStavSouboru($stavSoubor);

        foreach ($cesty as $relativniCesta) {
            $relativniCesta = str_replace(['../', '..\\.', '..\\', '..'], '', (string)$relativniCesta);
            $relativniCesta = ltrim($relativniCesta, '/\\');

            if (in_array($relativniCesta, $chranenesoubory)) {
                $chyby[] = $relativniCesta . ' (chráněný soubor)';
                continue;
            }

            $absolutniZdroj = realpath($korenAdresar . '/' . $relativniCesta);
            if ($absolutniZdroj === false || strpos($absolutniZdroj, $korenAdresar) !== 0) {
                $chyby[] = $relativniCesta . ' (neplatná cesta)';
                continue;
            }

            if (!file_exists($absolutniZdroj)) {
                $chyby[] = $relativniCesta . ' (soubor neexistuje)';
                continue;
            }

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

        ulozStavSouboru($stavSoubor, $stavy);

        if (file_exists($cacheSoubor)) {
            unlink($cacheSoubor);
        }

        $zprava = 'Archivováno ' . count($uspesne) . ' souborů do _archiv/' . $casovyRazitko;
        if (!empty($chyby)) {
            $zprava .= '. Chyby (' . count($chyby) . '): ' . implode(', ', array_slice($chyby, 0, 3));
        }

        echo json_encode([
            'status'       => 'success',
            'zprava'       => $zprava,
            'archivovano'  => count($uspesne),
            'chyby'        => count($chyby),
            'archivSlozka' => '_archiv/' . $casovyRazitko,
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'chyba', 'zprava' => 'Neznámá akce: ' . htmlspecialchars($akce)]);
}
