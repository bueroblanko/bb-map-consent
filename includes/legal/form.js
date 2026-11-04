/*
 * Formular der Rechtstexte-Zentrale (Klick-Assistent), angezeigt in WordPress und BB Studio.
 * Dieselbe Datei liegt in bb-map-consent (includes/legal/form.js) und bb-cms (public/bb-legal-form.js), bitte beide gleich halten.
 * Felder, Vorschau und Speichern kommen von der Zentrale, ueber einen Endpunkt der Website:
 * POST endpoint mit JSON {aktion: laden|vorschau|uebernehmen|speichern, typ, inputs, version}.
 * Antwort JSON; bei Fehler {fehler: "..."}.
 */
(function () {
  'use strict';
  var TYPEN = { imprint: 'Impressum', privacy_policy: 'Datenschutz' };
  var CSS = '.bblf{--bblf-rand:#dcdcde;--bblf-akzent:#2271b1;font-size:14px;line-height:1.5;color:#1d2327}'
    + '.bblf *{box-sizing:border-box}.bblf-tabs{display:flex;gap:6px;margin:0 0 14px;flex-wrap:wrap}'
    + '.bblf-tab{border:1px solid var(--bblf-rand);background:#fff;border-radius:4px;padding:6px 14px;cursor:pointer;font:inherit}'
    + '.bblf-tab[aria-selected=true]{background:var(--bblf-akzent);border-color:var(--bblf-akzent);color:#fff}'
    + '.bblf-raster{display:grid;grid-template-columns:200px minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:start}'
    + '@media (max-width:1100px){.bblf-raster{grid-template-columns:minmax(0,1fr)}}'
    + '.bblf-schritte{list-style:none;margin:0;padding:0}.bblf-schritte li{margin:0 0 4px}'
    + '.bblf-schritte button{display:flex;gap:8px;align-items:center;width:100%;text-align:left;border:0;background:none;padding:6px 8px;border-radius:4px;cursor:pointer;font:inherit;color:inherit}'
    + '.bblf-schritte button[aria-current=step]{background:#f0f6fc;font-weight:600}'
    + '.bblf-nr{display:inline-flex;width:22px;height:22px;border-radius:50%;border:1px solid var(--bblf-rand);align-items:center;justify-content:center;font-size:12px;flex:none}'
    + '.bblf-schritte .bblf-fertig .bblf-nr{background:var(--bblf-akzent);border-color:var(--bblf-akzent);color:#fff}'
    + '.bblf-karte{background:#fff;border:1px solid var(--bblf-rand);border-radius:6px;padding:16px}'
    + '.bblf-karte h2{font-size:16px;margin:0 0 12px}.bblf-feld{display:block;margin:0 0 14px}'
    + '.bblf-feld>span,.bblf-feld legend{display:block;font-weight:600;margin:0 0 4px;padding:0}'
    + '.bblf-feld input[type=text],.bblf-feld input[type=email],.bblf-feld input[type=url],.bblf-feld select,.bblf-feld textarea{width:100%;max-width:none;padding:6px 8px;border:1px solid #8c8f94;border-radius:4px;font:inherit}'
    + 'fieldset.bblf-feld{border:0;margin:0 0 14px;padding:0;min-width:0}.bblf-hilfe{display:block;color:#646970;font-size:12px;margin-top:3px;font-weight:400}'
    + '.bblf-optionen{max-height:260px;overflow:auto;border:1px solid var(--bblf-rand);border-radius:4px;padding:6px 8px}'
    + '.bblf-optionen label,.bblf-schalter{display:flex;gap:8px;align-items:flex-start;margin:3px 0;font-weight:400}'
    + '.bblf-suche{width:100%;margin:0 0 6px;padding:5px 8px;border:1px solid #8c8f94;border-radius:4px}'
    + '.bblf-zeile{display:flex;gap:6px;margin:0 0 6px;flex-wrap:wrap}.bblf-zeile input{flex:1 1 140px}'
    + '.bblf-fuss{display:flex;gap:8px;justify-content:space-between;margin-top:16px;flex-wrap:wrap}'
    + '.bblf-knopf{border:1px solid var(--bblf-akzent);color:var(--bblf-akzent);background:#fff;border-radius:4px;padding:6px 14px;cursor:pointer;font:inherit}'
    + '.bblf-knopf.bblf-primaer{background:var(--bblf-akzent);color:#fff}.bblf-knopf[disabled]{opacity:.6;cursor:default}'
    + '.bblf-hinweis{border-left:4px solid var(--bblf-akzent);background:#f0f6fc;padding:8px 12px;margin:0 0 14px;border-radius:2px}'
    + '.bblf-hinweis.bblf-fehler{border-color:#d63638;background:#fcf0f1}.bblf-hinweis.bblf-ok{border-color:#00a32a;background:#edfaef}'
    + '.bblf-vorschau iframe{width:100%;height:70vh;border:1px solid var(--bblf-rand);border-radius:4px;background:#fff}'
    + '.bblf-diff{margin:0 0 12px;padding-left:18px}.bblf-diff li{margin:0 0 4px}.bblf-alt{color:#8a2424;text-decoration:line-through}.bblf-neu{color:#00661f}';
  var VORSCHAU_CSS = 'body{font:15px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#1d2327;margin:16px;overflow-wrap:anywhere}h1{font-size:22px;line-height:1.3}h2{font-size:18px;line-height:1.3}h3{font-size:16px}';

  function el(tag, attrs, kinder) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') e.textContent = attrs[k];
      else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), attrs[k]);
      else if (attrs[k] === true) e.setAttribute(k, '');
      else if (attrs[k] !== false && attrs[k] != null) e.setAttribute(k, attrs[k]);
    });
    (kinder || []).forEach(function (k) { if (k) e.appendChild(typeof k === 'string' ? document.createTextNode(k) : k); });
    return e;
  }
  var gleich = function (a, b) { return JSON.stringify(a == null ? '' : a) === JSON.stringify(b == null ? '' : b); };
  var leer = function (v) { return v == null || v === '' || v === false || (Array.isArray(v) && !v.length); };
  var kopie = function (o) { return JSON.parse(JSON.stringify(o || {})); };

  function mount(wurzel, optionen) {
    var typen = (optionen.typen || Object.keys(TYPEN)).filter(function (t) { return TYPEN[t]; });
    var s = { typ: typen.indexOf(optionen.typ) >= 0 ? optionen.typ : typen[0], schritt: 0, form: null, inputs: {}, anfang: {}, meldung: null, laedt: false };
    var vorschauTimer = null;
    var vorschauNr = 0;
    var iframe = null;
    var fehlerListe = null;

    if (!document.getElementById('bblf-css')) document.head.appendChild(el('style', { id: 'bblf-css', text: CSS }));
    wurzel.classList.add('bblf');

    function rufe(daten) {
      var kopf = Object.assign({ 'Content-Type': 'application/json' }, optionen.kopf || {});
      return fetch(optionen.endpunkt, { method: 'POST', credentials: 'same-origin', headers: kopf, body: JSON.stringify(daten) })
        .then(function (r) { return r.json().catch(function () { return { fehler: 'Ungültige Antwort (HTTP ' + r.status + ').' }; }); })
        .then(function (j) { if (j && j.fehler) throw new Error(j.fehler); return j; });
    }

    function laden(typ) {
      s.typ = typ; s.schritt = 0; s.form = null; s.vorschau = null; s.laedt = true; zeichne();
      rufe({ aktion: 'laden', typ: typ }).then(function (form) {
        s.form = form; s.inputs = kopie(form.inputs); s.anfang = kopie(form.inputs); s.laedt = false;
        zeichne(); vorschau(0);
      }).catch(function (e) { s.laedt = false; s.meldung = { art: 'fehler', text: e.message }; zeichne(); });
    }

    function vorschau(warte) {
      clearTimeout(vorschauTimer);
      vorschauTimer = setTimeout(function () {
        var nr = ++vorschauNr;
        rufe({ aktion: 'vorschau', typ: s.typ, inputs: s.inputs }).then(function (v) {
          if (nr !== vorschauNr) return;
          var html = (v.texts || []).filter(function (t) { return t.language === 'de'; }).map(function (t) { return t.html; }).join('\n')
            || '<p>Noch keine Vorschau.</p>';
          s.vorschau = { html: html, fehler: v.render_errors || [] };
          zeigeVorschau();
        }).catch(function () { /* Vorschau ist Beiwerk */ });
      }, warte == null ? 600 : warte);
    }

    function zeigeVorschau() {
      if (!iframe || !s.vorschau) return;
      iframe.srcdoc = '<!doctype html><meta charset="utf-8"><style>' + VORSCHAU_CSS + '</style>' + s.vorschau.html;
      fehlerListe.textContent = '';
      s.vorschau.fehler.forEach(function (f) { fehlerListe.appendChild(el('li', { text: f })); });
      fehlerListe.parentNode.hidden = !s.vorschau.fehler.length;
    }

    function setze(id, wert) { s.inputs[id] = wert; vorschau(); }

    function feld(f) {
      var v = s.inputs[f.id];
      var hilfe = f.help ? el('small', { class: 'bblf-hilfe', text: f.help }) : null;
      var titel = f.label + (f.required ? ' *' : '');
      if (f.localized) {
        var w = v && typeof v === 'object' ? v : { de: v || '', en: '' };
        return el('fieldset', { class: 'bblf-feld' }, [el('legend', { text: titel })].concat(['de', 'en'].map(function (l) {
          return el('label', { class: 'bblf-zeile' }, [l.toUpperCase() + ' ', el('input', { type: 'text', value: w[l] || '', oninput: function (e) {
            var n = Object.assign({ de: '', en: '' }, s.inputs[f.id] && typeof s.inputs[f.id] === 'object' ? s.inputs[f.id] : {}); n[l] = e.target.value; setze(f.id, n);
          } })]);
        })).concat([hilfe]));
      }
      if (f.type === 'bool') {
        return el('label', { class: 'bblf-feld bblf-schalter' }, [el('input', { type: 'checkbox', checked: !!v, onchange: function (e) { setze(f.id, e.target.checked); } }),
          el('span', {}, [f.label, hilfe])]);
      }
      if (f.type === 'multiselect') {
        var gewaehlt = Array.isArray(v) ? v.slice() : [];
        var boxen = Object.keys(f.options || {}).map(function (k) {
          return el('label', { 'data-text': (f.options[k] || '').toLowerCase() }, [el('input', { type: 'checkbox', value: k, checked: gewaehlt.indexOf(k) >= 0, onchange: function (e) {
            var a = Array.isArray(s.inputs[f.id]) ? s.inputs[f.id].slice() : [];
            if (e.target.checked && a.indexOf(k) < 0) a.push(k);
            if (!e.target.checked) a = a.filter(function (x) { return x !== k; });
            setze(f.id, a);
          } }), el('span', { text: f.options[k] })]);
        });
        var liste = el('div', { class: 'bblf-optionen' }, boxen);
        var suche = boxen.length > 12 ? el('input', { type: 'search', class: 'bblf-suche', placeholder: 'Filtern …', oninput: function (e) {
          var q = e.target.value.toLowerCase(); boxen.forEach(function (b) { b.hidden = q && b.getAttribute('data-text').indexOf(q) < 0; });
        } }) : null;
        return el('fieldset', { class: 'bblf-feld' }, [el('legend', { text: titel }), suche, liste, hilfe]);
      }
      if (f.type === 'list') {
        var kasten = el('fieldset', { class: 'bblf-feld' }, [el('legend', { text: titel })]);
        var zeilen = el('div');
        var eintraege = function () { return Array.isArray(s.inputs[f.id]) ? s.inputs[f.id] : []; };
        var zeichneListe = function () {
          zeilen.textContent = '';
          eintraege().forEach(function (eintrag, i) {
            zeilen.appendChild(el('div', { class: 'bblf-zeile' }, (f.fields || []).map(function (c) {
              return el('input', { type: 'text', placeholder: c.label, 'aria-label': c.label, value: eintrag[c.id] || '', oninput: function (e) {
                var a = kopie(eintraege()); a[i][c.id] = e.target.value; setze(f.id, a);
              } });
            }).concat([el('button', { type: 'button', class: 'bblf-knopf', text: 'Entfernen', onclick: function () {
              var a = kopie(eintraege()); a.splice(i, 1); setze(f.id, a); zeichneListe();
            } })])));
          });
        };
        zeichneListe();
        kasten.appendChild(zeilen);
        kasten.appendChild(el('button', { type: 'button', class: 'bblf-knopf', text: 'Eintrag hinzufügen', onclick: function () {
          var a = kopie(eintraege()); a.push({ id: Math.random().toString(16).slice(2, 18) }); s.inputs[f.id] = a; zeichneListe();
        } }));
        if (hilfe) kasten.appendChild(hilfe);
        return kasten;
      }
      var eingabe;
      if (f.type === 'select') {
        eingabe = el('select', { onchange: function (e) { setze(f.id, e.target.value); } }, [el('option', { value: '', text: 'Bitte wählen' })].concat(
          Object.keys(f.options || {}).map(function (k) { return el('option', { value: k, text: f.options[k], selected: k === v }); })));
      } else if (f.type === 'textarea') {
        eingabe = el('textarea', { rows: 5, oninput: function (e) { setze(f.id, e.target.value); } });
        eingabe.value = v || '';
      } else {
        eingabe = el('input', { type: f.type === 'email' || f.type === 'url' ? f.type : 'text', value: v || '', oninput: function (e) { setze(f.id, e.target.value); } });
      }
      return el('label', { class: 'bblf-feld' }, [el('span', { text: titel }), eingabe, hilfe]);
    }

    function aenderungen() {
      var liste = [];
      (s.form ? s.form.steps : []).forEach(function (st) {
        st.fields.forEach(function (f) {
          if (gleich(s.anfang[f.id], s.inputs[f.id])) return;
          var text = function (x) {
            if (leer(x)) return '(leer)';
            if (x === true) return 'Ja';
            if (Array.isArray(x)) return x.map(function (i) { return typeof i === 'object' ? Object.keys(i).filter(function (k) { return k !== 'id'; }).map(function (k) { return i[k]; }).join(' ') : ((f.options || {})[i] || i); }).join(', ');
            if (typeof x === 'object') return Object.keys(x).map(function (k) { return k.toUpperCase() + ': ' + x[k]; }).join(' / ');
            return (f.options || {})[x] || String(x);
          };
          liste.push(el('li', {}, [el('strong', { text: f.label + ': ' }), el('span', { class: 'bblf-alt', text: text(s.anfang[f.id]) }), ' → ', el('span', { class: 'bblf-neu', text: text(s.inputs[f.id]) })]));
        });
      });
      return liste;
    }

    function speichern(knopf) {
      knopf.disabled = true;
      rufe({ aktion: 'speichern', typ: s.typ, inputs: s.inputs, version: s.form.version }).then(function (r) {
        s.meldung = { art: r.unvollstaendig ? 'fehler' : 'ok', text: r.meldung || 'Gespeichert.' };
        laden(s.typ);
      }).catch(function (e) { knopf.disabled = false; s.meldung = { art: 'fehler', text: e.message }; zeichne(); });
    }

    function uebernehmen(knopf) {
      knopf.disabled = true;
      rufe({ aktion: 'uebernehmen', typ: s.typ }).then(function (r) {
        Object.keys(r.inputs || {}).forEach(function (k) { if (leer(s.inputs[k])) s.inputs[k] = r.inputs[k]; });
        s.form.prefill = false; s.schritt = 0;
        s.meldung = { art: 'ok', text: 'Angaben aus dem bisherigen Impressum übernommen. Bitte prüfen und speichern.' + ((r.unknown || []).length ? ' Im Zusatztext: ' + r.unknown.join(', ') + '.' : '') };
        zeichne(); vorschau(0);
      }).catch(function (e) { knopf.disabled = false; s.meldung = { art: 'fehler', text: e.message }; zeichne(); });
    }

    function zeichne() {
      wurzel.textContent = '';
      if (typen.length > 1) {
        wurzel.appendChild(el('div', { class: 'bblf-tabs', role: 'tablist' }, typen.map(function (t) {
          return el('button', { type: 'button', class: 'bblf-tab', role: 'tab', 'aria-selected': t === s.typ ? 'true' : 'false', text: TYPEN[t], onclick: function () {
            if (t === s.typ) return;
            if (aenderungen().length && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
            s.meldung = null; laden(t);
          } });
        })));
      }
      if (s.meldung) wurzel.appendChild(el('div', { class: 'bblf-hinweis' + (s.meldung.art === 'fehler' ? ' bblf-fehler' : s.meldung.art === 'ok' ? ' bblf-ok' : ''), role: 'status', text: s.meldung.text }));
      if (s.laedt) { wurzel.appendChild(el('p', { text: 'Formular wird geladen …' })); return; }
      if (!s.form) return;
      if (s.form.mode && s.form.mode !== 'generated') {
        wurzel.appendChild(el('div', { class: 'bblf-hinweis', text: 'Diese Website hat individuelle Texte. Das Formular gilt nur für Texte aus Bausteinen, Änderungen nimmt die Agentur vor.' }));
        return;
      }
      var schritte = s.form.steps;
      var letzter = schritte.length;
      if (s.form.prefill) {
        var k = el('button', { type: 'button', class: 'bblf-knopf bblf-primaer', text: 'Aus bisherigem Impressum übernehmen' });
        k.addEventListener('click', function () { uebernehmen(k); });
        wurzel.appendChild(el('div', { class: 'bblf-hinweis' }, [el('strong', { text: 'Das Impressum ist noch fester Text. ' }),
          'Die Angaben lassen sich daraus in die Felder übernehmen, was keinem Feld entspricht, landet im Zusatztext. ', k]));
      }
      var nav = el('ol', { class: 'bblf-schritte' }, schritte.map(function (st, i) {
        return el('li', { class: i < s.schritt ? 'bblf-fertig' : '' }, [el('button', { type: 'button', 'aria-current': i === s.schritt ? 'step' : false, onclick: function () { s.schritt = i; zeichne(); } },
          [el('span', { class: 'bblf-nr', text: String(i + 1) }), st.title])]);
      }).concat([el('li', {}, [el('button', { type: 'button', 'aria-current': s.schritt === letzter ? 'step' : false, onclick: function () { s.schritt = letzter; zeichne(); } },
        [el('span', { class: 'bblf-nr', text: '✓' }), 'Abschluss'])])]));

      var haupt = el('div', { class: 'bblf-karte' });
      if (s.schritt < letzter) {
        haupt.appendChild(el('h2', { text: schritte[s.schritt].title }));
        schritte[s.schritt].fields.forEach(function (f) { haupt.appendChild(feld(f)); });
        haupt.appendChild(el('div', { class: 'bblf-fuss' }, [
          s.schritt > 0 ? el('button', { type: 'button', class: 'bblf-knopf', text: 'Zurück', onclick: function () { s.schritt--; zeichne(); } }) : el('span'),
          el('button', { type: 'button', class: 'bblf-knopf bblf-primaer', text: 'Weiter', onclick: function () { s.schritt++; zeichne(); } }),
        ]));
      } else {
        var diff = aenderungen();
        haupt.appendChild(el('h2', { text: 'Abschluss' }));
        haupt.appendChild(diff.length ? el('ul', { class: 'bblf-diff' }, diff) : el('p', { text: 'Keine Änderungen.' }));
        var knopf = el('button', { type: 'button', class: 'bblf-knopf bblf-primaer', text: 'Speichern und veröffentlichen', disabled: !diff.length });
        knopf.addEventListener('click', function () { speichern(knopf); });
        haupt.appendChild(el('div', { class: 'bblf-fuss' }, [
          el('button', { type: 'button', class: 'bblf-knopf', text: 'Zurück', onclick: function () { s.schritt--; zeichne(); } }),
          diff.length ? el('button', { type: 'button', class: 'bblf-knopf', text: 'Änderungen verwerfen', onclick: function () { s.inputs = kopie(s.anfang); s.schritt = 0; zeichne(); vorschau(0); } }) : null,
          knopf,
        ]));
      }

      fehlerListe = el('ul');
      var fehlerKasten = el('div', { class: 'bblf-hinweis bblf-fehler', hidden: true }, [el('strong', { text: 'Noch nicht vollständig. Bis dahin bleibt der bisherige Text online.' }), fehlerListe]);
      iframe = el('iframe', { sandbox: '', title: 'Vorschau' });
      var seite = el('div', { class: 'bblf-vorschau' }, [el('div', { class: 'bblf-karte' }, [el('h2', { text: 'Vorschau' }), fehlerKasten, iframe])]);
      wurzel.appendChild(el('div', { class: 'bblf-raster' }, [nav, haupt, seite]));
      zeigeVorschau();
    }

    window.addEventListener('beforeunload', function (e) { if (aenderungen().length) { e.preventDefault(); e.returnValue = ''; } });
    laden(s.typ);
  }

  window.BBLegalForm = { mount: mount };
  document.querySelectorAll('[data-bb-legal-form]').forEach(function (w) {
    var c = {};
    try { c = JSON.parse(w.getAttribute('data-bb-legal-form') || '{}'); } catch (e) { /* leer */ }
    mount(w, c);
  });
})();
