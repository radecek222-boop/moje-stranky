console.log('=== STATISTIKY DEBUG ===');

// Check if elements exist
setTimeout(() => {
  console.log('Element check:');
  console.log('- display-country:', document.getElementById('display-country'));
  console.log('- display-status:', document.getElementById('display-status'));
  console.log('- filter-date-from:', document.getElementById('filter-date-from'));

  // Check if JavaScript loaded
  console.log('\nGlobal check:');
  console.log('- WGS defined:', typeof WGS !== 'undefined');
  console.log('- openMultiSelect:', typeof openMultiSelect);

  // Test click
  console.log('\nTesting multi-select click:');
  const countryDisplay = document.getElementById('display-country');
  if (countryDisplay) {
    console.log('Country display found, attributes:', countryDisplay.attributes);
    console.log('data-multiselect value:', countryDisplay.getAttribute('data-multiselect'));
  }
}, 1000);
