<?php
/**
 * ULTIMATE SIMPLE - PDF jako TEXT + Kopíruj do formuláře!
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
    <title>📋 PDF → Formulář (KOPÍRUJ & VLOŽ!)</title>
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
            font-size: 1.5em;
            margin-bottom: 20px;
            font-weight: bold;
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

        .pdf-text-panel {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            max-height: 85vh;
            overflow-y: auto;
        }

        #pdfTextContent {
            background: white;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            border: 2px solid #ddd;
            user-select: text;
            cursor: text;
        }

        .form-panel {
            background: #f0f4ff;
            border-radius: 15px;
            padding: 20px;
            max-height: 85vh;
            overflow-y: auto;
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

        .save-btn {
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
        }

        .instructions {
            background: #d1ecf1;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #17a2b8;
            font-size: 1.2em;
        }

        .highlight {
            background: yellow;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 PDF → Formulář</h1>
        <p class="subtitle">KOPÍRUJ z PDF (vlevo) → VLOŽ do formuláře (vpravo)! 🚀</p>

        <!-- Upload -->
        <div class="upload-section">
            <h2>📄 Nahraj PDF</h2>
            <input type="file" id="pdfInput" accept=".pdf" style="display: none;">
            <button class="upload-btn" onclick="document.getElementById('pdfInput').click()">
                📁 VYBER PDF SOUBOR
            </button>
        </div>

        <!-- Pracovní plocha -->
        <div class="work-area" id="workArea">
            <!-- VLEVO - PDF TEXT -->
            <div class="pdf-text-panel">
                <h2 style="color: #667eea; margin-bottom: 15px;">📄 PDF Text (KOPÍRUJ ODTUD!)</h2>
                <div class="instructions">
                    <strong>💡 NÁVOD:</strong><br>
                    1. Vlevo vidíš TEXT z PDF<br>
                    2. <strong>OZNAČ myší text</strong> který chceš<br>
                    3. <strong>KOPÍRUJ</strong> (Ctrl+C)<br>
                    4. <strong>VLOŽ</strong> do formuláře vpravo (Ctrl+V)<br>
                    5. Opakuj pro všechna pole!
                </div>

                <div id="pdfTextContent">
                    Načítám PDF...
                </div>
            </div>

            <!-- VPRAVO - Formulář -->
            <div class="form-panel">
                <h2 style="color: #667eea; margin-bottom: 15px;">✍️ Formulář (VLOŽ SEM!)</h2>
                <div class="instructions">
                    <strong>📝 KOPÍRUJ & VLOŽ:</strong><br>
                    Prostě zkopíruj text z PDF vlevo a vlož ho sem!
                </div>

                <form id="mappingForm">
                    <div class="form-group">
                        <label>1️⃣ Číslo reklamace:</label>
                        <input type="text" name="cislo" placeholder="Označ v PDF → Kopíruj → Vlož sem">
                    </div>

                    <div class="form-group">
                        <label>2️⃣ Jméno a příjmení:</label>
                        <input type="text" name="jmeno" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>3️⃣ Email:</label>
                        <input type="email" name="email" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>4️⃣ Telefon:</label>
                        <input type="text" name="telefon" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>5️⃣ Ulice a ČP:</label>
                        <input type="text" name="ulice" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>6️⃣ Město:</label>
                        <input type="text" name="mesto" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>7️⃣ PSČ:</label>
                        <input type="text" name="psc" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>8️⃣ Datum prodeje:</label>
                        <input type="text" name="datum_prodeje" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>9️⃣ Datum reklamace:</label>
                        <input type="text" name="datum_reklamace" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>🔟 Model:</label>
                        <input type="text" name="model" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>1️⃣1️⃣ Provedení:</label>
                        <input type="text" name="provedeni" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>1️⃣2️⃣ Barva/Látka:</label>
                        <input type="text" name="barva" placeholder="Kopíruj & Vlož">
                    </div>

                    <div class="form-group">
                        <label>1️⃣3️⃣ Popis problému:</label>
                        <textarea name="popis_problemu" placeholder="Kopíruj & Vlož"></textarea>
                    </div>

                    <button type="button" class="save-btn" onclick="ulozData()">
                        💾 ULOŽIT DATA
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // Načíst PDF
        document.getElementById('pdfInput').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const textDiv = document.getElementById('pdfTextContent');
            textDiv.textContent = '⏳ Načítám PDF...';

            try {
                const arrayBuffer = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

                let fullText = '';

                // Extrahovat text ze všech stránek
                for (let i = 1; i <= pdf.numPages; i++) {
                    const page = await pdf.getPage(i);
                    const textContent = await page.getTextContent();
                    const pageText = textContent.items.map(item => item.str).join(' ');
                    fullText += `\n========== STRÁNKA ${i} ==========\n\n`;
                    fullText += pageText + '\n';
                }

                // Zobrazit text
                textDiv.textContent = fullText;

                console.log('📄 PDF text:', fullText.substring(0, 500));

                document.getElementById('workArea').classList.add('active');

            } catch (error) {
                console.error('Chyba při načítání PDF:', error);
                textDiv.textContent = '❌ Chyba při načítání PDF: ' + error.message;
            }
        });

        // Uložit data
        function ulozData() {
            const form = document.getElementById('mappingForm');
            const formData = new FormData(form);
            const data = {};

            for (let [key, value] of formData.entries()) {
                if (value.trim()) {
                    data[key] = value.trim();
                }
            }

            if (Object.keys(data).length === 0) {
                alert('❌ Nevyplnil jsi žádná pole!');
                return;
            }

            console.log('💾 Vyplněná data:', data);

            alert(`✅ Vyplněno ${Object.keys(data).length} polí!\n\n${Object.entries(data).map(([k, v]) => `${k}: ${v.substring(0, 50)}...`).join('\n')}\n\nData jsou připravena k použití!`);
        }
    </script>
</body>
</html>
