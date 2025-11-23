/**
 * FINGERPRINT MODULE
 *
 * Device fingerprinting for cross-session user tracking.
 * Generates stable fingerprint using multiple browser APIs.
 *
 * Module #1 of Enterprise Analytics System
 *
 * Techniques:
 * - Canvas fingerprinting
 * - WebGL fingerprinting
 * - Audio fingerprinting
 * - Screen properties
 * - Timezone detection
 * - Font detection
 * - Plugin detection
 * - Hardware detection
 *
 * @package WGS_Analytics
 * @version 1.0.0
 */

(function(window) {
    'use strict';

    const FingerprintModule = {

        /**
         * Generate complete fingerprint
         *
         * @returns {Promise<Object>} Fingerprint result with ID and components
         */
        async generateFingerprint() {
            try {
                const components = {
                    canvas_hash: await this.getCanvasFingerprint(),
                    ...this.getWebGLFingerprint(),
                    audio_hash: await this.getAudioFingerprint(),
                    ...this.getScreenFingerprint(),
                    ...this.getTimezoneFingerprint(),
                    fonts_hash: await this.getFontFingerprint(),
                    plugins_hash: this.getPluginFingerprint(),
                    ...this.getHardwareFingerprint()
                };

                // Calculate fingerprint ID
                const fingerprintId = await this.calculateFingerprintId(components);

                return {
                    fingerprintId: fingerprintId,
                    components: components
                };

            } catch (error) {
                console.warn('Fingerprint generation error:', error);
                // Return minimal fingerprint
                return {
                    fingerprintId: 'fp_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10),
                    components: {}
                };
            }
        },

        /**
         * Canvas fingerprinting
         *
         * Draws text and shapes on canvas and hashes the output.
         * Different GPUs and rendering engines produce different results.
         *
         * @returns {Promise<string>} SHA-256 hash of canvas data
         */
        async getCanvasFingerprint() {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 200;
                canvas.height = 50;
                const ctx = canvas.getContext('2d');

                if (!ctx) return null;

                // Draw text with different fonts
                ctx.textBaseline = 'top';
                ctx.font = '14px "Arial"';
                ctx.textBaseline = 'alphabetic';
                ctx.fillStyle = '#f60';
                ctx.fillRect(125, 1, 62, 20);

                ctx.fillStyle = '#069';
                ctx.fillText('Canvas Fingerprint 123', 2, 15);

                ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
                ctx.fillText('Canvas Fingerprint 123', 4, 17);

                // Draw some shapes
                ctx.fillStyle = 'rgb(255, 0, 255)';
                ctx.beginPath();
                ctx.arc(50, 25, 20, 0, Math.PI * 2, true);
                ctx.closePath();
                ctx.fill();

                ctx.fillStyle = 'rgb(0, 255, 255)';
                ctx.beginPath();
                ctx.arc(100, 25, 20, 0, Math.PI * 2, true);
                ctx.closePath();
                ctx.fill();

                // Get canvas data
                const dataUrl = canvas.toDataURL();

                // Hash the data URL
                return await this.hashString(dataUrl);

            } catch (error) {
                return null;
            }
        },

        /**
         * WebGL fingerprinting
         *
         * Extracts GPU vendor and renderer information.
         *
         * @returns {Object} {webgl_vendor, webgl_renderer}
         */
        getWebGLFingerprint() {
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');

                if (!gl) {
                    return {
                        webgl_vendor: null,
                        webgl_renderer: null
                    };
                }

                // Try to get unmasked vendor/renderer
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');

                let vendor, renderer;

                if (debugInfo) {
                    vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
                    renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
                } else {
                    vendor = gl.getParameter(gl.VENDOR);
                    renderer = gl.getParameter(gl.RENDERER);
                }

                return {
                    webgl_vendor: vendor || null,
                    webgl_renderer: renderer || null
                };

            } catch (error) {
                return {
                    webgl_vendor: null,
                    webgl_renderer: null
                };
            }
        },

        /**
         * Audio fingerprinting
         *
         * Uses AudioContext oscillator to generate unique audio signature.
         * Different hardware produces slightly different audio processing results.
         *
         * @returns {Promise<string>} SHA-256 hash of audio data
         */
        async getAudioFingerprint() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return null;

                const context = new AudioContext();
                const oscillator = context.createOscillator();
                const analyser = context.createAnalyser();
                const gainNode = context.createGain();
                const scriptProcessor = context.createScriptProcessor(4096, 1, 1);

                // Mute the output
                gainNode.gain.value = 0;

                // Create triangle wave
                oscillator.type = 'triangle';
                oscillator.frequency.value = 10000;

                // Connect nodes
                oscillator.connect(analyser);
                analyser.connect(scriptProcessor);
                scriptProcessor.connect(gainNode);
                gainNode.connect(context.destination);

                oscillator.start(0);

                return new Promise((resolve) => {
                    scriptProcessor.onaudioprocess = async (event) => {
                        const output = event.outputBuffer.getChannelData(0);

                        // Calculate sum of absolute values
                        const sum = Array.from(output).reduce((acc, val) => acc + Math.abs(val), 0);

                        oscillator.stop();
                        context.close();

                        // Hash the sum
                        const hash = await this.hashString(sum.toString());
                        resolve(hash);
                    };
                });

            } catch (error) {
                return null;
            }
        },

        /**
         * Screen fingerprinting
         *
         * Captures screen properties.
         *
         * @returns {Object} Screen properties
         */
        getScreenFingerprint() {
            return {
                screen_width: screen.width,
                screen_height: screen.height,
                color_depth: screen.colorDepth,
                pixel_ratio: window.devicePixelRatio || 1.0,
                avail_width: screen.availWidth,
                avail_height: screen.availHeight
            };
        },

        /**
         * Timezone fingerprinting
         *
         * Gets timezone information.
         *
         * @returns {Object} {timezone, timezone_offset}
         */
        getTimezoneFingerprint() {
            try {
                return {
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                    timezone_offset: new Date().getTimezoneOffset()
                };
            } catch (error) {
                return {
                    timezone: null,
                    timezone_offset: new Date().getTimezoneOffset()
                };
            }
        },

        /**
         * Font detection fingerprinting
         *
         * Detects available system fonts.
         *
         * @returns {Promise<string>} SHA-256 hash of available fonts
         */
        async getFontFingerprint() {
            try {
                const testFonts = [
                    'Arial', 'Verdana', 'Times New Roman', 'Courier New',
                    'Georgia', 'Palatino', 'Garamond', 'Bookman',
                    'Comic Sans MS', 'Trebuchet MS', 'Impact'
                ];

                const availableFonts = [];

                // Check if document.fonts API is available
                if (document.fonts && document.fonts.check) {
                    for (const font of testFonts) {
                        if (document.fonts.check('12px "' + font + '"')) {
                            availableFonts.push(font);
                        }
                    }
                } else {
                    // Fallback: use canvas measurement
                    const baseFonts = ['monospace', 'sans-serif', 'serif'];
                    const testString = 'mmmmmmmmmmlli';
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    if (ctx) {
                        for (const testFont of testFonts) {
                            let detected = false;

                            for (const baseFont of baseFonts) {
                                ctx.font = '72px ' + baseFont;
                                const baseWidth = ctx.measureText(testString).width;

                                ctx.font = '72px "' + testFont + '", ' + baseFont;
                                const testWidth = ctx.measureText(testString).width;

                                if (baseWidth !== testWidth) {
                                    detected = true;
                                    break;
                                }
                            }

                            if (detected) {
                                availableFonts.push(testFont);
                            }
                        }
                    }
                }

                // Sort and hash
                availableFonts.sort();
                return await this.hashString(availableFonts.join(','));

            } catch (error) {
                return null;
            }
        },

        /**
         * Plugin detection fingerprinting
         *
         * Gets list of browser plugins.
         *
         * @returns {string} SHA-256 hash of plugins or "none"
         */
        getPluginFingerprint() {
            try {
                if (!navigator.plugins || navigator.plugins.length === 0) {
                    return 'none';
                }

                const plugins = Array.from(navigator.plugins)
                    .map(p => p.name)
                    .sort()
                    .join(',');

                // Return hash synchronously (we'll hash it later if needed)
                return plugins;

            } catch (error) {
                return 'none';
            }
        },

        /**
         * Hardware fingerprinting
         *
         * Detects hardware capabilities.
         *
         * @returns {Object} Hardware properties
         */
        getHardwareFingerprint() {
            return {
                hardware_concurrency: navigator.hardwareConcurrency || null,
                device_memory: navigator.deviceMemory || null,
                platform: navigator.platform || null,
                touch_support: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
                max_touch_points: navigator.maxTouchPoints || 0
            };
        },

        /**
         * Calculate fingerprint ID from components
         *
         * Combines all components and hashes them.
         *
         * @param {Object} components All fingerprint components
         * @returns {Promise<string>} SHA-256 hash (32 chars)
         */
        async calculateFingerprintId(components) {
            // Create normalized string from core components
            const coreComponents = {
                canvas: components.canvas_hash || '',
                webgl_vendor: components.webgl_vendor || '',
                webgl_renderer: components.webgl_renderer || '',
                audio: components.audio_hash || '',
                screen_width: components.screen_width || 0,
                screen_height: components.screen_height || 0,
                color_depth: components.color_depth || 0,
                pixel_ratio: components.pixel_ratio || 1.0,
                timezone: components.timezone || '',
                platform: components.platform || ''
            };

            // Sort keys for consistency
            const sorted = Object.keys(coreComponents).sort().reduce((acc, key) => {
                acc[key] = coreComponents[key];
                return acc;
            }, {});

            // Serialize to JSON
            const serialized = JSON.stringify(sorted);

            // Hash and return first 32 chars
            const fullHash = await this.hashString(serialized);
            return fullHash.substring(0, 32);
        },

        /**
         * SHA-256 hash utility
         *
         * @param {string} str String to hash
         * @returns {Promise<string>} SHA-256 hash in hex format
         */
        async hashString(str) {
            try {
                const encoder = new TextEncoder();
                const data = encoder.encode(str);
                const hashBuffer = await crypto.subtle.digest('SHA-256', data);
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                return hashHex;
            } catch (error) {
                // Fallback to simple hash if crypto API not available
                let hash = 0;
                for (let i = 0; i < str.length; i++) {
                    const char = str.charCodeAt(i);
                    hash = ((hash << 5) - hash) + char;
                    hash = hash & hash; // Convert to 32bit integer
                }
                return Math.abs(hash).toString(16).padStart(8, '0');
            }
        },

        /**
         * Send fingerprint to server
         *
         * @param {string} sessionId Session ID
         * @param {Object} components Fingerprint components
         * @param {string} userAgent User agent string
         * @returns {Promise<Object>} Server response
         */
        async sendToServer(sessionId, components, userAgent) {
            try {
                // Get CSRF token
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                const payload = {
                    session_id: sessionId,
                    fingerprint_components: components,
                    user_agent: userAgent,
                    csrf_token: csrfToken
                };

                const response = await fetch('/api/fingerprint_store.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload),
                    keepalive: true
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();

                if (result.status !== 'success') {
                    throw new Error(result.message || 'Unknown error');
                }

                return result;

            } catch (error) {
                console.warn('Failed to send fingerprint to server:', error);
                return null;
            }
        },

        /**
         * Get or generate fingerprint with caching
         *
         * Checks localStorage for cached fingerprint, or generates new one.
         *
         * @param {string} sessionId Session ID
         * @returns {Promise<string>} Fingerprint ID
         */
        async getOrGenerateFingerprint(sessionId) {
            try {
                // Check localStorage for cached fingerprint
                const cached = localStorage.getItem('wgs_fingerprint_id');

                if (cached) {
                    console.log('Using cached fingerprint:', cached);
                    return cached;
                }

                // Generate new fingerprint
                console.log('Generating new fingerprint...');
                const result = await this.generateFingerprint();

                // Send to server
                const serverResponse = await this.sendToServer(
                    sessionId,
                    result.components,
                    navigator.userAgent
                );

                if (serverResponse && serverResponse.fingerprint_id) {
                    // Store in localStorage
                    localStorage.setItem('wgs_fingerprint_id', serverResponse.fingerprint_id);

                    console.log('Fingerprint generated and stored:', serverResponse.fingerprint_id);
                    console.log('Is new:', serverResponse.is_new);
                    console.log('Session count:', serverResponse.session_count);

                    return serverResponse.fingerprint_id;
                } else {
                    // Fallback to client-side ID
                    localStorage.setItem('wgs_fingerprint_id', result.fingerprintId);
                    return result.fingerprintId;
                }

            } catch (error) {
                console.warn('Fingerprint error:', error);
                // Generate fallback ID
                const fallbackId = 'fp_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
                localStorage.setItem('wgs_fingerprint_id', fallbackId);
                return fallbackId;
            }
        }
    };

    // Export to window
    window.FingerprintModule = FingerprintModule;

})(window);
