/**
 * modal-detail.js — Centrální správa detailního modalu reklamace
 *
 * PRAVIDLO: Veškerá DOM logika pro #detailOverlay patří SEM.
 * Žádný jiný soubor nesmí přímo manipulovat styly #detailOverlay.
 *
 * CSS layout řídí: assets/css/modal-detail.css (třídy, ne inline styly)
 * Alpine.js komponenta (hamburger-menu.php) volá metody tohoto modulu.
 * ModalManager v seznam.js nastavuje obsah a volá metody tohoto modulu.
 *
 * @version 1.0.0
 * @date 2026-03-07
 */

(function(window) {
  'use strict';

  // === PRIVÁTNÍ STAV ===

  const jeIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

  function jeMobilniSirka() {
    return window.innerWidth < 769;
  }

  function ziskatOverlay() {
    return document.getElementById('detailOverlay');
  }

  function ziskatObsah() {
    return document.querySelector('#detailOverlay .modal-content');
  }

  // === VEŘEJNÉ METODY ===

  /**
   * Otevřít modal — přidá třídy, nastaví scroll lock
   * Volá: Alpine detailModal.openModal() v hamburger-menu.php
   */
  function otevrit() {
    const overlay = ziskatOverlay();
    if (!overlay) return;

    overlay.classList.add('active');

    // iOS mobilní: CSS třída .ios-fullscreen aplikuje fullscreen layout
    // (pravidla v modal-detail.css — neupravovat pomocí style.setProperty)
    if (jeIOS && jeMobilniSirka()) {
      document.body.classList.add('ios-device');
      overlay.classList.add('ios-fullscreen');
    }

    // Scroll lock na body
    if (window.scrollLock) {
      window.scrollLock.enable('detail-overlay');
    }
    document.body.classList.add('modal-open');

    // Reset scroll pozice
    setTimeout(function() {
      const obsah = ziskatObsah();
      if (obsah) {
        obsah.scrollTop = 0;
      }
    }, 50);
  }

  /**
   * Zavřít modal — odebere třídy, odemkne scroll
   * Volá: Alpine detailModal.close() v hamburger-menu.php
   */
  function zavrit() {
    const overlay = ziskatOverlay();
    if (overlay) {
      overlay.classList.remove('active');
      overlay.classList.remove('ios-fullscreen');
    }
    document.body.classList.remove('ios-device');

    // Odemknout scroll po CSS transition
    setTimeout(function() {
      document.body.classList.remove('modal-open');
      if (window.scrollLock) {
        window.scrollLock.disable('detail-overlay');
      }
    }, 50);
  }

  /**
   * Nastavit HTML obsah modalu
   * Volá: ModalManager.show() v seznam.js
   * @param {string} html - HTML obsah pro #modalContent
   */
  function nastavitObsah(html) {
    const kontejner = document.getElementById('modalContent');
    if (kontejner) {
      kontejner.innerHTML = html;
    }
  }

  /**
   * Je modal aktuálně otevřen?
   * @returns {boolean}
   */
  function jeOtevreny() {
    const overlay = ziskatOverlay();
    return overlay ? overlay.classList.contains('active') : false;
  }

  /**
   * Zjistit jestli je zařízení iOS
   * @returns {boolean}
   */
  function jeIOSZarizeni() {
    return jeIOS;
  }

  // === VEŘEJNÉ API ===

  window.ModalDetail = {
    otevrit: otevrit,
    zavrit: zavrit,
    nastavitObsah: nastavitObsah,
    jeOtevreny: jeOtevreny,
    jeIOS: jeIOSZarizeni
  };

})(window);
