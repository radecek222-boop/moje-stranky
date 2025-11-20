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
      if (el.tagName === 'BR') return;

      // Handle elements with <br> tags
      if (text.includes('<br>')) {
        el.innerHTML = text;
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
    cs: 'Servis a opravy Natuzzi | Reklamace, montáž | WGS',
    en: 'Natuzzi Service and Repairs | Complaints, Assembly | WGS',
    it: 'Assistenza e Riparazioni Natuzzi | Reclami, Montaggio | WGS'
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

// Hamburger menu je spravováno v hamburger-menu.php (inline JavaScript)
// Neduplikovat kód zde - zabráníme konfliktům event listenerů