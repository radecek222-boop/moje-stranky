<?php
/**
 * NEJJEDNODUŠŠÍ MAPPING - Jako opisování domácího úkolu! 📝
 *
 * VLEVO = PDF (vykreslené)
 * VPRAVO = Formulář
 * TY = Vyplníš formulář podle PDF
 * SYSTÉM = Vygeneruje patterns!
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📝 PDF Mapping - Jako Opisování!</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        h1 {
            text-align: center;
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            font-size: 1.3em;
            margin-bottom: 20px;
        }

        .upload-section {
            background: #f0f4ff;
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }

        .upload-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 50px;
            border-radius: 50px;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.6);
        }

        .work-area {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .work-area.active {
            display: grid;
        }

        .pdf-panel {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            max-height: 85vh;
            overflow-y: auto;
        }

        .form-panel {
            background: #f0f4ff;
            border-radius: 15px;
            padding: 20px;
            max-height: 85vh;
            overflow-y: auto;
        }

        #pdfCanvas {
            border: 2px solid #ddd;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 100%;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            font-size: 1.1em;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .generate-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 50px;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.6);
        }

        .instructions {
            background: #d1ecf1;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #17a2b8;
            font-size: 1.1em;
        }

        .result-box {
            display: none;
            background: #d4edda;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 5px solid #28a745;
        }

        .result-box.active {
            display: block;
        }

        .result-box pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            margin-top: 15px;
            font-size: 0.9em;
        }

        .page-nav {
            text-align: center;
            margin: 15px 0;
        }

        .page-nav button {
            padding: 10px 20px;
            margin: 0 5px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }

        .page-nav button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 PDF Mapping - Jako Opisování!</h1>
        <p class="subtitle">Vlevo vidíš PDF, vpravo vyplníš formulář. Jednoduché! 😊</p>

        <!-- Upload -->
        <div class="upload-section">
            <h2>📄 KROK 1: Nahraj PDF</h2>
            <input type="file" id="pdfInput" accept=".pdf" style="display: none;">
            <button class="upload-btn" onclick="document.getElementById('pdfInput').click()">
                📁 VYBER PDF SOUBOR
            </button>
            <p style="margin-top: 15px; color: #666; font-size: 1.1em;">
                Vyber NATUZZI nebo PHASE protokol
            </p>
        </div>

        <!-- Pracovní plocha -->
        <div class="work-area" id="workArea">
            <!-- VLEVO - PDF -->
            <div class="pdf-panel">
                <h2 style="color: #667eea; margin-bottom: 15px;">📄 PDF Dokument</h2>
                <div class="instructions">
                    <strong>👀 PODÍVEJ SE NA PDF:</strong><br>
                    Vlevo vidíš PDF dokument. Prostě si ho přečti a opíšeš data do formuláře vpravo!<br>
                    <strong>Je to jako domácí úkol - vidíš text a přepíšeš ho!</strong>
                </div>

                <!-- Stránkování -->
                <div class="page-nav">
                    <button id="prevPage" onclick="prevPage()">⬅️ Předchozí</button>
                    <span id="pageInfo">Stránka 1 z 1</span>
                    <button id="nextPage" onclick="nextPage()">➡️ Další</button>
                </div>

                <canvas id="pdfCanvas"></canvas>
            </div>

            <!-- VPRAVO - Formulář -->
            <div class="form-panel">
                <h2 style="color: #667eea; margin-bottom: 15px;">✍️ Vyplň Formulář</h2>
                <div class="instructions">
                    <strong>✏️ VYPLŇ CO VIDÍŠ V PDF:</strong><br>
                    Prostě opíšeš data z PDF do těchto polí. Až vyplníš všechno, klikni "VYGENEROVAT PATTERNS"!
                </div>

                <form id="mappingForm">
                    <div class="form-group">
                        <label>1️⃣ Číslo reklamace:</label>
                        <input type="text" name="cislo" placeholder="Hodnota z PDF (např. NCE25-00002444-39)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou v PDF? (např. "Číslo reklamace:", "Čislo reklamace:")</small>
                        <input type="text" name="cislo_label" placeholder="Label v PDF (např. Číslo reklamace:)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>2️⃣ Jméno a příjmení:</label>
                        <input type="text" name="jmeno" placeholder="Hodnota z PDF (např. Jan Novák)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="jmeno_label" placeholder="Label v PDF (např. Jméno a příjmení:)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>3️⃣ Email:</label>
                        <input type="email" name="email" placeholder="Hodnota z PDF (např. jan@email.cz)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="email_label" placeholder="Label v PDF (volitelné)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>4️⃣ Telefon:</label>
                        <input type="text" name="telefon" placeholder="Hodnota z PDF (např. 777 123 456)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="telefon_label" placeholder="Label v PDF (volitelné)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>5️⃣ Ulice a číslo popisné:</label>
                        <input type="text" name="ulice" placeholder="Hodnota z PDF (např. Hlavní 123)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="ulice_label" placeholder="Label v PDF (volitelné)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>6️⃣ Město:</label>
                        <input type="text" name="mesto" placeholder="Hodnota z PDF (např. Praha)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="mesto_label" placeholder="Label v PDF (volitelné)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>7️⃣ PSČ:</label>
                        <input type="text" name="psc" placeholder="Hodnota z PDF (např. 110 00)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="psc_label" placeholder="Label v PDF (volitelné)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>8️⃣ Datum prodeje:</label>
                        <input type="text" name="datum_prodeje" placeholder="Hodnota z PDF (např. 01.01.2025)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="datum_prodeje_label" placeholder="Label v PDF (např. Datum vyhotovení:)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>9️⃣ Datum reklamace:</label>
                        <input type="text" name="datum_reklamace" placeholder="Hodnota z PDF (např. 15.01.2025)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="datum_reklamace_label" placeholder="Label v PDF (např. Datum podání:)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>🔟 Model:</label>
                        <input type="text" name="model" placeholder="Hodnota z PDF (např. C157 Intenso)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="model_label" placeholder="Label v PDF (např. Model:)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>1️⃣1️⃣ Provedení:</label>
                        <input type="text" name="provedeni" placeholder="Hodnota z PDF (např. TG 20JJ)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="provedeni_label" placeholder="Label v PDF (např. Složení:)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>1️⃣2️⃣ Barva/Látka:</label>
                        <input type="text" name="barva" placeholder="Hodnota z PDF (např. Light Beige)">
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="barva_label" placeholder="Label v PDF (např. Látka:)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <div class="form-group">
                        <label>1️⃣3️⃣ Popis problému:</label>
                        <textarea name="popis_problemu" placeholder="Hodnota z PDF (např. Vadný mechanismus...)"></textarea>
                        <small style="color: #666;">Jaký TEXT je PŘED touto hodnotou?</small>
                        <input type="text" name="popis_problemu_label" placeholder="Label v PDF (např. Závada:)" style="margin-top: 5px; border-color: #ffc107;">
                    </div>

                    <button type="button" class="generate-btn" onclick="vygenerujPatterns()">
                        🚀 VYGENEROVAT REGEX PATTERNS
                    </button>
                </form>

                <div class="result-box" id="resultBox">
                    <h3 style="color: #28a745; margin-bottom: 15px;">✅ Patterns Vygenerovány!</h3>
                    <div id="resultContent"></div>
                    <button onclick="navigator.clipboard.writeText(document.getElementById('sqlCode').textContent); alert('SQL zkopírováno!');"
                            style="padding: 12px 30px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; margin-top: 15px; font-size: 1.1em;">
                        📋 ZKOPÍROVAT SQL
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let currentPage = 1;
        let pdfRawText = '';

        // Načíst PDF
        document.getElementById('pdfInput').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const arrayBuffer = await file.arrayBuffer();
            pdfDoc = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

            // Extrahovat celý text
            pdfRawText = '';
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                const page = await pdfDoc.getPage(i);
                const textContent = await page.getTextContent();
                pdfRawText += textContent.items.map(item => item.str).join(' ') + '\n';
            }

            console.log('📄 PDF načteno:', pdfDoc.numPages, 'stránek');
            console.log('📝 RAW TEXT:', pdfRawText.substring(0, 500));

            // Zobrazit první stránku
            renderPage(1);
            document.getElementById('workArea').classList.add('active');
        });

        // Vykreslení stránky
        async function renderPage(pageNum) {
            const page = await pdfDoc.getPage(pageNum);
            const viewport = page.getViewport({ scale: 1.5 });

            const canvas = document.getElementById('pdfCanvas');
            const context = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            await page.render({ canvasContext: context, viewport: viewport }).promise;

            // Update page info
            document.getElementById('pageInfo').textContent = `Stránka ${pageNum} z ${pdfDoc.numPages}`;
            document.getElementById('prevPage').disabled = (pageNum === 1);
            document.getElementById('nextPage').disabled = (pageNum === pdfDoc.numPages);
            currentPage = pageNum;
        }

        function prevPage() {
            if (currentPage > 1) {
                renderPage(currentPage - 1);
            }
        }

        function nextPage() {
            if (currentPage < pdfDoc.numPages) {
                renderPage(currentPage + 1);
            }
        }

        // Vygenerovat patterns
        function vygenerujPatterns() {
            const form = document.getElementById('mappingForm');
            const formData = new FormData(form);
            const data = {};

            // Získat vyplněná data
            for (let [key, value] of formData.entries()) {
                if (value.trim()) {
                    data[key] = value.trim();
                }
            }

            if (Object.keys(data).length === 0) {
                alert('❌ Nevyplnil jsi žádná pole! Vyplň aspoň něco z PDF.');
                return;
            }

            console.log('📝 Vyplněná data:', data);

            // Vygenerovat patterns
            const patterns = {};
            const mapping = {};

            for (let [key, value] of Object.entries(data)) {
                // Escapovat speciální znaky pro regex
                const escapedValue = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

                // Najít hodnotu v raw textu a vytvořit pattern
                const index = pdfRawText.indexOf(value);
                if (index !== -1) {
                    // Vzít trochu kontextu před hodnotou
                    const contextStart = Math.max(0, index - 30);
                    const contextBefore = pdfRawText.substring(contextStart, index).trim();

                    // Vytvořit pattern
                    const lastWords = contextBefore.split(/\s+/).slice(-3).join('\\s+');
                    patterns[key] = `/${lastWords}\\s*([^\\n]+)/i`;
                    mapping[key] = key;
                } else {
                    // Fallback - jen hledej hodnotu
                    patterns[key] = `/(${escapedValue})/i`;
                    mapping[key] = key;
                }
            }

            // Zobrazit výsledek
            const resultContent = document.getElementById('resultContent');
            resultContent.innerHTML = `
                <p><strong>📊 Vygenerováno ${Object.keys(patterns).length} patterns!</strong></p>
                <p>Patterns jsou vytvořeny z vyplněných dat a kontextu v PDF.</p>
                <pre id="sqlCode">
-- Regex Patterns
${JSON.stringify(patterns, null, 2)}

-- Pole Mapping
${JSON.stringify(mapping, null, 2)}
                </pre>
            `;

            document.getElementById('resultBox').classList.add('active');
            document.getElementById('resultBox').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
