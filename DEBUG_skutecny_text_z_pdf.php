<?php
/**
 * DEBUG: Zobraz SKUTEČNÝ text z PDF jak ho vidí PDF.js
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$pdfPath = __DIR__ . '/uploads/NATUZZI PROTOKOL.pdf';
$pdfBase64 = base64_encode(file_get_contents($pdfPath));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>DEBUG: Skutečný PDF Text</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        pre { background: #252526; padding: 20px; border: 1px solid #3e3e3e; white-space: pre-wrap; }
        .char { color: #dcdcaa; }
    </style>
</head>
<body>
<h1>🔍 DEBUG: SKUTEČNÝ TEXT Z PDF</h1>
<div id="status">⏳ Načítám PDF...</div>
<h2>Extrahovaný text:</h2>
<pre id="output"></pre>

<h2>Text s viditelnými speciálními znaky:</h2>
<pre id="debug"></pre>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const pdfBase64 = '<?php echo $pdfBase64; ?>';

async function extractText() {
    const statusDiv = document.getElementById('status');
    const outputPre = document.getElementById('output');
    const debugPre = document.getElementById('debug');

    try {
        statusDiv.textContent = '⏳ Dekóduji PDF...';
        const pdfData = atob(pdfBase64);
        const bytes = new Uint8Array(pdfData.length);
        for (let i = 0; i < pdfData.length; i++) {
            bytes[i] = pdfData.charCodeAt(i);
        }

        statusDiv.textContent = '⏳ Načítám stránky...';
        const pdf = await pdfjsLib.getDocument({data: bytes}).promise;

        let fullText = '';
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const textContent = await page.getTextContent();

            // TOTO je klíčové - jak PDF.js spojuje text!
            const pageText = textContent.items.map(item => item.str).join(' ');
            fullText += pageText + '\n';
        }

        statusDiv.textContent = '✅ Hotovo!';

        // Zobrazit normální text
        outputPre.textContent = fullText;

        // Zobrazit se speciálními znaky
        const debugText = fullText
            .replace(/\r/g, '<span class="char">[CR]</span>')
            .replace(/\n/g, '<span class="char">[LF]</span>\n')
            .replace(/\t/g, '<span class="char">[TAB]</span>')
            .replace(/ /g, '<span class="char">·</span>');

        debugPre.innerHTML = debugText;

        // Zkopírovat do schránky
        navigator.clipboard.writeText(fullText);
        statusDiv.innerHTML += '<br>✅ Text zkopírován do schránky!';

    } catch (error) {
        statusDiv.textContent = '❌ CHYBA: ' + error.message;
        console.error(error);
    }
}

extractText();
</script>
</body>
</html>
