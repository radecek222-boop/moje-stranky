<?php
/**
 * TEST: Zobrazení extrahovaného textu z PDF pro ladění
 *
 * Tento skript ukáže přesně jaký text se extrahuje z PDF
 * a pomůže nastavit správné regex patterns.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>TEST: PDF Extrakce</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
      if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
      }
    </script>
    <style>
        body { font-family: monospace; max-width: 1400px; margin: 20px auto;
               padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .upload-area { border: 3px dashed #2D5016; padding: 40px; text-align: center;
                       border-radius: 10px; margin: 20px 0; background: #f9fdf7;
                       cursor: pointer; }
        .upload-area:hover { background: #e8f5e9; }
        .btn { display: inline-block; padding: 12px 24px; background: #2D5016;
               color: white; border: none; border-radius: 5px; cursor: pointer;
               font-size: 1rem; font-weight: 600; }
        .btn:hover { background: #1a300d; }
        pre { background: #f8f9fa; padding: 20px; border-radius: 5px;
              border: 1px solid #dee2e6; overflow-x: auto; white-space: pre-wrap;
              word-wrap: break-word; line-height: 1.6; }
        .section { margin: 30px 0; padding: 20px; border: 2px solid #dee2e6;
                   border-radius: 10px; }
        .success { color: #155724; background: #d4edda; padding: 10px;
                   border-radius: 5px; margin: 10px 0; }
        .error { color: #721c24; background: #f8d7da; padding: 10px;
                 border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px;
                border-radius: 5px; margin: 10px 0; }
        .highlight { background: yellow; font-weight: bold; }
        #status { font-weight: 600; margin: 20px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 TEST: PDF Extrakce Textu</h1>

    <div class='info'>
        <strong>📝 Účel:</strong> Tento nástroj ukáže přesně jaký text se extrahuje z PDF,
        abyste mohli správně nastavit regex patterns.
    </div>

    <div class='upload-area' onclick="document.getElementById('pdfInput').click()">
        <h2>📄 Klikněte pro nahrání PDF</h2>
        <p>nebo přetáhněte PDF soubor sem</p>
        <input type="file" id="pdfInput" accept="application/pdf" style="display:none">
    </div>

    <div id="status"></div>

    <div id="results" style="display:none;">
        <div class='section'>
            <h2>📄 Název souboru:</h2>
            <pre id="filename"></pre>
        </div>

        <div class='section'>
            <h2>📊 Informace:</h2>
            <pre id="info"></pre>
        </div>

        <div class='section'>
            <h2>📝 Extrahovaný text (RAW):</h2>
            <div class='info'>
                <strong>Tip:</strong> Použijte Ctrl+F pro hledání klíčových slov v textu níže.
            </div>
            <pre id="rawText"></pre>
        </div>

        <div class='section'>
            <h2>🔍 Test regex patterns:</h2>
            <div id="testPatterns"></div>
        </div>
    </div>
</div>

<script>
const pdfInput = document.getElementById('pdfInput');
const statusDiv = document.getElementById('status');
const resultsDiv = document.getElementById('results');
const filenameDiv = document.getElementById('filename');
const infoDiv = document.getElementById('info');
const rawTextDiv = document.getElementById('rawText');
const testPatternsDiv = document.getElementById('testPatterns');

pdfInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    statusDiv.innerHTML = '<div class="info">⏳ Zpracovávám PDF...</div>';
    resultsDiv.style.display = 'none';

    try {
        // Načíst PDF
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

        // Extrakce textu ze všech stránek
        let celkovyText = '';
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const textContent = await page.getTextContent();
            const textItems = textContent.items.map(item => item.str).join(' ');
            celkovyText += `\n========== STRÁNKA ${pageNum} ==========\n`;
            celkovyText += textItems + '\n';
        }

        // Zobrazit výsledky
        filenameDiv.textContent = file.name;
        infoDiv.textContent = `Počet stránek: ${pdf.numPages}\nVelikost: ${(file.size / 1024).toFixed(2)} KB\nDélka textu: ${celkovyText.length} znaků`;
        rawTextDiv.textContent = celkovyText;

        // Test patterns
        testRegexPatterns(celkovyText);

        statusDiv.innerHTML = '<div class="success">✅ PDF úspěšně zpracováno!</div>';
        resultsDiv.style.display = 'block';

    } catch (error) {
        statusDiv.innerHTML = `<div class="error">❌ Chyba: ${error.message}</div>`;
        console.error(error);
    }
});

function testRegexPatterns(text) {
    const patterns = {
        'Číslo reklamace': /Číslo reklamace:\s*\n?\s*([A-Z0-9\-\/]+)/ui,
        'Datum podání': /Datum podání:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui,
        'Číslo objednávky': /Číslo objednávky:\s*\n?\s*(\d+)/ui,
        'Číslo faktury': /Číslo faktury:\s*\n?\s*(\d+)/ui,
        'Datum vyhotovení': /Datum vyhotovení:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui,
        'Jméno a příjmení': /Jméno a příjmení:\s*\n?\s*([^\n]+)/ui,
        'Email': /Email:\s*\n?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/ui,
        'Telefon': /Telefon:\s*\n?\s*([\+\d\s]+)/ui,
        'Adresa (Místo reklamace)': /Místo reklamace.*?Adresa:\s*\n?\s*([^\n]+)/uis,
        'Město': /Město:\s*\n?\s*([^\n]+)/ui,
        'PSČ': /PSČ:\s*\n?\s*(\d{3}\s?\d{2})/ui,
        'Model': /Model:\s*\n?\s*([^\n]+)/ui,
        'Složení': /Složení:\s*\n?\s*([^\n]+(?:\n(?!Látka:)[^\n]+)*)/ui,
        'Látka': /Látka:\s*\n?\s*([^\n]+)/ui,
        'Závada': /Závada:\s*\n?\s*([^\n]+(?:\n(?!Vyjadrenie|Vyjádření)[^\n]+)*)/ui
    };

    let html = '<table style="width:100%; border-collapse: collapse;">';
    html += '<tr style="background:#2D5016;color:white;"><th style="padding:10px;text-align:left;">Pole</th><th style="padding:10px;text-align:left;">Pattern</th><th style="padding:10px;text-align:left;">Nalezeno</th></tr>';

    for (const [nazev, pattern] of Object.entries(patterns)) {
        const match = text.match(pattern);
        const nalezeno = match ? match[1] : '❌ NENALEZENO';
        const barva = match ? '#d4edda' : '#f8d7da';

        html += `<tr style="background:${barva};">`;
        html += `<td style="padding:10px;border-bottom:1px solid #ddd;font-weight:600;">${nazev}</td>`;
        html += `<td style="padding:10px;border-bottom:1px solid #ddd;font-family:monospace;font-size:0.85rem;">${pattern.source}</td>`;
        html += `<td style="padding:10px;border-bottom:1px solid #ddd;">${nalezeno}</td>`;
        html += '</tr>';
    }

    html += '</table>';
    testPatternsDiv.innerHTML = html;
}

// Drag & Drop support
const uploadArea = document.querySelector('.upload-area');

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.style.background = '#e8f5e9';
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.style.background = '#f9fdf7';
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.style.background = '#f9fdf7';

    const file = e.dataTransfer.files[0];
    if (file && file.type === 'application/pdf') {
        pdfInput.files = e.dataTransfer.files;
        pdfInput.dispatchEvent(new Event('change'));
    }
});
</script>
</body>
</html>
