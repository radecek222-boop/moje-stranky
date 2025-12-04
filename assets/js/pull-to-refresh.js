/**
 * Pull-to-Refresh pro PWA
 * iOS-like swipe down pro aktualizaci stranky
 * Vyzaduje potahnuti a podrzeni jako nativni iOS aplikace
 */

(function() {
  'use strict';

  // Pouze pro PWA a mobily
  const isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true;
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

  if (!isPWA && !isMobile) {
    return;
  }

  let touchStartY = 0;
  let touchCurrentY = 0;
  let isPulling = false;
  let pullIndicator = null;
  let holdStartTime = null;        // Cas kdy uzivatel dosahl prahu
  let isReadyToRefresh = false;    // Pripraveno k aktualizaci (drzeno dost dlouho)

  // KONFIGURACE - iOS-like chovani
  const PULL_THRESHOLD = 120;       // px pro dosazeni "pripraveno" stavu (zvyseno z 80)
  const MAX_PULL = 160;             // maximalni vytazeni
  const HOLD_DURATION = 400;        // ms - jak dlouho musi drzet pred uvolnenim (jako iOS)
  const MIN_PULL_START = 30;        // px - minimalni tah pred aktivaci (zabranuje nahodnemu spusteni)

  // Vytvorit indikator
  function vytvorIndikator() {
    pullIndicator = document.createElement('div');
    pullIndicator.id = 'pull-refresh-indicator';
    pullIndicator.innerHTML = `
      <div class="pull-refresh-content">
        <div class="pull-refresh-spinner">
          <svg viewBox="0 0 50 50">
            <circle cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle>
          </svg>
        </div>
        <span class="pull-refresh-text">Potahni dolu</span>
      </div>
    `;
    pullIndicator.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 0;
      background: linear-gradient(to bottom, #f0f0f0, #e8e8e8);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      z-index: 10003;
      transition: none;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    `;

    const style = document.createElement('style');
    style.textContent = `
      #pull-refresh-indicator .pull-refresh-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.4rem;
        opacity: 0;
        transform: scale(0.8);
        transition: opacity 0.2s, transform 0.2s;
      }
      #pull-refresh-indicator .pull-refresh-content.visible {
        opacity: 1;
        transform: scale(1);
      }
      #pull-refresh-indicator .pull-refresh-spinner {
        width: 32px;
        height: 32px;
      }
      #pull-refresh-indicator .pull-refresh-spinner svg {
        width: 100%;
        height: 100%;
        transform: rotate(-90deg);
      }
      #pull-refresh-indicator .pull-refresh-spinner circle {
        stroke: #333;
        stroke-dasharray: 126;
        stroke-dashoffset: 126;
        stroke-linecap: round;
        transition: stroke-dashoffset 0.05s linear;
      }
      #pull-refresh-indicator .pull-refresh-spinner.holding circle {
        stroke: #666;
        animation: ptr-pulse 0.5s ease-in-out infinite;
      }
      #pull-refresh-indicator .pull-refresh-spinner.ready circle {
        stroke-dashoffset: 0;
        stroke: #222;
      }
      #pull-refresh-indicator .pull-refresh-spinner.loading svg {
        animation: ptr-spin 0.8s linear infinite;
        transform: rotate(0deg);
      }
      #pull-refresh-indicator .pull-refresh-spinner.loading circle {
        stroke-dashoffset: 90;
        stroke: #333;
        animation: ptr-dash 1.2s ease-in-out infinite;
      }
      @keyframes ptr-spin {
        100% { transform: rotate(360deg); }
      }
      @keyframes ptr-dash {
        0% { stroke-dashoffset: 90; }
        50% { stroke-dashoffset: 30; }
        100% { stroke-dashoffset: 90; }
      }
      @keyframes ptr-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
      }
      #pull-refresh-indicator .pull-refresh-text {
        font-size: 0.7rem;
        color: #555;
        font-family: 'Poppins', -apple-system, sans-serif;
        font-weight: 500;
        letter-spacing: 0.02em;
        transition: color 0.2s;
      }
      #pull-refresh-indicator .pull-refresh-text.ready {
        color: #222;
        font-weight: 600;
      }
    `;
    document.head.appendChild(style);
    document.body.insertBefore(pullIndicator, document.body.firstChild);
  }

  // Kontrola jestli jsme na vrchu stranky
  function jeNaVrchu() {
    return window.scrollY <= 0;
  }

  // Kontrola jestli je otevreny modal (scroll je zamknuty)
  // FIX: Rozsirena detekce - pull-to-refresh se NIKDY nespusti kdyz je modal otevreny
  function jeModalOtevreny() {
    // 1. Zkontrolovat scrollLock utilitu
    if (window.scrollLock && window.scrollLock.isLocked && window.scrollLock.isLocked()) {
      return true;
    }

    // 2. Kontrola CSS tridy na body
    if (document.body.classList.contains('scroll-locked') ||
        document.body.classList.contains('modal-open') ||
        document.body.classList.contains('hamburger-menu-open') ||
        document.body.classList.contains('detail-open')) {
      return true;
    }

    // 3. Kontrola overflow na html/body (PWA scroll lock)
    const htmlStyle = getComputedStyle(document.documentElement);
    const bodyStyle = getComputedStyle(document.body);
    if (htmlStyle.overflow === 'hidden' || bodyStyle.overflow === 'hidden') {
      return true;
    }

    // 4. Kontrola vsech moznych overlays/modalu
    const overlaySelektory = [
      '#detailOverlay.active',
      '#detailOverlay:not(.hidden)',
      '#calendarOverlay.active',
      '#calendarOverlay:not(.hidden)',
      '#notesModal:not(.hidden)',
      '#pdfPreviewOverlay:not(.hidden)',
      '.modal-overlay.active',
      '.cc-modal-overlay.active',
      '.recovery-overlay',
      '.newkey-overlay'
    ];

    for (const selektor of overlaySelektory) {
      try {
        const element = document.querySelector(selektor);
        if (element) {
          // Zkontrolovat ze element je opravdu viditelny
          const style = getComputedStyle(element);
          if (style.display !== 'none' && style.visibility !== 'hidden') {
            return true;
          }
        }
      } catch (e) {
        // Ignorovat neplatne selektory
      }
    }

    return false;
  }

  // Kontrola zda touch zacal uvnitr scrollovatelneho kontejneru v modalu
  function jeUvnitrScrollovatelnehoKontejneru(target) {
    let element = target;
    while (element && element !== document.body) {
      const style = getComputedStyle(element);
      const isScrollable = (style.overflowY === 'auto' || style.overflowY === 'scroll') &&
                          element.scrollHeight > element.clientHeight;
      if (isScrollable) {
        return true;
      }
      element = element.parentElement;
    }
    return false;
  }

  // Touch start
  function onTouchStart(e) {
    // Ignorovat pokud je otevreny modal nebo jsme uvnitr scrollovatelneho kontejneru
    if (jeModalOtevreny()) return;
    if (jeUvnitrScrollovatelnehoKontejneru(e.target)) return;
    if (!jeNaVrchu()) return;

    touchStartY = e.touches[0].clientY;
    touchCurrentY = touchStartY;
    isPulling = false;  // Zacne az po MIN_PULL_START
    holdStartTime = null;
    isReadyToRefresh = false;
    pullIndicator.style.transition = 'none';
  }

  // Touch move
  function onTouchMove(e) {
    // Ignorovat pokud je otevreny modal
    if (jeModalOtevreny()) return;
    if (!jeNaVrchu()) return;

    touchCurrentY = e.touches[0].clientY;
    const pullDistance = touchCurrentY - touchStartY;

    // Aktivovat pulling az po MIN_PULL_START (zabrani nahodnemu spusteni)
    if (!isPulling && pullDistance > MIN_PULL_START) {
      isPulling = true;
    }

    if (!isPulling) return;

    const actualPull = Math.min(pullDistance - MIN_PULL_START, MAX_PULL);

    if (actualPull > 0) {
      // Zpomalit scroll stranky
      e.preventDefault();

      // Odpor - cim vic tahne, tim tezsi (iOS-like)
      const resistance = 0.5;
      const displayPull = actualPull * resistance + Math.min(actualPull * 0.3, 30);

      // Aktualizovat indikator
      pullIndicator.style.height = displayPull + 'px';

      const content = pullIndicator.querySelector('.pull-refresh-content');
      const spinner = pullIndicator.querySelector('.pull-refresh-spinner');
      const circle = pullIndicator.querySelector('.pull-refresh-spinner circle');
      const text = pullIndicator.querySelector('.pull-refresh-text');

      if (displayPull > 20) {
        content.classList.add('visible');
      }

      // Progresivni vyplnovani kruhu
      const progress = Math.min(actualPull / PULL_THRESHOLD, 1);
      const dashOffset = 126 - (progress * 126);
      circle.style.strokeDashoffset = dashOffset;

      // Posunout obsah stranky s odporem
      document.body.style.transform = `translateY(${displayPull}px)`;
      document.body.style.transition = 'none';

      // Logika pro iOS-like "drz a uvolni"
      if (actualPull >= PULL_THRESHOLD) {
        // Dosahl prahu - zacit pocitat cas drzeni
        if (holdStartTime === null) {
          holdStartTime = Date.now();
          spinner.classList.add('holding');
          spinner.classList.remove('ready');
          text.textContent = 'Podrz...';
          text.classList.remove('ready');
        }

        // Zkontrolovat jestli drzel dost dlouho
        const holdTime = Date.now() - holdStartTime;
        if (holdTime >= HOLD_DURATION && !isReadyToRefresh) {
          isReadyToRefresh = true;
          spinner.classList.remove('holding');
          spinner.classList.add('ready');
          text.textContent = 'Uvolni pro aktualizaci';
          text.classList.add('ready');

          // Hapticky feedback pokud je dostupny
          if (navigator.vibrate) {
            navigator.vibrate(10);
          }
        }
      } else {
        // Pod prahem - resetovat
        holdStartTime = null;
        isReadyToRefresh = false;
        spinner.classList.remove('ready', 'holding');
        text.textContent = 'Potahni dolu';
        text.classList.remove('ready');
      }
    }
  }

  // Touch end
  function onTouchEnd() {
    if (!isPulling) {
      touchStartY = 0;
      touchCurrentY = 0;
      return;
    }
    isPulling = false;

    // Animace navratu
    pullIndicator.style.transition = 'height 0.3s ease';
    document.body.style.transition = 'transform 0.3s ease';

    const content = pullIndicator.querySelector('.pull-refresh-content');
    const spinner = pullIndicator.querySelector('.pull-refresh-spinner');
    const text = pullIndicator.querySelector('.pull-refresh-text');

    if (isReadyToRefresh) {
      // Aktivovat refresh - drzel dost dlouho
      spinner.classList.remove('ready', 'holding');
      spinner.classList.add('loading');
      text.textContent = 'Aktualizuji...';
      text.classList.remove('ready');

      pullIndicator.style.height = '70px';
      document.body.style.transform = 'translateY(70px)';

      // Reload po kratke pauze
      setTimeout(() => {
        window.location.reload();
      }, 500);
    } else {
      // Zrusit - vratit zpet (nedrzel dost dlouho nebo nedosahl prahu)
      pullIndicator.style.height = '0';
      document.body.style.transform = 'translateY(0)';

      // Reset
      spinner.classList.remove('ready', 'holding', 'loading');
      text.classList.remove('ready');

      // Reset kruhu s malym zpozdenim
      setTimeout(() => {
        const circle = pullIndicator.querySelector('.pull-refresh-spinner circle');
        if (circle) {
          circle.style.strokeDashoffset = '126';
        }
        content.classList.remove('visible');
      }, 300);
    }

    // Reset stavu
    touchStartY = 0;
    touchCurrentY = 0;
    holdStartTime = null;
    isReadyToRefresh = false;
  }

  // Inicializace
  function init() {
    vytvorIndikator();

    document.addEventListener('touchstart', onTouchStart, { passive: true });
    document.addEventListener('touchmove', onTouchMove, { passive: false });
    document.addEventListener('touchend', onTouchEnd, { passive: true });
  }

  // Spustit po nacteni
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
