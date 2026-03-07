// VERSION: 20251210-01 - Skrytí technických tlačítek pro prodejce

// === GLOBÁLNÍ PROMĚNNÉ ===
let WGS_DATA_CACHE = [];
let ACTIVE_FILTERS = new Set(); // Může být více filtrů najednou (toggle)
let CURRENT_RECORD = null;
let SELECTED_DATE = null;
let SELECTED_TIME = null;
let EMAILS_S_CN = []; // Emaily zákazníků s cenovou nabídkou
let STAVY_NABIDEK = {}; // Mapa email -> stav nabídky ('potvrzena' nebo 'odeslana')
let _jeBackgroundRerender = false; // Guard proti vnořenému spuštění background fetchů

// PAGINATION FIX: Tracking pagination state
let CURRENT_PAGE = 1;
let HAS_MORE_PAGES = false;
let LOADING_MORE = false;
const PER_PAGE = 9999; // Načíst všechny karty najednou
let CAL_MONTH = new Date().getMonth();
let CAL_YEAR = new Date().getFullYear();
let SEARCH_QUERY = '';
let VIEW_MODE = window.innerWidth <= 768 ? 'karty' : (localStorage.getItem('wgs-seznam-view') || 'radky');
let ADMIN_PRODEJCE_FILTER = null; // null = vsichni, jinak created_by id vybrane ho prodejce

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

  // Přepínač zobrazení KARTY / ŘÁDKY
  document.querySelectorAll('.view-toggle-btn').forEach(btn => {
    if (btn.dataset.view === VIEW_MODE) btn.classList.add('active');
    btn.addEventListener('click', () => {
      VIEW_MODE = btn.dataset.view;
      localStorage.setItem('wgs-seznam-view', VIEW_MODE);
      document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);
      renderOrders(userItems);
    });
  });
}

// === AKTUALIZACE POČTŮ ===
function updateCounts(items) {
  if (!Array.isArray(items)) return;

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
    const response = await fetch(`/app/controllers/load.php?status=${status}&page=${page}&per_page=${PER_PAGE}&_t=${cacheBuster}`, {
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
    // Zachovat dočasné příznaky (např. _sms_odeslana) přes auto-refresh i page reload
    // Spojit ID z paměti (auto-refresh) a z localStorage (page reload)
    const _smsZPameti = WGS_DATA_CACHE
      .filter(x => x._sms_odeslana)
      .map(x => String(x.id || x.reklamace_id));
    const _smsZStorage = _nactiSmsZeStorage ? _nactiSmsZeStorage() : new Set();
    const _smsOdeslaneIds = new Set([..._smsZPameti, ..._smsZStorage]);

    if (append) {
      WGS_DATA_CACHE = [...WGS_DATA_CACHE, ...items];
      CURRENT_PAGE = page;
    } else {
      WGS_DATA_CACHE = items;
      CURRENT_PAGE = 1;
    }

    // Obnovit příznaky po přepisu cache
    WGS_DATA_CACHE.forEach(zaznam => {
      // Z databáze (persistentní pro všechny uživatele)
      if (zaznam.sms_kontakt_datum) {
        zaznam._sms_odeslana = true;
      }
      // Z localStorage nebo paměti (fallback pro zařízení bez DB záznamu)
      if (_smsOdeslaneIds.has(String(zaznam.id || zaznam.reklamace_id))) {
        zaznam._sms_odeslana = true;
      }
    });

    // PAGINATION: Detekce zda jsou další stránky
    HAS_MORE_PAGES = items.length === PER_PAGE;
    LOADING_MORE = false;

    let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);

    sestavAdminProdejceBox();
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


// === VYKRESLENÍ OBJEDNÁVEK ===
// Prioritní číslo záznamu pro řazení
function prioritaZaznamu(r) {
  const stav = r.stav || 'wait';
  const email = (r.email || '').toLowerCase().trim();
  const jeOdlozena = r.je_odlozena == 1 || r.je_odlozena === true;
  const isDone = stav === 'HOTOVO' || stav === 'done';
  const isWait = stav === 'ČEKÁ' || stav === 'wait';
  const isOpen = stav === 'DOMLUVENÁ' || stav === 'open';
  const isCekameNaDily = stav === 'ČEKÁME NA DÍLY' || stav === 'cekame_na_dily';
  const maCN = email && EMAILS_S_CN && EMAILS_S_CN.includes(email);
  const cnStav = maCN ? (STAVY_NABIDEK && STAVY_NABIDEK[email]) : null;

  if (isDone) return 7;                              // HOTOVO
  if (isCekameNaDily) return 5;                      // Čekáme na díly (= CN Čekáme ND)
  if (maCN && cnStav === 'cekame_nd') return 5;      // CN Čekáme ND (stejná pozice)
  if (jeOdlozena) return 4;                          // ODLOŽENÁ
  if (isOpen) return 3;                              // DOMLUVENÁ
  if (isWait && !maCN) return 1;                     // NOVÁ (bez CN)
  if (maCN && cnStav === 'potvrzena') return 2;      // CN Odsouhlasena (hned za NOVÁ)
  return 6;                                          // CN Poslána (za Čekáme na díly)
}

// Pomocná funkce pro parse termínu na timestamp
function parseTermin(r) {
  if (!r.termin) return Infinity;
  const parts = r.termin.split('.');
  if (parts.length === 3) {
    const dateStr = `${parts[2]}-${parts[1].padStart(2,'0')}-${parts[0].padStart(2,'0')}T${r.cas_navstevy || '00:00:00'}`;
    const ts = new Date(dateStr).getTime();
    return isNaN(ts) ? Infinity : ts;
  }
  return Infinity;
}

// Seřadit záznamy dle definované priority
function seraditZaznamy(zaznamy) {
  return [...zaznamy].sort((a, b) => {
    const pa = prioritaZaznamu(a);
    const pb = prioritaZaznamu(b);
    if (pa !== pb) return pa - pb;

    // V rámci stejné skupiny: subsort
    if (pa === 1) {
      // NOVÁ: nejnovější zadaný první (created_at DESC)
      const ta = new Date(a.created_at || 0).getTime();
      const tb = new Date(b.created_at || 0).getTime();
      return tb - ta;
    }
    if (pa === 2) {
      // DOMLUVENÁ: nejbližší termín a čas první (ASC)
      return parseTermin(a) - parseTermin(b);
    }
    return 0;
  });
}

function sestavAdminProdejceBox() {
  if (!CURRENT_USER || !CURRENT_USER.is_admin) return;
  const box = document.getElementById('adminProdejceBox');
  if (!box) return;
  box.style.display = 'flex';

  // Sestavit mapu unikatnich prodejcu z nactenych dat
  const prodejceMap = new Map();
  (WGS_DATA_CACHE || []).forEach(r => {
    const id = String(r.created_by || '').trim();
    if (!id) return;
    const jmeno = r.zadavatel_jmeno || r.created_by_name || id;
    if (!prodejceMap.has(id)) {
      prodejceMap.set(id, jmeno);
    }
  });

  const seznam = document.getElementById('adminProdejceList');
  seznam.innerHTML = '';

  // Tlacitko "Vse"
  const btnVse = document.createElement('button');
  btnVse.className = 'admin-prodejce-btn' + (ADMIN_PRODEJCE_FILTER === null ? ' active' : '');
  btnVse.dataset.prodejceId = '';
  const celkemPocet = (WGS_DATA_CACHE || []).length;
  btnVse.textContent = `Vše (${celkemPocet})`;
  seznam.appendChild(btnVse);

  // Tlacitka pro jednotlive prodejce - serazene podle poctu zakazek (sestupne)
  const prodejceSeznam = [...prodejceMap.entries()].map(([id, jmeno]) => ({
    id,
    jmeno,
    pocet: (WGS_DATA_CACHE || []).filter(r => String(r.created_by || '') === id).length
  })).sort((a, b) => b.pocet - a.pocet);

  prodejceSeznam.forEach(({ id, jmeno, pocet }) => {
    const btn = document.createElement('button');
    btn.className = 'admin-prodejce-btn' + (ADMIN_PRODEJCE_FILTER === id ? ' active' : '');
    btn.dataset.prodejceId = id;
    btn.textContent = `${jmeno} (${pocet})`;
    seznam.appendChild(btn);
  });

  // Odebrat stare listenery nahrazenim uzlu (klon)
  const novySeznam = seznam.cloneNode(true);
  seznam.parentNode.replaceChild(novySeznam, seznam);

  novySeznam.addEventListener('click', (e) => {
    const btn = e.target.closest('.admin-prodejce-btn');
    if (!btn) return;
    const id = btn.dataset.prodejceId;
    ADMIN_PRODEJCE_FILTER = id || null;
    novySeznam.querySelectorAll('.admin-prodejce-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);
    renderOrders(userItems);
  });
}

// === HTMX HELPER: Aktualizace gridu přes server-rendered HTML ===
function _htmxAktualizujGrid() {
  // Server renderuje pouze karty (order-box) — nepoužívat v režimu řádků
  if (typeof VIEW_MODE !== 'undefined' && VIEW_MODE === 'radky') {
    let userItems = Utils.filterByUserRole(WGS_DATA_CACHE);
    renderOrders(userItems);
    return;
  }

  const stavFiltr = ACTIVE_FILTERS.size === 1 ? [...ACTIVE_FILTERS][0] : 'all';
  const hledej = SEARCH_QUERY || '';
  const prodejceId = ADMIN_PRODEJCE_FILTER || '';

  let url = `/api/seznam_html.php?status=${encodeURIComponent(stavFiltr)}`;
  if (hledej) url += `&search=${encodeURIComponent(hledej)}`;
  if (prodejceId) url += `&prodejce_id=${encodeURIComponent(prodejceId)}`;

  // Aktualizovat info o výsledcích hledání
  const searchResultsInfo = document.getElementById('searchResultsInfo');
  if (searchResultsInfo) {
    if (hledej) {
      searchResultsInfo.classList.remove('hidden');
      searchResultsInfo.textContent = `Hledání: "${hledej}"`;
    } else {
      searchResultsInfo.classList.add('hidden');
    }
  }

  htmx.ajax('GET', url, { target: '#orderGrid', swap: 'innerHTML' });
}

async function renderOrders(items = null) {
  const grid = document.getElementById('orderGrid');
  const searchResultsInfo = document.getElementById('searchResultsInfo');

  if (!items) {
    items = Utils.filterByUserRole(WGS_DATA_CACHE);
  }

  if (!Array.isArray(items)) items = [];

  // Přímá kontrola localStorage – pojistka pro případ kdy _sms_odeslana není v cache
  const _smsOdeslaneVRenderu = _nactiSmsZeStorage ? _nactiSmsZeStorage() : new Set();

  // Admin filtr podle prodejce
  if (CURRENT_USER && CURRENT_USER.is_admin && ADMIN_PRODEJCE_FILTER !== null) {
    items = items.filter(r => String(r.created_by || '') === ADMIN_PRODEJCE_FILTER);
  }

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
          // NOVÁ: stav je wait A nemá CN A není odložená
          // ODSOUHLASENA: stav je wait A má CN odsouhlasenou A není odložená
          const isWait = stav === 'ČEKÁ' || stav === 'wait';
          const jeOdlozena = r.je_odlozena == 1 || r.je_odlozena === true;
          const maCN = email && EMAILS_S_CN.includes(email);
          const cnStavR = maCN ? (STAVY_NABIDEK && STAVY_NABIDEK[email]) : null;
          if (isWait && !maCN && !jeOdlozena) return true;
          if (isWait && maCN && cnStavR === 'potvrzena' && !jeOdlozena) return true;
        }

        if (filterType === 'open') {
          // V ŘEŠENÍ: stav je open
          if (stav === 'DOMLUVENÁ' || stav === 'open') return true;
        }

        if (filterType === 'done') {
          // VYŘÍZENÉ: stav je done
          if (stav === 'HOTOVO' || stav === 'done') return true;
        }

        if (filterType === 'poz') {
          // POZ: mimozáruční oprava (created_by je prázdné) NEBO zákazník s CN
          // HOTOVO se zobrazí pouze pokud je aktivní i filtr HOTOVO
          const isDone = stav === 'HOTOVO' || stav === 'done';
          if (isDone) return false;
          if (!createdBy) return true;
          if (email && EMAILS_S_CN.includes(email)) return true;
        }

        if (filterType === 'odlozene') {
          // ODLOŽENÉ: reklamace označena jako odložená (je_odlozena === 1)
          if (r.je_odlozena == 1 || r.je_odlozena === true) return true;
        }

        if (filterType === 'cekame-na-dily') {
          // ČEKÁME NA DÍLY: stav je cekame_na_dily
          const stavR = r.stav || 'wait';
          if (stavR === 'ČEKÁME NA DÍLY' || stavR === 'cekame_na_dily') return true;
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

  // Použít CACHED data pro okamžitý render (žádný await před vykreslením!)
  let unreadCountsMap = window.UNREAD_COUNTS_MAP || {};

  // Aktualizovat počet POZ (mimozáruční opravy + zákazníci s CN)
  const countPozEl = document.getElementById('count-poz');
  if (countPozEl) {
    const countPoz = items.filter(r => {
      const stav = r.stav || 'wait';
      const isDone = stav === 'HOTOVO' || stav === 'done';
      if (isDone) return false;
      const createdBy = (r.created_by || '').trim();
      if (!createdBy) return true;
      const email = (r.email || '').toLowerCase().trim();
      return email && EMAILS_S_CN.includes(email);
    }).length;
    countPozEl.textContent = `(${countPoz})`;
  }

  // Aktualizovat počet ODLOŽENÝCH
  const countOdlozeneEl = document.getElementById('count-odlozene');
  if (countOdlozeneEl) {
    const countOdlozene = items.filter(r => r.je_odlozena == 1 || r.je_odlozena === true).length;
    countOdlozeneEl.textContent = `(${countOdlozene})`;
  }

  // Aktualizovat počet ČEKÁME NA DÍLY
  const countCekamedDilyEl = document.getElementById('count-cekame-na-dily');
  if (countCekamedDilyEl) {
    const countCekameDily = items.filter(r => r.stav === 'cekame_na_dily' || r.stav === 'ČEKÁME NA DÍLY').length;
    countCekamedDilyEl.textContent = `(${countCekameDily})`;
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

  // Seřadit až TEĎ — EMAILS_S_CN a STAVY_NABIDEK jsou již načteny výše
  filtered = seraditZaznamy(filtered);

  // Přepnout třídu kontejneru podle módu zobrazení
  grid.className = VIEW_MODE === 'radky' ? 'order-list' : 'order-grid';

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
    const jeZamitnuta = stavNabidky === 'zamitnuta';

    const highlightedCustomer = SEARCH_QUERY ? highlightText(customerName, SEARCH_QUERY) : customerName;
    const highlightedAddress = SEARCH_QUERY ? highlightText(address, SEARCH_QUERY) : address;
    const highlightedProduct = SEARCH_QUERY ? highlightText(product, SEARCH_QUERY) : product;
    const highlightedOrderId = SEARCH_QUERY ? highlightText(orderId, SEARCH_QUERY) : orderId;

    const searchMatchClass = SEARCH_QUERY && matchesSearch(rec, SEARCH_QUERY) ? 'search-match' : '';
    // Přidat barevný nádech podle stavu (odložené mají fialový rámeček, čekáme na díly sdílí cn-cekame-nd třídu)
    const statusBgClass = (rec.je_odlozena == 1 || rec.je_odlozena === true)
      ? 'status-bg-odlozena'
      : (status.class === 'cekame-na-dily' ? 'cn-cekame-nd' : `status-bg-${status.class}`);
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
      } else if (jeZamitnuta) {
        cnClass = 'cn-zamitnuta';
      } else {
        cnClass = 'ma-cenovou-nabidku';
      }
    }

    // Text pro CN stav
    let cnText = 'Poslána CN';
    let cnTextClass = 'order-cn-text';
    if (jeCekameNd) {
      cnText = 'Čekáme na díly';
      cnTextClass = 'order-cn-text cekame-nd';
    } else if (jeOdsouhlasena) {
      cnText = 'Odsouhlasena';
      cnTextClass = 'order-cn-text odsouhlasena';
    } else if (jeZamitnuta) {
      cnText = 'Zamítnuta';
      cnTextClass = 'order-cn-text zamitnuta';
    }

    // Sdílený badge stavu (používá se v obou šablonách)
    // Priorita: 1) termín, 2) odloženo, 3) čekáme na díly, 4) CN, 5) stav (vč. DOMLUVENÁ/HOTOVO)
    // POSLÁNA SMS: nejnižší priorita – zobrazí se pouze u stavu NOVÁ (wait) když žádná jiná podmínka neplatí
    const maSmsBadge = (rec._sms_odeslana || rec.sms_kontakt_datum || _smsOdeslaneVRenderu.has(String(rec.id || rec.reklamace_id)));
    const stavBadge = appointmentText
      ? `<span class="order-appointment">${appointmentText}</span>`
      : ((rec.je_odlozena == 1 || rec.je_odlozena === true)
          ? `<span class="order-status-text status-odlozena">ODLOŽENO</span>`
          : (status.class === 'cekame-na-dily'
              ? `<span class="order-cn-text cekame-nd">Čekáme na díly</span>`
              : (maCenovouNabidku && !jeHotovo
                  ? `<span class="${cnTextClass}">${cnText}</span>`
                  : (maSmsBadge && status.class === 'wait'
                      ? `<span class="order-status-text status-poslana-sms">POSLÁNA SMS</span>`
                      : `<span class="order-status-text status-${status.class}">${status.text}</span>`))));

    const stavDot = `<div class="order-status status-${(jeCekameNd || status.class === 'cekame-na-dily') ? 'cekame-na-dily' : status.class}"></div>`;
    const chatBadge = `<div class="order-notes-badge ${hasUnread ? 'has-unread pulse unread-cerveny' : ''}" data-action="showNotes" data-id="${rec.id}" title="${unreadCount > 0 ? unreadCount + ' nepřečtené' : 'Chat'}">CHAT${unreadCount > 0 ? ` ${unreadCount}` : ''}</div>`;

    // Počet fotek (použito v detailu u tlačítka Galerie)
    const pocetFotek = (rec.photos || []).length;

    // U8: Checkbox pro hromadné akce
    const hromadneCheck = `<input type="checkbox" class="hromadne-check" data-id="${rec.id}" onclick="event.stopPropagation(); hromadneToggle(${rec.id}, this.checked);" style="width:16px;height:16px;cursor:pointer;display:none;" title="Vybrat">`;

    if (VIEW_MODE === 'radky') {
      return `
        <div class="order-row ${searchMatchClass} ${statusBgClass} ${cnClass}" data-action="showDetailById" data-id="${rec.id}">
          <div class="order-row-dot">${hromadneCheck}${stavDot}</div>
          <div class="order-row-id">${highlightedOrderId}</div>
          <div class="order-row-customer">${highlightedCustomer}</div>
          <div class="order-row-address">${highlightedAddress}</div>
          <div class="order-row-product">${highlightedProduct}</div>
          <div class="order-row-date">${date}</div>
          <div class="order-row-badge">${stavBadge}</div>
          <div class="order-row-chat">${chatBadge}</div>
        </div>
      `;
    }

    return `
      <div class="order-box ${searchMatchClass} ${statusBgClass} ${cnClass}" data-action="showDetailById" data-id="${rec.id}">
        <div class="order-header">
          <div class="order-number">${highlightedOrderId}</div>
          <div style="display: flex; gap: 0.4rem; align-items: center;">
            ${hromadneCheck}
            ${chatBadge}
            ${stavDot}
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
            <div class="order-detail-right">${stavBadge}</div>
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

  // Načíst čerstvá data NA POZADÍ — neblokuje render gridu
  // Guard: nespouštět background fetch pokud jsme už v background re-renderu
  if (_jeBackgroundRerender) return;
  const _filteredSnapshot = filtered;
  const _itemsSnapshot = items;
  Promise.all([
    fetch('/api/notes_api.php?action=get_unread_counts').then(r => r.json()).catch(() => null),
    fetch(`/api/nabidka_api.php?action=emaily_s_nabidkou&_t=${Date.now()}`, {
      cache: 'no-store',
      headers: { 'Cache-Control': 'no-cache' }
    }).then(r => r.json()).catch(() => null)
  ]).then(([unreadData, cnData]) => {
    let novyUnreadMap = window.UNREAD_COUNTS_MAP || {};
    if (unreadData?.status === 'success') {
      novyUnreadMap = unreadData.unread_counts || {};
      window.UNREAD_COUNTS_MAP = novyUnreadMap;
    }
    let cnDataChanged = false;
    if (cnData?.status === 'success') {
      EMAILS_S_CN = cnData.data?.emaily || cnData.emaily || [];
      STAVY_NABIDEK = cnData.data?.stavy || cnData.stavy || {};
      cnDataChanged = true;
    }
    // Aktualizovat počty a unread indikátor po načtení čerstvých dat
    const countPozElBg = document.getElementById('count-poz');
    if (countPozElBg && _itemsSnapshot) {
      const stav = _itemsSnapshot.filter(r => {
        const s = r.stav || 'wait';
        if (s === 'HOTOVO' || s === 'done') return false;
        const createdBy = (r.created_by || '').trim();
        if (!createdBy) return true;
        const email = (r.email || '').toLowerCase().trim();
        return email && EMAILS_S_CN.includes(email);
      }).length;
      countPozElBg.textContent = `(${stav})`;
    }
    const countWaitElBg = document.getElementById('count-wait');
    if (countWaitElBg && _itemsSnapshot) {
      const pocet = _itemsSnapshot.filter(r => {
        const s = r.stav || 'wait';
        if (s !== 'ČEKÁ' && s !== 'wait') return false;
        const email = (r.email || '').toLowerCase().trim();
        return !(email && EMAILS_S_CN.includes(email));
      }).length;
      countWaitElBg.textContent = `(${pocet})`;
    }
    // Po načtení CN dat překreslit seznam aby karta ukazala správný stav (Odsouhlasena atd.)
    if (cnDataChanged && _itemsSnapshot) {
      _jeBackgroundRerender = true;
      renderOrders(Utils.filterByUserRole(_itemsSnapshot));
      _jeBackgroundRerender = false;
    }

    const totalUnread = Object.values(novyUnreadMap).reduce((sum, c) => sum + c, 0);
    const unreadInd = document.getElementById('unreadNotesIndicator');
    const unreadSpan = document.getElementById('unreadNotesCount');
    if (unreadInd && unreadSpan) {
      if (totalUnread > 0) {
        unreadSpan.textContent = totalUnread;
        unreadInd.style.display = 'block';
      } else {
        unreadInd.style.display = 'none';
      }
    }
  }).catch(() => {});
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
    // Nastavit obsah modalu přes ModalDetail (modal-detail.js)
    if (window.ModalDetail) {
      window.ModalDetail.nastavitObsah(content);
    } else {
      // Fallback pokud modal-detail.js ještě není načten
      const kontejner = document.getElementById('modalContent');
      if (kontejner) kontejner.innerHTML = content;
    }

    // Otevřít modal — preferujeme ModalDetail, pak Alpine API
    if (window.ModalDetail) {
      window.ModalDetail.otevrit();
    } else if (window.detailModal && window.detailModal.open) {
      window.detailModal.open();
    } else {
      // Fallback pro zpětnou kompatibilitu
      if (window.scrollLock) window.scrollLock.enable('detail-overlay');
      document.body.classList.add('modal-open');
      document.getElementById('detailOverlay').classList.add('active');
    }
  },

  close: () => {
    // Zavřít modal — preferujeme ModalDetail, pak Alpine API
    if (window.ModalDetail) {
      window.ModalDetail.zavrit();
    } else if (window.detailModal && window.detailModal.close) {
      window.detailModal.close();
    } else {
      // Fallback pro zpětnou kompatibilitu
      const overlay = document.getElementById('detailOverlay');
      if (overlay) overlay.classList.remove('active');
      setTimeout(() => {
        document.body.classList.remove('modal-open');
        if (window.scrollLock) window.scrollLock.disable('detail-overlay');
      }, 50);
    }

    // Cleanup - reset state variables
    CURRENT_RECORD = null;
    SELECTED_DATE = null;
    SELECTED_TIME = null;
  },
  
  createHeader: (title, subtitle, backAction = 'closeDetail', backId = '') => {
    const idAttr = backId ? ` data-id="${backId}"` : '';
    return `
      <div class="modal-header">
        <button class="modal-close-btn" data-action="${backAction}"${idAttr} aria-label="Zpět" title="Zpět">×</button>
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
function createCustomerHeader(backAction = 'closeDetail', ulozitId = '') {
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
                        CURRENT_RECORD.stav === 'done' || CURRENT_RECORD.stav === 'HOTOVO' ? 'done' :
                        CURRENT_RECORD.stav === 'cekame_na_dily' || CURRENT_RECORD.stav === 'ČEKÁME NA DÍLY' ? 'cekame_na_dily' : 'wait';

  // Odložená má přednost před CN stavy (ale ne před HOTOVO)
  if ((CURRENT_RECORD.je_odlozena == 1 || CURRENT_RECORD.je_odlozena === true) && aktualniHodnota !== 'done') {
    aktualniHodnota = 'odlozena';
  } else if (maCN && aktualniHodnota !== 'done' && aktualniHodnota !== 'cekame_na_dily' && cnStav) {
    // Pokud má CN a není HOTOVO, zobrazit CN stav
    if (cnStav === 'cekame_nd') aktualniHodnota = 'cn_cekame_nd';
    else if (cnStav === 'potvrzena') aktualniHodnota = 'cn_odsouhlasena';
    else if (cnStav === 'zamitnuta') aktualniHodnota = 'cn_zamitnuta';
    else aktualniHodnota = 'cn_poslana';
  }

  // Helper: vygeneruje 1 pill tlačítko (horní řada stavů)
  // Stejná výška jako spodní CN řada: padding 0.2rem 0.3rem, border-radius 8px, font 0.6rem/600
  const pill = (stav, label, barva, textAktivni = '#000') => {
    const aktivni = aktualniHodnota === stav;
    const bg      = aktivni ? barva : 'transparent';
    const barvaText = aktivni ? textAktivni : barva;
    const border  = aktivni ? `2px solid ${barva}` : `1px solid ${barva}`;
    const cls     = aktivni ? 'workflow-pill workflow-pill--aktivni' : 'workflow-pill';
    const glowVar = aktivni ? `--pill-glow-barva:${barva};` : '';
    return `<span class="${cls}" style="flex:1;text-align:center;background:${bg};color:${barvaText};border:${border};${glowVar}cursor:pointer;padding:0.35rem 0.8rem;border-radius:10px;font-size:0.6rem;font-weight:400;display:inline-flex;align-items:center;justify-content:center;" data-action="zmenaStavuPill" data-id="${CURRENT_RECORD.id}" data-stav="${stav}" data-email="${zakaznikEmail}">${label}</span>`;
  };
  // Helper: CN pill (spodní řada) - referenční styl workflow-btn z cenova-nabidka.php
  const pillCN = (stav, label) => {
    const aktivni = aktualniHodnota === stav;
    const bg      = aktivni ? '#1a1a1a' : '#888';
    const barvaText = '#fff';
    const border  = aktivni ? '2px solid #39ff14' : '1px solid #666';
    const cls     = aktivni ? 'workflow-pill workflow-pill--aktivni' : 'workflow-pill';
    const glowVar = aktivni ? '--pill-glow-barva:rgba(57,255,20,0.6);' : '';
    return `<span class="${cls}" style="flex:1;text-align:center;white-space:nowrap;background:${bg};color:${barvaText};border:${border};${glowVar}cursor:pointer;padding:0.35rem 0.8rem;border-radius:10px;font-size:0.6rem;font-weight:400;display:inline-flex;align-items:center;justify-content:center;" data-action="zmenaStavuPill" data-id="${CURRENT_RECORD.id}" data-stav="${stav}" data-email="${zakaznikEmail}">${label}</span>`;
  };

  const stavHtml = isAdmin ? `
    <div class="stav-workflow" style="margin-top:0.1rem;">
      <div style="display:flex;gap:0.25rem;width:100%;margin-bottom:0.25rem;">
        ${pill('wait',           'NOVÁ',   '#ffdd00', '#000')}
        ${pill('open',           'DOML',   '#00e5ff', '#000')}
        ${pill('cekame_na_dily', 'DÍLY',   '#888888', '#fff')}
        ${pill('odlozena',       'ODLOŽ',  '#9b59b6', '#fff')}
        ${pill('done',           'HOTOVO', '#39ff14', '#000')}
      </div>
      <div style="display:flex;gap:0.2rem;width:100%;">
        ${pillCN('cn_poslana',      'Pos.CN')}
        ${pillCN('cn_odsouhlasena', 'Odsouh.')}
        ${pillCN('cn_cekame_nd',    'Čeká ND')}
        ${pillCN('cn_zamitnuta',    'Zamítnu.')}
      </div>
    </div>
  ` : status.text;

  const smsBylKontaktovan = CURRENT_RECORD._sms_odeslana || CURRENT_RECORD.sms_kontakt_datum;
  const smsHtml = smsBylKontaktovan ? `<span class="order-status-text status-poslana-sms" style="display:inline-flex;align-items:center;font-size:0.75rem;padding:0.2rem 0.7rem;white-space:nowrap;border-radius:6px;">POSLÁNA SMS</span>` : '';
  const ulozitBtn = ulozitId ? `<button class="detail-btn-ulozit" data-action="saveAllCustomerData" data-id="${ulozitId}" style="width:auto;padding:0.2rem 1.2rem;min-height:unset;display:inline-flex;align-items:center;">Uložit změny</button>` : '';

  const backId = backAction !== 'closeDetail' ? (CURRENT_RECORD.reklamace_id || CURRENT_RECORD.id || '') : '';
  return ModalManager.createHeader(customerName, `
    <strong>Adresa:</strong> ${address}<br>
    <strong>Termín:</strong> ${termin} ${time !== '—' ? 'v ' + time : ''}<br>
    <strong>Stav:</strong> ${stavHtml}
    ${(smsHtml || ulozitBtn) ? `<div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;margin-top:0.25rem;">${smsHtml}${ulozitBtn}</div>` : ''}
  `, backAction, backId);
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
  let dokoncenoDatum, dokoncenoData, dokoncenoCas, jeProdejce;
  let vytvorCNBtn, jeProdejceElse, technickaFunkce;

  if (isCompleted) {
    // Formátovat datum a čas dokončení
    dokoncenoDatum = record.updated_at ? formatDate(record.updated_at) : '—';
    dokoncenoData = record.updated_at ? new Date(record.updated_at) : null;
    dokoncenoCas = dokoncenoData ? `${dokoncenoData.getHours()}:${String(dokoncenoData.getMinutes()).padStart(2, '0')}` : '—';

    // Tlacitka podle role - prodejce nema pristup k technickim funkcim
    jeProdejce = CURRENT_USER && CURRENT_USER.role === 'prodejce';

    buttonsHtml = `
      <div class="detail-info-box">
        <div class="detail-info-box-title">Zakázka dokončena</div>
        <div class="detail-info-box-subtitle">Vyřízeno dne ${dokoncenoDatum} v ${dokoncenoCas}</div>
      </div>

      <div class="detail-buttons">
        <button class="detail-btn detail-btn-primary" data-action="showCustomerDetail" data-id="${record.id}">Detail zákazníka</button>
        ${!jeProdejce ? `
          <button class="detail-btn detail-btn-primary" data-action="showContactMenu" data-id="${record.id}">Kontaktovat</button>
          <button class="detail-btn detail-btn-primary" data-action="showQrPlatbaModal" data-id="${record.id}">QR Platba</button>
        ` : ''}
        ${record.original_reklamace_id ? `
          <button class="detail-btn detail-btn-primary" data-action="showHistoryPDF" data-original-id="${record.original_reklamace_id}">Historie zákazníka</button>
        ` : ''}
        <button class="detail-btn detail-btn-primary" data-action="openKnihovnaPDF" data-id="${record.id}">Knihovna PDF${(record.documents && record.documents.length > 0) ? ` (${record.documents.length})` : ''}</button>
        <button class="detail-btn detail-btn-primary" data-action="otevritGalerii" data-id="${record.id}">Galerie${(record.photos && record.photos.length > 0) ? ` (${record.photos.length})` : ''}</button>
        <button class="detail-btn detail-btn-primary" data-action="showVideoteka" data-id="${record.id}">Videotéka</button>
        <button class="detail-btn detail-btn-secondary" data-action="tiskniVytisk" data-id="${record.id}">Tisk zakázky</button>
        <button class="detail-btn" style="background:#dc3545 !important;color:#fff !important;border:none;" data-action="zalozitZnovu" data-id="${record.id}">Založit znovu</button>
        ${CURRENT_USER && CURRENT_USER.is_admin ? `
          <button class="detail-btn" style="background: #fff !important; color: #dc3545 !important; border: 1px solid #dc3545 !important; margin-top: 0.5rem;" data-action="deleteReklamace" data-id="${record.id}">Smazat reklamaci</button>
        ` : ''}
      </div>
    `;
  } else {
    // Tlacitko pro vytvoreni cenove nabidky - pouze pro adminy
    vytvorCNBtn = CURRENT_USER && CURRENT_USER.is_admin ? `
        <button class="detail-btn detail-btn-success" data-action="vytvorCenovouNabidku" data-id="${record.id}">Vytvořit CN</button>
    ` : '';

    // Prodejce nema pristup k technickim funkcim (zahajit navstevu, naplanovat termin, kontaktovat)
    jeProdejceElse = CURRENT_USER && CURRENT_USER.role === 'prodejce';

    // Tlacitka pro techniky a adminy (ne pro prodejce)
    technickaFunkce = !jeProdejceElse ? `
        <button class="detail-btn detail-btn-primary" data-action="startVisit" data-id="${record.id}">Zahájit návštěvu</button>
        <button class="detail-btn detail-btn-primary" data-action="showContactMenu" data-id="${record.id}">Kontaktovat</button>
        <button class="detail-btn detail-btn-primary" data-action="showQrPlatbaModal" data-id="${record.id}">QR Platba</button>
    ` : '';

    const jeDesktopBtn = window.innerWidth >= 769;
    const btnGap = jeDesktopBtn ? '0.15rem' : '0.4rem';
    const btnPad = jeDesktopBtn ? '0.25rem 0.6rem' : '0.4rem 0.75rem';
    const btnMinH = jeDesktopBtn ? '28px' : '34px';
    const btnFs = jeDesktopBtn ? '0.78rem' : '0.78rem';

    buttonsHtml = `
      <div class="detail-buttons">
        <button class="detail-btn detail-btn-primary" data-action="showCustomerDetail" data-id="${record.id}">Detail zákazníka</button>
        ${vytvorCNBtn}
        ${technickaFunkce}
        <button class="detail-btn detail-btn-primary" data-action="openKnihovnaPDF" data-id="${record.id}">Knihovna PDF${(record.documents && record.documents.length > 0) ? ` (${record.documents.length})` : ''}</button>
        <button class="detail-btn detail-btn-primary" data-action="otevritGalerii" data-id="${record.id}">Galerie${(record.photos && record.photos.length > 0) ? ` (${record.photos.length})` : ''}</button>
        <button class="detail-btn detail-btn-primary" data-action="showVideoteka" data-id="${record.id}">Videotéka</button>
        <button class="detail-btn detail-btn-secondary" data-action="tiskniVytisk" data-id="${record.id}">Tisk zakázky</button>
        ${CURRENT_USER && CURRENT_USER.is_admin ? `
          <button class="detail-btn" style="background: #fff !important; color: #dc3545 !important; border: 1px solid #dc3545 !important; margin-top: 0.5rem;" data-action="deleteReklamace" data-id="${record.id}">Smazat reklamaci</button>
        ` : ''}
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

// QR Platba modal přesunuta do seznam-qr-platba.js

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

  logger.log('Normalizovaná data uložena:', normalizedData);

  // BEZ ?new=true — rozpracované fotky se načtou z IndexedDB automaticky
  // Novou návštěvu (smazat vše) zahájí technik tlačítkem v photocustomer.php
  window.location.href = 'photocustomer.php';
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
    const csrfToken = await fetchCsrfToken();

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

// Kalendář přesunut do seznam-kalendar.js
// Vzdálenosti přesunuty do seznam-vzdalenost.js

// === KONTAKT ===
function showContactMenu(id) {
  const phone = CURRENT_RECORD.telefon || '';
  const address = Utils.getAddress(CURRENT_RECORD);
  const technikJmeno = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.name) ? CURRENT_USER.name : 'Technik';

  const textPrijedu = `Dobrý den,\n\njsem na cestě k vám na adresu:\n${address}\n\nohledně domluvené servisní návštěvy.\nPřijedu za zhruba 30 min podle situace v dopravě.\n\nTechnik ${technikJmeno}\nWhite Glove Servis`;

  const content = `
    ${createCustomerHeader('showDetail')}

    <div class="modal-body">
      <div class="detail-buttons">
        ${phone ? `<a href="tel:${phone}" class="detail-btn detail-btn-primary" style="text-decoration: none;">Zavolat</a>` : ''}
        ${phone ? `<button class="detail-btn detail-btn-primary" data-action="sendContactAttemptEmail" data-id="${id}" data-phone="${phone}" style="color: #ff2233; border: 2px solid #ff2233; box-shadow: 0 0 6px #ff2233, 0 0 14px rgba(255,34,51,0.7), 0 0 28px rgba(255,34,51,0.3);">NEZVEDA SMS</button>` : ''}
        <button class="detail-btn detail-btn-primary" data-action="openCalendarFromDetail" data-id="${id}">Termín návštěvy</button>
        ${address && address !== '—' ? `<a href="https://waze.com/ul?q=${encodeURIComponent(address)}&navigate=yes" class="detail-btn detail-btn-primary" style="text-decoration: none; color: #00aaff; border: 2px solid #00aaff; box-shadow: 0 0 6px #00aaff, 0 0 14px rgba(0,170,255,0.7), 0 0 28px rgba(0,170,255,0.3);" target="_blank">Navigovat (Waze)</a>` : ''}
        ${address && address !== '—' ? `<a href="https://www.google.com/maps?q=${encodeURIComponent(address)}&layer=c" class="detail-btn detail-btn-primary" style="text-decoration: none;" target="_blank">Google Street View</a>` : ''}
        ${phone ? `<a href="sms:${phone}?body=${encodeURIComponent(textPrijedu)}" class="detail-btn detail-btn-primary" style="text-decoration: none; color: #39ff14; border: 2px solid #39ff14; box-shadow: 0 0 6px #39ff14, 0 0 14px rgba(57,255,20,0.7), 0 0 28px rgba(57,255,20,0.3);">PŘIJEDU ZA 30</a>` : ''}
      </div>
    </div>
  `;

  ModalManager.show(content);
}

// Mapové funkce přesunuty do seznam-mapa.js

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
  const zadavatelId = CURRENT_RECORD.created_by || '';
  const datum_prodeje = CURRENT_RECORD.datum_prodeje || '';
  const datum_reklamace = CURRENT_RECORD.datum_reklamace || '';
  const provedeni = CURRENT_RECORD.provedeni || '';
  const barva = CURRENT_RECORD.barva || '';
  const doplnujici_info = CURRENT_RECORD.doplnujici_info || '';
  const fakturace_firma = CURRENT_RECORD.fakturace_firma || 'CZ';

  const jeAdmin = CURRENT_USER && CURRENT_USER.is_admin;
  let zadavatelSelectHtml = '';
  if (jeAdmin) {
    try {
      const odpovUzivatele = await fetch('/api/admin_users_api.php?action=zadavatel_seznam');
      const dataUzivatele = await odpovUzivatele.json();
      if (dataUzivatele.status === 'success' && Array.isArray(dataUzivatele.uzivatele)) {
        const moznosti = dataUzivatele.uzivatele.map(u => {
          const vybrany = String(u.uzivatel_id) === String(zadavatelId) ? ' selected' : '';
          const popisek = u.jmeno ? `${u.jmeno} (${u.email})` : u.email;
          return `<option value="${Utils.escapeHtml(String(u.uzivatel_id))}"${vybrany}>${Utils.escapeHtml(popisek)}</option>`;
        }).join('');
        const prazdna = !zadavatelId ? ' selected' : '';
        zadavatelSelectHtml = `<select id="edit_zadavatel" style="border: 1px solid #555; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000; width: 100%;"><option value=""${prazdna}>-- nevybráno --</option>${moznosti}</select>`;
      }
    } catch (e) {
      logger.error('Chyba při načítání uživatelů pro zadavatele:', e);
    }
  }
  if (!zadavatelSelectHtml) {
    zadavatelSelectHtml = `<input type="text" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #eee; color: #000;" value="${Utils.escapeHtml(zadavatel)}" readonly placeholder="Prodejce/Uživatel">`;
  }

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
    ${createCustomerHeader('showDetail', id)}

    <div class="modal-body detail-modal-body" style="padding: 1rem; overflow-y: auto;">

      <!-- DVOUSLOUPCOVÝ GRID - desktop: vlevo zákazník, vpravo popisy -->
      <div class="detail-dvousloupce">

        <!-- LEVÝ SLOUPEC: informace o zákazníkovi -->
        <div class="detail-sloupec-levy">

          <!-- ACCORDION PŘEPÍNAČ - pouze mobil -->
          <button id="btn-rozkrit-detail" onclick="
            const obsah = document.getElementById('rozkryvaci-detail');
            const sipka = document.getElementById('sipka-detail');
            const jeOtevreno = obsah.style.display !== 'none';
            obsah.style.display = jeOtevreno ? 'none' : 'block';
            sipka.textContent = jeOtevreno ? '▼' : '▲';
          " style="
            width: 100%; background: #1a1a1a; border: 1px solid #444;
            color: #ccc; padding: 0.6rem 1rem; border-radius: 4px;
            font-size: 0.85rem; font-weight: 600; cursor: pointer;
            display: none; justify-content: space-between; align-items: center;
            margin-bottom: 0.5rem; text-align: left;
          " class="accordion-pouze-mobil">
            <span>Informace o zákazníkovi</span>
            <span id="sipka-detail">▼</span>
          </button>

          <!-- ROZKRÝVACÍ OBSAH - na desktopu vždy viditelný, na mobilu skrytý -->
          <div id="rozkryvaci-detail" class="rozkryvaci-detail-wrap">
            <div style="background: #1a1a1a; border: none; border-radius: 4px; padding: 0.75rem; margin-bottom: 0.75rem;">
              <div style="display: grid; grid-template-columns: 110px minmax(0,1fr); gap: 0.4rem; font-size: 0.82rem; overflow: hidden;">
            <span style="color: #aaa; font-weight: 600;">Číslo objednávky:</span>
            <input type="text" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(reklamaceId)}" readonly>

            <span style="color: #aaa; font-weight: 600;">Zadavatel:</span>
            ${zadavatelSelectHtml}

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
            <div style="position:relative;">
              <input type="tel" id="edit_telefon" style="width:100%;box-sizing:border-box;border: 1px solid #333; padding: 0.25rem 1.6rem 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${phone}">
              <button onclick="
                const tel = document.getElementById('edit_telefon').value;
                if (tel) navigator.clipboard.writeText(tel).then(() => {
                  this.style.filter = 'brightness(2)';
                  setTimeout(() => { this.style.filter = ''; }, 800);
                  const tip = document.createElement('span');
                  tip.textContent = 'Zkopírováno';
                  tip.style.cssText = 'position:fixed;background:#222;color:#39ff14;font-size:0.7rem;padding:3px 8px;border-radius:4px;border:1px solid #39ff14;pointer-events:none;z-index:99999;opacity:1;transition:opacity 0.4s;';
                  const r = this.getBoundingClientRect();
                  tip.style.left = r.left + 'px';
                  tip.style.top = (r.top - 24) + 'px';
                  document.body.appendChild(tip);
                  setTimeout(() => { tip.style.opacity = '0'; setTimeout(() => tip.remove(), 400); }, 1200);
                });
              " title="Kopírovat číslo" style="position:absolute;right:4px;top:50%;transform:translateY(-50%);background:none;border:none;padding:0;cursor:pointer;line-height:1;display:flex;align-items:center;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#39ff14" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.65">
                  <rect x="9" y="9" width="13" height="13" rx="2"/>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
              </button>
            </div>

            <span style="color: #aaa; font-weight: 600;">E-mail:</span>
            <div style="position:relative;">
              <input type="email" id="edit_email" style="width:100%;box-sizing:border-box;border: 1px solid #333; padding: 0.25rem 1.6rem 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${email}">
              <button onclick="
                const val = document.getElementById('edit_email').value;
                if (val) navigator.clipboard.writeText(val).then(() => {
                  this.style.filter = 'brightness(2)';
                  setTimeout(() => { this.style.filter = ''; }, 800);
                  const tip = document.createElement('span');
                  tip.textContent = 'Zkopírováno';
                  tip.style.cssText = 'position:fixed;background:#222;color:#39ff14;font-size:0.7rem;padding:3px 8px;border-radius:4px;border:1px solid #39ff14;pointer-events:none;z-index:99999;opacity:1;transition:opacity 0.4s;';
                  const r = this.getBoundingClientRect();
                  tip.style.left = r.left + 'px';
                  tip.style.top = (r.top - 24) + 'px';
                  document.body.appendChild(tip);
                  setTimeout(() => { tip.style.opacity = '0'; setTimeout(() => tip.remove(), 400); }, 1200);
                });
              " title="Kopírovat email" style="position:absolute;right:4px;top:50%;transform:translateY(-50%);background:none;border:none;padding:0;cursor:pointer;line-height:1;display:flex;align-items:center;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#39ff14" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.65">
                  <rect x="9" y="9" width="13" height="13" rx="2"/>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
              </button>
            </div>

            <span style="color: #aaa; font-weight: 600;">Adresa:</span>
            <div style="position:relative;">
              <input type="text" id="edit_adresa" style="width:100%;box-sizing:border-box;border: 1px solid #333; padding: 0.25rem 1.6rem 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${address}">
              <button onclick="
                const val = document.getElementById('edit_adresa').value;
                if (val) navigator.clipboard.writeText(val).then(() => {
                  this.style.filter = 'brightness(2)';
                  setTimeout(() => { this.style.filter = ''; }, 800);
                  const tip = document.createElement('span');
                  tip.textContent = 'Zkopírováno';
                  tip.style.cssText = 'position:fixed;background:#222;color:#39ff14;font-size:0.7rem;padding:3px 8px;border-radius:4px;border:1px solid #39ff14;pointer-events:none;z-index:99999;opacity:1;transition:opacity 0.4s;';
                  const r = this.getBoundingClientRect();
                  tip.style.left = r.left + 'px';
                  tip.style.top = (r.top - 24) + 'px';
                  document.body.appendChild(tip);
                  setTimeout(() => { tip.style.opacity = '0'; setTimeout(() => tip.remove(), 400); }, 1200);
                });
              " title="Kopírovat adresu" style="position:absolute;right:4px;top:50%;transform:translateY(-50%);background:none;border:none;padding:0;cursor:pointer;line-height:1;display:flex;align-items:center;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#39ff14" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.65">
                  <rect x="9" y="9" width="13" height="13" rx="2"/>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
              </button>
            </div>

            <span style="color: #aaa; font-weight: 600;">Model:</span>
            <input type="text" id="edit_model" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${product}">

            <span style="color: #aaa; font-weight: 600;">Provedení:</span>
            <input type="text" id="edit_provedeni" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(provedeni)}" placeholder="Látka / Kůže">

            <span style="color: #aaa; font-weight: 600;">Barva:</span>
            <input type="text" id="edit_barva" style="border: 1px solid #333; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.9rem; background: #fff; color: #000;" value="${Utils.escapeHtml(barva)}">
              </div>
            </div>
          </div>

        </div><!-- /levy sloupec -->

        <!-- PRAVÝ SLOUPEC: popis problému + doplňující informace -->
        <div class="detail-sloupec-pravy">

          <div style="margin-bottom: 0.75rem;">
            <label style="display: block; color: #aaa; font-weight: 600; font-size: 0.75rem; margin-bottom: 0.3rem; text-transform: none; letter-spacing: normal;">Popis problému od zákazníka:</label>
            <textarea id="edit_popis_problemu" class="detail-textarea-popis"
                      style="width: 100%; border: 1px solid #999; padding: 0.6rem; border-radius: 3px; background: #f0f0f0; color: #111; -webkit-text-fill-color: #111; font-size: ${window.innerWidth <= 768 ? '16px' : '0.85rem'}; resize: none; font-family: inherit; overflow: hidden;"
                      placeholder="Zadejte popis problému od zákazníka"
                      oninput="this.style.setProperty('height','auto','important');this.style.setProperty('height',(this.scrollHeight+12)+'px','important')">${Utils.escapeHtml(description)}</textarea>
          </div>

          <div style="margin-bottom: 0.75rem;">
            <label style="display: block; color: #aaa; font-weight: 600; font-size: 0.75rem; margin-bottom: 0.3rem; text-transform: none; letter-spacing: normal;">Doplňující informace od prodejce:</label>
            <textarea id="edit_doplnujici_info" class="detail-textarea-popis"
                      style="width: 100%; border: 1px solid #999; padding: 0.6rem; border-radius: 3px; background: #f0f0f0; color: #111; -webkit-text-fill-color: #111; font-size: ${window.innerWidth <= 768 ? '16px' : '0.85rem'}; resize: none; font-family: inherit; overflow: hidden;"
                      placeholder="Zadejte doplňující informace od prodejce"
                      oninput="this.style.setProperty('height','auto','important');this.style.setProperty('height',(this.scrollHeight+12)+'px','important')">${Utils.escapeHtml(doplnujici_info)}</textarea>
          </div>

        </div><!-- /pravy sloupec -->

      </div><!-- /dvousloupce -->

    </div>
  `;

  ModalManager.show(content);

  // Rozšířit modal pro detail zákazníka na desktopu (dvousloupcový layout)
  const obsahDetailZakaznika = document.querySelector('#detailOverlay .modal-content');
  if (obsahDetailZakaznika) {
    const sirka = window.innerWidth > 768 ? '760px' : '95vw';
    obsahDetailZakaznika.style.setProperty('max-width', sirka, 'important');
    obsahDetailZakaznika.style.setProperty('width', '100%', 'important');
  }

  // Auto-resize + nastavení fontu podle zařízení
  // iOS FIX: double requestAnimationFrame zajistí, že flexbox layout je dokončen
  // před měřením scrollHeight — bez toho vrátí iOS nesprávnou (příliš malou) hodnotu.
  const nastavVyskuTextarey = (ta) => {
    if (!ta) return;
    const jeMobil = window.innerWidth <= 768;
    ta.style.setProperty('font-size', jeMobil ? '14px' : '0.85rem', 'important');
    ta.style.setProperty('background', '#f0f0f0', 'important');
    ta.style.setProperty('color', '#111', 'important');
    ta.style.setProperty('-webkit-text-fill-color', '#111', 'important');
    ta.style.setProperty('min-height', '0', 'important');
    ta.style.setProperty('height', 'auto', 'important');
    void ta.offsetHeight; // vynutí synchronní reflow
    const vyska = ta.scrollHeight;
    if (vyska > 20) {
      ta.style.setProperty('height', (vyska + 12) + 'px', 'important');
    }
  };

  // Primární průchod: double RAF zajistí settled layout na iOS (400ms + 2 framy)
  setTimeout(() => {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        document.querySelectorAll('#edit_doplnujici_info, #edit_popis_problemu').forEach(nastavVyskuTextarey);
      });
    });
  }, 400);

  // Záložní průchod: opraví špatné měření z prvního průchodu na pomalých iOS zařízeních
  setTimeout(() => {
    document.querySelectorAll('#edit_doplnujici_info, #edit_popis_problemu').forEach(ta => {
      if (!ta) return;
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          nastavVyskuTextarey(ta);
        });
      });
    });
  }, 900);
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

  // Tlačítko X (zavřít) - fixní v pravém horním rohu
  const btnXPdf = document.createElement('button');
  btnXPdf.innerHTML = '&times;';
  btnXPdf.style.cssText = 'position: fixed; top: 12px; right: 12px; z-index: 10010; width: 30px; height: 30px; max-width: 30px; max-height: 30px; aspect-ratio: 1/1; border-radius: 50%; background: rgba(180,180,180,0.35); color: #cc0000; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; overflow: hidden; flex-shrink: 0;';
  btnXPdf.onclick = () => overlay.remove();

  // === HLAVIČKA (fixní nahoře) ===
  const header = document.createElement('div');
  header.style.cssText = `
    flex-shrink: 0; padding: 12px 16px;
    background: #222; color: white;
    display: flex; align-items: center;
    border-bottom: 1px solid #444;
  `;
  header.innerHTML = `
    <div>
      <div style="font-weight: 600; font-size: 1rem;">${titulek}</div>
      <div style="font-size: 0.75rem; opacity: 0.7;">ID: ${claimId || '-'}</div>
    </div>
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

  footer.appendChild(btnStahnout);
  footer.appendChild(btnSdilet);

  // Sestavení
  overlay.appendChild(btnXPdf);
  overlay.appendChild(header);
  overlay.appendChild(pdfContainer);
  overlay.appendChild(footer);

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
    position: relative; width: 100%; max-width: 600px; max-height: 90vh;
    background: #1a1a1a; border-radius: 12px;
    display: flex; flex-direction: column;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
  `;

  // Tlačítko X (zavřít) - fixní v pravém horním rohu
  const btnXKnihovna = document.createElement('button');
  btnXKnihovna.innerHTML = '&times;';
  btnXKnihovna.style.cssText = 'position: absolute; top: 8px; right: 8px; z-index: 10; width: 30px; height: 30px; max-width: 30px; max-height: 30px; aspect-ratio: 1/1; border-radius: 50%; background: rgba(180,180,180,0.35); color: #cc0000; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; overflow: hidden; flex-shrink: 0;';

  // === HLAVIČKA ===
  const header = document.createElement('div');
  header.style.cssText = `
    flex-shrink: 0; padding: 12px 16px;
    background: #222; color: white;
    display: flex; align-items: center;
    border-bottom: 1px solid #444;
    border-radius: 12px 12px 0 0;
  `;
  header.innerHTML = `
    <div>
      <div style="font-weight: 600; font-size: 1rem;">KNIHOVNA PDF</div>
      <div style="font-size: 0.75rem; opacity: 0.7;">ID: ${claimId}</div>
    </div>
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
  `;

  // Sestavení - elementy jdou do modalBox
  modalBox.appendChild(header);
  modalBox.appendChild(content);
  modalBox.appendChild(footer);
  modalBox.appendChild(btnXKnihovna);
  overlay.appendChild(modalBox);

  // Event handlery
  const zavritKnihovnu = () => overlay.remove();
  btnXKnihovna.onclick = zavritKnihovnu;

  // Zavřít kliknutím mimo modal
  overlay.onclick = (e) => {
    if (e.target === overlay) zavritKnihovnu();
  };

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
      const csrfToken = await fetchCsrfToken();
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
                 onfocus="this.style.borderColor='var(--wgs-gray-66)'" onblur="this.style.borderColor='var(--wgs-gray-44)'">
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
      const csrfToken = await fetchCsrfToken();
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

      const csrfToken = await fetchCsrfToken();
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

function showPhotoFullscreen(photoUrl, zdrojovyElement) {
  // Sestavit seznam všech fotek ze stejné galerie
  let vsechnyFotky = [photoUrl];
  let aktualniIndex = 0;

  if (zdrojovyElement) {
    const rodic = zdrojovyElement.closest('.foto-wrapper')?.parentElement;
    if (rodic) {
      const imgs = Array.from(rodic.querySelectorAll('img[data-action="showPhotoFullscreen"]'));
      if (imgs.length > 1) {
        vsechnyFotky = imgs.map(i => i.getAttribute('data-url') || i.src);
        aktualniIndex = imgs.indexOf(zdrojovyElement);
        if (aktualniIndex < 0) aktualniIndex = 0;
      }
    }
  }

  // Stav zoomu a posunu
  let zoomUroven = 1;
  let posunX = 0, posunY = 0;
  let tazeni = false, bylTazen = false;
  let tazeniStartX = 0, tazeniStartY = 0;

  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.95);z-index:10010;display:flex;align-items:center;justify-content:center;overflow:hidden;';

  const obalImg = document.createElement('div');
  obalImg.style.cssText = 'width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;';

  const img = document.createElement('img');
  img.alt = 'Zvětšená fotka reklamace';
  img.src = vsechnyFotky[aktualniIndex];
  img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;border-radius:4px;user-select:none;transform-origin:center center;cursor:zoom-in;';

  const aktualizujTransform = (prechod) => {
    img.style.transition = prechod ? 'transform 0.2s ease' : 'none';
    img.style.transform = `scale(${zoomUroven}) translate(${posunX / zoomUroven}px, ${posunY / zoomUroven}px)`;
    img.style.cursor = zoomUroven > 1 ? (tazeni ? 'grabbing' : 'grab') : 'zoom-in';
  };

  const resetujZoom = () => {
    zoomUroven = 1; posunX = 0; posunY = 0;
    aktualizujTransform(true);
  };

  // Kolečko myši pro zoom
  obalImg.addEventListener('wheel', (e) => {
    e.preventDefault();
    const koef = e.deltaY < 0 ? 1.2 : 1 / 1.2;
    zoomUroven = Math.min(5, Math.max(1, zoomUroven * koef));
    if (zoomUroven <= 1) resetujZoom();
    else aktualizujTransform(false);
  }, { passive: false });

  // Klik na obrázek — zoom in/out
  img.addEventListener('click', (e) => {
    e.stopPropagation();
    if (bylTazen) { bylTazen = false; return; }
    if (zoomUroven === 1) { zoomUroven = 2; aktualizujTransform(true); }
    else resetujZoom();
  });

  // Drag pro posun při zoom
  img.addEventListener('mousedown', (e) => {
    if (zoomUroven <= 1) return;
    e.preventDefault();
    tazeni = true; bylTazen = false;
    tazeniStartX = e.clientX - posunX;
    tazeniStartY = e.clientY - posunY;
    img.style.cursor = 'grabbing';
  });

  const onMouseMove = (e) => {
    if (!tazeni) return;
    const nx = e.clientX - tazeniStartX;
    const ny = e.clientY - tazeniStartY;
    if (Math.abs(nx - posunX) > 3 || Math.abs(ny - posunY) > 3) bylTazen = true;
    posunX = nx; posunY = ny;
    aktualizujTransform(false);
  };

  const onMouseUp = () => {
    tazeni = false;
    if (zoomUroven > 1) img.style.cursor = 'grab';
  };

  document.addEventListener('mousemove', onMouseMove);
  document.addEventListener('mouseup', onMouseUp);

  // Touch pinch-to-zoom a posun
  let dotykZoomZacatek = 1, pocatecniRoztaz = 0;
  let dotyk1StartX = 0, dotyk1StartY = 0;

  obalImg.addEventListener('touchstart', (e) => {
    if (e.touches.length === 2) {
      e.preventDefault();
      pocatecniRoztaz = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      dotykZoomZacatek = zoomUroven;
    } else if (e.touches.length === 1) {
      dotyk1StartX = e.touches[0].clientX - posunX;
      dotyk1StartY = e.touches[0].clientY - posunY;
    }
  }, { passive: false });

  obalImg.addEventListener('touchmove', (e) => {
    if (e.touches.length === 2) {
      e.preventDefault();
      const aktRoztaz = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      zoomUroven = Math.min(5, Math.max(1, dotykZoomZacatek * (aktRoztaz / pocatecniRoztaz)));
      aktualizujTransform(false);
    } else if (e.touches.length === 1 && zoomUroven > 1) {
      e.preventDefault();
      posunX = e.touches[0].clientX - dotyk1StartX;
      posunY = e.touches[0].clientY - dotyk1StartY;
      aktualizujTransform(false);
    }
  }, { passive: false });

  obalImg.addEventListener('touchend', () => {
    if (zoomUroven < 1.05) resetujZoom();
  });

  // Počítadlo fotek (jen pokud víc než 1)
  const pocitadlo = document.createElement('div');
  pocitadlo.style.cssText = 'position:absolute;top:1rem;left:50%;transform:translateX(-50%);color:#fff;font-size:0.9rem;opacity:0.7;pointer-events:none;z-index:1;';
  const aktualizujPocitadlo = () => {
    pocitadlo.textContent = vsechnyFotky.length > 1 ? `${aktualniIndex + 1} / ${vsechnyFotky.length}` : '';
  };
  aktualizujPocitadlo();

  const prejitNa = (novyIndex) => {
    aktualniIndex = (novyIndex + vsechnyFotky.length) % vsechnyFotky.length;
    img.src = vsechnyFotky[aktualniIndex];
    resetujZoom();
    aktualizujPocitadlo();
  };

  // Tlačítko zavřít
  const btnZavrit = document.createElement('button');
  btnZavrit.textContent = 'x';
  btnZavrit.style.cssText = 'position:absolute;top:1rem;right:1rem;background:transparent;border:none;color:#fff;font-size:1.5rem;cursor:pointer;opacity:0.7;padding:0.25rem 0.5rem;line-height:1;z-index:1;';
  btnZavrit.onclick = (e) => { e.stopPropagation(); zavrit(); };

  // Šipky navigace (jen pokud víc než 1 fotka)
  const tlacitkoSipky = (text, smer) => {
    const btn = document.createElement('button');
    btn.textContent = text;
    btn.style.cssText = `position:absolute;top:50%;${smer}:1rem;transform:translateY(-50%);background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.3);color:#fff;font-size:1.8rem;cursor:pointer;padding:0.5rem 0.9rem;border-radius:4px;line-height:1;user-select:none;z-index:1;`;
    btn.onmouseenter = () => { btn.style.background = 'rgba(255,255,255,0.25)'; };
    btn.onmouseleave = () => { btn.style.background = 'rgba(255,255,255,0.1)'; };
    return btn;
  };

  let btnPrev = null, btnNext = null;
  if (vsechnyFotky.length > 1) {
    btnPrev = tlacitkoSipky('\u2039', 'left');
    btnPrev.onclick = (e) => { e.stopPropagation(); prejitNa(aktualniIndex - 1); };

    btnNext = tlacitkoSipky('\u203a', 'right');
    btnNext.onclick = (e) => { e.stopPropagation(); prejitNa(aktualniIndex + 1); };
  }

  // Klik na pozadí zavře (jen bez zoomu)
  overlay.onclick = (e) => {
    if (e.target === overlay && zoomUroven === 1) zavrit();
  };

  const zavrit = () => {
    document.removeEventListener('keydown', klavesyHandler);
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
    overlay.remove();
  };

  const klavesyHandler = (e) => {
    if (e.key === 'Escape') zavrit();
    else if (e.key === 'ArrowLeft' && vsechnyFotky.length > 1) prejitNa(aktualniIndex - 1);
    else if (e.key === 'ArrowRight' && vsechnyFotky.length > 1) prejitNa(aktualniIndex + 1);
    else if (e.key === '+' || e.key === '=') { zoomUroven = Math.min(5, zoomUroven * 1.3); aktualizujTransform(true); }
    else if (e.key === '-') { zoomUroven = Math.max(1, zoomUroven / 1.3); if (zoomUroven <= 1.05) resetujZoom(); else aktualizujTransform(true); }
  };
  document.addEventListener('keydown', klavesyHandler);

  obalImg.appendChild(img);
  overlay.appendChild(pocitadlo);
  overlay.appendChild(obalImg);
  overlay.appendChild(btnZavrit);
  if (btnPrev) overlay.appendChild(btnPrev);
  if (btnNext) overlay.appendChild(btnNext);
  document.body.appendChild(overlay);
}

function showTextOverlay(fieldName) {
  if (!CURRENT_RECORD) return;

  const nadpis = fieldName === 'popis_problemu' ? 'Popis problému' : 'Doplňující informace';
  const currentText = CURRENT_RECORD[fieldName] || '';

  const overlay = document.createElement('div');
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 2rem;';

  const contentBox = document.createElement('div');
  contentBox.style.cssText = 'position:relative;background: white; padding: 1.5rem; border-radius: 6px; max-width: 700px; width: 100%; max-height: 85vh; display: flex; flex-direction: column;';
  contentBox.onclick = (e) => e.stopPropagation();

  const btnXText = document.createElement('button');
  btnXText.innerHTML = '&times;';
  btnXText.style.cssText = 'position:absolute;top:10px;right:10px;z-index:1;width:28px;height:28px;border-radius:50%;background:rgba(180,180,180,0.25);color:#cc0000;border:none;font-size:1.2rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;';
  btnXText.onclick = () => { overlay.remove(); document.removeEventListener('keydown', escTextHandler); };
  contentBox.appendChild(btnXText);

  const header = document.createElement('div');
  header.style.cssText = 'font-weight: 600; font-size: 1rem; color: #1a1a1a; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #dee2e6; padding-right: 2rem;';
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
      const csrfToken = await fetchCsrfToken();
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
  cancelBtn.onclick = () => { overlay.remove(); document.removeEventListener('keydown', escTextHandler); };

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
      document.removeEventListener('keydown', escTextHandler);
    }
  };

  const escTextHandler = (e) => {
    if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', escTextHandler); }
  };
  document.addEventListener('keydown', escTextHandler);

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

  // Admin může měnit zadavatele (created_by)
  const selectZadavatel = document.getElementById('edit_zadavatel');
  if (selectZadavatel && CURRENT_USER && CURRENT_USER.is_admin) {
    data.created_by = selectZadavatel.value;
  }

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
    const csrfToken = await fetchCsrfToken();

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
    const csrfToken = await fetchCsrfToken();

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

// === SYSTÉM POZNÁMEK - Lazy-loaded modul (Step 167+168) ===
// Kód přesunut do assets/js/seznam-poznamky.js
// Modul se stáhne automaticky při prvním kliknutí na CHAT.

let _szLoadingPromise = null;

function _nacistModulPoznamek() {
  if (_szLoadingPromise) return _szLoadingPromise;
  _szLoadingPromise = new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = '/assets/js/seznam-poznamky.js';
    s.onload = resolve;
    s.onerror = () => reject(new Error('Nepodařilo se načíst modul poznámek'));
    document.head.appendChild(s);
  });
  return _szLoadingPromise;
}

async function showNotes(recordOrId) {
  if (!window._szPoznamkyNacten) {
    try {
      await _nacistModulPoznamek();
    } catch (e) {
      logger.error('[Notes] Chyba načítání modulu:', e);
      if (typeof wgsToast !== 'undefined') wgsToast.error('Chyba při načítání modulu poznámek');
      return;
    }
  }
  window._showNotes(recordOrId);
}

// ---- PLACEHOLDER FUNKCE ----
// Zástupné funkce — přepíší se po načtení seznam-poznamky.js.
// Existují proto, aby typeof X === 'function' vrátilo true
// v EMERGENCY listeneru ještě před načtením modulu.
// Modul se načte automaticky při prvním volání showNotes().

async function getNotes(orderId) {}
async function addNote(orderId, text, audioBlob) {}
async function deleteNote(noteId, orderId) {}
async function markNotesAsRead(orderId) {}
async function saveNewNote(orderId) {}
function closeNotesModal() {}
async function startRecording(orderId) {}
function stopRecording() {}
function releaseMicrophone() {}
function deleteAudioPreview() {}
function formatDateTime(s) {
  if (!s) return '';
  const d = new Date(s);
  return d.toLocaleString('cs-CZ', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// === UTILITY ===
function getStatus(stav) {
  const statusMap = {
    'ČEKÁ': { class: 'wait', text: 'NOVÁ' },
    'wait': { class: 'wait', text: 'NOVÁ' },
    'DOMLUVENÁ': { class: 'open', text: 'DOMLUVENÁ' },
    'open': { class: 'open', text: 'DOMLUVENÁ' },
    'HOTOVO': { class: 'done', text: 'HOTOVO' },
    'done': { class: 'done', text: 'HOTOVO' },
    'ČEKÁME NA DÍLY': { class: 'cekame-na-dily', text: 'Čekáme na díly' },
    'cekame_na_dily': { class: 'cekame-na-dily', text: 'Čekáme na díly' }
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
      'deleteAudioPreview', 'closeErrorModal', 'filterUnreadNotes', 'otevritVyberFotek',
      'zmenaStavuPill', 'zpetDoDetailu'
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
    // Handler pro změnu stavu zakázky (admin dropdown - fallback)
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

// Admin akce přesunuty do seznam-admin.js

// Fototéka přesunuta do seznam-fototeka.js

// === ODESLÁNÍ POKUSU O KONTAKT (EMAIL + SMS) ===

/**
 * Označí kartu i řádek zákazníka jako "POSLÁNA SMS" v DOM i v cache
 * @param {string|number} reklamaceId - ID reklamace
 */
// Klíč v localStorage pro persistenci SMS příznaků přes page reload
const _SMS_STORAGE_KEY = 'wgs_sms_odeslane';

function _ulozSmsDoStorage(reklamaceId) {
  try {
    const ulozene = JSON.parse(localStorage.getItem(_SMS_STORAGE_KEY) || '[]');
    const idStr = String(reklamaceId);
    if (!ulozene.includes(idStr)) {
      ulozene.push(idStr);
      localStorage.setItem(_SMS_STORAGE_KEY, JSON.stringify(ulozene));
    }
  } catch (e) { /* localStorage nedostupný */ }
}

function _nactiSmsZeStorage() {
  try {
    return new Set(JSON.parse(localStorage.getItem(_SMS_STORAGE_KEY) || '[]'));
  } catch (e) { return new Set(); }
}

function _oznacPoslanaSms(reklamaceId) {
  const badgeHtml = '<span class="order-status-text status-poslana-sms">POSLÁNA SMS</span>';

  // Aktualizovat kartu (VIEW_MODE = karty)
  const karta = document.querySelector(`.order-box[data-id="${reklamaceId}"]`);
  if (karta) {
    const stavBadgeEl = karta.querySelector('.order-status-text, .order-appointment, .order-cn-text');
    if (stavBadgeEl) {
      stavBadgeEl.outerHTML = badgeHtml;
    }
  }

  // Aktualizovat řádek (VIEW_MODE = radky)
  const radek = document.querySelector(`.order-row[data-id="${reklamaceId}"]`);
  if (radek) {
    const stavBadgeEl = radek.querySelector('.order-status-text, .order-appointment, .order-cn-text');
    if (stavBadgeEl) {
      stavBadgeEl.outerHTML = badgeHtml;
    }
  }

  // Aktualizovat cache - aby badge přežil re-render
  const zaznam = WGS_DATA_CACHE.find(x => x.id == reklamaceId || x.reklamace_id == reklamaceId);
  if (zaznam) {
    zaznam._sms_odeslana = true;
    zaznam.sms_kontakt_datum = new Date().toISOString(); // Okamžitě viditelné pro všechny
  }

  // Uložit do localStorage - aby badge přežil page reload
  _ulozSmsDoStorage(reklamaceId);
}

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
      wgsToast.error('Záznam nenalezen');
      return;
    }

    // Zobrazit loading overlay
    showLoading('Odesílám email zákazníkovi... Prosím čekejte');

    // Získat CSRF token
    const csrfToken = await fetchCsrfToken();

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

      // Označit kartu/řádek jako "POSLÁNA SMS" v DOM i cache
      _oznacPoslanaSms(reklamaceId);

      // Zavřít detail modal
      closeDetail();

      // Překreslit seznam karet aby byl badge vidět ihned (i na mobilu)
      renderOrders();

      // Zobrazit neonový toast (WGSToast pro důležité akce)
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit('Email odeslán zákazníkovi', { titulek: 'WGS' });
      } else {
        wgsToast.success('Email odeslán zákazníkovi');
      }

      // DŮLEŽITÉ: SMS text je nyní generován na serveru ze stejných dat jako email
      // To znamená, že změna v emailové šabloně automaticky ovlivní i SMS
      const smsText = data.sms_text || `Dobrý den,\n\npokoušeli jsme se Vás kontaktovat.\n\nNepodařilo se nám Vás zastihnout. Zavolejte prosím zpět na tel. +420 725 965 826.\n\nDěkujeme,\nWhite Glove Servis`;

      // Počkat 2 sekundy, aby technik viděl potvrzení, pak otevřít SMS aplikaci
      setTimeout(() => {
        const encodedText = encodeURIComponent(smsText);
        window.location.href = `sms:${telefon}?body=${encodedText}`;
      }, 2000);

    } else {
      logger.error('⚠ Chyba při odesílání emailu:', data.error || data.message);
      wgsToast.error(data.error || 'Chyba při odesílání emailu');
    }

  } catch (chyba) {
    logger.error('Chyba při odesílání kontaktního emailu:', chyba);
    wgsToast.error('Nepodařilo se odeslat email');
    // Skrýt loading overlay i při chybě
    hideLoading();
  }
}

// Videotéka přesunuta do seznam-videoteka.js


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
// window.zmenitStavZakazky exportováno v seznam-admin.js

// ==========================================
// GALERIE — fototéka zakázky
// ==========================================
function openGalerie(id) {
  const reklamaceId = id || (CURRENT_RECORD && CURRENT_RECORD.id);
  if (!reklamaceId) return;

  const zaznam = WGS_DATA_CACHE[reklamaceId] || CURRENT_RECORD;
  const fotky = (zaznam && zaznam.photos || []).filter(f => f && (f.photo_path || f.url || f.path));

  const renderGrid = (seznam) => seznam.map((f, i) => {
    const photoPath = typeof f === 'object' ? (f.photo_path || f.url || f.path) : f;
    const photoId   = typeof f === 'object' ? f.id : null;
    const escapedUrl = photoPath.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");
    return `
      <div class="foto-wrapper" style="position:relative;aspect-ratio:1;min-width:0;">
        <img src='${photoPath}'
             style='width:100%;height:100%;object-fit:cover;border:1px solid #444;cursor:pointer;border-radius:4px;display:block;'
             alt='Fotka ${i + 1}'
             loading="lazy"
             data-action="showPhotoFullscreen"
             data-url="${escapedUrl}">
        ${photoId ? `
          <button class="foto-delete-btn"
                  data-action="smazatFotku"
                  data-photo-id="${photoId}"
                  data-url="${escapedUrl}"
                  title="Smazat fotku">x</button>
        ` : ''}
      </div>
    `;
  }).join('');

  ModalManager.show(`
    <div style="padding:1rem;">
      <div style="background:#1a1a1a;border-radius:6px;padding:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
          <label style="color:#aaa;font-weight:600;font-size:0.85rem;" id="fototeka-nadpis">Fototeka (${fotky.length})</label>
          <button type="button"
                  data-action="otevritVyberFotek"
                  data-id="${reklamaceId}"
                  style="background:#333;color:#fff;border:1px solid #555;padding:0.4rem 0.8rem;border-radius:4px;font-size:0.8rem;cursor:pointer;">
            Přidat fotky
          </button>
        </div>
        <input type="file"
               id="fototeka-input-${reklamaceId}"
               accept="image/*"
               multiple
               style="display:none;"
               data-reklamace-id="${reklamaceId}">
        <div id="fototeka-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;min-height:60px;">
          ${fotky.length > 0 ? renderGrid(fotky) : `<p style="color:#666;font-size:0.85rem;margin:0;padding:0.5rem 0;">Žádné fotografie</p>`}
        </div>
        <div id="fototeka-nahravani" style="display:none;margin-top:0.75rem;padding:0.5rem;background:#222;border-radius:4px;">
          <p style="color:#aaa;font-size:0.8rem;margin:0;">Nahrávání fotek...</p>
          <div style="background:#333;height:4px;border-radius:2px;margin-top:0.5rem;overflow:hidden;">
            <div id="fototeka-progress" style="background:#fff;height:100%;width:0%;transition:width 0.3s;"></div>
          </div>
        </div>
      </div>
    </div>
  `);
}
window.openGalerie = openGalerie;

// Hromadné akce přesunuty do seznam-hromadne.js
