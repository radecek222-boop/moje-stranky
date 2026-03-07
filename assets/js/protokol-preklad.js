/**
 * protokol-preklad.js
 * Automatický překlad polí protokolu (CS→EN, CS→IT) přes API
 * Závisí na: protokol.js (logger, wgsToast)
 */

async function translateTextApi(text, sourceLang = 'cs', targetLang = 'en') {
  if (!text || text.trim() === '') return '';

  try {
    // Použití server-side proxy místo přímého volání externího API
    const response = await fetch('api/translate_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        text: text,
        source: sourceLang,
        target: targetLang
      })
    });

    const data = await response.json();

    if (data.status === 'success' && data.translated) {
      return data.translated;
    }

    logger.warn('Překlad selhal:', data.message || 'Neznámá chyba');
    return '';
  } catch (err) {
    logger.error('Chyba překladu:', err);
    return '';
  }
}

// Wrapper funkce pro překlad mezi textovými poli
async function translateText(sourceId, targetId) {
  const sourceField = document.getElementById(sourceId);
  const targetField = document.getElementById(targetId);

  if (!sourceField || !targetField) {
    logger.error('Pole pro překlad nenalezeno:', sourceId, targetId);
    return;
  }

  const text = sourceField.value.trim();
  if (!text) {
    showNotification('Nejdříve napište text pro překlad', 'error');
    return;
  }

  // Najít tlačítko pro animaci
  const button = sourceField.parentElement.querySelector('.translate-btn');
  if (button) {
    button.classList.add('loading');
    button.disabled = true;
  }

  try {
    logger.log('[Sync] Překládám:', text.substring(0, 50) + '...');
    const translated = await translateTextApi(text, 'cs', 'en');

    if (translated) {
      targetField.value = translated;
      logger.log('Přeloženo:', translated.substring(0, 50) + '...');
      showNotification('Text přeložen', 'success');
    } else {
      showNotification('Překlad selhal', 'error');
    }
  } catch (err) {
    logger.error('Chyba při překladu:', err);
    showNotification('Chyba při překladu', 'error');
  } finally {
    if (button) {
      button.classList.remove('loading');
      button.disabled = false;
    }
  }
}

// Automatický překlad pro konkrétní pole
async function autoTranslateField(fieldId) {
  const field = document.getElementById(fieldId);
  if (!field) return;

  const text = field.value.trim();
  if (!text) return;

  logger.log('[Sync] Překládám pole:', fieldId);

  let enLabel = field.parentElement.querySelector('.en-label');

  if (!enLabel) {
    const container = field.closest('.input-group, .form-group, div');
    if (container) {
      enLabel = container.querySelector('.en-label');
    }
  }

  if (!enLabel) {
    logger.warn('En-label pro', fieldId, 'nenalezen');
    return;
  }

  const translated = await translateTextApi(text, 'cs', 'en');

  if (translated) {
    enLabel.textContent = translated;
    logger.log('Přeloženo:', fieldId, '->', translated.substring(0, 50) + '...');
  }
}

// Inicializace auto-překladu
function initAutoTranslation() {
  const fieldsToTranslate = [
    { source: 'description-cz', target: 'description-en' },
    { source: 'problem-cz', target: 'problem-en' },
    { source: 'repair-cz', target: 'repair-en' }
  ];

  fieldsToTranslate.forEach(({ source, target }) => {
    const sourceField = document.getElementById(source);
    if (!sourceField) {
      logger.warn('Auto-překlad: Pole nenalezeno:', source);
      return;
    }

    const debouncedTranslate = debounce(() => {
      translateText(source, target);
    }, 1500);

    sourceField.addEventListener('input', debouncedTranslate);

    sourceField.addEventListener('blur', () => {
      translateText(source, target);
    });

    logger.log('Auto-překlad aktivován pro:', source, '→', target);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAutoTranslation);
} else {
  initAutoTranslation();
}

logger.log('Automatický překlad aktivován');

async function translateField(fieldName, silent = false) {
  const czField = document.getElementById(fieldName + '-cz');
  const enField = document.getElementById(fieldName + '-en');
  if (!czField || !enField) return;
  const text = czField.value.trim();
  if (!text || text.length < 5) return;
  try {
    enField.value = 'Prekladam...';
    const response = await fetch('api/translate_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text: text, engine: 'mymemory' })
    });
    const result = await response.json();
    if (result.status === 'success') {
      enField.value = result.translated;
      logger.log('OK:', fieldName);
    } else {
      enField.value = '';
    }
  } catch (e) {
    logger.error('Err:', e);
    enField.value = '';
  }
}

window.addEventListener('load', () => {
  ['description', 'problem', 'repair'].forEach(f => {
    const el = document.getElementById(f + '-cz');
    if (!el) return;
    let t;
    el.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        if (el.value.trim().length > 10) translateField(f, true);
      }, 2000);
    });
  });
  logger.log('Translate ready');
});
