/**
 * seznam-videoteka.js
 * Správa videí v archívu zákazníka (přehrávání, nahrávání, komprimace)
 * Závisí na: seznam.js (fetchCsrfToken, logger, wgsToast)
 */

/**
 * Zobrazí modal s archivem videí pro zakázku
 * @param {number} claimId - ID zakázky
 */
async function zobrazVideotekaArchiv(claimId) {
  logger.log(`[Videotéka] Otevírám archiv pro zakázku ID: ${claimId}`);

  // Kontrola - pokud už overlay existuje, nezobrazovat znovu (prevence dvojitého kliknutí)
  const existujiciOverlay = document.getElementById('videotekaOverlay');
  if (existujiciOverlay) {
    logger.log('[Videotéka] Overlay už existuje, ignoruji');
    return;
  }

  // Vytvořit overlay
  const overlay = document.createElement('div');
  overlay.id = 'videotekaOverlay';
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10004; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem;';

  // Kontejner
  const container = document.createElement('div');
  container.style.cssText = 'position: relative; width: 95%; max-width: 900px; height: 90%; background: #222; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column;';

  // Tlačítko X (zavřít) - fixní v pravém horním rohu
  const btnX = document.createElement('button');
  btnX.innerHTML = '&times;';
  btnX.style.cssText = 'position: absolute; top: 8px; right: 8px; z-index: 10; width: 30px; height: 30px; max-width: 30px; max-height: 30px; aspect-ratio: 1/1; border-radius: 50%; background: rgba(180,180,180,0.35); color: #cc0000; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; overflow: hidden; flex-shrink: 0;';
  btnX.onclick = () => overlay.remove();

  // Header (bude aktualizován po načtení dat z API)
  const isMobileHeader = window.innerWidth < 600;
  const header = document.createElement('div');
  header.style.cssText = isMobileHeader
    ? 'padding: 12px 16px; background: #333; color: white; font-weight: 600; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 2px; border-bottom: 2px solid #444;'
    : 'padding: 16px 20px; background: #333; color: white; font-weight: 600; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #444;';
  header.innerHTML = `<span>Načítání...</span>`;

  // Content area - seznam videí (s drag & drop podporou) - sloupcový layout
  const content = document.createElement('div');
  content.id = 'videotekaContent';
  content.style.cssText = 'flex: 1; overflow-y: auto; padding: 16px; background: #1a1a1a; display: flex; flex-direction: column; gap: 12px; align-content: start; position: relative; transition: background 0.2s ease;';

  // Drag & drop overlay (skrytý, zobrazí se při přetahování)
  const dropOverlay = document.createElement('div');
  dropOverlay.id = 'videotekaDropOverlay';
  dropOverlay.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(45, 80, 22, 0.3); border: 3px dashed #2D5016; display: none; align-items: center; justify-content: center; z-index: 10; pointer-events: none;';
  dropOverlay.innerHTML = '<div style="color: white; font-size: 1.2rem; font-weight: 600; text-align: center; padding: 2rem;">Pusťte video pro nahrání</div>';

  // Drag & drop event handlery
  let dragCounter = 0;

  content.addEventListener('dragenter', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter++;
    dropOverlay.classList.remove('hidden');
    content.style.background = 'var(--wgs-gray-25)';
  });

  content.addEventListener('dragleave', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter--;
    if (dragCounter === 0) {
      dropOverlay.classList.add('hidden');
      content.style.background = 'var(--wgs-darkest)';
    }
  });

  content.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.stopPropagation();
  });

  content.addEventListener('drop', async (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter = 0;
    dropOverlay.classList.add('hidden');
    content.style.background = 'var(--wgs-darkest)';

    const files = e.dataTransfer.files;
    if (files.length > 0) {
      const file = files[0];
      // Kontrola, zda je to video
      if (file.type.startsWith('video/')) {
        logger.log(`[Videotéka] Drag & drop: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`);
        await nahratVideoDragDrop(file, claimId, overlay);
      } else {
        wgsToast.error('Lze nahrát pouze video soubory');
      }
    }
  });

  // Načíst videa z API
  try {
    const response = await fetch(`/api/video_api.php?action=list_videos&claim_id=${claimId}`);
    const result = await response.json();

    if (result.status === 'success') {
      // Aktualizovat nadpis s jménem zákazníka a číslem reklamace
      const customerName = result.customer_name || 'Neznámý zákazník';
      const reklamaceNum = result.reklamace_cislo || claimId;
      if (isMobileHeader) {
        header.innerHTML = `
          <div style="padding-right: 2.5rem; overflow: hidden;">
            <div style="font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${customerName}</div>
            <div style="font-size: 0.75rem; opacity: 0.6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${reklamaceNum}</div>
          </div>
        `;
      } else {
        // Desktop: jméno + číslo za sebou, s odsazením od X tlačítka
        header.innerHTML = `
          <div style="padding-right: 2.5rem; overflow: hidden;">
            <span style="font-weight: 600;">${customerName}</span>
            <span style="font-size: 0.85rem; opacity: 0.7; margin-left: 0.75rem;">${reklamaceNum}</span>
          </div>
        `;
      }

      if (result.videos && result.videos.length > 0) {
        // Zobrazit seznam videí
        result.videos.forEach(video => {
          const videoCard = vytvorVideoKartu(video, claimId);
          content.appendChild(videoCard);
        });
      } else {
        // Žádná videa - změna na grid layout s centrováním
        content.classList.add('flex-center');
        const emptyState = document.createElement('div');
        emptyState.style.cssText = 'text-align: center; padding: 3rem; color: #999;';
        emptyState.innerHTML = `
          <div style="font-size: 0.85rem; opacity: 0.7; margin-bottom: 1rem;">Žádná videa v archivu</div>
          <div style="font-size: 1.05rem; font-weight: 500; margin-bottom: 0.5rem;">Přetáhněte video sem</div>
          <div style="font-size: 0.85rem; opacity: 0.7;">nebo použijte tlačítko níže</div>
        `;
        content.appendChild(emptyState);
      }
    }
  } catch (error) {
    logger.error('[Videotéka] Chyba při načítání videí:', error);
    header.innerHTML = `<span>Chyba</span>`;
    content.innerHTML = `
      <div style="text-align: center; padding: 3rem; color: #f44;">
        <div style="font-size: 1rem; margin-bottom: 0.5rem;">Chyba při načítání videí</div>
        <div style="font-size: 0.85rem; opacity: 0.7;">${error.message}</div>
      </div>
    `;
  }

  // Footer s tlačítky
  const footer = document.createElement('div');
  footer.style.cssText = 'padding: 16px 20px; background: #333; border-top: 2px solid #444; display: flex; gap: 12px; flex-wrap: wrap; justify-content: center;';

  // Tlačítko Nahrát video
  const btnNahrat = document.createElement('button');
  btnNahrat.textContent = 'Nahrát video';
  btnNahrat.style.cssText = 'padding: 12px 24px; font-size: 0.95rem; font-weight: 600; background: #39ff14; color: #000; border: none; border-radius: 6px; cursor: pointer; min-width: 140px; touch-action: manipulation;';
  btnNahrat.onclick = () => otevritNahravaniVidea(claimId, overlay);

  footer.appendChild(btnNahrat);

  // Přidat drop overlay do content
  content.appendChild(dropOverlay);

  // Sestavit modal
  container.appendChild(header);
  container.appendChild(content);
  container.appendChild(footer);
  container.appendChild(btnX);
  overlay.appendChild(container);

  // Zavřít při kliknutí mimo
  overlay.onclick = (e) => {
    if (e.target === overlay) overlay.remove();
  };

  // Zavřít při ESC
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      overlay.remove();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  document.body.appendChild(overlay);
}

/**
 * Generuje náhled (thumbnail) z videa pomocí HTML5 video + canvas
 * @param {string} videoPath - Cesta k videu
 * @param {number} maxSirka - Maximální šířka náhledu
 * @param {number} maxVyska - Maximální výška náhledu
 * @returns {Promise<string|null>} Data URL obrázku nebo null při chybě
 */
function generujNahledVidea(videoPath, maxSirka, maxVyska) {
  return new Promise((resolve) => {
    const video = document.createElement('video');
    video.crossOrigin = 'anonymous';
    video.muted = true;
    video.preload = 'metadata';

    // Timeout - pokud se video nenačte do 5 sekund, vrátit null
    const timeout = setTimeout(() => {
      video.src = '';
      resolve(null);
    }, 5000);

    video.onloadedmetadata = () => {
      // Seeknout na 1 sekundu nebo 10% délky (co je menší)
      const seekCas = Math.min(1, video.duration * 0.1);
      video.currentTime = seekCas;
    };

    video.onseeked = () => {
      clearTimeout(timeout);
      try {
        const canvas = document.createElement('canvas');

        // FIX: Zachovat pomer stran - nikdy nedeformovat video
        const videoWidth = video.videoWidth;
        const videoHeight = video.videoHeight;
        const scale = Math.min(maxSirka / videoWidth, maxVyska / videoHeight, 1);

        canvas.width = Math.round(videoWidth * scale * 2); // 2x pro ostrost
        canvas.height = Math.round(videoHeight * scale * 2);

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
        video.src = ''; // Uvolnit video
        resolve(dataUrl);
      } catch (e) {
        video.src = '';
        resolve(null);
      }
    };

    video.onerror = () => {
      clearTimeout(timeout);
      resolve(null);
    };

    video.src = videoPath;
  });
}

/**
 * Vytvoří kartu s video náhledem a tlačítky
 * @param {object} video - Video objekt z databáze
 * @param {number} claimId - ID zakázky
 * @returns {HTMLElement}
 */
function vytvorVideoKartu(video, claimId) {
  // Karta - jednoduchý layout: [video] [info] [tlačítka]
  // Všechno zarovnané nahoru (flex-start), tlačítka mají stejnou výšku jako video
  const isMobile = window.innerWidth < 600;

  const card = document.createElement('div');
  card.style.cssText = `
    background: #2a2a2a;
    border-radius: 6px;
    padding: ${isMobile ? '8px' : '12px'};
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: ${isMobile ? '8px' : '12px'};
    border: 1px solid #444;
    width: 100%;
    box-sizing: border-box;
  `;

  // Video thumbnail (náhled)
  const thumbnailContainer = document.createElement('div');
  const thumbWidth = isMobile ? 100 : 120;
  const thumbHeight = isMobile ? 60 : 68;
  thumbnailContainer.style.cssText = `
    flex-shrink: 0;
    width: ${thumbWidth}px;
    height: ${thumbHeight}px;
    background: #1a1a1a;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #555;
    cursor: pointer;
    overflow: hidden;
    position: relative;
  `;
  // Placeholder s ikonou play (zobrazí se dokud se nenačte náhled)
  thumbnailContainer.innerHTML = `<span style="font-size: ${isMobile ? '1.5rem' : '2rem'}; opacity: 0.5; color: #fff;">▶</span>`;
  thumbnailContainer.onclick = () => prehratVideo(video.video_path, video.video_name);

  // Generovat skutečný náhled z videa
  generujNahledVidea(video.video_path, thumbWidth, thumbHeight).then(nahledUrl => {
    if (nahledUrl) {
      // Nahradit placeholder obrázkem s malou ikonou play
      thumbnailContainer.innerHTML = `
        <img src="${nahledUrl}" style="width: 100%; height: 100%; object-fit: cover;">
        <span style="position: absolute; font-size: ${isMobile ? '1.2rem' : '1.5rem'}; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.8); opacity: 0.9;">▶</span>
      `;
    }
  }).catch(() => {
    // Pokud se náhled nepodaří, zůstane placeholder
  });

  // Informace o videu
  const infoContainer = document.createElement('div');
  infoContainer.style.cssText = 'flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 2px; min-width: 0;';
  infoContainer.title = video.video_name; // Název souboru v tooltipu

  // Kdo přidal video (hlavní řádek) - menší na mobilu
  const autorRow = document.createElement('div');
  autorRow.style.cssText = `font-weight: 500; font-size: ${isMobile ? '0.7rem' : '0.9rem'}; color: ${isMobile ? 'var(--wgs-gray-aa)' : 'var(--wgs-white)'};`;
  if (video.uploader_email) {
    const emailKratky = video.uploader_email.split('@')[0];
    autorRow.textContent = emailKratky;
    autorRow.title = video.uploader_email;
  } else {
    autorRow.textContent = 'Admin';
    autorRow.style.color = 'var(--wgs-gray-88)';
  }

  // Velikost a datum (sekundární řádek) - menší na mobilu
  const metaRow = document.createElement('div');
  metaRow.style.cssText = `display: flex; gap: ${isMobile ? '6px' : '8px'}; flex-wrap: wrap; align-items: center;`;

  const velikost = document.createElement('span');
  velikost.style.cssText = `font-size: ${isMobile ? '0.6rem' : '0.7rem'}; color: #666;`;
  velikost.textContent = `${(video.file_size / 1024 / 1024).toFixed(1)} MB`;

  const datum = document.createElement('span');
  datum.style.cssText = `font-size: ${isMobile ? '0.6rem' : '0.7rem'}; color: #666;`;
  // Formatovat datum s dnem v tydnu
  let datumText = '—';
  if (video.uploaded_at) {
    const d = new Date(video.uploaded_at);
    const dny = ['ne', 'po', 'ut', 'st', 'ct', 'pa', 'so'];
    const den = dny[d.getDay()];
    if (isMobile) {
      datumText = `${den} ${d.getDate()}.${d.getMonth() + 1}.`;
    } else {
      datumText = `${den} ${d.getDate()}.${d.getMonth() + 1}.${d.getFullYear()} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
    }
  }
  datum.textContent = datumText;

  metaRow.appendChild(velikost);
  metaRow.appendChild(datum);

  infoContainer.appendChild(autorRow);
  infoContainer.appendChild(metaRow);

  // Tlačítka - na mobilu vertikálně se stejnou výškou jako video náhled
  const buttonsContainer = document.createElement('div');
  // Na mobilu: kontejner má výšku videa, tlačítka se roztáhnou pomocí flex:1
  // Na desktopu: horizontálně
  const btnGap = 4;

  buttonsContainer.style.cssText = isMobile
    ? `display: flex; flex-direction: column; gap: ${btnGap}px; flex-shrink: 0; height: ${thumbHeight}px; max-height: ${thumbHeight}px; overflow: hidden; box-sizing: border-box;`
    : 'display: flex; flex-direction: row; align-items: center; gap: 6px; flex-shrink: 0;';

  // Společný styl pro ikony na mobilu - pevná výška 28px
  // !important přepíše globální min-height: 44px z seznam-mobile-fixes.css
  const ikonaBtnStyle = `height: 28px !important; min-height: 28px !important; max-height: 28px !important; width: 36px; padding: 0; margin: 0; border-radius: 3px; cursor: pointer; touch-action: manipulation; display: flex; align-items: center; justify-content: center; border: 1px solid #555; box-sizing: border-box;`;

  // Tlačítko Stáhnout - ikona na mobilu, text na desktopu
  const btnStahnout = document.createElement('button');
  if (isMobile) {
    btnStahnout.innerHTML = '<i class="fas fa-download"></i>';
    btnStahnout.title = 'Stáhnout video';
    btnStahnout.style.cssText = ikonaBtnStyle + ' background: #444; color: #ccc; font-size: 0.75rem;';
  } else {
    btnStahnout.textContent = 'Stáhnout';
    btnStahnout.style.cssText = 'min-height: 36px; padding: 0.4rem 0.8rem; font-size: 0.8rem; border: 1px solid #555; border-radius: 4px; cursor: pointer; touch-action: manipulation; white-space: nowrap; background: #444; color: white;';
  }
  btnStahnout.onclick = () => {
    const link = document.createElement('a');
    link.href = video.video_path;
    link.download = video.video_name || 'video.mp4';
    link.click();
  };

  // Tlačítko Smazat - dva-klikove potvrzeni (obchazi z-index problemy s wgsConfirm)
  const btnSmazat = document.createElement('button');
  const origBtnStyle = isMobile
    ? ikonaBtnStyle + ' background: #442222; color: #c66; font-size: 0.85rem; font-weight: bold;'
    : 'min-height: 36px; width: 36px; padding: 0; font-size: 1.1rem; font-weight: bold; background: #553333; color: #c66; border: 1px solid #664444; border-radius: 4px; cursor: pointer; touch-action: manipulation; display: flex; align-items: center; justify-content: center;';
  btnSmazat.innerHTML = '&#10005;'; // × křížek
  btnSmazat.title = 'Smazat video';
  btnSmazat.style.cssText = origBtnStyle;

  let potvrzeniTimeout = null;
  btnSmazat.onclick = async (e) => {
    e.stopPropagation();

    // Prvni klik - zobrazit potvrzeni
    if (!btnSmazat.classList.contains('potvrzeni-video')) {
      btnSmazat.classList.add('potvrzeni-video');
      btnSmazat.innerHTML = 'Smazat?';
      btnSmazat.style.cssText = isMobile
        ? ikonaBtnStyle + ' background: #662222; color: #fff; font-size: 0.7rem; font-weight: bold; min-width: 50px;'
        : 'min-height: 36px; padding: 0 8px; font-size: 0.75rem; font-weight: bold; background: #662222; color: #fff; border: 1px solid #884444; border-radius: 4px; cursor: pointer; touch-action: manipulation; white-space: nowrap;';

      // Reset po 3s
      potvrzeniTimeout = setTimeout(() => {
        btnSmazat.classList.remove('potvrzeni-video');
        btnSmazat.innerHTML = '&#10005;';
        btnSmazat.style.cssText = origBtnStyle;
      }, 3000);
      return;
    }

    // Druhy klik - smazat
    clearTimeout(potvrzeniTimeout);
    btnSmazat.innerHTML = '...';
    btnSmazat.disabled = true;

    try {
      const formData = new FormData();
      formData.append('action', 'delete_video');
      formData.append('video_id', video.id);
      formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

      const response = await fetch('/api/video_api.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.status === 'success') {
        wgsToast.success('Video bylo smazáno');
        card.remove();
      } else {
        throw new Error(result.message || 'Chyba při mazání');
      }
    } catch (error) {
      logger.error('[Videotéka] Chyba při mazání videa:', error);
      wgsToast.error('Chyba při mazání videa: ' + error.message);
      // Vratit tlacitko
      btnSmazat.classList.remove('potvrzeni-video');
      btnSmazat.innerHTML = '&#10005;';
      btnSmazat.style.cssText = origBtnStyle;
      btnSmazat.disabled = false;
    }
  };

  buttonsContainer.appendChild(btnStahnout);
  buttonsContainer.appendChild(btnSmazat);

  // Sestavit kartu: [video] [info] [tlačítka]
  card.appendChild(thumbnailContainer);
  card.appendChild(infoContainer);
  card.appendChild(buttonsContainer);

  return card;
}

/**
 * Přehraje video v modálním okně
 * @param {string} videoPath - Cesta k video souboru
 * @param {string} videoName - Název videa
 */
function prehratVideo(videoPath, videoName) {
  // Vytvořit overlay pro přehrávač
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10005; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem;';

  // Video element
  const video = document.createElement('video');
  video.src = videoPath;
  video.controls = true;
  video.autoplay = true;
  video.style.cssText = 'max-width: 95%; max-height: 85vh; border-radius: 8px;';

  // Název videa
  const title = document.createElement('div');
  title.style.cssText = 'color: white; font-size: 1rem; margin-top: 16px; text-align: center;';
  title.textContent = videoName || 'Video';

  // Tlačítko X (zavřít) - fixní v pravém horním rohu
  const btnCloseX = document.createElement('button');
  btnCloseX.innerHTML = '&times;';
  btnCloseX.style.cssText = 'position:fixed;top:12px;right:12px;z-index:10010;width:30px;height:30px;border-radius:50%;background:rgba(180,180,180,0.35);color:#cc0000;border:none;font-size:1.1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;';
  btnCloseX.onclick = () => { video.pause(); overlay.remove(); };

  overlay.appendChild(btnCloseX);
  overlay.appendChild(video);
  overlay.appendChild(title);

  // Zavřít při kliknutí mimo video
  overlay.onclick = (e) => {
    if (e.target === overlay) {
      video.pause();
      overlay.remove();
    }
  };

  // ESC zavře přehrávač
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      video.pause();
      overlay.remove();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  document.body.appendChild(overlay);
}

/**
 * Otevře modal pro nahrání nového videa s automatickou kompresí
 * @param {number} claimId - ID zakázky
 * @param {HTMLElement} parentOverlay - Rodičovský overlay (videotéka archiv)
 */
function otevritNahravaniVidea(claimId, parentOverlay) {
  logger.log(`[Videotéka] Otevírám upload pro zakázku ID: ${claimId}`);

  // Vytvořit overlay pro upload
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10006; display: flex; align-items: center; justify-content: center; padding: 1rem;';

  // Kontejner
  const container = document.createElement('div');
  container.style.cssText = 'position:relative;background: #2a2a2a; border-radius: 8px; padding: 24px; max-width: 500px; width: 100%; border: 2px solid #444;';

  // Tlačítko X (zavřít)
  const btnXUpload = document.createElement('button');
  btnXUpload.innerHTML = '&times;';
  btnXUpload.style.cssText = 'position:absolute;top:10px;right:10px;z-index:1;width:28px;height:28px;border-radius:50%;background:rgba(180,180,180,0.25);color:#cc0000;border:none;font-size:1.2rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;';
  btnXUpload.onclick = () => overlay.remove();
  container.appendChild(btnXUpload);

  // Nadpis
  const nadpis = document.createElement('h3');
  nadpis.style.cssText = 'color: white; margin: 0 0 20px 0; font-size: 1.1rem; padding-right: 2rem;';
  nadpis.textContent = 'Nahrát video';

  // File input
  const fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.id = 'video';
  fileInput.name = 'video';
  fileInput.accept = 'video/*';
  fileInput.style.cssText = 'display: block; width: 100%; padding: 12px; background: #1a1a1a; color: white; border: 1px solid #555; border-radius: 4px; margin-bottom: 16px; font-size: 0.9rem;';

  // Info o velikosti
  const infoBox = document.createElement('div');
  infoBox.style.cssText = 'background: #1a1a1a; padding: 12px; border-radius: 4px; margin-bottom: 16px; color: #999; font-size: 0.85rem; border: 1px solid #555;';
  infoBox.innerHTML = `
    <div style="margin-bottom: 6px;">ℹ️ <strong>Informace o nahrávání:</strong></div>
    <div style="margin-left: 24px;">
      <div>• Maximální velikost: 500 MB</div>
      <div>• Podporované formáty: MP4, MOV, AVI, WebM</div>
      <div>• Video nad 500 MB bude automaticky komprimováno</div>
    </div>
  `;

  // Progress bar (skrytý)
  const progressContainer = document.createElement('div');
  progressContainer.style.cssText = 'display: none; margin-bottom: 16px;';

  const progressBar = document.createElement('div');
  progressBar.style.cssText = 'width: 100%; height: 24px; background: #1a1a1a; border-radius: 4px; overflow: hidden; border: 1px solid #555;';

  const progressFill = document.createElement('div');
  progressFill.style.cssText = 'height: 100%; background: #2D5016; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: 600;';

  progressBar.appendChild(progressFill);
  progressContainer.appendChild(progressBar);

  // Status text
  const statusText = document.createElement('div');
  statusText.style.cssText = 'text-align: center; color: #999; font-size: 0.85rem; margin-top: 8px; display: none;';

  progressContainer.appendChild(statusText);

  // Tlačítka
  const buttonContainer = document.createElement('div');
  buttonContainer.style.cssText = 'display: flex; gap: 12px; justify-content: flex-end;';

  const btnZrusit = document.createElement('button');
  btnZrusit.textContent = 'Zrušit';
  btnZrusit.style.cssText = 'padding: 10px 20px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem;';
  btnZrusit.onclick = () => overlay.remove();

  const btnNahrat = document.createElement('button');
  btnNahrat.textContent = 'Nahrát';
  btnNahrat.style.cssText = 'padding: 10px 20px; background: #2D5016; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; font-weight: 600;';
  btnNahrat.onclick = async () => {
    const file = fileInput.files[0];
    if (!file) {
      wgsToast.warning('Vyberte video soubor');
      return;
    }

    // Kontrola velikosti
    const maxSize = 524288000; // 500 MB
    const needsCompression = file.size > maxSize;

    btnNahrat.disabled = true;
    btnZrusit.disabled = true;
    progressContainer.classList.remove('hidden');
    statusText.classList.remove('hidden');

    try {
      let uploadFile = file;

      // Komprese pokud je potřeba A prohlížeč to podporuje
      if (needsCompression) {
        statusText.textContent = 'Komprimuji video...';
        progressFill.style.width = '10%';
        progressFill.textContent = '10%';

        try {
          // Zkontrolovat podporu MediaRecorder
          if (typeof MediaRecorder === 'undefined') {
            throw new Error('MediaRecorder není podporován');
          }

          uploadFile = await komprimovatVideo(file, (progress) => {
            const percent = Math.round(10 + progress * 40); // 10% - 50%
            progressFill.style.width = percent + '%';
            progressFill.textContent = percent + '%';
          });

          logger.log(`[Videotéka] Video komprimováno: ${file.size} → ${uploadFile.size} bytů`);
        } catch (kompErr) {
          // Fallback - použít originální soubor
          logger.warn(`[Videotéka] Komprese selhala, používám originál: ${kompErr.message}`);
          uploadFile = file;
          statusText.textContent = 'Komprese nedostupná, nahrávám originál...';

          // Pokud je soubor příliš velký bez komprese, zobrazit varování
          if (file.size > 524288000) {
            wgsToast.error('Video je příliš velké (max 500 MB). Komprese selhala.');
            throw new Error('Video příliš velké a komprese není dostupná');
          }
        }
      }

      // Upload
      statusText.textContent = 'Nahrávám video...';
      progressFill.style.width = '50%';
      progressFill.textContent = '50%';

      const formData = new FormData();
      formData.append('action', 'upload_video');
      formData.append('claim_id', claimId);
      formData.append('video', uploadFile, uploadFile.name || file.name);
      formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

      const response = await fetch('/api/video_api.php', {
        method: 'POST',
        body: formData
      });

      progressFill.style.width = '90%';
      progressFill.textContent = '90%';

      const result = await response.json();

      if (result.status === 'success') {
        progressFill.style.width = '100%';
        progressFill.textContent = '100%';
        statusText.textContent = 'Hotovo!';
        progressFill.style.background = 'var(--wgs-gray-33)';

        // Neonový toast pro úspěšný upload
        if (typeof WGSToast !== 'undefined') {
          WGSToast.zobrazit('Video bylo úspěšně nahráno', { titulek: 'WGS' });
        } else {
          wgsToast.success('Video bylo úspěšně nahráno');
        }

        // Zavřít upload modal
        setTimeout(() => {
          overlay.remove();

          // Reload videotéky
          parentOverlay.remove();
          zobrazVideotekaArchiv(claimId);
        }, 1000);

      } else {
        throw new Error(result.message || 'Chyba při nahrávání');
      }

    } catch (error) {
      logger.error('[Videotéka] Chyba při uploadu:', error);
      progressFill.style.background = 'var(--c-progress-red)';
      statusText.textContent = 'Chyba: ' + error.message;
      btnNahrat.disabled = false;
      btnZrusit.disabled = false;
      wgsToast.error('Chyba při nahrávání videa: ' + error.message);
    }
  };

  buttonContainer.appendChild(btnZrusit);
  buttonContainer.appendChild(btnNahrat);

  // Sestavit modal
  container.appendChild(nadpis);
  container.appendChild(infoBox);
  container.appendChild(fileInput);
  container.appendChild(progressContainer);
  container.appendChild(buttonContainer);
  overlay.appendChild(container);

  overlay.addEventListener('click', (e) => { if (e.target === overlay && !btnNahrat.disabled) overlay.remove(); });

  // Zavřít při ESC
  const escHandler = (e) => {
    if (e.key === 'Escape' && !btnNahrat.disabled) {
      overlay.remove();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  document.body.appendChild(overlay);
}

/**
 * Nahraje video přetažené drag & drop
 * @param {File} file - Video soubor
 * @param {number} claimId - ID zakázky
 * @param {HTMLElement} parentOverlay - Rodičovský overlay (videotéka archiv)
 */
async function nahratVideoDragDrop(file, claimId, parentOverlay) {
  logger.log(`[Videotéka] Zahajuji drag & drop upload: ${file.name}`);

  // Vytvořit progress overlay
  const progressOverlay = document.createElement('div');
  progressOverlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10007; display: flex; align-items: center; justify-content: center; padding: 1rem;';

  const progressContainer = document.createElement('div');
  progressContainer.style.cssText = 'background: #2a2a2a; border-radius: 8px; padding: 24px; max-width: 400px; width: 100%; border: 2px solid #444; text-align: center;';

  const progressTitle = document.createElement('div');
  progressTitle.style.cssText = 'color: white; font-size: 1rem; font-weight: 600; margin-bottom: 16px;';
  progressTitle.textContent = 'Nahrávání videa...';

  const progressBarOuter = document.createElement('div');
  progressBarOuter.style.cssText = 'width: 100%; height: 24px; background: #1a1a1a; border-radius: 4px; overflow: hidden; border: 1px solid #555;';

  const progressBarInner = document.createElement('div');
  progressBarInner.style.cssText = 'height: 100%; background: #2D5016; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: 600;';
  progressBarInner.textContent = '0%';

  const progressStatus = document.createElement('div');
  progressStatus.style.cssText = 'color: #999; font-size: 0.85rem; margin-top: 12px;';
  progressStatus.textContent = file.name;

  progressBarOuter.appendChild(progressBarInner);
  progressContainer.appendChild(progressTitle);
  progressContainer.appendChild(progressBarOuter);
  progressContainer.appendChild(progressStatus);
  progressOverlay.appendChild(progressContainer);
  document.body.appendChild(progressOverlay);

  try {
    const maxSize = 524288000; // 500 MB
    let uploadFile = file;

    // Komprese pokud je potřeba
    if (file.size > maxSize) {
      progressStatus.textContent = 'Komprimuji video...';
      progressBarInner.style.width = '10%';
      progressBarInner.textContent = '10%';

      uploadFile = await komprimovatVideo(file, (progress) => {
        const percent = Math.round(10 + progress * 40);
        progressBarInner.style.width = percent + '%';
        progressBarInner.textContent = percent + '%';
      });

      logger.log(`[Videotéka] Video komprimováno: ${file.size} → ${uploadFile.size} bytů`);
    }

    // Upload
    progressStatus.textContent = 'Odesílám na server...';
    progressBarInner.style.width = '50%';
    progressBarInner.textContent = '50%';

    const formData = new FormData();
    formData.append('action', 'upload_video');
    formData.append('claim_id', claimId);
    formData.append('video', uploadFile, uploadFile.name || file.name);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

    const response = await fetch('/api/video_api.php', {
      method: 'POST',
      body: formData
    });

    progressBarInner.style.width = '90%';
    progressBarInner.textContent = '90%';

    const result = await response.json();

    if (result.status === 'success') {
      progressBarInner.style.width = '100%';
      progressBarInner.textContent = '100%';
      progressStatus.textContent = 'Hotovo!';
      progressBarInner.style.background = 'var(--wgs-gray-33)';

      // Neonový toast pro úspěšný upload
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit('Video bylo úspěšně nahráno', { titulek: 'WGS' });
      } else {
        wgsToast.success('Video bylo úspěšně nahráno');
      }

      // Zavřít progress a reload videotéky
      setTimeout(() => {
        progressOverlay.remove();
        parentOverlay.remove();
        zobrazVideotekaArchiv(claimId);
      }, 1000);

    } else {
      throw new Error(result.message || 'Chyba při nahrávání');
    }

  } catch (error) {
    logger.error('[Videotéka] Chyba při drag & drop uploadu:', error);
    progressBarInner.style.background = 'var(--c-progress-red)';
    progressBarInner.style.width = '100%';
    progressStatus.textContent = 'Chyba: ' + error.message;
    wgsToast.error('Chyba při nahrávání videa: ' + error.message);

    // Zavřít progress po 3 sekundách
    setTimeout(() => {
      progressOverlay.remove();
    }, 3000);
  }
}

/**
 * Komprimuje video pomocí MediaRecorder API
 * @param {File} videoFile - Původní video soubor
 * @param {Function} progressCallback - Callback pro progress update
 * @returns {Promise<Blob>} - Komprimované video
 */
async function komprimovatVideo(videoFile, progressCallback) {
  return new Promise((resolve, reject) => {
    try {
      // Vytvořit video element
      const video = document.createElement('video');
      video.preload = 'metadata';
      video.muted = true;
      video.playsInline = true; // Důležité pro iOS

      video.onloadedmetadata = () => {
        const width = video.videoWidth;
        const height = video.videoHeight;

        // Maximální rozlišení 1920x1080
        let targetWidth = width;
        let targetHeight = height;

        if (width > 1920 || height > 1080) {
          const ratio = Math.min(1920 / width, 1080 / height);
          targetWidth = Math.round(width * ratio);
          targetHeight = Math.round(height * ratio);
        }

        // Vytvořit canvas
        const canvas = document.createElement('canvas');
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        const ctx = canvas.getContext('2d');

        // Vytvořit stream z canvasu
        const stream = canvas.captureStream(30); // 30 FPS

        // Najít podporovaný MIME typ (VP9 → VP8 → default)
        const mimeTypy = [
          'video/webm;codecs=vp9',
          'video/webm;codecs=vp8',
          'video/webm',
          'video/mp4'
        ];

        let vybranyMime = '';
        for (const mime of mimeTypy) {
          if (MediaRecorder.isTypeSupported(mime)) {
            vybranyMime = mime;
            break;
          }
        }

        if (!vybranyMime) {
          reject(new Error('Žádný video kodek není podporován'));
          return;
        }

        logger.log(`[Videotéka] Používám kodek: ${vybranyMime}`);

        // MediaRecorder s kompresí
        const options = {
          mimeType: vybranyMime,
          videoBitsPerSecond: 2500000 // 2.5 Mbps - dobrá komprese při zachování kvality
        };

        let mediaRecorder;
        try {
          mediaRecorder = new MediaRecorder(stream, options);
        } catch (e) {
          // Fallback bez specifikace bitrate
          mediaRecorder = new MediaRecorder(stream, { mimeType: vybranyMime });
        }

        const chunks = [];

        mediaRecorder.ondataavailable = (e) => {
          if (e.data.size > 0) {
            chunks.push(e.data);
          }
        };

        mediaRecorder.onstop = () => {
          const blob = new Blob(chunks, { type: vybranyMime });
          // Přidat správnou příponu podle MIME
          const ext = vybranyMime.includes('mp4') ? 'mp4' : 'webm';
          blob.name = videoFile.name.replace(/\.[^.]+$/, '') + '_compressed.' + ext;
          resolve(blob);
        };

        mediaRecorder.onerror = (e) => {
          reject(new Error('Chyba při kompresi videa: ' + (e.error?.message || 'neznámá')));
        };

        // Spustit záznam
        mediaRecorder.start(1000); // chunk každou sekundu

        // Přehrát video a renderovat do canvasu
        video.play().catch(e => {
          reject(new Error('Nelze přehrát video pro kompresi: ' + e.message));
        });

        const renderFrame = () => {
          if (!video.paused && !video.ended) {
            ctx.drawImage(video, 0, 0, targetWidth, targetHeight);

            // Update progress
            if (progressCallback) {
              const progress = video.currentTime / video.duration;
              progressCallback(progress);
            }

            requestAnimationFrame(renderFrame);
          } else {
            // Video skončilo
            setTimeout(() => mediaRecorder.stop(), 100);
          }
        };

        video.onplay = () => {
          renderFrame();
        };

        video.onerror = () => {
          reject(new Error('Chyba při načítání videa pro kompresi'));
        };
      };

      video.onerror = () => {
        reject(new Error('Video soubor nelze načíst'));
      };

      // Načíst video
      video.src = URL.createObjectURL(videoFile);

    } catch (error) {
      reject(error);
    }
  });
}
