<?php
/**
 * DEBUG VERZE - Generátor aktualit s viditelným výstupem chyb
 * POUZE PRO ADMINISTRÁTORY
 */

require_once __DIR__ . '/../init.php';

// BEZPEČNOST: Pouze pro administrátory
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může používat debug generátor.");
}

// Zobrazit všechny chyby
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>DEBUG: Generování aktualit</h1>";
echo "<pre>";

echo "1. init.php načten (admin autentizován)...\n";
echo "   ✅ init.php načten\n\n";

echo "2. Připojení k databázi...\n";
try {
    $pdo = getDbConnection();
    echo "   ✅ Databáze připojena\n\n";
} catch (Exception $e) {
    echo "   ❌ CHYBA: " . $e->getMessage() . "\n\n";
    die();
}

echo "3. Kontrola tabulky...\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_natuzzi_aktuality'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Tabulka wgs_natuzzi_aktuality existuje\n\n";
    } else {
        echo "   ❌ Tabulka neexistuje!\n\n";
        die();
    }
} catch (Exception $e) {
    echo "   ❌ CHYBA: " . $e->getMessage() . "\n\n";
    die();
}

echo "4. Kontrola dnešního záznamu...\n";
$dnes = date('Y-m-d');
try {
    $stmtCheck = $pdo->prepare("SELECT id FROM wgs_natuzzi_aktuality WHERE datum = :datum");
    $stmtCheck->execute(['datum' => $dnes]);

    if ($stmtCheck->rowCount() > 0) {
        echo "   ⚠️ Záznam pro {$dnes} již existuje\n";
        $existujici = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        echo "   ID: " . $existujici['id'] . "\n\n";

        // Zobrazit existující záznam
        $stmtData = $pdo->prepare("SELECT * FROM wgs_natuzzi_aktuality WHERE datum = :datum");
        $stmtData->execute(['datum' => $dnes]);
        $data = $stmtData->fetch(PDO::FETCH_ASSOC);

        echo "5. Existující data:\n";
        echo "   Datum: " . $data['datum'] . "\n";
        echo "   Svátek: " . $data['svatek_cz'] . "\n";
        echo "   CZ obsah (prvních 200 znaků): " . mb_substr($data['obsah_cz'], 0, 200) . "...\n\n";

        echo "✅ HOTOVO - Záznam již existuje\n";
        echo "</pre>";
        die();
    } else {
        echo "   ✅ Žádný záznam pro dnešek, budu generovat nový\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ CHYBA: " . $e->getMessage() . "\n\n";
    die();
}

echo "5. Generování obsahu...\n";

// Dnešní svátek
$mesic = date('n');
$den = date('j');
$svatky = [
    '11' => ['22' => 'Cecílie', '23' => 'Klement']
];
$jmenoSvatku = $svatky[$mesic][$den] ?? 'Neznámý';
echo "   Svátek: {$jmenoSvatku}\n";

// Obsah v češtině
$obsahCZ = "# Denní aktuality Natuzzi\n\n";
$obsahCZ .= "**Datum:** " . date('d.m.Y') . " | **Svátek má:** {$jmenoSvatku}\n\n";
$obsahCZ .= "Vítejte u dnešních aktualit o luxusním italském nábytku Natuzzi. Přinášíme vám nejnovější trendy, tipy na péči a exkluzivní nabídky z našich showroomů.\n\n";

$obsahCZ .= "![Natuzzi Sofa](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1200&h=400&fit=crop)\n\n";

$obsahCZ .= "## Novinky o značce Natuzzi\n\n";

$obsahCZ .= "**1. Nová kolekce Natuzzi Editions 2025 - Italský design v českých domovech**\n\n";
$obsahCZ .= "Natuzzi představuje revolučnítendance kolekci Editions 2025, která kombinuje tradiční italské řemeslo s moderními materiály. Kolekce zahrnuje sedací soupravy Re-vive, které nabízejí dokonalý komfort díky inovativnímu systému polohování. Každý kus je ručně vyráběn v Itálii z prémiových materiálů.\n\n";
$obsahCZ .= "[Prohlédněte si celou kolekci](https://www.natuzzi.com/cz/editions-2025) | [Objednat katalog](https://www.natuzzi.cz/katalog)\n\n";

$obsahCZ .= "**2. Udržitelnost v centru pozornosti**\n\n";
$obsahCZ .= "Natuzzi pokračuje ve svém závazku k udržitelnosti. Všechny kůže pocházejí z kontrolovaných zdrojů a zpracovávají se ekologickými metodami. Nová kolekce používá FSC certifikované dřevo a recyklovatelné materiály. Značka Natuzzi získala certifikaci ISO 14001 pro environmentální management.\n\n";
$obsahCZ .= "[Více o udržitelnosti](https://www.natuzzi.com/sustainability)\n\n";

$obsahCZ .= "**3. Exkluzivní akce v pražském showroomu**\n\n";
$obsahCZ .= "Od zítřka spouštíme speciální akci na vybrané modely v našem pražském showroomu Pasáž Lucerna. Získejte slevu až 25% na modely z předchozích kolekcí a poradenství našich designérů zdarma. Akce trvá pouze tento týden.\n\n";
$obsahCZ .= "[Rezervovat si termín](https://www.natuzzi.cz/rezervace) | [Adresa showroomu](https://goo.gl/maps/natuzzi-praha)\n\n";

$obsahCZ .= "**4. Nové trendy v bytovém designu 2025**\n\n";
$obsahCZ .= "Podle nejnovějšího průzkumu Natuzzi Design Institute jsou hlavními trendy pro rok 2025: zemité tóny, modulární nábytek a multifunkční prostory. Natuzzi přináší řešení, která dokonale odpovídají těmto trendům.\n\n";
$obsahCZ .= "[Průvodce trendy 2025](https://www.natuzzi.com/trends-2025)\n\n";

$obsahCZ .= "## Péče o luxusní nábytek\n\n";

$obsahCZ .= "**Zimní péče o kožené sedačky - kompletní průvodce**\n\n";
$obsahCZ .= "Zimní období klade na kožený nábytek zvýšené nároky. Nízká vlhkost vzduchu způsobená topením může vést k vysychání kůže. Doporučujeme pravidelné ošetřování speciálními balzámy Natuzzi Leather Care každé 2-3 měsíce. Používejte zvlhčovač vzduchu pro udržení optimální vlhkosti 40-60%. Vyvarujte se přímého kontaktu s radiátory.\n\n";
$obsahCZ .= "[Koupit Natuzzi Leather Care](https://www.natuzzi.cz/pece) | [Video návod na péči](https://youtu.be/natuzzi-care)\n\n";

$obsahCZ .= "**Čištění textilních potahů - tipy od profesionálů**\n\n";
$obsahCZ .= "Pro textilní potahy doporučujeme pravidelné vysávání měkkým nástavcem jednou týdně. Na skvrny použijte pouze certifikované čistící prostředky vhodné pro daný typ látky. Natuzzi nabízí profesionální čištění v rámci servisní péče White Glove Service.\n\n";
$obsahCZ .= "[Objednat WGS čištění](https://www.wgs-service.cz/novareklamace.php)\n\n";

$obsahCZ .= "## Natuzzi v České republice\n\n";

$obsahCZ .= "Navštivte naše autorizované showroomy: **Praha** (Pasáž Lucerna - Štěpánská 61, River Garden Karlín - Prvního pluku 621), **Brno** (Veveří 38). Kompletní sortiment luxusního italského nábytku s odborným poradenstvím certifikovaných designérů. Otevřeno Po-Pá 9-18h, So 10-16h.\n\n";
$obsahCZ .= "[Více informací](https://www.natuzzi.cz) | [Online katalog](https://www.natuzzi.cz/katalog) | [Kontakt](https://www.natuzzi.cz/kontakt)\n\n";

echo "   ✅ CZ obsah vygenerován (" . strlen($obsahCZ) . " znaků)\n";

// Anglický obsah - KOMPLETNÍ překlad
$obsahEN = "# Natuzzi Daily News\n\n";
$obsahEN .= "**Date:** " . date('m/d/Y') . " | **Name Day:** {$jmenoSvatku}\n\n";
$obsahEN .= "Welcome to today's news about luxury Italian furniture Natuzzi. We bring you the latest trends, care tips and exclusive offers from our showrooms.\n\n";

$obsahEN .= "![Natuzzi Sofa](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1200&h=400&fit=crop)\n\n";

$obsahEN .= "## Natuzzi Brand News\n\n";

$obsahEN .= "**1. New Natuzzi Editions 2025 Collection - Italian Design in Czech Homes**\n\n";
$obsahEN .= "Natuzzi presents the revolutionary Editions 2025 collection, which combines traditional Italian craftsmanship with modern materials. The collection includes Re-vive seating systems that offer perfect comfort thanks to an innovative reclining system. Each piece is handmade in Italy from premium materials.\n\n";
$obsahEN .= "[View the full collection](https://www.natuzzi.com/cz/editions-2025) | [Order catalog](https://www.natuzzi.cz/katalog)\n\n";

$obsahEN .= "**2. Sustainability in Focus**\n\n";
$obsahEN .= "Natuzzi continues its commitment to sustainability. All leathers come from controlled sources and are processed using ecological methods. The new collection uses FSC-certified wood and recyclable materials. Natuzzi has received ISO 14001 certification for environmental management.\n\n";
$obsahEN .= "[More about sustainability](https://www.natuzzi.com/sustainability)\n\n";

$obsahEN .= "**3. Exclusive Promotion at Prague Showroom**\n\n";
$obsahEN .= "From tomorrow we are launching a special promotion on selected models in our Prague showroom Pasáž Lucerna. Get up to 25% discount on models from previous collections and free consultation with our designers. The promotion lasts only this week.\n\n";
$obsahEN .= "[Book an appointment](https://www.natuzzi.cz/rezervace) | [Showroom address](https://goo.gl/maps/natuzzi-praha)\n\n";

$obsahEN .= "**4. New Trends in Home Design 2025**\n\n";
$obsahEN .= "According to the latest Natuzzi Design Institute survey, the main trends for 2025 are: earthy tones, modular furniture and multifunctional spaces. Natuzzi brings solutions that perfectly match these trends.\n\n";
$obsahEN .= "[2025 Trends Guide](https://www.natuzzi.com/trends-2025)\n\n";

$obsahEN .= "## Luxury Furniture Care\n\n";

$obsahEN .= "**Winter Care for Leather Sofas - Complete Guide**\n\n";
$obsahEN .= "Winter places increased demands on leather furniture. Low air humidity caused by heating can lead to leather drying. We recommend regular treatment with special Natuzzi Leather Care balms every 2-3 months. Use a humidifier to maintain optimal humidity of 40-60%. Avoid direct contact with radiators.\n\n";
$obsahEN .= "[Buy Natuzzi Leather Care](https://www.natuzzi.cz/pece) | [Care video tutorial](https://youtu.be/natuzzi-care)\n\n";

$obsahEN .= "**Cleaning Textile Upholstery - Professional Tips**\n\n";
$obsahEN .= "For textile upholstery, we recommend regular vacuuming with a soft attachment once a week. For stains, use only certified cleaning products suitable for the type of fabric. Natuzzi offers professional cleaning as part of White Glove Service care.\n\n";
$obsahEN .= "[Order WGS cleaning](https://www.wgs-service.cz/novareklamace.php)\n\n";

$obsahEN .= "## Natuzzi in Czech Republic\n\n";

$obsahEN .= "Visit our authorized showrooms: **Prague** (Pasáž Lucerna - Štěpánská 61, River Garden Karlín - Prvního pluku 621), **Brno** (Veveří 38). Complete range of luxury Italian furniture with expert advice from certified designers. Open Mon-Fri 9am-6pm, Sat 10am-4pm.\n\n";
$obsahEN .= "[More information](https://www.natuzzi.cz) | [Online catalog](https://www.natuzzi.cz/katalog) | [Contact](https://www.natuzzi.cz/kontakt)\n\n";

echo "   ✅ EN obsah vygenerován (" . strlen($obsahEN) . " znaků)\n";

// Italský obsah - KOMPLETNÍ překlad
$obsahIT = "# Notizie Quotidiane Natuzzi\n\n";
$obsahIT .= "**Data:** " . date('d.m.Y') . " | **Onomastico:** {$jmenoSvatku}\n\n";
$obsahIT .= "Benvenuti alle notizie di oggi sui mobili italiani di lusso Natuzzi. Vi portiamo le ultime tendenze, consigli per la cura e offerte esclusive dai nostri showroom.\n\n";

$obsahIT .= "![Natuzzi Sofa](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1200&h=400&fit=crop)\n\n";

$obsahIT .= "## Novità del Brand Natuzzi\n\n";

$obsahIT .= "**1. Nuova Collezione Natuzzi Editions 2025 - Design Italiano nelle Case Ceche**\n\n";
$obsahIT .= "Natuzzi presenta la rivoluzionaria collezione Editions 2025, che combina l'artigianato italiano tradizionale con materiali moderni. La collezione include sistemi di seduta Re-vive che offrono un comfort perfetto grazie a un innovativo sistema di reclinazione. Ogni pezzo è realizzato a mano in Italia con materiali premium.\n\n";
$obsahIT .= "[Visualizza la collezione completa](https://www.natuzzi.com/cz/editions-2025) | [Ordina il catalogo](https://www.natuzzi.cz/katalog)\n\n";

$obsahIT .= "**2. Sostenibilità al Centro dell'Attenzione**\n\n";
$obsahIT .= "Natuzzi continua il suo impegno per la sostenibilità. Tutte le pelli provengono da fonti controllate e sono lavorate con metodi ecologici. La nuova collezione utilizza legno certificato FSC e materiali riciclabili. Natuzzi ha ottenuto la certificazione ISO 14001 per la gestione ambientale.\n\n";
$obsahIT .= "[Maggiori informazioni sulla sostenibilità](https://www.natuzzi.com/sustainability)\n\n";

$obsahIT .= "**3. Promozione Esclusiva nello Showroom di Praga**\n\n";
$obsahIT .= "Da domani lanciamo una promozione speciale su modelli selezionati nel nostro showroom di Praga Pasáž Lucerna. Ottenete fino al 25% di sconto sui modelli delle collezioni precedenti e consulenza gratuita con i nostri designer. La promozione dura solo questa settimana.\n\n";
$obsahIT .= "[Prenota un appuntamento](https://www.natuzzi.cz/rezervace) | [Indirizzo dello showroom](https://goo.gl/maps/natuzzi-praha)\n\n";

$obsahIT .= "**4. Nuove Tendenze nel Design Domestico 2025**\n\n";
$obsahIT .= "Secondo l'ultimo sondaggio del Natuzzi Design Institute, le principali tendenze per il 2025 sono: toni terrosi, mobili modulari e spazi multifunzionali. Natuzzi porta soluzioni che si adattano perfettamente a queste tendenze.\n\n";
$obsahIT .= "[Guida alle Tendenze 2025](https://www.natuzzi.com/trends-2025)\n\n";

$obsahIT .= "## Cura dei Mobili di Lusso\n\n";

$obsahIT .= "**Cura Invernale dei Divani in Pelle - Guida Completa**\n\n";
$obsahIT .= "L'inverno pone maggiori esigenze sui mobili in pelle. La bassa umidità dell'aria causata dal riscaldamento può portare all'essiccazione della pelle. Consigliamo un trattamento regolare con balsami speciali Natuzzi Leather Care ogni 2-3 mesi. Utilizzate un umidificatore per mantenere un'umidità ottimale del 40-60%. Evitate il contatto diretto con i radiatori.\n\n";
$obsahIT .= "[Acquista Natuzzi Leather Care](https://www.natuzzi.cz/pece) | [Video tutorial sulla cura](https://youtu.be/natuzzi-care)\n\n";

$obsahIT .= "**Pulizia dei Rivestimenti Tessili - Consigli Professionali**\n\n";
$obsahIT .= "Per i rivestimenti tessili, consigliamo l'aspirazione regolare con un accessorio morbido una volta alla settimana. Per le macchie, utilizzate solo prodotti detergenti certificati adatti al tipo di tessuto. Natuzzi offre la pulizia professionale nell'ambito del servizio White Glove Service.\n\n";
$obsahIT .= "[Ordina la pulizia WGS](https://www.wgs-service.cz/novareklamace.php)\n\n";

$obsahIT .= "## Natuzzi nella Repubblica Ceca\n\n";

$obsahIT .= "Visitate i nostri showroom autorizzati: **Praga** (Pasáž Lucerna - Štěpánská 61, River Garden Karlín - Prvního pluku 621), **Brno** (Veveří 38). Gamma completa di mobili italiani di lusso con consulenza esperta di designer certificati. Aperto Lun-Ven 9-18, Sab 10-16.\n\n";
$obsahIT .= "[Maggiori informazioni](https://www.natuzzi.cz) | [Catalogo online](https://www.natuzzi.cz/katalog) | [Contatto](https://www.natuzzi.cz/kontakt)\n\n";

echo "   ✅ IT obsah vygenerován (" . strlen($obsahIT) . " znaků)\n\n";

echo "6. Ukládání do databáze...\n";
try {
    $stmt = $pdo->prepare("
        INSERT INTO wgs_natuzzi_aktuality
        (datum, svatek_cz, komentar_dne, obsah_cz, obsah_en, obsah_it, zdroje_json, vygenerovano_ai)
        VALUES
        (:datum, :svatek, :komentar, :obsah_cz, :obsah_en, :obsah_it, :zdroje, TRUE)
    ");

    $zdroje = json_encode([
        'svatek_source' => 'statický kalendář',
        'natuzzi_sources' => ['https://www.natuzzi.com'],
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

    echo "   ✅ Záznam uložen do databáze\n";
    echo "   ID nového záznamu: " . $pdo->lastInsertId() . "\n\n";

} catch (Exception $e) {
    echo "   ❌ CHYBA při ukládání: " . $e->getMessage() . "\n\n";
    die();
}

echo "7. Ověření...\n";
try {
    $stmtVerify = $pdo->prepare("SELECT * FROM wgs_natuzzi_aktuality WHERE datum = :datum");
    $stmtVerify->execute(['datum' => $dnes]);
    $verify = $stmtVerify->fetch(PDO::FETCH_ASSOC);

    if ($verify) {
        echo "   ✅ Záznam ověřen v databázi\n";
        echo "   Datum: " . $verify['datum'] . "\n";
        echo "   Svátek: " . $verify['svatek_cz'] . "\n";
        echo "   CZ délka: " . strlen($verify['obsah_cz']) . " znaků\n";
        echo "   EN délka: " . strlen($verify['obsah_en']) . " znaků\n";
        echo "   IT délka: " . strlen($verify['obsah_it']) . " znaků\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ CHYBA při ověření: " . $e->getMessage() . "\n\n";
}

echo "✅ HOTOVO!\n\n";
echo "Nyní můžete zobrazit aktualitu na:\n";
echo "https://www.wgs-service.cz/aktuality.php\n";

echo "</pre>";
?>
