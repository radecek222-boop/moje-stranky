// BEZPEČNOST: Cache CSRF tokenu pro prevenci nekonečné smyčky
window.csrfTokenCache = window.csrfTokenCache || null;

// Uložit originální fetch PŘED jakýmkoliv přepsáním
const originalFetch = window.fetch || fetch;

async function getCSRFToken() {
  if (window.csrfTokenCache) return window.csrfTokenCache;

  try {
    // OPRAVENO: Použít originalFetch místo window.fetch aby se předešlo rekurzi
    const response = await originalFetch("/app/controllers/get_csrf_token.php");
    const data = await response.json();
    window.csrfTokenCache = data.token;
    return data.token;
  } catch (err) {
    if (typeof logger !== 'undefined') {
      logger.error("Chyba získání CSRF tokenu:", err);
    }
    return "";
  }
}

// === GLOBÁLNÍ PROMĚNNÉ ===
let WGS_DATA_CACHE = [];
let ACTIVE_FILTER = 'all';
let CURRENT_RECORD = null;
let SELECTED_DATE = null;
let SELECTED_TIME = null;

// ✅ PAGINATION FIX: Tracking pagination state
let CURRENT_PAGE = 1;
let HAS_MORE_PAGES = false;
let LOADING_MORE = false;
const PER_PAGE = 50;
let CAL_MONTH = new Date().getMonth();
let CAL_YEAR = new Date().getFullYear();
let SEARCH_QUERY = '';

const WGS_ADDRESS = "Dubče 364, Běchovice 190 11, Česká republika";
const WGS_COORDS = { lat: 50.0472, lng: 14.5881 };

let DISTANCE_CACHE = {};

// === UTILITY FUNKCE ===
const Utils = {
  getCustomerName: (record) => {
    return record.jmeno || record.zakaznik || 'Neznámý zákazník';
  },
  
  getAddress: (record) => {
    if (record.adresa) return record.adresa;
    const parts = [record.ulice, record.mesto, record.psc].filter(x => x);
    return parts.length > 0 ? parts.join(', ') : '—';
  },
  
  getProduct: (record) => {
    return record.model || record.vyrobek || '—';
  },
  
  getOrderId: (record, index = 0) => {
    return record.cislo || record.reklamacniCislo || record.id || ('WGS-' + (index + 1));
  },
  
  isCompleted: (record) => {
    return record.stav === 'HOTOVO' || record.stav === 'done';
  },
  
  escapeHtml: (text) => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },
  
  addCountryToAddress: (address) => {
    if (!address) return address;
    if (!address.toLowerCase().includes('česk')) {
      return address + ', Česká republika';
    }
    return address;
  },
  
  filterByUserRole: (items) => {
    if (!CURRENT_USER || CURRENT_USER.role !== 'prodejce') {
      return items;
    }
    return items.filter(x => String(x.zpracoval_id) === String(CURRENT_USER.id));
  }
};

// === CACHE MANAGEMENT ===
const CacheManager = {
  load: () => {
    try {
      const cached = localStorage.getItem('wgs_distance_cache');
      if (cached) {
        DISTANCE_CACHE = JSON.parse(cached);
        logger.log('✓ Načteno', Object.keys(DISTANCE_CACHE).length, 'vzdáleností z cache');
      }
    } catch (e) {
      logger.error('Chyba při načítání cache:', e);
    }
  },
  
  save: () => {
    try {
      localStorage.setItem('wgs_distance_cache', JSON.stringify(DISTANCE_CACHE));
    } catch (e) {
      logger.error('Chyba při ukládání cache:', e);
    }
  }
};

// === INIT ===
window.addEventListener('DOMContentLoaded', async () => {
  CacheManager.load();
  
  
  initFilters();
  initSearch();
  await loadAll();
});

// === VYHLEDÁVÁNÍ ===
function initSearch() {
  const searchInput = document.getElementById('searchInput');
  const searchClear = document.getElementById('searchClear');
  
  searchInput.addEventListener('input', (e) => {
    SEARCH_QUERY = e.target.value.trim().toLowerCase();
    
    searchClear.classList.toggle('visible', SEARCH_QUERY.length > 0);

    let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);
    renderOrders(userItems);
  });
  
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      clearSearch();
    }
  });
}

function clearSearch() {
  const searchInput = document.getElementById('searchInput');
  const searchClear = document.getElementById('searchClear');
  
  searchInput.value = '';
  SEARCH_QUERY = '';
  searchClear.classList.remove('visible');

  let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);
    renderOrders(userItems);
}

function highlightText(text, query) {
  if (!query || !text) return escapeHtml(text);

  // ✅ SECURITY FIX: Escape HTML PŘED highlightováním
  const escapedText = escapeHtml(text);
  const escapedQuery = escapeRegex(query);

  const regex = new RegExp(`(${escapedQuery})`, 'gi');
  return escapedText.replace(regex, '<span class="highlight">$1</span>');
}

function escapeRegex(string) {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function matchesSearch(record, query) {
  if (!query) return true;
  
  const searchableFields = [
    Utils.getCustomerName(record),
    record.telefon || '',
    record.email || '',
    Utils.getAddress(record),
    Utils.getProduct(record),
    Utils.getOrderId(record),
    record.popis_problemu || ''
  ];
  
  const searchString = searchableFields.join(' ').toLowerCase();
  return searchString.includes(query);
}

// === FILTRY ===
function initFilters() {
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      ACTIVE_FILTER = btn.dataset.filter;

      let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);
    renderOrders(userItems);
    });
  });
}

// === AKTUALIZACE POČTŮ ===
function updateCounts(items) {
  if (!Array.isArray(items)) return;
  
  const countAll = items.length;
  const countWait = items.filter(r => {
    const stav = r.stav || 'wait';
    return stav === 'ČEKÁ' || stav === 'wait';
  }).length;
  const countOpen = items.filter(r => {
    const stav = r.stav || 'wait';
    return stav === 'DOMLUVENÁ' || stav === 'open';
  }).length;
  const countDone = items.filter(r => {
    const stav = r.stav || 'wait';
    return stav === 'HOTOVO' || stav === 'done';
  }).length;
  
  document.getElementById('count-all').textContent = `(${countAll})`;
  document.getElementById('count-wait').textContent = `(${countWait})`;
  document.getElementById('count-open').textContent = `(${countOpen})`;
  document.getElementById('count-done').textContent = `(${countDone})`;
}

// === NAČTENÍ DAT ===
async function loadAll(status = 'all', append = false) {
  try {
    // ✅ PAGINATION FIX: Přidat page a per_page parametry
    const page = append ? CURRENT_PAGE + 1 : 1;
    const response = await fetch(`app/controllers/load.php?status=${status}&page=${page}&per_page=${PER_PAGE}`);
    if (!response.ok) throw new Error('Chyba načítání');

    const json = await response.json();

    let items = [];
    if (json.status === 'success' && Array.isArray(json.data)) {
      items = json.data;
    } else if (Array.isArray(json)) {
      items = json;
    }

    // ✅ PAGINATION: Append místo replace při loadMore
    if (append) {
      WGS_DATA_CACHE = [...WGS_DATA_CACHE, ...items];
      CURRENT_PAGE = page;
    } else {
      WGS_DATA_CACHE = items;
      CURRENT_PAGE = 1;
    }

    // ✅ PAGINATION: Detekce zda jsou další stránky
    HAS_MORE_PAGES = items.length === PER_PAGE;
    LOADING_MORE = false;

    let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);

    updateCounts(userItems);
    renderOrders(userItems);

    // ✅ PAGINATION: Zobrazit/skrýt "Načíst další" tlačítko
    updateLoadMoreButton();
  } catch (err) {
    logger.error('Chyba:', err);
    WGS_DATA_CACHE = [];
    document.getElementById('orderGrid').innerHTML = `
      <div class="empty-state">
        <div class="empty-state-text">Chyba při načítání dat</div>
      </div>
    `;
  }
}

// === VYKRESLENÍ OBJEDNÁVEK ===
function renderOrders(items = null) {
  const grid = document.getElementById('orderGrid');
  const searchResultsInfo = document.getElementById('searchResultsInfo');
  
  if (!items) {
    items = Utils.filterByUserRole(WGS_DATA_CACHE);
  }
  
  if (!Array.isArray(items)) items = [];
  
  let filtered = items;
  
  if (ACTIVE_FILTER !== 'all') {
    const statusMap = {
      'wait': ['ČEKÁ', 'wait'],
      'open': ['DOMLUVENÁ', 'open'],
      'done': ['HOTOVO', 'done']
    };
    
    filtered = items.filter(r => {
      const stav = r.stav || 'wait';
      return statusMap[ACTIVE_FILTER]?.includes(stav);
    });
  }
  
  const totalBeforeSearch = filtered.length;
  if (SEARCH_QUERY) {
    filtered = filtered.filter(r => matchesSearch(r, SEARCH_QUERY));
    
    if (filtered.length > 0) {
      searchResultsInfo.className = 'search-results-info';
      searchResultsInfo.textContent = `Nalezeno ${filtered.length} z ${totalBeforeSearch} výsledků pro "${SEARCH_QUERY}"`;
    } else {
      searchResultsInfo.className = 'search-results-info no-results';
      searchResultsInfo.textContent = `Žádné výsledky pro "${SEARCH_QUERY}"`;
    }
    searchResultsInfo.style.display = 'block';
  } else {
    searchResultsInfo.style.display = 'none';
  }
  
  if (filtered.length === 0) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-text">${SEARCH_QUERY ? 'Žádné výsledky nenalezeny' : 'Žádné reklamace k zobrazení'}</div>
      </div>
    `;
    return;
  }
  
  filtered.sort((a, b) => {
    const dateA = new Date(a.datum || a.timestamp || 0);
    const dateB = new Date(b.datum || b.timestamp || 0);
    return dateB - dateA;
  });
  
  grid.innerHTML = filtered.map((rec, index) => {
    const customerName = Utils.getCustomerName(rec);
    const product = Utils.getProduct(rec);
    const date = formatDate(rec.datum);
    const status = getStatus(rec.stav);
    const orderId = Utils.getOrderId(rec, index);
    
    let address = Utils.getAddress(rec);
    if (address !== '—') {
      const parts = address.split(',').map(p => p.trim());
      address = parts.slice(0, 2).join(', ');
    }
    
    let appointmentText = '';
    if (rec.termin && rec.cas_navstevy) {
      appointmentText = formatAppointment(rec.termin, rec.cas_navstevy);
    }
    
    const notes = [];
    const unreadCount = 0;
    const hasUnread = false;
    
    const highlightedCustomer = SEARCH_QUERY ? highlightText(customerName, SEARCH_QUERY) : customerName;
    const highlightedAddress = SEARCH_QUERY ? highlightText(address, SEARCH_QUERY) : address;
    const highlightedProduct = SEARCH_QUERY ? highlightText(product, SEARCH_QUERY) : product;
    const highlightedOrderId = SEARCH_QUERY ? highlightText(orderId, SEARCH_QUERY) : orderId;
    
    const searchMatchClass = SEARCH_QUERY && matchesSearch(rec, SEARCH_QUERY) ? 'search-match' : '';
    // Přidat barevný nádech podle stavu
    const statusBgClass = `status-bg-${status.class}`;

    return `
      <div class="order-box ${searchMatchClass} ${statusBgClass}" onclick='showDetail(${JSON.stringify(rec).replace(/'/g, "&#39;")})'>
        <div class="order-header">
          <div class="order-number">${highlightedOrderId}</div>
          <div style="display: flex; gap: 0.4rem; align-items: center;">
            <div class="order-notes-badge ${hasUnread ? 'has-unread' : ''}" onclick='event.stopPropagation(); showNotes(${JSON.stringify(rec).replace(/'/g, "&#39;")})' title="${notes.length} poznámek">
              ${notes.length > 0 ? notes.length : ''}
            </div>
            <div class="order-status status-${status.class}"></div>
          </div>
        </div>
        <div class="order-body">
          <div class="order-customer">${highlightedCustomer}</div>
          <div class="order-detail">
            <div class="order-detail-line">${highlightedAddress}</div>
            <div class="order-detail-line">${highlightedProduct}</div>
            ${appointmentText ? `<div class="order-detail-line" style="color: #00d4ff; font-weight: 700; font-size: 0.7rem;">${appointmentText}</div>` : ''}
            <div class="order-detail-line" style="opacity: 0.6;">${date}</div>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// === MODAL MANAGER ===
const ModalManager = {
  show: (content) => {
    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('detailOverlay').classList.add('active');
  },
  
  close: () => {
    document.getElementById('detailOverlay').classList.remove('active');
    CURRENT_RECORD = null;
    SELECTED_DATE = null;
    SELECTED_TIME = null;
  },
  
  createHeader: (title, subtitle) => {
    return `
      <div class="modal-header">
        <h2 class="modal-title">${title}</h2>
        ${subtitle ? `<p class="modal-subtitle">${subtitle}</p>` : ''}
      </div>
    `;
  },
  
  createActions: (buttons) => {
    return `
      <div class="modal-actions">
        ${buttons.join('')}
      </div>
    `;
  }
};

// === DETAIL ===
async function showDetail(recordOrId) {
  let record;
  if (typeof recordOrId === 'string') {
    record = WGS_DATA_CACHE.find(x => x.id == recordOrId || x.reklamace_id == recordOrId);
    if (!record) {
      alert('Záznam nenalezen');
      return;
    }
  } else {
    record = WGS_DATA_CACHE.find(x => x.id == recordOrId.id || x.reklamace_id == recordOrId.reklamace_id) || recordOrId;
  }
  
  CURRENT_RECORD = record;
  
  const customerName = Utils.getCustomerName(record);
  const address = Utils.getAddress(record);
  const termin = record.termin ? formatDate(record.termin) : '—';
  const time = record.cas_navstevy || '—';
  const status = getStatus(record.stav);
  
  const isCompleted = Utils.isCompleted(record);
  
  let buttonsHtml = '';
  
  if (isCompleted) {
    buttonsHtml = `
      <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 0.75rem; margin-bottom: 1rem; border-radius: 4px;">
        <div style="text-align: center;">
          <div style="font-size: 0.85rem; font-weight: 600; color: #1a1a1a; margin-bottom: 0.25rem;">Zakázka dokončena</div>
          <div style="font-size: 0.75rem; color: #666;">Tato zakázka byla již vyřízena</div>
        </div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; background: #333; color: white; font-weight: 600; font-size: 0.85rem;" onclick="reopenOrder('${record.id}')">
          Znovu otevřít
        </button>

        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem;" onclick="showContactMenu('${record.id}')">Kontaktovat</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem;" onclick="showCustomerDetail('${record.id}')">Detail zákazníka</button>

      <div style="width: 100%; margin-top: 0.25rem;">
        ${record.documents && record.documents.length > 0 ? `
          <button class="btn" style="background: #2D5016; color: white; width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; font-weight: 600;"
                  onclick="window.open('${record.documents[0].file_path}', '_blank')">
            📄 PDF REPORT
          </button>
        ` : `
          <div style="background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; padding: 0.5rem; text-align: center; color: #666; font-size: 0.75rem;">
            PDF report ještě nebyl vytvořen
          </div>
        `}
      </div>

        <button class="btn btn-secondary" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem;" onclick="closeDetail()">Zavřít</button>
      </div>
    `;
  } else {
    buttonsHtml = `
      <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #1a1a1a; color: white;" onclick="startVisit('${record.id}')">Zahájit návštěvu</button>

        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #1a1a1a; color: white;" onclick="showCalendar('${record.id}')">Naplánovat termín</button>

        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem;" onclick="showContactMenu('${record.id}')">Kontaktovat</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem;" onclick="showCustomerDetail('${record.id}')">Detail zákazníka</button>
        <button class="btn btn-secondary" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem;" onclick="closeDetail()">Zavřít</button>
      </div>
    `;
  }
  
  const content = `
    ${ModalManager.createHeader(customerName, `
      <strong>Adresa:</strong> ${address}<br>
      <strong>Termín:</strong> ${termin} ${time !== '—' ? 'v ' + time : ''}<br>
      <strong>Stav:</strong> ${status.text}
    `)}
    
    <div class="modal-body">
      ${buttonsHtml}
    </div>
  `;
  
  ModalManager.show(content);
}

function closeDetail() {
  ModalManager.close();
}

// === ZNOVUOTEVŘENÍ ZAKÁZKY ===
async function reopenOrder(id) {
  const record = WGS_DATA_CACHE.find(x => x.id == id);
  if (!record) {
    alert('Záznam nenalezen');
    return;
  }
  
  const customerName = Utils.getCustomerName(record);
  const product = Utils.getProduct(record);
  
  const confirmed = window.confirm(
    `ZNOVU OTEVŘÍT ZAKÁZKU?\n\n` +
    `Zákazník: ${customerName}\n` +
    `Produkt: ${product}\n\n` +
    `Tato akce:\n` +
    `- Změní stav na NOVÁ (žlutá)\n` +
    `- Zruší původní termín návštěvy\n` +
    `- Umožní naplánovat novou návštěvu\n\n` +
    `Použijte pouze v případě, že se objevil nový problém u této zakázky.\n\n` +
    `Opravdu chcete pokračovat?`
  );
  
  if (!confirmed) {
    logger.log('❌ Znovuotevření zrušeno uživatelem');
    return;
  }
  
  try {
    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('id', id);
    formData.append('stav', 'ČEKÁ');
    formData.append('termin', '');
    formData.append('cas_navstevy', '');
    formData.append('csrf_token', csrfToken);

    const response = await fetch('/app/controllers/save.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${result.message || response.statusText}`);
    }

    if (result.status === 'success') {
      const cacheRecord = WGS_DATA_CACHE.find(x => x.id == id);
      if (cacheRecord) {
        cacheRecord.stav = "ČEKÁ";
        cacheRecord.termin = "";
        cacheRecord.cas_navstevy = "";
      }
      
      if (CURRENT_RECORD && CURRENT_RECORD.id == id) {
        CURRENT_RECORD.stav = "ČEKÁ";
        CURRENT_RECORD.termin = "";
        CURRENT_RECORD.cas_navstevy = "";
      }
      
      const noteText = `🔄 Zakázka znovu otevřena\n\nDokončená zakázka byla znovu otevřena pro nový problém nebo reklamaci.\n\nStav změněn: HOTOVO → NOVÁ\nTermín: vymazán`;
      try {
        if (typeof addNote === 'function') {
          const addNoteResult = addNote(id, noteText);
          if (addNoteResult && typeof addNoteResult.then === 'function') {
            await addNoteResult;
          }
        }
      } catch (noteError) {
        logger.error('Chyba při přidávání poznámky:', noteError);
      }
      
      try {
        // Get CSRF token
        const csrfToken = await getCSRFToken();

        const now = new Date();
        const formattedDate = now.toLocaleDateString('cs-CZ', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });

        await fetch("/app/notification_sender.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            notification_id: "order_reopened",
            csrf_token: csrfToken,
            data: {
              customer_name: customerName,
              order_id: id,
              product: product,
              reopened_by: "admin@wgs-service.cz",
              reopened_at: formattedDate
            }
          })
        });
      } catch (emailError) {
        logger.error('Chyba při odesílání notifikace:', emailError);
      }
      
      alert(
        `✓ ZAKÁZKA ZNOVU OTEVŘENA\n\n` +
        `Stav změněn na: NOVÁ (žlutá)\n` +
        `Termín byl vymazán.\n\n` +
        `Nyní můžete:\n` +
        `→ Naplánovat nový termín návštěvy\n` +
        `→ Zahájit novou návštěvu\n` +
        `→ Aktualizovat detail zakázky`
      );
      
      showDetail(id);
      
      if (typeof loadAll === 'function') {
        await loadAll(ACTIVE_FILTER);
      }
    } else {
      throw new Error(result.message || 'Nepodařilo se uložit změny');
    }
  } catch (e) {
    logger.error('Chyba při znovuotevření zakázky:', e);
    alert('Chyba při znovuotevření zakázky: ' + e.message);
  }
}

// === NORMALIZACE DAT ZÁKAZNÍKA ===
function normalizeCustomerData(data) {
  const normalized = { ...data };
  
  if (!normalized.jmeno && normalized.zakaznik) {
    normalized.jmeno = normalized.zakaznik;
  }
  if (!normalized.zakaznik && normalized.jmeno) {
    normalized.zakaznik = normalized.jmeno;
  }
  
  if (!normalized.adresa && (normalized.ulice || normalized.mesto || normalized.psc)) {
    const parts = [];
    if (normalized.ulice) parts.push(normalized.ulice);
    if (normalized.mesto) parts.push(normalized.mesto);
    if (normalized.psc) parts.push(normalized.psc);
    normalized.adresa = parts.join(', ');
  }
  
  if (normalized.adresa && (!normalized.ulice || !normalized.mesto)) {
    const parts = normalized.adresa.split(',').map(s => s.trim());
    if (!normalized.ulice && parts[0]) normalized.ulice = parts[0];
    if (!normalized.mesto && parts[1]) normalized.mesto = parts[1];
    if (!normalized.psc && parts[2]) normalized.psc = parts[2];
  }
  
  return normalized;
}

// === ZAHÁJIT NÁVŠTĚVU ===
function startVisit(id) {
  const z = WGS_DATA_CACHE.find(x => x.id == id);
  if (!z) {
    alert('Záznam nenalezen');
    return;
  }
  
  if (z.stav === 'ČEKÁ' || z.stav === 'wait') {
    const confirm = window.confirm(
      'VAROVÁNÍ: Termín návštěvy ještě není naplánován.\n\n' +
      'Chcete pokračovat i bez naplánovaného termínu?'
    );
    if (!confirm) return;
  }
  
  if (Utils.isCompleted(z)) {
    alert('Tato návštěva již byla dokončena.');
    return;
  }
  
  const normalizedData = normalizeCustomerData(z);
  
  localStorage.setItem('currentCustomer', JSON.stringify(normalizedData));
  localStorage.setItem('visitStartTime', new Date().toISOString());
  
  const photoKey = 'photoSections_' + normalizedData.id;
  localStorage.removeItem(photoKey);
  
  logger.log('✅ Normalizovaná data uložena:', normalizedData);
  
  window.location.href = 'photocustomer.php?new=true';
}

// === ULOŽENÍ ===
async function saveData(data, successMsg) {
  try {
    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const formData = new FormData();
    Object.keys(data).forEach(key => {
      formData.append(key, data[key]);
    });

    formData.append("action", "update");
    formData.append("csrf_token", csrfToken);

    const response = await fetch('/app/controllers/save.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${result.message || response.statusText}`);
    }

    if (result.status === 'success') {
      // Update cache with new data
      Object.keys(data).forEach(key => {
        const cacheRecord = WGS_DATA_CACHE.find(x => x.id == data.id);
        if (cacheRecord && key !== 'action') {
          cacheRecord[key] = data[key];
        }
        if (CURRENT_RECORD && CURRENT_RECORD.id == data.id && key !== 'action') {
          CURRENT_RECORD[key] = data[key];
        }
      });

      alert(successMsg);

      // Reload all data from DB to ensure consistency
      await loadAll(ACTIVE_FILTER);

      // Re-open detail to show updated data
      if (data.id) {
        closeDetail();
        setTimeout(() => showDetail(data.id), 100);
      } else {
        closeDetail();
      }
    } else {
      alert('Chyba: ' + (result.message || 'Nepodařilo se uložit.'));
    }
  } catch (e) {
    logger.error('Chyba při ukládání:', e);
    alert('Chyba při ukládání: ' + e.message);
  }
}

// === LOADING OVERLAY HELPERS ===
function showLoading(message = 'Načítání...') {
  const overlay = document.getElementById('loadingOverlay');
  const text = document.getElementById('loadingText');
  if (overlay && text) {
    text.textContent = message;
    overlay.classList.add('show');
  }
}

function hideLoading() {
  const overlay = document.getElementById('loadingOverlay');
  if (overlay) {
    overlay.classList.remove('show');
  }
}

// === KALENDÁŘ ===
function showCalendar(id) {
  const z = WGS_DATA_CACHE.find(x => x.id == id);
  if (!z) return;
  CURRENT_RECORD = z;
  SELECTED_DATE = null;
  SELECTED_TIME = null;
  
  const content = `
    <div class="modal-header">
      <div id="selectedDateDisplay" style="color: var(--c-grey); font-size: 0.9rem; font-weight: 600; text-align: center;">Zatím nevybráno</div>
      <button class="modal-close" onclick="ModalManager.close()">✕</button>
    </div>

    <div class="modal-body" style="max-height: 80vh; overflow-y: auto; padding: 1rem;">
      <div class="calendar-container">
        <div id="calGrid"></div>
        <div id="distanceInfo"></div>
        <div id="dayBookings"></div>
        <div id="timeGrid"></div>
      </div>
    </div>
    
    ${ModalManager.createActions([
      '<button class="btn btn-secondary" onclick="showDetail(CURRENT_RECORD)">Zpět</button>',
      '<button class="btn btn-success" onclick="saveSelectedDate()">Uložit termín</button>'
    ])}
  `;
  
  ModalManager.show(content);
  const today = new Date();
  CAL_MONTH = today.getMonth();
  CAL_YEAR = today.getFullYear();
  renderCalendar(CAL_MONTH, CAL_YEAR);
}

function previousMonth() {
  const today = new Date();
  const currentMonth = today.getMonth();
  const currentYear = today.getFullYear();

  // Calculate previous month
  let prevMonth = CAL_MONTH - 1;
  let prevYear = CAL_YEAR;
  if (prevMonth < 0) {
    prevMonth = 11;
    prevYear--;
  }

  // Don't allow going to past months
  if (prevYear < currentYear || (prevYear === currentYear && prevMonth < currentMonth)) {
    alert('⚠️ Nelze zobrazit minulé měsíce.\n\nTermíny lze plánovat pouze do budoucna.');
    return;
  }

  CAL_MONTH = prevMonth;
  CAL_YEAR = prevYear;
  renderCalendar(CAL_MONTH, CAL_YEAR);
}

function nextMonth(event) {
  if (event) event.stopPropagation();
  CAL_MONTH++;
  if (CAL_MONTH > 11) {
    CAL_MONTH = 0;
    CAL_YEAR++;
  }
  renderCalendar(CAL_MONTH, CAL_YEAR);
}

function renderCalendar(m, y) {
  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';
  
  const monthNames = ['Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen',
                      'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];
  const navHeader = document.createElement('div');
  navHeader.className = 'calendar-controls';
  navHeader.innerHTML = `
    <button class="calendar-nav-btn" onclick="previousMonth()">◀ Předchozí</button>
    <span class="calendar-month-title">${monthNames[m]} ${y}</span>
    <button class="calendar-nav-btn" onclick="event.stopPropagation(); nextMonth(event)">Další ▶</button>
  `;
  grid.appendChild(navHeader);
  
  const weekdays = document.createElement('div');
  weekdays.className = 'calendar-weekdays';
  weekdays.innerHTML = '<div>Po</div><div>Út</div><div>St</div><div>Čt</div><div>Pá</div><div>So</div><div>Ne</div>';
  grid.appendChild(weekdays);
  
  const daysGrid = document.createElement('div');
  daysGrid.className = 'calendar-days';
  
  const firstDay = new Date(y, m, 1);
  const lastDay = new Date(y, m + 1, 0);
  const daysInMonth = lastDay.getDate();
  const startDayOfWeek = (firstDay.getDay() + 6) % 7;
  
  const occupiedDays = new Set();
  WGS_DATA_CACHE.forEach(rec => {
    if (rec.termin && rec.id !== CURRENT_RECORD?.id) {
      const parts = rec.termin.split('.');
      if (parts.length === 3) {
        const day = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        const year = parseInt(parts[2]);
        if (month === m + 1 && year === y) {
          occupiedDays.add(day);
        }
      }
    }
  });
  
  for (let i = 0; i < startDayOfWeek; i++) {
    const empty = document.createElement('div');
    empty.className = 'cal-day empty';
    daysGrid.appendChild(empty);
  }
  
  // Get today's date for comparison (at midnight for accurate comparison)
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  for (let d = 1; d <= daysInMonth; d++) {
    const el = document.createElement('div');
    el.className = 'cal-day';

    // Create date for this calendar day
    const dayDate = new Date(y, m, d);
    dayDate.setHours(0, 0, 0, 0);

    // Disable past dates
    const isPast = dayDate < today;
    if (isPast) {
      el.classList.add('disabled');
      el.title = 'Nelze vybrat minulé datum';
      el.style.opacity = '0.3';
      el.style.cursor = 'not-allowed';
      el.style.backgroundColor = '#f0f0f0';
    } else if (occupiedDays.has(d)) {
      el.classList.add('occupied');
      el.title = 'Tento den má již nějaké termíny';
    }

    el.textContent = d;
    el.onclick = () => {
      // Prevent selection of past dates
      if (isPast) {
        alert('⚠️ Nelze vybrat minulé datum.\n\nVyberte prosím dnešní nebo budoucí datum.');
        return;
      }

      SELECTED_DATE = `${d}.${m + 1}.${y}`;
      document.querySelectorAll('.cal-day').forEach(x => x.classList.remove('selected'));
      el.classList.add('selected');

      // Získat vzdálenost k zákazníkovi pro zobrazení v hlavičce
      const currentAddress = Utils.addCountryToAddress(Utils.getAddress(CURRENT_RECORD));
      let displayText = `Vybraný den: ${SELECTED_DATE}`;

      // Zkusit získat vzdálenost asynchronně
      if (currentAddress && currentAddress !== '—') {
        getDistance(WGS_ADDRESS, currentAddress).then(distToCustomer => {
          if (distToCustomer && distToCustomer.km) {
            const updatedText = `Vybraný den: ${SELECTED_DATE} — ${distToCustomer.km} km`;
            document.getElementById('selectedDateDisplay').textContent = updatedText;
          }
        }).catch(err => {
          logger.error('Chyba při získání vzdálenosti:', err);
        });
      }

      document.getElementById('selectedDateDisplay').textContent = displayText;

      // Zobrazit časy okamžitě
      renderTimeGrid();

      // Načítat vzdálenosti na pozadí (neblokovat UI)
      showDayBookingsWithDistances(SELECTED_DATE).catch(err => {
        logger.error('Chyba při načítání vzdáleností:', err);
      });
    };
    daysGrid.appendChild(el);
  }
  
  grid.appendChild(daysGrid);
}

// === CSRF TOKEN HELPER ===
async function fetchCsrfToken() {
  if (typeof getCSRFToken === 'function') {
    try {
      const token = await getCSRFToken();
      if (token) {
        return token;
      }
    } catch (err) {
      logger?.warn?.('CSRF token z getCSRFToken selhal:', err);
    }
  }

  if (typeof getCSRFTokenFromMeta === 'function') {
    const metaToken = getCSRFTokenFromMeta();
    if (metaToken) {
      return metaToken;
    }
  }

  const fallbackMeta = document.querySelector('meta[name="csrf-token"]');
  if (fallbackMeta) {
    const token = fallbackMeta.getAttribute('content');
    if (token) {
      window.csrfTokenCache = token;
      return token;
    }
  }

  throw new Error('CSRF token není k dispozici. Obnovte stránku a zkuste to znovu.');
}

// === VÝPOČET VZDÁLENOSTI ===
async function getDistance(fromAddress, toAddress) {
  const cacheKey = `${fromAddress}|${toAddress}`;

  if (DISTANCE_CACHE[cacheKey]) {
    return DISTANCE_CACHE[cacheKey];
  }

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);

    // Načíst CSRF token
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
    if (error.name === 'AbortError') {
      logger.log('Request timeout');
    } else {
      logger.error('Chyba při výpočtu vzdálenosti:', error);
    }
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
  
  if (!Array.isArray(WGS_DATA_CACHE)) {
    WGS_DATA_CACHE = [];
  }
  
  const bookings = WGS_DATA_CACHE.filter(rec => 
    rec.termin === date && rec.id !== CURRENT_RECORD?.id
  );
  
  bookings.sort((a, b) => {
    const timeA = a.cas_navstevy || '00:00';
    const timeB = b.cas_navstevy || '00:00';
    return timeA.localeCompare(timeB);
  });
  
  let currentAddress = Utils.getAddress(CURRENT_RECORD);
  
  if (!currentAddress || currentAddress === '—') {
    distanceContainer.innerHTML = '';
    bookingsContainer.innerHTML = '';
    return;
  }
  
  currentAddress = Utils.addCountryToAddress(currentAddress);
  
  const cacheKey = `${WGS_ADDRESS}|${currentAddress}`;
  const isCached = DISTANCE_CACHE[cacheKey] !== undefined;
  
  if (!isCached) {
    distanceContainer.innerHTML = '<div style="text-align: center; color: var(--c-grey); font-size: 0.7rem; padding: 0.5rem;">Načítání...</div>';
  }
  
  if (bookings.length === 0) {
    const distToCustomer = await getDistance(WGS_ADDRESS, currentAddress);
    
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
    
    distancePairs.push({ from: fromAddr, to: toAddr, booking: booking });
    fromAddr = toAddr;
  }
  
  distancePairs.push({ from: fromAddr, to: currentAddress, booking: null, isNew: true });
  
  const distances = await getDistancesBatch(distancePairs.map(p => ({ from: p.from, to: p.to })));
  
  let totalKm = 0;
  let routes = [];
  
  for (let i = 0; i < distancePairs.length; i++) {
    const pair = distancePairs[i];
    const dist = distances[i];
    
    if (!dist) continue;
    
    totalKm += parseFloat(dist.km);
    
    const fromName = i === 0 ? 'WGS Sídlo' : Utils.getCustomerName(distancePairs[i-1].booking);
    const toName = pair.isNew ? Utils.getCustomerName(CURRENT_RECORD) : Utils.getCustomerName(pair.booking);
    const time = pair.isNew ? (SELECTED_TIME || '—') : (pair.booking?.cas_navstevy || '—');
    
    routes.push({
      from: fromName,
      to: toName,
      time: time,
      km: dist.km,
      isNew: pair.isNew
    });
  }
  
  let routesHtml = routes.map(r => `
    <div class="route-item ${r.isNew ? 'new-customer' : ''}">
      <div class="route-item-left">
        <span>${r.from}</span>
        <span class="route-arrow">→</span>
        <span>${r.to} ${r.time !== '—' ? '(' + r.time + ')' : ''}</span>
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
          <div class="distance-stat-value">${bookings.length + 1}</div>
        </div>
      </div>
      <div class="route-info">
        ${routesHtml}
      </div>
    </div>
  `;
  
  if (bookings.length > 0) {
    let html = '<div class="day-bookings" style="margin-top: 0.5rem;"><h4>Termíny v tento den:</h4>';
    bookings.forEach(b => {
      const customerName = Utils.getCustomerName(b);
      html += `
        <div class="booking-item" onclick='showBookingDetail(${JSON.stringify(b).replace(/'/g, "&apos;")})'>
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

function showBookingDetail(booking) {
  const customerName = Utils.getCustomerName(booking);
  const address = Utils.getAddress(booking);
  const product = Utils.getProduct(booking);
  const description = booking.popis_problemu || '—';
  
  const content = `
    ${ModalManager.createHeader('Detail obsazeného termínu', '<p style="color: var(--c-error); font-weight: 600;">Tento termín je již obsazen</p>')}
    
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
    
    ${ModalManager.createActions([
      '<button class="btn btn-secondary" onclick="showCalendar(\'' + CURRENT_RECORD.id + '\')">Zpět na kalendář</button>'
    ])}
  `;
  
  ModalManager.show(content);
}

function renderTimeGrid() {
  const t = document.getElementById('timeGrid');
  t.innerHTML = '';
  
  const occupiedTimes = {};
  WGS_DATA_CACHE.forEach(rec => {
    if (rec.termin === SELECTED_DATE && rec.cas_navstevy && rec.id !== CURRENT_RECORD?.id) {
      const customerName = Utils.getCustomerName(rec);
      occupiedTimes[rec.cas_navstevy] = {
        zakaznik: customerName,
        model: Utils.getProduct(rec)
      };
    }
  });
  
  // Časový rozsah: 8:00 - 19:00 (místo původního 7:00 - 20:30)
  // Snížení z 28 slotů na 22 slotů pro lepší mobilní zobrazení
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

        // Získat adresu zákazníka
        const currentAddress = Utils.addCountryToAddress(Utils.getAddress(CURRENT_RECORD));

        // Základní text bez vzdálenosti
        let displayText = `Vybraný termín: ${SELECTED_DATE}`;

        // Zkusit získat vzdálenost (z cache nebo vypočítat)
        if (currentAddress && currentAddress !== '—') {
          try {
            const distToCustomer = await getDistance(WGS_ADDRESS, currentAddress);
            if (distToCustomer && distToCustomer.km) {
              displayText += ` — ${distToCustomer.km} km`;
            }
          } catch (err) {
            logger.error('Chyba při získání vzdálenosti:', err);
          }
        }

        displayText += ` — ${SELECTED_TIME}`;

        if (occupiedTimes[time]) {
          displayText += ` ⚠️ KOLIZE: ${occupiedTimes[time].zakaznik}`;
        }

        document.getElementById('selectedDateDisplay').textContent = displayText;

        // Aktualizovat vzdálenosti na pozadí s novým časem
        showDayBookingsWithDistances(SELECTED_DATE).catch(err => {
          logger.error('Chyba při aktualizaci vzdáleností:', err);
        });
      };
      t.appendChild(el);
    }
  }
}

async function saveSelectedDate() {
  if (!SELECTED_DATE || !SELECTED_TIME) {
    alert('Vyberte datum i čas.');
    return;
  }

  if (!CURRENT_RECORD) {
    alert('Chyba: žádný záznam k uložení.');
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
    const confirm = window.confirm(
      `VAROVÁNÍ: Kolize termínu!\n\n` +
      `${SELECTED_DATE} v ${SELECTED_TIME} již má:\n` +
      `${collisionName}\n\n` +
      `Chcete i přesto uložit tento termín?`
    );
    if (!confirm) return;
  }

  // ZOBRAZIT LOADING OVERLAY
  showLoading('Ukládám termín...');

  try {
    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('id', CURRENT_RECORD.id);
    formData.append('termin', SELECTED_DATE);
    formData.append('cas_navstevy', SELECTED_TIME);
    formData.append('stav', 'DOMLUVENÁ');
    formData.append('csrf_token', csrfToken);

    // KROK 1: Uložení termínu do DB
    showLoading('Ukládám termín do databáze...');
    const response = await fetch('/app/controllers/save.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${result.message || response.statusText}`);
    }

    if (result.status === 'success') {
      // Update CURRENT_RECORD with new data
      CURRENT_RECORD.termin = SELECTED_DATE;
      CURRENT_RECORD.cas_navstevy = SELECTED_TIME;
      CURRENT_RECORD.stav = 'DOMLUVENÁ';

      // Update cache
      const cacheRecord = WGS_DATA_CACHE.find(x => x.id === CURRENT_RECORD.id);
      if (cacheRecord) {
        cacheRecord.termin = SELECTED_DATE;
        cacheRecord.cas_navstevy = SELECTED_TIME;
        cacheRecord.stav = 'DOMLUVENÁ';
      }

      // KROK 2: Načtení aktuálních dat z DB
      showLoading('Aktualizuji seznam...');
      await loadAll(ACTIVE_FILTER);

      // SKRÝT LOADING
      hideLoading();

      // ZOBRAZIT ÚSPĚCH (až po všech operacích)
      alert(`✓ Termín uložen: ${SELECTED_DATE} ${SELECTED_TIME}\n\nStav automaticky změněn na: DOMLUVENÁ`);

      // KROK 3: Odeslání potvrzení ASYNCHRONNĚ na pozadí (fire-and-forget)
      // Email se odešle, ale neuživatel na něj nečeká
      sendAppointmentConfirmation(CURRENT_RECORD, SELECTED_DATE, SELECTED_TIME)
        .catch(err => logger.warn('⚠ Email se nepodařilo odeslat:', err.message));

      // Re-open detail to show updated data
      const recordId = CURRENT_RECORD.id;
      closeDetail();
      setTimeout(() => showDetail(recordId), 100);
    } else {
      hideLoading();
      alert('Chyba: ' + (result.message || 'Nepodařilo se uložit.'));
    }
  } catch (e) {
    hideLoading();
    logger.error('Chyba při ukládání:', e);
    alert('Chyba při ukládání: ' + e.message);
  }
}

// === KONTAKT ===
function showContactMenu(id) {
  const phone = CURRENT_RECORD.telefon || '';
  const email = CURRENT_RECORD.email || '';
  const customerName = Utils.getCustomerName(CURRENT_RECORD);
  const address = Utils.getAddress(CURRENT_RECORD);
  
  const content = `
    ${ModalManager.createHeader('Kontaktovat zákazníka', customerName)}
    
    <div class="modal-body">
      <div class="info-grid" style="margin-bottom: 1rem;">
        <div class="info-label">Telefon:</div>
        <div class="info-value"><strong>${phone || 'Neuvedeno'}</strong></div>
        
        <div class="info-label">Email:</div>
        <div class="info-value"><strong>${email || 'Neuvedeno'}</strong></div>
        
        <div class="info-label">Adresa:</div>
        <div class="info-value"><strong>${address || 'Neuvedeno'}</strong></div>
      </div>
      
      <div class="modal-section">
        <h3 class="section-title">Rychlé akce</h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          ${phone ? `<a href="tel:${phone}" class="btn" style="padding: 0.5rem 0.75rem; font-size: 0.85rem; text-decoration: none; display: block; text-align: center; background: #1a1a1a; color: white;">Zavolat</a>` : ''}
          <button class="btn" style="padding: 0.5rem 0.75rem; font-size: 0.85rem; background: #1a1a1a; color: white;" onclick="closeDetail(); setTimeout(() => showCalendar('${id}'), 100)">Termín návštěvy</button>
          ${phone ? `<button class="btn" style="padding: 0.5rem 0.75rem; font-size: 0.85rem; background: #444; color: white;" onclick="sendContactAttemptEmail('${id}', '${phone}')">Odeslat SMS</button>` : ''}
          ${address && address !== '—' ? `<a href="https://waze.com/ul?q=${encodeURIComponent(address)}&navigate=yes" class="btn" style="padding: 0.5rem 0.75rem; font-size: 0.85rem; text-decoration: none; display: block; text-align: center; background: #444; color: white;" target="_blank">Navigovat (Waze)</a>` : ''}
        </div>
      </div>
    </div>
    
    ${ModalManager.createActions([
      '<button class="btn btn-secondary" onclick="showDetail(CURRENT_RECORD)">Zpět</button>'
    ])}
  `;
  
  ModalManager.show(content);
}

// === MAPA A VZDÁLENOST ===
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
    mapContainer.innerHTML = '<div class="map-error">Adresa zákazníka není k dispozici</div>';
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

    // ✅ PERFORMANCE FIX: Použít getDistance() která má cache místo přímého fetch
    // Tímto se vyhneme duplicitním API calls
    const distanceData = await getDistance(WGS_ADDRESS, customerAddress);

    if (distanceData && distanceData.text) {
      document.getElementById('mapDistance').textContent = distanceData.text;
      if (distanceData.duration) {
        document.getElementById('mapDuration').textContent = distanceData.duration;
      }
    } else {
      document.getElementById('mapDistance').textContent = '—';
      document.getElementById('mapDuration').textContent = '—';
      logger.error('Nepodařilo se načíst vzdálenost');
    }
    
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

// === DETAIL ZÁKAZNÍKA ===
async function showCustomerDetail(id) {
  const customerName = Utils.getCustomerName(CURRENT_RECORD);
  const phone = CURRENT_RECORD.telefon || '';
  const email = CURRENT_RECORD.email || '';
  const address = Utils.getAddress(CURRENT_RECORD);
  const product = Utils.getProduct(CURRENT_RECORD);
  const description = CURRENT_RECORD.popis_problemu || '';

  const cislo = CURRENT_RECORD.cislo || '';
  const reklamaceId = CURRENT_RECORD.reklamace_id || '';
  const zadavatel = CURRENT_RECORD.created_by_name || CURRENT_RECORD.prodejce || '';
  const datum_prodeje = CURRENT_RECORD.datum_prodeje || '';
  const datum_reklamace = CURRENT_RECORD.datum_reklamace || '';
  const provedeni = CURRENT_RECORD.provedeni || '';
  const barva = CURRENT_RECORD.barva || '';
  const doplnujici_info = CURRENT_RECORD.doplnujici_info || '';
  const fakturace_firma = CURRENT_RECORD.fakturace_firma || 'CZ';

  const dbPhotos = await loadPhotosFromDB(reklamaceId);
  let fotky = dbPhotos.length > 0 ? dbPhotos : [];

  if (fotky.length === 0) {
    const photoKey = 'photoSections_' + id;
    const photosData = localStorage.getItem(photoKey);

    if (photosData) {
      try {
        const sections = JSON.parse(photosData);
        Object.values(sections).forEach(section => {
          if (section.photos && Array.isArray(section.photos)) {
            fotky = fotky.concat(section.photos);
          }
        });
      } catch (e) {
        logger.error('Chyba při načítání fotek:', e);
      }
    }
  }

  const content = `
    ${ModalManager.createHeader('Detail zákazníka', customerName)}

    <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1rem;">

      <!-- KOMPAKTNÍ INFO BLOK -->
      <div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.75rem; margin-bottom: 1rem;">
        <div style="display: grid; grid-template-columns: auto 1fr; gap: 0.5rem; font-size: 0.85rem;">
          <span style="color: #666; font-weight: 600;">Číslo objednávky:</span>
          <input type="text" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem; background: white;" value="${Utils.escapeHtml(reklamaceId)}" readonly>

          <span style="color: #666; font-weight: 600;">Zadavatel:</span>
          <input type="text" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem; background: #f8f9fa;" value="${Utils.escapeHtml(zadavatel)}" readonly placeholder="Prodejce/Uživatel">

          <span style="color: #666; font-weight: 600;">Číslo reklamace:</span>
          <input type="text" id="edit_cislo" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${Utils.escapeHtml(cislo)}">

          <span style="color: #666; font-weight: 600;">Fakturace:</span>
          <span style="color: #1a1a1a; font-weight: 600; padding: 0.25rem 0;">${fakturace_firma.toUpperCase() === 'SK' ? 'Slovensko (SK)' : 'Česká republika (CZ)'}</span>

          <span style="color: #666; font-weight: 600;">Datum prodeje:</span>
          <input type="text" id="edit_datum_prodeje" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${Utils.escapeHtml(datum_prodeje)}" placeholder="DD.MM.RRRR">

          <span style="color: #666; font-weight: 600;">Datum reklamace:</span>
          <input type="text" id="edit_datum_reklamace" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${Utils.escapeHtml(datum_reklamace)}" placeholder="DD.MM.RRRR">

          <span style="color: #666; font-weight: 600;">Jméno:</span>
          <input type="text" id="edit_jmeno" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${customerName}">

          <span style="color: #666; font-weight: 600;">Telefon:</span>
          <input type="tel" id="edit_telefon" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${phone}">

          <span style="color: #666; font-weight: 600;">Email:</span>
          <input type="email" id="edit_email" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${email}">

          <span style="color: #666; font-weight: 600;">Adresa:</span>
          <input type="text" id="edit_adresa" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${address}">

          <span style="color: #666; font-weight: 600;">Model:</span>
          <input type="text" id="edit_model" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${product}">

          <span style="color: #666; font-weight: 600;">Provedení:</span>
          <input type="text" id="edit_provedeni" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${Utils.escapeHtml(provedeni)}" placeholder="Látka / Kůže">

          <span style="color: #666; font-weight: 600;">Barva:</span>
          <input type="text" id="edit_barva" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85rem;" value="${Utils.escapeHtml(barva)}">
        </div>
      </div>

      <!-- DOPLŇUJÍCÍ INFORMACE OD PRODEJCE -->
      <div style="margin-bottom: 1rem;">
        <label style="display: block; color: #666; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.25rem;">Doplňující informace od prodejce:</label>
        <div onclick="showTextOverlay('doplnujici_info')"
             style="width: 100%; border: 1px solid #ddd; padding: 0.5rem; border-radius: 3px; font-size: 0.85rem; min-height: 50px; background: white; cursor: pointer; white-space: pre-wrap; color: ${doplnujici_info ? '#1a1a1a' : '#999'};">
          ${doplnujici_info || 'Klikněte pro zobrazení/zadání doplňujících informací od prodejce'}
        </div>
      </div>

      <!-- POPIS PROBLÉMU OD ZÁKAZNÍKA -->
      <div style="margin-bottom: 2rem;">
        <label style="display: block; color: #666; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.25rem;">Popis problému od zákazníka:</label>
        <div onclick="showTextOverlay('popis_problemu')"
             style="width: 100%; border: 1px solid #ddd; padding: 0.5rem; border-radius: 3px; font-size: 0.85rem; min-height: 60px; background: white; cursor: pointer; white-space: pre-wrap; color: ${description ? '#1a1a1a' : '#999'};">
          ${description || 'Klikněte pro zobrazení/zadání popisu problému od zákazníka'}
        </div>
      </div>

      <!-- FOTOGRAFIE -->
      ${fotky.length > 0 ? `
        <div style="margin-bottom: 1rem;">
          <label style="display: block; color: #666; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.5rem;">Fotografie (${fotky.length}):</label>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 0.5rem;">
            ${fotky.map((f, i) => {
              const photoPath = typeof f === 'object' ? f.photo_path : f;
              const photoId = typeof f === 'object' ? f.id : null;
              const escapedUrl = photoPath.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");

              return `
                <div class="foto-wrapper" style="position: relative;">
                  <img src='${photoPath}'
                       style='width: 100%; aspect-ratio: 1; object-fit: cover; border: 1px solid #ddd; cursor: pointer; border-radius: 3px;'
                       alt='Fotka ${i+1}'
                       onclick='showPhotoFullscreen("${escapedUrl}")'>
                  ${photoId ? `
                    <button class="foto-delete-btn"
                            onclick='event.stopPropagation(); smazatFotku(${photoId}, "${escapedUrl}")'
                            title="Smazat fotku">
                      ×
                    </button>
                  ` : ''}
                </div>
              `;
            }).join('')}
          </div>
        </div>
      ` : ''}

      <!-- PDF DOKUMENTY -->
      ${(() => {
        const docs = CURRENT_RECORD.documents || [];
        // Hledat complete_report (nový formát) nebo fallback na jakýkoliv PDF dokument
        const completeReport = docs.find(d => d.document_type === 'complete_report');
        const anyPdf = docs.length > 0 ? docs[0] : null;
        const pdfDoc = completeReport || anyPdf;

        if (!pdfDoc) {
          return `
            <div style="padding: 0.75rem; text-align: center; background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; margin-bottom: 1rem;">
              <p style="margin: 0; color: #6c757d; font-size: 0.8rem;">PDF report ještě nebyl vytvořen</p>
            </div>
          `;
        }

        return `
          <div style="margin-bottom: 1rem;">
            <label style="display: block; color: #666; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.5rem;">PDF Report:</label>
            <button onclick="window.open('${pdfDoc.file_path.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}', '_blank')"
                    style="width: 100%; padding: 0.75rem; background: #2D5016; color: white; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer; font-weight: 600;">
              📄 Otevřít PDF Report
            </button>
          </div>
        `;
      })()}

      ${CURRENT_USER.is_admin ? `
        <div style="border-top: 1px solid #e0e0e0; padding-top: 1rem; margin-top: 1rem;">
          <button onclick="deleteReklamace('${id}')"
                  style="width: 100%; padding: 0.5rem; background: #dc3545; color: white; border: none; border-radius: 3px; font-size: 0.85rem; cursor: pointer; font-weight: 600;">
            Smazat reklamaci
          </button>
          <p style="font-size: 0.7rem; color: #999; margin-top: 0.25rem; text-align: center;">Smaže vše včetně fotek a PDF</p>
        </div>
      ` : ''}

    </div>

    ${ModalManager.createActions([
      '<button class="btn btn-secondary" onclick="showDetail(CURRENT_RECORD)">Zpět</button>',
      '<button class="btn" style="background: #1a1a1a; color: white;" onclick="saveAllCustomerData(\'' + id + '\')">Uložit změny</button>'
    ])}
  `;

  ModalManager.show(content);
}

function showPhotoFullscreen(photoUrl) {
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; align-items: center; justify-content: center; cursor: pointer;';
  overlay.onclick = () => overlay.remove();

  const img = document.createElement('img');
  img.src = photoUrl;
  img.style.cssText = 'max-width: 95%; max-height: 95%; object-fit: contain;';

  overlay.appendChild(img);
  document.body.appendChild(overlay);
}

function showTextOverlay(fieldName) {
  if (!CURRENT_RECORD) return;

  const nadpis = fieldName === 'popis_problemu' ? 'Popis problému' : 'Doplňující informace';
  const currentText = CURRENT_RECORD[fieldName] || '';

  const overlay = document.createElement('div');
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 2rem;';

  const contentBox = document.createElement('div');
  contentBox.style.cssText = 'background: white; padding: 1.5rem; border-radius: 6px; max-width: 700px; width: 100%; max-height: 85vh; display: flex; flex-direction: column;';
  contentBox.onclick = (e) => e.stopPropagation();

  const header = document.createElement('div');
  header.style.cssText = 'font-weight: 600; font-size: 1rem; color: #1a1a1a; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #dee2e6;';
  header.textContent = nadpis;

  const textareaWrapper = document.createElement('div');
  textareaWrapper.style.cssText = 'flex: 1; overflow-y: auto; margin-bottom: 1rem;';

  const textarea = document.createElement('textarea');
  textarea.style.cssText = 'width: 100%; min-height: 300px; border: 1px solid #ddd; padding: 0.75rem; border-radius: 4px; font-size: 0.9rem; line-height: 1.6; font-family: inherit; resize: vertical;';
  textarea.value = currentText;
  textarea.placeholder = 'Zadejte text...';

  textareaWrapper.appendChild(textarea);

  const buttonRow = document.createElement('div');
  buttonRow.style.cssText = 'display: flex; gap: 0.5rem; padding-top: 1rem; border-top: 1px solid #dee2e6;';

  const saveBtn = document.createElement('button');
  saveBtn.style.cssText = 'flex: 1; padding: 0.5rem 1rem; background: #1a1a1a; color: white; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer; font-weight: 600;';
  saveBtn.textContent = 'Uložit změny';
  saveBtn.onclick = async () => {
    const newValue = textarea.value;

    try {
      const csrfToken = await getCSRFToken();
      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('id', CURRENT_RECORD.id);
      formData.append(fieldName, newValue);
      formData.append('csrf_token', csrfToken);

      const response = await fetch('/app/controllers/save.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.status === 'success') {
        // Aktualizovat v cache
        CURRENT_RECORD[fieldName] = newValue;
        const cacheRecord = WGS_DATA_CACHE.find(x => x.id == CURRENT_RECORD.id);
        if (cacheRecord) {
          cacheRecord[fieldName] = newValue;
        }
        overlay.remove();
        // Znovu otevřít detail s aktualizovanými daty
        showCustomerDetail(CURRENT_RECORD.id);
        alert('Text byl úspěšně uložen');
      } else {
        alert('Chyba při ukládání: ' + result.message);
      }
    } catch (error) {
      alert('Chyba při ukládání: ' + error.message);
    }
  };

  const cancelBtn = document.createElement('button');
  cancelBtn.style.cssText = 'flex: 1; padding: 0.5rem 1rem; background: #666; color: white; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer;';
  cancelBtn.textContent = 'Zrušit';
  cancelBtn.onclick = () => overlay.remove();

  buttonRow.appendChild(saveBtn);
  buttonRow.appendChild(cancelBtn);

  contentBox.appendChild(header);
  contentBox.appendChild(textareaWrapper);
  contentBox.appendChild(buttonRow);
  overlay.appendChild(contentBox);
  document.body.appendChild(overlay);

  // Zavřít při kliknutí na overlay pozadí
  overlay.onclick = (e) => {
    if (e.target === overlay) {
      overlay.remove();
    }
  };

  // Focus na textarea
  setTimeout(() => textarea.focus(), 100);
}

async function saveAllCustomerData(id) {
  const data = {
    action: 'update',
    id: id,
    cislo: document.getElementById('edit_cislo').value,
    datum_prodeje: document.getElementById('edit_datum_prodeje').value,
    datum_reklamace: document.getElementById('edit_datum_reklamace').value,
    jmeno: document.getElementById('edit_jmeno').value,
    telefon: document.getElementById('edit_telefon').value,
    email: document.getElementById('edit_email').value,
    adresa: document.getElementById('edit_adresa').value,
    model: document.getElementById('edit_model').value,
    provedeni: document.getElementById('edit_provedeni').value,
    barva: document.getElementById('edit_barva').value
  };

  await saveData(data, 'Všechny údaje byly aktualizovány');
}

// === ODESLÁNÍ POTVRZENÍ TERMÍNU ===
async function sendAppointmentConfirmation(customer, date, time) {
  const customerName = Utils.getCustomerName(customer);
  const phone = customer.telefon || '';
  const email = customer.email || '';
  const orderId = Utils.getOrderId(customer);

  try {
    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const response = await fetch('app/notification_sender.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        notification_id: "appointment_confirmed",
        csrf_token: csrfToken,
        data: {
          customer_name: customerName,
          customer_email: email,
          seller_email: "admin@wgs-service.cz",
          date: date,
          time: time,
          order_id: orderId
        }
      })
    });

    const result = await response.json();

    if (result.success === true) {
      logger.log('✓ Potvrzení termínu odesláno zákazníkovi');
      if (result.sent) {
        logger.log('  ✓ Email odeslán na:', result.to || email);
      }
      if (result.sms_sent) {
        logger.log('  ✓ SMS odeslána na:', phone);
      }
    } else {
      logger.error('⚠ Chyba při odesílání potvrzení:', result.error || result.message);
    }
  } catch (error) {
    logger.error('❌ Chyba při odesílání potvrzení:', error);
  }
}

// === SYSTÉM POZNÁMEK - API VERSION ===

async function getNotes(orderId) {
  try {
    const record = WGS_DATA_CACHE.find(x => x.id == orderId || x.reklamace_id == orderId);
    if (!record) return [];

    const reklamaceId = record.reklamace_id || record.id;
    const response = await fetch(`api/notes_api.php?action=get&reklamace_id=${encodeURIComponent(reklamaceId)}`);
    const data = await response.json();

    if (data.status === 'success' || data.success === true) {
      return data.notes || [];
    }
    return [];
  } catch (e) {
    logger.error('Chyba při načítání poznámek:', e);
    return [];
  }
}

async function addNote(orderId, text) {
  try {
    const record = WGS_DATA_CACHE.find(x => x.id == orderId || x.reklamace_id == orderId);
    if (!record) {
      throw new Error('Reklamace nenalezena');
    }

    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const reklamaceId = record.reklamace_id || record.id;
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('reklamace_id', reklamaceId);
    formData.append('text', text.trim());
    formData.append('csrf_token', csrfToken);

    const response = await fetch('api/notes_api.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.status === 'success' || data.success === true) {
      return { success: true, note_id: data.note_id };
    } else {
      throw new Error(data.message || 'Chyba při přidávání poznámky');
    }
  } catch (e) {
    logger.error('Chyba při přidávání poznámky:', e);
    throw e;
  }
}

async function markNotesAsRead(orderId) {
  try {
    const record = WGS_DATA_CACHE.find(x => x.id == orderId || x.reklamace_id == orderId);
    if (!record) return;

    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const reklamaceId = record.reklamace_id || record.id;
    const formData = new FormData();
    formData.append('action', 'mark_read');
    formData.append('reklamace_id', reklamaceId);
    formData.append('csrf_token', csrfToken);

    await fetch('api/notes_api.php', {
      method: 'POST',
      body: formData
    });
  } catch (e) {
    logger.error('Chyba při označování poznámek:', e);
  }
}

async function showNotes(record) {
  CURRENT_RECORD = record;
  const customerName = Utils.getCustomerName(record);

  const loadingContent = `
    ${ModalManager.createHeader('Poznámky', customerName)}
    <div class="modal-body" style="text-align: center; padding: 3rem;">
      <div class="loading">Načítání poznámek...</div>
    </div>
  `;
  ModalManager.show(loadingContent);

  const notes = await getNotes(record.id);

  notes.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

  const content = `
    ${ModalManager.createHeader('Poznámky', customerName)}

    <div class="modal-body">
      <div class="notes-container">
        ${notes.length > 0
          ? notes.map(note => `
              <div class="note-item ${note.read ? '' : 'unread'}">
                <div class="note-header">
                  <span class="note-author">${note.author_name || note.author}</span>
                  <span class="note-time">${formatDateTime(note.timestamp)}</span>
                </div>
                <div class="note-text">${Utils.escapeHtml(note.text)}</div>
              </div>
            `).join('')
          : '<div class="empty-notes">Zatím žádné poznámky</div>'
        }
      </div>

      <div class="note-input-area">
        <textarea
          class="note-textarea"
          id="newNoteText"
          placeholder="Napište poznámku..."
        ></textarea>
      </div>

      <div style="background: var(--c-bg); border: 1px solid var(--c-border); padding: 1rem; margin-top: 1.5rem;">
        <h3 style="font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--c-black); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--c-border);">📄 PDF Report</h3>
        ${(() => {
          const docs = CURRENT_RECORD.documents || [];
          // Hledat complete_report (nový formát) nebo fallback na jakýkoliv PDF dokument
          const completeReport = docs.find(d => d.document_type === 'complete_report');
          const anyPdf = docs.length > 0 ? docs[0] : null;
          const pdfDoc = completeReport || anyPdf;

          if (!pdfDoc) {
            return `
              <div style="padding: 1rem; text-align: center; background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 4px;">
                <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">ℹ️ PDF report ještě nebyl vytvořen</p>
                <p style="margin: 0.3rem 0 0 0; font-size: 0.8rem; color: #adb5bd;">Vytvoří se po dokončení servisu</p>
              </div>
            `;
          }

          return `
            <button onclick="window.open('${pdfDoc.file_path.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}', '_blank')"
                    class="btn"
                    style="width: 100%; padding: 0.75rem; background: #2D5016; color: white; border: none; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; border-radius: 4px;">
              📄 Otevřít PDF Report
            </button>
            <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--c-grey); text-align: center;">
              Vytvořeno: ${new Date(pdfDoc.uploaded_at || pdfDoc.created_at).toLocaleString('cs-CZ')}
            </p>
          `;
        })()}

      ${CURRENT_USER.is_admin ? `
        <div style="background: #fff5f5; border: 2px solid #ff4444; padding: 1rem; margin-top: 1.5rem; border-radius: 4px;">
          <h3 style="color: #ff4444; font-size: 0.9rem; font-weight: 600; margin-bottom: 1rem;">⚠️ ADMIN PANEL</h3>
          <button onclick="deleteReklamace('${id}')"
                  style="width: 100%; padding: 1rem; background: #ff4444; color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">
            🗑️ Smazat celou reklamaci
          </button>
          <p style="font-size: 0.75rem; color: #999; margin-top: 0.5rem; text-align: center;">Smaže vše včetně fotek a PDF</p>
        </div>
      ` : ''}

    </div>

    ${ModalManager.createActions([
      '<button class="btn btn-secondary" onclick="closeNotesModal()">Zavřít</button>',
      '<button class="btn btn-success" onclick="saveNewNote(\'' + record.id + '\')">Přidat poznámku</button>'
    ])}
  `;

  ModalManager.show(content);

  setTimeout(async () => {
    await markNotesAsRead(record.id);
    await loadAll(ACTIVE_FILTER);
  }, 1000);
}

async function saveNewNote(orderId) {
  const textarea = document.getElementById('newNoteText');
  const text = textarea.value.trim();

  if (!text) {
    alert('Napište text poznámky');
    return;
  }

  try {
    await addNote(orderId, text);

    const record = CURRENT_RECORD;
    await showNotes(record);

    await loadAll(ACTIVE_FILTER);
  } catch (e) {
    alert('Chyba při ukládání poznámky: ' + e.message);
  }
}

function closeNotesModal() {
  closeDetail();
  renderOrders();
}

function formatDateTime(isoString) {
  const date = new Date(isoString);
  const now = new Date();
  const diff = now - date;
  
  if (diff < 60000) {
    return 'Právě teď';
  }
  
  if (diff < 3600000) {
    const mins = Math.floor(diff / 60000);
    return `Před ${mins} min`;
  }
  
  if (diff < 86400000) {
    const hours = Math.floor(diff / 3600000);
    return `Před ${hours} h`;
  }
  
  return date.toLocaleDateString('cs-CZ', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

// === UTILITY ===
function getStatus(stav) {
  const statusMap = {
    'ČEKÁ': { class: 'wait', text: 'NOVÁ' },
    'wait': { class: 'wait', text: 'NOVÁ' },
    'DOMLUVENÁ': { class: 'open', text: 'DOMLUVENÁ' },
    'open': { class: 'open', text: 'DOMLUVENÁ' },
    'HOTOVO': { class: 'done', text: 'HOTOVO' },
    'done': { class: 'done', text: 'HOTOVO' }
  };
  
  return statusMap[stav] || { class: 'wait', text: 'NOVÁ' };
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const date = new Date(dateStr);
  if (isNaN(date)) return dateStr;
  return date.toLocaleDateString('cs-CZ');
}

function formatAppointment(dateStr, timeStr) {
  if (!dateStr || !timeStr) return '';
  
  const parts = dateStr.split('.');
  if (parts.length !== 3) return `${dateStr} ${timeStr}`;
  
  const day = parseInt(parts[0]);
  const month = parseInt(parts[1]);
  const year = parseInt(parts[2]);
  
  const date = new Date(year, month - 1, day);
  
  const weekdays = ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'];
  const weekday = weekdays[date.getDay()];
  
  return `${weekday} ${day}.${month}.-${timeStr}`;
}

// === HAMBURGER MENU ===
function toggleMenu() {
  const navLinks = document.getElementById('navLinks');
  const hamburger = document.querySelector('.hamburger');
  
  navLinks.classList.toggle('active');
  hamburger.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;
    
    const action = target.getAttribute('data-action');
    
    if (action === 'reload') {
      location.reload();
      return;
    }
    
    if (typeof window[action] === 'function') {
      window[action]();
    }
  });

  document.addEventListener('click', (e) => {
    const navigate = e.target.closest('[data-navigate]')?.getAttribute('data-navigate');
    if (navigate) {
      if (typeof navigateTo === 'function') {
        navigateTo(navigate);
      } else {
        location.href = navigate;
      }
    }
  });

  document.addEventListener('change', (e) => {
    const target = e.target.closest('[data-onchange]');
    if (!target) return;
    
    const action = target.getAttribute('data-onchange');
    const value = target.getAttribute('data-onchange-value') || target.value;
    
    if (typeof window[action] === 'function') {
      window[action](value);
    }
  });
});

// === SMAZÁNÍ REKLAMACE (ADMIN ONLY) ===
async function deleteReklamace(reklamaceId) {
  const confirmed = confirm(
    '⚠️ VAROVÁNÍ!\n\n' +
    'Opravdu chcete TRVALE SMAZAT tuto reklamaci?\n\n' +
    'Bude smazáno:\n' +
    '• Záznam z databáze\n' +
    '• Všechny fotografie\n' +
    '• PDF protokoly\n' +
    '• Poznámky a historie\n\n' +
    'Tato akce je NEVRATNÁ!'
  );
  
  if (!confirmed) {
    logger.log('Mazání zrušeno (1. krok)');
    return;
  }
  
  const reklamaceNumber = CURRENT_RECORD.reklamace_id || CURRENT_RECORD.id || reklamaceId;
  const userInput = prompt(
    'Pro potvrzení smazání napište číslo reklamace:\n\n' + reklamaceNumber
  );
  
  if (userInput !== reklamaceNumber) {
    alert('Nesprávné číslo. Mazání zrušeno.');
    logger.log('Mazání zrušeno - špatné číslo (2. krok)');
    return;
  }
  
  logger.log('🗑️ Mazání reklamace:', reklamaceId);

  try {
    // Získat CSRF token
    const csrfToken = await getCSRFToken();

    const response = await fetch('api/delete_reklamace.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        reklamace_id: reklamaceId,
        csrf_token: csrfToken
      })
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${result.message || result.error || response.statusText}`);
    }

    if (result.success || result.status === 'success') {
      logger.log('✅ Smazáno!');
      alert('✅ Reklamace byla úspěšně smazána!');
      closeDetail();
      setTimeout(() => location.reload(), 500);
    } else {
      const errorMsg = result.message || result.error || 'Nepodařilo se smazat';
      logger.error('❌ Chyba:', errorMsg);
      alert('Chyba: ' + errorMsg);
    }
  } catch (error) {
    logger.error('❌ Chyba při mazání:', error);
    alert('Chyba při mazání: ' + error.message);
  }
}

// === SMAZÁNÍ JEDNOTLIVÉ FOTKY ===
async function smazatFotku(photoId, photoUrl) {
  const confirmed = confirm('Opravdu chcete smazat tuto fotku?\n\nTato akce je nevratná!');

  if (!confirmed) {
    logger.log('Mazání fotky zrušeno');
    return;
  }

  logger.log('🗑️ Mazání fotky ID:', photoId);

  try {
    // Získat CSRF token
    const csrfToken = await getCSRFToken();

    const formData = new FormData();
    formData.append('photo_id', photoId);
    formData.append('csrf_token', csrfToken);

    const response = await fetch('api/delete_photo.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${result.message || result.error || response.statusText}`);
    }

    if (result.status === 'success') {
      logger.log('✅ Fotka smazána!');

      // Odstranit fotku z DOM
      const fotoElements = document.querySelectorAll('.foto-wrapper img');
      for (const img of fotoElements) {
        if (img.src.includes(photoUrl.replace(/\\/g, ''))) {
          img.closest('.foto-wrapper').remove();
          break;
        }
      }

      // Aktualizovat počet fotek v nadpisu
      const fotkyNadpis = document.querySelector('[style*="Fotografie"]');
      if (fotkyNadpis) {
        const zbyvajiciFotky = document.querySelectorAll('.foto-wrapper').length;
        fotkyNadpis.textContent = `Fotografie (${zbyvajiciFotky})`;

        // Pokud nezbyla žádná fotka, zobrazit "Žádné fotografie"
        if (zbyvajiciFotky === 0) {
          const fotoContainer = fotkyNadpis.closest('div');
          const grid = fotoContainer.querySelector('[style*="grid"]');
          if (grid) {
            grid.innerHTML = '<p style="color: var(--c-grey); text-align: center; padding: 1rem; font-size: 0.85rem;">Žádné fotografie</p>';
          }
        }
      }

      alert('✅ Fotka byla úspěšně smazána!');
    } else {
      const errorMsg = result.message || result.error || 'Nepodařilo se smazat fotku';
      logger.error('❌ Chyba:', errorMsg);
      alert('Chyba: ' + errorMsg);
    }
  } catch (error) {
    logger.error('❌ Chyba při mazání fotky:', error);
    alert('Chyba při mazání fotky: ' + error.message);
  }
}

// Načti fotky z databáze
async function loadPhotosFromDB(reklamaceId) {
  try {
    const response = await fetch(`api/get_photos_api.php?reklamace_id=${reklamaceId}`);
    if (!response.ok) return [];

    const data = await response.json();
    if (data.success && data.photos) {
      // ✅ Vrátit celé objekty včetně ID pro možnost mazání
      return data.photos.map(p => ({
        id: p.id,
        photo_path: p.photo_path,
        section_name: p.section_name
      }));
    }
    return [];
  } catch (err) {
    logger.error('Chyba načítání fotek:', err);
    return [];
  }
}

// ✅ PAGINATION: Load more handler
async function loadMoreOrders() {
  if (LOADING_MORE || !HAS_MORE_PAGES) return;

  LOADING_MORE = true;
  const btn = document.getElementById('loadMoreBtn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Načítání...';
  }

  await loadAll(ACTIVE_FILTER, true); // append = true
}

// ✅ PAGINATION: Update "Load More" button visibility
function updateLoadMoreButton() {
  let btn = document.getElementById('loadMoreBtn');

  // Create button if doesn't exist
  if (!btn) {
    const grid = document.getElementById('orderGrid');
    if (grid && grid.parentElement) {
      btn = document.createElement('button');
      btn.id = 'loadMoreBtn';
      btn.className = 'load-more-btn';
      btn.textContent = 'Načíst další zakázky';
      btn.onclick = loadMoreOrders;

      grid.parentElement.appendChild(btn);
    }
  }

  // Show/hide based on HAS_MORE_PAGES
  if (btn) {
    btn.style.display = HAS_MORE_PAGES ? 'block' : 'none';
    btn.disabled = LOADING_MORE;
    btn.textContent = LOADING_MORE ? 'Načítání...' : `Načíst další (stránka ${CURRENT_PAGE + 1})`;
  }
}

// === ODESLÁNÍ POKUSU O KONTAKT (EMAIL + SMS) ===

/**
 * Odešle zákazníkovi email o pokusu o kontakt a otevře SMS aplikaci s předvyplněným textem
 * @param {string} reklamaceId - ID reklamace
 * @param {string} telefon - Telefonní číslo zákazníka
 */
async function sendContactAttemptEmail(reklamaceId, telefon) {
  try {
    // Najít záznam v cache
    const zaznam = WGS_DATA_CACHE.find(x => x.id == reklamaceId || x.reklamace_id == reklamaceId);
    if (!zaznam) {
      showToast('Záznam nenalezen', 'error');
      return;
    }

    // Získat CSRF token
    const csrfToken = await getCSRFToken();

    // Zavolat API pro odeslání emailu
    const odpoved = await fetch('/api/send_contact_attempt_email.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        reklamace_id: reklamaceId,
        csrf_token: csrfToken
      })
    });

    const data = await odpoved.json();

    if (data.success) {
      logger.log('✓ Email o pokusu o kontakt odeslán zákazníkovi');
      showToast('Email odeslán zákazníkovi', 'success');

      // Připravit SMS text se stejnými informacemi jako v emailu
      const jmeno = zaznam.jmeno || zaznam.zakaznik || 'zákazníku';
      const cisloZakazky = zaznam.reklamace_id || zaznam.id || '—';
      const produkt = zaznam.produkt || 'neuvedeno';
      const datum = new Date().toLocaleDateString('cs-CZ');

      const smsText = `Dobrý den ${jmeno}, pokusili jsme se Vás kontaktovat ohledně servisní prohlídky č. ${cisloZakazky} (${produkt}, ${datum}). Prosím zavolejte zpět na +420 725 965 826. Děkujeme, WGS Service`;

      // Otevřít SMS aplikaci na telefonu s předvyplněným textem
      const encodedText = encodeURIComponent(smsText);
      window.location.href = `sms:${telefon}?body=${encodedText}`;

    } else {
      logger.error('⚠ Chyba při odesílání emailu:', data.error || data.message);
      showToast(data.error || 'Chyba při odesílání emailu', 'error');
    }

  } catch (chyba) {
    logger.error('❌ Chyba při odesílání kontaktního emailu:', chyba);
    showToast('Nepodařilo se odeslat email', 'error');
  }
}