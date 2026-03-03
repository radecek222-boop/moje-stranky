<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Diagnostika IndexedDB - Ztracené fotky</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #555;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .foto-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .foto-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .foto-item:last-child {
            border-bottom: none;
        }
        .foto-preview {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .reklamace-box {
            background: #f0f0f0;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid #333;
        }
        .stat-box {
            display: inline-block;
            padding: 20px 30px;
            background: #333;
            color: white;
            border-radius: 8px;
            margin: 10px;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.8;
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Diagnostika IndexedDB - Ztracené fotky</h1>

    <div class="info">
        <strong>ℹ️ CO TENTO NÁSTROJ DĚLÁ:</strong><br>
        • Prohledává IndexedDB ve vašem prohlížeči<br>
        • Hledá ztracené fotky které se neuložily na server<br>
        • Umožňuje obnovit fotky pro konkrétní reklamaci<br>
        • Pomáhá najít data která byla ztracena při vypršení session
    </div>

    <button class="btn" onclick="nactiVsechnyFotky()">📂 Načíst všechny uložené fotky</button>
    <button class="btn btn-danger" onclick="vymazatVsechno()">🗑️ Vymazat všechny fotky z IndexedDB</button>

    <div id="statistiky" style="margin: 30px 0; text-align: center;"></div>
    <div id="vysledky"></div>

</div>

<script src="assets/js/photo-storage-db.min.js"></script>
<script>
    // Načíst všechny fotky z IndexedDB
    async function nactiVsechnyFotky() {
        try {
            const db = await initPhotoStorageDB();
            const transaction = db.transaction(['photoSections'], 'readonly');
            const objectStore = transaction.objectStore('photoSections');
            const request = objectStore.getAll();

            request.onsuccess = () => {
                const data = request.result;
                db.close();

                const vysledkyDiv = document.getElementById('vysledky');
                const statistikyDiv = document.getElementById('statistiky');

                if (!data || data.length === 0) {
                    statistikyDiv.innerHTML = '';
                    vysledkyDiv.innerHTML = `
                        <div class="warning">
                            <strong>⚠️ Žádné uložené fotky</strong><br>
                            V IndexedDB nejsou žádné fotky. To může znamenat:
                            <ul>
                                <li>Fotky byly už odeslány na server a smazány</li>
                                <li>Technik ještě nepořídil žádné fotky</li>
                                <li>IndexedDB byl vymazán prohlížečem</li>
                            </ul>
                        </div>
                    `;
                    return;
                }

                // Statistiky
                let celkemFotek = 0;
                data.forEach(reklamace => {
                    Object.keys(reklamace.sections).forEach(key => {
                        celkemFotek += reklamace.sections[key].length;
                    });
                });

                statistikyDiv.innerHTML = `
                    <div class="stat-box">
                        <span class="stat-label">REKLAMACÍ S FOTKAMI</span>
                        ${data.length}
                    </div>
                    <div class="stat-box">
                        <span class="stat-label">CELKEM FOTEK</span>
                        ${celkemFotek}
                    </div>
                `;

                // Zobrazit detail každé reklamace
                let html = '<h2>📋 Nalezené fotky v IndexedDB</h2>';

                data.forEach(reklamace => {
                    const timestamp = new Date(reklamace.timestamp);
                    const timestampFormatted = timestamp.toLocaleString('cs-CZ');

                    let pocetFotekVReklamaci = 0;
                    Object.keys(reklamace.sections).forEach(key => {
                        pocetFotekVReklamaci += reklamace.sections[key].length;
                    });

                    html += `
                        <div class="reklamace-box">
                            <h3>Reklamace #${reklamace.reklamaceId}</h3>
                            <p><strong>Timestamp:</strong> ${timestampFormatted}</p>
                            <p><strong>Uživatel:</strong> ${reklamace.userEmail || 'unknown'}</p>
                            <p><strong>Počet fotek:</strong> ${pocetFotekVReklamaci}</p>

                            <button class="btn" onclick="zobrazitDetail(${reklamace.reklamaceId})">
                                👁️ Zobrazit detail fotek
                            </button>
                            <button class="btn" onclick="obnovitFotky(${reklamace.reklamaceId})">
                                🔄 Obnovit fotky v protokolu
                            </button>
                            <button class="btn btn-danger" onclick="smazatReklamaci(${reklamace.reklamaceId})">
                                🗑️ Smazat
                            </button>
                        </div>
                    `;
                });

                vysledkyDiv.innerHTML = html;
            };

            request.onerror = () => {
                document.getElementById('vysledky').innerHTML = `
                    <div class="error">
                        <strong>❌ CHYBA:</strong><br>
                        ${request.error.message}
                    </div>
                `;
                db.close();
            };

        } catch (error) {
            document.getElementById('vysledky').innerHTML = `
                <div class="error">
                    <strong>❌ CHYBA:</strong><br>
                    ${error.message}
                </div>
            `;
        }
    }

    // Zobrazit detail fotek pro reklamaci
    async function zobrazitDetail(reklamaceId) {
        try {
            const sections = await loadSectionsFromIndexedDB(reklamaceId);

            if (!sections) {
                alert('Žádné fotky nenalezeny');
                return;
            }

            let html = `<h3>Detail fotek pro reklamaci #${reklamaceId}</h3>`;
            html += '<div class="foto-list">';

            Object.keys(sections).forEach(sectionKey => {
                const photos = sections[sectionKey];
                if (photos && photos.length > 0) {
                    html += `<h4>Sekce: ${sectionKey}</h4>`;
                    photos.forEach((photo, index) => {
                        html += `
                            <div class="foto-item">
                                <div>
                                    <strong>Fotka ${index + 1}</strong><br>
                                    <small>Velikost: ${(photo.length / 1024).toFixed(2)} KB</small>
                                </div>
                                <img src="${photo}" class="foto-preview" alt="Preview">
                            </div>
                        `;
                    });
                }
            });

            html += '</div>';

            const vysledkyDiv = document.getElementById('vysledky');
            const reklamaceBox = vysledkyDiv.querySelector(`.reklamace-box:has(h3:contains("#${reklamaceId}"))`);
            if (reklamaceBox) {
                // Přidat detail pod reklamaci
                const detailDiv = document.createElement('div');
                detailDiv.innerHTML = html;
                reklamaceBox.appendChild(detailDiv);
            }

        } catch (error) {
            alert('Chyba při načítání fotek: ' + error.message);
        }
    }

    // Obnovit fotky v protokolu
    function obnovitFotky(reklamaceId) {
        const url = `protokol.php?reklamace_id=${reklamaceId}&obnovit_fotky=1`;
        if (confirm(`Otevřít protokol a obnovit fotky pro reklamaci #${reklamaceId}?`)) {
            window.location.href = url;
        }
    }

    // Smazat fotky pro jednu reklamaci
    async function smazatReklamaci(reklamaceId) {
        if (!confirm(`Opravdu smazat fotky pro reklamaci #${reklamaceId}?`)) {
            return;
        }

        try {
            await deleteSectionsFromIndexedDB(reklamaceId);
            alert(`Fotky pro reklamaci #${reklamaceId} byly smazány`);
            nactiVsechnyFotky(); // Refresh
        } catch (error) {
            alert('Chyba při mazání: ' + error.message);
        }
    }

    // Vymazat všechny fotky
    async function vymazatVsechno() {
        if (!confirm('POZOR! Opravdu smazat VŠECHNY fotky z IndexedDB? Tato akce je NEVRATNÁ!')) {
            return;
        }

        if (!confirm('Jste si JISTÍ? Všechny neuložené fotky budou TRVALE ZTRACENY!')) {
            return;
        }

        try {
            const db = await initPhotoStorageDB();
            const transaction = db.transaction(['photoSections'], 'readwrite');
            const objectStore = transaction.objectStore('photoSections');
            const request = objectStore.clear();

            request.onsuccess = () => {
                db.close();
                alert('Všechny fotky byly smazány z IndexedDB');
                nactiVsechnyFotky(); // Refresh
            };

            request.onerror = () => {
                db.close();
                alert('Chyba při mazání: ' + request.error.message);
            };

        } catch (error) {
            alert('Chyba: ' + error.message);
        }
    }

    // Auto-load při otevření stránky
    window.addEventListener('DOMContentLoaded', () => {
        nactiVsechnyFotky();
    });
</script>
</body>
</html>
