console.log('=== STATISTIKY DEBUG ===');

// Check if elements exist
setTimeout(() => {
  console.log('Element check:');
  console.log('- display-country:', document.getElementById('display-country'));
  console.log('- display-status:', document.getElementById('display-status'));
  console.log('- filter-date-from:', document.getElementById('filter-date-from'));

  // Check if JavaScript loaded
  console.log('\nGlobal check:');
  console.log('- WGS defined:', typeof window.WGS !== 'undefined');
  console.log('- openMultiSelect:', typeof openMultiSelect);

  // Check WGS data
  if (typeof window.WGS !== 'undefined') {
    console.log('\nWGS Data:');
    console.log('- Users loaded:', window.WGS.users.length);
    console.log('- Claims loaded:', window.WGS.claims.length);
    console.log('- Filtered orders:', window.WGS.filteredOrders.length);
    console.log('- Current filters:', window.WGS.filters);

    if (window.WGS.claims.length > 0) {
      console.log('\nFirst claim sample:', window.WGS.claims[0]);
    }
  } else {
    console.error('WGS not available on window object!');
  }

  // Test click
  console.log('\nTesting multi-select click:');
  const countryDisplay = document.getElementById('display-country');
  if (countryDisplay) {
    console.log('Country display found, attributes:', countryDisplay.attributes);
    console.log('data-multiselect value:', countryDisplay.getAttribute('data-multiselect'));
  }
}, 2000);
