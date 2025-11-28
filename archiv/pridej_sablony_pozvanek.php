<?php
/**
 * Migrace: Pridani jednoduchych sablon pro pozvanky
 *
 * Tento skript prida do wgs_notifications dve sablony:
 * - invitation_prodejce (pro prodejce)
 * - invitation_technik (pro techniky)
 *
 * Sablony jsou jednoduchy plain text - zadne HTML problemy.
 * Editovat je muzete v admin panelu v karte "Email sablony".
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Sablony pozvanek</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 900px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .success { background: #e8f5e9; border: 1px solid #c8e6c9;
                   color: #2e7d32; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #ffebee; border: 1px solid #ffcdd2;
                 color: #c62828; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .info { background: #e3f2fd; border: 1px solid #bbdefb;
                color: #1565c0; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Sablony pozvanek</h1>";

    // Kontrola zda sablony uz existuji
    $stmt = $pdo->prepare("SELECT id, name FROM wgs_notifications WHERE id LIKE 'invitation_%'");
    $stmt->execute();
    $existujici = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($existujici)) {
        echo "<div class='info'>";
        echo "<strong>Nalezene existujici sablony:</strong><br>";
        foreach ($existujici as $s) {
            echo "- {$s['id']}: {$s['name']}<br>";
        }
        echo "</div>";
    }

    // Pokud je nastaveno ?execute=1, provest migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

        $pridano = 0;
        $aktualizovano = 0;

        // Sablona pro prodejce
        $sqlProdejce = "
            INSERT INTO wgs_notifications (
                id, name, description, trigger_event, recipient_type, type,
                subject, template, variables, active
            ) VALUES (
                'invitation_prodejce',
                'Pozvanka pro prodejce',
                'Email s pozvankou a registracnim klicem pro nove prodejce',
                'invitation_send',
                'seller',
                'email',
                'Pozvanka do systemu WGS - Prodejce',
                :template,
                :variables,
                1
            ) ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                subject = VALUES(subject),
                template = VALUES(template),
                variables = VALUES(variables),
                updated_at = NOW()
        ";

        $templateProdejce = 'Dobry den,

byli jste pozvani jako prodejce do systemu White Glove Service pro spravu servisnich zakazek Natuzzi.

================================================================================
                         VAS REGISTRACNI KLIC
================================================================================

                              {{registration_key}}

     (Zkopirujte tento klic - budete ho potrebovat pri registraci)

================================================================================
                    JAK SE ZAREGISTROVAT
================================================================================

KROK 1: Otevrete stranku registrace
        {{app_url}}/registration.php

KROK 2: Vyplnte formular
        - Registracni klic: vlozte klic z tohoto emailu
        - Jmeno a prijmeni: vase cele jmeno
        - Email: vase emailova adresa
        - Telefon: vase telefonni cislo
        - Heslo: vymyslete si heslo (min. 12 znaku)

KROK 3: Prihlaste se
        {{app_url}}/login.php

================================================================================
                    CO BUDETE MOCT DELAT V SYSTEMU
================================================================================

  - Zadavat nove reklamace pro vase zakazniky
  - Sledovat stav vasich zakazek v realnem case
  - Videt historii vsech reklamaci ktere jste zadali
  - Nahravat dokumenty a fotky k zakazkam
  - Pridavat poznamky pro techniky
  - Videt kdy technik navstivi zakaznika

================================================================================
                         DULEZITE UPOZORNENI
================================================================================

Registracni klic je urcen pouze pro vas.
Prosim, nesdílejte ho s nikym dalsim.

================================================================================
                    POTREBUJETE POMOC?
================================================================================

Radi vas proskolime po telefonu nebo osobne.
Skoleni je zdarma a trva priblizne 15-30 minut.

Telefon: +420 725 965 826
Email:   reklamace@wgs-service.cz

================================================================================
               White Glove Service - Autorizovany servis Natuzzi
                           www.wgs-service.cz
================================================================================';

        $stmt = $pdo->prepare($sqlProdejce);
        $stmt->execute([
            'template' => $templateProdejce,
            'variables' => json_encode(['{{registration_key}}', '{{app_url}}'])
        ]);

        if ($stmt->rowCount() > 0) {
            if ($stmt->rowCount() === 1) {
                $pridano++;
                echo "<div class='success'>Pridana sablona: invitation_prodejce</div>";
            } else {
                $aktualizovano++;
                echo "<div class='success'>Aktualizovana sablona: invitation_prodejce</div>";
            }
        }

        // Sablona pro techniky
        $sqlTechnik = "
            INSERT INTO wgs_notifications (
                id, name, description, trigger_event, recipient_type, type,
                subject, template, variables, active
            ) VALUES (
                'invitation_technik',
                'Pozvanka pro technika',
                'Email s pozvankou a registracnim klicem pro nove techniky',
                'invitation_send',
                'technician',
                'email',
                'Pozvanka do systemu WGS - Servisni technik',
                :template,
                :variables,
                1
            ) ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                subject = VALUES(subject),
                template = VALUES(template),
                variables = VALUES(variables),
                updated_at = NOW()
        ";

        $templateTechnik = 'Dobry den,

byli jste pozvani jako servisni technik do systemu White Glove Service pro spravu servisnich zakazek Natuzzi.

================================================================================
                         VAS REGISTRACNI KLIC
================================================================================

                              {{registration_key}}

     (Zkopirujte tento klic - budete ho potrebovat pri registraci)

================================================================================
                    JAK SE ZAREGISTROVAT
================================================================================

KROK 1: Otevrete stranku registrace
        {{app_url}}/registration.php

KROK 2: Vyplnte formular
        - Registracni klic: vlozte klic z tohoto emailu
        - Jmeno a prijmeni: vase cele jmeno
        - Email: vase emailova adresa
        - Telefon: vase telefonni cislo
        - Heslo: vymyslete si heslo (min. 12 znaku)

KROK 3: Prihlaste se
        {{app_url}}/login.php

================================================================================
                    CO BUDETE MOCT DELAT V SYSTEMU
================================================================================

  - Videt sve prirazene zakazky v prehlednem seznamu
  - Menit stav zakazky (Ceka / Domluvena / Hotovo)
  - Vyplnovat servisni protokoly s automatickym prekladem
  - Nahravat fotky pred a po oprave
  - Videt adresu zakaznika na mape s navigaci
  - Nechat zakaznika elektronicky podepsat protokol
  - Exportovat protokol do PDF a poslat zakaznikovi

================================================================================
                         DULEZITE UPOZORNENI
================================================================================

Registracni klic je urcen pouze pro vas.
Prosim, nesdílejte ho s nikym dalsim.

================================================================================
                    POTREBUJETE POMOC?
================================================================================

Radi vas proskolime po telefonu nebo osobne.
Skoleni je zdarma a trva priblizne 15-30 minut.

Telefon: +420 725 965 826
Email:   reklamace@wgs-service.cz

================================================================================
               White Glove Service - Autorizovany servis Natuzzi
                           www.wgs-service.cz
================================================================================';

        $stmt = $pdo->prepare($sqlTechnik);
        $stmt->execute([
            'template' => $templateTechnik,
            'variables' => json_encode(['{{registration_key}}', '{{app_url}}'])
        ]);

        if ($stmt->rowCount() > 0) {
            if ($stmt->rowCount() === 1) {
                $pridano++;
                echo "<div class='success'>Pridana sablona: invitation_technik</div>";
            } else {
                $aktualizovano++;
                echo "<div class='success'>Aktualizovana sablona: invitation_technik</div>";
            }
        }

        // Zobrazit vysledek
        echo "<div class='success'>";
        echo "<strong>MIGRACE DOKONCENA</strong><br>";
        echo "Pridano: $pridano sablon<br>";
        echo "Aktualizovano: $aktualizovano sablon";
        echo "</div>";

        // Zobrazit aktualni stav
        $stmt = $pdo->prepare("SELECT id, name, subject, recipient_type FROM wgs_notifications WHERE id LIKE 'invitation_%' ORDER BY id");
        $stmt->execute();
        $sablony = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($sablony)) {
            echo "<h2>Aktualni sablony pozvanek:</h2>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Nazev</th><th>Predmet</th><th>Prijemce</th></tr>";
            foreach ($sablony as $s) {
                echo "<tr>";
                echo "<td>{$s['id']}</td>";
                echo "<td>{$s['name']}</td>";
                echo "<td>{$s['subject']}</td>";
                echo "<td>{$s['recipient_type']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        echo "<p><a href='/admin.php' class='btn'>Zpet do admin panelu</a></p>";

    } else {
        // Nahled
        echo "<div class='info'>";
        echo "<strong>Tato migrace prida/aktualizuje dve sablony pozvanek:</strong><br><br>";
        echo "1. <strong>invitation_prodejce</strong> - Pozvanka pro prodejce<br>";
        echo "2. <strong>invitation_technik</strong> - Pozvanka pro technika<br><br>";
        echo "Sablony jsou jednoduchy plain text (zadne HTML).<br>";
        echo "Po pridani je muzete editovat v admin panelu v karte 'Email sablony'.";
        echo "</div>";

        echo "<p>";
        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='/admin.php' class='btn' style='background:#666;'>Zrusit</a>";
        echo "</p>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
