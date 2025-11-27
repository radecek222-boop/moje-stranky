/**
 * FIX: Oprava scope pro signaturePad
 * Tento patch zajišťuje, že signaturePad je dostupný globálně
 */
(function() {
  console.log('[Fix] Protokol Signature Fix - Inicializace...');

  // Počkat na inicializaci signature padu
  const maxAttempts = 50; // 5 sekund (50 × 100ms)
  let attempts = 0;

  const checkInterval = setInterval(() => {
    attempts++;

    // Zkusit najít canvas element
    const canvas = document.getElementById('signature-pad');

    if (!canvas) {
      console.warn('Canvas #signature-pad ještě neexistuje');
      if (attempts >= maxAttempts) {
        clearInterval(checkInterval);
        console.error('Canvas #signature-pad nenalezen po 5 sekundách');
      }
      return;
    }

    // Zkontrolovat, jestli už signaturePad existuje v window
    if (window.signaturePad && typeof window.signaturePad.clear === 'function') {
      console.log('window.signaturePad již existuje');
      clearInterval(checkInterval);
      return;
    }

    // Pokud signaturePad neexistuje, vytvořit nový
    if (typeof SignaturePad === 'function') {
      console.log('🎨 Vytvářím nový SignaturePad...');

      // Nastavit velikost canvas
      const resizeCanvas = () => {
        const rect = canvas.getBoundingClientRect();
        const canvasWidth = rect.width;
        const canvasHeight = rect.height;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);

        console.log('📐 Resize canvas:', {
          clientWidth: canvasWidth,
          clientHeight: canvasHeight,
          ratio: ratio,
          physicalWidth: canvasWidth * ratio,
          physicalHeight: canvasHeight * ratio
        });

        // Nastavit fyzickou velikost canvas
        canvas.width = canvasWidth * ratio;
        canvas.height = canvasHeight * ratio;

        const ctx = canvas.getContext('2d');
        if (ctx) {
          // Scale context pro sharp rendering
          ctx.scale(ratio, ratio);

          // Vyplnit bílou barvou (celý canvas včetně scaled velikosti)
          ctx.fillStyle = 'white';
          ctx.fillRect(0, 0, canvasWidth, canvasHeight);
        }
      };

      resizeCanvas();
      window.addEventListener('resize', resizeCanvas);

      // Vytvořit nový signaturePad a uložit do globálního scope
      window.signaturePad = new SignaturePad(canvas, {
        minWidth: 1,
        maxWidth: 2.5,
        penColor: 'black',
        backgroundColor: 'white'
      });

      console.log('SignaturePad vytvořen a uložen do window.signaturePad');
      clearInterval(checkInterval);
    } else {
      console.warn('SignaturePad class ještě není dostupná');
      if (attempts >= maxAttempts) {
        clearInterval(checkInterval);
        console.error('SignaturePad class nenalezena po 5 sekundách');
      }
    }
  }, 100); // Kontrolovat každých 100ms
})();
