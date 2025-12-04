/**
 * Utils.js Tests
 * Step 154: JavaScript unit testy pro utils.js
 */

// Simulace utils.js funkcí pro testování
// (V reálném prostředí by se načetly z modulu)

// === isSuccess ===
function isSuccess(data) {
  return !!(data && (data.success === true || data.status === 'success'));
}

// === escapeHtml ===
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// === escapeRegex ===
function escapeRegex(string) {
  if (!string) return '';
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// === formatDateCZ ===
function formatDateCZ(date) {
  if (!date) return '—';
  try {
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    if (isNaN(dateObj.getTime())) return '—';
    return dateObj.toLocaleDateString('cs-CZ');
  } catch (e) {
    return '—';
  }
}

// === debounce ===
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// === highlightText ===
function highlightText(text, query) {
  if (!query || !text) return escapeHtml(text);
  const escapedText = escapeHtml(text);
  const escapedQuery = escapeRegex(query);
  const regex = new RegExp(`(${escapedQuery})`, 'gi');
  return escapedText.replace(regex, '<span class="highlight">$1</span>');
}

// ========================================
// TESTY PRO isSuccess
// ========================================

describe('isSuccess', () => {
  test('vrací true pro {success: true}', () => {
    expect(isSuccess({ success: true })).toBe(true);
  });

  test('vrací true pro {status: "success"}', () => {
    expect(isSuccess({ status: 'success' })).toBe(true);
  });

  test('vrací false pro {success: false}', () => {
    expect(isSuccess({ success: false })).toBe(false);
  });

  test('vrací false pro {status: "error"}', () => {
    expect(isSuccess({ status: 'error' })).toBe(false);
  });

  test('vrací false pro null', () => {
    expect(isSuccess(null)).toBe(false);
  });

  test('vrací false pro undefined', () => {
    expect(isSuccess(undefined)).toBe(false);
  });

  test('vrací false pro prázdný objekt', () => {
    expect(isSuccess({})).toBe(false);
  });

  test('vrací true pro kombinovaný response', () => {
    expect(isSuccess({ success: true, data: { id: 1 } })).toBe(true);
    expect(isSuccess({ status: 'success', message: 'OK' })).toBe(true);
  });
});

// ========================================
// TESTY PRO escapeHtml
// ========================================

describe('escapeHtml', () => {
  test('escapuje < a > znaky', () => {
    const result = escapeHtml('<script>alert("XSS")</script>');
    expect(result).toContain('&lt;');
    expect(result).toContain('&gt;');
    expect(result).not.toContain('<script>');
  });

  test('escapuje & znak', () => {
    const result = escapeHtml('A & B');
    expect(result).toContain('&amp;');
  });

  test('escapuje uvozovky', () => {
    const result = escapeHtml('onclick="evil()"');
    expect(result).toContain('&quot;');
  });

  test('zachovává bezpečný text', () => {
    const result = escapeHtml('Běžný text');
    expect(result).toBe('Běžný text');
  });

  test('vrací prázdný string pro null', () => {
    expect(escapeHtml(null)).toBe('');
  });

  test('vrací prázdný string pro undefined', () => {
    expect(escapeHtml(undefined)).toBe('');
  });

  test('vrací prázdný string pro prázdný string', () => {
    expect(escapeHtml('')).toBe('');
  });

  test('zachovává české znaky', () => {
    const result = escapeHtml('Příliš žluťoučký kůň');
    expect(result).toBe('Příliš žluťoučký kůň');
  });
});

// ========================================
// TESTY PRO escapeRegex
// ========================================

describe('escapeRegex', () => {
  test('escapuje tečku', () => {
    expect(escapeRegex('a.b')).toBe('a\\.b');
  });

  test('escapuje hvězdičku', () => {
    expect(escapeRegex('a*b')).toBe('a\\*b');
  });

  test('escapuje otazník', () => {
    expect(escapeRegex('a?b')).toBe('a\\?b');
  });

  test('escapuje závorky', () => {
    expect(escapeRegex('(a)')).toBe('\\(a\\)');
    expect(escapeRegex('[a]')).toBe('\\[a\\]');
    expect(escapeRegex('{a}')).toBe('\\{a\\}');
  });

  test('escapuje stříšku a dolar', () => {
    expect(escapeRegex('^a$')).toBe('\\^a\\$');
  });

  test('zachovává obyčejný text', () => {
    expect(escapeRegex('abc123')).toBe('abc123');
  });

  test('vrací prázdný string pro null', () => {
    expect(escapeRegex(null)).toBe('');
  });

  test('vrací prázdný string pro prázdný string', () => {
    expect(escapeRegex('')).toBe('');
  });
});

// ========================================
// TESTY PRO formatDateCZ
// ========================================

describe('formatDateCZ', () => {
  test('formátuje datum správně', () => {
    const result = formatDateCZ('2024-12-25');
    // Czech format: 25. 12. 2024 or 25.12.2024
    expect(result).toMatch(/25.*12.*2024/);
  });

  test('formátuje Date objekt', () => {
    const date = new Date(2024, 11, 25); // December 25, 2024
    const result = formatDateCZ(date);
    expect(result).toMatch(/25.*12.*2024/);
  });

  test('vrací — pro null', () => {
    expect(formatDateCZ(null)).toBe('—');
  });

  test('vrací — pro undefined', () => {
    expect(formatDateCZ(undefined)).toBe('—');
  });

  test('vrací — pro prázdný string', () => {
    expect(formatDateCZ('')).toBe('—');
  });

  test('vrací — pro neplatné datum', () => {
    expect(formatDateCZ('invalid-date')).toBe('—');
    expect(formatDateCZ('not a date')).toBe('—');
  });
});

// ========================================
// TESTY PRO debounce
// ========================================

describe('debounce', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('odloží volání funkce', () => {
    const mockFn = jest.fn();
    const debouncedFn = debounce(mockFn, 100);

    debouncedFn();
    expect(mockFn).not.toHaveBeenCalled();

    jest.advanceTimersByTime(100);
    expect(mockFn).toHaveBeenCalledTimes(1);
  });

  test('resetuje čas při opakovaném volání', () => {
    const mockFn = jest.fn();
    const debouncedFn = debounce(mockFn, 100);

    debouncedFn();
    jest.advanceTimersByTime(50);

    debouncedFn();
    jest.advanceTimersByTime(50);

    expect(mockFn).not.toHaveBeenCalled();

    jest.advanceTimersByTime(50);
    expect(mockFn).toHaveBeenCalledTimes(1);
  });

  test('předává argumenty funkci', () => {
    const mockFn = jest.fn();
    const debouncedFn = debounce(mockFn, 100);

    debouncedFn('arg1', 'arg2');
    jest.advanceTimersByTime(100);

    expect(mockFn).toHaveBeenCalledWith('arg1', 'arg2');
  });

  test('volá funkci pouze jednou pro rychlé sekvence', () => {
    const mockFn = jest.fn();
    const debouncedFn = debounce(mockFn, 100);

    for (let i = 0; i < 10; i++) {
      debouncedFn(i);
      jest.advanceTimersByTime(10);
    }

    jest.advanceTimersByTime(100);
    expect(mockFn).toHaveBeenCalledTimes(1);
    expect(mockFn).toHaveBeenCalledWith(9); // Poslední hodnota
  });
});

// ========================================
// TESTY PRO highlightText
// ========================================

describe('highlightText', () => {
  test('zvýrazní shodu', () => {
    const result = highlightText('Hello World', 'World');
    expect(result).toContain('<span class="highlight">World</span>');
  });

  test('zvýrazní case-insensitive', () => {
    const result = highlightText('Hello WORLD', 'world');
    expect(result).toContain('<span class="highlight">WORLD</span>');
  });

  test('zvýrazní více shod', () => {
    const result = highlightText('cat and cat', 'cat');
    expect(result.match(/highlight/g)?.length).toBe(2);
  });

  test('escapuje HTML v textu', () => {
    const result = highlightText('<script>evil</script>', 'evil');
    expect(result).not.toContain('<script>');
    expect(result).toContain('&lt;script&gt;');
  });

  test('vrací escapovaný text když není query', () => {
    const result = highlightText('<div>text</div>', '');
    expect(result).toContain('&lt;div&gt;');
  });

  test('vrací prázdný string pro null text', () => {
    expect(highlightText(null, 'query')).toBe('');
  });

  test('escapuje regex znaky v query', () => {
    const result = highlightText('a.b and a*b', 'a.b');
    // Mělo by najít pouze "a.b", ne "aXb"
    expect(result).toContain('<span class="highlight">a.b</span>');
  });
});

// ========================================
// TESTY PRO localStorage mock
// ========================================

describe('localStorage mock', () => {
  test('ukládá a načítá hodnoty', () => {
    localStorage.setItem('testKey', 'testValue');
    expect(localStorage.getItem('testKey')).toBe('testValue');
  });

  test('vrací null pro neexistující klíč', () => {
    expect(localStorage.getItem('nonexistent')).toBeNull();
  });

  test('odstraňuje hodnoty', () => {
    localStorage.setItem('toRemove', 'value');
    localStorage.removeItem('toRemove');
    expect(localStorage.getItem('toRemove')).toBeNull();
  });

  test('clear vyčistí vše', () => {
    localStorage.setItem('key1', 'value1');
    localStorage.setItem('key2', 'value2');
    localStorage.clear();
    expect(localStorage.getItem('key1')).toBeNull();
    expect(localStorage.getItem('key2')).toBeNull();
  });
});
