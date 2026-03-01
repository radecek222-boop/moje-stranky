<?php
/**
 * Migrace: Doplnění žádosti o přístup k internetu do šablon potvrzení a připomenutí termínu
 *
 * Tento skript aktualizuje emailové šablony appointment_confirmed a appointment_reminder
 * v tabulce wgs_notifications tak, aby obsahovaly žádost o WiFi přístup pro technika.
 * Důvod: Pracujeme s online protokoly a ne všude má Vodafone pokrytí.
 *
 * Skript lze spustit vícekrát – přidá text pouze pokud tam ještě není.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola – pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Doplnění žádosti o přístup k internetu</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1100px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error   { background: #f8d7da; border: 1px solid #f5c6cb;
                   color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info    { background: #d1ecf1; border: 1px solid #bee5eb;
                   color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .preview { background: #f8f9fa; border: 1px solid #dee2e6;
                   padding: 15px; border-radius: 5px; margin: 15px 0;
                   white-space: pre-wrap; font-family: monospace; font-size: 0.85rem;
                   max-height: 400px; overflow-y: auto; }
        .highlight { background: #fffbcc; border-left: 4px solid #f0c040;
                     padding: 12px; margin: 10px 0; border-radius: 0 5px 5px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #000; }
        .btn-secondary { background: #666; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
        th { background: #eee; font-weight: 600; }
        .badge-ok { background: #28a745; color: #fff; padding: 2px 8px;
                    border-radius: 10px; font-size: 0.8rem; }
        .badge-chybi { background: #dc3545; color: #fff; padding: 2px 8px;
                       border-radius: 10px; font-size: 0.8rem; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Doplnění žádosti o přístup k internetu do šablon termínu</h1>";
echo "<div class='info'>
    <strong>Účel:</strong> Doplnit do emailu o potvrzení i připomenutí termínu žádost zákazníkovi,
    aby technikovi umožnil přístup k WiFi síti. Pracujeme s online protokoly
    a ne všude má Vodafone mobilní pokrytí.
</div>";

// Text, který chceme přidat
$textOInternetu = "\nPŘÍSTUP K INTERNETU:\nProsíme Vás o umožnění přístupu k Vaší WiFi síti pro technika během servisního zásahu. Pracujeme výhradně s online protokoly a v některých lokalitách není dostupné mobilní pokrytí sítě. Bez přístupu k internetu nelze řádně dokončit a odeslat protokol o servisním zásahu. Děkujeme za pochopení.\n";

// Identifikátor pro kontrolu přítomnosti textu
$identifikatorTextu = 'PŘÍSTUP K INTERNETU:';

try {
    $pdo = getDbConnection();

    // Načíst obě šablony
    $spousteciUdalosti = ['appointment_confirmed', 'appointment_reminder'];

    $stmtVyber = $pdo->prepare("
        SELECT id, name, trigger_event, subject, template
        FROM wgs_notifications
        WHERE trigger_event IN ('appointment_confirmed', 'appointment_reminder')
          AND type = 'email'
        ORDER BY trigger_event
    ");
    $stmtVyber->execute();
    $sablony = $stmtVyber->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sablony)) {
        echo "<div class='warning'><strong>Varování:</strong> V tabulce wgs_notifications nebyly nalezeny žádné šablony
        pro <code>appointment_confirmed</code> nebo <code>appointment_reminder</code>.<br>
        Zkontrolujte zda tabulka existuje a šablony jsou aktivní.</div>";
    }

    // Zobrazit přehled nalezených šablon
    echo "<h2>Nalezené šablony</h2>";
    echo "<table>
        <tr>
            <th>ID</th><th>Název</th><th>Trigger</th><th>Předmět</th><th>Stav WiFi textu</th>
        </tr>";

    $sablonyProAktualizaci = [];

    foreach ($sablony as $sablona) {
        $maText = strpos($sablona['template'], $identifikatorTextu) !== false;
        $stav = $maText
            ? "<span class='badge-ok'>Již obsahuje</span>"
            : "<span class='badge-chybi'>Chybí – bude doplněno</span>";

        if (!$maText) {
            $sablonyProAktualizaci[] = $sablona;
        }

        echo "<tr>
            <td>{$sablona['id']}</td>
            <td>" . htmlspecialchars($sablona['name']) . "</td>
            <td><code>{$sablona['trigger_event']}</code></td>
            <td>" . htmlspecialchars($sablona['subject']) . "</td>
            <td>{$stav}</td>
        </tr>";
    }
    echo "</table>";

    // Ukázat text, který bude doplněn
    echo "<h2>Text, který bude doplněn</h2>";
    echo "<div class='highlight'><pre>" . htmlspecialchars($textOInternetu) . "</pre></div>";

    // Náhled nových šablon
    if (!empty($sablonyProAktualizaci)) {
        echo "<h2>Náhled aktualizovaných šablon</h2>";
        foreach ($sablonyProAktualizaci as $sablona) {
            $novaSablona = rtrim($sablona['template']) . "\n" . $textOInternetu;
            echo "<h3>" . htmlspecialchars($sablona['name']) . " (<code>{$sablona['trigger_event']}</code>)</h3>";
            echo "<div class='preview'>" . htmlspecialchars($novaSablona) . "</div>";
        }
    } else {
        echo "<div class='success'><strong>Všechny nalezené šablony již text o WiFi přístupu obsahují.</strong></div>";
    }

    // Zpracování aktualizace
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        echo "<h2>Provádím aktualizaci...</h2>";

        if (empty($sablonyProAktualizaci)) {
            echo "<div class='info'>Žádné šablony není třeba aktualizovat – text je již přítomen.</div>";
        } else {
            $pdo->beginTransaction();
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE wgs_notifications
                    SET template = :novaSablona,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                foreach ($sablonyProAktualizaci as $sablona) {
                    $novaSablona = rtrim($sablona['template']) . "\n" . $textOInternetu;
                    $stmtUpdate->execute([
                        'novaSablona' => $novaSablona,
                        'id'          => $sablona['id'],
                    ]);
                    echo "<div class='success'>
                        Aktualizovana sablona ID {$sablona['id']}:
                        <strong>" . htmlspecialchars($sablona['name']) . "</strong>
                        (<code>{$sablona['trigger_event']}</code>)
                    </div>";
                }

                $pdo->commit();
                echo "<div class='success'><strong>Migrace dokoncena uspesne.</strong>
                    Celkem aktualizovano sablony: " . count($sablonyProAktualizaci) . "</div>";

            } catch (PDOException $chybaDb) {
                $pdo->rollBack();
                echo "<div class='error'><strong>CHYBA při aktualizaci:</strong><br>"
                     . htmlspecialchars($chybaDb->getMessage()) . "</div>";
            }
        }

        echo "<br><a href='aktualizuj_sablony_termin_internet.php' class='btn btn-secondary'>Zpet na kontrolu</a>";

    } else {
        // Zobrazit tlačítko pro spuštění
        if (!empty($sablonyProAktualizaci)) {
            echo "<br><a href='aktualizuj_sablony_termin_internet.php?execute=1' class='btn'>
                SPUSTIT AKTUALIZACI (" . count($sablonyProAktualizaci) . " sablony)
            </a>";
        }
        echo "<a href='admin.php' class='btn btn-secondary'>Zpet do adminu</a>";
    }

} catch (Exception $vyjimka) {
    echo "<div class='error'><strong>KRITICKÁ CHYBA:</strong><br>"
         . htmlspecialchars($vyjimka->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
