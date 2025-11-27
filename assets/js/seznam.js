// VERSION CHECK: 20251122-04 - Distance API vypnuto
console.log('🔍 SEZNAM.JS NAČTEN - VERZE: 20251122-04 (distance API vypnuto)');

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

// === TOAST NOTIFICATION FUNKCE ===
function showToast(message, type = 'info') {
  // Odstranit existující toast pokud existuje
  const existingToast = document.getElementById('wgs-toast');
  if (existingToast) {
    existingToast.remove();
  }

  // Vytvořit nový toast element
  const toast = document.createElement('div');
  toast.id = 'wgs-toast';
  toast.textContent = message;

  // Styly pro toast
  toast.style.cssText = `
    position: fixed;
    top: 80px;
    right: 20px;
    background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#333'};
    color: white;
    padding: 16px 24px;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 10000;
    font-size: 14px;
    font-weight: 600;
    min-width: 250px;
    max-width: 400px;
    animation: slideIn 0.3s ease-out;
  `;

  // Přidat animaci
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(400px);
        opacity: 0;
      }
    }
  `;

  if (!document.getElementById('wgs-toast-styles')) {
    style.id = 'wgs-toast-styles';
    document.head.appendChild(style);
  }

  document.body.appendChild(toast);

  // Automaticky odstranit po 3 sekundách
  setTimeout(() => {
    toast.style.animation = 'slideOut 0.3s ease-in';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// === GLOBÁLNÍ PROMĚNNÉ ===
let WGS_DATA_CACHE = [];
let ACTIVE_FILTER = 'all';
let CURRENT_RECORD = null;
let SELECTED_DATE = null;
let SELECTED_TIME = null;

// PAGINATION FIX: Tracking pagination state
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
        logger.log('Načteno', Object.keys(DISTANCE_CACHE).length, 'vzdáleností z cache');
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

// === AUTO-REFRESH KONFIGURACE ===
const AUTO_REFRESH_INTERVAL = 60000; // 60 sekund
let autoRefreshTimer = null;
let lastRefreshTime = Date.now();

// === INIT ===
window.addEventListener('DOMContentLoaded', async () => {
  CacheManager.load();


  initFilters();
  initSearch();
  await loadAll();

  // Spustit auto-refresh
  startAutoRefresh();

  // Inicializovat pull-to-refresh pro PWA
  initPullToRefresh();

  // Refresh pri navratu na stranku (tab visibility)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      const timeSinceLastRefresh = Date.now() - lastRefreshTime;
      // Refresh pokud byl tab skryty dele nez 30 sekund
      if (timeSinceLastRefresh > 30000) {
        logger.log('[AutoRefresh] Tab aktivni - nacitam nova data...');
        loadAll(ACTIVE_FILTER);
      }
    }
  });

  // Zobrazit dialog pro povoleni notifikaci (po 3 sekundach)
  setTimeout(() => {
    if (window.WGSNotifikace && typeof window.WGSNotifikace.zobrazitDialogPovoleni === 'function') {
      window.WGSNotifikace.zobrazitDialogPovoleni();
    }
  }, 3000);
});

// === AUTO-REFRESH FUNKCE ===
function startAutoRefresh() {
  // Zastavit predchozi timer pokud existuje
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer);
  }

  // Spustit novy timer
  autoRefreshTimer = setInterval(async () => {
    // Pouze refreshovat pokud je stranka viditelna
    if (document.visibilityState === 'visible') {
      logger.log('[AutoRefresh] Automaticka aktualizace dat...');
      await loadAll(ACTIVE_FILTER);
      lastRefreshTime = Date.now();
    }
  }, AUTO_REFRESH_INTERVAL);

  logger.log(`[AutoRefresh] Spusten - interval ${AUTO_REFRESH_INTERVAL/1000}s`);
}

function stopAutoRefresh() {
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = null;
    logger.log('[AutoRefresh] Zastaven');
  }
}

// === PULL TO REFRESH (PWA) ===
let pullStartY = 0;
let pullDistance = 0;
let isPulling = false;
const PULL_THRESHOLD = 80; // px pro aktivaci refreshe

function initPullToRefresh() {
  const container = document.getElementById('orderGrid');
  if (!container) return;

  // Vytvorit indikator
  const indicator = document.createElement('div');
  indicator.id = 'pullToRefreshIndicator';
  indicator.innerHTML = 'Tahni pro obnoveni...';
  indicator.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 50px;
    background: #222;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transform: translateY(-100%);
    transition: transform 0.2s ease;
    z-index: 9999;
  `;
  document.body.appendChild(indicator);

  // Touch events
  document.addEventListener('touchstart', (e) => {
    // Pouze kdyz jsme na vrchu stranky
    if (window.scrollY <= 0) {
      pullStartY = e.touches[0].clientY;
      isPulling = true;
    }
  }, { passive: true });

  document.addEventListener('touchmove', (e) => {
    if (!isPulling || window.scrollY > 0) return;

    const currentY = e.touches[0].clientY;
    pullDistance = currentY - pullStartY;

    // Pouze tazeni dolu
    if (pullDistance > 0) {
      const progress = Math.min(pullDistance / PULL_THRESHOLD, 1);
      indicator.style.transform = `translateY(${-100 + (progress * 100)}%)`;

      if (pullDistance >= PULL_THRESHOLD) {
        indicator.innerHTML = 'Pust pro obnoveni';
      } else {
        indicator.innerHTML = 'Tahni pro obnoveni...';
      }
    }
  }, { passive: true });

  document.addEventListener('touchend', async () => {
    if (!isPulling) return;

    if (pullDistance >= PULL_THRESHOLD) {
      indicator.innerHTML = 'Nacitam...';
      indicator.style.transform = 'translateY(0)';

      // Refresh data
      await loadAll(ACTIVE_FILTER);
      lastRefreshTime = Date.now();

      // Aktualizovat badge
      if (window.WGSNotifikace) {
        window.WGSNotifikace.aktualizovat();
      }

      logger.log('[PullToRefresh] Data obnovena');
    }

    // Reset
    setTimeout(() => {
      indicator.style.transform = 'translateY(-100%)';
    }, 500);

    pullStartY = 0;
    pullDistance = 0;
    isPulling = false;
  }, { passive: true });

  logger.log('[PullToRefresh] Inicializovan');
}

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

  // SECURITY FIX: Escape HTML PŘED highlightováním
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
    // PAGINATION FIX: Přidat page a per_page parametry
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

    // PAGINATION: Append místo replace při loadMore
    if (append) {
      WGS_DATA_CACHE = [...WGS_DATA_CACHE, ...items];
      CURRENT_PAGE = page;
    } else {
      WGS_DATA_CACHE = items;
      CURRENT_PAGE = 1;
    }

    // PAGINATION: Detekce zda jsou další stránky
    HAS_MORE_PAGES = items.length === PER_PAGE;
    LOADING_MORE = false;

    let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);

    updateCounts(userItems);
    renderOrders(userItems);

    // PAGINATION: Zobrazit/skrýt "Načíst další" tlačítko
    updateLoadMoreButton();

    // Aktualizovat cas posledniho refreshe
    lastRefreshTime = Date.now();
  } catch (err) {
    logger.error('Chyba:', err);
    WGS_DATA_CACHE = [];
    document.getElementById('orderGrid').innerHTML = `
      <div class="empty-state">
        <div class="empty-state-text">${t('data_load_error')}</div>
      </div>
    `;
  }
}

// === VYKRESLENÍ OBJEDNÁVEK ===
async function renderOrders(items = null) {
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
      searchResultsInfo.textContent = t('search_results_found')
        .replace('{count}', filtered.length)
        .replace('{total}', totalBeforeSearch)
        .replace('{query}', SEARCH_QUERY);
    } else {
      searchResultsInfo.className = 'search-results-info no-results';
      searchResultsInfo.textContent = t('no_search_results').replace('{query}', SEARCH_QUERY);
    }
    searchResultsInfo.style.display = 'block';
  } else {
    searchResultsInfo.style.display = 'none';
  }

  if (filtered.length === 0) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-text">${SEARCH_QUERY ? t('no_results_found') : t('no_claims_to_display')}</div>
      </div>
    `;
    return;
  }

  // Načíst unread counts pro všechny reklamace najednou
  let unreadCountsMap = {};
  try {
    const response = await fetch('api/notes_api.php?action=get_unread_counts');
    const data = await response.json();
    if (data.status === 'success') {
      unreadCountsMap = data.unread_counts || {};
    }
  } catch (e) {
    logger.warn('Nepodařilo se načíst unread counts:', e);
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

    // Datum termínu se ukazuje POUZE u stavu DOMLUVENÁ (open)
    // - NOVÁ: bez datumu
    // - DOMLUVENÁ: s datem termínu
    // - HOTOVO: bez datumu
    let appointmentText = '';
    const isDomluvena = status.class === 'open';
    if (isDomluvena && rec.termin && rec.cas_navstevy) {
      appointmentText = formatAppointment(rec.termin, rec.cas_navstevy);
    }

    // Načíst unread count z mapy
    const claimId = rec.id;
    const unreadCount = unreadCountsMap[claimId] || 0;
    const hasUnread = unreadCount > 0;

    if (unreadCount > 0) {
    }
    
    const highlightedCustomer = SEARCH_QUERY ? highlightText(customerName, SEARCH_QUERY) : customerName;
    const highlightedAddress = SEARCH_QUERY ? highlightText(address, SEARCH_QUERY) : address;
    const highlightedProduct = SEARCH_QUERY ? highlightText(product, SEARCH_QUERY) : product;
    const highlightedOrderId = SEARCH_QUERY ? highlightText(orderId, SEARCH_QUERY) : orderId;
    
    const searchMatchClass = SEARCH_QUERY && matchesSearch(rec, SEARCH_QUERY) ? 'search-match' : '';
    // Přidat barevný nádech podle stavu
    const statusBgClass = `status-bg-${status.class}`;

    return `
      <div class="order-box ${searchMatchClass} ${statusBgClass}" onclick='showDetail("${rec.id}")'>
        <div class="order-header">
          <div class="order-number">${highlightedOrderId}</div>
          <div style="display: flex; gap: 0.4rem; align-items: center;">
            <div class="order-notes-badge ${hasUnread ? 'has-unread pulse' : ''}" data-action="showNotes" data-id="${rec.id}" title="${unreadCount > 0 ? unreadCount + ' nepřečtené' : 'Poznámky'}">
              <span class="notes-icon">✎</span>${unreadCount > 0 ? unreadCount : ''}
            </div>
            <div class="order-status status-${status.class}"></div>
          </div>
        </div>
        <div class="order-body">
          <div class="order-customer">${highlightedCustomer}</div>
          <div class="order-detail">
            <div class="order-detail-line">${highlightedAddress}</div>
            <div class="order-detail-line">${highlightedProduct}</div>
            <div class="order-detail-line" style="opacity: 0.6;">${date}</div>
          </div>
        </div>
        <div class="order-footer">
          ${appointmentText
            ? `<span class="order-appointment">${appointmentText}</span>`
            : `<span class="order-status-text status-${status.class}">${status.text}</span>`}
        </div>
      </div>
    `;
  }).join('');

  // Aktualizovat indikátor nových poznámek
  const totalUnreadCount = Object.values(unreadCountsMap).reduce((sum, count) => sum + count, 0);

  const unreadIndicator = document.getElementById('unreadNotesIndicator');
  const unreadCountSpan = document.getElementById('unreadNotesCount');


  if (totalUnreadCount > 0) {
    unreadCountSpan.textContent = totalUnreadCount;
    unreadIndicator.style.display = 'block';
  } else {
    unreadIndicator.style.display = 'none';
  }

  // Uložit unreadCountsMap pro filtrování
  window.UNREAD_COUNTS_MAP = unreadCountsMap;
}

// === FILTROVÁNÍ PODLE NEPŘEČTENÝCH POZNÁMEK ===
function filterUnreadNotes() {
  const unreadCountsMap = window.UNREAD_COUNTS_MAP || {};

  // Najít všechny karty s nepřečtenými poznámkami
  const cardsWithUnread = WGS_DATA_CACHE.filter(rec => {
    const claimId = rec.id;
    return unreadCountsMap[claimId] > 0;
  });

  logger.log(`[Seznam] Filtrování nepřečtených poznámek: ${cardsWithUnread.length} karet`);

  // Vyrenderovat pouze karty s nepřečtenými poznámkami
  const grid = document.getElementById('orderGrid');

  if (cardsWithUnread.length === 0) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-text">Žádné nepřečtené poznámky</div>
      </div>
    `;
    return;
  }

  // Použít stejnou logiku jako renderOrders, ale s filtrovanými daty
  renderOrders(cardsWithUnread);

  // Scroll na začátek seznamu
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// === MODAL MANAGER ===
const ModalManager = {
  show: (content) => {
    // iOS scroll lock
    window.modalScrollPosition = window.pageYOffset;
    document.body.style.position = 'fixed';
    document.body.style.top = `-${window.modalScrollPosition}px`;
    document.body.style.width = '100%';
    document.body.style.left = '0';
    document.body.style.right = '0';

    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('detailOverlay').classList.add('active');
  },

  close: () => {
    document.getElementById('detailOverlay').classList.remove('active');

    // Obnovit scroll
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    document.body.style.left = '';
    document.body.style.right = '';
    window.scrollTo(0, window.modalScrollPosition);

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
      alert(t('record_not_found'));
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
          <div style="font-size: 0.9rem; font-weight: 600; color: #1a1a1a; margin-bottom: 0.25rem;">Zakázka dokončena</div>
          <div style="font-size: 0.75rem; color: #666;">Tato zakázka byla již vyřízena</div>
        </div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; background: #333; color: white; font-weight: 600; font-size: 0.9rem;" data-action="reopenOrder" data-id="${record.id}">
          Znovu otevřít
        </button>

        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem;" data-action="showContactMenu" data-id="${record.id}">Kontaktovat</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem;" data-action="showCustomerDetail" data-id="${record.id}">Detail zákazníka</button>

      <div style="width: 100%; margin-top: 0.25rem;">
        ${record.original_reklamace_id ? `
          <!-- Zakázka je KLON - zobrazit Historie zákazníka + PDF REPORT -->
          <button class="btn" style="background: #555; color: white; width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem; margin-bottom: 0.5rem;"
                  data-action="showHistoryPDF" data-original-id="${record.original_reklamace_id}">
            📚 Historie zákazníka
          </button>
          ${record.documents && record.documents.length > 0 ? `
            <button class="btn" style="background: #333333; color: white; width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem; font-weight: 600;"
                    data-action="openPDF" data-url="${record.documents[0].file_path}" data-id="${record.id}">
              [Doc] PDF REPORT
            </button>
          ` : `
            <div style="background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; padding: 0.5rem; text-align: center; color: #666; font-size: 0.75rem;">
              PDF report ještě nebyl vytvořen
            </div>
          `}
        ` : `
          <!-- Původní zakázka - standardní PDF tlačítko -->
          ${record.documents && record.documents.length > 0 ? `
            <button class="btn" style="background: #333333; color: white; width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem; font-weight: 600;"
                    data-action="openPDF" data-url="${record.documents[0].file_path}" data-id="${record.id}">
              [Doc] PDF REPORT
            </button>
          ` : `
            <div style="background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; padding: 0.5rem; text-align: center; color: #666; font-size: 0.75rem;">
              PDF report ještě nebyl vytvořen
            </div>
          `}
        `}
      </div>

        <button class="btn btn-secondary" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem;" data-action="closeDetail">Zavřít</button>
      </div>
    `;
  } else {
    buttonsHtml = `
      <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem; background: #1a1a1a; color: white;" data-action="startVisit" data-id="${record.id}">Zahájit návštěvu</button>

        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem; background: #1a1a1a; color: white;" data-action="showCalendar" data-id="${record.id}">Naplánovat termín</button>

        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem;" data-action="showContactMenu" data-id="${record.id}">Kontaktovat</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem;" data-action="showCustomerDetail" data-id="${record.id}">Detail zákazníka</button>

        ${record.original_reklamace_id ? `
          <!-- Nedokončená zakázka s historií - přidat Historie PDF -->
          <button class="btn" style="background: #555; color: white; width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem;"
                  data-action="showHistoryPDF" data-original-id="${record.original_reklamace_id}">
            📚 Historie PDF
          </button>
        ` : ''}

        <button class="btn btn-secondary" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 44px; font-size: 0.9rem;" data-action="closeDetail">Zavřít</button>
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
    alert(t('record_not_found'));
    return;
  }

  const customerName = Utils.getCustomerName(record);
  const product = Utils.getProduct(record);
  
  const confirmed = window.confirm(
    t('confirm_reopen_order')
      .replace('{customer}', customerName)
      .replace('{product}', product)
  );
  
  if (!confirmed) {
    logger.log('Znovuotevření zrušeno uživatelem');
    return;
  }
  
  try {
    // Get CSRF token
    const csrfToken = await getCSRFToken();

    const formData = new FormData();
    formData.append('action', 'reopen');
    formData.append('original_id', id);
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
      const newId = result.new_id;
      const newWorkflowId = result.new_workflow_id;

      logger.log(`Nová zakázka vytvořena: ${newWorkflowId} (ID: ${newId})`);

      alert(
        `NOVÁ ZAKÁZKA VYTVOŘENA\n\n` +
        `Číslo: ${newWorkflowId}\n` +
        `Stav: NOVÁ (žlutá karta)\n\n` +
        `Původní zakázka zůstává dokončená.\n\n` +
        `Nyní můžete:\n` +
        `→ Naplánovat termín návštěvy\n` +
        `→ Zahájit návštěvu\n` +
        `→ Aktualizovat detail zakázky`
      );

      // Reload seznamu
      if (typeof loadAll === 'function') {
        await loadAll(ACTIVE_FILTER);
      }

      // Zobrazit detail NOVÉ zakázky (ne původní)
      showDetail(newId);

    } else {
      throw new Error(result.message || 'Nepodařilo se vytvořit novou zakázku');
    }
  } catch (e) {
    logger.error('Chyba při znovuotevření zakázky:', e);
    alert(t('error_reopening_order') + ': ' + e.message);
  }
}

// Export pro inline event handler v seznam.php
window.reopenOrder = reopenOrder;

// === ZOBRAZENÍ HISTORIE PDF Z PŮVODNÍ ZAKÁZKY ===
async function showHistoryPDF(originalReklamaceId) {
  if (!originalReklamaceId) {
    alert('Chybí ID původní zakázky');
    return;
  }

  try {
    logger.log(`📚 Načítám historii PDF z původní zakázky: ${originalReklamaceId}`);

    // Načíst dokumenty z původní zakázky
    const response = await fetch(`/api/get_original_documents.php?reklamace_id=${encodeURIComponent(originalReklamaceId)}`);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const result = await response.json();

    if (result.status === 'error') {
      throw new Error(result.message || 'Nepodařilo se načíst dokumenty');
    }

    if (!result.documents || result.documents.length === 0) {
      alert('Historie PDF není k dispozici.\n\nPůvodní zakázka ještě nemá vytvořený PDF dokument.');
      return;
    }

    // Zobrazit první PDF dokument
    const firstDoc = result.documents[0];
    logger.log(`Otevírám PDF: ${firstDoc.file_path}`);

    // Otevřít PDF v novém okně
    window.open(firstDoc.file_path, '_blank');

  } catch (error) {
    logger.error('Chyba při načítání historie PDF:', error);
    alert(`Nepodařilo se načíst historii PDF:\n${error.message}`);
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
// Ochrana proti duplicitnímu volání
let startVisitInProgress = false;

function startVisit(id) {
  console.log('[startVisit] 🔍 Zahajuji návštěvu, ID:', id);

  // Ochrana proti duplicitnímu volání
  if (startVisitInProgress) {
    console.log('[startVisit] Funkce již běží, ignoruji duplicitní volání');
    return;
  }
  startVisitInProgress = true;

  const z = WGS_DATA_CACHE.find(x => x.id == id);
  console.log('[startVisit] [List] Nalezený záznam:', z);

  if (!z) {
    console.error('[startVisit] Záznam nenalezen v cache!');
    alert(t('record_not_found'));
    startVisitInProgress = false;
    return;
  }

  console.log('[startVisit] Záznam nalezen, stav:', z.stav);

  if (Utils.isCompleted(z)) {
    console.error('[startVisit] Návštěva již dokončena!');
    alert(t('visit_already_completed'));
    startVisitInProgress = false;
    return;
  }

  console.log('[startVisit] [Edit] Normalizuji data...');
  const normalizedData = normalizeCustomerData(z);
  console.log('[startVisit] Data normalizována:', normalizedData);

  console.log('[startVisit] [Save] Ukládám do localStorage...');
  localStorage.setItem('currentCustomer', JSON.stringify(normalizedData));
  localStorage.setItem('visitStartTime', new Date().toISOString());

  const photoKey = 'photoSections_' + normalizedData.id;
  localStorage.removeItem(photoKey);

  logger.log('Normalizovaná data uložena:', normalizedData);

  console.log('[startVisit] [Start] Přesměrovávám na photocustomer.php...');
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
      alert(t('error') + ': ' + (result.message || t('failed_to_save')));
    }
  } catch (e) {
    logger.error('Chyba při ukládání:', e);
    alert(t('save_error') + ': ' + e.message);
  }
}

// === LOADING OVERLAY HELPERS ===
function showLoading(message = null) {
  if (!message) message = t('loading');
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
    alert(t('cannot_show_past_months'));
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
  weekdays.innerHTML = `<div>${t('monday')}</div><div>${t('tuesday')}</div><div>${t('wednesday')}</div><div>${t('thursday')}</div><div>${t('friday')}</div><div>${t('saturday')}</div><div>${t('sunday')}</div>`;
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
        alert(t('cannot_select_past_date'));
        return;
      }

      SELECTED_DATE = `${d}.${m + 1}.${y}`;
      document.querySelectorAll('.cal-day').forEach(x => x.classList.remove('selected'));
      el.classList.add('selected');

      // PERFORMANCE: Vzdálenosti vypnuty kvůli API problémům
      let displayText = `Vybraný den: ${SELECTED_DATE}`;
      document.getElementById('selectedDateDisplay').textContent = displayText;

      // Zobrazit časy okamžitě
      renderTimeGrid();

      // PERFORMANCE: Vypnuto kvůli problémům s get_distance.php
      // showDayBookingsWithDistances(SELECTED_DATE);
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
  // PERFORMANCE: Funkce vypnuta kvůli problémům s get_distance.php API
  // Vzdálenosti se nezobrazují
  const distanceContainer = document.getElementById('distanceInfo');
  const bookingsContainer = document.getElementById('dayBookings');

  if (distanceContainer) distanceContainer.innerHTML = '';
  if (bookingsContainer) bookingsContainer.innerHTML = '';
  return;

  /* VYPNUTO - DISTANCE API NEFUNGUJE
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
  */
  
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
        <div class="booking-item" onclick='showBookingDetail("${b.id}")'>
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
      alert(t('record_not_found'));
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
      el.onclick = () => {
        SELECTED_TIME = time;
        document.querySelectorAll('.time-slot').forEach(x => x.classList.remove('selected'));
        el.classList.add('selected');

        // PERFORMANCE: Zobrazit termín bez vzdálenosti
        let displayText = `Vybraný termín: ${SELECTED_DATE} — ${SELECTED_TIME}`;

        if (occupiedTimes[time]) {
          displayText += ` KOLIZE: ${occupiedTimes[time].zakaznik}`;
        }

        document.getElementById('selectedDateDisplay').textContent = displayText;

        // PERFORMANCE: getDistance() a showDayBookingsWithDistances() vypnuty
      };
      t.appendChild(el);
    }
  }
}

async function saveSelectedDate() {
  if (!SELECTED_DATE || !SELECTED_TIME) {
    alert(t('select_date_and_time'));
    return;
  }

  if (!CURRENT_RECORD) {
    alert(t('no_record_to_save'));
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
      t('confirm_appointment_collision')
        .replace('{date}', SELECTED_DATE)
        .replace('{time}', SELECTED_TIME)
        .replace('{customer}', collisionName)
    );
    if (!confirm) return;
  }

  // ZOBRAZIT LOADING OVERLAY
  showLoading(t('saving_appointment'));

  // ⏱️ PERFORMANCE: Debug timing
  const t0 = performance.now();
  logger.log('⏱️ START ukládání termínu...');

  try {
    // Get CSRF token
    const t1 = performance.now();
    const csrfToken = await getCSRFToken();
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
      alert(t('appointment_saved_success').replace('{date}', SELECTED_DATE).replace('{time}', SELECTED_TIME));
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
      alert(t('error') + ': ' + (result.message || t('failed_to_save')));
    }
  } catch (e) {
    hideLoading();
    logger.error('Chyba při ukládání:', e);
    alert(t('save_error') + ': ' + e.message);

    // ⏱️ Log času i při chybě
    const tError = performance.now();
    logger.log(`⏱️ Čas do chyby: ${(tError - t0).toFixed(0)}ms`);
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
          ${phone ? `<a href="tel:${phone}" class="btn" style="padding: 0.5rem 0.75rem; font-size: 0.9rem; text-decoration: none; display: block; text-align: center; background: #1a1a1a; color: white;">Zavolat</a>` : ''}
          <button class="btn" style="padding: 0.5rem 0.75rem; font-size: 0.9rem; background: #1a1a1a; color: white;" onclick="closeDetail(); setTimeout(() => showCalendar('${id}'), 100)">Termín návštěvy</button>
          ${phone ? `<button class="btn" style="padding: 0.5rem 0.75rem; font-size: 0.9rem; background: #444; color: white;" onclick="sendContactAttemptEmail('${id}', '${phone}')">Odeslat SMS</button>` : ''}
          ${address && address !== '—' ? `<a href="https://waze.com/ul?q=${encodeURIComponent(address)}&navigate=yes" class="btn" style="padding: 0.5rem 0.75rem; font-size: 0.9rem; text-decoration: none; display: block; text-align: center; background: #444; color: white;" target="_blank">Navigovat (Waze)</a>` : ''}
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
    // Vzdálenost se nezobrazuje, zobrazí se jen '—'
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
        <div style="display: grid; grid-template-columns: auto 1fr; gap: 0.5rem; font-size: 0.9rem;">
          <span style="color: #666; font-weight: 600;">Číslo objednávky:</span>
          <input type="text" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: white;" value="${Utils.escapeHtml(reklamaceId)}" readonly>

          <span style="color: #666; font-weight: 600;">Zadavatel:</span>
          <input type="text" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #f8f9fa;" value="${Utils.escapeHtml(zadavatel)}" readonly placeholder="Prodejce/Uživatel">

          <span style="color: #666; font-weight: 600;">Číslo reklamace:</span>
          <input type="text" id="edit_cislo" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${Utils.escapeHtml(cislo)}">

          <span style="color: #666; font-weight: 600;">Fakturace:</span>
          <span style="color: #1a1a1a; font-weight: 600; padding: 0.25rem 0;">${fakturace_firma.toUpperCase() === 'SK' ? 'Slovensko (SK)' : 'Česká republika (CZ)'}</span>

          <span style="color: #666; font-weight: 600;">Datum prodeje:</span>
          <input type="text" id="edit_datum_prodeje" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${Utils.escapeHtml(datum_prodeje)}" placeholder="DD.MM.RRRR">

          <span style="color: #666; font-weight: 600;">Datum reklamace:</span>
          <input type="text" id="edit_datum_reklamace" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${Utils.escapeHtml(datum_reklamace)}" placeholder="DD.MM.RRRR">

          <span style="color: #666; font-weight: 600;">Jméno:</span>
          <input type="text" id="edit_jmeno" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${customerName}">

          <span style="color: #666; font-weight: 600;">Telefon:</span>
          <input type="tel" id="edit_telefon" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${phone}">

          <span style="color: #666; font-weight: 600;">Email:</span>
          <input type="email" id="edit_email" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${email}">

          <span style="color: #666; font-weight: 600;">Adresa:</span>
          <input type="text" id="edit_adresa" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${address}">

          <span style="color: #666; font-weight: 600;">Model:</span>
          <input type="text" id="edit_model" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${product}">

          <span style="color: #666; font-weight: 600;">Provedení:</span>
          <input type="text" id="edit_provedeni" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${Utils.escapeHtml(provedeni)}" placeholder="Látka / Kůže">

          <span style="color: #666; font-weight: 600;">Barva:</span>
          <input type="text" id="edit_barva" style="border: 1px solid #ddd; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem;" value="${Utils.escapeHtml(barva)}">
        </div>
      </div>

      <!-- DOPLŇUJÍCÍ INFORMACE OD PRODEJCE -->
      <div style="margin-bottom: 1rem;">
        <label style="display: block; color: #666; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.25rem;">Doplňující informace od prodejce:</label>
        <div onclick="showTextOverlay('doplnujici_info')"
             style="width: 100%; border: 1px solid #ddd; padding: 0.5rem; border-radius: 3px; font-size: 0.9rem; min-height: 50px; background: white; cursor: pointer; white-space: pre-wrap; color: ${doplnujici_info ? '#1a1a1a' : '#999'};">
          ${doplnujici_info || 'Klikněte pro zobrazení/zadání doplňujících informací od prodejce'}
        </div>
      </div>

      <!-- POPIS PROBLÉMU OD ZÁKAZNÍKA -->
      <div style="margin-bottom: 2rem;">
        <label style="display: block; color: #666; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.25rem;">Popis problému od zákazníka:</label>
        <div onclick="showTextOverlay('popis_problemu')"
             style="width: 100%; border: 1px solid #ddd; padding: 0.5rem; border-radius: 3px; font-size: 0.9rem; min-height: 60px; background: white; cursor: pointer; white-space: pre-wrap; color: ${description ? '#1a1a1a' : '#999'};">
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
                       data-action="showPhotoFullscreen"
                       data-url="${escapedUrl}">
                  ${photoId ? `
                    <button class="foto-delete-btn"
                            data-action="smazatFotku"
                            data-photo-id="${photoId}"
                            data-url="${escapedUrl}"
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
            <button data-action="openPDF"
                    data-url="${pdfDoc.file_path.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}"
                    style="width: 100%; padding: 0.75rem; background: #333333; color: white; border: none; border-radius: 4px; font-size: 0.9rem; cursor: pointer; font-weight: 600;">
              Otevřít PDF Report
            </button>
          </div>
        `;
      })()}

      ${CURRENT_USER.is_admin ? `
        <div style="border-top: 1px solid #e0e0e0; padding-top: 1rem; margin-top: 1rem;">
          <button data-action="deleteReklamace"
                  data-id="${id}"
                  style="width: 100%; padding: 0.5rem; background: #dc3545; color: white; border: none; border-radius: 3px; font-size: 0.9rem; cursor: pointer; font-weight: 600;">
            Smazat reklamaci
          </button>
          <p style="font-size: 0.7rem; color: #999; margin-top: 0.25rem; text-align: center;">Smaže vše včetně fotek a PDF</p>
        </div>
      ` : ''}

    </div>

    ${ModalManager.createActions([
      '<button class="btn btn-secondary" data-action="showDetail" data-id="' + id + '">Zpět</button>',
      '<button class="btn" style="background: #1a1a1a; color: white;" data-action="saveAllCustomerData" data-id="' + id + '">Uložit změny</button>'
    ])}
  `;

  ModalManager.show(content);
}

/**
 * Zobrazí PDF v modálním okně s tlačítky Zavřít a Odeslat
 */
function zobrazPDFModal(pdfUrl, claimId) {
  // Vytvořit overlay
  const overlay = document.createElement('div');
  overlay.id = 'pdfModalOverlay';
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center;';

  // Kontejner pro PDF
  const pdfContainer = document.createElement('div');
  pdfContainer.style.cssText = 'width: 95%; height: calc(100% - 80px); max-width: 900px; background: white; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column;';

  // Header s názvem
  const header = document.createElement('div');
  header.style.cssText = 'padding: 12px 16px; background: #333; color: white; font-weight: 600; font-size: 0.95rem; display: flex; justify-content: space-between; align-items: center;';
  header.innerHTML = '<span>PDF Report</span><span style="font-size: 0.8rem; opacity: 0.7;">ID: ' + (claimId || '-') + '</span>';

  // Iframe pro PDF
  const iframe = document.createElement('iframe');
  iframe.src = pdfUrl;
  iframe.style.cssText = 'flex: 1; width: 100%; border: none;';

  pdfContainer.appendChild(header);
  pdfContainer.appendChild(iframe);

  // Tlačítka
  const buttonContainer = document.createElement('div');
  buttonContainer.style.cssText = 'display: flex; gap: 12px; margin-top: 16px; padding: 0 16px;';

  // Tlačítko Zavřít
  const btnZavrit = document.createElement('button');
  btnZavrit.textContent = 'Zavrit';
  btnZavrit.style.cssText = 'padding: 12px 32px; font-size: 1rem; font-weight: 600; background: #666; color: white; border: none; border-radius: 6px; cursor: pointer; min-width: 120px;';
  btnZavrit.onclick = () => overlay.remove();

  // Tlačítko Odeslat
  const btnOdeslat = document.createElement('button');
  btnOdeslat.textContent = 'Odeslat';
  btnOdeslat.style.cssText = 'padding: 12px 32px; font-size: 1rem; font-weight: 600; background: #333; color: white; border: none; border-radius: 6px; cursor: pointer; min-width: 120px;';
  btnOdeslat.onclick = async () => {
    if (!claimId) {
      alert('Chyba: Chybí ID zakázky');
      return;
    }

    // Najít reklamaci v datech
    const reklamace = window.REKLAMACE_DATA?.find(r => r.id == claimId);
    if (!reklamace || !reklamace.email) {
      alert('Chyba: Nenalezen email zákazníka');
      return;
    }

    if (!confirm('Odeslat PDF na email: ' + reklamace.email + '?')) {
      return;
    }

    btnOdeslat.disabled = true;
    btnOdeslat.textContent = 'Odesílám...';

    try {
      // Načíst PDF jako base64
      const response = await fetch(pdfUrl);
      const blob = await response.blob();
      const reader = new FileReader();

      reader.onload = async function() {
        const base64 = reader.result.split(',')[1];

        // Odeslat email
        const formData = new FormData();
        formData.append('action', 'send_email');
        formData.append('reklamace_id', reklamace.reklamace_id || claimId);
        formData.append('customer_email', reklamace.email);
        formData.append('customer_name', reklamace.jmeno || 'Zákazník');
        formData.append('complete_pdf', base64);

        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
        if (csrfToken) {
          formData.append('csrf_token', csrfToken);
        }

        const emailResponse = await fetch('/api/protokol_api.php', {
          method: 'POST',
          body: formData
        });

        const result = await emailResponse.json();

        if (result.status === 'success') {
          alert('Email byl úspěšně odeslán!');
          overlay.remove();
        } else {
          throw new Error(result.message || 'Neznámá chyba');
        }
      };

      reader.onerror = () => {
        throw new Error('Nepodařilo se načíst PDF');
      };

      reader.readAsDataURL(blob);

    } catch (error) {
      console.error('Chyba při odesílání:', error);
      alert('Chyba při odesílání: ' + error.message);
      btnOdeslat.disabled = false;
      btnOdeslat.textContent = 'Odeslat';
    }
  };

  buttonContainer.appendChild(btnZavrit);
  buttonContainer.appendChild(btnOdeslat);

  overlay.appendChild(pdfContainer);
  overlay.appendChild(buttonContainer);

  // Zavřít při kliknutí mimo
  overlay.onclick = (e) => {
    if (e.target === overlay) overlay.remove();
  };

  // Zavřít při ESC
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      overlay.remove();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  document.body.appendChild(overlay);
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
  saveBtn.style.cssText = 'flex: 1; padding: 0.5rem 1rem; background: #1a1a1a; color: white; border: none; border-radius: 4px; font-size: 0.9rem; cursor: pointer; font-weight: 600;';
  saveBtn.textContent = t('save_changes');
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
        alert(t('text_saved_successfully'));
      } else {
        alert(t('save_error') + ': ' + result.message);
      }
    } catch (error) {
      alert(t('save_error') + ': ' + error.message);
    }
  };

  const cancelBtn = document.createElement('button');
  cancelBtn.style.cssText = 'flex: 1; padding: 0.5rem 1rem; background: #666; color: white; border: none; border-radius: 4px; font-size: 0.9rem; cursor: pointer;';
  cancelBtn.textContent = t('cancel');
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
  const address = Utils.getAddress(customer) || '';
  const product = customer.nazev_produktu || customer.produkt || customer.popis_produktu || '';

  // Data technika
  const technikJmeno = customer.technik_jmeno || customer.assigned_technician || '';
  const technikEmail = customer.technik_email || '';
  const technikTelefon = customer.technik_telefon || '';

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
          customer_phone: phone,
          seller_email: "admin@wgs-service.cz",
          date: date,
          time: time,
          order_id: orderId,
          address: address,
          product: product,
          technician_name: technikJmeno,
          technician_email: technikEmail,
          technician_phone: technikTelefon
        }
      })
    });

    const result = await response.json();

    if (result.success === true) {
      logger.log('Potvrzení termínu odesláno zákazníkovi');
      if (result.sent) {
        logger.log('  Email odeslán na:', result.to || email);
      }
      if (result.sms_sent) {
        logger.log('  SMS odeslána na:', phone);
      }
    } else {
      logger.error('⚠ Chyba při odesílání potvrzení:', result.error || result.message);
    }
  } catch (error) {
    logger.error('Chyba při odesílání potvrzení:', error);
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

async function showNotes(recordOrId) {
  let record;
  if (typeof recordOrId === 'string' || typeof recordOrId === 'number') {
    record = WGS_DATA_CACHE.find(x => x.id == recordOrId || x.reklamace_id == recordOrId);
    if (!record) {
      alert(t('record_not_found'));
      return;
    }
  } else {
    record = recordOrId;
  }

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
    </div>

    ${ModalManager.createActions([
      '<button class="btn btn-secondary" data-action="closeNotesModal">Zavřít</button>',
      '<button class="btn btn-success" data-action="saveNewNote" data-id="' + record.id + '">Přidat poznámku</button>'
    ])}
  `;

  ModalManager.show(content);

  setTimeout(async () => {
    await markNotesAsRead(record.id);
    await loadAll(ACTIVE_FILTER);
    // Aktualizovat badge na ikone PWA
    if (window.WGSNotifikace) {
      window.WGSNotifikace.aktualizovat();
    }
  }, 1000);
}

async function saveNewNote(orderId) {
  const textarea = document.getElementById('newNoteText');
  const text = textarea.value.trim();

  if (!text) {
    alert(t('write_note_text'));
    return;
  }

  try {
    await addNote(orderId, text);

    const record = CURRENT_RECORD;
    await showNotes(record);

    await loadAll(ACTIVE_FILTER);

    // Aktualizovat badge na ikone PWA (nova poznamka)
    if (window.WGSNotifikace) {
      window.WGSNotifikace.aktualizovat();
    }
  } catch (e) {
    alert(t('note_save_error') + ': ' + e.message);
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

// DUPLICITNÍ EVENT DELEGATION ODSTRANĚN
// Používá se hlavní event delegation na řádku 2587
// Ponecháme pouze data-navigate a data-onchange handlers

document.addEventListener('DOMContentLoaded', () => {
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

// === POMOCNÉ FUNKCE PRO DELETE MODALY ===
function showDeleteConfirmModal(reklamaceNumber) {
  return new Promise((resolve) => {
    const modalDiv = document.createElement('div');
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999999;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:white;padding:30px;border-radius:8px;max-width:450px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);';

    modalContent.innerHTML = `
      <h2 style="margin:0 0 20px 0;color:#dc3545;font-size:1.3rem;font-weight:700;">Smazat reklamaci?</h2>
      <p style="margin:0 0 15px 0;color:#555;line-height:1.6;font-size:1rem;">
        Opravdu chcete <strong>TRVALE SMAZAT</strong> reklamaci<br>
        <strong style="color:#dc3545;font-size:1.1rem;">${reklamaceNumber}</strong>?
      </p>
      <p style="margin:0 0 25px 0;color:#dc3545;font-size:0.9rem;font-weight:600;">
        Tato akce smaže VŠE včetně fotek a PDF!<br>
        Tuto akci NELZE vrátit zpět!
      </p>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <button id="deleteConfirmYes" style="padding:14px 28px;background:#dc3545;color:white;border:none;border-radius:6px;cursor:pointer;font-size:1rem;font-weight:700;">
          Ano, pokračovat →
        </button>
        <button id="deleteConfirmNo" style="padding:14px 28px;background:#999;color:white;border:none;border-radius:6px;cursor:pointer;font-size:1rem;font-weight:600;">
          Zrušit
        </button>
      </div>
    `;

    modalDiv.appendChild(modalContent);
    document.body.appendChild(modalDiv);

    document.getElementById('deleteConfirmNo').onclick = () => {
      document.body.removeChild(modalDiv);
      resolve(false);
    };

    document.getElementById('deleteConfirmYes').onclick = () => {
      document.body.removeChild(modalDiv);
      resolve(true);
    };
  });
}

function showDeleteInputModal(reklamaceNumber) {
  return new Promise((resolve) => {
    const modalDiv = document.createElement('div');
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999999;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:white;padding:30px;border-radius:8px;max-width:450px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);';

    modalContent.innerHTML = `
      <h2 style="margin:0 0 20px 0;color:#dc3545;font-size:1.3rem;font-weight:700;">Poslední ověření</h2>
      <p style="margin:0 0 15px 0;color:#555;line-height:1.6;font-size:1rem;">
        Pro potvrzení smazání zadejte přesně číslo reklamace:
      </p>
      <p style="margin:0 0 15px 0;color:#dc3545;font-size:1.2rem;font-weight:700;">
        ${reklamaceNumber}
      </p>
      <input type="text" id="deleteInputField"
             placeholder="Zadejte číslo reklamace"
             style="width:100%;padding:12px;border:2px solid #dc3545;border-radius:6px;font-size:1rem;text-align:center;margin-bottom:20px;">
      <div style="display:flex;flex-direction:column;gap:12px;">
        <button id="deleteInputConfirm" style="padding:14px 28px;background:#dc3545;color:white;border:none;border-radius:6px;cursor:pointer;font-size:1rem;font-weight:700;">
          SMAZAT NAVŽDY
        </button>
        <button id="deleteInputCancel" style="padding:14px 28px;background:#999;color:white;border:none;border-radius:6px;cursor:pointer;font-size:1rem;font-weight:600;">
          Zrušit
        </button>
      </div>
    `;

    modalDiv.appendChild(modalContent);
    document.body.appendChild(modalDiv);

    const inputField = document.getElementById('deleteInputField');
    inputField.focus();

    document.getElementById('deleteInputCancel').onclick = () => {
      document.body.removeChild(modalDiv);
      resolve('');
    };

    document.getElementById('deleteInputConfirm').onclick = () => {
      const value = inputField.value.trim();
      document.body.removeChild(modalDiv);
      resolve(value);
    };

    // Enter key pro potvrzení
    inputField.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        const value = inputField.value.trim();
        document.body.removeChild(modalDiv);
        resolve(value);
      }
    });
  });
}

// === SMAZÁNÍ REKLAMACE (ADMIN ONLY) ===
async function deleteReklamace(reklamaceId) {
  logger.log('[deleteReklamace] Zobrazuji 1. confirmation modal');

  const reklamaceNumber = CURRENT_RECORD.reklamace_id || CURRENT_RECORD.id || reklamaceId;

  // 1. KROK: První potvrzení
  const firstConfirm = await showDeleteConfirmModal(reklamaceNumber);
  if (!firstConfirm) {
    logger.log('Mazání zrušeno (1. krok)');
    return;
  }

  // 2. KROK: Zadání čísla reklamace
  const userInput = await showDeleteInputModal(reklamaceNumber);
  if (userInput !== reklamaceNumber) {
    logger.log('Mazání zrušeno - špatné číslo (2. krok)');

    // Zobrazit chybovou hlášku
    const errorModal = document.createElement('div');
    errorModal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999999;display:flex;align-items:center;justify-content:center;';
    errorModal.innerHTML = `
      <div style="background:white;padding:30px;border-radius:8px;max-width:400px;width:90%;text-align:center;">
        <h2 style="margin:0 0 20px 0;color:#dc3545;">Nesprávné číslo!</h2>
        <p style="margin:0 0 25px 0;color:#555;">Zadali jste nesprávné číslo reklamace.<br>Mazání bylo zrušeno.</p>
        <button onclick="this.closest('div').parentElement.remove()" style="padding:12px 24px;background:#999;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
          OK
        </button>
      </div>
    `;
    document.body.appendChild(errorModal);
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
      logger.log('Smazáno!');
      alert(t('claim_deleted_successfully'));
      closeDetail();
      setTimeout(() => location.reload(), 500);
    } else {
      const errorMsg = result.message || result.error || t('delete_failed');
      logger.error('Chyba:', errorMsg);
      alert(t('error') + ': ' + errorMsg);
    }
  } catch (error) {
    logger.error('Chyba při mazání:', error);
    alert(t('delete_error') + ': ' + error.message);
  }
}

// === SMAZÁNÍ JEDNOTLIVÉ FOTKY ===
async function smazatFotku(photoId, photoUrl) {
  logger.log('[smazatFotku] Vytvářím confirmation modal pro ID:', photoId);

  // Vlastní confirmation modal (viditelný nad vším)
  return new Promise((resolve) => {
    const modalDiv = document.createElement('div');
    modalDiv.id = 'deleteFotoModal';
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999999;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:white;padding:30px;border-radius:8px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);';

    modalContent.innerHTML = `
      <h2 style="margin:0 0 20px 0;color:#333;font-size:1.2rem;font-weight:700;">Smazat fotku?</h2>
      <p style="margin:0 0 25px 0;color:#555;line-height:1.6;font-size:1rem;">
        Opravdu chcete smazat tuto fotografii?<br><br>
        <strong>Tato akce je nevratná!</strong>
      </p>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <button id="deleteFotoYes" style="padding:14px 28px;background:#dc3545;color:white;border:none;border-radius:6px;cursor:pointer;font-size:1rem;font-weight:700;">
          Ano, smazat
        </button>
        <button id="deleteFotoNo" style="padding:14px 28px;background:#999;color:white;border:none;border-radius:6px;cursor:pointer;font-size:1rem;font-weight:600;">
          Zrušit
        </button>
      </div>
    `;

    modalDiv.appendChild(modalContent);
    document.body.appendChild(modalDiv);

    document.getElementById('deleteFotoNo').onclick = () => {
      logger.log('[smazatFotku] Uživatel zrušil');
      document.body.removeChild(modalDiv);
      resolve(false);
    };

    document.getElementById('deleteFotoYes').onclick = async () => {
      logger.log('[smazatFotku] Uživatel potvrdil, mazám...');
      document.body.removeChild(modalDiv);
      await pokracovatSmazaniFotky(photoId, photoUrl);
      resolve(true);
    };
  });
}

async function pokracovatSmazaniFotky(photoId, photoUrl) {
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
      logger.log('Fotka smazána!');

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
            grid.innerHTML = `<p style="color: var(--c-grey); text-align: center; padding: 1rem; font-size: 0.9rem;">${t('no_photos')}</p>`;
          }
        }
      }

      alert(t('photo_deleted_successfully'));
    } else {
      const errorMsg = result.message || result.error || t('delete_failed');
      logger.error('Chyba:', errorMsg);
      alert(t('error') + ': ' + errorMsg);
    }
  } catch (error) {
    logger.error('Chyba při mazání fotky:', error);
    alert(t('photo_delete_error') + ': ' + error.message);
  }
}

// Načti fotky z databáze
async function loadPhotosFromDB(reklamaceId) {
  try {
    const response = await fetch(`api/get_photos_api.php?reklamace_id=${reklamaceId}`);
    if (!response.ok) return [];

    const data = await response.json();
    if (data.success && data.photos) {
      // Vrátit celé objekty včetně ID pro možnost mazání
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

// PAGINATION: Load more handler
async function loadMoreOrders() {
  if (LOADING_MORE || !HAS_MORE_PAGES) return;

  LOADING_MORE = true;
  const btn = document.getElementById('loadMoreBtn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = t('loading');
  }

  await loadAll(ACTIVE_FILTER, true); // append = true
}

// PAGINATION: Update "Load More" button visibility
function updateLoadMoreButton() {
  let btn = document.getElementById('loadMoreBtn');

  // Create button if doesn't exist
  if (!btn) {
    const grid = document.getElementById('orderGrid');
    if (grid && grid.parentElement) {
      btn = document.createElement('button');
      btn.id = 'loadMoreBtn';
      btn.className = 'load-more-btn';
      btn.textContent = t('load_more_orders');
      btn.onclick = loadMoreOrders;

      grid.parentElement.appendChild(btn);
    }
  }

  // Show/hide based on HAS_MORE_PAGES
  if (btn) {
    btn.style.display = HAS_MORE_PAGES ? 'block' : 'none';
    btn.disabled = LOADING_MORE;
    btn.textContent = LOADING_MORE ? t('loading') : t('load_more_page').replace('{page}', CURRENT_PAGE + 1);
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

    // Zobrazit loading overlay
    showLoading('Odesílám email zákazníkovi... Prosím čekejte');

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

    // Skrýt loading overlay
    hideLoading();

    if (data.success) {
      logger.log('Email o pokusu o kontakt odeslán zákazníkovi');

      // Zavřít detail modal
      closeDetail();

      // Zobrazit toast zprávu
      showToast('Email odeslán zákazníkovi', 'success');

      // DŮLEŽITÉ: SMS text je nyní generován na serveru ze stejných dat jako email
      // To znamená, že změna v emailové šabloně automaticky ovlivní i SMS
      const smsText = data.sms_text || `Dobrý den, pokusili jsme se Vás kontaktovat. Zavolejte prosím zpět na +420 725 965 826. Děkujeme, WGS Service`;

      // Počkat 2 sekundy, aby technik viděl potvrzení, pak otevřít SMS aplikaci
      setTimeout(() => {
        const encodedText = encodeURIComponent(smsText);
        window.location.href = `sms:${telefon}?body=${encodedText}`;
      }, 2000);

    } else {
      logger.error('⚠ Chyba při odesílání emailu:', data.error || data.message);
      showToast(data.error || 'Chyba při odesílání emailu', 'error');
    }

  } catch (chyba) {
    logger.error('Chyba při odesílání kontaktního emailu:', chyba);
    showToast('Nepodařilo se odeslat email', 'error');
    // Skrýt loading overlay i při chybě
    hideLoading();
  }
}

// ========================================
// EVENT DELEGATION PRO TLAČÍTKA V DETAILU
// ========================================
// Zachytává kliknutí na tlačítka s data-action atributem
// Řeší problém s inline onclick, které CSP blokuje
document.addEventListener('click', (e) => {
  const button = e.target.closest('[data-action]');
  if (!button) return;

  const action = button.getAttribute('data-action');
  const id = button.getAttribute('data-id');
  const url = button.getAttribute('data-url');

  logger.log(`[Seznam] Tlačítko kliknuto: ${action}`, { id, url });

  switch (action) {
    case 'reopenOrder':
      if (id) reopenOrder(id);
      break;

    case 'showContactMenu':
      if (id) showContactMenu(id);
      break;

    case 'showCustomerDetail':
      if (id) showCustomerDetail(id);
      break;

    case 'showDetail':
      if (CURRENT_RECORD) showDetail(CURRENT_RECORD);
      break;

    case 'openPDF':
      if (url) zobrazPDFModal(url, id);
      break;

    case 'showHistoryPDF':
      const originalId = button.getAttribute('data-original-id');
      if (originalId) showHistoryPDF(originalId);
      break;

    case 'showPhotoFullscreen':
      if (url) showPhotoFullscreen(url);
      break;

    case 'smazatFotku':
      const photoId = button.getAttribute('data-photo-id');
      if (photoId && url) {
        e.stopPropagation();
        smazatFotku(photoId, url);
      }
      break;

    case 'deleteReklamace':
      if (id) deleteReklamace(id);
      break;

    case 'saveAllCustomerData':
      if (id) saveAllCustomerData(id);
      break;

    case 'closeDetail':
      closeDetail();
      break;

    case 'showNotes':
      if (id && typeof showNotes === 'function') {
        e.stopPropagation();
        showNotes(id);
      }
      break;

    case 'filterUnreadNotes':
      if (typeof filterUnreadNotes === 'function') {
        filterUnreadNotes();
      }
      break;

    case 'closeNotesModal':
      if (typeof closeNotesModal === 'function') {
        closeNotesModal();
      }
      break;

    case 'saveNewNote':
      if (id && typeof saveNewNote === 'function') {
        saveNewNote(id);
      }
      break;

    default:
      logger.warn(`[Seznam] Neznámá akce: ${action}`);
  }
});