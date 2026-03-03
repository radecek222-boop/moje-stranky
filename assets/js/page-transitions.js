/**
 * Prechody mezi strankami - efekt listovani
 * Smer slajdu zavisi na poradi stranky v navigaci
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

  // Aplikovat smer vstupu podle sessionStorage
  var smer = sessionStorage.getItem('prechod-smer');
  if (smer === 'vpred') {
    document.body.classList.add('vstup-zprava');
  } else if (smer === 'zpet') {
    document.body.classList.add('vstup-zleva');
  }
  sessionStorage.removeItem('prechod-smer');

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

    var smerOdchodu, smerPrichodu;

    if (aktualniIndex !== -1 && cilIndex !== -1) {
      if (cilIndex > aktualniIndex) {
        smerOdchodu = 'odchod-vlevo';
        smerPrichodu = 'vpred';
      } else if (cilIndex < aktualniIndex) {
        smerOdchodu = 'odchod-vpravo';
        smerPrichodu = 'zpet';
      } else {
        return; // stejna stranka
      }
    } else {
      smerOdchodu = 'odchod-vlevo';
      smerPrichodu = 'vpred';
    }

    udalost.preventDefault();
    sessionStorage.setItem('prechod-smer', smerPrichodu);
    document.body.classList.add(smerOdchodu);

    setTimeout(function () {
      window.location.href = url.href;
    }, 135);
  });
})();
