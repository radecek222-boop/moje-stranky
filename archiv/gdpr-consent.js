/**
 * GDPR Consent Banner
 *
 * Cookie consent banner s granular options.
 * Integruje se s tracker-v2.js pro kontrolu souhlasu před trackingem.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #13 - GDPR Compliance Tools
 */

(function() {
    'use strict';

    // ========================================
    // KONFIGURACE
    // ========================================
    const CONFIG = {
        localStorageKey: 'gdpr_consent',
        fingerprintKey: 'wgs_fingerprint',
        apiEndpoint: '/api/gdpr_api.php',
        bannerDelay: 1000, // Zobrazit banner po 1s
        defaultConsent: {
            analytics: false,
            marketing: false,
            functional: true  // Vždy povinné
        }
    };

    // ========================================
    // STATE
    // ========================================
    let currentConsent = null;
    let fingerprintId = null;
    let csrfToken = null;
    let bannerVisible = false;

    // ========================================
    // INICIALIZACE
    // ========================================
    function init() {
        // Načíst fingerprint z localStorage
        fingerprintId = localStorage.getItem(CONFIG.fingerprintKey);

        if (!fingerprintId) {
            console.warn('[GDPR] Fingerprint not found - consent banner disabled');
            return;
        }

        // Načíst CSRF token
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
            csrfToken = csrfInput.value;
        } else {
            console.warn('[GDPR] CSRF token not found');
        }

        // Načíst uložený consent
        const savedConsent = localStorage.getItem(CONFIG.localStorageKey);

        if (savedConsent) {
            try {
                currentConsent = JSON.parse(savedConsent);
                console.log('[GDPR] Consent loaded from localStorage:', currentConsent);
            } catch (e) {
                console.error('[GDPR] Failed to parse saved consent:', e);
                currentConsent = null;
            }
        }

        // Pokud consent neexistuje, zobrazit banner po delay
        if (!currentConsent) {
            setTimeout(() => {
                zobrazBanner();
            }, CONFIG.bannerDelay);
        } else {
            // Consent už existuje - synchronizovat s API (fire and forget)
            synchronizujConsent();
        }

        // Vystavit globální API
        window.GDPRConsent = {
            hasAnalyticsConsent: hasAnalyticsConsent,
            hasMarketingConsent: hasMarketingConsent,
            hasFunctionalConsent: hasFunctionalConsent,
            getConsent: getConsent,
            openCustomize: zobrazCustomizeModal,
            withdrawConsent: withdrawConsent
        };
    }

    // ========================================
    // ZOBRAZENÍ BANNERU
    // ========================================
    function zobrazBanner() {
        if (bannerVisible) return;

        const banner = document.createElement('div');
        banner.id = 'gdpr-consent-banner';
        banner.innerHTML = `
            <div class="gdpr-banner-overlay"></div>
            <div class="gdpr-banner-content">
                <div class="gdpr-banner-inner">
                    <h3>Používáme cookies</h3>
                    <p>
                        Používáme cookies pro zajištění funkčnosti webu, analytiku návštěvnosti a marketing.
                        Funkční cookies jsou nezbytné pro provoz webu a nelze je odmítnout.
                    </p>
                    <p>
                        <a href="/gdpr-portal.php" target="_blank">Více informací o ochraně osobních údajů</a>
                    </p>

                    <div class="gdpr-banner-buttons">
                        <button id="gdpr-accept-all" class="gdpr-btn gdpr-btn-primary">
                            Přijmout vše
                        </button>
                        <button id="gdpr-reject-all" class="gdpr-btn gdpr-btn-secondary">
                            Odmítnout vše
                        </button>
                        <button id="gdpr-customize" class="gdpr-btn gdpr-btn-tertiary">
                            Upravit preference
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Přidat CSS
        const style = document.createElement('style');
        style.textContent = `
            #gdpr-consent-banner {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            }

            .gdpr-banner-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
            }

            .gdpr-banner-content {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                background: white;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.2);
                animation: slideUp 0.3s ease-out;
            }

            @keyframes slideUp {
                from {
                    transform: translateY(100%);
                }
                to {
                    transform: translateY(0);
                }
            }

            .gdpr-banner-inner {
                max-width: 1200px;
                margin: 0 auto;
                padding: 30px 20px;
            }

            .gdpr-banner-inner h3 {
                margin: 0 0 15px 0;
                font-size: 24px;
                color: #333333;
            }

            .gdpr-banner-inner p {
                margin: 0 0 10px 0;
                font-size: 14px;
                line-height: 1.6;
                color: #333;
            }

            .gdpr-banner-inner a {
                color: #333333;
                text-decoration: underline;
            }

            .gdpr-banner-buttons {
                display: flex;
                gap: 10px;
                margin-top: 20px;
                flex-wrap: wrap;
            }

            .gdpr-btn {
                padding: 12px 24px;
                border: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }

            .gdpr-btn-primary {
                background: #333333;
                color: white;
            }

            .gdpr-btn-primary:hover {
                background: #1a300d;
            }

            .gdpr-btn-secondary {
                background: #6c757d;
                color: white;
            }

            .gdpr-btn-secondary:hover {
                background: #5a6268;
            }

            .gdpr-btn-tertiary {
                background: white;
                color: #333333;
                border: 2px solid #333333;
            }

            .gdpr-btn-tertiary:hover {
                background: #f5f7fa;
            }

            /* Mobile */
            @media (max-width: 768px) {
                .gdpr-banner-buttons {
                    flex-direction: column;
                }

                .gdpr-btn {
                    width: 100%;
                }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(banner);

        bannerVisible = true;

        // Event listeners
        document.getElementById('gdpr-accept-all').addEventListener('click', acceptAll);
        document.getElementById('gdpr-reject-all').addEventListener('click', rejectAll);
        document.getElementById('gdpr-customize').addEventListener('click', showCustomize);
    }

    // ========================================
    // ACCEPT ALL
    // ========================================
    function acceptAll() {
        const consent = {
            analytics: true,
            marketing: true,
            functional: true,
            timestamp: Date.now()
        };

        ulozConsent(consent);
    }

    // ========================================
    // REJECT ALL
    // ========================================
    function rejectAll() {
        const consent = {
            analytics: false,
            marketing: false,
            functional: true,  // Vždy povinné
            timestamp: Date.now()
        };

        ulozConsent(consent);
    }

    // ========================================
    // SHOW CUSTOMIZE MODAL
    // ========================================
    function showCustomize() {
        // Zavřít banner
        skryjBanner();

        // Zobrazit customize modal
        const modal = document.createElement('div');
        modal.id = 'gdpr-customize-modal';
        modal.innerHTML = `
            <div class="gdpr-modal-overlay"></div>
            <div class="gdpr-modal-content">
                <div class="gdpr-modal-inner">
                    <h3>Upravit preference cookies</h3>

                    <div class="gdpr-option">
                        <div class="gdpr-option-header">
                            <label>
                                <input type="checkbox" id="gdpr-functional" checked disabled>
                                <strong>Funkční cookies (povinné)</strong>
                            </label>
                        </div>
                        <p class="gdpr-option-desc">
                            Nezbytné pro základní funkčnost webu. Nelze odmítnout.
                        </p>
                    </div>

                    <div class="gdpr-option">
                        <div class="gdpr-option-header">
                            <label>
                                <input type="checkbox" id="gdpr-analytics">
                                <strong>Analytické cookies</strong>
                            </label>
                        </div>
                        <p class="gdpr-option-desc">
                            Pomáhají nám pochopit, jak návštěvníci používají web, abychom mohli vylepšovat jeho funkčnost.
                        </p>
                    </div>

                    <div class="gdpr-option">
                        <div class="gdpr-option-header">
                            <label>
                                <input type="checkbox" id="gdpr-marketing">
                                <strong>Marketingové cookies</strong>
                            </label>
                        </div>
                        <p class="gdpr-option-desc">
                            Používají se k zobrazování relevantních reklam a měření účinnosti marketingových kampaní.
                        </p>
                    </div>

                    <div class="gdpr-modal-buttons">
                        <button id="gdpr-save-preferences" class="gdpr-btn gdpr-btn-primary">
                            Uložit preference
                        </button>
                        <button id="gdpr-cancel" class="gdpr-btn gdpr-btn-secondary">
                            Zrušit
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Přidat CSS
        const style = document.createElement('style');
        style.textContent = `
            #gdpr-customize-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            }

            .gdpr-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(3px);
            }

            .gdpr-modal-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border-radius: 8px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                max-width: 600px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                animation: fadeIn 0.3s ease-out;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translate(-50%, -45%);
                }
                to {
                    opacity: 1;
                    transform: translate(-50%, -50%);
                }
            }

            .gdpr-modal-inner {
                padding: 30px;
            }

            .gdpr-modal-inner h3 {
                margin: 0 0 20px 0;
                font-size: 22px;
                color: #333333;
            }

            .gdpr-option {
                margin-bottom: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 4px;
            }

            .gdpr-option-header label {
                display: flex;
                align-items: center;
                cursor: pointer;
            }

            .gdpr-option-header input[type="checkbox"] {
                margin-right: 10px;
                width: 18px;
                height: 18px;
                cursor: pointer;
            }

            .gdpr-option-header input[type="checkbox"]:disabled {
                cursor: not-allowed;
                opacity: 0.6;
            }

            .gdpr-option-desc {
                margin: 10px 0 0 28px;
                font-size: 13px;
                color: #666;
                line-height: 1.5;
            }

            .gdpr-modal-buttons {
                display: flex;
                gap: 10px;
                margin-top: 30px;
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(modal);

        // Event listeners
        document.getElementById('gdpr-save-preferences').addEventListener('click', saveCustomPreferences);
        document.getElementById('gdpr-cancel').addEventListener('click', () => {
            document.getElementById('gdpr-customize-modal').remove();
            // Zobrazit banner znovu
            zobrazBanner();
        });
    }

    // ========================================
    // SAVE CUSTOM PREFERENCES
    // ========================================
    function saveCustomPreferences() {
        const analytics = document.getElementById('gdpr-analytics').checked;
        const marketing = document.getElementById('gdpr-marketing').checked;

        const consent = {
            analytics: analytics,
            marketing: marketing,
            functional: true,
            timestamp: Date.now()
        };

        ulozConsent(consent);

        // Zavřít modal
        const modal = document.getElementById('gdpr-customize-modal');
        if (modal) {
            modal.remove();
        }
    }

    // ========================================
    // ULOŽIT CONSENT
    // ========================================
    async function ulozConsent(consent) {
        // Uložit do localStorage
        currentConsent = consent;
        localStorage.setItem(CONFIG.localStorageKey, JSON.stringify(consent));

        console.log('[GDPR] Consent saved:', consent);

        // Zavřít banner
        skryjBanner();

        // Synchronizovat s API
        await odeslatConsentNaAPI(consent);

        // Pokud analytics consent = true, inicializovat tracker
        if (consent.analytics && window.WGSTracker) {
            console.log('[GDPR] Analytics consent granted - tracker enabled');
            // Tracker už běží, ale může se restartovat
        }

        // Reload stránky pro aplikování změn (optional)
        // window.location.reload();
    }

    // ========================================
    // ODESLAT CONSENT NA API
    // ========================================
    async function odeslatConsentNaAPI(consent) {
        if (!fingerprintId || !csrfToken) {
            console.warn('[GDPR] Cannot send consent to API - missing fingerprint or CSRF token');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'record_consent');
            formData.append('csrf_token', csrfToken);
            formData.append('fingerprint_id', fingerprintId);
            formData.append('consent_analytics', consent.analytics ? 1 : 0);
            formData.append('consent_marketing', consent.marketing ? 1 : 0);
            formData.append('consent_functional', 1); // Vždy 1

            const response = await fetch(CONFIG.apiEndpoint, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                console.log('[GDPR] Consent synced to API');
            } else {
                console.error('[GDPR] Failed to sync consent:', result.message);
            }
        } catch (error) {
            console.error('[GDPR] Error syncing consent:', error);
        }
    }

    // ========================================
    // SYNCHRONIZOVAT EXISTUJÍCÍ CONSENT
    // ========================================
    async function synchronizujConsent() {
        if (!currentConsent) return;

        // Fire and forget - neblokovat stránku
        odeslatConsentNaAPI(currentConsent).catch(err => {
            console.error('[GDPR] Background consent sync failed:', err);
        });
    }

    // ========================================
    // SKRÝT BANNER
    // ========================================
    function skryjBanner() {
        const banner = document.getElementById('gdpr-consent-banner');
        if (banner) {
            banner.style.animation = 'slideDown 0.3s ease-out';
            setTimeout(() => {
                banner.remove();
            }, 300);
        }

        bannerVisible = false;
    }

    // ========================================
    // PUBLIC API - GETTERS
    // ========================================
    function hasAnalyticsConsent() {
        return currentConsent ? currentConsent.analytics === true : false;
    }

    function hasMarketingConsent() {
        return currentConsent ? currentConsent.marketing === true : false;
    }

    function hasFunctionalConsent() {
        return true; // Vždy true - funkční cookies jsou povinné
    }

    function getConsent() {
        return currentConsent ? { ...currentConsent } : null;
    }

    // ========================================
    // WITHDRAW CONSENT
    // ========================================
    function zobrazCustomizeModal() {
        showCustomize();
    }

    async function withdrawConsent() {
        // Smazat z localStorage
        localStorage.removeItem(CONFIG.localStorageKey);
        currentConsent = null;

        console.log('[GDPR] Consent withdrawn');

        // Odeslat withdraw na API
        if (!fingerprintId || !csrfToken) {
            console.warn('[GDPR] Cannot withdraw consent via API - missing credentials');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'withdraw_consent');
            formData.append('csrf_token', csrfToken);
            formData.append('fingerprint_id', fingerprintId);

            const response = await fetch(CONFIG.apiEndpoint, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                console.log('[GDPR] Consent withdrawn from API');
            } else {
                console.error('[GDPR] Failed to withdraw consent:', result.message);
            }
        } catch (error) {
            console.error('[GDPR] Error withdrawing consent:', error);
        }

        // Zobrazit banner znovu
        setTimeout(() => {
            zobrazBanner();
        }, 500);
    }

    // ========================================
    // AUTO-INIT
    // ========================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
