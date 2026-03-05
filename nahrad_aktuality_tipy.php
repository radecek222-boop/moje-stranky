<?php
/**
 * Migrace: Nahrazení Natuzzi aktualit obecnými tipy péče o nábytek
 *
 * Smaže všechny stávající záznamy a vloží 4 nové tematické sady tipů.
 * Obsah je zaměřen na péči o čalouněný nábytek obecně (ne jen Natuzzi).
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
    <title>Migrace: Naše tipy - péče o nábytek</title>
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

    // === OBSAH NOVÝCH TIPŮ ===

    $aktuality = [];

    // --- TIP 1: Péče o kožený nábytek ---
    $obsahCz1 = <<<'EOT'
## JAK SPRÁVNĚ ČISTIT KOŽENÝ NÁBYTEK

Kůže je přírodní materiál, který potřebuje pravidelnou péči — jinak praská, vysychá a ztrácí lesk. Základem je suchý nebo mírně vlhký hadřík pro každodenní otírání prachu. **Nikdy nepoužívejte agresivní čisticí prostředky, acetón ani ředidla** — poškodí povrchovou úpravu kůže nenávratně.

Pro hlubší čištění použijte speciální přípravek na kůži (pH-neutrální). Naneste malé množství na hadřík, ne přímo na kůži, a krouživými pohyby čistěte povrch. Po vyčištění vždy aplikujte hydratační krém na kůži — udržíte pružnost a zabráníte praskání.

**Frekvence péče:** Pravidelné čištění každých 3–6 měsíců, impregnace 1× ročně.

[Objednat profesionální čištění](/novareklamace.php)


## IMPREGNACE KŮŽE — PROČ JE KLÍČOVÁ

Impregnace vytváří ochrannou vrstvu, která odpuzuje nečistoty a vlhkost. Světlé kůže jsou obzvláště náchylné na skvrny od džínů, inkoustu a tuku — impregnace toto riziko výrazně snižuje.

Přípravek naneste rovnoměrně na čistou a suchou kůži. Nechte vstřebat 15–20 minut, přebytek setřete. **Vyhněte se přímému slunečnímu záření** během schnutí. Po impregnaci kůže nejen ochrání, ale i zjemní na omak.

Profesionální impregnace v naší dílně zahrnuje také ošetření švů a záhybů, kde se nečistoty hromadí nejčastěji.

[Zjistit cenu péče o kůži](/cenik.php)

## CO DĚLAT KDYŽ SE KŮŽE ODÍRÁ NEBO PRASKÁ

Odřená nebo popraskající kůže není nutně důvod ke koupi nové sedačky. V takových případech je nejlepším krokem **kontaktovat výrobce nebo dodavatele nábytku** — u prémiových značek je totiž možné nechat vyměnit pouze potah přímo u zákazníka doma, bez nutnosti odvážet sedačku.

Výměna potahu na místě je výrazně úspornější řešení než nákup nového kusu. Technik přiveze nový potah ve správné kůži a barevném provedení a provede výměnu v pohodlí vašeho domova. Celý zásah trvá zpravidla několik hodin.

Pokud si nejste jisti, zda váš výrobce tuto možnost nabízí, obraťte se na prodejce — nebo nás kontaktujte, rádi vám poradíme, jak postupovat.

[Kontaktovat nás pro poradenství](/novareklamace.php)
EOT;

    $obsahEn1 = <<<'EOT'
## HOW TO PROPERLY CLEAN LEATHER FURNITURE

Leather is a natural material that needs regular care — otherwise it cracks, dries out and loses its shine. The basics are a dry or slightly damp cloth for daily dusting. **Never use aggressive cleaning agents, acetone or solvents** — they will irreversibly damage the leather surface finish.

For deeper cleaning, use a special leather cleaner (pH-neutral). Apply a small amount to a cloth, not directly to the leather, and clean the surface in circular motions. After cleaning, always apply leather conditioner — it maintains elasticity and prevents cracking.

**Care frequency:** Regular cleaning every 3–6 months, waterproofing once a year.

[Order professional cleaning](/novareklamace.php)


## LEATHER WATERPROOFING — WHY IT'S CRUCIAL

Waterproofing creates a protective layer that repels dirt and moisture. Light-coloured leathers are particularly susceptible to stains from jeans, ink and grease — waterproofing significantly reduces this risk.

Apply the product evenly to clean and dry leather. Allow to absorb for 15–20 minutes, wipe off excess. **Avoid direct sunlight** during drying. After waterproofing, the leather is not only protected but also feels softer.

Professional waterproofing at our workshop also includes treating seams and folds where dirt accumulates most frequently.

[Find out the price of leather care](/cenik.php)

## WHAT TO DO WHEN LEATHER WEARS OR CRACKS

Worn or cracking leather is not necessarily a reason to buy a new sofa. In such cases, the best step is to **contact the furniture manufacturer or retailer** — with premium brands it is often possible to have only the cover replaced on-site at the customer's home, without the need to transport the sofa.

On-site cover replacement is a significantly more economical solution than purchasing a new piece. The technician brings a new cover in the correct leather and colour specification and carries out the replacement in the comfort of your home. The entire procedure typically takes a few hours.

If you are unsure whether your manufacturer offers this option, contact your retailer — or get in touch with us and we will be happy to advise you on how to proceed.

[Contact us for advice](/novareklamace.php)
EOT;

    $obsahIt1 = <<<'EOT'
## COME PULIRE CORRETTAMENTE I MOBILI IN PELLE

La pelle è un materiale naturale che necessita di cure regolari — altrimenti si crepa, si secca e perde la lucentezza. La base è un panno asciutto o leggermente umido per spolverare quotidianamente. **Non utilizzare mai detergenti aggressivi, acetone o solventi** — danneggerebbero irreversibilmente il trattamento superficiale della pelle.

Per una pulizia più profonda, utilizzare un detergente specifico per pelle (pH-neutro). Applicare una piccola quantità su un panno, non direttamente sulla pelle, e pulire la superficie con movimenti circolari. Dopo la pulizia, applicare sempre una crema idratante per pelle — mantiene l'elasticità e previene le crepe.

**Frequenza delle cure:** Pulizia regolare ogni 3–6 mesi, impermeabilizzazione 1 volta all'anno.

[Prenota pulizia professionale](/novareklamace.php)


## IMPERMEABILIZZAZIONE DELLA PELLE — PERCHÉ È FONDAMENTALE

L'impermeabilizzazione crea uno strato protettivo che respinge sporco e umidità. Le pelli di colore chiaro sono particolarmente suscettibili alle macchie di jeans, inchiostro e grasso — l'impermeabilizzazione riduce significativamente questo rischio.

Applicare il prodotto uniformemente sulla pelle pulita e asciutta. Lasciare assorbire per 15–20 minuti, eliminare l'eccesso. **Evitare la luce solare diretta** durante l'asciugatura. Dopo l'impermeabilizzazione, la pelle non è solo protetta ma risulta anche più morbida al tatto.

[Scoprire il prezzo della cura della pelle](/cenik.php)

## COSA FARE QUANDO LA PELLE SI CONSUMA O SI CREPOLA

La pelle consumata o screpolata non è necessariamente un motivo per acquistare un nuovo divano. In questi casi, il passo migliore è **contattare il produttore o il rivenditore del mobile** — con i marchi premium è spesso possibile far sostituire solo il rivestimento direttamente a casa del cliente, senza necessità di trasportare il divano.

La sostituzione del rivestimento in loco è una soluzione significativamente più economica rispetto all'acquisto di un nuovo pezzo. Il tecnico porta il nuovo rivestimento nella pelle e nella colorazione corretta e lo sostituisce comodamente a casa vostra. L'intera procedura richiede generalmente poche ore.

Se non siete sicuri che il vostro produttore offra questa opzione, contattate il rivenditore — o mettetevi in contatto con noi e saremo felici di consigliarvi su come procedere.

[Contattateci per una consulenza](/novareklamace.php)
EOT;

    $aktuality[] = [
        'datum'    => '2026-01-10',
        'svatek'   => 'Péče o kožený nábytek',
        'komentar' => 'Kompletní průvodce péčí o kožené sedačky, křesla a pohovky',
        'obsah_cz' => $obsahCz1,
        'obsah_en' => $obsahEn1,
        'obsah_it' => $obsahIt1,
    ];

    // --- TIP 2: Péče o látkový nábytek ---
    $obsahCz2 = <<<'EOT'
## LÁTKOVÉ SEDAČKY — JAK UDRŽOVAT ČISTOTU

Látkové sedačky jsou oblíbené pro svou pohodlnost a rozmanitost designu, ale vyžadují pravidelnou péči. Základem je **pravidelné vysávání** — ideálně jednou týdně nástavcem na čalounění. Odstraňujete tím prach, roztoče a drobky, které jinak degradují vlákna.

Čerstvé skvrny vždy ošetřujte okamžitě — čím déle čekáte, tím hůře se odstraňují. Nikdy neskvrnu netřete, ale **jemně přikládejte čistý hadřík** od okraje ke středu. Na vodou ředitelné skvrny (káva, džus) použijte vlažnou vodu s kapkou jaru. Na olejové skvrny posypte talkem, nechte 30 minut vstřebat, pak vysajte.

[Objednat profesionální čištění látky](/novareklamace.php)

## HLUBŠÍ ČIŠTĚNÍ — PÁRA NEBO SUCHÝ ČISTICÍ PROSTŘEDEK

Jednou ročně je vhodné provést důkladnější čištění čalounění. Máte dvě možnosti: čištění párou nebo suchou metodou.

**Parní čištění** je vhodné pro většinu pevných tkanin — pára proniká do vláken, uvolňuje nečistoty a dezinfikuje. Pozor: některé delikátní látky (samet, len, hedvábí) mohou být poškozeny párou — vždy zkontrolujte štítek.

**Suchá metoda** pomocí pěnových čisticích prostředků je šetrnější pro citlivé materiály. Pěna se nanáší, suší a po zaschnutí vysává spolu s nečistotami.

V obou případech nechte čalounění **důkladně vysušit** před opětovným používáním — vlhkost v čalounění způsobuje plíseň a nepříjemný zápach.

[Zjistit cenu čištění látky](/cenik.php)

## OCHRANA LÁTKOVÉHO ČALOUNĚNÍ — IMPREGNACE A POTAHY

Moderní impregnační přípravky pro textil vytvářejí neviditelnou ochrannou vrstvu, která odpuzuje tekutiny a nečistoty. Zejména pokud máte děti nebo domácí mazlíčky, impregnace výrazně prodlouží životnost čalounění.

Alternativou jsou snímatelné potahy na sedací polštáře, které lze prát v pračce. Jsou praktické a chrání originální čalounění. Vyměňte je jednou za sezónu nebo po větším znečištění.

**Tip:** Otáčejte sedací polštáře každých několik týdnů, aby se opotřebovávaly rovnoměrně na obou stranách.

[Poptávka péče o čalounění](/novareklamace.php)
EOT;

    $obsahEn2 = <<<'EOT'
## FABRIC SOFAS — HOW TO MAINTAIN CLEANLINESS

Fabric sofas are popular for their comfort and variety of designs, but require regular care. The basics are **regular vacuuming** — ideally once a week with an upholstery attachment. This removes dust, mites and crumbs that otherwise degrade the fibres.

Always treat fresh stains immediately — the longer you wait, the harder they are to remove. Never rub a stain, but **gently press a clean cloth** from the edge to the centre. For water-soluble stains (coffee, juice) use lukewarm water with a drop of washing-up liquid. For oily stains, sprinkle with talcum powder, leave for 30 minutes to absorb, then vacuum.

[Order professional fabric cleaning](/novareklamace.php)

## DEEPER CLEANING — STEAM OR DRY CLEANER

Once a year it is advisable to carry out a more thorough cleaning of the upholstery. You have two options: steam cleaning or dry method.

**Steam cleaning** is suitable for most firm fabrics — steam penetrates the fibres, loosens dirt and disinfects. Note: some delicate fabrics (velvet, linen, silk) can be damaged by steam — always check the label.

**Dry method** using foam cleaners is gentler for sensitive materials. The foam is applied, dried and vacuumed after drying along with the dirt.

In both cases, allow the upholstery to **dry thoroughly** before using again — moisture in the upholstery causes mould and unpleasant odour.

[Find out fabric cleaning price](/cenik.php)

## PROTECTING FABRIC UPHOLSTERY — WATERPROOFING AND COVERS

Modern waterproofing products for textiles create an invisible protective layer that repels liquids and dirt. Especially if you have children or pets, waterproofing will significantly extend the life of the upholstery.

An alternative is removable covers for seat cushions that can be washed in the washing machine. They are practical and protect the original upholstery. Replace them once per season or after major soiling.

**Tip:** Turn seat cushions every few weeks so they wear evenly on both sides.

[Request upholstery care](/novareklamace.php)
EOT;

    $obsahIt2 = <<<'EOT'
## DIVANI IN TESSUTO — COME MANTENERE LA PULIZIA

I divani in tessuto sono apprezzati per il comfort e la varietà di design, ma richiedono cure regolari. La base è la **pulizia regolare con l'aspirapolvere** — idealmente una volta alla settimana con l'accessorio per tappezzerie. In questo modo si rimuovono polvere, acari e briciole che altrimenti degradano le fibre.

Trattare sempre immediatamente le macchie fresche — più si aspetta, più difficile è rimuoverle. Non strofinare mai la macchia, ma **tamponare delicatamente con un panno pulito** dal bordo verso il centro. Per macchie solubili in acqua (caffè, succo) utilizzare acqua tiepida con una goccia di detersivo. Per macchie di grasso, cospargere con talco, lasciare assorbire per 30 minuti, poi aspirare.

[Prenota pulizia professionale del tessuto](/novareklamace.php)

## PULIZIA PROFONDA — VAPORE O DETERGENTE A SECCO

Una volta all'anno è consigliabile eseguire una pulizia più approfondita della tappezzeria. Ci sono due opzioni: pulizia a vapore o metodo a secco.

**La pulizia a vapore** è adatta alla maggior parte dei tessuti compatti — il vapore penetra nelle fibre, scioglie lo sporco e disinfetta. Attenzione: alcuni tessuti delicati (velluto, lino, seta) possono essere danneggiati dal vapore — controllare sempre l'etichetta.

**Il metodo a secco** con detergenti in schiuma è più delicato per i materiali sensibili. La schiuma viene applicata, asciugata e aspirata dopo l'asciugatura insieme allo sporco.

[Scoprire il prezzo della pulizia del tessuto](/cenik.php)

## PROTEZIONE DELLA TAPPEZZERIA IN TESSUTO — IMPERMEABILIZZAZIONE E COPERTE

I moderni prodotti impermeabilizzanti per tessuti creano uno strato protettivo invisibile che respinge liquidi e sporco. Soprattutto se si hanno bambini o animali domestici, l'impermeabilizzazione prolungherà significativamente la vita della tappezzeria.

Un'alternativa sono le fodere removibili per i cuscini del sedile, lavabili in lavatrice. Sono pratiche e proteggono la tappezzeria originale. Sostituirle una volta per stagione o dopo un forte sporco.

**Consiglio:** Girare i cuscini del sedile ogni poche settimane in modo che si consumino uniformemente su entrambi i lati.

[Richiesta cura della tappezzeria](/novareklamace.php)
EOT;

    $aktuality[] = [
        'datum'    => '2026-01-25',
        'svatek'   => 'Péče o látkový nábytek',
        'komentar' => 'Jak čistit a udržovat látkové sedačky, odstranění skvrn a ochrana čalounění',
        'obsah_cz' => $obsahCz2,
        'obsah_en' => $obsahEn2,
        'obsah_it' => $obsahIt2,
    ];

    // --- TIP 3: Výplně, mechanismy, veletrhy ---
    $obsahCz3 = <<<'EOT'
## STŘÍDÁNÍ SEDU — TAJEMSTVÍ DLOUHÉ ŽIVOTNOSTI VÝPLNÍ

Nejčastější příčinou deformace sedacích polštářů není špatná kvalita, ale **opakované sezení na stejném místě**. Každá sedačka má oblíbené místo — a to se časem propadá, zatímco zbytek zůstává nepoužívaný.

Řešení je jednoduché: **střídejte místa sezení** a pravidelně otáčejte odnímatelné polštáře. Ideálně jednou za 2–4 týdny. Tím rovnoměrně rozložíte zatížení a výplně se opotřebovávají rovnoměrně.

Pěnové výplně s paměťovým efektem (visco-elastická pěna) se samy vrací do tvaru, ale i ony potřebují čas na regeneraci — nechte je občas „odpočinout" bez zatížení. Péřové výplně je dobré jednou za čas vyklepat a provzdušnit.

**Tip pro rodinné sedačky:** Označte polštáře číslicí nebo tečkou a systematicky je rotujte. Za rok oceníte rovnoměrné opotřebení.

## OPRAVA A SEŘÍZENÍ RELAXAČNÍCH MECHANISMŮ

Relaxační a polohovací mechanismy jsou nejvíce namáhané části sedačky. Pokud pocítíte, že mechanismus skřípe, tuhne nebo nereaguje správně, neodkládejte opravu. Malá závada se rychle rozvine ve větší poruchu.

**Ruční mechanismy** (páky, táhla) se mohou uvolnit nebo opotřebovat. Většinou stačí dotáhnout šrouby nebo vyměnit opotřebované části. **Elektrické mechanismy** (motory, dálkové ovladače) vyžadují odborný zásah — kontrola napájení, vodičů a samotného motoru.

Originální náhradní díly pro přední výrobce čalouněného nábytku objednáváme přímo od výrobce. Doba dodání je obvykle 2–4 týdny. Nestandardní díly řešíme individuálně.

[Objednat opravu mechanismu](/novareklamace.php)

## VELETRHY NÁBYTKU 2026 — CO NÁS ČEKÁ

Salone del Mobile v Miláně je každoroční setkání světového nábytku. **Ročník 2026** (duben, Milán) přináší témata udržitelnosti, modulárního designu a multifunkčních kusů. Rostoucí obliba čalounění s přírodními materiály — vlna, len, konopí — mění trh prémiového nábytku.

**IMM Cologne** (leden, Kolín nad Rýnem) prezentuje trendy pro každodenní interiér s důrazem na dostupný luxus. Oblíbené jsou neutrální barvy, skandinávská jednoduchost a opravitelné kusy — trend, který kopíruje naše filozofie servisu a péče o nábytek.

Sledujeme světové trendy proto, abychom vám mohli nabídnout opravy a péči odpovídající aktuálním materiálům a konstrukčním řešením nábytku.

[Zjistit více o našich službách](/onas.php)
EOT;

    $obsahEn3 = <<<'EOT'
## ROTATING SEATS — THE SECRET TO LONG CUSHION LIFE

The most common cause of seat cushion deformation is not poor quality, but **repeated sitting in the same place**. Every sofa has a favourite spot — and it gradually sinks while the rest remains unused.

The solution is simple: **rotate your sitting positions** and regularly turn removable cushions. Ideally every 2–4 weeks. This evenly distributes the load and the fillings wear evenly.

Memory foam fillings (viscoelastic foam) return to shape on their own, but they also need time to recover — occasionally let them "rest" without load. Feather fillings are good to plump up and air out from time to time.

**Tip for family sofas:** Mark cushions with a number or dot and rotate them systematically. After a year you will appreciate the even wear.

## REPAIR AND ADJUSTMENT OF RECLINER MECHANISMS

Recliner and positioning mechanisms are the most stressed parts of a sofa. If you feel the mechanism creaking, stiffening or not responding properly, don't delay repair. A small fault quickly develops into a larger breakdown.

**Manual mechanisms** (levers, rods) can loosen or wear out. Usually just tightening screws or replacing worn parts is enough. **Electric mechanisms** (motors, remote controls) require professional intervention — checking the power supply, wires and the motor itself.

We order original spare parts for leading upholstered furniture manufacturers directly from the manufacturer. Delivery time is usually 2–4 weeks. Non-standard parts are handled individually.

[Order mechanism repair](/novareklamace.php)

## FURNITURE FAIRS 2026 — WHAT TO EXPECT

Salone del Mobile in Milan is the annual global furniture meeting. **The 2026 edition** (April, Milan) brings themes of sustainability, modular design and multi-functional pieces. The growing popularity of upholstery with natural materials — wool, linen, hemp — is changing the premium furniture market.

**IMM Cologne** (January, Cologne) presents trends for everyday interiors with emphasis on accessible luxury. Neutral colours, Scandinavian simplicity and repairable pieces are popular — a trend that mirrors our philosophy of furniture service and care.

We follow global trends so that we can offer you repairs and care corresponding to current materials and construction solutions for furniture.

[Find out more about our services](/onas.php)
EOT;

    $obsahIt3 = <<<'EOT'
## ROTAZIONE DEI SEDILI — IL SEGRETO DELLA LUNGA VITA DEI CUSCINI

La causa più comune della deformazione dei cuscini non è la scarsa qualità, ma **sedersi ripetutamente nello stesso posto**. Ogni divano ha un posto preferito — che col tempo si abbassa, mentre il resto rimane inutilizzato.

La soluzione è semplice: **ruotare i posti a sedere** e girare regolarmente i cuscini rimovibili. Idealmente ogni 2–4 settimane. Questo distribuisce uniformemente il carico e le imbottiture si consumano in modo uniforme.

Le imbottiture in memory foam (schiuma viscoelastica) tornano da sole alla forma, ma hanno anche bisogno di tempo per recuperare — lasciarle occasionalmente "riposare" senza carico. Le imbottiture in piuma è bene gonfiarle e arieggiarle di tanto in tanto.

**Consiglio per divani di famiglia:** Contrassegnare i cuscini con un numero o un punto e ruotarli sistematicamente. Dopo un anno si apprezzerà l'usura uniforme.

## RIPARAZIONE E REGOLAZIONE DEI MECCANISMI RECLINABILI

I meccanismi reclinabili e di posizionamento sono le parti più sollecitate del divano. Se si avverte che il meccanismo scricchiola, si irrigidisce o non risponde correttamente, non rimandare la riparazione. Un piccolo guasto si trasforma rapidamente in un'avaria più grave.

**I meccanismi manuali** (leve, tiranti) possono allentarsi o usurarsi. Di solito è sufficiente stringere le viti o sostituire le parti consumate. **I meccanismi elettrici** (motori, telecomandi) richiedono un intervento professionale — controllo dell'alimentazione, dei cavi e del motore stesso.

Ordiniamo ricambi originali per i principali produttori di mobili imbottiti direttamente dal produttore. I tempi di consegna sono solitamente di 2–4 settimane.

[Ordina riparazione meccanismo](/novareklamace.php)

## FIERE DEL MOBILE 2026 — COSA CI ASPETTA

Il Salone del Mobile di Milano è l'incontro annuale del mobile mondiale. **L'edizione 2026** (aprile, Milano) porta temi di sostenibilità, design modulare e pezzi multifunzionali. La crescente popolarità delle tappezzerie con materiali naturali — lana, lino, canapa — sta cambiando il mercato del mobile premium.

**IMM Cologne** (gennaio, Colonia) presenta tendenze per l'interior design quotidiano con enfasi sul lusso accessibile. Colori neutri, semplicità scandinava e pezzi riparabili sono popolari — una tendenza che rispecchia la nostra filosofia di assistenza e cura dei mobili.

[Scopri di più sui nostri servizi](/onas.php)
EOT;

    $aktuality[] = [
        'datum'    => '2026-02-12',
        'svatek'   => 'Výplně, mechanismy a veletrhy',
        'komentar' => 'Jak prodloužit životnost výplní, oprava mechanismů, novinky z veletrhů nábytku 2026',
        'obsah_cz' => $obsahCz3,
        'obsah_en' => $obsahEn3,
        'obsah_it' => $obsahIt3,
    ];

    // --- TIP 4: Prevence a B2B ---
    $obsahCz4 = <<<'EOT'
## UMÍSTĚNÍ NÁBYTKU — CO ZKRACUJE JEHO ŽIVOTNOST

Správné umístění nábytku v interiéru má zásadní vliv na jeho životnost. **Přímé sluneční záření** je největším nepřítelem kůže i látky — UV záření odbarvuje a degraduje materiály mnohem rychleji než běžné opotřebení. Pokud nelze přemístit sedačku mimo přímé slunce, investujte do kvalitních žaluzií nebo UV ochranné folie na okna.

**Teplo a sucho** způsobuje praskání kůže — zejména v bytě s ústředním topením. Ideální vlhkost vzduchu pro kožený nábytek je 40–60 %. Zvlhčovač vzduchu v zimě je levnou prevencí drahých oprav.

**Radiátory a krby** v bezprostřední blízkosti sedačky urychlují vysychání materiálů. Dodržujte minimální odstup alespoň 50 cm od zdrojů tepla.

## DOMÁCÍ MAZLÍČCI A ČALOUNĚNÝ NÁBYTEK — JAK NA TO

Kočky a psi jsou největší výzvou pro čalouněný nábytek. Drápy poškozují kůži i tkaniny, srst se zachytává ve vláknech a moč zanechává trvalé skvrny i zápach.

**Preventivní opatření:** Speciální kryty sedacích ploch (z odolných mikrovláken), pravidelné stříhání drápů mazlíčků a přesvědčení mazlíčka k vlastnímu místu. Pro kůži existují speciální **ochranné spreje odpuzující pachy**, které nezanechají stopy.

Pokud k poškození dojde, nepokoušejte se opravit hlubší škrábance domácími prostředky — laická oprava bývá hůře opravitelná než původní poškození. Kontaktujte nás pro posouzení rozsahu škody.

[Poptávka opravy po škodě mazlíčkem](/novareklamace.php)

## SPOLUPRÁCE S PRODEJCI A VÝROBCI — B2B SERVIS

Jste prodejce, dovozce nebo výrobce čalouněného nábytku? Hledáte spolehlivého servisního partnera, který převezme reklamační agendu a zajistí technické posudky?

White Glove Service nabízí komplexní B2B servisní pokrytí pro celou Českou republiku a Slovensko. **Přebíráme reklamace, provádíme záruční i pozáruční opravy** a zajišťujeme technické posudky pro pojišťovny. Pracujeme transparentně s jasným ceníkem a rychlými termíny.

Naši certifikovaní technici jsou obeznámeni se širokým spektrem výrobců a materiálů — od italských luxusních značek po středoevropské producenty dostupného prémiového nábytku.

[Kontaktovat nás pro B2B spolupráci](/index.php#b2b-sekce)
EOT;

    $obsahEn4 = <<<'EOT'
## FURNITURE PLACEMENT — WHAT SHORTENS ITS LIFESPAN

Proper placement of furniture in the interior has a fundamental impact on its lifespan. **Direct sunlight** is the biggest enemy of both leather and fabric — UV radiation discolours and degrades materials much faster than normal wear. If the sofa cannot be moved away from direct sun, invest in quality blinds or UV-protective film for windows.

**Heat and dryness** causes leather cracking — especially in flats with central heating. The ideal air humidity for leather furniture is 40–60%. A humidifier in winter is cheap prevention of expensive repairs.

**Radiators and fireplaces** in close proximity to the sofa accelerate the drying of materials. Keep a minimum distance of at least 50 cm from heat sources.

## PETS AND UPHOLSTERED FURNITURE — HOW TO MANAGE

Cats and dogs are the biggest challenge for upholstered furniture. Claws damage leather and fabrics, hair gets caught in fibres and urine leaves permanent stains and odour.

**Preventive measures:** Special seat covers (from resistant microfibre), regular trimming of pet claws and convincing the pet to use its own place. For leather there are special **odour-repellent protective sprays** that leave no traces.

If damage occurs, don't try to repair deeper scratches with home remedies — amateur repair is often harder to fix than the original damage. Contact us for damage assessment.

[Request repair after pet damage](/novareklamace.php)

## COOPERATION WITH RETAILERS AND MANUFACTURERS — B2B SERVICE

Are you a retailer, importer or manufacturer of upholstered furniture? Are you looking for a reliable service partner who will take over complaint handling and provide technical assessments?

White Glove Service offers comprehensive B2B service coverage for the entire Czech Republic and Slovakia. **We handle complaints, carry out warranty and post-warranty repairs** and provide technical assessments for insurance companies. We work transparently with a clear price list and quick turnaround times.

Our certified technicians are familiar with a wide range of manufacturers and materials — from Italian luxury brands to Central European producers of affordable premium furniture.

[Contact us for B2B cooperation](/index.php#b2b-sekce)
EOT;

    $obsahIt4 = <<<'EOT'
## POSIZIONAMENTO DEI MOBILI — COSA NE ACCORCIA LA VITA

Il corretto posizionamento dei mobili nell'interno ha un impatto fondamentale sulla loro durata. **La luce solare diretta** è il peggior nemico sia della pelle che del tessuto — le radiazioni UV scoloriscono e degradano i materiali molto più velocemente dell'usura normale. Se il divano non può essere spostato lontano dalla luce diretta del sole, investire in tende di qualità o pellicole protettive UV per finestre.

**Il calore e la secchezza** causano la screpolatura della pelle — soprattutto negli appartamenti con riscaldamento centralizzato. L'umidità ideale dell'aria per i mobili in pelle è 40–60%. Un umidificatore in inverno è una prevenzione economica di riparazioni costose.

**Termosifoni e caminetti** in prossimità del divano accelerano l'essiccamento dei materiali. Mantenere una distanza minima di almeno 50 cm dalle fonti di calore.

## ANIMALI DOMESTICI E MOBILI IMBOTTITI — COME GESTIRLO

Gatti e cani sono la sfida più grande per i mobili imbottiti. Gli artigli danneggiano pelle e tessuti, i peli si incastrano nelle fibre e l'urina lascia macchie permanenti e odori.

**Misure preventive:** Copri speciali per le superfici sedute (in microfibra resistente), taglio regolare degli artigli degli animali e convincere l'animale ad usare il proprio posto. Per la pelle esistono speciali **spray protettivi repellenti agli odori** che non lasciano tracce.

Se si verificano danni, non cercare di riparare graffi profondi con rimedi casalinghi — la riparazione fai-da-te è spesso più difficile da riparare del danno originale. Contattateci per la valutazione dell'entità del danno.

[Richiesta riparazione dopo danno da animale domestico](/novareklamace.php)

## COOPERAZIONE CON RIVENDITORI E PRODUTTORI — SERVIZIO B2B

Siete rivenditori, importatori o produttori di mobili imbottiti? Cercate un partner di servizio affidabile che si occupi della gestione dei reclami e fornisca perizie tecniche?

White Glove Service offre una copertura di servizio B2B completa per tutta la Repubblica Ceca e la Slovacchia. **Gestiamo i reclami, eseguiamo riparazioni in garanzia e fuori garanzia** e forniamo perizie tecniche per le compagnie assicurative. Lavoriamo in modo trasparente con un listino prezzi chiaro e tempi rapidi.

[Contattateci per la cooperazione B2B](/index.php#b2b-sekce)
EOT;

    $aktuality[] = [
        'datum'    => '2026-02-28',
        'svatek'   => 'Prevence a B2B spolupráce',
        'komentar' => 'Jak správně umístit nábytek, ochrana před mazlíčky a B2B servisní partnerství',
        'obsah_cz' => $obsahCz4,
        'obsah_en' => $obsahEn4,
        'obsah_it' => $obsahIt4,
    ];

    // === PŘEHLED ===
    $stmtPocet = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_natuzzi_aktuality");
    $pocetStavajicich = $stmtPocet->fetchColumn();

    echo "<h1>Migrace: Naše tipy — péče o nábytek</h1>";
    echo "<div class='warning'>";
    echo "<strong>Tato operace SMAŽE všechny stávající záznamy ({$pocetStavajicich}) a nahradí je " . count($aktuality) . " novými sadami tipů.</strong>";
    echo "</div>";

    echo "<h2>Nové sady tipů, které budou vloženy:</h2>";
    echo "<table>
        <thead>
            <tr><th>Datum</th><th>Téma</th><th>Články</th></tr>
        </thead>
        <tbody>";

    foreach ($aktuality as $akt) {
        preg_match_all('/^## (.+)$/m', $akt['obsah_cz'], $shody);
        $nadpisy = $shody[1] ?? [];
        echo "<tr>";
        echo "<td><span class='datum-tag'>" . htmlspecialchars($akt['datum']) . "</span></td>";
        echo "<td>" . htmlspecialchars($akt['svatek']) . "</td>";
        echo "<td>";
        foreach ($nadpisy as $n) {
            echo "<div class='clanek-nadpis'>" . htmlspecialchars($n) . "</div>";
        }
        echo "</td></tr>";
    }
    echo "</tbody></table>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {

        echo "<h2>Průběh migrace:</h2>";
        $pdo->beginTransaction();

        try {
            $smazano = $pdo->exec("DELETE FROM wgs_natuzzi_aktuality");
            echo "<div class='info'>Smazáno stávajících záznamů: <strong>{$smazano}</strong></div>";

            $vlozeno = 0;
            foreach ($aktuality as $akt) {
                $zdroje = json_encode([
                    'created_by' => 'admin_migration',
                    'user_id'    => $_SESSION['user_id'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'migration'  => 'nahrad_aktuality_tipy.php',
                    'note'       => 'Obecne tipy pece o nabytek - bez vazby na konkretni znacku'
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
                echo "<div class='success'>Vloženo: <strong>" . htmlspecialchars($akt['datum']) . "</strong> (ID: {$novyId}) — " . htmlspecialchars($akt['svatek']) . " — {$pocetClanku} články</div>";
                $vlozeno++;
            }

            $pdo->commit();

            echo "<div class='success' style='margin-top:15px;'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA. Vloženo {$vlozeno} sad tipů.</strong>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>CHYBA — transakce zrušena:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<br>";
        echo "<a href='?execute=1' class='btn btn-danger' onclick=\"return confirm('Opravdu smazat všechny stávající aktuality a nahradit tipy?');\">SPUSTIT MIGRACI</a>";
        echo "<a href='/admin.php' class='btn btn-back'>Zpět na admin</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
