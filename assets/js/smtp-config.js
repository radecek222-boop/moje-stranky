/**
 * SMTP Configuration Manager
 * Správa SMTP nastavení pro Email & SMS sekci
 */

let smtpConfigData = {};

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
            alert('Chyba při načítání SMTP konfigurace: ' + result.message);
        }
    } catch (error) {
        console.error('Load SMTP config error:', error);
        alert('Chyba při načítání SMTP konfigurace');
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
        alert('Vyplňte SMTP server');
        return;
    }

    if (!smtpData.smtp_username) {
        alert('Vyplňte uživatelské jméno');
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
            alert('✓ SMTP konfigurace byla uložena');
        } else {
            alert('Chyba: ' + result.message);
        }
    } catch (error) {
        console.error('Save SMTP config error:', error);
        alert('Chyba při ukládání SMTP konfigurace');
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
        const response = await fetch('api/control_center_api.php?action=test_smtp_connection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: getCSRFToken()
            }),
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        if (result.status === 'success') {
            alert('✓ ' + result.message);
        } else {
            alert('✗ Test selhal: ' + result.message);
        }
    } catch (error) {
        console.error('Test SMTP error:', error);
        alert('✗ Chyba při testování SMTP připojení');
    } finally {
        testBtn.disabled = false;
        testBtn.textContent = originalText;
    }
}

// Helper pro získání CSRF tokenu
function getCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : null;
}
