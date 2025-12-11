<?php
/**
 * Základní HTML šablona pro všechny grafické emaily WGS
 *
 * Použití:
 * $html = renderujGrafickyEmail([
 *     'predmet' => 'Předmět emailu',
 *     'nadpis' => 'Hlavní nadpis',           // Volitelné
 *     'osloveni' => 'Vážený/á {{customer_name}}',
 *     'obsah' => '<p>Text emailu...</p>',    // Může obsahovat HTML
 *     'tlacitko' => [                        // Volitelné
 *         'text' => 'Klikněte zde',
 *         'url' => 'https://...'
 *     ],
 *     'infobox' => 'Text v info boxu',       // Volitelné
 *     'upozorneni' => 'Důležité upozornění', // Volitelné - žlutý box
 * ]);
 */

/**
 * Renderuje kompletní grafický email
 *
 * @param array $data Data pro šablonu
 * @return string Kompletní HTML email
 */
function renderujGrafickyEmail(array $data): string {
    $baseUrl = 'https://www.wgs-service.cz';

    // Výchozí hodnoty + Markdown + nl2br pro zachování zalomení řádků
    // Markdown se převádí na HTML, pak nl2br pro řádky
    $nadpis = $data['nadpis'] ?? '';
    $osloveni = nl2br(markdownNaHtml($data['osloveni'] ?? 'Vážený zákazníku,'));
    $obsah = nl2br(markdownNaHtml($data['obsah'] ?? ''));
    $tlacitko = $data['tlacitko'] ?? null;
    $infobox = nl2br(markdownNaHtml($data['infobox'] ?? ''));
    $upozorneni = nl2br(markdownNaHtml($data['upozorneni'] ?? ''));

    // Sestavení sekcí
    $nadpisHtml = '';
    if (!empty($nadpis)) {
        $nadpisHtml = "
        <!-- Nadpis -->
        <div style='background: #f8f9fa; padding: 25px 40px; border-bottom: 1px solid #e5e5e5;'>
            <h2 style='margin: 0; font-size: 20px; font-weight: 600; color: #333;'>{$nadpis}</h2>
        </div>";
    }

    $tlacitkoHtml = '';
    if (!empty($tlacitko) && !empty($tlacitko['text']) && !empty($tlacitko['url'])) {
        $tlacitkoHtml = "
        <!-- CTA Tlačítko -->
        <div style='padding: 10px 40px 35px 40px; text-align: center;'>
            <a href='{$tlacitko['url']}' style='display: inline-block; background: linear-gradient(135deg, #333 0%, #1a1a1a 100%); color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; letter-spacing: 0.5px;'>
                {$tlacitko['text']}
            </a>
        </div>";
    }

    $infoboxHtml = '';
    if (!empty($infobox)) {
        $infoboxHtml = "
        <!-- Info box -->
        <div style='padding: 0 40px 25px 40px;'>
            <div style='background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px 20px;'>
                <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.6;'>{$infobox}</p>
            </div>
        </div>";
    }

    $upozorneniHtml = '';
    if (!empty($upozorneni)) {
        $upozorneniHtml = "
        <!-- Upozornění -->
        <div style='padding: 0 40px 25px 40px;'>
            <div style='background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px 20px;'>
                <p style='margin: 0; font-size: 14px; color: #92400e; line-height: 1.6;'>{$upozorneni}</p>
            </div>
        </div>";
    }

    return "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>White Glove Service</title>
    <link rel='icon' href='data:,'>
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

                            {$nadpisHtml}

                            <!-- Oslovení a obsah -->
                            <div style='padding: 30px 40px 25px 40px;'>
                                <p style='margin: 0 0 15px 0; font-size: 15px; color: #333;'>{$osloveni}</p>
                                <div style='font-size: 14px; color: #555; line-height: 1.7;'>
                                    {$obsah}
                                </div>
                            </div>

                            {$upozorneniHtml}
                            {$infoboxHtml}
                            {$tlacitkoHtml}

                            <!-- Závěrečná sekce -->
                            <div style='padding: 25px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.6;'>
                                    S pozdravem,<br>
                                    <strong>Tým White Glove Service</strong>
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
 * Renderuje grafický email z šablony v databázi
 * Nahrazuje proměnné ve formátu {{variable}} skutečnými hodnotami
 *
 * @param array $sablona Data šablony z DB (musí obsahovat template_data JSON)
 * @param array $promenne Pole proměnných k nahrazení
 * @return string Kompletní HTML email
 */
function renderujEmailZeSablony(array $sablona, array $promenne = []): string {
    // Pokud šablona obsahuje strukturovaná data (JSON)
    if (!empty($sablona['template_data'])) {
        $data = json_decode($sablona['template_data'], true);
        if (is_array($data)) {
            // Nahradit proměnné v každém poli
            foreach ($data as $klic => $hodnota) {
                if (is_string($hodnota)) {
                    $data[$klic] = nahradPromenne($hodnota, $promenne);
                } elseif (is_array($hodnota)) {
                    foreach ($hodnota as $k => $v) {
                        if (is_string($v)) {
                            $data[$klic][$k] = nahradPromenne($v, $promenne);
                        }
                    }
                }
            }
            return renderujGrafickyEmail($data);
        }
    }

    // Fallback - stará šablona (plain text nebo starý HTML)
    $obsah = $sablona['template'] ?? '';
    $obsah = nahradPromenne($obsah, $promenne);

    // Pokud obsah neobsahuje HTML tagy, převést na odstavce
    if (strip_tags($obsah) === $obsah) {
        $obsah = '<p>' . nl2br(htmlspecialchars($obsah)) . '</p>';
    }

    return renderujGrafickyEmail([
        'obsah' => $obsah
    ]);
}

/**
 * Převede základní Markdown syntaxi na HTML
 * Podporuje: **tučné**, *kurzíva*, __tučné__, _kurzíva_
 *
 * @param string $text Text s Markdown
 * @return string Text s HTML
 */
function markdownNaHtml(string $text): string {
    // **tučné** nebo __tučné__
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);

    // *kurzíva* nebo _kurzíva_ (ale ne uprostřed slova)
    $text = preg_replace('/(?<!\w)\*([^*]+?)\*(?!\w)/', '<em>$1</em>', $text);
    $text = preg_replace('/(?<!\w)_([^_]+?)_(?!\w)/', '<em>$1</em>', $text);

    return $text;
}

/**
 * Nahradí proměnné ve formátu {{variable}} skutečnými hodnotami
 *
 * @param string $text Text s proměnnými
 * @param array $promenne Pole proměnných [název => hodnota]
 * @return string Text s nahrazenými proměnnými
 */
function nahradPromenne(string $text, array $promenne): string {
    foreach ($promenne as $nazev => $hodnota) {
        // Podpora obou formátů: {{var}} a {var}
        $text = str_replace('{{' . $nazev . '}}', $hodnota, $text);
        $text = str_replace('{' . $nazev . '}', $hodnota, $text);
    }
    return $text;
}

/**
 * Vrací JSON strukturu pro novou grafickou šablonu
 * Použít při vytváření/updatu šablon v DB
 *
 * @param array $data Data šablony
 * @return string JSON string pro uložení do template_data
 */
function vytvorSablonuJSON(array $data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Vrací HTML náhled šablony pro editor
 *
 * @param array $data Data šablony
 * @return string HTML náhled
 */
function nahledSablony(array $data): string {
    // Pro náhled použijeme ukázkové hodnoty proměnných
    $ukazkovyData = array_merge($data, [
        'osloveni' => nahradPromenne($data['osloveni'] ?? 'Vážený/á {{customer_name}},', [
            'customer_name' => 'Jan Novák',
            'customer_email' => 'jan.novak@example.cz',
            'order_id' => '2024-0001',
            'product' => 'Natuzzi pohovka',
            'address' => 'Václavské náměstí 1, Praha 1',
            'date' => date('d.m.Y'),
            'time' => '14:00',
            'technician_name' => 'Pavel Technik',
            'technician_phone' => '+420 777 888 999',
            'company_email' => 'reklamace@wgs-service.cz',
            'company_phone' => '+420 725 965 826'
        ]),
        'obsah' => nahradPromenne($data['obsah'] ?? '', [
            'customer_name' => 'Jan Novák',
            'customer_email' => 'jan.novak@example.cz',
            'order_id' => '2024-0001',
            'product' => 'Natuzzi pohovka',
            'address' => 'Václavské náměstí 1, Praha 1',
            'date' => date('d.m.Y'),
            'time' => '14:00',
            'technician_name' => 'Pavel Technik',
            'technician_phone' => '+420 777 888 999',
            'company_email' => 'reklamace@wgs-service.cz',
            'company_phone' => '+420 725 965 826'
        ])
    ]);

    return renderujGrafickyEmail($ukazkovyData);
}
