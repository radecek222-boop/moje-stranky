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
            "latitude" => "50.0755",
            "longitude" => "14.5756"
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
