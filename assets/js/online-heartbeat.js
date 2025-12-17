/**
 * Online Heartbeat - automaticka aktualizace online stavu
 *
 * Vola /api/heartbeat.php kazdych 30 sekund pro udrzeni
 * presneho online stavu uzivatele v admin panelu.
 *
 * Funguje i pro PWA - obchazi Service Worker cache.
 */

(function() {
    'use strict';

    // Konfigurace
    var HEARTBEAT_INTERVAL = 30000; // 30 sekund
    var API_ENDPOINT = '/api/heartbeat.php';

    // Stav
    var heartbeatTimer = null;
    var isRunning = false;

    /**
     * Odesle heartbeat na server
     */
    function sendHeartbeat() {
        // Pouzit POST aby se obchazela cache
        fetch(API_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.status === 'success') {
                // Heartbeat uspesny
                if (window.console && window.console.debug) {
                    console.debug('[Heartbeat] OK:', data.timestamp);
                }
            }
        })
        .catch(function(error) {
            // Ignorovat chyby - nechceme rusit uzivatele
            if (window.console && window.console.debug) {
                console.debug('[Heartbeat] Chyba:', error);
            }
        });
    }

    /**
     * Spusti heartbeat
     */
    function startHeartbeat() {
        if (isRunning) return;

        isRunning = true;

        // Odeslat prvni heartbeat s malym zpozdenim (predejiti Load failed pri nacteni stranky)
        setTimeout(sendHeartbeat, 500);

        // Nastavit interval
        heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);

        if (window.console && window.console.debug) {
            console.debug('[Heartbeat] Spusten (interval: ' + HEARTBEAT_INTERVAL + 'ms)');
        }
    }

    /**
     * Zastavi heartbeat
     */
    function stopHeartbeat() {
        if (!isRunning) return;

        isRunning = false;

        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }

        if (window.console && window.console.debug) {
            console.debug('[Heartbeat] Zastaven');
        }
    }

    /**
     * Inicializace pri nacteni stranky
     */
    function init() {
        // Spustit heartbeat
        startHeartbeat();

        // Zastavit pri opusteni stranky
        window.addEventListener('beforeunload', function() {
            stopHeartbeat();
        });

        // Pozastavit pri skryti stranky (tab na pozadi)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopHeartbeat();
            } else {
                startHeartbeat();
            }
        });
    }

    // Spustit po nacteni DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Exportovat pro externi pouziti
    window.OnlineHeartbeat = {
        start: startHeartbeat,
        stop: stopHeartbeat,
        ping: sendHeartbeat
    };

})();
