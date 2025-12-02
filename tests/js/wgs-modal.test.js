/**
 * wgsConfirm & wgsToast Tests
 * Step 155: JavaScript testy pro vlastní modal/toast systém
 */

// === wgsConfirm implementace pro testování ===
function wgsConfirm(message, okText = 'OK', cancelText = 'Zrušit') {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'wgs-confirm-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'wgs-confirm-message');

    const dialog = document.createElement('div');
    dialog.className = 'wgs-confirm-dialog';

    const messageEl = document.createElement('p');
    messageEl.id = 'wgs-confirm-message';
    messageEl.className = 'wgs-confirm-message';
    messageEl.textContent = message;

    const buttons = document.createElement('div');
    buttons.className = 'wgs-confirm-buttons';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'wgs-confirm-btn wgs-confirm-cancel';
    cancelBtn.textContent = cancelText;

    const okBtn = document.createElement('button');
    okBtn.type = 'button';
    okBtn.className = 'wgs-confirm-btn wgs-confirm-ok';
    okBtn.textContent = okText;

    buttons.appendChild(cancelBtn);
    buttons.appendChild(okBtn);
    dialog.appendChild(messageEl);
    dialog.appendChild(buttons);
    overlay.appendChild(dialog);

    function cleanup(result) {
      document.removeEventListener('keydown', handleKeydown);
      overlay.remove();
      resolve(result);
    }

    function handleKeydown(e) {
      if (e.key === 'Escape') {
        cleanup(false);
      } else if (e.key === 'Enter') {
        cleanup(true);
      }
    }

    okBtn.addEventListener('click', () => cleanup(true));
    cancelBtn.addEventListener('click', () => cleanup(false));
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) cleanup(false);
    });
    document.addEventListener('keydown', handleKeydown);

    document.body.appendChild(overlay);
    okBtn.focus();
  });
}

// === wgsToast implementace pro testování ===
function wgsToast(message, type = 'info', duration = 3000) {
  let container = document.getElementById('wgs-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'wgs-toast-container';
    container.className = 'wgs-toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `wgs-toast wgs-toast-${type}`;
  toast.setAttribute('role', 'alert');
  toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

  const messageEl = document.createElement('span');
  messageEl.className = 'wgs-toast-message';
  messageEl.textContent = message;

  const closeBtn = document.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'wgs-toast-close';
  closeBtn.innerHTML = '&times;';
  closeBtn.setAttribute('aria-label', 'Zavřít');

  toast.appendChild(messageEl);
  toast.appendChild(closeBtn);
  container.appendChild(toast);

  // Simulace animace
  toast.classList.add('wgs-toast-visible');

  function closeToast() {
    toast.classList.remove('wgs-toast-visible');
    toast.classList.add('wgs-toast-hiding');
    toast.remove();
  }

  closeBtn.addEventListener('click', closeToast);

  if (duration > 0) {
    setTimeout(closeToast, duration);
  }

  return toast;
}

// ========================================
// TESTY PRO wgsConfirm
// ========================================

describe('wgsConfirm', () => {
  afterEach(() => {
    // Vyčistit všechny modaly
    document.querySelectorAll('.wgs-confirm-overlay').forEach(el => el.remove());
  });

  test('vytvoří overlay s dialogem', async () => {
    const promise = wgsConfirm('Test zpráva');

    const overlay = document.querySelector('.wgs-confirm-overlay');
    const dialog = document.querySelector('.wgs-confirm-dialog');
    const message = document.querySelector('.wgs-confirm-message');

    expect(overlay).toBeTruthy();
    expect(dialog).toBeTruthy();
    expect(message).toBeTruthy();
    expect(message.textContent).toBe('Test zpráva');

    // Cleanup
    document.querySelector('.wgs-confirm-ok').click();
    await promise;
  });

  test('má správné ARIA atributy', async () => {
    const promise = wgsConfirm('Test');

    const overlay = document.querySelector('.wgs-confirm-overlay');

    expect(overlay.getAttribute('role')).toBe('dialog');
    expect(overlay.getAttribute('aria-modal')).toBe('true');
    expect(overlay.getAttribute('aria-labelledby')).toBe('wgs-confirm-message');

    document.querySelector('.wgs-confirm-ok').click();
    await promise;
  });

  test('zobrazí vlastní texty tlačítek', async () => {
    const promise = wgsConfirm('Smazat?', 'Smazat', 'Zrušit');

    const okBtn = document.querySelector('.wgs-confirm-ok');
    const cancelBtn = document.querySelector('.wgs-confirm-cancel');

    expect(okBtn.textContent).toBe('Smazat');
    expect(cancelBtn.textContent).toBe('Zrušit');

    okBtn.click();
    await promise;
  });

  test('vrací true při kliknutí na OK', async () => {
    const promise = wgsConfirm('Test');

    document.querySelector('.wgs-confirm-ok').click();

    const result = await promise;
    expect(result).toBe(true);
  });

  test('vrací false při kliknutí na Cancel', async () => {
    const promise = wgsConfirm('Test');

    document.querySelector('.wgs-confirm-cancel').click();

    const result = await promise;
    expect(result).toBe(false);
  });

  test('vrací false při kliknutí na overlay', async () => {
    const promise = wgsConfirm('Test');

    const overlay = document.querySelector('.wgs-confirm-overlay');
    // Simulovat klik na overlay (ne na dialog)
    const clickEvent = new MouseEvent('click', { bubbles: true });
    Object.defineProperty(clickEvent, 'target', { value: overlay });
    overlay.dispatchEvent(clickEvent);

    const result = await promise;
    expect(result).toBe(false);
  });

  test('vrací false při stisknutí Escape', async () => {
    const promise = wgsConfirm('Test');

    const escEvent = new KeyboardEvent('keydown', { key: 'Escape' });
    document.dispatchEvent(escEvent);

    const result = await promise;
    expect(result).toBe(false);
  });

  test('vrací true při stisknutí Enter', async () => {
    const promise = wgsConfirm('Test');

    const enterEvent = new KeyboardEvent('keydown', { key: 'Enter' });
    document.dispatchEvent(enterEvent);

    const result = await promise;
    expect(result).toBe(true);
  });

  test('odstraní overlay po zavření', async () => {
    const promise = wgsConfirm('Test');

    document.querySelector('.wgs-confirm-ok').click();
    await promise;

    const overlay = document.querySelector('.wgs-confirm-overlay');
    expect(overlay).toBeNull();
  });

  test('fokusuje OK tlačítko při otevření', async () => {
    const promise = wgsConfirm('Test');

    const okBtn = document.querySelector('.wgs-confirm-ok');
    expect(document.activeElement).toBe(okBtn);

    okBtn.click();
    await promise;
  });

  test('používá výchozí texty tlačítek', async () => {
    const promise = wgsConfirm('Test');

    const okBtn = document.querySelector('.wgs-confirm-ok');
    const cancelBtn = document.querySelector('.wgs-confirm-cancel');

    expect(okBtn.textContent).toBe('OK');
    expect(cancelBtn.textContent).toBe('Zrušit');

    okBtn.click();
    await promise;
  });
});

// ========================================
// TESTY PRO wgsToast
// ========================================

describe('wgsToast', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
    // Vyčistit toasty
    const container = document.getElementById('wgs-toast-container');
    if (container) container.remove();
  });

  test('vytvoří toast kontejner pokud neexistuje', () => {
    wgsToast('Test');

    const container = document.getElementById('wgs-toast-container');
    expect(container).toBeTruthy();
    expect(container.className).toBe('wgs-toast-container');
  });

  test('znovu použije existující kontejner', () => {
    wgsToast('První');
    wgsToast('Druhý');

    const containers = document.querySelectorAll('#wgs-toast-container');
    expect(containers.length).toBe(1);
  });

  test('vytvoří toast s správnou zprávou', () => {
    wgsToast('Testovací zpráva');

    const message = document.querySelector('.wgs-toast-message');
    expect(message.textContent).toBe('Testovací zpráva');
  });

  test('přidá správnou CSS třídu pro typ info', () => {
    wgsToast('Info zpráva', 'info');

    const toast = document.querySelector('.wgs-toast');
    expect(toast.classList.contains('wgs-toast-info')).toBe(true);
  });

  test('přidá správnou CSS třídu pro typ success', () => {
    wgsToast('Úspěch', 'success');

    const toast = document.querySelector('.wgs-toast-success');
    expect(toast).toBeTruthy();
  });

  test('přidá správnou CSS třídu pro typ error', () => {
    wgsToast('Chyba', 'error');

    const toast = document.querySelector('.wgs-toast-error');
    expect(toast).toBeTruthy();
  });

  test('přidá správnou CSS třídu pro typ warning', () => {
    wgsToast('Varování', 'warning');

    const toast = document.querySelector('.wgs-toast-warning');
    expect(toast).toBeTruthy();
  });

  test('má ARIA role="alert"', () => {
    wgsToast('Test');

    const toast = document.querySelector('.wgs-toast');
    expect(toast.getAttribute('role')).toBe('alert');
  });

  test('má aria-live="assertive" pro error', () => {
    wgsToast('Chyba', 'error');

    const toast = document.querySelector('.wgs-toast');
    expect(toast.getAttribute('aria-live')).toBe('assertive');
  });

  test('má aria-live="polite" pro ostatní typy', () => {
    wgsToast('Info', 'info');

    const toast = document.querySelector('.wgs-toast');
    expect(toast.getAttribute('aria-live')).toBe('polite');
  });

  test('automaticky zmizí po výchozí době (3s)', () => {
    wgsToast('Test');

    expect(document.querySelector('.wgs-toast')).toBeTruthy();

    jest.advanceTimersByTime(3000);

    expect(document.querySelector('.wgs-toast')).toBeNull();
  });

  test('automaticky zmizí po vlastní době', () => {
    wgsToast('Test', 'info', 5000);

    expect(document.querySelector('.wgs-toast')).toBeTruthy();

    jest.advanceTimersByTime(3000);
    expect(document.querySelector('.wgs-toast')).toBeTruthy();

    jest.advanceTimersByTime(2000);
    expect(document.querySelector('.wgs-toast')).toBeNull();
  });

  test('nezmizí automaticky když duration=0', () => {
    wgsToast('Permanentní', 'info', 0);

    jest.advanceTimersByTime(10000);

    expect(document.querySelector('.wgs-toast')).toBeTruthy();
  });

  test('má zavírací tlačítko', () => {
    wgsToast('Test');

    const closeBtn = document.querySelector('.wgs-toast-close');
    expect(closeBtn).toBeTruthy();
    expect(closeBtn.getAttribute('aria-label')).toBe('Zavřít');
  });

  test('zavírací tlačítko odstraní toast', () => {
    wgsToast('Test');

    const closeBtn = document.querySelector('.wgs-toast-close');
    closeBtn.click();

    expect(document.querySelector('.wgs-toast')).toBeNull();
  });

  test('přidá visible class', () => {
    wgsToast('Test');

    const toast = document.querySelector('.wgs-toast');
    expect(toast.classList.contains('wgs-toast-visible')).toBe(true);
  });

  test('podporuje více toastů najednou', () => {
    wgsToast('První');
    wgsToast('Druhý');
    wgsToast('Třetí');

    const toasts = document.querySelectorAll('.wgs-toast');
    expect(toasts.length).toBe(3);
  });

  test('vrací toast element', () => {
    const toast = wgsToast('Test');

    expect(toast).toBeInstanceOf(HTMLElement);
    expect(toast.classList.contains('wgs-toast')).toBe(true);
  });
});

// ========================================
// INTEGRAČNÍ TESTY
// ========================================

describe('wgsConfirm + wgsToast integrace', () => {
  afterEach(() => {
    document.querySelectorAll('.wgs-confirm-overlay').forEach(el => el.remove());
    const container = document.getElementById('wgs-toast-container');
    if (container) container.remove();
  });

  test('typický flow: confirm -> akce -> toast', async () => {
    const promise = wgsConfirm('Smazat položku?', 'Smazat', 'Zrušit');

    // Uživatel potvrdí
    document.querySelector('.wgs-confirm-ok').click();
    const confirmed = await promise;

    expect(confirmed).toBe(true);

    // Zobrazit úspěch toast
    if (confirmed) {
      wgsToast('Položka byla smazána', 'success');
    }

    const toast = document.querySelector('.wgs-toast-success');
    expect(toast).toBeTruthy();
    expect(toast.querySelector('.wgs-toast-message').textContent).toBe('Položka byla smazána');
  });

  test('cancel flow: confirm zrušeno -> žádná akce', async () => {
    const promise = wgsConfirm('Smazat položku?');

    // Uživatel zruší
    document.querySelector('.wgs-confirm-cancel').click();
    const confirmed = await promise;

    expect(confirmed).toBe(false);

    // Žádný toast by neměl být zobrazen
    const toast = document.querySelector('.wgs-toast');
    expect(toast).toBeNull();
  });
});
