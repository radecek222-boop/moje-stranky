/**
 * Heatmap Tracker - Modul #6
 * Trackuje clicks a scroll depth pro heatmap vizualizaci
 * @version 1.1.0
 */

(function() {
    'use strict';

    // Config
    const CONFIG = {
        enabled: true,
        apiUrl: window.location.origin + '/api/track_heatmap.php',
        batchInterval: 5000,
        maxBatchSize: 50,
        scrollBuckets: [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
    };

    let clickBuffer = [];
    let scrollDepths = new Set();
    let csrfToken = null;
    let deviceType = null;

    // ===== CSRF TOKEN =====
    function getCSRFToken() {
        return (
            document.querySelector('meta[name="csrf-token"]')?.content ||
            document.querySelector('input[name="csrf_token"]')?.value ||
            window.csrfToken ||
            null
        );
    }

    // ===== DEVICE DETECTION =====
    function detectDeviceType() {
        const w = window.innerWidth;
        if (w >= 1024) return 'desktop';
        if (w >= 768) return 'tablet';
        return 'mobile';
    }

    // ===== CLICK TRACKING =====
    function trackClick(event) {
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        const x = event.clientX;
        const y = event.clientY + window.scrollY;

        clickBuffer.push({
            x: +( (x / vw) * 100 ).toFixed(2),
            y: +( (y / document.documentElement.scrollHeight) * 100 ).toFixed(2),
            viewport_width: vw,
            viewport_height: vh,
            timestamp: Date.now()
        });

        if (clickBuffer.length >= CONFIG.maxBatchSize) {
            sendData();
        }
    }

    // ===== SCROLL TRACKING =====
    let scrollTimeout = null;
    function trackScroll() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            const scrollTop = window.scrollY;
            const docHeight = Math.max(document.documentElement.scrollHeight - window.innerHeight, 0);
            const scrollPercent = docHeight ? (scrollTop / docHeight) * 100 : 0;

            const bucket = CONFIG.scrollBuckets.reduce((a, b) =>
                Math.abs(b - scrollPercent) < Math.abs(a - scrollPercent) ? b : a
            );

            scrollDepths.add(bucket);
        }, 100);
    }

    // ===== SEND DATA =====
    async function sendData() {
        if (clickBuffer.length === 0 && scrollDepths.size === 0) return;
        if (!csrfToken) return;

        const data = {
            page_url: window.location.href,
            device_type: deviceType,
            clicks: clickBuffer.splice(0),
            scrolls: Array.from(scrollDepths),
            csrf_token: csrfToken
        };

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

            if (!response.ok) {
                console.error('[Heatmap Tracker] API HTTP error:', response.status);
                console.error(await response.text());
                clickBuffer.unshift(...data.clicks);
                data.scrolls.forEach(s => scrollDepths.add(s));
                return;
            }

            const result = await response.json();
            if (result.status !== 'success') {
                console.error('[Heatmap Tracker] API error:', result.message);
            }

        } catch (err) {
            console.error('[Heatmap Tracker] Network error:', err);
            clickBuffer.unshift(...data.clicks);
            data.scrolls.forEach(s => scrollDepths.add(s));
        }
    }

    // ===== INIT =====
    function init() {
        if (!CONFIG.enabled) return;

        csrfToken = getCSRFToken();
        if (!csrfToken) return;

        deviceType = detectDeviceType();

        document.addEventListener('click', trackClick, true);
        window.addEventListener('scroll', trackScroll, { passive: true });

        setInterval(sendData, CONFIG.batchInterval);

        window.addEventListener('beforeunload', () => {
            if (clickBuffer.length || scrollDepths.size) {
                const data = {
                    page_url: window.location.href,
                    device_type: deviceType,
                    clicks: clickBuffer,
                    scrolls: Array.from(scrollDepths),
                    csrf_token: csrfToken
                };

                navigator.sendBeacon(
                    CONFIG.apiUrl,
                    new Blob([JSON.stringify(data)], { type: 'application/json' })
                );
            }
        });

        console.log('[Heatmap Tracker] Initialized - device:', deviceType);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
