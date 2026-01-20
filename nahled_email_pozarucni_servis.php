<?php
/**
 * Náhled emailu: Pozáruční servis Natuzzi pro existující zákazníky
 *
 * Tento email se odesílá zákazníkům, u kterých WGS již byl na servisu
 * Cíl: Informovat o dostupnosti pozáručního servisu
 */

require_once __DIR__ . '/includes/email_template_base.php';

// Data pro email
$emailData = [
    'nadpis' => 'NATUZZI – Pozáruční servis',

    'osloveni' => 'Vážená paní, vážený pane,',

    'obsah' => '<p style="margin-bottom: 15px;">
        rádi bychom Vás informovali, že pro společnost <strong>Natuzzi</strong> poskytujeme komplexní <strong>pozáruční servisní služby</strong>.
    </p>

    <p style="margin-bottom: 15px;">
        Jelikož jsme u Vás již v minulosti prováděli servis zakoupeného produktu Natuzzi, rádi bychom Vám připomněli, že jsme tu pro Vás i po skončení záruční doby.
    </p>

    <h3 style="margin: 25px 0 15px 0; font-size: 16px; font-weight: 600; color: #333;">
        Naše služby zahrnují:
    </h3>

    <ul style="margin: 0 0 20px 0; padding-left: 25px; line-height: 1.8;">
        <li><strong>Opravy elektrických prvků</strong> – ovládání polohování, LED osvětlení, USB nabíječky</li>
        <li><strong>Opravy mechanismů</strong> – výsuvné mechanismy, polohování, otočné hlavy</li>
        <li><strong>Řešení vad prosezení</strong> – obnova komfortu sedacích ploch</li>
        <li><strong>Profesionální přečalounění</strong> – včetně výběru kvalitních materiálů</li>
        <li><strong>Čištění kožených sedaček</strong> – výhradně originálními prostředky Natuzzi</li>
    </ul>

    <p style="margin-bottom: 15px;">
        Pro čištění používáme <strong>pouze produkty Natuzzi</strong>, které jsou chemicky sladěné s impregnací a povrchovou úpravou Vašeho nábytku. Tím zajišťujeme maximální péči a dlouhou životnost sedacích souprav.
    </p>',

    'infobox' => '💡 <strong>Tip:</strong> Pravidelné čištění a údržba kožených sedaček 1-2× ročně výrazně prodlouží jejich životnost a zachová luxusní vzhled.',

    'tlacitko' => [
        'text' => 'Objednat servis online',
        'url' => 'https://www.wgs-service.cz/novareklamace.php'
    ],

    'upozorneni' => '<strong>Máte zájem o více informací?</strong><br>
    Navštivte naše webové stránky <a href="https://www.wgs-service.cz" style="color: #92400e; text-decoration: underline;">www.wgs-service.cz</a>, kde najdete:<br>
    • Kompletní přehled našich služeb<br>
    • Cenové podmínky<br>
    • Online objednávkový formulář<br>
    • Kontaktní údaje a provozní dobu'
];

// Vygenerovat HTML email
$htmlEmail = renderujGrafickyEmail($emailData);

// Zobrazit náhled
echo $htmlEmail;
?>
