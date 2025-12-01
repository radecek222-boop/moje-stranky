/**
 * ScrollLock Utility - Centralizovaná správa zamykání scrollu
 *
 * Poskytuje jednotný interface pro zamykání scrollu při otevření
 * modálních oken a overlayů. Kompatibilní s iOS Safari.
 *
 * Použití:
 *   scrollLock.enable('nazev-overlay');  // Zamknout scroll
 *   scrollLock.disable('nazev-overlay'); // Odemknout scroll
 *   scrollLock.isLocked();               // Zkontrolovat stav
 *
 * @author Claude Code
 * @version 1.0.0
 */

(function(window) {
    'use strict';

    // Stack aktivních zámků (podporuje vnořené modály)
    var aktivniZamky = [];

    // Uložená pozice scrollu před zamknutím
    var ulozenyScroll = 0;

    // CSS třída přidaná na body při zamknutí
    var CSS_TRIDA_ZAMKNUTO = 'scroll-locked';

    /**
     * Zamkne scroll stránky
     * @param {string} idOverlaye - Unikátní identifikátor overlay/modalu
     */
    function zamknoutScroll(idOverlaye) {
        if (!idOverlaye) {
            console.warn('[ScrollLock] Chybí ID overlay');
            return;
        }

        // Pokud je toto první zámek, uložit pozici a zamknout
        if (aktivniZamky.length === 0) {
            ulozenyScroll = window.pageYOffset || document.documentElement.scrollTop || 0;

            // iOS Safari fix - použít position: fixed
            document.body.style.position = 'fixed';
            document.body.style.top = '-' + ulozenyScroll + 'px';
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
            document.body.classList.add(CSS_TRIDA_ZAMKNUTO);

            // Uložit do CSS proměnné pro případné použití v CSS
            document.documentElement.style.setProperty('--scroll-locked-position', ulozenyScroll + 'px');
        }

        // Přidat do stacku (pokud tam ještě není)
        if (aktivniZamky.indexOf(idOverlaye) === -1) {
            aktivniZamky.push(idOverlaye);
        }
    }

    /**
     * Odemkne scroll stránky
     * @param {string} idOverlaye - Unikátní identifikátor overlay/modalu
     */
    function odemknoutScroll(idOverlaye) {
        if (!idOverlaye) {
            console.warn('[ScrollLock] Chybí ID overlay');
            return;
        }

        // Odebrat ze stacku
        var index = aktivniZamky.indexOf(idOverlaye);
        if (index > -1) {
            aktivniZamky.splice(index, 1);
        }

        // Pokud je stack prázdný, odemknout scroll
        if (aktivniZamky.length === 0) {
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';
            document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);

            // Obnovit pozici scrollu
            window.scrollTo(0, ulozenyScroll);

            // Vyčistit CSS proměnnou
            document.documentElement.style.removeProperty('--scroll-locked-position');
        }
    }

    /**
     * Zkontroluje, zda je scroll aktuálně zamknutý
     * @returns {boolean}
     */
    function jeZamknuto() {
        return aktivniZamky.length > 0;
    }

    /**
     * Vrátí seznam aktivních zámků (pro debugging)
     * @returns {string[]}
     */
    function ziskatAktivniZamky() {
        return aktivniZamky.slice(); // Vrátit kopii
    }

    /**
     * Vynucené odemknutí všech zámků (nouzové použití)
     */
    function odemknoutVse() {
        aktivniZamky = [];
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);
        window.scrollTo(0, ulozenyScroll);
        document.documentElement.style.removeProperty('--scroll-locked-position');
    }

    // Exportovat API do globálního objektu
    window.scrollLock = {
        enable: zamknoutScroll,
        disable: odemknoutScroll,
        isLocked: jeZamknuto,
        getActiveLocks: ziskatAktivniZamky,
        forceUnlockAll: odemknoutVse,
        // České aliasy
        zamknout: zamknoutScroll,
        odemknout: odemknoutScroll,
        jeZamknuto: jeZamknuto
    };

})(window);
