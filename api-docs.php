<?php
/**
 * API Dokumentace - WGS Service
 *
 * Auto-generovaná dokumentace ze zdrojových kódů api/*.php
 * Přístup: pouze admin
 */

require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Přístup odepřen. Vyžadováno administrátorské přihlášení.');
}

session_write_close();

// =============================================
// Extrakce metadat ze zdrojového kódu API
// =============================================

/**
 * Načte PHPDoc komentář a základní metadata ze souboru API.
 */
function nactiMetadataApi(string $soubor): array
{
    $obsah = file_get_contents($soubor);
    if ($obsah === false) {
        return [];
    }

    $meta = [
        'soubor'   => basename($soubor),
        'popis'    => '',
        'akce'     => [],
        'autorizace' => [],
        'metody'   => [],
    ];

    // Extrahovat první /** ... */ blok
    if (preg_match('#/\*\*(.*?)\*/#s', $obsah, $m)) {
        $docblock = $m[1];

        // Popis = první řádek bez hvězdičky
        $radky = array_map(fn($r) => trim(ltrim(trim($r), '*')), explode("\n", $docblock));
        $radky = array_filter($radky, fn($r) => $r !== '' && !str_starts_with($r, '@'));
        $meta['popis'] = implode(' ', array_slice(array_values($radky), 0, 3));
    }

    // Detekovat akce ze switch/case bloků
    preg_match_all("/case\s+'([a-z_]+)'\s*:/", $obsah, $shodyAkci);
    if (!empty($shodyAkci[1])) {
        $meta['akce'] = array_unique($shodyAkci[1]);
    }

    // Detekovat HTTP metody
    if (preg_match("/\\\$_POST/", $obsah))   $meta['metody'][] = 'POST';
    if (preg_match("/\\\$_GET/", $obsah))    $meta['metody'][] = 'GET';
    $meta['metody'] = array_unique($meta['metody']);

    // Detekovat autorizaci
    if (preg_match('/validateCSRFToken/i', $obsah))       $meta['autorizace'][] = 'CSRF';
    if (preg_match('/is_admin/i', $obsah))                $meta['autorizace'][] = 'Admin';
    if (preg_match('/user_id.*SESSION|SESSION.*user_id/i', $obsah)) $meta['autorizace'][] = 'Login';
    if (preg_match('/RateLimiter/i', $obsah))             $meta['autorizace'][] = 'RateLimit';

    return $meta;
}

// Načíst všechna API
$adresarApi = __DIR__ . '/api';
$soubory    = glob($adresarApi . '/*.php');
sort($soubory);

$kategorie = [
    'reklamace'   => ['delete_reklamace', 'zmenit_stav', 'odloz_reklamaci', 'klonovani_api'],
    'zakaznici'   => ['zakaznici_api', 'notes_api', 'soubory_api', 'documents_api', 'get_photos_api', 'delete_photo', 'get_original_documents'],
    'cenik'       => ['pricing_api', 'get_kalkulace_api', 'save_kalkulace_api', 'nabidka_api', 'qr_platba_api'],
    'protokoly'   => ['protokol_api', 'uloz_pdf_mapping', 'parse_povereni_pdf'],
    'technici'    => ['auto_assign_technician', 'tech_provize_api', 'supervisor_api', 'transport_events_api', 'transport_sync'],
    'statistiky'  => ['statistiky_api', 'analytics_api', 'analytics_heatmap', 'track_heatmap', 'track_pageview', 'admin_stats_api', 'get_user_stats'],
    'notifikace'  => ['notification_api', 'notification_list_direct', 'notification_list_html', 'push_subscription_api', 'email_resend_api', 'send_contact_attempt_email'],
    'aktuality'   => ['nacti_aktualitu', 'vytvor_aktualitu', 'uprav_celou_aktualitu', 'uprav_odkaz_aktuality', 'generuj_aktuality', 'generuj_aktuality_nove', 'preloz_aktualitu'],
    'admin'       => ['admin_api', 'admin_users_api', 'admin_bot_whitelist', 'backup_api', 'migration_executor', 'gdpr_zadost'],
    'system'      => ['heartbeat', 'session_keepalive', 'log_js_error', 'geocode_proxy', 'github_webhook', 'debug_errors', 'debug_errors_extended', 'debug_frontend_filter', 'debug_moje_reklamace', 'advanced_diagnostics_api'],
    'ostatni'     => ['flight_api', 'hry_api', 'video_api', 'video_download', 'translate_api'],
];

$nazevKategorie = [
    'reklamace'  => 'Reklamace',
    'zakaznici'  => 'Zákazníci a dokumenty',
    'cenik'      => 'Ceník a kalkulace',
    'protokoly'  => 'Protokoly',
    'technici'   => 'Technici a transport',
    'statistiky' => 'Statistiky a analytika',
    'notifikace' => 'Notifikace a emaily',
    'aktuality'  => 'Aktuality (CMS)',
    'admin'      => 'Administrace',
    'system'     => 'Systémové',
    'ostatni'    => 'Ostatní',
];

// Sestavit data
$skupiny = [];
$nezarazene = [];
$mapaSouboru = [];

foreach ($soubory as $soubor) {
    $zaklad = pathinfo($soubor, PATHINFO_FILENAME);
    $meta = nactiMetadataApi($soubor);
    $mapaSouboru[$zaklad] = $meta;
}

foreach ($kategorie as $klic => $seznamSouboru) {
    $skupiny[$klic] = [];
    foreach ($seznamSouboru as $zaklad) {
        if (isset($mapaSouboru[$zaklad])) {
            $skupiny[$klic][] = $mapaSouboru[$zaklad];
            unset($mapaSouboru[$zaklad]);
        }
    }
}

// Zbytek = nezařazené
foreach ($mapaSouboru as $meta) {
    $skupiny['ostatni'][] = $meta;
}

// Počty
$celkemSouboru = count($soubory);

// Export jako JSON pro strojové zpracování
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    $export = [];
    foreach ($skupiny as $klic => $polozky) {
        foreach ($polozky as $p) {
            $export[] = [
                'soubor'     => $p['soubor'],
                'kategorie'  => $nazevKategorie[$klic] ?? $klic,
                'popis'      => $p['popis'],
                'akce'       => $p['akce'],
                'metody'     => $p['metody'],
                'autorizace' => $p['autorizace'],
            ];
        }
    }
    echo json_encode(['verze' => '1.0', 'celkem' => $celkemSouboru, 'endpoints' => $export], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Dokumentace - WGS Service</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            background: #f5f5f5;
            color: #222;
            line-height: 1.5;
        }

        .hlavicka {
            background: #111;
            color: #fff;
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .hlavicka h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .hlavicka .meta {
            font-size: 12px;
            color: #999;
        }

        .hlavicka .akce-hlavicky {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-export {
            background: #333;
            color: #fff;
            border: 1px solid #555;
            padding: 6px 14px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-export:hover { background: #444; }

        .obsah {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 20px;
        }

        /* Statistiky nahoře */
        .statistiky-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 28px;
        }

        .stat-box {
            background: #fff;
            border: 1px solid #ddd;
            padding: 16px;
            text-align: center;
        }

        .stat-box .cislo {
            font-size: 28px;
            font-weight: 700;
            color: #111;
            display: block;
        }

        .stat-box .popis-stat {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* Filtr */
        .filtr-panel {
            background: #fff;
            border: 1px solid #ddd;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .filtr-panel label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        #vyhledavani {
            flex: 1;
            min-width: 200px;
            padding: 7px 10px;
            border: 1px solid #ccc;
            font-size: 14px;
            color: #222;
            background: #fff;
        }

        #vyhledavani:focus { outline: 2px solid #333; border-color: #333; }

        /* Kategorie */
        .kategorie-sekce {
            margin-bottom: 28px;
        }

        .kategorie-nadpis {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #555;
            border-bottom: 2px solid #333;
            padding-bottom: 6px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .kategorie-pocet {
            background: #333;
            color: #fff;
            font-size: 11px;
            padding: 1px 7px;
            font-weight: 600;
        }

        /* Tabulka endpointů */
        .endpoint-tabulka {
            width: 100%;
            background: #fff;
            border: 1px solid #ddd;
            border-collapse: collapse;
        }

        .endpoint-tabulka th {
            background: #222;
            color: #fff;
            padding: 8px 12px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
        }

        .endpoint-tabulka td {
            padding: 9px 12px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .endpoint-tabulka tr:last-child td { border-bottom: none; }

        .endpoint-tabulka tr:hover td { background: #f9f9f9; }

        /* Soubor */
        .soubor-nazev {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            font-weight: 600;
            color: #111;
        }

        /* Tagy metod */
        .tag {
            display: inline-block;
            padding: 1px 6px;
            font-size: 10px;
            font-weight: 700;
            margin: 1px 2px 1px 0;
            letter-spacing: 0.3px;
        }

        .tag-get    { background: #eee; color: #333; border: 1px solid #ccc; }
        .tag-post   { background: #333; color: #fff; }
        .tag-csrf   { background: #555; color: #fff; }
        .tag-admin  { background: #111; color: #fff; }
        .tag-login  { background: #888; color: #fff; }
        .tag-rate   { background: #666; color: #fff; }

        /* Akce */
        .akce-seznam {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .akce-tag {
            background: #f0f0f0;
            border: 1px solid #ddd;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            padding: 1px 6px;
            color: #444;
        }

        /* Popis */
        .popis-endpoint {
            color: #555;
            font-size: 13px;
            max-width: 320px;
        }

        .zadne-akce { color: #bbb; font-size: 12px; font-style: italic; }

        /* Skryté řádky při vyhledávání */
        .endpoint-radek.skryty { display: none; }

        /* Skrytá sekce */
        .kategorie-sekce.skryta { display: none; }

        /* Patička */
        .paticka {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #aaa;
            border-top: 1px solid #ddd;
            margin-top: 30px;
        }

        @media (max-width: 700px) {
            .endpoint-tabulka th:nth-child(3),
            .endpoint-tabulka td:nth-child(3) { display: none; }
        }
    </style>
</head>
<body>

<div class="hlavicka">
    <div>
        <h1>API Dokumentace</h1>
        <div class="meta">WGS Service &mdash; auto-generováno ze zdrojových kódů &mdash; <?= date('d.m.Y H:i') ?></div>
    </div>
    <div class="akce-hlavicky">
        <a href="?format=json" class="btn-export" target="_blank">Export JSON</a>
        <a href="admin.php" class="btn-export">Zpět do adminu</a>
    </div>
</div>

<div class="obsah">

    <!-- Statistiky -->
    <?php
    $pocetAdmin    = 0;
    $pocetLogin    = 0;
    $pocetCsrf     = 0;
    $pocetVsech    = 0;
    foreach ($skupiny as $polozky) {
        foreach ($polozky as $p) {
            $pocetVsech++;
            if (in_array('Admin', $p['autorizace']))    $pocetAdmin++;
            if (in_array('Login', $p['autorizace']))    $pocetLogin++;
            if (in_array('CSRF', $p['autorizace']))     $pocetCsrf++;
        }
    }
    ?>
    <div class="statistiky-panel">
        <div class="stat-box">
            <span class="cislo"><?= $pocetVsech ?></span>
            <span class="popis-stat">Celkem endpointů</span>
        </div>
        <div class="stat-box">
            <span class="cislo"><?= count(array_filter($skupiny, fn($s) => !empty($s))) ?></span>
            <span class="popis-stat">Kategorií</span>
        </div>
        <div class="stat-box">
            <span class="cislo"><?= $pocetCsrf ?></span>
            <span class="popis-stat">S CSRF ochranou</span>
        </div>
        <div class="stat-box">
            <span class="cislo"><?= $pocetAdmin ?></span>
            <span class="popis-stat">Admin-only</span>
        </div>
        <div class="stat-box">
            <span class="cislo"><?= $pocetLogin ?></span>
            <span class="popis-stat">Vyžaduje login</span>
        </div>
        <div class="stat-box">
            <span class="cislo"><?= $pocetVsech - $pocetCsrf - $pocetAdmin - $pocetLogin ?></span>
            <span class="popis-stat">Veřejné</span>
        </div>
    </div>

    <!-- Vyhledávání -->
    <div class="filtr-panel">
        <label for="vyhledavani">Hledat:</label>
        <input type="text" id="vyhledavani" placeholder="Název souboru, popis nebo akce..." autocomplete="off">
    </div>

    <!-- Skupiny -->
    <?php foreach ($skupiny as $klic => $polozky):
        if (empty($polozky)) continue;
        $nazev = $nazevKategorie[$klic] ?? ucfirst($klic);
    ?>
    <div class="kategorie-sekce" data-kategorie="<?= htmlspecialchars($klic) ?>">
        <div class="kategorie-nadpis">
            <?= htmlspecialchars($nazev) ?>
            <span class="kategorie-pocet"><?= count($polozky) ?></span>
        </div>

        <table class="endpoint-tabulka">
            <thead>
                <tr>
                    <th style="width:200px">Soubor</th>
                    <th>Popis</th>
                    <th style="width:180px">Akce</th>
                    <th style="width:90px">Metody</th>
                    <th style="width:160px">Autorizace</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($polozky as $p): ?>
            <tr class="endpoint-radek"
                data-hledani="<?= htmlspecialchars(strtolower($p['soubor'] . ' ' . $p['popis'] . ' ' . implode(' ', $p['akce']))) ?>">
                <td>
                    <div class="soubor-nazev"><?= htmlspecialchars($p['soubor']) ?></div>
                    <div style="font-size:11px;color:#aaa;margin-top:2px">/api/<?= htmlspecialchars($p['soubor']) ?></div>
                </td>
                <td>
                    <div class="popis-endpoint">
                        <?= $p['popis'] ? htmlspecialchars($p['popis']) : '<span class="zadne-akce">bez popisu</span>' ?>
                    </div>
                </td>
                <td>
                    <?php if (!empty($p['akce'])): ?>
                    <div class="akce-seznam">
                        <?php foreach ($p['akce'] as $akce): ?>
                        <span class="akce-tag"><?= htmlspecialchars($akce) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span class="zadne-akce">přímý endpoint</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php foreach ($p['metody'] as $metoda): ?>
                    <span class="tag tag-<?= strtolower($metoda) ?>"><?= $metoda ?></span>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php foreach ($p['autorizace'] as $auth): ?>
                    <span class="tag tag-<?= strtolower($auth) ?>"><?= htmlspecialchars($auth) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($p['autorizace'])): ?>
                    <span style="color:#bbb;font-size:12px">veřejné</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <div class="paticka">
        WGS Service &mdash; API Dokumentace &mdash; <?= $pocetVsech ?> endpointů
        &mdash; <a href="?format=json" style="color:#888">JSON export</a>
    </div>
</div>

<script>
    (function() {
        var vstup = document.getElementById('vyhledavani');

        vstup.addEventListener('input', function() {
            var dotaz = this.value.trim().toLowerCase();
            var sekce = document.querySelectorAll('.kategorie-sekce');

            sekce.forEach(function(sekce) {
                var radky = sekce.querySelectorAll('.endpoint-radek');
                var viditelneRadky = 0;

                radky.forEach(function(radek) {
                    var text = radek.getAttribute('data-hledani') || '';
                    var shoda = !dotaz || text.includes(dotaz);
                    radek.classList.toggle('skryty', !shoda);
                    if (shoda) viditelneRadky++;
                });

                sekce.classList.toggle('skryta', viditelneRadky === 0 && dotaz !== '');
            });
        });

        vstup.focus();
    })();
</script>

</body>
</html>
