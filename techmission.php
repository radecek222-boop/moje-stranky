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
            flex-direction: column;
            align-items: center;
            padding: 15px 0 25px;
            border-bottom: 1px solid #333;
            margin-bottom: 20px;
        }

        .logo-um {
            position: absolute;
            top: 10px;
            left: 10px;
            height: 25px;
            object-fit: contain;
        }

        /* Language switcher */
        .lang-switcher {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 100;
        }

        .lang-current {
            background: transparent;
            border: none;
            color: #fff;
            padding: 6px 8px;
            font-size: 16px;
            font-weight: 700;
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
            font-weight: 600;
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

        .logo-tech {
            height: 200px;
            object-fit: contain;
            margin-top: 80px;
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

        /* Tlačítko smazat */
        .btn-smazat {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            background: transparent;
            border: none;
            color: #444;
            font-size: 16px;
            cursor: pointer;
            opacity: 0;
            transition: all 0.2s;
        }

        .transport:hover .btn-smazat {
            opacity: 1;
        }

        .btn-smazat:hover {
            color: #ff4444;
        }

        /* Tlačítko upravit */
        .btn-upravit {
            position: absolute;
            top: 8px;
            left: 8px;
            width: 20px;
            height: 20px;
            background: transparent;
            border: none;
            color: #444;
            font-size: 14px;
            cursor: pointer;
            opacity: 0;
            transition: all 0.2s;
        }

        .transport:hover .btn-upravit {
            opacity: 1;
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

        .modal-input {
            width: 100%;
            background: #222;
            border: 1px solid #333;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 12px;
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

<!-- Header s logy -->
<div class="header">
    <img src="assets/img/um_white.png.webp" alt="United Music" class="logo-um">

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

    <img src="assets/img/front_logo_tech.png" alt="Techmission" class="logo-tech">
</div>

<!-- Řidiči -->
<div class="ridici">
    <div class="ridic">
        <svg class="ridic-auto-svg" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
        <div class="ridic-info">
            <div class="ridic-jmeno">MILAN</div>
            <div class="ridic-auto">MB V CLASS</div>
            <div class="ridic-standby">transport van</div>
        </div>
        <a href="tel:+420735084519" class="ridic-tel">
            <svg viewBox="0 0 24 24" fill="#fff" width="18" height="18"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
        </a>
    </div>
    <div class="ridic">
        <svg class="ridic-auto-svg" viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>
        <div class="ridic-info">
            <div class="ridic-jmeno">MIREK</div>
            <div class="ridic-auto">MB S CLASS</div>
            <div class="ridic-standby standby-caps">STAND BY 21:00 - 06:00</div>
        </div>
        <a href="tel:+420736611777" class="ridic-tel">
            <svg viewBox="0 0 24 24" fill="#fff" width="18" height="18"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
        </a>
    </div>
</div>

<!-- SOBOTA 13.12. -->
<div class="den den-sobota" data-den="sobota">
    <div class="den-header">
        <span data-i18n="saturday">Sobota 13.12.</span>
        <button class="btn-pridat" onclick="otevriModalPridat('sobota')" data-i18n="add">+ Pridat</button>
    </div>
    <div class="transporty" id="transporty-sobota"></div>
</div>

<!-- NEDĚLE 14.12. -->
<div class="den den-nedele" data-den="nedele">
    <div class="den-header">
        <span data-i18n="sunday">Nedele 14.12.</span>
        <button class="btn-pridat" onclick="otevriModalPridat('nedele')" data-i18n="add">+ Pridat</button>
    </div>
    <div class="transporty" id="transporty-nedele"></div>
</div>


<!-- Modal pro přidání/editaci -->
<div class="modal" id="modal-edit">
    <div class="modal-obsah">
        <div class="modal-titulek" id="modal-titulek" data-i18n="addTransport">Pridat transport</div>
        <input type="text" class="modal-input" id="input-cas" data-i18n-placeholder="timePlaceholder" placeholder="Cas (napr. 21:30)">
        <input type="text" class="modal-input" id="input-jmeno" data-i18n-placeholder="namePlaceholder" placeholder="Jmeno pasazera">
        <input type="text" class="modal-input" id="input-odkud" data-i18n-placeholder="fromPlaceholder" placeholder="Odkud">
        <input type="text" class="modal-input" id="input-kam" data-i18n-placeholder="toPlaceholder" placeholder="Kam">
        <input type="tel" class="modal-input" id="input-heslo" data-i18n-placeholder="passwordPlaceholder" placeholder="Heslo pro potvrzeni" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
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

// Data transportů
let transporty = {
    sobota: [
        { id: 'so-2130', cas: '21:30', jmeno: 'Manuele Tessarollo (T78)', odkud: 'Marriott Airport', kam: 'venue' },
        { id: 'so-2230', cas: '22:30', jmeno: 'Bjorn Verbeeck (BYORN) + Manager', odkud: 'Marriott Airport', kam: 'venue' },
        { id: 'so-2330', cas: '23:30', jmeno: 'Yanick van Geldere + Sem Klinkenberg (DYEN)', odkud: 'Marriott Airport', kam: 'venue' }
    ],
    nedele: [
        { id: 'ne-0150', cas: '01:50', jmeno: 'Kenzo Thomas Meservey + Lucas van den Nadort (Fantasm)', odkud: 'T3', kam: 'venue' },
        { id: 'ne-0300', cas: '03:00', jmeno: 'Simon Andre Schytrumpf + 2 (Holy Priest)', odkud: 'T3', kam: 'venue' },
        { id: 'ne-1730', cas: '17:30', jmeno: 'Kenzo Thomas Meservey + Lucas van den Nadort', odkud: 'Hotel Expo', kam: 'T2' }
    ]
};

// Stavy transportů
let stavy = {};

// Aktuální editace
let editAkce = null; // { typ: 'pridat'/'editovat', den: 'sobota'/'nedele', id: null/string, pole: null/'cas'/'jmeno'/'trasa' }
let smazatId = null;
let resetId = null;

// Generovat unikátní ID
function generujId() {
    return 'tr-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5);
}

// Vykreslit transporty
function vykresli() {
    const tr = translations[currentLang];

    ['sobota', 'nedele'].forEach(den => {
        const kontejner = document.getElementById('transporty-' + den);
        kontejner.innerHTML = '';

        // Seřadit podle času
        transporty[den].sort((a, b) => a.cas.localeCompare(b.cas));

        transporty[den].forEach(item => {
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
                <button class="btn-upravit" onclick="event.stopPropagation(); editujVse('${item.id}', '${den}')">✎</button>
                <button class="btn-smazat" onclick="event.stopPropagation(); otevriModalSmazat('${item.id}', '${den}')">&times;</button>
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
            kontejner.appendChild(div);
        });
    });
}

// Otevřít modal pro přidání
function otevriModalPridat(den) {
    editAkce = { typ: 'pridat', den: den, id: null, pole: null };
    document.getElementById('modal-titulek').textContent = 'Pridat transport';
    document.getElementById('input-cas').value = '';
    document.getElementById('input-jmeno').value = '';
    document.getElementById('input-odkud').value = '';
    document.getElementById('input-kam').value = '';
    document.getElementById('input-heslo').value = '';
    document.getElementById('modal-chyba').style.display = 'none';

    // Zobrazit všechny inputy
    document.getElementById('input-cas').style.display = 'block';
    document.getElementById('input-jmeno').style.display = 'block';
    document.getElementById('input-odkud').style.display = 'block';
    document.getElementById('input-kam').style.display = 'block';

    document.getElementById('modal-edit').classList.add('aktivni');
}

// Editovat všechna pole najednou
function editujVse(id, den) {
    const transport = transporty[den].find(t => t.id === id);
    if (!transport) return;

    editAkce = { typ: 'editovat', den: den, id: id, pole: 'vse' };
    document.getElementById('modal-titulek').textContent = 'Upravit transport';

    // Zobrazit všechny inputy
    document.getElementById('input-cas').style.display = 'block';
    document.getElementById('input-jmeno').style.display = 'block';
    document.getElementById('input-odkud').style.display = 'block';
    document.getElementById('input-kam').style.display = 'block';

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
        const novy = {
            id: generujId(),
            cas: document.getElementById('input-cas').value || '00:00',
            jmeno: document.getElementById('input-jmeno').value || 'Neznamy',
            odkud: document.getElementById('input-odkud').value || '?',
            kam: document.getElementById('input-kam').value || '?'
        };
        transporty[editAkce.den].push(novy);
    } else if (editAkce.typ === 'editovat') {
        const transport = transporty[editAkce.den].find(t => t.id === editAkce.id);
        if (transport) {
            if (editAkce.pole === 'vse') {
                // Editace všech polí najednou
                transport.cas = document.getElementById('input-cas').value;
                transport.jmeno = document.getElementById('input-jmeno').value;
                transport.odkud = document.getElementById('input-odkud').value;
                transport.kam = document.getElementById('input-kam').value;
            } else if (editAkce.pole === 'cas') {
                transport.cas = document.getElementById('input-cas').value;
            } else if (editAkce.pole === 'jmeno') {
                transport.jmeno = document.getElementById('input-jmeno').value;
            } else if (editAkce.pole === 'trasa') {
                transport.odkud = document.getElementById('input-odkud').value;
                transport.kam = document.getElementById('input-kam').value;
            }
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

// Otevřít modal pro smazání
function otevriModalSmazat(id, den) {
    const transport = transporty[den].find(t => t.id === id);
    if (!transport) return;

    smazatId = { id: id, den: den };
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

    transporty[smazatId.den] = transporty[smazatId.den].filter(t => t.id !== smazatId.id);
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
    ['sobota', 'nedele'].forEach(den => {
        const transport = transporty[den].find(t => t.id === id);
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
            if (prvniNacteni && (!data.transporty || !data.transporty.sobota)) {
                prvniNacteni = false;
                await ulozData();
                return;
            }
            prvniNacteni = false;

            // Server je zdroj pravdy - vždy přepsat lokální data
            stavy = data.stavy || {};
            if (data.transporty) {
                transporty = data.transporty;
            }
            vykresli();
        }
    } catch (e) {
        console.log('Chyba pri nacitani');
        vykresli();
    }
}

// Zavřít modaly klávesou Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        zavriModal();
        zavriModalSmazat();
        zavriModalReset();
    }
});

// Inicializace
// Nastavit jazyk při načtení
document.getElementById('lang-current').textContent = currentLang.toUpperCase();
document.querySelectorAll('.lang-option').forEach(opt => {
    opt.classList.toggle('active', opt.dataset.lang === currentLang);
});
translatePage();

nactiData();

// Pravidelná synchronizace každé 3 sekundy
setInterval(nactiData, 3000);
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
