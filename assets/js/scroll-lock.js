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
 * FIX PWA: Přidána speciální logika pro PWA mód
 * - PWA mód používá overflow: hidden místo position: fixed
 * - Řeší problém se zaseknutým scrollem v iOS Safari PWA
 *
 * @author Claude Code
 * @version 1.1.0
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

    // FIX PWA: Pro PWA mód použít jednodušší metodu (overflow: hidden)
    // position: fixed způsobuje problémy se scrollem v iOS Safari PWA
    var pouzitOverflowMetodu = isPWA || isIOS;

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

            if (pouzitOverflowMetodu) {
                // FIX PWA: Použít overflow: hidden pro PWA/iOS
                // Tato metoda je spolehlivější a nezpůsobuje zaseknutí scrollu
                document.documentElement.style.overflow = 'hidden';
                document.body.style.overflow = 'hidden';
                // Zachovat pozici pomocí scroll-behavior
                document.documentElement.style.scrollBehavior = 'auto';
            } else {
                // Desktop: Použít position: fixed (původní metoda)
                document.body.style.position = 'fixed';
                document.body.style.top = '-' + ulozenyScroll + 'px';
                document.body.style.left = '0';
                document.body.style.right = '0';
                document.body.style.width = '100%';
            }

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
            if (pouzitOverflowMetodu) {
                // FIX PWA: Obnovit overflow
                document.documentElement.style.overflow = '';
                document.body.style.overflow = '';
                document.documentElement.style.scrollBehavior = '';

                // FIX PWA: Použít requestAnimationFrame pro spolehlivé obnovení scrollu
                requestAnimationFrame(function() {
                    window.scrollTo(0, ulozenyScroll);
                });
            } else {
                // Desktop: Obnovit position
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.left = '';
                document.body.style.right = '';
                document.body.style.width = '';

                // Obnovit pozici scrollu
                window.scrollTo(0, ulozenyScroll);
            }

            document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);

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

        // Vyčistit oba typy stylů
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        document.documentElement.style.scrollBehavior = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';

        document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);

        // Obnovit scroll s requestAnimationFrame
        requestAnimationFrame(function() {
            window.scrollTo(0, ulozenyScroll);
        });

        document.documentElement.style.removeProperty('--scroll-locked-position');
    }

    // FIX PWA: Přidat listener pro případ, že uživatel opustí stránku se zamknutým scrollem
    window.addEventListener('pagehide', function() {
        if (aktivniZamky.length > 0) {
            odemknoutVse();
        }
    });

    // FIX PWA: Odemknout scroll při návratu na stránku (pro případ že se PWA "probudí")
    window.addEventListener('pageshow', function(event) {
        // Pokud stránka přichází z bfcache a scroll je zamknutý bez aktivních overlayů
        if (event.persisted && aktivniZamky.length === 0) {
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.top = '';
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
        jeZamknuto: jeZamknuto,
        // Debug info
        isPWA: isPWA,
        isIOS: isIOS
    };

})(window);
