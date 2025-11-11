/**
 * Control Center Modal System
 * Univerzální modal pro všechny sekce Control Center
 */

class ControlCenterModal {
    constructor() {
        this.overlay = null;
        this.modal = null;
        this.modalBody = null;
        this.currentSection = null;
        this.init();
    }

    init() {
        // Vytvoř modal strukturu pokud neexistuje
        if (!document.getElementById('ccModal')) {
            this.createModal();
        }

        this.overlay = document.getElementById('ccModalOverlay');
        this.modal = document.getElementById('ccModal');
        this.modalBody = document.getElementById('ccModalBody');

        // Event listeners
        this.setupEventListeners();
    }

    createModal() {
        const modalHTML = `
            <!-- Modal Overlay -->
            <div class="cc-modal-overlay" id="ccModalOverlay"></div>

            <!-- Modal -->
            <div class="cc-modal" id="ccModal">
                <!-- Header -->
                <div class="cc-modal-header">
                    <div class="cc-modal-title-section">
                        <button class="cc-modal-back-btn" id="ccModalBackBtn">
                            <span class="cc-modal-back-icon">←</span>
                            <span>Zpět do menu</span>
                        </button>
                        <div>
                            <h2 class="cc-modal-title" id="ccModalTitle">Loading...</h2>
                            <div class="cc-modal-subtitle" id="ccModalSubtitle"></div>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="cc-modal-body" id="ccModalBody">
                    <div class="cc-modal-loading">
                        <div class="cc-modal-spinner"></div>
                        <div class="cc-modal-loading-text">Načítání...</div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    setupEventListeners() {
        // Tlačítko Zpět
        const backBtn = document.getElementById('ccModalBackBtn');
        if (backBtn) {
            backBtn.addEventListener('click', () => this.close());

            // Touch support
            backBtn.addEventListener('touchend', (e) => {
                e.preventDefault();
                this.close();
            });
        }

        // Zavřít na overlay click (volitelné)
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.close());

            // Touch support pro overlay
            this.overlay.addEventListener('touchend', (e) => {
                if (e.target === this.overlay) {
                    this.close();
                }
            });
        }

        // ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal?.classList.contains('active')) {
                this.close();
            }
        });

        // Prevent pull-to-refresh when modal is open (mobile)
        let startY = 0;
        this.modalBody?.addEventListener('touchstart', (e) => {
            startY = e.touches[0].pageY;
        }, { passive: true });

        this.modalBody?.addEventListener('touchmove', (e) => {
            const y = e.touches[0].pageY;
            const scrollTop = this.modalBody.scrollTop;

            // Prevent pull-to-refresh at top
            if (scrollTop === 0 && y > startY) {
                e.preventDefault();
            }
        }, { passive: false });
    }

    open(options = {}) {
        const {
            title = 'Control Center',
            subtitle = '',
            section = null,
            url = null,
            content = null,
            type = 'iframe', // 'iframe', 'html', 'ajax'
            testMode = false // Nový parametr pro test mode
        } = options;

        this.currentSection = section;

        // Nastav title
        document.getElementById('ccModalTitle').textContent = title;
        document.getElementById('ccModalSubtitle').textContent = subtitle;

        // Zobraz loading
        this.showLoading();

        // Zobraz modal
        this.overlay.classList.add('active');
        this.modal.classList.add('active');
        document.body.classList.add('cc-modal-open');

        // Pokud je test mode, přidej speciální styling
        if (testMode) {
            document.body.classList.add('cc-modal-test-mode');

            // Přidej test badge do headeru
            const header = this.modal.querySelector('.cc-modal-header');
            if (header && !header.querySelector('.cc-modal-test-badge')) {
                const badge = document.createElement('div');
                badge.className = 'cc-modal-test-badge';
                badge.textContent = 'TEST MODE';
                header.style.position = 'relative';
                header.appendChild(badge);
            }
        }

        // Načti obsah
        if (type === 'iframe' && url) {
            this.loadIframe(url);
        } else if (type === 'html' && content) {
            this.loadHTML(content);
        } else if (type === 'ajax' && url) {
            this.loadAjax(url);
        } else {
            this.showError('Neplatný typ obsahu');
        }

    }

    close() {
        this.overlay.classList.remove('active');
        this.modal.classList.remove('active');
        document.body.classList.remove('cc-modal-open');
        document.body.classList.remove('cc-modal-test-mode');

        // Clear content po zavření
        setTimeout(() => {
            this.modalBody.innerHTML = this.getLoadingHTML();
            this.currentSection = null;

            // Remove test badge if exists
            const badge = this.modal.querySelector('.cc-modal-test-badge');
            if (badge) {
                badge.remove();
            }
        }, 300);
    }

    showLoading() {
        this.modalBody.innerHTML = this.getLoadingHTML();
    }

    getLoadingHTML() {
        return `
            <div class="cc-modal-loading">
                <div class="cc-modal-spinner"></div>
                <div class="cc-modal-loading-text">Načítání...</div>
            </div>
        `;
    }

    showError(message = 'Nastala chyba při načítání') {
        this.modalBody.innerHTML = `
            <div class="cc-modal-error">
                <div class="cc-modal-error-icon">⚠️</div>
                <div class="cc-modal-error-title">Chyba načítání</div>
                <div class="cc-modal-error-message">${message}</div>
                <button class="cc-modal-error-btn" onclick="ccModal.close()">
                    Zavřít
                </button>
            </div>
        `;
    }

    loadIframe(url) {
        // Přidej parametr pro embed mode
        const embedUrl = url + (url.includes('?') ? '&' : '?') + 'embed=1';

        // Create iframe element safely
        const iframe = document.createElement('iframe');
        iframe.className = 'cc-modal-iframe';
        iframe.src = embedUrl;
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms');
        iframe.setAttribute('title', 'Control Center Content');

        // Add event listeners (not inline handlers)
        iframe.addEventListener('load', () => {
            // Iframe loaded successfully
        });

        iframe.addEventListener('error', () => {
            this.showError('Nepodařilo se načíst stránku');
        });

        // Clear and append
        this.modalBody.innerHTML = '';
        this.modalBody.appendChild(iframe);
    }

    loadHTML(htmlContent) {
        this.modalBody.innerHTML = htmlContent;
    }

    async loadAjax(url) {
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const html = await response.text();
            this.modalBody.innerHTML = html;

        } catch (error) {
            this.showError('Chyba při načítání obsahu');
        }
    }

    // Helper metody pro rychlé otevření specifických sekcí
    openStatistics() {
        this.open({
            title: 'Statistiky',
            subtitle: 'Analýza a reporty reklamací',
            section: 'statistics',
            url: 'statistiky.php',
            type: 'iframe'
        });
    }

    openAnalytics() {
        this.open({
            title: 'Web Analytics',
            subtitle: 'Komplexní analýza návštěvnosti a chování na webu',
            section: 'analytics',
            url: 'analytics.php',
            type: 'iframe'
        });
    }

    openKeys() {
        this.open({
            title: 'Registrační klíče',
            subtitle: 'Správa přístupových klíčů pro registraci uživatelů',
            section: 'keys',
            url: 'admin.php?tab=keys',
            type: 'iframe'
        });
    }

    openUsers() {
        this.open({
            title: 'Uživatelé',
            subtitle: 'Správa všech uživatelů systému',
            section: 'users',
            url: 'admin.php?tab=users',
            type: 'iframe'
        });
    }

    openOnline() {
        this.open({
            title: 'Online uživatelé',
            subtitle: 'Real-time přehled aktivních uživatelů',
            section: 'online',
            url: 'admin.php?tab=online',
            type: 'iframe'
        });
    }

    openNotifications() {
        this.open({
            title: 'Email & SMS notifikace',
            subtitle: 'Správa email šablon a SMS nastavení',
            section: 'notifications',
            url: 'admin.php?tab=notifications',
            type: 'iframe'
        });
    }

    openClaims() {
        this.open({
            title: 'Reklamace',
            subtitle: 'Správa všech reklamací',
            section: 'claims',
            url: 'seznam.php',
            type: 'iframe'
        });
    }

    openTools() {
        this.open({
            title: 'Diagnostika',
            subtitle: 'Nástroje, migrace a system health',
            section: 'tools',
            url: 'admin.php?tab=tools',
            type: 'iframe'
        });
    }

    openTesting() {
        this.open({
            title: 'Interaktivní testovací prostředí',
            subtitle: 'Vizuální průchod workflow s diagnostikou',
            section: 'testing',
            url: 'admin.php?tab=control_center_testing_interactive',
            type: 'iframe',
            testMode: true // Aktivovat test mode s 80% velikostí
        });
    }
}

// Vytvoř globální instanci - MUSÍ být window.ccModal pro inline event handlers
window.ccModal = new ControlCenterModal();

// Export pro použití v jiných skriptech
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ControlCenterModal;
}
