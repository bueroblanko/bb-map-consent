(() => {
  'use strict';
  const node = document.getElementById('bbmc-cmp-config');
  if (!node || !window.CookieConsent) return;
  const config = JSON.parse(node.textContent);
  const own = config.texts || {};
  const esc = (text) => String(text).replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
  const privacy = config.privacy ? '<a href="' + esc(config.privacy) + '">Datenschutzerklärung</a>' : 'Datenschutzerklärung';
  const texts = {
    analytics: ['Statistik', 'Hilft zu verstehen, wie die Website genutzt wird.'],
    marketing: ['Marketing', 'Wird für Werbung und die Erfolgsmessung von Anzeigen genutzt.']
  };
  // Cookies these services set themselves; removed when consent is withdrawn.
  const clear = {
    analytics: [/^_ga/, /^_gid$/, /^_gat/, /^_pk_/, /^_hj/, /^mf_/, /^_clck$/, /^_clsk$/, /^MATOMO_/],
    marketing: [/^_gcl/, /^_fbp$/, /^_fbc$/, /^_ttp$/, /^li_/, /^_uet/]
  };
  const root = location.hostname.split('.').slice(-2).join('.');
  // Own texts (settings page) win; {datenschutz} and {dienste} are filled in.
  const text = (key, fallback, services) => (own[key] ? own[key].replace(/\{datenschutz\}/g, privacy).replace(/\{dienste\}/g, services || '') : fallback);
  const categories = {necessary: {enabled: true, readOnly: true}};
  const sections = [{description: text('intro', 'Sie entscheiden, welche Dienste geladen werden. Ohne Zustimmung werden nur technisch notwendige Funktionen genutzt. Ihre Auswahl gilt 30 Tage und lässt sich jederzeit über „Datenschutz-Einstellungen“ ändern.')},
    {title: 'Notwendig', description: text('necessary', 'Speichert Ihre Auswahl in diesem Browser.'), linkedCategory: 'necessary'}];
  const names = [];
  Object.keys(texts).forEach((key) => {
    const services = config.categories[key];
    if (!services) return;
    names.push(texts[key][0]);
    // Trackers often set cookies on the main domain (.kunde.de), also when the site runs on a subdomain.
    const cookies = clear[key].map((name) => ({name}));
    if (root !== location.hostname.replace(/^www\./, '')) clear[key].forEach((name) => cookies.push({name, domain: root}));
    categories[key] = {autoClear: {cookies, reloadPage: true}};
    const list = services.map(esc).join(', ');
    sections.push({title: texts[key][0], description: text(key, texts[key][1] + ' Auf dieser Website: ' + list + '.', list), linkedCategory: key});
  });
  sections.push({title: 'Weitere Informationen', description: 'Details finden Sie in der ' + privacy + '.'});
  window.CookieConsent.run({
    revision: config.revision,
    cookie: {name: 'bbmc_cmp', useLocalStorage: true, expiresAfterDays: 30},
    guiOptions: {
      consentModal: {layout: 'box', position: 'bottom left', equalWeightButtons: true, flipButtons: false},
      preferencesModal: {layout: 'box', equalWeightButtons: true, flipButtons: false}
    },
    categories,
    // Already running Google tags would rewrite their cookies when the page unloads for the reload.
    onChange: () => {
      if (typeof window.gtag !== 'function') return;
      const state = (category) => (window.CookieConsent.acceptedCategory(category) ? 'granted' : 'denied');
      window.gtag('consent', 'update', {analytics_storage: state('analytics'), ad_storage: state('marketing'), ad_user_data: state('marketing'), ad_personalization: state('marketing')});
    },
    language: {default: 'de', translations: {de: {
      consentModal: {
        title: text('title', 'Datenschutz-Einstellungen'),
        description: text('description', 'Diese Website möchte ' + (names.length > 1 ? names[0] + '- und ' + names[1] : names[0]) + 'dienste nutzen. Diese werden erst nach Ihrer Zustimmung geladen. Ihre Auswahl können Sie jederzeit über „Datenschutz-Einstellungen“ ändern. Mehr in der ' + privacy + '.'),
        acceptAllBtn: text('accept_all', 'Alle akzeptieren'),
        acceptNecessaryBtn: text('accept_necessary', 'Nur notwendige'),
        showPreferencesBtn: text('preferences', 'Einstellungen')
      },
      preferencesModal: {
        title: text('title', 'Datenschutz-Einstellungen'),
        acceptAllBtn: text('accept_all', 'Alle akzeptieren'),
        acceptNecessaryBtn: text('accept_necessary', 'Nur notwendige'),
        savePreferencesBtn: text('save', 'Auswahl speichern'),
        closeIconLabel: 'Schließen',
        sections
      }
    }}}
  });
  // Footer links: [bb_consent_settings] or any menu link to #bb-consent-settings.
  document.addEventListener('click', (event) => {
    const link = event.target.closest && event.target.closest('a[href$="#bb-consent-settings"], .bbmc-cmp-settings');
    if (!link) return;
    event.preventDefault();
    window.CookieConsent.showPreferences();
  });
})();
