(() => {
  'use strict';
  const node = document.getElementById('bbmc-cmp-config');
  if (!node || !window.CookieConsent) return;
  const config = JSON.parse(node.textContent);
  const own = config.texts || {};
  const esc = (text) => String(text).replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
  const privacy = config.privacy ? '<a href="' + esc(config.privacy) + '">Datenschutzerklärung</a>' : 'Datenschutzerklärung';
  // Default texts per form of address. Banner short (mobile), details in the settings dialog.
  // Placeholders: {kategorien}, {datenschutz}, {dienste}.
  const defaults = {
    sie: {
      title: 'Datenschutz-Einstellungen',
      description: 'Wir möchten auf dieser Website {kategorien} nutzen. Diese werden erst geladen, wenn Sie einwilligen. Dabei können personenbezogene Daten wie Ihre IP-Adresse verarbeitet werden, teilweise auch in den USA. Ihre Einwilligung ist freiwillig und lässt sich jederzeit über „Datenschutz-Einstellungen“ widerrufen. Mehr in der {datenschutz}.',
      accept_all: 'Alle akzeptieren',
      accept_necessary: 'Nur essenzielle',
      preferences: 'Einstellungen',
      save: 'Auswahl speichern',
      intro: 'Hier legen Sie fest, welche Dienste geladen werden dürfen. Essenzielle Funktionen sind immer aktiv. Eine Einwilligung ist nicht erforderlich, um die Website zu nutzen. Einige Dienste verarbeiten Daten in den USA. Mit Ihrer Einwilligung stimmen Sie auch dieser Verarbeitung zu (Art. 49 Abs. 1 lit. a DSGVO). Für die USA besteht nach Einschätzung des EuGH kein mit der EU vergleichbares Datenschutzniveau. Wenn Sie unter 16 Jahre alt sind, fragen Sie bitte Ihre Erziehungsberechtigten um Erlaubnis. Ihre Auswahl gilt 30 Tage.',
      necessary: 'Ermöglichen grundlegende Funktionen der Website, zum Beispiel das Speichern dieser Auswahl in Ihrem Browser.',
      analytics: 'Erfassen Nutzungsdaten, damit wir verstehen, wie Besucher die Website nutzen. Auf dieser Website: {dienste}.',
      marketing: 'Werden von Anbietern genutzt, um Werbung zu personalisieren und ihren Erfolg zu messen, auch über mehrere Websites hinweg. Auf dieser Website: {dienste}.',
      more: 'Weitere Informationen finden Sie in der {datenschutz}.'
    },
    du: {
      title: 'Datenschutz-Einstellungen',
      description: 'Wir möchten auf dieser Website {kategorien} nutzen. Diese werden erst geladen, wenn du einwilligst. Dabei können personenbezogene Daten wie deine IP-Adresse verarbeitet werden, teilweise auch in den USA. Deine Einwilligung ist freiwillig und lässt sich jederzeit über „Datenschutz-Einstellungen“ widerrufen. Mehr in der {datenschutz}.',
      accept_all: 'Alle akzeptieren',
      accept_necessary: 'Nur essenzielle',
      preferences: 'Einstellungen',
      save: 'Auswahl speichern',
      intro: 'Hier legst du fest, welche Dienste geladen werden dürfen. Essenzielle Funktionen sind immer aktiv. Eine Einwilligung ist nicht erforderlich, um die Website zu nutzen. Einige Dienste verarbeiten Daten in den USA. Mit deiner Einwilligung stimmst du auch dieser Verarbeitung zu (Art. 49 Abs. 1 lit. a DSGVO). Für die USA besteht nach Einschätzung des EuGH kein mit der EU vergleichbares Datenschutzniveau. Wenn du unter 16 Jahre alt bist, frag bitte deine Erziehungsberechtigten um Erlaubnis. Deine Auswahl gilt 30 Tage.',
      necessary: 'Ermöglichen grundlegende Funktionen der Website, zum Beispiel das Speichern dieser Auswahl in deinem Browser.',
      analytics: 'Erfassen Nutzungsdaten, damit wir verstehen, wie Besucher die Website nutzen. Auf dieser Website: {dienste}.',
      marketing: 'Werden von Anbietern genutzt, um Werbung zu personalisieren und ihren Erfolg zu messen, auch über mehrere Websites hinweg. Auf dieser Website: {dienste}.',
      more: 'Weitere Informationen findest du in der {datenschutz}.'
    }
  };
  const base = defaults[config.address] || defaults.sie;
  const titles = {necessary: 'Essenziell', analytics: 'Statistik', marketing: 'Marketing'};
  // Cookies these services set themselves; removed when consent is withdrawn.
  const clear = {
    analytics: [/^_ga/, /^_gid$/, /^_gat/, /^_pk_/, /^_hj/, /^mf_/, /^_clck$/, /^_clsk$/, /^MATOMO_/],
    marketing: [/^_gcl/, /^_fbp$/, /^_fbc$/, /^_ttp$/, /^li_/, /^_uet/]
  };
  const root = location.hostname.split('.').slice(-2).join('.');
  const used = ['analytics', 'marketing'].filter((key) => config.categories[key]);
  const kinds = used.map((key) => titles[key]);
  const kategorien = (kinds.length > 1 ? kinds[0] + '- und ' + kinds[1] : kinds[0]) + 'dienste';
  // Own texts (settings page) win over the defaults.
  const text = (key, services) => (own[key] || base[key]).replace(/\{datenschutz\}/g, privacy).replace(/\{kategorien\}/g, kategorien).replace(/\{dienste\}/g, services || '');
  const categories = {necessary: {enabled: true, readOnly: true}};
  const sections = [{description: text('intro')}, {title: titles.necessary, description: text('necessary'), linkedCategory: 'necessary'}];
  used.forEach((key) => {
    // Trackers often set cookies on the main domain (.kunde.de), also when the site runs on a subdomain.
    const cookies = clear[key].map((name) => ({name}));
    if (root !== location.hostname.replace(/^www\./, '')) clear[key].forEach((name) => cookies.push({name, domain: root}));
    categories[key] = {autoClear: {cookies, reloadPage: true}};
    sections.push({title: titles[key], description: text(key, config.categories[key].map(esc).join(', ')), linkedCategory: key});
  });
  sections.push({title: 'Weitere Informationen', description: text('more')});
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
        title: text('title'),
        description: text('description'),
        acceptAllBtn: text('accept_all'),
        acceptNecessaryBtn: text('accept_necessary'),
        showPreferencesBtn: text('preferences')
      },
      preferencesModal: {
        title: text('title'),
        acceptAllBtn: text('accept_all'),
        acceptNecessaryBtn: text('accept_necessary'),
        savePreferencesBtn: text('save'),
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
