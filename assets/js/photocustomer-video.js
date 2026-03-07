/**
 * photocustomer-video.js
 * Video sekce pro fotodokumentaci zákazníka
 * Závisí na: photocustomer.js (currentCustomerData, showAlert, showWaitDialog, logger)
 */

// ============================================================
// VIDEO SECTION - Nahrání do videotéky
// ============================================================

let videotekaVideos = [];

async function initVideoSection() {
  logger.log('[Video] Inicializace video sekce');

  // Tlačítko nahrát video
  const btnNahrat = document.getElementById('btnNahratVideo');
  if (btnNahrat) {
    btnNahrat.addEventListener('click', () => {
      document.getElementById('videoInput').click();
    });
  }

  // Video input handler
  const videoInput = document.getElementById('videoInput');
  if (videoInput) {
    videoInput.addEventListener('change', handleVideoSelect);
  }

  // Video modal close
  const btnClose = document.getElementById('btnCloseVideoModal');
  if (btnClose) {
    btnClose.addEventListener('click', zavritVideoModal);
  }

  const overlay = document.getElementById('videoModalOverlay');
  if (overlay) {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) zavritVideoModal();
    });
  }

  // ESC key pro zavření modalu
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') zavritVideoModal();
  });

  // Načíst existující videa
  await nactiVidea();
}

async function nactiVidea() {
  if (!currentCustomerData) {
    logger.warn('[Video] Nelze načíst videa - currentCustomerData není nastaveno');
    renderVideoPreview();
    return;
  }

  if (!currentCustomerData.id) {
    logger.warn('[Video] Nelze načíst videa - chybí ID zakázky v currentCustomerData:', currentCustomerData);
    renderVideoPreview();
    return;
  }

  const claimId = currentCustomerData.id;
  logger.log(`[Video] Načítám videa pro zakázku ID: ${claimId}, zákazník: ${currentCustomerData.jmeno || 'N/A'}`);

  try {
    const response = await fetch(`/api/video_api.php?action=list_videos&claim_id=${claimId}`);
    const result = await response.json();

    if (result.status === 'success' && result.videos) {
      videotekaVideos = result.videos;
      logger.log(`[Video] Načteno ${videotekaVideos.length} videí`);
    } else {
      videotekaVideos = [];
    }
  } catch (error) {
    logger.error('[Video] Chyba při načítání videí:', error);
    videotekaVideos = [];
  }

  renderVideoPreview();
}

function renderVideoPreview() {
  const container = document.getElementById('video-preview');
  if (!container) return;

  container.innerHTML = '';

  if (videotekaVideos.length === 0) {
    container.innerHTML = '<div class="video-preview-empty">Zatím žádná videa</div>';
    return;
  }

  videotekaVideos.forEach((video, index) => {
    const card = vytvorVideoKartu(video, index);
    container.appendChild(card);
  });
}

function vytvorVideoKartu(video, index) {
  const card = document.createElement('div');
  card.className = 'video-card';

  // Thumbnail container
  const thumbContainer = document.createElement('div');
  thumbContainer.className = 'video-thumb-container';
  thumbContainer.innerHTML = '<span class="video-play-icon">▶</span>';
  thumbContainer.onclick = () => prehratVideo(video.video_path, video.video_name);

  // Generovat náhled
  generujNahledVidea(video.video_path, 100, 60).then(nahledUrl => {
    if (nahledUrl) {
      const img = document.createElement('img');
      img.src = nahledUrl;
      thumbContainer.insertBefore(img, thumbContainer.firstChild);
    }
  });

  // Info
  const info = document.createElement('div');
  info.className = 'video-info';

  const name = document.createElement('div');
  name.className = 'video-info-name';
  name.textContent = video.video_name || `Video ${index + 1}`;
  name.title = video.video_name;

  const meta = document.createElement('div');
  meta.className = 'video-info-meta';
  const velikost = (video.file_size / 1024 / 1024).toFixed(1);
  let datumText = '';
  if (video.uploaded_at) {
    const d = new Date(video.uploaded_at);
    datumText = ` | ${d.getDate()}.${d.getMonth() + 1}.`;
  }
  meta.textContent = `${velikost} MB${datumText}`;

  info.appendChild(name);
  info.appendChild(meta);

  // Delete button
  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'video-delete-btn';
  deleteBtn.innerHTML = '×';
  deleteBtn.onclick = (e) => {
    e.stopPropagation();
    smazatVideo(video.id);
  };

  card.appendChild(thumbContainer);
  card.appendChild(info);
  card.appendChild(deleteBtn);

  return card;
}

function generujNahledVidea(videoPath, maxSirka, maxVyska) {
  return new Promise((resolve) => {
    const video = document.createElement('video');
    video.crossOrigin = 'anonymous';
    video.muted = true;
    video.preload = 'metadata';

    const timeout = setTimeout(() => {
      video.src = '';
      resolve(null);
    }, 5000);

    video.onloadedmetadata = () => {
      const seekCas = Math.min(1, video.duration * 0.1);
      video.currentTime = seekCas;
    };

    video.onseeked = () => {
      clearTimeout(timeout);
      try {
        const canvas = document.createElement('canvas');
        const videoWidth = video.videoWidth;
        const videoHeight = video.videoHeight;
        const scale = Math.min(maxSirka / videoWidth, maxVyska / videoHeight, 1);

        canvas.width = Math.round(videoWidth * scale * 2);
        canvas.height = Math.round(videoHeight * scale * 2);

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
        video.src = '';
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

function prehratVideo(videoPath, videoName) {
  const overlay = document.getElementById('videoModalOverlay');
  const player = document.getElementById('videoPlayer');
  const title = document.getElementById('videoModalTitle');

  if (!overlay || !player) return;

  title.textContent = videoName || 'Video';
  player.src = videoPath;
  overlay.classList.add('active');
  player.play().catch(() => {});
}

function zavritVideoModal() {
  const overlay = document.getElementById('videoModalOverlay');
  const player = document.getElementById('videoPlayer');

  if (overlay) {
    overlay.classList.remove('active');
  }
  if (player) {
    player.pause();
    player.src = '';
  }
}

async function handleVideoSelect(e) {
  const file = e.target.files[0];
  if (!file) return;

  // Reset input
  e.target.value = '';

  if (!file.type.startsWith('video/')) {
    showAlert('Vyberte prosím video soubor', 'error');
    return;
  }

  // Kontrola velikosti (max 500MB)
  if (file.size > 524288000) {
    showAlert('Video je příliš velké. Maximum je 500 MB.', 'error');
    return;
  }

  if (!currentCustomerData || !currentCustomerData.id) {
    showAlert('Chybí data zákazníka', 'error');
    return;
  }

  showWaitDialog(true, 'Nahrávám video...');

  try {
    const formData = new FormData();
    formData.append('action', 'upload_video');
    formData.append('claim_id', currentCustomerData.id);
    formData.append('video', file, file.name);

    // CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    formData.append('csrf_token', csrfToken);

    const response = await fetch('/api/video_api.php', {
      method: 'POST',
      body: formData,
      credentials: 'include'
    });

    const result = await response.json();

    if (result.status === 'success') {
      showAlert('Video úspěšně nahráno');
      await nactiVidea();
    } else {
      showAlert(result.message || 'Chyba při nahrávání videa', 'error');
    }
  } catch (error) {
    logger.error('[Video] Chyba uploadu:', error);
    showAlert('Chyba při nahrávání videa', 'error');
  } finally {
    showWaitDialog(false);
  }
}

async function smazatVideo(videoId) {
  if (!confirm('Opravdu smazat toto video?')) return;

  showWaitDialog(true, 'Mažu video...');

  try {
    const formData = new FormData();
    formData.append('action', 'delete_video');
    formData.append('video_id', videoId);

    // CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    formData.append('csrf_token', csrfToken);

    const response = await fetch('/api/video_api.php', {
      method: 'POST',
      body: formData,
      credentials: 'include'
    });

    const result = await response.json();

    if (result.status === 'success') {
      showAlert('Video smazáno');
      await nactiVidea();
    } else {
      showAlert(result.message || 'Chyba při mazání videa', 'error');
    }
  } catch (error) {
    logger.error('[Video] Chyba mazání:', error);
    showAlert('Chyba při mazání videa', 'error');
  } finally {
    showWaitDialog(false);
  }
}
