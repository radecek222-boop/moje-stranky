<?php
/**
 * Skript pro vytvoření testovací zakázky pro test PDF exportu
 * Spustit: https://www.wgs-service.cz/vytvor_testovaci_zakazku.php
 */

require_once __DIR__ . '/init.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vytvoreni testovaci zakazky</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // Generovat unikátní reklamace_id
    $rok = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM wgs_reklamace WHERE reklamace_id LIKE 'TEST-{$rok}-%'");
    $pocet = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    $noveId = sprintf("TEST-%s-%03d", $rok, $pocet + 1);

    // Dlouhý popis problému pro test zalamování
    $popisProblemu = "Zákazník hlásí následující problémy s pohovkou Natuzzi model Editions B845:

1. SEDACÍ ČÁST: Propadlé sedáky na obou stranách pohovky, zejména v místě častého sezení. Pěna ztratila pružnost přibližně po 8 měsících používání.

2. POTAH: Viditelné opotřebení kůže na opěradlech, zejména v místě kontaktu s hlavou. Barva se mírně změnila a kůže je sušší.

3. MECHANISMUS: Rozkládací mechanismus vydává skřípavé zvuky při rozkládání. Zákazník uvádí, že zvuk se objevil přibližně před 2 měsíci.

Zákazník požaduje kompletní opravu nebo výměnu postižených částí. GDPR souhlas udělen.";

    $popisOpravy = "PROVEDENÁ OPRAVA:

1. Výměna sedákové pěny HR45 za novou s vyšší hustotou (HR55) pro delší životnost.

2. Ošetření kožených povrchů specializovaným kondicionérem a ochranným prostředkem.

3. Promazání a seřízení rozkládacího mechanismu, výměna opotřebovaných plastových kluzáků.

4. Kontrola všech spojů a dotažení uvolněných šroubů.

Zákazník byl poučen o správné údržbě kožených povrchů a obdržel sadu na údržbu.";

    $doplnujiciInfo = "Zákazník je VIP klient s historií 3 předchozích nákupů. Požaduje rychlé vyřízení. Preferuje ranní termíny návštěv (8:00-10:00). Parkování před domem je možné. Pes v domácnosti - přátelský. Klíče u sousedů v případě nepřítomnosti.";

    // Vložit testovací záznam - pouze sloupce které existují v DB
    $sql = "INSERT INTO wgs_reklamace (
        reklamace_id, typ, cislo,
        jmeno, email, telefon,
        adresa,
        model,
        popis_problemu, doplnujici_info, popis_opravy,
        stav, termin, cas_navstevy,
        technik,
        poznamky,
        created_at, updated_at
    ) VALUES (
        :reklamace_id, 'SERVIS', :cislo,
        :jmeno, :email, :telefon,
        :adresa,
        :model,
        :popis_problemu, :doplnujici_info, :popis_opravy,
        'open', :termin, :cas_navstevy,
        :technik,
        :poznamky,
        NOW(), NOW()
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'reklamace_id' => $noveId,
        'cislo' => 'OBJ-2025-TEST-' . rand(1000, 9999),
        'jmeno' => 'Radek Zikmund (TEST)',
        'email' => 'zikmund.radek@seznam.cz',
        'telefon' => '+420 777 888 999',
        'adresa' => 'Testovací ulice 123/45, Praha 5 - Smíchov, 150 00',
        'model' => 'Natuzzi Editions B845 Leather Sofa',
        'popis_problemu' => $popisProblemu,
        'doplnujici_info' => $doplnujiciInfo,
        'popis_opravy' => $popisOpravy,
        'termin' => date('Y-m-d', strtotime('+3 days')),
        'cas_navstevy' => '9:00 - 11:00',
        'technik' => 'Radek Zikmund',
        'poznamky' => 'TESTOVACÍ ZAKÁZKA pro ověření PDF exportu. Obsahuje dlouhé texty pro test zalamování.'
    ]);

    $primaryId = $pdo->lastInsertId();

    echo "<h1>Testovaci zakazka vytvorena</h1>";

    echo "<div class='success'>";
    echo "<strong>Zakazka uspesne vytvorena!</strong><br><br>";
    echo "ID: <strong>{$primaryId}</strong><br>";
    echo "Reklamace ID: <strong>{$noveId}</strong><br>";
    echo "Email: <strong>zikmund.radek@seznam.cz</strong>";
    echo "</div>";

    echo "<h2>Odkazy</h2>";
    echo "<a href='/protokol.php?id={$primaryId}' class='btn'>Otevrit protokol</a>";
    echo "<a href='/seznam.php?id={$primaryId}' class='btn'>Otevrit v seznamu</a>";

    echo "<h2>Vlozena data</h2>";
    echo "<table>";
    echo "<tr><th>Pole</th><th>Hodnota</th></tr>";
    echo "<tr><td>Jmeno</td><td>Radek Zikmund (TEST)</td></tr>";
    echo "<tr><td>Email</td><td>zikmund.radek@seznam.cz</td></tr>";
    echo "<tr><td>Telefon</td><td>+420 777 888 999</td></tr>";
    echo "<tr><td>Adresa</td><td>Testovací ulice 123/45, Praha 5 - Smíchov, 150 00</td></tr>";
    echo "<tr><td>Model</td><td>Natuzzi Editions B845 Leather Sofa</td></tr>";
    echo "<tr><td>Technik</td><td>Radek Zikmund</td></tr>";
    echo "<tr><td>Termin</td><td>" . date('Y-m-d', strtotime('+3 days')) . " (9:00 - 11:00)</td></tr>";
    echo "</table>";

    echo "<h2>Popis problemu (dlouhy text pro test)</h2>";
    echo "<pre>" . htmlspecialchars($popisProblemu) . "</pre>";

    echo "<h2>Navrh opravy (dlouhy text pro test)</h2>";
    echo "<pre>" . htmlspecialchars($popisOpravy) . "</pre>";

} catch (Exception $e) {
    echo "<h1>Chyba</h1>";
    echo "<div class='error'>";
    echo "<strong>Chyba pri vytvareni zakazky:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
