<?php
/**
 * Náhled emailu: Pozáruční servis Natuzzi pro existující zákazníky
 *
 * Tento email se odesílá zákazníkům, u kterých WGS již byl na servisu
 * Cíl: Informovat o dostupnosti pozáručního servisu
 */

// Bezpečnostní kontrola - pouze admin
require_once __DIR__ . '/init.php';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Přístup odepřen');
}

// Kompaktní verze emailu - menší mezery
$baseUrl = 'https://www.wgs-service.cz';

$htmlEmail = "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>White Glove Service</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>
    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f4f4f4;'>
        <tr>
            <td style='padding: 20px 15px;'>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='margin: 0 auto; max-width: 600px;'>

                    <!-- HEADER -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 20px 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                            <h1 style='margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; letter-spacing: 1.5px;'>WHITE GLOVE SERVICE</h1>
                            <p style='margin: 5px 0 0 0; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.8px;'>Premium Furniture Care</p>
                        </td>
                    </tr>

                    <!-- HLAVNÍ OBSAH -->
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>

                            <!-- Nadpis -->
                            <div style='background: #f8f9fa; padding: 15px 30px; border-bottom: 1px solid #e5e5e5;'>
                                <h2 style='margin: 0; font-size: 18px; font-weight: 600; color: #333;'>NATUZZI – Pozáruční servis</h2>
                            </div>

                            <!-- Oslovení a obsah -->
                            <div style='padding: 20px 30px 15px 30px;'>
                                <p style='margin: 0 0 18px 0; font-size: 15px; color: #333;'>Vážená paní, vážený pane,</p>

                                <p style='margin: 0 0 18px 0; font-size: 13px; color: #666; line-height: 1.6; font-style: italic;'>
                                    Dovolujeme si Vás kontaktovat, protože Váš email máme z naší předchozí spolupráce – ať už z doručení nábytku Natuzzi nebo z poskytnutého servisu.
                                </p>

                                <p style='margin: 0 0 18px 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    Rádi bychom Vás informovali, že pro společnost <strong>Natuzzi</strong> poskytujeme komplexní <strong>pozáruční servisní služby</strong> a jsme tu pro Vás i nadále.
                                </p>

                                <h3 style='margin: 15px 0 8px 0; font-size: 15px; font-weight: 600; color: #333;'>
                                    Naše služby zahrnují:
                                </h3>

                                <ul style='margin: 0 0 12px 0; padding-left: 20px; font-size: 14px; color: #555; line-height: 1.5;'>
                                    <li style='margin-bottom: 4px;'><strong>Řešení vad prosezení</strong> – obnova komfortu sedacích ploch</li>
                                    <li style='margin-bottom: 4px;'><strong>Profesionální přečalounění</strong> – včetně výběru kvalitních materiálů</li>
                                    <li style='margin-bottom: 4px;'><strong>Opravy elektrických prvků</strong> – ovládání polohování, LED osvětlení, USB nabíječky, výměna spínačů, výměna motoru apod.</li>
                                    <li style='margin-bottom: 4px;'><strong>Opravy mechanismů</strong> – výsuvné mechanismy, polohování, otočné hlavy</li>
                                    <li style='margin-bottom: 4px;'><strong>Čištění kožených sedaček</strong> – výhradně originálními prostředky Natuzzi</li>
                                </ul>

                                <p style='margin: 12px 0; font-size: 14px; color: #333; line-height: 1.6; background: #f8f9fa; padding: 12px 15px; border-left: 3px solid #333; border-radius: 4px;'>
                                    <strong>Prosezení sedačky není vada, se kterou se musíte smířit!</strong> Většinu problémů vyřešíme během jediné návštěvy přímo u Vás doma – <strong>bez nutnosti odvážet nábytek</strong>. Nemusíte mít obavu z přepravy ani z toho, že byste zůstali bez místa k sezení. Přes 90 % našich oprav lze provést na místě a Vaše sedačka bude vypadat a fungovat jako nová.
                                </p>

                                <p style='margin: 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    Pro čištění používáme <strong>pouze produkty Natuzzi</strong>, které jsou chemicky sladěné s impregnací a povrchovou úpravou Vašeho nábytku. Tím zajišťujeme maximální péči a dlouhou životnost sedacích souprav.
                                </p>
                            </div>

                            <!-- Upozornění -->
                            <div style='padding: 0 30px 15px 30px;'>
                                <div style='background: #fff3cd; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px 15px;'>
                                    <p style='margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;'>
                                        <strong>Máte zájem o více informací?</strong><br>
                                        Navštivte naše webové stránky <a href='{$baseUrl}' style='color: #92400e; text-decoration: underline;'>www.wgs-service.cz</a>, kde najdete:<br>
                                        • Kompletní přehled našich služeb<br>
                                        • Cenové podmínky<br>
                                        • Online objednávkový formulář<br>
                                        • Kontaktní údaje a provozní dobu
                                    </p>
                                </div>
                            </div>

                            <!-- Info box -->
                            <div style='padding: 0 30px 15px 30px;'>
                                <div style='background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 12px 15px;'>
                                    <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.5;'>
                                        💡 <strong>Tip:</strong> Pravidelné čištění a údržba kožených sedaček 1-2× ročně výrazně prodlouží jejich životnost a zachová luxusní vzhled.
                                    </p>
                                </div>
                            </div>

                            <!-- CTA Tlačítko -->
                            <div style='padding: 5px 30px 20px 30px; text-align: center;'>
                                <a href='{$baseUrl}/objednatservis.php' style='display: inline-block; background: linear-gradient(135deg, #333 0%, #1a1a1a 100%); color: #ffffff; padding: 12px 35px; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 14px; letter-spacing: 0.5px;'>
                                    Objednat servis online
                                </a>
                            </div>

                            <!-- Závěrečná sekce -->
                            <div style='padding: 15px 30px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.5;'>
                                    S pozdravem,<br>
                                    <strong>Tým White Glove Service</strong>
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style='background: #1a1a1a; padding: 20px 30px; border-radius: 0 0 8px 8px; text-align: center;'>
                            <p style='margin: 0; font-size: 14px; font-weight: 600; color: #fff;'>White Glove Service, s.r.o.</p>
                            <p style='margin: 6px 0 0 0; font-size: 12px; color: #888;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
                            <p style='margin: 6px 0 0 0; font-size: 12px; color: #888;'>
                                Tel: <a href='tel:+420725965826' style='color: #888; text-decoration: none;'>+420 725 965 826</a> |
                                Email: <a href='mailto:reklamace@wgs-service.cz' style='color: #888; text-decoration: none;'>reklamace@wgs-service.cz</a>
                            </p>
                            <p style='margin: 10px 0 0 0; font-size: 11px; color: #555;'>
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

// Zobrazit náhled
echo $htmlEmail;
?>
