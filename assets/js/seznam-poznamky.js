/**
 * seznam-poznamky.js - Systém poznámek a hlasového nahrávání
 *
 * Lazy-loaded modul — načítá se dynamicky při prvním otevření poznámek.
 * Závislosti (globální): WGS_DATA_CACHE, CURRENT_USER, CURRENT_RECORD,
 *   ModalManager, wgsToast, wgsConfirm, loadAll, renderOrders,
 *   closeDetail, createCustomerHeader, getCSRFToken, t, Utils, logger
 *
 * Vstupní bod: seznam.js volá window._showNotes() po načtení tohoto modulu.
 */

// Příznak pro lazy-loader v seznam.js
window._szPoznamkyNacten = true;

// === SYSTÉM POZNÁMEK - API VERSION ===

async function getNotes(orderId) {
  try {
    const record = WGS_DATA_CACHE.find(x => x.id == orderId || x.reklamace_id == orderId);
    if (!record) return [];

    const reklamaceId = record.reklamace_id || record.id;
    const response = await fetch(`api/notes_api.php?action=get&reklamace_id=${encodeURIComponent(reklamaceId)}`);
    const data = await response.json();

    if (data.status === 'success' || data.success === true) {
      return data.notes || [];
    }
    return [];
  } catch (e) {
    logger.error('Chyba při načítání poznámek:', e);
    return [];
  }
}

async function addNote(orderId, text, audioBlob = null) {
  try {
    const record = WGS_DATA_CACHE.find(x => x.id == orderId || x.reklamace_id == orderId);
    if (!record) {
      throw new Error('Reklamace nenalezena');
    }

    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const reklamaceId = record.reklamace_id || record.id;
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('reklamace_id', reklamaceId);
    formData.append('text', text.trim());
    formData.append('csrf_token', csrfToken);

    // Pridat audio pokud existuje
    logger.log('[Audio] audioBlob status:', audioBlob ? 'existuje' : 'null', audioBlob ? audioBlob.size + ' bytes' : '');

    if (audioBlob) {
      // Urcit priponu podle MIME typu
      let ext = 'webm';
      if (audioBlob.type.includes('mp4')) ext = 'm4a';
      else if (audioBlob.type.includes('ogg')) ext = 'ogg';
      else if (audioBlob.type.includes('mp3') || audioBlob.type.includes('mpeg')) ext = 'mp3';
      else if (audioBlob.type.includes('wav')) ext = 'wav';

      formData.append('audio', audioBlob, `nahravka.${ext}`);
      logger.log('[Audio] Odesilam nahravku:', Math.round(audioBlob.size / 1024), 'KB, type:', audioBlob.type);
    }

    logger.log('[Notes] Odesilam poznamku na API...');
    const response = await fetch('api/notes_api.php', {
      method: 'POST',
      body: formData
    });

    logger.log('[Notes] API odpoved status:', response.status);
    const data = await response.json();
    logger.log('[Notes] API odpoved data:', JSON.stringify(data));

    if (data.status === 'success' || data.success === true) {
      return { success: true, note_id: data.note_id };
    } else {
      // PHP vraci 'error' ne 'message'
      throw new Error(data.error || data.message || 'Chyba pri pridavani poznamky');
    }
  } catch (e) {
    logger.error('Chyba pri pridavani poznamky:', e);
    throw e;
  }
}

async function deleteNote(noteId, orderId) {
  if (!await wgsConfirm('Opravdu chcete smazat tuto poznámku?', 'Smazat', 'Zrušit')) {
    return;
  }

  try {
    const csrfToken = await getCSRFToken();

    // FIX: Pouzit URLSearchParams misto FormData - spolehlivejsi pro Safari
    const params = new URLSearchParams();
    params.append('action', 'delete');
    params.append('note_id', noteId);
    params.append('csrf_token', csrfToken);

    const response = await fetch('/api/notes_api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: params
    });

    const data = await response.json();

    if (data.status === 'success') {
      // Odstranit poznamku z DOM
      const noteElement = document.querySelector(`[data-note-id="${noteId}"]`);
      if (noteElement) {
        noteElement.remove();
      }

      // Zkontrolovat zda jsou jeste nejake poznamky
      const notesContainer = document.querySelector('.notes-container');
      if (notesContainer && notesContainer.querySelectorAll('.note-item').length === 0) {
        notesContainer.innerHTML = '<div class="empty-notes">Zatim zadne poznamky</div>';
      }

      await loadAll();
    } else {
      wgsToast.error('Chyba: ' + (data.error || data.message || 'Neznama chyba'));
    }
  } catch (e) {
    logger.error('Chyba pri mazani poznamky:', e);
    wgsToast.error('Chyba pri mazani poznamky: ' + e.message);
  }
}

async function markNotesAsRead(orderId) {
  try {
    const record = WGS_DATA_CACHE.find(x => x.id == orderId || x.reklamace_id == orderId);
    if (!record) return;

    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const reklamaceId = record.reklamace_id || record.id;
    const formData = new FormData();
    formData.append('action', 'mark_read');
    formData.append('reklamace_id', reklamaceId);
    formData.append('csrf_token', csrfToken);

    await fetch('api/notes_api.php', {
      method: 'POST',
      body: formData
    });
  } catch (e) {
    logger.error('Chyba při označování poznámek:', e);
  }
}

async function showNotes(recordOrId) {
  let record;
  if (typeof recordOrId === 'string' || typeof recordOrId === 'number') {
    record = WGS_DATA_CACHE.find(x => x.id == recordOrId || x.reklamace_id == recordOrId);
    if (!record) {
      wgsToast.error(t('record_not_found'));
      return;
    }
  } else {
    record = recordOrId;
  }

  CURRENT_RECORD = record;

  const loadingContent = `
    ${createCustomerHeader()}
    <div class="modal-body" style="text-align: center; padding: 3rem;">
      <div class="loading">Načítání poznámek...</div>
    </div>
  `;
  ModalManager.show(loadingContent);

  const notes = await getNotes(record.id);

  notes.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

  const content = `
    ${createCustomerHeader()}

    <div class="modal-body">
      <div class="notes-container" id="notes-container-htmx" data-reklamace-id="${record.reklamace_id || record.id}">
        ${notes.length > 0
          ? notes.map(note => {
              const canDelete = CURRENT_USER && (CURRENT_USER.is_admin || note.author === CURRENT_USER.email);
              const hasAudio = note.has_audio && note.audio_url;
              const isVoiceNote = note.text === '[Hlasová poznámka]' || note.text === '[Hlasova poznamka]';
              return `
              <div class="note-item ${note.read ? '' : 'unread'} ${hasAudio ? 'has-audio' : ''}" data-note-id="${note.id}">
                <div class="note-header">
                  <span class="note-author">${note.author_name || note.author}</span>
                  <span class="note-time">${formatDateTime(note.timestamp)}</span>
                  ${canDelete ? `<button class="note-delete-btn" data-note-id="${note.id}" data-order-id="${record.id}" onclick="event.stopPropagation(); potvrditSmazaniPoznamky(this);" title="Smazat poznamku">x</button>` : ''}
                </div>
                ${!isVoiceNote ? `<div class="note-text">${Utils.escapeHtml(note.text)}</div>` : ''}
                ${hasAudio ? `
                <div class="note-audio">
                  <audio controls preload="metadata" class="note-audio-player">
                    <source src="${note.audio_url}" type="audio/mp4">
                    <source src="${note.audio_url}" type="audio/webm">
                    <source src="${note.audio_url}" type="audio/mpeg">
                    Vas prohlizec nepodporuje prehravani audia.
                  </audio>
                </div>
                ` : ''}
              </div>
            `;
            }).join('')
          : '<div class="empty-notes">Zatim zadne poznamky</div>'
        }
      </div>

      <div class="note-input-area">
        <textarea
          class="note-textarea"
          id="newNoteText"
          placeholder="Napiste poznamku..."
        ></textarea>
        <div class="note-input-controls">
          <button type="button" class="btn-record" id="btnStartRecord" data-action="startRecording" data-id="${record.id}" title="Nahrat hlasovou zpravu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
              <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
            </svg>
          </button>
          <div class="recording-indicator" id="recordingIndicator">
            <span class="recording-dot"></span>
            <span class="recording-time" id="recordingTime">0:00</span>
            <button type="button" class="btn-stop-record" id="btnStopRecord" data-action="stopRecording" data-id="${record.id}">Stop</button>
          </div>
          <div class="audio-preview hidden" id="audioPreview">
            <audio id="audioPreviewPlayer" controls></audio>
            <button type="button" class="btn-delete-audio" id="btnDeleteAudio" data-action="deleteAudioPreview" title="Smazat nahravku">x</button>
          </div>
        </div>
      </div>
    </div>

    <div class="detail-buttons">
      <button class="detail-btn detail-btn-primary" data-action="saveNewNote" data-id="${record.id}">Pridat poznamku</button>
      <button class="detail-btn detail-btn-secondary" data-action="closeNotesModal">Zavrit</button>
    </div>
  `;

  ModalManager.show(content);

  // Pridat error handling pro vsechny audio prehravace
  setTimeout(() => {
    const audioPlayers = document.querySelectorAll('.note-audio-player');
    audioPlayers.forEach(audio => {
      audio.onerror = function() {
        logger.log('[Audio] Chyba pri nacitani ulozene nahravky');
        // Nahradit audio element chybovou zpravou
        const parent = audio.closest('.note-audio');
        if (parent) {
          parent.innerHTML = '<span style="color: var(--c-grey); font-size: 0.75rem;">Audio nelze nacist</span>';
        }
      };
    });
  }, 100);

  setTimeout(async () => {
    await markNotesAsRead(record.id);
    await loadAll();
    // Aktualizovat badge na ikone PWA
    if (window.WGSNotifikace) {
      window.WGSNotifikace.aktualizovat();
    }
  }, 1000);
}

async function saveNewNote(orderId) {
  const textarea = document.getElementById('newNoteText');
  const text = textarea.value.trim();
  const audioBlob = window.wgsAudioRecorder ? window.wgsAudioRecorder.audioBlob : null;

  // Musi byt text NEBO audio
  if (!text && !audioBlob) {
    wgsToast.warning(t('write_note_text'));
    return;
  }

  try {
    await addNote(orderId, text, audioBlob);

    // Vycistit audio recorder
    if (window.wgsAudioRecorder) {
      window.wgsAudioRecorder.audioBlob = null;
      window.wgsAudioRecorder.audioChunks = [];
    }

    // HTMX refresh: obnovit seznam poznámek bez zavření modalu (Step 142)
    const notesObs = document.getElementById('notes-container-htmx');
    if (window.htmx && notesObs) {
      const rekId = notesObs.getAttribute('data-reklamace-id');
      htmx.ajax('GET', `/api/poznamky_html.php?reklamace_id=${encodeURIComponent(rekId)}`, {
        target: '#notes-container-htmx',
        swap: 'outerHTML'
      });
      // Vyčistit textarea po přidání
      const textarea = document.getElementById('newNoteText');
      if (textarea) textarea.value = '';
    } else {
      // Fallback: zavřít modal a obnovit celý seznam
      closeNotesModal();
    }

    await loadAll();

    // Aktualizovat badge na ikone PWA (nova poznamka)
    if (window.WGSNotifikace) {
      window.WGSNotifikace.aktualizovat();
    }
  } catch (e) {
    wgsToast.error(t('note_save_error') + ': ' + e.message);
  }
}

function closeNotesModal() {
  // Zastavit nahravani pokud probiha
  if (window.wgsAudioRecorder && window.wgsAudioRecorder.isRecording) {
    stopRecording();
  }
  // Uvolnit mikrofon (kdyby zustal aktivni)
  if (typeof releaseMicrophone === 'function') {
    releaseMicrophone();
  }
  closeDetail();
  renderOrders();
}

// ========================================
// AUDIO NAHRAVANI - Hlasove poznamky
// ========================================
window.wgsAudioRecorder = {
  mediaRecorder: null,
  audioChunks: [],
  audioBlob: null,
  isRecording: false,
  recordingStartTime: null,
  recordingTimer: null,
  permissionGranted: false, // Zapamatovat ze bylo povoleno
  stream: null // Ulozit stream pro pozdejsi zastaveni
};

// Zkontrolovat stav opravneni mikrofonu
async function checkMicrophonePermission() {
  try {
    // Pouzit Permissions API pokud je k dispozici
    if (navigator.permissions && navigator.permissions.query) {
      const result = await navigator.permissions.query({ name: 'microphone' });
      logger.log('[Audio] Stav opravneni mikrofonu:', result.state);

      if (result.state === 'granted') {
        window.wgsAudioRecorder.permissionGranted = true;
        return 'granted';
      } else if (result.state === 'denied') {
        return 'denied';
      }
      return 'prompt'; // Jeste se nezeptalo
    }
  } catch (e) {
    // Permissions API neni podporovano (napr. Safari)
    logger.log('[Audio] Permissions API neni podporovano, zkusim primo');
  }
  return 'unknown';
}

async function startRecording(orderId) {
  logger.log('[Audio] Spoustim nahravani...');

  try {
    // Zkontrolovat podporu
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error('Vas prohlizec nepodporuje nahravani zvuku');
    }

    // Zkontrolovat stav opravneni
    const permissionState = await checkMicrophonePermission();

    if (permissionState === 'denied') {
      throw new Error('Pristup k mikrofonu byl trvale odepren. Povolte ho v nastaveni prohlizece.');
    }

    // Pokud jeste nebylo povoleno, zobrazit vysvetleni (jen poprve)
    if (!window.wgsAudioRecorder.permissionGranted && permissionState !== 'granted') {
      // Ulozit do localStorage ze jsme uz vysvetleni zobrazili
      const explanationShown = localStorage.getItem('wgs_mic_explained');
      if (!explanationShown) {
        wgsToast.info('Pro nahravani hlasovych poznamek potrebujeme pristup k mikrofonu. Po kliknuti na OK vas prohlizec pozada o povoleni.');
        localStorage.setItem('wgs_mic_explained', '1');
      }
    }

    // Pozadat o pristup k mikrofonu
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

    // Ulozit stream pro pozdejsi zastaveni
    window.wgsAudioRecorder.stream = stream;

    // Zapamatovat ze bylo povoleno
    window.wgsAudioRecorder.permissionGranted = true;

    // Vybrat podporovany format
    // Safari/iOS: preferovat MP4 (WebM nefunguje pri prehravani)
    // Chrome/Firefox: preferovat WebM (lepsi komprese)
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent) ||
                     /iPad|iPhone|iPod/.test(navigator.userAgent);

    let mimeType = 'audio/webm';

    if (isSafari) {
      // Safari/iOS - pouzit MP4 (jediny spolehlivy format)
      if (MediaRecorder.isTypeSupported('audio/mp4')) {
        mimeType = 'audio/mp4';
      } else if (MediaRecorder.isTypeSupported('audio/aac')) {
        mimeType = 'audio/aac';
      }
      // Fallback na cokoliv co funguje
      logger.log('[Audio] Safari detekovan, preferuji MP4');
    } else {
      // Chrome/Firefox - pouzit WebM (lepsi komprese)
      if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
        mimeType = 'audio/webm;codecs=opus';
      } else if (MediaRecorder.isTypeSupported('audio/webm')) {
        mimeType = 'audio/webm';
      } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
        mimeType = 'audio/mp4';
      } else if (MediaRecorder.isTypeSupported('audio/ogg')) {
        mimeType = 'audio/ogg';
      }
    }

    logger.log('[Audio] Pouzivam format:', mimeType);

    const recorder = window.wgsAudioRecorder;
    recorder.mimeType = mimeType; // Ulozit pro pouziti v onstop
    recorder.mediaRecorder = new MediaRecorder(stream, { mimeType });
    recorder.audioChunks = [];
    recorder.isRecording = true;
    recorder.recordingStartTime = Date.now();

    // Sbírat data
    recorder.mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) {
        recorder.audioChunks.push(e.data);
        logger.log('[Audio] Data chunk:', e.data.size, 'bytes');
      }
    };

    // Po ukonceni nahravani
    recorder.mediaRecorder.onstop = () => {
      logger.log('[Audio] Nahravani ukonceno, chunks:', recorder.audioChunks.length);

      if (recorder.audioChunks.length === 0) {
        logger.error('[Audio] Zadna data nebyla nahrana');
        wgsToast.warning('Nahravka je prazdna. Zkuste to prosim znovu.');
        document.getElementById('btnStartRecord').classList.remove('hidden');
        document.getElementById('recordingIndicator').classList.remove('active');
        return;
      }

      // Pouzit ulozeny mimeType
      const blobType = recorder.mimeType || 'audio/webm';
      recorder.audioBlob = new Blob(recorder.audioChunks, { type: blobType });
      recorder.isRecording = false;

      logger.log('[Audio] Blob vytvoren:', recorder.audioBlob.size, 'bytes, type:', blobType);

      // Uvolnit mikrofon
      releaseMicrophone();

      // Zobrazit nahled
      showAudioPreview(recorder.audioBlob);
    };

    // Spustit nahravani s timeslice 1000ms
    // Timeslice zajisti ze ondataavailable se vola kazdou sekundu
    // To je dulezite pro mobilni prohlizece/PWA kde bez timeslice muze byt nespolehlivy
    recorder.mediaRecorder.start(1000);

    // Aktualizovat UI
    document.getElementById('btnStartRecord').classList.add('hidden');
    document.getElementById('recordingIndicator').classList.add('active');

    // Casovac
    recorder.recordingTimer = setInterval(() => {
      const elapsed = Math.floor((Date.now() - recorder.recordingStartTime) / 1000);
      const mins = Math.floor(elapsed / 60);
      const secs = elapsed % 60;
      document.getElementById('recordingTime').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
    }, 1000);

    logger.log('[Audio] Nahravani spusteno');

  } catch (err) {
    logger.error('[Audio] Chyba pri nahravani:', err);

    // Uvolnit prostredky pri chybe (dulezite pro iOS PWA)
    releaseMicrophone();

    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
      wgsToast.error('Pristup k mikrofonu byl odepren. Povolte pristup v nastaveni prohlizece.');
    } else {
      wgsToast.error('Chyba pri nahravani: ' + err.message);
    }
  }
}

function stopRecording() {
  logger.log('[Audio] Zastavuji nahravani...');

  const recorder = window.wgsAudioRecorder;

  // Zastavit casovac hned
  if (recorder.recordingTimer) {
    clearInterval(recorder.recordingTimer);
    recorder.recordingTimer = null;
  }

  if (recorder.mediaRecorder && recorder.isRecording) {
    // Vyzadat posledni data pred zastavenim (dulezite pro mobilni prohlizece)
    if (recorder.mediaRecorder.state === 'recording') {
      try {
        recorder.mediaRecorder.requestData();
        // FIX: Pridat male zpozdeni aby data stihla dorazit pred stop()
        // Na nekterych prohlizecich (Safari/iOS) requestData() je asynchronni
        // a data dorazila az po stop(), coz vedlo k prazdnym nahrávkam
        setTimeout(() => {
          if (recorder.mediaRecorder && recorder.mediaRecorder.state === 'recording') {
            recorder.mediaRecorder.stop();
            logger.log('[Audio] MediaRecorder zastaven po requestData zpozdeni');
          }
        }, 150);
      } catch (e) {
        logger.log('[Audio] requestData neni podporovano:', e.message);
        // Fallback - zavolat stop() primo
        recorder.mediaRecorder.stop();
      }
    } else if (recorder.mediaRecorder.state !== 'inactive') {
      recorder.mediaRecorder.stop();
    }
  }

  // Aktualizovat UI - skryt recording indicator
  // Pozn: tlacitko startRecord se ukaze az v onstop handleru po zpracovani dat
  const recordingIndicator = document.getElementById('recordingIndicator');
  if (recordingIndicator) recordingIndicator.classList.remove('active');
}

// Uvolnit mikrofon - zastavit stream
// Dulezite pro iOS PWA - bez kompletniho uvolneni nahravani funguje jen jednou
function releaseMicrophone() {
  const recorder = window.wgsAudioRecorder;

  // Zastavit vsechny tracky streamu
  if (recorder.stream) {
    recorder.stream.getTracks().forEach(track => {
      track.stop();
      logger.log('[Audio] Track zastaven:', track.kind);
    });
    recorder.stream = null;
  }

  // Reset MediaRecorder (dulezite pro iOS PWA)
  if (recorder.mediaRecorder) {
    recorder.mediaRecorder = null;
  }

  // Reset stavu
  recorder.isRecording = false;
  recorder.audioChunks = [];

  logger.log('[Audio] Mikrofon a MediaRecorder uvolneny');
}

function showAudioPreview(audioBlob) {
  const audioUrl = URL.createObjectURL(audioBlob);
  const previewPlayer = document.getElementById('audioPreviewPlayer');
  const previewContainer = document.getElementById('audioPreview');

  // Odstranit predchozi error handlery
  previewPlayer.onerror = null;
  previewPlayer.oncanplay = null;

  // Flag aby se error zobrazil jen jednou
  let errorShown = false;

  // Pridat error handler
  previewPlayer.onerror = function(e) {
    if (errorShown) return; // Zabranit opakovanemu zobrazeni
    errorShown = true;

    logger.log('[Audio] Chyba pri nacitani nahravky:', e);
    // Skryt preview a zobrazit tlacitko pro nahravani
    previewContainer.classList.add('hidden');
    document.getElementById('btnStartRecord').classList.remove('hidden');

    // Uvolnit blob URL
    if (previewPlayer.src) {
      URL.revokeObjectURL(previewPlayer.src);
      previewPlayer.src = '';
    }

    // Zobrazit info v console misto alertu
    logger.error('[Audio] Nahravka se nepodarila nacist');
  };

  previewPlayer.src = audioUrl;
  previewContainer.classList.remove('hidden');

  logger.log('[Audio] Nahled zobrazen, velikost:', Math.round(audioBlob.size / 1024), 'KB');
}

function deleteAudioPreview() {
  const recorder = window.wgsAudioRecorder;
  recorder.audioBlob = null;
  recorder.audioChunks = [];

  const previewPlayer = document.getElementById('audioPreviewPlayer');
  const previewContainer = document.getElementById('audioPreview');

  if (previewPlayer.src) {
    URL.revokeObjectURL(previewPlayer.src);
    previewPlayer.src = '';
  }

  previewContainer.classList.add('hidden');
  document.getElementById('btnStartRecord').classList.remove('hidden');

  logger.log('[Audio] Nahled smazan');
}

function formatDateTime(isoString) {
  const date = new Date(isoString);
  const now = new Date();
  const diff = now - date;

  if (diff < 60000) {
    return 'Právě teď';
  }

  if (diff < 3600000) {
    const mins = Math.floor(diff / 60000);
    return `Před ${mins} min`;
  }

  if (diff < 86400000) {
    const hours = Math.floor(diff / 3600000);
    return `Před ${hours} h`;
  }

  // Zkracene nazvy dnu v tydnu (cesky)
  const dny = ['ne', 'po', 'ut', 'st', 'ct', 'pa', 'so'];
  const den = dny[date.getDay()];
  const datum = date.getDate();
  const mesic = date.getMonth() + 1;
  const rok = date.getFullYear();
  const hodiny = date.getHours().toString().padStart(2, '0');
  const minuty = date.getMinutes().toString().padStart(2, '0');

  return `${den} ${datum}.${mesic}.${rok} ${hodiny}:${minuty}`;
}

// Vystavit vstupní bod pro lazy-loader v seznam.js (Step 168)
window._showNotes = showNotes;
