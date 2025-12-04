/**
 * Jest Setup File
 * Step 153: Konfigurace testovacího prostředí pro JavaScript testy
 */

// Mock pro console.log/warn/error (abychom neviděli spam v testech)
// Zakomentujte pokud potřebujete debug output
// global.console = {
//   ...console,
//   log: jest.fn(),
//   warn: jest.fn(),
//   error: jest.fn(),
// };

// Mock pro window.logger (náš custom logger)
global.logger = {
  log: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
  debug: jest.fn(),
  info: jest.fn()
};

// Mock pro localStorage
const localStorageMock = (() => {
  let store = {};
  return {
    getItem: jest.fn(key => store[key] || null),
    setItem: jest.fn((key, value) => { store[key] = value?.toString(); }),
    removeItem: jest.fn(key => { delete store[key]; }),
    clear: jest.fn(() => { store = {}; }),
    get length() { return Object.keys(store).length; },
    key: jest.fn(index => Object.keys(store)[index] || null)
  };
})();

Object.defineProperty(global, 'localStorage', {
  value: localStorageMock
});

// Mock pro sessionStorage
Object.defineProperty(global, 'sessionStorage', {
  value: localStorageMock
});

// Mock pro fetch
global.fetch = jest.fn(() =>
  Promise.resolve({
    ok: true,
    json: () => Promise.resolve({ status: 'success' }),
    text: () => Promise.resolve(''),
    blob: () => Promise.resolve(new Blob())
  })
);

// Mock pro showNotif (notifikace)
global.showNotif = jest.fn();

// Mock pro FormData
global.FormData = class FormData {
  constructor() {
    this.data = new Map();
  }
  append(key, value) {
    this.data.set(key, value);
  }
  get(key) {
    return this.data.get(key);
  }
  has(key) {
    return this.data.has(key);
  }
  delete(key) {
    this.data.delete(key);
  }
  entries() {
    return this.data.entries();
  }
};

// Reset mocks před každým testem
beforeEach(() => {
  jest.clearAllMocks();
  localStorage.clear();
});

// Čištění po všech testech
afterAll(() => {
  jest.restoreAllMocks();
});
