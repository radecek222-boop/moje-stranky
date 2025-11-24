/**
 * Heatmap Tracker - Modul #6
 * Trackuje clicks a scroll depth pro heatmap vizualizaci
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Config
    const CONFIG = {
        apiUrl: '/api/track_heatmap.php',
        batchInterval: 5000, // Posílat data každých 5 sekund
        maxBatchSize: 50,    // Max 50 událostí v jednom batchi
        scrollBuckets: [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
    };

    // Data buffer
    let clickBuffer = [];
    let scrollDepths = new Set();
    let maxScrollDepth = 0;
    let csrfToken = null;
    let deviceType = null;

    // ========================================
    // CSRF TOKEN
    // ========================================
    function getCSRFToken() {
        // Zkusit meta tag
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.content;
        }

        // Zkusit hidden input
        const hiddenInput = document.querySelector('input[name="csrf_token"]');
        if (hiddenInput) {
            return hiddenInput.value;
        }

        // Zkusit z window
        if (typeof window.csrfToken !== 'undefined') {
            return window.csrfToken;
        }

        return null;
    }

    // ========================================
    // DEVICE DETECTION
    // ========================================
    function detectDeviceType() {
        const width = window.innerWidth;

        if (width >= 1024) {
            return 'desktop';
        } else if (width >= 768) {
            return 'tablet';
        } else {
            return 'mobile';
        }
    }

    // ========================================
    // CLICK TRACKING
    // ========================================
    function trackClick(event) {
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        const clickX = event.clientX;
        const clickY = event.clientY + window.scrollY; // Přidat scroll offset

        const clickXPercent = (clickX / viewportWidth) * 100;
        const clickYPercent = (clickY / document.documentElement.scrollHeight) * 100;

        clickBuffer.push({
            x: Math.round(clickXPercent * 100) / 100,
            y: Math.round(clickYPercent * 100) / 100,
            viewport_width: viewportWidth,
            viewport_height: viewportHeight,
            timestamp: Date.now()
        });

        // Auto-send pokud buffer je plný
        if (clickBuffer.length >= CONFIG.maxBatchSize) {
            sendData();
        }
    }

    // ========================================
    // SCROLL TRACKING
    // ========================================
    let scrollTimeout = null;
    function trackScroll() {
        if (scrollTimeout) {
            clearTimeout(scrollTimeout);
        }

        scrollTimeout = setTimeout(() => {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrollPercent = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;

            // Zaokrouhlit na nearest bucket
            const bucket = CONFIG.scrollBuckets.reduce((prev, curr) => {
                return Math.abs(curr - scrollPercent) < Math.abs(prev - scrollPercent) ? curr : prev;
            });

            scrollDepths.add(bucket);

            if (scrollPercent > maxScrollDepth) {
                maxScrollDepth = scrollPercent;
            }
        }, 100); // Debounce 100ms
    }

    // ========================================
    // SEND DATA TO API
    // ========================================
    async function sendData() {
        if (clickBuffer.length === 0 && scrollDepths.size === 0) {
            return;
        }

        if (!csrfToken) {
            console.warn('[Heatmap Tracker] CSRF token not found - skipping send');
            return;
        }

        const data = {
            page_url: window.location.href,
            device_type: deviceType,
            clicks: clickBuffer.splice(0), // Vyprázdnit buffer
            scroll_depths: Array.from(scrollDepths), // OPRAVA: API očekává scroll_depths, ne scrolls
            csrf_token: csrfToken
        };

        // Vyčistit scrollDepths po odeslání
        scrollDepths.clear();

        try {
            const response = await fetch(CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.status !== 'success') {
                console.error('[Heatmap Tracker] API error:', result.message);
            }

        } catch (error) {
            console.error('[Heatmap Tracker] Network error:', error);
            // Vrátit data zpět do bufferu
            clickBuffer.unshift(...data.clicks);
            data.scroll_depths.forEach(s => scrollDepths.add(s)); // OPRAVA: používat scroll_depths
        }
    }

    // ========================================
    // INITIALIZATION
    // ========================================
    function init() {
        // Získat CSRF token
        csrfToken = getCSRFToken();
        if (!csrfToken) {
            console.warn('[Heatmap Tracker] CSRF token not found - tracking disabled');
            return;
        }

        // Detekovat device type
        deviceType = detectDeviceType();

        // Event listeners
        document.addEventListener('click', trackClick, true);
        window.addEventListener('scroll', trackScroll, { passive: true });

        // Batch interval
        setInterval(sendData, CONFIG.batchInterval);

        // Send před unload
        window.addEventListener('beforeunload', () => {
            if (clickBuffer.length > 0 || scrollDepths.size > 0) {
                // Použít sendBeacon pro spolehlivé odeslání
                const data = {
                    page_url: window.location.href,
                    device_type: deviceType,
                    clicks: clickBuffer,
                    scrolls: Array.from(scrollDepths),
                    csrf_token: csrfToken
                };

                navigator.sendBeacon(CONFIG.apiUrl, JSON.stringify(data));
            }
        });

        console.log('[Heatmap Tracker] Initialized - device:', deviceType);
    }

    // Auto-init při DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
