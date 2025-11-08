// === LANGUAGE SWITCHER ===
let currentLang = localStorage.getItem('wgs-lang') || 'cs';

function switchLanguage(lang) {
  currentLang = lang;
  localStorage.setItem('wgs-lang', lang);
  document.documentElement.lang = lang;

  // Update all elements with data-lang attributes
  document.querySelectorAll('[data-lang-cs]').forEach(el => {
    const text = el.getAttribute('data-lang-' + lang);
    if (text) {
      // Pro odkazy a tlačítka zachovat pouze text, ne HTML strukturu
      if (el.tagName === 'A' || el.tagName === 'BUTTON') {
        // Pokud má element vnořené prvky (jako logo se spanem), přeskočit
        if (el.querySelector('span')) {
          return;
        }
        // Změnit pouze textový obsah, href zůstane zachován
        el.textContent = text;
      } else {
        el.textContent = text;
      }
    }
  });

  // Update active flag
  document.querySelectorAll('.lang-flag').forEach(flag => {
    flag.classList.remove('active');
    if (flag.dataset.lang === lang) {
      flag.classList.add('active');
    }
  });

  // Update page title
  const titles = {
    cs: 'White Glove Service – Domů',
    en: 'White Glove Service – Home',
    it: 'White Glove Service – Home'
  };
  document.title = titles[lang];
}

// Initialize language on page load
document.addEventListener('DOMContentLoaded', () => {
  if (currentLang !== 'cs') {
    switchLanguage(currentLang);
  }

  // Add click handlers to flags
  document.querySelectorAll('.lang-flag').forEach(flag => {
    flag.addEventListener('click', () => {
      switchLanguage(flag.dataset.lang);
    });
  });
});

// === MOBILE MENU ===
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const nav = document.querySelector('.nav');

// ✅ KONTROLA - Pokud elementy neexistují, zastav inicializaci
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
    
    // Zabránit scrollování těla když je menu otevřené
    if (!isActive) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
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
  // ✅ Debug info (můžeš smazat po opravě)
  console.log('ℹ️ Mobile menu: Elements not found (this is OK if using hamburger-menu.php)');
}