/**
 * Plynule prechody mezi strankami (fade efekt)
 * Vstup: fade-in pri nacteni stranky
 * Odchod: fade-out pred presmerovanim
 */
(function () {
  // Fade-in pri nacteni - CSS animace je nastavena na body

  // Fade-out pri odchodu ze stranky
  document.addEventListener('click', function (udalost) {
    var odkaz = udalost.target.closest('a[href]');
    if (!odkaz) return;

    var href = odkaz.getAttribute('href');
    if (!href) return;

    // Preskocit externi, hash, mailto, tel, target=_blank, javascript
    if (
      odkaz.target === '_blank' ||
      href.startsWith('#') ||
      href.startsWith('mailto:') ||
      href.startsWith('tel:') ||
      href.startsWith('javascript:')
    ) return;

    try {
      var url = new URL(href, window.location.origin);
      if (url.origin !== window.location.origin) return;
    } catch (e) {
      return;
    }

    udalost.preventDefault();
    var cil = odkaz.href;

    document.body.classList.add('stranka-odchazi');
    setTimeout(function () {
      window.location.href = cil;
    }, 280);
  });
})();
