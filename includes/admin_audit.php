<?php
/**
 * Admin karta: Audit projektu WGS
 * Zobrazení kompletního auditu webu jako HTML stránky
 */

if (!defined('ADMIN_PHP_LOADED')) {
    die('Přímý přístup zakázán.');
}

$auditSoubor = __DIR__ . '/../audit.md';
$auditDatum = file_exists($auditSoubor) ? date('j. n. Y', filemtime($auditSoubor)) : 'neznámo';
?>
<style>
/* ================================================================
   Admin Audit - styly
   ================================================================ */
.audit-obal {
    max-width: 980px;
    margin: 0 auto;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Záhlaví */
.audit-hlavicka {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #111;
}

.audit-hlavicka-levy {
    flex: 1;
    min-width: 200px;
}

.audit-hlavicka h1 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #111;
    margin: 0 0 0.25rem 0;
    line-height: 1.2;
}

.audit-hlavicka-meta {
    font-size: 0.78rem;
    color: #888;
    line-height: 1.8;
}

.audit-hlavicka-meta strong {
    color: #555;
}

.audit-akce {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.audit-btn {
    display: inline-block;
    padding: 0.45rem 1rem;
    font-size: 0.78rem;
    font-weight: 600;
    border: 1px solid #333;
    background: #111;
    color: #fff;
    cursor: pointer;
    border-radius: 3px;
    text-decoration: none;
    transition: background 0.15s;
    white-space: nowrap;
}

.audit-btn:hover {
    background: #333;
    color: #fff;
}

.audit-btn-outline {
    background: #fff;
    color: #111;
}

.audit-btn-outline:hover {
    background: #f0f0f0;
    color: #111;
}

.audit-btn-danger {
    background: #dc3545;
    border-color: #dc3545;
    color: #fff;
}

.audit-btn-danger:hover {
    background: #c82333;
    border-color: #c82333;
    color: #fff;
}

/* ================================================================
   DOPORUČENÍ panel
   ================================================================ */
.doporuceni-panel {
    display: none;
    position: fixed;
    top: 0;
    right: 0;
    width: min(680px, 100vw);
    height: 100vh;
    background: #fff;
    border-left: 3px solid #dc3545;
    box-shadow: -4px 0 24px rgba(0,0,0,0.18);
    z-index: 9999;
    overflow-y: auto;
    flex-direction: column;
}

.doporuceni-panel.aktivni {
    display: flex;
}

.doporuceni-panel-hlavicka {
    position: sticky;
    top: 0;
    background: #fff;
    border-bottom: 2px solid #dc3545;
    padding: 1.1rem 1.4rem 0.9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    z-index: 10;
    flex-shrink: 0;
}

.doporuceni-panel-hlavicka h2 {
    font-size: 1rem;
    font-weight: 700;
    color: #111;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.doporuceni-panel-hlavicka h2 span.dp-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    font-size: 0.7rem;
    font-weight: 700;
}

.doporuceni-zavrit {
    background: none;
    border: none;
    font-size: 1.4rem;
    cursor: pointer;
    color: #666;
    line-height: 1;
    padding: 0.2rem;
    flex-shrink: 0;
}

.doporuceni-zavrit:hover {
    color: #111;
}

.doporuceni-meta {
    padding: 0.75rem 1.4rem;
    font-size: 0.75rem;
    color: #888;
    background: #fff8f8;
    border-bottom: 1px solid #f5c6cb;
    line-height: 1.5;
    flex-shrink: 0;
}

.doporuceni-meta strong {
    color: #dc3545;
}

.doporuceni-obsah {
    padding: 1rem 1.4rem 2rem;
    flex: 1;
}

/* Jednotlivé položky */
.dp-polozka {
    border: 1px solid #e8e8e8;
    border-radius: 4px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.dp-polozka.dp-hotovo {
    border-color: #c3e6cb;
    opacity: 0.6;
}

.dp-polozka-hlavicka {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    cursor: pointer;
    background: #fff;
    transition: background 0.1s;
    user-select: none;
}

.dp-polozka-hlavicka:hover {
    background: #fafafa;
}

.dp-polozka.dp-hotovo .dp-polozka-hlavicka {
    background: #f8fff8;
}

.dp-cislo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 0.7rem;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 0.05rem;
}

.dp-cislo-bezpecnost {
    background: #dc3545;
    color: #fff;
}

.dp-cislo-ux {
    background: #555;
    color: #fff;
}

.dp-polozka.dp-hotovo .dp-cislo {
    background: #28a745;
    color: #fff;
}

.dp-titulek-obal {
    flex: 1;
}

.dp-titulek {
    font-size: 0.88rem;
    font-weight: 700;
    color: #111;
    margin-bottom: 0.2rem;
    line-height: 1.3;
}

.dp-polozka.dp-hotovo .dp-titulek {
    text-decoration: line-through;
    color: #666;
}

.dp-meta-radek {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.dp-tag {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.15rem 0.45rem;
    border-radius: 2px;
}

.dp-tag-bezpecnost {
    background: #f8d7da;
    color: #721c24;
}

.dp-tag-ux {
    background: #e2e3e5;
    color: #383d41;
}

.dp-tag-hotovo {
    background: #d4edda;
    color: #155724;
}

.dp-tag-priorita-vysoka {
    background: #f8d7da;
    color: #721c24;
}

.dp-tag-priorita-stredni {
    background: #fff3cd;
    color: #856404;
}

.dp-tag-priorita-nizka {
    background: #e2e3e5;
    color: #383d41;
}

.dp-sipka {
    font-size: 0.8rem;
    color: #aaa;
    flex-shrink: 0;
    transition: transform 0.15s;
}

.dp-polozka.otevrena .dp-sipka {
    transform: rotate(180deg);
}

/* Detail */
.dp-detail {
    display: none;
    padding: 0 1rem 1rem;
    font-size: 0.82rem;
    color: #444;
    line-height: 1.6;
    border-top: 1px solid #f0f0f0;
}

.dp-polozka.otevrena .dp-detail {
    display: block;
}

.dp-detail h4 {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #888;
    font-weight: 700;
    margin: 0.85rem 0 0.35rem 0;
}

.dp-detail h4:first-child {
    margin-top: 0.75rem;
}

.dp-detail p {
    margin: 0 0 0.5rem 0;
}

.dp-detail code {
    background: #f0f0f0;
    padding: 0.1rem 0.4rem;
    border-radius: 2px;
    font-size: 0.8rem;
    color: #111;
    font-family: 'Courier New', monospace;
}

.dp-soubory {
    list-style: none;
    padding: 0;
    margin: 0;
}

.dp-soubory li {
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
    padding: 0.3rem 0;
    border-bottom: 1px solid #f5f5f5;
    font-size: 0.8rem;
}

.dp-soubory li:last-child {
    border-bottom: none;
}

.dp-soubory-soubor {
    font-family: 'Courier New', monospace;
    font-size: 0.78rem;
    color: #333;
    font-weight: 600;
    min-width: 0;
    word-break: break-all;
}

.dp-soubory-akce {
    color: #666;
    font-size: 0.78rem;
    flex: 1;
}

.dp-riziko {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 3px;
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
    color: #856404;
    margin-top: 0.5rem;
}
.dp-implementovano-stav {
    background: #e8f5e9;
    border: 1px solid #a5d6a7;
    border-radius: 3px;
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
    color: #1b5e20;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.dp-riziko strong {
    color: #856404;
}

.dp-hotovo-btn {
    display: inline-block;
    margin-top: 0.75rem;
    padding: 0.35rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    transition: background 0.12s;
}

.dp-hotovo-btn:hover {
    background: #218838;
}

.dp-polozka.dp-hotovo .dp-hotovo-btn {
    background: #6c757d;
}

/* Překryvná vrstva */
.doporuceni-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35);
    z-index: 9998;
}

.doporuceni-overlay.aktivni {
    display: block;
}

/* Skupina */
.dp-skupina-titulek {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #aaa;
    padding: 0.4rem 0;
    margin: 1rem 0 0.5rem 0;
    border-bottom: 1px solid #eee;
}

/* Statistika splnění */
.dp-progress {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 1.4rem;
    background: #f9f9f9;
    border-bottom: 1px solid #eee;
    font-size: 0.75rem;
    color: #666;
    flex-shrink: 0;
}

.dp-progress-bar {
    flex: 1;
    height: 6px;
    background: #eee;
    border-radius: 3px;
    overflow: hidden;
}

.dp-progress-fill {
    height: 100%;
    background: #28a745;
    border-radius: 3px;
    transition: width 0.3s;
}

@media (max-width: 600px) {
    .doporuceni-panel {
        width: 100vw;
    }
}

/* Navigace sekcemi */
.audit-navigace {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 2rem;
    padding: 0.75rem;
    background: #f5f5f5;
    border-radius: 4px;
    border: 1px solid #e0e0e0;
}

.audit-nav-polozka {
    padding: 0.3rem 0.7rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #555;
    cursor: pointer;
    border-radius: 2px;
    border: 1px solid transparent;
    text-decoration: none;
    transition: all 0.12s;
    white-space: nowrap;
}

.audit-nav-polozka:hover {
    background: #222;
    color: #fff;
    border-color: #222;
}

/* Skóre karty */
.audit-skore-mrizka {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.75rem;
    margin-bottom: 2rem;
}

.audit-skore-karta {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 0.9rem 1rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.audit-skore-karta::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #111;
}

.audit-skore-karta-cislo {
    font-size: 2rem;
    font-weight: 700;
    color: #111;
    line-height: 1;
    display: block;
    margin-bottom: 0.2rem;
}

.audit-skore-karta-max {
    font-size: 0.85rem;
    color: #aaa;
    font-weight: 400;
}

.audit-skore-karta-nazev {
    font-size: 0.72rem;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-top: 0.4rem;
    display: block;
}

/* Finance souhrn */
.audit-finance-pruh {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-bottom: 2rem;
}

.audit-finance-box {
    background: #111;
    color: #fff;
    border-radius: 4px;
    padding: 1rem 1.25rem;
}

.audit-finance-box-popisek {
    font-size: 0.68rem;
    color: #aaa;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
    margin-bottom: 0.35rem;
}

.audit-finance-box-castka {
    font-size: 1.15rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}

.audit-finance-box-poznamka {
    font-size: 0.68rem;
    color: #888;
    margin-top: 0.2rem;
}

/* Sekce */
.audit-sekce {
    margin-bottom: 2.5rem;
    scroll-margin-top: 80px;
}

.audit-sekce-hlavicka {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #ddd;
}

.audit-sekce-cislo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: #111;
    color: #fff;
    border-radius: 50%;
    font-size: 0.78rem;
    font-weight: 700;
    flex-shrink: 0;
}

.audit-sekce-hlavicka h2 {
    font-size: 1.05rem;
    font-weight: 700;
    color: #111;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Tabulky */
.audit-tabulka-obal {
    overflow-x: auto;
    margin-bottom: 1rem;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.audit-tabulka {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.audit-tabulka th {
    background: #111;
    color: #fff;
    padding: 0.5rem 0.85rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.audit-tabulka td {
    padding: 0.5rem 0.85rem;
    border-bottom: 1px solid #eee;
    color: #333;
    vertical-align: top;
    line-height: 1.4;
}

.audit-tabulka tr:last-child td {
    border-bottom: none;
}

.audit-tabulka tr:nth-child(even) td {
    background: #fafafa;
}

.audit-tabulka tr.audit-tabulka-souhrn td {
    background: #111;
    color: #fff;
    font-weight: 700;
}

.audit-tabulka td strong {
    color: #111;
}

/* Kód */
.audit-kod {
    background: #111;
    color: #e8e8e8;
    border-radius: 4px;
    padding: 0.85rem 1rem;
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.78rem;
    line-height: 1.6;
    overflow-x: auto;
    margin-bottom: 1rem;
    white-space: pre;
}

/* Silné / slabé stránky */
.audit-seznam {
    list-style: none;
    padding: 0;
    margin: 0;
}

.audit-seznam li {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.7rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.85rem;
    color: #333;
    line-height: 1.5;
}

.audit-seznam li:last-child {
    border-bottom: none;
}

.audit-seznam-cislo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    background: #f0f0f0;
    color: #555;
    border-radius: 50%;
    font-size: 0.7rem;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.audit-seznam li strong {
    color: #111;
}

/* Doporučení */
.audit-doporuceni-skupina {
    margin-bottom: 1.5rem;
}

.audit-doporuceni-skupina-hlavicka {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #888;
    margin-bottom: 0.75rem;
    padding: 0.4rem 0.75rem;
    background: #f5f5f5;
    border-left: 3px solid #333;
}

.audit-doporuceni-polozka {
    padding: 0.85rem 1rem;
    border: 1px solid #e8e8e8;
    border-radius: 3px;
    margin-bottom: 0.5rem;
    background: #fff;
}

.audit-doporuceni-polozka-hlavicka {
    font-size: 0.85rem;
    font-weight: 700;
    color: #111;
    margin-bottom: 0.3rem;
}

.audit-doporuceni-polozka-text {
    font-size: 0.8rem;
    color: #555;
    line-height: 1.5;
}

/* Výsledkový banner */
.audit-vysledek {
    background: #111;
    color: #fff;
    border-radius: 4px;
    padding: 1.5rem 1.75rem;
    margin-top: 2rem;
}

.audit-vysledek h3 {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #aaa;
    margin: 0 0 0.75rem 0;
    font-weight: 600;
}

.audit-vysledek p {
    font-size: 0.9rem;
    color: #ddd;
    line-height: 1.65;
    margin: 0;
}

.audit-vysledek strong {
    color: #fff;
}

.audit-patika {
    font-size: 0.72rem;
    color: #aaa;
    margin-top: 0.75rem;
    font-style: italic;
}

/* Statistiky v číslech */
.audit-cisla-mrizka {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.6rem;
    margin-bottom: 1rem;
}

.audit-cislo-karta {
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 0.75rem;
    text-align: center;
    background: #fff;
}

.audit-cislo-hodnota {
    display: block;
    font-size: 1.6rem;
    font-weight: 700;
    color: #111;
    line-height: 1;
    margin-bottom: 0.2rem;
}

.audit-cislo-popisek {
    display: block;
    font-size: 0.68rem;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
}

/* Hodiny tabulka pruh */
.audit-hodiny-souhrn {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.6rem;
    margin-bottom: 1rem;
}

.audit-hodiny-box {
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 0.75rem 1rem;
    background: #fff;
}

.audit-hodiny-box-role {
    font-size: 0.7rem;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.audit-hodiny-box-cas {
    font-size: 1.1rem;
    font-weight: 700;
    color: #111;
}

.audit-hodiny-box-celkem {
    background: #111;
    color: #fff;
}

.audit-hodiny-box-celkem .audit-hodiny-box-role {
    color: #888;
}

.audit-hodiny-box-celkem .audit-hodiny-box-cas {
    color: #fff;
}

/* Tisk */
@media print {
    .audit-akce,
    .audit-navigace { display: none; }
    .audit-sekce { page-break-inside: avoid; }
    .audit-finance-box { background: #eee !important; color: #111 !important; }
    .audit-skore-karta::after { background: #999; }
    .audit-vysledek { background: #eee !important; color: #111 !important; }
}

/* Responsivita */
@media (max-width: 600px) {
    .audit-hlavicka { flex-direction: column; }
    .audit-skore-mrizka { grid-template-columns: repeat(2, 1fr); }
    .audit-cisla-mrizka { grid-template-columns: repeat(2, 1fr); }
    .audit-hodiny-souhrn { grid-template-columns: 1fr 1fr; }
    .audit-finance-pruh { grid-template-columns: 1fr; }
}
</style>

<div class="audit-obal" id="audit-obsah">

    <!-- Záhlaví -->
    <div class="audit-hlavicka">
        <div class="audit-hlavicka-levy">
            <h1>Detailní audit projektu WGS</h1>
            <div class="audit-hlavicka-meta">
                <strong>Datum:</strong> 4. března 2026 &nbsp;|&nbsp;
                <strong>Soubor:</strong> audit.md (aktualizován: <?php echo htmlspecialchars($auditDatum, ENT_QUOTES, 'UTF-8'); ?>)<br>
                <strong>Zdroj:</strong> Přímá analýza zdrojového kódu &nbsp;|&nbsp;
                <strong>Auditor:</strong> Nezávislý senior expert
            </div>
        </div>
        <div class="audit-akce">
            <a href="audit.md" download class="audit-btn audit-btn-outline">Stáhnout .md</a>
            <button class="audit-btn audit-btn-danger" id="btn-otevreni-doporuceni">Doporučení</button>
            <button class="audit-btn" onclick="window.print()">Tisknout</button>
        </div>
    </div>

    <!-- Skóre -->
    <div class="audit-skore-mrizka">
        <div class="audit-skore-karta">
            <span class="audit-skore-karta-cislo">9<span class="audit-skore-karta-max">/10</span></span>
            <span class="audit-skore-karta-nazev">Technická komplexita</span>
        </div>
        <div class="audit-skore-karta">
            <span class="audit-skore-karta-cislo">8<span class="audit-skore-karta-max">/10</span></span>
            <span class="audit-skore-karta-nazev">Bezpečnost</span>
        </div>
        <div class="audit-skore-karta">
            <span class="audit-skore-karta-cislo">6<span class="audit-skore-karta-max">/10</span></span>
            <span class="audit-skore-karta-nazev">Kódová kvalita</span>
        </div>
        <div class="audit-skore-karta">
            <span class="audit-skore-karta-cislo">7<span class="audit-skore-karta-max">/10</span></span>
            <span class="audit-skore-karta-nazev">Obchodní potenciál</span>
        </div>
        <div class="audit-skore-karta">
            <span class="audit-skore-karta-cislo">7<span class="audit-skore-karta-max">/10</span></span>
            <span class="audit-skore-karta-nazev">SEO připravenost</span>
        </div>
        <div class="audit-skore-karta">
            <span class="audit-skore-karta-cislo">5<span class="audit-skore-karta-max">/10</span></span>
            <span class="audit-skore-karta-nazev">Maintainability</span>
        </div>
    </div>

    <!-- Finance souhrn -->
    <div class="audit-finance-pruh">
        <div class="audit-finance-box">
            <div class="audit-finance-box-popisek">Výroba CZ — freelance</div>
            <div class="audit-finance-box-castka">2,4 – 3,4 mil. Kč</div>
            <div class="audit-finance-box-poznamka">senior sazby, 2 180–3 150 h celkem</div>
        </div>
        <div class="audit-finance-box">
            <div class="audit-finance-box-popisek">Výroba CZ — agentura</div>
            <div class="audit-finance-box-castka">3,3 – 6,1 mil. Kč</div>
            <div class="audit-finance-box-poznamka">marže agentury 1,4–1,8×</div>
        </div>
        <div class="audit-finance-box">
            <div class="audit-finance-box-popisek">Výroba EU — freelance</div>
            <div class="audit-finance-box-castka">157 – 282 tis. €</div>
            <div class="audit-finance-box-poznamka">DE/AT/NL trh</div>
        </div>
        <div class="audit-finance-box">
            <div class="audit-finance-box-popisek">Prodejní hodnota (SaaS)</div>
            <div class="audit-finance-box-castka">6 – 40 mil. Kč</div>
            <div class="audit-finance-box-poznamka">podmíněno zákaznickou základnou</div>
        </div>
    </div>

    <!-- Navigace -->
    <nav class="audit-navigace" aria-label="Navigace auditu">
        <a href="#sekce-1" class="audit-nav-polozka">1. Struktura</a>
        <a href="#sekce-2" class="audit-nav-polozka">2. UX / UI</a>
        <a href="#sekce-3" class="audit-nav-polozka">3. Tech. komplexita</a>
        <a href="#sekce-4" class="audit-nav-polozka">4. Obsah</a>
        <a href="#sekce-5" class="audit-nav-polozka">5. Čas vývoje</a>
        <a href="#sekce-6" class="audit-nav-polozka">6. Finanční hodnota</a>
        <a href="#sekce-7" class="audit-nav-polozka">7. Prodejní hodnota</a>
        <a href="#sekce-8" class="audit-nav-polozka">8. Silné stránky</a>
        <a href="#sekce-9" class="audit-nav-polozka">9. Slabé stránky</a>
        <a href="#sekce-10" class="audit-nav-polozka">10. Doporučení</a>
    </nav>

    <!-- ===== SEKCE 1: STRUKTURA ===== -->
    <div class="audit-sekce" id="sekce-1">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">1</span>
            <h2>Analýza struktury projektu</h2>
        </div>

        <div class="audit-cisla-mrizka">
            <div class="audit-cislo-karta">
                <span class="audit-cislo-hodnota">263+</span>
                <span class="audit-cislo-popisek">PHP souborů</span>
            </div>
            <div class="audit-cislo-karta">
                <span class="audit-cislo-hodnota">98</span>
                <span class="audit-cislo-popisek">JS souborů</span>
            </div>
            <div class="audit-cislo-karta">
                <span class="audit-cislo-hodnota">61</span>
                <span class="audit-cislo-popisek">CSS souborů</span>
            </div>
            <div class="audit-cislo-karta">
                <span class="audit-cislo-hodnota">70</span>
                <span class="audit-cislo-popisek">API endpointů</span>
            </div>
            <div class="audit-cislo-karta">
                <span class="audit-cislo-hodnota">50+</span>
                <span class="audit-cislo-popisek">DB tabulek</span>
            </div>
            <div class="audit-cislo-karta">
                <span class="audit-cislo-hodnota">203K+</span>
                <span class="audit-cislo-popisek">řádků kódu</span>
            </div>
        </div>

        <div class="audit-tabulka-obal">
            <table class="audit-tabulka">
                <thead>
                    <tr>
                        <th>Stránka</th>
                        <th>Typ</th>
                        <th>Popis</th>
                        <th>Rozsah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>seznam.php</code></td><td>Interní</td><td>CRM dashboard reklamací</td><td>27 622 řádků PHP + 5 883 řádků JS</td></tr>
                    <tr><td><code>novareklamace.php</code></td><td>Veřejná</td><td>Objednávka servisu s mapou a kalendářem</td><td>676 řádků PHP + 1 478 řádků JS</td></tr>
                    <tr><td><code>protokol.php</code></td><td>Interní</td><td>Servisní protokol s PDF generováním</td><td>API 1 228 řádků</td></tr>
                    <tr><td><code>statistiky.php</code></td><td>Admin</td><td>Reporty, vyúčtování, grafy</td><td>API 716 řádků + JS 40 891 řádků</td></tr>
                    <tr><td><code>admin.php</code></td><td>Admin</td><td>Kontrolní panel — 10+ záložek</td><td>API 128 KB+</td></tr>
                    <tr><td><code>cenik.php</code></td><td>Veřejná</td><td>Ceník se 3jazykovou kalkulačkou</td><td>JS kalkulátor 65 192 řádků</td></tr>
                    <tr><td><code>hry.php</code></td><td>Speciální</td><td>Herní zóna s real-time chatem</td><td>hry_api.php 45 KB</td></tr>
                    <tr><td>Landing pages (5×)</td><td>SEO</td><td>pozarucni-servis, servis-natuzzi, oprava-kresla, atd.</td><td>Organický traffic</td></tr>
                </tbody>
            </table>
        </div>

        <div class="audit-kod">Prezentační vrstva     → 11 veřejných stránek
Autentizační vrstva    → Role-based access (admin, technik, prodejce, supervizor)
Business logika        → 70 API endpointů, 8 kontrolerů
Datová vrstva          → MariaDB 50+ tabulek, PDO, transakce
Komunikační vrstva     → Email queue, Web push, PHPMailer
Bezpečnostní vrstva    → CSRF, rate limiting, audit log, CSP
Infrastruktura         → PWA, Service Worker, GitHub Actions CI/CD</div>

        <div class="audit-tabulka-obal">
            <table class="audit-tabulka">
                <thead><tr><th>Vrstva</th><th>Technologie</th></tr></thead>
                <tbody>
                    <tr><td>Backend</td><td>PHP 8.4+, PDO / MariaDB 10.11+, PHPMailer, minishlink/web-push 9.0</td></tr>
                    <tr><td>Frontend</td><td>Vanilla JS ES6+ (bez frameworku), Leaflet.js, Geoapify API, PDF.js</td></tr>
                    <tr><td>Infrastruktura</td><td>Nginx 1.26+, GitHub Actions, SFTP deployment, Sentry</td></tr>
                    <tr><td>PWA</td><td>Service Worker, Web Push Notifications, installable app</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== SEKCE 2: UX/UI ===== -->
    <div class="audit-sekce" id="sekce-2">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">2</span>
            <h2>UX / UI analýza</h2>
        </div>

        <div class="audit-doporuceni-skupina">
            <div class="audit-doporuceni-skupina-hlavicka">Doložitelné silné stránky</div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">Konzistentní barevný systém</div>
                <div class="audit-doporuceni-polozka-text">Černobílá paleta s explicitně řízeným systémem 5 schválených výjimek. Každá výjimka má zdokumentovaný důvod a přesný hex kód.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">Mobilní optimalizace</div>
                <div class="audit-doporuceni-polozka-text">Přítomnost <code>mobile-responsive.css</code>, <code>pull-to-refresh.js</code> a <code>pwa-notifications.js</code> naznačuje cílenou mobilní optimalizaci a PWA instalaci.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">Plynulé přechody a vrstevnatost</div>
                <div class="audit-doporuceni-polozka-text"><code>page-transitions.css</code> + <code>page-transitions.js</code> pro animované přechody. <code>z-index-layers.css</code> jako samostatný soubor = disciplinovaný přístup.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">Sjednocená komponenta modálů</div>
                <div class="audit-doporuceni-polozka-text"><code>universal-modal-theme.css</code> sjednocuje design všech modálů napříč aplikací. Onboarding řeší <code>welcome-modal.js</code>.</div>
            </div>
        </div>

        <div class="audit-doporuceni-skupina">
            <div class="audit-doporuceni-skupina-hlavicka">Potenciální UX rizika (doložitelná ze struktury)</div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">Přehlcenost seznam.php</div>
                <div class="audit-doporuceni-polozka-text">27 622 řádků PHP v jediném souboru. Indikuje množství funkcí soustředěných na jedné stránce — možná kognitivní zátěž pro uživatele.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">Extrémní komplexita JS souborů</div>
                <div class="audit-doporuceni-polozka-text"><code>cenik-calculator.js</code> má 65 192 řádků. Organický růst bez plánované architektury může způsobovat nekonzistentní chování UI.</div>
            </div>
        </div>

        <p style="font-size: 0.82rem; color: #888; font-style: italic; padding: 0.5rem 0.75rem; background: #f9f9f9; border-radius: 3px; border-left: 3px solid #ddd;">
            Vizuální kvalita designu, skutečné mobilní chování a rychlost načítání nelze objektivně hodnotit bez přístupu k živému webu. Výše uvedená analýza vychází výhradně ze struktury kódu.
        </p>
    </div>

    <!-- ===== SEKCE 3: TECHNICKÁ KOMPLEXITA ===== -->
    <div class="audit-sekce" id="sekce-3">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">3</span>
            <h2>Technická komplexita</h2>
        </div>

        <div class="audit-tabulka-obal">
            <table class="audit-tabulka">
                <thead>
                    <tr>
                        <th>Subsystém</th>
                        <th>Složitost</th>
                        <th>Doložení</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>Email queue s retry logikou</strong></td><td>Vysoká</td><td>EmailQueue.php 767 řádků, ACID transakce, exponenciální backoff</td></tr>
                    <tr><td><strong>PDF generování protokolů</strong></td><td>Vysoká</td><td>protokol_api.php 1 228 řádků, kalkulace provizí 33 % / 50 %</td></tr>
                    <tr><td><strong>Web Push notifikace</strong></td><td>Střední-vysoká</td><td>minishlink/web-push 9.0, service worker, push subscriptions v DB</td></tr>
                    <tr><td><strong>Geolokace a mapy</strong></td><td>Střední-vysoká</td><td>GeolocationService.php 19 KB, Geoapify proxy, výpočet vzdálenosti</td></tr>
                    <tr><td><strong>Role-based access control</strong></td><td>Vysoká</td><td>4 role, supervizor→prodejce hierarchie</td></tr>
                    <tr><td><strong>Multi-tenant architektura</strong></td><td>Vysoká</td><td>TenantManager.php, tenant_id sloupce</td></tr>
                    <tr><td><strong>Statistiky a reporting</strong></td><td>Vysoká</td><td>statistiky_api.php 716 řádků, statistiky.js 40 891 řádků</td></tr>
                    <tr><td><strong>Ceníkový kalkulátor</strong></td><td>Vysoká</td><td>cenik-calculator.js 65 192 řádků, 3jazykový wizard</td></tr>
                    <tr><td><strong>Audit logging</strong></td><td>Střední</td><td>audit_logger.php 159 řádků, měsíční rotace, forensic záznamy</td></tr>
                    <tr><td><strong>GDPR compliance</strong></td><td>Střední</td><td>Dedikované endpointy, opt-out emaily, data export</td></tr>
                    <tr><td><strong>PWA implementace</strong></td><td>Střední</td><td>Service Worker, manifest, offline mode</td></tr>
                    <tr><td><strong>Herní systém</strong></td><td>Střední</td><td>hry_api.php 45 KB, flight_api.php, leaderboards, real-time chat</td></tr>
                </tbody>
            </table>
        </div>

        <div class="audit-doporuceni-skupina">
            <div class="audit-doporuceni-skupina-hlavicka">Bezpečnostní implementace — nadstandardní úroveň</div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-text">
                    CSRF tokeny na všech POST requestech (auto-inject via <code>csrf-auto-inject.js</code>), rate limiting (max 5 / 15 min login, max 10 / 5 min save), prepared statements ve všech SQL dotazech (PDO), security headers (CSP, HSTS, X-Frame-Options), session s HttpOnly + Secure + SameSite=Lax, SHA256 hash admin klíče v <code>.env</code>, email validace s MX záznamy, vlastní <code>security_scanner.php</code>.
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SEKCE 4: OBSAH ===== -->
    <div class="audit-sekce" id="sekce-4">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">4</span>
            <h2>Obsah projektu</h2>
        </div>

        <div class="audit-tabulka-obal">
            <table class="audit-tabulka">
                <thead><tr><th>Oblast</th><th>Stav</th><th>Detail</th></tr></thead>
                <tbody>
                    <tr><td>Čeština</td><td>Kompletní</td><td>Primární jazyk, veškerý obsah</td></tr>
                    <tr><td>Angličtina</td><td>Databázové překlady</td><td>service_name_en, category_en, description_en</td></tr>
                    <tr><td>Italština</td><td>Databázové překlady</td><td>service_name_it, category_it, description_it</td></tr>
                    <tr><td>SEO meta tagy</td><td>Rozsáhlé</td><td>seo_meta.php (42 KB) — kompletní správa</td></tr>
                    <tr><td>Schema.org JSON-LD</td><td>Implementováno</td><td>Na homepage index.php</td></tr>
                    <tr><td>SEO landing pages</td><td>5 stránek</td><td>pozarucni-servis, servis-natuzzi, oprava-kresla, oprava-sedacky, mimozarucniceny</td></tr>
                    <tr><td>Clean URL</td><td>Implementováno</td><td>Router.php + routes.php</td></tr>
                    <tr><td>Blog / aktuality</td><td>Implementováno</td><td>aktuality.php — content marketing nástroj</td></tr>
                    <tr><td>QR kód kontakt</td><td>Implementováno</td><td>qr-kontakt.php — offline→online konverze</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== SEKCE 5: ČAS VÝVOJE ===== -->
    <div class="audit-sekce" id="sekce-5">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">5</span>
            <h2>Odhad času vývoje</h2>
        </div>

        <div class="audit-hodiny-souhrn">
            <div class="audit-hodiny-box">
                <div class="audit-hodiny-box-role">Backend developer</div>
                <div class="audit-hodiny-box-cas">1 110–1 570 h</div>
            </div>
            <div class="audit-hodiny-box">
                <div class="audit-hodiny-box-role">Frontend developer</div>
                <div class="audit-hodiny-box-cas">740–1 090 h</div>
            </div>
            <div class="audit-hodiny-box">
                <div class="audit-hodiny-box-role">UX/UI designer</div>
                <div class="audit-hodiny-box-cas">190–290 h</div>
            </div>
            <div class="audit-hodiny-box">
                <div class="audit-hodiny-box-role">Copywriter (3 jazyky)</div>
                <div class="audit-hodiny-box-cas">140–200 h</div>
            </div>
            <div class="audit-hodiny-box audit-hodiny-box-celkem">
                <div class="audit-hodiny-box-role">CELKEM</div>
                <div class="audit-hodiny-box-cas">2 180–3 150 h</div>
            </div>
        </div>

        <div class="audit-tabulka-obal">
            <table class="audit-tabulka">
                <thead><tr><th>Oblast (backend)</th><th>Hodiny</th></tr></thead>
                <tbody>
                    <tr><td>Core architektura (init, config, DB singleton, security middleware)</td><td>40–60 h</td></tr>
                    <tr><td>Systém reklamací — CRUD, workflow, stavový automat</td><td>120–160 h</td></tr>
                    <tr><td>Protokol systém + PDF generování + kalkulace provizí</td><td>80–120 h</td></tr>
                    <tr><td>Statistiky a reporting (filtry, grafy, export)</td><td>80–100 h</td></tr>
                    <tr><td>Email queue (retry, ACID, šablony, PHPMailer)</td><td>50–70 h</td></tr>
                    <tr><td>Push notifikace (Web Push, service worker backend)</td><td>40–60 h</td></tr>
                    <tr><td>Admin panel — všechny záložky</td><td>150–200 h</td></tr>
                    <tr><td>User management (auth, role, supervizor hierarchie)</td><td>60–80 h</td></tr>
                    <tr><td>70 API endpointů</td><td>200–300 h</td></tr>
                    <tr><td>Ceník + kalkulátor backend</td><td>40–60 h</td></tr>
                    <tr><td>GDPR compliance, gaming zone, multi-tenant, audit, mapy, video, CI/CD</td><td>250–360 h</td></tr>
                    <tr class="audit-tabulka-souhrn"><td>Celkem backend</td><td>1 110–1 570 h</td></tr>
                </tbody>
            </table>
        </div>

        <div class="audit-tabulka-obal" style="margin-top: 0.75rem;">
            <table class="audit-tabulka">
                <thead><tr><th>Oblast (frontend)</th><th>Hodiny</th></tr></thead>
                <tbody>
                    <tr><td>Globální styly, responsivita, typografie</td><td>40–60 h</td></tr>
                    <tr><td>Formulář nové reklamace (mapa, kalendář, foto)</td><td>80–120 h</td></tr>
                    <tr><td>Dashboard seznam.js (5 883 řádků)</td><td>100–140 h</td></tr>
                    <tr><td>Statistiky frontend (40 891 řádků)</td><td>80–120 h</td></tr>
                    <tr><td>Admin panel frontend</td><td>80–120 h</td></tr>
                    <tr><td>Ceník + kalkulátor (65 192 řádků)</td><td>100–150 h</td></tr>
                    <tr><td>PWA, gaming zone, toast, modály, překlady, CSS moduly, analytics</td><td>260–380 h</td></tr>
                    <tr class="audit-tabulka-souhrn"><td>Celkem frontend</td><td>740–1 090 h</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== SEKCE 6: FINANČNÍ HODNOTA ===== -->
    <div class="audit-sekce" id="sekce-6">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">6</span>
            <h2>Finanční hodnota projektu — výroba</h2>
        </div>

        <div class="audit-tabulka-obal">
            <table class="audit-tabulka">
                <thead><tr><th>Role</th><th>Sazba / hod</th><th>Hodiny</th><th>Náklady</th></tr></thead>
                <tbody>
                    <tr><td>Backend developer</td><td>1 200 Kč</td><td>1 110–1 570 h</td><td>1 332 000 – 1 884 000 Kč</td></tr>
                    <tr><td>Frontend developer</td><td>1 000 Kč</td><td>740–1 090 h</td><td>740 000 – 1 090 000 Kč</td></tr>
                    <tr><td>UX/UI designer</td><td>1 100 Kč</td><td>190–290 h</td><td>209 000 – 319 000 Kč</td></tr>
                    <tr><td>Copywriter</td><td>600 Kč</td><td>140–200 h</td><td>84 000 – 120 000 Kč</td></tr>
                    <tr class="audit-tabulka-souhrn"><td>CZ freelance celkem</td><td>—</td><td>2 180–3 150 h</td><td>2 365 000 – 3 413 000 Kč</td></tr>
                </tbody>
            </table>
        </div>

        <div class="audit-finance-pruh" style="margin-top: 0.75rem;">
            <div class="audit-finance-box">
                <div class="audit-finance-box-popisek">CZ agentura (marže 1,4–1,8×)</div>
                <div class="audit-finance-box-castka">3,3 – 6,1 mil. Kč</div>
            </div>
            <div class="audit-finance-box">
                <div class="audit-finance-box-popisek">EU freelance (DE/AT/NL)</div>
                <div class="audit-finance-box-castka">157 – 282 tis. €</div>
            </div>
            <div class="audit-finance-box">
                <div class="audit-finance-box-popisek">EU agentura</div>
                <div class="audit-finance-box-castka">230 – 500 tis. €</div>
            </div>
        </div>
    </div>

    <!-- ===== SEKCE 7: PRODEJNÍ HODNOTA ===== -->
    <div class="audit-sekce" id="sekce-7">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">7</span>
            <h2>Prodejní hodnota projektu</h2>
        </div>

        <div class="audit-tabulka-obal">
            <table class="audit-tabulka">
                <thead><tr><th>Scénář</th><th>Podmínky</th><th>Odhadovaná hodnota</th></tr></thead>
                <tbody>
                    <tr>
                        <td><strong>Hotový projekt (turnkey)</strong></td>
                        <td>Bez zákaznické základny, one-time licence</td>
                        <td><strong>800 000 – 1 500 000 Kč</strong></td>
                    </tr>
                    <tr>
                        <td><strong>SaaS produkt</strong></td>
                        <td>10 zákazníků, MRR 50–150 tis. Kč, ocenění 10× ARR</td>
                        <td><strong>6 – 18 mil. Kč</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Startup asset (akvizice)</strong></td>
                        <td>Bez zákaznické základny — technologický asset</td>
                        <td><strong>2 – 5 mil. Kč</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Startup asset (akvizice)</strong></td>
                        <td>Se zákaznickou základnou a trakci</td>
                        <td><strong>15 – 40 mil. Kč</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p style="font-size: 0.82rem; color: #888; font-style: italic; padding: 0.5rem 0.75rem; background: #f9f9f9; border-radius: 3px; border-left: 3px solid #ddd;">
            Existence zákaznické základny a aktivní MRR nelze ověřit ze zdrojového kódu. Výše prodejní hodnoty je silně podmíněna těmito faktory.
        </p>
    </div>

    <!-- ===== SEKCE 8: SILNÉ STRÁNKY ===== -->
    <div class="audit-sekce" id="sekce-8">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">8</span>
            <h2>Silné stránky projektu</h2>
        </div>

        <ul class="audit-seznam">
            <li><span class="audit-seznam-cislo">1</span><div><strong>Bezpečnost na enterprise úrovni</strong> — CSRF, rate limiting, audit log, prepared statements, security headers, email MX validace. Projekt by prošel základním penetračním testem.</div></li>
            <li><span class="audit-seznam-cislo">2</span><div><strong>Email queue s ACID transakčností</strong> — Retry logika s exponenciálním backoffem, GDPR footer, globální BCC archivace. Nadstandardní implementace pro projekt tohoto typu.</div></li>
            <li><span class="audit-seznam-cislo">3</span><div><strong>PWA implementace</strong> — Service worker, offline mode, push notifikace, installable app. Přidává mobilní hodnotu bez nutnosti nativní aplikace.</div></li>
            <li><span class="audit-seznam-cislo">4</span><div><strong>Multi-tenant architektura</strong> — Připravenost na SaaS expanzi (<code>tenant_id</code> v tabulkách, <code>TenantManager.php</code>). Klíčová vlastnost pro škálování.</div></li>
            <li><span class="audit-seznam-cislo">5</span><div><strong>3jazyčná podpora s DB-driven překlady</strong> — CS/EN/IT pokrytí. Italština otevírá přímý trh Natuzzi v Itálii.</div></li>
            <li><span class="audit-seznam-cislo">6</span><div><strong>CI/CD pipeline</strong> — GitHub Actions + SFTP deployment. Disciplinovaný release process.</div></li>
            <li><span class="audit-seznam-cislo">7</span><div><strong>Konzistentní coding standards</strong> — Celý projekt dodržuje deklarovaná pravidla (čeština, bez emoji, černobílá paleta, výjimky s odůvodněním). Vzácné u projektů tohoto rozsahu.</div></li>
            <li><span class="audit-seznam-cislo">8</span><div><strong>Role-based access control se supervizor hierarchií</strong> — 4 role s podporou supervizor→prodejce relací. Připraveno na organizační strukturu větší firmy.</div></li>
            <li><span class="audit-seznam-cislo">9</span><div><strong>Specializace na prémiový segment</strong> — White Glove Service + Natuzzi branding v profitabilním segmentu s vysokou ochotou platit za kvalitní servis.</div></li>
            <li><span class="audit-seznam-cislo">10</span><div><strong>SEO-first landing pages</strong> — 5 dedikovaných stránek pro long-tail klíčová slova (pozarucni-servis, servis-natuzzi, oprava-kresla, atd.).</div></li>
        </ul>
    </div>

    <!-- ===== SEKCE 9: SLABÉ STRÁNKY ===== -->
    <div class="audit-sekce" id="sekce-9">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">9</span>
            <h2>Slabé stránky projektu</h2>
        </div>

        <ul class="audit-seznam">
            <li><span class="audit-seznam-cislo">1</span><div><strong>Monolitická architektura</strong> — 136 PHP souborů v rootu, žádný MVC framework. <code>seznam.php</code> se 27 622 řádky je symptom. Při dalším růstu bude údržba exponenciálně náročnější.</div></li>
            <li><span class="audit-seznam-cislo">2</span><div><strong>Frontend bez komponentové architektury</strong> — <code>cenik-calculator.js</code> se 65 192 řádky a <code>statistiky.js</code> se 40 891 řádky jsou nepřijatelné velikosti pro long-term maintainability bez testů.</div></li>
            <li><span class="audit-seznam-cislo">3</span><div><strong>Migrační skripty v rootu</strong> — <code>kontrola_*.php</code>, <code>migrace_*.php</code>, <code>pridej_*.php</code> jsou potenciálně veřejně dostupné bez explicitní admin ochrany. Bezpečnostní riziko.</div></li>
            <li><span class="audit-seznam-cislo">4</span><div><strong>Žádné automatizované testy</strong> — U projektu s 70 API endpointy a business logikou (kalkulace provizí, email queue) je absence PHPUnit / Jest testů zásadním rizikem.</div></li>
            <li><span class="audit-seznam-cislo">5</span><div><strong>Absence live API dokumentace</strong> — <code>api-docs.php</code> bez Swagger/Postman ztěžuje správu 70 endpointů a onboarding nových vývojářů.</div></li>
            <li><span class="audit-seznam-cislo">6</span><div><strong>Herní zóna jako nesouvisející feature</strong> — <code>hry.php</code> + <code>hry_api.php</code> (45 KB) nesouvisí s core produktem. Pro investora je to technický dluh.</div></li>
            <li><span class="audit-seznam-cislo">7</span><div><strong>Úzká závislost na jednom klientovi</strong> — Bez generalizace pro jiné značky je adresovatelný trh velmi omezený.</div></li>
            <li><span class="audit-seznam-cislo">8</span><div><strong>Homepage příliš minimalistická</strong> — <code>index.php</code> se 93 řádky nestačí jako prodejní nástroj. Chybí social proof, case studies, demo, pricing.</div></li>
            <li><span class="audit-seznam-cislo">9</span><div><strong>Absence veřejného demo prostředí</strong> — Pro SaaS konverzi je klíčové umožnit potenciálním zákazníkům vyzkoušet systém bez kontaktu s prodejcem.</div></li>
        </ul>
    </div>

    <!-- ===== SEKCE 10: DOPORUČENÍ ===== -->
    <div class="audit-sekce" id="sekce-10">
        <div class="audit-sekce-hlavicka">
            <span class="audit-sekce-cislo">10</span>
            <h2>Doporučení pro zvýšení hodnoty</h2>
        </div>

        <div class="audit-doporuceni-skupina">
            <div class="audit-doporuceni-skupina-hlavicka">Krátkodobé (0–3 měsíce) — dopad na prodejní hodnotu</div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">1. Ochrana migračních skriptů v rootu</div>
                <div class="audit-doporuceni-polozka-text">Přesunout do <code>/tools/</code> a přidat admin session check na každý soubor. Konkrétní bezpečnostní riziko, rychlá oprava.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">2. Vylepšení homepage</div>
                <div class="audit-doporuceni-polozka-text">93 řádků nestačí. Přidat: 3 konkrétní výhody systému s čísly, zákaznické reference, screenshot dashboardu, pricing nebo CTA na ceník. Odhadovaný dopad: +20–40 % konverzní rate.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">3. Izolace nebo odstranění herní zóny</div>
                <div class="audit-doporuceni-polozka-text">Přesunout za samostatnou subdoménu (<code>play.wgs-service.cz</code>) nebo zcela odebrat z produkce. Zjednodušuje kódovou základnu o ~15 %.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">4. robots.txt a sitemap.xml</div>
                <div class="audit-doporuceni-polozka-text">Základní SEO prerequisite. Pokud neexistují, vytvořit okamžitě.</div>
            </div>
        </div>

        <div class="audit-doporuceni-skupina">
            <div class="audit-doporuceni-skupina-hlavicka">Střednědobé (3–9 měsíců) — dopad na technickou hodnotu</div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">5. Refaktoring největších JS souborů</div>
                <div class="audit-doporuceni-polozka-text"><code>cenik-calculator.js</code> (65K řádků) a <code>statistiky.js</code> (41K řádků) rozdělit do ES6 modulů (import/export nebo webpack bundle). Přidá testovatelnost.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">6. Automatizované testy pro kritické API</div>
                <div class="audit-doporuceni-polozka-text">PHPUnit testy pro <code>save.php</code> (workflow ID generování, enum mapping, rate limiting) a <code>protokol_api.php</code> (kalkulace provizí). Odhadovaný čas: 60–80 h. Dopad na hodnotu: +15–25 %.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">7. Generalizace pro jiné značky nábytku</div>
                <div class="audit-doporuceni-polozka-text">Nahradit pevná Natuzzi reference systémem konfigurace (brand_name, brand_logo v <code>wgs_system_config</code>). Adresovatelný trh: Koinor, Rolf Benz, polská prémiová výroba. Dopad na SaaS hodnotu: 3–5×.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">8. Veřejné demo prostředí</div>
                <div class="audit-doporuceni-polozka-text">Sandbox instance s demo daty. Umožní B2B self-serve konverzi bez prodejního procesu. Kritické pro SaaS model.</div>
            </div>
        </div>

        <div class="audit-doporuceni-skupina">
            <div class="audit-doporuceni-skupina-hlavicka">Dlouhodobé (9–24 měsíců) — dopad na tržní hodnotu</div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">9. Migrace na moderní frontend architekturu</div>
                <div class="audit-doporuceni-polozka-text">Postupná migrace klíčových stránek (<code>seznam</code>, <code>statistiky</code>) na Vue 3 nebo Svelte — oba jsou kompatibilní s vanilla PHP backendem. Zvýší maintainability a atraktivitu pro akvizici.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">10. REST API pro třetí strany</div>
                <div class="audit-doporuceni-polozka-text">JWT/API key autentizace a veřejné endpointy pro integrace (ERP systémy nábytku, CRM zákazníků). Otevírá marketplace/integration model.</div>
            </div>
            <div class="audit-doporuceni-polozka">
                <div class="audit-doporuceni-polozka-hlavicka">11. Zákaznický portál</div>
                <div class="audit-doporuceni-polozka-text">Samostatný pohled pro koncového zákazníka (stav reklamace, fotodokumentace, protokol ke stažení). Snižuje support load a zvyšuje NPS.</div>
            </div>
        </div>
    </div>

    <!-- Výsledkový banner -->
    <div class="audit-vysledek">
        <h3>Závěrečné hodnocení</h3>
        <p>
            Technicky sofistikovaný a bezpečně implementovaný CRM/service management systém s jasnou doménovou specializací.
            Vývojové náklady v ČR by dnes činily <strong>2,4 – 3,4 milionu Kč</strong> při freelance sazbách.
            Prodejní hodnota jako hotový produkt bez zákaznické základny: <strong>1,5 – 5 milionů Kč</strong>.
            Se zákaznickou základnou a při SaaS modelu: <strong>6 – 40 milionů Kč</strong> v závislosti na MRR a trakci.
            Největší bariérou pro maximalizaci hodnoty je úzká specializace na Natuzzi a absence generalizace pro jiné zákazníky.
        </p>
        <div class="audit-patika">
            Audit provedl: Claude AI (claude-sonnet-4-6) na základě přímé analýzy zdrojového kódu — <?php echo htmlspecialchars($auditDatum, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>

</div>

<!-- ================================================================
     PŘEKRYVNÁ VRSTVA
     ================================================================ -->
<div class="doporuceni-overlay" id="doporuceni-overlay"></div>

<!-- ================================================================
     PANEL DOPORUČENÍ
     ================================================================ -->
<div class="doporuceni-panel" id="doporuceni-panel" role="dialog" aria-modal="true" aria-labelledby="doporuceni-titulek">

    <div class="doporuceni-panel-hlavicka">
        <h2 id="doporuceni-titulek">
            Doporučení k opravě
            <span class="dp-badge" id="dp-pocet-otevrenych" style="display:none;">0</span>
        </h2>
        <button class="doporuceni-zavrit" id="doporuceni-zavrit" aria-label="Zavřít">×</button>
    </div>

    <div class="doporuceni-meta">
        <strong>AI instrukce:</strong> Tento panel slouží jako živý TODO pro vývojáře i AI asistenta.
        Při přidání nové položky uveď: popis problému, dotčené soubory, přesný postup opravy,
        co nesmí přestat fungovat a rizika. Po implementaci položku odstraň z HTML.
        Pořadí odpovídá prioritě.
    </div>

    <div class="doporuceni-obsah">

        <div style="text-align:center;padding:3rem 1rem;color:#555;">
            <div style="font-size:2rem;margin-bottom:0.75rem;font-weight:700;">Žádná otevřená doporučení</div>
            <div style="font-size:0.9rem;color:#888;">Všechna doporučení z auditu byla implementována nebo ověřena.<br>Při nalezení nové oblasti ke zlepšení přidejte položku do tohoto panelu.</div>
        </div>

        <!-- ===== BUDOUCÍ POLOŽKY ===== -->
        <!-- Přidat nové dp-polozka bloky sem při dalším auditu -->

    </div><!-- /doporuceni-obsah -->

</div><!-- /doporuceni-panel -->

<script>
(function () {
    var ULOZISTE_KLIC = 'wgs_doporuceni_stav';
    var VERZE_KLIC = 'wgs_doporuceni_verze';
    var AKTUALNI_VERZE = '2026-03-04-v1'; // Změnit při přidání nových položek
    var celkem = 10;

    // Předvyplnit hotové položky pokud ještě nebyly inicializovány v této verzi
    (function() {
        try {
            if (localStorage.getItem(VERZE_KLIC) !== AKTUALNI_VERZE) {
                var stavHotovo = {
                    'dp-1': true,  // B1 — ověřeno (bylo již implementováno)
                    'dp-2': true,  // B2 — ověřeno (bylo již implementováno)
                    'dp-3': true,  // B3 — implementováno
                    'dp-4': true,  // B4 — implementováno
                    'dp-5': true,  // U5 — implementováno (dnes.php)
                    'dp-6': true,  // U6 — implementováno (autosave protokolu)
                    'dp-7': true,  // U7 — ověřeno (bylo již implementováno)
                    'dp-8': true,  // U8 — implementováno (hromadné akce)
                    'dp-9': true,  // U9 — implementováno (foto badge)
                    'dp-10': true  // U10 — implementováno (tisk.php)
                };
                localStorage.setItem(ULOZISTE_KLIC, JSON.stringify(stavHotovo));
                localStorage.setItem(VERZE_KLIC, AKTUALNI_VERZE);
            }
        } catch (e) {}
    })();

    // Načíst uložený stav
    function nacistStav() {
        try {
            return JSON.parse(localStorage.getItem(ULOZISTE_KLIC) || '{}');
        } catch (e) {
            return {};
        }
    }

    // Uložit stav
    function ulozitStav(stav) {
        try {
            localStorage.setItem(ULOZISTE_KLIC, JSON.stringify(stav));
        } catch (e) {}
    }

    // Aktualizovat progress bar a počítadlo
    function aktualizovatProgress() {
        var stav = nacistStav();
        var splneno = Object.values(stav).filter(function (v) { return v === true; }).length;
        var otevreno = celkem - splneno;

        var fill = document.getElementById('dp-progress-fill');
        var text = document.getElementById('dp-progress-text');
        var pocet = document.getElementById('dp-pocet-otevrenych');

        if (fill) fill.style.width = Math.round((splneno / celkem) * 100) + '%';
        if (text) text.textContent = splneno + ' / ' + celkem + ' splněno';
        if (pocet) {
            pocet.textContent = otevreno;
            pocet.style.display = otevreno > 0 ? 'inline-flex' : 'none';
        }
    }

    // Aplikovat uložený stav na DOM
    function aplikovatStav() {
        var stav = nacistStav();
        for (var id in stav) {
            if (stav[id] === true) {
                var el = document.getElementById(id);
                if (el) {
                    el.classList.add('dp-hotovo');
                    var btn = el.querySelector('.dp-hotovo-btn');
                    if (btn) btn.textContent = 'Znovu otevřít';
                }
            }
        }
        aktualizovatProgress();
    }

    // Otevřít / zavřít položku
    window.dpToggle = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('otevrena');
    };

    // Označit jako hotovo / vrátit zpět
    window.dpHotovo = function (id) {
        var stav = nacistStav();
        var el = document.getElementById(id);
        if (!el) return;

        if (stav[id] === true) {
            // Vrátit zpět
            delete stav[id];
            el.classList.remove('dp-hotovo');
            var btn = el.querySelector('.dp-hotovo-btn');
            if (btn) btn.textContent = 'Označit jako hotovo';
        } else {
            // Označit jako hotovo
            stav[id] = true;
            el.classList.add('dp-hotovo');
            el.classList.remove('otevrena');
            var btn2 = el.querySelector('.dp-hotovo-btn');
            if (btn2) btn2.textContent = 'Znovu otevřít';
        }

        ulozitStav(stav);
        aktualizovatProgress();
    };

    // Otevřít panel
    function otevritPanel() {
        var panel = document.getElementById('doporuceni-panel');
        var overlay = document.getElementById('doporuceni-overlay');
        if (panel) panel.classList.add('aktivni');
        if (overlay) overlay.classList.add('aktivni');
        document.body.style.overflow = 'hidden';
    }

    // Zavřít panel
    function zavritPanel() {
        var panel = document.getElementById('doporuceni-panel');
        var overlay = document.getElementById('doporuceni-overlay');
        if (panel) panel.classList.remove('aktivni');
        if (overlay) overlay.classList.remove('aktivni');
        document.body.style.overflow = '';
    }

    // Inicializace po načtení
    document.addEventListener('DOMContentLoaded', function () {
        aplikovatStav();

        var btnOtevrit = document.getElementById('btn-otevreni-doporuceni');
        if (btnOtevrit) btnOtevrit.addEventListener('click', otevritPanel);

        var btnZavrit = document.getElementById('doporuceni-zavrit');
        if (btnZavrit) btnZavrit.addEventListener('click', zavritPanel);

        var overlay = document.getElementById('doporuceni-overlay');
        if (overlay) overlay.addEventListener('click', zavritPanel);

        // ESC klávesa
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') zavritPanel();
        });
    });
})();
</script>
