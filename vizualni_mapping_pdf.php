<?php
/**
 * Vizuální Mapping Tool pro PDF Parser
 * Interaktivní nástroj pro spojení PDF dat s formulářovými poli
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
    <title>🎯 Vizuální Mapping PDF → Formulář (PRO DĚTI)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Comic Sans MS', 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        h1 {
            text-align: center;
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .subtitle {
            text-align: center;
            color: #666;
            font-size: 1.2em;
            margin-bottom: 30px;
        }

        .upload-section {
            background: #f0f4ff;
            border: 3px dashed #667eea;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .upload-section input[type="file"] {
            display: none;
        }

        .upload-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            border: none;
            font-size: 1.2em;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s;
            font-weight: bold;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.6);
        }

        .mapping-grid {
            display: none;
            grid-template-columns: 1fr 100px 1fr;
            gap: 20px;
            margin-top: 30px;
        }

        .mapping-grid.active {
            display: grid;
        }

        .column {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
        }

        .column h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
            font-size: 1.5em;
        }

        .left-column { background: #fff3cd; }
        .right-column { background: #d1ecf1; }

        .data-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid #ffc107;
        }

        .form-field {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid #17a2b8;
        }

        .item-label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .item-value {
            color: #000;
            font-size: 1.1em;
            background: #fffbea;
            padding: 8px;
            border-radius: 5px;
            margin-top: 5px;
            word-wrap: break-word;
            font-weight: bold;
        }

        .field-value {
            background: #e7f6f8;
            padding: 8px;
            border-radius: 5px;
            margin-top: 5px;
            color: #000;
            font-weight: bold;
        }

        .connector-column {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 15px;
            padding-top: 20px;
        }

        .connector {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 15px;
        }

        .connector input {
            width: 60px;
            height: 60px;
            text-align: center;
            font-size: 2em;
            border: 3px solid #667eea;
            border-radius: 50%;
            background: white;
            font-weight: bold;
            color: #667eea;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }

        .connector input:focus {
            outline: none;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            background: #667eea;
            color: white;
        }

        .connector-arrow {
            font-size: 2em;
            color: #667eea;
            margin: 5px 0;
        }

        .submit-section {
            text-align: center;
            margin-top: 40px;
            display: none;
        }

        .submit-section.active {
            display: block;
        }

        .submit-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px 60px;
            border-radius: 50px;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            transition: all 0.3s;
            font-weight: bold;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.6);
        }

        .loading {
            text-align: center;
            padding: 20px;
            display: none;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
        }

        .success-message.active {
            display: block;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
        }

        .error-message.active {
            display: block;
        }

        .emoji {
            font-size: 1.5em;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 Vizuální Mapping PDF → Formulář</h1>
        <p class="subtitle">Jednoduše jako spojovačka! Vyber PDF a spoj data čísly 😊</p>

        <!-- Upload sekce -->
        <div class="upload-section">
            <h2>📄 KROK 1: Vyber PDF soubor</h2>
            <input type="file" id="pdfInput" accept=".pdf">
            <button class="upload-btn" onclick="document.getElementById('pdfInput').click()">
                📁 VYBER PDF SOUBOR
            </button>
            <p style="margin-top: 15px; color: #666;">Vyber NATUZZI nebo PHASE protokol</p>
        </div>

        <!-- Loading -->
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Načítám PDF a rozpoznávám data...</p>
        </div>

        <!-- Mapping grid -->
        <div class="mapping-grid" id="mappingGrid">
            <!-- Levá strana - CO PARSER NAŠEL -->
            <div class="column left-column">
                <h2>📥 CO NAŠEL PARSER V PDF</h2>
                <div id="pdfData"></div>
            </div>

            <!-- Prostřední spojovací sloupec -->
            <div class="connector-column" id="connectorColumn">
                <h2 style="text-align: center; margin-bottom: 20px;">➡️</h2>
            </div>

            <!-- Pravá strana - POLE VE FORMULÁŘI -->
            <div class="column right-column">
                <h2>📤 POLE VE FORMULÁŘI</h2>
                <div id="formFields"></div>
            </div>
        </div>

        <!-- Submit tlačítko -->
        <div class="submit-section" id="submitSection">
            <button class="submit-btn" onclick="ulozMapping()">
                💾 ULOŽIT MAPPING DO DATABÁZE
            </button>
        </div>

        <!-- Zprávy -->
        <div class="success-message" id="successMessage"></div>
        <div class="error-message" id="errorMessage"></div>
    </div>

    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <script>
        // Globální proměnné
        let extrahovanData = {};
        let pdfText = '';
        let detectedConfigName = '';

        // Formulářová pole (podle novareklamace.php)
        const formularovaPole = [
            { key: 'cislo', label: 'Číslo reklamace', example: 'NCE25-00002444-39' },
            { key: 'jmeno', label: 'Jméno a příjmení', example: 'Jan Novák' },
            { key: 'email', label: 'Email', example: 'jan@email.cz' },
            { key: 'telefon', label: 'Telefon', example: '777 123 456' },
            { key: 'ulice', label: 'Ulice a číslo popisné', example: 'Hlavní 123' },
            { key: 'mesto', label: 'Město', example: 'Praha' },
            { key: 'psc', label: 'PSČ', example: '110 00' },
            { key: 'datum_prodeje', label: 'Datum prodeje', example: '01.01.2025' },
            { key: 'datum_reklamace', label: 'Datum reklamace', example: '15.01.2025' },
            { key: 'model', label: 'Model', example: 'C157 Intenso' },
            { key: 'provedeni', label: 'Provedení', example: 'TG 20JJ' },
            { key: 'barva', label: 'Barva/Látka', example: 'Light Beige' },
            { key: 'popis_problemu', label: 'Popis problému', example: 'Vadný mechanismus' }
        ];

        // PDF.js setup
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // Event listener pro výběr souboru
        document.getElementById('pdfInput').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            document.getElementById('loading').classList.add('active');
            document.getElementById('mappingGrid').classList.remove('active');
            document.getElementById('submitSection').classList.remove('active');

            try {
                // Načíst PDF pomocí PDF.js
                const arrayBuffer = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

                let fullText = '';

                // Extrahovat text ze všech stránek
                for (let i = 1; i <= pdf.numPages; i++) {
                    const page = await pdf.getPage(i);
                    const textContent = await page.getTextContent();
                    const pageText = textContent.items.map(item => item.str).join(' ');
                    fullText += pageText + ' ';
                }

                pdfText = fullText;
                console.log('📄 Extrahovaný text:', fullText.substring(0, 500));

                // Poslat na API
                await poslNaAPI(fullText);

            } catch (error) {
                console.error('Chyba při načítání PDF:', error);
                zobrazChybu('❌ Chyba při načítání PDF: ' + error.message);
            }
        });

        // Poslat text na API
        async function poslNaAPI(text) {
            try {
                // Získat CSRF token
                const csrfResponse = await fetch('/app/controllers/get_csrf_token.php');
                const csrfData = await csrfResponse.json();
                const csrfToken = csrfData.token;

                // Poslat na parser API
                const formData = new FormData();
                formData.append('pdf_text', text);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('/api/parse_povereni_pdf.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('📡 API odpověď:', result);

                if (result.status === 'success') {
                    extrahovanData = result.data || {};
                    detectedConfigName = result.config_name || 'Neznámý';

                    zobrazMappingGrid();
                } else {
                    zobrazChybu('❌ API chyba: ' + result.message);
                }

            } catch (error) {
                console.error('Chyba při volání API:', error);
                zobrazChybu('❌ Chyba při zpracování: ' + error.message);
            } finally {
                document.getElementById('loading').classList.remove('active');
            }
        }

        // Zobrazit mapping grid
        function zobrazMappingGrid() {
            const pdfDataDiv = document.getElementById('pdfData');
            const formFieldsDiv = document.getElementById('formFields');
            const connectorDiv = document.getElementById('connectorColumn');

            pdfDataDiv.innerHTML = '';
            formFieldsDiv.innerHTML = '';
            connectorDiv.innerHTML = '<h2 style="text-align: center; margin-bottom: 20px;">➡️</h2>';

            // Levá strana - CO PARSER NAŠEL
            const pdfKeys = Object.keys(extrahovanData);
            pdfKeys.forEach((key, index) => {
                const value = extrahovanData[key];
                if (!value) return; // Přeskočit prázdné

                const itemDiv = document.createElement('div');
                itemDiv.className = 'data-item';
                itemDiv.innerHTML = `
                    <div class="item-label">🔍 Klíč: <code>${key}</code></div>
                    <div class="item-value">${value}</div>
                `;
                pdfDataDiv.appendChild(itemDiv);

                // Spojovací input
                const connector = document.createElement('div');
                connector.className = 'connector';
                connector.innerHTML = `
                    <input type="number"
                           id="left_${index}"
                           min="1"
                           max="${formularovaPole.length}"
                           placeholder="${index + 1}"
                           data-pdf-key="${key}"
                           data-pdf-value="${value}">
                    <div class="connector-arrow">→</div>
                `;
                connectorDiv.appendChild(connector);
            });

            // Pravá strana - POLE VE FORMULÁŘI
            formularovaPole.forEach((field, index) => {
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'form-field';
                fieldDiv.id = `field_${index + 1}`;
                fieldDiv.innerHTML = `
                    <div class="item-label">
                        <span class="emoji">🎯</span>
                        ${index + 1}. ${field.label}
                    </div>
                    <div class="field-value">
                        <code>${field.key}</code><br>
                        <small>Příklad: ${field.example}</small>
                    </div>
                `;
                formFieldsDiv.appendChild(fieldDiv);
            });

            document.getElementById('mappingGrid').classList.add('active');
            document.getElementById('submitSection').classList.add('active');
        }

        // Uložit mapping
        async function ulozMapping() {
            // Projít všechny connector inputy a vytvořit mapping
            const connectorInputs = document.querySelectorAll('.connector input');
            const mapping = {};

            connectorInputs.forEach(input => {
                const pdfKey = input.dataset.pdfKey;
                const targetNumber = parseInt(input.value);

                if (targetNumber && targetNumber >= 1 && targetNumber <= formularovaPole.length) {
                    const targetField = formularovaPole[targetNumber - 1];
                    mapping[pdfKey] = targetField.key;
                }
            });

            console.log('💾 Vytvořený mapping:', mapping);

            if (Object.keys(mapping).length === 0) {
                zobrazChybu('❌ Nevyplnil jsi žádné spojení! Vyplň čísla u šipek.');
                return;
            }

            // Zobrazit co se uloží
            const confirmMsg = `
Uložím tento mapping do databáze:

${Object.entries(mapping).map(([k, v]) => `${k} → ${v}`).join('\n')}

Detekovaná konfigurace: ${detectedConfigName}

Pokračovat?
            `;

            if (!confirm(confirmMsg)) return;

            try {
                // Získat CSRF token
                const csrfResponse = await fetch('/app/controllers/get_csrf_token.php');
                const csrfData = await csrfResponse.json();
                const csrfToken = csrfData.token;

                // Poslat na API
                const formData = new FormData();
                formData.append('mapping', JSON.stringify(mapping));
                formData.append('config_name', detectedConfigName);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('/api/uloz_pdf_mapping.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('📡 API odpověď:', result);

                if (result.status === 'success') {
                    let zprava = `
                        ✅ MAPPING ÚSPĚŠNĚ VYTVOŘEN!<br><br>
                        📋 Počet spojení: ${Object.keys(mapping).length}<br>
                        🔧 Konfigurace: ${detectedConfigName}<br><br>
                        <strong>Mapping:</strong><br>
                        ${Object.entries(mapping).map(([k, v]) => `${k} → ${v}`).join('<br>')}
                    `;

                    if (result.data && result.data.sql) {
                        zprava += `<br><br>
                            <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; text-align: left; margin-top: 20px;">
                                <strong style="color: #4ec9b0;">📝 SQL PŘÍKAZ (zkopíruj a spusť):</strong><br><br>
                                <code style="white-space: pre-wrap; font-size: 0.9em;">${result.data.sql.replace(/\n/g, '<br>')}</code>
                            </div>
                            <br>
                            <button onclick="navigator.clipboard.writeText(\`${result.data.sql}\`); alert('SQL zkopírováno!');"
                                    style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px;">
                                📋 ZKOPÍROVAT SQL
                            </button>
                        `;
                    }

                    zobrazUspech(zprava);
                } else {
                    zobrazChybu('❌ Chyba při ukládání: ' + result.message);
                }

            } catch (error) {
                console.error('Chyba při ukládání mappingu:', error);
                zobrazChybu('❌ Chyba při ukládání: ' + error.message);
            }
        }

        function zobrazUspech(msg) {
            const successDiv = document.getElementById('successMessage');
            successDiv.innerHTML = msg;
            successDiv.classList.add('active');
            document.getElementById('errorMessage').classList.remove('active');
        }

        function zobrazChybu(msg) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.innerHTML = msg;
            errorDiv.classList.add('active');
            document.getElementById('successMessage').classList.remove('active');
        }
    </script>
</body>
</html>
