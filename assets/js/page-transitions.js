/**
 * Fade přechod mezi stránkami - bez AJAX
 *
 * Kliknutí na odkaz:
 *   1. Stránka zčerná (fade out, 400 ms)
 *   2. Prohlížeč načte novou stránku (plný reload)
 *   3. Nová stránka se rozsvítí (fade in, 400 ms)
 */
(function () {
  'use strict';

  var DOBA_FADE = 400;       // ms – délka jedné fáze
  var KLIC_FADE = 'wgs-fade-prichod'; // klíč v sessionStorage

  // Zjistit jestli přicházíme na stránku s požadavkem na fade-in
  var prichazime = sessionStorage.getItem(KLIC_FADE) === '1';
  if (prichazime) {
    sessionStorage.removeItem(KLIC_FADE);
  }

  // --- Vytvořit overlay ---
  // Musí být vložen do DOM co nejdřív, aby překryl stránku hned po načtení
  var overlay = document.createElement('div');
  overlay.id = 'prechod-overlay';
  overlay.style.cssText = [
    'position:fixed',
    'inset:0',
    'background:#000',
    'z-index:99999',
    'pointer-events:none',
    // Přicházíme? Začínáme černou (bez přechodu, aby byl overlay okamžitě černý)
    'opacity:' + (prichazime ? '1' : '0'),
    'transition:none'
  ].join(';');

  // Přidat overlay co nejdříve – body je dostupné při defer skriptu
  if (document.body) {
    document.body.insertBefore(overlay, document.body.firstChild);
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      document.body.insertBefore(overlay, document.body.firstChild);
    });
  }

  // --- Fade IN: černá → průhledná ---
  if (prichazime) {
    // Dva animační snímky: prohlížeč stihne vykreslit černou PŘED začátkem přechodu
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        overlay.style.transition = 'opacity ' + DOBA_FADE + 'ms ease';
        overlay.style.opacity = '0';
      });
    });
  }

  // --- Zachytit kliknutí na interní odkaz ---
  document.addEventListener('click', function (e) {
    var odkaz = e.target.closest('a[href]');
    if (!odkaz) return;

    var href = odkaz.getAttribute('href');
    if (!href ||
        odkaz.target === '_blank' ||
        href.startsWith('#') ||
        href.startsWith('mailto:') ||
        href.startsWith('tel:') ||
        href.startsWith('javascript:')) return;

    var url;
    try { url = new URL(href, location.origin); } catch (err) { return; }
    if (url.origin !== location.origin) return;

    // Přeskočit aktuální stránku
    var cesta = function (p) { return p.split('?')[0].replace(/\/$/, '') || '/'; };
    if (cesta(url.pathname) === cesta(location.pathname)) return;

    // Přeskočit non-HTML soubory
    var ext = url.pathname.split('.').pop().toLowerCase();
    if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'css', 'js', 'svg'].includes(ext)) return;

    // Přeskočit admin / API stránky (ty potřebují plný reload bez efektu)
    var skipPaths = ['/admin', '/api/', '/app/', '/setup/', '/logout'];
    if (skipPaths.some(function (s) { return url.pathname.startsWith(s); })) return;

    e.preventDefault();

    // --- Fade OUT: průhledná → černá ---
    overlay.style.transition = 'opacity ' + DOBA_FADE + 'ms ease';
    overlay.style.opacity = '1';
    overlay.style.pointerEvents = 'all'; // zablokovat klikání během přechodu

    // Po dokončení zčernání navigovat na novou stránku
    setTimeout(function () {
      sessionStorage.setItem(KLIC_FADE, '1');
      window.location.href = url.href;
    }, DOBA_FADE);
  });

})();
