<?php
/**
 * TRANSPORT - Techmission Festival / United Music Events
 * Přehled transportů pro řidiče
 * Dočasná stránka - bude odstraněna po akci
 */
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Techmission</title>
    <!-- Favicon pro desktop -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon_tech_32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon_tech_32.png">
    <link rel="shortcut icon" href="/assets/img/favicon_tech_32.png">
    <!-- iOS -->
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/favicon_tech_180.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Techmission">
    <!-- Android -->
    <link rel="manifest" href="/manifest-transport.json">
    <meta name="theme-color" content="#000000">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #000;
            color: #fff;
            min-height: 100vh;
            padding: 15px;
        }

        .header {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #333;
            margin-bottom: 20px;
        }

        .logo-wgs {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 3px;
            color: #fff;
        }

        /* Language switcher */
        .lang-switcher {
            z-index: 100;
        }

        .lang-current {
            background: transparent;
            border: none;
            color: #fff;
            padding: 6px 8px;
            font-size: 16px;
            font-weight: 300;
            cursor: pointer;
            display: flex;
            align-items: center;
            letter-spacing: 0.5px;
        }

        .lang-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: #111;
            border-radius: 4px;
            margin-top: 4px;
            overflow: hidden;
            min-width: 100%;
        }

        .lang-switcher.open .lang-dropdown {
            display: block;
        }

        .lang-option {
            padding: 10px 14px;
            font-size: 15px;
            font-weight: 300;
            cursor: pointer;
            transition: background 0.2s;
            white-space: nowrap;
            color: #fff;
        }

        .lang-option:hover {
            background: #333;
        }

        .lang-option.active {
            background: #222;
        }

        /* Řidiči */
        .ridici {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .ridic {
            display: grid;
            justify-items: center;
            text-align: center;
            gap: 8px;
        }

        .ridic-info {
            text-align: center;
        }

        .ridic-jmeno {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .ridic-auto {
            font-size: 11px;
            color: #888;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .ridic-standby {
            font-size: 9px;
            color: #666;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .ridic-auto-svg {
            width: 28px;
            height: 28px;
            fill: #888;
        }

        .ridic-auto-icon {
            font-size: 32px;
            color: #888;
        }

        .ridic-tel {
            width: 36px;
            height: 36px;
            background: #222;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            color: #fff;
            font-size: 16px;
        }

        .ridic-tel:hover {
            background: #444;
        }

        .ridic-pridat-btn {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .ridic-pridat-btn:hover {
            color: #fff;
        }

        .ridic-pridat .ridic-info {
            opacity: 0.6;
        }

        /* Kalendář */
        .kalendar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 1rem;
            z-index: 2000;
        }

        .kalendar-overlay.aktivni {
            display: flex;
        }

        .kalendar-box {
            background: #111;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 20px;
            max-width: 380px;
            width: 100%;
        }

        .kalendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #333;
        }

        .kalendar-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
        }

        .kalendar-nav {
            display: flex;
            gap: 8px;
        }

        .kalendar-nav button {
            background: #333;
            color: #fff;
            border: none;
            padding: 8px 14px;
            cursor: pointer;
            font-size: 16px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .kalendar-nav button:hover {
            background: #444;
        }

        .kalendar-mesic {
            text-align: center;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 14px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .kalendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
        }

        .kalendar-den-tyden {
            font-weight: 600;
            text-align: center;
            padding: 8px 4px;
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
        }

        .kalendar-den {
            text-align: center;
            padding: 10px 4px;
            cursor: pointer;
            border: 1px solid #222;
            border-radius: 4px;
            transition: all 0.2s;
            color: #fff;
            font-size: 14px;
        }

        .kalendar-den:hover:not(.disabled) {
            background: #333;
            border-color: #555;
        }

        .kalendar-den.disabled {
            opacity: 0;
            cursor: default;
            pointer-events: none;
        }

        .kalendar-den.vybrany {
            background: #fff;
            color: #000;
            font-weight: 700;
        }

        .kalendar-den.dnes {
            border-color: #666;
        }

        .kalendar-zavrit {
            display: block;
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            background: #222;
            color: #888;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .kalendar-zavrit:hover {
            background: #333;
            color: #fff;
        }

        /* Datum input wrapper */
        .datum-input-wrapper {
            position: relative;
            cursor: pointer;
        }

        .datum-input-wrapper input {
            cursor: pointer;
        }

        /* Sekce dne */
        .den {
            margin-bottom: 25px;
        }

        .den-header {
            font-size: 14px;
            font-weight: 600;
            color: #888;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .den-sobota .den-header {
            color: #aaa;
        }

        .den-nedele .den-header {
            color: #666;
        }

        .btn-pridat {
            background: #222;
            border: 1px solid #444;
            color: #888;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-pridat:hover {
            background: #333;
            color: #fff;
        }

        /* Transport řádek */
        .transport {
            background: #111;
            border: 1px solid #222;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            cursor: pointer;
            transition: background 0.2s;
        }

        .transport:hover {
            background: #1a1a1a;
        }

        .den-nedele .transport {
            background: #0a0a0a;
            border-color: #1a1a1a;
        }

        .transport-cas {
            font-size: 24px;
            font-weight: 700;
            min-width: 70px;
            font-variant-numeric: tabular-nums;
        }

        .transport-info {
            flex: 1;
        }

        .transport-jmena {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .transport-trasa {
            font-size: 12px;
            color: #666;
        }

        /* Tlačítko smazat - pravý dolní roh */
        .btn-smazat {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: transparent;
            border: none;
            color: #ff4444;
            font-size: 20px;
            cursor: pointer;
            opacity: 1;
            transition: all 0.2s;
            z-index: 10;
        }

        .btn-smazat:hover {
            color: #ff6666;
        }

        /* Tlačítko upravit - levý dolní roh */
        .btn-upravit {
            position: absolute;
            bottom: 8px;
            left: 8px;
            background: transparent;
            border: none;
            color: #888;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            transition: all 0.2s;
            z-index: 10;
        }

        .btn-upravit:hover {
            color: #fff;
        }

        /* Stav */
        .transport-stav {
            min-width: 110px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .stav-btn {
            padding: 10px 18px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
        }

        .stav-wait {
            background: #333;
            color: #fff;
        }

        .stav-wait:hover {
            background: #444;
        }

        .stav-onway {
            background: #fff;
            color: #000;
        }

        .stav-drop {
            background: #111;
            color: #39ff14;
            cursor: pointer;
            border: 1px solid #39ff14;
            box-shadow: 0 0 10px rgba(57, 255, 20, 0.4), 0 0 20px rgba(57, 255, 20, 0.2);
            animation: drop-pulse 2s ease-in-out infinite;
        }

        @keyframes drop-pulse {
            0%, 100% { box-shadow: 0 0 10px rgba(57, 255, 20, 0.4), 0 0 20px rgba(57, 255, 20, 0.2); }
            50% { box-shadow: 0 0 15px rgba(57, 255, 20, 0.6), 0 0 30px rgba(57, 255, 20, 0.3); }
        }

        .stav-cas {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-height: 14px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.aktivni {
            display: flex;
        }

        .modal-obsah {
            background: #111;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 25px;
            width: 90%;
            max-width: 400px;
        }

        .modal-titulek {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .modal-pole {
            margin-bottom: 12px;
        }

        .modal-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .modal-input {
            width: 100%;
            background: #222;
            border: 1px solid #333;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 0;
        }

        .modal-pole .modal-input {
            margin-bottom: 0;
        }

        .modal-input:focus {
            outline: none;
            border-color: #555;
        }

        .modal-btns {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .modal-btn-zrusit {
            background: #222;
            color: #888;
        }

        .modal-btn-zrusit:hover {
            background: #333;
        }

        .modal-btn-potvrdit {
            background: #fff;
            color: #000;
        }

        .modal-btn-potvrdit:hover {
            background: #ddd;
        }

        .modal-btn-smazat {
            background: #ff4444;
            color: #fff;
        }

        .modal-btn-smazat:hover {
            background: #cc3333;
        }

        .modal-chyba {
            color: #ff4444;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        @media (max-width: 500px) {
            .ridici {
                gap: 20px;
                max-width: 100%;
            }

            .transport {
                flex-wrap: wrap;
            }

            .transport-cas {
                font-size: 20px;
            }

            .transport-stav {
                width: 100%;
                margin-top: 10px;
            }

            .btn-smazat {
                opacity: 1;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="logo-wgs">WGS</div>

    <!-- Language switcher -->
    <div class="lang-switcher" id="lang-switcher" onclick="toggleLangMenu(event)">
        <div class="lang-current" id="lang-current">CZ</div>
        <div class="lang-dropdown">
            <div class="lang-option active" data-lang="cz">CZ</div>
            <div class="lang-option" data-lang="sk">SK</div>
            <div class="lang-option" data-lang="en">EN</div>
            <div class="lang-option" data-lang="de">DE</div>
            <div class="lang-option" data-lang="nl">NL</div>
        </div>
    </div>
</div>

<!-- Řidiči - dynamicky generováno -->
<div class="ridici" id="ridici-kontejner">
    <!-- Ridici se vykresli JavaScriptem -->
</div>

<!-- Modal pro editaci řidiče -->
<div class="modal" id="modal-ridic">
    <div class="modal-obsah">
        <div class="modal-titulek" id="modal-ridic-titulek">Upravit ridice</div>
        <input type="hidden" id="ridic-edit-id">
        <input type="text" class="modal-input" id="ridic-jmeno" placeholder="Jmeno (napr. MILAN)">
        <input type="text" class="modal-input" id="ridic-auto" placeholder="Auto (napr. MB V CLASS)">
        <input type="text" class="modal-input" id="ridic-poznamka" placeholder="Poznamka (napr. transport van)">
        <input type="tel" class="modal-input" id="ridic-telefon" placeholder="Telefon (napr. +420735084519)">
        <input type="tel" class="modal-input" id="ridic-heslo" placeholder="Heslo pro potvrzeni" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        <div class="modal-chyba" id="modal-ridic-chyba">Spatne heslo</div>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-zrusit" onclick="zavriModalRidic()">Zrusit</button>
            <button class="modal-btn modal-btn-potvrdit" onclick="ulozRidice()">Ulozit</button>
        </div>
    </div>
</div>

<!-- Dynamické sekce dnů - generováno JavaScriptem -->
<div id="dny-kontejner"></div>

<!-- Tlačítko pro přidání nového dne/transportu -->
<div style="text-align: center; margin: 20px 0;">
    <button class="btn-pridat" onclick="otevriModalPridat(null)" style="padding: 10px 20px;">+ Pridat transport</button>
</div>


<!-- Kalendář overlay -->
<div class="kalendar-overlay" id="kalendar-overlay">
    <div class="kalendar-box">
        <div class="kalendar-header">
            <h3>Vyberte datum</h3>
            <div class="kalendar-nav">
                <button id="kalendar-prev">&larr;</button>
                <button id="kalendar-next">&rarr;</button>
            </div>
        </div>
        <div class="kalendar-mesic" id="kalendar-mesic"></div>
        <div class="kalendar-grid" id="kalendar-grid"></div>
        <button class="kalendar-zavrit" onclick="zavriKalendar()">Zavrit</button>
    </div>
</div>

<!-- Modal pro přidání/editaci -->
<div class="modal" id="modal-edit">
    <div class="modal-obsah">
        <div class="modal-titulek" id="modal-titulek" data-i18n="addTransport">Pridat transport</div>

        <div class="modal-pole" id="datum-wrapper" style="display: none;">
            <label class="modal-label">Datum</label>
            <div class="datum-input-wrapper" onclick="otevriKalendar()">
                <input type="text" class="modal-input" id="input-datum" placeholder="Kliknete pro vyber" readonly>
            </div>
        </div>

        <div class="modal-pole" id="pole-cas">
            <label class="modal-label">Cas</label>
            <input type="text" class="modal-input" id="input-cas" placeholder="napr. 21:30">
        </div>

        <div class="modal-pole" id="pole-jmeno">
            <label class="modal-label">Jmeno</label>
            <input type="text" class="modal-input" id="input-jmeno" placeholder="Jmeno pasazera">
        </div>

        <div class="modal-pole" id="pole-odkud">
            <label class="modal-label">Odkud</label>
            <input type="text" class="modal-input" id="input-odkud" placeholder="Misto vyzvednut">
        </div>

        <div class="modal-pole" id="pole-kam">
            <label class="modal-label">Kam</label>
            <input type="text" class="modal-input" id="input-kam" placeholder="Cilove misto">
        </div>

        <div class="modal-pole">
            <label class="modal-label">Heslo</label>
            <input type="tel" class="modal-input" id="input-heslo" placeholder="Heslo pro potvrzeni" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        </div>

        <div class="modal-chyba" id="modal-chyba" data-i18n="wrongPassword">Spatne heslo</div>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-zrusit" onclick="zavriModal()" data-i18n="cancel">Zrusit</button>
            <button class="modal-btn modal-btn-potvrdit" onclick="potvrdEdit()" data-i18n="confirm">Potvrdit</button>
        </div>
    </div>
</div>

<!-- Modal pro smazání -->
<div class="modal" id="modal-smazat">
    <div class="modal-obsah">
        <div class="modal-titulek" data-i18n="deleteTransport">Smazat transport?</div>
        <p style="color: #888; margin-bottom: 15px;" id="smazat-info"></p>
        <input type="tel" class="modal-input" id="input-heslo-smazat" data-i18n-placeholder="passwordPlaceholder" placeholder="Heslo pro potvrzeni" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        <div class="modal-chyba" id="modal-chyba-smazat" data-i18n="wrongPassword">Spatne heslo</div>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-zrusit" onclick="zavriModalSmazat()" data-i18n="cancel">Zrusit</button>
            <button class="modal-btn modal-btn-smazat" onclick="potvrdSmazat()" data-i18n="delete">Smazat</button>
        </div>
    </div>
</div>

<!-- Modal pro reset stavu -->
<div class="modal" id="modal-reset">
    <div class="modal-obsah">
        <div class="modal-titulek" data-i18n="resetStatus">Resetovat stav na WAIT?</div>
        <p style="color: #888; margin-bottom: 15px;" id="reset-info"></p>
        <input type="tel" class="modal-input" id="input-heslo-reset" data-i18n-placeholder="passwordPlaceholder" placeholder="Heslo pro potvrzeni" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        <div class="modal-chyba" id="modal-chyba-reset" data-i18n="wrongPassword">Spatne heslo</div>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-zrusit" onclick="zavriModalReset()" data-i18n="cancel">Zrusit</button>
            <button class="modal-btn modal-btn-potvrdit" onclick="potvrdReset()" data-i18n="reset">Resetovat</button>
        </div>
    </div>
</div>

<script>
// Heslo pro editaci
const HESLO = '9545';

// Aktuální jazyk - výchozí angličtina
let currentLang = localStorage.getItem('techLang') || 'en';

// Překlady
const translations = {
    cz: {
        saturday: 'Sobota 13.12.',
        sunday: 'Nedele 14.12.',
        add: '+ Pridat',
        addTransport: 'Pridat transport',
        editTransport: 'Upravit transport',
        deleteTransport: 'Smazat transport?',
        resetStatus: 'Resetovat stav na WAIT?',
        timePlaceholder: 'Cas (napr. 21:30)',
        namePlaceholder: 'Jmeno pasazera',
        fromPlaceholder: 'Odkud',
        toPlaceholder: 'Kam',
        passwordPlaceholder: 'Heslo pro potvrzeni',
        wrongPassword: 'Spatne heslo',
        cancel: 'Zrusit',
        confirm: 'Potvrdit',
        delete: 'Smazat',
        reset: 'Resetovat',
        wait: 'WAIT',
        onway: 'ON THE WAY',
        drop: 'DROP OFF',
        departed: 'vyjeli',
        delivered: 'doruceno',
        footerText: 'Aplikaci vytvořil a transport servis zajišťuje',
        transportVan: 'transport van',
        standby: 'STAND BY 21:00 - 06:00'
    },
    sk: {
        saturday: 'Sobota 13.12.',
        sunday: 'Nedela 14.12.',
        add: '+ Pridat',
        addTransport: 'Pridat transport',
        editTransport: 'Upravit transport',
        deleteTransport: 'Zmazat transport?',
        resetStatus: 'Resetovat stav na WAIT?',
        timePlaceholder: 'Cas (napr. 21:30)',
        namePlaceholder: 'Meno pasaziera',
        fromPlaceholder: 'Odkial',
        toPlaceholder: 'Kam',
        passwordPlaceholder: 'Heslo pre potvrdenie',
        wrongPassword: 'Nespravne heslo',
        cancel: 'Zrusit',
        confirm: 'Potvrdit',
        delete: 'Zmazat',
        reset: 'Resetovat',
        wait: 'WAIT',
        onway: 'ON THE WAY',
        drop: 'DROP OFF',
        departed: 'odisli',
        delivered: 'dorucene',
        footerText: 'Aplikaciu vytvoril a transport servis zabezpecuje',
        transportVan: 'transport van',
        standby: 'STAND BY 21:00 - 06:00'
    },
    en: {
        saturday: 'Saturday 13.12.',
        sunday: 'Sunday 14.12.',
        add: '+ Add',
        addTransport: 'Add transport',
        editTransport: 'Edit transport',
        deleteTransport: 'Delete transport?',
        resetStatus: 'Reset status to WAIT?',
        timePlaceholder: 'Time (e.g. 21:30)',
        namePlaceholder: 'Passenger name',
        fromPlaceholder: 'From',
        toPlaceholder: 'To',
        passwordPlaceholder: 'Password to confirm',
        wrongPassword: 'Wrong password',
        cancel: 'Cancel',
        confirm: 'Confirm',
        delete: 'Delete',
        reset: 'Reset',
        wait: 'WAIT',
        onway: 'ON THE WAY',
        drop: 'DROP OFF',
        departed: 'departed',
        delivered: 'delivered',
        footerText: 'App created and transport service provided by',
        transportVan: 'transport van',
        standby: 'STAND BY 21:00 - 06:00'
    },
    de: {
        saturday: 'Samstag 13.12.',
        sunday: 'Sonntag 14.12.',
        add: '+ Hinzufugen',
        addTransport: 'Transport hinzufugen',
        editTransport: 'Transport bearbeiten',
        deleteTransport: 'Transport loschen?',
        resetStatus: 'Status auf WAIT zurucksetzen?',
        timePlaceholder: 'Zeit (z.B. 21:30)',
        namePlaceholder: 'Passagiername',
        fromPlaceholder: 'Von',
        toPlaceholder: 'Nach',
        passwordPlaceholder: 'Passwort zur Bestatigung',
        wrongPassword: 'Falsches Passwort',
        cancel: 'Abbrechen',
        confirm: 'Bestatigen',
        delete: 'Loschen',
        reset: 'Zurucksetzen',
        wait: 'WARTEN',
        onway: 'UNTERWEGS',
        drop: 'ABGESETZT',
        departed: 'abgefahren',
        delivered: 'zugestellt',
        footerText: 'App erstellt und Transportservice bereitgestellt von',
        transportVan: 'transport van',
        standby: 'BEREITSCHAFT 21:00 - 06:00'
    },
    nl: {
        saturday: 'Zaterdag 13.12.',
        sunday: 'Zondag 14.12.',
        add: '+ Toevoegen',
        addTransport: 'Transport toevoegen',
        editTransport: 'Transport bewerken',
        deleteTransport: 'Transport verwijderen?',
        resetStatus: 'Status resetten naar WAIT?',
        timePlaceholder: 'Tijd (bijv. 21:30)',
        namePlaceholder: 'Naam passagier',
        fromPlaceholder: 'Van',
        toPlaceholder: 'Naar',
        passwordPlaceholder: 'Wachtwoord ter bevestiging',
        wrongPassword: 'Verkeerd wachtwoord',
        cancel: 'Annuleren',
        confirm: 'Bevestigen',
        delete: 'Verwijderen',
        reset: 'Resetten',
        wait: 'WACHT',
        onway: 'ONDERWEG',
        drop: 'AFGELEVERD',
        departed: 'vertrokken',
        delivered: 'afgeleverd',
        footerText: 'App gemaakt en transportservice verzorgd door',
        transportVan: 'transport van',
        standby: 'STAND-BY 21:00 - 06:00'
    }
};

// Přepnout jazyk
function toggleLangMenu(event) {
    event.stopPropagation();
    const switcher = document.getElementById('lang-switcher');
    switcher.classList.toggle('open');
}

// Zavřít menu při kliknutí mimo
document.addEventListener('click', () => {
    document.getElementById('lang-switcher').classList.remove('open');
});

// Vybrat jazyk
document.querySelectorAll('.lang-option').forEach(option => {
    option.addEventListener('click', (e) => {
        e.stopPropagation();
        const lang = option.dataset.lang;
        setLanguage(lang);
        document.getElementById('lang-switcher').classList.remove('open');
    });
});

// Nastavit jazyk
function setLanguage(lang) {
    currentLang = lang;
    localStorage.setItem('techLang', lang);

    // Aktualizovat tlačítko
    document.getElementById('lang-current').textContent = lang.toUpperCase();

    // Aktualizovat aktivní volbu
    document.querySelectorAll('.lang-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.lang === lang);
    });

    // Přeložit stránku
    translatePage();
    vykresli();
}

// Přeložit stránku
function translatePage() {
    const t = translations[currentLang];

    // Přeložit elementy s data-i18n
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.dataset.i18n;
        if (t[key]) {
            el.textContent = t[key];
        }
    });

    // Přeložit placeholdery
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.dataset.i18nPlaceholder;
        if (t[key]) {
            el.placeholder = t[key];
        }
    });
}

// Získat překlad
function t(key) {
    return translations[currentLang][key] || translations['cz'][key] || key;
}

// Názvy dnů v týdnu pro různé jazyky
const dnyVTydnu = {
    cz: ['Nedele', 'Pondeli', 'Utery', 'Streda', 'Ctvrtek', 'Patek', 'Sobota'],
    sk: ['Nedela', 'Pondelok', 'Utorok', 'Streda', 'Stvrtok', 'Piatok', 'Sobota'],
    en: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    de: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
    nl: ['Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag']
};

// Získat název dne z data
function ziskejNazevDne(datumStr) {
    if (!datumStr) return '';
    const datum = new Date(datumStr);
    const denIndex = datum.getDay();
    const nazevDne = dnyVTydnu[currentLang]?.[denIndex] || dnyVTydnu['en'][denIndex];
    const den = datum.getDate();
    const mesic = datum.getMonth() + 1;
    return nazevDne.toUpperCase() + ' ' + den + '.' + mesic + '.';
}

// Data transportů - nyní s datem
let transporty = {
    '2024-12-13': [
        { id: 'so-2130', cas: '21:30', jmeno: 'Manuele Tessarollo (T78)', odkud: 'Marriott Airport', kam: 'venue', datum: '2024-12-13' },
        { id: 'so-2230', cas: '22:30', jmeno: 'Bjorn Verbeeck (BYORN) + Manager', odkud: 'Marriott Airport', kam: 'venue', datum: '2024-12-13' },
        { id: 'so-2330', cas: '23:30', jmeno: 'Yanick van Geldere + Sem Klinkenberg (DYEN)', odkud: 'Marriott Airport', kam: 'venue', datum: '2024-12-13' }
    ],
    '2024-12-14': [
        { id: 'ne-0150', cas: '01:50', jmeno: 'Kenzo Thomas Meservey + Lucas van den Nadort (Fantasm)', odkud: 'T3', kam: 'venue', datum: '2024-12-14' },
        { id: 'ne-0300', cas: '03:00', jmeno: 'Simon Andre Schytrumpf + 2 (Holy Priest)', odkud: 'T3', kam: 'venue', datum: '2024-12-14' },
        { id: 'ne-1730', cas: '17:30', jmeno: 'Kenzo Thomas Meservey + Lucas van den Nadort', odkud: 'Hotel Expo', kam: 'T2', datum: '2024-12-14' }
    ]
};

// Stavy transportů
let stavy = {};

// Data řidičů
let ridici = [
    { id: 'ridic-1', jmeno: 'MILAN', auto: 'MB V CLASS', poznamka: 'transport van', telefon: '+420735084519' },
    { id: 'ridic-2', jmeno: 'RADEK', auto: 'KIA CARNIVAL', poznamka: 'transport van', telefon: '+420725965826' }
];

// Aktuální editace
let editAkce = null; // { typ: 'pridat'/'editovat', den: 'sobota'/'nedele', id: null/string, pole: null/'cas'/'jmeno'/'trasa' }
let smazatId = null;
let resetId = null;

// Generovat unikátní ID
function generujId() {
    return 'tr-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5);
}

// Normalizovat čas na formát HH:MM (s vedoucí nulou)
function normalizujCas(cas) {
    if (!cas) return '00:00';
    // Odstranit mezery
    cas = cas.trim();
    // Pokud obsahuje ':', rozdělit
    const casti = cas.split(':');
    if (casti.length >= 2) {
        const hodiny = casti[0].padStart(2, '0');
        const minuty = casti[1].padStart(2, '0');
        return hodiny + ':' + minuty;
    }
    return cas;
}

// Porovnat dva časy pro řazení
function porovnejCas(cas1, cas2) {
    const norm1 = normalizujCas(cas1);
    const norm2 = normalizujCas(cas2);
    return norm1.localeCompare(norm2);
}

// Vykreslit řidiče
function vykresliRidice() {
    const kontejner = document.getElementById('ridici-kontejner');
    if (!kontejner) return;

    kontejner.innerHTML = '';

    // Seradit ridice podle jmena (A-Z)
    const serazeniRidici = [...ridici].sort((a, b) =>
        (a.jmeno || '').localeCompare(b.jmeno || '', 'cs')
    );

    serazeniRidici.forEach(ridic => {
        const div = document.createElement('div');
        div.className = 'ridic';
        div.innerHTML = `
            <div class="ridic-auto-svg" onclick="otevriModalRidic('${ridic.id}')" style="cursor: pointer;" title="Kliknete pro upravu">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
                </svg>
            </div>
            <div class="ridic-info">
                <div class="ridic-jmeno">${ridic.jmeno}</div>
                <div class="ridic-auto">${ridic.auto}</div>
                <div class="ridic-standby">${ridic.poznamka || t('transportVan')}</div>
            </div>
            <a href="tel:${ridic.telefon}" class="ridic-tel" onclick="event.stopPropagation();">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                </svg>
            </a>
        `;
        kontejner.appendChild(div);
    });

    // Pridat tlacitko "+" pro pridani noveho ridice
    const pridatDiv = document.createElement('div');
    pridatDiv.className = 'ridic ridic-pridat';
    pridatDiv.innerHTML = `
        <div class="ridic-pridat-btn" onclick="otevriModalPridatRidice()" title="Pridat ridice">
            <svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
            </svg>
        </div>
        <div class="ridic-info">
            <div class="ridic-jmeno" style="color: #666;">PRIDAT</div>
        </div>
    `;
    kontejner.appendChild(pridatDiv);
}

// Mod editace ridice: 'edit' nebo 'add'
let ridicEditMod = 'edit';

// Otevřít modal pro editaci řidiče
function otevriModalRidic(id) {
    const ridic = ridici.find(r => r.id === id);
    if (!ridic) return;

    ridicEditMod = 'edit';
    document.getElementById('modal-ridic-titulek').textContent = 'Upravit ridice';
    document.getElementById('ridic-edit-id').value = id;
    document.getElementById('ridic-jmeno').value = ridic.jmeno || '';
    document.getElementById('ridic-auto').value = ridic.auto || '';
    document.getElementById('ridic-poznamka').value = ridic.poznamka || '';
    document.getElementById('ridic-telefon').value = ridic.telefon || '';
    document.getElementById('ridic-heslo').value = '';
    document.getElementById('modal-ridic-chyba').style.display = 'none';

    document.getElementById('modal-ridic').classList.add('aktivni');
}

// Otevřít modal pro přidání nového řidiče
function otevriModalPridatRidice() {
    ridicEditMod = 'add';
    document.getElementById('modal-ridic-titulek').textContent = 'Pridat ridice';
    document.getElementById('ridic-edit-id').value = '';
    document.getElementById('ridic-jmeno').value = '';
    document.getElementById('ridic-auto').value = '';
    document.getElementById('ridic-poznamka').value = '';
    document.getElementById('ridic-telefon').value = '';
    document.getElementById('ridic-heslo').value = '';
    document.getElementById('modal-ridic-chyba').style.display = 'none';

    document.getElementById('modal-ridic').classList.add('aktivni');
}

// Zavřít modal řidiče
function zavriModalRidic() {
    document.getElementById('modal-ridic').classList.remove('aktivni');
}

// Uložit změny řidiče
async function ulozRidice() {
    const heslo = document.getElementById('ridic-heslo').value;
    if (heslo !== HESLO) {
        document.getElementById('modal-ridic-chyba').style.display = 'block';
        return;
    }

    if (ridicEditMod === 'add') {
        // Pridat noveho ridice
        const novyRidic = {
            id: 'ridic-' + Date.now(),
            jmeno: document.getElementById('ridic-jmeno').value.toUpperCase() || 'NOVY',
            auto: document.getElementById('ridic-auto').value.toUpperCase() || '',
            poznamka: document.getElementById('ridic-poznamka').value || '',
            telefon: document.getElementById('ridic-telefon').value || ''
        };
        ridici.push(novyRidic);
    } else {
        // Editovat existujiciho ridice
        const id = document.getElementById('ridic-edit-id').value;
        const ridic = ridici.find(r => r.id === id);
        if (!ridic) return;

        ridic.jmeno = document.getElementById('ridic-jmeno').value.toUpperCase();
        ridic.auto = document.getElementById('ridic-auto').value.toUpperCase();
        ridic.poznamka = document.getElementById('ridic-poznamka').value;
        ridic.telefon = document.getElementById('ridic-telefon').value;
    }

    zavriModalRidic();
    vykresliRidice();
    await ulozData();
}

// Vykreslit transporty - dynamické sekce podle data
function vykresli() {
    const tr = translations[currentLang];
    const kontejner = document.getElementById('dny-kontejner');
    kontejner.innerHTML = '';

    // Získat všechna data a seřadit je
    const data = Object.keys(transporty).sort();

    data.forEach(datum => {
        if (!transporty[datum] || transporty[datum].length === 0) return;

        // Vytvořit sekci pro den
        const denDiv = document.createElement('div');
        denDiv.className = 'den';
        denDiv.dataset.datum = datum;

        // Hlavička dne
        const nazevDne = ziskejNazevDne(datum);
        denDiv.innerHTML = `
            <div class="den-header">
                <span>${nazevDne}</span>
                <button class="btn-pridat" onclick="otevriModalPridat('${datum}')">+ ${t('add').replace('+ ', '')}</button>
            </div>
            <div class="transporty" id="transporty-${datum}"></div>
        `;
        kontejner.appendChild(denDiv);

        const transportyKontejner = document.getElementById('transporty-' + datum);

        // Seřadit podle času
        transporty[datum].sort((a, b) => porovnejCas(a.cas, b.cas));

        transporty[datum].forEach(item => {
            const stavData = stavy[item.id] || { stav: 'wait' };
            let stavClass = 'stav-wait';
            let stavText = tr.wait;
            let stavCas = '';

            if (stavData.stav === 'onway') {
                stavClass = 'stav-onway';
                stavText = tr.onway;
                stavCas = tr.departed + ' ' + stavData.cas;
            } else if (stavData.stav === 'drop') {
                stavClass = 'stav-drop';
                stavText = tr.drop;
                stavCas = tr.delivered + ' ' + stavData.casDrop;
            }

            const div = document.createElement('div');
            div.className = 'transport';
            div.dataset.id = item.id;
            div.innerHTML = `
                <button class="btn-smazat" onclick="event.stopPropagation(); otevriModalSmazat('${item.id}', '${datum}')">&times;</button>
                <button class="btn-upravit" onclick="event.stopPropagation(); editujVse('${item.id}', '${datum}')">✎</button>
                <div class="transport-cas">${item.cas}</div>
                <div class="transport-info">
                    <div class="transport-jmena">${item.jmeno}</div>
                    <div class="transport-trasa">${item.odkud} → ${item.kam}</div>
                </div>
                <div class="transport-stav">
                    <button class="stav-btn ${stavClass}" onclick="event.stopPropagation(); zmenStav('${item.id}')">${stavText}</button>
                    <div class="stav-cas">${stavCas}</div>
                </div>
            `;
            transportyKontejner.appendChild(div);
        });
    });
}

// Otevřít modal pro přidání
function otevriModalPridat(datum) {
    editAkce = { typ: 'pridat', datum: datum, id: null, pole: null };
    document.getElementById('modal-titulek').textContent = t('addTransport');
    document.getElementById('input-cas').value = '';
    document.getElementById('input-jmeno').value = '';
    document.getElementById('input-odkud').value = '';
    document.getElementById('input-kam').value = '';
    document.getElementById('input-heslo').value = '';
    document.getElementById('modal-chyba').style.display = 'none';

    // Zobrazit pole pro datum pri pridavani
    const datumWrapper = document.getElementById('datum-wrapper');
    const datumInput = document.getElementById('input-datum');
    datumWrapper.style.display = 'block';

    // Nastavit vychozi datum
    if (datum) {
        // Pokud je zadano datum, pouzit ho
        const d = new Date(datum);
        const den = d.getDate().toString().padStart(2, '0');
        const mesic = (d.getMonth() + 1).toString().padStart(2, '0');
        const rok = d.getFullYear();
        datumInput.value = den + '.' + mesic + '.' + rok;
        datumInput.dataset.hodnota = datum;
    } else {
        // Jinak dnesni datum
        const dnes = new Date();
        const den = dnes.getDate().toString().padStart(2, '0');
        const mesic = (dnes.getMonth() + 1).toString().padStart(2, '0');
        const rok = dnes.getFullYear();
        datumInput.value = den + '.' + mesic + '.' + rok;
        datumInput.dataset.hodnota = rok + '-' + mesic + '-' + den;
    }

    document.getElementById('modal-edit').classList.add('aktivni');
}

// Editovat všechna pole najednou
function editujVse(id, datum) {
    const transport = transporty[datum]?.find(t => t.id === id);
    if (!transport) return;

    editAkce = { typ: 'editovat', datum: datum, id: id, pole: 'vse' };
    document.getElementById('modal-titulek').textContent = t('editTransport');

    // Skryt pole pro datum pri editaci (datum nelze menit)
    document.getElementById('datum-wrapper').style.display = 'none';

    // Vyplnit aktuální hodnoty
    document.getElementById('input-cas').value = transport.cas;
    document.getElementById('input-jmeno').value = transport.jmeno;
    document.getElementById('input-odkud').value = transport.odkud;
    document.getElementById('input-kam').value = transport.kam;
    document.getElementById('input-heslo').value = '';
    document.getElementById('modal-chyba').style.display = 'none';

    document.getElementById('modal-edit').classList.add('aktivni');
}

// Potvrdit editaci
async function potvrdEdit() {
    const heslo = document.getElementById('input-heslo').value;
    if (heslo !== HESLO) {
        document.getElementById('modal-chyba').style.display = 'block';
        return;
    }

    if (editAkce.typ === 'pridat') {
        // Zjistit datum z inputu
        const datumInput = document.getElementById('input-datum');
        const vybraneDatum = datumInput.dataset.hodnota || '';

        if (!vybraneDatum) {
            alert('Vyberte datum');
            return;
        }

        const novy = {
            id: generujId(),
            cas: normalizujCas(document.getElementById('input-cas').value) || '00:00',
            jmeno: document.getElementById('input-jmeno').value || 'Neznamy',
            odkud: document.getElementById('input-odkud').value || '?',
            kam: document.getElementById('input-kam').value || '?',
            datum: vybraneDatum
        };

        // Pridat do spravneho datumu
        if (!transporty[vybraneDatum]) {
            transporty[vybraneDatum] = [];
        }
        transporty[vybraneDatum].push(novy);

    } else if (editAkce.typ === 'editovat') {
        const transport = transporty[editAkce.datum]?.find(t => t.id === editAkce.id);
        if (transport) {
            transport.cas = normalizujCas(document.getElementById('input-cas').value);
            transport.jmeno = document.getElementById('input-jmeno').value;
            transport.odkud = document.getElementById('input-odkud').value;
            transport.kam = document.getElementById('input-kam').value;
        }
    }

    zavriModal();
    vykresli();
    await ulozData();
}

// Zavřít modal
function zavriModal() {
    document.getElementById('modal-edit').classList.remove('aktivni');
    editAkce = null;
}

// ===== KALENDÁŘ =====
let kalendarDatum = new Date();
const mesiceNazvy = ['Leden', 'Unor', 'Brezen', 'Duben', 'Kveten', 'Cerven', 'Cervenec', 'Srpen', 'Zari', 'Rijen', 'Listopad', 'Prosinec'];
const dnyTydne = ['Po', 'Ut', 'St', 'Ct', 'Pa', 'So', 'Ne'];

// Otevřít kalendář
function otevriKalendar() {
    // Nastavit aktualni mesic podle vybrane hodnoty
    const datumInput = document.getElementById('input-datum');
    if (datumInput.dataset.hodnota) {
        kalendarDatum = new Date(datumInput.dataset.hodnota);
    } else {
        kalendarDatum = new Date();
    }
    vykresliKalendar();
    document.getElementById('kalendar-overlay').classList.add('aktivni');
}

// Zavřít kalendář
function zavriKalendar() {
    document.getElementById('kalendar-overlay').classList.remove('aktivni');
}

// Vykreslit kalendář
function vykresliKalendar() {
    const rok = kalendarDatum.getFullYear();
    const mesic = kalendarDatum.getMonth();

    // Zobrazit mesic a rok
    document.getElementById('kalendar-mesic').textContent = mesiceNazvy[mesic] + ' ' + rok;

    const grid = document.getElementById('kalendar-grid');
    grid.innerHTML = '';

    // Hlavička - dny týdne
    dnyTydne.forEach(den => {
        const div = document.createElement('div');
        div.className = 'kalendar-den-tyden';
        div.textContent = den;
        grid.appendChild(div);
    });

    // Prvni den mesice
    const prvniDen = new Date(rok, mesic, 1).getDay();
    const dniVMesici = new Date(rok, mesic + 1, 0).getDate();
    const upravenyPrvniDen = prvniDen === 0 ? 6 : prvniDen - 1; // Pondeli = 0

    // Prazdne dny na zacatku
    for (let i = 0; i < upravenyPrvniDen; i++) {
        const div = document.createElement('div');
        div.className = 'kalendar-den disabled';
        grid.appendChild(div);
    }

    // Dny mesice
    const dnes = new Date();
    const datumInput = document.getElementById('input-datum');
    const vybranaHodnota = datumInput.dataset.hodnota || '';

    for (let den = 1; den <= dniVMesici; den++) {
        const div = document.createElement('div');
        div.className = 'kalendar-den';
        div.textContent = den;

        // Oznacit dnesni den
        if (den === dnes.getDate() && mesic === dnes.getMonth() && rok === dnes.getFullYear()) {
            div.classList.add('dnes');
        }

        // Oznacit vybrany den
        const datumStr = rok + '-' + String(mesic + 1).padStart(2, '0') + '-' + String(den).padStart(2, '0');
        if (datumStr === vybranaHodnota) {
            div.classList.add('vybrany');
        }

        div.addEventListener('click', () => {
            // Nastavit vybrany datum
            const vybranyDatum = rok + '-' + String(mesic + 1).padStart(2, '0') + '-' + String(den).padStart(2, '0');
            const zobrazenyDatum = String(den).padStart(2, '0') + '.' + String(mesic + 1).padStart(2, '0') + '.' + rok;

            datumInput.value = zobrazenyDatum;
            datumInput.dataset.hodnota = vybranyDatum;

            zavriKalendar();
        });

        grid.appendChild(div);
    }
}

// Inicializace kalendáře - navigace
document.addEventListener('DOMContentLoaded', function() {
    const prevBtn = document.getElementById('kalendar-prev');
    const nextBtn = document.getElementById('kalendar-next');

    if (prevBtn) {
        prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            kalendarDatum.setMonth(kalendarDatum.getMonth() - 1);
            vykresliKalendar();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            kalendarDatum.setMonth(kalendarDatum.getMonth() + 1);
            vykresliKalendar();
        });
    }

    // Zavrit kalendar klikem na overlay
    const overlay = document.getElementById('kalendar-overlay');
    if (overlay) {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                zavriKalendar();
            }
        });
    }
});

// Otevřít modal pro smazání
function otevriModalSmazat(id, datum) {
    const transport = transporty[datum]?.find(t => t.id === id);
    if (!transport) return;

    smazatId = { id: id, datum: datum };
    document.getElementById('smazat-info').textContent = transport.cas + ' - ' + transport.jmeno;
    document.getElementById('input-heslo-smazat').value = '';
    document.getElementById('modal-chyba-smazat').style.display = 'none';
    document.getElementById('modal-smazat').classList.add('aktivni');
}

// Potvrdit smazání
async function potvrdSmazat() {
    const heslo = document.getElementById('input-heslo-smazat').value;
    if (heslo !== HESLO) {
        document.getElementById('modal-chyba-smazat').style.display = 'block';
        return;
    }

    if (transporty[smazatId.datum]) {
        transporty[smazatId.datum] = transporty[smazatId.datum].filter(t => t.id !== smazatId.id);
        // Pokud je datum prazdne, odstranit ho
        if (transporty[smazatId.datum].length === 0) {
            delete transporty[smazatId.datum];
        }
    }
    delete stavy[smazatId.id];

    zavriModalSmazat();
    vykresli();
    await ulozData();
}

// Zavřít modal smazání
function zavriModalSmazat() {
    document.getElementById('modal-smazat').classList.remove('aktivni');
    smazatId = null;
}

// Změnit stav transportu
async function zmenStav(id) {
    const aktualniStav = stavy[id]?.stav || 'wait';
    const cas = new Date().toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });

    if (aktualniStav === 'wait') {
        stavy[id] = { stav: 'onway', cas: cas };
        vykresli();
        await ulozData();
        // Po uložení načíst data ze serveru pro potvrzení
        setTimeout(() => nactiData(), 500);
    } else if (aktualniStav === 'onway') {
        stavy[id] = { stav: 'drop', casDrop: cas, cas: stavy[id].cas };
        vykresli();
        await ulozData();
        setTimeout(() => nactiData(), 500);
    } else if (aktualniStav === 'drop') {
        // DROP OFF - otevřít modal pro reset
        otevriModalReset(id);
    }
}

// Otevřít modal pro reset stavu
function otevriModalReset(id) {
    resetId = id;
    // Najít transport info
    let info = 'Transport';
    Object.keys(transporty).forEach(datum => {
        const transport = transporty[datum]?.find(t => t.id === id);
        if (transport) {
            info = transport.cas + ' - ' + transport.jmeno;
        }
    });
    document.getElementById('reset-info').textContent = info;
    document.getElementById('input-heslo-reset').value = '';
    document.getElementById('modal-chyba-reset').style.display = 'none';
    document.getElementById('modal-reset').classList.add('aktivni');
}

// Potvrdit reset stavu
async function potvrdReset() {
    const heslo = document.getElementById('input-heslo-reset').value;
    if (heslo !== HESLO) {
        document.getElementById('modal-chyba-reset').style.display = 'block';
        return;
    }

    // Resetovat stav na wait
    delete stavy[resetId];

    zavriModalReset();
    vykresli();
    await ulozData();
}

// Zavřít modal reset
function zavriModalReset() {
    document.getElementById('modal-reset').classList.remove('aktivni');
    resetId = null;
}

// Flag pro zamezení přepsání během ukládání
let ukladaSe = false;

// Uložit data na server
async function ulozData() {
    ukladaSe = true;
    try {
        const formData = new FormData();
        formData.append('stavy', JSON.stringify(stavy));
        formData.append('transporty', JSON.stringify(transporty));
        formData.append('ridici', JSON.stringify(ridici));
        await fetch('api/transport_sync.php', {
            method: 'POST',
            body: formData
        });
    } catch (e) {
        console.log('Chyba pri ukladani');
    }
    ukladaSe = false;
}

// Flag pro první načtení
let prvniNacteni = true;

// Načíst data ze serveru
async function nactiData() {
    // Nepřepisovat lokální data pokud právě ukládáme
    if (ukladaSe) return;

    try {
        // Cache-busting - přidat timestamp k URL
        const odpoved = await fetch('api/transport_sync.php?t=' + Date.now(), {
            cache: 'no-store'
        });
        const data = await odpoved.json();
        if (data.status === 'success') {
            // Při prvním načtení - pokud server nemá data, uložit výchozí
            const maServerData = data.transporty && typeof data.transporty === 'object' && Object.keys(data.transporty).length > 0;
            if (prvniNacteni && !maServerData) {
                prvniNacteni = false;
                await ulozData();
                return;
            }
            prvniNacteni = false;

            // Server je zdroj pravdy - vždy přepsat lokální data
            // Pozor: server může vrátit [] (prázdné pole) místo {} (objekt)
            stavy = (Array.isArray(data.stavy) || !data.stavy) ? {} : data.stavy;

            // Nacist transporty - kontrola zda jsou ve spravnem formatu
            if (data.transporty && typeof data.transporty === 'object' && !Array.isArray(data.transporty)) {
                // Migrace stare struktury (sobota/nedele) na novou (datumy)
                if (data.transporty.sobota || data.transporty.nedele) {
                    const novyFormat = {};
                    if (data.transporty.sobota) {
                        novyFormat['2024-12-13'] = data.transporty.sobota.map(t => ({...t, datum: '2024-12-13'}));
                    }
                    if (data.transporty.nedele) {
                        novyFormat['2024-12-14'] = data.transporty.nedele.map(t => ({...t, datum: '2024-12-14'}));
                    }
                    transporty = novyFormat;
                    // Ulozit migrovana data
                    await ulozData();
                } else {
                    transporty = data.transporty;
                }
            }

            // Načíst řidiče - pokud existují na serveru
            if (data.ridici && Array.isArray(data.ridici) && data.ridici.length > 0) {
                ridici = data.ridici;
            }
            vykresli();
            vykresliRidice();
        }
    } catch (e) {
        console.log('Chyba pri nacitani:', e);
        vykresli();
        vykresliRidice();
    }
}

// Zavřít modaly klávesou Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        zavriModal();
        zavriModalSmazat();
        zavriModalReset();
        zavriModalRidic();
    }
});

// Inicializace po načtení stránky
document.addEventListener('DOMContentLoaded', function() {
    // Nastavit jazyk při načtení
    document.getElementById('lang-current').textContent = currentLang.toUpperCase();
    document.querySelectorAll('.lang-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.lang === currentLang);
    });
    translatePage();

    // Vykreslit řidiče ihned s výchozími daty
    vykresliRidice();

    nactiData();

    // Pravidelná synchronizace každé 3 sekundy
    setInterval(nactiData, 3000);
});
</script>

<!-- Footer -->
<footer style="text-align: center; padding: 30px 15px; margin-top: 40px; border-top: 1px solid #222; color: #555; font-size: 11px; line-height: 1.6;">
    <span data-i18n="footerText">Aplikaci vytvořil a transport servis zajišťuje</span><br>
    <strong style="color: #888;">Radek Zikmund</strong>
    <a href="tel:+420725965826" style="color: #fff; text-decoration: none;">+420 725 965 826</a><br>
    <span style="color: #666; letter-spacing: 1px;">WHITE GLOVE SERVICE</span>
</footer>

</body>
</html>
