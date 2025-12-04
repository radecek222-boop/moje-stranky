(function() {
  const safeLogger = (typeof window !== 'undefined' && window.logger) ? window.logger : console;
  const dataNode = document.getElementById('initialReklamaceData');

  function log(level, ...args) {
    if (!safeLogger) {
      return;
    }

    const method = typeof safeLogger[level] === 'function' ? safeLogger[level] : safeLogger.log;
    if (typeof method === 'function') {
      method.apply(safeLogger, args);
    }
  }

  // FIX: Pokus o načtení z DOM, pokud selže, zkus localStorage
  let payload = null;
  let dataSource = null;

  // Pokus 1: Načíst z DOM (initialReklamaceData)
  if (dataNode) {
    const raw = (dataNode.textContent || dataNode.innerText || '').trim();
    if (raw) {
      try {
        payload = JSON.parse(raw);
        dataSource = 'DOM';
        log('log', 'Data načtena z DOM (initialReklamaceData)');
      } catch (error) {
        log('error', 'JSON parse z DOM selhal', error);
      }
    }
  }

  // Pokus 2: Pokud DOM nemá data, zkus localStorage
  if (!payload) {
    try {
      const storedData = localStorage.getItem('currentCustomer');
      if (storedData) {
        payload = JSON.parse(storedData);
        dataSource = 'localStorage';
        log('log', 'Data načtena z localStorage (currentCustomer)');
      }
    } catch (error) {
      log('warn', 'Nepodařilo se načíst localStorage', error);
    }
  }

  // Pokud stále nemáme žádná data, konec
  if (!payload || typeof payload !== 'object') {
    log('warn', 'Žádná data k dispozici (ani DOM ani localStorage)');
    return;
  }

  const deriveId = (record) => {
    return [record.reklamace_id, record.cislo, record.id]
      .map((value) => (value ?? '').toString().trim())
      .find((value) => value.length > 0) || '';
  };

  const buildAddress = (record) => {
    if (record.adresa) {
      return record.adresa;
    }

    const parts = [record.ulice, record.mesto, record.psc]
      .map((value) => (value || '').toString().trim())
      .filter(Boolean);

    return parts.join(', ');
  };

  const bootstrapId = deriveId(payload);
  window.__INITIAL_REKLAMACE__ = payload;

  try {
    localStorage.setItem('currentCustomer', JSON.stringify(payload));
  } catch (storageErr) {
    log('warn', 'Nepodařilo se uložit data zákazníka do localStorage', storageErr);
  }

  window.currentReklamace = window.currentReklamace || payload;
  if (bootstrapId && !window.currentReklamaceId) {
    window.currentReklamaceId = bootstrapId;
  }

  const hydrateStaticFields = () => {
    const defaults = {
      'order-number': payload.reklamace_id || '',
      'claim-number': payload.cislo || '',
      customer: payload.jmeno || payload.zakaznik || '',
      address: buildAddress(payload),
      phone: payload.telefon || '',
      email: payload.email || '',
      brand: payload.znacka || payload.model || '',
      model: payload.model || '',
      'description-cz': payload.popis_problemu || '',
    };

    Object.entries(defaults).forEach(([fieldId, value]) => {
      const field = document.getElementById(fieldId);
      if (field && !field.value) {
        field.value = value || '';
      }
    });

    const fakturaceField = document.getElementById('fakturace-firma');
    if (fakturaceField && !fakturaceField.value && payload.fakturace_firma) {
      const code = payload.fakturace_firma.toString().trim().toUpperCase();
      if (code === 'CZ') {
        fakturaceField.value = '🇨🇿 Česká republika (CZ)';
      } else if (code === 'SK') {
        fakturaceField.value = '🇸🇰 Slovensko (SK)';
      } else {
        fakturaceField.value = payload.fakturace_firma;
      }
    }

    // Předvyplnit technika z databáze (pokud je uložený)
    const technikField = document.getElementById('technician');
    if (technikField && payload.technik) {
      const technikValue = payload.technik.toString().trim();
      // Najít option s touto hodnotou
      const options = Array.from(technikField.options);
      const matchingOption = options.find(opt => opt.value === technikValue || opt.text === technikValue);
      if (matchingOption) {
        technikField.value = matchingOption.value;
      } else {
        // Pokud technik není v seznamu, přidat ho
        const newOption = document.createElement('option');
        newOption.value = technikValue;
        newOption.text = technikValue;
        newOption.selected = true;
        technikField.add(newOption);
      }
    }

    // Předvyplnit datum fields pokud existují
    const deliveryDateField = document.getElementById('delivery-date');
    if (deliveryDateField && payload.datum_prodeje) {
      deliveryDateField.value = payload.datum_prodeje;
    }

    const claimDateField = document.getElementById('claim-date');
    if (claimDateField && payload.datum_reklamace) {
      claimDateField.value = payload.datum_reklamace;
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hydrateStaticFields, { once: true });
  } else {
    hydrateStaticFields();
  }

  // Pomocná funkce pro aktualizaci fakturace pole (nahrazuje protokol-fakturace-patch.js)
  const aktualizujFakturaci = (zakaznik) => {
    const fakturaceField = document.getElementById('fakturace-firma');
    if (!fakturaceField) return;

    const kod = ((zakaznik.fakturace_firma || 'cz')).toString().trim().toUpperCase();
    if (kod === 'CZ') {
      fakturaceField.value = '🇨🇿 Česká republika (CZ)';
    } else if (kod === 'SK') {
      fakturaceField.value = '🇸🇰 Slovensko (SK)';
    }
    log('log', `Fakturace nastavena: ${kod}`);
  };

  const attachLoadPatch = () => {
    if (typeof window.loadReklamace !== 'function') {
      attachLoadPatch._attempts = (attachLoadPatch._attempts || 0) + 1;
      if (attachLoadPatch._attempts > 40) {
        log('warn', 'loadReklamace stále není dostupná, přeskočeno');
        return;
      }
      setTimeout(attachLoadPatch, 50);
      return;
    }

    const originalLoadReklamace = window.loadReklamace;
    window.loadReklamace = async function patchedLoadReklamace(id) {
      const normalizedParam = (id || '').toString().trim();
      let vysledek;

      if (!normalizedParam && bootstrapId) {
        log('info', 'ℹ️ Protokol data patch: doplňuji ID z bootstrap payloadu');
        hydrateStaticFields();
        vysledek = await originalLoadReklamace.call(this, bootstrapId);
      } else if (bootstrapId && normalizedParam && normalizedParam === bootstrapId) {
        hydrateStaticFields();
        try {
          vysledek = await originalLoadReklamace.apply(this, arguments);
        } catch (error) {
          log('warn', 'Původní loadReklamace selhal, zachovávám bootstrap data', error);
          return null;
        }
      } else {
        // Pro jiné ID - zavolat originál a pak aktualizovat fakturaci
        try {
          vysledek = await originalLoadReklamace.apply(this, arguments);
        } catch (error) {
          log('warn', 'Původní loadReklamace selhal', error);
          return null;
        }
      }

      // VŽDY aktualizovat fakturaci po loadReklamace (nahrazuje protokol-fakturace-patch.js)
      setTimeout(() => {
        const zakaznik = window.currentReklamace || JSON.parse(localStorage.getItem('currentCustomer') || '{}');
        aktualizujFakturaci(zakaznik);
      }, 100);

      return vysledek;
    };

    log('log', 'Protokol data patch aktivován (včetně fakturace)');
  };

  attachLoadPatch();
})();
