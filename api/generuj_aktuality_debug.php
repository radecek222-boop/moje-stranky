<?php
/**
 * DEBUG VERZE - Generátor aktualit s viditelným výstupem chyb
 */

// Zobrazit všechny chyby
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>DEBUG: Generování aktualit</h1>";
echo "<pre>";

echo "1. Načítání init.php...\n";
require_once __DIR__ . '/../init.php';
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
$obsahCZ .= "Vítejte u dnešních aktualit o luxusním italském nábytku Natuzzi.\n\n";
$obsahCZ .= "## 📰 Novinky o značce Natuzzi\n\n";
$obsahCZ .= "**1. Nová kolekce Natuzzi 2025**\n\n";
$obsahCZ .= "Natuzzi představuje novou kolekci inspirovanou italskou přírodou a moderním designem.\n\n";
$obsahCZ .= "[Více informací](https://www.natuzzi.com)\n\n";
$obsahCZ .= "## 🛠️ Péče o luxusní nábytek\n\n";
$obsahCZ .= "**Pravidelná údržba kožených sedaček**\n\n";
$obsahCZ .= "Týdenní péče o kůži prodlužuje životnost vašeho nábytku. Používejte měkký hadřík a specializované přípravky.\n\n";
$obsahCZ .= "## 🇨🇿 Natuzzi v České republice\n\n";
$obsahCZ .= "Navštivte naše showroomy v Praze a Brně. Více informací na [natuzzi.cz](https://www.natuzzi.cz/).\n\n";

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
