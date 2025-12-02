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
 * Format date to Czech locale
 * @param {string|Date} date - Date to format
 * @returns {string} - Formatted date or '—' if invalid
 */
function formatDateCZ(date) {
    if (!date) return '—';

    try {
        const dateObj = typeof date === 'string' ? new Date(date) : date;
        if (isNaN(dateObj.getTime())) return '—';
        return dateObj.toLocaleDateString('cs-CZ');
    } catch (e) {
        return '—';
    }
}

/**
 * Format datetime to Czech locale with time
 * @param {string|Date} date - Date to format
 * @returns {string} - Formatted datetime or '—' if invalid
 */
function formatDateTimeCZ(date) {
    if (!date) return '—';

    try {
        const dateObj = typeof date === 'string' ? new Date(date) : date;
        if (isNaN(dateObj.getTime())) return '—';
        return dateObj.toLocaleString('cs-CZ');
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
