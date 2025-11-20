<?php
/**
 * Test Patterns na SKUTEČNÝCH PDF souborech
 *
 * Tento nástroj načte Base64 PDF soubory, extrahuje z nich text
 * a otestuje všechny patterns na skutečných datech.
 */
require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

// Načíst Base64 PDF soubory v PHP (vyřeší CORS problém)
$pdfFiles = [
    [
        'name' => 'NATUZZI PROTOKOL (Osnice)',
        'base64' => file_get_contents(__DIR__ . '/uploads/base64.txt'),
        'expectedConfig' => 'NATUZZI'
    ],
    [
        'name' => 'NCM-NATUZZI (Praha)',
        'base64' => file_get_contents(__DIR__ . '/uploads/base64-2.txt'),
        'expectedConfig' => 'NATUZZI'
    ],
    [
        'name' => 'PHASE CZ (Praha)',
        'base64' => file_get_contents(__DIR__ . '/uploads/base64-3.txt'),
        'expectedConfig' => 'PHASE CZ'
    ],
    [
        'name' => 'PHASE SK (Zlín)',
        'base64' => file_get_contents(__DIR__ . '/uploads/base64-4.txt'),
        'expectedConfig' => 'PHASE SK'
    ]
];

// Převést do JSON pro JavaScript
$pdfFilesJson = json_encode($pdfFiles);
?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test Patterns na Skutečných PDF</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #1e1e1e;
               color: #d4d4d4; padding: 20px; max-width: 1600px; margin: 0 auto; }
        h1 { color: #4ec9b0; }
        h2 { color: #dcdcaa; margin-top: 30px; }
        .pdf-section { background: #252526; padding: 20px; border-radius: 5px;
                       margin: 20px 0; border-left: 4px solid #007acc; }
        .config-test { background: #1e1e1e; padding: 15px; margin: 10px 0;
                       border: 1px solid #3e3e3e; border-radius: 5px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #3e3e3e; }
        th { background: #264f78; }
        tr:hover { background: #2a2d2e; }
        pre { background: #1e1e1e; padding: 10px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #3e3e3e; max-height: 300px; }
        .score { font-size: 24px; font-weight: bold; }
        #loading { color: #dcdcaa; padding: 20px; text-align: center; }
        .progress { color: #dcdcaa; font-style: italic; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>
<body>

<h1>🔍 Test Patterns na Skutečných PDF</h1>

<div id="loading">⏳ Načítám a zpracovávám PDF...</div>
<div id="results"></div>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// Base64 PDF data (načtená z PHP)
const pdfFiles = <?php echo $pdfFilesJson; ?>;

async function extractTextFromBase64(base64String) {
    // Dekódovat Base64 → ArrayBuffer
    const binaryString = atob(base64String.trim());
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }

    // Načíst PDF
    const pdf = await pdfjsLib.getDocument({ data: bytes }).promise;

    // Extrahovat text ze všech stránek
    let fullText = '';
    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        const textContent = await page.getTextContent();
        const pageText = textContent.items.map(item => item.str).join(' ');
        fullText += pageText + '\n';
    }

    return fullText;
}

async function testPDF(pdfInfo) {
    const resultsDiv = document.getElementById('results');
    const loadingDiv = document.getElementById('loading');

    const section = document.createElement('div');
    section.className = 'pdf-section';
    section.innerHTML = `<h2>📄 ${pdfInfo.name}</h2>
                         <p>Očekávaná konfigurace: <strong>${pdfInfo.expectedConfig}</strong></p>
                         <p class="progress">⏳ Extrahuji text z PDF...</p>`;
    resultsDiv.appendChild(section);

    try {
        // Extrahovat text z PDF
        const pdfText = await extractTextFromBase64(pdfInfo.base64);

        // Zobrazit ukázku textu
        section.innerHTML = `<h2>📄 ${pdfInfo.name}</h2>
                             <p>Očekávaná konfigurace: <strong>${pdfInfo.expectedConfig}</strong></p>
                             <h3>📋 Extrahovaný text (prvních 500 znaků):</h3>
                             <pre>${pdfText.substring(0, 500)}...</pre>
                             <p class="progress">⏳ Testuji patterns...</p>`;

        // Poslat na server pro test patterns
        const formData = new FormData();
        formData.append('pdf_text', pdfText);
        formData.append('expected_config', pdfInfo.expectedConfig);

        const response = await fetch('test_patterns_backend.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            section.innerHTML = `<h2>📄 ${pdfInfo.name}</h2>
                                 <p>Očekávaná konfigurace: <strong>${pdfInfo.expectedConfig}</strong></p>
                                 <h3>📋 Extrahovaný text (prvních 500 znaků):</h3>
                                 <pre>${pdfText.substring(0, 500)}...</pre>
                                 ${result.html}`;
        } else {
            section.innerHTML += `<p class="error">❌ CHYBA: ${result.message}</p>`;
        }

    } catch (error) {
        section.innerHTML += `<p class="error">❌ CHYBA: ${error.message}</p>`;
        console.error('Error:', error);
    }
}

async function runAllTests() {
    const loadingDiv = document.getElementById('loading');
    loadingDiv.textContent = '⏳ Zpracovávám ' + pdfFiles.length + ' PDF souborů...';

    for (const pdfInfo of pdfFiles) {
        await testPDF(pdfInfo);
    }

    loadingDiv.textContent = '✅ Všechny testy dokončeny!';
    setTimeout(() => {
        loadingDiv.style.display = 'none';
    }, 3000);
}

// Spustit testy
runAllTests();
</script>

<p><a href="admin.php" style="color: #4ec9b0;">← Zpět do Admin</a></p>

</body>
</html>
