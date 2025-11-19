<?php
/**
 * PWA Splash Screen
 * Úvodní obrazovka pro PWA aplikaci
 * Zobrazí se pouze při spuštění z PWA ikony na mobilním zařízení
 */
require_once "init.php";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

  <title>WGS - White Glove Service</title>

  <!-- PWA Manifest -->
  <link rel="manifest" href="./manifest.json">

  <!-- Google Fonts - Poppins (stejný jako zbytek aplikace) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: #000000;
      color: #ffffff;
      width: 100vw;
      height: 100vh;
      overflow: hidden;
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      -webkit-tap-highlight-color: transparent;
      user-select: none;
    }

    /* Hlavní kontejner */
    .splash-kontejner {
      text-align: center;
      cursor: pointer;
      transition: transform 0.3s ease, opacity 0.3s ease;
      padding: 2rem;
    }

    .splash-kontejner:active {
      transform: scale(0.95);
      opacity: 0.8;
    }

    /* WGS logo text */
    .wgs-logo {
      font-size: clamp(5rem, 20vw, 12rem);
      font-weight: 700;
      letter-spacing: 0.5rem;
      margin-bottom: 1.5rem;
      color: #ffffff;
      text-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
      animation: pulseLogo 2.5s ease-in-out infinite;
      transform-origin: center;
      position: relative;
    }

    /* Glow efekt za logem */
    .wgs-logo::before {
      content: 'WGS';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: -1;
      opacity: 0;
      filter: blur(40px);
      animation: glowPulse 2.5s ease-in-out infinite;
    }

    /* Podtitulek */
    .wgs-podtitulek {
      font-size: clamp(1rem, 3vw, 1.5rem);
      font-weight: 300;
      letter-spacing: 0.3rem;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.7);
      margin-bottom: 3rem;
    }

    /* Hint pro kliknutí */
    .klik-hint {
      font-size: clamp(0.9rem, 2vw, 1.1rem);
      font-weight: 400;
      color: rgba(255, 255, 255, 0.5);
      animation: fadeInOut 2s ease-in-out infinite;
      margin-top: 3rem;
    }

    /* Animace pulzování loga - živé */
    @keyframes pulseLogo {
      0%, 100% {
        transform: scale(1);
        text-shadow:
          0 0 20px rgba(255, 255, 255, 0.4),
          0 0 40px rgba(255, 255, 255, 0.2);
      }
      50% {
        transform: scale(1.08);
        text-shadow:
          0 0 30px rgba(255, 255, 255, 0.6),
          0 0 60px rgba(255, 255, 255, 0.3),
          0 0 80px rgba(255, 255, 255, 0.1);
      }
    }

    /* Glow puls */
    @keyframes glowPulse {
      0%, 100% {
        opacity: 0;
      }
      50% {
        opacity: 0.3;
      }
    }

    /* Animace fade in/out pro hint */
    @keyframes fadeInOut {
      0%, 100% {
        opacity: 0.3;
      }
      50% {
        opacity: 0.7;
      }
    }

    /* Responzivita pro malé displeje */
    @media (max-width: 480px) {
      .wgs-logo {
        letter-spacing: 0.3rem;
      }

      .wgs-podtitulek {
        letter-spacing: 0.2rem;
      }
    }

    /* Landscape režim na mobilech */
    @media (max-height: 500px) and (orientation: landscape) {
      .wgs-logo {
        font-size: 4rem;
        margin-bottom: 0.5rem;
      }

      .wgs-podtitulek {
        font-size: 1rem;
        margin-bottom: 1rem;
      }

      .klik-hint {
        margin-top: 1rem;
        font-size: 0.85rem;
      }
    }
  </style>
</head>
<body>
  <div class="splash-kontejner" onclick="presmerujNaLogin()">
    <div class="wgs-logo">WGS</div>
    <div class="wgs-podtitulek">White Glove Service</div>
    <div class="klik-hint">Klepněte pro vstup</div>
  </div>

  <script>
    // Přesměrování na login stránku
    function presmerujNaLogin() {
      // Plynulý fade out před přesměrováním
      document.body.style.transition = 'opacity 0.3s ease';
      document.body.style.opacity = '0';

      setTimeout(() => {
        window.location.href = 'login.php?pwa=1';
      }, 300);
    }

    // Detekce PWA režimu
    const jePWA = window.matchMedia('(display-mode: standalone)').matches ||
                  window.navigator.standalone === true;

    // Pokud NENÍ PWA režim, přesměruj na normální homepage
    if (!jePWA) {
      console.log('⚠️ Přístup mimo PWA režim - přesměrování na index.php');
      window.location.replace('index.php');
    }

    // Auto-redirect po 5 sekundách (pokud uživatel neklikne)
    setTimeout(() => {
      console.log('⏱️ Auto-redirect na login po 5s');
      presmerujNaLogin();
    }, 5000);

    // Podpora klávesnice (Enter nebo Space)
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        presmerujNaLogin();
      }
    });

    // Prevence pull-to-refresh na iOS
    document.body.addEventListener('touchmove', (e) => {
      if (e.touches.length > 1) return;
      e.preventDefault();
    }, { passive: false });

    console.log('✅ PWA Splash Screen loaded');
  </script>
</body>
</html>
