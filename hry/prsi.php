<?php
/**
 * Karetní hra PRŠÍ
 *
 * Pravidla:
 * - 32 karet (7, 8, 9, 10, J, Q, K, A ve 4 barvách)
 * - Každý hráč dostane 4 karty
 * - Přikládat kartu stejné barvy nebo hodnoty
 * - Speciální karty:
 *   - 7: další hráč bere 2 karty (kumuluje se)
 *   - Eso: další hráč stojí
 *   - Svršek (Q): změna barvy
 *   - Král pikový: další hráč bere 4 karty
 * - Vyhrává kdo se první zbaví karet
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? $_SESSION['email'] ?? 'Hráč';

// Režim: solo (proti PC) nebo multiplayer (místnost)
$rezim = $_GET['rezim'] ?? 'solo';
$mistnostId = isset($_GET['mistnost']) ? (int)$_GET['mistnost'] : null;

// Aktualizovat online status
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO wgs_hry_online (user_id, username, posledni_aktivita, aktualni_hra, mistnost_id)
        VALUES (:user_id, :username, NOW(), 'prsi', :mistnost_id)
        ON DUPLICATE KEY UPDATE
            posledni_aktivita = NOW(),
            aktualni_hra = 'prsi',
            mistnost_id = :mistnost_id2
    ");
    $stmt->execute([
        'user_id' => $userId,
        'username' => $username,
        'mistnost_id' => $mistnostId,
        'mistnost_id2' => $mistnostId
    ]);
} catch (PDOException $e) {
    error_log("Prsi error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prší | White Glove Service</title>
    <link rel="stylesheet" href="/assets/css/wgs-base.min.css">
    <style>
        :root {
            --prsi-bg: #0a4a0a;
            --prsi-table: #0d5f0d;
            --prsi-border: #1a7a1a;
            --prsi-card-bg: #fff;
            --prsi-card-border: #ccc;
            --prsi-red: #c41e3a;
            --prsi-black: #1a1a1a;
            --prsi-accent: #39ff14;
        }

        body {
            background: var(--prsi-bg);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .prsi-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Herní stůl */
        .herni-stul {
            background: var(--prsi-table);
            border: 4px solid var(--prsi-border);
            border-radius: 20px;
            min-height: 70vh;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: inset 0 0 50px rgba(0,0,0,0.3);
        }

        /* Protihráč (nahoře) */
        .protihra-zona {
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            min-height: 100px;
        }

        .karta-rub {
            width: 50px;
            height: 70px;
            background: linear-gradient(135deg, #1a237e 0%, #0d47a1 100%);
            border: 2px solid #fff;
            border-radius: 8px;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.3);
        }

        /* Střed stolu */
        .stred-stolu {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 3rem;
            padding: 2rem;
        }

        /* Balíček */
        .balicek {
            position: relative;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .balicek:hover {
            transform: scale(1.05);
        }

        .balicek .karta-rub {
            width: 80px;
            height: 112px;
        }

        .balicek-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            color: #fff;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        /* Odkládací balíček */
        .odkladaci {
            position: relative;
        }

        .odkladaci-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            color: #fff;
            font-size: 0.8rem;
        }

        /* Karta */
        .karta {
            width: 80px;
            height: 112px;
            background: var(--prsi-card-bg);
            border: 2px solid var(--prsi-card-border);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 5px;
            font-family: 'Times New Roman', serif;
            box-shadow: 2px 2px 8px rgba(0,0,0,0.3);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            user-select: none;
        }

        .karta:hover {
            transform: translateY(-5px);
            box-shadow: 4px 4px 15px rgba(0,0,0,0.4);
        }

        .karta.cervena {
            color: var(--prsi-red);
        }

        .karta.cerna {
            color: var(--prsi-black);
        }

        .karta.vybrana {
            transform: translateY(-15px);
            box-shadow: 0 0 20px var(--prsi-accent);
            border-color: var(--prsi-accent);
        }

        .karta.nelze {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .karta-roh {
            font-size: 1rem;
            font-weight: bold;
            line-height: 1;
        }

        .karta-stred {
            font-size: 2.5rem;
            text-align: center;
        }

        .karta-roh-dole {
            transform: rotate(180deg);
        }

        /* Moje karty (dole) */
        .moje-zona {
            padding: 1rem 2rem;
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: -10px;
            min-height: 140px;
            flex-wrap: wrap;
        }

        .moje-zona .karta {
            margin: 0 -10px;
        }

        /* Info panel */
        .info-panel {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0,0,0,0.7);
            padding: 1rem;
            border-radius: 10px;
            color: #fff;
            min-width: 150px;
        }

        .info-radek {
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }

        .info-label {
            color: #aaa;
        }

        .info-hodnota {
            font-weight: bold;
        }

        .info-hodnota.na-tahu {
            color: var(--prsi-accent);
        }

        /* Akční tlačítka */
        .akce-panel {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .akce-btn {
            background: rgba(0,0,0,0.7);
            color: #fff;
            border: 1px solid #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .akce-btn:hover {
            background: var(--prsi-accent);
            color: #000;
            border-color: var(--prsi-accent);
        }

        .akce-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Volba barvy modal */
        .volba-barvy-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .volba-barvy-overlay.active {
            display: flex;
        }

        .volba-barvy-modal {
            background: #1a1a1a;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
        }

        .volba-barvy-modal h3 {
            color: #fff;
            margin-bottom: 1.5rem;
        }

        .volba-barvy-btns {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .volba-barvy-btn {
            width: 60px;
            height: 60px;
            border: none;
            border-radius: 10px;
            font-size: 2rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .volba-barvy-btn:hover {
            transform: scale(1.2);
        }

        .volba-barvy-btn.srdce { background: #ffdddd; color: var(--prsi-red); }
        .volba-barvy-btn.kary { background: #ffdddd; color: var(--prsi-red); }
        .volba-barvy-btn.piky { background: #dddddd; color: var(--prsi-black); }
        .volba-barvy-btn.kriz { background: #dddddd; color: var(--prsi-black); }

        /* Zprávy */
        .zprava {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.9);
            color: var(--prsi-accent);
            padding: 2rem 4rem;
            border-radius: 15px;
            font-size: 1.5rem;
            z-index: 999;
            animation: fadeInOut 2s forwards;
            border: 2px solid var(--prsi-accent);
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
            20% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            80% { opacity: 1; }
            100% { opacity: 0; }
        }

        /* Výhra/Prohra overlay */
        .vysledek-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            flex-direction: column;
        }

        .vysledek-overlay.active {
            display: flex;
        }

        .vysledek-text {
            font-size: 4rem;
            font-weight: bold;
            margin-bottom: 2rem;
        }

        .vysledek-text.vyhra {
            color: var(--prsi-accent);
            text-shadow: 0 0 30px var(--prsi-accent);
        }

        .vysledek-text.prohra {
            color: #ff4444;
        }

        .vysledek-btn {
            background: var(--prsi-accent);
            color: #000;
            border: none;
            padding: 1rem 3rem;
            font-size: 1.2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }

        /* Aktivní barva indikátor */
        .aktivni-barva {
            position: absolute;
            top: 50%;
            right: -40px;
            transform: translateY(-50%);
            font-size: 2rem;
            background: rgba(255,255,255,0.9);
            padding: 0.5rem;
            border-radius: 8px;
        }

        /* K tažení indikátor */
        .k-tazeni {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #ff4444;
            color: #fff;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        /* Zpět tlačítko */
        .zpet-btn {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(0,0,0,0.7);
            color: #fff;
            border: 1px solid #fff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .zpet-btn:hover {
            background: #fff;
            color: #000;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .karta {
                width: 60px;
                height: 84px;
            }

            .karta-stred {
                font-size: 1.8rem;
            }

            .moje-zona .karta {
                margin: 0 -15px;
            }

            .stred-stolu {
                gap: 1.5rem;
            }

            .balicek .karta-rub {
                width: 60px;
                height: 84px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <main class="prsi-container">
        <div class="herni-stul" id="herniStul">
            <a href="/hry.php" class="zpet-btn">Zpět do lobby</a>

            <!-- Protihráč -->
            <div class="protihra-zona" id="protihrZona">
                <!-- Karty protihráče (rubem) -->
            </div>

            <!-- Střed stolu -->
            <div class="stred-stolu">
                <!-- Balíček -->
                <div class="balicek" id="balicek" title="Táhnout kartu">
                    <div class="karta-rub"></div>
                    <div class="karta-rub" style="position:absolute;top:2px;left:2px;"></div>
                    <div class="karta-rub" style="position:absolute;top:4px;left:4px;"></div>
                    <span class="balicek-label">Balíček (<span id="balicekPocet">0</span>)</span>
                    <div class="k-tazeni" id="kTazeni" style="display:none;">+<span id="kTazeniPocet">0</span></div>
                </div>

                <!-- Odkládací balíček -->
                <div class="odkladaci" id="odkladaci">
                    <!-- Vrchní karta -->
                    <span class="odkladaci-label">Odkládací</span>
                    <div class="aktivni-barva" id="aktivniBarva" style="display:none;"></div>
                </div>
            </div>

            <!-- Moje karty -->
            <div class="moje-zona" id="mojeZona">
                <!-- Moje karty -->
            </div>

            <!-- Info panel -->
            <div class="info-panel">
                <div class="info-radek">
                    <span class="info-label">Na tahu:</span>
                    <span class="info-hodnota" id="naTahu">--</span>
                </div>
                <div class="info-radek">
                    <span class="info-label">Moje karty:</span>
                    <span class="info-hodnota" id="mojeKartyPocet">0</span>
                </div>
                <div class="info-radek">
                    <span class="info-label">Protihráč:</span>
                    <span class="info-hodnota" id="protihrKartyPocet">0</span>
                </div>
            </div>

            <!-- Akční tlačítka -->
            <div class="akce-panel">
                <button class="akce-btn" id="btnNemamCo">Nemám co hrát</button>
                <button class="akce-btn" id="btnNovaHra">Nová hra</button>
            </div>
        </div>
    </main>

    <!-- Volba barvy modal -->
    <div class="volba-barvy-overlay" id="volbaBarvy">
        <div class="volba-barvy-modal">
            <h3>Vyber novou barvu:</h3>
            <div class="volba-barvy-btns">
                <button class="volba-barvy-btn srdce" data-barva="srdce">♥</button>
                <button class="volba-barvy-btn kary" data-barva="kary">♦</button>
                <button class="volba-barvy-btn piky" data-barva="piky">♠</button>
                <button class="volba-barvy-btn kriz" data-barva="kriz">♣</button>
            </div>
        </div>
    </div>

    <!-- Výsledek overlay -->
    <div class="vysledek-overlay" id="vysledekOverlay">
        <div class="vysledek-text" id="vysledekText">VYHRÁL JSI!</div>
        <button class="vysledek-btn" id="vysledekBtn">Hrát znovu</button>
    </div>

    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

    <script>
    /**
     * PRŠÍ - Herní logika
     */
    (function() {
        'use strict';

        // Konstanty
        const BARVY = ['srdce', 'kary', 'piky', 'kriz'];
        const HODNOTY = ['7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        const SYMBOLY = {
            srdce: '♥',
            kary: '♦',
            piky: '♠',
            kriz: '♣'
        };
        const CERVENE = ['srdce', 'kary'];

        // Herní stav
        let hra = {
            balicek: [],
            odkladaci: [],
            mojeKarty: [],
            protihrKarty: [],
            naTahu: 1, // 1 = já, 2 = protihráč
            aktivniBarva: null, // Změněná barva (svršek)
            kartyKTazeni: 0, // Počet karet k tažení (sedmičky)
            konec: false
        };

        // DOM elementy
        const mojeZona = document.getElementById('mojeZona');
        const protihrZona = document.getElementById('protihrZona');
        const odkladaciEl = document.getElementById('odkladaci');
        const balicekEl = document.getElementById('balicek');
        const balicekPocet = document.getElementById('balicekPocet');
        const naTahuEl = document.getElementById('naTahu');
        const mojeKartyPocet = document.getElementById('mojeKartyPocet');
        const protihrKartyPocet = document.getElementById('protihrKartyPocet');
        const aktivniBarvaEl = document.getElementById('aktivniBarva');
        const kTazeniEl = document.getElementById('kTazeni');
        const kTazeniPocet = document.getElementById('kTazeniPocet');
        const volbaBarvy = document.getElementById('volbaBarvy');
        const vysledekOverlay = document.getElementById('vysledekOverlay');
        const vysledekText = document.getElementById('vysledekText');

        // Vytvořit balíček
        function vytvorBalicek() {
            const balicek = [];
            for (const barva of BARVY) {
                for (const hodnota of HODNOTY) {
                    balicek.push({ barva, hodnota });
                }
            }
            return zamichat(balicek);
        }

        // Zamíchat pole
        function zamichat(pole) {
            const kopie = [...pole];
            for (let i = kopie.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [kopie[i], kopie[j]] = [kopie[j], kopie[i]];
            }
            return kopie;
        }

        // Táhnout kartu z balíčku
        function tahniKartu() {
            if (hra.balicek.length === 0) {
                // Přesypat odkládací (kromě vrchní) do balíčku
                if (hra.odkladaci.length > 1) {
                    const vrchni = hra.odkladaci.pop();
                    hra.balicek = zamichat(hra.odkladaci);
                    hra.odkladaci = [vrchni];
                } else {
                    return null; // Není co táhnout
                }
            }
            return hra.balicek.pop();
        }

        // Vykreslit kartu
        function vykresliKartu(karta, jeKlikatelna = true) {
            const div = document.createElement('div');
            div.className = 'karta ' + (CERVENE.includes(karta.barva) ? 'cervena' : 'cerna');
            div.dataset.barva = karta.barva;
            div.dataset.hodnota = karta.hodnota;

            const symbol = SYMBOLY[karta.barva];

            div.innerHTML = `
                <div class="karta-roh">${karta.hodnota}<br>${symbol}</div>
                <div class="karta-stred">${symbol}</div>
                <div class="karta-roh karta-roh-dole">${karta.hodnota}<br>${symbol}</div>
            `;

            if (jeKlikatelna) {
                div.addEventListener('click', () => zahrajKartu(karta, div));
            }

            return div;
        }

        // Můžu zahrát kartu?
        function muzuZahrat(karta) {
            if (hra.konec) return false;
            if (hra.naTahu !== 1) return false;

            const vrchni = hra.odkladaci[hra.odkladaci.length - 1];
            if (!vrchni) return true;

            // Pokud jsou karty k tažení
            if (hra.kartyKTazeni > 0) {
                // Můžu přihodit sedmičku
                if (karta.hodnota === '7') return true;
                // Nebo krále pikového
                if (karta.hodnota === 'K' && karta.barva === 'piky') return true;
                return false;
            }

            // Svršek (Q) může vždy
            if (karta.hodnota === 'Q') return true;

            // Pokud je změněná barva
            if (hra.aktivniBarva) {
                return karta.barva === hra.aktivniBarva || karta.hodnota === 'Q';
            }

            // Stejná barva nebo hodnota
            return karta.barva === vrchni.barva || karta.hodnota === vrchni.hodnota;
        }

        // Zahrát kartu
        function zahrajKartu(karta, element) {
            if (!muzuZahrat(karta)) {
                element.classList.add('nelze');
                setTimeout(() => element.classList.remove('nelze'), 300);
                return;
            }

            // Odebrat z ruky
            hra.mojeKarty = hra.mojeKarty.filter(k =>
                !(k.barva === karta.barva && k.hodnota === karta.hodnota)
            );

            // Položit na odkládací
            hra.odkladaci.push(karta);
            hra.aktivniBarva = null;

            // Efekty speciálních karet
            if (karta.hodnota === '7') {
                hra.kartyKTazeni += 2;
            } else if (karta.hodnota === 'K' && karta.barva === 'piky') {
                hra.kartyKTazeni += 4;
            } else {
                hra.kartyKTazeni = 0;
            }

            // Svršek - volba barvy
            if (karta.hodnota === 'Q') {
                volbaBarvy.classList.add('active');
                // Po volbě barvy pokračuje tahProtihr()
                return;
            }

            // Zkontrolovat výhru
            if (hra.mojeKarty.length === 0) {
                konecHry(true);
                return;
            }

            // Předat tah (pokud to není eso)
            if (karta.hodnota !== 'A') {
                hra.naTahu = 2;
                setTimeout(tahProtihr, 1000);
            } else {
                // Eso - protihráč stojí, hraju znovu
                zobrazZpravu('Protihráč stojí!');
            }

            vykresliVse();
        }

        // Táhnout z balíčku (když nemám co hrát)
        function tahniZBalicku() {
            if (hra.naTahu !== 1 || hra.konec) return;

            const pocet = hra.kartyKTazeni > 0 ? hra.kartyKTazeni : 1;
            hra.kartyKTazeni = 0;

            for (let i = 0; i < pocet; i++) {
                const karta = tahniKartu();
                if (karta) {
                    hra.mojeKarty.push(karta);
                }
            }

            // Předat tah
            hra.naTahu = 2;
            setTimeout(tahProtihr, 1000);
            vykresliVse();
        }

        // Tah protihráče (AI)
        function tahProtihr() {
            if (hra.konec) return;

            const vrchni = hra.odkladaci[hra.odkladaci.length - 1];

            // Najít hratelnou kartu
            let hratelna = null;

            // Priorita: pokud musí brát, zkus sedmičku nebo krále pikového
            if (hra.kartyKTazeni > 0) {
                hratelna = hra.protihrKarty.find(k =>
                    k.hodnota === '7' || (k.hodnota === 'K' && k.barva === 'piky')
                );
            }

            // Jinak hledej normální kartu
            if (!hratelna) {
                for (const karta of hra.protihrKarty) {
                    if (karta.hodnota === 'Q') {
                        hratelna = karta;
                        break;
                    }

                    if (hra.aktivniBarva) {
                        if (karta.barva === hra.aktivniBarva) {
                            hratelna = karta;
                            break;
                        }
                    } else {
                        if (karta.barva === vrchni.barva || karta.hodnota === vrchni.hodnota) {
                            hratelna = karta;
                            break;
                        }
                    }
                }
            }

            if (hratelna && hra.kartyKTazeni === 0) {
                // Zahrát kartu
                hra.protihrKarty = hra.protihrKarty.filter(k =>
                    !(k.barva === hratelna.barva && k.hodnota === hratelna.hodnota)
                );
                hra.odkladaci.push(hratelna);
                hra.aktivniBarva = null;

                // Efekty
                if (hratelna.hodnota === '7') {
                    hra.kartyKTazeni += 2;
                } else if (hratelna.hodnota === 'K' && hratelna.barva === 'piky') {
                    hra.kartyKTazeni += 4;
                } else {
                    hra.kartyKTazeni = 0;
                }

                // Svršek - AI vybere nejčastější barvu
                if (hratelna.hodnota === 'Q') {
                    const poctyBarev = {};
                    for (const k of hra.protihrKarty) {
                        poctyBarev[k.barva] = (poctyBarev[k.barva] || 0) + 1;
                    }
                    let nejcastejsi = BARVY[Math.floor(Math.random() * 4)];
                    let max = 0;
                    for (const [barva, pocet] of Object.entries(poctyBarev)) {
                        if (pocet > max) {
                            max = pocet;
                            nejcastejsi = barva;
                        }
                    }
                    hra.aktivniBarva = nejcastejsi;
                    zobrazZpravu('Protihráč změnil barvu na ' + SYMBOLY[nejcastejsi]);
                }

                // Zkontrolovat výhru protihráče
                if (hra.protihrKarty.length === 0) {
                    konecHry(false);
                    vykresliVse();
                    return;
                }

                // Eso - hraju znovu
                if (hratelna.hodnota === 'A') {
                    zobrazZpravu('Stojíš!');
                    setTimeout(tahProtihr, 1500);
                    vykresliVse();
                    return;
                }
            } else {
                // Musí táhnout
                const pocet = hra.kartyKTazeni > 0 ? hra.kartyKTazeni : 1;
                hra.kartyKTazeni = 0;

                for (let i = 0; i < pocet; i++) {
                    const karta = tahniKartu();
                    if (karta) {
                        hra.protihrKarty.push(karta);
                    }
                }

                if (pocet > 1) {
                    zobrazZpravu('Protihráč bere ' + pocet + ' karet!');
                }
            }

            hra.naTahu = 1;
            vykresliVse();
        }

        // Volba barvy (svršek)
        document.querySelectorAll('.volba-barvy-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                hra.aktivniBarva = btn.dataset.barva;
                volbaBarvy.classList.remove('active');

                zobrazZpravu('Změna barvy na ' + SYMBOLY[hra.aktivniBarva]);

                // Zkontrolovat výhru
                if (hra.mojeKarty.length === 0) {
                    konecHry(true);
                    return;
                }

                hra.naTahu = 2;
                setTimeout(tahProtihr, 1000);
                vykresliVse();
            });
        });

        // Zobrazit zprávu
        function zobrazZpravu(text) {
            const div = document.createElement('div');
            div.className = 'zprava';
            div.textContent = text;
            document.body.appendChild(div);
            setTimeout(() => div.remove(), 2000);
        }

        // Konec hry
        function konecHry(vyhral) {
            hra.konec = true;
            vysledekText.textContent = vyhral ? 'VYHRÁL JSI!' : 'PROHRÁL JSI';
            vysledekText.className = 'vysledek-text ' + (vyhral ? 'vyhra' : 'prohra');
            vysledekOverlay.classList.add('active');
        }

        // Vykreslit vše
        function vykresliVse() {
            // Moje karty
            mojeZona.innerHTML = '';
            for (const karta of hra.mojeKarty) {
                const el = vykresliKartu(karta);
                if (!muzuZahrat(karta)) {
                    el.classList.add('nelze');
                }
                mojeZona.appendChild(el);
            }

            // Protihráčovy karty (rubem)
            protihrZona.innerHTML = '';
            for (let i = 0; i < hra.protihrKarty.length; i++) {
                const div = document.createElement('div');
                div.className = 'karta-rub';
                div.style.marginLeft = i > 0 ? '-30px' : '0';
                protihrZona.appendChild(div);
            }

            // Odkládací balíček
            const vrchni = hra.odkladaci[hra.odkladaci.length - 1];
            const existujiciKarta = odkladaciEl.querySelector('.karta');
            if (existujiciKarta) existujiciKarta.remove();

            if (vrchni) {
                const kartaEl = vykresliKartu(vrchni, false);
                odkladaciEl.insertBefore(kartaEl, odkladaciEl.firstChild);
            }

            // Aktivní barva
            if (hra.aktivniBarva) {
                aktivniBarvaEl.textContent = SYMBOLY[hra.aktivniBarva];
                aktivniBarvaEl.style.display = 'block';
                aktivniBarvaEl.style.color = CERVENE.includes(hra.aktivniBarva) ? 'var(--prsi-red)' : 'var(--prsi-black)';
            } else {
                aktivniBarvaEl.style.display = 'none';
            }

            // K tažení
            if (hra.kartyKTazeni > 0) {
                kTazeniEl.style.display = 'block';
                kTazeniPocet.textContent = hra.kartyKTazeni;
            } else {
                kTazeniEl.style.display = 'none';
            }

            // Info
            balicekPocet.textContent = hra.balicek.length;
            mojeKartyPocet.textContent = hra.mojeKarty.length;
            protihrKartyPocet.textContent = hra.protihrKarty.length;

            naTahuEl.textContent = hra.naTahu === 1 ? 'TY' : 'Protihráč';
            naTahuEl.className = 'info-hodnota' + (hra.naTahu === 1 ? ' na-tahu' : '');
        }

        // Nová hra
        function novaHra() {
            hra = {
                balicek: vytvorBalicek(),
                odkladaci: [],
                mojeKarty: [],
                protihrKarty: [],
                naTahu: 1,
                aktivniBarva: null,
                kartyKTazeni: 0,
                konec: false
            };

            // Rozdat karty
            for (let i = 0; i < 4; i++) {
                hra.mojeKarty.push(tahniKartu());
                hra.protihrKarty.push(tahniKartu());
            }

            // První karta na odkládací (nesmí být speciální)
            let prvni;
            do {
                prvni = tahniKartu();
            } while (['7', 'Q', 'A'].includes(prvni.hodnota) || (prvni.hodnota === 'K' && prvni.barva === 'piky'));
            hra.odkladaci.push(prvni);

            vysledekOverlay.classList.remove('active');
            vykresliVse();
        }

        // Event listenery
        balicekEl.addEventListener('click', tahniZBalicku);
        document.getElementById('btnNemamCo').addEventListener('click', tahniZBalicku);
        document.getElementById('btnNovaHra').addEventListener('click', novaHra);
        document.getElementById('vysledekBtn').addEventListener('click', novaHra);

        // Start
        novaHra();

    })();
    </script>
</body>
</html>
