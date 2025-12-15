<?php
/**
 * TRANSPORT - Správa transportů
 * Přehled transportů pro řidiče
 */
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport</title>
    <!-- Favicon - SVG auto -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z'/%3E%3C/svg%3E">
    <link rel="shortcut icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z'/%3E%3C/svg%3E">
    <!-- iOS -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Transport">
    <!-- Android -->
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

        .header-title {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 2px;
            color: #fff;
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .header-title:hover {
            background: #222;
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

        /* Kontejner pro hlavni tlacitka */
        .hlavni-tlacitka {
            display: flex;
            gap: 10px;
            padding: 15px 10px;
            margin: 0;
        }

        /* Tlacitko ridici */
        .btn-ridici {
            flex: 1;
            background: #222;
            color: #fff;
            border: 1px solid #444;
            padding: 12px 5px;
            border-radius: 8px;
            font-size: clamp(9px, 2.5vw, 14px);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .btn-ridici:hover {
            background: #333;
            border-color: #555;
        }

        .btn-dokoncene #pocet-dokoncenych:not(:empty) {
            background: #39ff14;
            color: #000;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 6px;
        }

        /* Dokonceny transport v modalu */
        .dokonceny-item {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dokonceny-info {
            flex: 1;
        }

        .dokonceny-jmeno {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
        }

        .dokonceny-meta {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }

        .dokonceny-cas {
            font-size: 12px;
            color: #39ff14;
            font-weight: 600;
        }

        .dokonceny-akce {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-smazat-dokonceny {
            background: transparent;
            border: none;
            color: #dc3545;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.2s;
        }

        .btn-smazat-dokonceny:hover {
            color: #ff4444;
        }

        /* Modal ridici obsah */
        .modal-ridici-obsah {
            max-width: 450px;
        }

        /* Řidiči */
        .ridici {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 20px 0;
            max-width: 100%;
        }

        .ridic {
            display: grid;
            justify-items: center;
            text-align: center;
            gap: 8px;
            position: relative;
            cursor: pointer;
        }

        .ridic-info {
            text-align: center;
        }

        /* Mini-overlay pro řidiče */
        .ridic-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #222;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 6px;
            gap: 6px;
            z-index: 100;
            margin-top: 8px;
        }

        .ridic-menu.aktivni {
            display: flex;
        }

        .ridic-menu-btn {
            background: #333;
            border: none;
            color: #fff;
            padding: 8px 14px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            transition: background 0.2s;
        }

        .ridic-menu-btn:hover {
            background: #444;
        }

        /* Seznam transportů řidiče */
        .seznam-transport-item {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .seznam-transport-info {
            flex: 1;
        }

        .seznam-transport-cas {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        .seznam-transport-jmeno {
            font-size: 12px;
            color: #ccc;
            margin-top: 2px;
        }

        .seznam-transport-trasa {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }

        .seznam-transport-stav {
            font-size: 10px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
            background: #333;
        }

        .seznam-transport-stav.stav-onway-mini {
            background: #fff;
            color: #000;
        }

        .seznam-transport-stav.stav-drop-mini {
            background: transparent;
            color: #39ff14;
            border: 1px solid #39ff14;
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

        /* Transport řádek - Grid layout */
        .transport {
            background: #111;
            border: 1px solid #222;
            border-radius: 8px;
            padding: 6px 10px;
            margin-bottom: 3px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            grid-template-rows: auto auto auto auto;
            gap: 0px 8px;
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

        /* Levy horni roh - cas */
        .transport-cas {
            font-size: 20px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            grid-column: 1;
            grid-row: 1;
        }

        /* Pravy horni roh - telefon */
        .transport-kontakty {
            grid-column: 3;
            grid-row: 1;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        /* Trasa - nad jmenem */
        .transport-trasa {
            font-size: 11px;
            color: #555;
            grid-column: 1 / 4;
            grid-row: 2;
        }

        /* Jmeno klienta */
        .transport-jmena {
            font-size: 16px;
            font-weight: 700;
            grid-column: 1 / 4;
            grid-row: 3;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Meta info - let + ridic */
        .transport-meta {
            font-size: 10px;
            color: #555;
            grid-column: 1 / 4;
            grid-row: 4;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .let-info-inline {
            width: 100%;
            font-size: 10px;
            padding: 4px 0;
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .transport-poznamka {
            font-size: 11px;
            color: #888;
            grid-column: 1 / 4;
            grid-row: 5;
            padding: 6px 10px;
            background: #1a1a1a;
            border-radius: 4px;
            border-left: 2px solid #444;
        }

        /* Levy dolni roh - upravit (male) */
        .btn-upravit {
            grid-column: 1;
            grid-row: 6;
            justify-self: start;
            background: transparent;
            border: none;
            color: #555;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-upravit:hover {
            background: #222;
            color: #888;
        }

        /* Pravy dolni roh - stav (male) */
        .transport-stav {
            grid-column: 3;
            grid-row: 6;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .transport-let {
            display: inline-block;
            background: transparent;
            border: none;
            padding: 0;
            font-size: 10px;
            font-weight: 500;
            color: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }

        .transport-let:hover {
            color: #ccc;
        }

        .transport-let.aktivni {
            color: #39ff14;
        }

        .transport-let.pristano {
            color: #39ff14;
        }

        .transport-let.zpozdeno {
            color: #ff8800;
        }

        @keyframes let-pulse {
            0%, 100% { box-shadow: 0 0 5px rgba(57, 255, 20, 0.3); }
            50% { box-shadow: 0 0 10px rgba(57, 255, 20, 0.5); }
        }

        /* Ridic badge (male) */
        .transport-ridic {
            display: inline-block;
            background: transparent;
            border: none;
            padding: 0;
            font-size: 10px;
            font-weight: 500;
            color: #fff;
            text-transform: uppercase;
        }

        /* Pocet osob */
        .transport-pocet {
            display: inline-block;
            color: #fff;
            margin-left: 8px;
        }

        /* Kontaktni ikony */
        .transport-kontakt {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: #222;
            border-radius: 50%;
            color: #888;
            text-decoration: none;
            transition: all 0.2s;
        }

        .transport-kontakt svg {
            width: 20px;
            height: 20px;
        }

        .transport-kontakt:hover {
            background: #444;
            color: #fff;
        }

        /* Stav */
        .stav-cas {
            font-size: 10px;
            color: #666;
            margin-top: 4px;
        }

        .stav-btn {
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 22px;
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
            max-height: 90vh;
            overflow-y: auto;
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

        .modal-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        .modal-select option {
            background: #222;
            color: #fff;
            padding: 10px;
        }

        .modal-textarea {
            resize: none;
            min-height: 60px;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.4;
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

        /* Let - vyhledavani */
        .let-input-wrapper {
            display: flex;
            gap: 8px;
        }

        .let-input-wrapper .modal-input {
            flex: 1;
        }

        .btn-hledat-let {
            background: #333;
            color: #fff;
            border: 1px solid #444;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-hledat-let:hover {
            background: #444;
            border-color: #555;
        }

        .btn-hledat-let:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-hledat-let.nacitam {
            background: #222;
            color: #666;
        }

        .let-info {
            margin-top: 8px;
            padding: 10px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 6px;
            font-size: 12px;
            display: none;
        }

        .let-info.aktivni {
            display: block;
        }

        .let-info.chyba {
            border-color: #ff4444;
            color: #ff6666;
        }

        .let-info.uspech {
            border-color: #39ff14;
        }

        .let-info-radek {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            color: #888;
        }

        .let-info-radek:last-child {
            margin-bottom: 0;
        }

        .let-info-hodnota {
            color: #fff;
            font-weight: 600;
        }

        .let-info-cas {
            color: #39ff14;
            font-size: 16px;
            font-weight: 700;
        }

        .let-info-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .let-info-status.scheduled { background: #333; color: #888; }
        .let-info-status.active { background: #fff; color: #000; }
        .let-info-status.landed { background: #39ff14; color: #000; }
        .let-info-status.cancelled { background: #ff4444; color: #fff; }
        .let-info-status.delayed { background: #ff8800; color: #000; }

        /* WGS Info Overlay pro let */
        .wgs-let-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .wgs-let-overlay.aktivni {
            display: flex;
        }

        .wgs-let-box {
            background: #111;
            border: 2px solid #39ff14;
            border-radius: 12px;
            padding: 25px;
            width: 100%;
            max-width: 360px;
            box-shadow:
                0 0 15px rgba(57, 255, 20, 0.4),
                0 0 30px rgba(57, 255, 20, 0.2),
                0 0 45px rgba(57, 255, 20, 0.1);
            animation: wgsLetSlideIn 0.3s ease;
        }

        @keyframes wgsLetSlideIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .wgs-let-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #333;
        }

        .wgs-let-cislo {
            font-size: 28px;
            font-weight: 700;
            color: #39ff14;
            text-shadow: 0 0 10px rgba(57, 255, 20, 0.5);
        }

        .wgs-let-status {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .wgs-let-status.scheduled { background: #333; color: #888; }
        .wgs-let-status.active { background: #fff; color: #000; }
        .wgs-let-status.landed { background: #39ff14; color: #000; }
        .wgs-let-status.delayed { background: #ff8800; color: #000; }
        .wgs-let-status.cancelled { background: #ff4444; color: #fff; }

        .wgs-let-aerolinky {
            font-size: 14px;
            color: #888;
            margin-bottom: 20px;
        }

        .wgs-let-radek {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #222;
            font-size: 14px;
        }

        .wgs-let-radek:last-child {
            border-bottom: none;
        }

        .wgs-let-label {
            color: #666;
        }

        .wgs-let-hodnota {
            color: #fff;
            font-weight: 600;
        }

        .wgs-let-cas-prilet {
            font-size: 32px;
            font-weight: 700;
            color: #39ff14;
            text-align: center;
            margin: 20px 0;
            text-shadow: 0 0 15px rgba(57, 255, 20, 0.5);
        }

        .wgs-let-zavrit {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: #222;
            border: 1px solid #444;
            border-radius: 8px;
            color: #888;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .wgs-let-zavrit:hover {
            background: #333;
            color: #fff;
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
                grid-row: 6;
            }
        }

        /* Excel nahled */
        .excel-item {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        .excel-item-info {
            flex: 1;
        }

        .excel-item-cas {
            font-weight: 700;
            color: #fff;
        }

        .excel-item-jmeno {
            color: #ccc;
        }

        .excel-item-trasa {
            color: #666;
            font-size: 11px;
        }

        .excel-item-checkbox {
            margin-left: 10px;
        }

        .excel-item-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Confirm modal */
        .transport-confirm-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }

        .transport-confirm-modal {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
        }

        .transport-confirm-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
        }

        .transport-confirm-message {
            font-size: 14px;
            color: #ccc;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .transport-confirm-input {
            width: 100%;
            padding: 10px 12px;
            background: #222;
            border: 1px solid #444;
            border-radius: 4px;
            color: #fff;
            font-size: 14px;
            margin-bottom: 16px;
            box-sizing: border-box;
        }

        .transport-confirm-input:focus {
            outline: none;
            border-color: #666;
        }

        .transport-confirm-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .transport-confirm-btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .transport-confirm-btn-zrusit {
            background: #333;
            color: #fff;
        }

        .transport-confirm-btn-zrusit:hover {
            background: #444;
        }

        .transport-confirm-btn-potvrdit {
            background: #28a745;
            color: #fff;
        }

        .transport-confirm-btn-potvrdit:hover {
            background: #218838;
        }

        .transport-confirm-btn-potvrdit.nebezpecne {
            background: #dc3545;
        }

        .transport-confirm-btn-potvrdit.nebezpecne:hover {
            background: #c82333;
        }
    </style>
    <!-- SheetJS pro parsovani Excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="logo-wgs">WGS</div>

    <!-- Editovatelny nazev -->
    <div class="header-title" id="header-title" onclick="editovatNazev()">TRANSPORT</div>

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

<!-- Tlačítka pro řidiče a dokončené -->
<div class="hlavni-tlacitka">
    <button class="btn-ridici" onclick="otevriModalRidici()" data-i18n="drivers">Ridici</button>
    <button class="btn-ridici btn-dokoncene" onclick="otevriModalDokoncene()">Dokoncene <span id="pocet-dokoncenych"></span></button>
    <button class="btn-ridici btn-excel" onclick="otevriModalNahrat()" id="btn-excel-hlavni">Aktualizovat z Excelu</button>
</div>

<!-- Modal se seznamem řidičů -->
<div class="modal" id="modal-ridici-seznam">
    <div class="modal-obsah modal-ridici-obsah">
        <div class="modal-titulek" data-i18n="drivers">Ridici</div>
        <div class="ridici" id="ridici-kontejner">
            <!-- Ridici se vykresli JavaScriptem -->
        </div>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-zrusit" onclick="zavriModalRidici()" data-i18n="close">Zavrit</button>
        </div>
    </div>
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

<!-- Modal pro dokončené transporty -->
<div class="modal" id="modal-dokoncene">
    <div class="modal-obsah" style="max-width: 500px;">
        <div class="modal-titulek">Dokoncene transporty</div>
        <div id="dokoncene-kontejner" style="max-height: 60vh; overflow-y: auto;">
            <!-- Dokončené transporty se vykreslí JavaScriptem -->
        </div>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-potvrdit" onclick="exportDokoncenych()" style="background:#333;">Export PDF</button>
            <button class="modal-btn modal-btn-zrusit" onclick="zavriModalDokoncene()">Zavrit</button>
        </div>
    </div>
</div>

<!-- Modal pro seznam transportů řidiče -->
<div class="modal" id="modal-seznam-ridice">
    <div class="modal-obsah" style="max-width: 500px;">
        <div class="modal-titulek" id="modal-seznam-ridice-titulek">Seznam</div>
        <div id="seznam-transportu-ridice" style="max-height: 60vh; overflow-y: auto;">
            <!-- Transporty se vykreslí JavaScriptem -->
        </div>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-zrusit" onclick="zavriModalSeznamRidice()">Zpet</button>
        </div>
    </div>
</div>

<!-- Modal pro nahrání Excel souboru -->
<div class="modal" id="modal-nahrat">
    <div class="modal-obsah" style="max-width: 600px;">
        <div class="modal-titulek">Nahrat Excel</div>

        <div class="modal-pole">
            <label class="modal-label">Vyberte soubor (.xls, .xlsx)</label>
            <input type="file" class="modal-input" id="input-excel" accept=".xls,.xlsx" onchange="zpracujExcel(this)">
        </div>

        <div id="excel-nahled" style="display: none;">
            <div class="modal-label" style="margin-top: 15px;">Nahled (<span id="excel-pocet">0</span> zaznamu)</div>
            <div id="excel-seznam" style="max-height: 300px; overflow-y: auto; margin: 10px 0;"></div>
        </div>

        <div id="excel-chyba" style="display: none; color: #ff4444; padding: 10px; background: #331111; border-radius: 6px; margin: 10px 0;"></div>

        <div class="modal-btns">
            <button class="modal-btn modal-btn-zrusit" onclick="zavriModalNahrat()">Zrusit</button>
            <button class="modal-btn modal-btn-potvrdit" id="btn-importovat" onclick="importovatExcel()" style="display: none;">Importovat</button>
        </div>
    </div>
</div>

<!-- Dynamické sekce dnů - generováno JavaScriptem -->
<div id="dny-kontejner"></div>

<!-- Tlačítko pro ruční přidání (dole) -->
<div style="text-align: center; margin: 20px 0;">
    <button class="btn-pridat" onclick="otevriModalPridat(null)" style="padding: 10px 20px;">+ Pridat transport</button>
</div>

<!-- WGS Let Info Overlay -->
<div class="wgs-let-overlay" id="wgs-let-overlay" onclick="zavriLetOverlay(event)">
    <div class="wgs-let-box" onclick="event.stopPropagation()">
        <div class="wgs-let-header">
            <div class="wgs-let-cislo" id="wgs-let-cislo">OK123</div>
            <div class="wgs-let-status" id="wgs-let-status">SCHEDULED</div>
        </div>
        <div class="wgs-let-aerolinky" id="wgs-let-aerolinky">Czech Airlines</div>
        <div class="wgs-let-cas-prilet" id="wgs-let-cas-prilet">21:30</div>
        <div class="wgs-let-radek">
            <span class="wgs-let-label">Z letiste</span>
            <span class="wgs-let-hodnota" id="wgs-let-odkud">Paris CDG</span>
        </div>
        <div class="wgs-let-radek">
            <span class="wgs-let-label">Na letiste</span>
            <span class="wgs-let-hodnota" id="wgs-let-kam">Praha PRG</span>
        </div>
        <div class="wgs-let-radek" id="wgs-let-terminal-row">
            <span class="wgs-let-label">Terminal</span>
            <span class="wgs-let-hodnota" id="wgs-let-terminal">T2</span>
        </div>
        <div class="wgs-let-radek" id="wgs-let-bagaz-row">
            <span class="wgs-let-label">Zavazadla</span>
            <span class="wgs-let-hodnota" id="wgs-let-bagaz">Pas 5</span>
        </div>
        <button class="wgs-let-zavrit" onclick="zavriLetOverlay()">Zavrit</button>
    </div>
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

        <div class="modal-pole" id="pole-let">
            <label class="modal-label">Cislo letu (volitelne)</label>
            <div class="let-input-wrapper">
                <input type="text" class="modal-input" id="input-let" placeholder="napr. OK123, FR1234" style="text-transform: uppercase;">
                <button type="button" class="btn-hledat-let" id="btn-hledat-let" onclick="hledejLet()">Hledat</button>
            </div>
            <div class="let-info" id="let-info"></div>
        </div>

        <div class="modal-pole" id="pole-cas">
            <label class="modal-label">Cas</label>
            <input type="text" class="modal-input" id="input-cas" placeholder="napr. 21:30" inputmode="numeric" pattern="[0-9:]*">
        </div>

        <div class="modal-pole" id="pole-jmeno">
            <label class="modal-label">Jmeno</label>
            <input type="text" class="modal-input" id="input-jmeno" placeholder="Jmeno pasazera">
        </div>

        <div class="modal-pole" id="pole-telefon">
            <label class="modal-label">Telefon (volitelne)</label>
            <input type="tel" class="modal-input" id="input-telefon" placeholder="napr. +420123456789">
        </div>

        <div class="modal-pole" id="pole-email">
            <label class="modal-label">Email (volitelne)</label>
            <input type="email" class="modal-input" id="input-email" placeholder="napr. pasazer@email.com">
        </div>

        <div class="modal-pole" id="pole-odkud">
            <label class="modal-label">Odkud</label>
            <input type="text" class="modal-input" id="input-odkud" placeholder="Misto vyzvednut">
        </div>

        <div class="modal-pole" id="pole-kam">
            <label class="modal-label">Kam</label>
            <input type="text" class="modal-input" id="input-kam" placeholder="Cilove misto">
        </div>

        <div class="modal-pole" id="pole-ridic">
            <label class="modal-label">Ridic</label>
            <select class="modal-input modal-select" id="input-ridic">
                <option value="">-- Neprirazeno --</option>
            </select>
        </div>

        <div class="modal-pole" id="pole-pocet">
            <label class="modal-label">Pocet osob</label>
            <input type="text" class="modal-input" id="input-pocet" placeholder="napr. 2" inputmode="numeric" pattern="[0-9]*">
        </div>

        <div class="modal-pole" id="pole-poznamka">
            <label class="modal-label">Poznamka</label>
            <textarea class="modal-input modal-textarea" id="input-poznamka" placeholder="Zvlastni pozadavky, informace..." rows="2"></textarea>
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

// Nazev stranky (ulozeny v localStorage)
function nactiNazev() {
    const ulozenyNazev = localStorage.getItem('transportNazev');
    if (ulozenyNazev) {
        document.getElementById('header-title').textContent = ulozenyNazev;
    }
}

// Editovat nazev (s heslem)
async function editovatNazev() {
    const hesloVysledek = await transportConfirm('Zadejte heslo pro editaci', {
        titulek: 'Overeni',
        btnPotvrdit: 'Pokracovat',
        heslo: true
    });

    if (!hesloVysledek.potvrzeno) return;

    if (hesloVysledek.heslo !== HESLO) {
        await transportConfirm('Spatne heslo!', {
            titulek: 'Chyba',
            btnPotvrdit: 'OK',
            btnZrusit: null,
            nebezpecne: true
        });
        return;
    }

    // Heslo spravne - zobrazit input pro novy nazev
    const aktualniNazev = document.getElementById('header-title').textContent;
    const nazevVysledek = await transportConfirm('Zadejte novy nazev', {
        titulek: 'Upravit nazev',
        btnPotvrdit: 'Ulozit',
        input: true,
        inputValue: aktualniNazev
    });

    if (nazevVysledek.potvrzeno && nazevVysledek.hodnota && nazevVysledek.hodnota.trim()) {
        document.getElementById('header-title').textContent = nazevVysledek.hodnota.trim();
        localStorage.setItem('transportNazev', nazevVysledek.hodnota.trim());
    }
}

// Confirm modal funkce (podobna wgsConfirm)
function transportConfirm(zprava, options = {}) {
    return new Promise((resolve) => {
        const {
            titulek = 'Potvrzeni',
            btnPotvrdit = 'Potvrdit',
            btnZrusit = 'Zrusit',
            nebezpecne = false,
            heslo = false,
            input = false,
            inputValue = '',
            inputPlaceholder = ''
        } = options;

        const overlay = document.createElement('div');
        overlay.className = 'transport-confirm-overlay';

        let inputHtml = '';
        if (heslo) {
            inputHtml = `<input type="password" class="transport-confirm-input" placeholder="Zadejte heslo" id="confirm-input">`;
        } else if (input) {
            inputHtml = `<input type="text" class="transport-confirm-input" placeholder="${inputPlaceholder}" value="${inputValue}" id="confirm-input">`;
        }

        let zrusitBtn = '';
        if (btnZrusit !== null) {
            zrusitBtn = `<button class="transport-confirm-btn transport-confirm-btn-zrusit" id="confirm-zrusit">${btnZrusit}</button>`;
        }

        overlay.innerHTML = `
            <div class="transport-confirm-modal">
                <div class="transport-confirm-title">${titulek}</div>
                <div class="transport-confirm-message">${zprava}</div>
                ${inputHtml}
                <div class="transport-confirm-buttons">
                    ${zrusitBtn}
                    <button class="transport-confirm-btn transport-confirm-btn-potvrdit ${nebezpecne ? 'nebezpecne' : ''}" id="confirm-potvrdit">${btnPotvrdit}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        const inputEl = document.getElementById('confirm-input');
        if (inputEl) {
            inputEl.focus();
            if (input) inputEl.select();
            inputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    document.getElementById('confirm-potvrdit').click();
                }
            });
        }

        const zavrit = (vysledek) => {
            overlay.remove();
            resolve(vysledek);
        };

        document.getElementById('confirm-potvrdit').addEventListener('click', () => {
            if (heslo) {
                const zadaneHeslo = inputEl?.value || '';
                zavrit({ potvrzeno: true, heslo: zadaneHeslo });
            } else if (input) {
                const hodnota = inputEl?.value || '';
                zavrit({ potvrzeno: true, hodnota: hodnota });
            } else {
                zavrit({ potvrzeno: true });
            }
        });

        const zrusitEl = document.getElementById('confirm-zrusit');
        if (zrusitEl) {
            zrusitEl.addEventListener('click', () => zavrit({ potvrzeno: false }));
        }

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                zavrit({ potvrzeno: false });
            }
        });
    });
}

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

// Dokončené transporty (po DROP)
let dokoncene = [];

// Timery pro automatický přesun do dokončených
let dropTimery = {};

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
        // Spočítat počet přiřazených transportů
        const pocetTransportu = spocitatTransportyRidice(ridic.id);

        const div = document.createElement('div');
        div.className = 'ridic';
        div.onclick = (e) => {
            if (e.target.closest('.ridic-tel') || e.target.closest('.ridic-menu-btn')) return;
            prepniMenuRidice(ridic.id);
        };
        div.innerHTML = `
            <div class="ridic-auto-svg">
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
            <div class="ridic-menu" id="ridic-menu-${ridic.id}">
                <button class="ridic-menu-btn" onclick="event.stopPropagation(); zavriVsechnyMenuRidicu(); otevriModalRidic('${ridic.id}')">Upravit</button>
                <button class="ridic-menu-btn" onclick="event.stopPropagation(); zavriVsechnyMenuRidicu(); zobrazSeznamTransportuRidice('${ridic.id}')">Seznam ${pocetTransportu > 0 ? '(' + pocetTransportu + ')' : ''}</button>
            </div>
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

// Přepnout menu řidiče
function prepniMenuRidice(ridicId) {
    const menu = document.getElementById('ridic-menu-' + ridicId);
    if (!menu) return;

    const jeOtevreno = menu.classList.contains('aktivni');
    zavriVsechnyMenuRidicu();

    if (!jeOtevreno) {
        menu.classList.add('aktivni');
    }
}

// Zavřít všechny menu řidičů
function zavriVsechnyMenuRidicu() {
    document.querySelectorAll('.ridic-menu').forEach(menu => {
        menu.classList.remove('aktivni');
    });
}

// Spočítat transporty přiřazené řidiči
function spocitatTransportyRidice(ridicId) {
    let pocet = 0;
    Object.keys(transporty).forEach(datum => {
        transporty[datum]?.forEach(transport => {
            if (transport.ridicId === ridicId || stavy[transport.id]?.ridic === ridicId) {
                pocet++;
            }
        });
    });
    return pocet;
}

// Získat transporty přiřazené řidiči
function ziskatTransportyRidice(ridicId) {
    const seznam = [];
    Object.keys(transporty).forEach(datum => {
        transporty[datum]?.forEach(transport => {
            if (transport.ridicId === ridicId || stavy[transport.id]?.ridic === ridicId) {
                seznam.push({
                    ...transport,
                    datum: datum,
                    stav: stavy[transport.id]?.stav || 'wait'
                });
            }
        });
    });
    // Seřadit podle data a času
    return seznam.sort((a, b) => {
        const datumA = a.datum + ' ' + a.cas;
        const datumB = b.datum + ' ' + b.cas;
        return datumA.localeCompare(datumB);
    });
}

// Zobrazit seznam transportů řidiče
function zobrazSeznamTransportuRidice(ridicId) {
    const ridic = ridici.find(r => r.id === ridicId);
    if (!ridic) return;

    const transportyRidice = ziskatTransportyRidice(ridicId);

    // Aktualizovat titulek
    document.getElementById('modal-seznam-ridice-titulek').textContent = ridic.jmeno + ' - Seznam';

    // Vykreslit transporty
    const kontejner = document.getElementById('seznam-transportu-ridice');

    if (transportyRidice.length === 0) {
        kontejner.innerHTML = '<div style="text-align: center; color: #666; padding: 20px;">Zadne prirazene transporty</div>';
    } else {
        kontejner.innerHTML = transportyRidice.map(t => {
            const stavText = t.stav === 'wait' ? 'CEKA' : (t.stav === 'onway' ? 'CESTA' : 'DROP');
            const stavClass = t.stav === 'wait' ? '' : (t.stav === 'onway' ? 'stav-onway-mini' : 'stav-drop-mini');
            return `
                <div class="seznam-transport-item">
                    <div class="seznam-transport-info">
                        <div class="seznam-transport-cas">${t.cas}</div>
                        <div class="seznam-transport-jmeno">${t.jmeno}</div>
                        <div class="seznam-transport-trasa">${t.odkud} → ${t.kam}</div>
                    </div>
                    <div class="seznam-transport-stav ${stavClass}">${stavText}</div>
                </div>
            `;
        }).join('');
    }

    // Zavřít modal řidičů a otevřít seznam
    zavriModalRidici();
    document.getElementById('modal-seznam-ridice').classList.add('aktivni');
}

// Zavřít modal seznamu transportů řidiče
function zavriModalSeznamRidice() {
    document.getElementById('modal-seznam-ridice').classList.remove('aktivni');
    // Znovu otevřít modal řidičů
    otevriModalRidici();
}

// Otevřít modal se seznamem řidičů
function otevriModalRidici() {
    vykresliRidice();
    document.getElementById('modal-ridici-seznam').classList.add('aktivni');
}

// Zavřít modal se seznamem řidičů
function zavriModalRidici() {
    document.getElementById('modal-ridici-seznam').classList.remove('aktivni');
}

function zavriModalRidic() {
    document.getElementById('modal-ridic').classList.remove('aktivni');
}

// Otevřít modal s dokončenými transporty
function otevriModalDokoncene() {
    vykresliDokoncene();
    document.getElementById('modal-dokoncene').classList.add('aktivni');
}

// Zavřít modal s dokončenými
function zavriModalDokoncene() {
    document.getElementById('modal-dokoncene').classList.remove('aktivni');
}

// ===== NAHRANI EXCEL =====
// Data z Excel souboru
let excelData = [];

// Otevrit modal pro nahrani
function otevriModalNahrat() {
    document.getElementById('input-excel').value = '';
    document.getElementById('excel-nahled').style.display = 'none';
    document.getElementById('excel-chyba').style.display = 'none';
    document.getElementById('btn-importovat').style.display = 'none';
    excelData = [];
    document.getElementById('modal-nahrat').classList.add('aktivni');
}

// Zavrit modal nahrani
function zavriModalNahrat() {
    document.getElementById('modal-nahrat').classList.remove('aktivni');
}

// Zpracovat Excel soubor
function zpracujExcel(input) {
    const soubor = input.files[0];
    if (!soubor) return;

    document.getElementById('excel-chyba').style.display = 'none';
    document.getElementById('excel-nahled').style.display = 'none';

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });

            // Prvni list
            const listName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[listName];

            // Prevest na JSON
            const json = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

            if (json.length < 2) {
                zobrazExcelChybu('Soubor neobsahuje zadna data');
                return;
            }

            // Hlavicka - najit indexy sloupcu
            const hlavicka = json[0];
            console.log('Excel hlavicka:', hlavicka);

            // Mapovani sloupcu z Excel souboru
            const sloupce = {
                prijmeni: najdiSloupec(hlavicka, ['LASTNAME', 'PRIJMENI', 'SURNAME']),
                jmeno: najdiSloupec(hlavicka, ['FIRSTNAME', 'JMENO', 'FIRST NAME']),
                email: najdiSloupec(hlavicka, ['CONTACT EMAIL', 'EMAIL', 'E-MAIL']),
                datum: najdiSloupec(hlavicka, ['ARRIVAL FLIGHT/TRAIN DATE/TIME', 'ARRIVAL DATE/TIME', 'ARRIVAL']),
                odkud: najdiSloupec(hlavicka, ['PICK-UP LOCATION', 'PICKUP LOCATION']),
                kam: najdiSloupec(hlavicka, ['DROP-OFF LOCATION', 'DROPOFF LOCATION']),
                let: najdiSloupec(hlavicka, ['FLIGHT/TRAIN NUMBER', 'FLIGHT NUMBER', 'FLIGHT NO']),
                pocet: najdiSloupec(hlavicka, ['PAX', 'POCET', 'PASSENGERS']),
                telefon: najdiSloupec(hlavicka, ['CONTACT PHONE', 'PHONE', 'TELEFON'])
            };

            console.log('Mapovani sloupcu:', sloupce);

            // Kontrola povinnych sloupcu
            if (sloupce.jmeno < 0 && sloupce.prijmeni < 0) {
                zobrazExcelChybu('Excel neobsahuje sloupec FIRSTNAME ani LASTNAME');
                return;
            }

            // Zpracovat radky
            excelData = [];
            for (let i = 1; i < json.length; i++) {
                const radek = json[i];
                if (!radek || radek.length === 0) continue;

                // Jmeno: FIRSTNAME + LASTNAME
                const jmenoVal = sloupce.jmeno >= 0 ? String(radek[sloupce.jmeno] || '').trim() : '';
                const prijmeniVal = sloupce.prijmeni >= 0 ? String(radek[sloupce.prijmeni] || '').trim() : '';
                const jmeno = (jmenoVal + ' ' + prijmeniVal).trim();

                if (!jmeno) continue; // Preskocit prazdne radky

                // Datum a cas: "30/04/2022 19:00" nebo Excel datum
                let datum = '';
                let cas = '';
                const datumCas = radek[sloupce.datum];

                if (datumCas) {
                    const datumStr = String(datumCas);
                    // Zkusit format "DD/MM/YYYY HH:MM"
                    const datumMatch = datumStr.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*(\d{1,2}):(\d{2})/);
                    if (datumMatch) {
                        datum = `${datumMatch[3]}-${datumMatch[2].padStart(2, '0')}-${datumMatch[1].padStart(2, '0')}`;
                        cas = `${datumMatch[4].padStart(2, '0')}:${datumMatch[5]}`;
                    }
                }

                // Lokace
                const odkud = sloupce.odkud >= 0 ? String(radek[sloupce.odkud] || '').trim() : '';
                const kam = sloupce.kam >= 0 ? String(radek[sloupce.kam] || '').trim() : '';

                // Cislo letu
                let cisloLetu = '';
                if (sloupce.let >= 0 && radek[sloupce.let]) {
                    cisloLetu = String(radek[sloupce.let]).trim();
                }

                // Pocet osob
                const pocetOsob = sloupce.pocet >= 0 ? (parseInt(radek[sloupce.pocet]) || 1) : 1;

                // Kontakty
                const telefon = sloupce.telefon >= 0 ? String(radek[sloupce.telefon] || '').trim() : '';
                const email = sloupce.email >= 0 ? String(radek[sloupce.email] || '').trim() : '';

                excelData.push({
                    jmeno,
                    datum,
                    cas,
                    odkud,
                    kam,
                    cisloLetu,
                    pocetOsob,
                    telefon,
                    email,
                    vybrano: true
                });
            }

            if (excelData.length === 0) {
                zobrazExcelChybu('Nepodarilo se najit platna data');
                return;
            }

            // Zobrazit nahled
            zobrazExcelNahled();

        } catch (err) {
            console.error('Chyba pri cteni Excel:', err);
            zobrazExcelChybu('Chyba pri cteni souboru: ' + err.message);
        }
    };

    reader.readAsArrayBuffer(soubor);
}

// Najit sloupec podle PRESNEHO nazvu (bez includes)
function najdiSloupecPresne(hlavicka, nazvy) {
    for (let i = 0; i < hlavicka.length; i++) {
        const val = String(hlavicka[i] || '').toUpperCase().trim();
        for (const nazev of nazvy) {
            if (val === nazev.toUpperCase()) {
                return i;
            }
        }
    }
    return -1;
}

// Najit sloupec podle moznych nazvu (vcetne castecne shody)
function najdiSloupec(hlavicka, nazvy) {
    // Nejprve zkusit presnou shodu
    const presny = najdiSloupecPresne(hlavicka, nazvy);
    if (presny >= 0) return presny;

    // Pak zkusit castecnou shodu (includes)
    for (let i = 0; i < hlavicka.length; i++) {
        const val = String(hlavicka[i] || '').toUpperCase().trim();
        for (const nazev of nazvy) {
            if (val.includes(nazev.toUpperCase())) {
                return i;
            }
        }
    }
    return -1;
}

// Zobrazit chybu
function zobrazExcelChybu(zprava) {
    document.getElementById('excel-chyba').textContent = zprava;
    document.getElementById('excel-chyba').style.display = 'block';
    document.getElementById('excel-nahled').style.display = 'none';
    document.getElementById('btn-importovat').style.display = 'none';
}

// Zobrazit nahled dat
// Zjistit status polozky pro nahled (NOVY/ZMENA/EXISTUJE/DOKONCENO)
function zjistiStatusPolozky(item) {
    const datum = item.datum || new Date().toISOString().split('T')[0];
    const novyTransport = {
        cas: item.cas || '00:00',
        jmeno: item.jmeno,
        odkud: item.odkud || '',
        kam: item.kam || '',
        cisloLetu: item.cisloLetu || null,
        pocetOsob: item.pocetOsob || null,
        datum: datum
    };

    const existujici = najdiExistujiciTransport(item.jmeno);

    if (!existujici) {
        return { status: 'novy', text: 'NOVY', barva: '#39ff14' };
    }

    if (existujici.typ === 'dokonceny') {
        return { status: 'dokonceno', text: 'HOTOVO', barva: '#666' };
    }

    if (transportSeZmenil(existujici.transport, novyTransport)) {
        // Zjistit co se zmenilo
        const zmeny = [];
        if (existujici.transport.cas !== novyTransport.cas) {
            zmeny.push(`cas: ${existujici.transport.cas} -> ${novyTransport.cas}`);
        }
        if (existujici.transport.cisloLetu !== novyTransport.cisloLetu) {
            zmeny.push(`let: ${existujici.transport.cisloLetu || '-'} -> ${novyTransport.cisloLetu || '-'}`);
        }
        if (existujici.transport.datum !== novyTransport.datum) {
            zmeny.push(`datum zmenen`);
        }
        return { status: 'zmena', text: 'ZMENA', barva: '#ff8800', detail: zmeny.join(', ') };
    }

    return { status: 'existuje', text: 'BEZ ZMENY', barva: '#555' };
}

function zobrazExcelNahled() {
    const kontejner = document.getElementById('excel-seznam');

    // Debug - prvni zaznam
    if (excelData.length > 0) {
        console.log('Prvni zaznam:', excelData[0]);
    }

    // Spocitat statistiky
    let pocetNovych = 0;
    let pocetZmen = 0;
    let pocetExistuje = 0;
    let pocetDokoncenych = 0;

    excelData.forEach(item => {
        const statusInfo = zjistiStatusPolozky(item);
        item._status = statusInfo;
        if (statusInfo.status === 'novy') pocetNovych++;
        else if (statusInfo.status === 'zmena') pocetZmen++;
        else if (statusInfo.status === 'existuje') pocetExistuje++;
        else if (statusInfo.status === 'dokonceno') pocetDokoncenych++;
    });

    // Zobrazit souhrn
    document.getElementById('excel-pocet').innerHTML = `${excelData.length} zaznamu: <span style="color:#39ff14">${pocetNovych} novych</span>${pocetZmen > 0 ? `, <span style="color:#ff8800">${pocetZmen} zmen</span>` : ''}${pocetExistuje > 0 ? `, <span style="color:#555">${pocetExistuje} bez zmeny</span>` : ''}${pocetDokoncenych > 0 ? `, <span style="color:#666">${pocetDokoncenych} hotovych</span>` : ''}`;

    kontejner.innerHTML = excelData.map((item, index) => {
        const statusInfo = item._status;
        const statusBadge = `<span style="display:inline-block; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; background:${statusInfo.barva}; color:#fff; margin-left:8px;">${statusInfo.text}</span>`;

        return `
        <div class="excel-item" style="${statusInfo.status === 'existuje' || statusInfo.status === 'dokonceno' ? 'opacity:0.5;' : ''}">
            <div class="excel-item-info">
                <div><span class="excel-item-cas">${item.cas || '?'}</span> <span class="excel-item-jmeno">${item.jmeno}</span>${statusBadge}</div>
                <div class="excel-item-trasa">${item.odkud || '?'} → ${item.kam || '?'}</div>
                ${item.cisloLetu ? `<div style="color: #39ff14; font-weight: 600;">Let: ${item.cisloLetu}</div>` : ''}
                ${statusInfo.detail ? `<div style="color: #ff8800; font-size: 11px;">${statusInfo.detail}</div>` : ''}
            </div>
            <div class="excel-item-checkbox">
                <input type="checkbox" ${item.vybrano && statusInfo.status !== 'existuje' && statusInfo.status !== 'dokonceno' ? 'checked' : ''} onchange="excelData[${index}].vybrano = this.checked" ${statusInfo.status === 'dokonceno' ? 'disabled' : ''}>
            </div>
        </div>
    `}).join('');

    document.getElementById('excel-nahled').style.display = 'block';
    document.getElementById('btn-importovat').style.display = 'block';
}

// Normalizovat jmeno pro porovnani (lowercase, bez diakritiky, bez extra mezer)
function normalizujJmeno(jmeno) {
    if (!jmeno) return '';
    return jmeno
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Odstranit diakritiku
        .replace(/\s+/g, ' ')
        .trim();
}

// Najit existujici transport podle jmena (ve vsech datech a dokoncenech)
function najdiExistujiciTransport(jmenoHledane) {
    const normalJmeno = normalizujJmeno(jmenoHledane);

    // Hledat v aktivnich transportech
    for (const datum in transporty) {
        for (const transport of transporty[datum]) {
            if (normalizujJmeno(transport.jmeno) === normalJmeno) {
                return { transport, datum, typ: 'aktivni' };
            }
        }
    }

    // Hledat v dokoncenech
    for (const transport of dokoncene) {
        if (normalizujJmeno(transport.jmeno) === normalJmeno) {
            return { transport, datum: transport.datum, typ: 'dokonceny' };
        }
    }

    return null;
}

// Porovnat zda se data zmenila
function transportSeZmenil(stary, novy) {
    // Porovnat dulezite pole
    if (stary.cas !== novy.cas) return true;
    if (stary.cisloLetu !== novy.cisloLetu) return true;
    if (stary.odkud !== novy.odkud) return true;
    if (stary.kam !== novy.kam) return true;
    if (stary.datum !== novy.datum) return true;
    if ((stary.pocetOsob || 1) !== (novy.pocetOsob || 1)) return true;
    return false;
}

// Importovat vybrana data s detekci duplicit
async function importovatExcel() {
    const kImportu = excelData.filter(item => item.vybrano);

    if (kImportu.length === 0) {
        zobrazExcelChybu('Nevybrano zadne zaznamy k importu');
        return;
    }

    let pocetNovych = 0;
    let pocetAktualizovanych = 0;
    let pocetPreskocenych = 0;
    let pocetDokoncenych = 0;
    const zmeny = [];

    for (const item of kImportu) {
        const datum = item.datum || new Date().toISOString().split('T')[0];
        const novyTransport = {
            cas: item.cas || '00:00',
            jmeno: item.jmeno,
            telefon: item.telefon || null,
            email: item.email || null,
            odkud: item.odkud || '',
            kam: item.kam || '',
            cisloLetu: item.cisloLetu || null,
            pocetOsob: item.pocetOsob || null,
            datum: datum
        };

        // Hledat existujici
        const existujici = najdiExistujiciTransport(item.jmeno);

        if (existujici) {
            if (existujici.typ === 'dokonceny') {
                // Uz je dokonceny - preskocit
                pocetDokoncenych++;
                continue;
            }

            // Porovnat zda se neco zmenilo
            if (transportSeZmenil(existujici.transport, novyTransport)) {
                // Aktualizovat existujici
                const staryDatum = existujici.datum;
                const stareData = { ...existujici.transport };

                // Pokud se zmenilo datum, presunout
                if (staryDatum !== datum) {
                    // Odstranit ze stareho data
                    const indexStary = transporty[staryDatum]?.findIndex(t => t.id === existujici.transport.id);
                    if (indexStary >= 0) {
                        transporty[staryDatum].splice(indexStary, 1);
                        if (transporty[staryDatum].length === 0) {
                            delete transporty[staryDatum];
                        }
                    }

                    // Pridat do noveho data
                    if (!transporty[datum]) {
                        transporty[datum] = [];
                    }
                    transporty[datum].push({
                        ...existujici.transport,
                        ...novyTransport
                    });
                } else {
                    // Aktualizovat na miste
                    Object.assign(existujici.transport, novyTransport);
                }

                pocetAktualizovanych++;
                zmeny.push(`${item.jmeno}: ${stareData.cas} -> ${novyTransport.cas}${stareData.cisloLetu !== novyTransport.cisloLetu ? ', let: ' + (novyTransport.cisloLetu || 'zadny') : ''}`);
            } else {
                // Zadna zmena - preskocit
                pocetPreskocenych++;
            }
        } else {
            // Novy transport
            if (!transporty[datum]) {
                transporty[datum] = [];
            }

            transporty[datum].push({
                id: generujId(),
                ...novyTransport,
                ridic: null
            });
            pocetNovych++;
        }
    }

    // Ulozit a aktualizovat
    await ulozData();
    vykresli();
    zavriModalNahrat();

    // Zobrazit souhrn
    let zprava = `Import dokoncen:\n`;
    zprava += `- Novych: ${pocetNovych}\n`;
    zprava += `- Aktualizovanych: ${pocetAktualizovanych}\n`;
    zprava += `- Preskocenych (beze zmeny): ${pocetPreskocenych}\n`;
    if (pocetDokoncenych > 0) {
        zprava += `- Uz dokoncenych: ${pocetDokoncenych}\n`;
    }

    if (zmeny.length > 0 && zmeny.length <= 10) {
        zprava += `\nZmeny:\n${zmeny.join('\n')}`;
    }

    alert(zprava);
}

// Vykreslit dokončené transporty
function vykresliDokoncene() {
    const kontejner = document.getElementById('dokoncene-kontejner');

    if (dokoncene.length === 0) {
        kontejner.innerHTML = '<div style="text-align: center; color: #666; padding: 20px;">Zadne dokoncene transporty</div>';
        return;
    }

    // Seřadit od nejnovějších
    const serazene = [...dokoncene].reverse();

    kontejner.innerHTML = serazene.map((item, index) => {
        const ridic = item.ridicId ? ridici.find(r => r.id === item.ridicId) : null;
        const ridicJmeno = ridic ? ridic.jmeno : '';
        // Index v původním poli (ne v seřazeném)
        const origIndex = dokoncene.length - 1 - index;

        return `
            <div class="dokonceny-item">
                <div class="dokonceny-info">
                    <div class="dokonceny-jmeno">${item.jmeno}</div>
                    <div class="dokonceny-meta">${item.odkud} → ${item.kam}${ridicJmeno ? ' | ' + ridicJmeno : ''}</div>
                </div>
                <div class="dokonceny-akce">
                    <div class="dokonceny-cas">${item.casDrop || item.dokoncenoCas}</div>
                    <button class="btn-smazat-dokonceny" onclick="smazatDokonceny(${origIndex})" title="Smazat">X</button>
                </div>
            </div>
        `;
    }).join('');
}

// Aktualizovat počet dokončených v badge
function aktualizovatPocetDokoncenych() {
    const badge = document.getElementById('pocet-dokoncenych');
    if (dokoncene.length > 0) {
        badge.textContent = dokoncene.length;
    } else {
        badge.textContent = '';
    }
}

// Smazat dokončený transport (s heslem)
async function smazatDokonceny(index) {
    const item = dokoncene[index];
    if (!item) return;

    // Prvni krok - zadat heslo
    const hesloVysledek = await transportConfirm('Zadejte heslo pro smazani', {
        titulek: 'Overeni',
        btnPotvrdit: 'Pokracovat',
        heslo: true
    });

    if (!hesloVysledek.potvrzeno) return;

    if (hesloVysledek.heslo !== HESLO) {
        await transportConfirm('Spatne heslo!', {
            titulek: 'Chyba',
            btnPotvrdit: 'OK',
            btnZrusit: null,
            nebezpecne: true
        });
        return;
    }

    // Druhy krok - potvrzeni smazani
    const potvrzeni = await transportConfirm(`Opravdu smazat dokonceny transport: ${item.jmeno}?`, {
        titulek: 'Smazat transport',
        btnPotvrdit: 'Smazat',
        nebezpecne: true
    });

    if (!potvrzeni.potvrzeno) return;

    dokoncene.splice(index, 1);
    await ulozData();
    vykresliDokoncene();
    aktualizovatPocetDokoncenych();
}

// Export dokončených transportů do PDF
async function exportDokoncenych() {
    if (dokoncene.length === 0) {
        await transportConfirm('Zadne dokoncene transporty k exportu', {
            titulek: 'Export',
            btnPotvrdit: 'OK',
            btnZrusit: null
        });
        return;
    }

    // Vytvorit obsah pro tisk
    const datum = new Date().toLocaleDateString('cs-CZ');
    let html = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Dokoncene transporty - ${datum}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { font-size: 18px; border-bottom: 2px solid #000; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #333; padding: 8px; text-align: left; }
                th { background: #333; color: #fff; }
                .cas { font-weight: bold; }
                .footer { margin-top: 30px; font-size: 11px; color: #666; }
            </style>
        </head>
        <body>
            <h1>Dokoncene transporty - ${datum}</h1>
            <table>
                <thead>
                    <tr>
                        <th>Cas</th>
                        <th>Jmeno</th>
                        <th>Trasa</th>
                        <th>Ridic</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Seřadit od nejnovějších
    const serazene = [...dokoncene].reverse();

    serazene.forEach(item => {
        const ridic = item.ridicId ? ridici.find(r => r.id === item.ridicId) : null;
        const ridicJmeno = ridic ? ridic.jmeno : '-';
        html += `
            <tr>
                <td class="cas">${item.casDrop || item.dokoncenoCas || '-'}</td>
                <td>${item.jmeno}</td>
                <td>${item.odkud} → ${item.kam}</td>
                <td>${ridicJmeno}</td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
            <div class="footer">
                Celkem: ${dokoncene.length} transportu | Vygenerovano: ${new Date().toLocaleString('cs-CZ')}
            </div>
        </body>
        </html>
    `;

    // Otevřít nové okno pro tisk/PDF
    const printWindow = window.open('', '_blank');
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.print();
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

            // Sestavit info o letu
            const letadloSvg = '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;vertical-align:middle;margin-left:4px;"><path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/></svg>';
            const letInfo = item.cisloLetu ? `<span class="transport-let" data-let="${item.cisloLetu}" title="Kliknete pro aktualizaci">${item.cisloLetu}${letadloSvg}</span>` : '';

            // Sestavit info o ridici
            const autoSvg = '<svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;vertical-align:middle;margin-left:4px;"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>';
            const ridic = item.ridicId ? ridici.find(r => r.id === item.ridicId) : null;
            const ridicInfo = ridic ? `<span class="transport-ridic" title="${ridic.auto || ''}">${ridic.jmeno}${autoSvg}</span>` : '';

            // Sestavit kontaktni ikonu telefonu
            let kontaktIkony = '';
            if (item.telefon) {
                kontaktIkony += `<a href="tel:${item.telefon}" class="transport-kontakt" onclick="event.stopPropagation();" title="${item.telefon}"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg></a>`;
            }

            // Pocet osob badge - tolik panacku kolik je osob
            let pocetOsobInfo = '';
            if (item.pocetOsob && item.pocetOsob > 0) {
                const panacek = '<svg viewBox="0 0 24 24" fill="currentColor" style="width:11px;height:11px;vertical-align:middle;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
                const pocet = Math.min(item.pocetOsob, 6); // Max 6 panacku
                pocetOsobInfo = `<span class="transport-pocet" title="${item.pocetOsob} PAX">${panacek.repeat(pocet)}${item.pocetOsob > 6 ? '+' : ''}</span>`;
            }

            // Poznamka
            const poznamkaInfo = item.poznamka ? `<div class="transport-poznamka">${item.poznamka}</div>` : '';

            const div = document.createElement('div');
            div.className = 'transport';
            div.dataset.id = item.id;
            if (item.cisloLetu) div.dataset.let = item.cisloLetu;

            div.innerHTML = `
                <div class="transport-cas">${item.cas}</div>
                <div class="transport-kontakty">${kontaktIkony}</div>
                <div class="transport-trasa">${item.odkud} → ${item.kam}</div>
                <div class="transport-jmena">${item.jmeno} ${pocetOsobInfo}</div>
                <div class="transport-meta">${letInfo} ${ridicInfo}</div>
                ${poznamkaInfo}
                <button class="btn-upravit" onclick="event.stopPropagation(); editujVse('${item.id}', '${datum}')">Upravit</button>
                <div class="transport-stav">
                    <button class="stav-btn ${stavClass}" onclick="event.stopPropagation(); zmenStav('${item.id}')">${stavText}</button>
                    <div class="stav-cas">${stavCas}</div>
                </div>
            `;
            transportyKontejner.appendChild(div);
        });
    });
}

// Naplnit select s řidiči
function naplnSelectRidicu(vybranyId = '') {
    const select = document.getElementById('input-ridic');
    select.innerHTML = '<option value="">-- Neprirazeno --</option>';

    // Seradit ridice podle jmena
    const serazeniRidici = [...ridici].sort((a, b) =>
        (a.jmeno || '').localeCompare(b.jmeno || '', 'cs')
    );

    serazeniRidici.forEach(ridic => {
        const option = document.createElement('option');
        option.value = ridic.id;
        option.textContent = ridic.jmeno + (ridic.auto ? ' (' + ridic.auto + ')' : '');
        if (ridic.id === vybranyId) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

// Otevřít modal pro přidání
function otevriModalPridat(datum) {
    editAkce = { typ: 'pridat', datum: datum, id: null, pole: null };
    document.getElementById('modal-titulek').textContent = t('addTransport');
    document.getElementById('input-let').value = '';
    document.getElementById('input-cas').value = '';
    document.getElementById('input-jmeno').value = '';
    document.getElementById('input-telefon').value = '';
    document.getElementById('input-email').value = '';
    document.getElementById('input-odkud').value = '';
    document.getElementById('input-kam').value = '';
    document.getElementById('input-pocet').value = '';
    document.getElementById('input-poznamka').value = '';
    document.getElementById('input-heslo').value = '';
    document.getElementById('modal-chyba').style.display = 'none';
    document.getElementById('let-info').className = 'let-info';
    document.getElementById('let-info').innerHTML = '';
    resetTextareaVyska();

    // Naplnit select ridicu
    naplnSelectRidicu('');

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
    document.getElementById('input-let').value = transport.cisloLetu || '';
    document.getElementById('input-cas').value = transport.cas;
    document.getElementById('input-jmeno').value = transport.jmeno;
    document.getElementById('input-telefon').value = transport.telefon || '';
    document.getElementById('input-email').value = transport.email || '';
    document.getElementById('input-odkud').value = transport.odkud;
    document.getElementById('input-kam').value = transport.kam;
    document.getElementById('input-pocet').value = transport.pocetOsob || '';
    document.getElementById('input-poznamka').value = transport.poznamka || '';
    document.getElementById('input-heslo').value = '';
    document.getElementById('modal-chyba').style.display = 'none';
    document.getElementById('let-info').className = 'let-info';
    document.getElementById('let-info').innerHTML = '';
    upravTextareaVyska();

    // Naplnit select ridicu a vybrat aktualniho
    naplnSelectRidicu(transport.ridicId || '');

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

        const cisloLetu = document.getElementById('input-let').value.trim().toUpperCase();
        const ridicId = document.getElementById('input-ridic').value || null;
        const pocetOsob = parseInt(document.getElementById('input-pocet').value) || null;
        const poznamka = document.getElementById('input-poznamka').value.trim() || null;
        const novy = {
            id: generujId(),
            cas: normalizujCas(document.getElementById('input-cas').value) || '00:00',
            jmeno: document.getElementById('input-jmeno').value || 'Neznamy',
            telefon: document.getElementById('input-telefon').value.trim() || null,
            email: document.getElementById('input-email').value.trim() || null,
            odkud: document.getElementById('input-odkud').value || '?',
            kam: document.getElementById('input-kam').value || '?',
            datum: vybraneDatum,
            cisloLetu: cisloLetu || null,
            ridicId: ridicId,
            pocetOsob: pocetOsob,
            poznamka: poznamka
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
            transport.telefon = document.getElementById('input-telefon').value.trim() || null;
            transport.email = document.getElementById('input-email').value.trim() || null;
            transport.odkud = document.getElementById('input-odkud').value;
            transport.kam = document.getElementById('input-kam').value;
            transport.cisloLetu = document.getElementById('input-let').value.trim().toUpperCase() || null;
            transport.ridicId = document.getElementById('input-ridic').value || null;
            transport.pocetOsob = parseInt(document.getElementById('input-pocet').value) || null;
            transport.poznamka = document.getElementById('input-poznamka').value.trim() || null;
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

// ===== VYHLEDAVANI LETU =====
let aktualniLetData = null;

// Hledat let podle čísla
async function hledejLet() {
    const cisloLetu = document.getElementById('input-let').value.trim().toUpperCase();
    const letInfo = document.getElementById('let-info');
    const btnHledat = document.getElementById('btn-hledat-let');

    if (!cisloLetu) {
        letInfo.className = 'let-info aktivni chyba';
        letInfo.innerHTML = 'Zadejte cislo letu';
        return;
    }

    // Zobrazit načítání
    btnHledat.disabled = true;
    btnHledat.classList.add('nacitam');
    btnHledat.textContent = '...';
    letInfo.className = 'let-info aktivni';
    letInfo.innerHTML = 'Hledam let ' + cisloLetu + '...';

    try {
        const odpoved = await fetch('api/flight_api.php?let=' + encodeURIComponent(cisloLetu));
        const data = await odpoved.json();

        if (data.status === 'success') {
            aktualniLetData = data;

            // Získat čas příletu
            const prilet = data.prilet;
            let casPriletu = '';

            // Použít odhadovaný čas, pokud existuje, jinak plánovaný
            const casStr = prilet.odhadovano || prilet.planovano || '';
            if (casStr) {
                const datum = new Date(casStr);
                casPriletu = datum.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
            }

            // Překlad stavů
            const stavyPreklad = {
                'scheduled': 'Naplanovano',
                'active': 'Ve vzduchu',
                'landed': 'Pristano',
                'cancelled': 'Zruseno',
                'delayed': 'Zpozdeno',
                'unknown': 'Neznamy'
            };

            const stavText = stavyPreklad[data.stavLetu] || data.stavLetu;
            const stavClass = data.stavLetu || 'scheduled';

            // Zobrazit info o letu
            letInfo.className = 'let-info aktivni uspech';
            letInfo.innerHTML = `
                <div class="let-info-radek">
                    <span>${data.aerolinky}</span>
                    <span class="let-info-status ${stavClass}">${stavText}</span>
                </div>
                <div class="let-info-radek">
                    <span>${prilet.letiste} (${prilet.iata})</span>
                </div>
                <div class="let-info-radek">
                    <span>Prilet:</span>
                    <span class="let-info-cas">${casPriletu}</span>
                </div>
                ${prilet.terminal ? `<div class="let-info-radek"><span>Terminal:</span><span class="let-info-hodnota">T${prilet.terminal}</span></div>` : ''}
                ${prilet.bagaz ? `<div class="let-info-radek"><span>Zavazadla:</span><span class="let-info-hodnota">Pas ${prilet.bagaz}</span></div>` : ''}
                ${data.zdroj === 'demo' ? '<div style="color: #666; font-size: 10px; margin-top: 5px;">Demo data - pro live data nastavte API klic</div>' : ''}
                <button type="button" onclick="pouzitDataLetu()" style="margin-top: 10px; width: 100%; padding: 8px; background: #333; border: 1px solid #555; color: #fff; border-radius: 4px; cursor: pointer;">Pouzit tento cas</button>
            `;

            // Automaticky nastavit odkud na letiště
            if (prilet.iata) {
                const odkudInput = document.getElementById('input-odkud');
                if (!odkudInput.value) {
                    odkudInput.value = 'T' + (prilet.terminal || '') + ' ' + prilet.iata;
                }
            }

        } else {
            aktualniLetData = null;
            letInfo.className = 'let-info aktivni chyba';
            letInfo.innerHTML = data.message || 'Let nenalezen';
        }

    } catch (e) {
        console.error('Chyba pri hledani letu:', e);
        letInfo.className = 'let-info aktivni chyba';
        letInfo.innerHTML = 'Chyba pri komunikaci s API';
    }

    // Reset tlačítka
    btnHledat.disabled = false;
    btnHledat.classList.remove('nacitam');
    btnHledat.textContent = 'Hledat';
}

// Použít data z nalezeného letu
function pouzitDataLetu() {
    if (!aktualniLetData) return;

    const prilet = aktualniLetData.prilet;
    const casStr = prilet.odhadovano || prilet.planovano || '';

    if (casStr) {
        const datum = new Date(casStr);
        const cas = datum.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
        document.getElementById('input-cas').value = cas;

        // Nastavit také datum pokud je to přidání
        if (editAkce?.typ === 'pridat') {
            const datumInput = document.getElementById('input-datum');
            const den = datum.getDate().toString().padStart(2, '0');
            const mesic = (datum.getMonth() + 1).toString().padStart(2, '0');
            const rok = datum.getFullYear();
            datumInput.value = den + '.' + mesic + '.' + rok;
            datumInput.dataset.hodnota = rok + '-' + mesic + '-' + den;
        }
    }

    // Nastavit odkud na letiště
    if (prilet.iata) {
        document.getElementById('input-odkud').value = 'T' + (prilet.terminal || '') + ' ' + prilet.iata;
    }
}

// Vyhledat let při stisknutí Enter v inputu
document.addEventListener('DOMContentLoaded', function() {
    const inputLet = document.getElementById('input-let');
    if (inputLet) {
        inputLet.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                hledejLet();
            }
        });
    }
});

// ===== AUTO-EXPAND TEXTAREA =====
function upravTextareaVyska() {
    const textarea = document.getElementById('input-poznamka');
    if (textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
    }
}

function resetTextareaVyska() {
    const textarea = document.getElementById('input-poznamka');
    if (textarea) {
        textarea.style.height = '60px';
    }
}

// Event listener pro auto-expand
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('input-poznamka');
    if (textarea) {
        textarea.addEventListener('input', upravTextareaVyska);
    }
});

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

        // Spustit timer pro přesun do dokončených (1 minuta)
        spustitDropTimer(id);
    } else if (aktualniStav === 'drop') {
        // DROP OFF - otevřít modal pro reset
        otevriModalReset(id);
    }
}

// Spustit timer pro automatický přesun do dokončených
function spustitDropTimer(id) {
    // Zrušit existující timer pokud existuje
    if (dropTimery[id]) {
        clearTimeout(dropTimery[id]);
    }

    // Po 1 minutě přesunout do dokončených
    dropTimery[id] = setTimeout(() => {
        presunDoDokoncene(id);
    }, 60000); // 60 sekund = 1 minuta
}

// Přesunout transport do dokončených
async function presunDoDokoncene(id) {
    // Najít transport data
    let transportData = null;
    let transportDatum = null;

    Object.keys(transporty).forEach(datum => {
        const transport = transporty[datum]?.find(t => t.id === id);
        if (transport) {
            transportData = { ...transport };
            transportDatum = datum;
        }
    });

    if (!transportData) return;

    // Přidat do dokončených s časem dokončení
    const stavData = stavy[id] || {};
    dokoncene.push({
        ...transportData,
        casOdjezdu: stavData.cas || '',
        casDrop: stavData.casDrop || '',
        ridic: stavData.ridic || transportData.ridic || '',
        dokoncenoCas: new Date().toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' })
    });

    // Odstranit z aktivních transportů
    if (transporty[transportDatum]) {
        transporty[transportDatum] = transporty[transportDatum].filter(t => t.id !== id);
        // Pokud je den prázdný, smazat
        if (transporty[transportDatum].length === 0) {
            delete transporty[transportDatum];
        }
    }

    // Smazat stav
    delete stavy[id];
    delete dropTimery[id];

    // Aktualizovat UI
    vykresli();
    aktualizovatPocetDokoncenych();
    await ulozData();
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
        formData.append('dokoncene', JSON.stringify(dokoncene));
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

            // Načíst dokončené
            if (data.dokoncene && Array.isArray(data.dokoncene)) {
                dokoncene = data.dokoncene;
            }

            // Spustit timery pro existující DROP stavy
            obnovitDropTimery();

            vykresli();
            vykresliRidice();
            aktualizovatPocetDokoncenych();
        }
    } catch (e) {
        console.log('Chyba pri nacitani:', e);
        vykresli();
        vykresliRidice();
        aktualizovatPocetDokoncenych();
    }
}

// Obnovit timery pro existující DROP stavy po načtení
function obnovitDropTimery() {
    Object.keys(stavy).forEach(id => {
        if (stavy[id]?.stav === 'drop' && !dropTimery[id]) {
            // Spustit timer pouze pokud ještě neběží
            spustitDropTimer(id);
        }
    });
}

// Zavřít modaly klávesou Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        zavriModal();
        zavriModalSmazat();
        zavriModalReset();
        zavriModalRidic();
        zavriModalRidici();
        zavriModalDokoncene();
        zavriModalNahrat();
        // Zavřít seznam řidiče bez otevření hlavního modalu
        document.getElementById('modal-seznam-ridice')?.classList.remove('aktivni');
        zavriVsechnyMenuRidicu();
        zavriLetOverlay();
    }
});

// ===== SLEDOVANI STAVU LETU =====
// Cache pro stavy letu
let letoveStavy = {};

// Aktualizovat stav letu pro konkretní transport
async function aktualizujStavLetu(cisloLetu, elementLet) {
    if (!cisloLetu) return;

    try {
        const odpoved = await fetch('api/flight_api.php?let=' + encodeURIComponent(cisloLetu));
        const data = await odpoved.json();

        if (data.status === 'success') {
            letoveStavy[cisloLetu] = data;

            // Aktualizovat vsechny elementy s timto letem
            document.querySelectorAll(`.transport-let[data-let="${cisloLetu}"]`).forEach(el => {
                // Odstranit stare tridy
                el.classList.remove('aktivni', 'pristano', 'zpozdeno');

                // Pridat novou tridu podle stavu
                switch (data.stavLetu) {
                    case 'active':
                        el.classList.add('aktivni');
                        break;
                    case 'landed':
                        el.classList.add('pristano');
                        break;
                    case 'delayed':
                        el.classList.add('zpozdeno');
                        break;
                }
            });
        }
    } catch (e) {
        console.log('Chyba pri aktualizaci letu:', cisloLetu, e);
    }
}

// Aktualizovat vsechny lety na strance
async function aktualizujVsechnyLety() {
    // Ziskat unikatni cisla letu
    const cislaLetu = new Set();
    document.querySelectorAll('.transport-let[data-let]').forEach(el => {
        cislaLetu.add(el.dataset.let);
    });

    // Aktualizovat kazdy let (postupne, aby se nepretizilo API)
    for (const cislo of cislaLetu) {
        await aktualizujStavLetu(cislo);
        // Pockej 500ms mezi pozadavky
        await new Promise(r => setTimeout(r, 500));
    }
}

// Kliknuti na badge letu - zobrazit detaily INLINE pod číslem
function zobrazDetailLetu(event) {
    const letElement = event.target;
    const cisloLetu = letElement.dataset.let;
    if (!cisloLetu) return;

    event.stopPropagation();

    // Najit nebo vytvorit info element pod letem
    const parent = letElement.closest('.transport-meta');
    let infoEl = parent.querySelector('.let-info-inline');

    // Pokud info uz existuje, skryt/zobrazit
    if (infoEl) {
        infoEl.remove();
        return;
    }

    // Pokud mame cachovana data, zobrazit je
    const data = letoveStavy[cisloLetu];
    if (data) {
        zobrazLetInline(letElement, data);
    } else {
        // Nacist data a pak zobrazit
        nactiAZobrazLetInline(letElement, cisloLetu);
    }
}

// Nacist data letu a zobrazit inline
async function nactiAZobrazLetInline(letElement, cisloLetu) {
    // Zobrazit loading
    const parent = letElement.closest('.transport-meta');
    let infoEl = document.createElement('div');
    infoEl.className = 'let-info-inline';
    infoEl.innerHTML = '<span style="color:#666;">Nacitam...</span>';
    parent.appendChild(infoEl);

    try {
        const odpoved = await fetch('api/flight_api.php?let=' + encodeURIComponent(cisloLetu));
        const data = await odpoved.json();
        if (data.status === 'success') {
            letoveStavy[cisloLetu] = data;
            zobrazLetInline(letElement, data);
        } else {
            infoEl.innerHTML = '<span style="color:#ff4444;">Let nenalezen</span>';
        }
    } catch (e) {
        infoEl.innerHTML = '<span style="color:#ff4444;">Chyba</span>';
        console.log('Chyba pri nacitani letu:', e);
    }
}

// Zobrazit info o letu inline pod číslem
function zobrazLetInline(letElement, data) {
    const parent = letElement.closest('.transport-meta');

    // Odstranit stary info element
    const oldInfo = parent.querySelector('.let-info-inline');
    if (oldInfo) oldInfo.remove();

    const prilet = data.prilet;
    const cas = prilet.odhadovano || prilet.planovano || '';
    const casStr = cas ? new Date(cas).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' }) : '';

    const stavyPreklad = {
        'scheduled': 'NAPLANOVANO',
        'active': 'VE VZDUCHU',
        'landed': 'PRISTANO',
        'cancelled': 'ZRUSENO',
        'delayed': 'ZPOZDENO'
    };

    const stavyBarvy = {
        'scheduled': '#666',
        'active': '#39ff14',
        'landed': '#39ff14',
        'cancelled': '#ff4444',
        'delayed': '#ff8800'
    };

    const stav = data.stavLetu || 'scheduled';
    const stavText = stavyPreklad[stav] || stav;
    const stavBarva = stavyBarvy[stav] || '#666';

    const infoEl = document.createElement('div');
    infoEl.className = 'let-info-inline';
    infoEl.innerHTML = `
        <span style="color:${stavBarva};font-weight:700;">${stavText}</span>
        ${casStr ? `<span style="color:#fff;margin-left:8px;">${casStr}</span>` : ''}
        ${prilet.terminal ? `<span style="color:#666;margin-left:8px;">T${prilet.terminal}</span>` : ''}
    `;
    parent.appendChild(infoEl);
}

// Zobrazit WGS overlay s informacemi o letu
function zobrazLetOverlay(data) {
    const prilet = data.prilet;
    const odlet = data.odlet;
    const cas = prilet.odhadovano || prilet.planovano || '';
    const casStr = cas ? new Date(cas).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' }) : '?';

    const stavyPreklad = {
        'scheduled': 'NAPLANOVANO',
        'active': 'VE VZDUCHU',
        'landed': 'PRISTANO',
        'cancelled': 'ZRUSENO',
        'delayed': 'ZPOZDENO'
    };

    // Vyplnit overlay
    document.getElementById('wgs-let-cislo').textContent = data.cisloLetu;
    document.getElementById('wgs-let-aerolinky').textContent = data.aerolinky || '';
    document.getElementById('wgs-let-cas-prilet').textContent = casStr;
    document.getElementById('wgs-let-odkud').textContent = odlet.letiste + (odlet.iata ? ' (' + odlet.iata + ')' : '');
    document.getElementById('wgs-let-kam').textContent = prilet.letiste + (prilet.iata ? ' (' + prilet.iata + ')' : '');

    // Status
    const statusEl = document.getElementById('wgs-let-status');
    statusEl.textContent = stavyPreklad[data.stavLetu] || data.stavLetu;
    statusEl.className = 'wgs-let-status ' + (data.stavLetu || 'scheduled');

    // Terminal
    const terminalRow = document.getElementById('wgs-let-terminal-row');
    if (prilet.terminal) {
        terminalRow.style.display = 'flex';
        document.getElementById('wgs-let-terminal').textContent = 'T' + prilet.terminal;
    } else {
        terminalRow.style.display = 'none';
    }

    // Bagaz
    const bagazRow = document.getElementById('wgs-let-bagaz-row');
    if (prilet.bagaz) {
        bagazRow.style.display = 'flex';
        document.getElementById('wgs-let-bagaz').textContent = 'Pas ' + prilet.bagaz;
    } else {
        bagazRow.style.display = 'none';
    }

    // Zobrazit overlay
    document.getElementById('wgs-let-overlay').classList.add('aktivni');
}

// Zavrit overlay
function zavriLetOverlay(event) {
    if (event && event.target !== event.currentTarget) return;
    document.getElementById('wgs-let-overlay').classList.remove('aktivni');
}

// Event delegation pro kliknuti na let badge
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('transport-let')) {
        zobrazDetailLetu(e);
    }
});

// Inicializace po načtení stránky
document.addEventListener('DOMContentLoaded', function() {
    // Nacist ulozeny nazev
    nactiNazev();

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

    // Aktualizovat stavy letu kazde 2 minuty (setrit API volani)
    setTimeout(aktualizujVsechnyLety, 5000); // Prvni aktualizace po 5s
    setInterval(aktualizujVsechnyLety, 120000); // Pak kazde 2 minuty
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
