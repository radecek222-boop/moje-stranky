// ==============================================
// KOMPLEXNÍ DEBUG SKRIPT PRO TLAČÍTKA
// ==============================================
// Zkopírujte do konzole prohlížeče na seznam.php
// POTÉ otevřete detail dokončené zakázky
// ==============================================

console.clear();
console.log('%c=== KOMPLEXNÍ DEBUG START ===', 'background: #222; color: #bada55; font-size: 16px; padding: 10px;');

// 1. Zkontrolovat načtený soubor seznam.js
console.log('\n%c1. NAČTENÉ SCRIPTY:', 'background: #333; color: #fff; padding: 5px;');
const scripts = Array.from(document.querySelectorAll('script[src*="seznam"]'));
scripts.forEach(script => {
  console.log(`   📄 ${script.src}`);
});

// 2. Zjistit skutečnou verzi načteného souboru
console.log('\n%c2. KONTROLA VERZE (zkontrolujte konzoli):', 'background: #333; color: #fff; padding: 5px;');
console.log('   👀 Hledejte výše zprávu "🔍 SEZNAM.JS NAČTEN - VERZE: ..."');
console.log('   ✅ Měla by být: 20251123-01 nebo novější');
console.log('   ❌ POKUD je 20251122-04 nebo starší = PROBLÉM S CACHE!');

// 3. Zkontrolovat funkce
console.log('\n%c3. DOSTUPNÉ FUNKCE:', 'background: #333; color: #fff; padding: 5px;');
console.log('   - reopenOrder:', typeof reopenOrder, reopenOrder ? '✅' : '❌');
console.log('   - showDetail:', typeof showDetail, showDetail ? '✅' : '❌');
console.log('   - closeDetail:', typeof closeDetail, closeDetail ? '✅' : '❌');

// 4. Zkontrolovat event listenery na document
console.log('\n%c4. EVENT LISTENERY:', 'background: #333; color: #fff; padding: 5px;');
console.log('   ⚠️  Nemůžeme přímo zjistit z JS, použijte Chrome DevTools:');
console.log('   1. Otevřete DevTools (F12)');
console.log('   2. Elements → Event Listeners → document → click');
console.log('   3. Měli byste vidět listener ze seznam.js');

// 5. Test: Přidat vlastní event listener
console.log('\n%c5. PŘIDÁVÁM TESTOVACÍ EVENT LISTENER:', 'background: #333; color: #fff; padding: 5px;');
const testListener = (e) => {
  const button = e.target.closest('[data-action]');
  if (button) {
    const action = button.getAttribute('data-action');
    console.log('%c🎯 TESTOVACÍ LISTENER ZACHYTIL KLIKNUTÍ!', 'background: green; color: white; padding: 5px;');
    console.log('   Action:', action);
    console.log('   ID:', button.getAttribute('data-id'));
    console.log('   URL:', button.getAttribute('data-url'));
  }
};
document.addEventListener('click', testListener);
console.log('   ✅ Testovací listener přidán');
console.log('   📝 Po kliknutí na tlačítko uvidíte zprávu výše ☝️');

// 6. Čekání na modal
console.log('\n%c6. ČEKÁNÍ NA OTEVŘENÍ MODALU...', 'background: #333; color: #fff; padding: 5px;');
console.log('   👉 OTEVŘETE NYNÍ DETAIL DOKONČENÉ ZAKÁZKY');

const checkInterval = setInterval(() => {
  const overlay = document.getElementById('detailOverlay');
  if (overlay && overlay.classList.contains('active')) {
    clearInterval(checkInterval);

    console.log('\n%c✅ MODAL OTEVŘEN! ANALÝZA...', 'background: #4CAF50; color: white; font-size: 14px; padding: 10px;');

    // 7. Analýza tlačítek v modalu
    console.log('\n%c7. TLAČÍTKA V MODALU:', 'background: #333; color: #fff; padding: 5px;');

    const allButtons = overlay.querySelectorAll('[data-action]');
    console.log(`   📊 Celkem tlačítek s data-action: ${allButtons.length}`);

    allButtons.forEach((btn, index) => {
      const action = btn.getAttribute('data-action');
      const id = btn.getAttribute('data-id');
      const url = btn.getAttribute('data-url');
      const text = btn.textContent.trim().substring(0, 30);
      const visible = btn.offsetParent !== null;

      console.log(`\n   [${index + 1}] ${action}`);
      console.log(`       Text: "${text}"`);
      console.log(`       ID: ${id || 'N/A'}`);
      console.log(`       URL: ${url || 'N/A'}`);
      console.log(`       Viditelné: ${visible ? '✅' : '❌'}`);
      console.log(`       Class: ${btn.className}`);
    });

    // 8. Konkrétní tlačítka
    console.log('\n%c8. SPECIFICKÁ TLAČÍTKA:', 'background: #333; color: #fff; padding: 5px;');

    const reopenBtn = overlay.querySelector('[data-action="reopenOrder"]');
    const pdfBtn = overlay.querySelector('[data-action="openPDF"]');

    console.log('\n   🔄 "Znovu otevřít":');
    if (reopenBtn) {
      console.log(`       ✅ Existuje`);
      console.log(`       ID: ${reopenBtn.getAttribute('data-id')}`);
      console.log(`       Viditelné: ${reopenBtn.offsetParent !== null ? '✅' : '❌'}`);
      console.log(`       onclick: ${reopenBtn.onclick || 'null (správně!)'}`);
    } else {
      console.log(`       ❌ NEEXISTUJE!`);
    }

    console.log('\n   📄 "PDF REPORT":');
    if (pdfBtn) {
      console.log(`       ✅ Existuje`);
      console.log(`       URL: ${pdfBtn.getAttribute('data-url')}`);
      console.log(`       Viditelné: ${pdfBtn.offsetParent !== null ? '✅' : '❌'}`);
      console.log(`       onclick: ${pdfBtn.onclick || 'null (správně!)'}`);
    } else {
      console.log(`       ❌ NEEXISTUJE! (možná PDF není vygenerováno)`);
    }

    // 9. Test manuálního kliknutí
    console.log('\n%c9. TEST MANUÁLNÍHO KLIKNUTÍ:', 'background: #333; color: #fff; padding: 5px;');

    if (reopenBtn) {
      console.log('   🎯 Simuluji kliknutí na "Znovu otevřít"...');
      setTimeout(() => {
        reopenBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        console.log('   ✅ Kliknutí odesláno');
        console.log('   👀 Měli byste vidět zprávu z testovacího listeneru výše');
        console.log('   👀 A možná confirmation dialog od reopenOrder()');
      }, 500);
    }

    // 10. Závěrečný report
    setTimeout(() => {
      console.log('\n%c=== ZÁVĚREČNÝ REPORT ===', 'background: #222; color: #bada55; font-size: 16px; padding: 10px;');
      console.log('\n📋 CO ZKONTROLOVAT:');
      console.log('   1. Je verze 20251123-01 nebo novější?');
      console.log('   2. Existují tlačítka s data-action?');
      console.log('   3. Zachytil testovací listener kliknutí?');
      console.log('   4. Zobrazil se confirmation dialog?');
      console.log('\n💡 POKUD NE:');
      console.log('   - Stará verze v cache → Vyčistěte cache (Ctrl+Shift+Delete)');
      console.log('   - Tlačítka neexistují → Problém s generováním HTML');
      console.log('   - Listener nezachytil → Problém s event delegation');
      console.log('   - Dialog se nezobrazil → Problém s funkcí reopenOrder()');
    }, 2000);
  }
}, 500);

// Timeout po 30 sekundách
setTimeout(() => {
  clearInterval(checkInterval);
  console.log('\n%c⏱️  TIMEOUT: Modal se neotevřel za 30 sekund', 'background: #ff9800; color: white; padding: 10px;');
  console.log('   Otevřete detail zakázky a spusťte skript znovu');
}, 30000);

console.log('\n%c👉 NYNÍ OTEVŘETE DETAIL DOKONČENÉ ZAKÁZKY', 'background: #2196F3; color: white; font-size: 14px; padding: 10px;');
