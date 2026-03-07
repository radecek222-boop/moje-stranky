<?php
/**
 * White Label landing page
 * Dostupné na: wl.wgs-service.cz
 * Prezentace WGS platformy jako white label řešení pro B2B prodej
 */

// Minimální bootstrap - bez plného init.php
define('BASE_PATH', __DIR__);
session_start();
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="WGS White Label — kompletní servisní platforma pod vaším brandem. Pro autorizované servisy, dealery a facility management firmy.">
    <meta name="robots" content="index, follow">
    <title>WGS White Label — Servisní platforma pod vaším brandem</title>

    <!-- Canonical -->
    <link rel="canonical" href="https://wl.wgs-service.cz/">

    <!-- Preload kritických fontů -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=optional" rel="stylesheet">

    <style>
        /* === RESET & ZÁKLAD === */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --cerna:     #000;
            --temer-cerna: #0a0a0a;
            --tmava:     #111;
            --tmava2:    #1a1a1a;
            --seda-tmava: #333;
            --seda:      #666;
            --seda-svetla: #999;
            --seda-svetlejsi: #ccc;
            --obrys:     #ddd;
            --svetla:    #eee;
            --temer-bila: #f5f5f5;
            --bila:      #fff;
            --prechod-tmava: linear-gradient(135deg, #000 0%, #1a1a1a 100%);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bila);
            color: var(--tmava);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* === NAVIGACE === */
        .nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid #222;
        }

        .nav-vnitrni {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-logo {
            font-size: 1rem;
            font-weight: 700;
            color: var(--bila);
            letter-spacing: 0.08em;
            text-decoration: none;
        }

        .nav-logo span {
            color: var(--seda-svetla);
            font-weight: 300;
            font-size: 0.85rem;
            margin-left: 8px;
            letter-spacing: 0.12em;
        }

        .nav-akce {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-odkaz {
            color: var(--seda-svetla);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
            padding: 6px 0;
        }

        .nav-odkaz:hover { color: var(--bila); }

        .nav-btn {
            padding: 8px 20px;
            background: var(--bila);
            color: var(--cerna);
            border: none;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            letter-spacing: 0.02em;
            transition: background 0.2s, color 0.2s;
        }

        .nav-btn:hover { background: var(--svetla); }

        /* === LAYOUT OBALY === */
        .sekce {
            padding: 100px 24px;
        }

        .sekce-tmava {
            background: var(--temer-cerna);
            color: var(--bila);
        }

        .sekce-seda {
            background: var(--temer-bila);
            color: var(--tmava);
        }

        .obal {
            max-width: 1100px;
            margin: 0 auto;
        }

        .obal-uzky {
            max-width: 720px;
            margin: 0 auto;
        }

        /* === HERO === */
        .hero {
            min-height: 100vh;
            background: var(--prechod-tmava);
            color: var(--bila);
            display: flex;
            align-items: center;
            padding: 120px 24px 80px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                radial-gradient(ellipse 60% 50% at 70% 40%, rgba(255,255,255,0.04) 0%, transparent 70%),
                repeating-linear-gradient(
                    0deg,
                    transparent,
                    transparent 79px,
                    rgba(255,255,255,0.02) 79px,
                    rgba(255,255,255,0.02) 80px
                ),
                repeating-linear-gradient(
                    90deg,
                    transparent,
                    transparent 79px,
                    rgba(255,255,255,0.02) 79px,
                    rgba(255,255,255,0.02) 80px
                );
            pointer-events: none;
        }

        .hero-obsah {
            position: relative;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
        }

        .hero-stitek {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            color: var(--seda-svetla);
            text-transform: uppercase;
            border: 1px solid #333;
            padding: 6px 14px;
            border-radius: 2px;
            margin-bottom: 28px;
        }

        .hero-nadpis {
            font-size: clamp(2.4rem, 6vw, 4.5rem);
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -0.02em;
            margin-bottom: 24px;
            max-width: 800px;
        }

        .hero-nadpis em {
            font-style: normal;
            color: var(--seda-svetlejsi);
        }

        .hero-popis {
            font-size: 1.15rem;
            color: var(--seda-svetla);
            max-width: 560px;
            line-height: 1.7;
            margin-bottom: 44px;
        }

        .hero-akce {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn-hlavni {
            padding: 14px 32px;
            background: var(--bila);
            color: var(--cerna);
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            letter-spacing: 0.02em;
            transition: background 0.2s, transform 0.15s;
            display: inline-block;
        }

        .btn-hlavni:hover { background: var(--svetla); transform: translateY(-1px); }

        .btn-vedlejsi {
            padding: 14px 32px;
            background: transparent;
            color: var(--bila);
            border: 1px solid #444;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            letter-spacing: 0.02em;
            transition: border-color 0.2s, transform 0.15s;
            display: inline-block;
        }

        .btn-vedlejsi:hover { border-color: #888; transform: translateY(-1px); }

        .hero-statistiky {
            display: flex;
            gap: 48px;
            margin-top: 72px;
            padding-top: 48px;
            border-top: 1px solid #1a1a1a;
            flex-wrap: wrap;
        }

        .stat {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stat-cislo {
            font-size: 2rem;
            font-weight: 700;
            color: var(--bila);
            letter-spacing: -0.02em;
        }

        .stat-popis {
            font-size: 0.8rem;
            color: var(--seda);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* === NADPISY SEKCÍ === */
        .sekce-nadpis {
            font-size: clamp(1.6rem, 3.5vw, 2.4rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin-bottom: 16px;
        }

        .sekce-podnadpis {
            font-size: 1rem;
            color: var(--seda);
            max-width: 560px;
            line-height: 1.7;
            margin-bottom: 56px;
        }

        .sekce-tmava .sekce-podnadpis { color: var(--seda-svetla); }

        .sekce-stitek {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--seda-svetla);
            margin-bottom: 12px;
        }

        .sekce-tmava .sekce-stitek { color: var(--seda); }
        .sekce-seda .sekce-stitek { color: var(--seda); }

        /* === CO DOSTANETE - GRID === */
        .vlastnosti-mrizka {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1px;
            background: #1a1a1a;
            border: 1px solid #1a1a1a;
            border-radius: 2px;
            overflow: hidden;
        }

        .vlastnost {
            background: var(--temer-cerna);
            padding: 36px 32px;
            transition: background 0.2s;
        }

        .vlastnost:hover { background: #111; }

        .vlastnost-ikona {
            width: 40px;
            height: 40px;
            border: 1px solid #333;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .vlastnost-ikona svg {
            width: 20px;
            height: 20px;
            stroke: var(--seda-svetla);
            fill: none;
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .vlastnost-nadpis {
            font-size: 1rem;
            font-weight: 600;
            color: var(--bila);
            margin-bottom: 10px;
        }

        .vlastnost-popis {
            font-size: 0.88rem;
            color: var(--seda);
            line-height: 1.65;
        }

        /* === JAK TO FUNGUJE === */
        .kroky {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 48px;
            position: relative;
        }

        .krok {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .krok-cislo {
            font-size: 3rem;
            font-weight: 700;
            color: var(--obrys);
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .krok-nadpis {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--tmava);
        }

        .krok-popis {
            font-size: 0.9rem;
            color: var(--seda);
            line-height: 1.65;
        }

        /* === PRO KOHO === */
        .segmenty {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .segment {
            background: var(--bila);
            border: 1px solid var(--obrys);
            border-radius: 4px;
            padding: 28px 24px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .segment:hover {
            border-color: var(--seda-svetla);
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .segment-nadpis {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--tmava);
        }

        .segment-popis {
            font-size: 0.85rem;
            color: var(--seda);
            line-height: 1.6;
        }

        /* === SROVNÁNÍ === */
        .srovnani-mrizka {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .srovnani-sloupec {
            padding: 36px 32px;
            border-radius: 4px;
        }

        .srovnani-sloupec.tmava {
            background: var(--temer-cerna);
            border: 1px solid #222;
        }

        .srovnani-sloupec.svetla {
            background: var(--temer-bila);
            border: 1px solid var(--obrys);
        }

        .srovnani-nadpis {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .srovnani-sloupec.tmava .srovnani-nadpis { color: var(--seda-svetla); }
        .srovnani-sloupec.svetla .srovnani-nadpis { color: var(--seda); }

        .srovnani-seznam {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .srovnani-polozka {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .srovnani-sloupec.tmava .srovnani-polozka { color: var(--seda-svetla); }
        .srovnani-sloupec.svetla .srovnani-polozka { color: var(--seda); }

        .znacka-ok, .znacka-ne {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
            font-size: 10px;
            font-weight: 700;
        }

        .znacka-ok { background: var(--bila); color: var(--cerna); }
        .srovnani-sloupec.svetla .znacka-ok { background: var(--seda-svetla); color: var(--bila); }
        .znacka-ne { background: #333; color: #666; }

        /* === KONTAKTNÍ FORMULÁŘ === */
        .formular-mrizka {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .pole-skupina {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .pole-skupina.cele {
            grid-column: 1 / -1;
        }

        .pole-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--seda-svetla);
            letter-spacing: 0.04em;
        }

        .pole-vstup, .pole-textarea, .pole-select {
            background: #111;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 12px 14px;
            font-size: 0.9rem;
            color: var(--bila);
            font-family: inherit;
            width: 100%;
            transition: border-color 0.2s;
        }

        .pole-vstup:focus,
        .pole-textarea:focus,
        .pole-select:focus {
            outline: none;
            border-color: var(--seda-svetla);
        }

        .pole-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .pole-select option { background: #111; }

        .formular-poznamka {
            font-size: 0.78rem;
            color: var(--seda);
            margin-top: 6px;
        }

        .btn-odeslat {
            padding: 14px 36px;
            background: var(--bila);
            color: var(--cerna);
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.02em;
            transition: background 0.2s;
            margin-top: 8px;
        }

        .btn-odeslat:hover { background: var(--svetla); }

        /* === FOOTER === */
        .footer {
            background: var(--temer-cerna);
            border-top: 1px solid #111;
            padding: 48px 24px;
        }

        .footer-vnitrni {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-logo {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--bila);
            letter-spacing: 0.08em;
        }

        .footer-prava {
            font-size: 0.8rem;
            color: var(--seda);
        }

        .footer-odkaz {
            color: var(--seda);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.2s;
        }

        .footer-odkaz:hover { color: var(--seda-svetla); }

        /* === ZPRÁVA PO ODESLÁNÍ === */
        .zprava-odeslano {
            background: #111;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 24px 28px;
            color: var(--bila);
            display: none;
        }

        .zprava-chyba-form {
            background: #1a0000;
            border: 1px solid #440000;
            border-radius: 4px;
            padding: 16px 20px;
            color: #ffaaaa;
            display: none;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }

        /* === SCROLL ANIMACE === */
        .fade-in {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .fade-in.viditelny {
            opacity: 1;
            transform: translateY(0);
        }

        /* === RESPONZIVITA === */
        @media (max-width: 768px) {
            .hero { padding: 100px 20px 60px; }
            .hero-statistiky { gap: 28px; margin-top: 48px; padding-top: 32px; }
            .sekce { padding: 64px 20px; }
            .formular-mrizka { grid-template-columns: 1fr; }
            .srovnani-mrizka { grid-template-columns: 1fr; }
            .nav-odkaz { display: none; }
            .hero-akce { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- NAVIGACE -->
<nav class="nav" role="navigation" aria-label="Hlavní navigace">
    <div class="nav-vnitrni">
        <a href="/" class="nav-logo">WGS <span>WHITE LABEL</span></a>
        <div class="nav-akce">
            <a href="#vlastnosti" class="nav-odkaz">Funkce</a>
            <a href="#jak-to-funguje" class="nav-odkaz">Jak to funguje</a>
            <a href="#kontakt" class="nav-btn">Nezávazná poptávka</a>
        </div>
    </div>
</nav>

<!-- HERO SEKCE -->
<section class="hero" id="uvod">
    <div class="hero-obsah">
        <div class="hero-stitek">White Label platforma</div>

        <h1 class="hero-nadpis">
            Váš servisní systém.<br>
            <em>Váš brand.</em>
        </h1>

        <p class="hero-popis">
            Kompletní, odladěná platforma pro správu servisních zakázek —
            nasazená pod vaším logem, vaší doménou, pro vaše zákazníky.
            Bez nutnosti vývoje. Připravená ke spuštění.
        </p>

        <div class="hero-akce">
            <a href="#kontakt" class="btn-hlavni">Mám zájem — kontaktujte mě</a>
            <a href="#vlastnosti" class="btn-vedlejsi">Prohlédnout funkce</a>
        </div>

        <div class="hero-statistiky">
            <div class="stat">
                <span class="stat-cislo">100+</span>
                <span class="stat-popis">Funkcí v systému</span>
            </div>
            <div class="stat">
                <span class="stat-cislo">Multi</span>
                <span class="stat-popis">Tenant architektura</span>
            </div>
            <div class="stat">
                <span class="stat-cislo">PWA</span>
                <span class="stat-popis">Mobilní aplikace</span>
            </div>
            <div class="stat">
                <span class="stat-cislo">GDPR</span>
                <span class="stat-popis">Plná shoda</span>
            </div>
        </div>
    </div>
</section>

<!-- CO JE WHITE LABEL WGS -->
<section class="sekce sekce-seda fade-in" id="co-je">
    <div class="obal">
        <div class="sekce-stitek">Co je White Label WGS</div>
        <h2 class="sekce-nadpis">Systém, který jste vždy chtěli vlastnit</h2>
        <p class="sekce-podnadpis">
            WGS je produkční platforma pro správu servisních zakázek, používaná reálnými servisními firmami.
            White label verze vám dává celý systém — se svým logem, barvami a doménou — bez nutnosti cokoliv vyvíjet.
        </p>

        <div class="srovnani-mrizka fade-in">
            <div class="srovnani-sloupec tmava">
                <div class="srovnani-nadpis" style="color: #fff;">WGS White Label</div>
                <ul class="srovnani-seznam">
                    <li class="srovnani-polozka">
                        <span class="znacka-ok">+</span>
                        Nasazení do 48 hodin
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ok">+</span>
                        Váš brand — logo, barvy, doména
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ok">+</span>
                        Kompletní funkce od prvního dne
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ok">+</span>
                        Průběžné aktualizace v ceně
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ok">+</span>
                        Technická podpora v češtině
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ok">+</span>
                        Předvídatelný měsíční poplatek
                    </li>
                </ul>
            </div>
            <div class="srovnani-sloupec svetla">
                <div class="srovnani-nadpis">Vývoj vlastního systému</div>
                <ul class="srovnani-seznam">
                    <li class="srovnani-polozka">
                        <span class="znacka-ne">-</span>
                        6–18 měsíců vývoje
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ne">-</span>
                        Vysoké jednorázové náklady
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ne">-</span>
                        Závislost na vývojářích
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ne">-</span>
                        Aktualizace a bezpečnost na vaší straně
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ne">-</span>
                        Ladění chyb odčerpává čas
                    </li>
                    <li class="srovnani-polozka">
                        <span class="znacka-ne">-</span>
                        Nepředvídatelné náklady
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- VLASTNOSTI -->
<section class="sekce sekce-tmava fade-in" id="vlastnosti">
    <div class="obal">
        <div class="sekce-stitek">Co dostanete</div>
        <h2 class="sekce-nadpis" style="color: var(--bila);">Kompletní platforma, připravená ke spuštění</h2>
        <p class="sekce-podnadpis">
            Každá funkce je produkčně otestovaná. Nic nedodáváme jako beta — vše funguje od prvního dne.
        </p>

        <div class="vlastnosti-mrizka fade-in">

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><path d="M9 12h6m-3-3v6M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0z"/></svg>
                </div>
                <div class="vlastnost-nadpis">Správa zakázek</div>
                <div class="vlastnost-popis">Kompletní životní cyklus servisní zakázky — od přijetí přes technické práce až po uzavření a fakturaci.</div>
            </div>

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </div>
                <div class="vlastnost-nadpis">Kalendář a plánování</div>
                <div class="vlastnost-popis">Interaktivní kalendář pro plánování termínů techniků. Přehled obsazenosti, přesuny drag&drop, denní/týdenní pohled.</div>
            </div>

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="vlastnost-nadpis">Správa techniků</div>
                <div class="vlastnost-popis">Hierarchie rolí — admin, technik, prodejce. Každý vidí jen to, co potřebuje. Přiřazování zakázek, sledování výkonu.</div>
            </div>

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div class="vlastnost-nadpis">E-mailové notifikace</div>
                <div class="vlastnost-popis">Automatické emaily zákazníkům i technikům. Šablony, fronta, opakované pokusy. SMTP přes vlastní server nebo přes naši infrastrukturu.</div>
            </div>

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="vlastnost-nadpis">Reporty a statistiky</div>
                <div class="vlastnost-popis">Přehledný dashboard s grafy, filtrováním a exportem. Počty zakázek, výkon techniků, průměrná doba řešení, geografické rozložení.</div>
            </div>

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div class="vlastnost-nadpis">Bezpečnost a GDPR</div>
                <div class="vlastnost-popis">CSRF ochrana, rate limiting, auditní log, šifrování hesel, HTTPS. Plná shoda s GDPR — automatické mazání starých dat, export uživatelských dat.</div>
            </div>

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18.01"/></svg>
                </div>
                <div class="vlastnost-nadpis">Mobilní aplikace (PWA)</div>
                <div class="vlastnost-popis">Instalovatelná webová aplikace pro Android a iOS. Technici pracují v terénu bez nutnosti stahovat z App Store. Offline schopnosti.</div>
            </div>

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div class="vlastnost-nadpis">Mapové zobrazení</div>
                <div class="vlastnost-popis">Geolokace zakázek na mapě, výpočet vzdálenosti pro přiřazení technika, vizualizace servisních oblastí.</div>
            </div>

            <div class="vlastnost">
                <div class="vlastnost-ikona">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <div class="vlastnost-nadpis">Cenové nabídky a PDF</div>
                <div class="vlastnost-popis">Generování cenových nabídek přímo v systému, přehled schválení zákazníkem, automatické PDF protokoly ze servisních zásahů.</div>
            </div>
        </div>
    </div>
</section>

<!-- JAK TO FUNGUJE -->
<section class="sekce fade-in" id="jak-to-funguje">
    <div class="obal">
        <div class="sekce-stitek">Proces nasazení</div>
        <h2 class="sekce-nadpis">Od poptávky ke spuštění za 48 hodin</h2>
        <p class="sekce-podnadpis">
            Bez složitého nastavení. Postaráme se o technickou část — vy se soustředíte na svůj byznys.
        </p>

        <div class="kroky fade-in">
            <div class="krok">
                <div class="krok-cislo">01</div>
                <div class="krok-nadpis">Odešlete poptávku</div>
                <div class="krok-popis">Vyplňte kontaktní formulář níže. Do 24 hodin vás kontaktujeme, probereme vaše potřeby a připravíme nabídku.</div>
            </div>
            <div class="krok">
                <div class="krok-cislo">02</div>
                <div class="krok-nadpis">Připravíme váš tenant</div>
                <div class="krok-popis">Nastavíme váš vlastní prostor v systému — logo, název firmy, doménu. Vytvoříme administrátorský účet a provedeme vás systémem.</div>
            </div>
            <div class="krok">
                <div class="krok-cislo">03</div>
                <div class="krok-nadpis">Spustíte pod svým brandem</div>
                <div class="krok-popis">Vaši technici a zákazníci pracují s plnohodnotným systémem pod vaším jménem. Průběžná podpora a aktualizace jsou součástí.</div>
            </div>
        </div>
    </div>
</section>

<!-- PRO KOHO -->
<section class="sekce sekce-seda fade-in" id="pro-koho">
    <div class="obal">
        <div class="sekce-stitek">Pro koho je WGS White Label</div>
        <h2 class="sekce-nadpis">Servisní firmy všech velikostí</h2>
        <p class="sekce-podnadpis">
            Systém je navržen pro firmy, které potřebují organizovat servisní techniky, evidovat zakázky a komunikovat se zákazníky.
        </p>

        <div class="segmenty fade-in">
            <div class="segment">
                <div class="segment-nadpis">Autorizované servisy</div>
                <div class="segment-popis">Servisy výrobců spotřebičů, nábytku, elektroniky. Správa záručních i pozáručních oprav, evidence náhradních dílů.</div>
            </div>
            <div class="segment">
                <div class="segment-nadpis">Importéři a dealerské sítě</div>
                <div class="segment-popis">Koordinace servisní sítě na národní úrovni. Každý dealer jako samostatný tenant, centrální přehled pro importéra.</div>
            </div>
            <div class="segment">
                <div class="segment-nadpis">Facility management</div>
                <div class="segment-popis">Správa servisních výjezdů pro klienty. Evidence smluv, SLA sledování, pravidelná údržba i jednorázové opravy.</div>
            </div>
            <div class="segment">
                <div class="segment-nadpis">Výrobci s vlastní sítí</div>
                <div class="segment-popis">Integrovaný systém pro servisní zákaznické centrum. Napojení na CRM, sledování reklamací a spokojenosti zákazníků.</div>
            </div>
        </div>
    </div>
</section>

<!-- KONTAKTNÍ FORMULÁŘ -->
<section class="sekce sekce-tmava fade-in" id="kontakt">
    <div class="obal-uzky">
        <div class="sekce-stitek">Nezávazná poptávka</div>
        <h2 class="sekce-nadpis" style="color: var(--bila);">Pojďme to probrat</h2>
        <p class="sekce-podnadpis">
            Napište nám — odpovíme do 24 hodin v pracovní dny.
        </p>

        <div id="zpravaOdeslano" class="zprava-odeslano">
            <strong style="font-size:1rem;">Poptávka odeslána.</strong><br>
            <span style="color:var(--seda-svetla);font-size:0.9rem;margin-top:6px;display:block;">Ozveme se vám do 24 hodin v pracovní dny.</span>
        </div>

        <div id="zpravaChyba" class="zprava-chyba-form"></div>

        <form id="poptavkaFormular" novalidate>
            <div class="formular-mrizka">
                <div class="pole-skupina">
                    <label class="pole-label" for="jmeno">Jméno a příjmení *</label>
                    <input type="text" id="jmeno" name="jmeno" class="pole-vstup" required autocomplete="name">
                </div>
                <div class="pole-skupina">
                    <label class="pole-label" for="firma">Název firmy *</label>
                    <input type="text" id="firma" name="firma" class="pole-vstup" required autocomplete="organization">
                </div>
                <div class="pole-skupina">
                    <label class="pole-label" for="email">E-mail *</label>
                    <input type="email" id="email" name="email" class="pole-vstup" required autocomplete="email">
                </div>
                <div class="pole-skupina">
                    <label class="pole-label" for="telefon">Telefon</label>
                    <input type="tel" id="telefon" name="telefon" class="pole-vstup" autocomplete="tel">
                </div>
                <div class="pole-skupina">
                    <label class="pole-label" for="pocetTechniku">Počet techniků</label>
                    <select id="pocetTechniku" name="pocet_techniku" class="pole-select pole-vstup">
                        <option value="">Vyberte...</option>
                        <option value="1-5">1–5</option>
                        <option value="6-20">6–20</option>
                        <option value="21-50">21–50</option>
                        <option value="50+">Více než 50</option>
                    </select>
                </div>
                <div class="pole-skupina">
                    <label class="pole-label" for="segment">Typ firmy</label>
                    <select id="segment" name="segment" class="pole-select pole-vstup">
                        <option value="">Vyberte...</option>
                        <option value="autorizovany-servis">Autorizovaný servis</option>
                        <option value="dealer">Importér / dealer</option>
                        <option value="facility">Facility management</option>
                        <option value="vyrobce">Výrobce</option>
                        <option value="jine">Jiné</option>
                    </select>
                </div>
                <div class="pole-skupina cele">
                    <label class="pole-label" for="zprava">Zpráva</label>
                    <textarea id="zprava" name="zprava" class="pole-textarea" placeholder="Popište svůj záměr, případné specifické požadavky nebo otázky..."></textarea>
                </div>
            </div>

            <p class="formular-poznamka" style="margin-top: 16px; margin-bottom: 16px;">
                Odesláním formuláře souhlasíte se zpracováním osobních údajů za účelem odpovědi na poptávku.
                Neposkytujeme data třetím stranám.
            </p>

            <button type="submit" class="btn-odeslat" id="tlacitkoOdeslat">Odeslat poptávku</button>
        </form>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    <div class="footer-vnitrni">
        <div class="footer-logo">WGS SERVICE</div>
        <div class="footer-prava">© <?= date('Y') ?> WGS Service. Všechna práva vyhrazena.</div>
        <div style="display:flex;gap:20px;">
            <a href="https://www.wgs-service.cz" class="footer-odkaz">Hlavní web</a>
            <a href="mailto:radek@wgs-service.cz" class="footer-odkaz">radek@wgs-service.cz</a>
        </div>
    </div>
</footer>

<script>
// === SCROLL ANIMACE ===
const fadeElemety = document.querySelectorAll('.fade-in');
const pozorovatel = new IntersectionObserver((zaznamy) => {
    zaznamy.forEach(zaznam => {
        if (zaznam.isIntersecting) {
            zaznam.target.classList.add('viditelny');
            pozorovatel.unobserve(zaznam.target);
        }
    });
}, { threshold: 0.1 });

fadeElemety.forEach(el => pozorovatel.observe(el));

// === PLYNULÝ SCROLL PRO KOTVY ===
document.querySelectorAll('a[href^="#"]').forEach(odkaz => {
    odkaz.addEventListener('click', function(e) {
        const cil = document.querySelector(this.getAttribute('href'));
        if (!cil) return;
        e.preventDefault();
        const offset = 72;
        const pozice = cil.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: pozice, behavior: 'smooth' });
    });
});

// === KONTAKTNÍ FORMULÁŘ ===
const poptavkaFormular  = document.getElementById('poptavkaFormular');
const zpravaOdeslano    = document.getElementById('zpravaOdeslano');
const zpravaChyba       = document.getElementById('zpravaChyba');
const tlacitkoOdeslat   = document.getElementById('tlacitkoOdeslat');

poptavkaFormular.addEventListener('submit', async function(e) {
    e.preventDefault();

    const jmeno = document.getElementById('jmeno').value.trim();
    const firma = document.getElementById('firma').value.trim();
    const email = document.getElementById('email').value.trim();

    if (!jmeno || !firma || !email) {
        zobrazChybu('Vyplňte prosím povinná pole: Jméno, Firma a E-mail.');
        return;
    }

    if (!email.includes('@') || !email.includes('.')) {
        zobrazChybu('Zadejte platnou e-mailovou adresu.');
        return;
    }

    tlacitkoOdeslat.disabled = true;
    tlacitkoOdeslat.textContent = 'Odesílám...';
    zpravaChyba.style.display = 'none';

    const formData = new FormData(poptavkaFormular);

    try {
        const odpoved = await fetch('/api/wl_poptavka.php', {
            method: 'POST',
            body: formData
        });

        const data = await odpoved.json();

        if (data.status === 'success') {
            poptavkaFormular.style.display = 'none';
            zpravaOdeslano.style.display = 'block';
        } else {
            zobrazChybu(data.message || 'Nepodařilo se odeslat poptávku. Zkuste to prosím znovu.');
            tlacitkoOdeslat.disabled = false;
            tlacitkoOdeslat.textContent = 'Odeslat poptávku';
        }
    } catch (chyba) {
        zobrazChybu('Chyba sítě. Zkuste to prosím znovu nebo nás kontaktujte přímo na radek@wgs-service.cz');
        tlacitkoOdeslat.disabled = false;
        tlacitkoOdeslat.textContent = 'Odeslat poptávku';
    }
});

function zobrazChybu(text) {
    zpravaChyba.textContent = text;
    zpravaChyba.style.display = 'block';
    zpravaChyba.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

</body>
</html>
