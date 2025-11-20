<?php
/**
 * Vizuální Mapping Tool V2 - S vykreslením PDF!
 * Uživatel vidí PDF vlevo, formulář vpravo, označí text myší a spojí ho s polem
 */
require_once __DIR__ . '/init.php';

// Kontrola přihlášení - pouze pro adminy
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
    <title>🎯 PDF Mapping - Vizuální Editor</title>
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
            font-size: 2em;
            margin-bottom: 20px;
        }

        .upload-section {
            background: #f0f4ff;
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .upload-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            border: none;
            font-size: 1.2em;
            cursor: pointer;
            font-weight: bold;
        }

        .editor-grid {
            display: none;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .editor-grid.active {
            display: grid;
        }

        .pdf-viewer {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }

        #pdfCanvas {
            border: 2px solid #ddd;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 100%;
            cursor: crosshair;
        }

        .form-panel {
            background: #f0f4ff;
            border-radius: 15px;
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .form-field {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 5px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
        }

        .form-field:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .form-field.selected {
            background: #e7f0ff;
            border-left-color: #28a745;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.4);
        }

        .field-label {
            font-weight: bold;
            color: #333;
            font-size: 1.1em;
        }

        .field-key {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .selection-box {
            position: absolute;
            border: 3px solid #28a745;
            background: rgba(40, 167, 69, 0.2);
            pointer-events: none;
            z-index: 1000;
        }

        .selected-text-display {
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }

        .selected-text-display.active {
            display: block;
        }

        .mapping-item {
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
        }

        .submit-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            border: none;
            font-size: 1.3em;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
        }

        .instructions {
            background: #d1ecf1;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #17a2b8;
        }

        .text-layer {
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            opacity: 0.2;
            line-height: 1.0;
        }

        .text-layer > div {
            color: transparent;
            position: absolute;
            white-space: pre;
            cursor: text;
            transform-origin: 0% 0%;
        }

        #pdfContainer {
            position: relative;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 PDF Mapping - Vizuální Editor</h1>

        <!-- Upload sekce -->
        <div class="upload-section">
            <h2>📄 Nahraj PDF soubor</h2>
            <input type="file" id="pdfInput" accept=".pdf" style="display: none;">
            <button class="upload-btn" onclick="document.getElementById('pdfInput').click()">
                📁 VYBER PDF
            </button>
        </div>

        <!-- Editor grid -->
        <div class="editor-grid" id="editorGrid">
            <!-- Levá strana - PDF Viewer -->
            <div>
                <div class="instructions">
                    <strong>📖 NÁVOD:</strong><br>
                    1. Klikni na pole VPRAVO (např. "Číslo reklamace")<br>
                    2. Pak vyber text v PDF myší (drag &amp; drop)<br>
                    3. Text se spojí s polem!<br>
                    4. Opakuj pro všechny pole<br>
                    5. Klikni "ULOŽIT MAPPING"
                </div>

                <div class="pdf-viewer">
                    <h3>📄 PDF Dokument:</h3>
                    <div id="pdfContainer">
                        <canvas id="pdfCanvas"></canvas>
                        <div class="text-layer" id="textLayer"></div>
                    </div>
                </div>

                <div class="selected-text-display" id="selectedTextDisplay">
                    <strong>✅ Vybraný text:</strong>
                    <div id="selectedTextValue"></div>
                    <button onclick="potvrdVyber()" style="padding: 8px 20px; background: #28a745; color: white; border: none; border-radius: 5px; margin-top: 10px; cursor: pointer;">
                        ✔️ POTVRDIT SPOJENÍ
                    </button>
                    <button onclick="zrusVyber()" style="padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; margin-top: 10px; margin-left: 10px; cursor: pointer;">
                        ❌ ZRUŠIT
                    </button>
                </div>

                <div id="mappingList" style="margin-top: 20px;">
                    <h3>📋 Vytvořené spojení:</h3>
                </div>

                <button class="submit-btn" onclick="ulozMapping()" id="submitBtn" style="display: none;">
                    💾 ULOŽIT MAPPING DO DATABÁZE
                </button>
            </div>

            <!-- Pravá strana - Formulář -->
            <div class="form-panel">
                <h3>📝 Pole ve formuláři:</h3>
                <p style="margin-bottom: 15px; color: #666;">Klikni na pole které chceš spojit s textem v PDF</p>
                <div id="formFields"></div>
            </div>
        </div>
    </div>

    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // Formulářová pole
        const formularovaPole = [
            { key: 'cislo', label: 'Číslo reklamace' },
            { key: 'jmeno', label: 'Jméno a příjmení' },
            { key: 'email', label: 'Email' },
            { key: 'telefon', label: 'Telefon' },
            { key: 'ulice', label: 'Ulice a číslo popisné' },
            { key: 'mesto', label: 'Město' },
            { key: 'psc', label: 'PSČ' },
            { key: 'datum_prodeje', label: 'Datum prodeje' },
            { key: 'datum_reklamace', label: 'Datum reklamace' },
            { key: 'model', label: 'Model' },
            { key: 'provedeni', label: 'Provedení' },
            { key: 'barva', label: 'Barva/Látka' },
            { key: 'popis_problemu', label: 'Popis problému' }
        ];

        let aktivniPole = null;
        let mappings = [];
        let pdfText = '';

        // Vykreslit formulářová pole
        function vykresliFormular() {
            const container = document.getElementById('formFields');
            container.innerHTML = '';

            formularovaPole.forEach((field, index) => {
                const div = document.createElement('div');
                div.className = 'form-field';
                div.dataset.key = field.key;
                div.innerHTML = `
                    <div class="field-label">${index + 1}. ${field.label}</div>
                    <div class="field-key"><code>${field.key}</code></div>
                `;
                div.onclick = () => vybratPole(field, div);
                container.appendChild(div);
            });
        }

        // Vybrat pole
        function vybratPole(field, element) {
            // Odstranit předchozí výběr
            document.querySelectorAll('.form-field').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            aktivniPole = field;
            alert(`✅ Vyber teď pole "${field.label}" klikni a táhni myší v PDF!`);
        }

        // Načíst PDF
        document.getElementById('pdfInput').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

            // Vykreslit první stránku
            const page = await pdf.getPage(1);
            const viewport = page.getViewport({ scale: 1.5 });

            const canvas = document.getElementById('pdfCanvas');
            const context = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            await page.render({ canvasContext: context, viewport: viewport }).promise;

            // Extrahovat text pro selection
            const textContent = await page.getTextContent();
            pdfText = textContent.items.map(item => item.str).join(' ');

            // Vykreslit text layer
            const textLayer = document.getElementById('textLayer');
            textLayer.innerHTML = '';
            textLayer.style.width = canvas.width + 'px';
            textLayer.style.height = canvas.height + 'px';

            textContent.items.forEach(item => {
                const tx = pdfjsLib.Util.transform(viewport.transform, item.transform);
                const fontSize = Math.sqrt(tx[2] * tx[2] + tx[3] * tx[3]);
                const div = document.createElement('div');
                div.textContent = item.str;
                div.style.left = tx[4] + 'px';
                div.style.top = (tx[5] - fontSize) + 'px';
                div.style.fontSize = fontSize + 'px';
                div.style.fontFamily = item.fontName;
                textLayer.appendChild(div);
            });

            // Povolit selection na text layer
            enableTextSelection();

            document.getElementById('editorGrid').classList.add('active');
            vykresliFormular();
        });

        // Povolit výběr textu
        function enableTextSelection() {
            const textLayer = document.getElementById('textLayer');

            textLayer.addEventListener('mouseup', () => {
                const selection = window.getSelection();
                const selectedText = selection.toString().trim();

                if (selectedText && aktivniPole) {
                    document.getElementById('selectedTextValue').textContent = selectedText;
                    document.getElementById('selectedTextDisplay').classList.add('active');

                    // Dočasně uložit
                    window.tempSelection = {
                        text: selectedText,
                        field: aktivniPole
                    };
                }
            });
        }

        // Potvrdit výběr
        function potvrdVyber() {
            if (window.tempSelection) {
                mappings.push({
                    field: window.tempSelection.field.key,
                    label: window.tempSelection.field.label,
                    text: window.tempSelection.text
                });

                zobrazMappings();
                zrusVyber();

                // Odstranit výběr pole
                document.querySelectorAll('.form-field').forEach(el => el.classList.remove('selected'));
                aktivniPole = null;

                document.getElementById('submitBtn').style.display = 'block';
            }
        }

        // Zrušit výběr
        function zrusVyber() {
            document.getElementById('selectedTextDisplay').classList.remove('active');
            window.getSelection().removeAllRanges();
            window.tempSelection = null;
        }

        // Zobrazit vytvořené mappingy
        function zobrazMappings() {
            const list = document.getElementById('mappingList');
            list.innerHTML = '<h3>📋 Vytvořené spojení:</h3>';

            mappings.forEach((m, i) => {
                const div = document.createElement('div');
                div.className = 'mapping-item';
                div.innerHTML = `
                    <strong>${m.label}:</strong> "${m.text}"
                    <button onclick="smazatMapping(${i})" style="float: right; background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                        🗑️ Smazat
                    </button>
                `;
                list.appendChild(div);
            });
        }

        // Smazat mapping
        function smazatMapping(index) {
            mappings.splice(index, 1);
            zobrazMappings();
            if (mappings.length === 0) {
                document.getElementById('submitBtn').style.display = 'none';
            }
        }

        // Uložit mapping
        async function ulozMapping() {
            if (mappings.length === 0) {
                alert('❌ Žádné spojení nevytvořeno!');
                return;
            }

            const result = {
                mappings: mappings,
                count: mappings.length
            };

            console.log('💾 Mapping k uložení:', result);

            alert(`✅ Vytvořeno ${mappings.length} spojení!\n\n${mappings.map(m => `${m.label}: "${m.text}"`).join('\n')}\n\nTento mapping bude použit pro generování regex patterns.`);
        }
    </script>
</body>
</html>
