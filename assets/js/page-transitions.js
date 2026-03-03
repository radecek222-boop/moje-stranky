/**
 * Prechody mezi strankami - efekt listovani
 * Slide-out: okamzity, smerovy (pocit listovani)
 * Fade-in: rychly, nezavisi na load time
 */
(function () {
  // Poradi stranek v navigaci (zleva doprava)
  var poradi = [
    '/', '/index.php',
    '/novareklamace.php',
    '/cenik.php',
    '/nasesluzby.php',
    '/onas.php',
    '/aktuality.php',
    '/login.php',
    '/registration.php'
  ];

  function ziskIndex(pathname) {
    var p = pathname.replace(/\/$/, '') || '/';
    for (var i = 0; i < poradi.length; i++) {
      var ref = poradi[i].replace(/\/$/, '') || '/';
      if (ref === p) return i;
    }
    return -1;
  }

  // Zachytit kliknuti na odkaz
  document.addEventListener('click', function (udalost) {
    var odkaz = udalost.target.closest('a[href]');
    if (!odkaz) return;

    var href = odkaz.getAttribute('href');
    if (!href) return;

    if (
      odkaz.target === '_blank' ||
      href.startsWith('#') ||
      href.startsWith('mailto:') ||
      href.startsWith('tel:') ||
      href.startsWith('javascript:')
    ) return;

    var url;
    try {
      url = new URL(href, window.location.origin);
    } catch (e) {
      return;
    }
    if (url.origin !== window.location.origin) return;

    var aktualniIndex = ziskIndex(window.location.pathname);
    var cilIndex = ziskIndex(url.pathname);

    var trida;
    if (aktualniIndex !== -1 && cilIndex !== -1 && cilIndex !== aktualniIndex) {
      trida = cilIndex > aktualniIndex ? 'odchod-vlevo' : 'odchod-vpravo';
    } else if (cilIndex !== aktualniIndex) {
      trida = 'odchod-vlevo';
    } else {
      return; // stejna stranka
    }

    udalost.preventDefault();
    document.body.classList.add(trida);

    setTimeout(function () {
      window.location.href = url.href;
    }, 125);
  });
})();
