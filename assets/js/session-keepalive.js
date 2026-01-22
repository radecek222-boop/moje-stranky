/**
 * Session Keep-Alive pro protokol.php
 *
 * KRITICKÉ: Brání vypršení session při vyplňování protokolu
 * - Ping každých 5 minut (30min timeout / 6 = bezpečná rezerva)
 * - Automatický restart při selhání
 * - Vizuální indikátor pro debugging
 */

(function() {
    'use strict';

    const PING_INTERVAL = 5 * 60 * 1000; // 5 minut v milisekundách
    const ENDPOINT = '/api/session_keepalive.php';

    let intervalId = null;
    let failureCount = 0;
    const MAX_FAILURES = 3;

    /**
     * Ping session keep-alive endpoint
     */
    async function pingSession() {
        try {
            const response = await fetch(ENDPOINT, {
                method: 'GET',
                credentials: 'same-origin', // Důležité pro session cookies
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.status === 'success') {
                failureCount = 0; // Reset failure counter

                // Log úspěšného pingu (pouze v dev módu)
                if (typeof logger !== 'undefined') {
                    logger.log(`[Session Keep-Alive] ✓ Ping OK - Last activity: ${data.last_activity}`);
                } else {
                    console.log(`[Session Keep-Alive] ✓ Ping OK - Last activity: ${data.last_activity}`);
                }

                // Vizuální potvrzení (neonový toast)
                if (typeof WGSToast !== 'undefined') {
                    // Pouze každých 30 minut zobrazit toast
                    const now = Date.now();
                    const lastToast = localStorage.getItem('lastSessionPingToast');
                    if (!lastToast || (now - parseInt(lastToast)) > (30 * 60 * 1000)) {
                        WGSToast.zobrazit('Session aktivní', {
                            titulek: 'WGS',
                            trvani: 2000
                        });
                        localStorage.setItem('lastSessionPingToast', now.toString());
                    }
                }

            } else {
                // Session vypršela - přesměrovat na login
                handleSessionExpired(data.message);
            }

        } catch (error) {
            failureCount++;
            console.error(`[Session Keep-Alive] ❌ Chyba pingu (${failureCount}/${MAX_FAILURES}):`, error);

            if (failureCount >= MAX_FAILURES) {
                // Po 3 selháních přesměrovat na login
                handleSessionExpired('Session keep-alive selhal opakovaně');
            }
        }
    }

    /**
     * Handler pro vypršení session
     */
    function handleSessionExpired(message) {
        console.error('[Session Keep-Alive] Session vypršela:', message);

        // Zastavit interval
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }

        // Zobrazit varování
        if (typeof wgsToast !== 'undefined') {
            wgsToast.error('Vaše session vypršela. Budete přesměrováni na přihlášení.');
        } else if (typeof alert === 'function') {
            alert('Vaše session vypršela. Budete přesměrováni na přihlášení.');
        }

        // Přesměrovat na login s redirect zpět na protokol
        setTimeout(() => {
            const currentUrl = window.location.href;
            window.location.href = `login.php?redirect=${encodeURIComponent(currentUrl)}`;
        }, 2000);
    }

    /**
     * Spustit session keep-alive
     */
    function start() {
        // Okamžitý první ping
        pingSession();

        // Nastavit interval pro další pingy
        intervalId = setInterval(pingSession, PING_INTERVAL);

        console.log(`[Session Keep-Alive] ✓ Spuštěno - Ping každých ${PING_INTERVAL / 1000 / 60} minut`);

        // Ping také při focus okna (pokud uživatel přepíná mezi taby)
        window.addEventListener('focus', () => {
            console.log('[Session Keep-Alive] Window focus - okamžitý ping');
            pingSession();
        });

        // Ping před unloadem (zavření/refresh stránky)
        window.addEventListener('beforeunload', () => {
            // Synchronní ping pomocí sendBeacon (asynchronní fetch by byl zrušen)
            if (navigator.sendBeacon) {
                navigator.sendBeacon(ENDPOINT);
            }
        });
    }

    /**
     * Zastavit session keep-alive
     */
    function stop() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
            console.log('[Session Keep-Alive] ✓ Zastaveno');
        }
    }

    // Export do window pro globální použití
    window.SessionKeepAlive = {
        start: start,
        stop: stop,
        ping: pingSession
    };

    // Auto-start při načtení stránky
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

})();
