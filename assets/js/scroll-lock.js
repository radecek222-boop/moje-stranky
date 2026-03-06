/**
 * ScrollLock Utility - Centralizovaná správa zamykání scrollu
 *
 * Poskytuje jednotný interface pro zamykání scrollu při otevření
 * modálních oken a overlayů. Kompatibilní s iOS Safari a PWA.
 *
 * Použití:
 *   scrollLock.enable('nazev-overlay');  // Zamknout scroll
 *   scrollLock.disable('nazev-overlay'); // Odemknout scroll
 *   scrollLock.isLocked();               // Zkontrolovat stav
 *
 * METODA: position: fixed + top: -scrollY pro všechny platformy
 * - iOS/PWA: overflow:hidden na body blokuje scroll v fixed modalech
 * - position:fixed přístup tento problém obchází
 *
 * @author Claude Code
 * @version 1.2.0
 */

(function(window) {
    'use strict';

    // Stack aktivních zámků (podporuje vnořené modály)
    var aktivniZamky = [];

    // Uložená pozice scrollu před zamknutím
    var ulozenyScroll = 0;

    // CSS třída přidaná na body při zamknutí
    var CSS_TRIDA_ZAMKNUTO = 'scroll-locked';

    // Detekce PWA módu
    var isPWA = (function() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true;
    })();

    // Detekce iOS
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

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

            // Použít position:fixed pro všechny platformy včetně iOS/PWA
            // overflow:hidden na body blokuje touch scroll v fixed modalech na iOS
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
            // Obnovit position a scroll
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';

            document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);

            // Obnovit pozici scrollu (requestAnimationFrame pro spolehlivost na iOS)
            requestAnimationFrame(function() {
                window.scrollTo(0, ulozenyScroll);
            });

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
        document.body.style.overflow = '';

        document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);

        // Obnovit scroll s requestAnimationFrame
        requestAnimationFrame(function() {
            window.scrollTo(0, ulozenyScroll);
        });

        document.documentElement.style.removeProperty('--scroll-locked-position');
    }

    // Odemknout scroll při opuštění stránky
    window.addEventListener('pagehide', function() {
        if (aktivniZamky.length > 0) {
            odemknoutVse();
        }
    });

    // Odemknout scroll při návratu na stránku (pro případ že se PWA "probudí")
    window.addEventListener('pageshow', function(event) {
        if (event.persisted && aktivniZamky.length === 0) {
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.overflow = '';
            document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);
        }
    });

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
        jeZamknout: jeZamknuto,
        // Debug info
        isPWA: isPWA,
        isIOS: isIOS
    };

})(window);
