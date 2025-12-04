/**
 * WGS Service - Logger Utility
 *
 * Production-safe logging wrapper that:
 * - Disables debug logs in production
 * - Always shows errors
 * - Allows manual override with window.DEBUG_MODE
 *
 * Usage:
 * - Replace: console.log(...)  with: logger.log(...)
 * - Replace: console.error(...) with: logger.error(...)
 * - Replace: console.warn(...)  with: logger.warn(...)
 *
 * Debug mode can be enabled by:
 * - Running on localhost
 * - Setting window.DEBUG_MODE = true in browser console
 */

(function() {
    'use strict';

    /**
     * Check if debug mode is enabled
     * @returns {boolean}
     */
    function isDebugMode() {
        // Enable debug on localhost
        if (window.location.hostname === 'localhost' ||
            window.location.hostname === '127.0.0.1') {
            return true;
        }

        // Allow manual override
        if (window.DEBUG_MODE === true) {
            return true;
        }

        // Disable in production
        return false;
    }

    // V produkci potlacit vsechny console.log a console.info
    // (console.error a console.warn zustanou aktivni)
    if (!isDebugMode()) {
        const noop = function() {};
        window.console.log = noop;
        window.console.info = noop;
        // console.error a console.warn ponechame aktivni
    }

    /**
     * Logger utility object
     */
    window.logger = {
        /**
         * Debug logging - only in development
         * @param {...*} args - Arguments to log
         */
        log: function(...args) {
            if (isDebugMode()) {
                console.log(...args);
            }
        },

        /**
         * Error logging - always enabled
         * @param {...*} args - Arguments to log
         */
        error: function(...args) {
            console.error(...args);
        },

        /**
         * Warning logging - only in development
         * @param {...*} args - Arguments to log
         */
        warn: function(...args) {
            if (isDebugMode()) {
                console.warn(...args);
            }
        },

        /**
         * Info logging - only in development
         * @param {...*} args - Arguments to log
         */
        info: function(...args) {
            if (isDebugMode()) {
                console.info(...args);
            }
        },

        /**
         * Table logging - only in development
         * @param {*} data - Data to display in table
         */
        table: function(data) {
            if (isDebugMode() && console.table) {
                console.table(data);
            }
        },

        /**
         * Check if debug mode is active
         * @returns {boolean}
         */
        isDebug: isDebugMode
    };

    // Inicializace bez logu (logy jsou potlaceny v produkci)
})();