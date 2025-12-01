// === LANGUAGE SWITCHER ===
// REMOVED: Duplikovaný kód přesunut do centrálního language-switcher.js
// Jazykové přepínání je nyní automatické přes assets/js/language-switcher.js

// === MOBILE MENU ===
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const nav = document.querySelector('.nav');

// KONTROLA - Pokud elementy neexistují, zastav inicializaci
if (mobileMenuBtn && nav) {
  // Vytvořit overlay pro zavření menu
  let menuOverlay = document.createElement('div');
  menuOverlay.className = 'menu-overlay';
  document.body.appendChild(menuOverlay);

  // Funkce pro otevření/zavření menu
  function toggleMenu() {
    const isActive = nav.classList.contains('active');
    nav.classList.toggle('active');
    menuOverlay.classList.toggle('active');
    mobileMenuBtn.textContent = !isActive ? '✕' : '☰';

    // Scroll-lock pres centralizovanou utilitu (iOS kompatibilni)
    if (window.scrollLock) {
      if (!isActive) {
        window.scrollLock.enable('index-menu');
      } else {
        window.scrollLock.disable('index-menu');
      }
    }
  }

  // Kliknutí na hamburger tlačítko
  mobileMenuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMenu();
  });

  // Zavřít menu kliknutím na overlay
  menuOverlay.addEventListener('click', () => {
    toggleMenu();
  });

  // Zavřít menu kliknutím na odkaz v menu
  nav.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      if (nav.classList.contains('active')) {
        toggleMenu();
      }
    });
  });
} else {
  // Debug info (můžeš smazat po opravě)
  console.log('ℹ️ Mobile menu: Elements not found (this is OK if using hamburger-menu.php)');
}