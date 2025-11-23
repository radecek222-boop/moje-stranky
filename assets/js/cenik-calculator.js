/**
 * Kalkulačka ceny - Ceník služeb
 * @version 1.0.0
 */

(function() {
    'use strict';

    // ========================================
    // GLOBÁLNÍ PROMĚNNÉ
    // ========================================
    const WORKSHOP_COORDS = { lat: 50.056725, lon: 14.577261 }; // Běchovice
    const TRANSPORT_RATE = 0.28; // €/km

    let calculableServices = [];
    let selectedServices = [];
    let currentDistance = 0;
    let currentTransportCost = 0;

    // ========================================
    // INIT KALKULAČKY
    // ========================================
    window.addEventListener('DOMContentLoaded', () => {
        initKalkulacka();
    });

    async function initKalkulacka() {
        try {
            // Načíst kalkulovatelné služby z API
            const response = await fetch('/api/pricing_api.php?action=list');
            const result = await response.json();

            if (result.status === 'success') {
                // Filtrovat pouze kalkulovatelné položky (is_calculable = 1)
                calculableServices = result.items.filter(item => item.is_calculable == 1);
                // Čekat až se zobrazí sekce služeb
                initAddressAutocomplete();
            } else {
                console.error('[Kalkulačka] Chyba:', result.message);
            }
        } catch (error) {
            console.error('[Kalkulačka] Síťová chyba:', error);
        }
    }

    // ========================================
    // AUTOCOMPLETE ADRES
    // ========================================
    function initAddressAutocomplete() {
        const input = document.getElementById('calc-address');
        const dropdown = document.getElementById('address-suggestions');

        if (!input) return;

        let debounceTimer;

        input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            const query = e.target.value.trim();

            if (query.length < 3) {
                dropdown.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => hledatAdresy(query, dropdown), 300);
        });
    }

    async function hledatAdresy(query, dropdown) {
        try {
            const response = await fetch('/api/geocode_proxy.php?text=' + encodeURIComponent(query));
            const data = await response.json();

            if (data.features && data.features.length > 0) {
                zobrazitNavrhy(data.features, dropdown);
            } else {
                dropdown.style.display = 'none';
            }
        } catch (error) {
            console.error('[Kalkulačka] Chyba autocomplete:', error);
        }
    }

    function zobrazitNavrhy(features, dropdown) {
        dropdown.innerHTML = '';

        features.slice(0, 5).forEach(feature => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.textContent = feature.properties.formatted || feature.properties.address_line1 || 'Neznámá adresa';

            item.addEventListener('click', () => {
                const coords = feature.geometry.coordinates;
                vybratAdresu(feature.properties.formatted || feature.properties.address_line1, coords[1], coords[0]);
                dropdown.style.display = 'none';
            });

            dropdown.appendChild(item);
        });

        dropdown.style.display = 'block';
    }

    // ========================================
    // VÝBĚR ADRESY & VÝPOČET VZDÁLENOSTI
    // ========================================
    async function vybratAdresu(formatted, lat, lon) {
        document.getElementById('calc-address').value = formatted;

        try {
            // Zavolat OSRM routing API přes proxy
            const url = '/api/geocode_proxy.php?action=route&start_lat=' + WORKSHOP_COORDS.lat +
                        '&start_lon=' + WORKSHOP_COORDS.lon + '&end_lat=' + lat + '&end_lon=' + lon;
            const response = await fetch(url);
            const data = await response.json();

            if (data.routes && data.routes.length > 0) {
                const distanceMeters = data.routes[0].distance;
                const distanceKm = Math.round(distanceMeters / 1000);

                currentDistance = distanceKm;
                currentTransportCost = Math.round(distanceKm * 2 * TRANSPORT_RATE * 100) / 100; // Tam a zpět

                // Zobrazit výsledek
                document.getElementById('distance-value').textContent = distanceKm;
                document.getElementById('transport-cost').textContent = currentTransportCost.toFixed(2);
                document.getElementById('distance-result').style.display = 'block';

                // Zobrazit výběr služeb
                zobrazitVyberSluzeb();
            } else {
                alert('Nepodařilo se vypočítat vzdálenost. Zkuste jinou adresu.');
            }
        } catch (error) {
            console.error('[Kalkulačka] Chyba výpočtu vzdálenosti:', error);
            alert('Chyba při výpočtu vzdálenosti');
        }
    }

    // ========================================
    // ZOBRAZIT VÝBĚR SLUŽEB
    // ========================================
    function zobrazitVyberSluzeb() {
        const servicesSection = document.getElementById('services-selection');
        const checkboxesContainer = document.getElementById('services-checkboxes');

        checkboxesContainer.innerHTML = '';

        calculableServices.forEach(service => {
            const div = document.createElement('div');
            div.className = 'service-checkbox-item';
            div.dataset.serviceId = service.id;

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = 'service-' + service.id;
            checkbox.value = service.id;
            checkbox.addEventListener('change', () => aktualizovatVyber());

            const label = document.createElement('label');
            label.htmlFor = 'service-' + service.id;
            label.innerHTML = '<span>' + service.service_name + '</span><span class="service-price">' +
                              service.price_from + ' ' + service.price_unit + '</span>';

            div.appendChild(checkbox);
            div.appendChild(label);

            // Klik na celý div = toggle checkbox
            div.addEventListener('click', (e) => {
                if (e.target !== checkbox) {
                    checkbox.checked = !checkbox.checked;
                    aktualizovatVyber();
                }
            });

            checkboxesContainer.appendChild(div);
        });

        servicesSection.style.display = 'block';
        aktualizovatVyber();
    }

    // ========================================
    // AKTUALIZOVAT VÝBĚR SLUŽEB
    // ========================================
    function aktualizovatVyber() {
        selectedServices = [];
        let totalServices = 0;

        document.querySelectorAll('.service-checkbox-item input[type="checkbox"]').forEach(checkbox => {
            const parent = checkbox.closest('.service-checkbox-item');

            if (checkbox.checked) {
                parent.classList.add('selected');

                const service = calculableServices.find(s => s.id == checkbox.value);
                if (service) {
                    selectedServices.push(service);
                    totalServices += parseFloat(service.price_from);
                }
            } else {
                parent.classList.remove('selected');
            }
        });

        // Zobrazit cenový souhrn
        if (selectedServices.length > 0) {
            document.getElementById('services-total').textContent = totalServices.toFixed(2) + ' €';
            document.getElementById('transport-total').textContent = currentTransportCost.toFixed(2) + ' €';
            document.getElementById('grand-total').innerHTML = '<strong>' + (totalServices + currentTransportCost).toFixed(2) + ' €</strong>';

            document.getElementById('price-summary').style.display = 'block';
            document.getElementById('reset-btn').style.display = 'inline-block';
        } else {
            document.getElementById('price-summary').style.display = 'none';
        }
    }

    // ========================================
    // RESETOVAT KALKULAČKU
    // ========================================
    window.resetovatKalkulacku = function() {
        document.getElementById('calc-address').value = '';
        document.getElementById('distance-result').style.display = 'none';
        document.getElementById('services-selection').style.display = 'none';
        document.getElementById('price-summary').style.display = 'none';
        document.getElementById('reset-btn').style.display = 'none';
        document.getElementById('address-suggestions').style.display = 'none';

        selectedServices = [];
        currentDistance = 0;
        currentTransportCost = 0;
    };

})();
