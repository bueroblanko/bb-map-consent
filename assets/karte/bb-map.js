/*
 * bb-map – Karte in Kundenfarben mit Einwilligungs-Platzhalter.
 * Eine Datei für WordPress und statische Seiten. MapLibre wird erst nach dem Klick geladen.
 *
 * <bb-map lat="50.119483" lng="8.682741" zoom="13"
 *         platzhalter="platzhalter.svg" datenschutz="/datenschutz"
 *         route="https://maps.app.goo.gl/…" titel="Kanzlei"></bb-map>
 *
 * Farben als CSS-Variablen am Element oder an :root: --bb-map-land, -wasser, -strasse, -akzent (Hex).
 */
(function (root) {
  'use strict';

  // Vier Farben reichen (land, wasser, strasse, akzent), der Rest wird daraus abgeleitet.
  // Dieselbe Rechnung steht in BB Consent (includes/karte.php), beide müssen gleich bleiben.
  var NAMEN = ['land', 'wasser', 'gruen', 'strasse', 'nebenstrasse', 'schrift', 'akzent'];
  function ok(c) {
    c = String(c || '').trim().toLowerCase();
    if (/^#[0-9a-f]{3}$/.test(c)) c = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
    return /^#[0-9a-f]{6}$/.test(c) ? c : '';
  }
  function mix(a, b, t) {
    var out = '#';
    for (var i = 1; i < 6; i += 2) {
      var x = parseInt(a.substr(i, 2), 16), y = parseInt(b.substr(i, 2), 16);
      out += ('0' + Math.round(x + (y - x) * t).toString(16)).slice(-2);
    }
    return out;
  }
  function hell(c) {
    var l = [1, 3, 5].map(function (i) {
      var v = parseInt(c.substr(i, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2] > 0.45;
  }
  function farben(e) {
    e = e || {};
    var land = ok(e.land) || '#f2f1ed', h = hell(land);
    var schrift = ok(e.schrift) || (h ? '#2b2b2b' : '#ffffff');
    var strasse = ok(e.strasse) || (h ? '#ffffff' : mix(land, '#ffffff', 0.35));
    var akzent = ok(e.akzent) || '#222222';
    return {
      land: land,
      wasser: ok(e.wasser) || mix(land, h ? '#7fa6c4' : '#8fa3c8', 0.45),
      gruen: ok(e.gruen) || mix(land, h ? '#7fa56b' : '#ffffff', h ? 0.18 : 0.05),
      gebaeude: mix(land, schrift, 0.08),
      strasse: strasse,
      nebenstrasse: ok(e.nebenstrasse) || mix(land, strasse, 0.4),
      bahn: mix(land, schrift, 0.2),
      schrift: schrift,
      akzent: akzent,
      akzentText: hell(akzent) ? '#111111' : '#ffffff'
    };
  }

  // Strichstärke je Straßenklasse bei Zoom 13. Platzhalter und Live-Karte nutzen dieselbe Tabelle.
  var STRASSEN = [
    { klassen: ['minor'], farbe: 'nebenstrasse', breite: 0.7 },
    { klassen: ['tertiary'], farbe: 'strasse', breite: 1.1 },
    { klassen: ['secondary'], farbe: 'strasse', breite: 1.6 },
    { klassen: ['primary', 'trunk'], farbe: 'strasse', breite: 2.2 },
    { klassen: ['motorway'], farbe: 'strasse', breite: 3 }
  ];
  var KACHELN = 'https://tiles.openfreemap.org/planet';
  var SCHRIFTEN = 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf';

  function breite(b) {
    return ['interpolate', ['exponential', 1.6], ['zoom'], 8, b * 0.3, 13, b, 18, b * 12];
  }

  function stil(f) {
    var ebenen = [
      { id: 'land', type: 'background', paint: { 'background-color': f.land } },
      { id: 'gruen', type: 'fill', source: 'omt', 'source-layer': 'park', paint: { 'fill-color': f.gruen } },
      { id: 'wald', type: 'fill', source: 'omt', 'source-layer': 'landcover',
        filter: ['in', ['get', 'class'], ['literal', ['wood', 'grass']]], paint: { 'fill-color': f.gruen } },
      { id: 'wasser', type: 'fill', source: 'omt', 'source-layer': 'water', paint: { 'fill-color': f.wasser } },
      { id: 'bach', type: 'line', source: 'omt', 'source-layer': 'waterway',
        paint: { 'line-color': f.wasser, 'line-width': breite(1) } },
      { id: 'gebaeude', type: 'fill', source: 'omt', 'source-layer': 'building', minzoom: 14,
        paint: { 'fill-color': f.gebaeude } },
      { id: 'bahn', type: 'line', source: 'omt', 'source-layer': 'transportation',
        filter: ['==', ['get', 'class'], 'rail'],
        paint: { 'line-color': f.bahn, 'line-width': breite(0.8) } }
    ];
    STRASSEN.forEach(function (s, i) {
      var klassen = i === 0 ? s.klassen.concat(['service']) : s.klassen;
      ebenen.push({
        id: 'strasse-' + s.klassen[0], type: 'line', source: 'omt', 'source-layer': 'transportation',
        filter: ['all', ['==', ['geometry-type'], 'LineString'], ['in', ['get', 'class'], ['literal', klassen]]],
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': f[s.farbe], 'line-width': breite(s.breite) }
      });
    });
    ebenen.push(
      { id: 'strassennamen', type: 'symbol', source: 'omt', 'source-layer': 'transportation_name', minzoom: 14,
        layout: { 'symbol-placement': 'line', 'text-field': ['get', 'name'], 'text-font': ['Noto Sans Regular'],
          'text-size': 11, 'text-letter-spacing': 0.05 },
        paint: { 'text-color': f.schrift, 'text-opacity': 0.75, 'text-halo-color': f.land, 'text-halo-width': 1.5 } },
      { id: 'orte', type: 'symbol', source: 'omt', 'source-layer': 'place',
        filter: ['in', ['get', 'class'], ['literal', ['city', 'town', 'village', 'suburb', 'quarter', 'neighbourhood']]],
        layout: { 'text-field': ['get', 'name'], 'text-font': ['Noto Sans Regular'],
          'text-size': ['match', ['get', 'class'], 'city', 17, 'town', 14, 12],
          'text-transform': ['match', ['get', 'class'], ['city', 'town', 'village'], 'none', 'uppercase'],
          'text-letter-spacing': ['match', ['get', 'class'], ['city', 'town', 'village'], 0, 0.12] },
        paint: { 'text-color': f.schrift, 'text-halo-color': f.land, 'text-halo-width': 1.5 } }
    );
    return { version: 8, glyphs: SCHRIFTEN, sources: { omt: { type: 'vector', url: KACHELN } }, layers: ebenen };
  }

  root.BBMap = { farben: farben, STRASSEN: STRASSEN, KACHELN: KACHELN, stil: stil };
  if (typeof customElements === 'undefined') return; // im Generator (Node) reicht die Stil-Tabelle

  var BASIS = (document.currentScript && document.currentScript.src || '').replace(/[^/]*$/, '');
  function kebab(k) { return '--bb-map-' + k.replace(/[A-Z]/g, function (c) { return '-' + c.toLowerCase(); }); }
  var MERKEN = 'bb-map-freigabe';
  var CSS =
    'bb-map{display:block;position:relative;height:var(--bb-map-h,400px);overflow:hidden;background:var(--bb-map-land);color:var(--bb-map-schrift)}' +
    'bb-map .bbm-bild,bb-map .bbm-karte{position:absolute;inset:0}' +
    'bb-map .bbm-bild{background-position:center;background-repeat:no-repeat;transition:opacity .5s}' +
    'bb-map .bbm-bild::after{content:"";position:absolute;inset:0;background:radial-gradient(ellipse at center,transparent 0,var(--bb-map-land) 130%)}' +
    'bb-map.an .bbm-bild,bb-map.an .bbm-box{opacity:0;pointer-events:none}' +
    'bb-map .bbm-nadel{position:absolute;left:50%;top:50%;width:30px;height:40px;margin:-40px 0 0 -15px;filter:drop-shadow(0 3px 6px rgba(0,0,0,.45))}' +
    'bb-map .bbm-box{position:absolute;left:clamp(16px,6vw,96px);top:50%;transform:translateY(-50%);box-sizing:border-box;width:min(360px,calc(100% - 32px));padding:26px 28px;background:var(--bb-map-land);border-top:2px solid var(--bb-map-akzent);box-shadow:0 18px 50px rgba(0,0,0,.4);font-size:14px;line-height:1.55;transition:opacity .3s}' +
    'bb-map .bbm-box strong{display:block;margin-bottom:8px;font-family:var(--bb-map-titelschrift,inherit);font-size:22px;font-weight:400;line-height:1.2}' +
    'bb-map .bbm-box p{margin:0 0 14px;padding:0;opacity:.82}' +
    'bb-map .bbm-box a{color:inherit;text-decoration:underline}' +
    'bb-map .bbm-box label{display:flex;gap:8px;align-items:center;margin:0 0 16px;font-size:13px;opacity:.82}' +
    'bb-map .bbm-zeile{display:flex;flex-wrap:wrap;gap:10px 18px;align-items:center}' +
    'bb-map .bbm-laden{cursor:pointer;border:0;border-radius:var(--bb-map-radius,0);padding:12px 22px;background:var(--bb-map-akzent);color:var(--bb-map-akzent-text);font:inherit;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:13px}' +
    'bb-map .bbm-laden:hover{filter:brightness(.9)}' +
    'bb-map button:focus-visible,bb-map input:focus-visible,bb-map a:focus-visible{outline:2px solid var(--bb-map-schrift);outline-offset:3px}' +
    'bb-map .bbm-quelle{position:absolute;right:0;bottom:0;padding:2px 8px;font-size:11px;background:var(--bb-map-land);opacity:.8}' +
    'bb-map .bbm-quelle a{color:inherit}' +
    'bb-map .bbm-aus{position:absolute;left:10px;bottom:10px;z-index:2;cursor:pointer;border:0;padding:6px 10px;background:var(--bb-map-land);color:inherit;font:inherit;font-size:12px;text-decoration:underline}' +
    'bb-map .maplibregl-ctrl-attrib{background:var(--bb-map-land);color:var(--bb-map-schrift);opacity:.8}' +
    'bb-map .maplibregl-ctrl-attrib a{color:inherit}' +
    'bb-map .maplibregl-popup-content{background:var(--bb-map-land);color:var(--bb-map-schrift);border-radius:0;padding:12px 16px;font:inherit;font-size:14px}' +
    'bb-map .maplibregl-popup-tip{border-top-color:var(--bb-map-land)!important}' +
    '@media(max-width:560px){bb-map .bbm-box{left:16px;padding:18px 20px}bb-map .bbm-box strong{font-size:19px}bb-map .bbm-lang{display:none}}';

  var bibliothek;
  function ladeBibliothek(basis) {
    if (bibliothek) return bibliothek;
    bibliothek = new Promise(function (ok, fehler) {
      var l = document.createElement('link');
      l.rel = 'stylesheet';
      l.href = basis + 'vendor/maplibre-gl.css';
      document.head.appendChild(l);
      var s = document.createElement('script');
      s.src = basis + 'vendor/maplibre-gl.js';
      s.onload = function () { ok(root.maplibregl); };
      s.onerror = function () { bibliothek = null; fehler(new Error('Die Karte konnte nicht geladen werden.')); };
      document.head.appendChild(s);
    });
    return bibliothek;
  }

  function nadel(farbe, innen) {
    return '<svg class="bbm-nadel" viewBox="0 0 30 40" aria-hidden="true"><path d="M15 0C6.7 0 0 6.6 0 14.8 0 25.6 15 40 15 40s15-14.4 15-25.2C30 6.6 23.3 0 15 0z" fill="' +
      farbe + '"/><circle cx="15" cy="14.5" r="5" fill="' + innen + '"/></svg>';
  }

  function freigegeben() {
    try { return Number(localStorage.getItem(MERKEN)) > Date.now(); } catch (e) { return false; }
  }

  // Widerruf: Knopf auf der Karte oder ein beliebiges Element mit data-bb-map-widerruf (Datenschutzseite).
  function widerrufen() {
    try { localStorage.removeItem(MERKEN); } catch (e) {}
    Array.prototype.forEach.call(document.querySelectorAll('bb-map'), function (k) { k.aus(); });
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-bb-map-widerruf]')) widerrufen();
  });

  class BBMapElement extends HTMLElement {
    connectedCallback() {
      if (this.fertig) return;
      this.fertig = true;
      if (!document.getElementById('bb-map-css')) {
        var st = document.createElement('style');
        st.id = 'bb-map-css';
        st.textContent = CSS;
        document.head.appendChild(st);
      }
      var cs = getComputedStyle(this), eingabe = {}, el = this;
      NAMEN.forEach(function (k) { eingabe[k] = cs.getPropertyValue(kebab(k)); });
      var f = this.farben = farben(eingabe);
      Object.keys(f).forEach(function (k) { el.style.setProperty(kebab(k), f[k]); });
      var hoehe = this.getAttribute('hoehe');
      if (hoehe) this.style.setProperty('--bb-map-h', hoehe + 'px');
      var ds = this.getAttribute('datenschutz') || '/datenschutz';
      var route = this.getAttribute('route');
      this.innerHTML =
        '<div class="bbm-karte"></div>' +
        '<div class="bbm-bild" style="background-image:url(\'' + (this.getAttribute('platzhalter') || '') + '\')">' +
        nadel(f.akzent, f.land) +
        '<span class="bbm-quelle">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></span></div>' +
        '<div class="bbm-box"><strong>Interaktive Karte</strong>' +
        '<p>Beim Laden werden Kartendaten von OpenFreeMap abgerufen<span class="bbm-lang">, dabei wird ' + (this.getAttribute('anrede') === 'du' ? 'deine' : 'Ihre') + ' IP-Adresse übermittelt</span>. ' +
        '<a href="' + ds + '">Datenschutzerklärung</a></p>' +
        '<label><input type="checkbox"> Freigabe für 30 Tage merken</label>' +
        '<div class="bbm-zeile"><button type="button" class="bbm-laden">Karte laden</button>' +
        (route ? '<a href="' + route + '" target="_blank" rel="noopener">Route planen</a>' : '') + '</div></div>' +
        '<button type="button" class="bbm-aus" hidden>Karte wieder ausblenden</button>';
      this.querySelector('.bbm-laden').addEventListener('click', function () {
        if (el.querySelector('input').checked) {
          try { localStorage.setItem(MERKEN, String(Date.now() + 30 * 864e5)); } catch (e) {}
        }
        el.laden();
      });
      this.querySelector('.bbm-aus').addEventListener('click', widerrufen);
      if (freigegeben()) this.laden();
    }

    aus() {
      if (this.karte) { this.karte.remove(); this.karte = null; }
      this.classList.remove('an');
      var knopf = this.querySelector('.bbm-aus');
      if (knopf) knopf.hidden = true;
    }

    laden() {
      var el = this, f = this.farben;
      if (this.karte) return Promise.resolve();
      var mitte = [Number(this.getAttribute('lng')), Number(this.getAttribute('lat'))];
      return ladeBibliothek(this.getAttribute('basis') || BASIS).then(function (ml) {
        var karte = el.karte = new ml.Map({
          container: el.querySelector('.bbm-karte'),
          style: stil(f),
          center: mitte,
          zoom: Number(el.getAttribute('zoom') || 13),
          cooperativeGestures: true,
          attributionControl: { compact: false }
        });
        karte.addControl(new ml.NavigationControl({ showCompass: false }), 'top-right');
        var pin = document.createElement('div');
        pin.innerHTML = nadel(f.akzent, f.land);
        pin.firstChild.style.cssText = 'position:static;margin:0;display:block';
        var marker = new ml.Marker({ element: pin, anchor: 'bottom' }).setLngLat(mitte).addTo(karte);
        var titel = el.getAttribute('titel');
        if (titel) {
          var p = document.createElement('span');
          p.textContent = titel;
          marker.setPopup(new ml.Popup({ offset: 42, closeButton: false }).setDOMContent(p));
        }
        karte.once('idle', function () {
          el.classList.add('an');
          el.querySelector('.bbm-aus').hidden = false;
        });
      }).catch(function (e) {
        el.querySelector('.bbm-box p').textContent = e.message;
      });
    }
  }
  customElements.define('bb-map', BBMapElement);
})(typeof globalThis !== 'undefined' ? globalThis : this);
