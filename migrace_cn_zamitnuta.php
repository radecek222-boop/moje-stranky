<?php
/**
 * Migrace: CN Zamítnuta - nový stav cenové nabídky
 *
 * Přidává:
 *  - Stav 'zamitnuta' do ENUM wgs_nabidky.stav
 *  - Sloupce: zamitnuta_at, zamitnuto_ip, zamitnuto_kym, pripominka_7d_at, reklamace_id
 *  - Slouží i jako náhled emailu 7 dní před expirací
 *
 * Spusťte vícekrát bezpečně - přidá pouze chybějící sloupce.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.');
}

$pdo = getDbConnection();

// Sekce k zobrazení
$zobrazitNahled = isset($_GET['nahled']) && $_GET['nahled'] === 'email';
$spustitMigraci = isset($_GET['execute']) && $_GET['execute'] === '1';

// ============================================
// NÁHLED EMAILU - 7 DNÍ PŘED EXPIRACÍ
// ============================================
if ($zobrazitNahled) {
    // Vzorová nabídka pro náhled
    $vzorNabidka = [
        'id'               => 999,
        'cislo_nabidky'    => 'CN-2026-02-3-01',
        'zakaznik_jmeno'   => 'Jan Novák',
        'zakaznik_email'   => 'jan.novak@example.cz',
        'zakaznik_telefon' => '+420 777 123 456',
        'zakaznik_adresa'  => 'Hlavní 15, 110 00 Praha 1',
        'mena'             => 'EUR',
        'celkova_cena'     => 1250.00,
        'platnost_do'      => date('Y-m-d H:i:s', strtotime('+7 days')),
        'token'            => 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
        'vytvoreno_at'     => date('Y-m-d H:i:s', strtotime('-23 days')),
        'polozky_json'     => json_encode([
            ['nazev' => 'Oprava čalounění - křeslo', 'pocet' => 1, 'cena' => 450.00],
            ['nazev' => 'Oprava mechanismu pohovky', 'pocet' => 1, 'cena' => 600.00],
            ['nazev' => 'Čištění a impregnace kůže', 'pocet' => 2, 'cena' => 100.00],
        ]),
    ];

    echo vygenerujEmailPripominka7dni($vzorNabidka);
    exit;
}

// ============================================
// FUNKCE EMAILU (sdílená s nabidka_api.php)
// ============================================

function vygenerujEmailPripominka7dni(array $nabidka): string {
    $polozky    = json_decode($nabidka['polozky_json'], true);
    $baseUrl    = 'https://www.wgs-service.cz';
    $token      = urlencode($nabidka['token']);
    $schvalitUrl  = $baseUrl . '/potvrzeni-nabidky.php?token=' . $token;
    $zamitnutUrl  = $baseUrl . '/potvrzeni-nabidky.php?token=' . $token . '&akce=zamitnut';

    $platnostDo     = date('d.m.Y', strtotime($nabidka['platnost_do']));
    $datumVytvoreni = date('d.m.Y', strtotime($nabidka['vytvoreno_at'] ?? 'now'));
    $nabidkaCislo   = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));
    $celkovaCena    = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');

    // Tabulka položek
    $polozkyHtml = '';
    if (is_array($polozky)) {
        foreach ($polozky as $p) {
            $nazev         = htmlspecialchars($p['nazev'] ?? '');
            $pocet         = intval($p['pocet'] ?? 1);
            $cenaJednotka  = floatval($p['cena'] ?? 0);
            $cenaCelkem    = $cenaJednotka * $pocet;
            $cenaFmt       = number_format($cenaJednotka, 2, ',', ' ');
            $celkemFmt     = number_format($cenaCelkem, 2, ',', ' ');

            $polozkyHtml .= "
            <tr>
                <td style='padding: 13px 16px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #333;'>{$nazev}</td>
                <td style='padding: 13px 16px; border-bottom: 1px solid #e5e5e5; text-align: center; font-size: 14px; color: #666;'>{$pocet}</td>
                <td style='padding: 13px 16px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; color: #666;'>{$cenaFmt} {$nabidka['mena']}</td>
                <td style='padding: 13px 16px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; font-weight: 600; color: #333;'>{$celkemFmt} {$nabidka['mena']}</td>
            </tr>";
        }
    }

    // Telefon
    $telefonHtml = '';
    if (!empty($nabidka['zakaznik_telefon'])) {
        $telefonHtml = "<p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>Tel: " . htmlspecialchars($nabidka['zakaznik_telefon']) . "</p>";
    }

    // Adresa
    $adresaHtml = '';
    if (!empty($nabidka['zakaznik_adresa'])) {
        $adresaHtml = "<p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>" . nl2br(htmlspecialchars($nabidka['zakaznik_adresa'])) . "</p>";
    }

    return "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Poslední upozornění - cenová nabídka č. {$nabidkaCislo}</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>

    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f4f4f4;'>
        <tr>
            <td style='padding: 30px 20px;'>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='margin: 0 auto; max-width: 600px;'>

                    <!-- HEADER -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 35px 40px; text-align: center; border-radius: 12px 12px 0 0;'>
                            <h1 style='margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: 2px;'>WHITE GLOVE SERVICE</h1>
                            <p style='margin: 8px 0 0 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px;'>Premium Furniture Care</p>
                        </td>
                    </tr>

                    <!-- URGENTNÍ UPOZORNĚNÍ - pruh -->
                    <tr>
                        <td style='background: #1a1a1a; padding: 16px 40px; border-top: 3px solid #dc3545;'>
                            <p style='margin: 0; font-size: 14px; font-weight: 700; color: #fff; text-align: center; text-transform: uppercase; letter-spacing: 1px;'>
                                Poslední upozornění &mdash; platnost nabídky vyprší za 7 dní
                            </p>
                        </td>
                    </tr>

                    <!-- HLAVNÍ OBSAH -->
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>

                            <!-- Nadpis -->
                            <div style='background: #f8f9fa; padding: 25px 40px; border-bottom: 1px solid #e5e5e5;'>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%'>
                                    <tr>
                                        <td>
                                            <h2 style='margin: 0; font-size: 20px; font-weight: 600; color: #333;'>Cenová nabídka</h2>
                                            <p style='margin: 5px 0 0 0; font-size: 13px; color: #888;'>č. {$nabidkaCislo}</p>
                                        </td>
                                        <td style='text-align: right;'>
                                            <p style='margin: 0; font-size: 13px; color: #666;'>Vystavena: <strong>{$datumVytvoreni}</strong></p>
                                            <p style='margin: 5px 0 0 0; font-size: 13px; color: #666;'>Platná do: <strong style='color: #dc3545;'>{$platnostDo}</strong></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Oslovení a text -->
                            <div style='padding: 30px 40px 20px 40px;'>
                                <p style='margin: 0; font-size: 15px; color: #333;'>Vážený/á <strong>" . htmlspecialchars($nabidka['zakaznik_jmeno']) . "</strong>,</p>
                                <p style='margin: 15px 0 0 0; font-size: 14px; color: #555; line-height: 1.7;'>
                                    před 23 dny jsme Vám zaslali cenovou nabídku na servisní zákrok u Vašeho nábytku Natuzzi.
                                    Platnost nabídky <strong>vyprší za 7 dní</strong> dne <strong style='color: #dc3545;'>{$platnostDo}</strong>.
                                </p>
                                <p style='margin: 12px 0 0 0; font-size: 14px; color: #555; line-height: 1.7;'>
                                    Pokud jste se zatím nerozhodli, rádi Vám zodpovíme veškeré dotazy. Kontaktujte nás na
                                    <a href='mailto:reklamace@wgs-service.cz' style='color: #333; font-weight: 600;'>reklamace@wgs-service.cz</a>
                                    nebo na <a href='tel:+420725965826' style='color: #333; font-weight: 600;'>+420 725 965 826</a>.
                                </p>
                            </div>

                            <!-- Zákazník -->
                            <div style='padding: 0 40px 25px 40px;'>
                                <div style='background: #f8f9fa; border-radius: 8px; padding: 18px 20px;'>
                                    <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;'>Zákazník</p>
                                    <p style='margin: 8px 0 0 0; font-size: 15px; font-weight: 600; color: #333;'>" . htmlspecialchars($nabidka['zakaznik_jmeno']) . "</p>
                                    <p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>" . htmlspecialchars($nabidka['zakaznik_email']) . "</p>
                                    {$telefonHtml}
                                    {$adresaHtml}
                                </div>
                            </div>

                            <!-- Shrnutí položek -->
                            <div style='padding: 0 40px 25px 40px;'>
                                <p style='margin: 0 0 14px 0; font-size: 13px; font-weight: 600; color: #333; text-transform: uppercase; letter-spacing: 0.5px;'>Shrnutí nabídky</p>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;'>
                                    <thead>
                                        <tr style='background: #f8f9fa;'>
                                            <th style='padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Služba</th>
                                            <th style='padding: 12px 16px; text-align: center; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Počet</th>
                                            <th style='padding: 12px 16px; text-align: right; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Cena/ks</th>
                                            <th style='padding: 12px 16px; text-align: right; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Celkem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$polozkyHtml}
                                    </tbody>
                                    <tfoot>
                                        <tr style='background: #1a1a1a;'>
                                            <td colspan='3' style='padding: 16px; text-align: right; font-size: 14px; font-weight: 600; color: #fff;'>Celková cena (bez DPH):</td>
                                            <td style='padding: 16px; text-align: right; font-size: 20px; font-weight: 700; color: #39ff14;'>{$celkovaCena} {$nabidka['mena']}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Box: co se stane po expiraci -->
                            <div style='padding: 0 40px 25px 40px;'>
                                <div style='background: #fff3cd; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px 20px;'>
                                    <p style='margin: 0; font-size: 14px; color: #92400e; line-height: 1.6;'>
                                        <strong>Upozornění:</strong> Pokud na nabídku do <strong>{$platnostDo}</strong> nereagujete,
                                        bude automaticky uzavřena jako odmítnutá a nebude ji možné znovu aktivovat.
                                        Nová nabídka by musela být vystavena znovu.
                                    </p>
                                </div>
                            </div>

                            <!-- CTA tlačítka -->
                            <div style='padding: 0 40px 35px 40px; text-align: center;'>
                                <p style='margin: 0 0 24px 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    Zvolte prosím jednu z možností:
                                </p>

                                <!-- Tlačítko: Potvrdit -->
                                <a href='{$schvalitUrl}' style='display: inline-block; background: #28a745; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; margin-right: 12px; margin-bottom: 12px;'>
                                    Souhlasím s nabídkou
                                </a>

                                <!-- Tlačítko: Odmítnout -->
                                <a href='{$zamitnutUrl}' style='display: inline-block; background: #dc3545; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;'>
                                    Nemám zájem
                                </a>

                                <p style='margin: 20px 0 0 0; font-size: 12px; color: #999; line-height: 1.5;'>
                                    Tlačítky výše potvrzujete svůj záměr. Pokud kliknete na &quot;Souhlasím s nabídkou&quot;,
                                    uzavíráte závaznou smlouvu o dílo dle § 2586 občanského zákoníku.
                                    Pokud kliknete na &quot;Nemám zájem&quot;, nabídka bude uzavřena jako odmítnutá.
                                </p>
                            </div>

                            <!-- Právní patička -->
                            <div style='padding: 25px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #666; line-height: 1.6;'>
                                    Ceny jsou uvedeny bez DPH. Při platbě v CZK bude částka přepočtena dle kurzu ČNB
                                    v den vystavení faktury. Doba dodání originálních dílů z továrny Natuzzi je 4–8 týdnů.
                                </p>
                                <p style='margin: 10px 0 0 0; font-size: 12px; color: #999; line-height: 1.5;'>
                                    Tento email byl odeslán automaticky systémem WGS. Neodpovídejte na tento email přímo.
                                    Kontaktujte nás na <a href='mailto:reklamace@wgs-service.cz' style='color: #666;'>reklamace@wgs-service.cz</a>.
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style='background: #1a1a1a; padding: 30px 40px; border-radius: 0 0 12px 12px; text-align: center;'>
                            <p style='margin: 0; font-size: 14px; font-weight: 600; color: #fff;'>White Glove Service, s.r.o.</p>
                            <p style='margin: 8px 0 0 0; font-size: 13px; color: #888;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
                            <p style='margin: 8px 0 0 0; font-size: 13px; color: #888;'>
                                Tel: <a href='tel:+420725965826' style='color: #888; text-decoration: none;'>+420 725 965 826</a> |
                                Email: <a href='mailto:reklamace@wgs-service.cz' style='color: #888; text-decoration: none;'>reklamace@wgs-service.cz</a>
                            </p>
                            <p style='margin: 15px 0 0 0; font-size: 12px; color: #555;'>
                                <a href='{$baseUrl}' style='color: #39ff14; text-decoration: none;'>www.wgs-service.cz</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>";
}

// ============================================
// KONTROLA AKTUÁLNÍHO STAVU DB
// ============================================

$zpravy = [];
$chyby  = [];

// Ověřit existenci tabulky
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_nabidky'");
    $tabulkaExistuje = (bool)$stmt->fetch();
} catch (PDOException $e) {
    $tabulkaExistuje = false;
}

// Ověřit existenci sloupců
$pozadovaneSloupce = [
    'zamitnuta_at'   => "DATETIME NULL COMMENT 'Kdy byla nabídka zamítnuta'",
    'zamitnuto_ip'   => "VARCHAR(45) NULL COMMENT 'IP adresa při zamítnutí'",
    'zamitnuto_kym'  => "ENUM('zakaznik','admin') NULL COMMENT 'Kdo zamítnul'",
    'pripominka_7d_at' => "DATETIME NULL COMMENT 'Kdy byl odeslán 7-denní reminder'",
    'reklamace_id'   => "INT NULL COMMENT 'Propojení s wgs_reklamace'",
];

$chybejiciSloupce = [];
if ($tabulkaExistuje) {
    foreach (array_keys($pozadovaneSloupce) as $sloupec) {
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE '{$sloupec}'");
        if (!$stmt->fetch()) {
            $chybejiciSloupce[] = $sloupec;
        }
    }

    // Zkontrolovat ENUM stav
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'stav'");
    $stavSloupec = $stmt->fetch(PDO::FETCH_ASSOC);
    $enumObsahujeZamitnuta = $stavSloupec && strpos($stavSloupec['Type'], 'zamitnuta') !== false;
} else {
    $chybejiciSloupce = array_keys($pozadovaneSloupce);
    $enumObsahujeZamitnuta = false;
}

// ============================================
// SPUŠTĚNÍ MIGRACE
// ============================================

if ($spustitMigraci && $tabulkaExistuje) {
    $pdo->beginTransaction();
    $provedeno = 0;

    try {
        // 1. Přidat chybějící sloupce
        foreach ($chybejiciSloupce as $sloupec) {
            $definice = $pozadovaneSloupce[$sloupec];
            $pdo->exec("ALTER TABLE wgs_nabidky ADD COLUMN {$sloupec} {$definice}");
            $zpravy[] = "Přidán sloupec: {$sloupec}";
            $provedeno++;
        }

        // 2. Aktualizovat ENUM - přidat 'zamitnuta'
        if (!$enumObsahujeZamitnuta) {
            $pdo->exec("ALTER TABLE wgs_nabidky MODIFY COLUMN stav
                ENUM('nova','odeslana','potvrzena','zamitnuta','expirovana','zrusena')
                DEFAULT 'nova'
                COMMENT 'Stav cenové nabídky'
            ");
            $zpravy[] = "ENUM stav rozšířen o hodnotu 'zamitnuta'";
            $provedeno++;
        }

        // 3. Přidat index na reklamace_id pokud byl přidán
        if (in_array('reklamace_id', $chybejiciSloupce)) {
            try {
                $pdo->exec("ALTER TABLE wgs_nabidky ADD INDEX idx_reklamace_id (reklamace_id)");
                $zpravy[] = "Přidán index idx_reklamace_id";
            } catch (PDOException $e) {
                // Index možná už existuje
            }
        }

        $pdo->commit();

        // Znovu načíst stav po migraci
        $chybejiciSloupce = [];
        foreach (array_keys($pozadovaneSloupce) as $sloupec) {
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE '{$sloupec}'");
            if (!$stmt->fetch()) {
                $chybejiciSloupce[] = $sloupec;
            }
        }
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'stav'");
        $stavSloupec = $stmt->fetch(PDO::FETCH_ASSOC);
        $enumObsahujeZamitnuta = $stavSloupec && strpos($stavSloupec['Type'], 'zamitnuta') !== false;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $chyby[] = 'Chyba při migraci: ' . $e->getMessage();
    }
}

// ============================================
// HTML VÝSTUP
// ============================================

$vse_ok = $tabulkaExistuje && empty($chybejiciSloupce) && $enumObsahujeZamitnuta;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Migrace: CN Zamítnuta</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px; margin: 40px auto; padding: 20px;
            background: #f5f5f5; color: #222;
        }
        .container {
            background: #fff; padding: 32px; border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }
        h1 { margin: 0 0 6px 0; font-size: 22px; color: #111; }
        h2 { margin: 24px 0 12px 0; font-size: 17px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        .popis { font-size: 14px; color: #666; margin: 0 0 24px 0; }

        .zprava { padding: 11px 16px; border-radius: 5px; margin: 8px 0; font-size: 14px; }
        .zprava.ok        { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .zprava.chyba     { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .zprava.info      { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .zprava.varovani  { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }

        table.db-info { width: 100%; border-collapse: collapse; font-size: 13px; margin: 12px 0; }
        table.db-info th, table.db-info td { padding: 9px 12px; border: 1px solid #ddd; text-align: left; }
        table.db-info th { background: #f5f5f5; font-weight: 600; }
        table.db-info td.ok   { color: #155724; }
        table.db-info td.nok  { color: #721c24; font-weight: 600; }

        .btn {
            display: inline-block; padding: 11px 24px;
            background: #333; color: #fff; text-decoration: none;
            border-radius: 5px; margin: 8px 8px 8px 0; font-size: 14px;
            font-weight: 600; border: none; cursor: pointer;
        }
        .btn:hover { background: #111; }
        .btn.nebezpecne { background: #dc3545; }
        .btn.nebezpecne:hover { background: #b02a37; }
        .btn.ok-btn { background: #28a745; }
        .btn.ok-btn:hover { background: #1e7e34; }

        .email-nahled-wrapper {
            border: 2px solid #ddd; border-radius: 8px; overflow: hidden; margin-top: 16px;
        }
        .email-nahled-header {
            background: #f5f5f5; padding: 10px 16px; font-size: 13px; color: #555;
            border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;
        }
        iframe.email-preview {
            width: 100%; height: 780px; border: none; display: block;
        }

        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
        .sekce { margin-bottom: 28px; }
    </style>
</head>
<body>
<div class="container">

    <h1>Migrace: CN Zamítnuta</h1>
    <p class="popis">Přidání stavu "zamítnuta" do systému cenových nabídek. Automatický email 7 dní před expirací + ruční zamítnutí adminem.</p>

    <!-- ZPRÁVY PO MIGRACI -->
    <?php if (!empty($zpravy)): ?>
        <div class="sekce">
            <?php foreach ($zpravy as $z): ?>
                <div class="zprava ok"><?= htmlspecialchars($z) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($chyby)): ?>
        <div class="sekce">
            <?php foreach ($chyby as $c): ?>
                <div class="zprava chyba"><?= htmlspecialchars($c) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- STAV DATABÁZE -->
    <div class="sekce">
        <h2>Stav databáze</h2>

        <?php if (!$tabulkaExistuje): ?>
            <div class="zprava chyba">Tabulka <code>wgs_nabidky</code> neexistuje. Nejdřív spusťte cenova-nabidka.php.</div>
        <?php else: ?>

            <table class="db-info">
                <tr>
                    <th>Položka</th>
                    <th>Stav</th>
                    <th>Popis</th>
                </tr>
                <tr>
                    <td>Tabulka <code>wgs_nabidky</code></td>
                    <td class="ok">Existuje</td>
                    <td>OK</td>
                </tr>
                <tr>
                    <td>ENUM stav – hodnota <code>zamitnuta</code></td>
                    <td class="<?= $enumObsahujeZamitnuta ? 'ok' : 'nok' ?>">
                        <?= $enumObsahujeZamitnuta ? 'Přidána' : 'Chybí' ?>
                    </td>
                    <td><?= $enumObsahujeZamitnuta ? 'OK' : 'Bude přidána migrací' ?></td>
                </tr>
                <?php foreach ($pozadovaneSloupce as $sloupec => $def): ?>
                    <?php $chybi = in_array($sloupec, $chybejiciSloupce); ?>
                    <tr>
                        <td>Sloupec <code><?= htmlspecialchars($sloupec) ?></code></td>
                        <td class="<?= $chybi ? 'nok' : 'ok' ?>">
                            <?= $chybi ? 'Chybí' : 'Existuje' ?>
                        </td>
                        <td><?= $chybi ? 'Bude přidán migrací' : 'OK' ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

        <?php endif; ?>
    </div>

    <!-- CO BUDE PROVEDENO -->
    <?php if (!$vse_ok): ?>
        <div class="sekce">
            <h2>Co bude provedeno migrací</h2>
            <div class="zprava info">
                <?php if (!$enumObsahujeZamitnuta): ?>
                    <strong>ALTER TABLE wgs_nabidky MODIFY COLUMN stav</strong>
                    ENUM('nova','odeslana','potvrzena','<strong>zamitnuta</strong>','expirovana','zrusena')<br>
                <?php endif; ?>
                <?php foreach ($chybejiciSloupce as $s): ?>
                    <strong>ALTER TABLE wgs_nabidky ADD COLUMN <?= htmlspecialchars($s) ?></strong>
                    <?= htmlspecialchars($pozadovaneSloupce[$s]) ?><br>
                <?php endforeach; ?>
            </div>

            <a href="?execute=1" class="btn nebezpecne">Spustit migraci</a>
        </div>
    <?php else: ?>
        <div class="zprava ok">Databáze je aktuální. Migrace není potřeba.</div>
    <?php endif; ?>

    <!-- NÁHLED EMAILU -->
    <div class="sekce">
        <h2>Náhled emailu — 7 dní před expirací</h2>
        <p style="font-size: 14px; color: #555; margin: 0 0 12px 0;">
            Tento email se odešle automaticky zákazníkům, kteří neodpověděli na cenovou nabídku 7 dní před jejím vypršením.
            Obsahuje tlačítko pro schválení i odmítnutí nabídky.
        </p>

        <div class="email-nahled-wrapper">
            <div class="email-nahled-header">
                <span>Náhled emailu (vzorová data)</span>
                <a href="?nahled=email" target="_blank" class="btn" style="padding: 6px 14px; font-size: 12px; margin: 0;">Otevřít celý email</a>
            </div>
            <iframe class="email-preview" src="?nahled=email" title="Náhled emailu"></iframe>
        </div>
    </div>

    <!-- PLÁN IMPLEMENTACE -->
    <div class="sekce">
        <h2>Plán implementace (co ještě bude nasazeno)</h2>
        <table class="db-info">
            <tr><th>Část</th><th>Popis</th></tr>
            <tr><td>nabidka_api.php</td><td>Akce <code>zamitnut</code> – zákazník odmítne přes odkaz v emailu</td></tr>
            <tr><td>nabidka_api.php</td><td>Funkce <code>vygenerujEmailPripominka7dni()</code> + email zákazníkovi po auto-expiraci</td></tr>
            <tr><td>potvrzeni-nabidky.php</td><td>Zpracování parametru <code>?akce=zamitnut</code> + potvrzovací stránka</td></tr>
            <tr><td>scripts/nabidky_cron.php</td><td>Cron: odesílání 7-denních připomínek + auto-expiraci po 30 dnech</td></tr>
            <tr><td>ultra_master_cron.php</td><td>Zaregistrovat nový cron job</td></tr>
            <tr><td>seznam.js</td><td>Nový stav <code>cn-zamitnuta</code> v kartách/řádcích + filtr</td></tr>
            <tr><td>seznam.css / seznam.min.css</td><td>Červené styly pro zamítnuté nabídky</td></tr>
            <tr><td>admin panel (detail)</td><td>Admin může ručně nastavit stav <code>zamitnuta</code></td></tr>
        </table>
    </div>

</div>
</body>
</html>
