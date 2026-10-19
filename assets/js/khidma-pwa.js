/**
 * khidma-pwa.js
 * - Registers the service worker
 * - Handles the "Add to home screen" install prompt
 * - Shows a subtle install banner after 30s if not installed
 */
(function(){
  'use strict';

  // ── Register Service Worker ───────────────────────────────
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js')
        .then(reg => {
          console.log('[PWA] SW registered, scope:', reg.scope);
          // Check for updates every 60s
          setInterval(() => reg.update(), 60000);
        })
        .catch(err => console.warn('[PWA] SW registration failed:', err));
    });
  }

  // ── Install prompt ────────────────────────────────────────
  let deferredPrompt = null;

  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    // Show banner after 8 seconds if user hasn't dismissed before
    if (!localStorage.getItem('pwa_dismissed')) {
      setTimeout(showBanner, 8000);
    }
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    hideBanner();
    localStorage.setItem('pwa_installed', '1');
  });

  function showBanner() {
    if (!deferredPrompt) return;
    if (document.getElementById('pwa-banner')) return;

    const banner = document.createElement('div');
    banner.id = 'pwa-banner';
    banner.style.cssText = [
      'position:fixed',
      'bottom:80px',           // above mobile nav if present
      'left:50%',
      'transform:translateX(-50%)',
      'width:calc(100% - 32px)',
      'max-width:400px',
      'background:#fff',
      'border:2px solid #c4b5fd',
      'border-radius:18px',
      'padding:16px 18px',
      'display:flex',
      'align-items:center',
      'gap:14px',
      'box-shadow:0 8px 32px rgba(124,58,237,.18)',
      'z-index:9999',
      'animation:slideUp .35s ease',
    ].join(';');

    banner.innerHTML = `
      <style>
        @keyframes slideUp{from{opacity:0;transform:translateX(-50%) translateY(20px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
      </style>
      <img src="icons/icon-72.png" style="width:44px;height:44px;border-radius:12px;flex-shrink:0;" alt="">
      <div style="flex:1;min-width:0">
        <div style="font-weight:800;font-size:14px;color:#0f0c1d;margin-bottom:2px">Installer Khidma DZ</div>
        <div style="font-size:12px;color:#8b83a8;line-height:1.4">Accédez rapidement depuis votre écran d'accueil</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
        <button id="pwa-install-btn" style="padding:7px 14px;background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border:none;border-radius:9px;font-weight:700;font-size:12px;cursor:pointer;white-space:nowrap">Installer</button>
        <button id="pwa-dismiss-btn" style="padding:5px 10px;background:none;color:#8b83a8;border:1px solid #e8e4f8;border-radius:7px;font-size:11px;cursor:pointer">Plus tard</button>
      </div>`;

    document.body.appendChild(banner);

    document.getElementById('pwa-install-btn').addEventListener('click', async () => {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      deferredPrompt = null;
      hideBanner();
      if (outcome === 'dismissed') localStorage.setItem('pwa_dismissed', '1');
    });

    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
      hideBanner();
      localStorage.setItem('pwa_dismissed', '1');
    });
  }

  function hideBanner() {
    const b = document.getElementById('pwa-banner');
    if (b) { b.style.opacity = '0'; setTimeout(() => b.remove(), 300); }
  }

  // ── iOS "Add to Home Screen" hint (Safari doesn't support beforeinstallprompt) ──
  function isIOS() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  }
  function isInStandaloneMode() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  }

  if (isIOS() && !isInStandaloneMode() && !localStorage.getItem('ios_hint_shown')) {
    setTimeout(() => {
      const hint = document.createElement('div');
      hint.style.cssText = [
        'position:fixed','bottom:80px','left:50%','transform:translateX(-50%)',
        'width:calc(100% - 32px)','max-width:380px',
        'background:#1c1c1e','color:#fff',
        'border-radius:16px','padding:16px 18px',
        'font-size:13px','line-height:1.5',
        'z-index:9999','text-align:center',
        'box-shadow:0 8px 32px rgba(0,0,0,.4)',
      ].join(';');
      hint.innerHTML = `
        <div style="font-weight:700;margin-bottom:6px">📲 Installer Khidma DZ</div>
        <div style="opacity:.85">Appuyez sur <strong>⎋ Partager</strong> puis <strong>"Sur l'écran d'accueil"</strong></div>
        <button onclick="this.parentElement.remove();localStorage.setItem('ios_hint_shown','1')"
          style="margin-top:12px;padding:8px 20px;background:#7c3aed;color:#fff;border:none;border-radius:9px;font-weight:700;cursor:pointer;font-size:13px">
          OK compris
        </button>`;
      document.body.appendChild(hint);
    }, 10000);
  }

})();