/**
 * Replay Player - Přehrávání session replay v admin UI
 *
 * Canvas-based player pro vizualizaci nahraných uživatelských interakcí.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #7 - Session Replay Engine
 */

(function() {
    'use strict';

    const ReplayPlayer = {
        config: {
            canvasId: 'replay-canvas',
            timelineId: 'replay-timeline',
            playPauseButtonId: 'play-pause-btn',
            speedSelectId: 'speed-select',
            currentTimeId: 'current-time',
            totalTimeId: 'total-time',

            // Playback
            defaultSpeed: 1.0,
            speeds: [0.5, 1.0, 2.0, 4.0]
        },

        state: {
            frames: [],
            currentFrameIndex: 0,
            isPlaying: false,
            playbackSpeed: 1.0,
            startTime: null,
            animationFrameId: null,

            // Canvas
            canvas: null,
            ctx: null,

            // Mouse cursor
            cursorX: 0,
            cursorY: 0,

            // Session data
            sessionId: null,
            pageIndex: null
        },

        /**
         * Inicializace playeru
         */
        init: function(sessionId, pageIndex) {
            this.state.sessionId = sessionId;
            this.state.pageIndex = pageIndex;

            // Setup canvas
            this.state.canvas = document.getElementById(this.config.canvasId);
            if (!this.state.canvas) {
                console.error('[Replay Player] Canvas nenalezen:', this.config.canvasId);
                return false;
            }

            this.state.ctx = this.state.canvas.getContext('2d');

            // Fetch replay data
            this.loadReplayData(sessionId, pageIndex);

            // Setup controls
            this.setupControls();

            return true;
        },

        /**
         * Načtení replay dat z API
         */
        loadReplayData: async function(sessionId, pageIndex) {
            const csrfToken = document.querySelector('[name="csrf_token"]').value;

            const loadingEl = document.getElementById('loading-message');
            if (loadingEl) {
                loadingEl.textContent = 'Načítám replay data...';
            }

            try {
                const response = await fetch(
                    `/api/analytics_replay.php?session_id=${encodeURIComponent(sessionId)}&page_index=${pageIndex}&csrf_token=${encodeURIComponent(csrfToken)}`
                );

                const result = await response.json();

                if (result.status === 'success') {
                    this.state.frames = result.data.frames;

                    // Setup canvas dimensions
                    this.state.canvas.width = result.data.viewport.width;
                    this.state.canvas.height = result.data.viewport.height;

                    // Update total time display
                    const totalTimeMs = result.data.duration_ms;
                    const totalTimeEl = document.getElementById(this.config.totalTimeId);
                    if (totalTimeEl) {
                        totalTimeEl.textContent = this.formatTime(totalTimeMs);
                    }

                    // Update page info
                    const pageInfoEl = document.getElementById('page-info');
                    if (pageInfoEl) {
                        pageInfoEl.textContent = `${result.data.page_url} (${result.data.total_frames} framů, ${this.formatTime(totalTimeMs)})`;
                    }

                    // Hide loading
                    if (loadingEl) {
                        loadingEl.classList.add('hidden');
                    }

                    // Draw first frame
                    this.drawFrame(0);
                } else {
                    console.error('[Replay Player] Chyba:', result.message);
                    if (loadingEl) {
                        loadingEl.textContent = 'Chyba: ' + result.message;
                        loadingEl.style.color = 'red';
                    }
                }
            } catch (error) {
                console.error('[Replay Player] Síťová chyba:', error);
                if (loadingEl) {
                    loadingEl.textContent = 'Síťová chyba: ' + error.message;
                    loadingEl.style.color = 'red';
                }
            }
        },

        /**
         * Setup playback controls
         */
        setupControls: function() {
            // Play/Pause button
            const playPauseBtn = document.getElementById(this.config.playPauseButtonId);
            if (playPauseBtn) {
                playPauseBtn.addEventListener('click', () => {
                    if (this.state.isPlaying) {
                        this.pause();
                    } else {
                        this.play();
                    }
                });
            }

            // Speed selector
            const speedSelect = document.getElementById(this.config.speedSelectId);
            if (speedSelect) {
                speedSelect.addEventListener('change', (e) => {
                    this.setSpeed(parseFloat(e.target.value));
                });
            }

            // Timeline scrubber
            const timeline = document.getElementById(this.config.timelineId);
            if (timeline) {
                timeline.addEventListener('input', (e) => {
                    const percent = parseFloat(e.target.value);
                    this.seekToPercent(percent);
                });
            }
        },

        /**
         * Play
         */
        play: function() {
            if (this.state.frames.length === 0) return;

            this.state.isPlaying = true;
            this.state.startTime = Date.now() - (this.getCurrentTimestamp() / this.state.playbackSpeed);

            this.animatePlayback();

            // Update button text
            const btn = document.getElementById(this.config.playPauseButtonId);
            if (btn) {
                btn.textContent = '⏸ Pause';
            }
        },

        /**
         * Pause
         */
        pause: function() {
            this.state.isPlaying = false;

            if (this.state.animationFrameId) {
                cancelAnimationFrame(this.state.animationFrameId);
            }

            // Update button text
            const btn = document.getElementById(this.config.playPauseButtonId);
            if (btn) {
                btn.textContent = '▶ Play';
            }
        },

        /**
         * Animation loop
         */
        animatePlayback: function() {
            if (!this.state.isPlaying) return;

            const now = Date.now();
            const elapsed = (now - this.state.startTime) * this.state.playbackSpeed;

            // Find current frame by timestamp
            const frameIndex = this.findFrameIndexByTimestamp(elapsed);

            if (frameIndex >= this.state.frames.length) {
                // End of replay
                this.pause();
                return;
            }

            // Draw frame
            this.drawFrame(frameIndex);

            // Update timeline & time display
            this.updateTimeline();

            // Next frame
            this.state.animationFrameId = requestAnimationFrame(this.animatePlayback.bind(this));
        },

        /**
         * Vykreslit frame
         */
        drawFrame: function(frameIndex) {
            if (frameIndex < 0 || frameIndex >= this.state.frames.length) return;

            const frame = this.state.frames[frameIndex];
            this.state.currentFrameIndex = frameIndex;

            // Clear canvas
            this.state.ctx.clearRect(0, 0, this.state.canvas.width, this.state.canvas.height);

            // Process event
            switch (frame.event_type) {
                case 'mousemove':
                    this.state.cursorX = frame.event_data.x || 0;
                    this.state.cursorY = frame.event_data.y || 0;
                    break;

                case 'click':
                    // Show click animation (ripple effect)
                    this.drawClickAnimation(frame.event_data.x || 0, frame.event_data.y || 0);
                    this.state.cursorX = frame.event_data.x || 0;
                    this.state.cursorY = frame.event_data.y || 0;
                    break;

                case 'scroll':
                    // Scroll indicator (optional - could show scrollbar)
                    this.drawScrollIndicator(frame.event_data.scroll_percent || 0);
                    break;
            }

            // Draw cursor
            this.drawCursor(this.state.cursorX, this.state.cursorY);
        },

        /**
         * Vykreslit kurzor myši
         */
        drawCursor: function(x, y) {
            const ctx = this.state.ctx;

            // Outer circle (shadow)
            ctx.fillStyle = 'rgba(0, 0, 0, 0.2)';
            ctx.beginPath();
            ctx.arc(x + 2, y + 2, 8, 0, Math.PI * 2);
            ctx.fill();

            // Inner circle (grayscale per color policy)
            ctx.fillStyle = '#666';
            ctx.beginPath();
            ctx.arc(x, y, 8, 0, Math.PI * 2);
            ctx.fill();

            // White dot
            ctx.fillStyle = '#fff';
            ctx.beginPath();
            ctx.arc(x, y, 3, 0, Math.PI * 2);
            ctx.fill();
        },

        /**
         * Vykreslit click animaci
         */
        drawClickAnimation: function(x, y) {
            const ctx = this.state.ctx;

            // Ripple effect (grayscale per color policy)
            ctx.strokeStyle = '#888';
            ctx.lineWidth = 3;

            for (let i = 1; i <= 3; i++) {
                ctx.globalAlpha = 1 - (i * 0.3);
                ctx.beginPath();
                ctx.arc(x, y, 20 * i, 0, Math.PI * 2);
                ctx.stroke();
            }

            ctx.globalAlpha = 1.0;
        },

        /**
         * Vykreslit scroll indicator
         */
        drawScrollIndicator: function(scrollPercent) {
            const ctx = this.state.ctx;
            const barWidth = 10;
            const barHeight = this.state.canvas.height;
            const barX = this.state.canvas.width - barWidth - 5;

            // Background
            ctx.fillStyle = 'rgba(200, 200, 200, 0.3)';
            ctx.fillRect(barX, 0, barWidth, barHeight);

            // Filled part (scroll position)
            const filledHeight = (scrollPercent / 100) * barHeight;
            ctx.fillStyle = 'rgba(51, 51, 51, 0.7)';
            ctx.fillRect(barX, 0, barWidth, filledHeight);
        },

        /**
         * Aktualizovat timeline scrubber
         */
        updateTimeline: function() {
            const currentFrame = this.state.frames[this.state.currentFrameIndex];
            if (!currentFrame) return;

            const currentTime = currentFrame.timestamp_offset;
            const totalTime = this.state.frames[this.state.frames.length - 1].timestamp_offset;

            const percent = totalTime > 0 ? (currentTime / totalTime) * 100 : 0;

            // Update scrubber position
            const timeline = document.getElementById(this.config.timelineId);
            if (timeline) {
                timeline.value = percent;
            }

            // Update time display
            const currentTimeEl = document.getElementById(this.config.currentTimeId);
            if (currentTimeEl) {
                currentTimeEl.textContent = this.formatTime(currentTime);
            }
        },

        /**
         * Seek to percent
         */
        seekToPercent: function(percent) {
            if (this.state.frames.length === 0) return;

            const totalTime = this.state.frames[this.state.frames.length - 1].timestamp_offset;
            const targetTime = (percent / 100) * totalTime;

            const frameIndex = this.findFrameIndexByTimestamp(targetTime);
            this.drawFrame(frameIndex);

            // Update time display
            const currentTimeEl = document.getElementById(this.config.currentTimeId);
            if (currentTimeEl) {
                currentTimeEl.textContent = this.formatTime(targetTime);
            }
        },

        /**
         * Set playback speed
         */
        setSpeed: function(speed) {
            const wasPlaying = this.state.isPlaying;

            if (wasPlaying) {
                this.pause();
            }

            this.state.playbackSpeed = speed;

            if (wasPlaying) {
                this.play();
            }
        },

        /**
         * Helper: Find frame index by timestamp
         */
        findFrameIndexByTimestamp: function(timestamp) {
            for (let i = 0; i < this.state.frames.length; i++) {
                if (this.state.frames[i].timestamp_offset >= timestamp) {
                    return i;
                }
            }
            return this.state.frames.length - 1;
        },

        /**
         * Helper: Format time (ms → mm:ss)
         */
        formatTime: function(ms) {
            const totalSeconds = Math.floor(ms / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return `${minutes}:${seconds.toString().padStart(2, '0')}`;
        },

        /**
         * Helper: Get current timestamp
         */
        getCurrentTimestamp: function() {
            if (this.state.currentFrameIndex >= this.state.frames.length) return 0;
            return this.state.frames[this.state.currentFrameIndex].timestamp_offset;
        }
    };

    // Export do global scope
    window.ReplayPlayer = ReplayPlayer;

})();
