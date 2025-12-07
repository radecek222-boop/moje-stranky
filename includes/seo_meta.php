<?php
/**
 * SEO Meta Tags a Schema.org strukturovana data
 *
 * Tento soubor obsahuje vsechna SEO klicova slova a meta tagy
 * pro maximalni viditelnost ve vyhledavacich.
 *
 * Pouziti: renderSeoMeta('index'); a renderSchemaOrg('index');
 */

// Kompletni seznam klicovych slov pro WGS - MAXIMALNI POKRYTI
$seoKlicovaSlova = implode(', ', [
    // === TYPY NABYTKU - VSECHNY VARIANTY ===
    'sedacka', 'sedacky', 'pohovka', 'pohovky', 'gauc', 'gauce', 'sofa', 'sofy',
    'kreslo', 'kresla', 'zidle', 'zidle', 'stul', 'stoly', 'taburet', 'podnozka',
    'lehatko', 'lenoska', 'lavice', 'ottoman', 'futon',

    // Styly sedacek
    'moderni sedacka', 'trendy sedacka', 'designova sedacka', 'klasicka sedacka',
    'luxusni sedacka', 'italska sedacka', 'skandinavska sedacka', 'minimalisticka sedacka',
    'moderni kreslo', 'designove kreslo', 'retro sedacka', 'vintage sedacka',

    // Typy sedacek
    'kozena sedacka', 'latkova sedacka', 'rohova sedacka', 'rozkladaci sedacka',
    'kozene kreslo', 'relaxacni kreslo', 'masazni kreslo', 'houpaci kreslo',
    'sedaci souprava', 'obyvaci sedacka', 'kancelarska zidle', 'jidelni zidle',
    'barova zidle', 'konfercni zidle', 'loznicovy nabytek',

    // Tvary a velikosti
    'mala sedacka', 'velka sedacka', 'dvousedacka', 'trisedak', 'ctyrlocal',
    'rohova souprava', 'u-sedacka', 'l-sedacka', 'modulova sedacka',

    // === AKCE A SLUZBY ===
    'oprava sedacky', 'oprava sedacek', 'oprava kresla', 'oprava nabytku',
    'servis sedacky', 'servis nabytku', 'servis Natuzzi', 'servis kresla',
    'cisteni sedacky', 'cisteni kozene sedacky', 'cisteni pohovky', 'cisteni kresla',
    'cisteni koberce', 'cisteni calouneneho nabytku', 'cisteni latkovych sedacek',
    'reklamace sedacky', 'reklamace nabytku', 'reklamace Natuzzi', 'reklamace kresla',
    'udrzba sedacky', 'udrzba kozene sedacky', 'udrzba nabytku',
    'renovace sedacky', 'renovace kresla', 'renovace nabytku', 'renovace pohovky',
    'precalouneni', 'calouneni', 'precalouneni sedacky', 'precalouneni kresla',
    'vymena potahu', 'vymena calouneni', 'vymena kuze',
    'montaz nabytku', 'montaz sedacky', 'demontaz nabytku',
    'oprava mechanismu', 'oprava relaxu', 'oprava elektriky',

    // === MATERIALY ===
    'kozena', 'kuze', 'kozene', 'prava kuze', 'umela kuze', 'ekokuze',
    'textil', 'latka', 'latkova', 'samet', 'mansestr', 'len', 'bavlna',
    'calouneni', 'calouneny nabytek', 'mikroplyš', 'polyester',

    // === ZNACKA NATUZZI A DALSI ===
    'Natuzzi', 'Natuzzi servis', 'Natuzzi oprava', 'Natuzzi reklamace',
    'Natuzzi Ceska republika', 'autorizovany servis Natuzzi', 'Natuzzi Italia',
    'italsky nabytek', 'luxusni nabytek', 'premiovy nabytek', 'znackovy nabytek',
    'designovy nabytek', 'kvalitni nabytek',

    // === GEOGRAFICKE ===
    'Praha', 'Brno', 'Ostrava', 'Plzen', 'Olomouc', 'Liberec', 'Hradec Kralove',
    'Ceske Budejovice', 'Usti nad Labem', 'Pardubice', 'Zlin', 'Karlovy Vary',
    'Ceska republika', 'CR', 'Stredocesky kraj', 'Moravskoslezsky kraj',
    'servis nabytku Praha', 'oprava sedacek Brno', 'cisteni sedacek Praha',
    'oprava nabytku Ostrava', 'servis sedacek Plzen', 'calouneni Praha',

    // === PROBLEMY A STAV ===
    'poskozena sedacka', 'rozbita sedacka', 'spinava sedacka', 'stara sedacka',
    'praskla kuze', 'odrena kuze', 'opotrebovana sedacka', 'roztrhana sedacka',
    'nefunkcni mechanismus', 'rozbity relax', 'pokousana sedacka', 'poskrabana kuze',
    'vyboulena sedacka', 'prosezenasedacka', 'zaschlá sedacka',

    // === DOPLNKY A PRISLUSENSTVI ===
    'koberec', 'koberce', 'kusovy koberec', 'behoun', 'rohozka',
    'polstar', 'polstare', 'dekoracni polstare', 'prehož', 'deka',
    'potah na sedacku', 'navlek na kreslo', 'chranič sedacky',

    // === LONG-TAIL FRAZE - OTAZKY ===
    'kde opravit sedacku', 'kolik stoji oprava sedacky', 'cena opravy sedacky',
    'jak vycistit kozenou sedacku', 'jak opravit sedacku', 'kam s rozbitou sedackou',
    'oprava kozene sedacky cena', 'servis luxusniho nabytku',
    'profesionalni cisteni sedacek', 'oprava mechanismu sedacky',
    'nejlepsi servis nabytku', 'kvalitni oprava sedacek',

    // === BRANDING ===
    'White Glove Service', 'WGS servis', 'WGS', 'wgs-service'
]);

// Konfigurace SEO pro jednotlive stranky - MAXIMALNI POKRYTI KLICOVYCH SLOV
$seoStranky = [
    'index' => [
        'title' => 'Oprava sedacky, kresla, pohovky, gauce | Servis nabytku Natuzzi | Praha, Brno, CR',
        'description' => 'Profesionalni oprava a servis sedacek, kresel, pohovek znacky Natuzzi. Cisteni kozenych i latkovych sedacek. Precalouneni, renovace, reklamace. Oprava moderni sedacky, designove kreslo, rohova sedacka. Autorizovany servis Praha, Brno, cela CR. Tel: +420 725 965 826',
        'keywords' => 'oprava sedacky, oprava kresla, oprava pohovky, oprava gauce, servis sedacky, servis nabytku, servis Natuzzi, cisteni sedacky, cisteni kozene sedacky, cisteni latkove sedacky, reklamace sedacky, reklamace nabytku, precalouneni sedacky, renovace sedacky, moderni sedacka, designova sedacka, trendy sedacka, luxusni sedacka, italska sedacka, rohova sedacka, kozena sedacka, latkova sedacka, rozkladaci sedacka, relaxacni kreslo, sedaci souprava, oprava mechanismu, oprava relaxu, cisteni koberce, koberec, polstare, Praha, Brno, Ostrava, Plzen, White Glove Service, WGS',
        'canonical' => 'https://wgs-service.cz/',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'novareklamace' => [
        'title' => 'Objednat opravu sedacky, kresla, pohovky online | Reklamace Natuzzi | Servis nabytku',
        'description' => 'Objednejte opravu sedacky, kresla nebo pohovky online. Reklamace nabytku Natuzzi. Oprava kozene sedacky, latkove pohovky, designoveho kresla. Cisteni, precalouneni, renovace. Rychle vyrizeni Praha, Brno a okoli.',
        'keywords' => 'objednat opravu sedacky, objednat servis kresla, reklamace Natuzzi, oprava sedacky online, oprava kresla online, oprava pohovky online, servis nabytku online, formular reklamace, objednat cisteni sedacky, objednat precalouneni, moderni sedacka oprava, designove kreslo servis, rohova sedacka oprava, relaxacni kreslo servis, kozena sedacka cisteni, latkova pohovka oprava, Praha, Brno',
        'canonical' => 'https://wgs-service.cz/novareklamace.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'cenik' => [
        'title' => 'Cenik oprav sedacek, kresel, pohovek | Kolik stoji oprava sedacky | Servis Natuzzi ceny',
        'description' => 'Cenik oprav sedacek, kresel a nabytku Natuzzi. Kolik stoji oprava sedacky? Cena cisteni kozene sedacky, precalouneni, oprava mechanismu. Kalkulacka ceny servisu online. Od 110 EUR. Oprava moderni sedacky cena.',
        'keywords' => 'cenik oprava sedacky, kolik stoji oprava sedacky, cena opravy kresla, cenik servis Natuzzi, cena cisteni sedacky, cena precalouneni, cena opravy mechanismu, kalkulacka ceny servisu, oprava kozene sedacky cena, oprava latkove pohovky cena, cenik renovace nabytku, moderni sedacka cena opravy, designove kreslo cenik, rohova sedacka oprava cena, relaxacni kreslo servis cena',
        'canonical' => 'https://wgs-service.cz/cenik.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'seznam' => [
        'title' => 'Prehled reklamaci a servisnich zakazek | Stav opravy sedacky | WGS Natuzzi',
        'description' => 'Prehled servisnich zakazek a reklamaci nabytku Natuzzi. Sledovani stavu opravy sedacek, kresel a pohovek. White Glove Service - profesionalni servis luxusniho nabytku.',
        'keywords' => 'prehled reklamaci, stav opravy sedacky, stav opravy kresla, sledovani servisu, reklamace Natuzzi stav, servisni zakazky, prehled oprav nabytku',
        'canonical' => 'https://wgs-service.cz/seznam.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'protokol' => [
        'title' => 'Servisni protokol opravy nabytku | Dokumentace servisu sedacky | White Glove Service',
        'description' => 'Servisni protokol pro opravy a reklamace nabytku Natuzzi. Dokumentace servisu sedacek, kresel a pohovek. Profesionalni evidence oprav.',
        'keywords' => 'servisni protokol, protokol opravy sedacky, dokumentace servisu, reklamacni protokol Natuzzi, evidence oprav nabytku',
        'canonical' => 'https://wgs-service.cz/protokol.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'gdpr' => [
        'title' => 'Zpracovani osobnich udaju GDPR | White Glove Service',
        'description' => 'Informace o zpracovani osobnich udaju podle GDPR. White Glove Service - servis nabytku Natuzzi.',
        'keywords' => 'GDPR, osobni udaje, ochrana udaju, White Glove Service',
        'canonical' => 'https://wgs-service.cz/gdpr.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'aktuality' => [
        'title' => 'Aktuality Natuzzi | Novinky o luxusnim nabytku | Pece o sedacky a kresla | WGS',
        'description' => 'Denne aktuality o znacce Natuzzi. Novinky o luxusnim italskem nabytku, tipy na peci o kozene sedacky a kresla, showroomy v CR. Jak cistit kozenou sedacku, udrzba luxusniho nabytku, trendy v designu sedacek.',
        'keywords' => 'aktuality Natuzzi, novinky nabytek, luxusni nabytek novinky, pece o kozenou sedacku, jak cistit sedacku, udrzba kozene sedacky, tipy na peci o nabytek, Natuzzi showroom, italsky nabytek aktuality, trendy sedacky, designovy nabytek novinky, kozena sedacka pece, kreslo udrzba, pohovka cisteni, White Glove Service aktuality',
        'canonical' => 'https://wgs-service.cz/aktuality.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    // === NOVE SEO STRANKY PRO ORGANICKE VYHLEDAVANI ===
    'nasesluzby' => [
        'title' => 'Servis a opravy Natuzzi | Reklamace sedacek, montaz | White Glove Service',
        'description' => 'Autorizovany servis sedacek Natuzzi v CR a SR. Opravy kozenych i latkovych sedacek, reklamace, montaz, calouneni, renovace. Spoluprace s prednimi ceskymi prodejci. Tel: +420 725 965 826',
        'keywords' => 'servis Natuzzi, opravy sedacek, reklamace sedacky, montaz sedacky, calouneni, renovace sedacky, oprava kozene sedacky, oprava latkove sedacky, Natuzzi oprava, autorizovany servis',
        'canonical' => 'https://wgs-service.cz/nasesluzby.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'onas' => [
        'title' => 'O nas - White Glove Service | Autorizovany servis Natuzzi | Praha, Brno',
        'description' => 'White Glove Service - autorizovany servisni partner Natuzzi s vice nez 5 letou zkusenosti. Certifikovani technici, originalni dily, garance kvality. Servis po cele CR a SR.',
        'keywords' => 'White Glove Service, WGS, autorizovany servis Natuzzi, servis nabytku Praha, servis nabytku Brno, certifikovani technici, originalni dily Natuzzi',
        'canonical' => 'https://wgs-service.cz/onas.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'pozarucni-servis' => [
        'title' => 'Pozarucni servis sedacky | Mimozarucni oprava nabytku | Servis po zaruce',
        'description' => 'Pozarucni a mimozarucni servis sedacek, kresel a pohovek. Opravime vas nabytek i po skonceni zaruky. Profesionalni oprava, puvodni kvalita, dostupne ceny. Cela CR a SR.',
        'keywords' => 'pozarucni servis, mimozarucni servis, servis po zaruce, oprava po zaruce, pozarucni oprava sedacky, mimozarucni oprava kresla, servis nabytku po zaruce, oprava stare sedacky, renovace sedacky',
        'canonical' => 'https://wgs-service.cz/pozarucni-servis.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'neuznana-reklamace' => [
        'title' => 'Neuznana reklamace sedacky? | Co delat kdyz vam neuznali reklamaci | WGS',
        'description' => 'Neuznali vam reklamaci sedacky nebo kresla? Poradime co delat dal. Nabizime nezavisle posouzeni, odborny posudek a moznost opravy za fer cenu. Nezoufejte, reseni existuje.',
        'keywords' => 'neuznana reklamace, zamitnuta reklamace, reklamace sedacky neuznana, co delat kdyz neuznali reklamaci, odvolani reklamace, posudek sedacky, nezavisly posudek nabytku, oprava po neuznane reklamaci',
        'canonical' => 'https://wgs-service.cz/neuznana-reklamace.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'oprava-sedacky' => [
        'title' => 'Oprava sedacky, gauce, pohovky | Profesionalni servis sedacich souprav',
        'description' => 'Profesionalni oprava sedacek, gaucu a pohovek vsech znacek. Opravujeme kozene i latkove sedacky, vymena mechanismu, calouneni, renovace. Svoz z cele CR. Od 205 EUR.',
        'keywords' => 'oprava sedacky, oprava gauce, oprava pohovky, oprava sedaci soupravy, oprava kozene sedacky, oprava latkove sedacky, servis sedacky, renovace sedacky Praha, oprava mechanismu sedacky',
        'canonical' => 'https://wgs-service.cz/oprava-sedacky.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'oprava-kresla' => [
        'title' => 'Oprava kresla | Servis relaxacnich a klasickych kresel | WGS',
        'description' => 'Profesionalni oprava kresel - relaxacnich, klasickych i designovych. Oprava mechanismu, vymena calouneni, renovace kuze. Specializace na Natuzzi. Svoz z cele CR.',
        'keywords' => 'oprava kresla, oprava relaxacniho kresla, servis kresla, oprava mechanismu kresla, renovace kresla, oprava kozeneho kresla, oprava latkoveho kresla, oprava Natuzzi kresla',
        'canonical' => 'https://wgs-service.cz/oprava-kresla.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ],
    'servis-natuzzi' => [
        'title' => 'Servis Natuzzi | Autorizovane opravy a reklamace Natuzzi | WGS',
        'description' => 'Autorizovany servis Natuzzi v Ceske republice a na Slovensku. Opravy, reklamace, montaz a udrzba nabytku Natuzzi Italia, Editions a Softaly. Originalni dily, certifikovani technici.',
        'keywords' => 'servis Natuzzi, Natuzzi servis, Natuzzi oprava, Natuzzi reklamace, autorizovany servis Natuzzi, Natuzzi Italia servis, Natuzzi Editions oprava, Natuzzi Softaly, oprava Natuzzi sedacky',
        'canonical' => 'https://wgs-service.cz/servis-natuzzi.php',
        'og_image' => 'https://wgs-service.cz/assets/img/og-image.png'
    ]
];

/**
 * Vykresli SEO meta tagy pro danou stranku
 *
 * @param string $stranka Nazev stranky (index, novareklamace, cenik, seznam, protokol)
 */
function renderSeoMeta($stranka = 'index') {
    global $seoStranky;

    $seo = $seoStranky[$stranka] ?? $seoStranky['index'];

    echo "\n  <!-- SEO Meta Tags -->\n";
    echo "  <meta name=\"keywords\" content=\"{$seo['keywords']}\">\n";
    echo "  <meta name=\"author\" content=\"White Glove Service s.r.o.\">\n";
    echo "  <meta name=\"robots\" content=\"index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1\">\n";
    echo "  <meta name=\"googlebot\" content=\"index, follow\">\n";
    echo "  <link rel=\"canonical\" href=\"{$seo['canonical']}\">\n";

    // Open Graph (Facebook, LinkedIn)
    echo "\n  <!-- Open Graph / Facebook -->\n";
    echo "  <meta property=\"og:type\" content=\"website\">\n";
    echo "  <meta property=\"og:url\" content=\"{$seo['canonical']}\">\n";
    echo "  <meta property=\"og:title\" content=\"{$seo['title']}\">\n";
    echo "  <meta property=\"og:description\" content=\"{$seo['description']}\">\n";
    echo "  <meta property=\"og:image\" content=\"{$seo['og_image']}\">\n";
    echo "  <meta property=\"og:locale\" content=\"cs_CZ\">\n";
    echo "  <meta property=\"og:site_name\" content=\"White Glove Service\">\n";

    // Twitter Card
    echo "\n  <!-- Twitter Card -->\n";
    echo "  <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    echo "  <meta name=\"twitter:title\" content=\"{$seo['title']}\">\n";
    echo "  <meta name=\"twitter:description\" content=\"{$seo['description']}\">\n";
    echo "  <meta name=\"twitter:image\" content=\"{$seo['og_image']}\">\n";

    // POZNAMKA: Hreflang tagy docasne deaktivovany
    // Web pouziva klientsky JavaScript pro prepinani jazyku (data-lang-* atributy),
    // ne serverove generovane jazykove verze. Hreflang s ?lang= parametry by zpusobil
    // problemy s duplicitnim obsahem (Google by videl cesky obsah na vsech URL).
    // Aktivovat az pri implementaci skutecnych jazykovych verzi (/en/, /it/).

    // Seznam.cz specificke tagy
    echo "\n  <!-- Seznam.cz -->\n";
    echo "  <meta name=\"seznam-wmt\" content=\"wgs-service-verified\">\n";

    // Geo lokace pro lokalni vyhledavani
    // Souradnice pro Do Dubce 364, Praha-Bechovice (vychodni Praha)
    echo "\n  <!-- Geo lokace -->\n";
    echo "  <meta name=\"geo.region\" content=\"CZ\">\n";
    echo "  <meta name=\"geo.placename\" content=\"Praha-Bechovice, Ceska republika\">\n";
    echo "  <meta name=\"geo.position\" content=\"50.0839;14.5991\">\n";
    echo "  <meta name=\"ICBM\" content=\"50.0839, 14.5991\">\n";

    // Doplnkove meta tagy pro prohlizece
    echo "\n  <!-- Prohlizece a kompatibilita -->\n";
    echo "  <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">\n";
    echo "  <meta name=\"format-detection\" content=\"telephone=yes\">\n";
    echo "  <meta name=\"mobile-web-app-capable\" content=\"yes\">\n";
}

/**
 * Vykresli JSON-LD Schema.org strukturovana data
 *
 * @param string $stranka Nazev stranky
 */
function renderSchemaOrg($stranka = 'index') {
    // LocalBusiness schema - zakladni info o firme
    $localBusiness = [
        "@context" => "https://schema.org",
        "@type" => "LocalBusiness",
        "@id" => "https://wgs-service.cz/#organization",
        "name" => "White Glove Service",
        "alternateName" => ["WGS", "WGS Service", "Servis Natuzzi"],
        "description" => "Profesionalni servis a opravy luxusniho nabytku Natuzzi. Opravy sedacek, kresel, pohovek. Cisteni kozenych i latkovych sedacek. Reklamace, precalouneni, montaz, udrzba. Autorizovany servis v Ceske republice.",
        "url" => "https://wgs-service.cz",
        "telephone" => "+420725965826",
        "email" => "reklamace@wgs-service.cz",
        "image" => "https://wgs-service.cz/assets/img/og-image.png",
        "logo" => "https://wgs-service.cz/icon512.png",
        "priceRange" => "110-500 EUR",
        "currenciesAccepted" => "EUR, CZK",
        "paymentAccepted" => "Cash, Credit Card, Bank Transfer",
        "address" => [
            "@type" => "PostalAddress",
            "streetAddress" => "Do Dubce 364",
            "addressLocality" => "Praha - Bechovice",
            "postalCode" => "190 11",
            "addressRegion" => "Praha",
            "addressCountry" => "CZ"
        ],
        "geo" => [
            "@type" => "GeoCoordinates",
            "latitude" => "50.0839",
            "longitude" => "14.5991"
        ],
        "areaServed" => [
            [
                "@type" => "Country",
                "name" => "Ceska republika"
            ],
            [
                "@type" => "Country",
                "name" => "Slovensko"
            ],
            [
                "@type" => "City",
                "name" => "Praha"
            ],
            [
                "@type" => "City",
                "name" => "Brno"
            ],
            [
                "@type" => "City",
                "name" => "Ostrava"
            ],
            [
                "@type" => "City",
                "name" => "Plzen"
            ]
        ],
        "openingHoursSpecification" => [
            [
                "@type" => "OpeningHoursSpecification",
                "dayOfWeek" => ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                "opens" => "08:00",
                "closes" => "17:00"
            ]
        ],
        "knowsAbout" => [
            "Oprava sedacek",
            "Oprava kresel",
            "Oprava pohovek",
            "Cisteni kozenych sedacek",
            "Cisteni latkovych sedacek",
            "Precalouneni nabytku",
            "Reklamace Natuzzi",
            "Servis luxusniho nabytku",
            "Oprava mechanismu sedacky",
            "Renovace nabytku"
        ],
        "sameAs" => []
    ];

    // Service schema - sluzby
    $services = [
        "@context" => "https://schema.org",
        "@type" => "Service",
        "serviceType" => "Servis a opravy nabytku",
        "provider" => [
            "@type" => "LocalBusiness",
            "name" => "White Glove Service",
            "url" => "https://wgs-service.cz"
        ],
        "areaServed" => [
            "@type" => "Country",
            "name" => "Ceska republika"
        ],
        "hasOfferCatalog" => [
            "@type" => "OfferCatalog",
            "name" => "Servisni sluzby nabytku Natuzzi",
            "itemListElement" => [
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => "Oprava sedacky",
                        "description" => "Profesionalni oprava kozenych a latkovych sedacek Natuzzi. Oprava poskozenych sedacek, rozbite kuze, opotrebovaneho calouneni."
                    ],
                    "priceSpecification" => [
                        "@type" => "PriceSpecification",
                        "priceCurrency" => "EUR",
                        "price" => "205",
                        "minPrice" => "205"
                    ]
                ],
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => "Oprava kresla",
                        "description" => "Oprava relaxacnich a klasickych kresel Natuzzi. Oprava mechanismu, calouneni, kuze."
                    ],
                    "priceSpecification" => [
                        "@type" => "PriceSpecification",
                        "priceCurrency" => "EUR",
                        "price" => "165",
                        "minPrice" => "165"
                    ]
                ],
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => "Cisteni sedacky",
                        "description" => "Profesionalni cisteni kozenych i latkovych sedacek. Odstraneni skvrn, renovace povrchu."
                    ]
                ],
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => "Reklamace nabytku Natuzzi",
                        "description" => "Vyrizeni reklamaci nabytku Natuzzi. Autorizovany servis."
                    ]
                ],
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => "Precalouneni sedacky",
                        "description" => "Kompletni precalouneni sedacek, kresel a pohovek. Vymena calouneni, latky, kuze."
                    ],
                    "priceSpecification" => [
                        "@type" => "PriceSpecification",
                        "priceCurrency" => "EUR",
                        "price" => "205",
                        "minPrice" => "205"
                    ]
                ],
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => "Oprava mechanismu sedacky",
                        "description" => "Oprava relax mechanismu a elektrickych dilu. Nefunkcni mechanismus, rozbity relax."
                    ],
                    "priceSpecification" => [
                        "@type" => "PriceSpecification",
                        "priceCurrency" => "EUR",
                        "price" => "45",
                        "minPrice" => "45"
                    ]
                ],
                [
                    "@type" => "Offer",
                    "itemOffered" => [
                        "@type" => "Service",
                        "name" => "Diagnostika nabytku",
                        "description" => "Zjisteni rozsahu poskozeni a posouzeni stavu nabytku."
                    ],
                    "priceSpecification" => [
                        "@type" => "PriceSpecification",
                        "priceCurrency" => "EUR",
                        "price" => "110"
                    ]
                ]
            ]
        ]
    ];

    // WebSite schema
    $website = [
        "@context" => "https://schema.org",
        "@type" => "WebSite",
        "name" => "White Glove Service - Servis nabytku Natuzzi",
        "alternateName" => "WGS Service",
        "url" => "https://wgs-service.cz",
        "description" => "Profesionalni servis a opravy sedacek, kresel, pohovek znacky Natuzzi. Cisteni, reklamace, precalouneni.",
        "inLanguage" => "cs-CZ",
        "potentialAction" => [
            "@type" => "SearchAction",
            "target" => [
                "@type" => "EntryPoint",
                "urlTemplate" => "https://wgs-service.cz/cenik.php?hledat={search_term_string}"
            ],
            "query-input" => "required name=search_term_string"
        ]
    ];

    // BreadcrumbList pro specificke stranky
    $breadcrumbs = null;
    switch ($stranka) {
        case 'cenik':
            $breadcrumbs = [
                "@context" => "https://schema.org",
                "@type" => "BreadcrumbList",
                "itemListElement" => [
                    ["@type" => "ListItem", "position" => 1, "name" => "Domu", "item" => "https://wgs-service.cz/"],
                    ["@type" => "ListItem", "position" => 2, "name" => "Cenik oprav sedacek a nabytku", "item" => "https://wgs-service.cz/cenik.php"]
                ]
            ];
            break;
        case 'novareklamace':
            $breadcrumbs = [
                "@context" => "https://schema.org",
                "@type" => "BreadcrumbList",
                "itemListElement" => [
                    ["@type" => "ListItem", "position" => 1, "name" => "Domu", "item" => "https://wgs-service.cz/"],
                    ["@type" => "ListItem", "position" => 2, "name" => "Objednat servis sedacky", "item" => "https://wgs-service.cz/novareklamace.php"]
                ]
            ];
            break;
        case 'aktuality':
            $breadcrumbs = [
                "@context" => "https://schema.org",
                "@type" => "BreadcrumbList",
                "itemListElement" => [
                    ["@type" => "ListItem", "position" => 1, "name" => "Domu", "item" => "https://wgs-service.cz/"],
                    ["@type" => "ListItem", "position" => 2, "name" => "Aktuality Natuzzi", "item" => "https://wgs-service.cz/aktuality.php"]
                ]
            ];
            break;
    }

    // Vystup JSON-LD
    echo "\n  <!-- Schema.org Structured Data -->\n";
    echo "  <script type=\"application/ld+json\">\n";
    echo "  " . json_encode($localBusiness, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n  </script>\n";

    echo "  <script type=\"application/ld+json\">\n";
    echo "  " . json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n  </script>\n";

    echo "  <script type=\"application/ld+json\">\n";
    echo "  " . json_encode($website, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n  </script>\n";

    if ($breadcrumbs !== null) {
        echo "  <script type=\"application/ld+json\">\n";
        echo "  " . json_encode($breadcrumbs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n  </script>\n";
    }
}

/**
 * Vykresli FAQ Schema.org pro zobrazeni v Google vysledcich
 *
 * @param string $stranka Nazev stranky
 */
function renderFaqSchema($stranka = 'index') {
    // FAQ otazky a odpovedi pro jednotlive stranky
    $faqData = [
        'index' => [
            [
                'otazka' => 'Kolik stoji oprava sedacky?',
                'odpoved' => 'Cena opravy sedacky zacina od 110 EUR za diagnostiku. Konkretni cena zavisi na typu poskozeni a rozsahu opravy. Oprava kozene sedacky stoji od 205 EUR, oprava mechanismu od 45 EUR. Nabizime bezplatnou kalkulaci ceny.'
            ],
            [
                'otazka' => 'Jak dlouho trva oprava sedacky nebo kresla?',
                'odpoved' => 'Standardni oprava sedacky nebo kresla trva 2-4 tydny. Slozitejsi opravy jako kompletni precalouneni mohou trvat 4-6 tydnu. Expresni opravy jsou mozne za priplatek.'
            ],
            [
                'otazka' => 'Opravujete i jine znacky nez Natuzzi?',
                'odpoved' => 'Ano, opravujeme vsechny znacky luxusniho nabytku. Specializujeme se na Natuzzi, ale opravujeme take sedacky a kresla jinych vyrobcu - kozene i latkove.'
            ],
            [
                'otazka' => 'Poskytujete servis v celem Cesku?',
                'odpoved' => 'Ano, poskytujeme servis po cele Ceske republice. Svoz nabytku zajistujeme z Prahy, Brna, Ostravy, Plzne a dalsich mest. Nabidka zahrnuje i Slovensko.'
            ],
            [
                'otazka' => 'Jak objednat opravu sedacky?',
                'odpoved' => 'Opravu sedacky muzete objednat online pres formular na nasem webu, telefonicky na +420 725 965 826 nebo emailem na reklamace@wgs-service.cz. Odpovime do 24 hodin.'
            ],
            [
                'otazka' => 'Jak vycistit kozenou sedacku doma?',
                'odpoved' => 'Pro beznou udrzbu kozene sedacky pouzijte vlhky hadrik a specialni pripravek na kuzi. Vyhnete se agresivnim cisticum. Pro hluboke cisteni a renovaci doporucujeme profesionalni servis.'
            ]
        ],
        'cenik' => [
            [
                'otazka' => 'Kolik stoji oprava kozene sedacky?',
                'odpoved' => 'Oprava kozene sedacky zacina od 205 EUR. Cena zahrnuje diagnostiku, opravu a finalni upravu. Presna cena zavisi na rozsahu poskozeni - praskliny, odreni, ci celkova renovace.'
            ],
            [
                'otazka' => 'Kolik stoji cisteni sedacky?',
                'odpoved' => 'Profesionalni cisteni sedacky stoji od 80 EUR pro latkove sedacky a od 120 EUR pro kozene sedacky. Cena zahrnuje hluboke cisteni, odstraneni skvrn a osetreni materialu.'
            ],
            [
                'otazka' => 'Kolik stoji oprava mechanismu sedacky?',
                'odpoved' => 'Oprava mechanismu sedacky (relax, vyklapeciho systemu) stoji od 45 EUR za praci. K tomu se prictia naklady na nahradni dily podle typu mechanismu.'
            ],
            [
                'otazka' => 'Kolik stoji precalouneni sedacky?',
                'odpoved' => 'Precalouneni sedacky zacina od 205 EUR za praci. Celkova cena zahrnuje material (latka nebo kuze), praci a dopravu. Presnou kalkulaci vam pripravime zdarma.'
            ],
            [
                'otazka' => 'Je diagnostika zdarma?',
                'odpoved' => 'Diagnostika stoji 110 EUR a zahrnuje kompletni posouzeni stavu nabytku a detailni cenovou kalkulaci opravy. Pri realizaci opravy se cena diagnostiky odecita z celkove ceny.'
            ]
        ],
        'novareklamace' => [
            [
                'otazka' => 'Jak podat reklamaci na nabytek Natuzzi?',
                'odpoved' => 'Reklamaci nabytku Natuzzi podejte pres nas online formular. Vyplnte kontaktni udaje, popis problemu a prilozte fotografie. Reklamaci vyridime jako autorizovany servis.'
            ],
            [
                'otazka' => 'Co potrebuji k objednani opravy?',
                'odpoved' => 'K objednani opravy potrebujete: kontaktni udaje, adresu pro svoz nabytku, popis problemu a idealne fotografie poskozeni. Pomaha take cislo faktury nebo zaruky.'
            ],
            [
                'otazka' => 'Jak rychle odpovite na objednavku?',
                'odpoved' => 'Na vsechny objednavky odpovidame do 24 hodin v pracovni dny. Obdrzite potvrzeni objednavky a navrh terminu svozu ci navstevy technika.'
            ],
            [
                'otazka' => 'Musim byt doma pri svozu nabytku?',
                'odpoved' => 'Ano, pri svozu nabytku je nutna pritomnost dospele osoby, ktera preda nabytek a podepise predavaci protokol. Termin svozu si domluvime predem.'
            ]
        ],
        'aktuality' => [
            [
                'otazka' => 'Kde najdu showroom Natuzzi v Cesku?',
                'odpoved' => 'Showroomy Natuzzi najdete v Praze (KARE Design, Centrum nabytku) a v Brne. Aktualni seznam a oteviraci doby najdete v nasich aktualitach nebo na oficialnim webu Natuzzi.'
            ],
            [
                'otazka' => 'Jak casto udrzovat kozenou sedacku?',
                'odpoved' => 'Kozenou sedacku doporucujeme cistit a osetrit specialnim pripravkem kazdych 3-6 mesicu. Pravidelna udrzba prodluzuje zivotnost kuze a zachovava jeji vzhled.'
            ],
            [
                'otazka' => 'Jake jsou trendy v designu sedacek?',
                'odpoved' => 'Aktualni trendy zahrnuji modularni sedacky, minimalisticky design, prirodni materialy a neutralni barvy. Natuzzi nabizi kolekce kombinujici italsky design s modernimi trendy.'
            ]
        ],
        // === FAQ PRO NOVE SEO LANDING PAGES ===
        'pozarucni-servis' => [
            [
                'otazka' => 'Co je pozarucni servis nabytku?',
                'odpoved' => 'Pozarucni servis je oprava nabytku po skonceni zaruky. Opravujeme sedacky, kresla a pohovky vsech znacek vcetne Natuzzi i po letech pouzivani. Pouzivame originalni dily a profesionalni techniky.'
            ],
            [
                'otazka' => 'Kolik stoji pozarucni oprava sedacky?',
                'odpoved' => 'Cena pozarucni opravy zacina od 205 EUR za praci. Konecna cena zavisi na typu poskozeni a potrebnych dilech. Diagnostika stoji 110 EUR a pripoctete se k cene opravy.'
            ],
            [
                'otazka' => 'Opravujete nabytek po zaruce od vsech vyrobcu?',
                'odpoved' => 'Ano, poskytujeme pozarucni servis pro vsechny znacky nabytku. Specializujeme se na Natuzzi, ale opravujeme i sedacky jinych vyrobcu - kozene, latkove i kombinovane.'
            ],
            [
                'otazka' => 'Jak dlouho trva pozarucni oprava?',
                'odpoved' => 'Bezna pozarucni oprava trva 2-4 tydny. Slozitejsi zasahy jako kompletni precalouneni mohou trvat 4-6 tydnu. Termin zavisi i na dostupnosti nahradnich dilu.'
            ]
        ],
        'neuznana-reklamace' => [
            [
                'otazka' => 'Co delat kdyz mi neuznali reklamaci sedacky?',
                'odpoved' => 'Pokud vam byla reklamace zamitnuta, mate vice moznosti: 1) Pozadat o prezkum ci nezavisle posouzeni, 2) Obratit se na Ceskou obchodni inspekci, 3) Nechat si nabytek opravit u nas za fer cenu. Poradime vam s dalsim postupem.'
            ],
            [
                'otazka' => 'Muzete posoudit oprávnenost zamitnuté reklamace?',
                'odpoved' => 'Ano, nabizime odborne posouzeni stavu nabytku. Na zaklade prohlídky vam sdelíme, zda bylo zamitnutí reklamace opravnene a jake mate moznosti. Posouzení stoji 110 EUR.'
            ],
            [
                'otazka' => 'Kolik stoji oprava po neuznane reklamaci?',
                'odpoved' => 'Ceny oprav zacinaji od 205 EUR za praci. Nabizime fer ceny a kvalitni provedení. Pred opravou vzdy obdrzite presnou kalkulaci, aby vás cena nepřekvapila.'
            ],
            [
                'otazka' => 'Jak postupovat pri odvolani proti zamitnute reklamaci?',
                'odpoved' => 'Doporucujeme: 1) Zdokumentovat stav nabytku fotografiemi, 2) Piscemne pozadat prodejce o prezkum, 3) Pripadne se obratit na COI nebo soudniho znalce. Muzeme vam pripravit odborny posudek.'
            ]
        ],
        'oprava-sedacky' => [
            [
                'otazka' => 'Kolik stoji oprava sedacky nebo pohovky?',
                'odpoved' => 'Oprava sedacky zacina od 205 EUR za jeden dil (sedak, operka, podrucka). Kazdy dalsi dil stoji 70 EUR. Celkova cena zavisi na poctu dilu a typu opravy. Nabizime online kalkulacku.'
            ],
            [
                'otazka' => 'Opravujete latkove i kozene sedacky?',
                'odpoved' => 'Ano, opravujeme vsechny typy sedacek - kozene, latkove, semisove i kombinovane. Pracujeme s originálními materiály od vyrobce i kvalitnymi alternativami.'
            ],
            [
                'otazka' => 'Jak probiha oprava sedacky?',
                'odpoved' => 'Proces opravy: 1) Objednate servis online nebo telefonicky, 2) Domluvime termin svozu, 3) Sedacku prevezeme do dilny, 4) Provedeme opravu, 5) Vracime opravenou sedacku. Celý proces trva 2-4 tydny.'
            ],
            [
                'otazka' => 'Svazite sedacku k oprave i z jineho mesta?',
                'odpoved' => 'Ano, zajistujeme svoz sedacek z cele Ceske republiky a Slovenska. Dopravne se pocita podle vzdalenosti od nasi dilny v Praze - pouzijte nasi kalkulacku pro presny vypocet.'
            ],
            [
                'otazka' => 'Opravujete i mechanismy sedacky (relax, vysuv)?',
                'odpoved' => 'Ano, opravujeme vsechny typy mechanismu - manualni relax, elektricke polohovani, vysuvne podnozky i naklápeci operadla. Cena opravy mechanismu zacina od 45 EUR plus dily.'
            ]
        ],
        'oprava-kresla' => [
            [
                'otazka' => 'Kolik stoji oprava kresla?',
                'odpoved' => 'Oprava kresla zacina od 165 EUR za mechanicke opravy bez calouneni. Kompletni oprava vcetne calouneni stoji od 205 EUR. Presnou cenu urcime po diagnostice.'
            ],
            [
                'otazka' => 'Opravujete relaxacni kresla s elektrickym pohonem?',
                'odpoved' => 'Ano, specializujeme se na opravy relaxacnich kresel vcetne elektrickych. Opravujeme motory, ovladace, transformatory i mechanicke casti. Pouzivame originalni i alternativní nahradni dily.'
            ],
            [
                'otazka' => 'Muzete opravit prasklou kuzi na kresle?',
                'odpoved' => 'Ano, opravujeme prasklou, odrenou i jinou poskozenou kuzi na kreslech. Podle rozsahu poskozeni provedeme lokalní opravu nebo kompletni precalouneni. Pouzivame kvalitni materialy.'
            ],
            [
                'otazka' => 'Jak dlouho trva oprava kresla?',
                'odpoved' => 'Bezna oprava kresla trva 1-3 tydny podle slozitosti. Opravy mechanismu jsou rychlejsi (1-2 tydny), precalouneni trva dele (3-4 tydny). Pri objednávce vas informujeme o predpokladanem terminu.'
            ]
        ],
        'servis-natuzzi' => [
            [
                'otazka' => 'Jste autorizovany servis Natuzzi?',
                'odpoved' => 'Ano, jsme autorizovany servisni partner znacky Natuzzi pro Ceskou republiku a Slovensko. Pouzivame originalni dily a postupy schvalene vyrobcem.'
            ],
            [
                'otazka' => 'Opravujete vsechny rady Natuzzi?',
                'odpoved' => 'Ano, opravujeme nabytek vsech rad - Natuzzi Italia, Natuzzi Editions i Natuzzi Softaly. Mame zkusenosti se vsemi typy sedacek, kresel a doplnku.'
            ],
            [
                'otazka' => 'Jak vyridit reklamaci Natuzzi?',
                'odpoved' => 'Reklamaci Natuzzi podejte pres nas online formular. Jako autorizovany servis vyrizujeme reklamace primo s vyrobcem. Potrebujete doklad o koupi a fotografie problemu.'
            ],
            [
                'otazka' => 'Kde sehnat nahradni dily Natuzzi?',
                'odpoved' => 'Originalni nahradni dily Natuzzi objednavame primo od vyrobce z Italie. Dodaci lhuta je obvykle 2-4 tydny. Jako autorizovany servis mame pristup ke kompletnimu sortimentu dilu.'
            ],
            [
                'otazka' => 'Poskytujete zaruku na opravy Natuzzi?',
                'odpoved' => 'Ano, na vsechny opravy poskytujeme zaruku 12 mesicu. Zaruka se vztahuje na provedenou praci i pouzite originalni dily.'
            ]
        ]
    ];

    // Ziskat FAQ pro danou stranku
    $faq = $faqData[$stranka] ?? $faqData['index'];

    // Sestavit FAQ schema
    $faqSchema = [
        "@context" => "https://schema.org",
        "@type" => "FAQPage",
        "mainEntity" => []
    ];

    foreach ($faq as $polozka) {
        $faqSchema['mainEntity'][] = [
            "@type" => "Question",
            "name" => $polozka['otazka'],
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => $polozka['odpoved']
            ]
        ];
    }

    // Vystup JSON-LD
    echo "\n  <!-- FAQ Schema.org -->\n";
    echo "  <script type=\"application/ld+json\">\n";
    echo "  " . json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n  </script>\n";
}

/**
 * Vrati title pro danou stranku
 */
function getSeoTitle($stranka = 'index') {
    global $seoStranky;
    return $seoStranky[$stranka]['title'] ?? $seoStranky['index']['title'];
}

/**
 * Vrati description pro danou stranku
 */
function getSeoDescription($stranka = 'index') {
    global $seoStranky;
    return $seoStranky[$stranka]['description'] ?? $seoStranky['index']['description'];
}

/**
 * Stara funkce pro zpetnou kompatibilitu
 */
if (!function_exists('get_page_seo_meta')) {
    function get_page_seo_meta(string $page = ''): array {
        global $seoStranky;

        if (empty($page)) {
            $page = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        }

        $pageKey = str_replace('.php', '', $page);

        if (isset($seoStranky[$pageKey])) {
            return [
                'title' => $seoStranky[$pageKey]['title'],
                'description' => $seoStranky[$pageKey]['description'],
                'keywords' => $seoStranky[$pageKey]['keywords'],
                'robots' => 'index, follow'
            ];
        }

        return [
            'title' => 'White Glove Service | Servis nabytku Natuzzi',
            'description' => 'Profesionalni servis a opravy luxusniho nabytku Natuzzi.',
            'keywords' => 'servis Natuzzi, oprava sedacky, oprava nabytku',
            'robots' => 'index, follow'
        ];
    }
}
