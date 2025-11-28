/**
 * Event Tracker - Sledování uživatelských událostí
 *
 * Modul pro detekci a sledování:
 * - Kliků (s přesnými souřadnicemi a CSS selektory)
 * - Scrollování (scroll depth percentage)
 * - Rage clicks (frustrace uživatele - 3+ kliky rychle za sebou)
 * - Copy/paste událostí
 * - Interakcí s formuláři (focus/blur)
 * - Idle/active stavů (detekce nečinnosti 30s+)
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #5 - Event Tracking Engine
 */

(function() {
    'use strict';

    // ========================================
    // KONFIGURACE
    // ========================================
    const EventTracker = {
        config: {
            sessionId: null,
            fingerprintId: null,
            csrfToken: null,
            apiEndpoint: '/api/track_event.php',
            batchSize: 50,              // Max eventů v jednom batch
            sendInterval: 5000,          // Interval odesílání (5s)
            trackClicks: true,
            trackScroll: true,
            trackRageClicks: true,
            trackCopyPaste: true,
            trackFormInteractions: true,
            trackIdleState: true,
            scrollDebounce: 500,         // Debounce pro scroll (ms)
            idleTimeout: 30000           // Idle timeout (30s)
        },

        // Event fronta
        eventQueue: [],

        // Interval ID pro automatické odesílání
        sendIntervalId: null,

        // Idle tracking
        idleTimeoutId: null,
        isIdle: false,

        // Scroll tracking
        maxScrollDepth: 0,

        // Rage click tracking
        rageClickTracker: {
            clicks: [],              // [{x, y, timestamp}, ...]
            threshold: 3,            // Min 3 kliky
            timeWindow: 1000,        // V průběhu 1 sekundy
            radiusThreshold: 50      // V okruhu 50px
        },

        // Inicializace
        initialized: false
    };

    // ========================================
    // INICIALIZACE
    // ========================================
    EventTracker.init = function(options) {
        if (this.initialized) {
            console.warn('[Event Tracker] Již inicializováno, přeskakuji.');
            return;
        }

        // Sloučit konfiguraci
        Object.assign(this.config, options || {});

        // Validace povinných parametrů
        if (!this.config.sessionId) {
            console.error('[Event Tracker] Chybí session_id, tracking nebude fungovat.');
            return;
        }

        if (!this.config.fingerprintId) {
            console.error('[Event Tracker] Chybí fingerprint_id, tracking nebude fungovat.');
            return;
        }

        if (!this.config.csrfToken) {
            console.error('[Event Tracker] Chybí CSRF token, tracking nebude fungovat.');
            return;
        }

        // Spustit sledování podle konfigurace
        if (this.config.trackClicks) {
            this.sledujKliky();
        }

        if (this.config.trackScroll) {
            this.sledujScrollovani();
        }

        if (this.config.trackCopyPaste) {
            this.sledujCopyPaste();
        }

        if (this.config.trackFormInteractions) {
            this.sledujFormulare();
        }

        if (this.config.trackIdleState) {
            this.sledujNecinnost();
        }

        // Spustit automatické odesílání
        this.spustitAutomatickeOdesilani();

        // Odeslat před zavřením stránky
        this.navesitBeforeUnload();

        this.initialized = true;
        console.log('[Event Tracker] Inicializováno úspěšně');
    };

    // ========================================
    // CLICK TRACKING
    // ========================================
    EventTracker.sledujKliky = function() {
        document.addEventListener('click', (e) => {
            const event = {
                event_type: 'click',
                page_url: window.location.href,
                timestamp: Date.now(),
                click_x: e.clientX,
                click_y: e.clientY,
                click_x_percent: parseFloat((e.clientX / window.innerWidth * 100).toFixed(2)),
                click_y_percent: parseFloat((e.clientY / window.innerHeight * 100).toFixed(2)),
                element_selector: this.getCSSSelector(e.target),
                element_text: this.getElementText(e.target),
                element_tag: e.target.tagName,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight
            };

            this.pridejEventDoFronty(event);

            // Zkontrolovat rage click pattern
            if (this.config.trackRageClicks) {
                this.detekujRageClick(e);
            }
        }, true); // useCapture = true pro zachycení všech kliků
    };

    // ========================================
    // RAGE CLICK DETECTION
    // ========================================
    EventTracker.detekujRageClick = function(e) {
        const now = Date.now();

        // Přidat nový klik
        this.rageClickTracker.clicks.push({
            x: e.clientX,
            y: e.clientY,
            timestamp: now
        });

        // Odstranit staré kliky (starší než time window)
        this.rageClickTracker.clicks = this.rageClickTracker.clicks.filter(
            c => (now - c.timestamp) < this.rageClickTracker.timeWindow
        );

        // Pokud máme 3+ kliky v time window
        if (this.rageClickTracker.clicks.length >= this.rageClickTracker.threshold) {
            // Zkontrolovat zda jsou všechny v okruhu 50px
            const prvniKlik = this.rageClickTracker.clicks[0];
            const vsechnyVOkruhu = this.rageClickTracker.clicks.every(c => {
                const vzdalenost = Math.sqrt(
                    Math.pow(c.x - prvniKlik.x, 2) +
                    Math.pow(c.y - prvniKlik.y, 2)
                );
                return vzdalenost <= this.rageClickTracker.radiusThreshold;
            });

            if (vsechnyVOkruhu) {
                // RAGE CLICK DETEKOVÁN!
                this.pridejEventDoFronty({
                    event_type: 'rage_click',
                    page_url: window.location.href,
                    timestamp: now,
                    click_x: prvniKlik.x,
                    click_y: prvniKlik.y,
                    click_x_percent: parseFloat((prvniKlik.x / window.innerWidth * 100).toFixed(2)),
                    click_y_percent: parseFloat((prvniKlik.y / window.innerHeight * 100).toFixed(2)),
                    rage_click_count: this.rageClickTracker.clicks.length,
                    element_selector: this.getCSSSelector(e.target),
                    element_tag: e.target.tagName,
                    viewport_width: window.innerWidth,
                    viewport_height: window.innerHeight
                });

                console.warn('[Event Tracker] Rage click detekován!', this.rageClickTracker.clicks.length, 'kliků');

                // Reset trackeru
                this.rageClickTracker.clicks = [];
            }
        }
    };

    // ========================================
    // SCROLL TRACKING (s debouncing)
    // ========================================
    EventTracker.sledujScrollovani = function() {
        const debouncedScroll = this.debounce(() => {
            const scrollDepth = this.vypocitejScrollDepth();

            // Uložit pouze pokud je nový maximum
            if (scrollDepth > this.maxScrollDepth) {
                this.maxScrollDepth = scrollDepth;

                this.pridejEventDoFronty({
                    event_type: 'scroll',
                    page_url: window.location.href,
                    timestamp: Date.now(),
                    scroll_depth: Math.floor(scrollDepth),
                    viewport_width: window.innerWidth,
                    viewport_height: window.innerHeight
                });
            }
        }, this.config.scrollDebounce);

        window.addEventListener('scroll', debouncedScroll, { passive: true });
    };

    EventTracker.vypocitejScrollDepth = function() {
        const windowHeight = window.innerHeight;
        const documentHeight = Math.max(
            document.body.scrollHeight,
            document.body.offsetHeight,
            document.documentElement.clientHeight,
            document.documentElement.scrollHeight,
            document.documentElement.offsetHeight
        );
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        const scrollableHeight = documentHeight - windowHeight;
        if (scrollableHeight <= 0) return 100;

        return (scrollTop / scrollableHeight) * 100;
    };

    // ========================================
    // COPY/PASTE TRACKING
    // ========================================
    EventTracker.sledujCopyPaste = function() {
        document.addEventListener('copy', (e) => {
            const selectedText = window.getSelection().toString();

            this.pridejEventDoFronty({
                event_type: 'copy',
                page_url: window.location.href,
                timestamp: Date.now(),
                copied_text_length: selectedText.length,
                element_selector: this.getCSSSelector(e.target),
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight
            });
        });

        document.addEventListener('paste', (e) => {
            this.pridejEventDoFronty({
                event_type: 'paste',
                page_url: window.location.href,
                timestamp: Date.now(),
                element_selector: this.getCSSSelector(e.target),
                form_field_name: e.target.name || e.target.id || null,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight
            });
        });
    };

    // ========================================
    // FORM INTERACTION TRACKING
    // ========================================
    EventTracker.sledujFormulare = function() {
        // Použít timeout, aby mohly být formuláře načteny dynamicky
        setTimeout(() => {
            const formFields = document.querySelectorAll('input, textarea, select');

            formFields.forEach(field => {
                // Focus event
                field.addEventListener('focus', (e) => {
                    this.pridejEventDoFronty({
                        event_type: 'form_focus',
                        page_url: window.location.href,
                        timestamp: Date.now(),
                        form_field_name: e.target.name || e.target.id || 'unnamed',
                        element_selector: this.getCSSSelector(e.target),
                        element_tag: e.target.tagName,
                        viewport_width: window.innerWidth,
                        viewport_height: window.innerHeight
                    });
                });

                // Blur event
                field.addEventListener('blur', (e) => {
                    this.pridejEventDoFronty({
                        event_type: 'form_blur',
                        page_url: window.location.href,
                        timestamp: Date.now(),
                        form_field_name: e.target.name || e.target.id || 'unnamed',
                        element_selector: this.getCSSSelector(e.target),
                        element_tag: e.target.tagName,
                        viewport_width: window.innerWidth,
                        viewport_height: window.innerHeight
                    });
                });
            });
        }, 1000); // 1s delay pro dynamické formuláře
    };

    // ========================================
    // IDLE/ACTIVE STATE TRACKING
    // ========================================
    EventTracker.sledujNecinnost = function() {
        const resetIdleTimer = () => {
            clearTimeout(this.idleTimeoutId);

            // Pokud byl idle a vrátil se, zaznamenat active
            if (this.isIdle) {
                this.pridejEventDoFronty({
                    event_type: 'active',
                    page_url: window.location.href,
                    timestamp: Date.now(),
                    viewport_width: window.innerWidth,
                    viewport_height: window.innerHeight
                });
                this.isIdle = false;
            }

            this.idleTimeoutId = setTimeout(() => {
                this.pridejEventDoFronty({
                    event_type: 'idle',
                    page_url: window.location.href,
                    timestamp: Date.now(),
                    idle_duration: this.config.idleTimeout,
                    viewport_width: window.innerWidth,
                    viewport_height: window.innerHeight
                });
                this.isIdle = true;
            }, this.config.idleTimeout);
        };

        // Reset při jakékoli aktivitě
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
        events.forEach(eventName => {
            document.addEventListener(eventName, resetIdleTimer, { passive: true });
        });

        resetIdleTimer(); // Initial start
    };

    // ========================================
    // EVENT QUEUE MANAGEMENT
    // ========================================
    EventTracker.pridejEventDoFronty = function(event) {
        this.eventQueue.push(event);

        // Pokud fronta dosáhla batch size, poslat okamžitě
        if (this.eventQueue.length >= this.config.batchSize) {
            this.odeslEventyNaServer();
        }
    };

    EventTracker.odeslEventyNaServer = function() {
        if (this.eventQueue.length === 0) {
            return Promise.resolve();
        }

        // Vzít první N eventů z fronty
        const eventsToSend = this.eventQueue.splice(0, this.config.batchSize);

        const payload = {
            csrf_token: this.config.csrfToken,
            session_id: this.config.sessionId,
            fingerprint_id: this.config.fingerprintId,
            events: eventsToSend
        };

        return fetch(this.config.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.config.csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('[Event Tracker] Odesláno:', data.data.stored_count, 'eventů');
            } else {
                console.error('[Event Tracker] Chyba:', data.message);
                // Vrátit eventy zpět do fronty pro retry
                this.eventQueue.unshift(...eventsToSend);
            }
        })
        .catch(error => {
            console.error('[Event Tracker] Síťová chyba:', error);
            // Vrátit eventy zpět do fronty pro retry
            this.eventQueue.unshift(...eventsToSend);
        });
    };

    EventTracker.spustitAutomatickeOdesilani = function() {
        this.sendIntervalId = setInterval(() => {
            this.odeslEventyNaServer();
        }, this.config.sendInterval);
    };

    EventTracker.navesitBeforeUnload = function() {
        window.addEventListener('beforeunload', () => {
            if (this.eventQueue.length > 0) {
                // Použít Beacon API pro spolehlivé odeslání
                const payload = JSON.stringify({
                    csrf_token: this.config.csrfToken,
                    session_id: this.config.sessionId,
                    fingerprint_id: this.config.fingerprintId,
                    events: this.eventQueue
                });

                const blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(this.config.apiEndpoint, blob);

                console.log('[Event Tracker] beforeunload: Odesláno', this.eventQueue.length, 'eventů přes Beacon');
            }
        });
    };

    // ========================================
    // HELPER FUNKCE
    // ========================================

    /**
     * Získá CSS selector pro daný element
     */
    EventTracker.getCSSSelector = function(element) {
        if (!element || element.nodeType !== Node.ELEMENT_NODE) {
            return null;
        }

        // Pokud má ID, použít ID
        if (element.id) {
            return '#' + element.id;
        }

        // Pokud má unique class, použít class
        if (element.className && typeof element.className === 'string') {
            const classes = element.className.trim().replace(/\s+/g, '.');
            if (classes) {
                return element.tagName.toLowerCase() + '.' + classes;
            }
        }

        // Fallback: tag + nth-child
        let path = element.tagName.toLowerCase();

        if (element.parentElement) {
            const siblings = Array.from(element.parentElement.children);
            const index = siblings.indexOf(element) + 1;
            path += ':nth-child(' + index + ')';
        }

        return path;
    };

    /**
     * Získá text elementu (zkrácený na 255 znaků)
     */
    EventTracker.getElementText = function(element) {
        if (!element) return null;

        let text = element.innerText || element.textContent || '';
        text = text.trim();

        if (text.length > 255) {
            text = text.substring(0, 252) + '...';
        }

        return text || null;
    };

    /**
     * Debounce funkce
     */
    EventTracker.debounce = function(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    };

    // ========================================
    // EXPORT
    // ========================================
    window.EventTracker = EventTracker;

})();
