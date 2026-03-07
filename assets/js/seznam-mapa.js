/**
 * seznam-mapa.js
 * Mapové funkce pro detail zákazníka (toggle mapy, trasa)
 * Závisí na: seznam.js (CURRENT_RECORD, WGS_ADDRESS, Utils, t())
 */

function toggleMap() {
  const content = document.getElementById('mapContent');
  const icon = document.getElementById('mapToggleIcon');

  if (content.classList.contains('active')) {
    content.classList.remove('active');
    icon.classList.remove('active');
  } else {
    content.classList.add('active');
    icon.classList.add('active');

    if (!content.dataset.loaded) {
      content.dataset.loaded = 'true';
      loadMapAndRoute();
    }
  }
}

async function loadMapAndRoute() {
  const mapContainer = document.getElementById('mapContainer');

  if (!CURRENT_RECORD) return;

  let customerAddress = Utils.getAddress(CURRENT_RECORD);

  if (!customerAddress || customerAddress === '—') {
    mapContainer.innerHTML = `<div class="map-error">${t('customer_address_not_available')}</div>`;
    return;
  }

  customerAddress = Utils.addCountryToAddress(customerAddress);

  try {
    const origin = encodeURIComponent(WGS_ADDRESS);
    const destination = encodeURIComponent(customerAddress);

    mapContainer.innerHTML = `
      <div style="background: var(--c-bg); padding: 1rem; text-align: center; border: 1px solid var(--c-border);">
        <div style="font-size: 0.8rem; color: var(--c-grey); margin-bottom: 0.8rem; line-height: 1.4;">
          <strong>Z:</strong> WGS<br>
          <strong>Do:</strong> ${customerAddress}<br>
          <strong>Vzdálenost:</strong> <span id="mapDistance">Načítám...</span><br>
          <strong>Čas:</strong> <span id="mapDuration">—</span>
        </div>
        <div style="display: flex; gap: 0.5rem; justify-content: center;">
          <a href="https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destination}&travelmode=driving"
             class="btn" target="_blank" style="text-decoration: none; padding: 0.5rem 1rem; font-size: 0.75rem;">
            Google Maps
          </a>
          <a href="https://waze.com/ul?q=${destination}&navigate=yes"
             class="btn" target="_blank" style="text-decoration: none; padding: 0.5rem 1rem; font-size: 0.75rem;">
            Waze
          </a>
        </div>
      </div>
    `;

    // PERFORMANCE: getDistance() vypnuto kvůli API problémům
    document.getElementById('mapDistance').textContent = '—';
    document.getElementById('mapDuration').textContent = '—';

  } catch (error) {
    logger.error('Chyba při načítání mapy:', error);
    mapContainer.innerHTML = `
      <div class="map-error">
        Nepodařilo se načíst mapu<br>
        <small>${error.message}</small>
      </div>
    `;
  }
}
