(() => {
  'use strict';
  const key = 'bbmc-consent-v1';
  let consent = false, api;
  try { const saved = JSON.parse(localStorage.getItem(key)); consent = saved?.expires > Date.now(); if (!consent) localStorage.removeItem(key); } catch (_) {}
  const boxes = () => [...document.querySelectorAll('.bbmc')];
  function googleApi(apiKey) {
    if (window.google?.maps?.Map) return Promise.resolve();
    if (!api) api = new Promise((resolve, reject) => {
      if (!apiKey) { reject(new Error('Kein Google-Maps-API-Schlüssel in Divi hinterlegt.')); return; }
      const name = 'bbmcGoogleReady';
      window[name] = () => { delete window[name]; resolve(); };
      const script = document.createElement('script');
      script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) + '&callback=' + name + '&loading=async';
      script.async = true;
      script.onerror = () => { api = null; reject(new Error('Google Maps konnte nicht geladen werden.')); };
      document.head.append(script);
    });
    return api;
  }
  async function load(box) {
    if (box.dataset.loaded) return;
    box.dataset.loaded = 'yes';
    const config = JSON.parse(box.dataset.bbmc);
    const target = box.querySelector('.bbmc-target');
    box.querySelector('.bbmc-placeholder').hidden = true;
    box.querySelector('.bbmc-revoke').hidden = false;
    target.hidden = false;
    try {
      if (config.kind === 'iframe') {
        const frame = document.createElement('iframe');
        frame.title = config.title; frame.height = config.height;
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.allowFullscreen = true; frame.src = config.src; target.append(frame);
      } else {
        await googleApi(config.key);
        const map = new google.maps.Map(target, {center: {lat: config.lat, lng: config.lng}, zoom: config.zoom});
        config.pins.forEach(pin => {
          const marker = new google.maps.Marker({map, position: {lat: pin.lat, lng: pin.lng}, title: pin.title});
          const content = document.createElement('div');
          const title = document.createElement('strong'); title.textContent = pin.title;
          const body = document.createElement('p'); body.textContent = pin.text;
          content.append(title, body);
          const info = new google.maps.InfoWindow({content});
          marker.addListener('click', () => info.open({map, anchor: marker}));
        });
      }
    } catch (error) {
      box.querySelector('.bbmc-status').textContent = error.message;
    }
  }
  function revoke() {
    consent = false;
    try { localStorage.removeItem(key); } catch (_) {}
    // Reload destroys running Maps scripts, connections and all map instances.
    location.reload();
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    if (button.classList.contains('bbmc-revoke')) { revoke(); return; }
    if (!button.classList.contains('bbmc-allow')) return;
    consent = true;
    if (button.closest('.bbmc').querySelector('.bbmc-remember').checked) {
      try { localStorage.setItem(key, JSON.stringify({expires: Date.now() + 30 * 86400000})); } catch (_) {}
    }
    boxes().forEach(load);
  });
  window.addEventListener('storage', event => { if (event.key === key && !event.newValue) location.reload(); });
  if (consent) boxes().forEach(load);
})();
