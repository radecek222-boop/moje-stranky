/**
 * Tracker V2 - Pokročilé sledování relací a pageviews
 *
 * Tento modul orchestruje veškeré sledování analytiky:
 * - Správa relací (session management)
 * - Tracking pageviews
 * - Integrace s fingerprinting (Modul #1)
 * - UTM parametry
 * - Device detection
 * - Session heartbeat
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #2 - Advanced Session Tracking
 */

(function() {
    'use strict';

    // ========================================
    // KONFIGURACE
    // ========================================
    const CONFIG = {
        sessionTimeout: 30 * 60 * 1000, // 30 minut v ms
        heartbeatInterval: 30000, // 30 sekund
        apiEndpoint: '/api/track_v2.php',
        debug: false
    };

    // ========================================
    // GLOBÁLNÍ PROMĚNNÉ
    // ========================================
    let sessionId = null;
    let fingerprintId = null;
    let heartbeatIntervalId = null;

    // ========================================
    // INICIALIZACE
    // ========================================

    /**
     * Inicializuje tracking systém
     */
    async function inicializovatTracking() {
        try {
            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] Inicializuji tracking...');
            }

            // 1. Inicializovat relaci
            await inicializovatRelaci();

            // 2. Sledovat první pageview
            await sledovatPageview();

            // 3. Spustit heartbeat
            spustitHeartbeat();

            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] Tracking inicializován úspěšně');
                console.log('[WGS Analytics V2] Session ID:', sessionId);
                console.log('[WGS Analytics V2] Fingerprint ID:', fingerprintId);
            }

        } catch (error) {
            console.error('[WGS Analytics V2] Chyba při inicializaci:', error);
        }
    }

    // ========================================
    // SPRÁVA RELACÍ
    // ========================================

    /**
     * Inicializuje nebo obnoví relaci
     */
    async function inicializovatRelaci() {
        // Načíst timestamp poslední aktivity
        const posledniAktivita = localStorage.getItem('wgs_last_activity');
        const ted = Date.now();

        // Kontrola timeoutu
        if (posledniAktivita && (ted - parseInt(posledniAktivita)) < CONFIG.sessionTimeout) {
            // Relace je stále aktivní - znovu použít session_id
            sessionId = localStorage.getItem('wgs_session_id');

            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] Používám existující relaci:', sessionId);
            }
        } else {
            // Nová relace - vygenerovat nový session_id
            sessionId = 'sess_' + vygeneratNahodnyId();
            localStorage.setItem('wgs_session_id', sessionId);
            sessionStorage.setItem('wgs_session_start', ted);
            sessionStorage.setItem('wgs_entry_page', window.location.href);

            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] Vytvořena nová relace:', sessionId);
            }
        }

        // Aktualizovat timestamp poslední aktivity
        localStorage.setItem('wgs_last_activity', ted);

        // Načíst nebo vygenerovat fingerprint (Modul #1)
        fingerprintId = await ziskatNeboVytvoritFingerprint();
    }

    /**
     * Generuje náhodný ID řetězec
     *
     * @returns {string}
     */
    function vygeneratNahodnyId() {
        const timestamp = Date.now().toString(36);
        const randomPart = Math.random().toString(36).substring(2, 15);
        return timestamp + randomPart;
    }

    /**
     * Získá nebo vytvoří device fingerprint
     *
     * @returns {Promise<string>}
     */
    async function ziskatNeboVytvoritFingerprint() {
        // Kontrola, zda existuje FingerprintModule (Modul #1)
        if (typeof window.FingerprintModule !== 'undefined') {
            try {
                const result = await window.FingerprintModule.getOrGenerateFingerprint();
                return result.fingerprintId;
            } catch (error) {
                console.error('[WGS Analytics V2] Chyba při získání fingerprintu:', error);
                // Fallback - použít localStorage jako náhradní fingerprint
                return ziskatFallbackFingerprint();
            }
        } else {
            // FingerprintModule není dostupný - použít fallback
            if (CONFIG.debug) {
                console.warn('[WGS Analytics V2] FingerprintModule není dostupný, používám fallback');
            }
            return ziskatFallbackFingerprint();
        }
    }

    /**
     * Fallback fingerprint (když Modul #1 není dostupný)
     *
     * @returns {string}
     */
    function ziskatFallbackFingerprint() {
        let fallbackFp = localStorage.getItem('wgs_fallback_fingerprint');

        if (!fallbackFp) {
            fallbackFp = 'fp_fallback_' + vygeneratNahodnyId();
            localStorage.setItem('wgs_fallback_fingerprint', fallbackFp);
        }

        return fallbackFp;
    }

    // ========================================
    // TRACKING PAGEVIEWS
    // ========================================

    /**
     * Sleduje pageview
     */
    async function sledovatPageview() {
        try {
            const utmParams = extrahovatUtmParametry();
            const deviceInfo = ziskatDeviceInfo();
            const csrfToken = ziskatCsrfToken();

            const payload = {
                csrf_token: csrfToken,
                session_id: sessionId,
                fingerprint_id: fingerprintId,
                page_url: window.location.href,
                page_title: document.title,
                referrer: document.referrer || null,
                ...utmParams,
                ...deviceInfo
            };

            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] Odesílám pageview:', payload);
            }

            const odpoved = await fetch(CONFIG.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const vysledek = await odpoved.json();

            if (vysledek.status === 'success') {
                if (CONFIG.debug) {
                    console.log('[WGS Analytics V2] Pageview zaznamenán:', vysledek);
                }

                // Uložit engagement score pokud je dostupné
                if (vysledek.data && vysledek.data.session && vysledek.data.session.engagement_score) {
                    sessionStorage.setItem('wgs_engagement_score', vysledek.data.session.engagement_score);
                }
            } else {
                console.error('[WGS Analytics V2] Chyba při sledování pageview:', vysledek.message);
            }

        } catch (error) {
            console.error('[WGS Analytics V2] Síťová chyba při sledování pageview:', error);
        }
    }

    /**
     * Extrahuje UTM parametry z URL
     *
     * @returns {Object}
     */
    function extrahovatUtmParametry() {
        const params = new URLSearchParams(window.location.search);

        const utmParams = {
            utm_source: params.get('utm_source'),
            utm_medium: params.get('utm_medium'),
            utm_campaign: params.get('utm_campaign'),
            utm_term: params.get('utm_term'),
            utm_content: params.get('utm_content')
        };

        // Persistovat UTM params v localStorage (first-touch attribution)
        if (utmParams.utm_source) {
            localStorage.setItem('wgs_utm_params', JSON.stringify(utmParams));
        } else {
            // Použít uložené UTM params pokud nejsou nové v URL
            const ulozene = localStorage.getItem('wgs_utm_params');
            if (ulozene) {
                return JSON.parse(ulozene);
            }
        }

        return utmParams;
    }

    /**
     * Získá informace o zařízení
     *
     * @returns {Object}
     */
    function ziskatDeviceInfo() {
        return {
            device_type: detekceTypuZarizeni(),
            browser: detekceBrowseru(),
            os: detekceOS(),
            screen_width: screen.width,
            screen_height: screen.height,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            language: navigator.language
        };
    }

    /**
     * Detekce typu zařízení
     *
     * @returns {string} 'desktop' | 'mobile' | 'tablet'
     */
    function detekceTypuZarizeni() {
        const ua = navigator.userAgent.toLowerCase();

        if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
            return 'tablet';
        }

        if (/mobile|iphone|ipod|android|blackberry|opera mini|iemobile|wpdesktop/i.test(ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Detekce browseru
     *
     * @returns {string}
     */
    function detekceBrowseru() {
        const ua = navigator.userAgent;

        if (ua.indexOf('Firefox') > -1) {
            return 'Firefox';
        } else if (ua.indexOf('Edg') > -1) {
            return 'Edge';
        } else if (ua.indexOf('Chrome') > -1) {
            return 'Chrome';
        } else if (ua.indexOf('Safari') > -1) {
            return 'Safari';
        } else if (ua.indexOf('Opera') > -1 || ua.indexOf('OPR') > -1) {
            return 'Opera';
        } else if (ua.indexOf('Trident') > -1) {
            return 'Internet Explorer';
        }

        return 'Unknown';
    }

    /**
     * Detekce operačního systému
     *
     * @returns {string}
     */
    function detekceOS() {
        const ua = navigator.userAgent;

        if (ua.indexOf('Win') > -1) {
            return 'Windows';
        } else if (ua.indexOf('Mac') > -1) {
            return 'macOS';
        } else if (ua.indexOf('Linux') > -1) {
            return 'Linux';
        } else if (ua.indexOf('Android') > -1) {
            return 'Android';
        } else if (ua.indexOf('iOS') > -1 || ua.indexOf('iPhone') > -1 || ua.indexOf('iPad') > -1) {
            return 'iOS';
        }

        return 'Unknown';
    }

    /**
     * Získá CSRF token z meta tagu nebo generuje dočasný
     *
     * @returns {string}
     */
    function ziskatCsrfToken() {
        // Pokusit se najít CSRF token v meta tagu
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }

        // Pokusit se najít v hidden inputu
        const hiddenInput = document.querySelector('input[name="csrf_token"]');
        if (hiddenInput) {
            return hiddenInput.value;
        }

        // Fallback - prázdný string (server by měl mít alternativu)
        console.warn('[WGS Analytics V2] CSRF token nebyl nalezen');
        return '';
    }

    // ========================================
    // SESSION HEARTBEAT
    // ========================================

    /**
     * Spustí heartbeat pro udržování relace aktivní
     */
    function spustitHeartbeat() {
        heartbeatIntervalId = setInterval(() => {
            const ted = Date.now();
            localStorage.setItem('wgs_last_activity', ted);

            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] Heartbeat - aktivita aktualizována');
            }
        }, CONFIG.heartbeatInterval);

        if (CONFIG.debug) {
            console.log('[WGS Analytics V2] Heartbeat spuštěn (interval:', CONFIG.heartbeatInterval, 'ms)');
        }
    }

    /**
     * Zastaví heartbeat
     */
    function zastavitHeartbeat() {
        if (heartbeatIntervalId) {
            clearInterval(heartbeatIntervalId);
            heartbeatIntervalId = null;

            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] Heartbeat zastaven');
            }
        }
    }

    // ========================================
    // EVENT LISTENERS
    // ========================================

    /**
     * Listener pro opuštění stránky (visibility change)
     */
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Uživatel opustil stránku - aktualizovat timestamp
            localStorage.setItem('wgs_last_activity', Date.now());

            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] Stránka skryta - aktivita uložena');
            }
        }
    });

    /**
     * Listener pro beforeunload (zálohování dat před zavřením)
     */
    window.addEventListener('beforeunload', function() {
        // Uložit finální timestamp
        localStorage.setItem('wgs_last_activity', Date.now());

        // Zastavit heartbeat
        zastavitHeartbeat();
    });

    // ========================================
    // PUBLIC API
    // ========================================

    /**
     * Veřejné API pro externí použití
     */
    window.WgsTrackerV2 = {
        /**
         * Manuální track pageview (pokud je potřeba)
         */
        trackPageview: async function() {
            await sledovatPageview();
        },

        /**
         * Získat aktuální session ID
         */
        getSessionId: function() {
            return sessionId;
        },

        /**
         * Získat aktuální fingerprint ID
         */
        getFingerprintId: function() {
            return fingerprintId;
        },

        /**
         * Povolit/zakázat debug režim
         */
        setDebug: function(enabled) {
            CONFIG.debug = enabled;
        },

        /**
         * Získat engagement score aktuální relace
         */
        getEngagementScore: function() {
            return sessionStorage.getItem('wgs_engagement_score');
        }
    };

    // ========================================
    // AUTO-INICIALIZACE
    // ========================================

    /**
     * Spustí tracking po načtení DOM
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializovatTracking);
    } else {
        // DOM už je načtený
        inicializovatTracking();
    }

})();
