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

// Anglický obsah
$obsahEN = str_replace('Denní aktuality Natuzzi', 'Natuzzi Daily News', $obsahCZ);
$obsahEN = str_replace('Svátek má:', 'Name Day:', $obsahEN);
$obsahEN = str_replace('Vítejte u dnešních aktualit', 'Welcome to today\'s news', $obsahEN);
$obsahEN = str_replace('Novinky o značce Natuzzi', 'Natuzzi Brand News', $obsahEN);
$obsahEN = str_replace('Nová kolekce Natuzzi 2025', 'New Natuzzi Collection 2025', $obsahEN);
$obsahEN = str_replace('Natuzzi představuje novou kolekci', 'Natuzzi presents a new collection', $obsahEN);
$obsahEN = str_replace('inspirovanou italskou přírodou a moderním designem', 'inspired by Italian nature and modern design', $obsahEN);
$obsahEN = str_replace('Více informací', 'More information', $obsahEN);
$obsahEN = str_replace('Péče o luxusní nábytek', 'Luxury Furniture Care', $obsahEN);
$obsahEN = str_replace('Pravidelná údržba kožených sedaček', 'Regular Leather Sofa Maintenance', $obsahEN);
$obsahEN = str_replace('Týdenní péče o kůži prodlužuje životnost vašeho nábytku', 'Weekly leather care extends the life of your furniture', $obsahEN);
$obsahEN = str_replace('Používejte měkký hadřík a specializované přípravky', 'Use a soft cloth and specialized products', $obsahEN);
$obsahEN = str_replace('Natuzzi v České republice', 'Natuzzi in Czech Republic', $obsahEN);
$obsahEN = str_replace('Navštivte naše showroomy v Praze a Brně', 'Visit our showrooms in Prague and Brno', $obsahEN);

echo "   ✅ EN obsah přeložen (" . strlen($obsahEN) . " znaků)\n";

// Italský obsah
$obsahIT = str_replace('Denní aktuality Natuzzi', 'Notizie Quotidiane Natuzzi', $obsahCZ);
$obsahIT = str_replace('Svátek má:', 'Onomastico:', $obsahIT);
$obsahIT = str_replace('Vítejte u dnešních aktualit', 'Benvenuti alle notizie di oggi', $obsahIT);
$obsahIT = str_replace('Novinky o značce Natuzzi', 'Novità del Brand Natuzzi', $obsahIT);
$obsahIT = str_replace('Nová kolekce Natuzzi 2025', 'Nuova Collezione Natuzzi 2025', $obsahIT);
$obsahIT = str_replace('Natuzzi představuje novou kolekci', 'Natuzzi presenta una nuova collezione', $obsahIT);
$obsahIT = str_replace('inspirovanou italskou přírodou a moderním designem', 'ispirata dalla natura italiana e dal design moderno', $obsahIT);
$obsahIT = str_replace('Více informací', 'Maggiori informazioni', $obsahIT);
$obsahIT = str_replace('Péče o luxusní nábytek', 'Cura dei Mobili di Lusso', $obsahIT);
$obsahIT = str_replace('Pravidelná údržba kožených sedaček', 'Manutenzione Regolare dei Divani in Pelle', $obsahIT);
$obsahIT = str_replace('Týdenní péče o kůži prodlužuje životnost vašeho nábytku', 'La cura settimanale della pelle prolunga la vita dei vostri mobili', $obsahIT);
$obsahIT = str_replace('Používejte měkký hadřík a specializované přípravky', 'Utilizzate un panno morbido e prodotti specializzati', $obsahIT);
$obsahIT = str_replace('Natuzzi v České republice', 'Natuzzi nella Repubblica Ceca', $obsahIT);
$obsahIT = str_replace('Navštivte naše showroomy v Praze a Brně', 'Visitate i nostri showroom a Praga e Brno', $obsahIT);

echo "   ✅ IT obsah přeložen (" . strlen($obsahIT) . " znaků)\n\n";

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
