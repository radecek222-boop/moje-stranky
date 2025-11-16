/**
 * Vercel Edge Function - Geoapify Proxy
 *
 * Proxy endpoint pro Geoapify API kvůli firewall omezením na hosting serveru.
 * Umožňuje autocomplete a geocoding bez přímého přístupu k api.geoapify.com
 */

export default async function handler(request) {
  // CORS headers
  const headers = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Content-Type': 'application/json',
  };

  // Handle OPTIONS (preflight)
  if (request.method === 'OPTIONS') {
    return new Response(null, { status: 204, headers });
  }

  // Only allow GET
  if (request.method !== 'GET') {
    return new Response(
      JSON.stringify({ error: 'Method not allowed' }),
      { status: 405, headers }
    );
  }

  try {
    const { searchParams } = new URL(request.url);
    const action = searchParams.get('action') || 'autocomplete';
    const text = searchParams.get('text');
    const type = searchParams.get('type') || 'street';
    const country = searchParams.get('country') || 'CZ';
    const limit = searchParams.get('limit') || '5';

    // Validace
    if (!text || text.length < 1) {
      return new Response(
        JSON.stringify({ error: 'Parameter "text" is required' }),
        { status: 400, headers }
      );
    }

    if (text.length > 100) {
      return new Response(
        JSON.stringify({ error: 'Parameter "text" too long (max 100 chars)' }),
        { status: 400, headers }
      );
    }

    // API klíč z environment variable
    const apiKey = process.env.GEOAPIFY_API_KEY;
    if (!apiKey) {
      return new Response(
        JSON.stringify({ error: 'Server configuration error: API key missing' }),
        { status: 500, headers }
      );
    }

    let apiUrl;

    if (action === 'autocomplete') {
      // Autocomplete endpoint
      const params = new URLSearchParams({
        text,
        format: 'geojson',
        limit,
        apiKey,
      });

      if (type === 'street') {
        params.append('type', 'street');
      } else if (type === 'city') {
        params.append('type', 'city');
      }

      if (country) {
        params.append('filter', `countrycode:${country.toLowerCase()}`);
      }

      apiUrl = `https://api.geoapify.com/v1/geocode/autocomplete?${params.toString()}`;

    } else if (action === 'search') {
      // Geocoding endpoint
      const params = new URLSearchParams({
        text,
        format: 'geojson',
        apiKey,
      });

      apiUrl = `https://api.geoapify.com/v1/geocode/search?${params.toString()}`;

    } else {
      return new Response(
        JSON.stringify({ error: `Unknown action: ${action}` }),
        { status: 400, headers }
      );
    }

    // Volání Geoapify API
    const response = await fetch(apiUrl, {
      headers: {
        'User-Agent': 'WGS Service Proxy/1.0',
        'Accept': 'application/json',
      },
    });

    if (!response.ok) {
      return new Response(
        JSON.stringify({
          error: `Geoapify API error: ${response.status}`,
          status: response.status
        }),
        { status: response.status, headers }
      );
    }

    const data = await response.json();

    return new Response(
      JSON.stringify(data),
      { status: 200, headers }
    );

  } catch (error) {
    console.error('Proxy error:', error);
    return new Response(
      JSON.stringify({
        error: 'Internal server error',
        message: error.message
      }),
      { status: 500, headers }
    );
  }
}
