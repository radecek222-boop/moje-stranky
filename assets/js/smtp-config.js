/**
 * SMTP Configuration Manager
 * Správa SMTP nastavení pro Email & SMS sekci
 */

let smtpConfigData = {};

// Helper pro zobrazení notifikací
function showNotification(type, message) {
    console.log(`[${type.toUpperCase()}] ${message}`);

    // Vytvořit toast notifikaci
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; top: 20px; right: 20px; padding: 15px 20px;
        border-radius: 4px; color: white; font-family: sans-serif;
        font-size: 14px; z-index: 10000; max-width: 350px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;

    const colors = { success: '#28a745', error: '#dc3545', warning: '#ffc107', info: '#17a2b8' };
    toast.style.backgroundColor = colors[type] || colors.info;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Načíst SMTP konfiguraci
async function loadSmtpConfig() {
    try {
        const response = await fetch('api/control_center_api.php?action=get_smtp_config', {
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
            showNotification('error', 'Chyba při načítání SMTP konfigurace: ' + result.message);
        }
    } catch (error) {
        console.error('Load SMTP config error:', error);
        showNotification('error', 'Chyba při načítání SMTP konfigurace');
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
        showNotification('warning', 'Vyplňte SMTP server');
        return;
    }

    if (!smtpData.smtp_username) {
        showNotification('warning', 'Vyplňte uživatelské jméno');
        return;
    }

    // Disable tlačítko během ukládání
    const saveBtn = document.getElementById('saveSmtpBtn');
    const originalText = saveBtn.textContent;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Ukládání...';

    try {
        const response = await fetch('api/control_center_api.php?action=save_smtp_config', {
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
            showNotification('success', 'SMTP konfigurace byla uložena');
        } else {
            showNotification('error', 'Chyba: ' + result.message);
        }
    } catch (error) {
        console.error('Save SMTP config error:', error);
        showNotification('error', 'Chyba při ukládání SMTP konfigurace');
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
            showNotification('error', 'CSRF token nebyl nalezen - session problém v Safari iframe');
            throw new Error('CSRF token nebyl nalezen');
        }

        const response = await fetch('api/control_center_api.php?action=test_smtp_connection', {
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
            showNotification('error', `CSRF validace selhala: ${JSON.stringify(errorData.debug || {})}`);
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
            showNotification('error', `Server vrátil neplatnou odpověď (ne JSON). Zkontrolujte konzoli pro detaily.`);
            throw new Error(`Invalid JSON response: ${parseError.message}`);
        }

        if (result.status === 'success') {
            showNotification('success', result.message);
        } else {
            showNotification('error', 'Test selhal: ' + result.message);
        }
    } catch (error) {
        console.error('Test SMTP error:', error);
        showNotification('error', 'Chyba při testování SMTP připojení');
    } finally {
        testBtn.disabled = false;
        testBtn.textContent = originalText;
    }
}

// Helper pro získání CSRF tokenu
function getCSRFToken() {
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
