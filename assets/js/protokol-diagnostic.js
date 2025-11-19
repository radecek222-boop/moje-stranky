// Diagnostický patch pro protokol - zjistí proč se volá protokol_api.php 2x
(function() {
  console.log('🔍 PROTOKOL DIAGNOSTIC PATCH LOADED');

  // Intercept všechny fetch požadavky na protokol_api.php
  const originalFetch = window.fetch;
  window.fetch = function(...args) {
    const url = args[0];

    if (typeof url === 'string' && url.includes('protokol_api.php')) {
      console.log('🌐 FETCH INTERCEPTED:', url);
      console.trace('📍 Call stack:');

      // Pokud je to POST, vypiš body
      if (args[1] && args[1].method === 'POST') {
        const body = args[1].body;
        try {
          const parsed = JSON.parse(body);
          console.log('📦 Request body:', parsed);
        } catch (e) {
          console.log('📦 Request body (raw):', body);
        }
      }
    }

    return originalFetch.apply(this, args)
      .then(response => {
        if (typeof url === 'string' && url.includes('protokol_api.php')) {
          console.log('✅ Response status:', response.status);
          if (!response.ok) {
            console.error('❌ Response failed:', response.status, response.statusText);
          }
        }
        return response;
      })
      .catch(err => {
        if (typeof url === 'string' && url.includes('protokol_api.php')) {
          console.error('💥 Fetch error:', err);
        }
        throw err;
      });
  };

  console.log('✅ Fetch interceptor installed');
})();
