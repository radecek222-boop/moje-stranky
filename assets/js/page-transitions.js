/**
 * View Transitions API - smerovy slide podle nav poradi
 * Funguje v Chrome 111+ / Edge. Firefox: standardni prechod.
 */
(function () {
  if (!document.startViewTransition) return; // fallback pro stare prohlizece

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

  // Zachytit kliknuti pro nastaveni smeru prechodu
  document.addEventListener('click', function (udalost) {
    var odkaz = udalost.target.closest('a[href]');
    if (!odkaz) return;

    var href = odkaz.getAttribute('href');
    if (!href || odkaz.target === '_blank' ||
        href.startsWith('#') || href.startsWith('mailto:') ||
        href.startsWith('tel:') || href.startsWith('javascript:')) return;

    var url;
    try { url = new URL(href, window.location.origin); }
    catch (e) { return; }
    if (url.origin !== window.location.origin) return;

    var aktualniIndex = ziskIndex(window.location.pathname);
    var cilIndex = ziskIndex(url.pathname);
    if (cilIndex === aktualniIndex) return;

    var smer = (cilIndex === -1 || aktualniIndex === -1 || cilIndex > aktualniIndex)
      ? 'vpred' : 'zpet';

    document.documentElement.setAttribute('data-prechod', smer);
  });
})();
