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
    const HEARTBEAT_INTERVAL = 30000; // 30 sekund
    const API_ENDPOINT = '/api/heartbeat.php';

    // Stav
    let heartbeatTimer = null;
    let isRunning = false;

    /**
     * Odesle heartbeat na server
     */
    function sendHeartbeat() {
        // CSRF token - poskytnut csrf-auto-inject.js pri nacteni stranky
        const csrfToken = window.csrfTokenCache || '';
        if (!csrfToken) {
            // Token jeste neni dostupny - preskocit tento cyklus
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        // Pouzit POST aby se obchazela cache
        fetch(API_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            // Heartbeat uspesny
        })
        .catch(function(error) {
            // Ignorovat chyby - nechceme rusit uzivatele
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
