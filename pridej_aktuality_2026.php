<?php
/**
 * Migrace: Nahrazení všech aktualit novými novinkami Natuzzi pro rok 2026
 *
 * Smaže všechny stávající záznamy a vloží 4 nové aktuality.
 *
 * Zdroje:
 * - https://www.businesswire.com/news/home/20260205779129/en/
 * - https://www.businesswire.com/news/home/20251216911445/en/
 * - https://www.salonemilano.it/en/articles/salone-del-mobile-2026
 * - https://www.salonemilano.it/en/articoli/adi-design-index-2025-compasso-doro
 * - https://www.natuzzi.com
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Aktuality Natuzzi 2026</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 1100px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a1a1a; border-bottom: 3px solid #1a1a1a; padding-bottom: 10px; }
        h2 { color: #333; font-size: 1.1em; margin: 20px 0 8px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 12px; border-radius: 5px; margin: 8px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
                 padding: 12px; border-radius: 5px; margin: 8px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;
                   padding: 12px; border-radius: 5px; margin: 8px 0; }
        .info { background: #e8e8e8; border: 1px solid #ccc; color: #333;
                padding: 12px; border-radius: 5px; margin: 8px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #1a1a1a;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 10px 5px 10px 0; font-weight: 600; cursor: pointer; border: none; font-size: 1em; }
        .btn:hover { background: #333; }
        .btn-back { background: #666; }
        .btn-back:hover { background: #444; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #1a1a1a; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        tr:hover td { background: #f9f9f9; }
        .datum-tag { background: #1a1a1a; color: white; padding: 3px 10px;
                     border-radius: 20px; font-size: 0.85em; font-weight: 600; }
        .clanek-nadpis { font-size: 0.85em; color: #555; margin: 2px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // === OBSAH NOVÝCH AKTUALIT ===

    $aktuality = [];

    // --- AKTUALITA 1: 2026-01-15 - Nové kolekce Circle of Harmony ---
    $obsahCz1 = <<<'EOT'
## KOLEKCE CIRCLE OF HARMONY - ŽIVÁ PŘÍRODA V KAŽDÉM DETAILU

Katalog pro sezónu 2025/2026 s názvem **"Circle of Harmony – Live the Transition"** přináší kolekce s výjimečnou estetikou inspirovanou středomořskou přírodou. Pod vedením kreativního ředitele Pasqualeho Natuzziho Jr. vznikly nové kusy ve spolupráci s předními světovými designéry - Marcelem Wandersem, Elenou Salmistraro, Marcantoniom, Massimem Iosa Ghinim, Patrickem Norgutem a studiem Formafantasma.

Nová kolekce **Apulo** s čalouněním z kůže nebo kožešiny nabízí sofistikovaný vzhled pro moderní interiéry. Modulární rohová pohovka **Timeless** se symetrickým vlnitým tvarem potvrzuje, že luxus spočívá v nadčasovém designu.

[Zobrazit kolekci](https://www.natuzzi.com)

![Natuzzi luxusní pohovka kolekce 2025/2026](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=600&fit=crop)

## POSIDONIA - MULTIFUNKČNÍ DESIGN ELENY SALMISTRARO

Jedním z nejpozoruhodnějších počinů nové sezóny je kolekce **Posidonia** od Eleny Salmistraro. Tato italská designérka, proslulá svou výjimečnou kreativitou, vytvořila multifunkční celek zahrnující hned několik vzájemně propojených kusů.

Součástí kolekce je křeslo **Ensis** s podnožkou, zrcadlo **Anemonia** (nástěnné i volně stojící), textilní police **Lophelia** s lampou, ocelové tácky **Ciambotte** a přenosná LED stolní lampa **Cliona** s USB nabíječem. Pohovka **Calilla** s možností reklinace doplňuje celek a potvrzuje, že dnešní luxusní nábytek musí být především pohodlný a funkční.

[Více o kolekci Posidonia](https://www.natuzzi.com)

## GLOBÁLNÍ PRODEJNÍ SÍŤ - 565 PRODEJEN VE SVĚTĚ

Ke konci roku 2025 distribuuje Natuzzi své kolekce prostřednictvím globální maloobchodní sítě, která zahrnuje **565 monobrandových prodejen**, **487 Natuzzi galerií** a více než 550 kurátorských umístění ve větších multibrandových prostorách.

Tato celosvětová přítomnost značky potvrzuje, že Natuzzi si i přes náročné ekonomické podmínky zachovává silnou pozici na trhu luxusního nábytku a nadále rozšiřuje svůj dosah k zákazníkům po celém světě.

[Vyhledat nejbližší prodejnu](https://www.natuzzi.com)
EOT;

    $obsahEn1 = <<<'EOT'
## CIRCLE OF HARMONY COLLECTION - LIVING NATURE IN EVERY DETAIL

The 2025/2026 season catalogue entitled **"Circle of Harmony – Live the Transition"** presents collections with exceptional aesthetics inspired by Mediterranean nature. Under the creative direction of Pasquale Natuzzi Jr., new pieces were created in collaboration with world-leading designers - Marcel Wanders, Elena Salmistraro, Marcantonio, Massimo Iosa Ghini, Patrick Norguet, and Formafantasma studio.

The new **Apulo collection** with leather or hide upholstery offers a sophisticated look for modern interiors. The modular corner sofa **Timeless** with its symmetrical sinuous shape confirms that luxury lies in timeless design.

[View collection](https://www.natuzzi.com)

![Natuzzi luxury sofa collection 2025/2026](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=600&fit=crop)

## POSIDONIA - MULTIFUNCTIONAL DESIGN BY ELENA SALMISTRARO

One of the most remarkable achievements of the new season is the **Posidonia collection** by Elena Salmistraro. This Italian designer, known for her exceptional creativity, created a multifunctional ensemble comprising several interconnected pieces.

The collection includes the **Ensis armchair** with footrest, the **Anemonia mirror** (wall-mounted or free-standing), the textile **Lophelia** bookcase-floor lamp, **Ciambotte** steel trays, and the portable **Cliona** LED table lamp with USB charger. The **Calilla reclining sofa** completes the ensemble and confirms that today's luxury furniture must above all be comfortable and functional.

[More about the Posidonia collection](https://www.natuzzi.com)

## GLOBAL RETAIL NETWORK - 565 STORES WORLDWIDE

At the end of 2025, Natuzzi distributes its collections through a global retail network comprising **565 monobrand stores**, **487 Natuzzi galleries**, and more than 550 curated placements in larger multi-brand environments.

This worldwide brand presence confirms that Natuzzi, despite challenging economic conditions, maintains a strong position in the luxury furniture market and continues to expand its reach to customers around the world.

[Find your nearest store](https://www.natuzzi.com)
EOT;

    $obsahIt1 = <<<'EOT'
## COLLEZIONE CIRCLE OF HARMONY - LA NATURA VIVA IN OGNI DETTAGLIO

Il catalogo per la stagione 2025/2026 intitolato **"Circle of Harmony – Live the Transition"** presenta collezioni con un'estetica eccezionale ispirata alla natura mediterranea. Sotto la direzione creativa di Pasquale Natuzzi Jr., sono stati creati nuovi pezzi in collaborazione con i principali designer del mondo - Marcel Wanders, Elena Salmistraro, Marcantonio, Massimo Iosa Ghini, Patrick Norguet e lo studio Formafantasma.

La nuova **collezione Apulo** con rivestimento in pelle o pelliccia offre un look sofisticato per gli interni moderni. Il divano angolare modulare **Timeless** con la sua forma sinuosa simmetrica conferma che il lusso risiede nel design senza tempo.

[Visualizza la collezione](https://www.natuzzi.com)

![Divano di lusso Natuzzi collezione 2025/2026](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=600&fit=crop)

## POSIDONIA - DESIGN MULTIFUNZIONALE DI ELENA SALMISTRARO

Uno dei risultati più notevoli della nuova stagione è la **collezione Posidonia** di Elena Salmistraro. Questa designer italiana, nota per la sua eccezionale creatività, ha creato un insieme multifunzionale che comprende diversi pezzi interconnessi.

La collezione include la **poltrona Ensis** con poggiapiedi, lo specchio **Anemonia** (a parete o da terra), la libreria-lampada **Lophelia** in tessuto, i vassoi in acciaio **Ciambotte** e la lampada da tavolo LED portatile **Cliona** con caricatore USB. Il **divano reclinabile Calilla** completa l'insieme e conferma che i mobili di lusso di oggi devono essere prima di tutto comodi e funzionali.

[Maggiori informazioni sulla collezione Posidonia](https://www.natuzzi.com)

## RETE DI VENDITA GLOBALE - 565 NEGOZI IN TUTTO IL MONDO

Alla fine del 2025, Natuzzi distribuisce le sue collezioni attraverso una rete di vendita al dettaglio globale comprendente **565 negozi monomarca**, **487 gallerie Natuzzi** e oltre 550 posizionamenti curati in ambienti multimarca più grandi.

Questa presenza mondiale del brand conferma che Natuzzi, nonostante le difficili condizioni economiche, mantiene una posizione forte nel mercato dell'arredamento di lusso e continua ad espandere la propria portata verso i clienti di tutto il mondo.

[Trova il negozio più vicino](https://www.natuzzi.com)
EOT;

    $aktuality[] = [
        'datum'    => '2026-01-15',
        'svatek'   => 'Alice',
        'komentar' => 'Svátek Alice. Den inspirace novými kolekcemi a italskou elegancí Natuzzi.',
        'obsah_cz' => $obsahCz1,
        'obsah_en' => $obsahEn1,
        'obsah_it' => $obsahIt1,
    ];

    // --- AKTUALITA 2: 2026-02-05 - NYSE + finanční výsledky Q3 2025 ---
    $obsahCz2 = <<<'EOT'
## NATUZZI A NYSE - SITUACE A VÝHLED DO BUDOUCNA

Dne 6. ledna 2026 obdržela společnost Natuzzi S.p.A. (NYSE: NTZ) od Newyorské burzy cenných papírů upozornění na nedodržení standardů pro kontinuální kotaci. Průměrná tržní kapitalizace za 30 obchodních dnů a hodnota vlastního kapitálu k 30. září 2025 byly obě nižší než 50 milionů dolarů.

Podle pravidel NYSE má společnost **18měsíční lhůtu** k obnovení souladu s minimálními požadavky. V tomto období akcie ADR Natuzzi **nadále kótovány na NYSE** a vedení předkládá plán nápravy. Pro zákazníky a partnery tato situace nemá vliv na výrobu, dodávky ani záruky - Natuzzi funguje jako silná italská značka s více než 65 lety tradice.

[Tisková zpráva - BusinessWire](https://www.businesswire.com/news/home/20260205779129/en/Natuzzi-Received-Continued-Listing-Standard-Notice-From-the-NYSE)

## VÝSLEDKY ZA 3. ČTVRTLETÍ 2025 - ROSTOUCÍ HRUBÁ MARŽE

Natuzzi zveřejnil výsledky hospodaření za třetí čtvrtletí 2025. Hrubá marže dosáhla **36,0 %**, oproti **31,8 %** ve stejném období roku 2024 - zlepšení o více než 4 procentní body.

Generální ředitel Pasquale Natuzzi uvedl: *"Obchodní prostředí zůstává náročné, poznamenané přetrvávající geopolitickou nejistotou a makroekonomickými tlaky. Přesto se nám daří zlepšovat efektivitu."* Zlepšení marže bylo dosaženo díky lepšímu prodejnímu mixu a úsporám z optimalizace čínských operací.

[Finanční výsledky Q3 2025](https://www.businesswire.com/news/home/20251216911445/en/Natuzzi-Announces-Financial-Results-for-the-Third-Quarter-of-2025)

## NATUZZI - 65 LET ITALSKÉ TRADICE A CERTIFIKOVANÁ KVALITA

Značka Natuzzi byla založena v roce **1959** Pasqualem Natuzzim a je na Newyorské burze kótována od 13. května 1993. Za více než šest desetiletí si vybudovala reputaci jednoho z nejprestižnějších italských výrobců nábytku s celosvětovým dosahem.

Certifikace **ISO 9001** (kvalita), **ISO 14001** (životní prostředí), **ISO 45001** (bezpečnost práce) a **FSC® Chain of Custody** (odpovědná těžba dřeva) potvrzují závazek k vysokým standardům ve všech oblastech podnikání.

![Natuzzi - italská tradice a řemeslo](https://images.unsplash.com/photo-1567016432779-094069958ea5?w=800&h=600&fit=crop)
EOT;

    $obsahEn2 = <<<'EOT'
## NATUZZI AND NYSE - SITUATION AND OUTLOOK

On January 6, 2026, Natuzzi S.p.A. (NYSE: NTZ) received notice from the New York Stock Exchange that the company was no longer in compliance with the continued listing standards. The 30 trading-day average market capitalization and stockholders' equity as of September 30, 2025 were both below $50 million.

Under NYSE rules, the company has an **18-month cure period** to regain compliance. During this period, Natuzzi's ADR shares **continue trading on the NYSE** and management is submitting a remediation plan. For customers and partners, this situation does not affect production, deliveries, or warranties - Natuzzi operates as a strong Italian brand with more than 65 years of tradition.

[Press release - BusinessWire](https://www.businesswire.com/news/home/20260205779129/en/Natuzzi-Received-Continued-Listing-Standard-Notice-From-the-NYSE)

## Q3 2025 RESULTS - RISING GROSS MARGIN

Natuzzi published financial results for the third quarter of 2025. Gross margin reached **36.0%**, compared to **31.8%** in the same period of 2024 - an improvement of more than 4 percentage points.

CEO Pasquale Natuzzi stated: *"The business environment remains highly challenging, marked by persistent geopolitical uncertainty and macroeconomic headwinds. Nevertheless, we are improving efficiency."* The margin improvement was achieved through a better sales mix and savings from rightsizing Chinese operations.

[Q3 2025 Financial Results](https://www.businesswire.com/news/home/20251216911445/en/Natuzzi-Announces-Financial-Results-for-the-Third-Quarter-of-2025)

## NATUZZI - 65 YEARS OF ITALIAN TRADITION AND CERTIFIED QUALITY

The Natuzzi brand was founded in **1959** by Pasquale Natuzzi and has been listed on the New York Stock Exchange since May 13, 1993. Over more than six decades it has built a reputation as one of Italy's most prestigious furniture manufacturers with worldwide reach.

Certifications **ISO 9001** (quality), **ISO 14001** (environment), **ISO 45001** (workplace safety), and **FSC® Chain of Custody** (responsible timber) confirm a commitment to high standards across all areas of business.

![Natuzzi - Italian tradition and craftsmanship](https://images.unsplash.com/photo-1567016432779-094069958ea5?w=800&h=600&fit=crop)
EOT;

    $obsahIt2 = <<<'EOT'
## NATUZZI E NYSE - SITUAZIONE E PROSPETTIVE

Il 6 gennaio 2026, Natuzzi S.p.A. (NYSE: NTZ) ha ricevuto dalla Borsa di New York un avviso di non conformità agli standard di quotazione continua. La capitalizzazione di mercato media su 30 giorni di negoziazione e il patrimonio netto al 30 settembre 2025 erano entrambi inferiori a 50 milioni di dollari.

Secondo le regole NYSE, la società ha un **periodo di rimedio di 18 mesi** per ripristinare la conformità. In questo periodo, le azioni ADR di Natuzzi **continuano a essere quotate sul NYSE** e il management sta presentando un piano di rimedio. Per clienti e partner, questa situazione non influisce sulla produzione, le consegne o le garanzie - Natuzzi opera come un forte marchio italiano con più di 65 anni di tradizione.

[Comunicato stampa - BusinessWire](https://www.businesswire.com/news/home/20260205779129/en/Natuzzi-Received-Continued-Listing-Standard-Notice-From-the-NYSE)

## RISULTATI DEL 3° TRIMESTRE 2025 - MARGINE LORDO IN CRESCITA

Natuzzi ha pubblicato i risultati finanziari per il terzo trimestre del 2025. Il margine lordo ha raggiunto il **36,0%**, rispetto al **31,8%** nello stesso periodo del 2024 - un miglioramento di oltre 4 punti percentuali.

Il CEO Pasquale Natuzzi ha dichiarato: *"Il contesto commerciale rimane molto sfidante, segnato da persistente incertezza geopolitica e venti contrari macroeconomici. Tuttavia stiamo migliorando l'efficienza."* Il miglioramento del margine è stato ottenuto grazie a un mix di vendite migliore e ai risparmi dall'ottimizzazione delle operazioni cinesi.

[Risultati finanziari Q3 2025](https://www.businesswire.com/news/home/20251216911445/en/Natuzzi-Announces-Financial-Results-for-the-Third-Quarter-of-2025)

## NATUZZI - 65 ANNI DI TRADIZIONE ITALIANA E QUALITÀ CERTIFICATA

Il marchio Natuzzi è stato fondato nel **1959** da Pasquale Natuzzi ed è quotato alla Borsa di New York dal 13 maggio 1993. In oltre sei decenni ha costruito una reputazione come uno dei più prestigiosi produttori di mobili italiani con portata mondiale.

Le certificazioni **ISO 9001** (qualità), **ISO 14001** (ambiente), **ISO 45001** (sicurezza sul lavoro) e **FSC® Chain of Custody** (legno responsabile) confermano l'impegno verso gli standard elevati in tutte le aree aziendali.

![Natuzzi - tradizione e artigianato italiano](https://images.unsplash.com/photo-1567016432779-094069958ea5?w=800&h=600&fit=crop)
EOT;

    $aktuality[] = [
        'datum'    => '2026-02-05',
        'svatek'   => 'Dobromila',
        'komentar' => 'Svátek Dobromily. Den pracovitosti a odhodlání - vlastností, které sdílíme s italskými řemeslníky Natuzzi.',
        'obsah_cz' => $obsahCz2,
        'obsah_en' => $obsahEn2,
        'obsah_it' => $obsahIt2,
    ];

    // --- AKTUALITA 3: 2026-02-15 - Salone del Mobile 2026 ---
    $obsahCz3 = <<<'EOT'
## SALONE DEL MOBILE MILÁN 2026 - 21. DUBNA ZAČÍNÁ VELETRH DESIGNU

**64. ročník** světového veletrhu nábytku **Salone del Mobile.Milano** se uskuteční od **21. do 26. dubna 2026** v milánském výstavišti **Fiera Milano Rho**. Tato prestižní událost každoročně přiláká více než 300 000 návštěvníků a je místem, kde se rodí světové trendy v designu.

Letošní ročník přivítá **více než 1 900 vystavovatelů z 32 zemí** na ploše přes 169 000 čtverečních metrů. Vrátí se také bienální výstavy **EuroCucina** (106 značek ze 17 zemí) a **International Bathroom Exhibition** (163 značek ze 14 zemí). Natuzzi se jako vlajková italská značka tradičně zúčastní a představí nejnovější kolekce.

[Více o Salone del Mobile 2026](https://www.salonemilano.it/en/articles/salone-del-mobile-2026)

![Salone del Mobile Milán 2026](https://images.unsplash.com/photo-1493663284031-b7e3aaa4c7c7?w=800&h=600&fit=crop)

## SALONE CONTRACT - NOVÁ INICIATIVA S ARCHITEKTY OMA

Klíčovou novinkou letošního milánského veletrhu je iniciativa **Salone Contract**, která reaguje na transformaci sektoru kontraktního vybavení. Generální plán byl svěřen světoznámé architektonické kanceláři **Rem Koolhaase a Davida Gianottena z OMA**.

Tato iniciativa otevírá nový segment pro profesionální designéry, architekty, hotely, kanceláře a veřejné prostory. Natuzzi s bohatou zkušeností v kontraktním segmentu sleduje tento vývoj s velkým zájmem.

## SALONE RARITAS - PREMIÉRA LIMITOVANÝCH EDIC

Na 64. ročníku Salone del Mobile debutuje nová sekce **"Salone Raritas: Curated icons, unique objects, and outsider pieces"**. Tato výstavní platforma vytváří přímé propojení světa limitovaných edic, starožitností a vysoké řemeslné výroby s trhem profesionálního designu.

Udržitelnost bude ústředním tématem celého veletrhu 2026 - Salone del Mobile obnovil certifikaci **ISO 20121** pro období 2026-2028, čímž potvrdil závazek k environmentální odpovědnosti.

[Informace pro návštěvníky](https://www.salonemilano.it/en/general-public)
EOT;

    $obsahEn3 = <<<'EOT'
## SALONE DEL MOBILE MILAN 2026 - DESIGN FAIR STARTS APRIL 21

The **64th edition** of the world furniture fair **Salone del Mobile.Milano** will take place from **21 to 26 April 2026** at the Milan exhibition center **Fiera Milano Rho**. This prestigious event attracts more than 300,000 visitors each year and is where global design trends are born.

This year's edition will welcome **more than 1,900 exhibitors from 32 countries** across more than 169,000 square meters. The biennial exhibitions also return: **EuroCucina** (106 brands from 17 countries) and **International Bathroom Exhibition** (163 brands from 14 countries). Natuzzi, as a flagship Italian brand, will traditionally participate and present its latest collections.

[More about Salone del Mobile 2026](https://www.salonemilano.it/en/articles/salone-del-mobile-2026)

![Salone del Mobile Milan 2026](https://images.unsplash.com/photo-1493663284031-b7e3aaa4c7c7?w=800&h=600&fit=crop)

## SALONE CONTRACT - NEW INITIATIVE WITH OMA ARCHITECTS

A key novelty of this year's Milan fair is the **Salone Contract** initiative, which responds to the transformation of the contract furnishing sector. The master plan was entrusted to the world-renowned architectural firm **Rem Koolhaas and David Gianotten from OMA**.

This initiative opens a new segment for professional designers, architects, hotels, offices, and public spaces. Natuzzi, with extensive experience in the contract segment, is following this development with great interest.

## SALONE RARITAS - PREMIERE OF LIMITED EDITIONS

At the 64th edition of Salone del Mobile, a new section makes its debut: **"Salone Raritas: Curated icons, unique objects, and outsider pieces"**. This exhibition platform creates a direct interface between the world of limited editions, antiques, and high-end craftsmanship and the professional design market.

Sustainability will be a central theme of the entire 2026 fair - Salone del Mobile renewed its **ISO 20121** certification for the 2026-2028 period, confirming its commitment to environmental responsibility.

[Visitor information](https://www.salonemilano.it/en/general-public)
EOT;

    $obsahIt3 = <<<'EOT'
## SALONE DEL MOBILE MILANO 2026 - LA FIERA DEL DESIGN INIZIA IL 21 APRILE

La **64ª edizione** della fiera mondiale del mobile **Salone del Mobile.Milano** si terrà dal **21 al 26 aprile 2026** presso il centro fieristico milanese **Fiera Milano Rho**. Questo prestigioso evento attira ogni anno più di 300.000 visitatori ed è il luogo dove nascono le tendenze del design mondiale.

L'edizione di quest'anno accoglierà **più di 1.900 espositori da 32 paesi** su oltre 169.000 metri quadrati. Tornano anche le esposizioni biennali **EuroCucina** (106 marchi da 17 paesi) e **International Bathroom Exhibition** (163 marchi da 14 paesi). Natuzzi, come marchio italiano di punta, parteciperà tradizionalmente e presenterà le sue ultime collezioni.

[Maggiori informazioni sul Salone del Mobile 2026](https://www.salonemilano.it/en/articles/salone-del-mobile-2026)

![Salone del Mobile Milano 2026](https://images.unsplash.com/photo-1493663284031-b7e3aaa4c7c7?w=800&h=600&fit=crop)

## SALONE CONTRACT - NUOVA INIZIATIVA CON GLI ARCHITETTI OMA

Una novità fondamentale della fiera milanese di quest'anno è l'iniziativa **Salone Contract**, che risponde alla trasformazione del settore dell'arredamento contract. Il piano generale è stato affidato alla rinomata azienda di architettura mondiale **Rem Koolhaas e David Gianotten di OMA**.

Questa iniziativa apre un nuovo segmento per designer professionisti, architetti, hotel, uffici e spazi pubblici. Natuzzi, con una vasta esperienza nel segmento contract, segue questo sviluppo con grande interesse.

## SALONE RARITAS - PRIMA DI EDIZIONI LIMITATE

Alla 64ª edizione del Salone del Mobile debutta una nuova sezione: **"Salone Raritas: Curated icons, unique objects, and outsider pieces"**. Questa piattaforma espositiva crea un'interfaccia diretta tra il mondo delle edizioni limitate, dell'antiquariato e dell'artigianato di alta qualità e il mercato del design professionale.

La sostenibilità sarà tema centrale dell'intera fiera 2026 - il Salone del Mobile ha rinnovato la certificazione **ISO 20121** per il periodo 2026-2028, confermando l'impegno verso la responsabilità ambientale.

[Informazioni per i visitatori](https://www.salonemilano.it/en/general-public)
EOT;

    $aktuality[] = [
        'datum'    => '2026-02-15',
        'svatek'   => 'Jiřina',
        'komentar' => 'Svátek Jiřiny. Stejně jako tento jarní květ, i nový design Natuzzi přináší svěžest a eleganci.',
        'obsah_cz' => $obsahCz3,
        'obsah_en' => $obsahEn3,
        'obsah_it' => $obsahIt3,
    ];

    // --- AKTUALITA 4: 2026-02-23 - Mindfull + Rijád + Péče ---
    $obsahCz4 = <<<'EOT'
## MINDFULL - KANDIDÁT NA PRESTIŽNÍ CENU COMPASSO D'ORO

Produkt **Mindfull od Natuzzi**, navržený týmem Natuzzi Design Centre, byl zařazen do prestižního výběru **ADI Design Index 2025**, který nominuje kandidáty na jednu z nejvýznamnějších designérských cen světa - **Compasso d'Oro**.

Mindfull integruje technologie schopné generovat **personalizovaný komfort** - systém automaticky přizpůsobuje parametry sedění individuálním potřebám uživatele. Tato inovace ukazuje, jakým směrem se vyvíjí luxusní nábytek 21. století: spojení italského řemesla s chytrou technologií.

ADI Design Index 2025 byl vystaven v Miláně v **ADI Design Museum** (říjen 2025) a v sicilském **Agrigento** - italském Hlavním městě kultury 2025 (listopad 2025).

[Více o ADI Design Index 2025](https://www.salonemilano.it/en/articoli/adi-design-index-2025-compasso-doro)

## NATUZZI V RIJÁDU - ITALSKÝ DESIGN NA ARABSKÉM POLOOSTROVĚ

Salone del Mobile expanduje na Arabský poloostrov a pro svůj debut v **Rijádu** vybral více než 35 italských firem, mezi nimiž figuruje i **Natuzzi Italia**. Výběr byl proveden na základě kritérií **kvality, inovace a udržitelnosti**.

Tato expanze otevírá Natuzzi nové trhy a potvrzuje, že italský luxusní nábytek nachází zákazníky po celém světě. Rijád patří mezi nejrychleji rostoucí trhy s prémiovou rezidenční výstavbou.

## PÉČE O KOŽENÝ NÁBYTEK - JARNÍ TIPY OD ODBORNÍKŮ NATUZZI

Příchod jara je ideálním časem pro důkladnou péči o kožený nábytek. Odborníci Natuzzi doporučují tyto základní kroky:

**Čištění:** Používejte měkký hadřík navlhčený ve vlažné vodě. Nikdy nepoužívejte agresivní čisticí prostředky ani rozpouštědla. **Výživa kůže:** Jednou za 3-6 měsíců ošetřete povrch specializovaným kondicionérem na kůži. **Ochrana před sluncem:** Přímé sluneční záření vysoušuje a odbarvuje kůži - umístěte nábytek mimo přímý dosah oken. **Větrání:** Pravidelné větrání místnosti zabraňuje nadměrné vlhkosti, která poškozuje kůži i dřevěné části.

Pravidelná péče prodlužuje životnost vašeho luxusního nábytku Natuzzi o mnoho let a zachovává jeho původní krásu.

![Péče o kožený nábytek Natuzzi](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=600&fit=crop)
EOT;

    $obsahEn4 = <<<'EOT'
## MINDFULL - CANDIDATE FOR THE PRESTIGIOUS COMPASSO D'ORO AWARD

The **Mindfull product from Natuzzi**, designed by the Natuzzi Design Centre team, has been included in the prestigious **ADI Design Index 2025** selection, which nominates candidates for one of the world's most significant design awards - the **Compasso d'Oro**.

Mindfull integrates technologies capable of generating **personalized comfort** - the system automatically adjusts seating parameters to the individual needs of the user. This innovation shows the direction in which luxury furniture is evolving in the 21st century: combining Italian craftsmanship with smart technology.

ADI Design Index 2025 was exhibited in Milan at the **ADI Design Museum** (October 2025) and in **Agrigento**, Sicily - the Italian Capital of Culture 2025 (November 2025).

[More about ADI Design Index 2025](https://www.salonemilano.it/en/articoli/adi-design-index-2025-compasso-doro)

## NATUZZI IN RIYADH - ITALIAN DESIGN ON THE ARABIAN PENINSULA

Salone del Mobile is expanding to the Arabian Peninsula and for its debut in **Riyadh** has selected more than 35 Italian companies, including **Natuzzi Italia**. The selection was made based on criteria of **quality, innovation, and sustainability**.

This expansion opens new markets for Natuzzi and confirms that Italian luxury furniture finds customers throughout the world. Riyadh is among the fastest-growing markets for premium residential construction.

## LEATHER FURNITURE CARE - SPRING TIPS FROM NATUZZI EXPERTS

The arrival of spring is the ideal time for thorough leather furniture care. Natuzzi experts recommend these basic steps:

**Cleaning:** Use a soft cloth dampened with lukewarm water. Never use aggressive cleaning agents or solvents. **Leather nourishment:** Every 3-6 months, treat the surface with a specialized leather conditioner. **Sun protection:** Direct sunlight dries out and discolors leather - place furniture away from direct window exposure. **Ventilation:** Regular room ventilation prevents excessive humidity that damages both leather and wooden parts.

Regular care extends the life of your Natuzzi luxury furniture for many years while preserving its original beauty.

![Natuzzi leather furniture care](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=600&fit=crop)
EOT;

    $obsahIt4 = <<<'EOT'
## MINDFULL - CANDIDATO AL PRESTIGIOSO PREMIO COMPASSO D'ORO

Il prodotto **Mindfull di Natuzzi**, progettato dal team del Natuzzi Design Centre, è stato incluso nella prestigiosa selezione **ADI Design Index 2025**, che nomina i candidati a uno dei più importanti premi di design al mondo - il **Compasso d'Oro**.

Mindfull integra tecnologie capaci di generare **comfort personalizzato** - il sistema regola automaticamente i parametri della seduta alle esigenze individuali dell'utente. Questa innovazione mostra la direzione in cui si sta evolvendo il mobile di lusso del XXI secolo: la combinazione dell'artigianato italiano con la tecnologia intelligente.

ADI Design Index 2025 è stato esposto a Milano presso l'**ADI Design Museum** (ottobre 2025) e ad **Agrigento**, Sicilia - Capitale Italiana della Cultura 2025 (novembre 2025).

[Maggiori informazioni sull'ADI Design Index 2025](https://www.salonemilano.it/en/articoli/adi-design-index-2025-compasso-doro)

## NATUZZI A RIYADH - IL DESIGN ITALIANO NELLA PENISOLA ARABICA

Il Salone del Mobile si espande nella Penisola Arabica e per il suo debutto a **Riyadh** ha selezionato più di 35 aziende italiane, tra cui **Natuzzi Italia**. La selezione è stata effettuata sulla base di criteri di **qualità, innovazione e sostenibilità**.

Questa espansione apre nuovi mercati per Natuzzi e conferma che i mobili di lusso italiani trovano clienti in tutto il mondo. Riyadh è tra i mercati in più rapida crescita per la costruzione residenziale di lusso.

## CURA DEI MOBILI IN PELLE - CONSIGLI PRIMAVERILI DAGLI ESPERTI NATUZZI

L'arrivo della primavera è il momento ideale per una cura approfondita dei mobili in pelle. Gli esperti Natuzzi raccomandano questi passaggi fondamentali:

**Pulizia:** Utilizzare un panno morbido inumidito con acqua tiepida. Non utilizzare mai detergenti aggressivi o solventi. **Nutrimento della pelle:** Ogni 3-6 mesi, trattare la superficie con un condizionatore per pelle specializzato. **Protezione dal sole:** La luce solare diretta secca e decolora la pelle - posizionare i mobili lontano dall'esposizione diretta alle finestre. **Ventilazione:** La ventilazione regolare della stanza previene l'eccessiva umidità che danneggia sia la pelle che le parti in legno.

La cura regolare prolunga la vita dei vostri mobili di lusso Natuzzi per molti anni preservandone la bellezza originale.

![Cura dei mobili in pelle Natuzzi](https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=600&fit=crop)
EOT;

    $aktuality[] = [
        'datum'    => '2026-02-23',
        'svatek'   => 'Svatopluk',
        'komentar' => 'Svátek Svatopluka. Den inspirace pro váš domov - italská elegance Natuzzi v každém detailu.',
        'obsah_cz' => $obsahCz4,
        'obsah_en' => $obsahEn4,
        'obsah_it' => $obsahIt4,
    ];

    // === NAČÍST STÁVAJÍCÍ ZÁZNAMY ===
    $stmtPocet = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_natuzzi_aktuality");
    $pocetStavajicich = (int)$stmtPocet->fetch(PDO::FETCH_ASSOC)['pocet'];

    echo "<h1>Nahrazení aktualit - novinky Natuzzi 2026</h1>";

    echo "<div class='warning'>";
    echo "<strong>Tato operace SMAŽE všechny stávající aktuality (" . $pocetStavajicich . " záznamů) a nahradí je 4 novými aktualitami pro rok 2026.</strong>";
    echo "</div>";

    // Přehled nových aktualit
    echo "<h2>Nové aktuality, které budou vloženy:</h2>";
    echo "<table>
        <thead>
            <tr><th>Datum</th><th>Svátek</th><th>Články v aktualitě</th></tr>
        </thead>
        <tbody>";

    foreach ($aktuality as $akt) {
        // Najít nadpisy článků
        preg_match_all('/^## (.+)$/m', $akt['obsah_cz'], $shody);
        $nadpisy = $shody[1] ?? [];

        echo "<tr>";
        echo "<td><span class='datum-tag'>" . htmlspecialchars($akt['datum']) . "</span></td>";
        echo "<td>" . htmlspecialchars($akt['svatek']) . "</td>";
        echo "<td>";
        foreach ($nadpisy as $nadpis) {
            echo "<div class='clanek-nadpis'>" . htmlspecialchars($nadpis) . "</div>";
        }
        echo "</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";

    // === SPUŠTĚNÍ ===
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        echo "<h2>Průběh migrace:</h2>";

        $pdo->beginTransaction();

        try {
            // 1. Smazat všechny stávající záznamy
            $smazano = $pdo->exec("DELETE FROM wgs_natuzzi_aktuality");
            echo "<div class='info'>Smazáno stávajících záznamů: <strong>{$smazano}</strong></div>";

            // 2. Vložit nové záznamy
            $vlozeno = 0;
            foreach ($aktuality as $akt) {
                $zdroje = json_encode([
                    'created_by' => 'admin_migration',
                    'user_id'    => $_SESSION['user_id'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'migration'  => 'pridej_aktuality_2026.php',
                    'sources'    => [
                        'https://www.businesswire.com/news/home/20260205779129/en/',
                        'https://www.businesswire.com/news/home/20251216911445/en/',
                        'https://www.salonemilano.it/en/articles/salone-del-mobile-2026',
                        'https://www.salonemilano.it/en/articoli/adi-design-index-2025-compasso-doro',
                        'https://www.natuzzi.com'
                    ]
                ], JSON_UNESCAPED_UNICODE);

                $stmt = $pdo->prepare("
                    INSERT INTO wgs_natuzzi_aktuality
                    (datum, svatek_cz, komentar_dne, obsah_cz, obsah_en, obsah_it, zdroje_json, vygenerovano_ai, created_by_admin)
                    VALUES
                    (:datum, :svatek, :komentar, :obsah_cz, :obsah_en, :obsah_it, :zdroje, FALSE, TRUE)
                ");

                $stmt->execute([
                    'datum'    => $akt['datum'],
                    'svatek'   => $akt['svatek'],
                    'komentar' => $akt['komentar'],
                    'obsah_cz' => $akt['obsah_cz'],
                    'obsah_en' => $akt['obsah_en'],
                    'obsah_it' => $akt['obsah_it'],
                    'zdroje'   => $zdroje,
                ]);

                $novyId = $pdo->lastInsertId();
                $pocetClanku = preg_match_all('/^## /m', $akt['obsah_cz']);
                echo "<div class='success'>Vloženo: <strong>" . htmlspecialchars($akt['datum']) . "</strong> (ID: {$novyId}) - svátek: " . htmlspecialchars($akt['svatek']) . " - {$pocetClanku} články</div>";
                $vlozeno++;
            }

            $pdo->commit();

            echo "<div class='success' style='margin-top: 20px; font-size: 1.1em; padding: 20px;'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "Smazáno starých záznamů: {$smazano} | Vloženo nových aktualit: {$vlozeno}";
            echo "</div>";

            error_log(sprintf(
                "ADMIN MIGRATION pridej_aktuality_2026.php: User %d deleted %d old records, inserted %d new aktuality",
                $_SESSION['user_id'] ?? 0,
                $smazano,
                $vlozeno
            ));

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA - ROLLBACK PROVEDEN:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

        echo "<p style='margin-top: 20px;'>";
        echo "<a href='/aktuality.php' class='btn'>Zobrazit aktuality</a> ";
        echo "<a href='pridej_aktuality_2026.php' class='btn btn-back'>Zpět</a>";
        echo "</p>";

    } else {
        echo "<p style='margin-top: 25px;'>";
        echo "<a href='?execute=1' class='btn btn-danger'>SMAZAT STARÉ A VLOŽIT NOVÉ AKTUALITY</a> ";
        echo "<a href='/aktuality.php' class='btn btn-back'>Zpět na aktuality</a>";
        echo "</p>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>NEOČEKÁVANÁ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
