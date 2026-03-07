<?php
require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}
?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title>DEBUG: Touch/Scroll diagnostika</title>
<style>
body { font-family: monospace; font-size: 13px; padding: 10px; background: #111; color: #eee; }
h2 { color: #39ff14; margin: 10px 0 5px; font-size: 14px; }
.blok { background: #222; border: 1px solid #444; padding: 8px; margin: 6px 0; border-radius: 4px; }
.chyba { color: #ff4444; font-weight: bold; }
.ok { color: #39ff14; }
.info { color: #aaa; }
button { background: #333; color: #fff; border: 1px solid #666; padding: 8px 14px; margin: 4px; border-radius: 4px; font-size: 13px; }
#log { white-space: pre-wrap; word-break: break-all; }
</style>
</head>
<body>

<h2>TOUCH / SCROLL DIAGNOSTIKA</h2>

<div class="blok" id="zarizeni"></div>
<div class="blok" id="viewport"></div>
<div class="blok" id="styly-body"></div>

<h2>TEST MODAL (klikni na tlacitko)</h2>
<button onclick="otevritModal()">Otevrit testovaci modal</button>
<button onclick="document.getElementById('log').textContent = ''">Smazat log</button>

<div class="blok">
<div id="log" class="info">-- log --</div>
</div>

<!-- testovaci modal -->
<div id="testModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.9); z-index:9999; overflow-y:auto; -webkit-overflow-scrolling:touch;">
  <div style="background:#1a1a1a; margin:20px; padding:15px; border-radius:8px; min-height:200px;">
    <button onclick="zavritModal()" style="float:right">ZAVRIT</button>
    <h3 style="color:#39ff14; margin:0 0 10px">Modal je otevreny</h3>
    <div id="modal-styly" style="font-size:12px; font-family:monospace;"></div>
    <div style="height:600px; background: linear-gradient(#222, #333); margin-top:10px; display:flex; align-items:center; justify-content:center; color:#666;">
      SCROLLUJ DOLU → test scroll<br>PRSTAMA ZOOM → test zoom
    </div>
  </div>
</div>

<script>
var logEl = document.getElementById('log');

function log(text, typ) {
  var prefix = typ === 'chyba' ? '❌ ' : typ === 'ok' ? '✅ ' : '   ';
  logEl.textContent += prefix + text + '\n';
}

function ziskejStyle(el) {
  var cs = window.getComputedStyle(el);
  return {
    overflow: el.style.overflow || cs.overflow,
    overflowY: el.style.overflowY || cs.overflowY,
    touchAction: el.style.touchAction || cs.touchAction,
    position: el.style.position || cs.position,
    pointerEvents: cs.pointerEvents,
    overscrollBehavior: cs.overscrollBehavior || 'N/A'
  };
}

function zobrazZarizeni() {
  var el = document.getElementById('zarizeni');
  el.innerHTML =
    '<b>Zarizeni:</b> ' + navigator.userAgent.substring(0, 80) + '<br>' +
    '<b>iOS:</b> ' + (/iPad|iPhone|iPod/.test(navigator.userAgent) ? '<span class="chyba">ANO</span>' : '<span class="ok">NE</span>') + '<br>' +
    '<b>PWA:</b> ' + (window.navigator.standalone ? '<span class="info">ANO</span>' : 'NE') + '<br>' +
    '<b>innerWidth:</b> ' + window.innerWidth + 'px';
}

function zobrazViewport() {
  var metas = document.querySelectorAll('meta[name="viewport"]');
  var el = document.getElementById('viewport');
  var html = '<b>Viewport meta:</b><br>';
  metas.forEach(function(m) { html += m.getAttribute('content') + '<br>'; });
  el.innerHTML = html;
}

function zobrazStyleBody() {
  var bdy = ziskejStyle(document.body);
  var htm = ziskejStyle(document.documentElement);
  var el = document.getElementById('styly-body');

  function row(nazev, val, spatne) {
    var cls = spatne.includes(val) ? 'chyba' : 'ok';
    return '<b>' + nazev + ':</b> <span class="' + cls + '">' + val + '</span><br>';
  }

  el.innerHTML = '<b>-- BEZ MODALУ --</b><br>' +
    '<b>html:</b><br>' +
    row('  overflow', htm.overflow, ['hidden']) +
    row('  touchAction', htm.touchAction, ['none','manipulation','pan-x']) +
    '<b>body:</b><br>' +
    row('  overflow', bdy.overflow, ['hidden']) +
    row('  position', bdy.position, ['fixed']) +
    row('  touchAction', bdy.touchAction, ['none','manipulation','pan-x']) +
    row('  pointerEvents', bdy.pointerEvents, ['none']);
}

function otevritModal() {
  document.getElementById('testModal').style.display = 'block';

  // Simuluj co dela ModalManager
  if (window.scrollLock) {
    window.scrollLock.enable('debug-test');
    log('scrollLock.enable() zavolano', 'info');
    log('scrollLock.isIOS = ' + window.scrollLock.isIOS, 'info');
    log('scrollLock.isPWA = ' + window.scrollLock.isPWA, 'info');
  } else {
    log('window.scrollLock NENI DEFINOVAN', 'chyba');
    document.body.classList.add('modal-open');
  }

  setTimeout(function() {
    var bdy = ziskejStyle(document.body);
    var htm = ziskejStyle(document.documentElement);
    var modal = document.getElementById('testModal');
    var mst = ziskejStyle(modal);

    var html = '<b>-- PO OTEVRENI MODALU --</b><br>';
    html += '<b>html:</b><br>';
    html += '  overflow: <span class="' + (htm.overflow === 'hidden' ? 'chyba' : 'ok') + '">' + htm.overflow + '</span><br>';
    html += '  touchAction: <span class="' + (htm.touchAction === 'none' ? 'chyba' : 'ok') + '">' + htm.touchAction + '</span><br>';
    html += '<b>body:</b><br>';
    html += '  overflow: ' + bdy.overflow + '<br>';
    html += '  position: <span class="' + (bdy.position === 'fixed' ? 'chyba' : 'ok') + '">' + bdy.position + '</span><br>';
    html += '  touchAction: <span class="' + (bdy.touchAction === 'none' ? 'chyba' : 'ok') + '">' + bdy.touchAction + '</span><br>';
    html += '<b>modal overlay:</b><br>';
    html += '  overflow: ' + mst.overflow + '<br>';
    html += '  touchAction: ' + mst.touchAction + '<br>';
    html += '  pointerEvents: ' + mst.pointerEvents + '<br>';

    // Inline styly primo
    html += '<b>INLINE styly (JS nastavene):</b><br>';
    html += '  html.style.overflow: <span class="' + (document.documentElement.style.overflow === 'hidden' ? 'chyba' : 'ok') + '">"' + document.documentElement.style.overflow + '"</span><br>';
    html += '  body.style.overflow: "' + document.body.style.overflow + '"<br>';
    html += '  body.style.position: <span class="' + (document.body.style.position === 'fixed' ? 'chyba' : 'ok') + '">"' + document.body.style.position + '"</span><br>';
    html += '  body.style.touchAction: "' + document.body.style.touchAction + '"<br>';
    html += '  body.classList: "' + document.body.className + '"<br>';

    document.getElementById('modal-styly').innerHTML = html;
  }, 300);
}

function zavritModal() {
  if (window.scrollLock) {
    window.scrollLock.disable('debug-test');
  } else {
    document.body.classList.remove('modal-open');
  }
  document.getElementById('testModal').style.display = 'none';
}

// Zachyt touch eventy
document.addEventListener('touchstart', function(e) {
  if (e.touches.length > 1) {
    log('touchstart: ' + e.touches.length + ' prsty na ' + e.target.tagName + '#' + e.target.id, 'info');
    log('  defaultPrevented=' + e.defaultPrevented + ' cancelable=' + e.cancelable, 'info');
  }
}, { passive: true });

zobrazZarizeni();
zobrazViewport();
zobrazStyleBody();
</script>
</body>
</html>
