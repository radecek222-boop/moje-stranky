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
echo "   Svátek: {$jmenoSvatku}\n\n";

// === DATABÁZE ČLÁNKŮ (8 článků pro rotaci) ===
$databazeClanku = [
    // Článek 1
    [
        'cz' => [
            'titulek' => 'Nová kolekce Natuzzi Editions 2025 - Italský design v českých domovech',
            'text' => 'Natuzzi představuje revoluční kolekci Editions 2025, která kombinuje tradiční italské řemeslo s moderními materiály. Kolekce zahrnuje sedací soupravy Re-vive, které nabízejí dokonalý komfort díky inovativnímu systému polohování. Každý kus je ručně vyráběn v Itálii z prémiových materiálů.',
            'odkaz' => 'https://www.natuzzi.com/cz/editions-2025'
        ],
        'en' => [
            'titulek' => 'New Natuzzi Editions 2025 Collection - Italian Design in Czech Homes',
            'text' => 'Natuzzi presents the revolutionary Editions 2025 collection, which combines traditional Italian craftsmanship with modern materials. The collection includes Re-vive seating systems that offer perfect comfort thanks to an innovative reclining system. Each piece is handmade in Italy from premium materials.',
            'odkaz' => 'https://www.natuzzi.com/cz/editions-2025'
        ],
        'it' => [
            'titulek' => 'Nuova Collezione Natuzzi Editions 2025 - Design Italiano nelle Case Ceche',
            'text' => 'Natuzzi presenta la rivoluzionaria collezione Editions 2025, che combina l\'artigianato italiano tradizionale con materiali moderni. La collezione include sistemi di seduta Re-vive che offrono un comfort perfetto grazie a un innovativo sistema di reclinazione. Ogni pezzo è realizzato a mano in Italia con materiali premium.',
            'odkaz' => 'https://www.natuzzi.com/cz/editions-2025'
        ]
    ],
    // Článek 2
    [
        'cz' => [
            'titulek' => 'Udržitelnost v centru pozornosti',
            'text' => 'Natuzzi pokračuje ve svém závazku k udržitelnosti. Všechny kůže pocházejí z kontrolovaných zdrojů a zpracovávají se ekologickými metodami. Nová kolekce používá FSC certifikované dřevo a recyklovatelné materiály. Značka Natuzzi získala certifikaci ISO 14001 pro environmentální management.',
            'odkaz' => 'https://www.natuzzi.com/sustainability'
        ],
        'en' => [
            'titulek' => 'Sustainability in Focus',
            'text' => 'Natuzzi continues its commitment to sustainability. All leathers come from controlled sources and are processed using ecological methods. The new collection uses FSC-certified wood and recyclable materials. Natuzzi has received ISO 14001 certification for environmental management.',
            'odkaz' => 'https://www.natuzzi.com/sustainability'
        ],
        'it' => [
            'titulek' => 'Sostenibilità al Centro dell\'Attenzione',
            'text' => 'Natuzzi continua il suo impegno per la sostenibilità. Tutte le pelli provengono da fonti controllate e sono lavorate con metodi ecologici. La nuova collezione utilizza legno certificato FSC e materiali riciclabili. Natuzzi ha ottenuto la certificazione ISO 14001 per la gestione ambientale.',
            'odkaz' => 'https://www.natuzzi.com/sustainability'
        ]
    ],
    // Článek 3
    [
        'cz' => [
            'titulek' => 'Exkluzivní akce v pražském showroomu',
            'text' => 'Od zítřka spouštíme speciální akci na vybrané modely v našem pražském showroomu Pasáž Lucerna. Získejte slevu až 25% na modely z předchozích kolekcí a poradenství našich designérů zdarma. Akce trvá pouze tento týden.',
            'odkaz' => 'https://www.natuzzi.cz/rezervace'
        ],
        'en' => [
            'titulek' => 'Exclusive Promotion at Prague Showroom',
            'text' => 'From tomorrow we are launching a special promotion on selected models in our Prague showroom Pasáž Lucerna. Get up to 25% discount on models from previous collections and free consultation with our designers. The promotion lasts only this week.',
            'odkaz' => 'https://www.natuzzi.cz/rezervace'
        ],
        'it' => [
            'titulek' => 'Promozione Esclusiva nello Showroom di Praga',
            'text' => 'Da domani lanciamo una promozione speciale su modelli selezionati nel nostro showroom di Praga Pasáž Lucerna. Ottenete fino al 25% di sconto sui modelli delle collezioni precedenti e consulenza gratuita con i nostri designer. La promozione dura solo questa settimana.',
            'odkaz' => 'https://www.natuzzi.cz/rezervace'
        ]
    ],
    // Článek 4
    [
        'cz' => [
            'titulek' => 'Nové trendy v bytovém designu 2025',
            'text' => 'Podle nejnovějšího průzkumu Natuzzi Design Institute jsou hlavními trendy pro rok 2025: zemité tóny, modulární nábytek a multifunkční prostory. Natuzzi přináší řešení, která dokonale odpovídají těmto trendům.',
            'odkaz' => 'https://www.natuzzi.com/trends-2025'
        ],
        'en' => [
            'titulek' => 'New Trends in Home Design 2025',
            'text' => 'According to the latest Natuzzi Design Institute survey, the main trends for 2025 are: earthy tones, modular furniture and multifunctional spaces. Natuzzi brings solutions that perfectly match these trends.',
            'odkaz' => 'https://www.natuzzi.com/trends-2025'
        ],
        'it' => [
            'titulek' => 'Nuove Tendenze nel Design Domestico 2025',
            'text' => 'Secondo l\'ultimo sondaggio del Natuzzi Design Institute, le principali tendenze per il 2025 sono: toni terrosi, mobili modulari e spazi multifunzionali. Natuzzi porta soluzioni che si adattano perfettamente a queste tendenze.',
            'odkaz' => 'https://www.natuzzi.com/trends-2025'
        ]
    ],
    // Článek 5
    [
        'cz' => [
            'titulek' => 'Re-vive systém - Revoluce v pohodlí',
            'text' => 'Inovativní systém Re-vive od Natuzzi nabízí neomezené možnosti polohování. Elektrické ovládání umožňuje najít perfektní pozici pro relaxaci, čtení nebo sledování televize. Každá sedačka je vybavena USB porty pro nabíjení zařízení.',
            'odkaz' => 'https://www.natuzzi.com/revive'
        ],
        'en' => [
            'titulek' => 'Re-vive System - Revolution in Comfort',
            'text' => 'The innovative Re-vive system from Natuzzi offers unlimited positioning options. Electric controls allow you to find the perfect position for relaxation, reading or watching TV. Each sofa is equipped with USB ports for charging devices.',
            'odkaz' => 'https://www.natuzzi.com/revive'
        ],
        'it' => [
            'titulek' => 'Sistema Re-vive - Rivoluzione nel Comfort',
            'text' => 'L\'innovativo sistema Re-vive di Natuzzi offre opzioni di posizionamento illimitate. I comandi elettrici consentono di trovare la posizione perfetta per il relax, la lettura o la visione della TV. Ogni divano è dotato di porte USB per la ricarica dei dispositivi.',
            'odkaz' => 'https://www.natuzzi.com/revive'
        ]
    ],
    // Článek 6
    [
        'cz' => [
            'titulek' => 'Italské kůže nejvyšší kvality',
            'text' => 'Natuzzi používá pouze prémiové italské kůže vybrané z nejlepších světových zdrojů. Každá kůže prochází 21 kroky zpracování s důrazem na ekologické metody. Výsledkem je materiál, který je měkký, prodyšný a dlouhodobě krásný.',
            'odkaz' => 'https://www.natuzzi.com/leather'
        ],
        'en' => [
            'titulek' => 'Italian Leather of the Highest Quality',
            'text' => 'Natuzzi uses only premium Italian leathers selected from the best sources worldwide. Each leather undergoes 21 processing steps with an emphasis on ecological methods. The result is a material that is soft, breathable and beautiful long-term.',
            'odkaz' => 'https://www.natuzzi.com/leather'
        ],
        'it' => [
            'titulek' => 'Pelli Italiane della Massima Qualità',
            'text' => 'Natuzzi utilizza solo pelli italiane premium selezionate dalle migliori fonti mondiali. Ogni pelle subisce 21 fasi di lavorazione con enfasi sui metodi ecologici. Il risultato è un materiale morbido, traspirante e bello a lungo termine.',
            'odkaz' => 'https://www.natuzzi.com/leather'
        ]
    ],
    // Článek 7
    [
        'cz' => [
            'titulek' => 'Modulární nábytek pro moderní život',
            'text' => 'Modulární sedací soupravy Natuzzi se přizpůsobí vašim potřebám. Můžete je rozšiřovat, měnit konfiguraci nebo přemisťovat podle aktuálních požadavků. Perfektní řešení pro dynamický moderní život.',
            'odkaz' => 'https://www.natuzzi.com/modular'
        ],
        'en' => [
            'titulek' => 'Modular Furniture for Modern Life',
            'text' => 'Natuzzi modular seating systems adapt to your needs. You can expand them, change the configuration or move them according to current requirements. Perfect solution for dynamic modern life.',
            'odkaz' => 'https://www.natuzzi.com/modular'
        ],
        'it' => [
            'titulek' => 'Mobili Modulari per la Vita Moderna',
            'text' => 'I sistemi di seduta modulari Natuzzi si adattano alle tue esigenze. Puoi espanderli, cambiare la configurazione o spostarli secondo le esigenze attuali. Soluzione perfetta per la vita moderna dinamica.',
            'odkaz' => 'https://www.natuzzi.com/modular'
        ]
    ],
    // Článek 8
    [
        'cz' => [
            'titulek' => '60 let italského designu a řemesla',
            'text' => 'Od roku 1959 Natuzzi představuje vrchol italského designu. Značka spojuje tradiční řemeslné techniky s moderními technologiemi a inovacemi. Dnes je Natuzzi přítomna v 123 zemích světa a zůstává symbolem luxusu a kvality.',
            'odkaz' => 'https://www.natuzzi.com/history'
        ],
        'en' => [
            'titulek' => '60 Years of Italian Design and Craftsmanship',
            'text' => 'Since 1959, Natuzzi has represented the pinnacle of Italian design. The brand combines traditional craft techniques with modern technologies and innovations. Today Natuzzi is present in 123 countries worldwide and remains a symbol of luxury and quality.',
            'odkaz' => 'https://www.natuzzi.com/history'
        ],
        'it' => [
            'titulek' => '60 Anni di Design e Artigianato Italiano',
            'text' => 'Dal 1959 Natuzzi rappresenta il vertice del design italiano. Il marchio combina tecniche artigianali tradizionali con tecnologie e innovazioni moderne. Oggi Natuzzi è presente in 123 paesi in tutto il mondo e rimane un simbolo di lusso e qualità.',
            'odkaz' => 'https://www.natuzzi.com/history'
        ]
    ]
];

// === DATABÁZE TIPŮ NA PÉČI (6 tipů pro rotaci) ===
$databazeTipu = [
    // Tip 1
    [
        'cz' => [
            'nadpis' => 'Zimní péče o kožené sedačky - kompletní průvodce',
            'text' => 'Zimní období klade na kožený nábytek zvýšené nároky. Nízká vlhkost vzduchu způsobená topením může vést k vysychání kůže. Doporučujeme pravidelné ošetřování speciálními balzámy Natuzzi Leather Care každé 2-3 měsíce. Používejte zvlhčovač vzduchu pro udržení optimální vlhkosti 40-60%. Vyvarujte se přímého kontaktu s radiátory.',
            'odkaz' => 'https://www.natuzzi.cz/pece'
        ],
        'en' => [
            'nadpis' => 'Winter Care for Leather Sofas - Complete Guide',
            'text' => 'Winter places increased demands on leather furniture. Low air humidity caused by heating can lead to leather drying. We recommend regular treatment with special Natuzzi Leather Care balms every 2-3 months. Use a humidifier to maintain optimal humidity of 40-60%. Avoid direct contact with radiators.',
            'odkaz' => 'https://www.natuzzi.cz/pece'
        ],
        'it' => [
            'nadpis' => 'Cura Invernale dei Divani in Pelle - Guida Completa',
            'text' => 'L\'inverno pone maggiori esigenze sui mobili in pelle. La bassa umidità dell\'aria causata dal riscaldamento può portare all\'essiccazione della pelle. Consigliamo un trattamento regolare con balsami speciali Natuzzi Leather Care ogni 2-3 mesi. Utilizzate un umidificatore per mantenere un\'umidità ottimale del 40-60%. Evitate il contatto diretto con i radiatori.',
            'odkaz' => 'https://www.natuzzi.cz/pece'
        ]
    ],
    // Tip 2
    [
        'cz' => [
            'nadpis' => 'Čištění textilních potahů - tipy od profesionálů',
            'text' => 'Pro textilní potahy doporučujeme pravidelné vysávání měkkým nástavcem jednou týdně. Na skvrny použijte pouze certifikované čistící prostředky vhodné pro daný typ látky. Natuzzi nabízí profesionální čištění v rámci servisní péče White Glove Service.',
            'odkaz' => 'https://www.wgs-service.cz/novareklamace.php'
        ],
        'en' => [
            'nadpis' => 'Cleaning Textile Upholstery - Professional Tips',
            'text' => 'For textile upholstery, we recommend regular vacuuming with a soft attachment once a week. For stains, use only certified cleaning products suitable for the type of fabric. Natuzzi offers professional cleaning as part of White Glove Service care.',
            'odkaz' => 'https://www.wgs-service.cz/novareklamace.php'
        ],
        'it' => [
            'nadpis' => 'Pulizia dei Rivestimenti Tessili - Consigli Professionali',
            'text' => 'Per i rivestimenti tessili, consigliamo l\'aspirazione regolare con un accessorio morbido una volta alla settimana. Per le macchie, utilizzate solo prodotti detergenti certificati adatti al tipo di tessuto. Natuzzi offre la pulizia professionale nell\'ambito del servizio White Glove Service.',
            'odkaz' => 'https://www.wgs-service.cz/novareklamace.php'
        ]
    ],
    // Tip 3
    [
        'cz' => [
            'nadpis' => 'Odstranění skvrn - první pomoc pro váš nábytek',
            'text' => 'V případě skvrny okamžitě osušte přebytečnou tekutinu čistým savým hadříkem. Nikdy nereagujte vodou nebo domácími prostředky. Pro každý typ materiálu máme speciální čistící sadu. Kontaktujte naši servisní linku pro bezplatnou konzultaci.',
            'odkaz' => 'https://www.natuzzi.cz/kontakt'
        ],
        'en' => [
            'nadpis' => 'Stain Removal - First Aid for Your Furniture',
            'text' => 'In case of a stain, immediately dry the excess liquid with a clean absorbent cloth. Never use water or household products. We have a special cleaning kit for each type of material. Contact our service line for free consultation.',
            'odkaz' => 'https://www.natuzzi.cz/kontakt'
        ],
        'it' => [
            'nadpis' => 'Rimozione delle Macchie - Primo Soccorso per i Tuoi Mobili',
            'text' => 'In caso di macchia, asciugare immediatamente il liquido in eccesso con un panno pulito assorbente. Non usare mai acqua o prodotti casalinghi. Abbiamo un kit di pulizia speciale per ogni tipo di materiale. Contatta la nostra linea di servizio per una consulenza gratuita.',
            'odkaz' => 'https://www.natuzzi.cz/kontakt'
        ]
    ],
    // Tip 4
    [
        'cz' => [
            'nadpis' => 'Letní péče - ochrana před sluncem a teplem',
            'text' => 'V letních měsících chraňte nábytek před přímým slunečním zářením, které může vyblednutí barvy a vysušení materiálu. Používejte závěsy nebo žaluzie. Pravidelně větrané interiéry pomáhají předcházet přehřátí a udržují materiály v optimálním stavu.',
            'odkaz' => 'https://www.natuzzi.com/care'
        ],
        'en' => [
            'nadpis' => 'Summer Care - Protection from Sun and Heat',
            'text' => 'In summer months, protect furniture from direct sunlight, which can cause color fading and material drying. Use curtains or blinds. Regularly ventilated interiors help prevent overheating and keep materials in optimal condition.',
            'odkaz' => 'https://www.natuzzi.com/care'
        ],
        'it' => [
            'nadpis' => 'Cura Estiva - Protezione da Sole e Calore',
            'text' => 'Nei mesi estivi, proteggete i mobili dalla luce solare diretta, che può causare lo sbiadimento del colore e l\'essiccazione del materiale. Utilizzate tende o persiane. Gli interni regolarmente ventilati aiutano a prevenire il surriscaldamento e mantengono i materiali in condizioni ottimali.',
            'odkaz' => 'https://www.natuzzi.com/care'
        ]
    ],
    // Tip 5
    [
        'cz' => [
            'nadpis' => 'Údržba mechanismů - prodloužení životnosti',
            'text' => 'Pohyblivé části sedacího nábytku vyžadují pravidelnou údržbu. Doporučujeme mazání kloubů silikónovým sprejem každých 6 měsíců. Kontrolujte dotažení šroubů a stability konstrukce. Náš technický servis provádí preventivní prohlídky zdarma.',
            'odkaz' => 'https://www.wgs-service.cz'
        ],
        'en' => [
            'nadpis' => 'Mechanism Maintenance - Extending Lifespan',
            'text' => 'Moving parts of seating furniture require regular maintenance. We recommend lubricating joints with silicone spray every 6 months. Check screw tightness and structural stability. Our technical service performs preventive inspections free of charge.',
            'odkaz' => 'https://www.wgs-service.cz'
        ],
        'it' => [
            'nadpis' => 'Manutenzione dei Meccanismi - Prolungare la Durata',
            'text' => 'Le parti mobili dei mobili imbottiti richiedono una manutenzione regolare. Consigliamo di lubrificare le articolazioni con spray al silicone ogni 6 mesi. Controllare il serraggio delle viti e la stabilità della struttura. Il nostro servizio tecnico esegue ispezioni preventive gratuitamente.',
            'odkaz' => 'https://www.wgs-service.cz'
        ]
    ],
    // Tip 6
    [
        'cz' => [
            'nadpis' => 'Denní péče - jednoduché návyky pro dlouhověkost',
            'text' => 'Otáčejte polštáře jednou týdně pro rovnoměrné opotřebení. Pravidelně odstraňujte prach měkkým hadříkem. Vyhněte se ostrým předmětům a mazlíčkům s drápy na povrchu nábytku. Malá každodenní péče prodlouží životnost o mnoho let.',
            'odkaz' => 'https://www.natuzzi.com/tips'
        ],
        'en' => [
            'nadpis' => 'Daily Care - Simple Habits for Longevity',
            'text' => 'Rotate cushions once a week for even wear. Regularly remove dust with a soft cloth. Avoid sharp objects and pets with claws on furniture surfaces. Small daily care extends lifespan by many years.',
            'odkaz' => 'https://www.natuzzi.com/tips'
        ],
        'it' => [
            'nadpis' => 'Cura Quotidiana - Abitudini Semplici per la Longevità',
            'text' => 'Ruotare i cuscini una volta alla settimana per un\'usura uniforme. Rimuovere regolarmente la polvere con un panno morbido. Evitare oggetti affilati e animali domestici con artigli sulle superfici dei mobili. Una piccola cura quotidiana prolunga la durata di molti anni.',
            'odkaz' => 'https://www.natuzzi.com/tips'
        ]
    ]
];

// === NÁHODNÝ VÝBĚR ČLÁNKŮ A TIPŮ ===
echo "   📊 Náhodný výběr obsahu pro SEO optimalizaci...\n";

// Vybrat 4 náhodné články z 8
$indexyClanku = array_rand($databazeClanku, 4);
if (!is_array($indexyClanku)) {
    $indexyClanku = [$indexyClanku];
}

// Vybrat 2 náhodné tipy z 6
$indexyTipu = array_rand($databazeTipu, 2);
if (!is_array($indexyTipu)) {
    $indexyTipu = [$indexyTipu];
}

echo "   ✅ Vybrány články: " . implode(', ', array_map(function($i) { return $i + 1; }, $indexyClanku)) . "\n";
echo "   ✅ Vybrány tipy: " . implode(', ', array_map(function($i) { return $i + 1; }, $indexyTipu)) . "\n\n";

// === SESTAVENÍ OBSAHU V ČEŠTINĚ ===
$obsahCZ = "# Denní aktuality Natuzzi\n\n";
$obsahCZ .= "**Datum:** " . date('d.m.Y') . " | **Svátek má:** {$jmenoSvatku}\n\n";
$obsahCZ .= "Vítejte u dnešních aktualit o luxusním italském nábytku Natuzzi. Přinášíme vám nejnovější trendy, tipy na péči a exkluzivní nabídky z našich showroomů.\n\n";

$obsahCZ .= "![Natuzzi Sofa](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1200&h=400&fit=crop)\n\n";

$obsahCZ .= "## Novinky o značce Natuzzi\n\n";

foreach ($indexyClanku as $poradi => $index) {
    $clanek = $databazeClanku[$index]['cz'];
    $cislo = $poradi + 1;
    $obsahCZ .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahCZ .= "{$clanek['text']}\n\n";
    $obsahCZ .= "[Více informací]({$clanek['odkaz']})\n\n";
}

$obsahCZ .= "## Péče o luxusní nábytek\n\n";

foreach ($indexyTipu as $index) {
    $tip = $databazeTipu[$index]['cz'];
    $obsahCZ .= "**{$tip['nadpis']}**\n\n";
    $obsahCZ .= "{$tip['text']}\n\n";
    $obsahCZ .= "[Více tipů]({$tip['odkaz']})\n\n";
}

$obsahCZ .= "## Natuzzi v České republice\n\n";
$obsahCZ .= "Navštivte naše autorizované showroomy: **Praha** (Pasáž Lucerna - Štěpánská 61, River Garden Karlín - Prvního pluku 621), **Brno** (Veveří 38). Kompletní sortiment luxusního italského nábytku s odborným poradenstvím certifikovaných designérů. Otevřeno Po-Pá 9-18h, So 10-16h.\n\n";
$obsahCZ .= "[Více informací](https://www.natuzzi.cz) | [Online katalog](https://www.natuzzi.cz/katalog) | [Kontakt](https://www.natuzzi.cz/kontakt)\n\n";

echo "   ✅ CZ obsah vygenerován (" . strlen($obsahCZ) . " znaků)\n";

// === SESTAVENÍ OBSAHU V ANGLIČTINĚ ===
$obsahEN = "# Natuzzi Daily News\n\n";
$obsahEN .= "**Date:** " . date('m/d/Y') . " | **Name Day:** {$jmenoSvatku}\n\n";
$obsahEN .= "Welcome to today's news about luxury Italian furniture Natuzzi. We bring you the latest trends, care tips and exclusive offers from our showrooms.\n\n";

$obsahEN .= "![Natuzzi Sofa](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1200&h=400&fit=crop)\n\n";

$obsahEN .= "## Natuzzi Brand News\n\n";

foreach ($indexyClanku as $poradi => $index) {
    $clanek = $databazeClanku[$index]['en'];
    $cislo = $poradi + 1;
    $obsahEN .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahEN .= "{$clanek['text']}\n\n";
    $obsahEN .= "[More information]({$clanek['odkaz']})\n\n";
}

$obsahEN .= "## Luxury Furniture Care\n\n";

foreach ($indexyTipu as $index) {
    $tip = $databazeTipu[$index]['en'];
    $obsahEN .= "**{$tip['nadpis']}**\n\n";
    $obsahEN .= "{$tip['text']}\n\n";
    $obsahEN .= "[More tips]({$tip['odkaz']})\n\n";
}

$obsahEN .= "## Natuzzi in Czech Republic\n\n";
$obsahEN .= "Visit our authorized showrooms: **Prague** (Pasáž Lucerna - Štěpánská 61, River Garden Karlín - Prvního pluku 621), **Brno** (Veveří 38). Complete range of luxury Italian furniture with expert advice from certified designers. Open Mon-Fri 9am-6pm, Sat 10am-4pm.\n\n";
$obsahEN .= "[More information](https://www.natuzzi.cz) | [Online catalog](https://www.natuzzi.cz/katalog) | [Contact](https://www.natuzzi.cz/kontakt)\n\n";

echo "   ✅ EN obsah vygenerován (" . strlen($obsahEN) . " znaků)\n";

// === SESTAVENÍ OBSAHU V ITALŠTINĚ ===
$obsahIT = "# Notizie Quotidiane Natuzzi\n\n";
$obsahIT .= "**Data:** " . date('d.m.Y') . " | **Onomastico:** {$jmenoSvatku}\n\n";
$obsahIT .= "Benvenuti alle notizie di oggi sui mobili italiani di lusso Natuzzi. Vi portiamo le ultime tendenze, consigli per la cura e offerte esclusive dai nostri showroom.\n\n";

$obsahIT .= "![Natuzzi Sofa](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=1200&h=400&fit=crop)\n\n";

$obsahIT .= "## Novità del Brand Natuzzi\n\n";

foreach ($indexyClanku as $poradi => $index) {
    $clanek = $databazeClanku[$index]['it'];
    $cislo = $poradi + 1;
    $obsahIT .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahIT .= "{$clanek['text']}\n\n";
    $obsahIT .= "[Maggiori informazioni]({$clanek['odkaz']})\n\n";
}

$obsahIT .= "## Cura dei Mobili di Lusso\n\n";

foreach ($indexyTipu as $index) {
    $tip = $databazeTipu[$index]['it'];
    $obsahIT .= "**{$tip['nadpis']}**\n\n";
    $obsahIT .= "{$tip['text']}\n\n";
    $obsahIT .= "[Altri consigli]({$tip['odkaz']})\n\n";
}

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
