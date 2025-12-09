<?php
/**
 * Cenová nabídka - admin stránka
 * Vytvoření a správa cenových nabídek pro zákazníky
 */
require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Načíst data z reklamace pokud je ID předáno
$predvyplneno = null;
$reklamaceId = isset($_GET['reklamace_id']) ? intval($_GET['reklamace_id']) : 0;

if ($reklamaceId > 0) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT jmeno, email, telefon, adresa, mesto, psc FROM wgs_reklamace WHERE reklamace_id = ?");
        $stmt->execute([$reklamaceId]);
        $predvyplneno = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Chyba při načítání reklamace: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cenová nabídka - WGS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #0a0a0a;
            color: #fff;
            min-height: 100vh;
        }
        .nabidka-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .nabidka-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }
        .nabidka-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        .nabidka-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        .nabidka-tab {
            padding: 10px 20px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            color: #888;
            cursor: pointer;
            transition: all 0.2s;
        }
        .nabidka-tab:hover { border-color: #555; color: #fff; }
        .nabidka-tab.active {
            background: #222;
            border-color: #39ff14;
            color: #39ff14;
        }
        .nabidka-section { display: none; }
        .nabidka-section.active { display: block; }

        /* Formulář */
        .nabidka-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        @media (max-width: 768px) {
            .nabidka-form { grid-template-columns: 1fr; }
        }
        .form-card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 25px;
        }
        .form-card h2 {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
            color: #aaa;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 5px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
            font-size: 0.95rem;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #39ff14;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }

        /* Ceník - výběr položek */
        .cenik-kategorie {
            margin-bottom: 20px;
        }
        .cenik-kategorie-nadpis {
            font-size: 0.9rem;
            font-weight: 500;
            color: #39ff14;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #333;
        }
        .cenik-polozka {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #111;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .cenik-polozka:hover { background: #1a1a1a; }
        .cenik-polozka.vybrana {
            background: rgba(57, 255, 20, 0.1);
            border: 1px solid #39ff14;
        }
        .cenik-polozka-nazev {
            flex: 1;
            font-size: 0.85rem;
        }
        .cenik-polozka-cena {
            font-size: 0.85rem;
            color: #888;
        }
        .cenik-polozka-pocet {
            width: 60px;
            padding: 5px 8px;
            text-align: center;
        }

        /* Vybrané položky */
        .vybrane-polozky {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }
        .vybrane-polozky h3 {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 15px;
        }
        .vybrana-polozka {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #111;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .vybrana-polozka-nazev { flex: 1; font-size: 0.85rem; }
        .vybrana-polozka-cena-input {
            width: 100px;
            padding: 5px 8px;
            text-align: right;
        }
        .vybrana-polozka-odebrat {
            background: none;
            border: none;
            color: #ff4444;
            cursor: pointer;
            padding: 5px;
            font-size: 1.2rem;
        }

        /* Celková cena */
        .celkova-cena {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #111;
            border-radius: 8px;
            margin-top: 20px;
        }
        .celkova-cena-label {
            font-size: 1rem;
            color: #888;
        }
        .celkova-cena-hodnota {
            font-size: 1.5rem;
            font-weight: 600;
            color: #39ff14;
        }

        /* Tlačítka */
        .nabidka-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn-primary {
            background: #28a745;
            border: none;
            color: #fff;
        }
        .btn-primary:hover { background: #218838; }
        .btn-secondary {
            background: transparent;
            border: 1px solid #444;
            color: #ccc;
        }
        .btn-secondary:hover { border-color: #666; }

        /* Seznam nabídek */
        .nabidky-tabulka {
            width: 100%;
            border-collapse: collapse;
        }
        .nabidky-tabulka th,
        .nabidky-tabulka td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        .nabidky-tabulka th {
            background: #1a1a1a;
            color: #888;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .nabidky-tabulka td { font-size: 0.9rem; }
        .stav-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .stav-nova { background: #333; color: #888; }
        .stav-odeslana { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .stav-potvrzena { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        .stav-expirovana { background: rgba(220, 53, 69, 0.2); color: #dc3545; }

        /* Hledání v ceníku */
        .cenik-hledani {
            margin-bottom: 15px;
        }
        .cenik-hledani input {
            width: 100%;
            padding: 10px 15px;
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 0.9rem;
        }
        .cenik-seznam {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/hamburger-menu.php'; ?>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

    <main class="nabidka-container" id="main-content">
        <div class="nabidka-header">
            <h1>Cenová nabídka</h1>
            <?php if ($reklamaceId): ?>
                <span style="color: #888; font-size: 0.9rem;">Z poptávky #<?php echo $reklamaceId; ?></span>
            <?php endif; ?>
        </div>

        <div class="nabidka-tabs">
            <button class="nabidka-tab active" data-tab="nova">Nová nabídka</button>
            <button class="nabidka-tab" data-tab="seznam">Seznam nabídek</button>
        </div>

        <!-- Nová nabídka -->
        <section class="nabidka-section active" id="section-nova">
            <div class="nabidka-form">
                <!-- Levá strana - zákazník -->
                <div class="form-card">
                    <h2>Údaje zákazníka</h2>
                    <div class="form-group">
                        <label for="zakaznik_jmeno">Jméno a příjmení *</label>
                        <input type="text" id="zakaznik_jmeno" required
                               value="<?php echo htmlspecialchars($predvyplneno['jmeno'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="zakaznik_email">Email *</label>
                        <input type="email" id="zakaznik_email" required
                               value="<?php echo htmlspecialchars($predvyplneno['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="zakaznik_telefon">Telefon</label>
                        <input type="tel" id="zakaznik_telefon"
                               value="<?php echo htmlspecialchars($predvyplneno['telefon'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="zakaznik_adresa">Adresa</label>
                        <textarea id="zakaznik_adresa"><?php
                            $adresaParts = [];
                            if (!empty($predvyplneno['adresa'])) $adresaParts[] = $predvyplneno['adresa'];
                            if (!empty($predvyplneno['mesto'])) $adresaParts[] = $predvyplneno['mesto'];
                            if (!empty($predvyplneno['psc'])) $adresaParts[] = $predvyplneno['psc'];
                            echo htmlspecialchars(implode(', ', $adresaParts));
                        ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="poznamka">Poznámka (interní)</label>
                        <textarea id="poznamka" placeholder="Interní poznámka k nabídce..."></textarea>
                    </div>
                </div>

                <!-- Pravá strana - ceník -->
                <div class="form-card">
                    <h2>Výběr služeb z ceníku</h2>
                    <div class="cenik-hledani">
                        <input type="text" id="cenik-hledani" placeholder="Hledat službu...">
                    </div>
                    <div class="cenik-seznam" id="cenik-seznam">
                        <p style="color: #666; text-align: center; padding: 20px;">Načítám ceník...</p>
                    </div>

                    <div class="vybrane-polozky" id="vybrane-polozky">
                        <h3>Vybrané položky</h3>
                        <div id="vybrane-seznam">
                            <p style="color: #666; font-size: 0.85rem;">Zatím nejsou vybrány žádné položky</p>
                        </div>
                    </div>

                    <div class="celkova-cena">
                        <span class="celkova-cena-label">Celkem (bez DPH):</span>
                        <span class="celkova-cena-hodnota" id="celkova-cena">0,00 EUR</span>
                    </div>
                </div>
            </div>

            <div class="nabidka-actions">
                <button type="button" class="btn btn-secondary" id="btn-ulozit">Uložit koncept</button>
                <button type="button" class="btn btn-primary" id="btn-odeslat">Vytvořit a odeslat</button>
            </div>
        </section>

        <!-- Seznam nabídek -->
        <section class="nabidka-section" id="section-seznam">
            <div class="form-card">
                <table class="nabidky-tabulka">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Zákazník</th>
                            <th>Email</th>
                            <th>Cena</th>
                            <th>Stav</th>
                            <th>Platnost do</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody id="nabidky-tbody">
                        <tr><td colspan="7" style="text-align: center; color: #666;">Načítám...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
    (function() {
        'use strict';

        // State
        let cenikPolozky = [];
        let vybranePolozky = [];

        // Elements
        const csrfToken = document.querySelector('[name="csrf_token"]').value;
        const cenikSeznam = document.getElementById('cenik-seznam');
        const vybraneSeznam = document.getElementById('vybrane-seznam');
        const celkovaCenaEl = document.getElementById('celkova-cena');
        const hledaniInput = document.getElementById('cenik-hledani');

        // Tabs
        document.querySelectorAll('.nabidka-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.nabidka-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.nabidka-section').forEach(s => s.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('section-' + tab.dataset.tab).classList.add('active');

                if (tab.dataset.tab === 'seznam') {
                    nacistSeznamNabidek();
                }
            });
        });

        // Načíst ceník
        async function nacistCenik() {
            try {
                const response = await fetch('/api/nabidka_api.php?action=cenik');
                const data = await response.json();

                if (data.status === 'success') {
                    cenikPolozky = data.polozky;
                    zobrazitCenik(cenikPolozky);
                }
            } catch (e) {
                console.error('Chyba při načítání ceníku:', e);
                cenikSeznam.innerHTML = '<p style="color: #ff4444;">Chyba při načítání ceníku</p>';
            }
        }

        // Zobrazit ceník
        function zobrazitCenik(polozky) {
            const kategorieMap = {};
            polozky.forEach(p => {
                const kat = p.category || 'Ostatní';
                if (!kategorieMap[kat]) kategorieMap[kat] = [];
                kategorieMap[kat].push(p);
            });

            let html = '';
            for (const [kategorie, items] of Object.entries(kategorieMap)) {
                html += `<div class="cenik-kategorie">
                    <div class="cenik-kategorie-nadpis">${kategorie}</div>`;
                items.forEach(item => {
                    const cena = item.price_from || item.price_to || 0;
                    const cenaText = item.price_from && item.price_to
                        ? `${item.price_from} - ${item.price_to} ${item.price_unit}`
                        : `${cena} ${item.price_unit}`;
                    const jeVybrana = vybranePolozky.some(v => v.id === item.id);
                    html += `<div class="cenik-polozka ${jeVybrana ? 'vybrana' : ''}" data-id="${item.id}">
                        <span class="cenik-polozka-nazev">${item.service_name}</span>
                        <span class="cenik-polozka-cena">${cenaText}</span>
                    </div>`;
                });
                html += '</div>';
            }
            cenikSeznam.innerHTML = html;

            // Click handlery
            cenikSeznam.querySelectorAll('.cenik-polozka').forEach(el => {
                el.addEventListener('click', () => {
                    const id = parseInt(el.dataset.id);
                    const polozka = cenikPolozky.find(p => p.id === id);
                    if (polozka) {
                        pridatPolozku(polozka);
                        el.classList.add('vybrana');
                    }
                });
            });
        }

        // Přidat položku
        function pridatPolozku(polozka) {
            // Kontrola duplicity
            if (vybranePolozky.some(v => v.id === polozka.id)) {
                return;
            }

            vybranePolozky.push({
                id: polozka.id,
                nazev: polozka.service_name,
                cena: polozka.price_from || polozka.price_to || 0,
                pocet: 1
            });

            aktualizovatVybrane();
        }

        // Odebrat položku
        function odebratPolozku(id) {
            vybranePolozky = vybranePolozky.filter(v => v.id !== id);
            aktualizovatVybrane();

            // Aktualizovat vzhled v ceníku
            const el = cenikSeznam.querySelector(`[data-id="${id}"]`);
            if (el) el.classList.remove('vybrana');
        }

        // Aktualizovat vybrané
        function aktualizovatVybrane() {
            if (vybranePolozky.length === 0) {
                vybraneSeznam.innerHTML = '<p style="color: #666; font-size: 0.85rem;">Zatím nejsou vybrány žádné položky</p>';
                celkovaCenaEl.textContent = '0,00 EUR';
                return;
            }

            let html = '';
            let celkem = 0;

            vybranePolozky.forEach((p, idx) => {
                const mezisoucet = p.cena * p.pocet;
                celkem += mezisoucet;
                html += `<div class="vybrana-polozka">
                    <span class="vybrana-polozka-nazev">${p.nazev}</span>
                    <input type="number" class="vybrana-polozka-cena-input" value="${p.cena}"
                           data-idx="${idx}" min="0" step="0.01" title="Cena">
                    <span style="color: #888;">x</span>
                    <input type="number" class="cenik-polozka-pocet" value="${p.pocet}"
                           data-idx="${idx}" min="1" title="Počet">
                    <span style="color: #888;">= ${mezisoucet.toFixed(2)} EUR</span>
                    <button type="button" class="vybrana-polozka-odebrat" data-id="${p.id}">&times;</button>
                </div>`;
            });

            vybraneSeznam.innerHTML = html;
            celkovaCenaEl.textContent = celkem.toFixed(2).replace('.', ',') + ' EUR';

            // Event handlery pro změnu ceny/počtu
            vybraneSeznam.querySelectorAll('.vybrana-polozka-cena-input').forEach(input => {
                input.addEventListener('change', () => {
                    const idx = parseInt(input.dataset.idx);
                    vybranePolozky[idx].cena = parseFloat(input.value) || 0;
                    aktualizovatVybrane();
                });
            });

            vybraneSeznam.querySelectorAll('.cenik-polozka-pocet').forEach(input => {
                input.addEventListener('change', () => {
                    const idx = parseInt(input.dataset.idx);
                    vybranePolozky[idx].pocet = parseInt(input.value) || 1;
                    aktualizovatVybrane();
                });
            });

            vybraneSeznam.querySelectorAll('.vybrana-polozka-odebrat').forEach(btn => {
                btn.addEventListener('click', () => {
                    odebratPolozku(parseInt(btn.dataset.id));
                });
            });
        }

        // Hledání
        hledaniInput.addEventListener('input', () => {
            const hledany = hledaniInput.value.toLowerCase().trim();
            if (!hledany) {
                zobrazitCenik(cenikPolozky);
                return;
            }
            const filtrovane = cenikPolozky.filter(p =>
                p.service_name.toLowerCase().includes(hledany) ||
                (p.category && p.category.toLowerCase().includes(hledany))
            );
            zobrazitCenik(filtrovane);
        });

        // Uložit a odeslat
        document.getElementById('btn-odeslat').addEventListener('click', async () => {
            const jmeno = document.getElementById('zakaznik_jmeno').value.trim();
            const email = document.getElementById('zakaznik_email').value.trim();

            if (!jmeno || !email) {
                alert('Vyplňte jméno a email zákazníka');
                return;
            }

            if (vybranePolozky.length === 0) {
                alert('Vyberte alespoň jednu položku z ceníku');
                return;
            }

            // Vytvořit nabídku
            const formData = new FormData();
            formData.append('action', 'vytvorit');
            formData.append('csrf_token', csrfToken);
            formData.append('zakaznik_jmeno', jmeno);
            formData.append('zakaznik_email', email);
            formData.append('zakaznik_telefon', document.getElementById('zakaznik_telefon').value);
            formData.append('zakaznik_adresa', document.getElementById('zakaznik_adresa').value);
            formData.append('poznamka', document.getElementById('poznamka').value);
            formData.append('polozky', JSON.stringify(vybranePolozky));

            try {
                const response = await fetch('/api/nabidka_api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.status !== 'success') {
                    alert('Chyba: ' + data.message);
                    return;
                }

                const nabidkaId = data.nabidka_id;

                // Odeslat email
                const odeslatData = new FormData();
                odeslatData.append('action', 'odeslat');
                odeslatData.append('csrf_token', csrfToken);
                odeslatData.append('nabidka_id', nabidkaId);

                const odeslatResponse = await fetch('/api/nabidka_api.php', {
                    method: 'POST',
                    body: odeslatData
                });
                const odeslatResult = await odeslatResponse.json();

                if (odeslatResult.status === 'success') {
                    alert('Nabídka byla vytvořena a odeslána na ' + email);
                    // Reset formuláře
                    vybranePolozky = [];
                    aktualizovatVybrane();
                    document.getElementById('zakaznik_jmeno').value = '';
                    document.getElementById('zakaznik_email').value = '';
                    document.getElementById('zakaznik_telefon').value = '';
                    document.getElementById('zakaznik_adresa').value = '';
                    document.getElementById('poznamka').value = '';
                    zobrazitCenik(cenikPolozky);
                } else {
                    alert('Nabídka vytvořena, ale odeslání selhalo: ' + odeslatResult.message);
                }

            } catch (e) {
                console.error('Chyba:', e);
                alert('Chyba při vytváření nabídky');
            }
        });

        // Načíst seznam nabídek
        async function nacistSeznamNabidek() {
            try {
                const response = await fetch('/api/nabidka_api.php?action=seznam');
                const data = await response.json();

                if (data.status === 'success') {
                    zobrazitSeznamNabidek(data.nabidky);
                }
            } catch (e) {
                console.error('Chyba:', e);
            }
        }

        function zobrazitSeznamNabidek(nabidky) {
            const tbody = document.getElementById('nabidky-tbody');

            if (!nabidky || nabidky.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #666;">Žádné nabídky</td></tr>';
                return;
            }

            let html = '';
            nabidky.forEach(n => {
                const stavClass = 'stav-' + n.stav;
                const stavText = {nova: 'Nová', odeslana: 'Odesláno', potvrzena: 'Potvrzeno', expirovana: 'Vypršela'}[n.stav] || n.stav;
                const platnost = n.platnost_do ? new Date(n.platnost_do).toLocaleDateString('cs-CZ') : '-';

                html += `<tr>
                    <td>${n.id}</td>
                    <td>${n.zakaznik_jmeno}</td>
                    <td>${n.zakaznik_email}</td>
                    <td>${parseFloat(n.celkova_cena).toFixed(2)} ${n.mena}</td>
                    <td><span class="stav-badge ${stavClass}">${stavText}</span></td>
                    <td>${platnost}</td>
                    <td>
                        ${n.stav === 'nova' ? `<button onclick="znovu Odeslat(${n.id})" style="background: #333; border: none; color: #fff; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Odeslat</button>` : ''}
                    </td>
                </tr>`;
            });

            tbody.innerHTML = html;
        }

        // Init
        nacistCenik();

    })();
    </script>
</body>
</html>
