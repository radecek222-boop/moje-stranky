/**
 * Get CSRF token (async version)
 * @returns {Promise<string|null>} - CSRF token or null if failed
 */
async function getCSRFToken() {
    if (window.csrfTokenCache) return window.csrfTokenCache;

    try {
        const response = await fetch('/app/controllers/get_csrf_token.php', {
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (data.status === 'success' && data.token) {
            window.csrfTokenCache = data.token;
            return data.token;
        }
        return null;
    } catch (err) {
        console.error('CSRF Error:', err);
        return null;
    }
}

// Auto-inject CSRF tokens into forms on page load
(function() {
    fetch('/app/controllers/get_csrf_token.php', {
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                // Cache the token for async access
                window.csrfTokenCache = data.token;

                // Inject into forms
                document.querySelectorAll('form').forEach(form => {
                    let input = form.querySelector('input[name="csrf_token"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'csrf_token';
                        form.appendChild(input);
                    }
                    input.value = data.token;
                });
                if (typeof logger !== 'undefined') {
                    logger.log('CSRF tokens injected');
                }
            } else {
                // FIX 2: Error handling - token nebyl úspěšně získán
                console.error('CSRF token fetch failed:', data);
                showCsrfError('Bezpečnostní token se nepodařilo načíst. Zkontrolujte připojení.');
            }
        })
        .catch(err => {
            // FIX 2: Error handling - network error nebo server error
            console.error('CSRF Error:', err);
            showCsrfError('Bezpečnostní token se nepodařilo načíst. Obnovte stránku.');
        });
})();

// FIX 2: Helper funkce pro zobrazení CSRF chyby
function showCsrfError(message) {
    // Pokud už existuje warning, neukládat další
    if (document.getElementById('csrf-warning')) return;

    const warning = document.createElement('div');
    warning.id = 'csrf-warning';
    warning.className = 'csrf-error-notification';
    warning.innerHTML = `
        <strong>Bezpečnostní upozornění</strong>
        <p>${message}</p>
        <button data-action="reloadPage">Obnovit stránku</button>
    `;

    // Inline styly pro univerzální zobrazení
    warning.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10003;
        background: linear-gradient(135deg, #333 0%, #555 100%);
        color: white;
        padding: 20px 25px;
        border-radius: 8px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        max-width: 400px;
        width: 90%;
        text-align: center;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        animation: slideDown 0.3s ease-out;
    `;

    // Styl pro strong element
    const strong = warning.querySelector('strong');
    if (strong) {
        strong.style.cssText = 'display: block; font-size: 1.1rem; margin-bottom: 8px;';
    }

    // Styl pro paragraph
    const p = warning.querySelector('p');
    if (p) {
        p.style.cssText = 'margin: 10px 0 15px 0; font-size: 0.95rem; line-height: 1.4;';
    }

    // Styl pro tlačítko
    const button = warning.querySelector('button');
    if (button) {
        button.style.cssText = `
            background: white;
            color: #dc3545;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        `;
        button.onmouseover = () => button.style.transform = 'scale(1.05)';
        button.onmouseout = () => button.style.transform = 'scale(1)';
    }

    // Přidat CSS animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
    `;
    document.head.appendChild(style);

    document.body.appendChild(warning);

    if (typeof logger !== 'undefined') {
        logger.error('CSRF warning displayed to user');
    }
}

// ============================================
// ACTION REGISTRY - Step 115
// ============================================
if (typeof Utils !== 'undefined' && Utils.registerAction) {
    Utils.registerAction('reloadPage', () => {
        location.reload();
    });
}
