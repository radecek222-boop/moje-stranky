/**
 * Admin Orbit Drag - Interaktivní rotace planetárního systému
 * Myš/prst taháním roztáčí planety dokola
 */

(function() {
    'use strict';

    const orbit = document.querySelector('.admin-orbit');
    const planetContent = document.querySelectorAll('.planet-content');

    if (!orbit) return;

    let isRotating = false;
    let currentRotation = 0;
    let lastAngle = 0;
    let velocity = 0;
    let lastTime = 0;
    let animationFrame = null;

    // Vypočítat úhel mezi středem orbity a pozicí kurzoru/prstu
    function getAngle(clientX, clientY) {
        const rect = orbit.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;

        const deltaX = clientX - centerX;
        const deltaY = clientY - centerY;

        return Math.atan2(deltaY, deltaX) * (180 / Math.PI);
    }

    // Aplikovat rotaci
    function applyRotation(angle) {
        currentRotation = angle;
        orbit.style.transform = `rotate(${angle}deg)`;

        // Counter-rotate obsah planet aby text zůstal horizontální
        planetContent.forEach(content => {
            content.style.transform = `rotate(${-angle}deg)`;
        });
    }

    // Momentum efekt - pokračuje v točení když pustíš
    function applyMomentum() {
        if (Math.abs(velocity) < 0.1) {
            velocity = 0;
            return;
        }

        currentRotation += velocity;
        applyRotation(currentRotation);

        // Friction - zpomalování
        velocity *= 0.95;

        animationFrame = requestAnimationFrame(applyMomentum);
    }

    // Mouse události
    function onMouseDown(e) {
        // Pokud klikneš na planetu, nerotuj (umožni klik)
        if (e.target.closest('.admin-planet')) {
            return;
        }

        isRotating = true;
        lastAngle = getAngle(e.clientX, e.clientY);
        lastTime = Date.now();
        velocity = 0;

        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
        }

        e.preventDefault();
    }

    function onMouseMove(e) {
        if (!isRotating) return;

        const currentAngle = getAngle(e.clientX, e.clientY);
        const deltaAngle = currentAngle - lastAngle;
        const currentTime = Date.now();
        const deltaTime = currentTime - lastTime;

        // Normalizace úhlu přes 360° hranici
        let normalizedDelta = deltaAngle;
        if (Math.abs(deltaAngle) > 180) {
            normalizedDelta = deltaAngle > 0 ? deltaAngle - 360 : deltaAngle + 360;
        }

        currentRotation += normalizedDelta;
        applyRotation(currentRotation);

        // Výpočet rychlosti pro momentum
        if (deltaTime > 0) {
            velocity = normalizedDelta / deltaTime * 16; // normalizace na 60fps
        }

        lastAngle = currentAngle;
        lastTime = currentTime;

        e.preventDefault();
    }

    function onMouseUp() {
        if (!isRotating) return;

        isRotating = false;

        // Spustit momentum efekt
        if (Math.abs(velocity) > 0.5) {
            applyMomentum();
        }
    }

    // Touch události pro mobil
    function onTouchStart(e) {
        // Pokud klikneš na planetu, nerotuj
        if (e.target.closest('.admin-planet')) {
            return;
        }

        if (e.touches.length !== 1) return;

        const touch = e.touches[0];
        isRotating = true;
        lastAngle = getAngle(touch.clientX, touch.clientY);
        lastTime = Date.now();
        velocity = 0;

        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
        }

        e.preventDefault();
    }

    function onTouchMove(e) {
        if (!isRotating || e.touches.length !== 1) return;

        const touch = e.touches[0];
        const currentAngle = getAngle(touch.clientX, touch.clientY);
        const deltaAngle = currentAngle - lastAngle;
        const currentTime = Date.now();
        const deltaTime = currentTime - lastTime;

        // Normalizace úhlu
        let normalizedDelta = deltaAngle;
        if (Math.abs(deltaAngle) > 180) {
            normalizedDelta = deltaAngle > 0 ? deltaAngle - 360 : deltaAngle + 360;
        }

        currentRotation += normalizedDelta;
        applyRotation(currentRotation);

        if (deltaTime > 0) {
            velocity = normalizedDelta / deltaTime * 16;
        }

        lastAngle = currentAngle;
        lastTime = currentTime;

        e.preventDefault();
    }

    function onTouchEnd() {
        if (!isRotating) return;

        isRotating = false;

        // Momentum
        if (Math.abs(velocity) > 0.5) {
            applyMomentum();
        }
    }

    // Event listenery
    orbit.addEventListener('mousedown', onMouseDown);
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);

    orbit.addEventListener('touchstart', onTouchStart, { passive: false });
    document.addEventListener('touchmove', onTouchMove, { passive: false });
    document.addEventListener('touchend', onTouchEnd);

    // Cleanup při opuštění stránky
    window.addEventListener('beforeunload', () => {
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
        }
    });

    // Inicializace - nastav counter-rotate pro text
    planetContent.forEach(content => {
        content.style.transform = 'rotate(0deg)';
    });

    console.log('🌍 Admin Orbit Drag aktivován - taháním roztočíš planety!');
})();
