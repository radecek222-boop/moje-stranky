/**
 * FIX: Oprava scope pro signaturePad
 * Canvas je pouze pro zobrazení (read-only)
 * Podpis se dělá pouze přes modal "Podepsat protokol"
 */
(function() {
  console.log('[Fix] Protokol Signature Display - Inicializace...');

  // Počkat na DOM
  const maxAttempts = 50;
  let attempts = 0;

  const checkInterval = setInterval(() => {
    attempts++;

    const canvas = document.getElementById('signature-pad');

    if (!canvas) {
      if (attempts >= maxAttempts) {
        clearInterval(checkInterval);
        console.error('Canvas #signature-pad nenalezen');
      }
      return;
    }

    // Už inicializováno
    if (window.signaturePad) {
      clearInterval(checkInterval);
      return;
    }

    console.log('[SignatureDisplay] Inicializuji canvas pro zobrazení...');

    // Nastavit velikost canvas
    const resizeCanvas = () => {
      const rect = canvas.getBoundingClientRect();
      const ratio = Math.max(window.devicePixelRatio || 1, 1);

      canvas.width = rect.width * ratio;
      canvas.height = rect.height * ratio;

      const ctx = canvas.getContext('2d');
      if (ctx) {
        ctx.scale(ratio, ratio);
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, rect.width, rect.height);
      }
    };

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Vytvořit minimální signaturePad objekt (pro kompatibilitu s přenosem)
    window.signaturePad = {
      canvas: canvas,

      clear: function() {
        const ctx = canvas.getContext('2d');
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;

        ctx.save();
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.restore();
      },

      isEmpty: function() {
        const ctx = canvas.getContext('2d');
        const pixelData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        // Kontrola jestli jsou tam jen bílé pixely
        for (let i = 0; i < pixelData.length; i += 4) {
          // Pokud není bílá (255,255,255) nebo průhledná
          if (pixelData[i] < 250 || pixelData[i+1] < 250 || pixelData[i+2] < 250) {
            if (pixelData[i+3] > 10) { // není průhledný
              return false;
            }
          }
        }
        return true;
      },

      toDataURL: function() {
        return canvas.toDataURL('image/png');
      }
    };

    console.log('[SignatureDisplay] Canvas připraven pro zobrazení podpisu');
    clearInterval(checkInterval);

  }, 100);
})();
