<?php
/**
 * Email šablony pro nabídky - WGS Service
 *
 * Obsahuje HTML generátory emailů pro:
 * - Odeslání nabídky zákazníkovi
 * - Potvrzení nabídky zákazníkovi
 * - Potvrzení zálohy
 * - Poděkování za úhradu
 * - 7denní připomínka
 * - Automatická expirace
 */

if (!defined('BASE_PATH')) {
    die('Přímý přístup zakázán.');
}

/**
 * Vygeneruje HTML email s nabídkou - profesionální design
 */
function vygenerujEmailNabidky($nabidka) {
    $polozky = json_decode($nabidka['polozky_json'], true);
    $baseUrl = 'https://www.wgs-service.cz';
    $potvrzeniUrl = $baseUrl . '/potvrzeni-nabidky.php?token=' . urlencode($nabidka['token']);

    // Zjistit zda nabídka obsahuje náhradní díly
    $obsahujeDily = false;
    if (is_array($polozky)) {
        foreach ($polozky as $p) {
            $nazev = $p['nazev'] ?? '';
            // Detekce náhradních dílů - prefix "Náhradní díl:" nebo skupina "dily"
            if (stripos($nazev, 'Náhradní díl') !== false || ($p['skupina'] ?? '') === 'dily') {
                $obsahujeDily = true;
                break;
            }
        }
    }

    // Poznámka o zálohové faktuře - zobrazí se pouze pokud jsou náhradní díly
    $poznamkaOZaloze = '';
    if ($obsahujeDily) {
        $poznamkaOZaloze = "<p style='margin: 12px 0 0 0; font-size: 12px; color: #d97706; line-height: 1.6;'>
            <strong>Záloha na náhradní díly:</strong> Po odsouhlasení této nabídky Vám zašleme zálohovou fakturu na náhradní díly.
            Po přijetí zálohy objednáme díly u výrobce.
        </p>";
    }

    // Sestavení tabulky položek
    $polozkyHtml = '';
    foreach ($polozky as $p) {
        $nazev = htmlspecialchars($p['nazev'] ?? '');
        $pocet = intval($p['pocet'] ?? 1);
        $cenaJednotka = floatval($p['cena'] ?? 0);
        $cenaCelkem = $cenaJednotka * $pocet;

        $cenaFormatovana = number_format($cenaJednotka, 2, ',', ' ');
        $celkemFormatovane = number_format($cenaCelkem, 2, ',', ' ');

        $polozkyHtml .= "
        <tr>
            <td style='padding: 14px 16px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #333;'>{$nazev}</td>
            <td style='padding: 14px 16px; border-bottom: 1px solid #e5e5e5; text-align: center; font-size: 14px; color: #666;'>{$pocet}</td>
            <td style='padding: 14px 16px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; color: #666;'>{$cenaFormatovana} {$nabidka['mena']}</td>
            <td style='padding: 14px 16px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; font-weight: 600; color: #333;'>{$celkemFormatovane} {$nabidka['mena']}</td>
        </tr>";
    }

    $celkovaCena = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');
    $datumVytvoreni = date('d.m.Y', strtotime($nabidka['vytvoreno_at'] ?? 'now'));
    $platnostDo = date('d.m.Y', strtotime($nabidka['platnost_do']));
    // Použít číslo nabídky nebo fallback na padované ID
    $nabidkaCislo = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));

    // Průvodní text emailu - použít uložený text nebo výchozí
    $uvodniTextRaw = !empty($nabidka['uvodni_text'])
        ? $nabidka['uvodni_text']
        : "Vážený zákazníku,\n\nna základě Vaší poptávky Vám zasíláme cenovou nabídku za servisní práce na Vašem nábytku značky Natuzzi.\n\nProsíme, potvrďte nabídku kliknutím na tlačítko níže. Po potvrzení Vás budeme kontaktovat ohledně dalšího postupu. V případě dotazů jsme Vám plně k dispozici.\n\nS pozdravem,\ntým White Glove Service";
    $uvodniTextHtml = nl2br(htmlspecialchars($uvodniTextRaw, ENT_QUOTES, 'UTF-8'));

    // Adresa zákazníka (pokud existuje)
    $adresaHtml = '';
    if (!empty($nabidka['zakaznik_adresa'])) {
        $adresaHtml = "<p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>" . nl2br(htmlspecialchars($nabidka['zakaznik_adresa'])) . "</p>";
    }

    // Telefon zákazníka
    $telefonHtml = '';
    if (!empty($nabidka['zakaznik_telefon'])) {
        $telefonHtml = "<p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>Tel: {$nabidka['zakaznik_telefon']}</p>";
    }

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Cenová nabídka č. {$nabidkaCislo} - White Glove Service</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>

    <!-- Hlavní kontejner -->
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

                    <!-- HLAVNÍ OBSAH -->
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>

                            <!-- Nadpis nabídky -->
                            <div style='background: #f8f9fa; padding: 25px 40px; border-bottom: 1px solid #e5e5e5;'>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%'>
                                    <tr>
                                        <td>
                                            <h2 style='margin: 0; font-size: 20px; font-weight: 600; color: #333;'>Cenová nabídka</h2>
                                            <p style='margin: 5px 0 0 0; font-size: 13px; color: #888;'>č. {$nabidkaCislo}</p>
                                        </td>
                                        <td style='text-align: right;'>
                                            <p style='margin: 0; font-size: 13px; color: #666;'>Datum: <strong>{$datumVytvoreni}</strong></p>
                                            <p style='margin: 5px 0 0 0; font-size: 13px; color: #666;'>Platnost: <strong style='color: #d97706;'>{$platnostDo}</strong></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Průvodní dopis -->
                            <div style='padding: 30px 40px 20px 40px;'>
                                <p style='margin: 0; font-size: 14px; color: #333; line-height: 1.8; white-space: pre-line;'>{$uvodniTextHtml}</p>
                            </div>

                            <!-- Údaje zákazníka -->
                            <div style='padding: 0 40px 25px 40px;'>
                                <div style='background: #f8f9fa; border-radius: 8px; padding: 18px 20px;'>
                                    <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;'>Zákazník</p>
                                    <p style='margin: 8px 0 0 0; font-size: 15px; font-weight: 600; color: #333;'>{$nabidka['zakaznik_jmeno']}</p>
                                    <p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>{$nabidka['zakaznik_email']}</p>
                                    {$telefonHtml}
                                    {$adresaHtml}
                                </div>
                            </div>

                            <!-- Tabulka položek -->
                            <div style='padding: 0 40px 30px 40px;'>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;'>
                                    <thead>
                                        <tr style='background: #f8f9fa;'>
                                            <th style='padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Služba</th>
                                            <th style='padding: 14px 16px; text-align: center; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Počet</th>
                                            <th style='padding: 14px 16px; text-align: right; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Cena/ks</th>
                                            <th style='padding: 14px 16px; text-align: right; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Celkem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$polozkyHtml}
                                    </tbody>
                                    <tfoot>
                                        <tr style='background: #1a1a1a;'>
                                            <td colspan='3' style='padding: 18px 16px; text-align: right; font-size: 14px; font-weight: 600; color: #fff;'>Celková cena (bez DPH):</td>
                                            <td style='padding: 18px 16px; text-align: right; font-size: 20px; font-weight: 700; color: #39ff14;'>{$celkovaCena} {$nabidka['mena']}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Upozornění platnost -->
                            <div style='padding: 0 40px 20px 40px;'>
                                <div style='background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px 20px;'>
                                    <p style='margin: 0; font-size: 14px; color: #92400e;'>
                                        <strong>Platnost nabídky:</strong> Tato nabídka je platná do <strong>{$platnostDo}</strong>.
                                        Po tomto datu bude automaticky zrušena.
                                    </p>
                                </div>
                            </div>

                            <!-- Informace o měně -->
                            <div style='padding: 0 40px 30px 40px;'>
                                <div style='background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 14px 20px;'>
                                    <p style='margin: 0; font-size: 13px; color: #666;'>
                                        <strong>Informace o měně:</strong> Ceny jsou uvedeny v EUR. Při platbě v CZK bude částka přepočtena
                                        dle aktuálního kurzu ČNB platného v den vystavení faktury.
                                    </p>
                                </div>
                            </div>

                            <!-- CTA Tlačítko -->
                            <div style='padding: 0 40px 35px 40px; text-align: center;'>
                                <p style='margin: 0 0 20px 0; font-size: 14px; color: #555;'>
                                    Pokud s nabídkou souhlasíte, potvrďte ji kliknutím na tlačítko níže:
                                </p>
                                <a href='{$potvrzeniUrl}' style='display: inline-block; background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: #ffffff; padding: 18px 50px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);'>
                                    Potvrdit nabídku
                                </a>
                            </div>

                            <!-- Právní upozornění -->
                            <div style='padding: 25px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #666; line-height: 1.6;'>
                                    <strong>Důležité upozornění:</strong> Kliknutím na tlačítko \"Potvrdit nabídku\" potvrzujete, že souhlasíte s touto cenovou nabídkou
                                    a uzavíráte tím závaznou smlouvu o dílo dle § 2586 občanského zákoníku s White Glove Service, s.r.o.
                                    Podrobnosti naleznete v našich <a href='{$baseUrl}/podminky.php' style='color: #333;'>obchodních podmínkách</a>.
                                </p>
                                {$poznamkaOZaloze}
                                <p style='margin: 12px 0 0 0; font-size: 12px; color: #888;'>
                                    Ceny jsou uvedeny bez DPH. Při platbě v CZK bude částka přepočtena dle kurzu ČNB v den vystavení faktury.
                                    Doba dodání originálních dílů z továrny Natuzzi je 4–8 týdnů.
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

/**
 * Vygeneruje HTML email s potvrzením objednávky pro zákazníka
 */
function vygenerujEmailPotvrzeniZakaznik($nabidka) {
    $polozky = json_decode($nabidka['polozky_json'], true);
    $baseUrl = 'https://www.wgs-service.cz';
    $potvrzeniUrl = $baseUrl . '/potvrzeni-nabidky.php?token=' . urlencode($nabidka['token']);

    // Sestavení tabulky položek
    $polozkyHtml = '';
    foreach ($polozky as $p) {
        $nazev = htmlspecialchars($p['nazev'] ?? '');
        $pocet = intval($p['pocet'] ?? 1);
        $cenaJednotka = floatval($p['cena'] ?? 0);
        $cenaCelkem = $cenaJednotka * $pocet;

        $cenaFormatovana = number_format($cenaJednotka, 2, ',', ' ');
        $celkemFormatovane = number_format($cenaCelkem, 2, ',', ' ');

        $polozkyHtml .= "
        <tr>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #333;'>{$nazev}</td>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; text-align: center; font-size: 14px; color: #666;'>{$pocet}</td>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; font-weight: 600; color: #333;'>{$celkemFormatovane} {$nabidka['mena']}</td>
        </tr>";
    }

    $celkovaCena = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');
    // Použít číslo nabídky nebo fallback na padované ID
    $nabidkaCislo = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));

    // Datum a čas potvrzení
    $potvrzenoAt = isset($nabidka['potvrzeno_at']) ? date('d.m.Y H:i:s', strtotime($nabidka['potvrzeno_at'])) : date('d.m.Y H:i:s');
    $potvrzenoIp = $nabidka['potvrzeno_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'N/A';

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Potvrzení objednávky č. {$nabidkaCislo}</title>
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
                            <p style='margin: 8px 0 0 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px;'>Potvrzení objednávky</p>
                        </td>
                    </tr>

                    <!-- HLAVNÍ OBSAH -->
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>

                            <!-- Úspěšné potvrzení -->
                            <div style='background: #d4edda; padding: 25px 40px; border-bottom: 1px solid #c3e6cb;'>
                                <h2 style='margin: 0; font-size: 20px; font-weight: 600; color: #155724;'>Vaše objednávka byla úspěšně potvrzena</h2>
                                <p style='margin: 8px 0 0 0; font-size: 14px; color: #155724;'>Děkujeme za Vaši důvěru. Níže naleznete shrnutí Vaší objednávky.</p>
                            </div>

                            <!-- Číslo objednávky -->
                            <div style='padding: 25px 40px; border-bottom: 1px solid #e5e5e5;'>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%'>
                                    <tr>
                                        <td>
                                            <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Číslo objednávky</p>
                                            <p style='margin: 5px 0 0 0; font-size: 24px; font-weight: 700; color: #333;'>{$nabidkaCislo}</p>
                                        </td>
                                        <td style='text-align: right;'>
                                            <p style='margin: 0; font-size: 12px; color: #888;'>Potvrzeno:</p>
                                            <p style='margin: 5px 0 0 0; font-size: 14px; color: #333;'>{$potvrzenoAt}</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Údaje zákazníka -->
                            <div style='padding: 25px 40px; border-bottom: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Zákazník</p>
                                <p style='margin: 8px 0 0 0; font-size: 15px; font-weight: 600; color: #333;'>{$nabidka['zakaznik_jmeno']}</p>
                                <p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>{$nabidka['zakaznik_email']}</p>
                            </div>

                            <!-- Tabulka položek -->
                            <div style='padding: 25px 40px;'>
                                <p style='margin: 0 0 15px 0; font-size: 12px; color: #888; text-transform: uppercase;'>Objednané služby</p>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;'>
                                    <thead>
                                        <tr style='background: #f8f9fa;'>
                                            <th style='padding: 12px 14px; text-align: left; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; border-bottom: 2px solid #e5e5e5;'>Služba</th>
                                            <th style='padding: 12px 14px; text-align: center; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; border-bottom: 2px solid #e5e5e5;'>Ks</th>
                                            <th style='padding: 12px 14px; text-align: right; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; border-bottom: 2px solid #e5e5e5;'>Cena</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$polozkyHtml}
                                    </tbody>
                                    <tfoot>
                                        <tr style='background: #1a1a1a;'>
                                            <td colspan='2' style='padding: 15px 14px; text-align: right; font-size: 14px; font-weight: 600; color: #fff;'>Celková cena (bez DPH):</td>
                                            <td style='padding: 15px 14px; text-align: right; font-size: 18px; font-weight: 700; color: #39ff14;'>{$celkovaCena} {$nabidka['mena']}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Technické údaje potvrzení -->
                            <div style='padding: 20px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #666; line-height: 1.5;'>
                                    Toto elektronické potvrzení bylo zaznamenáno dne <strong>{$potvrzenoAt}</strong>
                                    a má právní platnost dle § 2586 občanského zákoníku (smlouva o dílo).
                                </p>
                            </div>

                            <!-- Tlačítko pro stažení PDF -->
                            <div style='padding: 25px 40px; text-align: center;'>
                                <p style='margin: 0 0 15px 0; font-size: 14px; color: #555;'>
                                    PDF potvrzení si můžete stáhnout na stránce objednávky:
                                </p>
                                <a href='{$potvrzeniUrl}' style='display: inline-block; background: #333; color: #fff; padding: 14px 35px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;'>
                                    Zobrazit objednávku a stáhnout PDF
                                </a>
                            </div>

                            <!-- Právní upozornění -->
                            <div style='padding: 20px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #666; line-height: 1.6;'>
                                    Tímto potvrzením jste uzavřeli závaznou smlouvu o dílo dle § 2586 občanského zákoníku
                                    s White Glove Service, s.r.o. Ceny jsou uvedeny bez DPH.
                                    Při platbě v CZK bude částka přepočtena dle kurzu ČNB v den vystavení faktury.
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

/**
 * Vygeneruje HTML email s potvrzením o přijaté záloze
 */
function vygenerujEmailPotvrzeniZalohy($nabidka) {
    $baseUrl = 'https://www.wgs-service.cz';
    $potvrzeniUrl = $baseUrl . '/potvrzeni-nabidky.php?token=' . urlencode($nabidka['token']);

    $celkovaCena = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');
    $nabidkaCislo = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));
    $datumZalohy = isset($nabidka['zf_uhrazena_at']) ? date('d.m.Y', strtotime($nabidka['zf_uhrazena_at'])) : date('d.m.Y');

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Potvrzení o přijaté záloze - {$nabidkaCislo}</title>
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
                            <p style='margin: 8px 0 0 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px;'>Potvrzení o přijaté záloze</p>
                        </td>
                    </tr>

                    <!-- HLAVNÍ OBSAH -->
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>

                            <!-- Potvrzení přijetí zálohy -->
                            <div style='background: #d4edda; padding: 25px 40px; border-bottom: 1px solid #c3e6cb;'>
                                <h2 style='margin: 0; font-size: 20px; font-weight: 600; color: #155724;'>Záloha byla úspěšně přijata</h2>
                                <p style='margin: 8px 0 0 0; font-size: 14px; color: #155724;'>Děkujeme za uhrazení zálohové faktury k objednávce č. {$nabidkaCislo}.</p>
                            </div>

                            <!-- Oslovení -->
                            <div style='padding: 30px 40px 20px 40px;'>
                                <p style='margin: 0; font-size: 15px; color: #333;'>Vážený/á <strong>{$nabidka['zakaznik_jmeno']}</strong>,</p>
                                <p style='margin: 15px 0 0 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    potvrzujeme přijetí Vaší zálohové platby dne <strong>{$datumZalohy}</strong>.
                                </p>
                            </div>

                            <!-- Číslo objednávky -->
                            <div style='padding: 0 40px 25px 40px;'>
                                <div style='background: #f8f9fa; border-radius: 8px; padding: 20px;'>
                                    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%'>
                                        <tr>
                                            <td>
                                                <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Číslo objednávky</p>
                                                <p style='margin: 5px 0 0 0; font-size: 22px; font-weight: 700; color: #333;'>{$nabidkaCislo}</p>
                                            </td>
                                            <td style='text-align: right;'>
                                                <p style='margin: 0; font-size: 12px; color: #888;'>Celková hodnota zakázky</p>
                                                <p style='margin: 5px 0 0 0; font-size: 18px; font-weight: 600; color: #333;'>{$celkovaCena} {$nabidka['mena']}</p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Informace o měně -->
                            <div style='padding: 0 40px 20px 40px;'>
                                <div style='background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 14px 20px;'>
                                    <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.5;'>
                                        <strong>Informace o měně:</strong> Ceny jsou uvedeny v EUR. Při platbě v CZK bude částka přepočtena
                                        dle aktuálního kurzu ČNB platného v den vystavení faktury.
                                    </p>
                                </div>
                            </div>

                            <!-- Co bude následovat -->
                            <div style='padding: 0 40px 30px 40px;'>
                                <h3 style='margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: #333;'>Co bude následovat?</h3>
                                <div style='background: #fff8e1; border: 1px solid #ffc107; border-radius: 8px; padding: 18px 20px;'>
                                    <ol style='margin: 0; padding: 0 0 0 20px; font-size: 14px; color: #555; line-height: 1.8;'>
                                        <li>Objednáme náhradní díly přímo u výrobce Natuzzi</li>
                                        <li>Doba dodání dílů je obvykle 4–8 týdnů</li>
                                        <li>Po přijetí dílů Vás budeme kontaktovat k domluvě termínu servisu</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Tlačítko -->
                            <div style='padding: 0 40px 35px 40px; text-align: center;'>
                                <a href='{$potvrzeniUrl}' style='display: inline-block; background: #333; color: #fff; padding: 14px 35px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;'>
                                    Zobrazit detail objednávky
                                </a>
                            </div>

                            <!-- Kontakt -->
                            <div style='padding: 20px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.6;'>
                                    V případě dotazů nás neváhejte kontaktovat na emailu
                                    <a href='mailto:reklamace@wgs-service.cz' style='color: #333;'>reklamace@wgs-service.cz</a>
                                    nebo na telefonu <a href='tel:+420725965826' style='color: #333;'>+420 725 965 826</a>.
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

/**
 * Vygeneruje HTML email s poděkováním za úhradu finální faktury
 */
function vygenerujEmailPodekovaniZaUhradu($nabidka) {
    $baseUrl = 'https://www.wgs-service.cz';

    $celkovaCena = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');
    $nabidkaCislo = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));
    $datumUhrady = isset($nabidka['fa_uhrazena_at']) ? date('d.m.Y', strtotime($nabidka['fa_uhrazena_at'])) : date('d.m.Y');

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Děkujeme za úhradu - {$nabidkaCislo}</title>
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
                            <p style='margin: 8px 0 0 0; font-size: 12px; color: #39ff14; text-transform: uppercase; letter-spacing: 1px;'>Děkujeme za Vaši důvěru</p>
                        </td>
                    </tr>

                    <!-- HLAVNÍ OBSAH -->
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>

                            <!-- Poděkování -->
                            <div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 30px 40px; text-align: center;'>
                                <h2 style='margin: 0; font-size: 24px; font-weight: 600; color: #155724;'>Zakázka úspěšně dokončena</h2>
                                <p style='margin: 10px 0 0 0; font-size: 15px; color: #155724;'>Děkujeme za úhradu a za využití našich služeb!</p>
                            </div>

                            <!-- Oslovení -->
                            <div style='padding: 35px 40px 20px 40px;'>
                                <p style='margin: 0; font-size: 16px; color: #333;'>Vážený/á <strong>{$nabidka['zakaznik_jmeno']}</strong>,</p>
                                <p style='margin: 20px 0 0 0; font-size: 15px; color: #555; line-height: 1.7;'>
                                    rádi bychom Vám touto cestou poděkovali za úhradu faktury k zakázce č. <strong>{$nabidkaCislo}</strong>
                                    ze dne <strong>{$datumUhrady}</strong>.
                                </p>
                                <p style='margin: 15px 0 0 0; font-size: 15px; color: #555; line-height: 1.7;'>
                                    Velice si vážíme Vaší důvěry v naše služby a těšíme se na případnou další spolupráci.
                                </p>
                            </div>

                            <!-- Shrnutí zakázky -->
                            <div style='padding: 0 40px 30px 40px;'>
                                <div style='background: #f8f9fa; border-radius: 8px; padding: 20px; border: 1px solid #e5e5e5;'>
                                    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%'>
                                        <tr>
                                            <td>
                                                <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Číslo zakázky</p>
                                                <p style='margin: 5px 0 0 0; font-size: 18px; font-weight: 700; color: #333;'>{$nabidkaCislo}</p>
                                            </td>
                                            <td style='text-align: right;'>
                                                <p style='margin: 0; font-size: 12px; color: #888;'>Uhrazená částka</p>
                                                <p style='margin: 5px 0 0 0; font-size: 18px; font-weight: 700; color: #28a745;'>{$celkovaCena} {$nabidka['mena']}</p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Informace o měně -->
                            <div style='padding: 0 40px 20px 40px;'>
                                <div style='background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 14px 20px;'>
                                    <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.5;'>
                                        <strong>Informace o měně:</strong> Ceny jsou uvedeny v EUR. Při platbě v CZK byla částka přepočtena
                                        dle aktuálního kurzu ČNB platného v den vystavení faktury.
                                    </p>
                                </div>
                            </div>

                            <!-- Doporučení -->
                            <div style='padding: 0 40px 30px 40px;'>
                                <div style='background: #e8f4f8; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px;'>
                                    <h3 style='margin: 0 0 12px 0; font-size: 15px; font-weight: 600; color: #0c5460;'>Péče o Váš nábytek Natuzzi</h3>
                                    <p style='margin: 0; font-size: 14px; color: #0c5460; line-height: 1.6;'>
                                        Pokud budete mít v budoucnu jakékoliv dotazy ohledně údržby Vašeho nábytku
                                        nebo budete potřebovat další servis, neváhejte nás kontaktovat.
                                        Jsme tu pro Vás.
                                    </p>
                                </div>
                            </div>

                            <!-- Zpětná vazba -->
                            <div style='padding: 0 40px 35px 40px; text-align: center;'>
                                <p style='margin: 0 0 15px 0; font-size: 14px; color: #666;'>
                                    Byli jste spokojeni s našimi službami? Budeme rádi za Vaši zpětnou vazbu!
                                </p>
                                <a href='mailto:reklamace@wgs-service.cz?subject=Zpětná vazba k zakázce {$nabidkaCislo}' style='display: inline-block; background: #333; color: #fff; padding: 14px 35px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;'>
                                    Napsat zpětnou vazbu
                                </a>
                            </div>

                            <!-- Kontakt -->
                            <div style='padding: 25px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.6; text-align: center;'>
                                    <strong>White Glove Service</strong> - profesionální servis prémiového nábytku<br>
                                    <a href='mailto:reklamace@wgs-service.cz' style='color: #333;'>reklamace@wgs-service.cz</a> |
                                    <a href='tel:+420725965826' style='color: #333;'>+420 725 965 826</a>
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
                            <p style='margin: 15px 0 0 0; font-size: 11px; color: #444;'>
                                Děkujeme, že jste si vybrali White Glove Service
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

/**
 * Vygeneruje HTML email - připomínka 7 dní před expirací nabídky
 */
function vygenerujEmailPripominka7dni($nabidka) {
    $polozky = json_decode($nabidka['polozky_json'], true);
    $baseUrl = 'https://www.wgs-service.cz';
    $potvrzeniUrl = $baseUrl . '/potvrzeni-nabidky.php?token=' . urlencode($nabidka['token']);
    $zamitnuriUrl = $baseUrl . '/potvrzeni-nabidky.php?token=' . urlencode($nabidka['token']) . '&akce=zamitnut';
    $nabidkaCislo = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));
    $platnostDo   = date('d.m.Y', strtotime($nabidka['platnost_do']));
    $celkovaCena  = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');

    $polozkyHtml = '';
    foreach ((array)$polozky as $p) {
        $nazev   = htmlspecialchars($p['nazev'] ?? '');
        $pocet   = intval($p['pocet'] ?? 1);
        $cenaKs  = floatval($p['cena'] ?? 0);
        $cenaCelk = number_format($cenaKs * $pocet, 2, ',', ' ');
        $polozkyHtml .= "<tr>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #333;'>{$nazev}</td>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; text-align: center; font-size: 14px; color: #666;'>{$pocet}</td>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; font-weight: 600; color: #333;'>{$cenaCelk} {$nabidka['mena']}</td>
        </tr>";
    }

    return "<!DOCTYPE html>
<html lang='cs'>
<head><meta charset='UTF-8'><title>Připomínka nabídky č. {$nabidkaCislo}</title></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;'>
<table cellspacing='0' cellpadding='0' border='0' width='100%' style='background:#f4f4f4;'>
<tr><td style='padding:30px 20px;'>
<table cellspacing='0' cellpadding='0' border='0' width='600' style='margin:0 auto;max-width:600px;'>
<tr><td style='background:linear-gradient(135deg,#1a1a1a 0%,#2d2d2d 100%);padding:35px 40px;text-align:center;border-radius:12px 12px 0 0;'>
    <h1 style='margin:0;font-size:28px;font-weight:700;color:#fff;letter-spacing:2px;'>WHITE GLOVE SERVICE</h1>
    <p style='margin:8px 0 0 0;font-size:12px;color:#888;text-transform:uppercase;'>Připomínka cenové nabídky</p>
</td></tr>
<tr><td style='background:#fff;'>
    <div style='background:#fff3cd;padding:25px 40px;border-bottom:1px solid #ffc107;'>
        <h2 style='margin:0;font-size:18px;color:#856404;'>Vaše nabídka vyprší za 7 dní</h2>
        <p style='margin:8px 0 0 0;font-size:14px;color:#856404;line-height:1.6;'>
            Platnost cenové nabídky č. <strong>{$nabidkaCislo}</strong> končí dne <strong>{$platnostDo}</strong>.
        </p>
    </div>
    <div style='padding:30px 40px 20px 40px;'>
        <p style='margin:0;font-size:15px;color:#333;'>Vážený/á <strong>{$nabidka['zakaznik_jmeno']}</strong>,</p>
        <p style='margin:15px 0 0 0;font-size:14px;color:#555;line-height:1.6;'>
            připomínáme Vám, že Vám byla zaslána cenová nabídka, která bude brzy expirovat.
            Prosíme, dejte nám vědět, zda máte o naše služby zájem.
        </p>
    </div>
    <div style='padding:0 40px 25px 40px;'>
        <table cellspacing='0' cellpadding='0' border='0' width='100%' style='border:1px solid #e5e5e5;border-radius:8px;'>
            <thead><tr style='background:#f8f9fa;'>
                <th style='padding:12px 14px;text-align:left;font-size:12px;color:#666;text-transform:uppercase;border-bottom:2px solid #e5e5e5;'>Služba</th>
                <th style='padding:12px 14px;text-align:center;font-size:12px;color:#666;text-transform:uppercase;border-bottom:2px solid #e5e5e5;'>Ks</th>
                <th style='padding:12px 14px;text-align:right;font-size:12px;color:#666;text-transform:uppercase;border-bottom:2px solid #e5e5e5;'>Cena</th>
            </tr></thead>
            <tbody>{$polozkyHtml}</tbody>
            <tfoot><tr style='background:#1a1a1a;'>
                <td colspan='2' style='padding:14px;text-align:right;font-size:13px;font-weight:600;color:#fff;'>Celková cena (bez DPH):</td>
                <td style='padding:14px;text-align:right;font-size:18px;font-weight:700;color:#39ff14;'>{$celkovaCena} {$nabidka['mena']}</td>
            </tr></tfoot>
        </table>
    </div>
    <div style='padding:10px 40px 35px 40px;text-align:center;'>
        <p style='margin:0 0 20px 0;font-size:14px;color:#555;'>Vyberte prosím jednu z možností:</p>
        <table cellspacing='0' cellpadding='0' border='0' style='margin:0 auto;'>
        <tr>
            <td style='padding-right:15px;'>
                <a href='{$potvrzeniUrl}' style='display:inline-block;background:linear-gradient(135deg,#28a745 0%,#218838 100%);color:#fff;padding:16px 35px;text-decoration:none;border-radius:8px;font-weight:700;font-size:15px;'>
                    Souhlasím s nabídkou
                </a>
            </td>
            <td>
                <a href='{$zamitnuriUrl}' style='display:inline-block;background:#dc3545;color:#fff;padding:16px 35px;text-decoration:none;border-radius:8px;font-weight:700;font-size:15px;'>
                    Nemám zájem
                </a>
            </td>
        </tr>
        </table>
    </div>
    <div style='padding:0 40px 30px 40px;'>
        <p style='margin:0;font-size:13px;color:#555;line-height:1.6;'>
            Dotazy: <a href='mailto:reklamace@wgs-service.cz' style='color:#333;'>reklamace@wgs-service.cz</a>
            | <a href='tel:+420725965826' style='color:#333;'>+420 725 965 826</a>
        </p>
    </div>
</td></tr>
<tr><td style='background:#1a1a1a;padding:25px 40px;border-radius:0 0 12px 12px;text-align:center;'>
    <p style='margin:0;font-size:14px;font-weight:600;color:#fff;'>White Glove Service, s.r.o.</p>
    <p style='margin:8px 0 0 0;font-size:12px;color:#888;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
    <p style='margin:8px 0 0 0;font-size:12px;color:#888;'>
        Tel: <a href='tel:+420725965826' style='color:#888;text-decoration:none;'>+420 725 965 826</a> |
        Email: <a href='mailto:reklamace@wgs-service.cz' style='color:#888;text-decoration:none;'>reklamace@wgs-service.cz</a>
    </p>
    <p style='margin:12px 0 0 0;font-size:11px;color:#555;'>
        <a href='{$baseUrl}' style='color:#39ff14;text-decoration:none;'>www.wgs-service.cz</a>
    </p>
</td></tr>
</table>
</td></tr>
</table>
</body></html>";
}

/**
 * Vygeneruje HTML email - automatická expirace nabídky (30 dní bez reakce zákazníka)
 */
function vygenerujEmailAutoExpirace($nabidka) {
    $baseUrl      = 'https://www.wgs-service.cz';
    $nabidkaCislo = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));
    $platnostDo   = date('d.m.Y', strtotime($nabidka['platnost_do']));
    $celkovaCena  = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');

    return "<!DOCTYPE html>
<html lang='cs'>
<head><meta charset='UTF-8'><title>Nabídka č. {$nabidkaCislo} expirovala</title></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;'>
<table cellspacing='0' cellpadding='0' border='0' width='100%' style='background:#f4f4f4;'>
<tr><td style='padding:30px 20px;'>
<table cellspacing='0' cellpadding='0' border='0' width='600' style='margin:0 auto;max-width:600px;'>
<tr><td style='background:linear-gradient(135deg,#1a1a1a 0%,#2d2d2d 100%);padding:35px 40px;text-align:center;border-radius:12px 12px 0 0;'>
    <h1 style='margin:0;font-size:28px;font-weight:700;color:#fff;letter-spacing:2px;'>WHITE GLOVE SERVICE</h1>
    <p style='margin:8px 0 0 0;font-size:12px;color:#888;text-transform:uppercase;'>Informace o cenové nabídce</p>
</td></tr>
<tr><td style='background:#fff;'>
    <div style='background:#f8f9fa;padding:25px 40px;border-bottom:1px solid #dee2e6;'>
        <h2 style='margin:0;font-size:18px;font-weight:600;color:#333;'>Nabídka č. {$nabidkaCislo} expirovala</h2>
        <p style='margin:8px 0 0 0;font-size:14px;color:#666;line-height:1.6;'>
            Platnost Vaší cenové nabídky dne <strong>{$platnostDo}</strong> vypršela.
        </p>
    </div>
    <div style='padding:30px 40px;'>
        <p style='margin:0;font-size:15px;color:#333;'>Vážený/á <strong>{$nabidka['zakaznik_jmeno']}</strong>,</p>
        <p style='margin:15px 0 0 0;font-size:14px;color:#555;line-height:1.6;'>
            cenová nabídka č. <strong>{$nabidkaCislo}</strong> na celkovou částku
            <strong>{$celkovaCena} {$nabidka['mena']}</strong> bohužel expirovala bez Vaší odpovědi.
        </p>
        <p style='margin:15px 0 0 0;font-size:14px;color:#555;line-height:1.6;'>
            Pokud máte stále zájem o naše služby, rádi Vám připravíme novou nabídku.
        </p>
    </div>
    <div style='padding:0 40px 35px 40px;text-align:center;'>
        <a href='{$baseUrl}/kontakt.php' style='display:inline-block;background:#333;color:#fff;padding:16px 40px;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;'>
            Kontaktovat WGS
        </a>
    </div>
    <div style='padding:0 40px 30px 40px;border-top:1px solid #e5e5e5;'>
        <p style='margin:20px 0 0 0;font-size:13px;color:#555;line-height:1.6;'>
            Kontakt: <a href='mailto:reklamace@wgs-service.cz' style='color:#333;'>reklamace@wgs-service.cz</a> |
            <a href='tel:+420725965826' style='color:#333;'>+420 725 965 826</a>
        </p>
    </div>
</td></tr>
<tr><td style='background:#1a1a1a;padding:25px 40px;border-radius:0 0 12px 12px;text-align:center;'>
    <p style='margin:0;font-size:14px;font-weight:600;color:#fff;'>White Glove Service, s.r.o.</p>
    <p style='margin:8px 0 0 0;font-size:12px;color:#888;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
    <p style='margin:8px 0 0 0;font-size:12px;color:#888;'>
        Tel: <a href='tel:+420725965826' style='color:#888;text-decoration:none;'>+420 725 965 826</a> |
        Email: <a href='mailto:reklamace@wgs-service.cz' style='color:#888;text-decoration:none;'>reklamace@wgs-service.cz</a>
    </p>
    <p style='margin:12px 0 0 0;font-size:11px;color:#555;'>
        <a href='{$baseUrl}' style='color:#39ff14;text-decoration:none;'>www.wgs-service.cz</a>
    </p>
</td></tr>
</table>
</td></tr>
</table>
</body></html>";
}
