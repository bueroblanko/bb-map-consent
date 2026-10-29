(() => {
  'use strict';
  const keys = {maps: 'bbmc-consent-v1', instagram: 'bbmc-instagram-consent-v1', dealerweb: 'bbmc-dealerweb-consent-v1', planity: 'bbmc-planity-consent-v1', elfsight: 'bbmc-elfsight-consent-v1', youtube: 'bbmc-youtube-consent-v1', tramino: 'bbmc-tramino-consent-v1', bookings: 'bbmc-bookings-consent-v1'};
  const consent = {};
  const scriptLoads = {};
  let api;
  Object.entries(keys).forEach(([service, key]) => {
    try {
      const saved = JSON.parse(localStorage.getItem(key));
      consent[service] = saved?.expires > Date.now();
      if (!consent[service]) localStorage.removeItem(key);
    } catch (_) { consent[service] = false; }
  });
  const boxes = (service) => [...document.querySelectorAll('.bbmc')].filter(box => !service || box.dataset.service === service);
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
  function decode(value) {
    const bytes = Uint8Array.from(atob(value), char => char.charCodeAt(0));
    return new TextDecoder().decode(bytes);
  }
  // Restores held-back scripts of one service in their original order.
  function loadScripts(service) {
    if (!scriptLoads[service]) scriptLoads[service] = (async () => {
      const holders = [...document.querySelectorAll('.bbmc-feed-script')].filter(holder => (holder.dataset.service || 'instagram') === service);
      for (const holder of holders) {
        const template = document.createElement('template');
        template.innerHTML = decode(holder.dataset.script);
        const original = template.content.querySelector('script');
        if (!original) continue;
        const script = document.createElement('script');
        for (const attribute of original.attributes) {
          if (!['async', 'defer'].includes(attribute.name)) script.setAttribute(attribute.name, attribute.value);
        }
        script.async = false;
        if (original.hasAttribute('src')) {
          await new Promise((resolve, reject) => {
            script.onload = resolve;
            script.onerror = () => reject(new Error('Ein Skript des Dienstes konnte nicht geladen werden.'));
            document.head.append(script);
          });
        } else {
          script.textContent = original.textContent;
          document.head.append(script);
        }
        holder.remove();
      }
    })();
    return scriptLoads[service];
  }
  async function load(box) {
    if (box.dataset.loaded) return;
    box.dataset.loaded = 'yes';
    const config = JSON.parse(box.dataset.bbmc);
    const target = box.querySelector('.bbmc-target');
    const placeholder = box.querySelector('.bbmc-placeholder');
    const revokeButton = box.querySelector('.bbmc-revoke');
    // Maps take over the placeholder's space so the layout does not jump.
    const reserved = box.dataset.service === 'maps' ? placeholder.offsetHeight || 0 : 0;
    placeholder.hidden = true;
    revokeButton.hidden = false;
    target.hidden = false;
    try {
      if (config.kind === 'smashballoon' || config.kind === 'html-scripts') {
        target.innerHTML = decode(config.html);
        // The whole group is restored synchronously before the SDK starts scanning.
        await Promise.resolve();
        await loadScripts(config.service || 'instagram');
      } else if (config.kind === 'scripts') {
        await loadScripts(config.service);
      } else if (config.kind === 'iframe') {
        if (!config.src) throw new Error('Diese Instagram-Einbettung wird noch nicht unterstützt. Bitte einen öffentlichen Beitrag oder ein Reel einbetten.');
        const frame = document.createElement('iframe');
        frame.title = config.title; frame.height = Math.max(config.height, reserved);
        if (reserved) target.style.minHeight = '0';
        if (config.width) frame.style.maxWidth = config.width + 'px';
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        if (config.service === 'youtube') frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        frame.allowFullscreen = true; frame.src = config.src; target.append(frame);
      } else {
        if (reserved > 400) target.style.minHeight = reserved + 'px';
        if (!Number.isFinite(config.lat) || !Number.isFinite(config.lng)) throw new Error('Die Divi-Karte enthält keine gültigen Standort-Koordinaten.');
        await googleApi(config.key);
        const map = new google.maps.Map(target, {center: {lat: config.lat, lng: config.lng}, zoom: config.zoom});
        // As a native control the button never collides with the fullscreen control.
        map.controls[google.maps.ControlPosition.TOP_RIGHT].push(revokeButton);
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
  function revoke(service) {
    consent[service] = false;
    try { localStorage.removeItem(keys[service]); } catch (_) {}
    // Reload destroys running Maps scripts, connections and all map instances.
    location.reload();
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    const service = button.closest('.bbmc')?.dataset.service || button.dataset.service || 'maps';
    if (!keys[service]) return;
    if (button.classList.contains('bbmc-revoke')) { revoke(service); return; }
    if (!button.classList.contains('bbmc-allow')) return;
    consent[service] = true;
    if (button.closest('.bbmc').querySelector('.bbmc-remember').checked) {
      try { localStorage.setItem(keys[service], JSON.stringify({expires: Date.now() + 30 * 86400000})); } catch (_) {}
    }
    boxes(service).forEach(load);
  });
  window.addEventListener('storage', event => { if (Object.values(keys).includes(event.key) && !event.newValue) location.reload(); });
  const restore = () => {
    boxes().forEach(box => {
      try { const height = JSON.parse(box.dataset.bbmc).height; if (height) box.style.setProperty('--bbmc-h', height + 'px'); } catch (_) {}
    });
    Object.keys(keys).forEach(service => { if (consent[service]) boxes(service).forEach(load); });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', restore, {once: true});
  else restore();
})();
