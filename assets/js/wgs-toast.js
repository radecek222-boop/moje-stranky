/**
 * WGS Toast Notifikace
 * In-app toast s neonovym efektem
 */

(function() {
    'use strict';

    // Konfigurace
    const CONFIG = {
        duration: 5000,  // 5 sekund
        maxToasts: 3     // Max pocet toastu najednou
    };

    // Kontejner pro toasty
    let kontejner = null;

    /**
     * Inicializace kontejneru
     */
    function inicializovatKontejner() {
        if (kontejner) return kontejner;

        kontejner = document.createElement('div');
        kontejner.className = 'wgs-toast-container';
        document.body.appendChild(kontejner);

        return kontejner;
    }

    /**
     * Zobrazit toast
     * @param {string} zprava - Text zpravy
     * @param {object} options - Volitelne nastaveni
     */
    function zobrazitToast(zprava, options = {}) {
        const {
            titulek = 'WGS',
            trvani = CONFIG.duration,
            onClick = null,
            claimId = null
        } = options;

        inicializovatKontejner();

        // Omezit pocet toastu
        while (kontejner.children.length >= CONFIG.maxToasts) {
            const nejstarsi = kontejner.firstChild;
            if (nejstarsi) {
                zavritToast(nejstarsi);
            }
        }

        // Vytvorit toast element
        const toast = document.createElement('div');
        toast.className = 'wgs-toast';
        toast.innerHTML = `
            <div class="wgs-toast-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                </svg>
            </div>
            <div class="wgs-toast-content">
                <div class="wgs-toast-title">${escapeHtml(titulek)}</div>
                <div class="wgs-toast-message">${escapeHtml(zprava)}</div>
            </div>
            <div class="wgs-toast-close">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/>
                </svg>
            </div>
        `;

        // Click handler pro cely toast
        toast.addEventListener('click', function(e) {
            if (e.target.closest('.wgs-toast-close')) {
                zavritToast(toast);
                return;
            }

            if (onClick) {
                onClick();
            } else if (claimId) {
                window.location.href = '/seznam.php?highlight=' + claimId;
            }

            zavritToast(toast);
        });

        // Pridat do kontejneru
        kontejner.appendChild(toast);

        // Automaticky zavrit po urcite dobe
        if (trvani > 0) {
            setTimeout(() => {
                zavritToast(toast);
            }, trvani);
        }

        console.log('[Toast] Zobrazen:', zprava);

        return toast;
    }

    /**
     * Zavrit toast s animaci
     */
    function zavritToast(toast) {
        if (!toast || toast.classList.contains('hiding')) return;

        toast.classList.add('hiding');

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    /**
     * Escape HTML pro bezpecnost
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Zavrit vsechny toasty
     */
    function zavritVsechny() {
        if (!kontejner) return;

        Array.from(kontejner.children).forEach(toast => {
            zavritToast(toast);
        });
    }

    // Export do globalniho objektu
    window.WGSToast = {
        zobrazit: zobrazitToast,
        zavrit: zavritToast,
        zavritVsechny: zavritVsechny
    };

    console.log('[Toast] WGS Toast inicializovan');

})();
