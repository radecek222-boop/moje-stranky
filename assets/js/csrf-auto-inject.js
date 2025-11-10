(function() {
    fetch('/app/controllers/get_csrf_token.php', {
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
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
                logger.log('✅ CSRF tokens injected');
            }
        })
        .catch(err => console.error('CSRF Error:', err));
})();
