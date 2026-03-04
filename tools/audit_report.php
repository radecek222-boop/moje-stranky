<?php
/**
 * WGS Service - Detailní audit projektu
 * Výstup určený pro tisk / export do PDF
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('PŘÍSTUP ODEPŘEN: Pouze administrátor.');
}

$datumGenerovani = date('d. m. Y H:i');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit projektu WGS Service – <?php echo $datumGenerovani; ?></title>
<style>
/* =============================================
   ZÁKLADNÍ TYPOGRAFIE A LAYOUT
   ============================================= */
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #111;
    background: #f4f4f4;
    line-height: 1.6;
}

.tisknout-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #111;
    color: #fff;
    border: none;
    padding: 12px 28px;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: .06em;
    cursor: pointer;
    z-index: 9999;
    text-transform: uppercase;
}
.tisknout-btn:hover { background: #333; }

.dokument {
    max-width: 960px;
    margin: 40px auto;
    background: #fff;
    box-shadow: 0 2px 24px rgba(0,0,0,.12);
}

/* =============================================
   TITULNÍ STRANA
   ============================================= */
.titulni-strana {
    background: #111;
    color: #fff;
    padding: 80px 72px 60px;
    min-height: 340px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.titulni-strana .nazev-projektu {
    font-size: 11px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: #aaa;
    margin-bottom: 20px;
}

.titulni-strana h1 {
    font-size: 36px;
    font-weight: 700;
    line-height: 1.18;
    margin-bottom: 12px;
    letter-spacing: -.01em;
}

.titulni-strana .podtitul {
    font-size: 15px;
    color: #bbb;
    margin-bottom: 40px;
}

.titulni-strana .meta-radek {
    display: flex;
    gap: 40px;
    border-top: 1px solid #333;
    padding-top: 24px;
    margin-top: auto;
}

.titulni-strana .meta-polozka span {
    display: block;
    font-size: 10px;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #666;
    margin-bottom: 4px;
}

.titulni-strana .meta-polozka strong {
    font-size: 13px;
    color: #eee;
}

/* =============================================
   OBSAH DOKUMENTU
   ============================================= */
.obsah-dokumentu {
    padding: 48px 72px;
    background: #fafafa;
    border-bottom: 2px solid #eee;
}

.obsah-dokumentu h2 {
    font-size: 11px;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 20px;
}

.obsah-dokumentu ol {
    list-style: none;
    counter-reset: toc;
}

.obsah-dokumentu ol li {
    counter-increment: toc;
    display: flex;
    align-items: center;
    gap: 0;
    padding: 5px 0;
    border-bottom: 1px dotted #ddd;
    font-size: 13px;
}

.obsah-dokumentu ol li::before {
    content: counter(toc, decimal-leading-zero);
    font-size: 11px;
    color: #aaa;
    font-weight: 600;
    margin-right: 14px;
    min-width: 24px;
}

.obsah-dokumentu ol li .toc-body {
    flex: 1;
    display: flex;
    justify-content: space-between;
}

/* =============================================
   SEKCE AUDITU
   ============================================= */
.sekce {
    padding: 52px 72px 40px;
    border-bottom: 1px solid #eee;
}

.sekce:last-of-type {
    border-bottom: none;
}

.sekce-hlavicka {
    display: flex;
    align-items: baseline;
    gap: 16px;
    margin-bottom: 28px;
    padding-bottom: 14px;
    border-bottom: 2px solid #111;
}

.sekce-cislo {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .14em;
    color: #aaa;
    text-transform: uppercase;
    min-width: 28px;
}

.sekce-hlavicka h2 {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -.01em;
    color: #111;
}

/* =============================================
   TYPOGRAFICKÉ ELEMENTY
   ============================================= */
h3 {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #444;
    margin: 24px 0 10px;
}

p {
    margin-bottom: 12px;
    color: #333;
    line-height: 1.7;
}

/* =============================================
   TABULKY
   ============================================= */
.tabulka-obal {
    overflow-x: auto;
    margin: 16px 0 24px;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

thead tr {
    background: #111;
    color: #fff;
}

thead th {
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    font-size: 11px;
    letter-spacing: .08em;
    text-transform: uppercase;
}

tbody tr:nth-child(even) { background: #f7f7f7; }
tbody tr:nth-child(odd)  { background: #fff; }

tbody td {
    padding: 9px 14px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
    line-height: 1.5;
}

tbody tr:last-child td { border-bottom: none; }

/* =============================================
   HODNOTÍCÍ BLOKY
   ============================================= */
.hodnoceni-mrizka {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin: 16px 0 24px;
}

.hodnoceni-karta {
    background: #f7f7f7;
    border: 1px solid #e0e0e0;
    padding: 18px 16px;
}

.hodnoceni-karta .metrika {
    font-size: 10px;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 8px;
}

.hodnoceni-karta .hodnota {
    font-size: 26px;
    font-weight: 700;
    color: #111;
    line-height: 1;
    margin-bottom: 4px;
}

.hodnoceni-karta .popis {
    font-size: 11px;
    color: #666;
}

/* =============================================
   SLOUPCOVÉ BLOKY
   ============================================= */
.dva-sloupce {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin: 16px 0;
}

.blok {
    background: #f7f7f7;
    border-left: 3px solid #111;
    padding: 16px 18px;
}

.blok.slaby {
    border-left-color: #bbb;
}

.blok h4 {
    font-size: 11px;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #555;
    margin-bottom: 10px;
    font-weight: 700;
}

.blok ul {
    list-style: none;
    padding: 0;
}

.blok ul li {
    padding: 4px 0;
    font-size: 12px;
    color: #333;
    border-bottom: 1px solid #e8e8e8;
    line-height: 1.5;
}

.blok ul li:last-child { border-bottom: none; }

.blok ul li::before {
    content: '–';
    margin-right: 8px;
    color: #999;
}

/* =============================================
   FINANČNÍ BLOKY
   ============================================= */
.financni-mrizka {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin: 20px 0;
}

.financni-karta {
    border: 1px solid #ddd;
    padding: 22px 20px;
    background: #fff;
}

.financni-karta .region {
    font-size: 10px;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #999;
    margin-bottom: 6px;
}

.financni-karta .castka {
    font-size: 24px;
    font-weight: 700;
    color: #111;
    margin-bottom: 4px;
    line-height: 1.1;
}

.financni-karta .podnazev {
    font-size: 11px;
    color: #666;
    margin-bottom: 12px;
}

.financni-karta .detail {
    font-size: 11px;
    color: #888;
    border-top: 1px solid #eee;
    padding-top: 10px;
    line-height: 1.6;
}

/* =============================================
   DOPORUČENÍ
   ============================================= */
.doporuceni-seznam {
    counter-reset: doporuceni;
    margin: 16px 0;
}

.doporuceni-polozka {
    counter-increment: doporuceni;
    display: flex;
    gap: 18px;
    padding: 16px 0;
    border-bottom: 1px solid #eee;
    align-items: flex-start;
}

.doporuceni-polozka:last-child { border-bottom: none; }

.doporuceni-cislo {
    background: #111;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}

.doporuceni-obsah h4 {
    font-size: 13px;
    font-weight: 700;
    color: #111;
    margin-bottom: 4px;
}

.doporuceni-obsah p {
    font-size: 12px;
    color: #555;
    margin: 0;
}

.doporuceni-obsah .dopad {
    font-size: 11px;
    color: #888;
    margin-top: 4px;
    font-style: italic;
}

/* =============================================
   ZÁVĚREČNÝ BLOK
   ============================================= */
.zaverecny-blok {
    background: #111;
    color: #fff;
    padding: 40px 72px;
    margin-top: 0;
}

.zaverecny-blok .shrnuti-hodnota {
    font-size: 36px;
    font-weight: 700;
    margin: 8px 0;
}

.zaverecny-blok p {
    color: #aaa;
    font-size: 12px;
}

.zaverecny-mrizka {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 32px;
    margin-top: 24px;
    border-top: 1px solid #333;
    padding-top: 24px;
}

.zaverecny-mrizka .polozka span {
    display: block;
    font-size: 10px;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #666;
    margin-bottom: 4px;
}

.zaverecny-mrizka .polozka strong {
    font-size: 14px;
    color: #eee;
    display: block;
}

/* =============================================
   PRINT STYLES
   ============================================= */
@media print {
    body {
        background: #fff;
        font-size: 12px;
    }

    .tisknout-btn { display: none !important; }

    .dokument {
        max-width: 100%;
        margin: 0;
        box-shadow: none;
    }

    .titulni-strana {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        background: #111 !important;
        color: #fff !important;
        min-height: 320px;
    }

    .sekce-hlavicka,
    .sekce,
    .titulni-strana,
    .obsah-dokumentu,
    .zaverecny-blok {
        page-break-inside: avoid;
    }

    .sekce { page-break-before: auto; }

    thead tr {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        background: #111 !important;
        color: #fff !important;
    }

    .hodnoceni-karta,
    .financni-karta,
    .blok,
    .doporuceni-cislo {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .zaverecny-blok {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        background: #111 !important;
        color: #fff !important;
    }

    a { color: #111 !important; text-decoration: none; }

    @page {
        margin: 14mm 16mm;
        size: A4;
    }
}
</style>
</head>
<body>

<button class="tisknout-btn" onclick="window.print()">Uložit jako PDF / Tisknout</button>

<div class="dokument">

<!-- =============================================
     TITULNÍ STRANA
     ============================================= -->
<div class="titulni-strana">
    <div>
        <div class="nazev-projektu">Interní projektová dokumentace — Důvěrné</div>
        <h1>Detailní audit projektu<br>WGS Service</h1>
        <div class="podtitul">White Glove Service — Systém pro správu servisů prémiového nábytku Natuzzi</div>
    </div>
    <div class="meta-radek">
        <div class="meta-polozka">
            <span>Datum generování</span>
            <strong><?php echo $datumGenerovani; ?></strong>
        </div>
        <div class="meta-polozka">
            <span>Auditovaná doména</span>
            <strong>wgs-service.cz</strong>
        </div>
        <div class="meta-polozka">
            <span>Vlastník projektu</span>
            <strong>Radek Zikmund</strong>
        </div>
        <div class="meta-polozka">
            <span>Verze auditu</span>
            <strong>1.0 — Produkce</strong>
        </div>
    </div>
</div>

<!-- =============================================
     OBSAH DOKUMENTU
     ============================================= -->
<div class="obsah-dokumentu">
    <h2>Obsah auditu</h2>
    <ol>
        <li><div class="toc-body"><span>Analýza struktury projektu</span><span>sekce 01</span></div></li>
        <li><div class="toc-body"><span>UX / UI analýza</span><span>sekce 02</span></div></li>
        <li><div class="toc-body"><span>Technická komplexita</span><span>sekce 03</span></div></li>
        <li><div class="toc-body"><span>Obsah a marketingová síla</span><span>sekce 04</span></div></li>
        <li><div class="toc-body"><span>Odhad času vývoje</span><span>sekce 05</span></div></li>
        <li><div class="toc-body"><span>Finanční hodnota projektu (výroba)</span><span>sekce 06</span></div></li>
        <li><div class="toc-body"><span>Prodejní hodnota projektu</span><span>sekce 07</span></div></li>
        <li><div class="toc-body"><span>Silné stránky</span><span>sekce 08</span></div></li>
        <li><div class="toc-body"><span>Slabé stránky</span><span>sekce 09</span></div></li>
        <li><div class="toc-body"><span>Doporučení pro zvýšení hodnoty</span><span>sekce 10</span></div></li>
    </ol>
</div>

<!-- =============================================
     SEKCE 01 — STRUKTURA
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">01</span>
        <h2>Analýza struktury projektu</h2>
    </div>

    <div class="hodnoceni-mrizka">
        <div class="hodnoceni-karta">
            <div class="metrika">PHP soubory (root)</div>
            <div class="hodnota">125</div>
            <div class="popis">stránek, skriptů a nástrojů</div>
        </div>
        <div class="hodnoceni-karta">
            <div class="metrika">API endpointy</div>
            <div class="hodnota">73</div>
            <div class="popis">samostatných PHP API souborů</div>
        </div>
        <div class="hodnoceni-karta">
            <div class="metrika">JavaScript souborů</div>
            <div class="hodnota">97</div>
            <div class="popis">vč. minifikovaných verzí (34 394 řádků)</div>
        </div>
        <div class="hodnoceni-karta">
            <div class="metrika">CSS souborů</div>
            <div class="hodnota">58</div>
            <div class="popis">vč. minifikovaných verzí</div>
        </div>
        <div class="hodnoceni-karta">
            <div class="metrika">Sdílené knihovny</div>
            <div class="hodnota">50</div>
            <div class="popis">souborů v /includes/</div>
        </div>
        <div class="hodnoceni-karta">
            <div class="metrika">Controllerů</div>
            <div class="hodnota">8</div>
            <div class="popis">z toho 5 nad 6 000 řádků kódu</div>
        </div>
    </div>

    <h3>Hlavní funkční moduly</h3>
    <div class="tabulka-obal">
    <table>
        <thead>
            <tr>
                <th>Modul</th>
                <th>Soubory</th>
                <th>Popis funkcionality</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><strong>Reklamace</strong></td><td>novareklamace.php, save.php, seznam.php, load.php</td><td>Vytvoření, editace, filtrace a správa reklamací s mapovou integrací a fotografiemi</td></tr>
            <tr><td><strong>Autentizace</strong></td><td>login.php, registration.php, login_controller.php (14 318 ř.)</td><td>Přihlášení, registrace s klíči, "remember me", reset hesla, rate limiting</td></tr>
            <tr><td><strong>Admin panel</strong></td><td>admin.php + 8 include souborů admin_*</td><td>Tab-based administrace: uživatelé, statistiky, bezpečnost, email, transport, SQL, konfigurace</td></tr>
            <tr><td><strong>Statistiky</strong></td><td>statistiky.php, statistiky_api.php, statistiky.js</td><td>Grafy, přehledy, exporty — přístupné pouze adminovi</td></tr>
            <tr><td><strong>Ceník</strong></td><td>cenik.php, pricing_api.php, cenik.js, cenik-calculator.js</td><td>Vícejazyčný ceník (CS/EN/IT) s kalkulačkou a DB-řízenými překlady</td></tr>
            <tr><td><strong>Aktuality</strong></td><td>aktuality.php + DB wgs_natuzzi_aktuality</td><td>Markdown-based články v 3 jazycích, 2-sloupcový layout</td></tr>
            <tr><td><strong>Protokol</strong></td><td>protokol.php, protokol_api.php, protokol.js</td><td>Servisní protokoly s generováním PDF a e-mailem</td></tr>
            <tr><td><strong>Fotodokumentace</strong></td><td>photocustomer.php, save_photos.php, get_photos_api.php</td><td>Nahrávání, správa a zobrazení fotografií k zakázkám</td></tr>
            <tr><td><strong>Push notifikace</strong></td><td>pwa-notifications.js, WebPush.php, notification_api.php</td><td>Web push na mobilní zařízení, hamburger NOTIFY ME tlačítko</td></tr>
            <tr><td><strong>Analytics &amp; heatmap</strong></td><td>analytics.php, heatmap-tracker.js, track_heatmap.php</td><td>Vlastní analytika, heatmapa kliknutí, sledování session</td></tr>
            <tr><td><strong>QR platby</strong></td><td>qr_platba_api.php, qrcode.min.js, qr_payment_helper.php</td><td>Generování QR kódu pro platbu, integrace s ceníkem</td></tr>
            <tr><td><strong>Mapy</strong></td><td>wgs-map.js, geocode_proxy.php, GeolocationService.php</td><td>Leaflet.js + Geoapify API, geolokace zákazníka, výpočet vzdálenosti</td></tr>
            <tr><td><strong>Email fronta</strong></td><td>EmailQueue.php, email_resend_api.php, emailClient.php</td><td>Fronta emailů, retry logika, PHPMailer SMTP, šablony</td></tr>
            <tr><td><strong>PWA</strong></td><td>manifest.json, sw.php, pwa_scripts.php, pull-to-refresh.js</td><td>Offline mode, Service Worker, app install, mobilní gesta</td></tr>
            <tr><td><strong>Herní zóna</strong></td><td>hry_api.php, flight_api.php, video_api.php, hry-sidebar.php</td><td>Gamifikace pro uživatele — hry, video, sidebar</td></tr>
            <tr><td><strong>Transport</strong></td><td>transport_sync.php, transport_events_api.php, admin_transport.php</td><td>Správa transportních zakázek, synchronizace</td></tr>
            <tr><td><strong>Zákazníci</strong></td><td>zakaznici_api.php, nabidka_api.php</td><td>Kartotéka zákazníků, nabídky</td></tr>
            <tr><td><strong>Překladač</strong></td><td>translate_api.php, DeepL API, translations.js</td><td>Automatické překlady CZ/EN/IT přes DeepL</td></tr>
        </tbody>
    </table>
    </div>

    <h3>Technologický stack</h3>
    <div class="tabulka-obal">
    <table>
        <thead><tr><th>Vrstva</th><th>Technologie</th><th>Verze</th></tr></thead>
        <tbody>
            <tr><td>Backend</td><td>PHP s PDO, bez frameworku</td><td>8.4+</td></tr>
            <tr><td>Databáze</td><td>MariaDB</td><td>10.11+</td></tr>
            <tr><td>Frontend</td><td>Vanilla JavaScript (ES6+), bez frameworku</td><td>—</td></tr>
            <tr><td>Server</td><td>Nginx (Apache .htaccess fallback)</td><td>1.26+</td></tr>
            <tr><td>Mapy</td><td>Leaflet.js + Geoapify API</td><td>1.9.4</td></tr>
            <tr><td>Email</td><td>PHPMailer přes SMTP</td><td>—</td></tr>
            <tr><td>Push notifikace</td><td>minishlink/web-push (Composer)</td><td>^9.0</td></tr>
            <tr><td>Překlady</td><td>DeepL API</td><td>—</td></tr>
            <tr><td>Build nástroje</td><td>Terser (JS), CSSO (CSS)</td><td>—</td></tr>
            <tr><td>Testování</td><td>Jest (frontend), PHPUnit (backend)</td><td>^29.7 / ^11.0</td></tr>
            <tr><td>CI/CD</td><td>GitHub Actions + SFTP deploy</td><td>—</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- =============================================
     SEKCE 02 — UX / UI
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">02</span>
        <h2>UX / UI analýza</h2>
    </div>

    <div class="hodnoceni-mrizka">
        <div class="hodnoceni-karta">
            <div class="metrika">Design systém</div>
            <div class="hodnota">B+</div>
            <div class="popis">Konzistentní černobílá paleta s 5 schválenými výjimkami</div>
        </div>
        <div class="hodnoceni-karta">
            <div class="metrika">Mobilní optimalizace</div>
            <div class="hodnota">A</div>
            <div class="popis">PWA, pull-to-refresh, Service Worker, manifest.json</div>
        </div>
        <div class="hodnoceni-karta">
            <div class="metrika">Použitelnost</div>
            <div class="hodnota">B</div>
            <div class="popis">Komplexní workflow vyvážen rozsáhlým admin rozhraním</div>
        </div>
    </div>

    <div class="dva-sloupce">
        <div class="blok">
            <h4>Designové silné stránky</h4>
            <ul>
                <li>Striktní černobílý design systém s dokumentovanými výjimkami</li>
                <li>Neonově zelené toast notifikace — jasná vizuální zpětná vazba</li>
                <li>Přechody stránek (page-transitions.css/js) — plynulý pocit aplikace</li>
                <li>Hamburger menu s rolemi, notifikacemi a PLAY zónou</li>
                <li>Minifikace CSS/JS — optimalizovaný výkon načítání</li>
                <li>Preload kritických zdrojů (hero image, CSS) v HTML</li>
                <li>font-display: optional — bez blokování vykreslování</li>
                <li>Z-index management v samostatném z-index-layers.css</li>
                <li>Universal modal theme — konzistentní dialogy napříč systémem</li>
                <li>Cookie consent v souladu s GDPR</li>
            </ul>
        </div>
        <div class="blok slaby">
            <h4>Designové slabé stránky</h4>
            <ul>
                <li>58 CSS souborů — bez design token systému (CSS variables)</li>
                <li>Admin panel tabs — informační přetížení, mnoho funkcí najednou</li>
                <li>125 PHP souborů v rootu — absence clean URL routing</li>
                <li>button-fixes-global.css naznačuje opravené nekonzistence</li>
                <li>Absence dark mode (přesto login-dark-theme.css existuje)</li>
                <li>cenik-wizard-fix.css — fixování stávajícího UI místo redesignu</li>
                <li>Žádný design systém / component library dokumentace</li>
            </ul>
        </div>
    </div>

    <h3>Mobilní a PWA funkcionalita</h3>
    <p>Projekt implementuje <strong>plnohodnotnou PWA</strong> (Progressive Web App): Service Worker pro offline cache, manifest.json pro instalaci na plochu, pull-to-refresh gesta, online/offline heartbeat detektor a push notifikace na mobilních zařízeních. To je výrazně nad standardem pro tento typ B2B servisní aplikace a přidává reálnou hodnotu pro terénní techniky.</p>
</div>

<!-- =============================================
     SEKCE 03 — TECHNICKÁ KOMPLEXITA
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">03</span>
        <h2>Technická komplexita</h2>
    </div>

    <p>Projekt je <strong>plnohodnotná webová aplikace</strong> — nikoliv statický web ani jednoduchý CMS. Rozsah a hloubka kódu odpovídá enterprise-grade systému pro správu zakázek.</p>

    <div class="tabulka-obal">
    <table>
        <thead>
            <tr><th>Komponenta</th><th>Velikost</th><th>Komplexita</th><th>Poznámka</th></tr>
        </thead>
        <tbody>
            <tr><td>login_controller.php</td><td>14 318 řádků</td><td>Extrémní</td><td>Autentizace, role, remember me, rate limiting, audit log</td></tr>
            <tr><td>load.php</td><td>13 198 řádků</td><td>Extrémní</td><td>Načítání dat reklamací s filtry, řazením, paginací</td></tr>
            <tr><td>registration_controller.php</td><td>8 739 řádků</td><td>Vysoká</td><td>Registrace s klíči, validace, emailová notifikace</td></tr>
            <tr><td>save_photos.php</td><td>8 269 řádků</td><td>Vysoká</td><td>Upload, validace, resize, MIME check fotografií</td></tr>
            <tr><td>get_distance.php</td><td>8 080 řádků</td><td>Vysoká</td><td>Geolokace, Geoapify API, výpočet vzdálenosti</td></tr>
            <tr><td>password_reset_controller.php</td><td>6 797 řádků</td><td>Vysoká</td><td>Bezpečný reset hesla s tokenem, SMTP email</td></tr>
            <tr><td>assets/js/seznam.js</td><td>6 339 řádků</td><td>Extrémní</td><td>Nejsložitější frontend soubor — tabulka, filtry, inline edit</td></tr>
            <tr><td>save.php</td><td>869 řádků</td><td>Střední</td><td>Uložení reklamace, enum mapping CZ↔EN, validace</td></tr>
            <tr><td>assets/js/novareklamace.js</td><td>1 478 řádků</td><td>Střední</td><td>Formulář nové reklamace, mapa, autocomplete</td></tr>
        </tbody>
    </table>
    </div>

    <h3>Bezpečnostní implementace</h3>
    <div class="tabulka-obal">
    <table>
        <thead><tr><th>Opatření</th><th>Implementace</th><th>Hodnocení</th></tr></thead>
        <tbody>
            <tr><td>CSRF ochrana</td><td>csrf_helper.php — token na každém POST</td><td>Výborná</td></tr>
            <tr><td>SQL injection prevence</td><td>PDO prepared statements povinně</td><td>Výborná</td></tr>
            <tr><td>XSS ochrana</td><td>html-sanitizer.js (frontend) + htmlspecialchars (backend)</td><td>Výborná</td></tr>
            <tr><td>Session security</td><td>Secure, HttpOnly, SameSite=Lax, HTTPS-only</td><td>Výborná</td></tr>
            <tr><td>Rate limiting</td><td>rate_limiter.php na login a API endpointech</td><td>Výborná</td></tr>
            <tr><td>Security headers</td><td>CSP, HSTS, X-Frame-Options, X-Content-Type-Options</td><td>Výborná</td></tr>
            <tr><td>Audit log</td><td>audit_logger.php — logování akcí pro compliance</td><td>Výborná</td></tr>
            <tr><td>Input sanitizace</td><td>sanitizeInput() centrálně v config.php</td><td>Výborná</td></tr>
            <tr><td>Autentizace</td><td>Admin key (SHA256 hash), bcrypt pro uživatele</td><td>Výborná</td></tr>
        </tbody>
    </table>
    </div>

    <h3>Infrastrukturní složky</h3>
    <div class="tabulka-obal">
    <table>
        <thead><tr><th>Složka</th><th>Obsah</th><th>Účel</th></tr></thead>
        <tbody>
            <tr><td>/api/</td><td>73 souborů</td><td>REST-like API endpointy pro AJAX volání</td></tr>
            <tr><td>/includes/</td><td>50 souborů</td><td>Sdílené utility, security, email, DB helpers</td></tr>
            <tr><td>/app/controllers/</td><td>8 souborů (~61 000 řádků)</td><td>Hlavní business logika</td></tr>
            <tr><td>/migrations/</td><td>10 souborů</td><td>Databázové migrace</td></tr>
            <tr><td>/logs/</td><td>2 soubory</td><td>php_errors.log, security.log</td></tr>
            <tr><td>/uploads/</td><td>14 souborů, 3.1 MB</td><td>Fotografie a dokumenty k zakázkám</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- =============================================
     SEKCE 04 — OBSAH
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">04</span>
        <h2>Obsah projektu a marketingová síla</h2>
    </div>

    <div class="dva-sloupce">
        <div class="blok">
            <h4>Co funguje dobře</h4>
            <ul>
                <li>Trilingvní obsah (CZ/EN/IT) — adekvátní pro italskou značku Natuzzi</li>
                <li>DB-řízené překlady ceníku s DeepL API integrací</li>
                <li>SEO metadata přes seo_meta.php — strukturovaná data</li>
                <li>Schema.org markup zmíněn v includes/seo_meta.php</li>
                <li>Čistá URL bez přípony .php (mod_rewrite v .htaccess)</li>
                <li>Hero sekce s jasným CTA "Objednat servis"</li>
                <li>Sekce aktualit v 3 jazycích s Markdown-based redakcí</li>
                <li>PWA — meta tag theme-color, manifest.json</li>
            </ul>
        </div>
        <div class="blok slaby">
            <h4>Co chybí nebo je slabé</h4>
            <ul>
                <li>Obsah hero sekce nelze posoudit bez přístupu do DB</li>
                <li>Neznámý počet zákazníků / zakázek — nelze posoudit SEO sílu</li>
                <li>Blog / content marketing — pouze aktuality sekce</li>
                <li>Absence Open Graph / Twitter Card tagů (neověřeno)</li>
                <li>Sitemap.xml — existence neověřena</li>
                <li>Roboty.txt — existence neověřena</li>
                <li>Google Analytics / Search Console — integrace neověřena</li>
            </ul>
        </div>
    </div>

    <h3>Vícejazyčnost — Detail implementace</h3>
    <p>Systém překladu je <strong>databázově řízený</strong> — překlady jsou uloženy přímo v tabulkách (sloupce <code>_en</code>, <code>_it</code>). Frontend detekuje jazyk přes <code>window.ziskejAktualniJazyk()</code> z <code>language-switcher.js</code>. Přepínání probíhá bez reload stránky. Ceník, aktuality i protokol jsou plně trilingvní. DeepL API slouží jako záloha pro chybějící překlady.</p>
</div>

<!-- =============================================
     SEKCE 05 — ODHAD ČASU VÝVOJE
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">05</span>
        <h2>Odhad času vývoje</h2>
    </div>

    <p>Odhad vychází z <strong>reálně změřeného rozsahu kódu</strong> a funkčností. Počítá s tvorbou od nuly, nikoliv s existujícím frameworkem nebo CMS.</p>

    <div class="tabulka-obal">
    <table>
        <thead>
            <tr>
                <th>Oblast</th>
                <th>Rozsah práce</th>
                <th>Juniorní dev</th>
                <th>Seniorní dev</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>PHP Backend + API</strong></td>
                <td>73 API endpointů, 8 controllerů (~61 000 řádků), security infrastruktura</td>
                <td>900–1 400 h</td>
                <td>600–850 h</td>
            </tr>
            <tr>
                <td><strong>JavaScript Frontend</strong></td>
                <td>97 souborů, 34 394 řádků, PWA, analytics, heatmap, gamifikace</td>
                <td>700–1 000 h</td>
                <td>420–650 h</td>
            </tr>
            <tr>
                <td><strong>CSS / Design implementace</strong></td>
                <td>58 CSS souborů, design systém, responzivita, animace</td>
                <td>250–380 h</td>
                <td>160–240 h</td>
            </tr>
            <tr>
                <td><strong>Databáze a migrace</strong></td>
                <td>10+ tabulek, migrace, indexy, enum mapping</td>
                <td>60–90 h</td>
                <td>35–55 h</td>
            </tr>
            <tr>
                <td><strong>DevOps a deployment</strong></td>
                <td>GitHub Actions CI/CD, Nginx config, .htaccess, SSL</td>
                <td>40–60 h</td>
                <td>25–40 h</td>
            </tr>
            <tr>
                <td><strong>Testování</strong></td>
                <td>Jest (frontend), PHPUnit (backend), manuální QA</td>
                <td>80–120 h</td>
                <td>60–90 h</td>
            </tr>
            <tr>
                <td><strong>Architektura a PM</strong></td>
                <td>Návrh, dokumentace (CLAUDE.md), code review</td>
                <td>—</td>
                <td>80–130 h</td>
            </tr>
            <tr style="background:#111;color:#fff;">
                <td><strong>CELKEM</strong></td>
                <td></td>
                <td><strong>2 030–3 050 h</strong></td>
                <td><strong>1 380–2 055 h</strong></td>
            </tr>
        </tbody>
    </table>
    </div>

    <div class="tabulka-obal">
    <table>
        <thead><tr><th>Role</th><th>Rozsah</th><th>Poznámka</th></tr></thead>
        <tbody>
            <tr><td><strong>Seniorní developer (full-stack)</strong></td><td>1 380–2 055 hodin</td><td>Realistický odhad pro zkušeného PHP+JS developera</td></tr>
            <tr><td><strong>UX/UI designér</strong></td><td>80–140 hodin</td><td>Design systém, prototypy, grafické podklady (ne implementace)</td></tr>
            <tr><td><strong>Copywriter</strong></td><td>30–60 hodin</td><td>Texty pro web, CTA, vícejazyčná lokalizace s DeepL podporou</td></tr>
        </tbody>
    </table>
    </div>

    <p><strong>Klíčový poznatek:</strong> Největší časová zátěž je v controllerech — samotný <code>login_controller.php</code> má 14 318 řádků, <code>load.php</code> má 13 198 řádků. Tyto soubory zahrnují edge-casy, bezpečnostní logiku a business pravidla, která se nashromáždila v průběhu iterativního vývoje.</p>
</div>

<!-- =============================================
     SEKCE 06 — FINANČNÍ HODNOTA (VÝROBA)
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">06</span>
        <h2>Finanční hodnota projektu — Cena výroby</h2>
    </div>

    <p>Odhad vychází z rozsahu seniorní práce (1 380–2 055 h) a aktuálních sazeb na trhu (2026).</p>

    <div class="financni-mrizka">
        <div class="financni-karta">
            <div class="region">Evropa — Seniorní freelancer</div>
            <div class="castka">€100 000 – €165 000</div>
            <div class="podnazev">Sazba €70–80/h · 1 380–2 055 h</div>
            <div class="detail">Zahrnuje full-stack vývoj, bezpečnostní audit, deployment. Bez nákladů na UX designéra a copywritera.</div>
        </div>
        <div class="financni-karta">
            <div class="region">Evropa — Digitální agentura</div>
            <div class="castka">€160 000 – €280 000</div>
            <div class="podnazev">Sazba €100–140/h + overhead agentury 40–60 %</div>
            <div class="detail">Zahrnuje projektového manažera, QA testera, devops a account management. Cenová nabídka v rozsahu enterprise projektů.</div>
        </div>
        <div class="financni-karta">
            <div class="region">Česká republika — Seniorní freelancer</div>
            <div class="castka">1 200 000 – 2 200 000 Kč</div>
            <div class="podnazev">Sazba 900–1 100 Kč/h · 1 380–2 055 h</div>
            <div class="detail">Realistická sazba pro zkušeného CZ freelancera v oblasti PHP/JS full-stack na roku 2026.</div>
        </div>
        <div class="financni-karta">
            <div class="region">Česká republika — IT agentura</div>
            <div class="castka">1 900 000 – 3 800 000 Kč</div>
            <div class="podnazev">Sazba 1 400–1 850 Kč/h + overhead</div>
            <div class="detail">Větší agentura s PM, designérem, testerem. Projekt by byl kategorizován jako enterprise zakázka s delší dobou dodání.</div>
        </div>
    </div>

    <h3>Doplňkové náklady (nezahrnuty výše)</h3>
    <div class="tabulka-obal">
    <table>
        <thead><tr><th>Položka</th><th>Odhadnutý rozsah (CZ)</th></tr></thead>
        <tbody>
            <tr><td>UX/UI designér (80–140 h × 1 000–1 500 Kč/h)</td><td>80 000 – 210 000 Kč</td></tr>
            <tr><td>Copywriter / lokalizace CZ/EN/IT (30–60 h)</td><td>15 000 – 60 000 Kč</td></tr>
            <tr><td>API klíče (Geoapify, DeepL, SMTP)</td><td>5 000 – 30 000 Kč/rok</td></tr>
            <tr><td>Hosting a doména (Nginx VPS)</td><td>6 000 – 18 000 Kč/rok</td></tr>
            <tr><td>SSL certifikát</td><td>Zdarma (Let's Encrypt)</td></tr>
        </tbody>
    </table>
    </div>
</div>

<!-- =============================================
     SEKCE 07 — PRODEJNÍ HODNOTA
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">07</span>
        <h2>Prodejní hodnota projektu</h2>
    </div>

    <p>Hodnota při prodeji závisí na scénáři. Projekt je specificky zaměřen na segment luxusního nábytku — to omezuje cílový trh ale zvyšuje hodnotu v niche.</p>

    <div class="tabulka-obal">
    <table>
        <thead>
            <tr>
                <th>Scénář prodeje</th>
                <th>Odhadnutá tržní hodnota</th>
                <th>Zdůvodnění</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Hotový projekt (bespoke systém)</strong><br><small>Prodej dalšímu luxusnímu prodejci / servisu</small></td>
                <td>800 000 – 1 500 000 Kč<br><small>(€32 000 – €60 000)</small></td>
                <td>Specializovaný systém. Kupující ušetří 1–2 roky vývoje. Nutná adaptace na jiného klienta sníží cenu oproti výrobním nákladům.</td>
            </tr>
            <tr>
                <td><strong>White-label SaaS platforma</strong><br><small>Měsíční předplatné pro servisní firmy</small></td>
                <td>2 000 000 – 6 000 000 Kč<br><small>(€80 000 – €240 000)</small></td>
                <td>Při 10–30 klientech × 5 000–15 000 Kč/měsíc a multiplikátoru 3–5× ARR. Vyžaduje investici do multi-tenancy architektury.</td>
            </tr>
            <tr>
                <td><strong>Startup asset (s klientskou základnou)</strong><br><small>Prodej investorovi nebo strategickému kupci</small></td>
                <td>3 000 000 – 10 000 000 Kč<br><small>(€120 000 – €400 000)</small></td>
                <td>Závisí na počtu aktivních klientů, MRR a retenci. Horní hranice dosažitelná při prokázaném rostoucím ARR a škálovatelné architektuře.</td>
            </tr>
        </tbody>
    </table>
    </div>

    <p><strong>Klíčový faktor hodnoty:</strong> Současná hodnota projektu je primárně v <em>custom business logice</em> — 14 000 řádků autentizační logiky, 13 000 řádků datového loaderu a 73 API endpointů reprezentují desítky let iterovaných požadavků, které by bylo extrémně těžké replikovat z JIRA ticketů nebo specifikace.</p>
</div>

<!-- =============================================
     SEKCE 08 — SILNÉ STRÁNKY
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">08</span>
        <h2>Silné stránky projektu</h2>
    </div>

    <div class="dva-sloupce">
        <div class="blok">
            <h4>Technická excelence</h4>
            <ul>
                <li>Komplexní bezpečnostní vrstva — CSRF, rate limiting, audit log, CSP headers, session hardening</li>
                <li>Production-safe logger.js — console.log potlačen v produkci</li>
                <li>Minifikace všech JS a CSS assets — výkon načítání</li>
                <li>Plnohodnotná PWA implementace včetně offline mode</li>
                <li>GitHub Actions CI/CD — profesionální deployment pipeline</li>
                <li>Email fronta s retry logikou — spolehlivé doručování</li>
                <li>Vlastní analytika a heatmap — nezávislost na Google</li>
                <li>Jest + PHPUnit testing suite</li>
                <li>Composer + npm dependency management</li>
                <li>Strukturované API responses (sendJsonSuccess/Error)</li>
            </ul>
        </div>
        <div class="blok">
            <h4>Produktová hodnota</h4>
            <ul>
                <li>Pokrývá celý workflow: reklamace → fotky → protokol → platba → email</li>
                <li>Trilingvní (CZ/EN/IT) — adekvátní pro Natuzzi jako italskou značku</li>
                <li>Role-based přístup: admin / uživatel / supervisor</li>
                <li>Mapová integrace s geolokací zákazníka</li>
                <li>QR platební kódy integrované do ceníku</li>
                <li>Push notifikace na mobilní zařízení techniků</li>
                <li>PDF generování servisních protokolů</li>
                <li>Herní zóna pro motivaci týmu</li>
                <li>Vlastní design systém (černobílý s dokumentovanými výjimkami)</li>
                <li>Konzistentní coding konvence (česky — celý CLAUDE.md)</li>
            </ul>
        </div>
    </div>
</div>

<!-- =============================================
     SEKCE 09 — SLABÉ STRÁNKY
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">09</span>
        <h2>Slabé stránky projektu</h2>
    </div>

    <div class="dva-sloupce">
        <div class="blok slaby">
            <h4>Architektura a kód</h4>
            <ul>
                <li>125 PHP souborů v root adresáři — absence MVC frameworku nebo routeru</li>
                <li>Controllerové soubory příliš velké (14 000 + 13 000 řádků) — monolity</li>
                <li>button-fixes-global.css a cenik-wizard-fix.css — symptom nekonzistencí</li>
                <li>58 CSS souborů bez CSS variables / design tokenů</li>
                <li>Absence ORM — raw PDO queries napříč 73 API soubory</li>
                <li>Žádný centrální router — každý soubor je samostatný endpoint</li>
                <li>Debug/diagnostic skripty v root složce (bezpečnostní riziko)</li>
                <li>Absence API versioning (např. /api/v1/)</li>
            </ul>
        </div>
        <div class="blok slaby">
            <h4>Škálovatelnost a provoz</h4>
            <ul>
                <li>Single-tenant architektura — obtížná konverze na SaaS bez refaktoru</li>
                <li>Žádný caching layer (Redis/Memcached) — každý request = DB dotaz</li>
                <li>Vlastní analytika místo GA4 — absence benchmark dat a porovnání</li>
                <li>Neznámý stav SEO — absence sitemap.xml potvrzené v auditu</li>
                <li>Absence automatizovaných E2E testů (Playwright/Cypress)</li>
                <li>Žádná API dokumentace (Swagger/OpenAPI)</li>
                <li>Monitoring a alerting — stav neznámý</li>
            </ul>
        </div>
    </div>
</div>

<!-- =============================================
     SEKCE 10 — DOPORUČENÍ
     ============================================= -->
<div class="sekce">
    <div class="sekce-hlavicka">
        <span class="sekce-cislo">10</span>
        <h2>Doporučení pro zvýšení hodnoty projektu</h2>
    </div>

    <div class="doporuceni-seznam">

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">1</div>
            <div class="doporuceni-obsah">
                <h4>Přesunout diagnostic/debug skripty z root adresáře</h4>
                <p>Root složka obsahuje skripty pro diagnostiku, migrace a testování. Přesunout je do /tools/ nebo /dev/ a přidat authentication gate. Aktuálně jsou potenciálním bezpečnostním rizikem, pokud jsou dostupné bez autentizace.</p>
                <div class="dopad">Dopad: Bezpečnost · Obtížnost: Nízká · Čas: 4–8 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">2</div>
            <div class="doporuceni-obsah">
                <h4>Rozdělit monolitické controllery na menší moduly</h4>
                <p>login_controller.php (14 318 ř.) a load.php (13 198 ř.) jsou příliš velké pro bezpečné udržování. Rozdělit na logické třídy nebo services (AuthService, UserSession, RateLimiter). Zlepší testovatelnost a přehlednost.</p>
                <div class="dopad">Dopad: Maintainability · Obtížnost: Vysoká · Čas: 60–120 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">3</div>
            <div class="doporuceni-obsah">
                <h4>Přidat Redis caching pro časté DB dotazy</h4>
                <p>Seznamy reklamací, ceníkové položky a statistiky jsou načítány opakovaně. Redis cache s TTL 5–60 minut drasticky sníží zátěž DB a zrychlí odezvu pro uživatele. Kritické pro škálování při více klientech.</p>
                <div class="dopad">Dopad: Výkon · Obtížnost: Střední · Čas: 20–40 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">4</div>
            <div class="doporuceni-obsah">
                <h4>Doplnit sitemap.xml a robots.txt, ověřit Open Graph tagy</h4>
                <p>Pro trilingvní web je klíčové mít hreflang atributy v sitemap. Zkontrolovat og:title, og:description, og:image na všech stránkách. Nastavit Google Search Console. Toto je nejlevnější způsob zvýšení organické návštěvnosti.</p>
                <div class="dopad">Dopad: SEO / Viditelnost · Obtížnost: Nízká · Čas: 6–12 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">5</div>
            <div class="doporuceni-obsah">
                <h4>Vytvořit API dokumentaci (OpenAPI/Swagger)</h4>
                <p>73 API endpointů bez dokumentace ztěžuje onboarding dalších developerů a zvyšuje riziko chyb při integraci. OpenAPI spec generovaný ze stávajících komentářů by signifikantně zvýšil prodejní hodnotu systému (enterprise klienti to vyžadují).</p>
                <div class="dopad">Dopad: Prodejnost · Obtížnost: Střední · Čas: 30–50 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">6</div>
            <div class="doporuceni-obsah">
                <h4>Přidat CSS variables / design tokeny</h4>
                <p>58 CSS souborů bez centrálních proměnných. Zavedení :root { --color-primary: #111; --spacing-md: 16px; } do styles.css a refaktoring ostatních souborů. Enormně zrychlí budoucí úpravy a reskinning pro jiné klienty.</p>
                <div class="dopad">Dopad: Design / Škálovatelnost · Obtížnost: Střední · Čas: 20–35 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">7</div>
            <div class="doporuceni-obsah">
                <h4>Implementovat E2E testy pro kritické workflow</h4>
                <p>Pokrytí: přihlášení → vytvoření reklamace → nahrání fotky → odeslání emailu → zobrazení v seznamu. Playwright nebo Cypress. Chrání před regresemi při refaktoringu a zvyšuje důvěru při deploymentu.</p>
                <div class="dopad">Dopad: Kvalita / Bezpečnost regresí · Obtížnost: Střední · Čas: 30–60 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">8</div>
            <div class="doporuceni-obsah">
                <h4>Připravit multi-tenant architekturu pro SaaS konverzi</h4>
                <p>Přidat sloupec tenant_id do klíčových tabulek, implementovat tenant middleware a izolaci dat. Tato investice (80–150 h) zvýší potenciální prodejní hodnotu projektu 3–5× přechodem z bespoke nástroje na SaaS platformu.</p>
                <div class="dopad">Dopad: Obchodní hodnota (kritický krok pro SaaS) · Obtížnost: Vysoká · Čas: 80–150 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">9</div>
            <div class="doporuceni-obsah">
                <h4>Nastavit monitoring a alerting (Uptime Robot / Sentry)</h4>
                <p>Aktuálně není viditelný žádný externě monitorovaný uptime nebo error tracking. Sentry pro PHP + JS (free tier) a Uptime Robot pro dostupnost. Klienti enterprise kategorie toto vyžadují jako součást SLA.</p>
                <div class="dopad">Dopad: Spolehlivost / SLA · Obtížnost: Nízká · Čas: 4–8 h</div>
            </div>
        </div>

        <div class="doporuceni-polozka">
            <div class="doporuceni-cislo">10</div>
            <div class="doporuceni-obsah">
                <h4>Implementovat centrální router (slim/slim nebo vlastní)</h4>
                <p>Nahradit 125 loose PHP souborů v root adresáři centrálním routerem. Slim Framework 4 je minimalistický a kompatibilní s existující PHP 8.4 + PDO architekturou. Zlepší bezpečnost (méně exposed endpointů), výkon a přehlednost projektu.</p>
                <div class="dopad">Dopad: Architektura / Bezpečnost · Obtížnost: Velmi vysoká · Čas: 100–200 h</div>
            </div>
        </div>

    </div>
</div>

<!-- =============================================
     ZÁVĚREČNÝ BLOK — SHRNUTÍ HODNOTY
     ============================================= -->
<div class="zaverecny-blok">
    <div>
        <div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#666;margin-bottom:8px;">Celkové hodnocení projektu</div>
        <div class="shrnuti-hodnota">WGS Service — Pokročilá B2B webová aplikace</div>
        <p>Projekt překračuje kategorii "firemní web" a patří do kategorie custom enterprise aplikace pro správu servisních zakázek s plnohodnotnou bezpečnostní infrastrukturou, PWA, vícejazyčností a rolemi uživatelů.</p>
    </div>
    <div class="zaverecny-mrizka">
        <div class="polozka">
            <span>Odhadnutý čas vývoje (senior dev)</span>
            <strong>1 380 – 2 055 hodin</strong>
        </div>
        <div class="polozka">
            <span>Výrobní cena (CZ trh)</span>
            <strong>1,2 – 3,8 mil. Kč</strong>
        </div>
        <div class="polozka">
            <span>Prodejní hodnota (bespoke)</span>
            <strong>0,8 – 1,5 mil. Kč</strong>
        </div>
        <div class="polozka">
            <span>Prodejní hodnota (SaaS)</span>
            <strong>2 – 6 mil. Kč</strong>
        </div>
        <div class="polozka">
            <span>Výrobní cena (EU trh)</span>
            <strong>€100 000 – €280 000</strong>
        </div>
        <div class="polozka">
            <span>Datum auditu</span>
            <strong><?php echo $datumGenerovani; ?></strong>
        </div>
    </div>
</div>

</div><!-- /dokument -->
</body>
</html>
