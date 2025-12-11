/**
 * Centralizovaný registr všech admin akcí
 * Načítá se VŽDY v admin.php - řeší problém "hluchých" tlačítek
 * 
 * Akce jsou definovány v include souborech, ale musí být zaregistrovány globálně
 */

// Čekat na Utils
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Utils === 'undefined' || !Utils.registerAction) {
        console.error('[AdminActions] Utils není k dispozici!');
        return;
    }

    console.log('[AdminActions] Registrace centralizovaných akcí...');

    // Globální akce
    Utils.registerAction('reloadPage', () => location.reload());

    // Admin Actions (admin_actions.php)
    Utils.registerAction('executeAction', (el, data) => {
        if (data.id && typeof executeAction === 'function') executeAction(event, data.id);
    });
    Utils.registerAction('completeAction', (el, data) => {
        if (data.id && typeof completeAction === 'function') completeAction(data.id);
    });
    Utils.registerAction('dismissAction', (el, data) => {
        if (data.id && typeof dismissAction === 'function') dismissAction(data.id);
    });
    Utils.registerAction('viewAllWebhooks', () => {
        if (typeof viewAllWebhooks === 'function') viewAllWebhooks();
    });
    Utils.registerAction('setupGitHubWebhook', () => {
        if (typeof setupGitHubWebhook === 'function') setupGitHubWebhook();
    });

    // Configuration (admin_configuration.php)
    Utils.registerAction('saveConfig', (el, data) => {
        if (data.id && data.key && typeof saveConfig === 'function') {
            saveConfig(data.id, data.key);
        }
    });
    Utils.registerAction('onChangeConfig', (el, data) => {
        if (data.id && data.key && typeof saveConfig === 'function') {
            saveConfig(data.id, data.key);
        }
    });

    // Console (admin_console.php)
    Utils.registerAction('runDiagnostics', () => {
        if (typeof runDiagnostics === 'function') runDiagnostics();
    });
    Utils.registerAction('clearConsole', () => {
        if (typeof clearConsole === 'function') clearConsole();
    });
    Utils.registerAction('exportLog', () => {
        if (typeof exportLog === 'function') exportLog();
    });
    Utils.registerAction('clearCacheMaintenance', () => {
        if (typeof clearCacheMaintenance === 'function') clearCacheMaintenance();
    });
    Utils.registerAction('optimizeDatabaseMaintenance', () => {
        if (typeof optimizeDatabaseMaintenance === 'function') optimizeDatabaseMaintenance();
    });
    Utils.registerAction('cleanupLogsMaintenance', () => {
        if (typeof cleanupLogsMaintenance === 'function') cleanupLogsMaintenance();
    });
    Utils.registerAction('archiveLogsMaintenance', () => {
        if (typeof archiveLogsMaintenance === 'function') archiveLogsMaintenance();
    });

    // Diagnostics (admin_diagnostics.php)
    Utils.registerAction('viewLog', (el, data) => {
        if (data.log && typeof viewLog === 'function') viewLog(data.log);
    });
    Utils.registerAction('clearCache', () => {
        if (typeof clearCache === 'function') clearCache();
    });
    Utils.registerAction('archiveLogs', () => {
        if (typeof archiveLogs === 'function') archiveLogs();
    });
    Utils.registerAction('optimizeDatabase', () => {
        if (typeof optimizeDatabase === 'function') optimizeDatabase();
    });
    Utils.registerAction('createBackup', () => {
        if (typeof createBackup === 'function') createBackup();
    });
    Utils.registerAction('viewBackups', () => {
        if (typeof viewBackups === 'function') viewBackups();
    });
    Utils.registerAction('setupActionsSystem', () => {
        if (typeof setupActionsSystem === 'function') setupActionsSystem();
    });

    // Email & SMS (admin_email_sms.php)
    Utils.registerAction('switchEmailSection', (el, data) => {
        if (data.section && typeof switchSection === 'function') switchSection(data.section);
    });
    Utils.registerAction('togglePasswordVisibility', (el, data) => {
        if (data.id && typeof togglePasswordVisibility === 'function') {
            togglePasswordVisibility(parseInt(data.id));
        }
    });
    Utils.registerAction('saveEmailConfig', (el, data) => {
        if (data.id && data.key && typeof saveConfig === 'function') {
            saveConfig(parseInt(data.id), data.key);
        }
    });
    Utils.registerAction('sendTestEmail', () => {
        if (typeof sendTestEmail === 'function') sendTestEmail();
    });
    Utils.registerAction('toggleNotifikaceActive', (el, data) => {
        if (data.id && typeof toggleNotifikaceActive === 'function') {
            toggleNotifikaceActive(data.id, el);
        }
    });
    Utils.registerAction('otevritNotifikace', (el, data) => {
        if (data.id && typeof otevritNotifikace === 'function') otevritNotifikace(data.id);
    });

    // Security (admin_security.php)
    Utils.registerAction('switchSecuritySection', (el, data) => {
        if (data.section && typeof switchSection === 'function') switchSection(data.section);
    });
    Utils.registerAction('otevritPozvanku', () => {
        if (typeof otevritPozvanku === 'function') otevritPozvanku();
    });
    Utils.registerAction('vytvorNovyKlic', () => {
        if (typeof vytvorNovyKlic === 'function') vytvorNovyKlic();
    });
    Utils.registerAction('nactiRegistracniKlice', () => {
        if (typeof nactiRegistracniKlice === 'function') nactiRegistracniKlice();
    });
    Utils.registerAction('togglePasswordVisibilitySimple', (el, data) => {
        if (data.input && typeof togglePasswordVisibilitySimple === 'function') {
            togglePasswordVisibilitySimple(data.input);
        }
    });
    Utils.registerAction('ulozitApiKlic', (el, data) => {
        if (data.key && data.input && typeof ulozitApiKlic === 'function') {
            ulozitApiKlic(data.key, data.input);
        }
    });
    Utils.registerAction('saveSecurityConfig', (el, data) => {
        if (data.id && data.key && typeof saveConfig === 'function') {
            saveConfig(parseInt(data.id), data.key);
        }
    });
    Utils.registerAction('zmenitAdminHeslo', () => {
        if (typeof zmenitAdminHeslo === 'function') zmenitAdminHeslo();
    });
    Utils.registerAction('resetovatUzivatelskeHeslo', () => {
        if (typeof resetovatUzivatelskeHeslo === 'function') resetovatUzivatelskeHeslo();
    });
    Utils.registerAction('nactiAuditLogy', () => {
        if (typeof nactiAuditLogy === 'function') nactiAuditLogy();
    });
    Utils.registerAction('kopirovatDoSchranky', (el, data) => {
        if (data.code && typeof kopirovatDoSchranky === 'function') {
            kopirovatDoSchranky(data.code);
        }
    });
    Utils.registerAction('smazatKlic', (el, data) => {
        if (data.code && typeof smazatKlic === 'function') smazatKlic(data.code);
    });
    Utils.registerAction('odeslatVytvoreniKlice', () => {
        if (typeof odeslatVytvoreniKlice === 'function') odeslatVytvoreniKlice();
    });
    Utils.registerAction('zavritModalVytvorKlic', () => {
        const modal = document.getElementById('modalVytvorKlic');
        if (modal) modal.remove();
    });
    Utils.registerAction('zavritModalPozvanka', () => {
        const modal = document.getElementById('modalPozvanka');
        if (modal) modal.remove();
    });
    Utils.registerAction('odeslatPozvanky', () => {
        if (typeof odeslatPozvanky === 'function') odeslatPozvanky();
    });
    Utils.registerAction('aktualizovatVyber', () => {
        if (typeof aktualizovatVyber === 'function') aktualizovatVyber();
    });

    // Online uživatelé (admin_security.php)
    Utils.registerAction('refreshOnlineUzivatele', () => {
        if (typeof nactiOnlineUzivatele === 'function') nactiOnlineUzivatele();
    });

    // Email management (admin_email_sms.php)
    Utils.registerAction('filterEmaily', (el, data) => {
        if (data.filter && typeof filterEmaily === 'function') {
            filterEmaily(data.filter);
        }
    });
    Utils.registerAction('openEmailDetailModal', (el, data) => {
        if (data.id && typeof openEmailDetailModal === 'function') {
            openEmailDetailModal(data.id);
        }
    });
    Utils.registerAction('closeEmailDetailModal', () => {
        const modal = document.getElementById('emailDetailModal');
        if (modal) modal.style.display = 'none';
    });
    Utils.registerAction('zavritSablonaModal', () => {
        const modal = document.getElementById('sablonaModal');
        if (modal) modal.style.display = 'none';
    });
    Utils.registerAction('editSmsTemplate', (el, data) => {
        if (data.id && typeof editSmsTemplate === 'function') {
            editSmsTemplate(data.id);
        }
    });
    Utils.registerAction('closeSmsModal', () => {
        const modal = document.getElementById('smsModal');
        if (modal) modal.style.display = 'none';
    });
    Utils.registerAction('saveSmsTemplate', (el, data) => {
        if (data.id && typeof saveSmsTemplate === 'function') {
            saveSmsTemplate(data.id);
        }
    });
    Utils.registerAction('otevritModalPrijemcu', (el, data) => {
        if (data.id && typeof otevritModalPrijemcu === 'function') {
            otevritModalPrijemcu(data.id);
        }
    });
    Utils.registerAction('zavritModalPrijemcu', () => {
        const modal = document.getElementById('modalPrijemcu');
        if (modal) modal.style.display = 'none';
    });
    Utils.registerAction('ulozitPrijemce', () => {
        if (typeof ulozitPrijemce === 'function') {
            ulozitPrijemce();
        }
    });
    Utils.registerAction('ulozitSablonu', (el, data) => {
        if (data.id && typeof ulozitSablonu === 'function') {
            ulozitSablonu(data.id);
        }
    });
    Utils.registerAction('upravitEmailKlice', (el, data) => {
        if (data.code && typeof upravitEmailKlice === 'function') {
            upravitEmailKlice(data.code, data.email || '');
        }
    });
    Utils.registerAction('openNewWindow', (el, data) => {
        if (data.url) window.open(data.url, '_blank');
    });
    Utils.registerAction('openSection', (el, data) => {
        if (data.section && typeof openSection === 'function') {
            openSection(data.section);
        }
    });

    // Reklamace Management (admin_reklamace_management.php)
    Utils.registerAction('zmenitStavReklamace', (el, data) => {
        if (data.id && typeof zmenitStavReklamace === 'function') {
            zmenitStavReklamace(data.id, el.value);
        }
    });
    Utils.registerAction('filterReklamace', (el, data) => {
        if (data.filter && typeof filterReklamace === 'function') {
            filterReklamace(data.filter);
        }
    });
    Utils.registerAction('smazatReklamaci', (el, data) => {
        if (data.id && data.cislo && typeof smazatReklamaci === 'function') {
            smazatReklamaci(data.id, data.cislo);
        }
    });
    Utils.registerAction('otevritDetailReklamace', (el, data) => {
        if (data.id && typeof otevritDetailReklamace === 'function') {
            otevritDetailReklamace(data.id);
        }
    });
    Utils.registerAction('zavritDetailModal', () => {
        if (typeof zavritDetailModal === 'function') zavritDetailModal();
    });

    // Users Management - Supervizor (admin.js)
    Utils.registerAction('zobrazDetailUzivatele', (el, data) => {
        if (data.id && typeof zobrazDetailUzivatele === 'function') {
            zobrazDetailUzivatele(data.id);
        }
    });
    Utils.registerAction('zavritDetailUzivatele', () => {
        if (typeof zavritDetailUzivatele === 'function') zavritDetailUzivatele();
    });
    Utils.registerAction('ulozitZmenyUzivatele', (el, data) => {
        if (data.id && typeof ulozitZmenyUzivatele === 'function') {
            ulozitZmenyUzivatele(data.id);
        }
    });
    Utils.registerAction('zmenitHesloUzivatele', (el, data) => {
        if (data.id && typeof zmenitHesloUzivatele === 'function') {
            zmenitHesloUzivatele(data.id);
        }
    });
    Utils.registerAction('prepnoutStatusUzivatele', (el, data) => {
        if (data.id && data.status && typeof prepnoutStatusUzivatele === 'function') {
            prepnoutStatusUzivatele(data.id, data.status);
        }
    });
    Utils.registerAction('otevritSpravuSupervize', (el, data) => {
        if (data.id && typeof otevritSpravuSupervize === 'function') {
            otevritSpravuSupervize(data.id);
        }
    });
    Utils.registerAction('zavritSupervizorOverlay', () => {
        if (typeof zavritSupervizorOverlay === 'function') zavritSupervizorOverlay();
    });
    Utils.registerAction('ulozitSupervizorPrirazeni', (el, data) => {
        if (data.id && typeof ulozitSupervizorPrirazeni === 'function') {
            ulozitSupervizorPrirazeni(data.id);
        }
    });
    Utils.registerAction('deleteUser', (el, data) => {
        if (data.id && typeof deleteUser === 'function') {
            el.closest('[data-action="stopPropagation"]')?.dispatchEvent(new Event('click', { bubbles: false }));
            deleteUser(data.id);
        }
    });

    // Testing (admin_testing.php)
    Utils.registerAction('cleanupTestData', () => {
        if (typeof cleanupTestData === 'function') cleanupTestData();
    });
    Utils.registerAction('viewTestDataInDB', () => {
        if (typeof viewTestDataInDB === 'function') viewTestDataInDB();
    });
    Utils.registerAction('copyResults', () => {
        if (typeof copyResults === 'function') copyResults();
    });

    // Testing Interactive (admin_testing_interactive.php)
    Utils.registerAction('selectRole', (el, data) => {
        if (data.role && typeof selectRole === 'function') selectRole(data.role);
    });
    Utils.registerAction('startInteractiveTest', () => {
        if (typeof startTest === 'function') startTest();
    });
    Utils.registerAction('executeStep1', () => {
        if (typeof executeStep1 === 'function') executeStep1();
    });
    Utils.registerAction('resetInteractiveTest', () => {
        if (typeof resetTest === 'function') resetTest();
    });
    Utils.registerAction('goToInteractiveStep', (el, data) => {
        if (data.step && typeof goToStep === 'function') {
            goToStep(parseInt(data.step));
        }
    });
    Utils.registerAction('cleanupInteractiveTestData', () => {
        if (typeof cleanupTestData === 'function') cleanupTestData();
    });

    // Testing Simulator (admin_testing_simulator.php)
    Utils.registerAction('startTest', () => {
        if (typeof startTest === 'function') startTest();
    });
    Utils.registerAction('resetSimulator', () => {
        if (typeof resetSimulator === 'function') resetSimulator();
    });
    Utils.registerAction('cleanupTest', () => {
        if (typeof cleanupTest === 'function') cleanupTest();
    });
    Utils.registerAction('goToStage', (el, data) => {
        if (data.stage && typeof goToStage === 'function') {
            goToStage(parseInt(data.stage));
        }
    });
    Utils.registerAction('flagError', (el, data) => {
        if (data.stage && data.message && typeof flagError === 'function') {
            flagError(parseInt(data.stage), data.message);
        }
    });

    // ============================================
    // SMTP CONFIGURATION (admin.php)
    // ============================================

    Utils.registerAction('testSmtpConnection', async () => {
        try {
            // Získat CSRF token
            let csrf = null;
            if (typeof getCSRFToken === 'function') {
                csrf = await getCSRFToken();
            } else {
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                csrf = csrfInput ? csrfInput.value : null;
            }

            if (!csrf) {
                throw new Error('CSRF token není dostupný');
            }

            // FIX: Použití správného API endpointu
            const res = await fetch('/api/admin.php?action=test_smtp_connection', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrf
                })
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || data.status === 'error') {
                throw new Error(data.message || `HTTP ${res.status}`);
            }

            // Zobrazit úspěšnou zprávu
            if (typeof wgsToast !== 'undefined' && wgsToast.success) {
                wgsToast.success(data.message || 'SMTP test úspěšný');
            } else {
                alert(data.message || 'SMTP test úspěšný');
            }
        } catch (e) {
            console.error('[SMTP] Test failed:', e);
            if (typeof wgsToast !== 'undefined' && wgsToast.error) {
                wgsToast.error(e?.message || 'Chyba při testu SMTP');
            } else {
                alert('Chyba: ' + (e?.message || 'Chyba při testu SMTP'));
            }
        }
    });

    Utils.registerAction('saveSmtpConfig', async () => {
        try {
            // Získat CSRF token
            let csrf = null;
            if (typeof getCSRFToken === 'function') {
                csrf = await getCSRFToken();
            } else {
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                csrf = csrfInput ? csrfInput.value : null;
            }

            if (!csrf) {
                throw new Error('CSRF token není dostupný');
            }

            // Získat hodnoty z formuláře
            const payload = {
                smtp_host: document.getElementById('smtp_host')?.value?.trim() || '',
                smtp_port: document.getElementById('smtp_port')?.value?.trim() || '587',
                smtp_encryption: document.getElementById('smtp_encryption')?.value || 'tls',
                smtp_username: document.getElementById('smtp_username')?.value?.trim() || '',
                smtp_password: document.getElementById('smtp_password')?.value || '',
                smtp_from: document.getElementById('smtp_from')?.value?.trim() || '',
                smtp_from_name: document.getElementById('smtp_from_name')?.value?.trim() || ''
            };

            // FIX: Použití správného API endpointu
            const res = await fetch('/api/admin.php?action=save_smtp_config', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ...payload,
                    csrf_token: csrf
                })
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || data.status === 'error') {
                throw new Error(data.message || `HTTP ${res.status}`);
            }

            // Zobrazit úspěšnou zprávu
            if (typeof wgsToast !== 'undefined' && wgsToast.success) {
                wgsToast.success(data.message || 'SMTP konfigurace uložena');
            } else {
                alert(data.message || 'SMTP konfigurace uložena');
            }
        } catch (e) {
            console.error('[SMTP] Save failed:', e);
            if (typeof wgsToast !== 'undefined' && wgsToast.error) {
                wgsToast.error(e?.message || 'Chyba při ukládání SMTP');
            } else {
                alert('Chyba: ' + (e?.message || 'Chyba při ukládání SMTP'));
            }
        }
    });

    console.log('[AdminActions] ✓ Všechny akce zaregistrovány');
});
