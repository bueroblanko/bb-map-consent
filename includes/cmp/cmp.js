(() => {
  'use strict';
  const node = document.getElementById('bbmc-cmp-config');
  if (!node || !window.CookieConsent) return;
  const config = JSON.parse(node.textContent);
  const own = config.texts || {};
  const esc = (text) => String(text).replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
  const privacy = config.privacy ? '<a href="' + esc(config.privacy) + '">Datenschutzerklärung</a>' : 'Datenschutzerklärung';
  const imprint = config.imprint ? '<a href="' + esc(config.imprint) + '">Impressum</a>' : 'Impressum';
  // Default texts per form of address, following the Borlabs Cookie 3.5 standard texts.
  // Placeholders: {kategorien}, {datenschutz}, {impressum}, {einstellungen}, {dienste}.
  const defaults = {
    sie: {
      title: 'Datenschutz-Präferenz',
      description: 'Wir benötigen Ihre Einwilligung, bevor Sie unsere Website weiter besuchen können.<br><br>Wenn Sie unter 16 Jahre alt sind und Ihre Einwilligung zu optionalen Services geben möchten, müssen Sie Ihre Erziehungsberechtigten um Erlaubnis bitten.<br><br>Wir verwenden Cookies und andere Technologien auf unserer Website. Einige von ihnen sind essenziell, während andere uns helfen, diese Website und Ihre Erfahrung zu verbessern. Personenbezogene Daten können verarbeitet werden (z. B. IP-Adressen), z. B. für personalisierte Anzeigen und Inhalte oder die Messung von Anzeigen und Inhalten. Weitere Informationen über die Verwendung Ihrer Daten finden Sie in unserer {datenschutz}. Es besteht keine Verpflichtung, in die Verarbeitung Ihrer Daten einzuwilligen, um dieses Angebot zu nutzen. Sie können Ihre Auswahl jederzeit unter {einstellungen} widerrufen oder anpassen. Bitte beachten Sie, dass aufgrund individueller Einstellungen möglicherweise nicht alle Funktionen der Website verfügbar sind.<br><br>Einige Services verarbeiten personenbezogene Daten in den USA. Mit Ihrer Einwilligung zur Nutzung dieser Services willigen Sie auch in die Verarbeitung Ihrer Daten in den USA gemäß Art. 49 (1) lit. a DSGVO ein. Der EuGH stuft die USA als ein Land mit unzureichendem Datenschutz nach EU-Standards ein. Es besteht beispielsweise die Gefahr, dass US-Behörden personenbezogene Daten in Überwachungsprogrammen verarbeiten, ohne dass für Europäerinnen und Europäer eine Klagemöglichkeit besteht.',
      accept_all: 'Ich akzeptiere alle',
      accept_necessary: 'Nur essenzielle Cookies akzeptieren',
      preferences: 'Individuelle Datenschutz-Präferenzen',
      save: 'Speichern',
      intro: 'Hier finden Sie eine Übersicht über alle verwendeten Services. Sie können Ihre Einwilligung für ganze Kategorien geben.',
      necessary: 'Essenzielle Services ermöglichen grundlegende Funktionen und sind für das ordnungsgemäße Funktionieren der Website erforderlich.',
      analytics: 'Statistik-Cookies sammeln Nutzungsdaten, die uns Aufschluss darüber geben, wie unsere Besucher mit unserer Website umgehen. Auf dieser Website: {dienste}.',
      marketing: 'Marketing Services werden von Drittanbietern oder Herausgebern genutzt, um personalisierte Werbung anzuzeigen. Sie tun dies, indem sie Besucher über Websites hinweg verfolgen. Auf dieser Website: {dienste}.',
      more: 'Weitere Informationen über die Verwendung Ihrer Daten finden Sie in unserer {datenschutz} und im {impressum}.'
    },
    du: {
      title: 'Datenschutz-Präferenz',
      description: 'Wir benötigen deine Einwilligung, bevor du unsere Website weiter besuchen kannst.<br><br>Wenn du unter 16 Jahre alt bist und deine Einwilligung zu optionalen Services geben möchtest, musst du deine Erziehungsberechtigten um Erlaubnis bitten.<br><br>Wir verwenden Cookies und andere Technologien auf unserer Website. Einige von ihnen sind essenziell, während andere uns helfen, diese Website und deine Erfahrung zu verbessern. Personenbezogene Daten können verarbeitet werden (z. B. IP-Adressen), z. B. für personalisierte Anzeigen und Inhalte oder die Messung von Anzeigen und Inhalten. Weitere Informationen über die Verwendung deiner Daten findest du in unserer {datenschutz}. Es besteht keine Verpflichtung, in die Verarbeitung deiner Daten einzuwilligen, um dieses Angebot zu nutzen. Du kannst deine Auswahl jederzeit unter {einstellungen} widerrufen oder anpassen. Bitte beachte, dass aufgrund individueller Einstellungen möglicherweise nicht alle Funktionen der Website verfügbar sind.<br><br>Einige Services verarbeiten personenbezogene Daten in den USA. Mit deiner Einwilligung zur Nutzung dieser Services willigst du auch in die Verarbeitung deiner Daten in den USA gemäß Art. 49 (1) lit. a DSGVO ein. Der EuGH stuft die USA als ein Land mit unzureichendem Datenschutz nach EU-Standards ein. Es besteht beispielsweise die Gefahr, dass US-Behörden personenbezogene Daten in Überwachungsprogrammen verarbeiten, ohne dass für Europäerinnen und Europäer eine Klagemöglichkeit besteht.',
      accept_all: 'Alles akzeptieren',
      accept_necessary: 'Alles ablehnen',
      preferences: 'Individuelle Cookie-Einstellungen',
      save: 'Meine Auswahl speichern',
      intro: 'Hier findest du eine Übersicht über alle verwendeten Services. Du kannst deine Einwilligung für ganze Kategorien geben.',
      necessary: 'Essenzielle Services ermöglichen grundlegende Funktionen und sind für das ordnungsgemäße Funktionieren der Website erforderlich.',
      analytics: 'Statistik-Cookies sammeln Nutzungsdaten, die uns Aufschluss darüber geben, wie unsere Besucher mit unserer Website umgehen. Auf dieser Website: {dienste}.',
      marketing: 'Marketing Services werden von Drittanbietern oder Herausgebern genutzt, um personalisierte Werbung anzuzeigen. Sie tun dies, indem sie Besucher über Websites hinweg verfolgen. Auf dieser Website: {dienste}.',
      more: 'Weitere Informationen über die Verwendung deiner Daten findest du in unserer {datenschutz} und im {impressum}.'
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
  const text = (key, services) => (own[key] || base[key]).replace(/\{datenschutz\}/g, privacy).replace(/\{impressum\}/g, imprint).replace(/\{einstellungen\}/g, '<a href="#bb-consent-settings">Einstellungen</a>').replace(/\{kategorien\}/g, kategorien).replace(/\{dienste\}/g, services || '');
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
      consentModal: {layout: 'box', position: 'middle center', equalWeightButtons: true, flipButtons: false},
      preferencesModal: {layout: 'box', equalWeightButtons: true, flipButtons: false}
    },
    disablePageInteraction: true,
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
        showPreferencesBtn: text('preferences'),
        footer: privacy + ' ' + imprint
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
