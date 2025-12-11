<?php
/**
 * Migrace: Přidání grafických emailových šablon
 *
 * Tento skript:
 * 1. Přidá sloupec template_data do wgs_notifications
 * 2. Aktualizuje stávající šablony na grafické verze
 *
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Grafické emailové šablony</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #2d2d2d; padding: 30px; border-radius: 10px; }
        h1 { color: #fff; border-bottom: 3px solid #39ff14; padding-bottom: 10px; }
        .success { background: #1a3d1a; border: 1px solid #28a745; color: #90EE90; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3d1a1a; border: 1px solid #dc3545; color: #ff8888; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3d3d1a; border: 1px solid #f59e0b; color: #ffd700; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2d3d; border: 1px solid #17a2b8; color: #87CEEB; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        pre { background: #1a1a1a; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.85rem; border: 1px solid #444; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #444; text-align: left; }
        th { background: #333; }
        .preview { background: #fff; padding: 20px; border-radius: 5px; margin: 10px 0; }
        .preview iframe { width: 100%; height: 400px; border: none; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Grafické emailové šablony</h1>";

    // 1. Zkontrolovat zda sloupec template_data existuje
    $columns = $pdo->query("SHOW COLUMNS FROM wgs_notifications")->fetchAll(PDO::FETCH_COLUMN);

    $templateDataExists = in_array('template_data', $columns);

    echo "<h2>1. Kontrola databázové struktury</h2>";

    if ($templateDataExists) {
        echo "<div class='success'>Sloupec template_data již existuje.</div>";
    } else {
        echo "<div class='warning'>Sloupec template_data NEEXISTUJE - přidávám...</div>";
        ob_flush();
        flush();

        // Přidat sloupec HNED, aby další dotazy fungovaly
        try {
            $pdo->exec("ALTER TABLE wgs_notifications ADD COLUMN template_data JSON DEFAULT NULL AFTER template");
            echo "<div class='success'>Sloupec template_data byl úspěšně přidán.</div>";
            $templateDataExists = true;
        } catch (PDOException $e) {
            echo "<div class='error'>Chyba při přidávání sloupce: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<div class='info'>Zkuste spustit ručně v phpMyAdmin:<br><code>ALTER TABLE wgs_notifications ADD COLUMN template_data JSON DEFAULT NULL AFTER template;</code></div>";
        }
        ob_flush();
        flush();
    }

    // 2. Načíst stávající šablony
    echo "<h2>2. Stávající šablony</h2>";

    // Dynamický SELECT - bez template_data pokud sloupec neexistuje
    if ($templateDataExists) {
        $sablony = $pdo->query("SELECT id, name, subject, template, template_data FROM wgs_notifications WHERE type IN ('email', 'both') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sablony = $pdo->query("SELECT id, name, subject, template, NULL as template_data FROM wgs_notifications WHERE type IN ('email', 'both') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    echo "<table>
        <tr>
            <th>ID</th>
            <th>Název</th>
            <th>Předmět</th>
            <th>Typ šablony</th>
        </tr>";

    foreach ($sablony as $s) {
        $typ = !empty($s['template_data']) ? '<span style="color: #39ff14;">GRAFICKÁ</span>' : '<span style="color: #f59e0b;">TEXTOVÁ</span>';
        echo "<tr>
            <td>{$s['id']}</td>
            <td>{$s['name']}</td>
            <td>" . htmlspecialchars($s['subject'] ?? '-') . "</td>
            <td>{$typ}</td>
        </tr>";
    }

    echo "</table>";

    // 3. Definice nových grafických šablon
    $grafickeSablony = [
        'appointment_confirmed' => [
            'nadpis' => 'Potvrzení termínu',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => '<p>potvrzujeme termín návštěvy našeho technika.</p>

<div style="background: #f8f9fa; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Detaily návštěvy</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Datum:</strong> {{date}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Čas:</strong> {{time}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Číslo zakázky:</strong> {{order_id}}</p>
</div>

<p>V případě jakýchkoli dotazů nás prosím kontaktujte na telefonu <strong>+420 725 965 826</strong> nebo emailem na <strong>reklamace@wgs-service.cz</strong>.</p>',
            'infobox' => 'Prosíme, zajistěte přístup k nábytku a pokud možno buďte přítomni při návštěvě technika.'
        ],

        'appointment_reminder_customer' => [
            'nadpis' => 'Připomenutí termínu',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => '<p>připomínáme termín <strong>zítřejší</strong> návštěvy našeho technika.</p>

<div style="background: #f8f9fa; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Detaily návštěvy</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Datum:</strong> {{date}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Čas:</strong> {{time}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Adresa:</strong> {{address}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Číslo zakázky:</strong> {{order_id}}</p>
</div>

<p>Pokud potřebujete termín změnit, kontaktujte nás prosím co nejdříve.</p>',
            'upozorneni' => '<strong>Důležité:</strong> Pokud potřebujete termín změnit, kontaktujte nás prosím co nejdříve na telefonu +420 725 965 826.'
        ],

        'order_created' => [
            'nadpis' => 'Nová reklamace přijata',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => '<p>děkujeme za odeslání reklamace. Vaši žádost jsme přijali a budeme ji řešit.</p>

<div style="background: #f8f9fa; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Detaily reklamace</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Číslo zakázky:</strong> {{order_id}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Produkt:</strong> {{product}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Adresa:</strong> {{address}}</p>
</div>

<p>Brzy vás budeme kontaktovat ohledně termínu návštěvy technika.</p>',
            'infobox' => 'Přibližná doba vyřízení reklamace je 5-10 pracovních dní. O průběhu vás budeme informovat emailem nebo telefonicky.'
        ],

        'order_completed' => [
            'nadpis' => 'Zakázka dokončena',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => '<p>děkujeme, že jste využili služeb <strong>White Glove Service</strong>.</p>

<div style="background: #f8f9fa; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Dokončená zakázka</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Číslo zakázky:</strong> {{order_id}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Datum dokončení:</strong> {{completed_at}}</p>
</div>

<p>Pokud byste měli jakékoli dotazy nebo připomínky k provedené opravě, neváhejte nás kontaktovat.</p>

<p>Budeme rádi, když nás doporučíte svým známým.</p>',
            'tlacitko' => [
                'text' => 'Navštívit naše stránky',
                'url' => 'https://www.wgs-service.cz'
            ]
        ],

        'order_reopened' => [
            'nadpis' => 'Zakázka znovu otevřena',
            'osloveni' => 'Dobrý den,',
            'obsah' => '<p>zakázka byla znovu otevřena pro další zpracování.</p>

<div style="background: #f8f9fa; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Detaily</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Zákazník:</strong> {{customer_name}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Číslo zakázky:</strong> {{order_id}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Znovu otevřeno:</strong> {{reopened_by}} ({{reopened_at}})</p>
</div>

<p>Stav byl změněn na <strong>NOVÁ</strong>. Termín byl vymazán.</p>',
            'upozorneni' => 'Tato zakázka vyžaduje další pozornost. Prosím zkontrolujte ji v admin systému.'
        ],

        'appointment_assigned_technician' => [
            'nadpis' => 'Nový termín přiřazen',
            'osloveni' => 'Dobrý den {{technician_name}},',
            'obsah' => '<p>byl vám přiřazen nový servisní termín.</p>

<div style="background: #f8f9fa; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Detaily termínu</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Datum:</strong> {{date}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Čas:</strong> {{time}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Číslo zakázky:</strong> {{order_id}}</p>
</div>

<div style="background: #f8f9fa; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Zákazník</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Jméno:</strong> {{customer_name}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Telefon:</strong> {{customer_phone}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Adresa:</strong> {{address}}</p>
</div>

<div style="background: #f8f9fa; border-radius: 8px; padding: 18px 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Produkt</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Typ:</strong> {{product}}</p>
    <p style="margin: 5px 0; font-size: 14px;"><strong>Popis problému:</strong> {{description}}</p>
</div>',
            'infobox' => 'Prosím potvrďte přijetí termínu v admin systému.'
        ]
    ];

    // 4. Pokud je nastaveno execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>3. Provádím migraci...</h2>";

        $pdo->beginTransaction();

        try {
            // 4.1 Přidat sloupec template_data pokud neexistuje
            if (!$templateDataExists) {
                $pdo->exec("ALTER TABLE wgs_notifications ADD COLUMN template_data JSON DEFAULT NULL AFTER template");
                echo "<div class='success'>Sloupec template_data byl přidán.</div>";
            }

            // 4.2 Aktualizovat šablony podle name (ne ID!)
            $stmt = $pdo->prepare("UPDATE wgs_notifications SET template_data = :data WHERE name = :name");

            foreach ($grafickeSablony as $name => $data) {
                $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $stmt->execute(['data' => $jsonData, 'name' => $name]);

                if ($stmt->rowCount() > 0) {
                    echo "<div class='success'>Aktualizována šablona: <strong>{$name}</strong></div>";
                } else {
                    echo "<div class='warning'>Šablona {$name} nebyla nalezena v DB.</div>";
                }
            }

            $pdo->commit();

            echo "<div class='success' style='font-size: 1.2rem; padding: 20px; margin-top: 20px;'>
                <strong>MIGRACE ÚSPĚŠNĚ DOKONČENA!</strong><br>
                Všechny emailové šablony byly převedeny na grafické verze.
            </div>";

            echo "<a href='/admin.php' class='btn'>Zpět do administrace</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>
                <strong>CHYBA:</strong><br>
                " . htmlspecialchars($e->getMessage()) . "
            </div>";
        }
    } else {
        // Zobrazit náhled
        echo "<h2>3. Náhled grafických šablon</h2>";

        echo "<p>Kliknutím na 'Spustit migraci' budou všechny šablony převedeny na grafické verze.</p>";

        // Ukázka jedné šablony
        echo "<h3>Ukázka: Potvrzení termínu</h3>";

        require_once __DIR__ . '/includes/email_template_base.php';
        $ukazka = nahledSablony($grafickeSablony['appointment_confirmed']);

        echo "<div class='preview'>
            <iframe srcdoc='" . htmlspecialchars($ukazka) . "'></iframe>
        </div>";

        echo "<div style='margin-top: 30px;'>
            <a href='?execute=1' class='btn'>Spustit migraci</a>
            <a href='/admin.php' class='btn btn-danger'>Zrušit</a>
        </div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
