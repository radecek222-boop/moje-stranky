// DEBUG SKRIPT - Zkopírujte do konzole prohlížeče na stránce seznam.php
// Po otevření detailu dokončené zakázky

console.log('=== DEBUG TLAČÍTEK V DETAILU ===\n');

// 1. Zkontrolovat, jestli existuje modal overlay
const overlay = document.getElementById('detailOverlay');
console.log('1. Modal overlay existuje:', !!overlay);
console.log('   - Je aktivní:', overlay?.classList.contains('active'));

// 2. Zkontrolovat všechna tlačítka s data-action
const allButtons = document.querySelectorAll('[data-action]');
console.log('\n2. Všechna tlačítka s data-action:', allButtons.length);
allButtons.forEach((btn, index) => {
  console.log(`   [${index}] action="${btn.getAttribute('data-action')}"`, {
    id: btn.getAttribute('data-id'),
    url: btn.getAttribute('data-url'),
    text: btn.textContent.trim().substring(0, 30),
    visible: btn.offsetParent !== null
  });
});

// 3. Zkontrolovat konkrétně tlačítko "Znovu otevřít"
const reopenBtn = document.querySelector('[data-action="reopenOrder"]');
console.log('\n3. Tlačítko "Znovu otevřít":');
console.log('   - Existuje:', !!reopenBtn);
console.log('   - ID:', reopenBtn?.getAttribute('data-id'));
console.log('   - Viditelné:', reopenBtn?.offsetParent !== null);
console.log('   - Text:', reopenBtn?.textContent.trim());

// 4. Zkontrolovat tlačítko "PDF REPORT"
const pdfBtn = document.querySelector('[data-action="openPDF"]');
console.log('\n4. Tlačítko "PDF REPORT":');
console.log('   - Existuje:', !!pdfBtn);
console.log('   - URL:', pdfBtn?.getAttribute('data-url'));
console.log('   - Viditelné:', pdfBtn?.offsetParent !== null);
console.log('   - Text:', pdfBtn?.textContent.trim());

// 5. Zkontrolovat, jestli existují funkce
console.log('\n5. Funkce v window scope:');
console.log('   - reopenOrder:', typeof window.reopenOrder);
console.log('   - window.open:', typeof window.open);

// 6. Zkontrolovat event listenery (Chrome DevTools)
console.log('\n6. Event listenery:');
console.log('   - Použijte Chrome DevTools: pravý klik na tlačítko → Inspect → Event Listeners');
console.log('   - Nebo zkuste manuální kliknutí:');

if (reopenBtn) {
  console.log('\n   Simuluji kliknutí na "Znovu otevřít"...');
  reopenBtn.click();
}

console.log('\n=== KONEC DEBUG ===');
