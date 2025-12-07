/**
 * Global Utility Functions
 * Centralized helpers for API responses, data validation, and common operations
 */

/**
 * Check if API response indicates success
 * Handles both {success: true} and {status: 'success'} formats
 * @param {Object} data - API response data
 * @returns {boolean} - true if response indicates success
 */
function isSuccess(data) {
    return !!(data && (data.success === true || data.status === 'success'));
}

/**
 * Safe fetch with automatic response.ok checking and JSON parsing
 * @param {string} url - URL to fetch
 * @param {Object} options - Fetch options
 * @returns {Promise<Object>} - Parsed JSON response
 * @throws {Error} - If response is not ok or JSON parsing fails
 */
async function safeFetchJson(url, options = {}) {
    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options
        });

        if (!response.ok) {
            // Try to parse error message from JSON response
            let errorMsg = `HTTP ${response.status}: ${response.statusText}`;
            try {
                const errorData = await response.json();
                errorMsg = errorData.message || errorData.error || errorMsg;
            } catch (e) {
                // JSON parse failed, use default message
            }
            throw new Error(errorMsg);
        }

        return await response.json();
    } catch (error) {
        // Re-throw with context
        throw error;
    }
}

/**
 * Safely set iframe src with data-loaded tracking to prevent unnecessary reloads
 * @param {HTMLIFrameElement} iframe - Iframe element
 * @param {string} url - URL to load
 */
function safeSetIframeSrc(iframe, url) {
    if (!iframe) {
        console.error('[Utils] safeSetIframeSrc: iframe element not found');
        return;
    }

    const currentSrc = iframe.dataset.src || '';

    // Only set src if it's different from the current one
    if (currentSrc !== url) {
        iframe.src = url;
        iframe.dataset.src = url;
        iframe.dataset.loaded = 'true';
    }
}

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} text - Text to escape
 * @returns {string} - Escaped HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Get CSRF token from meta tag
 * @returns {string|null} - CSRF token or null if not found
 */
function getCSRFTokenFromMeta() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (!metaTag) {
        console.error('[Utils] CSRF token meta tag not found!');
        return null;
    }
    return metaTag.getAttribute('content');
}

/**
 * Format date to Czech locale with abbreviated day name
 * @param {string|Date} date - Date to format
 * @returns {string} - Formatted date like "st 23.1.2025" or '—' if invalid
 */
function formatDateCZ(date) {
    if (!date) return '—';

    // Zkracene nazvy dnu v tydnu (cesky)
    const dny = ['ne', 'po', 'ut', 'st', 'ct', 'pa', 'so'];

    try {
        const dateObj = typeof date === 'string' ? new Date(date) : date;
        if (isNaN(dateObj.getTime())) return '—';

        const den = dny[dateObj.getDay()];
        const datum = dateObj.getDate();
        const mesic = dateObj.getMonth() + 1;
        const rok = dateObj.getFullYear();

        return `${den} ${datum}.${mesic}.${rok}`;
    } catch (e) {
        return '—';
    }
}

/**
 * Format datetime to Czech locale with abbreviated day name and time
 * @param {string|Date} date - Date to format
 * @returns {string} - Formatted datetime like "st 23.1.2025 14:30" or '—' if invalid
 */
function formatDateTimeCZ(date) {
    if (!date) return '—';

    // Zkracene nazvy dnu v tydnu (cesky)
    const dny = ['ne', 'po', 'ut', 'st', 'ct', 'pa', 'so'];

    try {
        const dateObj = typeof date === 'string' ? new Date(date) : date;
        if (isNaN(dateObj.getTime())) return '—';

        const den = dny[dateObj.getDay()];
        const datum = dateObj.getDate();
        const mesic = dateObj.getMonth() + 1;
        const rok = dateObj.getFullYear();
        const hodiny = dateObj.getHours().toString().padStart(2, '0');
        const minuty = dateObj.getMinutes().toString().padStart(2, '0');

        return `${den} ${datum}.${mesic}.${rok} ${hodiny}:${minuty}`;
    } catch (e) {
        return '—';
    }
}

/**
 * Escape special regex characters in a string
 * @param {string} string - String to escape
 * @returns {string} - Regex-safe string
 */
function escapeRegex(string) {
    if (!string) return '';
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Highlight search query in text with HTML span
 * @param {string} text - Text to highlight in
 * @param {string} query - Query to highlight
 * @returns {string} - HTML with highlighted matches
 */
function highlightText(text, query) {
    if (!query || !text) return escapeHtml(text);

    // SECURITY: Escape HTML BEFORE highlighting
    const escapedText = escapeHtml(text);
    const escapedQuery = escapeRegex(query);

    const regex = new RegExp(`(${escapedQuery})`, 'gi');
    return escapedText.replace(regex, '<span class="highlight">$1</span>');
}

/**
 * Debounce funkce - odloží volání funkce dokud neuplyne čekací doba
 * @param {Function} func - Funkce k debounce
 * @param {number} wait - Čekací doba v ms
 * @returns {Function} - Debounced funkce
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export for module usage (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        isSuccess,
        safeFetchJson,
        safeSetIframeSrc,
        escapeHtml,
        getCSRFTokenFromMeta,
        formatDateCZ,
        formatDateTimeCZ,
        escapeRegex,
        highlightText,
        debounce,
        fetchCsrfToken
    };
}

/**
 * Fetch CSRF token from available sources
 * Tries getCSRFToken(), getCSRFTokenFromMeta(), then meta tag directly
 * @returns {Promise<string>} - CSRF token
 * @throws {Error} - If no token is available
 */
async function fetchCsrfToken() {
    // Try global getCSRFToken function first
    if (typeof getCSRFToken === 'function') {
        try {
            const token = await getCSRFToken();
            if (token) {
                return token;
            }
        } catch (err) {
            if (typeof logger !== 'undefined' && logger?.warn) {
                logger.warn('CSRF token z getCSRFToken selhal:', err);
            }
        }
    }

    // Try getCSRFTokenFromMeta
    if (typeof getCSRFTokenFromMeta === 'function') {
        const metaToken = getCSRFTokenFromMeta();
        if (metaToken) {
            return metaToken;
        }
    }

    // Fallback: read directly from meta tag
    const fallbackMeta = document.querySelector('meta[name="csrf-token"]');
    if (fallbackMeta) {
        const token = fallbackMeta.getAttribute('content');
        if (token) {
            window.csrfTokenCache = token;
            return token;
        }
    }

    throw new Error('CSRF token není k dispozici. Obnovte stránku a zkuste to znovu.');
}

// Global Utils object for browser usage
window.Utils = window.Utils || {};
window.Utils.escapeHtml = escapeHtml;
window.Utils.escapeRegex = escapeRegex;
window.Utils.highlightText = highlightText;
window.Utils.debounce = debounce;
window.Utils.fetchCsrfToken = fetchCsrfToken;

// Expose debounce globally for backwards compatibility
// (protokol.js používá globální debounce)
if (typeof window.debounce === 'undefined') {
    window.debounce = debounce;
}

// Expose escapeHtml globally for backwards compatibility
// (welcome-modal.js, admin.js, error-handler.js používají globální escapeHtml)
if (typeof window.escapeHtml === 'undefined') {
    window.escapeHtml = escapeHtml;
}

// Expose fetchCsrfToken globally for backwards compatibility
// (protokol-calculator-integration.js uses window.fetchCsrfToken)
window.fetchCsrfToken = fetchCsrfToken;

/**
 * Event Delegation System - Centralizovaná správa click eventů
 * Umožňuje nahradit inline onclick handlery data atributy
 *
 * Použití v HTML:
 *   <button data-action="save" data-id="123">Uložit</button>
 *   <a data-href="/seznam.php">Seznam</a>
 *
 * Registrace handleru:
 *   Utils.registerAction('save', (element, data) => { ... });
 *
 * @author Claude Code
 * @version 1.0.0
 */
const ActionRegistry = {
    handlers: {},

    /**
     * Registrovat handler pro akci
     * @param {string} actionName - Název akce (data-action hodnota)
     * @param {Function} handler - Handler funkce (element, dataset) => void
     */
    register(actionName, handler) {
        this.handlers[actionName] = handler;
    },

    /**
     * Spustit handler pro akci
     * @param {string} actionName - Název akce
     * @param {HTMLElement} element - Element který vyvolal akci
     * @returns {boolean} - true pokud handler existoval a byl spuštěn
     */
    execute(actionName, element) {
        const handler = this.handlers[actionName];
        if (handler) {
            handler(element, element.dataset);
            return true;
        }
        return false;
    }
};

/**
 * Inicializovat event delegation pro celý dokument
 * Volá se automaticky při načtení DOM
 */
function initEventDelegation() {
    // Click event delegation
    document.addEventListener('click', function(event) {
        const target = event.target.closest('[data-action]');
        if (target) {
            const action = target.dataset.action;

            // Podpora pro zastavení propagace
            if (target.dataset.stopPropagation === 'true') {
                event.stopPropagation();
            }

            if (ActionRegistry.execute(action, target)) {
                event.preventDefault();
            }
        }

        // Navigace pomocí data-href
        const hrefTarget = event.target.closest('[data-href]');
        if (hrefTarget && !event.target.closest('a')) {
            event.preventDefault();
            const href = hrefTarget.dataset.href;
            if (href) {
                window.location.href = href;
            }
        }
    });

    // Change event delegation (pro select, input, checkbox, radio)
    document.addEventListener('change', function(event) {
        const target = event.target.closest('[data-action]');
        if (target) {
            const action = target.dataset.action;
            ActionRegistry.execute(action, target);
        }
    });
}

// Auto-init při DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEventDelegation);
} else {
    initEventDelegation();
}

// Export pro použití
window.Utils.ActionRegistry = ActionRegistry;
window.Utils.registerAction = ActionRegistry.register.bind(ActionRegistry);

/**
 * Vlastní potvrzovací dialog nahrazující window.confirm()
 * Vrací Promise<boolean> - true = potvrzeno, false = zrušeno
 *
 * Použití (nový formát s options):
 *   const confirmed = await wgsConfirm('Opravdu smazat?', {
 *     titulek: 'Smazat záznam',
 *     btnPotvrdit: 'Smazat',
 *     btnZrusit: 'Zrušit',
 *     nebezpecne: true  // Červené tlačítko pro destruktivní akce
 *   });
 *
 * Použití (starý formát - zpětná kompatibilita):
 *   const confirmed = await wgsConfirm('Smazat položku?', 'Smazat', 'Zrušit');
 *
 * @param {string} zprava - Zpráva k zobrazení
 * @param {Object|string} [optionsOrOkText={}] - Options objekt nebo text OK tlačítka (zpětná kompatibilita)
 * @param {string} [cancelTextLegacy] - Text Cancel tlačítka (pouze pro zpětnou kompatibilitu)
 * @returns {Promise<boolean>} - true pokud uživatel potvrdil
 */
function wgsConfirm(zprava, optionsOrOkText = {}, cancelTextLegacy) {
    return new Promise((resolve) => {
        // Zpětná kompatibilita: wgsConfirm('zprava', 'OK text', 'Cancel text')
        let options = {};
        if (typeof optionsOrOkText === 'string') {
            options = {
                btnPotvrdit: optionsOrOkText,
                btnZrusit: cancelTextLegacy || 'Zrušit'
            };
        } else {
            options = optionsOrOkText;
        }

        const {
            titulek = 'Potvrzení',
            btnZrusit = 'Zrušit',
            nebezpecne = false
        } = options;

        // Výchozí text tlačítka: "Smazat" pro nebezpečné akce, jinak "Potvrdit"
        const btnPotvrdit = options.btnPotvrdit || (nebezpecne ? 'Smazat' : 'Potvrdit');

        // Odstranit existující modal
        const existujici = document.getElementById('wgsConfirmModal');
        if (existujici) existujici.remove();

        // Vytvořit overlay
        const modal = document.createElement('div');
        modal.id = 'wgsConfirmModal';
        modal.className = 'wgs-confirm-overlay';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'wgs-confirm-title');
        modal.style.cssText = `
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7); display: flex;
            align-items: center; justify-content: center; z-index: 10001;
        `;

        // Styl tlačítka potvrzení
        const btnPotvrditStyle = nebezpecne
            ? 'background: #dc3545; color: #fff;'
            : 'background: #fff; color: #000;';

        // Vytvořit dialog HTML
        modal.innerHTML = `
            <div class="wgs-confirm-dialog" style="background: #1a1a1a; padding: 25px; border-radius: 12px;
                        max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                        border: 1px solid #333; text-align: center; font-family: 'Poppins', sans-serif;">
                <h3 id="wgs-confirm-title" style="margin: 0 0 15px 0; color: #fff; font-size: 1.1rem; font-weight: 600;">${escapeHtml(titulek)}</h3>
                <p id="wgs-confirm-message" class="wgs-confirm-message" style="margin: 0 0 20px 0; color: #ccc; font-size: 0.95rem; line-height: 1.5;">${escapeHtml(zprava)}</p>
                <div class="wgs-confirm-buttons" style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" id="wgsConfirmBtnZrusit" class="wgs-confirm-btn wgs-confirm-cancel"
                            style="padding: 10px 20px; border: 1px solid #444; border-radius: 6px;
                                   background: transparent; color: #ccc; cursor: pointer; font-size: 0.9rem;">${escapeHtml(btnZrusit)}</button>
                    <button type="button" id="wgsConfirmBtnPotvrdit" class="wgs-confirm-btn wgs-confirm-ok"
                            style="padding: 10px 20px; border: none; border-radius: 6px; ${btnPotvrditStyle}
                                   cursor: pointer; font-size: 0.9rem; font-weight: 500;">${escapeHtml(btnPotvrdit)}</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Cleanup funkce
        const zavrit = (vysledek) => {
            modal.remove();
            document.removeEventListener('keydown', escHandler);
            resolve(vysledek);
        };

        // Event listenery
        document.getElementById('wgsConfirmBtnZrusit')?.addEventListener('click', () => zavrit(false));
        document.getElementById('wgsConfirmBtnPotvrdit')?.addEventListener('click', () => zavrit(true));
        modal.addEventListener('click', (e) => { if (e.target === modal) zavrit(false); });

        // Keyboard handler
        const escHandler = (e) => { if (e.key === 'Escape') zavrit(false); };
        document.addEventListener('keydown', escHandler);

        // Focus na tlačítko zrušit (bezpečnější default)
        document.getElementById('wgsConfirmBtnZrusit')?.focus();
    });
}

// Export wgsConfirm
window.wgsConfirm = wgsConfirm;
window.Utils.wgsConfirm = wgsConfirm;

/**
 * Toast notifikační systém nahrazující window.alert()
 * Zobrazí neinvazivní notifikaci v dolní části obrazovky
 *
 * Použití:
 *   wgsToast('Uloženo');                          // Info toast
 *   wgsToast('Úspěšně uloženo', 'success');      // Success toast
 *   wgsToast('Chyba při ukládání', 'error');     // Error toast
 *   wgsToast('Pozor!', 'warning', 5000);         // Warning, 5s
 *
 * @param {string} message - Zpráva k zobrazení
 * @param {string} [type='info'] - Typ: 'success', 'error', 'warning', 'info'
 * @param {number} [duration=3000] - Doba zobrazení v ms (0 = nezmizí automaticky)
 */
function wgsToast(message, type = 'info', duration = 3000) {
    // Zajistit kontejner pro toasty
    let container = document.getElementById('wgs-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'wgs-toast-container';
        container.className = 'wgs-toast-container';
        document.body.appendChild(container);
    }

    // Vytvořit toast element
    const toast = document.createElement('div');
    toast.className = `wgs-toast wgs-toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

    // Obsah toastu
    const messageEl = document.createElement('span');
    messageEl.className = 'wgs-toast-message';
    messageEl.textContent = message;

    // Zavírací tlačítko
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'wgs-toast-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label', 'Zavřít');

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);

    // Přidat do kontejneru
    container.appendChild(toast);

    // Animace vstupu
    requestAnimationFrame(() => {
        toast.classList.add('wgs-toast-visible');
    });

    // Funkce pro zavření
    function closeToast() {
        toast.classList.remove('wgs-toast-visible');
        toast.classList.add('wgs-toast-hiding');
        setTimeout(() => {
            toast.remove();
            // Odstranit kontejner pokud je prázdný
            if (container && container.children.length === 0) {
                container.remove();
            }
        }, 300);
    }

    // Event listener pro zavření
    closeBtn.addEventListener('click', closeToast);

    // Auto-dismiss
    if (duration > 0) {
        setTimeout(closeToast, duration);
    }

    return toast;
}

// Zkratky pro různé typy toastů
wgsToast.success = (msg, duration) => wgsToast(msg, 'success', duration);
wgsToast.error = (msg, duration) => wgsToast(msg, 'error', duration || 5000);
wgsToast.warning = (msg, duration) => wgsToast(msg, 'warning', duration || 4000);
wgsToast.info = (msg, duration) => wgsToast(msg, 'info', duration);

// Export wgsToast
window.wgsToast = wgsToast;
window.Utils.wgsToast = wgsToast;

// ========================================
// HELPER: toBase64 - Convert Blob to Base64
// Step 134: Centralized from photocustomer.js, protokol.js
// ========================================
function toBase64(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}

// Export toBase64
window.toBase64 = toBase64;
window.Utils.toBase64 = toBase64;

// ========================================
// HELPER: formatNumber - Format number with Czech locale
// Step 134: Centralized from analytics.js, psa-kalkulator.js
// ========================================
function formatNumber(num) {
    if (num === null || num === undefined) return '0';
    return new Intl.NumberFormat('cs-CZ').format(num);
}

// Export formatNumber
window.formatNumber = formatNumber;
window.Utils.formatNumber = formatNumber;

// ========================================
// GLOBALNI AKCE - Bezne data-action handlery
// Tyto akce fungují na všech stránkách
// ========================================

// Navigace zpět v historii
window.goBack = function() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '/';
    }
};
window.historyBack = window.goBack; // Alias

// Reload stránky
window.reloadPage = function() {
    window.location.reload();
};

// Tisk stránky
window.printPage = function() {
    window.print();
};

// Přesměrování na login
window.presmerujNaLogin = function() {
    window.location.href = '/login.php';
};

// Registrace do ActionRegistry pro data-action atributy
if (window.Utils && window.Utils.ActionRegistry) {
    window.Utils.ActionRegistry.register('goBack', window.goBack);
    window.Utils.ActionRegistry.register('historyBack', window.goBack);
    window.Utils.ActionRegistry.register('reloadPage', window.reloadPage);
    window.Utils.ActionRegistry.register('printPage', window.printPage);
    window.Utils.ActionRegistry.register('presmerujNaLogin', window.presmerujNaLogin);
}
