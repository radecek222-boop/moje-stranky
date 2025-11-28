/**
 * Replay Recorder - Nahrávání uživatelských interakcí pro session replay
 *
 * Zaznamenává mouse movement, clicks, scroll, resize events
 * a odesílá je batch-wise na server pro pozdější přehrání.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #7 - Session Replay Engine
 */

(function() {
    'use strict';

    const ReplayRecorder = {
        config: {
            sessionId: null,
            pageUrl: null,
            pageIndex: 0,
            csrfToken: null,

            // Throttling
            mouseMoveThrottle: 100,  // 100ms = 10 FPS
            scrollThrottle: 150,      // 150ms

            // Batching
            maxBatchSize: 50,         // max frames per request
            batchInterval: 30000,     // 30s auto-flush

            // Flags
            isRecording: false,
            recordMouseMove: true,
            recordClicks: true,
            recordScroll: true,
            recordResize: true,

            // API
            apiEndpoint: '/api/track_replay.php'
        },

        state: {
            frames: [],              // Buffer pro frames
            frameIndex: 0,           // Counter pro frame indexy
            pageLoadTime: null,      // Timestamp načtení stránky
            lastMouseMove: 0,        // Timestamp posledního mousemove
            lastScroll: 0,           // Timestamp posledního scroll
            batchTimer: null,        // Interval pro auto-flush
            viewportWidth: window.innerWidth,
            viewportHeight: window.innerHeight,
            deviceType: null
        },

        /**
         * Inicializace recorderu
         */
        init: function(options) {
            // Merge options
            Object.assign(this.config, options);

            // Detect device type
            this.state.deviceType = this.detekujTypZarizeni();

            // Set page load time
            this.state.pageLoadTime = Date.now();

            // Record initial "load" frame
            this.recordFrame('load', {});

            // Attach event listeners
            this.pripojListenery();

            // Start batch timer
            this.startBatchTimer();

            // Mark as recording
            this.config.isRecording = true;

            console.log('[Replay Recorder] Inicializováno pro session:', this.config.sessionId, 'page_index:', this.config.pageIndex);
        },

        /**
         * Připojení event listenerů
         */
        pripojListenery: function() {
            if (this.config.recordMouseMove) {
                document.addEventListener('mousemove', this.throttle(
                    this.handleMouseMove.bind(this),
                    this.config.mouseMoveThrottle
                ));
            }

            if (this.config.recordClicks) {
                document.addEventListener('click', this.handleClick.bind(this), true);
            }

            if (this.config.recordScroll) {
                window.addEventListener('scroll', this.throttle(
                    this.handleScroll.bind(this),
                    this.config.scrollThrottle
                ), { passive: true });
            }

            if (this.config.recordResize) {
                window.addEventListener('resize', this.debounce(
                    this.handleResize.bind(this),
                    200
                ));
            }

            // Zaznamenat před opuštěním stránky
            window.addEventListener('beforeunload', this.handleUnload.bind(this));

            // Focus/blur events
            window.addEventListener('focus', this.handleFocus.bind(this));
            window.addEventListener('blur', this.handleBlur.bind(this));
        },

        /**
         * Handler pro mousemove
         */
        handleMouseMove: function(e) {
            if (!this.config.isRecording) return;

            const now = Date.now();
            if (now - this.state.lastMouseMove < this.config.mouseMoveThrottle) {
                return; // Throttle
            }

            this.state.lastMouseMove = now;

            const eventData = {
                x: Math.round(e.clientX),
                y: Math.round(e.clientY),
                x_percent: parseFloat(((e.clientX / window.innerWidth) * 100).toFixed(2)),
                y_percent: parseFloat(((e.clientY / window.innerHeight) * 100).toFixed(2))
            };

            this.recordFrame('mousemove', eventData);
        },

        /**
         * Handler pro click
         */
        handleClick: function(e) {
            if (!this.config.isRecording) return;

            const eventData = {
                x: Math.round(e.clientX),
                y: Math.round(e.clientY),
                button: e.button,
                element: this.getCSSSelector(e.target),
                text: (e.target.textContent || '').trim().substring(0, 100)
            };

            this.recordFrame('click', eventData);
        },

        /**
         * Handler pro scroll
         */
        handleScroll: function(e) {
            if (!this.config.isRecording) return;

            const now = Date.now();
            if (now - this.state.lastScroll < this.config.scrollThrottle) {
                return;
            }

            this.state.lastScroll = now;

            const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrollPercent = scrollHeight > 0
                ? parseFloat(((window.scrollY / scrollHeight) * 100).toFixed(2))
                : 0;

            const eventData = {
                scrollY: Math.round(window.scrollY),
                scrollX: Math.round(window.scrollX),
                scroll_percent: scrollPercent
            };

            this.recordFrame('scroll', eventData);
        },

        /**
         * Handler pro resize
         */
        handleResize: function(e) {
            if (!this.config.isRecording) return;

            const eventData = {
                width: window.innerWidth,
                height: window.innerHeight
            };

            this.state.viewportWidth = window.innerWidth;
            this.state.viewportHeight = window.innerHeight;

            this.recordFrame('resize', eventData);
        },

        /**
         * Handler pro focus
         */
        handleFocus: function(e) {
            if (!this.config.isRecording) return;
            this.recordFrame('focus', {});
        },

        /**
         * Handler pro blur
         */
        handleBlur: function(e) {
            if (!this.config.isRecording) return;
            this.recordFrame('blur', {});
        },

        /**
         * Handler pro beforeunload
         */
        handleUnload: function(e) {
            // Final flush před opuštěním
            this.recordFrame('unload', {});
            this.flushFrames(true); // Force sync flush with Beacon
        },

        /**
         * Zaznamenat frame do bufferu
         */
        recordFrame: function(eventType, eventData) {
            const timestamp = Date.now() - this.state.pageLoadTime;

            const frame = {
                frame_index: this.state.frameIndex++,
                timestamp_offset: timestamp,
                event_type: eventType,
                event_data: eventData
            };

            this.state.frames.push(frame);

            // Auto-flush pokud dosáhneme max batch size
            if (this.state.frames.length >= this.config.maxBatchSize) {
                this.flushFrames();
            }
        },

        /**
         * Odeslat frames na server
         */
        flushFrames: async function(useBeacon = false) {
            if (this.state.frames.length === 0) return;

            const payload = {
                csrf_token: this.config.csrfToken,
                session_id: this.config.sessionId,
                page_url: this.config.pageUrl,
                page_index: this.config.pageIndex,
                device_type: this.state.deviceType,
                viewport_width: this.state.viewportWidth,
                viewport_height: this.state.viewportHeight,
                frames: this.state.frames
            };

            const jsonData = JSON.stringify(payload);

            // Použít Beacon API pro beforeunload (spolehlivější)
            if (useBeacon && navigator.sendBeacon) {
                const blob = new Blob([jsonData], { type: 'application/json' });
                const success = navigator.sendBeacon(this.config.apiEndpoint, blob);

                if (success) {
                    console.log('[Replay Recorder] Beacon odeslán:', this.state.frames.length, 'framů');
                }
            } else {
                // Normální fetch
                try {
                    const response = await fetch(this.config.apiEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.config.csrfToken
                        },
                        body: jsonData
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        console.log('[Replay Recorder] Frames odeslány:', result.data.frames_stored);
                    } else {
                        console.error('[Replay Recorder] Chyba:', result.message);
                    }
                } catch (error) {
                    console.error('[Replay Recorder] Síťová chyba:', error);
                }
            }

            // Vyčistit buffer
            this.state.frames = [];
        },

        /**
         * Start batch timer (auto-flush každých 30s)
         */
        startBatchTimer: function() {
            this.state.batchTimer = setInterval(() => {
                this.flushFrames();
            }, this.config.batchInterval);
        },

        /**
         * Stop recording
         */
        stop: function() {
            this.config.isRecording = false;

            if (this.state.batchTimer) {
                clearInterval(this.state.batchTimer);
            }

            this.flushFrames();
            console.log('[Replay Recorder] Zastaveno');
        },

        /**
         * Throttle funkce (omezí frekvenci volání)
         */
        throttle: function(func, wait) {
            let timeout = null;
            let previous = 0;

            return function(...args) {
                const now = Date.now();
                const remaining = wait - (now - previous);

                if (remaining <= 0 || remaining > wait) {
                    if (timeout) {
                        clearTimeout(timeout);
                        timeout = null;
                    }
                    previous = now;
                    func.apply(this, args);
                } else if (!timeout) {
                    timeout = setTimeout(() => {
                        previous = Date.now();
                        timeout = null;
                        func.apply(this, args);
                    }, remaining);
                }
            };
        },

        /**
         * Debounce funkce (počká až přestanou volání)
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        /**
         * Získat CSS selector pro element
         */
        getCSSSelector: function(element) {
            if (!element || !element.tagName) return '';

            // Pokud má ID, použít ho
            if (element.id) {
                return '#' + element.id;
            }

            // Pokud má unique class
            if (element.className && typeof element.className === 'string') {
                const classes = element.className.trim().split(/\s+/).filter(c => c.length > 0);
                if (classes.length > 0) {
                    return element.tagName.toLowerCase() + '.' + classes.join('.');
                }
            }

            // Fallback: tag name
            return element.tagName.toLowerCase();
        },

        /**
         * Detekce typu zařízení
         */
        detekujTypZarizeni: function() {
            const width = window.innerWidth;

            if (width <= 768) {
                return 'mobile';
            } else if (width <= 1024) {
                return 'tablet';
            } else {
                return 'desktop';
            }
        }
    };

    // Export do global scope
    window.ReplayRecorder = ReplayRecorder;

})();
