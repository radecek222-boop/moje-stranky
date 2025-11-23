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

    // Bot detection metrics
    let pageLoadStart = Date.now();
    let mouseMovements = [];
    let keyboardTimings = [];

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
            const botSignaly = sbiratBotDetectionSignaly();

            const payload = {
                csrf_token: csrfToken,
                session_id: sessionId,
                fingerprint_id: fingerprintId,
                page_url: window.location.href,
                page_title: document.title,
                referrer: document.referrer || null,
                ...utmParams,
                ...deviceInfo,
                bot_signals: botSignaly
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
     * Sbírá bot detection signály
     *
     * Detekuje:
     * - Webdriver (Selenium, Playwright, Puppeteer)
     * - Headless browser
     * - Automation tools (PhantomJS, Zombie)
     * - Mouse movement entropy
     * - Keyboard timing variance
     * - Pageview speed
     *
     * @returns {Object} Bot detection signály
     */
    function sbiratBotDetectionSignaly() {
        const signaly = {};

        // 1. Detekce WebDriver
        signaly.webdriver = !!(
            navigator.webdriver ||
            window.navigator.webdriver ||
            window.callPhantom ||
            window._phantom
        );

        // 2. Detekce Headless Chrome/Firefox
        signaly.headless = false;

        // Chrome headless detection
        if (navigator.userAgent.includes('HeadlessChrome')) {
            signaly.headless = true;
        }

        // Chrome headless API detection
        if (navigator.plugins && navigator.plugins.length === 0 && !navigator.userAgent.includes('Mobile')) {
            signaly.headless = true;
        }

        // Firefox headless detection
        if (navigator.userAgent.includes('Firefox') && !window.sidebar) {
            signaly.headless = true;
        }

        // 3. Detekce PhantomJS/Automation
        signaly.automation = !!(
            window.phantom ||
            window._phantom ||
            window.callPhantom ||
            window.Buffer ||
            (window.emit && window.spawn)
        );

        // 4. Pageview speed (čas od načtení stránky)
        const pageviewSpeed = Date.now() - pageLoadStart;
        signaly.pageview_speed_ms = pageviewSpeed;

        // 5. Mouse movement entropy (pokud je k dispozici)
        if (mouseMovements.length > 0) {
            signaly.mouse_movement_entropy = vypocitejMouseEntropii(mouseMovements);
        } else {
            signaly.mouse_movement_entropy = 0; // Žádný pohyb = suspektní
        }

        // 6. Keyboard timing variance (pokud je k dispozici)
        if (keyboardTimings.length > 1) {
            signaly.keyboard_timing_variance = vypocitejKeyboardVarianci(keyboardTimings);
        } else {
            signaly.keyboard_timing_variance = null;
        }

        // 7. Permissions API anomalie
        if (navigator.permissions) {
            signaly.permissions_available = true;
        }

        // 8. Battery API detection (často chybí v headless)
        if (!navigator.getBattery && !navigator.userAgent.includes('Mobile')) {
            signaly.no_battery_api = true;
        }

        return signaly;
    }

    /**
     * Výpočet entropie pohybu myši (0-1)
     *
     * Vysoká entropie = lidský pohyb (nepravidelný)
     * Nízká entropie = bot (lineární pohyb nebo žádný)
     *
     * @param {Array} movements Pole [{x, y, timestamp}]
     * @returns {number} 0-1
     */
    function vypocitejMouseEntropii(movements) {
        if (movements.length < 5) {
            return 0; // Nedostatek dat
        }

        // Výpočet směrových změn
        let smerovaZmena = 0;
        let predchoziUhel = null;

        for (let i = 1; i < movements.length; i++) {
            const dx = movements[i].x - movements[i - 1].x;
            const dy = movements[i].y - movements[i - 1].y;

            const uhel = Math.atan2(dy, dx);

            if (predchoziUhel !== null) {
                const zmena = Math.abs(uhel - predchoziUhel);
                smerovaZmena += zmena;
            }

            predchoziUhel = uhel;
        }

        // Normalizace na 0-1
        const maxZmena = (movements.length - 2) * Math.PI;
        const entropie = Math.min(smerovaZmena / maxZmena, 1);

        return parseFloat(entropie.toFixed(2));
    }

    /**
     * Výpočet variance klávesnicových timingů (0-1)
     *
     * Nízká variance = bot (pravidelné intervaly)
     * Vysoká variance = člověk (nepravidelné)
     *
     * @param {Array} timings Pole timestampů
     * @returns {number} 0-1
     */
    function vypocitejKeyboardVarianci(timings) {
        if (timings.length < 2) {
            return 0;
        }

        // Výpočet intervalů
        const intervaly = [];
        for (let i = 1; i < timings.length; i++) {
            intervaly.push(timings[i] - timings[i - 1]);
        }

        // Průměrný interval
        const prumer = intervaly.reduce((a, b) => a + b, 0) / intervaly.length;

        // Variance
        const variance = intervaly.reduce((sum, interval) => {
            return sum + Math.pow(interval - prumer, 2);
        }, 0) / intervaly.length;

        // Normalizace (předpokládejme max variance 10000ms²)
        const normalizedVariance = Math.min(variance / 10000, 1);

        return parseFloat(normalizedVariance.toFixed(2));
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

        // Pokud jsou nové UTM parametry v URL
        const maUtmParametry = utmParams.utm_source || utmParams.utm_medium || utmParams.utm_campaign;

        if (maUtmParametry) {
            // ========================================
            // FIRST-CLICK ATTRIBUTION (localStorage)
            // ========================================
            // Uložit první kampaň (pokud ještě není uložena)
            const firstClick = localStorage.getItem('wgs_utm_first_click');
            if (!firstClick) {
                localStorage.setItem('wgs_utm_first_click', JSON.stringify({
                    ...utmParams,
                    timestamp: Date.now()
                }));
            }

            // ========================================
            // LAST-CLICK ATTRIBUTION (sessionStorage)
            // ========================================
            // Uložit aktuální kampaň pro current session
            sessionStorage.setItem('wgs_utm_last_click', JSON.stringify({
                ...utmParams,
                timestamp: Date.now()
            }));

            // ========================================
            // LINEAR ATTRIBUTION - Conversion Path Tracking
            // ========================================
            // Sledovat conversion path (všechny kampaně v historii uživatele)
            let conversionPath = [];
            const existingPath = localStorage.getItem('wgs_utm_conversion_path');

            if (existingPath) {
                try {
                    conversionPath = JSON.parse(existingPath);
                } catch (e) {
                    conversionPath = [];
                }
            }

            // Přidat current kampaň do path (pokud už tam není duplicit)
            const kampanKey = `${utmParams.utm_source}|${utmParams.utm_medium}|${utmParams.utm_campaign}`;
            const existujeVPath = conversionPath.some(item =>
                `${item.utm_source}|${item.utm_medium}|${item.utm_campaign}` === kampanKey
            );

            if (!existujeVPath) {
                conversionPath.push({
                    ...utmParams,
                    timestamp: Date.now()
                });

                // Limit na 10 kampaní v path
                if (conversionPath.length > 10) {
                    conversionPath = conversionPath.slice(-10);
                }

                localStorage.setItem('wgs_utm_conversion_path', JSON.stringify(conversionPath));
            }

            if (CONFIG.debug) {
                console.log('[WGS Analytics V2] UTM Parameters detected:', utmParams);
                console.log('[WGS Analytics V2] Conversion Path length:', conversionPath.length);
            }

            return utmParams;

        } else {
            // ========================================
            // FALLBACK: Použít uložené UTM params
            // ========================================

            // Priorita: sessionStorage (last-click) > localStorage (first-click)
            const lastClick = sessionStorage.getItem('wgs_utm_last_click');
            if (lastClick) {
                try {
                    const parsed = JSON.parse(lastClick);
                    return {
                        utm_source: parsed.utm_source,
                        utm_medium: parsed.utm_medium,
                        utm_campaign: parsed.utm_campaign,
                        utm_term: parsed.utm_term,
                        utm_content: parsed.utm_content
                    };
                } catch (e) {
                    // Ignore parse error
                }
            }

            const firstClick = localStorage.getItem('wgs_utm_first_click');
            if (firstClick) {
                try {
                    const parsed = JSON.parse(firstClick);
                    return {
                        utm_source: parsed.utm_source,
                        utm_medium: parsed.utm_medium,
                        utm_campaign: parsed.utm_campaign,
                        utm_term: parsed.utm_term,
                        utm_content: parsed.utm_content
                    };
                } catch (e) {
                    // Ignore parse error
                }
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
    // BOT DETECTION - EVENT LISTENERS
    // ========================================

    /**
     * Trackování pohybu myši pro bot detection
     */
    document.addEventListener('mousemove', function(e) {
        // Throttle - pouze každý 100ms
        if (mouseMovements.length === 0 || Date.now() - mouseMovements[mouseMovements.length - 1].timestamp > 100) {
            mouseMovements.push({
                x: e.clientX,
                y: e.clientY,
                timestamp: Date.now()
            });

            // Omezit velikost pole (max 50 záznamů)
            if (mouseMovements.length > 50) {
                mouseMovements.shift();
            }
        }
    });

    /**
     * Trackování klávesnice pro bot detection
     */
    document.addEventListener('keydown', function() {
        keyboardTimings.push(Date.now());

        // Omezit velikost pole (max 30 záznamů)
        if (keyboardTimings.length > 30) {
            keyboardTimings.shift();
        }
    });

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
        },

        /**
         * Zaznamenat konverzi (Modul #9)
         *
         * @param {string} conversionType - Typ konverze (form_submit, login, contact, purchase, etc.)
         * @param {string|null} conversionLabel - Volitelný popis konverze
         * @param {number} conversionValue - Hodnota konverze v Kč (0 pokud není)
         * @param {Object|null} metadata - Volitelná custom data (JSON objekt)
         * @returns {Promise<Object>} API response
         *
         * @example
         * WgsTrackerV2.trackConversion('purchase', 'Product Purchase', 1250, {
         *     product_id: 123,
         *     quantity: 2
         * });
         */
        trackConversion: async function(conversionType, conversionLabel = null, conversionValue = 0, metadata = null) {
            try {
                if (!sessionId) {
                    throw new Error('Session ID není k dispozici. Tracker ještě není inicializován.');
                }

                if (!conversionType) {
                    throw new Error('Conversion type je povinný parametr.');
                }

                const csrfToken = ziskatCsrfToken();
                if (!csrfToken) {
                    throw new Error('CSRF token není k dispozici.');
                }

                const payload = {
                    session_id: sessionId,
                    conversion_type: conversionType,
                    conversion_label: conversionLabel,
                    conversion_value: parseFloat(conversionValue) || 0,
                    metadata: metadata ? JSON.stringify(metadata) : null,
                    csrf_token: csrfToken
                };

                if (CONFIG.debug) {
                    console.log('[WGS Analytics V2] Tracking conversion:', payload);
                }

                const odpoved = await fetch('/api/track_conversion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams(payload)
                });

                const vysledek = await odpoved.json();

                if (vysledek.status === 'success') {
                    if (CONFIG.debug) {
                        console.log('[WGS Analytics V2] Conversion tracked successfully:', vysledek.data);
                    }
                    return vysledek;
                } else {
                    console.error('[WGS Analytics V2] Chyba při trackování konverze:', vysledek.message);
                    throw new Error(vysledek.message);
                }

            } catch (error) {
                console.error('[WGS Analytics V2] Chyba při trackování konverze:', error);
                throw error;
            }
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

    // ========================================
    // EVENT TRACKING (Modul #5)
    // ========================================

    /**
     * Inicializace Event Trackeru po inicializaci hlavního trackingu
     */
    function inicializovatEventTracker() {
        if (typeof EventTracker === 'undefined') {
            console.warn('[WGS Analytics V2] EventTracker není načten, přeskakuji event tracking.');
            return;
        }

        // Počkat na inicializaci session a fingerprint ID
        if (!sessionId || !fingerprintId) {
            console.warn('[WGS Analytics V2] Session ID nebo Fingerprint ID není k dispozici, opakuji za 1s...');
            setTimeout(inicializovatEventTracker, 1000);
            return;
        }

        console.log('[WGS Analytics V2] Inicializuji Event Tracker...');

        EventTracker.init({
            sessionId: sessionId,
            fingerprintId: fingerprintId,
            csrfToken: csrfToken,
            apiEndpoint: '/api/track_event.php',
            batchSize: 50,
            sendInterval: 5000,
            trackClicks: true,
            trackScroll: true,
            trackRageClicks: true,
            trackCopyPaste: true,
            trackFormInteractions: true,
            trackIdleState: true,
            scrollDebounce: 500,
            idleTimeout: 30000
        });

        console.log('[WGS Analytics V2] Event Tracker inicializován');
    }

    // Spustit Event Tracker po inicializaci trackingu (s malým delay)
    setTimeout(inicializovatEventTracker, 2000);

    // ========================================
    // SESSION REPLAY (Modul #7)
    // ========================================

    /**
     * Inicializace Session Replay Recorder
     */
    function inicializovatReplayRecorder() {
        if (typeof ReplayRecorder === 'undefined') {
            console.warn('[WGS Analytics V2] ReplayRecorder není načten, přeskakuji session replay.');
            return;
        }

        // Počkat na inicializaci session ID
        if (!sessionId) {
            console.warn('[WGS Analytics V2] Session ID není k dispozici, opakuji za 1s...');
            setTimeout(inicializovatReplayRecorder, 1000);
            return;
        }

        // Určit page index (počet pageviews v current session)
        const pageIndex = parseInt(sessionStorage.getItem('wgs_page_index') || '0');

        console.log('[WGS Analytics V2] Inicializuji Replay Recorder (page_index: ' + pageIndex + ')...');

        ReplayRecorder.init({
            sessionId: sessionId,
            pageUrl: window.location.href,
            pageIndex: pageIndex,
            csrfToken: csrfToken,
            recordMouseMove: true,
            recordClicks: true,
            recordScroll: true,
            recordResize: true,
            mouseMoveThrottle: 100,
            scrollThrottle: 150,
            maxBatchSize: 50,
            batchInterval: 30000,
            apiEndpoint: '/api/track_replay.php'
        });

        // Increment page index pro další stránku
        sessionStorage.setItem('wgs_page_index', pageIndex + 1);

        console.log('[WGS Analytics V2] Replay Recorder inicializován');
    }

    // Spustit Replay Recorder po inicializaci trackingu (s delay po Event Trackeru)
    setTimeout(inicializovatReplayRecorder, 2500);

})();
