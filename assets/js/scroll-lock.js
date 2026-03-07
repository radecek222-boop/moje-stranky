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
 * @version 2.0.0 - Jednotná metoda position:fixed pro všechny platformy
 */

(function(window) {
    'use strict';

    var aktivniZamky = [];
    var ulozenyScroll = 0;
    var CSS_TRIDA_ZAMKNUTO = 'scroll-locked';

    var isPWA = (function() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true;
    })();

    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

    /**
     * Zamkne scroll stránky pomocí position:fixed
     * Tato metoda funguje správně na iOS, Android i desktop.
     * NIKDY nepoužívá overflow:hidden - to blokuje scroll v modalech na iOS.
     */
    function zamknoutScroll(idOverlaye) {
        if (!idOverlaye) {
            console.warn('[ScrollLock] Chybí ID overlay');
            return;
        }

        if (aktivniZamky.length === 0) {
            ulozenyScroll = window.pageYOffset || document.documentElement.scrollTop || 0;

            document.body.style.position = 'fixed';
            document.body.style.top = '-' + ulozenyScroll + 'px';
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';

            document.body.classList.add(CSS_TRIDA_ZAMKNUTO);
            document.documentElement.style.setProperty('--scroll-locked-position', ulozenyScroll + 'px');
        }

        if (aktivniZamky.indexOf(idOverlaye) === -1) {
            aktivniZamky.push(idOverlaye);
        }
    }

    function odemknoutScroll(idOverlaye) {
        if (!idOverlaye) {
            console.warn('[ScrollLock] Chybí ID overlay');
            return;
        }

        var index = aktivniZamky.indexOf(idOverlaye);
        if (index > -1) {
            aktivniZamky.splice(index, 1);
        }

        if (aktivniZamky.length === 0) {
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';

            document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);
            document.documentElement.style.removeProperty('--scroll-locked-position');

            window.scrollTo(0, ulozenyScroll);
        }
    }

    function jeZamknuto() {
        return aktivniZamky.length > 0;
    }

    function ziskatAktivniZamky() {
        return aktivniZamky.slice();
    }

    function odemknoutVse() {
        aktivniZamky = [];

        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';

        document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);
        document.documentElement.style.removeProperty('--scroll-locked-position');

        requestAnimationFrame(function() {
            window.scrollTo(0, ulozenyScroll);
        });
    }

    window.addEventListener('pagehide', function() {
        if (aktivniZamky.length > 0) {
            odemknoutVse();
        }
    });

    window.addEventListener('pageshow', function(event) {
        if (event.persisted && aktivniZamky.length === 0) {
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            document.body.classList.remove(CSS_TRIDA_ZAMKNUTO);
        }
    });

    window.scrollLock = {
        enable: zamknoutScroll,
        disable: odemknoutScroll,
        isLocked: jeZamknuto,
        getActiveLocks: ziskatAktivniZamky,
        forceUnlockAll: odemknoutVse,
        zamknout: zamknoutScroll,
        odemknout: odemknoutScroll,
        jeZamknout: jeZamknuto,
        isPWA: isPWA,
        isIOS: isIOS
    };

})(window);
