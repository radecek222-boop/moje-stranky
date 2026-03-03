/**
 * SMTP Configuration Manager
 * Správa SMTP nastavení pro Email & SMS sekci
 */

let smtpConfigData = {};

// Notifikace pouzivaji centralni wgsToast z utils.js

// Načíst SMTP konfiguraci
async function loadSmtpConfig() {
    try {
        // FIX: Použití správného API endpointu
        const response = await fetch('api/admin.php?action=get_smtp_config', {
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        if (result.status === 'success') {
            smtpConfigData = result.data;
            // Vyplnit formulář
            document.getElementById('smtp_host').value = smtpConfigData.smtp_host || '';
            document.getElementById('smtp_port').value = smtpConfigData.smtp_port || '587';
            document.getElementById('smtp_username').value = smtpConfigData.smtp_username || '';
            document.getElementById('smtp_password').value = smtpConfigData.smtp_password || '';
            document.getElementById('smtp_encryption').value = smtpConfigData.smtp_encryption || 'tls';
            document.getElementById('smtp_from').value = smtpConfigData.smtp_from || 'reklamace@wgs-service.cz';
            document.getElementById('smtp_from_name').value = smtpConfigData.smtp_from_name || 'White Glove Service';
        } else {
            console.error('Chyba při načítání SMTP konfigurace:', result.message);
            wgsToast.error( 'Chyba při načítání SMTP konfigurace: ' + result.message);
        }
    } catch (error) {
        console.error('Load SMTP config error:', error);
        wgsToast.error( 'Chyba při načítání SMTP konfigurace');
    }
}

// Uložit SMTP konfiguraci
async function saveSmtpConfig() {
    // Získat CSRF token PŘED vytvořením objektu
    const csrfToken = await getCSRFToken();

    const smtpData = {
        smtp_host: document.getElementById('smtp_host').value.trim(),
        smtp_port: document.getElementById('smtp_port').value.trim(),
        smtp_username: document.getElementById('smtp_username').value.trim(),
        smtp_password: document.getElementById('smtp_password').value,
        smtp_encryption: document.getElementById('smtp_encryption').value,
        smtp_from: document.getElementById('smtp_from').value.trim(),
        smtp_from_name: document.getElementById('smtp_from_name').value.trim(),
        csrf_token: csrfToken
    };

    // Validace
    if (!smtpData.smtp_host) {
        wgsToast.warning( 'Vyplňte SMTP server');
        return;
    }

    if (!smtpData.smtp_username) {
        wgsToast.warning( 'Vyplňte uživatelské jméno');
        return;
    }

    // Disable tlačítko během ukládání
    const saveBtn = document.getElementById('saveSmtpBtn');
    const originalText = saveBtn.textContent;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Ukládání...';

    try {
        // FIX: Použití správného API endpointu
        const response = await fetch('api/admin.php?action=save_smtp_config', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(smtpData),
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        if (result.status === 'success') {
            wgsToast.success( 'SMTP konfigurace byla uložena');
        } else {
            wgsToast.error( 'Chyba: ' + result.message);
        }
    } catch (error) {
        console.error('Save SMTP config error:', error);
        wgsToast.error( 'Chyba při ukládání SMTP konfigurace');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
    }
}

// Test SMTP připojení
async function testSmtpConnection() {
    const testBtn = document.getElementById('testSmtpBtn');
    const originalText = testBtn.textContent;
    testBtn.disabled = true;
    testBtn.textContent = 'Testování...';

    try {
        // Získat CSRF token PŘED vytvořením objektu (ASYNC!)
        const csrfToken = await getCSRFToken();

        if (!csrfToken) {
            wgsToast.error( 'CSRF token nebyl nalezen - session problém v Safari iframe');
            throw new Error('CSRF token nebyl nalezen');
        }

        // FIX: Použití správného API endpointu
        const response = await fetch('api/admin.php?action=test_smtp_connection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken
            }),
            credentials: 'same-origin'
        });

        // Pokud 403, zkus získat debug info
        if (response.status === 403) {
            const errorData = await response.json();
            console.error('403 Error details:', errorData);
            wgsToast.error( `CSRF validace selhala: ${JSON.stringify(errorData.debug || {})}`);
            throw new Error(`HTTP 403 - ${errorData.message || 'CSRF validation failed'}`);
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        // Získat raw response text
        const responseText = await response.text();

        // Zkusit parsovat JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse failed:', parseError);
            console.error('Full response:', responseText);
            wgsToast.error( `Server vrátil neplatnou odpověď (ne JSON). Zkontrolujte konzoli pro detaily.`);
            throw new Error(`Invalid JSON response: ${parseError.message}`);
        }

        if (result.status === 'success') {
            wgsToast.success( result.message);
        } else {
            wgsToast.error( 'Test selhal: ' + result.message);
        }
    } catch (error) {
        console.error('Test SMTP error:', error);
        wgsToast.error( 'Chyba při testování SMTP připojení');
    } finally {
        testBtn.disabled = false;
        testBtn.textContent = originalText;
    }
}

// Helper pro získání CSRF tokenu
async function getCSRFToken() {
    // Zkusit aktuální dokument
    let metaTag = document.querySelector('meta[name="csrf-token"]');

    // Pokud je skript v iframe, zkusit parent dokument
    if (!metaTag && window.parent && window.parent !== window) {
        try {
            metaTag = window.parent.document.querySelector('meta[name="csrf-token"]');
        } catch (e) {
            console.warn('Cannot access parent document for CSRF token:', e);
        }
    }

    if (!metaTag) {
        console.warn('No CSRF meta tag found');
        return null;
    }

    const token = metaTag.getAttribute('content');

    // Ujistit se, že vracíme string nebo null
    return token && typeof token === 'string' && token.length > 0 ? String(token) : null;
}
