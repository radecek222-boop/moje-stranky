/**
 * Herní zvuky - Web Audio API
 * Generuje zvuky programově (nepotřebuje externí soubory)
 */
(function() {
    'use strict';

    // Audio context
    let audioCtx = null;
    let zapnuto = localStorage.getItem('hry_zvuky') !== 'false';

    // Inicializace Audio Context (musí být po user interakci)
    function initAudio() {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    }

    // Přehrát tón
    function prehratTon(frekvence, trvani, typ = 'sine', hlasitost = 0.3) {
        if (!zapnuto) return;

        initAudio();
        if (!audioCtx) return;

        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        oscillator.type = typ;
        oscillator.frequency.setValueAtTime(frekvence, audioCtx.currentTime);

        gainNode.gain.setValueAtTime(hlasitost, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + trvani);

        oscillator.start(audioCtx.currentTime);
        oscillator.stop(audioCtx.currentTime + trvani);
    }

    // Přehrát šum (pro efekty)
    function prehratSum(trvani, hlasitost = 0.2) {
        if (!zapnuto) return;

        initAudio();
        if (!audioCtx) return;

        const bufferSize = audioCtx.sampleRate * trvani;
        const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
        const data = buffer.getChannelData(0);

        for (let i = 0; i < bufferSize; i++) {
            data[i] = Math.random() * 2 - 1;
        }

        const source = audioCtx.createBufferSource();
        const gainNode = audioCtx.createGain();

        source.buffer = buffer;
        source.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        gainNode.gain.setValueAtTime(hlasitost, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + trvani);

        source.start(audioCtx.currentTime);
    }

    // Definice zvuků
    const zvuky = {
        // Kliknutí / výběr
        klik: function() {
            prehratTon(800, 0.05, 'square', 0.15);
        },

        // Položení karty
        karta: function() {
            prehratTon(400, 0.08, 'triangle', 0.2);
            setTimeout(() => prehratTon(500, 0.05, 'triangle', 0.1), 30);
        },

        // Táhnutí karty z balíčku
        tah: function() {
            prehratTon(300, 0.1, 'sine', 0.15);
        },

        // Výhra
        vyhra: function() {
            prehratTon(523, 0.15, 'sine', 0.3); // C5
            setTimeout(() => prehratTon(659, 0.15, 'sine', 0.3), 150); // E5
            setTimeout(() => prehratTon(784, 0.3, 'sine', 0.3), 300); // G5
        },

        // Prohra
        prohra: function() {
            prehratTon(400, 0.2, 'sawtooth', 0.2);
            setTimeout(() => prehratTon(300, 0.2, 'sawtooth', 0.2), 200);
            setTimeout(() => prehratTon(200, 0.4, 'sawtooth', 0.2), 400);
        },

        // Chyba / nelze
        chyba: function() {
            prehratTon(200, 0.15, 'square', 0.2);
        },

        // Ping / notifikace
        ping: function() {
            prehratTon(880, 0.1, 'sine', 0.25);
        },

        // Úspěch / bod
        bod: function() {
            prehratTon(600, 0.08, 'sine', 0.25);
            setTimeout(() => prehratTon(800, 0.08, 'sine', 0.2), 80);
        },

        // Exploze / zničení
        exploze: function() {
            prehratSum(0.3, 0.3);
            prehratTon(100, 0.2, 'sawtooth', 0.2);
        },

        // Odraz míčku
        odraz: function() {
            prehratTon(440 + Math.random() * 200, 0.05, 'square', 0.15);
        },

        // Pohyb figurky
        pohyb: function() {
            prehratTon(350, 0.06, 'triangle', 0.15);
        },

        // Sebrání figurky
        sebrani: function() {
            prehratTon(500, 0.1, 'sine', 0.25);
            setTimeout(() => prehratTon(700, 0.1, 'sine', 0.2), 100);
        },

        // Level up
        levelUp: function() {
            prehratTon(440, 0.1, 'sine', 0.3);
            setTimeout(() => prehratTon(554, 0.1, 'sine', 0.3), 100);
            setTimeout(() => prehratTon(659, 0.1, 'sine', 0.3), 200);
            setTimeout(() => prehratTon(880, 0.2, 'sine', 0.3), 300);
        },

        // Ztráta života
        ztrataZivota: function() {
            prehratTon(300, 0.15, 'sawtooth', 0.25);
            setTimeout(() => prehratTon(200, 0.25, 'sawtooth', 0.2), 150);
        },

        // Chat zpráva
        chat: function() {
            prehratTon(600, 0.05, 'sine', 0.1);
        }
    };

    // Veřejné API
    window.HryZvuky = {
        // Přehrát zvuk
        prehrat: function(nazev) {
            if (zvuky[nazev]) {
                zvuky[nazev]();
            }
        },

        // Zapnout/vypnout zvuky
        prepnout: function() {
            zapnuto = !zapnuto;
            localStorage.setItem('hry_zvuky', zapnuto);
            if (zapnuto) {
                initAudio();
                this.prehrat('ping');
            }
            return zapnuto;
        },

        // Zjistit stav
        jeZapnuto: function() {
            return zapnuto;
        },

        // Nastavit stav
        nastavit: function(stav) {
            zapnuto = stav;
            localStorage.setItem('hry_zvuky', zapnuto);
        },

        // Inicializace (volat po user interakci)
        init: function() {
            initAudio();
        }
    };

    // Inicializovat při první interakci
    document.addEventListener('click', function initOnClick() {
        initAudio();
        document.removeEventListener('click', initOnClick);
    }, { once: true });

})();
