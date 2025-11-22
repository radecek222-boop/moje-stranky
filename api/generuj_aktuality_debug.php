<?php
/**
 * DEBUG VERZE - Generátor aktualit s viditelným výstupem chyb
 * POUZE PRO ADMINISTRÁTORY
 *
 * Struktura: 24 článků (8 Natuzzi Italia + 8 Natuzzi Editions + 8 Softaly)
 * Náhodný výběr: 6 článků (2 z každé kategorie)
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

// ====================================================================
// KATEGORIE 1: NATUZZI ITALIA (8 článků)
// Prémiová řada, showroom Praha Pasáž Lucerna & River Garden
// ====================================================================
$clankyNatuzziItalia = [
    // Článek 1 - Natuzzi Italia
    [
        'cz' => [
            'titulek' => 'Natuzzi Italia - Prémiový showroom v Pasáži Lucerna',
            'text' => 'Navštivte náš exkluzivní showroom Natuzzi Italia v srdci Prahy na adrese V Jámě 699/3, Pasáž Lucerna, Praha 1. Otevřeno Po-Pá 10:00-18:00, So 10:00-14:00. Prémiová kolekce italského nábytku s osobním poradenstvím certifikovaných designérů. Rezervace na telefonu 224 162 056-7 nebo emailem natuzzi@natuzzi.cz.',
            'odkaz' => 'https://www.natuzzi.cz/prodejny-praha1'
        ],
        'en' => [
            'titulek' => 'Natuzzi Italia - Premium Showroom in Pasáž Lucerna',
            'text' => 'Visit our exclusive Natuzzi Italia showroom in the heart of Prague at V Jámě 699/3, Pasáž Lucerna, Prague 1. Open Mon-Fri 10:00-18:00, Sat 10:00-14:00. Premium collection of Italian furniture with personal advice from certified designers. Book on 224 162 056-7 or email natuzzi@natuzzi.cz.',
            'odkaz' => 'https://www.natuzzi.cz/prodejny-praha1'
        ],
        'it' => [
            'titulek' => 'Natuzzi Italia - Showroom Premium in Pasáž Lucerna',
            'text' => 'Visitate il nostro showroom esclusivo Natuzzi Italia nel cuore di Praga all\'indirizzo V Jámě 699/3, Pasáž Lucerna, Praga 1. Aperto Lun-Ven 10:00-18:00, Sab 10:00-14:00. Collezione premium di mobili italiani con consulenza personale di designer certificati. Prenotazioni al 224 162 056-7 o email natuzzi@natuzzi.cz.',
            'odkaz' => 'https://www.natuzzi.cz/prodejny-praha1'
        ]
    ],
    // Článek 2 - Natuzzi Italia
    [
        'cz' => [
            'titulek' => 'River Garden Karlín - Moderní showroom Natuzzi Italia',
            'text' => 'Druhý pražský showroom Natuzzi Italia naleznete v moderním komplexu River Garden na adrese Rohanské nábřeží 678/25, Praha 8 - Karlín. Prostorný showroom s kompletní kolekcí prémiového italského nábytku. Parkování zdarma pro návštěvníky. Otevřeno Po-Pá 10:00-18:00, So 10:00-14:00.',
            'odkaz' => 'https://www.natuzzi.com/cz/en/stores/na-2000005236'
        ],
        'en' => [
            'titulek' => 'River Garden Karlín - Modern Natuzzi Italia Showroom',
            'text' => 'The second Prague showroom Natuzzi Italia is located in the modern River Garden complex at Rohanské nábřeží 678/25, Prague 8 - Karlín. Spacious showroom with complete collection of premium Italian furniture. Free parking for visitors. Open Mon-Fri 10:00-18:00, Sat 10:00-14:00.',
            'odkaz' => 'https://www.natuzzi.com/cz/en/stores/na-2000005236'
        ],
        'it' => [
            'titulek' => 'River Garden Karlín - Showroom Moderno Natuzzi Italia',
            'text' => 'Il secondo showroom di Praga Natuzzi Italia si trova nel moderno complesso River Garden all\'indirizzo Rohanské nábřeží 678/25, Praga 8 - Karlín. Showroom spazioso con collezione completa di mobili italiani premium. Parcheggio gratuito per i visitatori. Aperto Lun-Ven 10:00-18:00, Sab 10:00-14:00.',
            'odkaz' => 'https://www.natuzzi.com/cz/en/stores/na-2000005236'
        ]
    ],
    // Článek 3 - Natuzzi Italia
    [
        'cz' => [
            'titulek' => 'Italské kůže nejvyšší kvality - Natuzzi Italia',
            'text' => 'Natuzzi Italia používá pouze prémiové italské kůže vybrané z nejlepších světových zdrojů. Každá kůže prochází 21 kroky zpracování s důrazem na ekologické metody. Výsledkem je materiál měkký, prodyšný a dlouhodobě krásný. Získali jsme certifikaci ISO 14001 pro environmentální management.',
            'odkaz' => 'https://www.natuzzi.com/leather'
        ],
        'en' => [
            'titulek' => 'Italian Leather of the Highest Quality - Natuzzi Italia',
            'text' => 'Natuzzi Italia uses only premium Italian leathers selected from the best sources worldwide. Each leather undergoes 21 processing steps with emphasis on ecological methods. The result is a soft, breathable and long-lasting beautiful material. We have received ISO 14001 certification for environmental management.',
            'odkaz' => 'https://www.natuzzi.com/leather'
        ],
        'it' => [
            'titulek' => 'Pelli Italiane della Massima Qualità - Natuzzi Italia',
            'text' => 'Natuzzi Italia utilizza solo pelli italiane premium selezionate dalle migliori fonti mondiali. Ogni pelle subisce 21 fasi di lavorazione con enfasi sui metodi ecologici. Il risultato è un materiale morbido, traspirante e bello a lungo termine. Abbiamo ricevuto la certificazione ISO 14001 per la gestione ambientale.',
            'odkaz' => 'https://www.natuzzi.com/leather'
        ]
    ],
    // Článek 4 - Natuzzi Italia
    [
        'cz' => [
            'titulek' => '60 let italského designu a tradice - Natuzzi Italia',
            'text' => 'Od roku 1959 Natuzzi Italia představuje vrchol italského designu. Značka spojuje tradiční řemeslné techniky s moderními technologiemi a inovacemi. Dnes je Natuzzi přítomna v 123 zemích světa a zůstává symbolem luxusu, kvality a italského stylu života.',
            'odkaz' => 'https://www.natuzzi.com/history'
        ],
        'en' => [
            'titulek' => '60 Years of Italian Design and Tradition - Natuzzi Italia',
            'text' => 'Since 1959, Natuzzi Italia has represented the pinnacle of Italian design. The brand combines traditional craft techniques with modern technologies and innovations. Today Natuzzi is present in 123 countries worldwide and remains a symbol of luxury, quality and Italian lifestyle.',
            'odkaz' => 'https://www.natuzzi.com/history'
        ],
        'it' => [
            'titulek' => '60 Anni di Design e Tradizione Italiana - Natuzzi Italia',
            'text' => 'Dal 1959 Natuzzi Italia rappresenta il vertice del design italiano. Il marchio combina tecniche artigianali tradizionali con tecnologie e innovazioni moderne. Oggi Natuzzi è presente in 123 paesi in tutto il mondo e rimane un simbolo di lusso, qualità e stile di vita italiano.',
            'odkaz' => 'https://www.natuzzi.com/history'
        ]
    ],
    // Článek 5 - Natuzzi Italia
    [
        'cz' => [
            'titulek' => 'Udržitelnost v centru pozornosti - Natuzzi Italia',
            'text' => 'Natuzzi Italia pokračuje ve svém závazku k udržitelnosti. Všechny kůže pocházejí z kontrolovaných zdrojů a zpracovávají se ekologickými metodami. Nová kolekce používá FSC certifikované dřevo a recyklovatelné materiály. Jsme hrdí na naši certifikaci ISO 14001 pro environmentální management.',
            'odkaz' => 'https://www.natuzzi.com/sustainability'
        ],
        'en' => [
            'titulek' => 'Sustainability in Focus - Natuzzi Italia',
            'text' => 'Natuzzi Italia continues its commitment to sustainability. All leathers come from controlled sources and are processed using ecological methods. The new collection uses FSC-certified wood and recyclable materials. We are proud of our ISO 14001 certification for environmental management.',
            'odkaz' => 'https://www.natuzzi.com/sustainability'
        ],
        'it' => [
            'titulek' => 'Sostenibilità al Centro - Natuzzi Italia',
            'text' => 'Natuzzi Italia continua il suo impegno per la sostenibilità. Tutte le pelli provengono da fonti controllate e sono lavorate con metodi ecologici. La nuova collezione utilizza legno certificato FSC e materiali riciclabili. Siamo orgogliosi della nostra certificazione ISO 14001 per la gestione ambientale.',
            'odkaz' => 'https://www.natuzzi.com/sustainability'
        ]
    ],
    // Článek 6 - Natuzzi Italia
    [
        'cz' => [
            'titulek' => 'Luxusní kožené sedačky s životní zárukou',
            'text' => 'Natuzzi Italia nabízí na vybrané modely luxusních kožených sedaček mimořádnou životní záruku. Díky preciznímu ručnímu zpracování a použití nejkvalitnějších materiálů můžeme garantovat dlouhodobou dokonalost. Navštivte náš pražský showroom pro osobní prohlídku a konzultaci s designérem.',
            'odkaz' => 'https://www.natuzzi.cz/kontakt'
        ],
        'en' => [
            'titulek' => 'Luxury Leather Sofas with Lifetime Warranty',
            'text' => 'Natuzzi Italia offers an exceptional lifetime warranty on selected luxury leather sofa models. Thanks to precise handcrafting and the use of the highest quality materials, we can guarantee long-term perfection. Visit our Prague showroom for a personal viewing and consultation with a designer.',
            'odkaz' => 'https://www.natuzzi.cz/kontakt'
        ],
        'it' => [
            'titulek' => 'Divani in Pelle di Lusso con Garanzia a Vita',
            'text' => 'Natuzzi Italia offre una garanzia a vita eccezionale su modelli selezionati di divani in pelle di lusso. Grazie alla lavorazione artigianale precisa e all\'uso dei materiali di altissima qualità, possiamo garantire la perfezione a lungo termine. Visitate il nostro showroom di Praga per una visione personale e consulenza con un designer.',
            'odkaz' => 'https://www.natuzzi.cz/kontakt'
        ]
    ],
    // Článek 7 - Natuzzi Italia
    [
        'cz' => [
            'titulek' => 'Nové trendy v bytovém designu 2025 - Natuzzi Italia',
            'text' => 'Podle nejnovějšího průzkumu Natuzzi Design Institute jsou hlavními trendy pro rok 2025 zemité tóny, udržitelné materiály a nadčasový italský design. Natuzzi Italia přináší kolekci, která dokonale odpovídá těmto trendům a zároveň zachovává klasickou eleganci.',
            'odkaz' => 'https://www.natuzzi.com/trends-2025'
        ],
        'en' => [
            'titulek' => 'New Trends in Home Design 2025 - Natuzzi Italia',
            'text' => 'According to the latest Natuzzi Design Institute survey, the main trends for 2025 are earthy tones, sustainable materials and timeless Italian design. Natuzzi Italia brings a collection that perfectly matches these trends while maintaining classic elegance.',
            'odkaz' => 'https://www.natuzzi.com/trends-2025'
        ],
        'it' => [
            'titulek' => 'Nuove Tendenze nel Design Domestico 2025 - Natuzzi Italia',
            'text' => 'Secondo l\'ultimo sondaggio del Natuzzi Design Institute, le principali tendenze per il 2025 sono toni terrosi, materiali sostenibili e design italiano senza tempo. Natuzzi Italia porta una collezione che si adatta perfettamente a queste tendenze mantenendo l\'eleganza classica.',
            'odkaz' => 'https://www.natuzzi.com/trends-2025'
        ]
    ],
    // Článek 8 - Natuzzi Italia
    [
        'cz' => [
            'titulek' => 'Osobní designérské poradenství zdarma - Natuzzi Italia',
            'text' => 'V showroomech Natuzzi Italia v Praze nabízíme bezplatné osobní poradenství s certifikovanými designéry. Pomůžeme vám vybrat dokonalou kombinaci nábytku pro váš domov, vytvořit 3D vizualizaci a naplánovat dodávku. Rezervujte si termín na telefonu 224 162 056-7.',
            'odkaz' => 'https://www.natuzzi.cz/rezervace'
        ],
        'en' => [
            'titulek' => 'Free Personal Design Consultation - Natuzzi Italia',
            'text' => 'At Natuzzi Italia showrooms in Prague we offer free personal consultation with certified designers. We will help you choose the perfect furniture combination for your home, create 3D visualization and plan delivery. Book an appointment on 224 162 056-7.',
            'odkaz' => 'https://www.natuzzi.cz/rezervace'
        ],
        'it' => [
            'titulek' => 'Consulenza di Design Personale Gratuita - Natuzzi Italia',
            'text' => 'Negli showroom Natuzzi Italia di Praga offriamo consulenza personale gratuita con designer certificati. Vi aiuteremo a scegliere la combinazione perfetta di mobili per la vostra casa, creare una visualizzazione 3D e pianificare la consegna. Prenotate un appuntamento al 224 162 056-7.',
            'odkaz' => 'https://www.natuzzi.cz/rezervace'
        ]
    ]
];

// ====================================================================
// KATEGORIE 2: NATUZZI EDITIONS (8 článků)
// Dostupnější řada, showroom Praha Čestlice
// ====================================================================
$clankyNatuzziEditions = [
    // Článek 1 - Natuzzi Editions
    [
        'cz' => [
            'titulek' => 'Natuzzi Editions - Showroom Praha Čestlice s parkováním zdarma',
            'text' => 'Navštivte náš velkokapacitní showroom Natuzzi Editions v Praze - Čestlice, naproti OC Global Point. Parkování zdarma pro všechny návštěvníky. Široká nabídka dostupnějších modelů z kolekce Natuzzi Editions s italským designem a kvalitou za výhodné ceny. Otevřeno Po-Pá 10:00-18:00, So 10:00-14:00.',
            'odkaz' => 'https://natuzzidesign.cz/prodejny/praha-cestlice/'
        ],
        'en' => [
            'titulek' => 'Natuzzi Editions - Prague Čestlice Showroom with Free Parking',
            'text' => 'Visit our large showroom Natuzzi Editions in Prague - Čestlice, opposite OC Global Point. Free parking for all visitors. Wide range of affordable models from Natuzzi Editions collection with Italian design and quality at great prices. Open Mon-Fri 10:00-18:00, Sat 10:00-14:00.',
            'odkaz' => 'https://natuzzidesign.cz/prodejny/praha-cestlice/'
        ],
        'it' => [
            'titulek' => 'Natuzzi Editions - Showroom Praga Čestlice con Parcheggio Gratuito',
            'text' => 'Visitate il nostro grande showroom Natuzzi Editions a Praga - Čestlice, di fronte a OC Global Point. Parcheggio gratuito per tutti i visitatori. Ampia gamma di modelli convenienti dalla collezione Natuzzi Editions con design italiano e qualità a prezzi vantaggiosi. Aperto Lun-Ven 10:00-18:00, Sab 10:00-14:00.',
            'odkaz' => 'https://natuzzidesign.cz/prodejny/praha-cestlice/'
        ]
    ],
    // Článek 2 - Natuzzi Editions
    [
        'cz' => [
            'titulek' => 'Nová kolekce Natuzzi Editions 2025 - Italský design dostupně',
            'text' => 'Natuzzi Editions 2025 přináší revoluční kombinaci italského designu s dostupnými cenami. Kolekce zahrnuje sedací soupravy Re-vive s inovativním systémem polohování, modulární sedačky a designové křesla. Každý kus zachovává Natuzzi kvalitu při výhodné ceně.',
            'odkaz' => 'https://www.natuzzi.com/cz/editions-2025'
        ],
        'en' => [
            'titulek' => 'New Natuzzi Editions 2025 Collection - Italian Design Affordably',
            'text' => 'Natuzzi Editions 2025 brings a revolutionary combination of Italian design with affordable prices. The collection includes Re-vive seating systems with innovative reclining system, modular sofas and designer armchairs. Each piece maintains Natuzzi quality at a great price.',
            'odkaz' => 'https://www.natuzzi.com/cz/editions-2025'
        ],
        'it' => [
            'titulek' => 'Nuova Collezione Natuzzi Editions 2025 - Design Italiano Accessibile',
            'text' => 'Natuzzi Editions 2025 porta una combinazione rivoluzionaria di design italiano con prezzi accessibili. La collezione include sistemi di seduta Re-vive con sistema di reclinazione innovativo, divani modulari e poltrone di design. Ogni pezzo mantiene la qualità Natuzzi ad un prezzo vantaggioso.',
            'odkaz' => 'https://www.natuzzi.com/cz/editions-2025'
        ]
    ],
    // Článek 3 - Natuzzi Editions
    [
        'cz' => [
            'titulek' => 'Re-vive systém v Editions - Elektrické polohování za skvělou cenu',
            'text' => 'Inovativní systém Re-vive je nyní dostupný i v řadě Natuzzi Editions za výhodnou cenu. Elektrické ovládání umožňuje najít perfektní pozici pro relaxaci, čtení nebo sledování televize. Každá sedačka vybavena USB porty pro nabíjení zařízení. Přijďte vyzkoušet do showroomu Čestlice.',
            'odkaz' => 'https://www.natuzzi.com/revive'
        ],
        'en' => [
            'titulek' => 'Re-vive System in Editions - Electric Reclining at Great Price',
            'text' => 'The innovative Re-vive system is now also available in the Natuzzi Editions line at a great price. Electric controls allow you to find the perfect position for relaxation, reading or watching TV. Each sofa equipped with USB ports for charging devices. Come try it in Čestlice showroom.',
            'odkaz' => 'https://www.natuzzi.com/revive'
        ],
        'it' => [
            'titulek' => 'Sistema Re-vive in Editions - Reclinazione Elettrica a Ottimo Prezzo',
            'text' => 'L\'innovativo sistema Re-vive è ora disponibile anche nella linea Natuzzi Editions ad un ottimo prezzo. I comandi elettrici consentono di trovare la posizione perfetta per il relax, la lettura o la visione della TV. Ogni divano dotato di porte USB per la ricarica dei dispositivi. Venite a provarlo nello showroom di Čestlice.',
            'odkaz' => 'https://www.natuzzi.com/revive'
        ]
    ],
    // Článek 4 - Natuzzi Editions
    [
        'cz' => [
            'titulek' => 'Modulární nábytek Editions - Flexibilní řešení pro moderní život',
            'text' => 'Modulární sedací soupravy Natuzzi Editions se přizpůsobí vašim potřebám. Můžete je rozšiřovat, měnit konfiguraci nebo přemisťovat podle aktuálních požadavků. Perfektní řešení pro dynamický moderní život za dostupnou cenu. Skladem k okamžitému odběru.',
            'odkaz' => 'https://www.natuzzi.com/modular'
        ],
        'en' => [
            'titulek' => 'Editions Modular Furniture - Flexible Solution for Modern Life',
            'text' => 'Natuzzi Editions modular seating systems adapt to your needs. You can expand them, change configuration or move them according to current requirements. Perfect solution for dynamic modern life at affordable price. In stock for immediate pickup.',
            'odkaz' => 'https://www.natuzzi.com/modular'
        ],
        'it' => [
            'titulek' => 'Mobili Modulari Editions - Soluzione Flessibile per la Vita Moderna',
            'text' => 'I sistemi di seduta modulari Natuzzi Editions si adattano alle tue esigenze. Puoi espanderli, cambiare configurazione o spostarli secondo le esigenze attuali. Soluzione perfetta per la vita moderna dinamica a prezzo accessibile. Disponibile in magazzino per ritiro immediato.',
            'odkaz' => 'https://www.natuzzi.com/modular'
        ]
    ],
    // Článek 5 - Natuzzi Editions
    [
        'cz' => [
            'titulek' => 'Zimní výprodej Natuzzi Editions - Slevy až 30%',
            'text' => 'Využijte naši zimní akci na vybrané modely Natuzzi Editions! Slevy až 30% na sedací soupravy, křesla a doplňky z předchozích kolekcí. Všechny kusy skladem k okamžitému odběru. Akce platí do vyprodání zásob. Navštivte showroom Čestlice nebo volejte 224 162 056-7.',
            'odkaz' => 'https://natuzzidesign.cz/akce'
        ],
        'en' => [
            'titulek' => 'Winter Sale Natuzzi Editions - Up to 30% Off',
            'text' => 'Take advantage of our winter sale on selected Natuzzi Editions models! Up to 30% off on seating sets, armchairs and accessories from previous collections. All pieces in stock for immediate pickup. Sale valid while stocks last. Visit Čestlice showroom or call 224 162 056-7.',
            'odkaz' => 'https://natuzzidesign.cz/akce'
        ],
        'it' => [
            'titulek' => 'Saldi Invernali Natuzzi Editions - Fino al 30% di Sconto',
            'text' => 'Approfittate dei nostri saldi invernali su modelli selezionati Natuzzi Editions! Fino al 30% di sconto su set di sedute, poltrone e accessori delle collezioni precedenti. Tutti i pezzi in magazzino per ritiro immediato. Saldi validi fino ad esaurimento scorte. Visitate lo showroom di Čestlice o chiamate 224 162 056-7.',
            'odkaz' => 'https://natuzzidesign.cz/akce'
        ]
    ],
    // Článek 6 - Natuzzi Editions
    [
        'cz' => [
            'titulek' => 'Kvalitní textilní potahy Editions - Praktické a krásné',
            'text' => 'Natuzzi Editions nabízí širokou paletu kvalitních textilních potahů certifikovaných pro domácnosti s dětmi a mazlíčky. Materiály jsou odolné proti skvrnám, snadno udržovatelné a dostupné v desítkách barevných variant. Vzorník k nahlédnutí v showroomu Čestlice.',
            'odkaz' => 'https://www.natuzzi.com/fabrics'
        ],
        'en' => [
            'titulek' => 'Quality Textile Upholstery Editions - Practical and Beautiful',
            'text' => 'Natuzzi Editions offers a wide palette of quality textile upholstery certified for households with children and pets. Materials are stain-resistant, easy to maintain and available in dozens of color variants. Sample book for viewing in Čestlice showroom.',
            'odkaz' => 'https://www.natuzzi.com/fabrics'
        ],
        'it' => [
            'titulek' => 'Rivestimenti Tessili di Qualità Editions - Pratici e Belli',
            'text' => 'Natuzzi Editions offre un\'ampia gamma di rivestimenti tessili di qualità certificati per famiglie con bambini e animali domestici. I materiali sono resistenti alle macchie, facili da mantenere e disponibili in dozzine di varianti di colore. Campionario disponibile per la visione nello showroom di Čestlice.',
            'odkaz' => 'https://www.natuzzi.com/fabrics'
        ]
    ],
    // Článek 7 - Natuzzi Editions
    [
        'cz' => [
            'titulek' => 'Expressová dodávka Editions - Na míru za 6 týdnů',
            'text' => 'U vybraných modelů Natuzzi Editions nabízíme expresní výrobu a dodávku na míru za pouhých 6 týdnů! Vyberte si model, potah a konfiguraci v showroomu a my zajistíme rychlou výrobu přímo v italské továrně. Záruka kvality a rychlé dodání.',
            'odkaz' => 'https://natuzzidesign.cz/express-dodavka'
        ],
        'en' => [
            'titulek' => 'Express Delivery Editions - Custom-made in 6 Weeks',
            'text' => 'For selected Natuzzi Editions models we offer express production and custom delivery in just 6 weeks! Choose your model, upholstery and configuration in the showroom and we will arrange fast production directly in the Italian factory. Quality guarantee and fast delivery.',
            'odkaz' => 'https://natuzzidesign.cz/express-dodavka'
        ],
        'it' => [
            'titulek' => 'Consegna Express Editions - Su Misura in 6 Settimane',
            'text' => 'Per modelli selezionati Natuzzi Editions offriamo produzione express e consegna su misura in sole 6 settimane! Scegliete il vostro modello, rivestimento e configurazione nello showroom e organizzeremo una produzione rapida direttamente nella fabbrica italiana. Garanzia di qualità e consegna veloce.',
            'odkaz' => 'https://natuzzidesign.cz/express-dodavka'
        ]
    ],
    // Článek 8 - Natuzzi Editions
    [
        'cz' => [
            'titulek' => 'Bezúročné splátky na Editions - 0% navýšení',
            'text' => 'Nakupte váš vysněný nábytek Natuzzi Editions na bezúročné splátky až na 12 měsíců! Žádné skryté poplatky, 0% navýšení. Vyřízení přímo v showroomu Čestlice během 15 minut. Získejte italský design ještě dnes a plaťte postupně.',
            'odkaz' => 'https://natuzzidesign.cz/splatky'
        ],
        'en' => [
            'titulek' => 'Interest-Free Installments on Editions - 0% Increase',
            'text' => 'Buy your dream Natuzzi Editions furniture on interest-free installments up to 12 months! No hidden fees, 0% increase. Processing directly in Čestlice showroom within 15 minutes. Get Italian design today and pay gradually.',
            'odkaz' => 'https://natuzzidesign.cz/splatky'
        ],
        'it' => [
            'titulek' => 'Rate Senza Interessi su Editions - 0% di Aumento',
            'text' => 'Acquistate i vostri mobili Natuzzi Editions da sogno a rate senza interessi fino a 12 mesi! Nessun costo nascosto, 0% di aumento. Elaborazione diretta nello showroom di Čestlice entro 15 minuti. Ottenete il design italiano oggi e pagate gradualmente.',
            'odkaz' => 'https://natuzzidesign.cz/splatky'
        ]
    ]
];

// ====================================================================
// KATEGORIE 3: SOFTALY (8 článků)
// Partnerská značka, Italy Design showroom Modřice
// ====================================================================
$clankySoftaly = [
    // Článek 1 - Softaly
    [
        'cz' => [
            'titulek' => 'Softaly - Italský nábytek v SOHO Interior Center Modřice',
            'text' => 'Navštivte showroom značky Softaly v SOHO Interior Center, Modřice u Brna. Italský výrobce čalouněného nábytku s tradicí kvality a dostupnými cenami. Široký výběr sedacích souprav v textilu i kůži na míru i skladem. Italy Design - autorizovaný distributor pro ČR.',
            'odkaz' => 'https://www.italydesign.cz/kontakt'
        ],
        'en' => [
            'titulek' => 'Softaly - Italian Furniture in SOHO Interior Center Modřice',
            'text' => 'Visit the Softaly brand showroom in SOHO Interior Center, Modřice near Brno. Italian upholstered furniture manufacturer with tradition of quality and affordable prices. Wide selection of seating sets in fabric and leather, custom-made and in stock. Italy Design - authorized distributor for Czech Republic.',
            'odkaz' => 'https://www.italydesign.cz/kontakt'
        ],
        'it' => [
            'titulek' => 'Softaly - Mobili Italiani in SOHO Interior Center Modřice',
            'text' => 'Visitate lo showroom del marchio Softaly in SOHO Interior Center, Modřice vicino a Brno. Produttore italiano di mobili imbottiti con tradizione di qualità e prezzi accessibili. Ampia selezione di set di sedute in tessuto e pelle, su misura e in magazzino. Italy Design - distributore autorizzato per la Repubblica Ceca.',
            'odkaz' => 'https://www.italydesign.cz/kontakt'
        ]
    ],
    // Článek 2 - Softaly
    [
        'cz' => [
            'titulek' => 'Softaly kožené sedačky - Italská kvalita za rozumnou cenu',
            'text' => 'Softaly využívá italské kůže a precizní řemeslné zpracování pro vytvoření sedaček, které vydrží generace. Každá sedačka je vyráběna na míru podle vašich požadavků. Vyberte si z desítek modelů a stovek kombinací kůže a barev. Garance kvality přímo od výrobce.',
            'odkaz' => 'https://www.italydesign.cz'
        ],
        'en' => [
            'titulek' => 'Softaly Leather Sofas - Italian Quality at Reasonable Price',
            'text' => 'Softaly uses Italian leather and precise craftsmanship to create sofas that last generations. Each sofa is custom-made according to your requirements. Choose from dozens of models and hundreds of leather and color combinations. Quality guarantee directly from the manufacturer.',
            'odkaz' => 'https://www.italydesign.cz'
        ],
        'it' => [
            'titulek' => 'Divani in Pelle Softaly - Qualità Italiana a Prezzo Ragionevole',
            'text' => 'Softaly utilizza pelli italiane e lavorazione artigianale precisa per creare divani che durano generazioni. Ogni divano è realizzato su misura secondo le vostre esigenze. Scegliete tra dozzine di modelli e centinaia di combinazioni di pelle e colori. Garanzia di qualità direttamente dal produttore.',
            'odkaz' => 'https://www.italydesign.cz'
        ]
    ],
    // Článek 3 - Softaly
    [
        'cz' => [
            'titulek' => 'Softaly textilní soupravy - Praktické pro rodiny s dětmi',
            'text' => 'Textilní sedací soupravy Softaly jsou ideální pro rodiny s dětmi a domácími mazlíčky. Certifikované potahoviny odolné proti skvrnám, snadno omyvatelné a dostupné v široké škále barev a vzorů. Výběr z modulárních i klasických konfigurací. Skladem i na objednávku.',
            'odkaz' => 'https://www.italydesign.cz/textilni-sedacky'
        ],
        'en' => [
            'titulek' => 'Softaly Fabric Sets - Practical for Families with Children',
            'text' => 'Softaly fabric seating sets are ideal for families with children and pets. Certified upholstery resistant to stains, easily washable and available in wide range of colors and patterns. Choice of modular and classic configurations. In stock and to order.',
            'odkaz' => 'https://www.italydesign.cz/textilni-sedacky'
        ],
        'it' => [
            'titulek' => 'Set in Tessuto Softaly - Pratici per Famiglie con Bambini',
            'text' => 'I set di sedute in tessuto Softaly sono ideali per famiglie con bambini e animali domestici. Rivestimenti certificati resistenti alle macchie, facilmente lavabili e disponibili in un\'ampia gamma di colori e motivi. Scelta di configurazioni modulari e classiche. In magazzino e su ordinazione.',
            'odkaz' => 'https://www.italydesign.cz/textilni-sedacky'
        ]
    ],
    // Článek 4 - Softaly
    [
        'cz' => [
            'titulek' => 'Rozkládací sedačky Softaly - Komfort pro hosty',
            'text' => 'Softaly nabízí širokou řadu rozkládacích sedaček s precizním italským mechanismem. Jednoduchý rozklad na plnohodnotné lůžko během několika sekund. Kvalitní matrace zajišťuje pohodlný spánek. Perfektní řešení pro menší byty nebo jako lůžko pro návštěvy.',
            'odkaz' => 'https://www.italydesign.cz/rozkladaci'
        ],
        'en' => [
            'titulek' => 'Softaly Sofa Beds - Comfort for Guests',
            'text' => 'Softaly offers wide range of sofa beds with precise Italian mechanism. Simple conversion to full bed in seconds. Quality mattress ensures comfortable sleep. Perfect solution for smaller apartments or as guest bed.',
            'odkaz' => 'https://www.italydesign.cz/rozkladaci'
        ],
        'it' => [
            'titulek' => 'Divani Letto Softaly - Comfort per gli Ospiti',
            'text' => 'Softaly offre un\'ampia gamma di divani letto con preciso meccanismo italiano. Semplice conversione in letto completo in pochi secondi. Il materasso di qualità garantisce un sonno confortevole. Soluzione perfetta per appartamenti più piccoli o come letto per gli ospiti.',
            'odkaz' => 'https://www.italydesign.cz/rozkladaci'
        ]
    ],
    // Článek 5 - Softaly
    [
        'cz' => [
            'titulek' => 'Softaly rohové sedačky - Maximální využití prostoru',
            'text' => 'Rohové sedací soupravy Softaly jsou navrženy pro maximální využití prostoru ve vašem obývacím pokoji. Dostupné v levém i pravém provedení, s úložným prostorem i bez. Široký výběr rozměrů a konfigurací přesně podle vašich potřeb. Navštivte showroom v Modřicích pro výběr.',
            'odkaz' => 'https://www.italydesign.cz/rohove'
        ],
        'en' => [
            'titulek' => 'Softaly Corner Sofas - Maximum Space Utilization',
            'text' => 'Softaly corner seating sets are designed for maximum space utilization in your living room. Available in left and right versions, with and without storage space. Wide selection of sizes and configurations exactly according to your needs. Visit Modřice showroom for selection.',
            'odkaz' => 'https://www.italydesign.cz/rohove'
        ],
        'it' => [
            'titulek' => 'Divani Angolari Softaly - Massimo Utilizzo dello Spazio',
            'text' => 'I set di sedute angolari Softaly sono progettati per il massimo utilizzo dello spazio nel vostro soggiorno. Disponibili in versioni sinistra e destra, con e senza spazio di archiviazione. Ampia selezione di dimensioni e configurazioni esattamente secondo le vostre esigenze. Visitate lo showroom di Modřice per la selezione.',
            'odkaz' => 'https://www.italydesign.cz/rohove'
        ]
    ],
    // Článek 6 - Softaly
    [
        'cz' => [
            'titulek' => 'Italy Design - Bezplatná doprava a montáž Softaly',
            'text' => 'Italy Design zajišťuje bezplatnou dopravu a montáž všech sedacích souprav Softaly po celé České republice. Profesionální dopravci a montážní technici garantují bezpečné dodání a instalaci přímo ve vašem domě. Odvoz starého nábytku na vyžádání za příplatek.',
            'odkaz' => 'https://www.italydesign.cz/doprava'
        ],
        'en' => [
            'titulek' => 'Italy Design - Free Delivery and Assembly Softaly',
            'text' => 'Italy Design provides free delivery and assembly of all Softaly seating sets throughout the Czech Republic. Professional carriers and assembly technicians guarantee safe delivery and installation directly in your home. Old furniture removal on request for additional fee.',
            'odkaz' => 'https://www.italydesign.cz/doprava'
        ],
        'it' => [
            'titulek' => 'Italy Design - Consegna e Montaggio Gratuiti Softaly',
            'text' => 'Italy Design fornisce consegna e montaggio gratuiti di tutti i set di sedute Softaly in tutta la Repubblica Ceca. Trasportatori professionali e tecnici di montaggio garantiscono consegna e installazione sicure direttamente a casa vostra. Rimozione di vecchi mobili su richiesta a pagamento.',
            'odkaz' => 'https://www.italydesign.cz/doprava'
        ]
    ],
    // Článek 7 - Softaly
    [
        'cz' => [
            'titulek' => 'Softaly elektrické relax - Pohodlí na jedno stisknutí',
            'text' => 'Elektrické relax sedačky Softaly nabízejí dokonalé pohodlí díky tichému motoru a plynulému polohování. Nezávislé ovládání pro každou pozici, paměťová funkce oblíbených poloh a USB porty pro nabíjení. Dostupné ve 2místném i 3místném provedení.',
            'odkaz' => 'https://www.italydesign.cz/relax'
        ],
        'en' => [
            'titulek' => 'Softaly Electric Recliner - Comfort at One Touch',
            'text' => 'Softaly electric recliner sofas offer perfect comfort thanks to quiet motor and smooth positioning. Independent control for each position, memory function for favorite positions and USB ports for charging. Available in 2-seater and 3-seater versions.',
            'odkaz' => 'https://www.italydesign.cz/relax'
        ],
        'it' => [
            'titulek' => 'Softaly Relax Elettrico - Comfort a Un Tocco',
            'text' => 'I divani relax elettrici Softaly offrono comfort perfetto grazie al motore silenzioso e al posizionamento fluido. Controllo indipendente per ogni posizione, funzione di memoria per le posizioni preferite e porte USB per la ricarica. Disponibili in versioni a 2 e 3 posti.',
            'odkaz' => 'https://www.italydesign.cz/relax'
        ]
    ],
    // Článek 8 - Softaly
    [
        'cz' => [
            'titulek' => '5 let záruka na Softaly - Jistota italské kvality',
            'text' => 'Na všechny sedací soupravy Softaly poskytuje Italy Design prodlouženou záruku 5 let. Zahrnuje konstrukci, mechanismy i potahoviny. Bezplatné záruční opravy a servis v místě instalace. Softaly je synonymem pro dlouhodobou kvalitu a spolehlivost italského nábytku.',
            'odkaz' => 'https://www.italydesign.cz/zaruka'
        ],
        'en' => [
            'titulek' => '5 Year Warranty on Softaly - Certainty of Italian Quality',
            'text' => 'Italy Design provides extended 5-year warranty on all Softaly seating sets. Covers construction, mechanisms and upholstery. Free warranty repairs and service at installation location. Softaly is synonym for long-term quality and reliability of Italian furniture.',
            'odkaz' => 'https://www.italydesign.cz/zaruka'
        ],
        'it' => [
            'titulek' => '5 Anni di Garanzia su Softaly - Certezza della Qualità Italiana',
            'text' => 'Italy Design fornisce garanzia estesa di 5 anni su tutti i set di sedute Softaly. Copre costruzione, meccanismi e rivestimenti. Riparazioni e servizio in garanzia gratuiti nel luogo di installazione. Softaly è sinonimo di qualità a lungo termine e affidabilità dei mobili italiani.',
            'odkaz' => 'https://www.italydesign.cz/zaruka'
        ]
    ]
];

// === NÁHODNÝ VÝBĚR ČLÁNKŮ (3 Z KAŽDÉ KATEGORIE) ===
echo "   📊 Náhodný výběr obsahu (3 Natuzzi Italia + 3 Natuzzi Editions + 3 Softaly)...\n";

// Vybrat 3 náhodné články z každé kategorie
$indexyItalia = array_rand($clankyNatuzziItalia, 3);
$indexyEditions = array_rand($clankyNatuzziEditions, 3);
$indexySoftaly = array_rand($clankySoftaly, 3);

// Zajistit že jsou indexy v poli
if (!is_array($indexyItalia)) $indexyItalia = [$indexyItalia];
if (!is_array($indexyEditions)) $indexyEditions = [$indexyEditions];
if (!is_array($indexySoftaly)) $indexySoftaly = [$indexySoftaly];

echo "   ✅ Natuzzi Italia: článek " . ($indexyItalia[0] + 1) . ", " . ($indexyItalia[1] + 1) . " a " . ($indexyItalia[2] + 1) . "\n";
echo "   ✅ Natuzzi Editions: článek " . ($indexyEditions[0] + 1) . ", " . ($indexyEditions[1] + 1) . " a " . ($indexyEditions[2] + 1) . "\n";
echo "   ✅ Softaly: článek " . ($indexySoftaly[0] + 1) . ", " . ($indexySoftaly[1] + 1) . " a " . ($indexySoftaly[2] + 1) . "\n\n";

// === SESTAVENÍ OBSAHU V ČEŠTINĚ ===
$obsahCZ = "# Denní aktuality Natuzzi\n\n";
$obsahCZ .= "**Datum:** " . date('d.m.Y') . " | **Svátek má:** {$jmenoSvatku}\n\n";
$obsahCZ .= "Vítejte u dnešních aktualit o luxusním italském nábytku Natuzzi. Přinášíme vám nejnovější informace o prémiové řadě Natuzzi Italia, dostupnější kolekci Natuzzi Editions a partnerstké značce Softaly.\n\n";

$obsahCZ .= "![Natuzzi Showroom](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=300&fit=crop)\n\n";

$obsahCZ .= "## Natuzzi Italia - Prémiová řada\n\n";
$cislo = 1;
foreach ($indexyItalia as $poradi => $index) {
    $clanek = $clankyNatuzziItalia[$index]['cz'];
    $obsahCZ .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahCZ .= "{$clanek['text']}\n\n";
    $obsahCZ .= "[Více informací]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahCZ .= "![Luxusní kožená sedačka Natuzzi Italia](https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahCZ .= "## Natuzzi Editions - Dostupný luxus\n\n";
foreach ($indexyEditions as $poradi => $index) {
    $clanek = $clankyNatuzziEditions[$index]['cz'];
    $obsahCZ .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahCZ .= "{$clanek['text']}\n\n";
    $obsahCZ .= "[Více informací]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahCZ .= "![Moderní sedací souprava Natuzzi Editions](https://images.unsplash.com/photo-1540574163026-643ea20ade25?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahCZ .= "## Softaly - Italská kvalita pro každého\n\n";
foreach ($indexySoftaly as $poradi => $index) {
    $clanek = $clankySoftaly[$index]['cz'];
    $obsahCZ .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahCZ .= "{$clanek['text']}\n\n";
    $obsahCZ .= "[Více informací]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahCZ .= "![Softaly čalouněný nábytek](https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahCZ .= "## Kontakty\n\n";
$obsahCZ .= "**Natuzzi Italia Praha:**\n";
$obsahCZ .= "- Pasáž Lucerna: V Jámě 699/3, Praha 1 | Tel: 224 162 056-7\n";
$obsahCZ .= "- River Garden: Rohanské nábřeží 678/25, Praha 8 - Karlín\n";
$obsahCZ .= "- Email: natuzzi@natuzzi.cz | Otevřeno: Po-Pá 10-18h, So 10-14h\n\n";
$obsahCZ .= "**Natuzzi Editions:**\n";
$obsahCZ .= "- Praha Čestlice (naproti OC Global Point) | Parkování zdarma\n\n";
$obsahCZ .= "**Softaly (Italy Design):**\n";
$obsahCZ .= "- SOHO Interior Center, Modřice u Brna\n\n";

echo "   ✅ CZ obsah vygenerován (" . strlen($obsahCZ) . " znaků)\n";

// === SESTAVENÍ OBSAHU V ANGLIČTINĚ ===
$obsahEN = "# Natuzzi Daily News\n\n";
$obsahEN .= "**Date:** " . date('m/d/Y') . " | **Name Day:** {$jmenoSvatku}\n\n";
$obsahEN .= "Welcome to today's news about luxury Italian furniture Natuzzi. We bring you the latest information about premium Natuzzi Italia line, affordable Natuzzi Editions collection and partner brand Softaly.\n\n";

$obsahEN .= "![Natuzzi Showroom](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=300&fit=crop)\n\n";

$obsahEN .= "## Natuzzi Italia - Premium Line\n\n";
$cislo = 1;
foreach ($indexyItalia as $poradi => $index) {
    $clanek = $clankyNatuzziItalia[$index]['en'];
    $obsahEN .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahEN .= "{$clanek['text']}\n\n";
    $obsahEN .= "[More information]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahEN .= "![Luxury Leather Sofa Natuzzi Italia](https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahEN .= "## Natuzzi Editions - Affordable Luxury\n\n";
foreach ($indexyEditions as $poradi => $index) {
    $clanek = $clankyNatuzziEditions[$index]['en'];
    $obsahEN .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahEN .= "{$clanek['text']}\n\n";
    $obsahEN .= "[More information]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahEN .= "![Modern Seating System Natuzzi Editions](https://images.unsplash.com/photo-1540574163026-643ea20ade25?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahEN .= "## Softaly - Italian Quality for Everyone\n\n";
foreach ($indexySoftaly as $poradi => $index) {
    $clanek = $clankySoftaly[$index]['en'];
    $obsahEN .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahEN .= "{$clanek['text']}\n\n";
    $obsahEN .= "[More information]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahEN .= "![Softaly Upholstered Furniture](https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahEN .= "## Contacts\n\n";
$obsahEN .= "**Natuzzi Italia Prague:**\n";
$obsahEN .= "- Pasáž Lucerna: V Jámě 699/3, Prague 1 | Phone: 224 162 056-7\n";
$obsahEN .= "- River Garden: Rohanské nábřeží 678/25, Prague 8 - Karlín\n";
$obsahEN .= "- Email: natuzzi@natuzzi.cz | Open: Mon-Fri 10am-6pm, Sat 10am-2pm\n\n";
$obsahEN .= "**Natuzzi Editions:**\n";
$obsahEN .= "- Prague Čestlice (opposite OC Global Point) | Free parking\n\n";
$obsahEN .= "**Softaly (Italy Design):**\n";
$obsahEN .= "- SOHO Interior Center, Modřice near Brno\n\n";

echo "   ✅ EN obsah vygenerován (" . strlen($obsahEN) . " znaků)\n";

// === SESTAVENÍ OBSAHU V ITALŠTINĚ ===
$obsahIT = "# Notizie Quotidiane Natuzzi\n\n";
$obsahIT .= "**Data:** " . date('d.m.Y') . " | **Onomastico:** {$jmenoSvatku}\n\n";
$obsahIT .= "Benvenuti alle notizie di oggi sui mobili italiani di lusso Natuzzi. Vi portiamo le ultime informazioni sulla linea premium Natuzzi Italia, la collezione accessibile Natuzzi Editions e il marchio partner Softaly.\n\n";

$obsahIT .= "![Natuzzi Showroom](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=300&fit=crop)\n\n";

$obsahIT .= "## Natuzzi Italia - Linea Premium\n\n";
$cislo = 1;
foreach ($indexyItalia as $poradi => $index) {
    $clanek = $clankyNatuzziItalia[$index]['it'];
    $obsahIT .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahIT .= "{$clanek['text']}\n\n";
    $obsahIT .= "[Maggiori informazioni]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahIT .= "![Divano in Pelle di Lusso Natuzzi Italia](https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahIT .= "## Natuzzi Editions - Lusso Accessibile\n\n";
foreach ($indexyEditions as $poradi => $index) {
    $clanek = $clankyNatuzziEditions[$index]['it'];
    $obsahIT .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahIT .= "{$clanek['text']}\n\n";
    $obsahIT .= "[Maggiori informazioni]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahIT .= "![Sistema di Seduta Moderno Natuzzi Editions](https://images.unsplash.com/photo-1540574163026-643ea20ade25?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahIT .= "## Softaly - Qualità Italiana per Tutti\n\n";
foreach ($indexySoftaly as $poradi => $index) {
    $clanek = $clankySoftaly[$index]['it'];
    $obsahIT .= "**{$cislo}. {$clanek['titulek']}**\n\n";
    $obsahIT .= "{$clanek['text']}\n\n";
    $obsahIT .= "[Maggiori informazioni]({$clanek['odkaz']})\n\n";

    // Přidat obrázek po prvním článku
    if ($poradi === 0) {
        $obsahIT .= "![Mobili Imbottiti Softaly](https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=600&h=250&fit=crop)\n\n";
    }
    $cislo++;
}

$obsahIT .= "## Contatti\n\n";
$obsahIT .= "**Natuzzi Italia Praga:**\n";
$obsahIT .= "- Pasáž Lucerna: V Jámě 699/3, Praga 1 | Telefono: 224 162 056-7\n";
$obsahIT .= "- River Garden: Rohanské nábřeží 678/25, Praga 8 - Karlín\n";
$obsahIT .= "- Email: natuzzi@natuzzi.cz | Aperto: Lun-Ven 10-18, Sab 10-14\n\n";
$obsahIT .= "**Natuzzi Editions:**\n";
$obsahIT .= "- Praga Čestlice (di fronte a OC Global Point) | Parcheggio gratuito\n\n";
$obsahIT .= "**Softaly (Italy Design):**\n";
$obsahIT .= "- SOHO Interior Center, Modřice vicino a Brno\n\n";

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
        'natuzzi_sources' => [
            'https://www.natuzzi.cz',
            'https://natuzzidesign.cz',
            'https://www.italydesign.cz'
        ],
        'generated_at' => date('Y-m-d H:i:s'),
        'struktura' => '9 článků: 3x Natuzzi Italia + 3x Natuzzi Editions + 3x Softaly',
        'fotky' => '4 obrázky (1 hlavní + 3 sekční)'
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
echo "Statistiky:\n";
echo "- Celkem článků v databázi: 24 (8 Natuzzi Italia + 8 Natuzzi Editions + 8 Softaly)\n";
echo "- Denně zobrazeno: 9 článků (3+3+3)\n";
echo "- Počet obrázků: 4 (1 hlavní + 3 sekční)\n";
echo "- Možných kombinací: " . (56 * 56 * 56) . " různých variant obsahu (C(8,3) × C(8,3) × C(8,3))\n\n";
echo "Nyní můžete zobrazit aktualitu na:\n";
echo "https://www.wgs-service.cz/aktuality.php\n";

echo "</pre>";
?>
