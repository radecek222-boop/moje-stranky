/**
 * WGS Cookie Consent Banner
 * Správa souhlasu s cookies pro nepřihlášené uživatele
 *
 * Použití:
 *   - Automaticky se zobrazí pro nepřihlášené uživatele bez souhlasu
 *   - Pro znovuotevření: WGSCookieConsent.zobrazit()
 */

(function() {
    'use strict';

    // Konstanty
    const COOKIE_NAZEV = 'wgs_cookie_consent';
    const COOKIE_ANALYTICS = 'wgs_analytics_consent';
    const COOKIE_FUNKCNI = 'wgs_funkcni_consent';
    const COOKIE_PLATNOST = 365; // dní

    /**
     * Nastavit cookie
     */
    function nastavitCookie(nazev, hodnota, dny) {
        const datum = new Date();
        datum.setTime(datum.getTime() + (dny * 24 * 60 * 60 * 1000));
        const expires = 'expires=' + datum.toUTCString();
        document.cookie = nazev + '=' + hodnota + ';' + expires + ';path=/;SameSite=Lax;Secure';
    }

    /**
     * Získat cookie
     */
    function ziskatCookie(nazev) {
        const jmeno = nazev + '=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i].trim();
            if (c.indexOf(jmeno) === 0) {
                return c.substring(jmeno.length, c.length);
            }
        }
        return null;
    }

    /**
     * Kontrola, zda je uživatel přihlášen
     * Přihlášení uživatelé mají právní základ ve smlouvě
     */
    function jePrihlasen() {
        // Kontrola přes PHP proměnnou nebo session element
        const sessionElement = document.querySelector('[data-user-session]');
        if (sessionElement) return true;

        // Kontrola přes body class
        if (document.body.classList.contains('logged-in')) return true;
        if (document.body.classList.contains('prihlasen')) return true;

        // Kontrola přes hamburger menu (má odkaz na odhlášení)
        const odhlasitLink = document.querySelector('a[href*="logout"]');
        if (odhlasitLink) return true;

        return false;
    }

    /**
     * Vytvořit HTML banner
     */
    function vytvorBanner() {
        const banner = document.createElement('div');
        banner.id = 'wgsCookieConsent';
        banner.className = 'wgs-cookie-consent';
        banner.setAttribute('role', 'dialog');
        banner.setAttribute('aria-modal', 'false');
        banner.setAttribute('aria-labelledby', 'wgs-cookie-titulek');

        banner.innerHTML = `
            <h3 id="wgs-cookie-titulek" class="wgs-cookie-consent-titulek">Cookies</h3>
            <p class="wgs-cookie-consent-text">
                Používáme cookies pro zajištění funkčnosti webu.
                <a href="/cookies.php" target="_blank">Více informací</a>
            </p>

            <div class="wgs-cookie-consent-nastaveni">
                <div class="wgs-cookie-consent-volby">
                    <div class="wgs-cookie-consent-volba">
                        <input type="checkbox" id="cookieNezbytne" checked disabled>
                        <label for="cookieNezbytne">Nezbytné <span class="povinne">(vždy aktivní)</span></label>
                    </div>
                    <div class="wgs-cookie-consent-volba">
                        <input type="checkbox" id="cookieFunkcni" checked>
                        <label for="cookieFunkcni">Funkční (trvalé přihlášení, jazyk)</label>
                    </div>
                    <div class="wgs-cookie-consent-volba">
                        <input type="checkbox" id="cookieAnalyticke">
                        <label for="cookieAnalyticke">Analytické (statistiky návštěvnosti)</label>
                    </div>
                </div>
            </div>

            <div class="wgs-cookie-consent-tlacitka">
                <button type="button" class="wgs-cookie-consent-btn wgs-cookie-consent-btn-odmitnout" id="cookieOdmitnout">
                    Pouze nezbytné
                </button>
                <button type="button" class="wgs-cookie-consent-btn wgs-cookie-consent-btn-nastaveni" id="cookieNastaveni">
                    Nastavení
                </button>
                <button type="button" class="wgs-cookie-consent-btn wgs-cookie-consent-btn-prijmout" id="cookiePrijmout">
                    Přijmout vše
                </button>
            </div>
        `;

        return banner;
    }

    /**
     * Uložit preference
     */
    function ulozitPreference(vsechny) {
        const banner = document.getElementById('wgsCookieConsent');

        let analyticke = false;
        let funkcni = true;

        if (vsechny) {
            analyticke = true;
            funkcni = true;
        } else if (banner) {
            // Získat hodnoty z checkboxů
            const checkAnalyticke = document.getElementById('cookieAnalyticke');
            const checkFunkcni = document.getElementById('cookieFunkcni');

            analyticke = checkAnalyticke ? checkAnalyticke.checked : false;
            funkcni = checkFunkcni ? checkFunkcni.checked : true;
        }

        // Uložit hlavní consent cookie
        nastavitCookie(COOKIE_NAZEV, '1', COOKIE_PLATNOST);

        // Uložit jednotlivé preference
        nastavitCookie(COOKIE_ANALYTICS, analyticke ? '1' : '0', COOKIE_PLATNOST);
        nastavitCookie(COOKIE_FUNKCNI, funkcni ? '1' : '0', COOKIE_PLATNOST);

        // Pokud souhlas s analytikou, načíst tracker
        if (analyticke) {
            nacistAnalytiku();
        }

        // Schovat banner
        skrytBanner();
    }

    /**
     * Odmítnout vše (pouze nezbytné)
     */
    function odmitnoutVse() {
        nastavitCookie(COOKIE_NAZEV, '1', COOKIE_PLATNOST);
        nastavitCookie(COOKIE_ANALYTICS, '0', COOKIE_PLATNOST);
        nastavitCookie(COOKIE_FUNKCNI, '0', COOKIE_PLATNOST);

        skrytBanner();
    }

    /**
     * Schovat banner s animací
     */
    function skrytBanner() {
        const banner = document.getElementById('wgsCookieConsent');
        if (banner) {
            banner.classList.add('skryty');
            setTimeout(() => banner.remove(), 300);
        }
    }

    /**
     * Načíst analytiku dynamicky
     */
    function nacistAnalytiku() {
        // Kontrola, zda již není načteno
        if (document.querySelector('script[src*="tracker"]')) return;

        const script = document.createElement('script');
        script.src = '/assets/js/tracker.min.js';
        script.defer = true;
        document.head.appendChild(script);
    }

    /**
     * Zobrazit nastavení
     */
    function zobrazitNastaveni() {
        const banner = document.getElementById('wgsCookieConsent');
        if (banner) {
            banner.classList.add('rozsireny');
        }
    }

    /**
     * Zobrazit banner
     */
    function zobrazitBanner() {
        // Odstranit existující
        const existujici = document.getElementById('wgsCookieConsent');
        if (existujici) existujici.remove();

        // Vytvořit nový
        const banner = vytvorBanner();
        document.body.appendChild(banner);

        // Event listenery
        document.getElementById('cookiePrijmout')?.addEventListener('click', () => ulozitPreference(true));
        document.getElementById('cookieOdmitnout')?.addEventListener('click', odmitnoutVse);
        document.getElementById('cookieNastaveni')?.addEventListener('click', zobrazitNastaveni);

        // Po rozšíření - tlačítko "Uložit nastavení" místo "Přijmout vše"
        const btnPrijmout = document.getElementById('cookiePrijmout');
        document.getElementById('cookieNastaveni')?.addEventListener('click', () => {
            if (btnPrijmout) {
                btnPrijmout.textContent = 'Uložit nastavení';
                btnPrijmout.onclick = () => ulozitPreference(false);
            }
        });
    }

    /**
     * Inicializace
     */
    function init() {
        // Nespouštět pro přihlášené uživatele
        if (jePrihlasen()) {
            // Pro přihlášené automaticky povolit analytiku (smlouva)
            if (!ziskatCookie(COOKIE_ANALYTICS)) {
                nastavitCookie(COOKIE_ANALYTICS, '1', COOKIE_PLATNOST);
            }
            return;
        }

        // Pokud již existuje souhlas, nespouštět
        if (ziskatCookie(COOKIE_NAZEV)) {
            // Načíst analytiku pokud byl dán souhlas
            if (ziskatCookie(COOKIE_ANALYTICS) === '1') {
                nacistAnalytiku();
            }
            return;
        }

        // Zobrazit banner po načtení stránky
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', zobrazitBanner);
        } else {
            // Malé zpoždění pro lepší UX
            setTimeout(zobrazitBanner, 500);
        }
    }

    // Exportovat pro ruční použití
    window.WGSCookieConsent = {
        zobrazit: zobrazitBanner,
        skryt: skrytBanner,
        maAnalytiku: () => ziskatCookie(COOKIE_ANALYTICS) === '1',
        maFunkcni: () => ziskatCookie(COOKIE_FUNKCNI) !== '0',
        reset: () => {
            document.cookie = COOKIE_NAZEV + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            document.cookie = COOKIE_ANALYTICS + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            document.cookie = COOKIE_FUNKCNI + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            zobrazitBanner();
        }
    };

    // Spustit inicializaci
    init();

})();
