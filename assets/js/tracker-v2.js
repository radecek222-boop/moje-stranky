/**
 * WGS ENTERPRISE ANALYTICS TRACKER V2
 *
 * Features:
 * - Device Fingerprinting (canvas, WebGL, audio)
 * - Advanced Session Tracking
 * - Event Tracking (clicks, scroll, rage, copy/paste)
 * - Session Replay Recording
 * - Bot Detection Signals
 * - UTM Campaign Tracking
 * - Conversion Tracking
 * - Real-time Updates
 * - GDPR Compliance
 */

(function() {
    'use strict';

    // === CONFIGURATION ===
    const CONFIG = {
        apiEndpoint: '/api/track_v2.php',
        replayEndpoint: '/api/track_replay.php',
        eventEndpoint: '/api/track_event.php',
        heatmapEndpoint: '/api/track_heatmap.php',
        sessionUpdateInterval: 10000, // 10 seconds
        replayFrameInterval: 200, // 200ms
        mouseMoveThrottle: 100, // 100ms
        scrollThrottle: 150, // 150ms
        rageClickThreshold: 3, // clicks in 1 second
        rageClickWindow: 1000, // 1 second window
        idleTimeout: 30000, // 30 seconds
        maxScrollDepth: 0,
        gdprConsent: true // Set to false to require explicit consent
    };

    // === GLOBAL STATE ===
    const STATE = {
        sessionId: null,
        fingerprintId: null,
        entryPage: window.location.pathname,
        entryTime: Date.now(),
        lastActivityTime: Date.now(),
        isIdle: false,
        mouseDistance: 0,
        lastMouseX: 0,
        lastMouseY: 0,
        clickCount: 0,
        scrollDepth: 0,
        maxScrollDepth: 0,
        events: [],
        replayFrames: [],
        pageLoadTime: Date.now(),
        rageClicks: [],
        utmParams: null,
        conversionTracked: false,
        isBot: false,
        botScore: 0
    };

    // === GDPR COMPLIANCE ===
    function checkGDPRConsent() {
        // Check for consent cookie or localStorage
        const consent = localStorage.getItem('wgs_analytics_consent');
        if (CONFIG.gdprConsent || consent === 'granted') {
            return true;
        }
        return false;
    }

    // === SESSION MANAGEMENT ===
    function getOrCreateSessionId() {
        let sessionId = sessionStorage.getItem('wgs_session_id');

        if (!sessionId) {
            sessionId = 'sess_' + Date.now() + '_' + generateRandomString(12);
            sessionStorage.setItem('wgs_session_id', sessionId);
            sessionStorage.setItem('wgs_session_start', Date.now());
        }

        return sessionId;
    }

    function generateRandomString(length) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    // === FINGERPRINTING ENGINE ===
    async function generateFingerprint() {
        try {
            const components = {
                canvas: await getCanvasFingerprint(),
                webgl: getWebGLFingerprint(),
                audio: await getAudioFingerprint(),
                screen: getScreenFingerprint(),
                timezone: getTimezoneFingerprint(),
                fonts: getFontFingerprint(),
                plugins: getPluginFingerprint(),
                hardware: getHardwareFingerprint()
            };

            // Combine all components into a hash
            const fingerprintString = JSON.stringify(components);
            const fingerprintId = await hashString(fingerprintString);

            return {
                fingerprintId: fingerprintId,
                components: components
            };
        } catch (error) {
            console.warn('Fingerprinting error:', error);
            return {
                fingerprintId: 'fp_' + Date.now() + '_' + generateRandomString(8),
                components: {}
            };
        }
    }

    async function getCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 50;
            const ctx = canvas.getContext('2d');

            // Draw text with various styles
            ctx.textBaseline = 'top';
            ctx.font = '14px "Arial"';
            ctx.textBaseline = 'alphabetic';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('Canvas Fingerprint 🔒', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('Canvas Fingerprint 🔒', 4, 17);

            const dataUrl = canvas.toDataURL();
            return await hashString(dataUrl);
        } catch (error) {
            return null;
        }
    }

    function getWebGLFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');

            if (!gl) return null;

            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');

            return {
                vendor: debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR),
                renderer: debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER)
            };
        } catch (error) {
            return null;
        }
    }

    async function getAudioFingerprint() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return null;

            const context = new AudioContext();
            const oscillator = context.createOscillator();
            const analyser = context.createAnalyser();
            const gainNode = context.createGain();
            const scriptProcessor = context.createScriptProcessor(4096, 1, 1);

            gainNode.gain.value = 0; // Mute
            oscillator.type = 'triangle';
            oscillator.connect(analyser);
            analyser.connect(scriptProcessor);
            scriptProcessor.connect(gainNode);
            gainNode.connect(context.destination);
            oscillator.start(0);

            return new Promise((resolve) => {
                scriptProcessor.onaudioprocess = function(event) {
                    const output = event.outputBuffer.getChannelData(0);
                    const sum = Array.from(output).reduce((a, b) => a + Math.abs(b), 0);
                    oscillator.stop();
                    context.close();
                    hashString(sum.toString()).then(resolve);
                };
            });
        } catch (error) {
            return null;
        }
    }

    function getScreenFingerprint() {
        return {
            width: screen.width,
            height: screen.height,
            colorDepth: screen.colorDepth,
            pixelRatio: window.devicePixelRatio || 1,
            availWidth: screen.availWidth,
            availHeight: screen.availHeight
        };
    }

    function getTimezoneFingerprint() {
        return {
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            offset: new Date().getTimezoneOffset()
        };
    }

    function getFontFingerprint() {
        // Simplified font detection
        const testFonts = ['Arial', 'Verdana', 'Times New Roman', 'Courier New', 'Georgia'];
        const availableFonts = testFonts.filter(font => {
            return document.fonts.check('12px "' + font + '"');
        });
        return availableFonts.join(',');
    }

    function getPluginFingerprint() {
        if (!navigator.plugins) return null;
        const plugins = Array.from(navigator.plugins).map(p => p.name).sort().join(',');
        return plugins;
    }

    function getHardwareFingerprint() {
        return {
            concurrency: navigator.hardwareConcurrency || 0,
            deviceMemory: navigator.deviceMemory || 0,
            platform: navigator.platform,
            touchSupport: 'ontouchstart' in window || navigator.maxTouchPoints > 0
        };
    }

    async function hashString(str) {
        const encoder = new TextEncoder();
        const data = encoder.encode(str);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        return hashHex.substring(0, 32); // First 32 chars
    }

    // === BOT DETECTION ===
    function detectBotSignals() {
        const signals = [];
        let score = 0;

        // Check user agent
        const ua = navigator.userAgent.toLowerCase();
        const botPatterns = ['bot', 'crawler', 'spider', 'scraper', 'headless', 'phantom', 'selenium'];

        for (const pattern of botPatterns) {
            if (ua.includes(pattern)) {
                signals.push('user_agent_' + pattern);
                score += 40;
            }
        }

        // Check for headless browsers
        if (navigator.webdriver) {
            signals.push('webdriver_detected');
            score += 50;
        }

        // Check for missing features
        if (!navigator.plugins || navigator.plugins.length === 0) {
            signals.push('no_plugins');
            score += 10;
        }

        // Check for suspicious properties
        if (window.phantom || window._phantom || window.callPhantom) {
            signals.push('phantom_detected');
            score += 60;
        }

        if (window.Buffer) {
            signals.push('nodejs_buffer');
            score += 20;
        }

        // Check for normal mouse movement
        if (STATE.mouseDistance === 0 && Date.now() - STATE.entryTime > 5000) {
            signals.push('no_mouse_movement');
            score += 15;
        }

        // Check for 0 scroll
        if (STATE.maxScrollDepth === 0 && Date.now() - STATE.entryTime > 10000) {
            signals.push('no_scroll');
            score += 10;
        }

        STATE.botScore = Math.min(100, score);
        STATE.isBot = score > 50;

        return {
            isBot: STATE.isBot,
            score: STATE.botScore,
            signals: signals
        };
    }

    // === UTM TRACKING ===
    function parseUTMParams() {
        const urlParams = new URLSearchParams(window.location.search);

        const utmParams = {
            source: urlParams.get('utm_source') || null,
            medium: urlParams.get('utm_medium') || null,
            campaign: urlParams.get('utm_campaign') || null,
            content: urlParams.get('utm_content') || null,
            term: urlParams.get('utm_term') || null
        };

        // Store in session for attribution
        if (Object.values(utmParams).some(v => v !== null)) {
            sessionStorage.setItem('wgs_utm_params', JSON.stringify(utmParams));
        }

        return utmParams;
    }

    // === SCROLL TRACKING ===
    function calculateScrollDepth() {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        const scrollPercent = Math.min(100, Math.round((scrollTop + windowHeight) / documentHeight * 100));

        STATE.scrollDepth = scrollPercent;
        STATE.maxScrollDepth = Math.max(STATE.maxScrollDepth, scrollPercent);

        return scrollPercent;
    }

    // === MOUSE TRACKING ===
    function trackMouseMovement(x, y) {
        if (STATE.lastMouseX > 0 && STATE.lastMouseY > 0) {
            const dx = x - STATE.lastMouseX;
            const dy = y - STATE.lastMouseY;
            const distance = Math.sqrt(dx * dx + dy * dy);
            STATE.mouseDistance += distance;
        }

        STATE.lastMouseX = x;
        STATE.lastMouseY = y;
        updateActivity();
    }

    // === RAGE CLICK DETECTION ===
    function detectRageClick(x, y) {
        const now = Date.now();

        // Add click to rage tracker
        STATE.rageClicks.push({ x, y, time: now });

        // Remove old clicks outside window
        STATE.rageClicks = STATE.rageClicks.filter(click =>
            now - click.time < CONFIG.rageClickWindow
        );

        // Check for rage click (3+ clicks in same area within 1 second)
        if (STATE.rageClicks.length >= CONFIG.rageClickThreshold) {
            const avgX = STATE.rageClicks.reduce((sum, c) => sum + c.x, 0) / STATE.rageClicks.length;
            const avgY = STATE.rageClicks.reduce((sum, c) => sum + c.y, 0) / STATE.rageClicks.length;

            // Check if clicks are clustered (within 50px radius)
            const isClusteredconst isClustered = STATE.rageClicks.every(click => {
                const dx = click.x - avgX;
                const dy = click.y - avgY;
                return Math.sqrt(dx * dx + dy * dy) < 50;
            });

            if (isClustered) {
                trackEvent('rage_click', {
                    x: Math.round(avgX),
                    y: Math.round(avgY),
                    clickCount: STATE.rageClicks.length
                });

                STATE.rageClicks = []; // Reset
                return true;
            }
        }

        return false;
    }

    // === EVENT TRACKING ===
    function trackEvent(eventType, data = {}) {
        if (!checkGDPRConsent()) return;

        const event = {
            sessionId: STATE.sessionId,
            fingerprintId: STATE.fingerprintId,
            eventType: eventType,
            pageUrl: window.location.pathname,
            timestamp: Date.now(),
            data: data
        };

        STATE.events.push(event);

        // Send event immediately for important events
        if (['rage_click', 'conversion', 'form_submit'].includes(eventType)) {
            sendEvent(event);
        }
    }

    function sendEvent(event) {
        const payload = {
            session_id: event.sessionId,
            fingerprint_id: event.fingerprintId,
            event_type: event.eventType,
            page_url: event.pageUrl,
            timestamp: new Date(event.timestamp).toISOString(),
            element_selector: event.data.selector || null,
            element_text: event.data.text || null,
            x_position: event.data.x || null,
            y_position: event.data.y || null,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            scroll_depth: STATE.maxScrollDepth,
            event_data: JSON.stringify(event.data)
        };

        sendBeacon(CONFIG.eventEndpoint, payload);
    }

    // === SESSION REPLAY RECORDING ===
    function recordReplayFrame(eventType, data) {
        if (!checkGDPRConsent()) return;

        const frame = {
            sessionId: STATE.sessionId,
            pageUrl: window.location.pathname,
            frameIndex: STATE.replayFrames.length,
            timestampOffset: Date.now() - STATE.pageLoadTime,
            eventType: eventType,
            data: data
        };

        STATE.replayFrames.push(frame);

        // Send frames in batches (every 50 frames)
        if (STATE.replayFrames.length >= 50) {
            sendReplayFrames();
        }
    }

    function sendReplayFrames() {
        if (STATE.replayFrames.length === 0) return;

        const payload = {
            session_id: STATE.sessionId,
            frames: STATE.replayFrames
        };

        sendBeacon(CONFIG.replayEndpoint, payload);
        STATE.replayFrames = [];
    }

    // === HEATMAP TRACKING ===
    function trackClickHeatmap(x, y) {
        if (!checkGDPRConsent()) return;

        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        const payload = {
            session_id: STATE.sessionId,
            page_url: window.location.pathname,
            x_percent: ((x / viewportWidth) * 100).toFixed(2),
            y_percent: ((y / viewportHeight) * 100).toFixed(2),
            viewport_width: viewportWidth,
            viewport_height: viewportHeight,
            device_type: detectDeviceType(),
            browser: detectBrowser()
        };

        sendBeacon(CONFIG.heatmapEndpoint, payload);
    }

    function trackScrollHeatmap() {
        if (!checkGDPRConsent()) return;

        const payload = {
            session_id: STATE.sessionId,
            page_url: window.location.pathname,
            scroll_depth_percent: STATE.maxScrollDepth,
            viewport_height: window.innerHeight,
            page_height: document.documentElement.scrollHeight,
            device_type: detectDeviceType()
        };

        sendBeacon(CONFIG.heatmapEndpoint, payload);
    }

    // === DEVICE DETECTION ===
    function detectDeviceType() {
        const ua = navigator.userAgent.toLowerCase();
        if (/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i.test(ua)) {
            return 'tablet';
        }
        if (/mobile|iphone|ipod|android|blackberry|opera mini|opera mobi|skyfire|maemo|windows phone|palm|iemobile|symbian|symbianos|fennec/i.test(ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    function detectBrowser() {
        const ua = navigator.userAgent;
        if (ua.indexOf('Edge') > -1) return 'Edge';
        if (ua.indexOf('Chrome') > -1 && ua.indexOf('Edge') === -1) return 'Chrome';
        if (ua.indexOf('Firefox') > -1) return 'Firefox';
        if (ua.indexOf('Safari') > -1 && ua.indexOf('Chrome') === -1) return 'Safari';
        if (ua.indexOf('Opera') > -1 || ua.indexOf('OPR') > -1) return 'Opera';
        return 'Other';
    }

    function detectOS() {
        const ua = navigator.userAgent;
        if (ua.indexOf('Windows NT 10') > -1) return 'Windows 10';
        if (ua.indexOf('Windows') > -1) return 'Windows';
        if (ua.indexOf('Mac OS X') > -1) return 'macOS';
        if (ua.indexOf('Android') > -1) return 'Android';
        if (ua.indexOf('iPhone') > -1 || ua.indexOf('iPad') > -1) return 'iOS';
        if (ua.indexOf('Linux') > -1) return 'Linux';
        return 'Other';
    }

    // === ACTIVITY TRACKING ===
    function updateActivity() {
        STATE.lastActivityTime = Date.now();
        if (STATE.isIdle) {
            STATE.isIdle = false;
            trackEvent('user_active');
        }
    }

    function checkIdleState() {
        const idleTime = Date.now() - STATE.lastActivityTime;

        if (!STATE.isIdle && idleTime > CONFIG.idleTimeout) {
            STATE.isIdle = true;
            trackEvent('user_idle', { idleTime });
        }
    }

    // === INITIAL PAGEVIEW TRACKING ===
    async function trackInitialPageview() {
        if (!checkGDPRConsent()) return;

        // Generate fingerprint
        const fingerprint = await generateFingerprint();
        STATE.fingerprintId = fingerprint.fingerprintId;

        // Parse UTM
        STATE.utmParams = parseUTMParams();

        // Bot detection
        const botDetection = detectBotSignals();

        const payload = {
            session_id: STATE.sessionId,
            fingerprint_id: STATE.fingerprintId,
            fingerprint_components: fingerprint.components,
            page_url: window.location.pathname + window.location.search,
            page_title: document.title || '',
            referrer: document.referrer || '',
            entry_page: STATE.entryPage,
            screen_resolution: `${screen.width}x${screen.height}`,
            viewport_size: `${window.innerWidth}x${window.innerHeight}`,
            language: navigator.language || 'cs',
            device_type: detectDeviceType(),
            browser: detectBrowser(),
            os: detectOS(),
            utm_source: STATE.utmParams.source,
            utm_medium: STATE.utmParams.medium,
            utm_campaign: STATE.utmParams.campaign,
            utm_content: STATE.utmParams.content,
            utm_term: STATE.utmParams.term,
            is_bot: botDetection.isBot,
            bot_score: botDetection.score,
            bot_signals: botDetection.signals,
            timestamp: new Date().toISOString()
        };

        sendBeacon(CONFIG.apiEndpoint, payload);
    }

    // === SESSION UPDATE (Heartbeat) ===
    function updateSession() {
        if (!checkGDPRConsent()) return;

        const sessionDuration = Math.floor((Date.now() - STATE.entryTime) / 1000);
        const activeDuration = Math.floor((STATE.lastActivityTime - STATE.entryTime) / 1000);

        const payload = {
            session_id: STATE.sessionId,
            fingerprint_id: STATE.fingerprintId,
            page_url: window.location.pathname,
            total_duration: sessionDuration,
            active_duration: activeDuration,
            idle_duration: sessionDuration - activeDuration,
            click_count: STATE.clickCount,
            scroll_depth: STATE.maxScrollDepth,
            mouse_distance: Math.round(STATE.mouseDistance),
            is_idle: STATE.isIdle,
            action: 'session_update'
        };

        sendBeacon(CONFIG.apiEndpoint, payload);
    }

    // === EXIT TRACKING ===
    function trackExit() {
        if (!checkGDPRConsent()) return;

        const sessionDuration = Math.floor((Date.now() - STATE.entryTime) / 1000);
        const activeDuration = Math.floor((STATE.lastActivityTime - STATE.entryTime) / 1000);

        // Track scroll heatmap on exit
        trackScrollHeatmap();

        // Send remaining replay frames
        sendReplayFrames();

        const payload = {
            session_id: STATE.sessionId,
            fingerprint_id: STATE.fingerprintId,
            page_url: window.location.pathname,
            exit_page: window.location.pathname,
            total_duration: sessionDuration,
            active_duration: activeDuration,
            idle_duration: sessionDuration - activeDuration,
            click_count: STATE.clickCount,
            scroll_depth: STATE.maxScrollDepth,
            mouse_distance: Math.round(STATE.mouseDistance),
            action: 'exit'
        };

        sendBeacon(CONFIG.apiEndpoint, payload);
    }

    // === NETWORK UTILITIES ===
    function sendBeacon(endpoint, data) {
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            navigator.sendBeacon(endpoint, blob);
        } else {
            // Fallback
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                keepalive: true
            }).catch(err => {
                console.warn('Tracking request failed:', err);
            });
        }
    }

    // === EVENT LISTENERS ===
    function setupEventListeners() {
        // Mouse movement (throttled)
        let mouseMoveTimer;
        document.addEventListener('mousemove', (e) => {
            trackMouseMovement(e.clientX, e.clientY);

            clearTimeout(mouseMoveTimer);
            mouseMoveTimer = setTimeout(() => {
                recordReplayFrame('mousemove', {
                    x: e.clientX,
                    y: e.clientY
                });
            }, CONFIG.mouseMoveThrottle);
        }, { passive: true });

        // Clicks
        document.addEventListener('click', (e) => {
            STATE.clickCount++;
            updateActivity();

            const x = e.clientX;
            const y = e.clientY;

            // Track click event
            trackEvent('click', {
                x: x,
                y: y,
                selector: getElementSelector(e.target),
                text: e.target.textContent?.substring(0, 100) || null
            });

            // Track click heatmap
            trackClickHeatmap(x, y);

            // Detect rage clicks
            detectRageClick(x, y);

            // Record for replay
            recordReplayFrame('click', {
                x: x,
                y: y,
                target: getElementSelector(e.target)
            });
        }, { passive: true });

        // Scroll (throttled)
        let scrollTimer;
        window.addEventListener('scroll', () => {
            updateActivity();
            calculateScrollDepth();

            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                recordReplayFrame('scroll', {
                    scrollTop: window.pageYOffset,
                    scrollDepth: STATE.scrollDepth
                });
            }, CONFIG.scrollThrottle);
        }, { passive: true });

        // Copy
        document.addEventListener('copy', () => {
            trackEvent('copy', {
                selection: window.getSelection()?.toString().substring(0, 100) || null
            });
        });

        // Paste
        document.addEventListener('paste', () => {
            trackEvent('paste');
        });

        // Keyboard
        document.addEventListener('keydown', () => {
            updateActivity();
        }, { passive: true });

        // Viewport resize
        window.addEventListener('resize', () => {
            recordReplayFrame('resize', {
                width: window.innerWidth,
                height: window.innerHeight
            });
        });

        // Page visibility
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                trackEvent('page_hidden');
            } else {
                trackEvent('page_visible');
                updateActivity();
            }
        });

        // Form submits
        document.addEventListener('submit', (e) => {
            trackEvent('form_submit', {
                formId: e.target.id || null,
                formAction: e.target.action || null
            });
        }, { passive: true });

        // Before unload (exit tracking)
        window.addEventListener('beforeunload', () => {
            trackExit();
        });

        // Page unload (backup)
        window.addEventListener('unload', () => {
            trackExit();
        });
    }

    function getElementSelector(element) {
        if (!element) return null;

        if (element.id) {
            return '#' + element.id;
        }

        if (element.className && typeof element.className === 'string') {
            return element.tagName.toLowerCase() + '.' + element.className.split(' ')[0];
        }

        return element.tagName.toLowerCase();
    }

    // === INITIALIZATION ===
    async function init() {
        // Check GDPR consent
        if (!checkGDPRConsent()) {
            console.log('📊 Analytics tracking disabled - awaiting consent');
            return;
        }

        // Initialize session
        STATE.sessionId = getOrCreateSessionId();

        // Setup event listeners
        setupEventListeners();

        // Track initial pageview
        await trackInitialPageview();

        // Start heartbeat (session updates)
        setInterval(() => {
            updateSession();
            checkIdleState();
        }, CONFIG.sessionUpdateInterval);

        // Periodic replay frame sending
        setInterval(() => {
            if (STATE.replayFrames.length > 0) {
                sendReplayFrames();
            }
        }, 30000); // Every 30 seconds

        console.log('📊 WGS Enterprise Analytics Tracker V2 loaded');
        console.log('Session ID:', STATE.sessionId);
        console.log('Fingerprint ID:', STATE.fingerprintId);
    }

    // Start when DOM is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        init();
    } else {
        window.addEventListener('DOMContentLoaded', init);
    }

    // === PUBLIC API ===
    window.WGS_Analytics = {
        trackConversion: function(type, value = null, goal = null) {
            if (STATE.conversionTracked) return; // Prevent duplicates

            STATE.conversionTracked = true;

            trackEvent('conversion', {
                type: type,
                value: value,
                goal: goal
            });

            const payload = {
                session_id: STATE.sessionId,
                fingerprint_id: STATE.fingerprintId,
                conversion_type: type,
                conversion_value: value,
                conversion_goal: goal,
                utm_source: STATE.utmParams?.source,
                utm_medium: STATE.utmParams?.medium,
                utm_campaign: STATE.utmParams?.campaign
            };

            sendBeacon(CONFIG.apiEndpoint, payload);
        },

        grantConsent: function() {
            localStorage.setItem('wgs_analytics_consent', 'granted');
            CONFIG.gdprConsent = true;
            init();
        },

        revokeConsent: function() {
            localStorage.setItem('wgs_analytics_consent', 'revoked');
            CONFIG.gdprConsent = false;
        },

        getSessionId: function() {
            return STATE.sessionId;
        },

        getFingerprintId: function() {
            return STATE.fingerprintId;
        }
    };

})();
