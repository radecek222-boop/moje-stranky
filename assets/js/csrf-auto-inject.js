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
                    logger.log('✅ CSRF tokens injected');
                }
            }
        })
        .catch(err => console.error('CSRF Error:', err));
})();
