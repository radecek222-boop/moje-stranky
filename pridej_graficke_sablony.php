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
        code { background: #333; padding: 2px 6px; border-radius: 3px; }
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

        try {
            $pdo->exec("ALTER TABLE wgs_notifications ADD COLUMN template_data JSON DEFAULT NULL AFTER template");
            echo "<div class='success'>Sloupec template_data byl úspěšně přidán.</div>";
            $templateDataExists = true;
        } catch (PDOException $e) {
            echo "<div class='error'>Chyba při přidávání sloupce: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // 2. Načíst stávající šablony
    echo "<h2>2. Stávající šablony</h2>";

    if ($templateDataExists) {
        $sablony = $pdo->query("SELECT id, name, subject, template_data FROM wgs_notifications WHERE type IN ('email', 'both') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sablony = $pdo->query("SELECT id, name, subject, NULL as template_data FROM wgs_notifications WHERE type IN ('email', 'both') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    echo "<table>
        <tr>
            <th>ID (číslo)</th>
            <th>Name (klíč pro migraci)</th>
            <th>Předmět</th>
            <th>Stav</th>
        </tr>";

    foreach ($sablony as $s) {
        $typ = !empty($s['template_data']) ? '<span style="color: #39ff14;">GRAFICKÁ</span>' : '<span style="color: #f59e0b;">TEXTOVÁ</span>';
        echo "<tr>
            <td>{$s['id']}</td>
            <td><code>{$s['name']}</code></td>
            <td>" . htmlspecialchars(mb_substr($s['subject'] ?? '-', 0, 50)) . "</td>
            <td>{$typ}</td>
        </tr>";
    }

    echo "</table>";

    // 3. Definice grafických šablon
    // KLÍČ = hodnota sloupce 'name' v DB (např. 'appointment_confirmed')
    $grafickeSablony = [
        'appointment_confirmed' => [
            'nadpis' => 'Potvrzení termínu',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => 'potvrzujeme termín návštěvy našeho technika.

**Datum:** {{date}}
**Čas:** {{time}}
**Číslo zakázky:** {{order_id}}

V případě jakýchkoli dotazů nás prosím kontaktujte na telefonu **+420 725 965 826** nebo emailem na **reklamace@wgs-service.cz**.',
            'infobox' => 'Prosíme, zajistěte přístup k nábytku a pokud možno buďte přítomni při návštěvě technika.',
            'upozorneni' => '',
            'tlacitko' => ['text' => '', 'url' => '']
        ],

        'appointment_reminder_customer' => [
            'nadpis' => 'Připomenutí termínu',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => 'připomínáme termín **zítřejší** návštěvy našeho technika.

**Datum:** {{date}}
**Čas:** {{time}}
**Adresa:** {{address}}
**Číslo zakázky:** {{order_id}}

Pokud potřebujete termín změnit, kontaktujte nás prosím co nejdříve.',
            'upozorneni' => 'Pokud potřebujete termín změnit, kontaktujte nás prosím co nejdříve na telefonu +420 725 965 826.',
            'infobox' => '',
            'tlacitko' => ['text' => '', 'url' => '']
        ],

        'order_created' => [
            'nadpis' => 'Nová reklamace přijata',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => 'děkujeme za odeslání reklamace. Vaši žádost jsme přijali a budeme ji řešit.

**Číslo zakázky:** {{order_id}}
**Produkt:** {{product}}
**Adresa:** {{address}}

Brzy vás budeme kontaktovat ohledně termínu návštěvy technika.',
            'infobox' => 'Přibližná doba vyřízení reklamace je 5-10 pracovních dní. O průběhu vás budeme informovat emailem nebo telefonicky.',
            'upozorneni' => '',
            'tlacitko' => ['text' => '', 'url' => '']
        ],

        'order_completed' => [
            'nadpis' => 'Zakázka dokončena',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => 'děkujeme, že jste využili služeb **White Glove Service**.

**Číslo zakázky:** {{order_id}}
**Datum dokončení:** {{completed_at}}

Pokud byste měli jakékoli dotazy nebo připomínky k provedené opravě, neváhejte nás kontaktovat.

Budeme rádi, když nás doporučíte svým známým.',
            'tlacitko' => [
                'text' => 'Navštívit naše stránky',
                'url' => 'https://www.wgs-service.cz'
            ],
            'infobox' => '',
            'upozorneni' => ''
        ],

        'order_reopened' => [
            'nadpis' => 'Zakázka znovu otevřena',
            'osloveni' => 'Dobrý den,',
            'obsah' => 'zakázka byla znovu otevřena pro další zpracování.

**Zákazník:** {{customer_name}}
**Číslo zakázky:** {{order_id}}
**Znovu otevřeno:** {{reopened_by}} ({{reopened_at}})

Stav byl změněn na **NOVÁ**. Termín byl vymazán.',
            'upozorneni' => 'Tato zakázka vyžaduje další pozornost. Prosím zkontrolujte ji v admin systému.',
            'infobox' => '',
            'tlacitko' => ['text' => '', 'url' => '']
        ],

        'appointment_assigned_technician' => [
            'nadpis' => 'Nový termín přiřazen',
            'osloveni' => 'Dobrý den {{technician_name}},',
            'obsah' => 'byl vám přiřazen nový servisní termín.

**Datum:** {{date}}
**Čas:** {{time}}
**Číslo zakázky:** {{order_id}}

**Zákazník:**
Jméno: {{customer_name}}
Telefon: {{customer_phone}}
Adresa: {{address}}

**Produkt:**
Typ: {{product}}
Popis problému: {{description}}',
            'infobox' => 'Prosím potvrďte přijetí termínu v admin systému.',
            'upozorneni' => '',
            'tlacitko' => ['text' => '', 'url' => '']
        ],

        'contact_attempt' => [
            'nadpis' => 'Pokus o kontakt',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => 'pokusili jsme se Vás kontaktovat ohledně Vaší servisní žádosti, ale nepodařilo se nám Vás zastihnout.

**Číslo zakázky:** {{order_id}}

Prosíme, kontaktujte nás zpět na telefonu **+420 725 965 826** nebo odpovědí na tento email.',
            'upozorneni' => 'Pokud se nám nepodaří Vás kontaktovat do 3 pracovních dnů, bude zakázka dočasně pozastavena.',
            'infobox' => '',
            'tlacitko' => ['text' => '', 'url' => '']
        ],

        'waiting_dealer_response' => [
            'nadpis' => 'Čekání na vyjádření prodejce',
            'osloveni' => 'Vážený/á {{customer_name}},',
            'obsah' => 'informujeme Vás o průběhu Vaší reklamace.

**Číslo zakázky:** {{order_id}}

V současné době čekáme na vyjádření prodejce. Jakmile obdržíme odpověď, budeme Vás informovat o dalším postupu.',
            'infobox' => 'Průběžně sledujeme stav a v případě prodlení prodejce urgujeme.',
            'upozorneni' => '',
            'tlacitko' => ['text' => '', 'url' => '']
        ]
    ];

    // 4. Provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>3. Provádím migraci...</h2>";

        $pdo->beginTransaction();

        try {
            // Nejdřív zjistit strukturu - jaký je datový typ id?
            $checkStmt = $pdo->prepare("SELECT id, name FROM wgs_notifications WHERE id = :id LIMIT 1");
            $updateStmt = $pdo->prepare("UPDATE wgs_notifications SET template_data = :data WHERE id = :id");

            $uspesne = 0;
            $neuspesne = 0;

            foreach ($grafickeSablony as $id => $data) {
                // Kontrola existence
                $checkStmt->execute(['id' => $id]);
                $existuje = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$existuje) {
                    echo "<div class='warning'>Šablona <code>{$id}</code> nebyla nalezena v DB (SELECT vrátil prázdný výsledek).</div>";
                    $neuspesne++;
                    continue;
                }

                // UPDATE
                $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $updateStmt->execute(['data' => $jsonData, 'id' => $id]);

                echo "<div class='success'>Aktualizována šablona: <code>{$id}</code> (name: {$existuje['name']})</div>";
                $uspesne++;
            }

            $pdo->commit();

            echo "<div class='success' style='font-size: 1.2rem; padding: 20px; margin-top: 20px;'>
                <strong>MIGRACE DOKONČENA!</strong><br>
                Úspěšně aktualizováno: {$uspesne} šablon<br>
                Nenalezeno: {$neuspesne} šablon
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
        echo "<h2>3. Připraveno k migraci</h2>";
        echo "<p>Následující šablony budou aktualizovány na grafické verze:</p>";

        echo "<ul>";
        foreach (array_keys($grafickeSablony) as $name) {
            echo "<li><code>{$name}</code></li>";
        }
        echo "</ul>";

        echo "<div style='margin-top: 30px;'>
            <a href='?execute=1' class='btn'>Spustit migraci</a>
            <a href='/admin.php' class='btn btn-danger'>Zrušit</a>
        </div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
