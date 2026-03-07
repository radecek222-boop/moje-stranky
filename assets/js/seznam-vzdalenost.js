/**
 * seznam-vzdalenost.js
 * Výpočet vzdálenosti, zobrazení termínů s trasami, CacheManager
 * Závisí na: seznam.js (fetchCsrfToken, logger, wgsToast, WGS_ADDRESS, WGS_COORDS, t())
 */

// === VÝPOČET VZDÁLENOSTI ===
async function getDistance(fromAddress, toAddress) {
  const cacheKey = `${fromAddress}|${toAddress}`;

  if (DISTANCE_CACHE[cacheKey]) {
    return DISTANCE_CACHE[cacheKey];
  }

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);

    const csrfToken = await fetchCsrfToken();

    const response = await fetch('/app/controllers/get_distance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        origin: fromAddress,
        destination: toAddress,
        csrf_token: csrfToken
      }),
      signal: controller.signal
    });

    clearTimeout(timeoutId);

    if (response.ok) {
      const data = await response.json();
      if ((data.status === 'success' || data.success === true) && data.distance) {
        const result = {
          km: (data.distance.value / 1000).toFixed(1),
          text: data.distance.text,
          duration: data.duration ? data.duration.text : null
        };
        DISTANCE_CACHE[cacheKey] = result;
        CacheManager.save();
        return result;
      }
    }
  } catch (error) {
    // Tiché selhání - vzdálenost není kritická funkce
  }

  return null;
}

// === BATCH VÝPOČET VZDÁLENOSTÍ ===
async function getDistancesBatch(pairs) {
  const promises = pairs.map(pair => getDistance(pair.from, pair.to));
  return await Promise.all(promises);
}

// === ZOBRAZENÍ TERMÍNŮ S VZDÁLENOSTMI ===
async function showDayBookingsWithDistances(date) {
  const distanceContainer = document.getElementById('distanceInfo');
  const bookingsContainer = document.getElementById('dayBookings');

  if (!distanceContainer || !bookingsContainer) return;

  // Inicializace cache pokud neexistuje
  if (!Array.isArray(WGS_DATA_CACHE)) {
    WGS_DATA_CACHE = [];
  }

  // Filtrovat ostatní termíny na stejný den (ne aktuální reklamace)
  const bookings = WGS_DATA_CACHE.filter(rec =>
    rec.termin === date && rec.id !== CURRENT_RECORD?.id
  );

  // Pokud je vybraný čas, vložit CURRENT_RECORD do seznamu bookings na správné místo
  if (SELECTED_TIME) {
    const currentBooking = {
      ...CURRENT_RECORD,
      cas_navstevy: SELECTED_TIME,
      isNew: true  // Označit jako nový záznam
    };
    bookings.push(currentBooking);
  }

  // Seřadit podle času (včetně nového zákazníka)
  bookings.sort((a, b) => {
    const timeA = a.cas_navstevy || '00:00';
    const timeB = b.cas_navstevy || '00:00';
    return timeA.localeCompare(timeB);
  });

  // Získat adresu aktuální reklamace
  let currentAddress = Utils.getAddress(CURRENT_RECORD);

  if (!currentAddress || currentAddress === '—') {
    distanceContainer.innerHTML = '';
    bookingsContainer.innerHTML = '';
    return;
  }
  
  currentAddress = Utils.addCountryToAddress(currentAddress);
  
  const cacheKey = `${WGS_ADDRESS}|${currentAddress}`;
  const isCached = DISTANCE_CACHE[cacheKey] !== undefined;
  
  // PERFORMANCE FIX: Zobrazit loading a vypočítat vzdálenost asynchronně
  if (!isCached) {
    distanceContainer.innerHTML = `<div style="text-align: center; color: var(--c-grey); font-size: 0.7rem; padding: 0.5rem;">${t('loading')}</div>`;
  }

  if (bookings.length === 0) {
    // PERFORMANCE FIX: Neblokovat UI - vzdálenost načíst asynchronně
    getDistance(WGS_ADDRESS, currentAddress)
      .then(distToCustomer => {
        if (distToCustomer) {
          distanceContainer.innerHTML = `
            <div class="distance-info-panel">
              <div class="distance-info-title">Informace o trase</div>
              <div class="distance-stats">
                <div class="distance-stat">
                  <div class="distance-stat-label">Vzdálenost</div>
                  <div class="distance-stat-value">${distToCustomer.km} <span class="distance-stat-unit">km</span></div>
                </div>
                <div class="distance-stat">
                  <div class="distance-stat-label">Čas jízdy</div>
                  <div class="distance-stat-value">${distToCustomer.duration || '—'}</div>
                </div>
              </div>
              <div class="route-info">
                <div class="route-item">
                  <div class="route-item-left">
                    <span>WGS Sídlo</span>
                    <span class="route-arrow">→</span>
                    <span>${Utils.getCustomerName(CURRENT_RECORD)}</span>
                  </div>
                  <span class="route-distance">${distToCustomer.km} km</span>
                </div>
              </div>
            </div>
          `;
        } else {
          distanceContainer.innerHTML = '';
        }
      })
      .catch(err => {
        logger.error('Chyba při výpočtu vzdálenosti:', err);
        distanceContainer.innerHTML = ''; // Skrýt loading při chybě
      });

    bookingsContainer.innerHTML = '';
    return;
  }
  
  const distancePairs = [];
  let fromAddr = WGS_ADDRESS;

  for (let i = 0; i < bookings.length; i++) {
    const booking = bookings[i];
    let toAddr = Utils.getAddress(booking);

    if (!toAddr || toAddr === '—') continue;
    toAddr = Utils.addCountryToAddress(toAddr);

    distancePairs.push({ from: fromAddr, to: toAddr, booking: booking, isNew: booking.isNew || false });
    fromAddr = toAddr;
  }

  // Přidat zpáteční cestu do WGS sídla z poslední adresy
  if (distancePairs.length > 0) {
    distancePairs.push({
      from: fromAddr, // Poslední adresa
      to: WGS_ADDRESS,
      booking: null,
      isReturn: true
    });
  }

  const distances = await getDistancesBatch(distancePairs.map(p => ({ from: p.from, to: p.to })));
  
  let totalKm = 0;
  let routes = [];
  
  for (let i = 0; i < distancePairs.length; i++) {
    const pair = distancePairs[i];
    const dist = distances[i];

    if (!dist) continue;

    totalKm += parseFloat(dist.km);

    let fromName, toName, time;

    if (pair.isReturn) {
      // Zpáteční cesta do WGS sídla
      fromName = Utils.getCustomerName(distancePairs[i-1].booking);
      toName = 'WGS Sídlo';
      time = '—';
    } else {
      // Normální úsek trasy
      fromName = i === 0 ? 'WGS Sídlo' : Utils.getCustomerName(distancePairs[i-1].booking);
      toName = Utils.getCustomerName(pair.booking);
      time = pair.booking?.cas_navstevy || '—';
    }

    routes.push({
      from: fromName,
      to: toName,
      time: time,
      km: dist.km,
      isNew: pair.isNew,
      isReturn: pair.isReturn || false
    });
  }
  
  let routesHtml = routes.map(r => `
    <div class="route-item ${r.isNew ? 'new-customer' : ''} ${r.isReturn ? 'return-route' : ''}">
      <div class="route-item-left">
        <span>${r.from}</span>
        <span class="route-arrow">→</span>
        <span>${r.to}${r.isReturn ? ' (zpět)' : ''} ${r.time !== '—' ? '(' + r.time + ')' : ''}</span>
      </div>
      <span class="route-distance">${r.km} km</span>
    </div>
  `).join('');
  
  distanceContainer.innerHTML = `
    <div class="distance-info-panel">
      <div class="distance-info-title">Trasa pro ${date}</div>
      <div class="distance-stats">
        <div class="distance-stat">
          <div class="distance-stat-label">Celkem za den</div>
          <div class="distance-stat-value">${totalKm.toFixed(1)} <span class="distance-stat-unit">km</span></div>
        </div>
        <div class="distance-stat">
          <div class="distance-stat-label">Počet návštěv</div>
          <div class="distance-stat-value">${bookings.length}</div>
        </div>
      </div>
      <div class="route-info">
        ${routesHtml}
      </div>
    </div>
  `;
  
  // Zobrazit existující termíny (bez nového zákazníka)
  const existingBookings = bookings.filter(b => !b.isNew);

  if (existingBookings.length > 0) {
    let html = '<div class="day-bookings" style="margin-top: 0.5rem;"><h4>Termíny v tento den:</h4>';
    existingBookings.forEach(b => {
      const customerName = Utils.getCustomerName(b);
      html += `
        <div class="booking-item" data-action="showBookingDetail" data-id="${b.id}">
          <strong>${b.cas_navstevy || '—'}</strong> — ${customerName}
          <span style="opacity:.7">(${Utils.getProduct(b)})</span>
        </div>`;
    });
    html += '</div>';
    bookingsContainer.innerHTML = html;
  } else {
    bookingsContainer.innerHTML = '';
  }
}

function showBookingDetail(bookingOrId) {
  let booking;
  if (typeof bookingOrId === 'string' || typeof bookingOrId === 'number') {
    booking = WGS_DATA_CACHE.find(x => x.id == bookingOrId || x.reklamace_id == bookingOrId);
    if (!booking) {
      wgsToast.error(t('record_not_found'));
      return;
    }
  } else {
    booking = bookingOrId;
  }

  const customerName = Utils.getCustomerName(booking);
  const address = Utils.getAddress(booking);
  const product = Utils.getProduct(booking);
  const description = booking.popis_problemu || '—';
  
  const content = `
    ${ModalManager.createHeader('Detail obsazeného termínu', '<p style="color: var(--c-error); font-weight: 600;">Tento termín je již obsazen</p>', 'showCalendarBack')}
    
    <div class="modal-body">
      <div style="padding: 1.5rem; background: rgba(139, 0, 0, 0.05); border: 2px solid var(--c-error); margin-bottom: 1.5rem;">
        <div class="info-grid" style="font-family: 'Poppins', sans-serif;">
          <div class="info-label">Zákazník:</div>
          <div class="info-value"><strong>${customerName}</strong></div>
          
          <div class="info-label">Termín:</div>
          <div class="info-value"><strong>${formatDate(booking.termin || '')} v ${booking.cas_navstevy || '—'}</strong></div>
          
          <div class="info-label">Adresa:</div>
          <div class="info-value">${address}</div>
          
          <div class="info-label">Produkt:</div>
          <div class="info-value">${product}</div>
          
          <div class="info-label">Popis:</div>
          <div class="info-value" style="line-height: 1.6;">${description}</div>
        </div>
      </div>
    </div>

  `;

  ModalManager.show(content);
}

function renderTimeGrid() {
  const t = document.getElementById('timeGrid');
  t.innerHTML = '';

  // Najít všechny zákazníky na daný den, seřazené podle času
  const dayBookings = WGS_DATA_CACHE
    .filter(rec => rec.termin === SELECTED_DATE && rec.cas_navstevy && rec.id !== CURRENT_RECORD?.id)
    .sort((a, b) => a.cas_navstevy.localeCompare(b.cas_navstevy));

  const occupiedTimes = {};
  dayBookings.forEach(rec => {
    const customerName = Utils.getCustomerName(rec);
    occupiedTimes[rec.cas_navstevy] = {
      zakaznik: customerName,
      model: Utils.getProduct(rec),
      record: rec
    };
  });

  // Časový rozsah: 8:00 - 19:00 (místo původního 7:00 - 20:30)
  for (let h = 8; h <= 19; h++) {
    for (const mm of [0, 30]) {
      const time = `${String(h).padStart(2, '0')}:${mm === 0 ? '00' : '30'}`;
      const el = document.createElement('div');
      el.className = 'time-slot';

      if (occupiedTimes[time]) {
        el.classList.add('occupied');
        el.title = `${occupiedTimes[time].zakaznik} — ${occupiedTimes[time].model}`;
      }

      el.textContent = time;
      el.onclick = async () => {
        SELECTED_TIME = time;
        document.querySelectorAll('.time-slot').forEach(x => x.classList.remove('selected'));
        el.classList.add('selected');

        // PERFORMANCE: Zobrazit termín bez vzdálenosti
        const displayEl = document.getElementById('selectedDateDisplay');
        if (displayEl) {
          displayEl.textContent = `Termín: ${SELECTED_DATE} — ${SELECTED_TIME}`;
          displayEl.style.display = 'flex';
        }
        const btnUlozit = document.getElementById('btnUlozitTerminHore');
        if (btnUlozit) btnUlozit.style.display = 'inline-block';

        // Zobrazit/skrýt varování o kolizi
        const warningEl = document.getElementById('collisionWarning');

        if (occupiedTimes[time] && warningEl) {
          // Základní info o kolizi
          warningEl.innerHTML = `KOLIZE: ${occupiedTimes[time].zakaznik} — ${occupiedTimes[time].model}<br><span style="font-size: 0.75rem; opacity: 0.8;">Počítám vzdálenost...</span>`;
          warningEl.style.display = 'block';

          // Spočítat vzdálenost mezi aktuálním a kolizním zákazníkem
          const kolizniZakaznik = occupiedTimes[time].record;
          const currentAddress = Utils.getAddress(CURRENT_RECORD);
          const kolizniAddress = Utils.getAddress(kolizniZakaznik);

          if (currentAddress && currentAddress !== '—' && kolizniAddress && kolizniAddress !== '—') {
            try {
              // Najít pozici v denním plánu
              const kolizniIndex = dayBookings.findIndex(b => b.id === kolizniZakaznik.id);
              const predchozi = kolizniIndex > 0 ? dayBookings[kolizniIndex - 1] : null;
              const nasledujici = kolizniIndex < dayBookings.length - 1 ? dayBookings[kolizniIndex + 1] : null;

              // Vzdálenost mezi aktuálním a kolizním
              const vzdalenost = await getDistance(
                Utils.addCountryToAddress(currentAddress),
                Utils.addCountryToAddress(kolizniAddress)
              );

              let infoHtml = `KOLIZE: ${occupiedTimes[time].zakaznik} — ${occupiedTimes[time].model}`;

              if (vzdalenost) {
                infoHtml += `<div class="collision-distance-box">Vzdálenost mezi zákazníky: <strong>${vzdalenost.km} km</strong></div>`;
              }

              // Pokud jsou další zákazníci na ten den, ukázat kontext
              if (predchozi || nasledujici) {
                infoHtml += `<div class="collision-context">`;
                if (predchozi) {
                  const predchoziAddr = Utils.getAddress(predchozi);
                  if (predchoziAddr && predchoziAddr !== '—') {
                    const vzdalOdPredchoziho = await getDistance(
                      Utils.addCountryToAddress(predchoziAddr),
                      Utils.addCountryToAddress(currentAddress)
                    );
                    if (vzdalOdPredchoziho) {
                      infoHtml += `<span class="collision-route">Od ${Utils.getCustomerName(predchozi)} (${predchozi.cas_navstevy}): <strong>${vzdalOdPredchoziho.km} km</strong></span>`;
                    }
                  }
                }
                if (nasledujici) {
                  const nasledujiciAddr = Utils.getAddress(nasledujici);
                  if (nasledujiciAddr && nasledujiciAddr !== '—') {
                    const vzdalKNasledujicimu = await getDistance(
                      Utils.addCountryToAddress(currentAddress),
                      Utils.addCountryToAddress(nasledujiciAddr)
                    );
                    if (vzdalKNasledujicimu) {
                      infoHtml += `<span class="collision-route">K ${Utils.getCustomerName(nasledujici)} (${nasledujici.cas_navstevy}): <strong>${vzdalKNasledujicimu.km} km</strong></span>`;
                    }
                  }
                }
                infoHtml += `</div>`;
              }

              warningEl.innerHTML = infoHtml;
            } catch (err) {
              logger.error('Chyba při výpočtu vzdálenosti kolize:', err);
              warningEl.innerHTML = `KOLIZE: ${occupiedTimes[time].zakaznik} — ${occupiedTimes[time].model}`;
            }
          }
        } else if (warningEl) {
          warningEl.style.display = 'none';
        }
      };
      t.appendChild(el);
    }
  }
}

async function saveSelectedDate() {
  if (!SELECTED_DATE || !SELECTED_TIME) {
    wgsToast.warning(t('select_date_and_time'));
    return;
  }

  if (!CURRENT_RECORD) {
    wgsToast.warning(t('no_record_to_save'));
    return;
  }

  if (!Array.isArray(WGS_DATA_CACHE)) {
    WGS_DATA_CACHE = [];
  }

  const collision = WGS_DATA_CACHE.find(rec =>
    rec.termin === SELECTED_DATE &&
    rec.cas_navstevy === SELECTED_TIME &&
    rec.id !== CURRENT_RECORD.id
  );

  if (collision) {
    const collisionName = Utils.getCustomerName(collision);
    const potvrdit = await wgsConfirm(
      t('confirm_appointment_collision')
        .replace('{date}', SELECTED_DATE)
        .replace('{time}', SELECTED_TIME)
        .replace('{customer}', collisionName),
      t('confirm_yes') || 'Ano',
      t('confirm_no') || 'Ne'
    );
    if (!potvrdit) return;
  }

  // ZOBRAZIT LOADING OVERLAY
  showLoading(t('saving_appointment'));

  // ⏱️ PERFORMANCE: Debug timing
  const t0 = performance.now();
  logger.log('⏱️ START ukládání termínu...');

  try {
    // Get CSRF token
    const t1 = performance.now();
    const csrfToken = await fetchCsrfToken();
    const t2 = performance.now();
    logger.log(`⏱️ CSRF token získán za ${(t2 - t1).toFixed(0)}ms`);

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('id', CURRENT_RECORD.id);
    formData.append('termin', SELECTED_DATE);
    formData.append('cas_navstevy', SELECTED_TIME);
    formData.append('stav', 'DOMLUVENÁ');
    formData.append('csrf_token', csrfToken);

    // KROK 1: Uložení termínu do DB
    showLoading(t('saving_appointment_to_db'));
    const t3 = performance.now();
    logger.log('⏱️ Odesílám POST request na save.php...');
    const response = await fetch('/app/controllers/save.php', {
      method: 'POST',
      body: formData
    });
    const t4 = performance.now();
    logger.log(`⏱️ POST request dokončen za ${(t4 - t3).toFixed(0)}ms`);

    const result = await response.json();

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${result.message || response.statusText}`);
    }

    if (result.status === 'success') {
      // ⏱️ DEBUG: Měření každé operace v success bloku
      const tSuccess = performance.now();
      logger.log(`⏱️ SUCCESS block started: ${(tSuccess - t0).toFixed(0)}ms od začátku`);

      // Update CURRENT_RECORD with new data
      const tBeforeUpdate = performance.now();
      CURRENT_RECORD.termin = SELECTED_DATE;
      CURRENT_RECORD.cas_navstevy = SELECTED_TIME;
      CURRENT_RECORD.stav = 'DOMLUVENÁ';
      const tAfterUpdate = performance.now();
      logger.log(`⏱️ CURRENT_RECORD update: ${(tAfterUpdate - tBeforeUpdate).toFixed(0)}ms`);

      // Update cache
      const tBeforeCache = performance.now();
      const cacheRecord = WGS_DATA_CACHE.find(x => x.id === CURRENT_RECORD.id);
      if (cacheRecord) {
        cacheRecord.termin = SELECTED_DATE;
        cacheRecord.cas_navstevy = SELECTED_TIME;
        cacheRecord.stav = 'DOMLUVENÁ';
      }
      const tAfterCache = performance.now();
      logger.log(`⏱️ Cache update: ${(tAfterCache - tBeforeCache).toFixed(0)}ms`);

      // PERFORMANCE FIX: Odstranění zbytečného loadAll()
      // Cache je už aktualizovaná (řádky výše), seznam se obnoví automaticky
      // když uživatel zavře detail. Nemusíme čekat na reload celého seznamu.

      // SKRÝT LOADING
      const tBeforeHideLoading = performance.now();
      hideLoading();
      const tAfterHideLoading = performance.now();
      logger.log(`⏱️ hideLoading(): ${(tAfterHideLoading - tBeforeHideLoading).toFixed(0)}ms`);

      // ZOBRAZIT ÚSPĚCH
      const tBeforeAlert = performance.now();
      logger.log(`⏱️ TĚSNĚ PŘED ALERT: ${(tBeforeAlert - t0).toFixed(0)}ms od začátku`);
      wgsToast.success(t('appointment_saved_success').replace('{date}', SELECTED_DATE).replace('{time}', SELECTED_TIME));
      const tAfterAlert = performance.now();
      logger.log(`⏱️ alert() dokončen: ${(tAfterAlert - tBeforeAlert).toFixed(0)}ms`);

      // KROK 3: Odeslání potvrzení ASYNCHRONNĚ na pozadí (fire-and-forget)
      // Email se odešle, ale neuživatel na něj nečeká
      const tBeforeEmail = performance.now();
      sendAppointmentConfirmation(CURRENT_RECORD, SELECTED_DATE, SELECTED_TIME)
        .catch(err => logger.warn('⚠ Email se nepodařilo odeslat:', err.message));
      const tAfterEmail = performance.now();
      logger.log(`⏱️ sendAppointmentConfirmation() launch: ${(tAfterEmail - tBeforeEmail).toFixed(0)}ms`);

      // ⏱️ PERFORMANCE: Optimalizace - místo closeDetail() + showDetail() jen aktualizovat modal
      const t5 = performance.now();
      logger.log('⏱️ Aktualizuji detail...');
      const recordId = CURRENT_RECORD.id;

      const tBeforeClose = performance.now();
      closeDetail();
      const tAfterClose = performance.now();
      logger.log(`⏱️ closeDetail(): ${(tAfterClose - tBeforeClose).toFixed(0)}ms`);

      const tBeforeSetTimeout = performance.now();
      setTimeout(() => showDetail(recordId), 100);
      const tAfterSetTimeout = performance.now();
      logger.log(`⏱️ setTimeout() scheduled: ${(tAfterSetTimeout - tBeforeSetTimeout).toFixed(0)}ms`);

      const t6 = performance.now();
      logger.log(`⏱️ Detail aktualizován za ${(t6 - t5).toFixed(0)}ms`);

      // ⏱️ CELKOVÝ ČAS
      const tTotal = performance.now();
      logger.log(`⏱️ CELKOVÝ ČAS: ${(tTotal - t0).toFixed(0)}ms (${((tTotal - t0) / 1000).toFixed(1)}s)`);
    } else {
      hideLoading();
      wgsToast.error(t('error') + ': ' + (result.message || t('failed_to_save')));
    }
  } catch (e) {
    hideLoading();
    logger.error('Chyba při ukládání:', e);
    wgsToast.error(t('save_error') + ': ' + e.message);

    // ⏱️ Log času i při chybě
    const tError = performance.now();
    logger.log(`⏱️ Čas do chyby: ${(tError - t0).toFixed(0)}ms`);
  }
}

