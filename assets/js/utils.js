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

// Export for module usage (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        isSuccess,
        safeFetchJson,
        safeSetIframeSrc,
        escapeHtml,
        getCSRFTokenFromMeta,
        formatDateCZ,
        formatDateTimeCZ
    };
}
