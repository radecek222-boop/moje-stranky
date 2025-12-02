/**
 * Pull-to-Refresh pro PWA
 * Swipe down pro aktualizaci stranky
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
  const PULL_THRESHOLD = 80; // px pro aktivaci refreshe
  const MAX_PULL = 120; // maximalni vytazeni

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
        transition: stroke-dashoffset 0.1s linear;
      }
      #pull-refresh-indicator .pull-refresh-spinner.ready circle {
        stroke-dashoffset: 0;
      }
      #pull-refresh-indicator .pull-refresh-spinner.loading svg {
        animation: ptr-spin 0.8s linear infinite;
        transform: rotate(0deg);
      }
      #pull-refresh-indicator .pull-refresh-spinner.loading circle {
        stroke-dashoffset: 90;
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
      #pull-refresh-indicator .pull-refresh-text {
        font-size: 0.7rem;
        color: #555;
        font-family: 'Poppins', -apple-system, sans-serif;
        font-weight: 500;
        letter-spacing: 0.02em;
      }
    `;
    document.head.appendChild(style);
    document.body.insertBefore(pullIndicator, document.body.firstChild);
  }

  // Kontrola jestli jsme na vrchu stranky
  function jeNaVrchu() {
    return window.scrollY <= 0;
  }

  // Touch start
  function onTouchStart(e) {
    if (!jeNaVrchu()) return;

    touchStartY = e.touches[0].clientY;
    isPulling = true;
    pullIndicator.style.transition = 'none';
  }

  // Touch move
  function onTouchMove(e) {
    if (!isPulling || !jeNaVrchu()) return;

    touchCurrentY = e.touches[0].clientY;
    const pullDistance = Math.min(touchCurrentY - touchStartY, MAX_PULL);

    if (pullDistance > 0) {
      // Zpomalit scroll stranky
      e.preventDefault();

      // Aktualizovat indikator
      pullIndicator.style.height = pullDistance + 'px';

      const content = pullIndicator.querySelector('.pull-refresh-content');
      const spinner = pullIndicator.querySelector('.pull-refresh-spinner');
      const circle = pullIndicator.querySelector('.pull-refresh-spinner circle');
      const text = pullIndicator.querySelector('.pull-refresh-text');

      if (pullDistance > 15) {
        content.classList.add('visible');
      }

      // Progresivni vyplnovani kruhu
      const progress = Math.min(pullDistance / PULL_THRESHOLD, 1);
      const dashOffset = 126 - (progress * 126);
      circle.style.strokeDashoffset = dashOffset;

      if (pullDistance >= PULL_THRESHOLD) {
        spinner.classList.add('ready');
        text.textContent = 'Uvolni';
      } else {
        spinner.classList.remove('ready');
        text.textContent = 'Potahni dolu';
      }

      // Posunout obsah stranky
      document.body.style.transform = `translateY(${pullDistance}px)`;
      document.body.style.transition = 'none';
    }
  }

  // Touch end
  function onTouchEnd() {
    if (!isPulling) return;
    isPulling = false;

    const pullDistance = touchCurrentY - touchStartY;

    // Animace navratu
    pullIndicator.style.transition = 'height 0.3s ease';
    document.body.style.transition = 'transform 0.3s ease';

    if (pullDistance >= PULL_THRESHOLD) {
      // Aktivovat refresh
      const spinner = pullIndicator.querySelector('.pull-refresh-spinner');
      const text = pullIndicator.querySelector('.pull-refresh-text');

      spinner.classList.remove('ready');
      spinner.classList.add('loading');
      text.textContent = 'Aktualizuji...';

      pullIndicator.style.height = '70px';
      document.body.style.transform = 'translateY(70px)';

      // Reload po kratke pauze
      setTimeout(() => {
        window.location.reload();
      }, 600);
    } else {
      // Zrusit - vratit zpet
      pullIndicator.style.height = '0';
      document.body.style.transform = 'translateY(0)';

      // Reset kruhu
      const circle = pullIndicator.querySelector('.pull-refresh-spinner circle');
      circle.style.strokeDashoffset = '126';
    }

    touchStartY = 0;
    touchCurrentY = 0;
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
