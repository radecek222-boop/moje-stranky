/**
 * Jednoduchá implementace Signature Pad bez závislosti na externí knihovně
 * Použití: const pad = new SignaturePad(canvas, options);
 */
class SignaturePad {
  constructor(canvas, options = {}) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.isRysovani = false;
    this.posledniX = 0;
    this.posledniY = 0;

    // Nastavení
    this.options = {
      minWidth: options.minWidth || 1,
      maxWidth: options.maxWidth || 2.5,
      penColor: options.penColor || 'black',
      backgroundColor: options.backgroundColor || 'white'
    };

    // Vyčistit canvas
    this.clear();

    // Přidat event listeners
    this._pridatEventy();
  }

  _pridatEventy() {
    // Desktop myš
    this.canvas.addEventListener('mousedown', (e) => this._zacitRysovat(e));
    this.canvas.addEventListener('mousemove', (e) => this._rysovat(e));
    this.canvas.addEventListener('mouseup', () => this._ukoncitRysovani());
    this.canvas.addEventListener('mouseleave', () => this._ukoncitRysovani());

    // Mobilní dotyk
    this.canvas.addEventListener('touchstart', (e) => {
      e.preventDefault();
      this._zacitRysovat(e.touches[0]);
    }, { passive: false });

    this.canvas.addEventListener('touchmove', (e) => {
      e.preventDefault();
      this._rysovat(e.touches[0]);
    }, { passive: false });

    this.canvas.addEventListener('touchend', () => this._ukoncitRysovani());
  }

  _ziskejPozici(event) {
    const rect = this.canvas.getBoundingClientRect();

    // DŮLEŽITÉ: Souřadnice NESMÍ být násobeny ratio,
    // protože context je už scaled (ctx.scale(ratio, ratio))
    return {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top
    };
  }

  _zacitRysovat(event) {
    this.isRysovani = true;
    const pos = this._ziskejPozici(event);
    this.posledniX = pos.x;
    this.posledniY = pos.y;
  }

  _rysovat(event) {
    if (!this.isRysovani) return;

    const pos = this._ziskejPozici(event);

    this.ctx.beginPath();
    this.ctx.moveTo(this.posledniX, this.posledniY);
    this.ctx.lineTo(pos.x, pos.y);
    this.ctx.strokeStyle = this.options.penColor;
    this.ctx.lineWidth = this.options.maxWidth;
    this.ctx.lineCap = 'round';
    this.ctx.lineJoin = 'round';
    this.ctx.stroke();

    this.posledniX = pos.x;
    this.posledniY = pos.y;
  }

  _ukoncitRysovani() {
    this.isRysovani = false;
  }

  /**
   * Vymazat celý canvas
   */
  clear() {
    this.ctx.fillStyle = this.options.backgroundColor;
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
  }

  /**
   * Zkontrolovat, jestli je canvas prázdný
   */
  isEmpty() {
    const pixelData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height).data;

    // Zkontrolovat, jestli všechny pixely jsou bílé (255, 255, 255, 255)
    for (let i = 0; i < pixelData.length; i += 4) {
      if (pixelData[i] !== 255 || pixelData[i+1] !== 255 || pixelData[i+2] !== 255) {
        return false; // Našel jsme pixel, který není bílý
      }
    }
    return true;
  }

  /**
   * Exportovat jako data URL (base64 PNG)
   */
  toDataURL(type = 'image/png', quality = 1.0) {
    return this.canvas.toDataURL(type, quality);
  }

  /**
   * Načíst podpis z data URL
   */
  fromDataURL(dataUrl) {
    const img = new Image();
    img.onload = () => {
      this.clear();
      this.ctx.drawImage(img, 0, 0);
    };
    img.src = dataUrl;
  }
}

// Exportovat pro použití v ostatních skriptech
if (typeof window !== 'undefined') {
  window.SignaturePad = SignaturePad;

  // Globální helper funkce pro tlačítka (voláno přes data-action)
  window.clearSignaturePad = function() {
    if (window.signaturePad) {
      window.signaturePad.clear();
      console.log('✅ Podpis vymazán');
    } else {
      console.warn('⚠️ signaturePad není inicializován');
    }
  };
}

console.log('✅ SignaturePad načten (lokální implementace)');
