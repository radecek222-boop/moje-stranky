/**
 * Heatmap Renderer - Vizualizace heatmap na HTML5 Canvas
 *
 * Renderuje click a scroll heatmap s barevným gradientem (modrá → červená).
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #6 - Heatmap Engine
 */

(function() {
    'use strict';

    const HeatmapRenderer = {
        canvas: null,
        ctx: null,

        config: {
            radius: 30,              // Radius heatmap bodů (px)
            blur: 20,                // Blur radius
            maxIntensity: 100,       // Max intensity pro gradient
            gradient: {              // Barevný gradient (stop: color)
                0.0: 'rgba(0, 0, 255, 0)',      // Transparent blue
                0.2: 'rgba(0, 255, 255, 0.5)',  // Cyan
                0.4: 'rgba(0, 255, 0, 0.7)',    // Green
                0.6: 'rgba(255, 255, 0, 0.8)',  // Yellow
                0.8: 'rgba(255, 128, 0, 0.9)',  // Orange
                1.0: 'rgba(255, 0, 0, 1)'       // Red
            }
        },

        /**
         * Inicializace rendereru
         */
        init: function(canvasId) {
            this.canvas = document.getElementById(canvasId);
            if (!this.canvas) {
                console.error('[Heatmap Renderer] Canvas nenalezen:', canvasId);
                return false;
            }

            this.ctx = this.canvas.getContext('2d');
            console.log('[Heatmap Renderer] Inicializováno');
            return true;
        },

        /**
         * Renderování click heatmap
         */
        renderClickHeatmap: function(data) {
            if (!this.ctx) {
                console.error('[Heatmap Renderer] Canvas není inicializován');
                return;
            }

            // Vyčistit canvas
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

            if (!data.points || data.points.length === 0) {
                console.warn('[Heatmap Renderer] Žádné click body k zobrazení');
                return;
            }

            console.log('[Heatmap Renderer] Renderuji', data.points.length, 'click bodů');

            // Max intensity pro normalizaci
            const maxIntensity = Math.max(...data.points.map(p => p.count));

            // Vykreslit každý bod
            data.points.forEach(point => {
                const x = (point.x / 100) * this.canvas.width;
                const y = (point.y / 100) * this.canvas.height;
                const intensity = point.count / maxIntensity; // 0-1

                this.drawHeatPoint(x, y, intensity);
            });

            // Aplikovat barevný gradient
            this.applyGradient();

            console.log('[Heatmap Renderer] Click heatmap vykreslena');
        },

        /**
         * Renderování scroll heatmap
         */
        renderScrollHeatmap: function(data) {
            if (!this.ctx) {
                console.error('[Heatmap Renderer] Canvas není inicializován');
                return;
            }

            // Vyčistit canvas
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

            if (!data.buckets || data.buckets.length === 0) {
                console.warn('[Heatmap Renderer] Žádné scroll buckets k zobrazení');
                return;
            }

            console.log('[Heatmap Renderer] Renderuji', data.buckets.length, 'scroll buckets');

            // Výška pro každý bucket (10% stránky = canvas_height / 10)
            const bucketHeight = this.canvas.height / 10;

            data.buckets.forEach(bucket => {
                const y = (bucket.depth / 10) * bucketHeight;
                const intensity = bucket.percentage / 100; // 0-1
                const color = this.getColorForIntensity(intensity);

                // Vykreslit horizontální pruh
                this.ctx.fillStyle = `rgba(${color.r}, ${color.g}, ${color.b}, ${color.a / 255})`;
                this.ctx.fillRect(0, y, this.canvas.width, bucketHeight);
            });

            console.log('[Heatmap Renderer] Scroll heatmap vykreslena');
        },

        /**
         * Vykreslení jednoho heat pointu (radial gradient)
         */
        drawHeatPoint: function(x, y, intensity) {
            const gradient = this.ctx.createRadialGradient(
                x, y, 0,
                x, y, this.config.radius
            );

            const alpha = Math.min(intensity * 0.8, 0.8); // Max 80% opacity
            gradient.addColorStop(0, `rgba(0, 0, 0, ${alpha})`);
            gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');

            this.ctx.fillStyle = gradient;
            this.ctx.fillRect(
                x - this.config.radius,
                y - this.config.radius,
                this.config.radius * 2,
                this.config.radius * 2
            );
        },

        /**
         * Aplikace barevného gradientu na canvas
         */
        applyGradient: function() {
            const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
            const pixels = imageData.data;

            for (let i = 0; i < pixels.length; i += 4) {
                const alpha = pixels[i + 3]; // Alpha channel
                if (alpha > 0) {
                    const intensity = alpha / 255; // 0-1
                    const color = this.getColorForIntensity(intensity);

                    pixels[i] = color.r;     // Red
                    pixels[i + 1] = color.g; // Green
                    pixels[i + 2] = color.b; // Blue
                    pixels[i + 3] = color.a; // Alpha
                }
            }

            this.ctx.putImageData(imageData, 0, 0);
        },

        /**
         * Získá barvu pro danou intenzitu (interpolace gradientu)
         */
        getColorForIntensity: function(intensity) {
            const stops = Object.keys(this.config.gradient).map(Number).sort((a, b) => a - b);

            // Najít dva nejbližší stops
            for (let i = 0; i < stops.length - 1; i++) {
                if (intensity >= stops[i] && intensity <= stops[i + 1]) {
                    const lower = stops[i];
                    const upper = stops[i + 1];
                    const ratio = (intensity - lower) / (upper - lower);

                    const colorLower = this.parseColor(this.config.gradient[lower]);
                    const colorUpper = this.parseColor(this.config.gradient[upper]);

                    return {
                        r: Math.round(colorLower.r + (colorUpper.r - colorLower.r) * ratio),
                        g: Math.round(colorLower.g + (colorUpper.g - colorLower.g) * ratio),
                        b: Math.round(colorLower.b + (colorUpper.b - colorLower.b) * ratio),
                        a: Math.round(colorLower.a + (colorUpper.a - colorLower.a) * ratio)
                    };
                }
            }

            return {r: 0, g: 0, b: 0, a: 0};
        },

        /**
         * Parse rgba() string na object {r, g, b, a}
         */
        parseColor: function(colorString) {
            const match = colorString.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
            if (match) {
                return {
                    r: parseInt(match[1]),
                    g: parseInt(match[2]),
                    b: parseInt(match[3]),
                    a: match[4] ? Math.round(parseFloat(match[4]) * 255) : 255
                };
            }
            return {r: 0, g: 0, b: 0, a: 0};
        },

        /**
         * Vykreslení legendy (color scale)
         */
        renderLegend: function(containerId) {
            const container = document.getElementById(containerId);
            if (!container) {
                console.error('[Heatmap Renderer] Legend container nenalezen:', containerId);
                return;
            }

            // Vyčistit container
            container.innerHTML = '';

            const legendCanvas = document.createElement('canvas');
            legendCanvas.width = 20;
            legendCanvas.height = 200;

            const ctx = legendCanvas.getContext('2d');

            // Gradient od dola nahoru (blue → red)
            const gradient = ctx.createLinearGradient(0, 200, 0, 0);
            Object.entries(this.config.gradient).forEach(([stop, color]) => {
                gradient.addColorStop(parseFloat(stop), color);
            });

            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, 20, 200);

            // Přidat popisky
            const labels = document.createElement('div');
            labels.style.cssText = 'position: absolute; right: 30px; height: 200px; display: flex; flex-direction: column; justify-content: space-between;';

            const labelsText = ['Vysoká', 'Střední', 'Nízká'];
            labelsText.forEach(text => {
                const label = document.createElement('div');
                label.textContent = text;
                label.style.cssText = 'font-size: 12px; color: #333;';
                labels.appendChild(label);
            });

            container.style.position = 'relative';
            container.appendChild(legendCanvas);
            container.appendChild(labels);

            console.log('[Heatmap Renderer] Legenda vykreslena');
        },

        /**
         * Export canvas jako PNG
         */
        exportToPNG: function(filename = 'heatmap.png') {
            if (!this.canvas) {
                console.error('[Heatmap Renderer] Canvas není inicializován');
                return;
            }

            const link = document.createElement('a');
            link.download = filename;
            link.href = this.canvas.toDataURL('image/png');
            link.click();

            console.log('[Heatmap Renderer] Export PNG:', filename);
        }
    };

    // Export do global scope
    window.HeatmapRenderer = HeatmapRenderer;

})();
