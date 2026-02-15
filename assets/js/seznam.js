// VERSION: 20251210-01 - Skrytí technických tlačítek pro prodejce

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
    background: ${type === 'success' ? '#333' : type === 'error' ? '#666' : '#333'};
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
let ACTIVE_FILTERS = new Set(); // Může být více filtrů najednou (toggle)
let CURRENT_RECORD = null;
let SELECTED_DATE = null;
let SELECTED_TIME = null;
let EMAILS_S_CN = []; // Emaily zákazníků s cenovou nabídkou
let STAVY_NABIDEK = {}; // Mapa email -> stav nabídky ('potvrzena' nebo 'odeslana')

// PAGINATION FIX: Tracking pagination state
let CURRENT_PAGE = 1;
let HAS_MORE_PAGES = false;
let LOADING_MORE = false;
const PER_PAGE = 9999; // Načíst všechny karty najednou
let CAL_MONTH = new Date().getMonth();
let CAL_YEAR = new Date().getFullYear();
let SEARCH_QUERY = '';

const WGS_ADDRESS = "Dubče 364, Běchovice 190 11, Česká republika";
const WGS_COORDS = { lat: 50.08028448017454, lng: 14.598156697482635 };

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

    const lowerAddr = address.toLowerCase();

    // Pokud už obsahuje název země, nepřidávat
    if (lowerAddr.includes('česk') || lowerAddr.includes('slovak') ||
        lowerAddr.includes('czech') || lowerAddr.includes('republic')) {
      return address;
    }

    // Detekce slovenské adresy podle:
    // 1. Slovenské PSČ (formát: XXX XX, např. "010 04")
    // 2. Slovenská města
    const slovenskaMesta = ['žilina', 'bratislava', 'košice', 'prešov', 'nitra', 'banská bystrica', 'trnava', 'martin', 'trenčín', 'poprad'];
    const jeSlovenskoMesto = slovenskaMesta.some(mesto => lowerAddr.includes(mesto));
    const jeSlovenskePSC = /\d{3}\s?\d{2}/.test(address) && (jeSlovenskoMesto || lowerAddr.includes('sk-'));

    if (jeSlovenskoMesto || jeSlovenskePSC) {
      return address + ', Slovenská republika';
    }

    // Výchozí: Česká republika
    return address + ', Česká republika';
  },
  
  filterByUserRole: (items) => {
    // Admin vidí vše
    if (!CURRENT_USER || CURRENT_USER.is_admin || CURRENT_USER.role !== 'prodejce') {
      return items;
    }

    // Prodejce vidí své zakázky + zakázky supervizovaných uživatelů
    const myId = String(CURRENT_USER.id);
    const supervisedIds = (CURRENT_USER.supervised_user_ids || []).map(String);

    return items.filter(x => {
      // OPRAVA: Použít created_by místo zpracoval_id (ten sloupec neexistuje)
      const createdBy = String(x.created_by || '');
      // Moje zakázky
      if (createdBy === myId) return true;
      // Zakázky supervizovaných prodejců
      if (supervisedIds.includes(createdBy)) return true;
      return false;
    });
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

  // POZNÁMKA: Pull-to-refresh je nyní v samostatném souboru pull-to-refresh.js

  // Refresh pri navratu na stranku (tab visibility)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      const timeSinceLastRefresh = Date.now() - lastRefreshTime;
      // Refresh pokud byl tab skryty dele nez 30 sekund
      if (timeSinceLastRefresh > 30000) {
        logger.log('[AutoRefresh] Tab aktivni - nacitam nova data...');
        loadAll();
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
      await loadAll();
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

// === PULL TO REFRESH - PŘESUNUTO DO pull-to-refresh.js ===
// Starý kód byl odstraněn - nyní používáme samostatný soubor s lepším UI

// === VYHLEDÁVÁNÍ ===
function initSearch() {
  const searchInput = document.getElementById('searchInput');
  const searchClear = document.getElementById('searchClear');

  // Načíst vyhledávací dotaz z URL parametru (pro přesměrování z admin zákazníků)
  const urlParams = new URLSearchParams(window.location.search);
  const searchParam = urlParams.get('search');
  if (searchParam) {
    searchInput.value = searchParam;
    SEARCH_QUERY = searchParam.trim().toLowerCase();
    searchClear.classList.add('visible');
    // Vyčistit URL (odstranit ?search= parametr)
    window.history.replaceState({}, document.title, window.location.pathname);
  }

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

// highlightText a escapeRegex přesunuty do utils.js (Step 105)
// Použij Utils.highlightText(text, query) a Utils.escapeRegex(string)
function highlightText(text, query) {
  return Utils.highlightText ? Utils.highlightText(text, query) : Utils.escapeHtml(text);
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
  // Pro techniky nastavit výchozí filtry NOVÁ + DOMLUVENO
  const isTechnik = CURRENT_USER && (CURRENT_USER.role === 'technik' || CURRENT_USER.role === 'technician');
  if (isTechnik) {
    ACTIVE_FILTERS.add('wait');
    ACTIVE_FILTERS.add('open');

    // Aktivovat tlačítka NOVÁ a DOMLUVENO
    document.querySelector('.filter-btn[data-filter="wait"]')?.classList.add('active');
    document.querySelector('.filter-btn[data-filter="open"]')?.classList.add('active');

    // Deaktivovat tlačítko VŠECHNY
    document.querySelector('.filter-btn[data-filter="all"]')?.classList.remove('active');
  }

  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const filterType = btn.dataset.filter;

      // Tlačítko "VŠECHNY" - vypne všechny ostatní filtry
      if (filterType === 'all') {
        ACTIVE_FILTERS.clear();
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      } else {
        // Toggle filtr (zapnout/vypnout)
        if (ACTIVE_FILTERS.has(filterType)) {
          ACTIVE_FILTERS.delete(filterType);
          btn.classList.remove('active');
        } else {
          ACTIVE_FILTERS.add(filterType);
          btn.classList.add('active');
        }

        // Pokud je nějaký filtr aktivní, vypnout "VŠECHNY"
        const allBtn = document.querySelector('.filter-btn[data-filter="all"]');
        if (ACTIVE_FILTERS.size > 0) {
          allBtn?.classList.remove('active');
        } else {
          // Pokud nejsou žádné filtry, aktivovat "VŠECHNY"
          allBtn?.classList.add('active');
        }
      }

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
    const isWait = stav === 'ČEKÁ' || stav === 'wait';
    if (!isWait) return false;
    // Vyloučit zakázky s poslanou CN
    const email = (r.email || '').toLowerCase().trim();
    if (email && EMAILS_S_CN.includes(email)) return false;
    return true;
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
    // Cache-busting pro Safari PWA
    const cacheBuster = Date.now();
    const response = await fetch(`app/controllers/load.php?status=${status}&page=${page}&per_page=${PER_PAGE}&_t=${cacheBuster}`, {
      cache: 'no-store',
      headers: {
        'Cache-Control': 'no-cache'
      }
    });
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

  // Pokud jsou aktivní nějaké filtry, aplikovat je (OR logika - zobrazit pokud splňuje alespoň jeden filtr)
  if (ACTIVE_FILTERS.size > 0) {
    filtered = items.filter(r => {
      const stav = r.stav || 'wait';
      const email = (r.email || '').toLowerCase().trim();
      const createdBy = (r.created_by || '').trim();

      // Kontrolovat každý aktivní filtr
      for (const filterType of ACTIVE_FILTERS) {
        if (filterType === 'wait') {
          // ČEKAJÍCÍ: stav je wait A nemá CN
          const isWait = stav === 'ČEKÁ' || stav === 'wait';
          const hasNoCn = !(email && EMAILS_S_CN.includes(email));
          if (isWait && hasNoCn) return true;
        }

        if (filterType === 'open') {
          // V ŘEŠENÍ: stav je open
          if (stav === 'DOMLUVENÁ' || stav === 'open') return true;
        }

        if (filterType === 'done') {
          // VYŘÍZENÉ: stav je done
          if (stav === 'HOTOVO' || stav === 'done') return true;
        }

        if (filterType === 'cn') {
          // CN: má odeslanou cenovou nabídku A NENÍ hotovo
          const isDone = stav === 'HOTOVO' || stav === 'done';
          if (email && EMAILS_S_CN.includes(email) && !isDone) return true;
        }

        if (filterType === 'poz') {
          // POZ: mimozáruční oprava (created_by je prázdné)
          if (!createdBy) return true;
        }
      }

      // Záznam nesplňuje žádný z aktivních filtrů
      return false;
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
    searchResultsInfo.classList.remove('hidden');
  } else {
    searchResultsInfo.classList.add('hidden');
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
    const response = await fetch('/api/notes_api.php?action=get_unread_counts');
    const data = await response.json();
    if (data.status === 'success') {
      unreadCountsMap = data.unread_counts || {};
    }
  } catch (e) {
    logger.warn('Nepodařilo se načíst unread counts:', e);
  }

  // Načíst emaily zákazníků s cenovou nabídkou (CN) včetně stavů
  try {
    const cnResponse = await fetch(`/api/nabidka_api.php?action=emaily_s_nabidkou&_t=${Date.now()}`, {
      cache: 'no-store',
      headers: { 'Cache-Control': 'no-cache' }
    });
    const cnData = await cnResponse.json();
    if (cnData.status === 'success') {
      EMAILS_S_CN = cnData.data?.emaily || cnData.emaily || [];
      STAVY_NABIDEK = cnData.data?.stavy || cnData.stavy || {};
    }
  } catch (e) {
    logger.warn('Nepodařilo se načíst emaily s CN:', e);
  }

  // Aktualizovat počet CN (pouze zákazníci s CN, kteří NEJSOU hotovo)
  const countCnEl = document.getElementById('count-cn');
  if (countCnEl) {
    const countCn = items.filter(r => {
      const email = (r.email || '').toLowerCase().trim();
      const stav = r.stav || 'wait';
      const isDone = stav === 'HOTOVO' || stav === 'done';
      return email && EMAILS_S_CN.includes(email) && !isDone;
    }).length;
    countCnEl.textContent = `(${countCn})`;
  }

  // Aktualizovat počet POZ (mimozáruční opravy)
  const countPozEl = document.getElementById('count-poz');
  if (countPozEl) {
    const countPoz = items.filter(r => {
      const createdBy = (r.created_by || '').trim();
      return !createdBy; // Prázdné created_by = mimozáruční oprava
    }).length;
    countPozEl.textContent = `(${countPoz})`;
  }

  // Aktualizovat počet ČEKAJÍCÍ (vyloučit zakázky s CN)
  const countWaitEl = document.getElementById('count-wait');
  if (countWaitEl) {
    const countWait = items.filter(r => {
      const stav = r.stav || 'wait';
      const isWait = stav === 'ČEKÁ' || stav === 'wait';
      if (!isWait) return false;
      const email = (r.email || '').toLowerCase().trim();
      if (email && EMAILS_S_CN.includes(email)) return false;
      return true;
    }).length;
    countWaitEl.textContent = `(${countWait})`;
  }

  // ŘAZENÍ: Nechat backendové řazení (load.php)
  // Backend řadí chronologicky podle termínu (ASC) pro všechny karty
  // Frontend už NEŘADÍ - používá pořadí z backendu
  // (Komentář: Původní složité frontendové řazení bylo odstraněno)

  grid.innerHTML = filtered.map((rec, index) => {
    const customerName = Utils.getCustomerName(rec);
    const product = Utils.getProduct(rec);
    const date = formatDate(rec.created_at || rec.datum_reklamace);
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

    // Zkontrolovat zda zákazník má cenovou nabídku (CN) a její stav
    const zakaznikEmail = (rec.email || '').toLowerCase().trim();
    const maCenovouNabidku = zakaznikEmail && EMAILS_S_CN.includes(zakaznikEmail);
    const stavNabidky = STAVY_NABIDEK[zakaznikEmail] || null;
    const jeOdsouhlasena = stavNabidky === 'potvrzena';
    const jeCekameNd = stavNabidky === 'cekame_nd';

    const highlightedCustomer = SEARCH_QUERY ? highlightText(customerName, SEARCH_QUERY) : customerName;
    const highlightedAddress = SEARCH_QUERY ? highlightText(address, SEARCH_QUERY) : address;
    const highlightedProduct = SEARCH_QUERY ? highlightText(product, SEARCH_QUERY) : product;
    const highlightedOrderId = SEARCH_QUERY ? highlightText(orderId, SEARCH_QUERY) : orderId;

    const searchMatchClass = SEARCH_QUERY && matchesSearch(rec, SEARCH_QUERY) ? 'search-match' : '';
    // Přidat barevný nádech podle stavu
    const statusBgClass = `status-bg-${status.class}`;
    // Oranžový rámeček pro zákazníky s CN (pouze pokud NEMÁ domluvený termín)
    // Zelený rámeček pro odsouhlasené nabídky
    // Šedý rámeček pro "Čekáme ND"
    // Když se domluví termín → modrá (DOMLUVENÁ), když se pošle nová CN → zase oranžová
    // CN styling se NEaplikuje na dokončené zakázky (HOTOVO)
    let cnClass = '';
    const jeHotovo = status.class === 'done';
    if (maCenovouNabidku && !appointmentText && !jeHotovo) {
      if (jeCekameNd) {
        cnClass = 'cn-cekame-nd';
      } else if (jeOdsouhlasena) {
        cnClass = 'cn-odsouhlasena';
      } else {
        cnClass = 'ma-cenovou-nabidku';
      }
    }

    // Text pro CN stav
    let cnText = 'Poslána CN';
    let cnTextClass = 'order-cn-text';
    if (jeCekameNd) {
      cnText = 'Cekame ND';
      cnTextClass = 'order-cn-text cekame-nd';
    } else if (jeOdsouhlasena) {
      cnText = 'Odsouhlasena';
      cnTextClass = 'order-cn-text odsouhlasena';
    }

    return `
      <div class="order-box ${searchMatchClass} ${statusBgClass} ${cnClass}" data-action="showDetailById" data-id="${rec.id}">
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
          <div class="order-detail-line">${highlightedAddress}</div>
          <div class="order-detail-row">
            <div class="order-detail-left">
              <div class="order-detail-line">${highlightedProduct}</div>
              <div class="order-detail-line" style="opacity: 0.6;">${date}</div>
            </div>
            <div class="order-detail-right">
              ${appointmentText
                ? `<span class="order-appointment">${appointmentText}</span>`
                : (maCenovouNabidku && !jeHotovo
                    ? `<span class="${cnTextClass}">${cnText}</span>`
                    : `<span class="order-status-text status-${status.class}">${status.text}</span>`)}
            </div>
          </div>
        </div>
      </div>
    `;
  }).join('');

  // Aktualizovat indikátor nových poznámek
  const totalUnreadCount = Object.values(unreadCountsMap).reduce((sum, count) => sum + count, 0);

  const unreadIndicator = document.getElementById('unreadNotesIndicator');
  const unreadCountSpan = document.getElementById('unreadNotesCount');

  if (unreadIndicator && unreadCountSpan) {
    if (totalUnreadCount > 0) {
      unreadCountSpan.textContent = totalUnreadCount;
      unreadIndicator.style.display = 'block';
    } else {
      unreadIndicator.style.display = 'none';
    }
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
// Step 43: Migrace na Alpine.js - open/close/overlay click/ESC nyní řeší detailModal komponenta
const ModalManager = {
  show: (content) => {
    // Nastavit obsah modalu
    document.getElementById('modalContent').innerHTML = content;

    // Step 43: Otevřít modal přes Alpine.js API
    if (window.detailModal && window.detailModal.open) {
      window.detailModal.open();
    } else {
      // Fallback pro zpětnou kompatibilitu
      if (window.scrollLock) {
        window.scrollLock.enable('detail-overlay');
      }
      document.body.classList.add('modal-open');
      document.getElementById('detailOverlay').classList.add('active');
    }

    // FIX: Safari focus fix - zajistí že modal je v DOM před scrollem
    setTimeout(() => {
      const modalContent = document.querySelector('#detailOverlay .modal-content');
      if (modalContent) {
        modalContent.scrollTop = 0; // Reset scroll pozice modalu
      }
    }, 10);
  },

  close: () => {
    // Step 43: Zavřít modal přes Alpine.js API
    if (window.detailModal && window.detailModal.close) {
      window.detailModal.close();
    } else {
      // Fallback pro zpětnou kompatibilitu
      const overlay = document.getElementById('detailOverlay');
      overlay.classList.remove('active');

      setTimeout(() => {
        document.body.classList.remove('modal-open');
        if (window.scrollLock) {
          window.scrollLock.disable('detail-overlay');
        }
      }, 50);
    }

    // Cleanup - reset state variables
    CURRENT_RECORD = null;
    SELECTED_DATE = null;
    SELECTED_TIME = null;
  },
  
  createHeader: (title, subtitle) => {
    return `
      <div class="modal-header">
        <button class="modal-close-btn" data-action="closeDetail" aria-label="Zavřít" title="Zavřít">×</button>
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

// === HELPER: Konzistentní hlavička zákazníka pro všechny modaly ===
function createCustomerHeader() {
  if (!CURRENT_RECORD) return '';

  const customerName = Utils.getCustomerName(CURRENT_RECORD);
  const address = Utils.getAddress(CURRENT_RECORD);
  const termin = CURRENT_RECORD.termin ? formatDate(CURRENT_RECORD.termin) : '—';
  const time = CURRENT_RECORD.cas_navstevy || '—';
  const status = getStatus(CURRENT_RECORD.stav);

  // Pro adminy zobrazit dropdown pro změnu stavu
  const isAdmin = CURRENT_USER && CURRENT_USER.is_admin;

  // Zjistit aktuální CN stav zákazníka
  const zakaznikEmail = (CURRENT_RECORD.email || '').toLowerCase().trim();
  const maCN = zakaznikEmail && EMAILS_S_CN && EMAILS_S_CN.includes(zakaznikEmail);
  const cnStav = maCN && STAVY_NABIDEK ? STAVY_NABIDEK[zakaznikEmail] : null;

  // Určit aktuálně vybranou hodnotu
  let aktualniHodnota = CURRENT_RECORD.stav === 'wait' || CURRENT_RECORD.stav === 'ČEKÁ' ? 'wait' :
                        CURRENT_RECORD.stav === 'open' || CURRENT_RECORD.stav === 'DOMLUVENÁ' ? 'open' :
                        CURRENT_RECORD.stav === 'done' || CURRENT_RECORD.stav === 'HOTOVO' ? 'done' : 'wait';

  // Pokud má CN a není HOTOVO, zobrazit CN stav
  if (maCN && aktualniHodnota !== 'done' && cnStav) {
    if (cnStav === 'cekame_nd') aktualniHodnota = 'cn_cekame_nd';
    else if (cnStav === 'potvrzena') aktualniHodnota = 'cn_odsouhlasena';
    else aktualniHodnota = 'cn_poslana';
  }

  const stavHtml = isAdmin ? `
    <select id="zmenaStavuSelect" class="wgs-select wgs-select--small" data-id="${CURRENT_RECORD.id}" data-email="${zakaznikEmail}">
      <optgroup label="Základní stavy">
        <option value="wait" ${aktualniHodnota === 'wait' ? 'selected' : ''}>NOVÁ</option>
        <option value="open" ${aktualniHodnota === 'open' ? 'selected' : ''}>DOMLUVENÁ</option>
        <option value="done" ${aktualniHodnota === 'done' ? 'selected' : ''}>HOTOVO</option>
      </optgroup>
      <optgroup label="CN workflow">
        <option value="cn_poslana" ${aktualniHodnota === 'cn_poslana' ? 'selected' : ''}>Poslána CN</option>
        <option value="cn_odsouhlasena" ${aktualniHodnota === 'cn_odsouhlasena' ? 'selected' : ''}>Odsouhlasena</option>
        <option value="cn_cekame_nd" ${aktualniHodnota === 'cn_cekame_nd' ? 'selected' : ''}>Čekáme ND</option>
      </optgroup>
    </select>
  ` : status.text;

  return ModalManager.createHeader(customerName, `
    <strong>Adresa:</strong> ${address}<br>
    <strong>Termín:</strong> ${termin} ${time !== '—' ? 'v ' + time : ''}<br>
    <strong>Stav:</strong> ${stavHtml}
  `);
}

// === DETAIL ===
async function showDetail(recordOrId) {
  let record;
  if (typeof recordOrId === 'string') {
    record = WGS_DATA_CACHE.find(x => x.id == recordOrId || x.reklamace_id == recordOrId);
    if (!record) {
      wgsToast.error(t('record_not_found'));
      return;
    }
  } else {
    record = WGS_DATA_CACHE.find(x => x.id == recordOrId.id || x.reklamace_id == recordOrId.reklamace_id) || recordOrId;
  }
  
  CURRENT_RECORD = record;

  // Automatické přiřazení technika (fire-and-forget)
  autoAssignTechnician(record.reklamace_id || record.cislo || record.id)
    .catch(err => logger.warn('Auto-assign technika se nezdařilo:', err.message));

  const customerName = Utils.getCustomerName(record);
  const address = Utils.getAddress(record);
  const termin = record.termin ? formatDate(record.termin) : '—';
  const time = record.cas_navstevy || '—';
  const status = getStatus(record.stav);
  
  const isCompleted = Utils.isCompleted(record);
  
  let buttonsHtml = '';
  
  if (isCompleted) {
    // Formátovat datum a čas dokončení
    const dokoncenoDatum = record.updated_at ? formatDate(record.updated_at) : '—';
    const dokoncenoData = record.updated_at ? new Date(record.updated_at) : null;
    const dokoncenoCas = dokoncenoData ? `${dokoncenoData.getHours()}:${String(dokoncenoData.getMinutes()).padStart(2, '0')}` : '—';

    // Tlacitka podle role - prodejce nema pristup k technickim funkcim
    const jeProdejce = CURRENT_USER && CURRENT_USER.role === 'prodejce';

    buttonsHtml = `
      <div class="detail-info-box">
        <div class="detail-info-box-title">Zakázka dokončena</div>
        <div class="detail-info-box-subtitle">Vyřízeno dne ${dokoncenoDatum} v ${dokoncenoCas}</div>
      </div>

      <div class="detail-buttons">
        ${!jeProdejce ? `
          <button class="detail-btn detail-btn-primary" data-action="showContactMenu" data-id="${record.id}">Kontaktovat</button>
          <button class="detail-btn detail-btn-primary" style="background: #333; color: #39ff14; border: 1px solid #39ff14;" data-action="showQrPlatbaModal" data-id="${record.id}">QR Platba</button>
        ` : ''}
        <button class="detail-btn detail-btn-primary" data-action="showCustomerDetail" data-id="${record.id}">Detail zákazníka</button>
        <button class="detail-btn detail-btn-warning" style="background: #ffeb3b; color: #000; border: 2px solid #000; font-weight: 700;" data-action="zalozitZnovu" data-id="${record.id}">Založit znovu</button>
        ${record.original_reklamace_id ? `
          <button class="detail-btn detail-btn-primary" data-action="showHistoryPDF" data-original-id="${record.original_reklamace_id}">Historie zákazníka</button>
        ` : ''}
        <button class="detail-btn detail-btn-primary" data-action="openKnihovnaPDF" data-id="${record.id}">KNIHOVNA PDF</button>
        <button class="detail-btn detail-btn-primary" data-action="showVideoteka" data-id="${record.id}">Videotéka</button>
      </div>
    `;
  } else {
    // Tlacitko pro vytvoreni cenove nabidky - pouze pro adminy
    const vytvorCNBtn = CURRENT_USER && CURRENT_USER.is_admin ? `
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #28a745; color: white;" data-action="vytvorCenovouNabidku" data-id="${record.id}">Vytvořit CN</button>
    ` : '';

    // Prodejce nema pristup k technickim funkcim (zahajit navstevu, naplanovat termin, kontaktovat)
    const jeProdejceElse = CURRENT_USER && CURRENT_USER.role === 'prodejce';

    // Tlacitka pro techniky a adminy (ne pro prodejce)
    const technickaFunkce = !jeProdejceElse ? `
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #1a1a1a; color: white;" data-action="startVisit" data-id="${record.id}">Zahájit návštěvu</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #1a1a1a; color: white;" data-action="showCalendar" data-id="${record.id}">Naplánovat termín</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #1a1a1a; color: white;" data-action="showContactMenu" data-id="${record.id}">Kontaktovat</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #333; color: #39ff14; border: 1px solid #39ff14;" data-action="showQrPlatbaModal" data-id="${record.id}">QR Platba</button>
    ` : '';

    buttonsHtml = `
      <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        ${vytvorCNBtn}
        ${technickaFunkce}
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #1a1a1a; color: white;" data-action="showCustomerDetail" data-id="${record.id}">Detail zákazníka</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #1a1a1a; color: white;" data-action="showVideoteka" data-id="${record.id}">Videotéka</button>
        <button class="btn" style="width: 100%; padding: 0.5rem 0.75rem; min-height: 38px; font-size: 0.85rem; background: #1a1a1a; color: white;" data-action="closeDetail">Zavřít</button>
      </div>
    `;
  }
  
  const content = `
    ${createCustomerHeader()}

    <div class="modal-body">
      ${buttonsHtml}
    </div>
  `;

  ModalManager.show(content);
}

function closeDetail() {
  ModalManager.close();
}

// === QR PLATBA MODAL ===
let qrLibraryPromise = null;

async function ensureQrLibraryLoaded() {
  if (qrLibraryPromise) return qrLibraryPromise;

  qrLibraryPromise = new Promise((resolve, reject) => {
    const src = 'assets/js/qrcode.min.js';
    const existing = document.querySelector('script[data-qr-lib]') ||
      Array.from(document.scripts).find(s => (s.src || '').includes(src));

    if (existing && window.QRCode && typeof window.QRCode === 'function') {
      resolve(window.QRCode);
      return;
    }

    const script = document.createElement('script');
    script.src = '/' + src;
    script.setAttribute('data-qr-lib', 'true');
    script.onload = () => {
      if (window.QRCode && typeof window.QRCode === 'function') {
        resolve(window.QRCode);
      } else {
        reject(new Error('QRCode knihovna se nenačetla správně'));
      }
    };
    script.onerror = () => reject(new Error('Nepodařilo se načíst QR knihovnu'));
    document.head.appendChild(script);
  });

  return qrLibraryPromise;
}

// Globální data pro QR platbu (pro regeneraci)
let QR_PLATBA_DATA = null;

async function showQrPlatbaModal(reklamaceId) {
  if (!reklamaceId && CURRENT_RECORD) {
    reklamaceId = CURRENT_RECORD.id;
  }

  if (!reklamaceId) {
    wgsToast.error('Chybí ID reklamace');
    return;
  }

  // Zobrazit loading modal
  const loadingContent = `
    ${createCustomerHeader()}
    <div class="modal-body" style="text-align: center; padding: 3rem;">
      <div style="font-size: 1.2rem; color: #888;">Načítám QR platební data...</div>
    </div>
  `;
  ModalManager.show(loadingContent);

  try {
    // Načíst QR data z API
    const response = await fetch(`/api/qr_platba_api.php?id=${reklamaceId}`);
    const result = await response.json();

    if (result.status === 'error') {
      throw new Error(result.message || 'Chyba při načítání dat');
    }

    // Data jsou přímo v result (ne v result.data)
    const data = result;
    QR_PLATBA_DATA = data; // Uložit pro regeneraci

    // Načíst QR knihovnu
    await ensureQrLibraryLoaded();

    // Vytvořit modal s QR kódem a editovatelnou částkou
    const initialCastka = data.castka || 0;
    const content = `
      ${createCustomerHeader()}

      <div class="modal-body" style="padding: 1.5rem;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
          <h3 style="color: #39ff14; margin: 0 0 0.5rem 0; font-size: 1.2rem;">QR kód pro platbu</h3>
          <p style="color: #888; margin: 0; font-size: 0.85rem;">Naskenujte QR kód bankovní aplikací</p>
        </div>

        <!-- QR kód -->
        <div id="qrPlatbaContainer" style="
          display: flex;
          justify-content: center;
          align-items: center;
          margin: 1.5rem 0;
          padding: 1rem;
          background: #fff;
          border-radius: 8px;
          min-height: 220px;
        ">
          ${initialCastka <= 0 ? '<div style="color: #666; font-size: 0.9rem;">Zadejte částku pro vygenerování QR kódu</div>' : ''}
        </div>

        <!-- Platební údaje -->
        <div style="
          background: #1a1a1a;
          border: 1px solid #333;
          border-radius: 8px;
          padding: 1rem;
          margin-top: 1rem;
        ">
          <div style="display: grid; gap: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">
              <span style="color: #888;">Číslo účtu:</span>
              <span style="color: #fff; font-family: monospace; font-weight: bold;">${Utils.escapeHtml(data.ucet || data.iban_formatovany)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">
              <span style="color: #888;">Částka CZK:</span>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="number" id="qrCastkaCzkInput" class="wgs-input wgs-input--cislo" value="${initialCastka}" min="0" step="1" style="width: 100px;" onchange="prepoctiCastku('czk')" onkeyup="prepoctiCastku('czk')">
                <span style="color: #888;">Kč</span>
              </div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">
              <span style="color: #888;">Částka EUR:</span>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="number" id="qrCastkaEurInput" class="wgs-input wgs-input--small" value="${initialCastka > 0 ? (initialCastka / 25).toFixed(0) : ''}" min="0" step="1" style="width: 100px; text-align: right;" onchange="prepoctiCastku('eur')" onkeyup="prepoctiCastku('eur')">
                <span style="color: #888;">€</span>
              </div>
            </div>
            <div style="text-align: right; font-size: 0.75rem; color: #666; margin-top: -0.5rem;">
              Kurz: 1 EUR = 25 CZK
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">
              <span style="color: #888;">Variabilní symbol:</span>
              <span style="color: #fff; font-family: monospace;">${Utils.escapeHtml(data.vs)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="color: #888;">Splatnost:</span>
              <span style="color: #fff;">Ihned</span>
            </div>
          </div>
        </div>

        <!-- Tlačítka -->
        <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
          <button class="btn" style="flex: 1; padding: 0.75rem; background: #333; color: #fff;" data-action="showDetail" data-id="${reklamaceId}">Zpět</button>
        </div>
      </div>
    `;

    ModalManager.show(content);

    // Vygenerovat QR kód po zobrazení modalu (pokud je částka > 0)
    if (initialCastka > 0) {
      setTimeout(() => regenerovatQrKod(), 100);
    }

  } catch (error) {
    logger.error('QR platba - chyba:', error);

    const errorContent = `
      ${createCustomerHeader()}
      <div class="modal-body" style="padding: 2rem; text-align: center;">
        <div style="color: #ff4444; font-size: 1.1rem; margin-bottom: 1rem;">
          ${Utils.escapeHtml(error.message)}
        </div>
        <button class="btn" style="padding: 0.75rem 2rem; background: #333; color: #fff;" data-action="showDetail" data-id="${reklamaceId}">Zpět</button>
      </div>
    `;
    ModalManager.show(errorContent);
  }
}

// Konstanty pro přepočet měn
const QR_KURZ_EUR_CZK = 25;

// Funkce pro přepočet částky mezi CZK a EUR
let prepocetTimeout = null;
function prepoctiCastku(zdroj) {
  clearTimeout(prepocetTimeout);
  prepocetTimeout = setTimeout(() => {
    const inputCzk = document.getElementById('qrCastkaCzkInput');
    const inputEur = document.getElementById('qrCastkaEurInput');

    if (!inputCzk || !inputEur) return;

    if (zdroj === 'czk') {
      // CZK → EUR
      const czk = parseFloat(inputCzk.value) || 0;
      inputEur.value = czk > 0 ? Math.round(czk / QR_KURZ_EUR_CZK) : '';
    } else {
      // EUR → CZK
      const eur = parseFloat(inputEur.value) || 0;
      inputCzk.value = eur > 0 ? Math.round(eur * QR_KURZ_EUR_CZK) : '';
    }

    // Regenerovat QR kód
    regenerovatQrKod();
  }, 200);
}

// Funkce pro regeneraci QR kódu při změně částky
let qrRegenerateTimeout = null;
function regenerovatQrKod() {
  // Debounce - počkat 300ms po posledním stisku
  clearTimeout(qrRegenerateTimeout);
  qrRegenerateTimeout = setTimeout(() => {
    const inputCzk = document.getElementById('qrCastkaCzkInput');
    const container = document.getElementById('qrPlatbaContainer');

    if (!inputCzk || !container || !QR_PLATBA_DATA) return;

    const castka = parseFloat(inputCzk.value) || 0;

    if (castka <= 0) {
      container.innerHTML = '<div style="color: #666; font-size: 0.9rem;">Zadejte částku pro vygenerování QR kódu</div>';
      return;
    }

    // Generovat SPD string (vždy v CZK)
    // Použít účet ve formátu 188784838/0300, convertToIBAN ho převede na IBAN (jako PSA)
    // Přidat zprávu s číslem objednávky a jménem zákazníka
    const spdString = generujSpdString(
      QR_PLATBA_DATA.ucet,
      castka,
      QR_PLATBA_DATA.vs,
      QR_PLATBA_DATA.cislo,
      QR_PLATBA_DATA.jmeno
    );

    // Vygenerovat QR kód
    container.innerHTML = '';
    if (window.QRCode) {
      new QRCode(container, {
        text: spdString,
        width: 220,
        height: 220,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.L
      });
    }
  }, 300);
}

// Odstranění diakritiky pro SPAYD zprávu
function odstranDiakritiku(text) {
  if (!text) return '';
  const mapa = {
    'á': 'a', 'č': 'c', 'ď': 'd', 'é': 'e', 'ě': 'e', 'í': 'i', 'ň': 'n',
    'ó': 'o', 'ř': 'r', 'š': 's', 'ť': 't', 'ú': 'u', 'ů': 'u', 'ý': 'y', 'ž': 'z',
    'Á': 'A', 'Č': 'C', 'Ď': 'D', 'É': 'E', 'Ě': 'E', 'Í': 'I', 'Ň': 'N',
    'Ó': 'O', 'Ř': 'R', 'Š': 'S', 'Ť': 'T', 'Ú': 'U', 'Ů': 'U', 'Ý': 'Y', 'Ž': 'Z'
  };
  return text.split('').map(c => mapa[c] || c).join('');
}

// Sanitizace zprávy pro SPAYD (bez diakritiky, bez speciálních znaků)
function sanitizujZpravu(zprava) {
  if (!zprava) return '';
  return odstranDiakritiku(zprava)
    .replace(/[\r\n]+/g, ' ')  // Odstranit nové řádky
    .replace(/\*/g, '')        // Odstranit hvězdičky (rezervováno v SPAYD)
    .replace(/[^\w\s\-\.]/g, '') // Pouze alfanumerické, mezery, pomlčky, tečky
    .trim()
    .slice(0, 60);             // Max 60 znaků pro MSG pole
}

// Generování SPD stringu pro QR platbu
function generujSpdString(ucet, castka, vs, cisloObj, jmeno) {
  console.log('[QR] Vstupní údaje - účet:', ucet, 'částka:', castka, 'vs:', vs, 'obj:', cisloObj, 'jméno:', jmeno);

  // Pokud přijde účet ve formátu 188784838/0300, konvertuj na IBAN
  let cleanIban = ucet;
  if (ucet && ucet.includes('/')) {
    const parts = ucet.split('/');
    console.log('[QR] Konvertuji účet', parts[0], 'banka', parts[1]);
    cleanIban = convertToIBAN(parts[0], parts[1]);
  } else {
    cleanIban = ucet.replace(/\s/g, '').toUpperCase();
    console.log('[QR] Použití přímo IBAN:', cleanIban);
  }

  // Částka - vždy 2 desetinná místa s tečkou
  const amountStr = castka.toFixed(2);

  // Zpráva pro příjemce - číslo objednávky a jméno zákazníka
  const zprava = sanitizujZpravu(`Obj ${cisloObj || vs} ${jmeno || ''}`);

  // SPAYD formát s MSG
  const spd = `SPD*1.0*ACC:${cleanIban}*AM:${amountStr}*CC:CZK*MSG:${zprava}`;

  console.log('[QR] === VÝSLEDNÝ SPD STRING ===');
  console.log('[QR]', spd);
  console.log('[QR] IBAN:', cleanIban, '(délka:', cleanIban.length, ')');
  console.log('[QR] Částka:', amountStr, 'CZK');
  console.log('[QR] Zpráva:', zprava);
  return spd;
}

// Konverze čísla účtu na IBAN (z PSA kalkulátoru)
function convertToIBAN(account, bankCode) {
  let rawAccount = (account || '').toString().replace(/\s/g, '');
  bankCode = (bankCode || '').toString().replace(/\D/g, '').padStart(4, '0');

  let predcisli = '';
  let cisloUctu = '';

  if (rawAccount.includes('-')) {
    const parts = rawAccount.split('-');
    predcisli = (parts[0] || '').replace(/\D/g, '');
    cisloUctu = (parts[1] || '').replace(/\D/g, '');
  } else {
    const digits = rawAccount.replace(/\D/g, '');
    if (digits.length > 10) {
      predcisli = digits.slice(0, -10);
      cisloUctu = digits.slice(-10);
    } else {
      predcisli = '';
      cisloUctu = digits;
    }
  }

  predcisli = predcisli.padStart(6, '0');
  cisloUctu = cisloUctu.padStart(10, '0');

  const bban = bankCode + predcisli + cisloUctu;

  if (bban.length !== 20) {
    throw new Error(`Neplatná délka BBAN: ${bban.length}`);
  }

  // Výpočet kontrolních číslic (ISO 7064 Mod 97-10)
  const checkString = bban + '123500';
  let remainder = BigInt(checkString) % 97n;
  const checkDigits = String(98n - remainder).padStart(2, '0');

  const iban = 'CZ' + checkDigits + bban;

  // Kontrola IBAN délky (CZ má vždy 24 znaků)
  if (iban.length !== 24) {
    throw new Error(`Neplatná délka IBAN: ${iban.length} (očekáváno 24)`);
  }

  console.log('[QR] Konverze účtu:', account, '/', bankCode, '→', iban);
  return iban;
}

// === ZOBRAZENÍ HISTORIE PDF Z PŮVODNÍ ZAKÁZKY ===
async function showHistoryPDF(originalReklamaceId) {
  if (!originalReklamaceId) {
    wgsToast.error('Chybí ID původní zakázky');
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
      // Pokud je původní zakázka smazána, zobrazit specifickou hlášku
      if (result.message && result.message.includes('nebyla nalezena')) {
        wgsToast.error('Původní zakázka byla smazána.\n\nHistorie PDF není k dispozici.');
        return;
      }
      throw new Error(result.message || 'Nepodařilo se načíst dokumenty');
    }

    if (!result.documents || result.documents.length === 0) {
      wgsToast.info('Historie PDF není k dispozici.\n\nPůvodní zakázka ještě nemá vytvořený PDF dokument.');
      return;
    }

    // Zobrazit první PDF dokument
    const firstDoc = result.documents[0];
    logger.log(`Otevírám PDF: ${firstDoc.file_path}`);

    // Otevřít PDF v modal okně (funguje lépe na mobilu než window.open)
    zobrazPDFModal(firstDoc.file_path, originalReklamaceId, 'historie');

  } catch (error) {
    logger.error('Chyba při načítání historie PDF:', error);
    wgsToast.error(`Nepodařilo se načíst historii PDF:\n${error.message}`);
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
  // Ochrana proti duplicitnímu volání
  if (startVisitInProgress) {
    return;
  }
  startVisitInProgress = true;

  const z = WGS_DATA_CACHE.find(x => x.id == id);

  if (!z) {
    wgsToast.error(t('record_not_found'));
    startVisitInProgress = false;
    return;
  }

  if (Utils.isCompleted(z)) {
    wgsToast.warning(t('visit_already_completed'));
    startVisitInProgress = false;
    return;
  }

  const normalizedData = normalizeCustomerData(z);

  localStorage.setItem('currentCustomer', JSON.stringify(normalizedData));
  localStorage.setItem('visitStartTime', new Date().toISOString());

  const photoKey = 'photoSections_' + normalizedData.id;
  localStorage.removeItem(photoKey);

  logger.log('Normalizovaná data uložena:', normalizedData);

  window.location.href = 'photocustomer.php?new=true';
}

// === VYTVOŘIT CENOVOU NABÍDKU ===
function vytvorCenovouNabidku(reklamaceId) {
  if (!reklamaceId) {
    wgsToast.error('Chybí ID reklamace');
    return;
  }

  // Přesměrovat na stránku cenové nabídky s ID reklamace
  window.location.href = 'cenova-nabidka.php?reklamace_id=' + encodeURIComponent(reklamaceId);
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

      wgsToast.success(successMsg);

      // Reload all data from DB to ensure consistency
      await loadAll();

      // Re-open detail to show updated data
      if (data.id) {
        closeDetail();
        setTimeout(() => showDetail(data.id), 100);
      } else {
        closeDetail();
      }
    } else {
      wgsToast.error(t('error') + ': ' + (result.message || t('failed_to_save')));
    }
  } catch (e) {
    logger.error('Chyba při ukládání:', e);
    wgsToast.error(t('save_error') + ': ' + e.message);
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
    ${createCustomerHeader()}

    <!-- Vybraný termín - fixní nad kalendářem -->
    <div id="selectedDateDisplay" style="display: none; background: #f5f5f5; border: 2px solid #666; color: #333; font-size: 0.85rem; padding: 0.5rem 1rem; margin: 0 1rem; border-radius: 4px; font-weight: 600; text-align: center; font-family: inherit;"></div>

    <!-- Varování o kolizi - skryté, zobrazí se při výběru obsazeného času -->
    <div id="collisionWarning" style="display: none; background: #fee; border: 2px solid #c00; color: #900; font-size: 0.85rem; padding: 0.5rem 1rem; margin: 0.5rem 1rem 0; border-radius: 4px; font-weight: 600; text-align: center; font-family: inherit;"></div>

    <div class="modal-body" style="max-height: 60vh; overflow-y: auto; padding: 1rem;">
      <div class="calendar-container">
        <div id="calGrid"></div>
        <div id="distanceInfo"></div>
        <div id="dayBookings"></div>
        <div id="timeGrid"></div>
      </div>
    </div>

    <div class="detail-buttons">
      <button class="detail-btn detail-btn-primary" data-action="saveSelectedDate">Uložit termín</button>
      <button class="detail-btn detail-btn-secondary" data-action="showDetail">Zpět</button>
    </div>
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
    wgsToast.warning(t('cannot_show_past_months'));
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
    <button class="calendar-nav-btn" data-action="previousMonth">◀ Předchozí</button>
    <span class="calendar-month-title">${monthNames[m]} ${y}</span>
    <button class="calendar-nav-btn" data-action="nextMonth">Další ▶</button>
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
        wgsToast.warning(t('cannot_select_past_date'));
        return;
      }

      SELECTED_DATE = `${d}.${m + 1}.${y}`;
      document.querySelectorAll('.cal-day').forEach(x => x.classList.remove('selected'));
      el.classList.add('selected');

      let displayText = `Vybraný den: ${SELECTED_DATE}`;
      document.getElementById('selectedDateDisplay').textContent = displayText;

      // Zobrazit časy okamžitě
      renderTimeGrid();

      // Zobrazit vzdálenost a další termíny na tento den
      showDayBookingsWithDistances(SELECTED_DATE);
    };
    daysGrid.appendChild(el);
  }
  
  grid.appendChild(daysGrid);
}

// fetchCsrfToken přesunuto do utils.js (Step 106)
// Funkce je dostupná jako window.fetchCsrfToken() nebo Utils.fetchCsrfToken()

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

    <div class="detail-buttons">
      <button class="detail-btn detail-btn-secondary" data-action="showCalendarBack">Zpět na kalendář</button>
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
        displayEl.textContent = `Vybraný termín: ${SELECTED_DATE} — ${SELECTED_TIME}`;
        displayEl.style.display = 'block';

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

// === KONTAKT ===
function showContactMenu(id) {
  const phone = CURRENT_RECORD.telefon || '';
  const address = Utils.getAddress(CURRENT_RECORD);

  const content = `
    ${createCustomerHeader()}

    <div class="modal-body">
      <div class="detail-buttons">
        ${phone ? `<a href="tel:${phone}" class="detail-btn detail-btn-primary" style="text-decoration: none;">Zavolat</a>` : ''}
        <button class="detail-btn detail-btn-primary" data-action="openCalendarFromDetail" data-id="${id}">Termín návštěvy</button>
        ${phone ? `<button class="detail-btn detail-btn-primary" data-action="sendContactAttemptEmail" data-id="${id}" data-phone="${phone}">Odeslat SMS</button>` : ''}
        ${address && address !== '—' ? `<a href="https://waze.com/ul?q=${encodeURIComponent(address)}&navigate=yes" class="detail-btn detail-btn-primary" style="text-decoration: none;" target="_blank">Navigovat (Waze)</a>` : ''}
        ${address && address !== '—' ? `<a href="https://www.google.com/maps?q=${encodeURIComponent(address)}&layer=c" class="detail-btn detail-btn-primary" style="text-decoration: none;" target="_blank">Google Street View</a>` : ''}
        <button class="detail-btn detail-btn-primary" data-action="showDetail">Zpět</button>
      </div>
    </div>
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
  const zadavatel = CURRENT_RECORD.zadavatel_jmeno || CURRENT_RECORD.created_by_name || CURRENT_RECORD.prodejce || '';
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
    ${createCustomerHeader()}

    <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1rem;">

      <!-- KOMPAKTNÍ INFO BLOK -->
      <div style="background: #1a1a1a; border: none; border-radius: 4px; padding: 0.75rem; margin-bottom: 1rem;">
        <div style="display: grid; grid-template-columns: auto 1fr; gap: 0.5rem; font-size: 0.9rem;">
          <span style="color: #aaa; font-weight: 600;">Číslo objednávky:</span>
          <input type="text" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(reklamaceId)}" readonly>

          <span style="color: #aaa; font-weight: 600;">Zadavatel:</span>
          <input type="text" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #eee; color: #000;" value="${Utils.escapeHtml(zadavatel)}" readonly placeholder="Prodejce/Uživatel">

          <span style="color: #aaa; font-weight: 600;">Číslo reklamace:</span>
          <input type="text" id="edit_cislo" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(cislo)}">

          <span style="color: #aaa; font-weight: 600;">Fakturace:</span>
          <span style="color: #fff; font-weight: 600; padding: 0.25rem 0;">${fakturace_firma.toUpperCase() === 'SK' ? 'Slovensko (SK)' : 'Česká republika (CZ)'}</span>

          <span style="color: #aaa; font-weight: 600;">Datum prodeje:</span>
          <input type="text" id="edit_datum_prodeje" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(datum_prodeje)}" placeholder="DD.MM.RRRR">

          <span style="color: #aaa; font-weight: 600;">Datum reklamace:</span>
          <input type="text" id="edit_datum_reklamace" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(datum_reklamace)}" placeholder="DD.MM.RRRR">

          <span style="color: #aaa; font-weight: 600;">Jméno:</span>
          <input type="text" id="edit_jmeno" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${customerName}">

          <span style="color: #aaa; font-weight: 600;">Telefon:</span>
          <input type="tel" id="edit_telefon" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${phone}">

          <span style="color: #aaa; font-weight: 600;">Email:</span>
          <input type="email" id="edit_email" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${email}">

          <span style="color: #aaa; font-weight: 600;">Adresa:</span>
          <input type="text" id="edit_adresa" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${address}">

          <span style="color: #aaa; font-weight: 600;">Model:</span>
          <input type="text" id="edit_model" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${product}">

          <span style="color: #aaa; font-weight: 600;">Provedení:</span>
          <input type="text" id="edit_provedeni" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(provedeni)}" placeholder="Látka / Kůže">

          <span style="color: #aaa; font-weight: 600;">Barva:</span>
          <input type="text" id="edit_barva" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(barva)}">
        </div>
      </div>

      <!-- DOPLŇUJÍCÍ INFORMACE OD PRODEJCE -->
      <div style="margin-bottom: 1rem;">
        <label style="display: block; color: #aaa; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.25rem;">Doplňující informace od prodejce:</label>
        <textarea id="edit_doplnujici_info"
                  style="width: 100%; border: 1px solid #333; padding: 0.5rem; border-radius: 3px; font-size: 0.9rem; min-height: 40px; background: #fff; color: #000; resize: none; font-family: inherit; overflow: hidden;"
                  placeholder="Zadejte doplňující informace od prodejce"
                  oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'">${Utils.escapeHtml(doplnujici_info)}</textarea>
      </div>

      <!-- POPIS PROBLÉMU OD ZÁKAZNÍKA -->
      <div style="margin-bottom: 2rem;">
        <label style="display: block; color: #aaa; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.25rem;">Popis problému od zákazníka:</label>
        <textarea id="edit_popis_problemu"
                  style="width: 100%; border: 1px solid #333; padding: 0.5rem; border-radius: 3px; font-size: 0.9rem; min-height: 40px; background: #fff; color: #000; resize: none; font-family: inherit; overflow: hidden;"
                  placeholder="Zadejte popis problému od zákazníka"
                  oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'">${Utils.escapeHtml(description)}</textarea>
      </div>

      <!-- FOTOTÉKA -->
      <div style="margin-bottom: 1rem; background: #1a1a1a; border-radius: 6px; padding: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
          <label style="color: #aaa; font-weight: 600; font-size: 0.85rem;" id="fototeka-nadpis">Fototeka (${fotky.length})</label>
          <button type="button"
                  data-action="otevritVyberFotek"
                  data-id="${reklamaceId}"
                  style="background: #333; color: #fff; border: 1px solid #555; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.8rem; cursor: pointer;">
            Pridat fotky
          </button>
        </div>
        <input type="file"
               id="fototeka-input-${reklamaceId}"
               accept="image/*"
               multiple
               style="display: none;"
               data-reklamace-id="${reklamaceId}">
        <div id="fototeka-grid" style="display: flex; flex-wrap: wrap; gap: 8px; min-height: 60px;">
          ${fotky.length > 0 ? fotky.map((f, i) => {
            const photoPath = typeof f === 'object' ? f.photo_path : f;
            const photoId = typeof f === 'object' ? f.id : null;
            const escapedUrl = photoPath.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");

            return `
              <div class="foto-wrapper" style="position: relative; width: 60px; height: 60px; flex-shrink: 0;">
                <img src='${photoPath}'
                     style='width: 60px; height: 60px; object-fit: cover; border: 1px solid #444; cursor: pointer; border-radius: 4px;'
                     alt='Fotka ${i+1}'
                     data-action="showPhotoFullscreen"
                     data-url="${escapedUrl}">
                ${photoId ? `
                  <button class="foto-delete-btn"
                          data-action="smazatFotku"
                          data-photo-id="${photoId}"
                          data-url="${escapedUrl}"
                          title="Smazat fotku">
                    x
                  </button>
                ` : ''}
              </div>
            `;
          }).join('') : `
            <p style="color: #666; font-size: 0.85rem; margin: 0; padding: 0.5rem 0;">Zadne fotografie</p>
          `}
        </div>
        <div id="fototeka-nahravani" style="display: none; margin-top: 0.75rem; padding: 0.5rem; background: #222; border-radius: 4px;">
          <p style="color: #aaa; font-size: 0.8rem; margin: 0;">Nahravani fotek...</p>
          <div style="background: #333; height: 4px; border-radius: 2px; margin-top: 0.5rem; overflow: hidden;">
            <div id="fototeka-progress" style="background: #fff; height: 100%; width: 0%; transition: width 0.3s;"></div>
          </div>
        </div>
      </div>

      <!-- PDF DOKUMENTY - nyní jako knihovna s možností nahrát interní PDF -->
      <div style="margin-bottom: 1rem;">
        <label style="display: block; color: #aaa; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.5rem;">KNIHOVNA PDF:</label>
        <button class="btn customer-detail-btn"
                data-action="openKnihovnaPDF"
                data-id="${id}">
          KNIHOVNA PDF
        </button>
      </div>

      ${CURRENT_USER.is_admin ? `
        <div style="border-top: 1px solid #333; padding-top: 1rem; margin-top: 1rem;">
          <button class="btn customer-detail-btn danger" data-action="deleteReklamace" data-id="${id}">
            Smazat reklamaci
          </button>
          <p style="font-size: 0.7rem; color: #666; margin-top: 0.25rem; text-align: center;">Smaže vše včetně fotek a PDF</p>
        </div>
      ` : ''}

      <div class="detail-buttons">
        <button class="detail-btn detail-btn-primary" data-action="saveAllCustomerData" data-id="${id}">Uložit změny</button>
        <button class="detail-btn detail-btn-primary" data-action="showDetail" data-id="${id}">Zpět</button>
      </div>

    </div>
  `;

  ModalManager.show(content);

  // Auto-resize textarea pri prvnim zobrazeni
  setTimeout(() => {
    const textareas = document.querySelectorAll('#edit_doplnujici_info, #edit_popis_problemu');
    textareas.forEach(ta => {
      if (ta) {
        ta.style.height = 'auto';
        ta.style.height = ta.scrollHeight + 'px';
      }
    });
  }, 50);
}

/**
 * Zobrazí PDF v modálním okně s tlačítky Zavřít a Odeslat
 * Univerzální řešení pro desktop, PWA, iOS Safari
 * @param {string} pdfUrl - URL k PDF souboru
 * @param {string} claimId - ID zakázky
 * @param {string} typ - Typ PDF: 'report' (výchozí) nebo 'historie'
 */
function zobrazPDFModal(pdfUrl, claimId, typ = 'report') {
  const titulek = typ === 'historie' ? 'Historie PDF' : 'KNIHOVNA PDF';

  // Hlavní overlay - flexbox layout s fixní hlavičkou a patičkou
  const overlay = document.createElement('div');
  overlay.id = 'pdfModalOverlay';
  overlay.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.95); z-index: 10003;
    display: flex; flex-direction: column;
  `;

  // === HLAVIČKA (fixní nahoře) ===
  const header = document.createElement('div');
  header.style.cssText = `
    flex-shrink: 0; padding: 12px 16px;
    background: #222; color: white;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid #444;
  `;
  header.innerHTML = `
    <div>
      <div style="font-weight: 600; font-size: 1rem;">${titulek}</div>
      <div style="font-size: 0.75rem; opacity: 0.7;">ID: ${claimId || '-'}</div>
    </div>
    <button id="pdfCloseBtn" style="
      background: #555; color: white; border: none;
      padding: 10px 20px; border-radius: 6px;
      font-weight: 600; font-size: 0.9rem; cursor: pointer;
    ">Zavřít</button>
  `;

  // === PDF NÁHLED (zabere zbytek místa) ===
  const pdfContainer = document.createElement('div');
  // Na mobilu menší padding pro maximální využití obrazovky
  const isMobile = window.innerWidth < 768;
  const padding = isMobile ? '4px' : '16px';
  pdfContainer.style.cssText = `flex: 1; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: ${padding};`;

  const iframe = document.createElement('iframe');
  iframe.src = pdfUrl;
  // Na mobilu i desktopu zobrazit PDF na plnou šířku bez omezení
  iframe.style.cssText = 'width: 100%; height: 100%; border: none; background: white; border-radius: 8px;';
  pdfContainer.appendChild(iframe);

  // === PATIČKA S TLAČÍTKY (fixní dole) ===
  const footer = document.createElement('div');
  footer.style.cssText = `
    flex-shrink: 0; padding: 12px 16px;
    background: #222; border-top: 1px solid #444;
    display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;
  `;

  // Tlačítko Stáhnout
  const btnStahnout = document.createElement('button');
  btnStahnout.textContent = 'Stáhnout';
  btnStahnout.style.cssText = 'padding: 12px 24px; font-size: 0.9rem; font-weight: 600; background: #444; color: white; border: none; border-radius: 6px; cursor: pointer;';
  btnStahnout.onclick = () => {
    const link = document.createElement('a');
    link.href = pdfUrl;
    link.download = `WGS_PDF_${claimId || 'dokument'}.pdf`;
    link.click();
  };

  // Tlačítko Sdílet
  const btnSdilet = document.createElement('button');
  btnSdilet.textContent = 'Sdílet';
  btnSdilet.style.cssText = 'padding: 12px 24px; font-size: 0.9rem; font-weight: 600; background: #333; color: white; border: none; border-radius: 6px; cursor: pointer;';
  btnSdilet.onclick = async () => {
    if (!navigator.share) {
      wgsToast.info('Sdílení není podporováno. Použijte tlačítko Stáhnout.');
      return;
    }
    try {
      btnSdilet.textContent = 'Načítám...';
      const response = await fetch(pdfUrl);
      const blob = await response.blob();
      const file = new File([blob], `WGS_PDF_${claimId || 'dokument'}.pdf`, { type: 'application/pdf' });
      await navigator.share({ files: [file], title: titulek });
    } catch (e) {
      if (e.name !== 'AbortError') wgsToast.error('Chyba: ' + e.message);
    } finally {
      btnSdilet.textContent = 'Sdílet';
    }
  };

  // Tlačítko Zavřít
  const btnZavrit = document.createElement('button');
  btnZavrit.textContent = 'Zavřít';
  btnZavrit.style.cssText = 'padding: 12px 24px; font-size: 0.9rem; font-weight: 600; background: #666; color: white; border: none; border-radius: 6px; cursor: pointer;';
  btnZavrit.onclick = () => overlay.remove();

  footer.appendChild(btnStahnout);
  footer.appendChild(btnSdilet);
  footer.appendChild(btnZavrit);

  // Sestavení
  overlay.appendChild(header);
  overlay.appendChild(pdfContainer);
  overlay.appendChild(footer);

  // Event handlery
  header.querySelector('#pdfCloseBtn').onclick = () => overlay.remove();

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

/**
 * Zobrazí knihovnu dokumentů (PDF) s možností nahrát interní dokument
 * @param {string} claimId - ID zakázky
 */
async function zobrazKnihovnuPDF(claimId) {
  if (!claimId) {
    wgsToast.error('Chybí ID zakázky');
    return;
  }

  // Vytvořit modal overlay
  const overlay = document.createElement('div');
  overlay.id = 'knihovnaPdfOverlay';
  overlay.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.85); z-index: 10003;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
  `;

  // Vnitřní kontejner s omezenou šířkou
  const modalBox = document.createElement('div');
  modalBox.style.cssText = `
    width: 100%; max-width: 600px; max-height: 90vh;
    background: #1a1a1a; border-radius: 12px;
    display: flex; flex-direction: column;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
  `;

  // === HLAVIČKA ===
  const header = document.createElement('div');
  header.style.cssText = `
    flex-shrink: 0; padding: 12px 16px;
    background: #222; color: white;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid #444;
    border-radius: 12px 12px 0 0;
  `;
  header.innerHTML = `
    <div>
      <div style="font-weight: 600; font-size: 1rem;">KNIHOVNA PDF</div>
      <div style="font-size: 0.75rem; opacity: 0.7;">ID: ${claimId}</div>
    </div>
    <button id="knihovnaCloseBtn" style="
      background: #555; color: white; border: none;
      padding: 10px 20px; border-radius: 6px;
      font-weight: 600; font-size: 0.9rem; cursor: pointer;
    ">Zavrit</button>
  `;

  // === OBSAH ===
  const content = document.createElement('div');
  content.style.cssText = `
    flex: 1; overflow-y: auto; padding: 16px;
    display: flex; flex-direction: column; gap: 16px;
  `;
  content.innerHTML = `
    <div style="text-align: center; padding: 40px; color: #888;">
      Nacitam dokumenty...
    </div>
  `;

  // === PATIČKA S TLAČÍTKEM NAHRÁT ===
  const footer = document.createElement('div');
  footer.style.cssText = `
    flex-shrink: 0; padding: 12px 16px;
    background: #222; border-top: 1px solid #444;
    display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;
    border-radius: 0 0 12px 12px;
  `;
  footer.innerHTML = `
    <button id="btnNahratPdf" style="
      padding: 12px 24px; font-size: 0.9rem; font-weight: 600;
      background: #333; color: white; border: 1px solid #555;
      border-radius: 6px; cursor: pointer;
    ">+ Nahrat interni PDF</button>
    <button id="btnZavritKnihovnu" style="
      padding: 12px 24px; font-size: 0.9rem; font-weight: 600;
      background: #666; color: white; border: none;
      border-radius: 6px; cursor: pointer;
    ">Zavrit</button>
  `;

  // Sestavení - elementy jdou do modalBox
  modalBox.appendChild(header);
  modalBox.appendChild(content);
  modalBox.appendChild(footer);
  overlay.appendChild(modalBox);

  // Event handlery
  const zavritKnihovnu = () => overlay.remove();
  header.querySelector('#knihovnaCloseBtn').onclick = zavritKnihovnu;

  // Zavřít kliknutím mimo modal
  overlay.onclick = (e) => {
    if (e.target === overlay) zavritKnihovnu();
  };
  footer.querySelector('#btnZavritKnihovnu').onclick = zavritKnihovnu;

  // ESC pro zavření
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      zavritKnihovnu();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  // Tlačítko pro nahrání PDF
  footer.querySelector('#btnNahratPdf').onclick = () => {
    zobrazFormularNahraniPdf(claimId, () => nactiDokumenty());
  };

  document.body.appendChild(overlay);

  // Funkce pro načtení dokumentů
  async function nactiDokumenty() {
    try {
      const response = await fetch(`/api/documents_api.php?action=seznam&reklamace_id=${encodeURIComponent(claimId)}&_t=${Date.now()}`, {
        cache: 'no-store'
      });
      const data = await response.json();

      if (data.status !== 'success') {
        throw new Error(data.message || 'Chyba při načítání');
      }

      const dokumenty = data.dokumenty || data.data?.dokumenty || [];

      if (dokumenty.length === 0) {
        content.innerHTML = `
          <div style="text-align: center; padding: 40px; color: #888;">
            <div style="font-size: 1.2rem; margin-bottom: 10px;">Zadne dokumenty</div>
            <div style="font-size: 0.85rem;">Kliknete na "Nahrat interni PDF" pro pridani dokumentu.</div>
          </div>
        `;
        return;
      }

      // Zobrazit seznam dokumentů
      content.innerHTML = dokumenty.map(dok => {
        const datum = dok.nahrano ? new Date(dok.nahrano).toLocaleDateString('cs-CZ') : '-';
        const velikostKb = dok.velikost ? Math.round(dok.velikost / 1024) : 0;
        const jeInterni = dok.interni;

        return `
          <div class="dokument-polozka" style="
            background: #1a1a1a; border: 1px solid #333; border-radius: 8px;
            padding: 12px 16px; display: flex; justify-content: space-between;
            align-items: center; gap: 12px;
          ">
            <div style="flex: 1; min-width: 0;">
              <div style="font-weight: 600; color: #fff; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                ${escapeHtml(dok.nazev)}
              </div>
              <div style="font-size: 0.75rem; color: #888; display: flex; gap: 12px; flex-wrap: wrap;">
                <span>${dok.typ_popis}</span>
                <span>${datum}</span>
                <span>${velikostKb} KB</span>
                ${jeInterni ? '<span style="color: #ff9800;">Interni</span>' : ''}
              </div>
            </div>
            <div style="display: flex; gap: 8px;">
              ${jeInterni ? `
                <button class="btn-dokument-akce" data-action="prejmenovat" data-id="${dok.id}" data-nazev="${escapeHtml(dok.nazev)}" style="
                  padding: 8px 12px; font-size: 0.8rem; font-weight: 600;
                  background: #555; color: white; border: none;
                  border-radius: 4px; cursor: pointer;
                ">Upravit</button>
              ` : ''}
              <button class="btn-dokument-akce" data-action="zobrazitPdf" data-cesta="${escapeHtml(dok.cesta)}" style="
                padding: 8px 16px; font-size: 0.8rem; font-weight: 600;
                background: #333; color: white; border: 1px solid #555;
                border-radius: 4px; cursor: pointer;
              ">Zobrazit</button>
              ${CURRENT_USER.is_admin ? `
                <button class="btn-dokument-akce" data-action="smazatPdf" data-id="${dok.id}" data-nazev="${escapeHtml(dok.nazev)}" style="
                  padding: 8px 12px; font-size: 0.8rem; font-weight: 600;
                  background: #dc3545; color: white; border: none;
                  border-radius: 4px; cursor: pointer;
                ">Smazat</button>
              ` : ''}
            </div>
          </div>
        `;
      }).join('');

      // Event listener pro tlačítka dokumentů
      content.querySelectorAll('.btn-dokument-akce').forEach(btn => {
        btn.onclick = async (e) => {
          const akce = btn.getAttribute('data-action');

          if (akce === 'zobrazitPdf') {
            const cesta = btn.getAttribute('data-cesta');
            zobrazPDFModal(cesta, claimId, 'report');
          } else if (akce === 'prejmenovat') {
            const dokumentId = btn.getAttribute('data-id');
            const stavajiciNazev = btn.getAttribute('data-nazev');
            await zobrazDialogPrejmenovat(dokumentId, stavajiciNazev, nactiDokumenty);
          } else if (akce === 'smazatPdf') {
            const dokumentId = btn.getAttribute('data-id');
            const nazev = btn.getAttribute('data-nazev');

            const potvrdit = await wgsConfirm(`Opravdu chcete smazat dokument "${nazev}"?`, {
              titulek: 'Smazat dokument',
              btnPotvrdit: 'Smazat',
              nebezpecne: true
            });

            if (potvrdit) {
              await smazatDokument(dokumentId);
              nactiDokumenty();
            }
          }
        };
      });

    } catch (error) {
      logger.error('Chyba při načítání dokumentů:', error);
      content.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #dc3545;">
          Chyba: ${escapeHtml(error.message)}
        </div>
      `;
    }
  }

  // Smazání dokumentu
  async function smazatDokument(dokumentId) {
    try {
      const csrfToken = await getCSRFToken();
      const formData = new FormData();
      formData.append('action', 'smazat');
      formData.append('dokument_id', dokumentId);
      formData.append('csrf_token', csrfToken);

      const response = await fetch('/api/documents_api.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.status === 'success') {
        wgsToast.success('Dokument smazan');
      } else {
        throw new Error(data.message || 'Chyba při mazání');
      }
    } catch (error) {
      logger.error('Chyba při mazání dokumentu:', error);
      wgsToast.error('Chyba: ' + error.message);
    }
  }

  // WGS Prompt dialog - podobný wgsConfirm ale s inputem
  function wgsPrompt(zprava, vychoziHodnota = '', options = {}) {
    return new Promise((resolve) => {
      const {
        titulek = 'Zadejte hodnotu',
        btnPotvrdit = 'Potvrdit',
        btnZrusit = 'Zrušit',
        placeholder = ''
      } = options;

      // Odstranit existující modal
      const existujici = document.getElementById('wgsPromptModal');
      if (existujici) existujici.remove();

      // Vytvořit overlay
      const modal = document.createElement('div');
      modal.id = 'wgsPromptModal';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7); display: flex;
        align-items: center; justify-content: center; z-index: 10011;
      `;

      // Vytvořit dialog HTML
      modal.innerHTML = `
        <div class="wgs-prompt-dialog" style="background: #1a1a1a; padding: 25px; border-radius: 12px;
                    max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                    border: 1px solid #333; text-align: center; font-family: 'Poppins', sans-serif;">
          <h3 style="margin: 0 0 15px 0; color: #fff; font-size: 1.1rem; font-weight: 600;">${escapeHtml(titulek)}</h3>
          <p style="margin: 0 0 15px 0; color: #ccc; font-size: 0.95rem; line-height: 1.5;">${escapeHtml(zprava)}</p>
          <input type="text" id="wgsPromptInput" value="${escapeHtml(vychoziHodnota)}" placeholder="${escapeHtml(placeholder)}"
                 style="width: 100%; padding: 12px; border: 1px solid #444; border-radius: 8px;
                        background: #222; color: #fff; font-size: 1rem; margin-bottom: 20px;
                        box-sizing: border-box; outline: none;"
                 onfocus="this.style.borderColor='#666'" onblur="this.style.borderColor='#444'">
          <div style="display: grid; gap: 10px;">
            <button type="button" class="wgs-prompt-btn-potvrdit" style="padding: 12px 24px; border: none;
                        background: #28a745; color: #fff; border-radius: 8px; cursor: pointer;
                        font-size: 0.9rem; font-weight: 500; transition: all 0.2s; width: 100%;">
              ${escapeHtml(btnPotvrdit)}
            </button>
            <button type="button" class="wgs-prompt-btn-zrusit" style="padding: 12px 24px; border: 1px solid #555;
                        background: transparent; color: #ccc; border-radius: 8px; cursor: pointer;
                        font-size: 0.9rem; font-weight: 500; transition: all 0.2s; width: 100%;">
              ${escapeHtml(btnZrusit)}
            </button>
          </div>
        </div>
      `;

      document.body.appendChild(modal);

      const inputEl = document.getElementById('wgsPromptInput');
      const btnPotvr = modal.querySelector('.wgs-prompt-btn-potvrdit');
      const btnZrus = modal.querySelector('.wgs-prompt-btn-zrusit');

      // Focus na input a vybrat text
      setTimeout(() => {
        inputEl.focus();
        inputEl.select();
      }, 50);

      // Zavřít a vrátit hodnotu
      const zavrit = (hodnota) => {
        modal.remove();
        resolve(hodnota);
      };

      btnPotvr.onclick = () => zavrit(inputEl.value);
      btnZrus.onclick = () => zavrit(null);

      // Enter pro potvrzení, Escape pro zrušení
      inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          zavrit(inputEl.value);
        } else if (e.key === 'Escape') {
          e.preventDefault();
          zavrit(null);
        }
      });

      // Klik mimo dialog zavře
      modal.addEventListener('click', (e) => {
        if (e.target === modal) zavrit(null);
      });
    });
  }

  // Dialog pro přejmenování dokumentu
  async function zobrazDialogPrejmenovat(dokumentId, stavajiciNazev, onUspech) {
    // Použít WGS prompt modal
    const novyNazev = await wgsPrompt('Zadejte nový název dokumentu:', stavajiciNazev, {
      titulek: 'Prejmenovat dokument',
      btnPotvrdit: 'Uložit',
      btnZrusit: 'Zrušit'
    });

    if (novyNazev === null) return; // Uživatel zrušil
    if (novyNazev.trim() === '') {
      wgsToast.error('Název nemůže být prázdný');
      return;
    }
    if (novyNazev.trim() === stavajiciNazev) return; // Beze změny

    try {
      const csrfToken = await getCSRFToken();
      const formData = new FormData();
      formData.append('action', 'prejmenovat');
      formData.append('dokument_id', dokumentId);
      formData.append('novy_nazev', novyNazev.trim());
      formData.append('csrf_token', csrfToken);

      const response = await fetch('/api/documents_api.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.status === 'success') {
        wgsToast.success('Dokument prejmenovan');
        if (onUspech) onUspech();
      } else {
        throw new Error(data.message || 'Chyba při přejmenování');
      }
    } catch (error) {
      logger.error('Chyba při přejmenování dokumentu:', error);
      wgsToast.error('Chyba: ' + error.message);
    }
  }

  // Spustit načítání
  nactiDokumenty();
}

/**
 * Zobrazí formulář pro nahrání interního PDF
 * @param {string} claimId - ID zakázky
 * @param {Function} onUspech - Callback po úspěšném nahrání
 */
function zobrazFormularNahraniPdf(claimId, onUspech) {
  const formOverlay = document.createElement('div');
  formOverlay.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.8); z-index: 10005;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
  `;

  const formBox = document.createElement('div');
  formBox.style.cssText = `
    background: #1a1a1a; border: 1px solid #333; border-radius: 12px;
    padding: 24px; max-width: 400px; width: 100%;
  `;

  formBox.innerHTML = `
    <h3 style="margin: 0 0 20px 0; color: #fff; font-size: 1.1rem;">Nahrat interni PDF</h3>

    <div style="margin-bottom: 16px;">
      <label style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 6px;">Nazev dokumentu</label>
      <input type="text" id="inputNazevDokumentu" placeholder="Napr. Faktura, Smlouva..." style="
        width: 100%; padding: 10px 12px; font-size: 0.95rem;
        background: #222; color: #fff; border: 1px solid #444;
        border-radius: 6px; box-sizing: border-box;
      ">
    </div>

    <div style="margin-bottom: 20px;">
      <label style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 6px;">PDF soubor</label>
      <input type="file" id="inputPdfSoubor" accept="application/pdf,.pdf" style="
        width: 100%; padding: 10px 12px; font-size: 0.9rem;
        background: #222; color: #fff; border: 1px solid #444;
        border-radius: 6px; box-sizing: border-box;
      ">
      <div style="font-size: 0.75rem; color: #666; margin-top: 4px;">Max. velikost: 10 MB</div>
    </div>

    <div style="display: flex; gap: 12px; justify-content: flex-end;">
      <button id="btnZrusitUpload" style="
        padding: 10px 20px; font-size: 0.9rem; font-weight: 600;
        background: #333; color: #fff; border: 1px solid #555;
        border-radius: 6px; cursor: pointer;
      ">Zrusit</button>
      <button id="btnPotvrditUpload" style="
        padding: 10px 20px; font-size: 0.9rem; font-weight: 600;
        background: #28a745; color: #fff; border: none;
        border-radius: 6px; cursor: pointer;
      ">Nahrat</button>
    </div>
  `;

  formOverlay.appendChild(formBox);

  // Event handlery
  const zavritForm = () => formOverlay.remove();

  formBox.querySelector('#btnZrusitUpload').onclick = zavritForm;
  formOverlay.onclick = (e) => {
    if (e.target === formOverlay) zavritForm();
  };

  formBox.querySelector('#btnPotvrditUpload').onclick = async () => {
    const nazev = formBox.querySelector('#inputNazevDokumentu').value.trim();
    const souborInput = formBox.querySelector('#inputPdfSoubor');
    const soubor = souborInput.files[0];

    if (!soubor) {
      wgsToast.error('Vyberte PDF soubor');
      return;
    }

    // Kontrola typu
    if (soubor.type !== 'application/pdf' && !soubor.name.toLowerCase().endsWith('.pdf')) {
      wgsToast.error('Povoleny jsou pouze PDF soubory');
      return;
    }

    // Kontrola velikosti
    if (soubor.size > 10 * 1024 * 1024) {
      wgsToast.error('Soubor je prilis velky (max 10 MB)');
      return;
    }

    try {
      const btnUpload = formBox.querySelector('#btnPotvrditUpload');
      btnUpload.textContent = 'Nahravam...';
      btnUpload.disabled = true;

      const csrfToken = await getCSRFToken();
      const formData = new FormData();
      formData.append('action', 'nahrat');
      formData.append('reklamace_id', claimId);
      formData.append('nazev', nazev || 'Interni dokument');
      formData.append('soubor', soubor);
      formData.append('csrf_token', csrfToken);

      const response = await fetch('/api/documents_api.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.status === 'success') {
        wgsToast.success('Dokument nahran');
        zavritForm();
        if (onUspech) onUspech();
      } else {
        throw new Error(data.message || 'Chyba při nahrávání');
      }
    } catch (error) {
      logger.error('Chyba při nahrávání dokumentu:', error);
      wgsToast.error('Chyba: ' + error.message);

      const btnUpload = formBox.querySelector('#btnPotvrditUpload');
      btnUpload.textContent = 'Nahrat';
      btnUpload.disabled = false;
    }
  };

  document.body.appendChild(formOverlay);

  // Focus na input
  setTimeout(() => {
    formBox.querySelector('#inputNazevDokumentu').focus();
  }, 100);
}

function showPhotoFullscreen(photoUrl) {
  const overlay = document.createElement('div');
  // z-index 10010 - vyšší než detailOverlay (10002)
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10010; display: flex; align-items: center; justify-content: center; cursor: pointer;';
  overlay.onclick = () => overlay.remove();

  const img = document.createElement('img');
  img.alt = 'Zvětšená fotka reklamace';
  img.src = photoUrl;
  img.style.cssText = 'max-width: 95%; max-height: 95%; object-fit: contain; border-radius: 4px;';

  // Zavřít klávesou Escape
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      overlay.remove();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

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
        wgsToast.success(t('text_saved_successfully'));
      } else {
        wgsToast.error(t('save_error') + ': ' + result.message);
      }
    } catch (error) {
      wgsToast.error(t('save_error') + ': ' + error.message);
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
    barva: document.getElementById('edit_barva').value,
    doplnujici_info: document.getElementById('edit_doplnujici_info').value,
    popis_problemu: document.getElementById('edit_popis_problemu').value
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

  // Data technika - VZDY ten, kdo provadi akci (prihlaseny uzivatel)
  // Pokud akci provadi technik/admin, jeho udaje jdou do emailu jako kontakt
  const aktualnUzivatel = typeof CURRENT_USER !== 'undefined' ? CURRENT_USER : {};
  const technikJmeno = aktualnUzivatel.name || customer.technik_jmeno || customer.assigned_technician || '';
  const technikEmail = aktualnUzivatel.email || customer.technik_email || '';
  const technikTelefon = aktualnUzivatel.phone || customer.technik_telefon || '';

  // Email prodejce (vytvořil zakázku) - BEZ FALLBACKU
  // Pokud neni nastaven, neposlat CC prodejci
  const prodejceEmail = customer.created_by_email || '';

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
          seller_email: prodejceEmail,
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

// === AUTOMATICKÉ PŘIŘAZENÍ TECHNIKA ===
async function autoAssignTechnician(reklamaceId) {
  try {
    const csrfToken = await getCSRFToken();

    const response = await fetch('api/auto_assign_technician.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        reklamace_id: reklamaceId,
        csrf_token: csrfToken
      })
    });

    const result = await response.json();

    if (result.success && result.assigned) {
      logger.log(`✓ Technik ${result.technician_name} (${result.technician_email}) byl automaticky přiřazen`);

      // Aktualizovat CURRENT_RECORD s daty technika
      if (CURRENT_RECORD) {
        CURRENT_RECORD.assigned_to = result.technician_id;
        CURRENT_RECORD.technik_jmeno = result.technician_name || '';
        CURRENT_RECORD.technik_email = result.technician_email || '';
        CURRENT_RECORD.technik_telefon = result.technician_phone || '';
      }

      // Aktualizovat cache
      const cacheRecord = WGS_DATA_CACHE.find(x => x.reklamace_id == reklamaceId || x.cislo == reklamaceId || x.id == reklamaceId);
      if (cacheRecord) {
        cacheRecord.assigned_to = result.technician_id;
        cacheRecord.technik_jmeno = result.technician_name || '';
        cacheRecord.technik_email = result.technician_email || '';
        cacheRecord.technik_telefon = result.technician_phone || '';
      }
    } else if (result.success && !result.assigned) {
      // Není technik nebo už má přiřazeného - to je v pořádku
      logger.log('Auto-assign: ' + (result.message || 'Žádné přiřazení'));
    } else {
      logger.warn('Auto-assign selhalo:', result.error);
    }
  } catch (error) {
    logger.error('Chyba při auto-assign technika:', error);
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

async function addNote(orderId, text, audioBlob = null) {
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

    // Pridat audio pokud existuje
    logger.log('[Audio] audioBlob status:', audioBlob ? 'existuje' : 'null', audioBlob ? audioBlob.size + ' bytes' : '');

    if (audioBlob) {
      // Urcit priponu podle MIME typu
      let ext = 'webm';
      if (audioBlob.type.includes('mp4')) ext = 'm4a';
      else if (audioBlob.type.includes('ogg')) ext = 'ogg';
      else if (audioBlob.type.includes('mp3') || audioBlob.type.includes('mpeg')) ext = 'mp3';
      else if (audioBlob.type.includes('wav')) ext = 'wav';

      formData.append('audio', audioBlob, `nahravka.${ext}`);
      logger.log('[Audio] Odesilam nahravku:', Math.round(audioBlob.size / 1024), 'KB, type:', audioBlob.type);
    }

    logger.log('[Notes] Odesilam poznamku na API...');
    const response = await fetch('api/notes_api.php', {
      method: 'POST',
      body: formData
    });

    logger.log('[Notes] API odpoved status:', response.status);
    const data = await response.json();
    logger.log('[Notes] API odpoved data:', JSON.stringify(data));

    if (data.status === 'success' || data.success === true) {
      return { success: true, note_id: data.note_id };
    } else {
      // PHP vraci 'error' ne 'message'
      throw new Error(data.error || data.message || 'Chyba pri pridavani poznamky');
    }
  } catch (e) {
    logger.error('Chyba pri pridavani poznamky:', e);
    throw e;
  }
}

async function deleteNote(noteId, orderId) {
  console.log('[deleteNote] Zacinam mazat poznamku ID:', noteId);

  if (!await wgsConfirm('Opravdu chcete smazat tuto poznámku?', 'Smazat', 'Zrušit')) {
    console.log('[deleteNote] Uzivatel zrusil');
    return;
  }

  console.log('[deleteNote] Uzivatel potvrdil, pripravuji request...');

  try {
    const csrfToken = await getCSRFToken();
    console.log('[deleteNote] CSRF token:', csrfToken ? 'OK (' + csrfToken.substring(0, 10) + '...)' : 'CHYBI!');

    // FIX: Pouzit URLSearchParams misto FormData - spolehlivejsi pro Safari
    const params = new URLSearchParams();
    params.append('action', 'delete');
    params.append('note_id', noteId);
    params.append('csrf_token', csrfToken);

    console.log('[deleteNote] Params pripraveny:', params.toString().substring(0, 50) + '...');
    console.log('[deleteNote] Odesilam POST na /api/notes_api.php...');

    const response = await fetch('/api/notes_api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: params
    });

    console.log('[deleteNote] Response status:', response.status, response.statusText);

    const data = await response.json();

    if (data.status === 'success') {
      // Odstranit poznamku z DOM
      const noteElement = document.querySelector(`[data-note-id="${noteId}"]`);
      if (noteElement) {
        noteElement.remove();
      }

      // Zkontrolovat zda jsou jeste nejake poznamky
      const notesContainer = document.querySelector('.notes-container');
      if (notesContainer && notesContainer.querySelectorAll('.note-item').length === 0) {
        notesContainer.innerHTML = '<div class="empty-notes">Zatim zadne poznamky</div>';
      }

      await loadAll();
    } else {
      wgsToast.error('Chyba: ' + (data.error || data.message || 'Neznama chyba'));
    }
  } catch (e) {
    logger.error('Chyba pri mazani poznamky:', e);
    wgsToast.error('Chyba pri mazani poznamky: ' + e.message);
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
      wgsToast.error(t('record_not_found'));
      return;
    }
  } else {
    record = recordOrId;
  }

  CURRENT_RECORD = record;

  const loadingContent = `
    ${createCustomerHeader()}
    <div class="modal-body" style="text-align: center; padding: 3rem;">
      <div class="loading">Načítání poznámek...</div>
    </div>
  `;
  ModalManager.show(loadingContent);

  const notes = await getNotes(record.id);

  notes.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

  const content = `
    ${createCustomerHeader()}

    <div class="modal-body">
      <div class="notes-container">
        ${notes.length > 0
          ? notes.map(note => {
              const canDelete = CURRENT_USER && (CURRENT_USER.is_admin || note.author === CURRENT_USER.email);
              const hasAudio = note.has_audio && note.audio_url;
              const isVoiceNote = note.text === '[Hlasová poznámka]' || note.text === '[Hlasova poznamka]';
              return `
              <div class="note-item ${note.read ? '' : 'unread'} ${hasAudio ? 'has-audio' : ''}" data-note-id="${note.id}">
                <div class="note-header">
                  <span class="note-author">${note.author_name || note.author}</span>
                  <span class="note-time">${formatDateTime(note.timestamp)}</span>
                  ${canDelete ? `<button class="note-delete-btn" data-note-id="${note.id}" data-order-id="${record.id}" onclick="event.stopPropagation(); potvrditSmazaniPoznamky(this);" title="Smazat poznamku">x</button>` : ''}
                </div>
                ${!isVoiceNote ? `<div class="note-text">${Utils.escapeHtml(note.text)}</div>` : ''}
                ${hasAudio ? `
                <div class="note-audio">
                  <audio controls preload="metadata" class="note-audio-player">
                    <source src="${note.audio_url}" type="audio/mp4">
                    <source src="${note.audio_url}" type="audio/webm">
                    <source src="${note.audio_url}" type="audio/mpeg">
                    Vas prohlizec nepodporuje prehravani audia.
                  </audio>
                </div>
                ` : ''}
              </div>
            `;
            }).join('')
          : '<div class="empty-notes">Zatim zadne poznamky</div>'
        }
      </div>

      <div class="note-input-area">
        <textarea
          class="note-textarea"
          id="newNoteText"
          placeholder="Napiste poznamku..."
        ></textarea>
        <div class="note-input-controls">
          <button type="button" class="btn-record" id="btnStartRecord" data-action="startRecording" data-id="${record.id}" title="Nahrat hlasovou zpravu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
              <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
            </svg>
          </button>
          <div class="recording-indicator" id="recordingIndicator">
            <span class="recording-dot"></span>
            <span class="recording-time" id="recordingTime">0:00</span>
            <button type="button" class="btn-stop-record" id="btnStopRecord" data-action="stopRecording" data-id="${record.id}">Stop</button>
          </div>
          <div class="audio-preview hidden" id="audioPreview">
            <audio id="audioPreviewPlayer" controls></audio>
            <button type="button" class="btn-delete-audio" id="btnDeleteAudio" data-action="deleteAudioPreview" title="Smazat nahravku">x</button>
          </div>
        </div>
      </div>
    </div>

    <div class="detail-buttons">
      <button class="detail-btn detail-btn-primary" data-action="saveNewNote" data-id="${record.id}">Pridat poznamku</button>
      <button class="detail-btn detail-btn-secondary" data-action="closeNotesModal">Zavrit</button>
    </div>
  `;

  ModalManager.show(content);

  // Pridat error handling pro vsechny audio prehravace
  setTimeout(() => {
    const audioPlayers = document.querySelectorAll('.note-audio-player');
    audioPlayers.forEach(audio => {
      audio.onerror = function() {
        logger.log('[Audio] Chyba pri nacitani ulozene nahravky');
        // Nahradit audio element chybovou zpravou
        const parent = audio.closest('.note-audio');
        if (parent) {
          parent.innerHTML = '<span style="color: var(--c-grey); font-size: 0.75rem;">Audio nelze nacist</span>';
        }
      };
    });
  }, 100);

  setTimeout(async () => {
    await markNotesAsRead(record.id);
    await loadAll();
    // Aktualizovat badge na ikone PWA
    if (window.WGSNotifikace) {
      window.WGSNotifikace.aktualizovat();
    }
  }, 1000);
}

async function saveNewNote(orderId) {
  const textarea = document.getElementById('newNoteText');
  const text = textarea.value.trim();
  const audioBlob = window.wgsAudioRecorder ? window.wgsAudioRecorder.audioBlob : null;

  // Musi byt text NEBO audio
  if (!text && !audioBlob) {
    wgsToast.warning(t('write_note_text'));
    return;
  }

  try {
    await addNote(orderId, text, audioBlob);

    // Vycistit audio recorder
    if (window.wgsAudioRecorder) {
      window.wgsAudioRecorder.audioBlob = null;
      window.wgsAudioRecorder.audioChunks = [];
    }

    // Zavrit modal po uspesnem pridani poznamky
    closeNotesModal();

    await loadAll();

    // Aktualizovat badge na ikone PWA (nova poznamka)
    if (window.WGSNotifikace) {
      window.WGSNotifikace.aktualizovat();
    }
  } catch (e) {
    wgsToast.error(t('note_save_error') + ': ' + e.message);
  }
}

function closeNotesModal() {
  // Zastavit nahravani pokud probiha
  if (window.wgsAudioRecorder && window.wgsAudioRecorder.isRecording) {
    stopRecording();
  }
  // Uvolnit mikrofon (kdyby zustal aktivni)
  if (typeof releaseMicrophone === 'function') {
    releaseMicrophone();
  }
  closeDetail();
  renderOrders();
}

// ========================================
// AUDIO NAHRAVANI - Hlasove poznamky
// ========================================
window.wgsAudioRecorder = {
  mediaRecorder: null,
  audioChunks: [],
  audioBlob: null,
  isRecording: false,
  recordingStartTime: null,
  recordingTimer: null,
  permissionGranted: false, // Zapamatovat ze bylo povoleno
  stream: null // Ulozit stream pro pozdejsi zastaveni
};

// Zkontrolovat stav opravneni mikrofonu
async function checkMicrophonePermission() {
  try {
    // Pouzit Permissions API pokud je k dispozici
    if (navigator.permissions && navigator.permissions.query) {
      const result = await navigator.permissions.query({ name: 'microphone' });
      logger.log('[Audio] Stav opravneni mikrofonu:', result.state);

      if (result.state === 'granted') {
        window.wgsAudioRecorder.permissionGranted = true;
        return 'granted';
      } else if (result.state === 'denied') {
        return 'denied';
      }
      return 'prompt'; // Jeste se nezeptalo
    }
  } catch (e) {
    // Permissions API neni podporovano (napr. Safari)
    logger.log('[Audio] Permissions API neni podporovano, zkusim primo');
  }
  return 'unknown';
}

async function startRecording(orderId) {
  logger.log('[Audio] Spoustim nahravani...');

  try {
    // Zkontrolovat podporu
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error('Vas prohlizec nepodporuje nahravani zvuku');
    }

    // Zkontrolovat stav opravneni
    const permissionState = await checkMicrophonePermission();

    if (permissionState === 'denied') {
      throw new Error('Pristup k mikrofonu byl trvale odepren. Povolte ho v nastaveni prohlizece.');
    }

    // Pokud jeste nebylo povoleno, zobrazit vysvetleni (jen poprve)
    if (!window.wgsAudioRecorder.permissionGranted && permissionState !== 'granted') {
      // Ulozit do localStorage ze jsme uz vysvetleni zobrazili
      const explanationShown = localStorage.getItem('wgs_mic_explained');
      if (!explanationShown) {
        wgsToast.info('Pro nahravani hlasovych poznamek potrebujeme pristup k mikrofonu. Po kliknuti na OK vas prohlizec pozada o povoleni.');
        localStorage.setItem('wgs_mic_explained', '1');
      }
    }

    // Pozadat o pristup k mikrofonu
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

    // Ulozit stream pro pozdejsi zastaveni
    window.wgsAudioRecorder.stream = stream;

    // Zapamatovat ze bylo povoleno
    window.wgsAudioRecorder.permissionGranted = true;

    // Vybrat podporovany format
    // Safari/iOS: preferovat MP4 (WebM nefunguje pri prehravani)
    // Chrome/Firefox: preferovat WebM (lepsi komprese)
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent) ||
                     /iPad|iPhone|iPod/.test(navigator.userAgent);

    let mimeType = 'audio/webm';

    if (isSafari) {
      // Safari/iOS - pouzit MP4 (jediny spolehlivy format)
      if (MediaRecorder.isTypeSupported('audio/mp4')) {
        mimeType = 'audio/mp4';
      } else if (MediaRecorder.isTypeSupported('audio/aac')) {
        mimeType = 'audio/aac';
      }
      // Fallback na cokoliv co funguje
      logger.log('[Audio] Safari detekovan, preferuji MP4');
    } else {
      // Chrome/Firefox - pouzit WebM (lepsi komprese)
      if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
        mimeType = 'audio/webm;codecs=opus';
      } else if (MediaRecorder.isTypeSupported('audio/webm')) {
        mimeType = 'audio/webm';
      } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
        mimeType = 'audio/mp4';
      } else if (MediaRecorder.isTypeSupported('audio/ogg')) {
        mimeType = 'audio/ogg';
      }
    }

    logger.log('[Audio] Pouzivam format:', mimeType);

    const recorder = window.wgsAudioRecorder;
    recorder.mimeType = mimeType; // Ulozit pro pouziti v onstop
    recorder.mediaRecorder = new MediaRecorder(stream, { mimeType });
    recorder.audioChunks = [];
    recorder.isRecording = true;
    recorder.recordingStartTime = Date.now();

    // Sbírat data
    recorder.mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) {
        recorder.audioChunks.push(e.data);
        logger.log('[Audio] Data chunk:', e.data.size, 'bytes');
      }
    };

    // Po ukonceni nahravani
    recorder.mediaRecorder.onstop = () => {
      logger.log('[Audio] Nahravani ukonceno, chunks:', recorder.audioChunks.length);

      if (recorder.audioChunks.length === 0) {
        logger.error('[Audio] Zadna data nebyla nahrana');
        wgsToast.warning('Nahravka je prazdna. Zkuste to prosim znovu.');
        document.getElementById('btnStartRecord').classList.remove('hidden');
        document.getElementById('recordingIndicator').classList.remove('active');
        return;
      }

      // Pouzit ulozeny mimeType
      const blobType = recorder.mimeType || 'audio/webm';
      recorder.audioBlob = new Blob(recorder.audioChunks, { type: blobType });
      recorder.isRecording = false;

      logger.log('[Audio] Blob vytvoren:', recorder.audioBlob.size, 'bytes, type:', blobType);

      // Uvolnit mikrofon
      releaseMicrophone();

      // Zobrazit nahled
      showAudioPreview(recorder.audioBlob);
    };

    // Spustit nahravani s timeslice 1000ms
    // Timeslice zajisti ze ondataavailable se vola kazdou sekundu
    // To je dulezite pro mobilni prohlizece/PWA kde bez timeslice muze byt nespolehlivy
    recorder.mediaRecorder.start(1000);

    // Aktualizovat UI
    document.getElementById('btnStartRecord').classList.add('hidden');
    document.getElementById('recordingIndicator').classList.add('active');

    // Casovac
    recorder.recordingTimer = setInterval(() => {
      const elapsed = Math.floor((Date.now() - recorder.recordingStartTime) / 1000);
      const mins = Math.floor(elapsed / 60);
      const secs = elapsed % 60;
      document.getElementById('recordingTime').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
    }, 1000);

    logger.log('[Audio] Nahravani spusteno');

  } catch (err) {
    logger.error('[Audio] Chyba pri nahravani:', err);

    // Uvolnit prostredky pri chybe (dulezite pro iOS PWA)
    releaseMicrophone();

    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
      wgsToast.error('Pristup k mikrofonu byl odepren. Povolte pristup v nastaveni prohlizece.');
    } else {
      wgsToast.error('Chyba pri nahravani: ' + err.message);
    }
  }
}

function stopRecording() {
  logger.log('[Audio] Zastavuji nahravani...');

  const recorder = window.wgsAudioRecorder;

  // Zastavit casovac hned
  if (recorder.recordingTimer) {
    clearInterval(recorder.recordingTimer);
    recorder.recordingTimer = null;
  }

  if (recorder.mediaRecorder && recorder.isRecording) {
    // Vyzadat posledni data pred zastavenim (dulezite pro mobilni prohlizece)
    if (recorder.mediaRecorder.state === 'recording') {
      try {
        recorder.mediaRecorder.requestData();
        // FIX: Pridat male zpozdeni aby data stihla dorazit pred stop()
        // Na nekterych prohlizecich (Safari/iOS) requestData() je asynchronni
        // a data dorazila az po stop(), coz vedlo k prazdnym nahrávkam
        setTimeout(() => {
          if (recorder.mediaRecorder && recorder.mediaRecorder.state === 'recording') {
            recorder.mediaRecorder.stop();
            logger.log('[Audio] MediaRecorder zastaven po requestData zpozdeni');
          }
        }, 150);
      } catch (e) {
        logger.log('[Audio] requestData neni podporovano:', e.message);
        // Fallback - zavolat stop() primo
        recorder.mediaRecorder.stop();
      }
    } else if (recorder.mediaRecorder.state !== 'inactive') {
      recorder.mediaRecorder.stop();
    }
  }

  // Aktualizovat UI - skryt recording indicator
  // Pozn: tlacitko startRecord se ukaze az v onstop handleru po zpracovani dat
  const recordingIndicator = document.getElementById('recordingIndicator');
  if (recordingIndicator) recordingIndicator.classList.remove('active');
}

// Uvolnit mikrofon - zastavit stream
// Dulezite pro iOS PWA - bez kompletniho uvolneni nahravani funguje jen jednou
function releaseMicrophone() {
  const recorder = window.wgsAudioRecorder;

  // Zastavit vsechny tracky streamu
  if (recorder.stream) {
    recorder.stream.getTracks().forEach(track => {
      track.stop();
      logger.log('[Audio] Track zastaven:', track.kind);
    });
    recorder.stream = null;
  }

  // Reset MediaRecorder (dulezite pro iOS PWA)
  if (recorder.mediaRecorder) {
    recorder.mediaRecorder = null;
  }

  // Reset stavu
  recorder.isRecording = false;
  recorder.audioChunks = [];

  logger.log('[Audio] Mikrofon a MediaRecorder uvolneny');
}

function showAudioPreview(audioBlob) {
  const audioUrl = URL.createObjectURL(audioBlob);
  const previewPlayer = document.getElementById('audioPreviewPlayer');
  const previewContainer = document.getElementById('audioPreview');

  // Odstranit predchozi error handlery
  previewPlayer.onerror = null;
  previewPlayer.oncanplay = null;

  // Flag aby se error zobrazil jen jednou
  let errorShown = false;

  // Pridat error handler
  previewPlayer.onerror = function(e) {
    if (errorShown) return; // Zabranit opakovanemu zobrazeni
    errorShown = true;

    logger.log('[Audio] Chyba pri nacitani nahravky:', e);
    // Skryt preview a zobrazit tlacitko pro nahravani
    previewContainer.classList.add('hidden');
    document.getElementById('btnStartRecord').classList.remove('hidden');

    // Uvolnit blob URL
    if (previewPlayer.src) {
      URL.revokeObjectURL(previewPlayer.src);
      previewPlayer.src = '';
    }

    // Zobrazit info v console misto alertu
    logger.error('[Audio] Nahravka se nepodarila nacist');
  };

  previewPlayer.src = audioUrl;
  previewContainer.classList.remove('hidden');

  logger.log('[Audio] Nahled zobrazen, velikost:', Math.round(audioBlob.size / 1024), 'KB');
}

function deleteAudioPreview() {
  const recorder = window.wgsAudioRecorder;
  recorder.audioBlob = null;
  recorder.audioChunks = [];

  const previewPlayer = document.getElementById('audioPreviewPlayer');
  const previewContainer = document.getElementById('audioPreview');

  if (previewPlayer.src) {
    URL.revokeObjectURL(previewPlayer.src);
    previewPlayer.src = '';
  }

  previewContainer.classList.add('hidden');
  document.getElementById('btnStartRecord').classList.remove('hidden');

  logger.log('[Audio] Nahled smazan');
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

  // Zkracene nazvy dnu v tydnu (cesky)
  const dny = ['ne', 'po', 'ut', 'st', 'ct', 'pa', 'so'];
  const den = dny[date.getDay()];
  const datum = date.getDate();
  const mesic = date.getMonth() + 1;
  const rok = date.getFullYear();
  const hodiny = date.getHours().toString().padStart(2, '0');
  const minuty = date.getMinutes().toString().padStart(2, '0');

  return `${den} ${datum}.${mesic}.${rok} ${hodiny}:${minuty}`;
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

  // Zkracene nazvy dnu v tydnu (cesky)
  const dny = ['ne', 'po', 'ut', 'st', 'ct', 'pa', 'so'];
  const den = dny[date.getDay()];
  const datum = date.getDate();
  const mesic = date.getMonth() + 1;
  const rok = date.getFullYear();

  return `${den} ${datum}.${mesic}.${rok}`;
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
// REMOVED: Mrtvý kód - menu je nyní centrálně v hamburger-menu.php

// DUPLICITNÍ EVENT DELEGATION ODSTRANĚN
// Používá se hlavní event delegation na řádku 2587
// Ponecháme pouze data-navigate a data-onchange handlers

document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.getAttribute('data-action');

    // Ignorovat akce zpracované EMERGENCY event listenerem v seznam.php
    const emergencyActions = [
      'openPDF', 'openKnihovnaPDF', 'startVisit', 'showCalendar', 'vytvorCenovouNabidku',
      'showContactMenu', 'showCustomerDetail', 'closeDetail', 'deleteReklamace',
      'showDetailById', 'showDetail', 'showNotes', 'closeNotesModal', 'deleteNote',
      'saveNewNote', 'showHistoryPDF', 'showVideoteka', 'saveSelectedDate', 'showQrPlatbaModal',
      'previousMonth', 'nextMonth', 'showBookingDetail', 'showCalendarBack',
      'openCalendarFromDetail', 'sendContactAttemptEmail', 'showPhotoFullscreen',
      'smazatFotku', 'saveAllCustomerData', 'startRecording', 'stopRecording',
      'deleteAudioPreview', 'closeErrorModal', 'filterUnreadNotes', 'otevritVyberFotek'
    ];
    if (emergencyActions.includes(action)) {
      return;  // Nechat zpracovat EMERGENCY event listener
    }

    if (action === 'reload') {
      location.reload();
      return;
    }

    if (typeof window[action] === 'function') {
      const id = target.getAttribute('data-id');
      window[action](id);
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
    // Handler pro změnu stavu zakázky (admin dropdown)
    if (e.target.id === 'zmenaStavuSelect') {
      const novyStav = e.target.value;
      const reklamaceId = e.target.getAttribute('data-id');
      const zakaznikEmail = e.target.getAttribute('data-email');
      zmenitStavZakazky(reklamaceId, novyStav, zakaznikEmail);
      return;
    }

    const target = e.target.closest('[data-onchange]');
    if (!target) return;

    const action = target.getAttribute('data-onchange');
    const value = target.getAttribute('data-onchange-value') || target.value;

    if (typeof window[action] === 'function') {
      window[action](value);
    }
  });
});

// === ADMIN: ZMĚNA STAVU ZAKÁZKY ===
async function zmenitStavZakazky(reklamaceId, novyStav, zakaznikEmail) {
  console.log('[Admin] zmenitStavZakazky voláno:', { reklamaceId, novyStav, zakaznikEmail });

  if (!reklamaceId || !novyStav) {
    console.error('[Admin] Chybí povinné parametry:', { reklamaceId, novyStav });
    wgsToast.error('Chybí ID nebo nový stav');
    return;
  }

  // Mapování pro zobrazení
  const stavyMap = {
    'wait': 'NOVÁ',
    'open': 'DOMLUVENÁ',
    'done': 'HOTOVO',
    'cn_poslana': 'Poslána CN',
    'cn_odsouhlasena': 'Odsouhlasena',
    'cn_cekame_nd': 'Čekáme ND'
  };

  // Rozpoznat CN stavy
  const jeCnStav = novyStav.startsWith('cn_');

  try {
    logger.log(`[Admin] Měním stav zakázky ${reklamaceId} na ${novyStav}` + (jeCnStav ? ` (CN workflow)` : ''));

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value ||
                      document.querySelector('meta[name="csrf-token"]')?.content;

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('id', reklamaceId);
    formData.append('stav', novyStav);
    if (zakaznikEmail) {
      formData.append('email', zakaznikEmail);
    }

    const response = await fetch('/api/zmenit_stav.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.status === 'success') {
      // Aktualizovat lokální cache
      const record = WGS_DATA_CACHE.find(r => r.id == reklamaceId);
      if (record && data.db_stav) {
        record.stav = data.db_stav;
      }

      // Aktualizovat CURRENT_RECORD
      if (CURRENT_RECORD && CURRENT_RECORD.id == reklamaceId && data.db_stav) {
        CURRENT_RECORD.stav = data.db_stav;
      }

      // Aktualizovat CN cache pokud se změnil CN stav
      if (data.cn_stav && zakaznikEmail) {
        const emailLower = zakaznikEmail.toLowerCase();
        if (STAVY_NABIDEK) {
          STAVY_NABIDEK[emailLower] = data.cn_stav;
        }
        // Přidat email do EMAILS_S_CN pokud tam není
        if (EMAILS_S_CN && !EMAILS_S_CN.includes(emailLower) && data.cn_stav) {
          EMAILS_S_CN.push(emailLower);
        }
      }

      wgsToast.success(`Stav změněn na: ${stavyMap[novyStav] || novyStav}`);

      // Překreslit seznam (karty)
      renderOrders(WGS_DATA_CACHE);

      logger.log(`[Admin] Stav zakázky ${reklamaceId} změněn na ${novyStav}`);
    } else {
      wgsToast.error(data.message || 'Nepodařilo se změnit stav');
    }
  } catch (error) {
    logger.error('[Admin] Chyba při změně stavu:', error);
    wgsToast.error('Chyba při změně stavu: ' + error.message);
  }
}

// === POMOCNÉ FUNKCE PRO DELETE MODALY ===
function showDeleteConfirmModal(reklamaceNumber) {
  return new Promise((resolve) => {
    const modalDiv = document.createElement('div');
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:10003;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:#1a1a1a;padding:25px;border-radius:12px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);border:1px solid #333;';

    modalContent.innerHTML = `
      <h3 style="margin:0 0 15px 0;color:#fff;font-size:1.1rem;font-weight:600;">Smazat reklamaci?</h3>
      <p style="margin:0 0 15px 0;color:#ccc;line-height:1.5;font-size:0.95rem;">
        Opravdu chcete <strong style="color:#fff;">TRVALE SMAZAT</strong> reklamaci<br>
        <strong style="color:#fff;font-size:1rem;">${reklamaceNumber}</strong>?
      </p>
      <p style="margin:0 0 20px 0;color:#999;font-size:0.85rem;">
        Tato akce smaže VŠE včetně fotek a PDF!<br>
        Tuto akci NELZE vrátit zpět!
      </p>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button id="deleteConfirmNo" style="padding:10px 20px;background:transparent;color:#ccc;border:1px solid #444;border-radius:6px;cursor:pointer;font-size:0.9rem;">
          Zrušit
        </button>
        <button id="deleteConfirmYes" style="padding:10px 20px;background:#dc3545;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:500;">
          Smazat
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
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:10003;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:#1a1a1a;padding:25px;border-radius:12px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);border:1px solid #333;';

    modalContent.innerHTML = `
      <h3 style="margin:0 0 15px 0;color:#fff;font-size:1.1rem;font-weight:600;">Poslední ověření</h3>
      <p style="margin:0 0 15px 0;color:#ccc;line-height:1.5;font-size:0.95rem;">
        Pro potvrzení smazání zadejte přesně číslo reklamace:
      </p>
      <p style="margin:0 0 15px 0;color:#fff;font-size:1rem;font-weight:600;">
        ${reklamaceNumber}
      </p>
      <input type="text" id="deleteInputField"
             placeholder="Zadejte číslo reklamace"
             style="width:100%;padding:10px;background:#252525;border:1px solid #444;border-radius:6px;font-size:0.9rem;text-align:center;margin-bottom:20px;color:#fff;box-sizing:border-box;">
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button id="deleteInputCancel" style="padding:10px 20px;background:transparent;color:#ccc;border:1px solid #444;border-radius:6px;cursor:pointer;font-size:0.9rem;">
          Zrušit
        </button>
        <button id="deleteInputConfirm" style="padding:10px 20px;background:#dc3545;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:500;">
          Smazat
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
    errorModal.id = 'errorModal';
    errorModal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:10003;display:flex;align-items:center;justify-content:center;';
    errorModal.innerHTML = `
      <div style="background:#1a1a1a;padding:25px;border-radius:12px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);border:1px solid #333;">
        <h3 style="margin:0 0 15px 0;color:#fff;font-size:1.1rem;font-weight:600;">Nesprávné číslo!</h3>
        <p style="margin:0 0 20px 0;color:#ccc;font-size:0.95rem;line-height:1.5;">Zadali jste nesprávné číslo reklamace.<br>Mazání bylo zrušeno.</p>
        <button onclick="document.getElementById('errorModal').remove();" style="padding:10px 20px;background:#fff;color:#000;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:500;">
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
      wgsToast.success(t('claim_deleted_successfully'));
      closeDetail();
      setTimeout(() => location.reload(), 500);
    } else {
      const errorMsg = result.message || result.error || t('delete_failed');
      logger.error('Chyba:', errorMsg);
      wgsToast.error(t('error') + ': ' + errorMsg);
    }
  } catch (error) {
    logger.error('Chyba při mazání:', error);
    wgsToast.error(t('delete_error') + ': ' + error.message);
  }
}

// === SMAZÁNÍ JEDNOTLIVÉ FOTKY ===
async function smazatFotku(photoId, photoUrl) {
  logger.log('[smazatFotku] Vytvářím confirmation modal pro ID:', photoId);

  // Vlastní confirmation modal (viditelný nad vším)
  return new Promise((resolve) => {
    const modalDiv = document.createElement('div');
    modalDiv.id = 'deleteFotoModal';
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:10003;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:#1a1a1a;padding:25px;border-radius:12px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);border:1px solid #333;';

    modalContent.innerHTML = `
      <h3 style="margin:0 0 15px 0;color:#fff;font-size:1.1rem;font-weight:600;">Smazat fotku?</h3>
      <p style="margin:0 0 20px 0;color:#ccc;line-height:1.5;font-size:0.95rem;">
        Opravdu chcete smazat tuto fotografii?<br>
        <strong style="color:#999;">Tato akce je nevratná!</strong>
      </p>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button id="deleteFotoNo" style="padding:10px 20px;background:transparent;color:#ccc;border:1px solid #444;border-radius:6px;cursor:pointer;font-size:0.9rem;">
          Zrušit
        </button>
        <button id="deleteFotoYes" style="padding:10px 20px;background:#dc3545;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:500;">
          Smazat
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

      wgsToast.success(t('photo_deleted_successfully'));
    } else {
      const errorMsg = result.message || result.error || t('delete_failed');
      logger.error('Chyba:', errorMsg);
      wgsToast.error(t('error') + ': ' + errorMsg);
    }
  } catch (error) {
    logger.error('Chyba při mazání fotky:', error);
    wgsToast.error(t('photo_delete_error') + ': ' + error.message);
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

// === FOTOTEKA - NAHRANI FOTEK Z DETAILU ZAKAZNIKA ===

/**
 * Otevre dialog pro vyber fotek
 * @param {string} reklamaceId - ID reklamace
 */
function otevritVyberFotek(reklamaceId) {
  logger.log('[Fototeka] Otviram vyber fotek pro reklamaci:', reklamaceId);
  const input = document.getElementById('fototeka-input-' + reklamaceId);
  if (input) {
    input.click();
  } else {
    logger.error('[Fototeka] Input element nenalezen');
  }
}

/**
 * Zpracuje vybrane fotky a nahraje je na server
 * @param {Event} event - Change event z input file
 */
async function zpracujVybraneFotky(event) {
  const input = event.target;
  const reklamaceId = input.getAttribute('data-reklamace-id');
  const soubory = input.files;

  if (!soubory || soubory.length === 0) {
    logger.log('[Fototeka] Zadne soubory vybrane');
    return;
  }

  logger.log('[Fototeka] Vybrano souboru:', soubory.length, 'pro reklamaci:', reklamaceId);

  // Zobrazit progress bar
  const nahravaniDiv = document.getElementById('fototeka-nahravani');
  const progressBar = document.getElementById('fototeka-progress');
  if (nahravaniDiv) nahravaniDiv.style.display = 'block';
  if (progressBar) progressBar.style.width = '0%';

  try {
    // Konvertovat soubory na base64
    const fotkyBase64 = [];
    for (let i = 0; i < soubory.length; i++) {
      const soubor = soubory[i];

      // Kontrola typu souboru
      if (!soubor.type.startsWith('image/')) {
        logger.warn('[Fototeka] Preskakuji neobrazovy soubor:', soubor.name);
        continue;
      }

      // Kontrola velikosti (max 15MB pred kompresi)
      if (soubor.size > 15 * 1024 * 1024) {
        wgsToast.error('Soubor ' + soubor.name + ' je prilis velky (max 15MB)');
        continue;
      }

      // Komprimovat obrazek pred nahranim
      const komprimovany = await komprimujObrazek(soubor, 1200, 0.3);
      const base64 = await souborNaBase64(komprimovany);

      fotkyBase64.push({
        type: 'image',
        data: base64,
        size: komprimovany.size
      });

      // Aktualizovat progress
      if (progressBar) {
        const progress = Math.round(((i + 1) / soubory.length) * 50);
        progressBar.style.width = progress + '%';
      }
    }

    if (fotkyBase64.length === 0) {
      throw new Error('Zadne platne fotky k nahrani');
    }

    // Odeslat na server
    const csrfToken = await getCSRFToken();
    const response = await fetch('/app/save_photos.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        reklamace_id: reklamaceId,
        sections: { customer_detail: fotkyBase64 },
        csrf_token: csrfToken
      })
    });

    if (progressBar) progressBar.style.width = '80%';

    const vysledek = await response.json();

    if (!response.ok || !vysledek.success) {
      throw new Error(vysledek.error || 'Chyba pri nahravani');
    }

    if (progressBar) progressBar.style.width = '100%';

    logger.log('[Fototeka] Nahrano fotek:', vysledek.count);
    wgsToast.success('Nahrano ' + vysledek.count + ' fotek');

    // Aktualizovat grid s fotkami
    await aktualizujFototekaGrid(reklamaceId);

    // Reset inputu
    input.value = '';

  } catch (error) {
    logger.error('[Fototeka] Chyba pri nahravani:', error);
    wgsToast.error('Chyba: ' + error.message);
  } finally {
    // Skryt progress bar po chvili
    setTimeout(() => {
      if (nahravaniDiv) nahravaniDiv.style.display = 'none';
      if (progressBar) progressBar.style.width = '0%';
    }, 1000);
  }
}

/**
 * Prevede soubor na base64
 * @param {File|Blob} soubor - Soubor k prevodu
 * @returns {Promise<string>} - Base64 string
 */
function souborNaBase64(soubor) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = (error) => reject(error);
    reader.readAsDataURL(soubor);
  });
}

/**
 * Komprimuje obrazek na max velikost a kvalitu
 * @param {File} soubor - Obrazkovy soubor
 * @param {number} maxSirka - Maximalni sirka (default 1200px)
 * @param {number} maxMB - Maximalni velikost v MB (default 0.3)
 * @returns {Promise<Blob>} - Komprimovany blob
 */
function komprimujObrazek(soubor, maxSirka = 1200, maxMB = 0.3) {
  return new Promise((resolve) => {
    const reader = new FileReader();

    reader.onload = (e) => {
      const img = new Image();

      img.onload = async () => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Zachovat pomer stran
        const scale = Math.min(1, maxSirka / Math.max(img.width, img.height));
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;

        // Nakreslit obrazek
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        // Komprimovat s postupne snizovanou kvalitou
        let kvalita = 0.7;
        let blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', kvalita));

        while (blob.size > maxMB * 1024 * 1024 && kvalita > 0.3) {
          kvalita -= 0.05;
          blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', kvalita));
        }

        const puvodniKB = Math.round(soubor.size / 1024);
        const novaKB = Math.round(blob.size / 1024);
        logger.log(`[Fototeka] Komprese: ${puvodniKB} KB -> ${novaKB} KB (kvalita ${Math.round(kvalita * 100)}%)`);

        resolve(blob);
      };

      img.onerror = () => {
        // Fallback - vratit original
        logger.warn('[Fototeka] Nelze nacist obrazek pro kompresi, vracim original');
        resolve(soubor);
      };

      img.src = e.target.result;
    };

    reader.onerror = () => resolve(soubor);
    reader.readAsDataURL(soubor);
  });
}

/**
 * Aktualizuje grid fotek v fototece
 * @param {string} reklamaceId - ID reklamace
 */
async function aktualizujFototekaGrid(reklamaceId) {
  try {
    const fotky = await loadPhotosFromDB(reklamaceId);
    const grid = document.getElementById('fototeka-grid');
    const nadpis = document.getElementById('fototeka-nadpis');

    if (!grid) return;

    // Aktualizovat nadpis
    if (nadpis) {
      nadpis.textContent = 'Fototeka (' + fotky.length + ')';
    }

    // Aktualizovat grid
    if (fotky.length > 0) {
      grid.innerHTML = fotky.map((f, i) => {
        const photoPath = typeof f === 'object' ? f.photo_path : f;
        const photoId = typeof f === 'object' ? f.id : null;
        const escapedUrl = photoPath.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");

        return `
          <div class="foto-wrapper" style="position: relative; width: 60px; height: 60px; flex-shrink: 0;">
            <img src='${photoPath}'
                 style='width: 60px; height: 60px; object-fit: cover; border: 1px solid #444; cursor: pointer; border-radius: 4px;'
                 alt='Fotka ${i+1}'
                 data-action="showPhotoFullscreen"
                 data-url="${escapedUrl}">
            ${photoId ? `
              <button class="foto-delete-btn"
                      data-action="smazatFotku"
                      data-photo-id="${photoId}"
                      data-url="${escapedUrl}"
                      title="Smazat fotku">
                x
              </button>
            ` : ''}
          </div>
        `;
      }).join('');
    } else {
      grid.innerHTML = '<p style="color: #666; font-size: 0.85rem; margin: 0; padding: 0.5rem 0;">Zadne fotografie</p>';
    }

  } catch (error) {
    logger.error('[Fototeka] Chyba pri aktualizaci gridu:', error);
  }
}

// Globalni pristup k funkcim fototéky
window.otevritVyberFotek = otevritVyberFotek;
window.zpracujVybraneFotky = zpracujVybraneFotky;

// PAGINATION: Load more handler
async function loadMoreOrders() {
  if (LOADING_MORE || !HAS_MORE_PAGES) return;

  LOADING_MORE = true;
  const btn = document.getElementById('loadMoreBtn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = t('loading');
  }

  await loadAll('all', true); // append = true
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
    btn.classList.toggle('hidden', !HAS_MORE_PAGES);
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

      // Zobrazit neonový toast (WGSToast pro důležité akce)
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit('Email odeslán zákazníkovi', { titulek: 'WGS' });
      } else {
        showToast('Email odeslán zákazníkovi', 'success');
      }

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

/**
 * Zobrazí modal s archivem videí pro zakázku
 * @param {number} claimId - ID zakázky
 */
async function zobrazVideotekaArchiv(claimId) {
  logger.log(`[Videotéka] Otevírám archiv pro zakázku ID: ${claimId}`);

  // Kontrola - pokud už overlay existuje, nezobrazovat znovu (prevence dvojitého kliknutí)
  const existujiciOverlay = document.getElementById('videotekaOverlay');
  if (existujiciOverlay) {
    logger.log('[Videotéka] Overlay už existuje, ignoruji');
    return;
  }

  // Vytvořit overlay
  const overlay = document.createElement('div');
  overlay.id = 'videotekaOverlay';
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10004; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem;';

  // Kontejner
  const container = document.createElement('div');
  container.style.cssText = 'width: 95%; max-width: 900px; height: 90%; background: #222; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column;';

  // Header (bude aktualizován po načtení dat z API)
  const isMobileHeader = window.innerWidth < 600;
  const header = document.createElement('div');
  header.style.cssText = isMobileHeader
    ? 'padding: 12px 16px; background: #333; color: white; font-weight: 600; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 2px; border-bottom: 2px solid #444;'
    : 'padding: 16px 20px; background: #333; color: white; font-weight: 600; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #444;';
  header.innerHTML = `<span>Načítání...</span>`;

  // Content area - seznam videí (s drag & drop podporou) - sloupcový layout
  const content = document.createElement('div');
  content.id = 'videotekaContent';
  content.style.cssText = 'flex: 1; overflow-y: auto; padding: 16px; background: #1a1a1a; display: flex; flex-direction: column; gap: 12px; align-content: start; position: relative; transition: background 0.2s ease;';

  // Drag & drop overlay (skrytý, zobrazí se při přetahování)
  const dropOverlay = document.createElement('div');
  dropOverlay.id = 'videotekaDropOverlay';
  dropOverlay.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(45, 80, 22, 0.3); border: 3px dashed #2D5016; display: none; align-items: center; justify-content: center; z-index: 10; pointer-events: none;';
  dropOverlay.innerHTML = '<div style="color: white; font-size: 1.2rem; font-weight: 600; text-align: center; padding: 2rem;">Pusťte video pro nahrání</div>';

  // Drag & drop event handlery
  let dragCounter = 0;

  content.addEventListener('dragenter', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter++;
    dropOverlay.classList.remove('hidden');
    content.style.background = '#252525';
  });

  content.addEventListener('dragleave', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter--;
    if (dragCounter === 0) {
      dropOverlay.classList.add('hidden');
      content.style.background = '#1a1a1a';
    }
  });

  content.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.stopPropagation();
  });

  content.addEventListener('drop', async (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter = 0;
    dropOverlay.classList.add('hidden');
    content.style.background = '#1a1a1a';

    const files = e.dataTransfer.files;
    if (files.length > 0) {
      const file = files[0];
      // Kontrola, zda je to video
      if (file.type.startsWith('video/')) {
        logger.log(`[Videotéka] Drag & drop: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`);
        await nahratVideoDragDrop(file, claimId, overlay);
      } else {
        showToast('Lze nahrát pouze video soubory', 'error');
      }
    }
  });

  // Načíst videa z API
  try {
    const response = await fetch(`/api/video_api.php?action=list_videos&claim_id=${claimId}`);
    const result = await response.json();

    if (result.status === 'success') {
      // Aktualizovat nadpis s jménem zákazníka a číslem reklamace
      const customerName = result.customer_name || 'Neznámý zákazník';
      const reklamaceNum = result.reklamace_cislo || claimId;
      if (isMobileHeader) {
        // Mobil: jméno nahoře, číslo pod tím menší, centrované
        header.innerHTML = `
          <span style="font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">${customerName}</span>
          <span style="font-size: 0.75rem; opacity: 0.6;">${reklamaceNum}</span>
        `;
      } else {
        // Desktop: vedle sebe
        header.innerHTML = `<span>${customerName}</span><span style="font-size: 0.85rem; opacity: 0.7;">${reklamaceNum}</span>`;
      }

      if (result.videos && result.videos.length > 0) {
        // Zobrazit seznam videí
        result.videos.forEach(video => {
          const videoCard = vytvorVideoKartu(video, claimId);
          content.appendChild(videoCard);
        });
      } else {
        // Žádná videa - změna na grid layout s centrováním
        content.classList.add('flex-center');
        const emptyState = document.createElement('div');
        emptyState.style.cssText = 'text-align: center; padding: 3rem; color: #999;';
        emptyState.innerHTML = `
          <div style="font-size: 0.85rem; opacity: 0.7; margin-bottom: 1rem;">Žádná videa v archivu</div>
          <div style="font-size: 1.05rem; font-weight: 500; margin-bottom: 0.5rem;">Přetáhněte video sem</div>
          <div style="font-size: 0.85rem; opacity: 0.7;">nebo použijte tlačítko níže</div>
        `;
        content.appendChild(emptyState);
      }
    }
  } catch (error) {
    logger.error('[Videotéka] Chyba při načítání videí:', error);
    header.innerHTML = `<span>Chyba</span>`;
    content.innerHTML = `
      <div style="text-align: center; padding: 3rem; color: #f44;">
        <div style="font-size: 1rem; margin-bottom: 0.5rem;">Chyba při načítání videí</div>
        <div style="font-size: 0.85rem; opacity: 0.7;">${error.message}</div>
      </div>
    `;
  }

  // Footer s tlačítky
  const footer = document.createElement('div');
  footer.style.cssText = 'padding: 16px 20px; background: #333; border-top: 2px solid #444; display: flex; gap: 12px; flex-wrap: wrap; justify-content: center;';

  // Tlačítko Nahrát video
  const btnNahrat = document.createElement('button');
  btnNahrat.textContent = 'Nahrát video';
  btnNahrat.style.cssText = 'padding: 12px 24px; font-size: 0.95rem; font-weight: 600; background: #2D5016; color: white; border: none; border-radius: 6px; cursor: pointer; min-width: 140px; touch-action: manipulation;';
  btnNahrat.onclick = () => otevritNahravaniVidea(claimId, overlay);

  // Tlačítko Zavřít
  const btnZavrit = document.createElement('button');
  btnZavrit.textContent = 'Zavřít';
  btnZavrit.style.cssText = 'padding: 12px 24px; font-size: 0.95rem; font-weight: 600; background: #666; color: white; border: none; border-radius: 6px; cursor: pointer; min-width: 140px; touch-action: manipulation;';
  btnZavrit.onclick = () => overlay.remove();

  footer.appendChild(btnNahrat);
  footer.appendChild(btnZavrit);

  // Přidat drop overlay do content
  content.appendChild(dropOverlay);

  // Sestavit modal
  container.appendChild(header);
  container.appendChild(content);
  container.appendChild(footer);
  overlay.appendChild(container);

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

/**
 * Generuje náhled (thumbnail) z videa pomocí HTML5 video + canvas
 * @param {string} videoPath - Cesta k videu
 * @param {number} maxSirka - Maximální šířka náhledu
 * @param {number} maxVyska - Maximální výška náhledu
 * @returns {Promise<string|null>} Data URL obrázku nebo null při chybě
 */
function generujNahledVidea(videoPath, maxSirka, maxVyska) {
  return new Promise((resolve) => {
    const video = document.createElement('video');
    video.crossOrigin = 'anonymous';
    video.muted = true;
    video.preload = 'metadata';

    // Timeout - pokud se video nenačte do 5 sekund, vrátit null
    const timeout = setTimeout(() => {
      video.src = '';
      resolve(null);
    }, 5000);

    video.onloadedmetadata = () => {
      // Seeknout na 1 sekundu nebo 10% délky (co je menší)
      const seekCas = Math.min(1, video.duration * 0.1);
      video.currentTime = seekCas;
    };

    video.onseeked = () => {
      clearTimeout(timeout);
      try {
        const canvas = document.createElement('canvas');

        // FIX: Zachovat pomer stran - nikdy nedeformovat video
        const videoWidth = video.videoWidth;
        const videoHeight = video.videoHeight;
        const scale = Math.min(maxSirka / videoWidth, maxVyska / videoHeight, 1);

        canvas.width = Math.round(videoWidth * scale * 2); // 2x pro ostrost
        canvas.height = Math.round(videoHeight * scale * 2);

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
        video.src = ''; // Uvolnit video
        resolve(dataUrl);
      } catch (e) {
        video.src = '';
        resolve(null);
      }
    };

    video.onerror = () => {
      clearTimeout(timeout);
      resolve(null);
    };

    video.src = videoPath;
  });
}

/**
 * Vytvoří kartu s video náhledem a tlačítky
 * @param {object} video - Video objekt z databáze
 * @param {number} claimId - ID zakázky
 * @returns {HTMLElement}
 */
function vytvorVideoKartu(video, claimId) {
  // Karta - jednoduchý layout: [video] [info] [tlačítka]
  // Všechno zarovnané nahoru (flex-start), tlačítka mají stejnou výšku jako video
  const isMobile = window.innerWidth < 600;

  const card = document.createElement('div');
  card.style.cssText = `
    background: #2a2a2a;
    border-radius: 6px;
    padding: ${isMobile ? '8px' : '12px'};
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: ${isMobile ? '8px' : '12px'};
    border: 1px solid #444;
    width: 100%;
    box-sizing: border-box;
  `;

  // Video thumbnail (náhled)
  const thumbnailContainer = document.createElement('div');
  const thumbWidth = isMobile ? 100 : 120;
  const thumbHeight = isMobile ? 60 : 68;
  thumbnailContainer.style.cssText = `
    flex-shrink: 0;
    width: ${thumbWidth}px;
    height: ${thumbHeight}px;
    background: #1a1a1a;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #555;
    cursor: pointer;
    overflow: hidden;
    position: relative;
  `;
  // Placeholder s ikonou play (zobrazí se dokud se nenačte náhled)
  thumbnailContainer.innerHTML = `<span style="font-size: ${isMobile ? '1.5rem' : '2rem'}; opacity: 0.5; color: #fff;">▶</span>`;
  thumbnailContainer.onclick = () => prehratVideo(video.video_path, video.video_name);

  // Generovat skutečný náhled z videa
  generujNahledVidea(video.video_path, thumbWidth, thumbHeight).then(nahledUrl => {
    if (nahledUrl) {
      // Nahradit placeholder obrázkem s malou ikonou play
      thumbnailContainer.innerHTML = `
        <img src="${nahledUrl}" style="width: 100%; height: 100%; object-fit: cover;">
        <span style="position: absolute; font-size: ${isMobile ? '1.2rem' : '1.5rem'}; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.8); opacity: 0.9;">▶</span>
      `;
    }
  }).catch(() => {
    // Pokud se náhled nepodaří, zůstane placeholder
  });

  // Informace o videu
  const infoContainer = document.createElement('div');
  infoContainer.style.cssText = 'flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 2px; min-width: 0;';
  infoContainer.title = video.video_name; // Název souboru v tooltipu

  // Kdo přidal video (hlavní řádek) - menší na mobilu
  const autorRow = document.createElement('div');
  autorRow.style.cssText = `font-weight: 500; font-size: ${isMobile ? '0.7rem' : '0.9rem'}; color: ${isMobile ? '#aaa' : '#fff'};`;
  if (video.uploader_email) {
    const emailKratky = video.uploader_email.split('@')[0];
    autorRow.textContent = emailKratky;
    autorRow.title = video.uploader_email;
  } else {
    autorRow.textContent = 'Admin';
    autorRow.style.color = '#888';
  }

  // Velikost a datum (sekundární řádek) - menší na mobilu
  const metaRow = document.createElement('div');
  metaRow.style.cssText = `display: flex; gap: ${isMobile ? '6px' : '8px'}; flex-wrap: wrap; align-items: center;`;

  const velikost = document.createElement('span');
  velikost.style.cssText = `font-size: ${isMobile ? '0.6rem' : '0.7rem'}; color: #666;`;
  velikost.textContent = `${(video.file_size / 1024 / 1024).toFixed(1)} MB`;

  const datum = document.createElement('span');
  datum.style.cssText = `font-size: ${isMobile ? '0.6rem' : '0.7rem'}; color: #666;`;
  // Formatovat datum s dnem v tydnu
  let datumText = '—';
  if (video.uploaded_at) {
    const d = new Date(video.uploaded_at);
    const dny = ['ne', 'po', 'ut', 'st', 'ct', 'pa', 'so'];
    const den = dny[d.getDay()];
    if (isMobile) {
      datumText = `${den} ${d.getDate()}.${d.getMonth() + 1}.`;
    } else {
      datumText = `${den} ${d.getDate()}.${d.getMonth() + 1}.${d.getFullYear()} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
    }
  }
  datum.textContent = datumText;

  metaRow.appendChild(velikost);
  metaRow.appendChild(datum);

  infoContainer.appendChild(autorRow);
  infoContainer.appendChild(metaRow);

  // Tlačítka - na mobilu vertikálně se stejnou výškou jako video náhled
  const buttonsContainer = document.createElement('div');
  // Na mobilu: kontejner má výšku videa, tlačítka se roztáhnou pomocí flex:1
  // Na desktopu: horizontálně
  const btnGap = 4;

  buttonsContainer.style.cssText = isMobile
    ? `display: flex; flex-direction: column; gap: ${btnGap}px; flex-shrink: 0; height: ${thumbHeight}px; max-height: ${thumbHeight}px; overflow: hidden; box-sizing: border-box;`
    : 'display: flex; flex-direction: row; align-items: center; gap: 6px; flex-shrink: 0;';

  // Společný styl pro ikony na mobilu - pevná výška 28px
  // !important přepíše globální min-height: 44px z seznam-mobile-fixes.css
  const ikonaBtnStyle = `height: 28px !important; min-height: 28px !important; max-height: 28px !important; width: 36px; padding: 0; margin: 0; border-radius: 3px; cursor: pointer; touch-action: manipulation; display: flex; align-items: center; justify-content: center; border: 1px solid #555; box-sizing: border-box;`;

  // Tlačítko Stáhnout - ikona na mobilu, text na desktopu
  const btnStahnout = document.createElement('button');
  if (isMobile) {
    btnStahnout.innerHTML = '<i class="fas fa-download"></i>';
    btnStahnout.title = 'Stáhnout video';
    btnStahnout.style.cssText = ikonaBtnStyle + ' background: #444; color: #ccc; font-size: 0.75rem;';
  } else {
    btnStahnout.textContent = 'Stáhnout';
    btnStahnout.style.cssText = 'min-height: 36px; padding: 0.4rem 0.8rem; font-size: 0.8rem; border: 1px solid #555; border-radius: 4px; cursor: pointer; touch-action: manipulation; white-space: nowrap; background: #444; color: white;';
  }
  btnStahnout.onclick = () => {
    const link = document.createElement('a');
    link.href = video.video_path;
    link.download = video.video_name || 'video.mp4';
    link.click();
  };

  // Tlačítko Smazat - dva-klikove potvrzeni (obchazi z-index problemy s wgsConfirm)
  const btnSmazat = document.createElement('button');
  const origBtnStyle = isMobile
    ? ikonaBtnStyle + ' background: #442222; color: #c66; font-size: 0.85rem; font-weight: bold;'
    : 'min-height: 36px; width: 36px; padding: 0; font-size: 1.1rem; font-weight: bold; background: #553333; color: #c66; border: 1px solid #664444; border-radius: 4px; cursor: pointer; touch-action: manipulation; display: flex; align-items: center; justify-content: center;';
  btnSmazat.innerHTML = '&#10005;'; // × křížek
  btnSmazat.title = 'Smazat video';
  btnSmazat.style.cssText = origBtnStyle;

  let potvrzeniTimeout = null;
  btnSmazat.onclick = async (e) => {
    e.stopPropagation();

    // Prvni klik - zobrazit potvrzeni
    if (!btnSmazat.classList.contains('potvrzeni-video')) {
      btnSmazat.classList.add('potvrzeni-video');
      btnSmazat.innerHTML = 'Smazat?';
      btnSmazat.style.cssText = isMobile
        ? ikonaBtnStyle + ' background: #662222; color: #fff; font-size: 0.7rem; font-weight: bold; min-width: 50px;'
        : 'min-height: 36px; padding: 0 8px; font-size: 0.75rem; font-weight: bold; background: #662222; color: #fff; border: 1px solid #884444; border-radius: 4px; cursor: pointer; touch-action: manipulation; white-space: nowrap;';

      // Reset po 3s
      potvrzeniTimeout = setTimeout(() => {
        btnSmazat.classList.remove('potvrzeni-video');
        btnSmazat.innerHTML = '&#10005;';
        btnSmazat.style.cssText = origBtnStyle;
      }, 3000);
      return;
    }

    // Druhy klik - smazat
    clearTimeout(potvrzeniTimeout);
    btnSmazat.innerHTML = '...';
    btnSmazat.disabled = true;

    try {
      const formData = new FormData();
      formData.append('action', 'delete_video');
      formData.append('video_id', video.id);
      formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

      const response = await fetch('/api/video_api.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.status === 'success') {
        showToast('Video bylo smazáno', 'success');
        card.remove();
      } else {
        throw new Error(result.message || 'Chyba při mazání');
      }
    } catch (error) {
      logger.error('[Videotéka] Chyba při mazání videa:', error);
      showToast('Chyba při mazání videa: ' + error.message, 'error');
      // Vratit tlacitko
      btnSmazat.classList.remove('potvrzeni-video');
      btnSmazat.innerHTML = '&#10005;';
      btnSmazat.style.cssText = origBtnStyle;
      btnSmazat.disabled = false;
    }
  };

  buttonsContainer.appendChild(btnStahnout);
  buttonsContainer.appendChild(btnSmazat);

  // Sestavit kartu: [video] [info] [tlačítka]
  card.appendChild(thumbnailContainer);
  card.appendChild(infoContainer);
  card.appendChild(buttonsContainer);

  return card;
}

/**
 * Přehraje video v modálním okně
 * @param {string} videoPath - Cesta k video souboru
 * @param {string} videoName - Název videa
 */
function prehratVideo(videoPath, videoName) {
  // Vytvořit overlay pro přehrávač
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10005; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem;';

  // Video element
  const video = document.createElement('video');
  video.src = videoPath;
  video.controls = true;
  video.autoplay = true;
  video.style.cssText = 'max-width: 95%; max-height: 85vh; border-radius: 8px;';

  // Název videa
  const title = document.createElement('div');
  title.style.cssText = 'color: white; font-size: 1rem; margin-top: 16px; text-align: center;';
  title.textContent = videoName || 'Video';

  // Tlačítko Zavřít
  const btnClose = document.createElement('button');
  btnClose.textContent = 'Zavřít';
  btnClose.style.cssText = 'margin-top: 16px; padding: 10px 24px; background: #666; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem;';
  btnClose.onclick = () => {
    video.pause();
    overlay.remove();
  };

  overlay.appendChild(video);
  overlay.appendChild(title);
  overlay.appendChild(btnClose);

  // Zavřít při kliknutí mimo video
  overlay.onclick = (e) => {
    if (e.target === overlay) {
      video.pause();
      overlay.remove();
    }
  };

  // ESC zavře přehrávač
  const escHandler = (e) => {
    if (e.key === 'Escape') {
      video.pause();
      overlay.remove();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  document.body.appendChild(overlay);
}

/**
 * Otevře modal pro nahrání nového videa s automatickou kompresí
 * @param {number} claimId - ID zakázky
 * @param {HTMLElement} parentOverlay - Rodičovský overlay (videotéka archiv)
 */
function otevritNahravaniVidea(claimId, parentOverlay) {
  logger.log(`[Videotéka] Otevírám upload pro zakázku ID: ${claimId}`);

  // Vytvořit overlay pro upload
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10006; display: flex; align-items: center; justify-content: center; padding: 1rem;';

  // Kontejner
  const container = document.createElement('div');
  container.style.cssText = 'background: #2a2a2a; border-radius: 8px; padding: 24px; max-width: 500px; width: 100%; border: 2px solid #444;';

  // Nadpis
  const nadpis = document.createElement('h3');
  nadpis.style.cssText = 'color: white; margin: 0 0 20px 0; font-size: 1.1rem;';
  nadpis.textContent = 'Nahrát video';

  // File input
  const fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.id = 'video';
  fileInput.name = 'video';
  fileInput.accept = 'video/*';
  fileInput.style.cssText = 'display: block; width: 100%; padding: 12px; background: #1a1a1a; color: white; border: 1px solid #555; border-radius: 4px; margin-bottom: 16px; font-size: 0.9rem;';

  // Info o velikosti
  const infoBox = document.createElement('div');
  infoBox.style.cssText = 'background: #1a1a1a; padding: 12px; border-radius: 4px; margin-bottom: 16px; color: #999; font-size: 0.85rem; border: 1px solid #555;';
  infoBox.innerHTML = `
    <div style="margin-bottom: 6px;">ℹ️ <strong>Informace o nahrávání:</strong></div>
    <div style="margin-left: 24px;">
      <div>• Maximální velikost: 500 MB</div>
      <div>• Podporované formáty: MP4, MOV, AVI, WebM</div>
      <div>• Video nad 500 MB bude automaticky komprimováno</div>
    </div>
  `;

  // Progress bar (skrytý)
  const progressContainer = document.createElement('div');
  progressContainer.style.cssText = 'display: none; margin-bottom: 16px;';

  const progressBar = document.createElement('div');
  progressBar.style.cssText = 'width: 100%; height: 24px; background: #1a1a1a; border-radius: 4px; overflow: hidden; border: 1px solid #555;';

  const progressFill = document.createElement('div');
  progressFill.style.cssText = 'height: 100%; background: #2D5016; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: 600;';

  progressBar.appendChild(progressFill);
  progressContainer.appendChild(progressBar);

  // Status text
  const statusText = document.createElement('div');
  statusText.style.cssText = 'text-align: center; color: #999; font-size: 0.85rem; margin-top: 8px; display: none;';

  progressContainer.appendChild(statusText);

  // Tlačítka
  const buttonContainer = document.createElement('div');
  buttonContainer.style.cssText = 'display: flex; gap: 12px; justify-content: flex-end;';

  const btnZrusit = document.createElement('button');
  btnZrusit.textContent = 'Zrušit';
  btnZrusit.style.cssText = 'padding: 10px 20px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem;';
  btnZrusit.onclick = () => overlay.remove();

  const btnNahrat = document.createElement('button');
  btnNahrat.textContent = 'Nahrát';
  btnNahrat.style.cssText = 'padding: 10px 20px; background: #2D5016; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; font-weight: 600;';
  btnNahrat.onclick = async () => {
    const file = fileInput.files[0];
    if (!file) {
      wgsToast.warning('Vyberte video soubor');
      return;
    }

    // Kontrola velikosti
    const maxSize = 524288000; // 500 MB
    const needsCompression = file.size > maxSize;

    btnNahrat.disabled = true;
    btnZrusit.disabled = true;
    progressContainer.classList.remove('hidden');
    statusText.classList.remove('hidden');

    try {
      let uploadFile = file;

      // Komprese pokud je potřeba A prohlížeč to podporuje
      if (needsCompression) {
        statusText.textContent = 'Komprimuji video...';
        progressFill.style.width = '10%';
        progressFill.textContent = '10%';

        try {
          // Zkontrolovat podporu MediaRecorder
          if (typeof MediaRecorder === 'undefined') {
            throw new Error('MediaRecorder není podporován');
          }

          uploadFile = await komprimovatVideo(file, (progress) => {
            const percent = Math.round(10 + progress * 40); // 10% - 50%
            progressFill.style.width = percent + '%';
            progressFill.textContent = percent + '%';
          });

          logger.log(`[Videotéka] Video komprimováno: ${file.size} → ${uploadFile.size} bytů`);
        } catch (kompErr) {
          // Fallback - použít originální soubor
          logger.warn(`[Videotéka] Komprese selhala, používám originál: ${kompErr.message}`);
          uploadFile = file;
          statusText.textContent = 'Komprese nedostupná, nahrávám originál...';

          // Pokud je soubor příliš velký bez komprese, zobrazit varování
          if (file.size > 524288000) {
            showToast('Video je příliš velké (max 500 MB). Komprese selhala.', 'error');
            throw new Error('Video příliš velké a komprese není dostupná');
          }
        }
      }

      // Upload
      statusText.textContent = 'Nahrávám video...';
      progressFill.style.width = '50%';
      progressFill.textContent = '50%';

      const formData = new FormData();
      formData.append('action', 'upload_video');
      formData.append('claim_id', claimId);
      formData.append('video', uploadFile, uploadFile.name || file.name);
      formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

      const response = await fetch('/api/video_api.php', {
        method: 'POST',
        body: formData
      });

      progressFill.style.width = '90%';
      progressFill.textContent = '90%';

      const result = await response.json();

      if (result.status === 'success') {
        progressFill.style.width = '100%';
        progressFill.textContent = '100%';
        statusText.textContent = 'Hotovo!';
        progressFill.style.background = '#333';

        // Neonový toast pro úspěšný upload
        if (typeof WGSToast !== 'undefined') {
          WGSToast.zobrazit('Video bylo úspěšně nahráno', { titulek: 'WGS' });
        } else {
          showToast('Video bylo úspěšně nahráno', 'success');
        }

        // Zavřít upload modal
        setTimeout(() => {
          overlay.remove();

          // Reload videotéky
          parentOverlay.remove();
          zobrazVideotekaArchiv(claimId);
        }, 1000);

      } else {
        throw new Error(result.message || 'Chyba při nahrávání');
      }

    } catch (error) {
      logger.error('[Videotéka] Chyba při uploadu:', error);
      progressFill.style.background = '#c33';
      statusText.textContent = 'Chyba: ' + error.message;
      btnNahrat.disabled = false;
      btnZrusit.disabled = false;
      showToast('Chyba při nahrávání videa: ' + error.message, 'error');
    }
  };

  buttonContainer.appendChild(btnZrusit);
  buttonContainer.appendChild(btnNahrat);

  // Sestavit modal
  container.appendChild(nadpis);
  container.appendChild(infoBox);
  container.appendChild(fileInput);
  container.appendChild(progressContainer);
  container.appendChild(buttonContainer);
  overlay.appendChild(container);

  // Zavřít při ESC
  const escHandler = (e) => {
    if (e.key === 'Escape' && !btnNahrat.disabled) {
      overlay.remove();
      document.removeEventListener('keydown', escHandler);
    }
  };
  document.addEventListener('keydown', escHandler);

  document.body.appendChild(overlay);
}

/**
 * Nahraje video přetažené drag & drop
 * @param {File} file - Video soubor
 * @param {number} claimId - ID zakázky
 * @param {HTMLElement} parentOverlay - Rodičovský overlay (videotéka archiv)
 */
async function nahratVideoDragDrop(file, claimId, parentOverlay) {
  logger.log(`[Videotéka] Zahajuji drag & drop upload: ${file.name}`);

  // Vytvořit progress overlay
  const progressOverlay = document.createElement('div');
  progressOverlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10007; display: flex; align-items: center; justify-content: center; padding: 1rem;';

  const progressContainer = document.createElement('div');
  progressContainer.style.cssText = 'background: #2a2a2a; border-radius: 8px; padding: 24px; max-width: 400px; width: 100%; border: 2px solid #444; text-align: center;';

  const progressTitle = document.createElement('div');
  progressTitle.style.cssText = 'color: white; font-size: 1rem; font-weight: 600; margin-bottom: 16px;';
  progressTitle.textContent = 'Nahrávání videa...';

  const progressBarOuter = document.createElement('div');
  progressBarOuter.style.cssText = 'width: 100%; height: 24px; background: #1a1a1a; border-radius: 4px; overflow: hidden; border: 1px solid #555;';

  const progressBarInner = document.createElement('div');
  progressBarInner.style.cssText = 'height: 100%; background: #2D5016; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; font-weight: 600;';
  progressBarInner.textContent = '0%';

  const progressStatus = document.createElement('div');
  progressStatus.style.cssText = 'color: #999; font-size: 0.85rem; margin-top: 12px;';
  progressStatus.textContent = file.name;

  progressBarOuter.appendChild(progressBarInner);
  progressContainer.appendChild(progressTitle);
  progressContainer.appendChild(progressBarOuter);
  progressContainer.appendChild(progressStatus);
  progressOverlay.appendChild(progressContainer);
  document.body.appendChild(progressOverlay);

  try {
    const maxSize = 524288000; // 500 MB
    let uploadFile = file;

    // Komprese pokud je potřeba
    if (file.size > maxSize) {
      progressStatus.textContent = 'Komprimuji video...';
      progressBarInner.style.width = '10%';
      progressBarInner.textContent = '10%';

      uploadFile = await komprimovatVideo(file, (progress) => {
        const percent = Math.round(10 + progress * 40);
        progressBarInner.style.width = percent + '%';
        progressBarInner.textContent = percent + '%';
      });

      logger.log(`[Videotéka] Video komprimováno: ${file.size} → ${uploadFile.size} bytů`);
    }

    // Upload
    progressStatus.textContent = 'Odesílám na server...';
    progressBarInner.style.width = '50%';
    progressBarInner.textContent = '50%';

    const formData = new FormData();
    formData.append('action', 'upload_video');
    formData.append('claim_id', claimId);
    formData.append('video', uploadFile, uploadFile.name || file.name);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

    const response = await fetch('/api/video_api.php', {
      method: 'POST',
      body: formData
    });

    progressBarInner.style.width = '90%';
    progressBarInner.textContent = '90%';

    const result = await response.json();

    if (result.status === 'success') {
      progressBarInner.style.width = '100%';
      progressBarInner.textContent = '100%';
      progressStatus.textContent = 'Hotovo!';
      progressBarInner.style.background = '#333';

      // Neonový toast pro úspěšný upload
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit('Video bylo úspěšně nahráno', { titulek: 'WGS' });
      } else {
        showToast('Video bylo úspěšně nahráno', 'success');
      }

      // Zavřít progress a reload videotéky
      setTimeout(() => {
        progressOverlay.remove();
        parentOverlay.remove();
        zobrazVideotekaArchiv(claimId);
      }, 1000);

    } else {
      throw new Error(result.message || 'Chyba při nahrávání');
    }

  } catch (error) {
    logger.error('[Videotéka] Chyba při drag & drop uploadu:', error);
    progressBarInner.style.background = '#c33';
    progressBarInner.style.width = '100%';
    progressStatus.textContent = 'Chyba: ' + error.message;
    showToast('Chyba při nahrávání videa: ' + error.message, 'error');

    // Zavřít progress po 3 sekundách
    setTimeout(() => {
      progressOverlay.remove();
    }, 3000);
  }
}

/**
 * Komprimuje video pomocí MediaRecorder API
 * @param {File} videoFile - Původní video soubor
 * @param {Function} progressCallback - Callback pro progress update
 * @returns {Promise<Blob>} - Komprimované video
 */
async function komprimovatVideo(videoFile, progressCallback) {
  return new Promise((resolve, reject) => {
    try {
      // Vytvořit video element
      const video = document.createElement('video');
      video.preload = 'metadata';
      video.muted = true;
      video.playsInline = true; // Důležité pro iOS

      video.onloadedmetadata = () => {
        const width = video.videoWidth;
        const height = video.videoHeight;

        // Maximální rozlišení 1920x1080
        let targetWidth = width;
        let targetHeight = height;

        if (width > 1920 || height > 1080) {
          const ratio = Math.min(1920 / width, 1080 / height);
          targetWidth = Math.round(width * ratio);
          targetHeight = Math.round(height * ratio);
        }

        // Vytvořit canvas
        const canvas = document.createElement('canvas');
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        const ctx = canvas.getContext('2d');

        // Vytvořit stream z canvasu
        const stream = canvas.captureStream(30); // 30 FPS

        // Najít podporovaný MIME typ (VP9 → VP8 → default)
        const mimeTypy = [
          'video/webm;codecs=vp9',
          'video/webm;codecs=vp8',
          'video/webm',
          'video/mp4'
        ];

        let vybranyMime = '';
        for (const mime of mimeTypy) {
          if (MediaRecorder.isTypeSupported(mime)) {
            vybranyMime = mime;
            break;
          }
        }

        if (!vybranyMime) {
          reject(new Error('Žádný video kodek není podporován'));
          return;
        }

        logger.log(`[Videotéka] Používám kodek: ${vybranyMime}`);

        // MediaRecorder s kompresí
        const options = {
          mimeType: vybranyMime,
          videoBitsPerSecond: 2500000 // 2.5 Mbps - dobrá komprese při zachování kvality
        };

        let mediaRecorder;
        try {
          mediaRecorder = new MediaRecorder(stream, options);
        } catch (e) {
          // Fallback bez specifikace bitrate
          mediaRecorder = new MediaRecorder(stream, { mimeType: vybranyMime });
        }

        const chunks = [];

        mediaRecorder.ondataavailable = (e) => {
          if (e.data.size > 0) {
            chunks.push(e.data);
          }
        };

        mediaRecorder.onstop = () => {
          const blob = new Blob(chunks, { type: vybranyMime });
          // Přidat správnou příponu podle MIME
          const ext = vybranyMime.includes('mp4') ? 'mp4' : 'webm';
          blob.name = videoFile.name.replace(/\.[^.]+$/, '') + '_compressed.' + ext;
          resolve(blob);
        };

        mediaRecorder.onerror = (e) => {
          reject(new Error('Chyba při kompresi videa: ' + (e.error?.message || 'neznámá')));
        };

        // Spustit záznam
        mediaRecorder.start(1000); // chunk každou sekundu

        // Přehrát video a renderovat do canvasu
        video.play().catch(e => {
          reject(new Error('Nelze přehrát video pro kompresi: ' + e.message));
        });

        const renderFrame = () => {
          if (!video.paused && !video.ended) {
            ctx.drawImage(video, 0, 0, targetWidth, targetHeight);

            // Update progress
            if (progressCallback) {
              const progress = video.currentTime / video.duration;
              progressCallback(progress);
            }

            requestAnimationFrame(renderFrame);
          } else {
            // Video skončilo
            setTimeout(() => mediaRecorder.stop(), 100);
          }
        };

        video.onplay = () => {
          renderFrame();
        };

        video.onerror = () => {
          reject(new Error('Chyba při načítání videa pro kompresi'));
        };
      };

      video.onerror = () => {
        reject(new Error('Video soubor nelze načíst'));
      };

      // Načíst video
      video.src = URL.createObjectURL(videoFile);

    } catch (error) {
      reject(error);
    }
  });
}

// ========================================
// EVENT DELEGATION PRO TLAČÍTKA V DETAILU
// ========================================
// POZOR: Tento listener je DEAKTIVOVÁN - event handling se provádí v seznam.php (EMERGENCY event delegation V6)
// Důvod: Duplicitní event listenery způsobovaly vícenásobné volání funkcí
// Pokud EMERGENCY listener selže, můžete tento listener znovu aktivovat

// ============================================================================
// ZALOŽIT ZNOVU - Klonování dokončené karty
// ============================================================================
async function zalozitZnovu(reklamaceId) {
  if (!reklamaceId && CURRENT_RECORD) {
    reklamaceId = CURRENT_RECORD.id;
  }

  if (!reklamaceId) {
    wgsToast.error('Chybí ID reklamace');
    return;
  }

  // Najít původní reklamaci
  const puvodni = WGS_DATA_CACHE.find(r => r.id == reklamaceId);
  if (!puvodni) {
    wgsToast.error('Reklamace nenalezena');
    return;
  }

  // Potvrzovací dialog
  const potvrdit = await wgsConfirm(
    `Opravdu chcete vytvořit novou kartu pro zákazníka ${puvodni.jmeno || 'N/A'}? Vytvoří se nová žlutá karta se všemi údaji.`,
    {
      titulek: 'Založit znovu',
      btnPotvrdit: 'Ano, založit',
      btnZrusit: 'Zrušit'
    }
  );

  if (!potvrdit) return;

  try {
    // Zobrazit loading
    wgsToast.info('Zakládám novou kartu...');

    // Zavolat API pro klonování
    const formData = new FormData();
    formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
    formData.append('action', 'klonovat');
    formData.append('puvodni_id', reklamaceId);

    const response = await fetch('/api/klonovani_api.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.status === 'success') {
      WGSToast.zobrazit(`Nová karta úspěšně vytvořena: ${data.nova_reklamace_cislo}`, {
        titulek: 'Úspěch',
        trvani: 5000,
        claimId: data.nova_reklamace_id
      });

      // Zavřít detail
      closeDetail();

      // Obnovit seznam
      await loadAll('all');
    } else {
      wgsToast.error(data.message || 'Chyba při zakládání nové karty');
    }
  } catch (error) {
    logger.error('Chyba při zakládání znovu:', error);
    wgsToast.error('Chyba při zakládání nové karty');
  }
}

// Export do window
window.zalozitZnovu = zalozitZnovu;
