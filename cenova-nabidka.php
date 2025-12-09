<?php
/**
 * Cenová nabídka - admin stránka
 * Vytvoření a správa cenových nabídek pro zákazníky
 * S integrací kalkulačky služeb
 */
require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// CSRF token
require_once __DIR__ . '/includes/csrf_helper.php';
$csrfToken = generateCSRFToken();

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

    <!-- Styly pro kalkulačku -->
    <link rel="stylesheet" href="assets/css/styles.min.css">
    <link rel="stylesheet" href="assets/css/cenik.min.css">
    <link rel="stylesheet" href="assets/css/cenik-wizard-fix.css">

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
        .form-group textarea,
        .form-group select {
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
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #39ff14;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }

        /* Kalkulace sekce */
        .kalkulace-card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 25px;
        }
        .kalkulace-card h2 {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
            color: #aaa;
        }

        /* Tlačítko kalkulace */
        .btn-kalkulace {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 20px 30px;
            background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
            border: 2px dashed #39ff14;
            border-radius: 12px;
            color: #39ff14;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        .btn-kalkulace:hover {
            background: rgba(57, 255, 20, 0.1);
            box-shadow: 0 0 20px rgba(57, 255, 20, 0.2);
        }

        /* Výsledek kalkulace */
        .kalkulace-vysledek {
            margin-top: 20px;
            padding: 20px;
            background: #111;
            border-radius: 8px;
            display: none;
        }
        .kalkulace-vysledek.zobrazit { display: block; }
        .kalkulace-vysledek h3 {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 15px;
        }
        .kalkulace-polozka {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #222;
            font-size: 0.9rem;
        }
        .kalkulace-polozka:last-child { border-bottom: none; }
        .kalkulace-polozka-nazev { color: #ccc; }
        .kalkulace-polozka-cena { color: #39ff14; font-weight: 500; }

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
            flex-wrap: wrap;
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
        .btn-secondary:hover { border-color: #666; color: #fff; }
        .btn-pdf {
            background: #333;
            border: none;
            color: #fff;
        }
        .btn-pdf:hover { background: #444; }

        /* Modal kalkulačky */
        .kalkulacka-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 10000;
            overflow-y: auto;
        }
        .kalkulacka-modal.zobrazit { display: block; }
        .kalkulacka-modal-obsah {
            max-width: 900px;
            margin: 20px auto;
            background: #111;
            border-radius: 16px;
            padding: 30px;
            position: relative;
        }
        .kalkulacka-modal-zavreni {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            color: #888;
            font-size: 2rem;
            cursor: pointer;
            line-height: 1;
        }
        .kalkulacka-modal-zavreni:hover { color: #fff; }

        /* Přepsání stylů kalkulačky pro modal */
        .kalkulacka-modal .calculator-section {
            background: transparent;
            padding: 0;
        }
        .kalkulacka-modal .section-title {
            color: #fff;
        }
        .kalkulacka-modal .wizard-step {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 25px;
        }

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

        /* Workflow kroky */
        .workflow-container {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .workflow-btn {
            padding: 4px 8px;
            border: 1px solid #444;
            border-radius: 4px;
            background: #222;
            color: #888;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .workflow-btn:hover {
            border-color: #666;
            color: #ccc;
        }
        .workflow-btn.aktivni {
            background: rgba(57, 255, 20, 0.15);
            border-color: #39ff14;
            color: #39ff14;
        }
        .workflow-btn.auto {
            cursor: default;
            opacity: 0.7;
        }
        .workflow-btn.auto.aktivni {
            opacity: 1;
        }

        /* Řádek nabídky */
        .nabidka-radek {
            border-bottom: 1px solid #333;
        }
        .nabidka-radek td {
            padding: 15px !important;
            vertical-align: top;
        }
        .nabidka-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .nabidka-info-jmeno {
            font-weight: 500;
            color: #fff;
        }
        .nabidka-info-email {
            font-size: 0.85rem;
            color: #888;
        }
        .nabidka-info-telefon {
            font-size: 0.8rem;
            color: #666;
        }
        .stav-expirovana { background: rgba(220, 53, 69, 0.2); color: #dc3545; }

        /* Dodatečné položky */
        .dodatecne-polozky {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }
        .dodatecne-polozky h3 {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 15px;
        }
        .dodatecna-polozka {
            display: grid;
            grid-template-columns: 1fr 100px 80px 40px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .dodatecna-polozka input {
            padding: 8px 12px;
            background: #111;
            border: 1px solid #333;
            border-radius: 6px;
            color: #fff;
            font-size: 0.85rem;
        }
        .dodatecna-polozka input:focus {
            outline: none;
            border-color: #39ff14;
        }
        .btn-pridat-polozku {
            background: #222;
            border: 1px dashed #444;
            color: #888;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            width: 100%;
            margin-top: 10px;
        }
        .btn-pridat-polozku:hover {
            border-color: #39ff14;
            color: #39ff14;
        }
        .btn-odebrat {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/hamburger-menu.php'; ?>
    <input type="hidden" id="csrf_token" value="<?php echo $csrfToken; ?>">

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
                        <label for="mena">Měna</label>
                        <select id="mena">
                            <option value="EUR" selected>EUR</option>
                            <option value="CZK">CZK</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="poznamka">Poznámka (interní)</label>
                        <textarea id="poznamka" placeholder="Interní poznámka k nabídce..."></textarea>
                    </div>
                </div>

                <!-- Pravá strana - kalkulace -->
                <div class="kalkulace-card">
                    <h2>Kalkulace služeb</h2>

                    <button type="button" class="btn-kalkulace" id="btn-otevrit-kalkulacku">
                        Spustit kalkulaci služeb
                    </button>

                    <div class="kalkulace-vysledek" id="kalkulace-vysledek">
                        <h3>Položky z kalkulace</h3>
                        <div id="kalkulace-polozky">
                            <!-- Dynamicky generováno -->
                        </div>
                    </div>

                    <!-- Náhradní díly -->
                    <div class="dodatecne-polozky" style="border-top: 1px solid #333;">
                        <h3>Náhradní díly</h3>
                        <p style="font-size: 0.8rem; color: #666; margin-bottom: 15px;">Ceny originálních dílů z továrny Natuzzi</p>
                        <div id="nahradni-dily-seznam">
                            <!-- Dynamicky generováno -->
                        </div>
                        <button type="button" class="btn-pridat-polozku" id="btn-pridat-dil">
                            + Přidat náhradní díl
                        </button>
                    </div>

                    <!-- Dodatečné položky -->
                    <div class="dodatecne-polozky">
                        <h3>Další položky (ruční přidání)</h3>
                        <div id="dodatecne-polozky-seznam">
                            <!-- Dynamicky generováno -->
                        </div>
                        <button type="button" class="btn-pridat-polozku" id="btn-pridat-polozku">
                            + Přidat položku
                        </button>
                    </div>

                    <div class="celkova-cena">
                        <span class="celkova-cena-label">Celkem (bez DPH):</span>
                        <span class="celkova-cena-hodnota" id="celkova-cena">0,00 EUR</span>
                    </div>
                </div>
            </div>

            <div class="nabidka-actions">
                <button type="button" class="btn btn-pdf" id="btn-nahled-pdf">Náhled PDF</button>
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
                            <th style="width: 50px;">#</th>
                            <th>Zákazník</th>
                            <th style="width: 100px;">Cena</th>
                            <th>Workflow</th>
                            <th style="width: 100px;">Platnost</th>
                        </tr>
                    </thead>
                    <tbody id="nabidky-tbody">
                        <tr><td colspan="5" style="text-align: center; color: #666;">Načítám...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Modal s kalkulačkou -->
    <div class="kalkulacka-modal" id="kalkulacka-modal">
        <div class="kalkulacka-modal-obsah">
            <button type="button" class="kalkulacka-modal-zavreni" id="btn-zavrit-kalkulacku">&times;</button>

            <!-- KALKULAČKA CENY - kopie z cenik.php -->
            <div class="calculator-section" id="kalkulacka">
                <h2 class="section-title">Kalkulace ceny služby</h2>
                <p class="section-text">
                    Odpovězte na několik jednoduchých otázek a zjistěte orientační cenu servisu.
                </p>

                <!-- Progress Indicator -->
                <div class="wizard-progress" id="wizard-progress">
                    <div class="progress-step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-label">Adresa</span>
                    </div>
                    <div class="progress-step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-label">Typ servisu</span>
                    </div>
                    <div class="progress-step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-label">Detaily</span>
                    </div>
                    <div class="progress-step" data-step="4">
                        <span class="step-number">4</span>
                        <span class="step-label">Souhrn</span>
                    </div>
                </div>

                <!-- KROK 1: Zadání adresy -->
                <div class="wizard-step" id="step-address">
                    <h3 class="step-title">1. Zadejte adresu zákazníka</h3>
                    <p class="step-desc">Pro výpočet dopravného potřebujeme znát adresu.</p>

                    <div class="form-group">
                        <label for="calc-address">Adresa:</label>
                        <input
                            type="text"
                            id="calc-address"
                            class="calc-input"
                            placeholder="Začněte psát adresu (ulice, město)..."
                            autocomplete="off"
                        >
                        <div id="address-suggestions" class="suggestions-dropdown hidden"></div>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label class="checkbox-container">
                            <input type="checkbox" id="reklamace-bez-dopravy">
                            <span class="checkbox-label">Jedná se o reklamaci – neúčtuje se dopravné</span>
                        </label>
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <label class="checkbox-container">
                            <input type="checkbox" id="vyzvednuti-sklad">
                            <span class="checkbox-label">Vyzvednutí dílu pro reklamaci na skladě + 10 EUR</span>
                        </label>
                    </div>

                    <div id="distance-result" class="calc-result" style="display: none;">
                        <div class="result-box">
                            <p><strong>Vzdálenost z dílny:</strong> <span id="distance-value">-</span> km</p>
                            <p><strong>Dopravné (tam a zpět):</strong> <span id="transport-cost" class="highlight-price">-</span> EUR</p>
                        </div>
                    </div>

                    <div class="wizard-buttons">
                        <button class="btn-primary" data-action="nextStep">Pokračovat</button>
                    </div>
                </div>

                <!-- KROK 2: Typ servisu -->
                <div class="wizard-step hidden" id="step-service-type">
                    <h3 class="step-title">2. Jaký typ servisu potřebujete?</h3>
                    <p class="step-desc">Vyberte, co u zákazníka potřebujeme udělat.</p>

                    <div class="radio-group">
                        <label class="radio-card">
                            <input type="radio" name="service-type" value="diagnostika">
                            <div class="radio-content">
                                <div class="radio-title">Pouze diagnostika / inspekce</div>
                                <div class="radio-desc">Technik provede pouze zjištění rozsahu poškození a posouzení stavu.</div>
                                <div class="radio-price">110 EUR</div>
                            </div>
                        </label>

                        <label class="radio-card">
                            <input type="radio" name="service-type" value="calouneni" checked>
                            <div class="radio-content">
                                <div class="radio-title">Čalounické práce</div>
                                <div class="radio-desc">Oprava včetně rozčalounění konstrukce (sedáky, opěrky, područky).</div>
                                <div class="radio-price">Od 205 EUR</div>
                            </div>
                        </label>

                        <label class="radio-card">
                            <input type="radio" name="service-type" value="mechanika">
                            <div class="radio-content">
                                <div class="radio-title">Mechanické opravy</div>
                                <div class="radio-desc">Oprava mechanismů (relax, výsuv) bez rozčalounění.</div>
                                <div class="radio-price">Od 165 EUR</div>
                            </div>
                        </label>

                        <label class="radio-card">
                            <input type="radio" name="service-type" value="kombinace">
                            <div class="radio-content">
                                <div class="radio-title">Kombinace čalounění + mechaniky</div>
                                <div class="radio-desc">Komplexní oprava zahrnující čalounění i mechanické části.</div>
                                <div class="radio-price">Dle rozsahu</div>
                            </div>
                        </label>
                    </div>

                    <div class="wizard-buttons">
                        <button class="btn-secondary" data-action="previousStep">Zpět</button>
                        <button class="btn-primary" data-action="nextStep">Pokračovat</button>
                    </div>
                </div>

                <!-- KROK 3A: Čalounické práce -->
                <div class="wizard-step hidden" id="step-upholstery">
                    <h3 class="step-title">3. Kolik dílů potřebuje přečalounit?</h3>
                    <p class="step-desc">Jeden díl = sedák NEBO opěrka NEBO područka NEBO panel. První díl stojí 205 EUR, každý další 70 EUR.</p>

                    <div class="counter-group">
                        <div class="counter-item">
                            <label>Sedáky</label>
                            <div class="counter-controls">
                                <button class="btn-counter" data-action="decrementCounter" data-counter="sedaky">−</button>
                                <input type="number" id="sedaky" value="0" min="0" max="20" readonly>
                                <button class="btn-counter" data-action="incrementCounter" data-counter="sedaky">+</button>
                            </div>
                        </div>

                        <div class="counter-item">
                            <label>Opěrky</label>
                            <div class="counter-controls">
                                <button class="btn-counter" data-action="decrementCounter" data-counter="operky">−</button>
                                <input type="number" id="operky" value="0" min="0" max="20" readonly>
                                <button class="btn-counter" data-action="incrementCounter" data-counter="operky">+</button>
                            </div>
                        </div>

                        <div class="counter-item">
                            <label>Područky</label>
                            <div class="counter-controls">
                                <button class="btn-counter" data-action="decrementCounter" data-counter="podrucky">−</button>
                                <input type="number" id="podrucky" value="0" min="0" max="20" readonly>
                                <button class="btn-counter" data-action="incrementCounter" data-counter="podrucky">+</button>
                            </div>
                        </div>

                        <div class="counter-item">
                            <label>Panely (zadní/boční)</label>
                            <div class="counter-controls">
                                <button class="btn-counter" data-action="decrementCounter" data-counter="panely">−</button>
                                <input type="number" id="panely" value="0" min="0" max="20" readonly>
                                <button class="btn-counter" data-action="incrementCounter" data-counter="panely">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="parts-summary" id="parts-summary">
                        <strong>Celkem dílů:</strong> <span id="total-parts">0</span>
                        <span class="price-breakdown" id="parts-price-breakdown"></span>
                    </div>

                    <div class="wizard-buttons">
                        <button class="btn-secondary" data-action="previousStep">Zpět</button>
                        <button class="btn-primary" data-action="nextStep">Pokračovat</button>
                    </div>
                </div>

                <!-- KROK 3B: Mechanické práce -->
                <div class="wizard-step hidden" id="step-mechanics">
                    <h3 class="step-title">3. Mechanické části</h3>
                    <p class="step-desc">Vyberte, které mechanické části potřebují opravu.</p>

                    <div class="counter-group">
                        <div class="counter-item">
                            <label>Relax mechanismy</label>
                            <div class="counter-controls">
                                <button class="btn-counter" data-action="decrementCounter" data-counter="relax">−</button>
                                <input type="number" id="relax" value="0" min="0" max="10" readonly>
                                <button class="btn-counter" data-action="incrementCounter" data-counter="relax">+</button>
                            </div>
                            <div class="counter-price">45 EUR / kus</div>
                        </div>

                        <div class="counter-item">
                            <label>Elektrické díly</label>
                            <div class="counter-controls">
                                <button class="btn-counter" data-action="decrementCounter" data-counter="vysuv">−</button>
                                <input type="number" id="vysuv" value="0" min="0" max="10" readonly>
                                <button class="btn-counter" data-action="incrementCounter" data-counter="vysuv">+</button>
                            </div>
                            <div class="counter-price">45 EUR / kus</div>
                        </div>
                    </div>

                    <div class="wizard-buttons">
                        <button class="btn-secondary" data-action="previousStep">Zpět</button>
                        <button class="btn-primary" data-action="nextStep">Pokračovat</button>
                    </div>
                </div>

                <!-- KROK 4: Další parametry -->
                <div class="wizard-step hidden" id="step-extras">
                    <h3 class="step-title">4. Další parametry</h3>
                    <p class="step-desc">Poslední detaily pro přesný výpočet ceny.</p>

                    <div class="checkbox-group">
                        <label class="checkbox-card">
                            <input type="checkbox" id="tezky-nabytek">
                            <div class="checkbox-content">
                                <div class="checkbox-title">Nábytek je těžší než 50 kg</div>
                                <div class="checkbox-desc">Bude potřeba druhá osoba pro manipulaci</div>
                                <div class="checkbox-price">+ 95 EUR</div>
                            </div>
                        </label>

                        <label class="checkbox-card">
                            <input type="checkbox" id="material">
                            <div class="checkbox-content">
                                <div class="checkbox-title">Materiál dodán od WGS</div>
                                <div class="checkbox-desc">Výplně (vata, pěna) z naší zásoby</div>
                                <div class="checkbox-price">+ 50 EUR</div>
                            </div>
                        </label>
                    </div>

                    <div class="wizard-buttons">
                        <button class="btn-secondary" data-action="previousStep">Zpět</button>
                        <button class="btn-primary" data-action="nextStep">Zobrazit souhrn</button>
                    </div>
                </div>

                <!-- KROK 5: Cenový souhrn -->
                <div class="wizard-step hidden" id="step-summary">
                    <h3 class="step-title">Orientační cena servisu</h3>

                    <div class="price-summary-box">
                        <div id="summary-details">
                            <!-- Načteno dynamicky -->
                        </div>

                        <div class="summary-line total">
                            <span><strong>CELKOVÁ CENA:</strong></span>
                            <span id="grand-total" class="total-price"><strong>0 EUR</strong></span>
                        </div>

                        <div class="summary-note">
                            <strong>Upozornění:</strong> Ceny jsou uvedeny bez DPH.
                            U náhradních dílů můžeme požadovat zálohu ve výši jejich ceny. Doba dodání originálních dílů z továrny Natuzzi je 4–8 týdnů.
                        </div>
                    </div>

                    <div class="wizard-buttons">
                        <!-- Tlačítka se generují automaticky funkcí upravitTlacitkaProRezim() -->
                        <!-- V režimu 'protokol' se zobrazí: Zpět + Započítat -->
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- JavaScript knihovny -->
    <script src="assets/js/utils.min.js"></script>
    <script src="assets/js/logger.min.js"></script>
    <script src="assets/js/wgs-map.min.js"></script>
    <script src="assets/js/cenik-calculator.min.js"></script>

    <!-- PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
    (function() {
        'use strict';

        // State
        let kalkulaceData = null;
        let dodatecnePolozky = [];
        let nahradniDily = [];

        // Elements
        const csrfToken = document.getElementById('csrf_token').value;
        const modal = document.getElementById('kalkulacka-modal');
        const kalkulaceVysledek = document.getElementById('kalkulace-vysledek');
        const kalkulacePolozkyEl = document.getElementById('kalkulace-polozky');
        const celkovaCenaEl = document.getElementById('celkova-cena');
        const dodatecnePolozkySeznam = document.getElementById('dodatecne-polozky-seznam');
        const nahradniDilySeznam = document.getElementById('nahradni-dily-seznam');

        // ========================================
        // TABS
        // ========================================
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

        // ========================================
        // KALKULAČKA MODAL
        // ========================================
        document.getElementById('btn-otevrit-kalkulacku').addEventListener('click', () => {
            modal.classList.add('zobrazit');
            document.body.style.overflow = 'hidden';

            // Nastavit režim kalkulačky na 'protokol' - zobrazí tlačítko "Započítat"
            if (typeof window.nastavitKalkulackuRezim === 'function') {
                window.nastavitKalkulackuRezim('protokol');
            }

            // Předvyplnit adresu z formuláře
            const adresa = document.getElementById('zakaznik_adresa').value.trim();
            if (adresa && typeof window.predvyplnitAdresu === 'function') {
                window.predvyplnitAdresu(adresa);
            }

            // Inicializovat kalkulačku
            if (typeof window.initKalkulacka === 'function') {
                window.initKalkulacka();
            }
        });

        document.getElementById('btn-zavrit-kalkulacku').addEventListener('click', () => {
            modal.classList.remove('zobrazit');
            document.body.style.overflow = '';
        });

        // Zavřít modal kliknutím mimo obsah
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('zobrazit');
                document.body.style.overflow = '';
            }
        });

        // ========================================
        // CALLBACK PRO KALKULAČKU (režim 'protokol')
        // ========================================
        // Kalkulačka v režimu 'protokol' volá tuto funkci při kliknutí na "Započítat"
        window.protokolKalkulacka = {
            zpracovatVysledek: function(kalkulaceDataZ) {
                // Získat data z kalkulačky
                if (typeof window.stav !== 'undefined') {
                    kalkulaceData = { ...window.stav };

                    // Přenést adresu z kalkulačky do formuláře (pokud není již vyplněna)
                    const adresaPole = document.getElementById('zakaznik_adresa');
                    if (adresaPole && kalkulaceData.adresa && !adresaPole.value.trim()) {
                        adresaPole.value = kalkulaceData.adresa;
                    }

                    // Vypočítat položky a ceny
                    const polozky = sestavitPolozkyZKalkulace(kalkulaceData);
                    zobrazitKalkulaciVysledek(polozky);

                    // Zavřít modal
                    modal.classList.remove('zobrazit');
                    document.body.style.overflow = '';

                    // Aktualizovat celkovou cenu
                    aktualizovatCelkovouCenu();
                }
            }
        };

        // ========================================
        // SESTAVENÍ POLOŽEK Z KALKULACE
        // ========================================
        function sestavitPolozkyZKalkulace(data) {
            const polozky = [];
            const CENY = {
                diagnostika: 110,
                prvniDil: 205,
                dalsiDil: 70,
                zakladniSazba: 165,
                mechanismusPriplatek: 45,
                druhaOsoba: 95,
                material: 50,
                vyzvednutiSklad: 10
            };

            // Dopravné
            if (data.dopravne > 0) {
                polozky.push({
                    nazev: `Dopravné (${data.vzdalenost} km × 2)`,
                    cena: data.dopravne,
                    pocet: 1
                });
            }

            // Diagnostika
            if (data.typServisu === 'diagnostika') {
                polozky.push({
                    nazev: 'Inspekce / diagnostika',
                    cena: CENY.diagnostika,
                    pocet: 1
                });
            }

            // Čalounické práce
            if (data.typServisu === 'calouneni' || data.typServisu === 'kombinace') {
                const celkemDilu = data.sedaky + data.operky + data.podrucky + data.panely;
                if (celkemDilu > 0) {
                    const cenaDilu = celkemDilu === 1 ? CENY.prvniDil : CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;

                    let popis = 'Čalounické práce (';
                    const dily = [];
                    if (data.sedaky > 0) dily.push(`${data.sedaky}× sedák`);
                    if (data.operky > 0) dily.push(`${data.operky}× opěrka`);
                    if (data.podrucky > 0) dily.push(`${data.podrucky}× područka`);
                    if (data.panely > 0) dily.push(`${data.panely}× panel`);
                    popis += dily.join(', ') + ')';

                    polozky.push({
                        nazev: popis,
                        cena: cenaDilu,
                        pocet: 1
                    });
                }
            }

            // Mechanické práce
            if (data.typServisu === 'mechanika' || data.typServisu === 'kombinace') {
                if (data.typServisu === 'mechanika') {
                    polozky.push({
                        nazev: 'Základní servisní sazba',
                        cena: CENY.zakladniSazba,
                        pocet: 1
                    });
                }

                if (data.relax > 0) {
                    polozky.push({
                        nazev: 'Relax mechanismus',
                        cena: CENY.mechanismusPriplatek,
                        pocet: data.relax
                    });
                }

                if (data.vysuv > 0) {
                    polozky.push({
                        nazev: 'Elektrický díl',
                        cena: CENY.mechanismusPriplatek,
                        pocet: data.vysuv
                    });
                }
            }

            // Druhá osoba
            if (data.tezkyNabytek) {
                polozky.push({
                    nazev: 'Druhá osoba (těžký nábytek >50kg)',
                    cena: CENY.druhaOsoba,
                    pocet: 1
                });
            }

            // Materiál
            if (data.material) {
                polozky.push({
                    nazev: 'Materiál od WGS',
                    cena: CENY.material,
                    pocet: 1
                });
            }

            // Vyzvednutí na skladě
            if (data.vyzvednutiSklad) {
                polozky.push({
                    nazev: 'Vyzvednutí dílu na skladě',
                    cena: CENY.vyzvednutiSklad,
                    pocet: 1
                });
            }

            return polozky;
        }

        // ========================================
        // ZOBRAZENÍ VÝSLEDKU KALKULACE
        // ========================================
        function zobrazitKalkulaciVysledek(polozky) {
            if (!polozky || polozky.length === 0) {
                kalkulaceVysledek.classList.remove('zobrazit');
                return;
            }

            let html = '';
            polozky.forEach(p => {
                const celkem = (p.cena * p.pocet).toFixed(2);
                html += `<div class="kalkulace-polozka">
                    <span class="kalkulace-polozka-nazev">${p.nazev} ${p.pocet > 1 ? `(${p.pocet}×)` : ''}</span>
                    <span class="kalkulace-polozka-cena">${celkem} EUR</span>
                </div>`;
            });

            kalkulacePolozkyEl.innerHTML = html;
            kalkulaceVysledek.classList.add('zobrazit');

            // Uložit do globální proměnné pro odesílání
            window.kalkulacePolozky = polozky;
        }

        // ========================================
        // DODATEČNÉ POLOŽKY
        // ========================================
        document.getElementById('btn-pridat-polozku').addEventListener('click', () => {
            const novaPol = {
                id: Date.now(),
                nazev: '',
                cena: 0,
                pocet: 1
            };
            dodatecnePolozky.push(novaPol);
            renderDodatecnePolozky();
        });

        function renderDodatecnePolozky() {
            let html = '';
            dodatecnePolozky.forEach((p, idx) => {
                html += `<div class="dodatecna-polozka" data-idx="${idx}">
                    <input type="text" placeholder="Název položky" value="${p.nazev}" data-field="nazev">
                    <input type="number" placeholder="Cena" value="${p.cena}" step="0.01" min="0" data-field="cena">
                    <input type="number" placeholder="Ks" value="${p.pocet}" min="1" data-field="pocet">
                    <button type="button" class="btn-odebrat" data-idx="${idx}">&times;</button>
                </div>`;
            });
            dodatecnePolozkySeznam.innerHTML = html;

            // Event handlery
            dodatecnePolozkySeznam.querySelectorAll('input').forEach(input => {
                input.addEventListener('change', () => {
                    const idx = parseInt(input.parentElement.dataset.idx);
                    const field = input.dataset.field;
                    if (field === 'cena') {
                        dodatecnePolozky[idx][field] = parseFloat(input.value) || 0;
                    } else if (field === 'pocet') {
                        dodatecnePolozky[idx][field] = parseInt(input.value) || 1;
                    } else {
                        dodatecnePolozky[idx][field] = input.value;
                    }
                    aktualizovatCelkovouCenu();
                });
            });

            dodatecnePolozkySeznam.querySelectorAll('.btn-odebrat').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.dataset.idx);
                    dodatecnePolozky.splice(idx, 1);
                    renderDodatecnePolozky();
                    aktualizovatCelkovouCenu();
                });
            });
        }

        // ========================================
        // NÁHRADNÍ DÍLY
        // ========================================
        document.getElementById('btn-pridat-dil').addEventListener('click', () => {
            const novyDil = {
                id: Date.now(),
                nazev: '',
                cena: 0,
                pocet: 1
            };
            nahradniDily.push(novyDil);
            renderNahradniDily();
        });

        function renderNahradniDily() {
            let html = '';
            nahradniDily.forEach((d, idx) => {
                html += `<div class="dodatecna-polozka" data-idx="${idx}">
                    <input type="text" placeholder="Název dílu (např. Mechanismus relax)" value="${d.nazev}" data-field="nazev">
                    <input type="number" placeholder="Cena" value="${d.cena}" step="0.01" min="0" data-field="cena">
                    <input type="number" placeholder="Ks" value="${d.pocet}" min="1" data-field="pocet">
                    <button type="button" class="btn-odebrat" data-idx="${idx}">&times;</button>
                </div>`;
            });
            nahradniDilySeznam.innerHTML = html;

            // Event handlery
            nahradniDilySeznam.querySelectorAll('input').forEach(input => {
                input.addEventListener('change', () => {
                    const idx = parseInt(input.parentElement.dataset.idx);
                    const field = input.dataset.field;
                    if (field === 'cena') {
                        nahradniDily[idx][field] = parseFloat(input.value) || 0;
                    } else if (field === 'pocet') {
                        nahradniDily[idx][field] = parseInt(input.value) || 1;
                    } else {
                        nahradniDily[idx][field] = input.value;
                    }
                    aktualizovatCelkovouCenu();
                });
            });

            nahradniDilySeznam.querySelectorAll('.btn-odebrat').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.dataset.idx);
                    nahradniDily.splice(idx, 1);
                    renderNahradniDily();
                    aktualizovatCelkovouCenu();
                });
            });
        }

        // ========================================
        // CELKOVÁ CENA
        // ========================================
        function aktualizovatCelkovouCenu() {
            let celkem = 0;
            const mena = document.getElementById('mena').value;

            // Z kalkulace
            if (window.kalkulacePolozky) {
                window.kalkulacePolozky.forEach(p => {
                    celkem += p.cena * p.pocet;
                });
            }

            // Z náhradních dílů
            nahradniDily.forEach(d => {
                celkem += d.cena * d.pocet;
            });

            // Z dodatečných položek
            dodatecnePolozky.forEach(p => {
                celkem += p.cena * p.pocet;
            });

            celkovaCenaEl.textContent = celkem.toFixed(2).replace('.', ',') + ' ' + mena;
        }

        document.getElementById('mena').addEventListener('change', aktualizovatCelkovouCenu);

        // ========================================
        // ODESLAT NABÍDKU
        // ========================================
        document.getElementById('btn-odeslat').addEventListener('click', async () => {
            const jmeno = document.getElementById('zakaznik_jmeno').value.trim();
            const email = document.getElementById('zakaznik_email').value.trim();
            const mena = document.getElementById('mena').value;

            if (!jmeno || !email) {
                alert('Vyplňte jméno a email zákazníka');
                return;
            }

            // Sestavit všechny položky
            const vsechnyPolozky = [];

            if (window.kalkulacePolozky) {
                window.kalkulacePolozky.forEach(p => {
                    vsechnyPolozky.push({
                        nazev: p.nazev,
                        cena: p.cena,
                        pocet: p.pocet
                    });
                });
            }

            // Náhradní díly
            nahradniDily.forEach(d => {
                if (d.nazev && d.cena > 0) {
                    vsechnyPolozky.push({
                        nazev: 'Náhradní díl: ' + d.nazev,
                        cena: d.cena,
                        pocet: d.pocet
                    });
                }
            });

            // Dodatečné položky
            dodatecnePolozky.forEach(p => {
                if (p.nazev && p.cena > 0) {
                    vsechnyPolozky.push({
                        nazev: p.nazev,
                        cena: p.cena,
                        pocet: p.pocet
                    });
                }
            });

            if (vsechnyPolozky.length === 0) {
                alert('Nejsou vybrány žádné položky. Použijte kalkulaci nebo přidejte položky ručně.');
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
            formData.append('mena', mena);
            formData.append('polozky', JSON.stringify(vsechnyPolozky));

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
                    resetFormular();
                } else {
                    alert('Nabídka vytvořena, ale odeslání selhalo: ' + odeslatResult.message);
                }

            } catch (e) {
                console.error('Chyba:', e);
                alert('Chyba při vytváření nabídky');
            }
        });

        // ========================================
        // RESET FORMULÁŘE
        // ========================================
        function resetFormular() {
            document.getElementById('zakaznik_jmeno').value = '';
            document.getElementById('zakaznik_email').value = '';
            document.getElementById('zakaznik_telefon').value = '';
            document.getElementById('zakaznik_adresa').value = '';
            document.getElementById('poznamka').value = '';

            kalkulaceData = null;
            window.kalkulacePolozky = null;
            kalkulaceVysledek.classList.remove('zobrazit');
            kalkulacePolozkyEl.innerHTML = '';

            nahradniDily = [];
            renderNahradniDily();

            dodatecnePolozky = [];
            renderDodatecnePolozky();

            aktualizovatCelkovouCenu();
        }

        // ========================================
        // NÁHLED PDF
        // ========================================
        document.getElementById('btn-nahled-pdf').addEventListener('click', async () => {
            const jmeno = document.getElementById('zakaznik_jmeno').value.trim();
            const email = document.getElementById('zakaznik_email').value.trim();
            const mena = document.getElementById('mena').value;

            if (!jmeno) {
                alert('Vyplňte alespoň jméno zákazníka');
                return;
            }

            // Sestavit položky
            const vsechnyPolozky = [];

            if (window.kalkulacePolozky) {
                window.kalkulacePolozky.forEach(p => {
                    vsechnyPolozky.push({
                        nazev: p.nazev,
                        cena: p.cena,
                        pocet: p.pocet
                    });
                });
            }

            // Náhradní díly
            nahradniDily.forEach(d => {
                if (d.nazev && d.cena > 0) {
                    vsechnyPolozky.push({
                        nazev: 'Náhradní díl: ' + d.nazev,
                        cena: d.cena,
                        pocet: d.pocet
                    });
                }
            });

            // Dodatečné položky
            dodatecnePolozky.forEach(p => {
                if (p.nazev && p.cena > 0) {
                    vsechnyPolozky.push({
                        nazev: p.nazev,
                        cena: p.cena,
                        pocet: p.pocet
                    });
                }
            });

            // Vypočítat celkem
            let celkem = 0;
            vsechnyPolozky.forEach(p => {
                celkem += p.cena * p.pocet;
            });

            // Generovat PDF
            await generujPdfNabidky({
                jmeno: jmeno,
                email: email,
                telefon: document.getElementById('zakaznik_telefon').value,
                adresa: document.getElementById('zakaznik_adresa').value,
                polozky: vsechnyPolozky,
                celkem: celkem,
                mena: mena
            });
        });

        async function generujPdfNabidky(data) {
            if (typeof window.jspdf === 'undefined' || typeof html2canvas === 'undefined') {
                alert('Načítám knihovny pro PDF...');
                return;
            }

            const datum = new Date().toLocaleDateString('cs-CZ');
            const platnostDo = new Date(Date.now() + 30*24*60*60*1000).toLocaleDateString('cs-CZ');

            // Vytvořit HTML pro PDF
            let polozkyHtml = '';
            data.polozky.forEach(p => {
                const celkemPol = (p.cena * p.pocet).toFixed(2);
                polozkyHtml += `<tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;">${p.nazev}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">${p.pocet}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">${p.cena.toFixed(2)} ${data.mena}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">${celkemPol} ${data.mena}</td>
                </tr>`;
            });

            const pdfContent = document.createElement('div');
            pdfContent.style.cssText = 'width: 794px; padding: 40px; background: white; font-family: Poppins, sans-serif; position: fixed; left: -9999px; top: 0;';
            pdfContent.innerHTML = `
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="font-size: 28px; color: #1a1a1a; margin: 0;">WHITE GLOVE SERVICE</h1>
                    <p style="color: #666; margin: 5px 0 0 0; font-size: 14px;">Cenová nabídka</p>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                    <div>
                        <h3 style="font-size: 14px; color: #888; margin: 0 0 10px 0;">Zákazník</h3>
                        <p style="margin: 5px 0; font-size: 16px;"><strong>${data.jmeno}</strong></p>
                        ${data.email ? `<p style="margin: 5px 0; font-size: 14px;">${data.email}</p>` : ''}
                        ${data.telefon ? `<p style="margin: 5px 0; font-size: 14px;">${data.telefon}</p>` : ''}
                        ${data.adresa ? `<p style="margin: 5px 0; font-size: 14px;">${data.adresa}</p>` : ''}
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 5px 0; font-size: 14px;"><strong>Datum:</strong> ${datum}</p>
                        <p style="margin: 5px 0; font-size: 14px;"><strong>Platnost do:</strong> ${platnostDo}</p>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Služba</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd;">Počet</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd;">Cena/ks</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd;">Celkem</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${polozkyHtml}
                    </tbody>
                    <tfoot>
                        <tr style="background: #f9f9f9;">
                            <td colspan="3" style="padding: 15px; text-align: right; font-weight: bold; border-top: 2px solid #333;">Celková cena (bez DPH):</td>
                            <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; border-top: 2px solid #333;">${data.celkem.toFixed(2)} ${data.mena}</td>
                        </tr>
                    </tfoot>
                </table>

                <div style="background: #f5f5f5; border-left: 4px solid #666; padding: 15px; margin: 30px 0; font-size: 12px; color: #666;">
                    <strong>Upozornění:</strong> Ceny jsou uvedeny bez DPH. U náhradních dílů můžeme požadovat zálohu ve výši jejich ceny. Doba dodání originálních dílů z továrny Natuzzi je 4–8 týdnů.
                </div>

                <div style="text-align: center; margin-top: 50px; font-size: 11px; color: #999; border-top: 1px solid #eee; padding-top: 20px;">
                    <p style="margin: 5px 0;"><strong>White Glove Service s.r.o.</strong></p>
                    <p style="margin: 5px 0;">Do Dubče 364, Běchovice 190 11 | Tel: +420 725 965 826</p>
                    <p style="margin: 5px 0;">www.wgs-service.cz | reklamace@wgs-service.cz</p>
                </div>
            `;

            document.body.appendChild(pdfContent);
            await new Promise(resolve => setTimeout(resolve, 100));

            try {
                const canvas = await html2canvas(pdfContent, {
                    scale: 2,
                    backgroundColor: '#ffffff',
                    useCORS: true,
                    logging: false
                });

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                const imgData = canvas.toDataURL('image/jpeg', 0.95);

                const pageWidth = 210;
                const pageHeight = 297;
                const margin = 10;
                const availableWidth = pageWidth - (margin * 2);
                const canvasRatio = canvas.height / canvas.width;
                const imgWidth = availableWidth;
                const imgHeight = imgWidth * canvasRatio;

                doc.addImage(imgData, 'JPEG', margin, margin, imgWidth, Math.min(imgHeight, pageHeight - margin * 2));

                document.body.removeChild(pdfContent);

                const nazevSouboru = `nabidka_${data.jmeno.replace(/\s+/g, '_')}_${new Date().getTime()}.pdf`;
                doc.save(nazevSouboru);
            } catch (error) {
                console.error('Chyba při generování PDF:', error);
                document.body.removeChild(pdfContent);
                alert('Chyba při generování PDF');
            }
        }

        // ========================================
        // SEZNAM NABÍDEK
        // ========================================
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
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #666;">Žádné nabídky</td></tr>';
                return;
            }

            let html = '';
            nabidky.forEach(n => {
                const platnost = n.platnost_do ? new Date(n.platnost_do).toLocaleDateString('cs-CZ') : '-';

                // Workflow stavy
                const jePoslana = !!n.odeslano_at;
                const jePotvrzena = !!n.potvrzeno_at;
                const jeZalohaPrijata = !!n.zaloha_prijata_at;
                const jeHotovo = !!n.hotovo_at;
                const jeUhrazeno = !!n.uhrazeno_at;

                html += `<tr class="nabidka-radek">
                    <td style="color: #666;">${n.id}</td>
                    <td>
                        <div class="nabidka-info">
                            <span class="nabidka-info-jmeno">${n.zakaznik_jmeno}</span>
                            <span class="nabidka-info-email">${n.zakaznik_email}</span>
                            ${n.zakaznik_telefon ? `<span class="nabidka-info-telefon">${n.zakaznik_telefon}</span>` : ''}
                        </div>
                    </td>
                    <td style="font-weight: 600; color: #39ff14;">${parseFloat(n.celkova_cena).toFixed(2)} ${n.mena}</td>
                    <td>
                        <div class="workflow-container">
                            <!-- Automatické kroky -->
                            ${n.stav === 'nova' ?
                                `<button class="workflow-btn" onclick="znovuOdeslat(${n.id})">Odeslat CN</button>` :
                                `<span class="workflow-btn auto aktivni" title="Odesláno: ${formatDatum(n.odeslano_at)}">Poslána CN</span>`
                            }
                            <span class="workflow-btn auto ${jePotvrzena ? 'aktivni' : ''}" title="${jePotvrzena ? 'Potvrzeno: ' + formatDatum(n.potvrzeno_at) : 'Čeká na potvrzení zákazníkem'}">
                                Odsouhlasena
                            </span>
                            <!-- Manuální kroky -->
                            <button class="workflow-btn ${jeZalohaPrijata ? 'aktivni' : ''}"
                                    onclick="zmenitWorkflow(${n.id}, 'zaloha_prijata')"
                                    title="${jeZalohaPrijata ? 'Přijato: ' + formatDatum(n.zaloha_prijata_at) : 'Klikněte pro potvrzení'}">
                                Záloha
                            </button>
                            <button class="workflow-btn ${jeHotovo ? 'aktivni' : ''}"
                                    onclick="zmenitWorkflow(${n.id}, 'hotovo')"
                                    title="${jeHotovo ? 'Dokončeno: ' + formatDatum(n.hotovo_at) : 'Klikněte pro potvrzení'}">
                                Hotovo
                            </button>
                            <button class="workflow-btn ${jeUhrazeno ? 'aktivni' : ''}"
                                    onclick="zmenitWorkflow(${n.id}, 'uhrazeno')"
                                    title="${jeUhrazeno ? 'Uhrazeno: ' + formatDatum(n.uhrazeno_at) : 'Klikněte pro potvrzení'}">
                                Uhrazeno
                            </button>
                        </div>
                    </td>
                    <td style="color: #888; font-size: 0.85rem;">${platnost}</td>
                </tr>`;
            });

            tbody.innerHTML = html;
        }

        function formatDatum(datum) {
            if (!datum) return '-';
            return new Date(datum).toLocaleString('cs-CZ', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        window.znovuOdeslat = async function(nabidkaId) {
            const formData = new FormData();
            formData.append('action', 'odeslat');
            formData.append('csrf_token', csrfToken);
            formData.append('nabidka_id', nabidkaId);

            try {
                const response = await fetch('/api/nabidka_api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.status === 'success') {
                    alert('Nabídka odeslána');
                    nacistSeznamNabidek();
                } else {
                    alert('Chyba: ' + data.message);
                }
            } catch (e) {
                alert('Chyba při odesílání');
            }
        };

        // Změna workflow stavu (manuální kroky)
        window.zmenitWorkflow = async function(nabidkaId, krok) {
            const formData = new FormData();
            formData.append('action', 'zmenit_workflow');
            formData.append('csrf_token', csrfToken);
            formData.append('nabidka_id', nabidkaId);
            formData.append('krok', krok);

            try {
                const response = await fetch('/api/nabidka_api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.status === 'success') {
                    // Aktualizovat seznam bez reloadu
                    nacistSeznamNabidek();
                } else {
                    alert('Chyba: ' + data.message);
                }
            } catch (e) {
                alert('Chyba při změně stavu');
            }
        };

    })();
    </script>
</body>
</html>
