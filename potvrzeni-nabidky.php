<?php
/**
 * Potvrzení cenové nabídky - stránka pro zákazníka
 * Zákazník zde potvrdí nabídku a tím uzavře smlouvu
 */
require_once __DIR__ . '/init.php';

$token = $_GET['token'] ?? '';
$nabidka = null;
$chyba = null;
$potvrzeno = false;

if (empty($token)) {
    $chyba = 'Chybí token nabídky';
} else {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE token = ?");
        $stmt->execute([$token]);
        $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nabidka) {
            $chyba = 'Nabídka nebyla nalezena';
        } elseif ($nabidka['stav'] === 'potvrzena') {
            $potvrzeno = true;
        } elseif (strtotime($nabidka['platnost_do']) < time()) {
            $chyba = 'Platnost této nabídky již vypršela';
        }

        if ($nabidka) {
            $nabidka['polozky'] = json_decode($nabidka['polozky_json'], true);
        }
    } catch (Exception $e) {
        error_log("Chyba při načítání nabídky: " . $e->getMessage());
        $chyba = 'Chyba při načítání nabídky';
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cenová nabídka - White Glove Service</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            background: #1a1a1a;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0.1em;
        }
        .header p {
            color: #888;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .container {
            max-width: 700px;
            margin: 30px auto;
            padding: 0 20px;
            flex: 1;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        .card h2 {
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #888; font-size: 0.9rem; }
        .info-value { font-weight: 500; font-size: 0.9rem; }

        /* Tabulka položek */
        .polozky-tabulka {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .polozky-tabulka th,
        .polozky-tabulka td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .polozky-tabulka th {
            background: #f9f9f9;
            font-weight: 500;
            color: #666;
        }
        .polozky-tabulka td:last-child,
        .polozky-tabulka th:last-child {
            text-align: right;
        }
        .celkova-cena-row {
            background: #f9f9f9;
            font-weight: 600;
        }
        .celkova-cena-row td {
            font-size: 1.1rem;
            color: #333;
        }

        /* Platnost */
        .platnost-info {
            background: #fffde7;
            border: 1px solid #ffd600;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .platnost-info strong { color: #f57c00; }

        /* Tlačítko */
        .potvrdit-section {
            text-align: center;
            margin-top: 30px;
        }
        .btn-potvrdit {
            display: inline-block;
            background: #28a745;
            color: #fff;
            padding: 15px 50px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            font-family: inherit;
        }
        .btn-potvrdit:hover { background: #218838; }
        .btn-potvrdit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Právní text */
        .pravni-text {
            font-size: 0.75rem;
            color: #888;
            margin-top: 15px;
            line-height: 1.6;
        }
        .pravni-text a { color: #666; }

        /* Chyba */
        .chyba-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 50px 30px;
            text-align: center;
        }
        .chyba-card h2 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .chyba-card p { color: #666; }

        /* Úspěch */
        .uspech-card {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 12px;
            padding: 50px 30px;
            text-align: center;
        }
        .uspech-card h2 {
            color: #155724;
            margin-bottom: 15px;
        }
        .uspech-card p { color: #155724; }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            padding: 30px;
            text-align: center;
        }
        .modal h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #333;
        }
        .modal p {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .modal-btns {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .modal-btn {
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }
        .modal-btn-zrusit {
            background: #f5f5f5;
            border: 1px solid #ddd;
            color: #666;
        }
        .modal-btn-potvrdit {
            background: #28a745;
            border: none;
            color: #fff;
        }
        .modal-btn-potvrdit:hover { background: #218838; }

        /* Footer */
        .footer {
            background: #1a1a1a;
            color: #888;
            padding: 20px;
            text-align: center;
            font-size: 0.8rem;
        }
        .footer a { color: #aaa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>WHITE GLOVE SERVICE</h1>
        <p>Cenová nabídka</p>
    </div>

    <div class="container">
        <?php if ($chyba): ?>
            <div class="chyba-card">
                <h2>Nabídka není dostupná</h2>
                <p><?php echo htmlspecialchars($chyba); ?></p>
            </div>
        <?php elseif ($potvrzeno): ?>
            <div class="uspech-card" style="margin-bottom: 20px;">
                <h2>Objednávka byla potvrzena</h2>
                <p>Děkujeme za Vaši objednávku. Budeme Vás brzy kontaktovat ohledně realizace.</p>
            </div>

            <!-- Shrnutí potvrzené objednávky -->
            <div class="card" id="potvrzeni-obsah">
                <h2>Potvrzení objednávky č. <?php echo str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT); ?></h2>

                <div class="info-row">
                    <span class="info-label">Zákazník:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nabidka['zakaznik_jmeno']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nabidka['zakaznik_email']); ?></span>
                </div>

                <table class="polozky-tabulka" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>Služba</th>
                            <th>Počet</th>
                            <th>Cena</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nabidka['polozky'] as $polozka): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($polozka['nazev']); ?></td>
                            <td><?php echo intval($polozka['pocet']); ?>x</td>
                            <td><?php echo number_format(floatval($polozka['cena']) * intval($polozka['pocet']), 2, ',', ' '); ?> <?php echo $nabidka['mena']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="celkova-cena-row">
                            <td colspan="2">Celkem (bez DPH):</td>
                            <td><?php echo number_format(floatval($nabidka['celkova_cena']), 2, ',', ' '); ?> <?php echo $nabidka['mena']; ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Technické údaje potvrzení -->
                <div style="margin-top: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h3 style="font-size: 0.9rem; color: #666; margin-bottom: 10px;">Technické údaje potvrzení</h3>
                    <div class="info-row">
                        <span class="info-label">Datum a čas:</span>
                        <span class="info-value"><?php echo date('d.m.Y H:i:s', strtotime($nabidka['potvrzeno_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IP adresa:</span>
                        <span class="info-value"><?php echo htmlspecialchars($nabidka['potvrzeno_ip'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email potvrzení:</span>
                        <span class="info-value"><?php echo htmlspecialchars($nabidka['zakaznik_email']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Tlačítka pro stažení PDF a zavření -->
            <div style="text-align: center; margin-top: 20px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <button type="button" id="btn-stahnout-pdf" style="background: #333; color: #fff; border: none; padding: 15px 40px; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; font-family: inherit;">
                    Stáhnout PDF potvrzení
                </button>
                <a href="https://www.wgs-service.cz/" style="display: inline-block; background: #f5f5f5; color: #333; border: 1px solid #ddd; padding: 15px 40px; border-radius: 8px; font-size: 1rem; font-weight: 500; text-decoration: none; font-family: inherit; cursor: pointer;">
                    Zavřít
                </a>
            </div>
        <?php else: ?>
            <div class="card">
                <h2>Údaje zákazníka</h2>
                <div class="info-row">
                    <span class="info-label">Jméno:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nabidka['zakaznik_jmeno']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nabidka['zakaznik_email']); ?></span>
                </div>
                <?php if (!empty($nabidka['zakaznik_telefon'])): ?>
                <div class="info-row">
                    <span class="info-label">Telefon:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nabidka['zakaznik_telefon']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Položky nabídky</h2>
                <table class="polozky-tabulka">
                    <thead>
                        <tr>
                            <th>Služba</th>
                            <th>Počet</th>
                            <th>Cena</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nabidka['polozky'] as $polozka): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($polozka['nazev']); ?></td>
                            <td><?php echo intval($polozka['pocet']); ?>x</td>
                            <td><?php echo number_format(floatval($polozka['cena']) * intval($polozka['pocet']), 2, ',', ' '); ?> <?php echo $nabidka['mena']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="celkova-cena-row">
                            <td colspan="2">Celkem (bez DPH):</td>
                            <td><?php echo number_format(floatval($nabidka['celkova_cena']), 2, ',', ' '); ?> <?php echo $nabidka['mena']; ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="platnost-info">
                    <strong>Platnost nabídky:</strong> do <?php echo date('d.m.Y', strtotime($nabidka['platnost_do'])); ?>
                </div>
            </div>

            <div class="potvrdit-section">
                <button type="button" class="btn-potvrdit" id="btn-potvrdit">
                    POTVRDIT NABÍDKU
                </button>
                <p class="pravni-text">
                    Kliknutím na tlačítko "POTVRDIT NABÍDKU" potvrzujete, že souhlasíte s touto cenovou nabídkou
                    a uzavíráte tím smlouvu o dílo s White Glove Service, s.r.o. dle
                    <a href="/podminky.php" target="_blank">obchodních podmínek</a>.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Potvrzovací modal -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal">
            <h3>Potvrzení objednávky</h3>
            <p>
                Kliknutím na tlačítko "Souhlasím a objednávám" potvrzujete cenovou nabídku
                a <strong>uzavíráte závaznou smlouvu</strong> o dílo s White Glove Service, s.r.o.
                <br><br>
                Toto potvrzení má právní účinky dle § 1820 a násl. občanského zákoníku.
            </p>
            <div class="modal-btns">
                <button type="button" class="modal-btn modal-btn-zrusit" id="btn-zrusit">Zrušit</button>
                <button type="button" class="modal-btn modal-btn-potvrdit" id="btn-souhlasim">Souhlasím a objednávám</button>
            </div>
        </div>
    </div>

    <div class="footer">
        <p><strong>White Glove Service, s.r.o.</strong></p>
        <p>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
        <p>Tel: +420 725 965 826 | Email: reklamace@wgs-service.cz</p>
        <p style="margin-top: 10px;">
            <a href="/gdpr.php">GDPR</a> |
            <a href="/cookies.php">Cookies</a> |
            <a href="/podminky.php">Obchodní podmínky</a>
        </p>
    </div>

    <!-- PDF knihovny -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
    (function() {
        'use strict';

        const btnPotvrdit = document.getElementById('btn-potvrdit');
        const modalOverlay = document.getElementById('modal-overlay');
        const btnZrusit = document.getElementById('btn-zrusit');
        const btnSouhlasim = document.getElementById('btn-souhlasim');
        const btnStahnoutPdf = document.getElementById('btn-stahnout-pdf');

        // MODAL PRO POTVRZENÍ
        if (btnPotvrdit) {
            btnPotvrdit.addEventListener('click', () => {
                modalOverlay.classList.add('active');
            });

            btnZrusit.addEventListener('click', () => {
                modalOverlay.classList.remove('active');
            });

            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    modalOverlay.classList.remove('active');
                }
            });

            btnSouhlasim.addEventListener('click', async () => {
                btnSouhlasim.disabled = true;
                btnSouhlasim.textContent = 'Odesílám...';

                try {
                    const token = '<?php echo htmlspecialchars($token); ?>';
                    const formData = new FormData();
                    formData.append('action', 'potvrdit');
                    formData.append('token', token);

                    const response = await fetch('/api/nabidka_api.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        window.location.reload();
                    } else {
                        alert('Chyba: ' + data.message);
                        btnSouhlasim.disabled = false;
                        btnSouhlasim.textContent = 'Souhlasím a objednávám';
                    }
                } catch (e) {
                    console.error('Chyba:', e);
                    alert('Chyba při potvrzování nabídky');
                    btnSouhlasim.disabled = false;
                    btnSouhlasim.textContent = 'Souhlasím a objednávám';
                }
            });
        }

        // STAŽENÍ PDF POTVRZENÍ
        if (btnStahnoutPdf) {
            btnStahnoutPdf.addEventListener('click', async () => {
                btnStahnoutPdf.disabled = true;
                btnStahnoutPdf.textContent = 'Generuji PDF...';

                try {
                    await generujPdfPotvrzeni();
                } catch (e) {
                    console.error('Chyba při generování PDF:', e);
                    alert('Chyba při generování PDF');
                }

                btnStahnoutPdf.disabled = false;
                btnStahnoutPdf.textContent = 'Stáhnout PDF potvrzení';
            });
        }

        async function generujPdfPotvrzeni() {
            // Získat data z PHP
            const nabidkaData = <?php echo json_encode([
                'id' => $nabidka['id'] ?? 0,
                'zakaznik_jmeno' => $nabidka['zakaznik_jmeno'] ?? '',
                'zakaznik_email' => $nabidka['zakaznik_email'] ?? '',
                'zakaznik_telefon' => $nabidka['zakaznik_telefon'] ?? '',
                'polozky' => $nabidka['polozky'] ?? [],
                'celkova_cena' => $nabidka['celkova_cena'] ?? 0,
                'mena' => $nabidka['mena'] ?? 'EUR',
                'potvrzeno_at' => $nabidka['potvrzeno_at'] ?? '',
                'potvrzeno_ip' => $nabidka['potvrzeno_ip'] ?? ''
            ], JSON_UNESCAPED_UNICODE); ?>;

            const nabidkaCislo = String(nabidkaData.id).padStart(6, '0');
            const potvrzenoAt = nabidkaData.potvrzeno_at ? new Date(nabidkaData.potvrzeno_at).toLocaleString('cs-CZ') : '';

            // Sestavit HTML pro položky
            let polozkyHtml = '';
            nabidkaData.polozky.forEach(p => {
                const celkem = (parseFloat(p.cena) * parseInt(p.pocet)).toFixed(2);
                polozkyHtml += `<tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;">${p.nazev}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">${p.pocet}x</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">${celkem} ${nabidkaData.mena}</td>
                </tr>`;
            });

            // Vytvořit HTML pro PDF
            const pdfContent = document.createElement('div');
            pdfContent.style.cssText = 'width: 794px; padding: 40px; background: white; font-family: Arial, sans-serif; position: fixed; left: -9999px; top: 0;';
            pdfContent.innerHTML = `
                <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1a1a1a; padding-bottom: 20px;">
                    <h1 style="font-size: 28px; color: #1a1a1a; margin: 0;">WHITE GLOVE SERVICE</h1>
                    <p style="color: #666; margin: 10px 0 0 0; font-size: 16px; font-weight: bold;">POTVRZENÍ OBJEDNÁVKY</p>
                    <p style="color: #888; margin: 5px 0 0 0; font-size: 14px;">č. ${nabidkaCislo}</p>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                    <div>
                        <p style="margin: 0; font-size: 12px; color: #888; text-transform: uppercase;">Zákazník</p>
                        <p style="margin: 8px 0 0 0; font-size: 16px; font-weight: bold;">${nabidkaData.zakaznik_jmeno}</p>
                        <p style="margin: 4px 0 0 0; font-size: 14px; color: #666;">${nabidkaData.zakaznik_email}</p>
                        ${nabidkaData.zakaznik_telefon ? `<p style="margin: 4px 0 0 0; font-size: 14px; color: #666;">${nabidkaData.zakaznik_telefon}</p>` : ''}
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 0; font-size: 12px; color: #888; text-transform: uppercase;">Potvrzeno</p>
                        <p style="margin: 8px 0 0 0; font-size: 14px; color: #28a745; font-weight: bold;">${potvrzenoAt}</p>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-size: 12px; text-transform: uppercase; color: #666;">Služba</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #ddd; font-size: 12px; text-transform: uppercase; color: #666;">Počet</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd; font-size: 12px; text-transform: uppercase; color: #666;">Cena</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${polozkyHtml}
                    </tbody>
                    <tfoot>
                        <tr style="background: #1a1a1a;">
                            <td colspan="2" style="padding: 15px; text-align: right; font-weight: bold; color: #fff;">Celková cena (bez DPH):</td>
                            <td style="padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: #39ff14;">${parseFloat(nabidkaData.celkova_cena).toFixed(2)} ${nabidkaData.mena}</td>
                        </tr>
                    </tfoot>
                </table>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 30px 0;">
                    <h3 style="margin: 0 0 15px 0; font-size: 14px; color: #666; text-transform: uppercase;">Technické údaje potvrzení</h3>
                    <table style="width: 100%; font-size: 13px; color: #555;">
                        <tr>
                            <td style="padding: 5px 0;"><strong>Datum a čas potvrzení:</strong></td>
                            <td style="padding: 5px 0; text-align: right;">${potvrzenoAt}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0;"><strong>IP adresa:</strong></td>
                            <td style="padding: 5px 0; text-align: right;">${nabidkaData.potvrzeno_ip || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0;"><strong>Email potvrzení:</strong></td>
                            <td style="padding: 5px 0; text-align: right;">${nabidkaData.zakaznik_email}</td>
                        </tr>
                    </table>
                </div>

                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <p style="margin: 0; font-size: 13px; color: #155724;">
                        <strong>Tímto potvrzením byla uzavřena závazná smlouva o dílo</strong> dle § 2586 občanského zákoníku
                        s White Glove Service, s.r.o. Ceny jsou uvedeny bez DPH.
                    </p>
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

                const nazevSouboru = `potvrzeni_objednavky_${nabidkaCislo}.pdf`;
                doc.save(nazevSouboru);
            } catch (error) {
                console.error('Chyba při generování PDF:', error);
                document.body.removeChild(pdfContent);
                throw error;
            }
        }
    })();
    </script>
</body>
</html>
