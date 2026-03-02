<?php
/**
 * Admin karta: ROOT přehled souborů
 *
 * Zobrazuje kompletní přehled všech souborů v ROOT adresáři:
 * - Využití (kolikrát je soubor referencován)
 * - Závislosti (které soubory daný soubor includuje)
 * - Návaznosti (grafické propojení souborů)
 * - Možnost označení ke smazání
 *
 * Načítá data z: /api/soubory_api.php
 */

if (!defined('ADMIN_PHP_LOADED')) {
    die('Přímý přístup zakázán.');
}
?>
<style>
/* ========================================================
   ROOT Přehled souborů - styly
   ======================================================== */
.soub-obal {
    font-family: 'Courier New', Courier, monospace;
    max-width: 100%;
}

/* Záhlaví */
.soub-zahlavi {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.soub-zahlavi h2 {
    margin: 0 0 0.25rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #000;
}

.soub-zahlavi p {
    margin: 0;
    font-size: 0.8rem;
    color: #666;
}

.soub-zahlavi-akce {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

/* Stat karty */
.soub-statistiky {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.soub-stat {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 0.9rem 1rem;
    text-align: center;
    transition: border-color 0.2s;
}

.soub-stat:hover {
    border-color: #999;
}

.soub-stat-cislo {
    display: block;
    font-size: 1.6rem;
    font-weight: 700;
    color: #000;
    line-height: 1;
    margin-bottom: 0.3rem;
    font-family: 'Courier New', monospace;
}

.soub-stat-popis {
    font-size: 0.7rem;
    color: #666;
    font-family: sans-serif;
}

/* Filtry */
.soub-filtry {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: #f9f9f9;
    border: 1px solid #e8e8e8;
    border-radius: 6px;
}

.soub-filtr-btn {
    padding: 0.3rem 0.65rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: #fff;
    color: #333;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.15s;
    font-family: 'Courier New', monospace;
}

.soub-filtr-btn:hover {
    background: #f0f0f0;
}

.soub-filtr-btn.aktivni {
    background: #222;
    color: #fff;
    border-color: #222;
}

.soub-hledani {
    flex: 1;
    min-width: 180px;
    padding: 0.3rem 0.65rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 0.75rem;
    font-family: 'Courier New', monospace;
}

.soub-hledani:focus {
    outline: none;
    border-color: #666;
}

.soub-btn {
    padding: 0.3rem 0.65rem;
    border: 1px solid #444;
    border-radius: 4px;
    background: #333;
    color: #fff;
    font-size: 0.75rem;
    cursor: pointer;
    transition: background 0.15s;
    font-family: 'Courier New', monospace;
}

.soub-btn:hover {
    background: #000;
}

.soub-btn:disabled {
    background: #999;
    border-color: #999;
    cursor: not-allowed;
}

/* Legenda */
.soub-legenda {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: #666;
    margin-bottom: 1rem;
    padding: 0.6rem 0.75rem;
    background: #fafafa;
    border: 1px solid #eee;
    border-radius: 4px;
    font-family: sans-serif;
}

.soub-legenda-polozka {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.soub-legenda-ctverec {
    width: 20px;
    height: 10px;
    border-radius: 2px;
    border: 1px solid;
    display: inline-block;
    flex-shrink: 0;
}

/* Strom souborů */
.soub-strom {
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
}

/* Skupina adresáře */
.soub-adresar-skupina {
    border-bottom: 1px solid #ececec;
}

.soub-adresar-skupina:last-child {
    border-bottom: none;
}

/* Záhlaví adresáře */
.soub-adresar-hlavicka {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 1rem;
    background: #f4f4f4;
    border-bottom: 1px solid #e8e8e8;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
}

.soub-adresar-hlavicka:hover {
    background: #ebebeb;
}

.soub-adresar-nazev {
    font-size: 0.78rem;
    font-weight: 600;
    color: #333;
    font-family: 'Courier New', monospace;
    flex: 1;
}

.soub-adresar-toggler {
    font-size: 0.7rem;
    color: #888;
    width: 14px;
    text-align: center;
    transition: transform 0.2s;
    flex-shrink: 0;
}

.soub-adresar-toggler.zavreno {
    transform: rotate(-90deg);
}

.soub-adresar-pocet {
    font-size: 0.7rem;
    color: #aaa;
    font-family: sans-serif;
}

.soub-adresar-obsah {
    /* Výchozí: viditelný */
}

.soub-adresar-obsah.skryto {
    display: none;
}

/* Řádek souboru */
.soub-radek {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem 0.35rem 0;
    border-bottom: 1px solid #f5f5f5;
    font-size: 0.78rem;
    position: relative;
    transition: background 0.1s;
}

.soub-radek:last-child {
    border-bottom: none;
}

.soub-radek:hover {
    background: #fafafa;
}

.soub-radek.oznaceny {
    background: #f8f8f8;
    opacity: 0.72;
}

/* Grafický strom symbol */
.soub-strom-symbol {
    color: #ccc;
    font-family: 'Courier New', monospace;
    flex-shrink: 0;
    font-size: 0.8rem;
    width: 2rem;
    text-align: right;
    padding-right: 0.2rem;
    user-select: none;
}

/* Jméno souboru */
.soub-nazev-wrapper {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.soub-nazev {
    font-weight: 500;
    color: #111;
    font-family: 'Courier New', monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    min-width: 0;
}

.soub-nazev:hover {
    text-decoration: underline;
    color: #000;
}

/* Typ badge */
.soub-typ {
    font-size: 0.58rem;
    padding: 0.1rem 0.35rem;
    border-radius: 2px;
    font-weight: 700;
    text-transform: uppercase;
    flex-shrink: 0;
    font-family: 'Courier New', monospace;
    border: 1px solid;
    letter-spacing: 0.03em;
}

.soub-typ-php      { background: #ebebeb; color: #222; border-color: #bbb; }
.soub-typ-js       { background: #f0f0f0; color: #333; border-color: #c5c5c5; }
.soub-typ-css      { background: #e8e8e8; color: #333; border-color: #c0c0c0; }
.soub-typ-html     { background: #f2f2f2; color: #444; border-color: #ccc; }
.soub-typ-data     { background: #f5f5f5; color: #555; border-color: #ddd; }
.soub-typ-sql      { background: #efefef; color: #444; border-color: #ccc; }
.soub-typ-text     { background: #f7f7f7; color: #666; border-color: #ddd; }
.soub-typ-obr      { background: #f5f5f5; color: #777; border-color: #e0e0e0; }
.soub-typ-font     { background: #f5f5f5; color: #777; border-color: #e0e0e0; }
.soub-typ-htaccess { background: #e0e0e0; color: #222; border-color: #aaa; }
.soub-typ-shell    { background: #e5e5e5; color: #333; border-color: #bbb; }
.soub-typ-konfig   { background: #f0f0f0; color: #555; border-color: #ddd; }
.soub-typ-ostatni  { background: #f8f8f8; color: #999; border-color: #eee; }

/* Využití badge */
.soub-vyuziti-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    padding: 0.12rem 0.4rem;
    border-radius: 8px;
    font-weight: 600;
    flex-shrink: 0;
    cursor: pointer;
    border: 1px solid #ddd;
    background: #f5f5f5;
    color: #333;
    transition: all 0.1s;
    font-family: 'Courier New', monospace;
    white-space: nowrap;
}

.soub-vyuziti-badge:hover {
    border-color: #888;
    background: #eee;
}

.soub-vyuziti-badge.nulove {
    color: #bbb;
    border-color: #eee;
    background: #fafafa;
}

.soub-vyuziti-badge.vysoke {
    background: #e0e0e0;
    border-color: #999;
    font-weight: 700;
}

/* Stav badge */
.soub-stav {
    font-size: 0.62rem;
    padding: 0.12rem 0.4rem;
    border-radius: 8px;
    font-weight: 600;
    flex-shrink: 0;
    border: 1px solid;
    font-family: sans-serif;
    white-space: nowrap;
}

.soub-stav-aktivni    { background: #f5f5f5; color: #444; border-color: #ddd; }
.soub-stav-bezVyuziti { background: #ececec; color: #666; border-color: #ccc; font-style: italic; }
.soub-stav-oznacen    { background: #333; color: #fff; border-color: #222; }

/* Velikost souboru */
.soub-velikost {
    font-size: 0.63rem;
    color: #bbb;
    font-family: 'Courier New', monospace;
    flex-shrink: 0;
    white-space: nowrap;
}

/* Akční tlačítka */
.soub-akce {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
    align-items: center;
}

.soub-btn-oznacit {
    font-size: 0.63rem;
    padding: 0.15rem 0.45rem;
    border: 1px solid #ccc;
    border-radius: 3px;
    background: #fff;
    color: #333;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.1s;
    font-family: 'Courier New', monospace;
}

.soub-btn-oznacit:hover {
    background: #f0f0f0;
}

.soub-btn-oznacit.aktivni {
    background: #333;
    color: #fff;
    border-color: #222;
}

.soub-btn-detail {
    font-size: 0.63rem;
    padding: 0.15rem 0.45rem;
    border: 1px solid #e0e0e0;
    border-radius: 3px;
    background: #fafafa;
    color: #777;
    cursor: pointer;
    transition: all 0.1s;
    font-family: 'Courier New', monospace;
}

.soub-btn-detail:hover {
    background: #f0f0f0;
    border-color: #bbb;
    color: #333;
}

/* Detail panel */
.soub-detail {
    display: none;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    background: #f9f9f9;
    border-bottom: 1px solid #ececec;
    font-size: 0.73rem;
}

.soub-detail.viditelny {
    display: block;
}

.soub-detail-info {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: #666;
    margin-bottom: 0.75rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #eee;
}

.soub-detail-info strong {
    color: #444;
}

/* Závislosti */
.soub-zavislosti-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 700px) {
    .soub-zavislosti-grid {
        grid-template-columns: 1fr;
    }
}

.soub-zavislosti-sekce-nadpis {
    font-size: 0.67rem;
    font-weight: 700;
    color: #555;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.3rem;
    font-family: sans-serif;
}

.soub-zavislosti-strom {
    padding-left: 0.9rem;
    border-left: 2px solid #ddd;
    margin-left: 0.4rem;
}

.soub-zavislost-radek {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    padding: 0.1rem 0;
    position: relative;
}

.soub-zavislost-symbol {
    color: #bbb;
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    flex-shrink: 0;
    margin-left: -0.9rem;
}

.soub-zavislost-cesta {
    font-family: 'Courier New', monospace;
    color: #333;
    font-size: 0.7rem;
    word-break: break-all;
}

.soub-prazdne {
    color: #999;
    font-style: italic;
    font-size: 0.7rem;
    padding-left: 0.3rem;
    font-family: sans-serif;
}

/* Načítání */
.soub-nacitani {
    text-align: center;
    padding: 3.5rem 2rem;
    color: #777;
    font-family: sans-serif;
}

.soub-spinner {
    display: inline-block;
    width: 28px;
    height: 28px;
    border: 3px solid #eee;
    border-top-color: #555;
    border-radius: 50%;
    animation: soub-spin 0.75s linear infinite;
    margin-bottom: 1rem;
}

@keyframes soub-spin {
    to { transform: rotate(360deg); }
}

/* Chyba */
.soub-chyba {
    text-align: center;
    padding: 2.5rem;
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 6px;
    color: #666;
    font-family: sans-serif;
}

/* Prázdný výsledek */
.soub-prazdny-vysledek {
    text-align: center;
    padding: 2rem;
    color: #aaa;
    font-size: 0.85rem;
    font-family: sans-serif;
}

/* Počet info */
.soub-pocet-info {
    padding: 0.5rem 1rem;
    font-size: 0.7rem;
    color: #aaa;
    border-top: 1px solid #eee;
    background: #fafafa;
    font-family: sans-serif;
}

/* Info bar */
.soub-info-bar {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.7rem;
    color: #999;
    margin-bottom: 0.75rem;
    font-family: sans-serif;
}

/* Responsivní */
@media (max-width: 768px) {
    .soub-velikost,
    .soub-detail-info {
        display: none;
    }

    .soub-statistiky {
        grid-template-columns: repeat(3, 1fr);
    }

    .soub-zahlavi {
        flex-direction: column;
    }
}
</style>

<div class="soub-obal">

    <!-- Záhlaví -->
    <div class="soub-zahlavi">
        <div>
            <h2>ROOT - Přehled souborů a závislostí</h2>
            <p>Kompletní mapa všech souborů: využití, návaznosti, závislosti a správa ke smazání</p>
        </div>
        <div class="soub-zahlavi-akce">
            <span id="soub-cache-info" style="font-size: 0.7rem; color: #bbb;"></span>
            <button class="soub-btn" id="soub-btn-skenovat" onclick="soubNacist(true)">Znovu skenovat</button>
        </div>
    </div>

    <!-- Stat karty -->
    <div id="soub-statistiky" class="soub-statistiky" style="display: none;">
        <div class="soub-stat">
            <span class="soub-stat-cislo" id="soub-stat-celkem">-</span>
            <span class="soub-stat-popis">Celkem souborů</span>
        </div>
        <div class="soub-stat">
            <span class="soub-stat-cislo" id="soub-stat-php">-</span>
            <span class="soub-stat-popis">PHP soubory</span>
        </div>
        <div class="soub-stat">
            <span class="soub-stat-cislo" id="soub-stat-js">-</span>
            <span class="soub-stat-popis">JS soubory</span>
        </div>
        <div class="soub-stat">
            <span class="soub-stat-cislo" id="soub-stat-css">-</span>
            <span class="soub-stat-popis">CSS soubory</span>
        </div>
        <div class="soub-stat">
            <span class="soub-stat-cislo" id="soub-stat-bezVyuziti">-</span>
            <span class="soub-stat-popis">Bez využití</span>
        </div>
        <div class="soub-stat">
            <span class="soub-stat-cislo" id="soub-stat-oznaceno">-</span>
            <span class="soub-stat-popis">Ke smazání</span>
        </div>
    </div>

    <!-- Filtry -->
    <div class="soub-filtry">
        <button class="soub-filtr-btn aktivni" onclick="soubFiltr('vse', this)">Vše</button>
        <button class="soub-filtr-btn" onclick="soubFiltr('php', this)">PHP</button>
        <button class="soub-filtr-btn" onclick="soubFiltr('js', this)">JS</button>
        <button class="soub-filtr-btn" onclick="soubFiltr('css', this)">CSS</button>
        <button class="soub-filtr-btn" onclick="soubFiltr('ostatni', this)">Ostatní</button>
        <button class="soub-filtr-btn" onclick="soubFiltr('bez-vyuziti', this)">Bez využití</button>
        <button class="soub-filtr-btn" onclick="soubFiltr('oznaceno', this)">Ke smazání</button>
        <input type="text" class="soub-hledani" id="soub-hledani"
               placeholder="Hledat soubor nebo cestu..."
               oninput="soubFiltrovat()">
    </div>

    <!-- Legenda -->
    <div class="soub-legenda">
        <span class="soub-legenda-polozka">
            <span class="soub-legenda-ctverec" style="background:#f5f5f5;border-color:#ddd;"></span>
            Aktivní soubor
        </span>
        <span class="soub-legenda-polozka">
            <span class="soub-legenda-ctverec" style="background:#ececec;border-color:#ccc;"></span>
            Bez využití (lze smazat)
        </span>
        <span class="soub-legenda-polozka">
            <span class="soub-legenda-ctverec" style="background:#333;border-color:#222;"></span>
            Označeno ke smazání
        </span>
        <span class="soub-legenda-polozka">
            <span style="font-family:'Courier New';color:#bbb;font-size:0.9rem;">├ └</span>
            Grafické spojení závislostí
        </span>
        <span class="soub-legenda-polozka">
            <span style="font-size:0.7rem;font-family:sans-serif;">Klik na název nebo badge = zobrazit závislosti</span>
        </span>
    </div>

    <!-- Info bar -->
    <div class="soub-info-bar" id="soub-info-bar" style="display:none;">
        <span id="soub-info-text"></span>
    </div>

    <!-- Načítání -->
    <div id="soub-nacitani" class="soub-nacitani">
        <div class="soub-spinner"></div>
        <div style="font-weight: 600; margin-bottom: 0.3rem;">Skenování souborů...</div>
        <div style="font-size: 0.75rem;">První sken může trvat 3-10 sekund. Výsledky se ukládají do cache.</div>
    </div>

    <!-- Chyba -->
    <div id="soub-chyba" class="soub-chyba" style="display: none;">
        <div style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #ccc;">X</div>
        <strong>Chyba při načítání souborů</strong>
        <div id="soub-chyba-text" style="margin-top: 0.5rem; font-size: 0.8rem; color: #888;"></div>
        <button class="soub-btn" style="margin-top: 1rem;" onclick="soubNacist(false)">Zkusit znovu</button>
    </div>

    <!-- Strom souborů -->
    <div id="soub-strom" class="soub-strom" style="display: none;"></div>

    <!-- Počet info -->
    <div id="soub-pocet-info" class="soub-pocet-info" style="display: none;"></div>

</div>

<script>
(function () {
    'use strict';

    var vsechnySoubory = [];
    var aktivniFiltr   = 'vse';
    var hledanyText    = '';

    /* =====================================================
       Pomocné funkce
       ===================================================== */

    function escHtml(text) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(text || '')));
        return d.innerHTML;
    }

    function ziskejCsrf() {
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    function formatDatum(ts) {
        var d = new Date(ts * 1000);
        return d.toLocaleDateString('cs-CZ', { day: '2-digit', month: '2-digit', year: '2-digit' });
    }

    /* =====================================================
       Načtení dat z API
       ===================================================== */

    window.soubNacist = async function (nocache) {
        var btn = document.getElementById('soub-btn-skenovat');
        if (btn) { btn.disabled = true; btn.textContent = 'Skenuji...'; }

        document.getElementById('soub-nacitani').style.display = 'block';
        document.getElementById('soub-strom').style.display    = 'none';
        document.getElementById('soub-chyba').style.display    = 'none';
        document.getElementById('soub-statistiky').style.display = 'none';
        document.getElementById('soub-pocet-info').style.display = 'none';
        document.getElementById('soub-info-bar').style.display  = 'none';

        try {
            var url = '/api/soubory_api.php?akce=seznam' + (nocache ? '&nocache=1' : '');
            var odp = await fetch(url);

            if (!odp.ok) {
                throw new Error('HTTP ' + odp.status + ' - ' + odp.statusText);
            }

            var data = await odp.json();

            if (data.status !== 'success') {
                throw new Error(data.zprava || 'Neznámá chyba ze serveru');
            }

            vsechnySoubory = data.soubory || [];

            // Statistiky
            var stat = data.statistiky || {};
            document.getElementById('soub-stat-celkem').textContent    = stat.celkem || vsechnySoubory.length;
            document.getElementById('soub-stat-php').textContent       = stat.php || 0;
            document.getElementById('soub-stat-js').textContent        = stat.js || 0;
            document.getElementById('soub-stat-css').textContent       = stat.css || 0;
            document.getElementById('soub-stat-bezVyuziti').textContent = stat.bezVyuziti || 0;
            document.getElementById('soub-stat-oznaceno').textContent  = stat.oznaceno || 0;
            document.getElementById('soub-statistiky').style.display   = 'grid';

            // Info bar
            var cacheInfo = document.getElementById('soub-cache-info');
            if (data.zCache) {
                cacheInfo.textContent = 'Z cache (' + (data.cacheCas || '') + ')';
            } else {
                cacheInfo.textContent = 'Skenováno za ' + (stat.dobaSken || '?');
            }

            document.getElementById('soub-nacitani').style.display = 'none';
            soubFiltrovat();

        } catch (err) {
            document.getElementById('soub-nacitani').style.display = 'none';
            document.getElementById('soub-chyba').style.display    = 'block';
            document.getElementById('soub-chyba-text').textContent = err.message;
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Znovu skenovat'; }
        }
    };

    /* =====================================================
       Filtrování
       ===================================================== */

    window.soubFiltr = function (filtr, tlacitko) {
        aktivniFiltr = filtr;
        document.querySelectorAll('.soub-filtr-btn').forEach(function (b) {
            b.classList.remove('aktivni');
        });
        if (tlacitko) {
            tlacitko.classList.add('aktivni');
        }
        soubFiltrovat();
    };

    window.soubFiltrovat = function () {
        hledanyText = (document.getElementById('soub-hledani').value || '').toLowerCase();

        var filtrovaneSoubory = vsechnySoubory.filter(function (s) {
            // Textové vyhledávání
            if (hledanyText && s.cesta.toLowerCase().indexOf(hledanyText) === -1) {
                return false;
            }

            // Typ filtru
            switch (aktivniFiltr) {
                case 'php': return s.typ === 'php';
                case 'js':  return s.typ === 'js';
                case 'css': return s.typ === 'css';
                case 'ostatni': return ['php', 'js', 'css'].indexOf(s.typ) === -1;
                case 'bez-vyuziti': return s.pocetVyuzivani === 0 && ['php', 'js', 'css'].indexOf(s.typ) !== -1;
                case 'oznaceno': return s.oznaceno;
                default: return true;
            }
        });

        soubZobrazitStrom(filtrovaneSoubory);
    };

    /* =====================================================
       Sestavení stromu adresářů
       ===================================================== */

    function soubSestavitStrom(soubory) {
        var strom = {};
        soubory.forEach(function (s) {
            var adr = s.adresar || '';
            if (!strom[adr]) { strom[adr] = []; }
            strom[adr].push(s);
        });

        var klice = Object.keys(strom).sort(function (a, b) {
            if (a === '') { return -1; }
            if (b === '') { return 1; }
            return a.localeCompare(b, 'cs');
        });

        return { strom: strom, klice: klice };
    }

    window.soubZobrazitStrom = function (soubory) {
        var kontejner = document.getElementById('soub-strom');
        var infoEl    = document.getElementById('soub-pocet-info');
        var infoBar   = document.getElementById('soub-info-bar');

        if (soubory.length === 0) {
            kontejner.innerHTML = '<div class="soub-prazdny-vysledek">Žádné soubory neodpovídají filtru.</div>';
            kontejner.style.display = 'block';
            infoEl.style.display    = 'none';
            infoBar.style.display   = 'none';
            return;
        }

        var vysledek = soubSestavitStrom(soubory);
        var html = '';

        vysledek.klice.forEach(function (adresar) {
            var skupinaSouboru = vysledek.strom[adresar];
            var nazevAdresare  = adresar === '' ? '/ (ROOT)' : '/' + adresar + '/';
            var idAdr = 'soub-adr-' + (adresar || 'root').replace(/[^a-zA-Z0-9]/g, '_');

            html += '<div class="soub-adresar-skupina">';
            html += '<div class="soub-adresar-hlavicka" onclick="soubPrepnoutAdresar(\'' + escHtml(idAdr) + '\')">';
            html += '<span class="soub-adresar-toggler" id="soub-toggler-' + escHtml(idAdr) + '">▼</span>';
            html += '<span class="soub-adresar-nazev">' + escHtml(nazevAdresare) + '</span>';
            html += '<span class="soub-adresar-pocet">' + skupinaSouboru.length + ' ' + (skupinaSouboru.length === 1 ? 'soubor' : skupinaSouboru.length < 5 ? 'soubory' : 'souborů') + '</span>';
            html += '</div>';
            html += '<div class="soub-adresar-obsah" id="' + escHtml(idAdr) + '">';

            // Seřadit: PHP, JS, CSS, html, data, ostatní - abecedně v rámci skupin
            var typPoradi = { php: 0, js: 1, css: 2, html: 3, data: 4, sql: 5, text: 6, htaccess: 7, shell: 8, konfig: 9, obr: 10, font: 11, ostatni: 12 };
            skupinaSouboru.sort(function (a, b) {
                var pA = typPoradi[a.typ] !== undefined ? typPoradi[a.typ] : 12;
                var pB = typPoradi[b.typ] !== undefined ? typPoradi[b.typ] : 12;
                if (pA !== pB) { return pA - pB; }
                return a.nazev.localeCompare(b.nazev, 'cs');
            });

            skupinaSouboru.forEach(function (soubor, idx) {
                var jePosledni  = idx === skupinaSouboru.length - 1;
                var stromSymbol = jePosledni ? '└─' : '├─';
                html += soubSestavitRadek(soubor, stromSymbol);
            });

            html += '</div></div>';
        });

        kontejner.innerHTML = html;
        kontejner.style.display = 'block';

        infoEl.textContent  = 'Zobrazeno ' + soubory.length + ' z ' + vsechnySoubory.length + ' souborů';
        infoEl.style.display = 'block';
        infoBar.style.display = 'none';
    };

    /* =====================================================
       Sestavení řádku souboru
       ===================================================== */

    function soubSestavitRadek(s, stromSymbol) {
        var idS      = 'soub-s-' + s.cesta.replace(/[^a-zA-Z0-9]/g, '_');
        var oznacen  = s.oznaceno;
        var bezVyuzi = s.pocetVyuzivani === 0 && ['php', 'js', 'css'].indexOf(s.typ) !== -1;

        // Stav badge
        var stavHtml;
        if (oznacen) {
            stavHtml = '<span class="soub-stav soub-stav-oznacen">Ke smazání</span>';
        } else if (bezVyuzi) {
            stavHtml = '<span class="soub-stav soub-stav-bezVyuziti">Bez využití</span>';
        } else {
            stavHtml = '<span class="soub-stav soub-stav-aktivni">Aktivní</span>';
        }

        // Využití badge
        var pocetV = s.pocetVyuzivani;
        var vTrida = pocetV === 0 ? 'nulove' : (pocetV > 10 ? 'vysoke' : '');
        var vyuzitiHtml = '<span class="soub-vyuziti-badge ' + vTrida + '" '
            + 'onclick="soubPrepnoutDetail(\'' + escHtml(idS) + '\')" '
            + 'title="Využíváno v ' + pocetV + ' souborech">'
            + pocetV + 'x</span>';

        // Závislosti badge
        var pocetZ = s.pocetZavislosti;
        var zavHtml = '';
        if (pocetZ > 0) {
            zavHtml = '<span class="soub-vyuziti-badge" '
                + 'onclick="soubPrepnoutDetail(\'' + escHtml(idS) + '\')" '
                + 'title="Závisí na ' + pocetZ + ' souborech">'
                + pocetZ + ' dep</span>';
        }

        // Akční tlačítka
        var btnText  = oznacen ? 'Odznačit' : 'Označit';
        var btnTrida = oznacen ? 'soub-btn-oznacit aktivni' : 'soub-btn-oznacit';

        var radekTrida = oznacen ? 'soub-radek oznaceny' : 'soub-radek';

        var html = '<div class="' + radekTrida + '" id="soub-radek-' + escHtml(idS) + '" '
            + 'data-cesta="' + escHtml(s.cesta) + '" '
            + 'data-typ="' + escHtml(s.typ) + '" '
            + 'data-oznaceno="' + (oznacen ? '1' : '0') + '">';

        html += '<span class="soub-strom-symbol">' + escHtml(stromSymbol) + '</span>';

        html += '<span class="soub-nazev-wrapper">';
        html += '<span class="soub-typ soub-typ-' + escHtml(s.typ) + '">' + escHtml(s.typ.toUpperCase()) + '</span>';
        html += '<span class="soub-nazev" onclick="soubPrepnoutDetail(\'' + escHtml(idS) + '\')" title="' + escHtml(s.cesta) + '">' + escHtml(s.nazev) + '</span>';
        html += '</span>';

        html += '<span class="soub-velikost">' + escHtml(s.velikostText || '') + '</span>';
        html += vyuzitiHtml;
        html += zavHtml;
        html += stavHtml;

        html += '<span class="soub-akce">';
        html += '<button class="' + btnTrida + '" onclick="soubPrepnoutOznaceni(\'' + escHtml(s.cesta) + '\',\'' + escHtml(idS) + '\')">' + btnText + '</button>';
        html += '<button class="soub-btn-detail" onclick="soubPrepnoutDetail(\'' + escHtml(idS) + '\')">Detail</button>';
        html += '</span>';

        html += '</div>';

        // Detail panel
        html += soubSestavitDetail(s, idS);

        return html;
    }

    /* =====================================================
       Detail panel se závislostmi
       ===================================================== */

    function soubSestavitDetail(s, idS) {
        // Informace o souboru
        var infoHtml = '<div class="soub-detail-info">'
            + '<span><strong>Cesta:</strong> ' + escHtml(s.cesta) + '</span>'
            + '<span><strong>Velikost:</strong> ' + escHtml(s.velikostText || '') + '</span>'
            + '<span><strong>Změněno:</strong> ' + escHtml(formatDatum(s.zmeneno)) + '</span>'
            + '<span><strong>Využití:</strong> ' + s.pocetVyuzivani + 'x odkazován</span>'
            + '<span><strong>Závislosti:</strong> ' + s.pocetZavislosti + ' souborů</span>'
            + '</div>';

        // Závisí na (co includuje tento soubor)
        var zavHtml;
        if (s.zavislosti && s.zavislosti.length > 0) {
            zavHtml = '<div class="soub-zavislosti-sekce-nadpis">Závisí na (' + s.zavislosti.length + '):</div>'
                + '<div class="soub-zavislosti-strom">';
            s.zavislosti.forEach(function (zav, idx) {
                var symbol = idx === s.zavislosti.length - 1 ? '└' : '├';
                zavHtml += '<div class="soub-zavislost-radek">'
                    + '<span class="soub-zavislost-symbol">' + escHtml(symbol) + '</span>'
                    + '<span class="soub-zavislost-cesta">' + escHtml(zav) + '</span>'
                    + '</div>';
            });
            zavHtml += '</div>';
        } else {
            zavHtml = '<div class="soub-zavislosti-sekce-nadpis">Závisí na:</div>'
                + '<div class="soub-prazdne">Žádné závislosti</div>';
        }

        // Využíváno v (kdo includuje tento soubor)
        var vyuHtml;
        if (s.vyuzivani && s.vyuzivani.length > 0) {
            vyuHtml = '<div class="soub-zavislosti-sekce-nadpis">Využíváno v (' + s.vyuzivani.length + '):</div>'
                + '<div class="soub-zavislosti-strom">';
            s.vyuzivani.forEach(function (vyuz, idx) {
                var symbol = idx === s.vyuzivani.length - 1 ? '└' : '├';
                vyuHtml += '<div class="soub-zavislost-radek">'
                    + '<span class="soub-zavislost-symbol">' + escHtml(symbol) + '</span>'
                    + '<span class="soub-zavislost-cesta">' + escHtml(vyuz) + '</span>'
                    + '</div>';
            });
            vyuHtml += '</div>';
        } else {
            vyuHtml = '<div class="soub-zavislosti-sekce-nadpis">Využíváno v:</div>'
                + '<div class="soub-prazdne">Nikde není odkazován - lze bezpečně smazat</div>';
        }

        return '<div class="soub-detail" id="' + escHtml(idS) + '-detail">'
            + infoHtml
            + '<div class="soub-zavislosti-grid">'
            + '<div>' + zavHtml + '</div>'
            + '<div>' + vyuHtml + '</div>'
            + '</div>'
            + '</div>';
    }

    /* =====================================================
       Interakce
       ===================================================== */

    window.soubPrepnoutDetail = function (idS) {
        var el = document.getElementById(idS + '-detail');
        if (!el) { return; }
        el.classList.toggle('viditelny');
    };

    window.soubPrepnoutAdresar = function (idAdr) {
        var obsah   = document.getElementById(idAdr);
        var toggler = document.getElementById('soub-toggler-' + idAdr);
        if (!obsah || !toggler) { return; }
        obsah.classList.toggle('skryto');
        toggler.classList.toggle('zavreno');
    };

    window.soubPrepnoutOznaceni = async function (cesta, idS) {
        var radek    = document.getElementById('soub-radek-' + idS);
        var oznacen  = radek && radek.dataset.oznaceno === '1';
        var akceToggle = oznacen ? 'odznacit' : 'oznacit';

        // Varování pokud soubor má aktivní závislosti
        if (!oznacen) {
            var soubor = vsechnySoubory.find(function (s) { return s.cesta === cesta; });
            if (soubor && soubor.pocetVyuzivani > 0) {
                var seznam = (soubor.vyuzivani || []).slice(0, 5).join('\n  ');
                var vic = soubor.vyuzivani.length > 5 ? '\n  ... a ' + (soubor.vyuzivani.length - 5) + ' dalších' : '';
                var potvrzeni = confirm(
                    'Soubor "' + cesta + '" je využíván v ' + soubor.pocetVyuzivani + ' souborech!\n\n'
                    + 'Využíváno v:\n  ' + seznam + vic + '\n\n'
                    + 'Označit ke smazání přesto?'
                );
                if (!potvrzeni) { return; }
            }
        }

        try {
            var formData = new FormData();
            formData.append('akce', 'prepnout');
            formData.append('cesta', cesta);
            formData.append('akce_toggle', akceToggle);
            formData.append('csrf_token', ziskejCsrf());

            var odp  = await fetch('/api/soubory_api.php', { method: 'POST', body: formData });
            var data = await odp.json();

            if (data.status === 'success') {
                // Aktualizovat lokální data
                var soubor = vsechnySoubory.find(function (s) { return s.cesta === cesta; });
                if (soubor) {
                    soubor.oznaceno = (akceToggle === 'oznacit');
                }

                // Aktualizovat počet v statistikách
                var pocetOznacenych = vsechnySoubory.filter(function (s) { return s.oznaceno; }).length;
                document.getElementById('soub-stat-oznaceno').textContent = pocetOznacenych;

                // Překreslit
                soubFiltrovat();
            } else {
                alert('Chyba: ' + (data.zprava || 'Neznámá chyba'));
            }
        } catch (err) {
            alert('Chyba komunikace se serverem: ' + err.message);
        }
    };

    /* =====================================================
       Spuštění
       ===================================================== */

    soubNacist(false);

})();
</script>
