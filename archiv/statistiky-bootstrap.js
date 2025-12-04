(function () {
  'use strict';

  const defaultState = {
    claims: [],
    users: [],
    techStats: [],
    filteredOrders: [],
    filters: {
      country: [],
      status: [],
      salesperson: [],
      technician: [],
      dateFrom: '',
      dateTo: '',
    },
    calendars: {},
    multiSelect: {
      country: { selected: [], items: [] },
      status: { selected: [], items: [] },
      salesperson: { selected: [], items: [] },
      technician: { selected: [], items: [] },
    },
    currentUser: null,
  };

  const target = window.WGS || {};

  function normaliseArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function assignSection(sectionName) {
    const defaults = defaultState[sectionName];
    const existing = target[sectionName];

    if (Array.isArray(defaults)) {
      return normaliseArray(existing);
    }

    if (defaults && typeof defaults === 'object') {
      const result = { ...defaults };
      Object.keys(defaults).forEach((key) => {
        if (Array.isArray(defaults[key])) {
          result[key] = normaliseArray(existing?.[key]);
        } else if (defaults[key] && typeof defaults[key] === 'object') {
          result[key] = assignNested(defaults[key], existing?.[key]);
        } else if (typeof existing?.[key] !== 'undefined') {
          result[key] = existing[key];
        }
      });
      return result;
    }

    return typeof existing !== 'undefined' ? existing : defaults;
  }

  function assignNested(defaults, existing) {
    const result = { ...defaults };
    Object.keys(defaults).forEach((key) => {
      if (Array.isArray(defaults[key])) {
        result[key] = normaliseArray(existing?.[key]);
      } else if (defaults[key] && typeof defaults[key] === 'object') {
        result[key] = assignNested(defaults[key], existing?.[key]);
      } else if (typeof existing?.[key] !== 'undefined') {
        result[key] = existing[key];
      }
    });
    return result;
  }

  const bootstrapped = {
    ...defaultState,
    ...target,
    claims: normaliseArray(target.claims),
    users: normaliseArray(target.users),
    techStats: normaliseArray(target.techStats),
    filteredOrders: normaliseArray(target.filteredOrders),
    filters: assignSection('filters'),
    calendars:
      target.calendars && typeof target.calendars === 'object'
        ? target.calendars
        : { ...defaultState.calendars },
    multiSelect: assignSection('multiSelect'),
  };

  Object.keys(bootstrapped.multiSelect).forEach((key) => {
    const entry = bootstrapped.multiSelect[key] || {};
    entry.selected = normaliseArray(entry.selected);
    entry.items = normaliseArray(entry.items);
    bootstrapped.multiSelect[key] = entry;
  });

  window.WGS = bootstrapped;
})();
