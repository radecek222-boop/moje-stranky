<?php
/**
 * Admin karta: ROOT přehled souborů - řádkový výpis
 */

if (!defined('ADMIN_PHP_LOADED')) {
    die('Přímý přístup zakázán.');
}
?>
<style>
/* ================================================================
   ROOT Soubory - řádkový výpis
   ================================================================ */
.sf-wrap {
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.8rem;
}

/* Záhlaví */
.sf-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}

.sf-head h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    font-family: sans-serif;
}

.sf-head-meta {
    font-size: 0.7rem;
    color: #aaa;
    font-family: sans-serif;
    margin-top: 0.15rem;
}

/* Statistiky */
.sf-stats {
    display: flex;
    gap: 0;
    margin-bottom: 1rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.sf-stat {
    flex: 1;
    text-align: center;
    padding: 0.6rem 0.5rem;
    border-right: 1px solid #ddd;
    cursor: pointer;
    transition: background 0.1s;
    background: #fff;
}

.sf-stat:last-child { border-right: none; }

.sf-stat:hover { background: #f5f5f5; }

.sf-stat.aktivni { background: #222; }

.sf-stat.aktivni .sf-stat-n { color: #fff; }
.sf-stat.aktivni .sf-stat-l { color: #aaa; }

.sf-stat-n {
    display: block;
    font-size: 1.3rem;
    font-weight: 700;
    color: #111;
    line-height: 1.1;
}

.sf-stat-l {
    display: block;
    font-size: 0.62rem;
    color: #888;
    font-family: sans-serif;
    margin-top: 0.1rem;
}

/* Nástrojová lišta */
.sf-toolbar {
    display: flex;
    gap: 0.4rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 0.75rem;
}

.sf-search {
    flex: 1;
    min-width: 180px;
    padding: 0.3rem 0.6rem;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 0.78rem;
    font-family: 'Courier New', monospace;
    background: #fff;
}

.sf-search:focus { outline: none; border-color: #555; }

.sf-btn {
    padding: 0.3rem 0.65rem;
    border: 1px solid #444;
    border-radius: 3px;
    background: #333;
    color: #fff;
    font-size: 0.73rem;
    cursor: pointer;
    font-family: 'Courier New', monospace;
    white-space: nowrap;
    transition: background 0.1s;
}

.sf-btn:hover  { background: #000; }
.sf-btn:disabled { background: #999; border-color: #999; cursor: not-allowed; }

/* ================================================================
   TABULKA
   ================================================================ */
.sf-table-wrap {
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

/* Záhlaví tabulky */
.sf-table-head {
    display: grid;
    grid-template-columns: 26px 1fr 46px 54px 54px 74px 82px;
    align-items: center;
    padding: 0.3rem 0.6rem;
    background: #f0f0f0;
    border-bottom: 2px solid #ccc;
    font-size: 0.65rem;
    font-weight: 700;
    color: #555;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-family: sans-serif;
    gap: 0.4rem;
}

/* Skupina adresáře */
.sf-dir-head {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.28rem 0.6rem;
    background: #f7f7f7;
    border-bottom: 1px solid #e8e8e8;
    border-top: 1px solid #e8e8e8;
    cursor: pointer;
    user-select: none;
}

.sf-dir-head:first-child { border-top: none; }

.sf-dir-name {
    font-size: 0.73rem;
    font-weight: 600;
    color: #444;
    font-family: 'Courier New', monospace;
    flex: 1;
}

.sf-dir-toggler {
    font-size: 0.65rem;
    color: #999;
    width: 12px;
    flex-shrink: 0;
    transition: transform 0.15s;
}

.sf-dir-toggler.zavreno { transform: rotate(-90deg); }

.sf-dir-cnt {
    font-size: 0.65rem;
    color: #bbb;
    font-family: sans-serif;
}

.sf-dir-body { /* default viditelný */ }
.sf-dir-body.skryto { display: none; }

/* Řádek souboru */
.sf-row {
    display: grid;
    grid-template-columns: 26px 1fr 46px 54px 54px 74px 82px;
    align-items: center;
    padding: 0.25rem 0.6rem;
    border-bottom: 1px solid #f3f3f3;
    gap: 0.4rem;
    transition: background 0.08s;
    cursor: default;
}

.sf-row:last-child { border-bottom: none; }
.sf-row:hover      { background: #fafafa; }
.sf-row.ke-smazani { background: #f7f7f7; opacity: 0.65; }

/* Strom symbol */
.sf-sym {
    color: #ccc;
    font-size: 0.75rem;
    text-align: right;
    user-select: none;
    flex-shrink: 0;
}

/* Název souboru */
.sf-name {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    overflow: hidden;
}

.sf-typ {
    font-size: 0.57rem;
    padding: 0.08rem 0.3rem;
    border-radius: 2px;
    font-weight: 700;
    text-transform: uppercase;
    border: 1px solid;
    flex-shrink: 0;
    letter-spacing: 0.02em;
}

.sf-typ-php      { background: #e8e8e8; color: #222; border-color: #bbb; }
.sf-typ-js       { background: #efefef; color: #333; border-color: #c5c5c5; }
.sf-typ-css      { background: #e5e5e5; color: #333; border-color: #bbb; }
.sf-typ-html     { background: #f0f0f0; color: #444; border-color: #ccc; }
.sf-typ-data     { background: #f5f5f5; color: #666; border-color: #ddd; }
.sf-typ-sql      { background: #ebebeb; color: #444; border-color: #ccc; }
.sf-typ-text     { background: #f5f5f5; color: #777; border-color: #ddd; }
.sf-typ-obr      { background: #f5f5f5; color: #888; border-color: #e0e0e0; }
.sf-typ-font     { background: #f5f5f5; color: #888; border-color: #e0e0e0; }
.sf-typ-htaccess { background: #ddd; color: #222; border-color: #aaa; }
.sf-typ-shell    { background: #e8e8e8; color: #333; border-color: #bbb; }
.sf-typ-konfig   { background: #efefef; color: #555; border-color: #ddd; }
.sf-typ-ostatni  { background: #f8f8f8; color: #aaa; border-color: #eee; }

.sf-file-name {
    font-weight: 500;
    color: #111;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    font-size: 0.78rem;
}

.sf-file-name:hover { text-decoration: underline; }

/* Velikost */
.sf-size {
    font-size: 0.65rem;
    color: #bbb;
    text-align: right;
    white-space: nowrap;
}

/* Využití badge */
.sf-used {
    text-align: center;
    font-size: 0.68rem;
    font-weight: 700;
    color: #555;
    cursor: pointer;
    padding: 0.12rem 0.3rem;
    border-radius: 3px;
    border: 1px solid transparent;
    transition: all 0.1s;
}

.sf-used:hover  { background: #efefef; border-color: #ccc; }
.sf-used.nula   { color: #ccc; font-weight: 400; }
.sf-used.hodne  { font-weight: 700; }

/* Závislosti badge */
.sf-deps {
    text-align: center;
    font-size: 0.68rem;
    color: #777;
    cursor: pointer;
    padding: 0.12rem 0.3rem;
    border-radius: 3px;
    border: 1px solid transparent;
    transition: all 0.1s;
}

.sf-deps:hover  { background: #efefef; border-color: #ccc; }
.sf-deps.zadne  { color: #ddd; }

/* Stav */
.sf-stav {
    text-align: center;
    font-size: 0.62rem;
    font-family: sans-serif;
    white-space: nowrap;
}

.sf-badge {
    display: inline-block;
    padding: 0.1rem 0.4rem;
    border-radius: 8px;
    border: 1px solid;
}

.sf-b-aktivni    { background: #f5f5f5; color: #666; border-color: #e0e0e0; }
.sf-b-bezVyuz   { background: #ececec; color: #777; border-color: #ccc; font-style: italic; }
.sf-b-oznacen   { background: #2a2a2a; color: #eee; border-color: #111; }

/* Akce */
.sf-akce {
    display: flex;
    gap: 0.2rem;
    justify-content: flex-end;
    align-items: center;
}

.sf-btn-o {
    font-size: 0.62rem;
    padding: 0.12rem 0.4rem;
    border: 1px solid #ccc;
    border-radius: 2px;
    background: #fff;
    color: #444;
    cursor: pointer;
    font-family: 'Courier New', monospace;
    transition: all 0.1s;
    white-space: nowrap;
}

.sf-btn-o:hover    { background: #f0f0f0; }
.sf-btn-o.aktivni  { background: #333; color: #fff; border-color: #111; }

/* Červené tlačítko smazat - schválená výjimka (destruktivní akce) */
.sf-btn-smazat {
    font-size: 0.62rem;
    padding: 0.12rem 0.4rem;
    border: 1px solid #c82333;
    border-radius: 2px;
    background: #dc3545;
    color: #fff;
    cursor: pointer;
    font-family: 'Courier New', monospace;
    font-weight: 700;
    transition: all 0.1s;
    white-space: nowrap;
}

.sf-btn-smazat:hover    { background: #c82333; }
.sf-btn-smazat:disabled { background: #999; border-color: #999; cursor: not-allowed; }

.sf-btn-d {
    font-size: 0.62rem;
    padding: 0.12rem 0.4rem;
    border: 1px solid #e5e5e5;
    border-radius: 2px;
    background: #fafafa;
    color: #999;
    cursor: pointer;
    font-family: 'Courier New', monospace;
    transition: all 0.1s;
}

.sf-btn-d:hover { background: #efefef; color: #555; border-color: #ccc; }

/* ================================================================
   DETAIL ŘÁDEK - závislosti
   ================================================================ */
.sf-detail {
    display: none;
    padding: 0.5rem 0.6rem 0.6rem 2.6rem;
    background: #fafafa;
    border-bottom: 1px solid #ebebeb;
    border-top: 1px dotted #ebebeb;
}

.sf-detail.on { display: block; }

.sf-detail-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 650px) {
    .sf-detail-cols { grid-template-columns: 1fr; }
}

.sf-detail-label {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #888;
    font-family: sans-serif;
    margin-bottom: 0.3rem;
}

.sf-tree-line {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    padding: 0.08rem 0;
    border-left: 2px solid #e0e0e0;
    padding-left: 0.5rem;
    margin-left: 0.3rem;
}

.sf-tree-sym {
    color: #ccc;
    font-size: 0.7rem;
    flex-shrink: 0;
}

.sf-tree-val {
    font-size: 0.7rem;
    color: #333;
    word-break: break-all;
}

.sf-tree-empty {
    font-size: 0.7rem;
    color: #bbb;
    font-style: italic;
    font-family: sans-serif;
    padding-left: 0.3rem;
}

.sf-detail-meta {
    display: flex;
    gap: 1.2rem;
    flex-wrap: wrap;
    font-size: 0.68rem;
    color: #888;
    font-family: sans-serif;
    margin-bottom: 0.5rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid #ebebeb;
}

.sf-detail-meta strong { color: #555; }

/* ================================================================
   STAVY UI
   ================================================================ */
.sf-loading {
    text-align: center;
    padding: 3rem 1rem;
    color: #888;
    font-family: sans-serif;
}

.sf-spin {
    display: inline-block;
    width: 24px; height: 24px;
    border: 3px solid #ddd;
    border-top-color: #555;
    border-radius: 50%;
    animation: sf-spin 0.7s linear infinite;
    margin-bottom: 0.75rem;
}

@keyframes sf-spin { to { transform: rotate(360deg); } }

.sf-error {
    padding: 1.5rem;
    text-align: center;
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 4px;
    font-family: sans-serif;
    color: #777;
}

.sf-empty {
    padding: 1.5rem;
    text-align: center;
    color: #bbb;
    font-family: sans-serif;
    font-size: 0.85rem;
}

/* Spodní info */
.sf-footer {
    padding: 0.35rem 0.6rem;
    font-size: 0.68rem;
    color: #bbb;
    background: #fafafa;
    border-top: 1px solid #ebebeb;
    font-family: sans-serif;
}

/* Responsivní: skrýt méně důležité sloupce */
@media (max-width: 700px) {
    .sf-table-head,
    .sf-row {
        grid-template-columns: 22px 1fr 40px 44px 60px;
    }
    .sf-table-head > :nth-child(4),
    .sf-row .sf-deps,
    .sf-table-head > :nth-child(5),
    .sf-row .sf-size { display: none; }
}
</style>

<div class="sf-wrap">

    <!-- Záhlaví -->
    <div class="sf-head">
        <div>
            <h2>ROOT — přehled souborů</h2>
            <div class="sf-head-meta" id="sf-meta">Načítám...</div>
        </div>
        <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;">
            <input type="text" class="sf-search" id="sf-search"
                   placeholder="Hledat soubor nebo cestu..."
                   oninput="sfFiltrovat()">
            <button class="sf-btn" id="sf-btn-scan" onclick="sfNacist(true)">Znovu skenovat</button>
        </div>
    </div>

    <!-- Statistiky / rychlé filtry -->
    <div class="sf-stats" id="sf-stats" style="display:none;">
        <div class="sf-stat aktivni" onclick="sfFiltr('vse',this)" data-filtr="vse">
            <span class="sf-stat-n" id="sf-n-vse">-</span>
            <span class="sf-stat-l">Vše</span>
        </div>
        <div class="sf-stat" onclick="sfFiltr('php',this)" data-filtr="php">
            <span class="sf-stat-n" id="sf-n-php">-</span>
            <span class="sf-stat-l">PHP</span>
        </div>
        <div class="sf-stat" onclick="sfFiltr('js',this)" data-filtr="js">
            <span class="sf-stat-n" id="sf-n-js">-</span>
            <span class="sf-stat-l">JS</span>
        </div>
        <div class="sf-stat" onclick="sfFiltr('css',this)" data-filtr="css">
            <span class="sf-stat-n" id="sf-n-css">-</span>
            <span class="sf-stat-l">CSS</span>
        </div>
        <div class="sf-stat" onclick="sfFiltr('ostatni',this)" data-filtr="ostatni">
            <span class="sf-stat-n" id="sf-n-ost">-</span>
            <span class="sf-stat-l">Ostatní</span>
        </div>
        <div class="sf-stat" onclick="sfFiltr('bez-vyuziti',this)" data-filtr="bez-vyuziti">
            <span class="sf-stat-n" id="sf-n-bv">-</span>
            <span class="sf-stat-l">Bez využití</span>
        </div>
        <div class="sf-stat" onclick="sfFiltr('oznaceno',this)" data-filtr="oznaceno">
            <span class="sf-stat-n" id="sf-n-oz">-</span>
            <span class="sf-stat-l">Ke smazání</span>
        </div>
    </div>

    <!-- Načítání -->
    <div id="sf-loading" class="sf-loading">
        <div class="sf-spin"></div>
        <div style="font-weight:600;">Skenování souborů...</div>
        <div style="font-size:.75rem;color:#bbb;margin-top:.3rem;">
            První sken může trvat 5–15 sekund. Výsledky se ukládají do cache.
        </div>
    </div>

    <!-- Chyba -->
    <div id="sf-error" class="sf-error" style="display:none;">
        <strong>Chyba při načítání</strong>
        <div id="sf-error-text" style="margin-top:.4rem;font-size:.8rem;"></div>
        <button class="sf-btn" style="margin-top:.75rem;" onclick="sfNacist(false)">Zkusit znovu</button>
    </div>

    <!-- Tabulka -->
    <div id="sf-table-wrap" style="display:none;">
        <div class="sf-table-wrap">
            <div class="sf-table-head">
                <span></span>
                <span>Soubor / cesta</span>
                <span style="text-align:right;">Vel.</span>
                <span style="text-align:center;">Využití</span>
                <span style="text-align:center;">Závisl.</span>
                <span style="text-align:center;">Stav</span>
                <span style="text-align:right;">Akce</span>
            </div>
            <div id="sf-body"></div>
        </div>
        <div class="sf-footer" id="sf-footer"></div>
    </div>

</div>

<script>
(function () {
    'use strict';

    var vsechny = [];
    var filtr   = 'vse';
    var hledani = '';

    function esc(t) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(t || '')));
        return d.innerHTML;
    }

    function csrf() {
        var el = document.querySelector('input[name="csrf_token"]');
        return el ? el.value : '';
    }

    function fmtDatum(ts) {
        return new Date(ts * 1000).toLocaleDateString('cs-CZ', {
            day: '2-digit', month: '2-digit', year: '2-digit'
        });
    }

    /* --------------------------------------------------------
       Načtení dat
       -------------------------------------------------------- */
    window.sfNacist = async function (nocache) {
        var btn = document.getElementById('sf-btn-scan');
        if (btn) { btn.disabled = true; btn.textContent = 'Skenuji...'; }

        document.getElementById('sf-loading').style.display    = 'block';
        document.getElementById('sf-table-wrap').style.display = 'none';
        document.getElementById('sf-error').style.display      = 'none';
        document.getElementById('sf-stats').style.display      = 'none';

        try {
            var odp  = await fetch('/api/soubory_api.php?akce=seznam' + (nocache ? '&nocache=1' : ''));
            if (!odp.ok) { throw new Error('HTTP ' + odp.status); }
            var data = await odp.json();
            if (data.status !== 'success') { throw new Error(data.zprava || 'Chyba serveru'); }

            vsechny = data.soubory || [];
            var st  = data.statistiky || {};

            // Statistiky
            document.getElementById('sf-n-vse').textContent = st.celkem || vsechny.length;
            document.getElementById('sf-n-php').textContent = st.php    || 0;
            document.getElementById('sf-n-js').textContent  = st.js     || 0;
            document.getElementById('sf-n-css').textContent = st.css    || 0;
            document.getElementById('sf-n-ost').textContent = st.ostatni|| 0;
            document.getElementById('sf-n-bv').textContent  = st.bezVyuziti || 0;
            document.getElementById('sf-n-oz').textContent  = st.oznaceno   || 0;
            document.getElementById('sf-stats').style.display = 'flex';

            // Meta info
            var meta = document.getElementById('sf-meta');
            meta.textContent = data.zCache
                ? 'Z cache (' + (data.cacheCas || '') + ')  •  ' + (st.celkem || vsechny.length) + ' souborů'
                : 'Skenováno za ' + (st.dobaSken || '?') + '  •  ' + (st.celkem || vsechny.length) + ' souborů';

            document.getElementById('sf-loading').style.display = 'none';
            sfFiltrovat();

        } catch (err) {
            document.getElementById('sf-loading').style.display = 'none';
            document.getElementById('sf-error').style.display   = 'block';
            document.getElementById('sf-error-text').textContent = err.message;
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Znovu skenovat'; }
        }
    };

    /* --------------------------------------------------------
       Filtrování
       -------------------------------------------------------- */
    window.sfFiltr = function (f, el) {
        filtr = f;
        document.querySelectorAll('.sf-stat').forEach(function (s) { s.classList.remove('aktivni'); });
        if (el) { el.classList.add('aktivni'); }
        sfFiltrovat();
    };

    window.sfFiltrovat = function () {
        hledani = (document.getElementById('sf-search').value || '').toLowerCase();

        var seznam = vsechny.filter(function (s) {
            if (hledani && s.cesta.toLowerCase().indexOf(hledani) === -1) { return false; }
            switch (filtr) {
                case 'php':        return s.typ === 'php';
                case 'js':         return s.typ === 'js';
                case 'css':        return s.typ === 'css';
                case 'ostatni':    return ['php','js','css'].indexOf(s.typ) === -1;
                case 'bez-vyuziti': return s.pocetVyuzivani === 0 && ['php','js','css'].indexOf(s.typ) !== -1;
                case 'oznaceno':   return s.oznaceno;
                default:           return true;
            }
        });

        sfKreslit(seznam);
    };

    /* --------------------------------------------------------
       Kreslení tabulky
       -------------------------------------------------------- */
    function sfKreslit(seznam) {
        var wrap = document.getElementById('sf-table-wrap');
        var body = document.getElementById('sf-body');
        var foot = document.getElementById('sf-footer');

        if (seznam.length === 0) {
            body.innerHTML = '<div class="sf-empty">Žádné soubory neodpovídají filtru.</div>';
            wrap.style.display = 'block';
            foot.textContent   = '';
            return;
        }

        // Sestavit strom adresářů
        var strom = {};
        seznam.forEach(function (s) {
            var adr = s.adresar || '';
            if (!strom[adr]) { strom[adr] = []; }
            strom[adr].push(s);
        });

        var klice = Object.keys(strom).sort(function (a, b) {
            if (a === '') { return -1; }
            if (b === '') { return 1; }
            return a.localeCompare(b, 'cs');
        });

        var html = '';
        var typPor = { php:0, js:1, css:2, html:3, data:4, sql:5, text:6, htaccess:7, shell:8, konfig:9, obr:10, font:11, ostatni:12 };

        klice.forEach(function (adr) {
            var skupina = strom[adr];
            var nadpis  = adr === '' ? '/ ROOT' : '/' + adr;
            var idAdr   = 'sfadr_' + (adr || 'root').replace(/[^a-z0-9]/gi, '_');

            html += '<div class="sf-dir-head" onclick="sfToggleAdr(\'' + esc(idAdr) + '\')">'
                + '<span class="sf-dir-toggler" id="sft_' + esc(idAdr) + '">▼</span>'
                + '<span class="sf-dir-name">' + esc(nadpis) + '</span>'
                + '<span class="sf-dir-cnt">' + skupina.length + ' souborů</span>'
                + '</div>';

            html += '<div class="sf-dir-body" id="' + esc(idAdr) + '">';

            skupina.sort(function (a, b) {
                var pa = typPor[a.typ] !== undefined ? typPor[a.typ] : 12;
                var pb = typPor[b.typ] !== undefined ? typPor[b.typ] : 12;
                return pa !== pb ? pa - pb : a.nazev.localeCompare(b.nazev, 'cs');
            });

            skupina.forEach(function (s, idx) {
                var posledni = idx === skupina.length - 1;
                html += sfRadek(s, posledni ? '└─' : '├─');
            });

            html += '</div>';
        });

        body.innerHTML        = html;
        wrap.style.display    = 'block';
        foot.textContent      = 'Zobrazeno ' + seznam.length + ' z ' + vsechny.length + ' souborů';
    }

    /* --------------------------------------------------------
       Sestavení jednoho řádku
       -------------------------------------------------------- */
    function sfRadek(s, sym) {
        var id      = 'sfr_' + s.cesta.replace(/[^a-z0-9]/gi, '_');
        var ozn     = s.oznaceno;
        var bezVyu  = s.pocetVyuzivani === 0 && ['php','js','css'].indexOf(s.typ) !== -1;

        // Stav badge
        var stavBadge;
        if (ozn)    { stavBadge = '<span class="sf-badge sf-b-oznacen">Ke smazání</span>'; }
        else if (bezVyu) { stavBadge = '<span class="sf-badge sf-b-bezVyuz">Bez využití</span>'; }
        else        { stavBadge = '<span class="sf-badge sf-b-aktivni">Aktivní</span>'; }

        // Využití
        var vu = s.pocetVyuzivani;
        var vuTrida = vu === 0 ? 'nula' : (vu > 10 ? 'hodne' : '');
        var vuTxt   = vu === 0 ? '–' : vu + '×';

        // Závislosti
        var zd = s.pocetZavislosti;
        var zdTrida = zd === 0 ? 'zadne' : '';
        var zdTxt   = zd === 0 ? '–' : zd + ' dep';

        // Tlačítko označení
        var btnTxt   = ozn ? 'Odznačit' : 'Označit';
        var btnTrida = ozn ? 'sf-btn-o aktivni' : 'sf-btn-o';

        var radekTrida = ozn ? 'sf-row ke-smazani' : 'sf-row';

        return '<div class="' + radekTrida + '" id="' + esc(id) + '" '
            + 'data-cesta="' + esc(s.cesta) + '" data-ozn="' + (ozn ? '1' : '0') + '">'

            // Strom symbol
            + '<span class="sf-sym">' + esc(sym) + '</span>'

            // Název
            + '<span class="sf-name">'
            + '<span class="sf-typ sf-typ-' + esc(s.typ) + '">' + esc(s.typ.toUpperCase()) + '</span>'
            + '<span class="sf-file-name" onclick="sfToggleDetail(\'' + esc(id) + '\')" title="' + esc(s.cesta) + '">'
            + esc(s.nazev)
            + '</span>'
            + '</span>'

            // Velikost
            + '<span class="sf-size">' + esc(s.velikostText || '') + '</span>'

            // Využití
            + '<span class="sf-used ' + vuTrida + '" '
            + 'onclick="sfToggleDetail(\'' + esc(id) + '\')" '
            + 'title="Odkazováno v ' + vu + ' souborech">' + vuTxt + '</span>'

            // Závislosti
            + '<span class="sf-deps ' + zdTrida + '" '
            + 'onclick="sfToggleDetail(\'' + esc(id) + '\')" '
            + 'title="Závisí na ' + zd + ' souborech">' + zdTxt + '</span>'

            // Stav
            + '<span class="sf-stav">' + stavBadge + '</span>'

            // Akce: Označit/Odznačit + červené Smazat (pouze u označených) + detail
            + '<span class="sf-akce">'
            + '<button class="' + btnTrida + '" onclick="sfOznacit(\'' + esc(s.cesta) + '\',\'' + esc(id) + '\')">' + btnTxt + '</button>'
            + (ozn ? '<button class="sf-btn-smazat" onclick="sfSmazat(\'' + esc(s.cesta) + '\',\'' + esc(id) + '\')">Smazat</button>' : '')
            + '<button class="sf-btn-d" onclick="sfToggleDetail(\'' + esc(id) + '\')">+</button>'
            + '</span>'

            + '</div>'

            // Detail
            + sfDetail(s, id);
    }

    /* --------------------------------------------------------
       Detail se závislostmi (stromový výpis)
       -------------------------------------------------------- */
    function sfDetail(s, id) {
        // Meta
        var meta = '<div class="sf-detail-meta">'
            + '<span><strong>Cesta:</strong> ' + esc(s.cesta) + '</span>'
            + '<span><strong>Velikost:</strong> ' + esc(s.velikostText || '') + '</span>'
            + '<span><strong>Změněno:</strong> ' + esc(fmtDatum(s.zmeneno)) + '</span>'
            + '</div>';

        // Závisí na (co tento soubor includuje)
        var zavHtml = '<div class="sf-detail-label">Závisí na (' + s.pocetZavislosti + ')</div>';
        if (s.zavislosti && s.zavislosti.length > 0) {
            s.zavislosti.forEach(function (z, i) {
                var sym = i === s.zavislosti.length - 1 ? '└' : '├';
                zavHtml += '<div class="sf-tree-line">'
                    + '<span class="sf-tree-sym">' + sym + '</span>'
                    + '<span class="sf-tree-val">' + esc(z) + '</span>'
                    + '</div>';
            });
        } else {
            zavHtml += '<div class="sf-tree-empty">— žádné závislosti</div>';
        }

        // Využíváno v (kdo odkazuje na tento soubor)
        var vyuHtml = '<div class="sf-detail-label">Využíváno v (' + s.pocetVyuzivani + ')</div>';
        if (s.vyuzivani && s.vyuzivani.length > 0) {
            s.vyuzivani.forEach(function (v, i) {
                var sym = i === s.vyuzivani.length - 1 ? '└' : '├';
                vyuHtml += '<div class="sf-tree-line">'
                    + '<span class="sf-tree-sym">' + sym + '</span>'
                    + '<span class="sf-tree-val">' + esc(v) + '</span>'
                    + '</div>';
            });
        } else {
            vyuHtml += '<div class="sf-tree-empty">— nikde není odkazován → lze bezpečně smazat</div>';
        }

        return '<div class="sf-detail" id="' + esc(id) + '-d">'
            + meta
            + '<div class="sf-detail-cols">'
            + '<div>' + zavHtml + '</div>'
            + '<div>' + vyuHtml + '</div>'
            + '</div>'
            + '</div>';
    }

    /* --------------------------------------------------------
       Interakce
       -------------------------------------------------------- */
    window.sfToggleDetail = function (id) {
        var el = document.getElementById(id + '-d');
        if (el) { el.classList.toggle('on'); }
    };

    window.sfToggleAdr = function (id) {
        var body = document.getElementById(id);
        var tog  = document.getElementById('sft_' + id);
        if (!body || !tog) { return; }
        body.classList.toggle('skryto');
        tog.classList.toggle('zavreno');
    };

    window.sfSmazat = async function (cesta, id) {
        var s = vsechny.find(function (x) { return x.cesta === cesta; });

        // Potvrzení - dvojité varování kvůli nevratnosti
        var varovani = 'TRVALE SMAZAT soubor:\n"' + cesta + '"\n\n'
            + 'Tato akce je NEVRATNÁ. Soubor nelze obnovit (kromě gitu).\n';
        if (s && s.pocetVyuzivani > 0) {
            varovani += '\nVAROVÁNÍ: Soubor je využíván v ' + s.pocetVyuzivani + ' souborech!\n'
                + (s.vyuzivani || []).slice(0, 3).join('\n') + '\n';
        }
        varovani += '\nOpravdu smazat?';

        if (!confirm(varovani)) { return; }
        // Druhé potvrzení pro jistotu
        if (!confirm('Poslední potvrzení: Smazat "' + cesta + '" TRVALE?')) { return; }

        var btn = document.querySelector('#' + id + ' .sf-btn-smazat');
        if (btn) { btn.disabled = true; btn.textContent = 'Mažu...'; }

        try {
            var fd = new FormData();
            fd.append('akce', 'smazat');
            fd.append('cesta', cesta);
            fd.append('csrf_token', csrf());

            var odp  = await fetch('/api/soubory_api.php', { method: 'POST', body: fd });
            var data = await odp.json();

            if (data.status === 'success') {
                // Odstranit ze seznamu a překreslit
                vsechny = vsechny.filter(function (x) { return x.cesta !== cesta; });

                // Aktualizovat stat karty
                var pocetOzn = vsechny.filter(function (x) { return x.oznaceno; }).length;
                document.getElementById('sf-n-oz').textContent  = pocetOzn;
                document.getElementById('sf-n-vse').textContent = vsechny.length;

                sfFiltrovat();
            } else {
                alert('Chyba: ' + (data.zprava || 'Neznámá chyba'));
                if (btn) { btn.disabled = false; btn.textContent = 'Smazat'; }
            }
        } catch (err) {
            alert('Chyba komunikace: ' + err.message);
            if (btn) { btn.disabled = false; btn.textContent = 'Smazat'; }
        }
    };

    window.sfOznacit = async function (cesta, id) {
        var radek   = document.getElementById(id);
        var ozn     = radek && radek.dataset.ozn === '1';
        var akce    = ozn ? 'odznacit' : 'oznacit';

        // Varování při označení souboru s aktivními závislostmi
        if (!ozn) {
            var s = vsechny.find(function (x) { return x.cesta === cesta; });
            if (s && s.pocetVyuzivani > 0) {
                var prve = (s.vyuzivani || []).slice(0, 4).join('\n  ');
                var vic  = s.vyuzivani.length > 4 ? '\n  ... a ' + (s.vyuzivani.length - 4) + ' dalších' : '';
                if (!confirm('Soubor "' + cesta + '" je využíván v ' + s.pocetVyuzivani + ' souborech!\n\nVyužíváno v:\n  ' + prve + vic + '\n\nOpravdu označit ke smazání?')) {
                    return;
                }
            }
        }

        try {
            var fd = new FormData();
            fd.append('akce', 'prepnout');
            fd.append('cesta', cesta);
            fd.append('akce_toggle', akce);
            fd.append('csrf_token', csrf());

            var odp  = await fetch('/api/soubory_api.php', { method: 'POST', body: fd });
            var data = await odp.json();

            if (data.status === 'success') {
                var s = vsechny.find(function (x) { return x.cesta === cesta; });
                if (s) { s.oznaceno = akce === 'oznacit'; }

                // Aktualizovat počty
                var pocetOzn = vsechny.filter(function (x) { return x.oznaceno; }).length;
                document.getElementById('sf-n-oz').textContent = pocetOzn;

                sfFiltrovat();
            } else {
                alert('Chyba: ' + (data.zprava || 'Neznámá chyba'));
            }
        } catch (err) {
            alert('Chyba komunikace: ' + err.message);
        }
    };

    /* --------------------------------------------------------
       Start
       -------------------------------------------------------- */
    sfNacist(false);

}());
</script>
