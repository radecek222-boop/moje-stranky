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
window.Utils.fetchCsrfToken = fetchCsrfToken;

// Expose escapeHtml globally for backwards compatibility
// (welcome-modal.js, admin.js, error-handler.js používají globální escapeHtml)
if (typeof window.escapeHtml === 'undefined') {
    window.escapeHtml = escapeHtml;
}

// Expose fetchCsrfToken globally for backwards compatibility
// (protokol-calculator-integration.js uses window.fetchCsrfToken)
window.fetchCsrfToken = fetchCsrfToken;
