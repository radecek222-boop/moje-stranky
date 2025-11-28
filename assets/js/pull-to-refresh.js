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
        <div class="pull-refresh-arrow"></div>
        <span class="pull-refresh-text">Potahni pro aktualizaci</span>
      </div>
    `;
    pullIndicator.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 0;
      background: #f5f5f5;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      z-index: 99999;
      transition: none;
      border-bottom: 1px solid #ddd;
    `;

    const style = document.createElement('style');
    style.textContent = `
      .pull-refresh-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.3rem;
        opacity: 0;
        transform: translateY(-10px);
        transition: opacity 0.2s, transform 0.2s;
      }
      .pull-refresh-content.visible {
        opacity: 1;
        transform: translateY(0);
      }
      .pull-refresh-arrow {
        width: 20px;
        height: 20px;
        border: 2px solid #333;
        border-top: none;
        border-right: none;
        transform: rotate(-45deg);
        transition: transform 0.2s;
      }
      .pull-refresh-arrow.ready {
        transform: rotate(135deg);
      }
      .pull-refresh-arrow.loading {
        animation: spin 0.8s linear infinite;
        border-radius: 50%;
        border: 2px solid #333;
        border-top-color: transparent;
        transform: none;
      }
      @keyframes spin {
        to { transform: rotate(360deg); }
      }
      .pull-refresh-text {
        font-size: 0.75rem;
        color: #666;
        font-family: 'Poppins', sans-serif;
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
      const arrow = pullIndicator.querySelector('.pull-refresh-arrow');
      const text = pullIndicator.querySelector('.pull-refresh-text');

      if (pullDistance > 20) {
        content.classList.add('visible');
      }

      if (pullDistance >= PULL_THRESHOLD) {
        arrow.classList.add('ready');
        text.textContent = 'Uvolni pro aktualizaci';
      } else {
        arrow.classList.remove('ready');
        text.textContent = 'Potahni pro aktualizaci';
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
      const arrow = pullIndicator.querySelector('.pull-refresh-arrow');
      const text = pullIndicator.querySelector('.pull-refresh-text');

      arrow.classList.remove('ready');
      arrow.classList.add('loading');
      text.textContent = 'Aktualizuji...';

      pullIndicator.style.height = '60px';
      document.body.style.transform = 'translateY(60px)';

      // Reload po kratke pauze
      setTimeout(() => {
        window.location.reload();
      }, 500);
    } else {
      // Zrusit - vratit zpet
      pullIndicator.style.height = '0';
      document.body.style.transform = 'translateY(0)';
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

    console.log('[PWA] Pull-to-refresh aktivovan');
  }

  // Spustit po nacteni
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
