<?php
/**
 * Pomocný skript pro přegenerování aktualit
 * Smaže dnešní aktualitu a vygeneruje novou s 6 články
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze pro administrátory
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může generovat aktuality.");
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Přegenerování aktualit</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Přegenerování aktualit - 6 článků</h1>";

try {
    $pdo = getDbConnection();
    $dnes = date('Y-m-d');

    echo "<div class='info'><strong>KROK 1:</strong> Kontrola existujících záznamů...</div>";

    // Zkontrolovat dnešní záznam
    $stmtCheck = $pdo->prepare("SELECT id, LENGTH(obsah_cz) as delka FROM wgs_natuzzi_aktuality WHERE datum = :datum");
    $stmtCheck->execute(['datum' => $dnes]);
    $existujici = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existujici) {
        echo "<div class='info'>Nalezen existující záznam ID #{$existujici['id']} pro {$dnes} (délka: {$existujici['delka']} znaků)</div>";

        if (isset($_GET['potvrzeni']) && $_GET['potvrzeni'] === '1') {
            echo "<div class='info'><strong>KROK 2:</strong> Mazání starého záznamu...</div>";

            $stmtDelete = $pdo->prepare("DELETE FROM wgs_natuzzi_aktuality WHERE id = :id");
            $stmtDelete->execute(['id' => $existujici['id']]);

            echo "<div class='success'>Starý záznam smazán!</div>";
        } else {
            echo "<div class='error'><strong>POZOR:</strong> Existující záznam bude smazán!</div>";
            echo "<a href='?potvrzeni=1' class='btn'>POTVRDIT A PŘEGENEROVAT</a>";
            echo "<a href='aktuality.php' class='btn' style='background: #6c757d;'>ZRUŠIT</a>";
            echo "</div></body></html>";
            exit;
        }
    }

    echo "<div class='info'><strong>KROK 3:</strong> Generování nové aktuality s 6 články...</div>";

    // Svátek
    $mesic = date('n');
    $den = date('j');
    $svatky = ['11' => ['22' => 'Cecílie', '23' => 'Klement']];
    $jmenoSvatku = $svatky[$mesic][$den] ?? 'Neznámý';

    // === ŠIROKÝ ČLÁNEK (vždy první, přes celou šířku) ===
    $sirokyArticle = [
        'cz' => "## ŠIROKÝ: NATUZZI V ČESKÉ REPUBLICE\n\nNavštivte naše autorizované showroomy: **Praha** (Pasáž Lucerna - V Jámě 699/3, River Garden Karlín - Rohanské nábřeží 678/25), **Brno** (SOHO Interior Center - Modřice). Kompletní sortiment luxusního italského nábytku s odborným poradenstvím certifikovaných designérů. Otevřeno Po-Pá 10-18h, So 10-14h.\n\n[Více informací](https://www.natuzzi.cz) | [Online katalog](https://www.natuzzi.cz/katalog) | [Kontakt](https://www.natuzzi.cz/kontakt)",
        'en' => "## ŠIROKÝ: NATUZZI IN CZECH REPUBLIC\n\nVisit our authorized showrooms: **Prague** (Pasáž Lucerna - V Jámě 699/3, River Garden Karlín - Rohanské nábřeží 678/25), **Brno** (SOHO Interior Center - Modřice). Complete range of luxury Italian furniture with professional advice from certified designers. Open Mon-Fri 10am-6pm, Sat 10am-2pm.\n\n[More information](https://www.natuzzi.cz) | [Online catalog](https://www.natuzzi.cz/katalog) | [Contact](https://www.natuzzi.cz/kontakt)",
        'it' => "## ŠIROKÝ: NATUZZI NELLA REPUBBLICA CECA\n\nVisitate i nostri showroom autorizzati: **Praga** (Pasáž Lucerna - V Jámě 699/3, River Garden Karlín - Rohanské nábřeží 678/25), **Brno** (SOHO Interior Center - Modřice). Gamma completa di mobili italiani di lusso con consulenza professionale di designer certificati. Aperto Lun-Ven 10-18, Sab 10-14.\n\n[Maggiori informazioni](https://www.natuzzi.cz) | [Catalogo online](https://www.natuzzi.cz/katalog) | [Contatto](https://www.natuzzi.cz/kontakt)"
    ];

    // === 6 MENŠÍCH ČLÁNKŮ ===
    $articles = [];

    // Článek 1
    $articles[] = [
        'cz' => "## NOVINKY O ZNAČCE NATUZZI\n\nNatuzzi představuje novou kolekci Editions 2025 s revolučním designem. Kolekce zahrnuje sedací soupravy Re-vive s inovativním systémem polohování. Každý kus je ručně vyráběn v Itálii.\n\n[Prohlédněte si kolekci](https://www.natuzzi.cz/kolekce-2025)",
        'en' => "## NATUZZI BRAND NEWS\n\nNatuzzi presents new Editions 2025 collection with revolutionary design. Collection includes Re-vive seating systems with innovative reclining system. Each piece handmade in Italy.\n\n[View collection](https://www.natuzzi.cz/kolekce-2025)",
        'it' => "## NOTIZIE SUL MARCHIO NATUZZI\n\nNatuzzi presenta nuova collezione Editions 2025 con design rivoluzionario. Collezione include sistemi di seduta Re-vive con sistema innovativo di reclinazione. Ogni pezzo fatto a mano in Italia.\n\n[Visualizza collezione](https://www.natuzzi.cz/kolekce-2025)"
    ];

    // Článek 2
    $articles[] = [
        'cz' => "## PÉČE O LUXUSNÍ NÁBYTEK\n\nZimní péče o kožené sedačky. Doporučujeme pravidelné ošetřování speciálními balzámy Natuzzi Leather Care každé 2-3 měsíce. Používejte zvlhčovač vzduchu pro udržení optimální vlhkosti.\n\n[Video návod](https://www.natuzzi.cz/videa)",
        'en' => "## LUXURY FURNITURE CARE\n\nWinter care for leather sofas. We recommend regular treatment with Natuzzi Leather Care balms every 2-3 months. Use humidifier to maintain optimal humidity.\n\n[Video tutorial](https://www.natuzzi.cz/videa)",
        'it' => "## CURA DEI MOBILI DI LUSSO\n\nCura invernale divani in pelle. Raccomandiamo trattamento regolare con balsami Natuzzi Leather Care ogni 2-3 mesi. Utilizzare umidificatore per mantenere umidità ottimale.\n\n[Video tutorial](https://www.natuzzi.cz/videa)"
    ];

    // Článek 3
    $articles[] = [
        'cz' => "## WHITE GLOVE SERVICE\n\nProfesionální servisní péče o váš nábytek. Certifikovaní technici školení v Itálii. Opravy, čištění a údržba všech modelů Natuzzi.\n\n[Objednat servis](https://www.wgs-service.cz)",
        'en' => "## WHITE GLOVE SERVICE\n\nProfessional service care for your furniture. Certified technicians trained in Italy. Repairs, cleaning and maintenance of all Natuzzi models.\n\n[Order service](https://www.wgs-service.cz)",
        'it' => "## WHITE GLOVE SERVICE\n\nAssistenza professionale per i vostri mobili. Tecnici certificati formati in Italia. Riparazioni, pulizia e manutenzione di tutti modelli Natuzzi.\n\n[Ordina servizio](https://www.wgs-service.cz)"
    ];

    // Článek 4
    $articles[] = [
        'cz' => "## ITALSKÉ MATERIÁLY\n\nPrémiová italská kůže z nejlepších koželužen. Každá kůže prochází 21denním procesem zpracování. Výsledkem je materiál mimořádné kvality.\n\n[Průvodce materiály](https://www.natuzzi.cz/materialy)",
        'en' => "## ITALIAN MATERIALS\n\nPremium Italian leather from finest tanneries. Each leather undergoes 21-day processing. Result is exceptional quality material.\n\n[Materials guide](https://www.natuzzi.cz/materialy)",
        'it' => "## MATERIALI ITALIANI\n\nPelle italiana premium dalle migliori concerie. Ogni pelle subisce processo di 21 giorni. Risultato è materiale di qualità eccezionale.\n\n[Guida materiali](https://www.natuzzi.cz/materialy)"
    ];

    // Článek 5
    $articles[] = [
        'cz' => "## 60 LET TRADICE\n\nNatuzzi byla založena v roce 1959 Pasqualem Natuzzim. Dnes největší výrobce kožených sedaček na světě s více než 1200 prodejnami v 123 zemích.\n\n[Historie značky](https://www.natuzzi.cz/historie)",
        'en' => "## 60 YEARS OF TRADITION\n\nNatuzzi founded in 1959 by Pasquale Natuzzi. Today world's largest leather sofa manufacturer with over 1200 stores in 123 countries.\n\n[Brand history](https://www.natuzzi.cz/historie)",
        'it' => "## 60 ANNI DI TRADIZIONE\n\nNatuzzi fondato nel 1959 da Pasquale Natuzzi. Oggi più grande produttore mondiale divani in pelle con oltre 1200 negozi in 123 paesi.\n\n[Storia marchio](https://www.natuzzi.cz/historie)"
    ];

    // Článek 6
    $articles[] = [
        'cz' => "## AKTUÁLNÍ AKCE\n\nZimní výprodej - slevy až 30% na vybrané modely. Slevový kód NATUZZI2025 pro 15% slevu. Trade-in program - vyměňte starou sedačku za novou.\n\n[Výprodejové modely](https://www.natuzzi.cz/vyprodej)",
        'en' => "## CURRENT SALES\n\nWinter sale - discounts up to 30% on selected models. Discount code NATUZZI2025 for 15% off. Trade-in program - exchange old sofa for new.\n\n[Sale models](https://www.natuzzi.cz/vyprodej)",
        'it' => "## PROMOZIONI ATTUALI\n\nSaldi invernali - sconti fino 30% su modelli selezionati. Codice sconto NATUZZI2025 per 15% sconto. Programma Trade-in - scambia vecchio divano con nuovo.\n\n[Modelli in saldo](https://www.natuzzi.cz/vyprodej)"
    ];

    // Sestavit obsah
    $obsahCZ = $sirokyArticle['cz'] . "\n\n";
    foreach ($articles as $article) {
        $obsahCZ .= $article['cz'] . "\n\n";
    }

    $obsahEN = $sirokyArticle['en'] . "\n\n";
    foreach ($articles as $article) {
        $obsahEN .= $article['en'] . "\n\n";
    }

    $obsahIT = $sirokyArticle['it'] . "\n\n";
    foreach ($articles as $article) {
        $obsahIT .= $article['it'] . "\n\n";
    }

    // Uložit do databáze
    $stmt = $pdo->prepare("
        INSERT INTO wgs_natuzzi_aktuality
        (datum, svatek_cz, komentar_dne, obsah_cz, obsah_en, obsah_it, zdroje_json, vygenerovano_ai)
        VALUES
        (:datum, :svatek, :komentar, :obsah_cz, :obsah_en, :obsah_it, :zdroje, TRUE)
    ");

    $zdroje = json_encode([
        'struktura' => '1 široký článek + 6 menších článků',
        'generated_at' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

    $stmt->execute([
        'datum' => $dnes,
        'svatek' => $jmenoSvatku,
        'komentar' => "Dnes si připomínáme svátek {$jmenoSvatku}.",
        'obsah_cz' => $obsahCZ,
        'obsah_en' => $obsahEN,
        'obsah_it' => $obsahIT,
        'zdroje' => $zdroje
    ]);

    $newId = $pdo->lastInsertId();

    echo "<div class='success'><strong>HOTOVO!</strong></div>";
    echo "<div class='info'>";
    echo "Vytvořen nový záznam ID: <strong>{$newId}</strong><br>";
    echo "Datum: <strong>{$dnes}</strong><br>";
    echo "Svátek: <strong>{$jmenoSvatku}</strong><br>";
    echo "Struktura: <strong>1 široký + 6 menších článků</strong><br>";
    echo "Délka CZ: <strong>" . strlen($obsahCZ) . " znaků</strong>";
    echo "</div>";

    echo "<a href='aktuality.php' class='btn'>ZOBRAZIT AKTUALITY</a>";
    echo "<a href='admin.php' class='btn' style='background: #6c757d;'>ADMIN</a>";

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
