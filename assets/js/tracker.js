/**
 * WGS Analytics Tracker
 * Automatické sledování návštěvnosti stránek
 */

(function() {
    'use strict';

    // Generovat nebo načíst session ID
    function getSessionId() {
        let sessionId = sessionStorage.getItem('wgs_session_id');

        if (!sessionId) {
            sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substring(2, 15);
            sessionStorage.setItem('wgs_session_id', sessionId);
        }

        return sessionId;
    }

    // Získat informace o obrazovce
    function getScreenInfo() {
        return {
            width: screen.width,
            height: screen.height,
            resolution: `${screen.width}x${screen.height}`
        };
    }

    // Zaznamenat pageview
    function trackPageview() {
        try {
            const screenInfo = getScreenInfo();

            const data = {
                session_id: getSessionId(),
                page_url: window.location.pathname + window.location.search,
                page_title: document.title || '',
                referrer: document.referrer || '',
                screen_resolution: screenInfo.resolution,
                language: navigator.language || 'cs',
                timestamp: new Date().toISOString()
            };

            // Poslat data na server (beacon API - funguje i při unload)
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
                navigator.sendBeacon('/api/track_pageview.php', blob);
            } else {
                // Fallback pro starší prohlížeče
                fetch('/api/track_pageview.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data),
                    keepalive: true
                }).catch(err => {
                    console.warn('Analytics tracking failed:', err);
                });
            }

        } catch (error) {
            console.warn('Analytics tracking error:', error);
        }
    }

    // Sledovat dobu na stránce
    let pageLoadTime = Date.now();
    let lastActivityTime = Date.now();

    function updateActivity() {
        lastActivityTime = Date.now();
    }

    // Aktualizovat aktivitu při interakci
    ['click', 'scroll', 'keydown', 'mousemove'].forEach(event => {
        document.addEventListener(event, updateActivity, { passive: true, once: false });
    });

    // Zaznamenat při načtení stránky
    if (document.readyState === 'complete') {
        trackPageview();
    } else {
        window.addEventListener('load', trackPageview);
    }

    // Zaznamenat čas před odchodem
    window.addEventListener('beforeunload', function() {
        const timeOnPage = Math.floor((lastActivityTime - pageLoadTime) / 1000);

        // Pokud je čas rozumný (ne příliš krátký, ne příliš dlouhý)
        if (timeOnPage > 2 && timeOnPage < 3600) {
            const data = {
                session_id: getSessionId(),
                page_url: window.location.pathname,
                duration: timeOnPage,
                action: 'exit'
            };

            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
                navigator.sendBeacon('/api/track_pageview.php', blob);
            }
        }
    });

    console.log('WGS Analytics Tracker loaded');

})();
