<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#000000">
    <title>TM Transport</title>
    <link rel="manifest" href="manifest-transport.json">
    <link rel="apple-touch-icon" href="assets/img/icon-192.png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #000;
            color: #fff;
            min-height: 100vh;
            padding: 10px;
            padding-bottom: 80px;
        }
        .header {
            text-align: center;
            padding: 15px 0;
            border-bottom: 1px solid #333;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .header .datum {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .tab {
            flex: 1;
            padding: 12px;
            background: #222;
            border: none;
            color: #888;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
        }
        .tab.active {
            background: #333;
            color: #fff;
        }
        .sekce {
            margin-bottom: 20px;
        }
        .sekce-nazev {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 0;
            border-bottom: 1px solid #222;
        }
        .jizda {
            display: flex;
            align-items: center;
            padding: 15px 10px;
            border-bottom: 1px solid #1a1a1a;
            cursor: pointer;
            transition: all 0.2s;
            gap: 12px;
        }
        .jizda:active {
            background: #111;
        }
        .jizda.hotovo {
            background: rgba(40, 167, 69, 0.15);
        }
        .jizda.hotovo .cas,
        .jizda.hotovo .jmeno,
        .jizda.hotovo .trasa {
            opacity: 0.5;
        }
        .check {
            width: 28px;
            height: 28px;
            border: 2px solid #444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .jizda.hotovo .check {
            background: #28a745;
            border-color: #28a745;
        }
        .check::after {
            content: '';
            width: 10px;
            height: 6px;
            border-left: 2px solid transparent;
            border-bottom: 2px solid transparent;
            transform: rotate(-45deg);
            margin-top: -2px;
        }
        .jizda.hotovo .check::after {
            border-color: #fff;
        }
        .cas {
            font-size: 20px;
            font-weight: 700;
            min-width: 55px;
        }
        .info {
            flex: 1;
            overflow: hidden;
        }
        .jmeno {
            font-size: 15px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .trasa {
            font-size: 12px;
            color: #888;
            margin-top: 3px;
        }
        .telefon-btn {
            width: 40px;
            height: 40px;
            background: #222;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            flex-shrink: 0;
        }
        .telefon-btn svg {
            width: 18px;
            height: 18px;
            fill: #fff;
        }
        .self-label {
            font-size: 11px;
            color: #666;
            background: #1a1a1a;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #111;
            padding: 15px;
            display: flex;
            gap: 10px;
            border-top: 1px solid #333;
        }
        .footer-btn {
            flex: 1;
            padding: 12px;
            background: #222;
            border: none;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
        }
        .footer-btn.reset {
            background: #333;
            color: #888;
        }
        .skryto {
            display: none;
        }
        .ridic-info {
            background: #111;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #888;
        }
        .ridic-info strong {
            color: #fff;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>TM TRANSPORT</h1>
    <div class="datum">Praha - Holešovice</div>
</div>

<div class="ridic-info">
    <strong>Milan</strong> (V-Class) - hlavní | <strong>Mirek</strong> (S-Class) - backup
</div>

<div class="tabs">
    <button class="tab active" onclick="zobrazDen('sobota')">SO 13.12.</button>
    <button class="tab" onclick="zobrazDen('nedele')">NE 14.12.</button>
</div>

<div id="obsah"></div>

<div class="footer">
    <button class="footer-btn reset" onclick="resetovat()">Resetovat</button>
    <button class="footer-btn" onclick="sdilej()">Sdílet stav</button>
</div>

<script>
const DATA = {
    sobota: [
        { id: 's1', cas: '21:30', jmeno: 'T78 (Manuele)', odkud: 'Marriott Airport', kam: 'Venue', tel: '46701424228' },
        { id: 's2', cas: '22:30', jmeno: 'BYORN (Bjorn)', odkud: 'Marriott Airport', kam: 'Venue', tel: '32471230478' },
        { id: 's3', cas: '22:30', jmeno: 'BYORN manager', odkud: 'Marriott Airport', kam: 'Venue', tel: '32477082652' },
        { id: 's4', cas: '23:30', jmeno: 'DYEN (Yanick + Sem)', odkud: 'Marriott Airport', kam: 'Venue', tel: '31642753844' }
    ],
    nedele: [
        { id: 'n1', cas: '01:50', jmeno: 'Fantasm (Kenzo + Lucas)', odkud: 'T3', kam: 'Venue', tel: '' },
        { id: 'n2', cas: '03:00', jmeno: 'Holy Priest (Simon +2)', odkud: 'T3', kam: 'Venue', tel: '4917672054357' },
        { id: 'n3', cas: '17:30', jmeno: 'Kenzo', odkud: 'Hotel Expo', kam: 'T2', tel: '' },
        { id: 'n4', cas: '17:30', jmeno: 'Lucas', odkud: 'Hotel Expo', kam: 'T2', tel: '' },
        { id: 'sep', typ: 'separator', text: 'ODVOZ PO SHOW (21:00-06:00)' },
        { id: 'n5', cas: '22:30', jmeno: 'T78', odkud: 'Venue', kam: 'Marriott Airport', self: false },
        { id: 'n6', cas: '23:30', jmeno: 'Byorn', odkud: 'Venue', kam: 'Marriott Airport', self: false },
        { id: 'n7', cas: '00:30', jmeno: 'Dyen', odkud: 'Venue', kam: 'Marriott Airport', self: false },
        { id: 'n8', cas: '02:30', jmeno: 'Fantasm', odkud: 'Venue', kam: 'Hotel Expo', self: false },
        { id: 'n9', cas: '04:00', jmeno: 'Holy Priest', odkud: 'Venue', kam: 'Marriott Airport', self: false }
    ]
};

let aktualniDen = 'sobota';
let stav = JSON.parse(localStorage.getItem('transport_stav') || '{}');

function ulozStav() {
    localStorage.setItem('transport_stav', JSON.stringify(stav));
}

function zobrazDen(den) {
    aktualniDen = den;
    document.querySelectorAll('.tab').forEach((t, i) => {
        t.classList.toggle('active', (i === 0 && den === 'sobota') || (i === 1 && den === 'nedele'));
    });
    renderuj();
}

function toggleJizda(id) {
    stav[id] = !stav[id];
    ulozStav();
    renderuj();
}

function renderuj() {
    const obsah = document.getElementById('obsah');
    const jizdy = DATA[aktualniDen];

    let html = '';

    jizdy.forEach(j => {
        if (j.typ === 'separator') {
            html += `<div class="sekce-nazev">${j.text}</div>`;
            return;
        }

        const hotovo = stav[j.id] ? 'hotovo' : '';
        const telBtn = j.tel ? `<a href="tel:+${j.tel}" class="telefon-btn" onclick="event.stopPropagation()">
            <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
        </a>` : '';

        html += `
        <div class="jizda ${hotovo}" onclick="toggleJizda('${j.id}')">
            <div class="check"></div>
            <div class="cas">${j.cas}</div>
            <div class="info">
                <div class="jmeno">${j.jmeno}</div>
                <div class="trasa">${j.odkud} → ${j.kam}</div>
            </div>
            ${telBtn}
        </div>`;
    });

    obsah.innerHTML = html;
}

function resetovat() {
    if (confirm('Resetovat všechny položky?')) {
        stav = {};
        ulozStav();
        renderuj();
    }
}

function sdilej() {
    const hotove = Object.keys(stav).filter(k => stav[k]).length;
    const celkem = DATA.sobota.length + DATA.nedele.filter(j => !j.typ).length;
    const text = `TECHMISSION Transport: ${hotove}/${celkem} hotovo`;

    if (navigator.share) {
        navigator.share({ title: 'Transport stav', text: text });
    } else {
        alert(text);
    }
}

// PWA install
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw-transport.js');
}

// Init
renderuj();
</script>

</body>
</html>
