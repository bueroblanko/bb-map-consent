<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Cookie banner for tracking (CookieConsent 3, bundled locally). Default off.
 * Known trackers are recognised in the finished HTML and turned into inactive
 * <script type="text/plain" data-category="…"> tags; CookieConsent runs them after consent.
 * External content (maps, videos, …) keeps its own placeholders and is not part of the banner.
 */
final class BBMC_Cmp {
    const OPTION = 'bbmc_cmp_enabled';
    const SEEN = 'bbmc_cmp_seen';
    const TEXTS = 'bbmc_cmp_texts';
    const ADDRESS = 'bbmc_cmp_address';
    /** Own texts per site (e.g. from eRecht24); empty fields keep the neutral defaults in cmp.js. */
    const TEXT_FIELDS = ['title' => 'Titel', 'description' => 'Banner-Text', 'accept_all' => 'Button „Alle akzeptieren“', 'accept_necessary' => 'Button „Nur notwendige“', 'preferences' => 'Button „Einstellungen“', 'save' => 'Button „Auswahl speichern“', 'intro' => 'Einleitung in den Einstellungen', 'necessary' => 'Beschreibung Notwendig', 'analytics' => 'Beschreibung Statistik', 'marketing' => 'Beschreibung Marketing', 'more' => 'Weitere Informationen'];
    const CATEGORIES = ['analytics' => 'Statistik', 'marketing' => 'Marketing'];
    /** id => [label, category, script/link URL pattern, inline code pattern, noscript pattern]. Order matters: first match wins. */
    const SERVICES = [
        'google_tag_manager' => ['Google Tag Manager', 'marketing', '~googletagmanager\.com/gtm\.js~i', '~googletagmanager\.com/gtm\.js~i', '~googletagmanager\.com/ns\.html~i'],
        'google_ads' => ['Google Ads', 'marketing', '~googletagmanager\.com/gtag/js\?[^"\'\s>]*id=(?:AW|DC|GT)-|googleadservices\.com|googlesyndication\.com|doubleclick\.net~i', '~gtag\(\s*[\'"]config[\'"]\s*,\s*[\'"](?:AW|DC|GT)-|googleadservices\.com~i', '~googleadservices\.com|doubleclick\.net~i'],
        'google_analytics' => ['Google Analytics', 'analytics', '~googletagmanager\.com/gtag/js|google-analytics\.com|analytics\.google\.com~i', '~\bgtag\s*\(|google-analytics\.com|GoogleAnalyticsObject|__gtagTracker~', '~google-analytics\.com~i'],
        'meta_pixel' => ['Meta-Pixel', 'marketing', '~connect\.facebook\.net~i', '~\bfbq\s*\(|connect\.facebook\.net~', '~facebook\.com/tr~i'],
        'linkedin' => ['LinkedIn Insight Tag', 'marketing', '~snap\.licdn\.com~i', '~_linkedin_partner_id|snap\.licdn\.com~', '~px\.ads\.linkedin\.com~i'],
        'tiktok' => ['TikTok-Pixel', 'marketing', '~analytics\.tiktok\.com~i', '~analytics\.tiktok\.com|\bttq\.(?:load|page)\s*\(~', '~analytics\.tiktok\.com~i'],
        'matomo' => ['Matomo', 'analytics', '~/(?:matomo|piwik)\.js(?:[?#"\']|$)|/container_[a-z0-9]+\.js~i', '~\b_paq\b|\b_mtm\b~', '~/(?:matomo|piwik)\.php~i'],
        'hotjar' => ['Hotjar', 'analytics', '~(?:static|script)\.hotjar\.com~i', '~_hjSettings|static\.hotjar\.com~', ''],
        'mouseflow' => ['Mouseflow', 'analytics', '~cdn\.mouseflow\.com~i', '~\b_mfq\b|cdn\.mouseflow\.com~', ''],
        'clarity' => ['Microsoft Clarity', 'analytics', '~clarity\.ms~i', '~clarity\.ms~', ''],
    ];
    /** Other consent plugins; the banner stays off while one is active so two never run in parallel. */
    const OTHER_CMPS = ['borlabs-cookie', 'complianz-gdpr', 'real-cookie-banner', 'cookie-law-info', 'cookiebot', 'cookie-notice', 'gdpr-cookie-compliance', 'uk-cookie-consent', 'cookie-script'];

    public static function boot() {
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('bbmc_settings_screen', [__CLASS__, 'screen']);
        add_action('admin_post_bbmc_cmp_reset', [__CLASS__, 'reset']);
        add_shortcode('bb_consent_settings', [__CLASS__, 'shortcode']);
        add_action('template_redirect', function () {
            if (self::active() && !is_feed() && !is_admin() && !wp_doing_ajax() && !isset($_GET['et_fb']) && !is_customize_preview()) { ob_start([__CLASS__, 'output']); }
        }, 0);
    }
    public static function active() {
        return (bool) get_option(self::OPTION, false) && !self::other_cmp();
    }
    public static function other_cmp() {
        foreach ((array) get_option('active_plugins', []) as $plugin) {
            if (in_array(strtok((string) $plugin, '/'), self::OTHER_CMPS, true)) { return (string) $plugin; }
        }
        return '';
    }
    /** Which known service a script tag (complete element) belongs to, or ''. */
    public static function classify_script($tag) {
        $open = substr($tag, 0, strpos($tag, '>') + 1);
        $type = strtolower(trim(BB_Map_Consent::attribute($open, 'type')));
        if ($type !== '' && !in_array($type, ['text/javascript', 'application/javascript', 'module'], true)) { return ''; }
        if (BB_Map_Consent::attribute($open, 'data-bbmc-cmp') !== '') { return ''; }
        $src = BB_Map_Consent::attribute($open, 'src');
        $code = $src === '' ? substr($tag, strlen($open)) : '';
        foreach (self::SERVICES as $id => $service) {
            if ($src !== '' ? preg_match($service[2], $src) : preg_match($service[3], $code)) { return $id; }
        }
        return '';
    }
    /** Returns [filtered html, ids of services found]. */
    public static function filter($html) {
        $found = [];
        $html = preg_replace_callback('~<!--.*?-->|<script\b[^>]*>.*?</script\s*>|<noscript\b[^>]*>.*?</noscript\s*>|<link\b[^>]*>~is', function ($m) use (&$found) {
            $tag = $m[0];
            if (strpos($tag, '<!--') === 0) { return $tag; }
            if (stripos($tag, '<script') === 0) {
                $id = self::classify_script($tag);
                if ($id === '') { return $tag; }
                $found[$id] = true;
                $open = substr($tag, 0, strpos($tag, '>') + 1);
                $inactive = preg_replace('~\stype\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27|[^\s>]+)~i', '', $open);
                $inactive = '<script type="text/plain" data-category="' . self::SERVICES[$id][1] . '" data-bbmc-service="' . $id . '"' . substr($inactive, 7);
                return $inactive . substr($tag, strlen($open));
            }
            if (stripos($tag, '<noscript') === 0) {
                // Tracking pixels in noscript would load without JavaScript and therefore without consent.
                foreach (self::SERVICES as $id => $service) {
                    if ($service[4] !== '' && preg_match($service[4], $tag)) { $found[$id] = true; return ''; }
                }
                return $tag;
            }
            if (preg_match('/preconnect|dns-prefetch|preload|prefetch/i', BB_Map_Consent::attribute($tag, 'rel'))) {
                $href = BB_Map_Consent::attribute($tag, 'href');
                foreach (self::SERVICES as $service) {
                    if ($href !== '' && preg_match($service[2], $href)) { return ''; }
                }
                if (preg_match('~^(?:https?:)?//(?:www\.)?(?:googletagmanager\.com|google-analytics\.com|connect\.facebook\.net|snap\.licdn\.com|static\.hotjar\.com|cdn\.mouseflow\.com|www\.clarity\.ms)/?$~i', $href)) { return ''; }
            }
            return $tag;
        }, $html);
        return [$html, array_keys($found)];
    }
    /** Services seen anywhere on the site so far; the banner always lists all of them. */
    public static function remember(array $found) {
        $seen = array_values(array_intersect(array_keys(self::SERVICES), array_unique(array_merge((array) get_option(self::SEEN, []), $found))));
        if ($seen !== array_values((array) get_option(self::SEEN, []))) { update_option(self::SEEN, $seen, true); }
        return $seen;
    }
    public static function config(array $seen, $privacy, array $texts = [], $address = 'sie', $imprint = '') {
        $categories = [];
        foreach ($seen as $id) { $categories[self::SERVICES[$id][1]][] = self::SERVICES[$id][0]; }
        // A newly seen service changes the revision, so earlier decisions are asked for again.
        return ['privacy' => $privacy, 'imprint' => $imprint, 'categories' => $categories, 'texts' => (object) self::clean_texts($texts), 'address' => $address === 'du' ? 'du' : 'sie', 'revision' => $seen ? (int) sprintf('%u', crc32(implode(',', $seen))) % 1000000 + 1 : 0];
    }
    public static function inject($html, array $config, $base, $version) {
        $css = '<link rel="stylesheet" id="bbmc-cmp-css" href="' . esc_url($base . 'vendor/cookieconsent.css?ver=' . $version) . '" media="all">';
        $js = '<script type="application/json" id="bbmc-cmp-config">' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>'
            . '<script data-bbmc-cmp="1" data-no-optimize="1" data-no-defer="1" data-cfasync="false" src="' . esc_url($base . 'vendor/cookieconsent.umd.js?ver=' . $version) . '"></script>'
            . '<script data-bbmc-cmp="1" data-no-optimize="1" data-no-defer="1" data-cfasync="false" src="' . esc_url($base . 'cmp.js?ver=' . $version) . '"></script>';
        $head = stripos($html, '</head>');
        $html = $head === false ? $css . $html : substr_replace($html, $css, $head, 0);
        $body = strripos($html, '</body>');
        return $body === false ? $html . $js : substr_replace($html, $js, $body, 0);
    }
    public static function output($html) {
        if (stripos($html, '<html') === false) { return $html; }
        [$html, $found] = self::filter($html);
        $seen = self::remember($found);
        if (!$seen) { return $html; }
        return self::inject($html, self::config($seen, get_privacy_policy_url() ?: home_url('/datenschutz'), (array) get_option(self::TEXTS, []), get_option(self::ADDRESS, 'sie'), self::imprint_url()), plugins_url('', __FILE__) . '/', BBMC_VERSION);
    }
    /** WordPress has no imprint setting; a published page with the slug impressum or imprint, else /impressum. */
    public static function imprint_url() {
        foreach (['impressum', 'imprint'] as $slug) {
            $page = get_page_by_path($slug);
            if ($page && $page->post_status === 'publish') { return get_permalink($page); }
        }
        return home_url('/impressum');
    }
    public static function shortcode() {
        return '<a href="#bb-consent-settings" class="bbmc-cmp-settings">Datenschutz-Einstellungen</a>';
    }
    /** Known keys only, simple inline HTML (links, bold) allowed. */
    public static function clean_texts(array $texts) {
        $clean = [];
        foreach (self::TEXT_FIELDS as $key => $label) {
            $value = isset($texts[$key]) && is_string($texts[$key]) ? trim(wp_kses($texts[$key], ['a' => ['href' => true, 'target' => true, 'rel' => true], 'strong' => [], 'b' => [], 'em' => [], 'br' => []])) : '';
            if ($value !== '') { $clean[$key] = $value; }
        }
        return $clean;
    }
    public static function register() {
        register_setting('bbmc_cmp', self::OPTION, ['type' => 'boolean', 'default' => false, 'sanitize_callback' => function ($v) { return (bool) $v; }]);
        register_setting('bbmc_cmp', self::ADDRESS, ['type' => 'string', 'default' => 'sie', 'sanitize_callback' => function ($v) { return $v === 'du' ? 'du' : 'sie'; }]);
        register_setting('bbmc_cmp', self::TEXTS, ['type' => 'array', 'default' => [], 'sanitize_callback' => function ($v) { return self::clean_texts(is_array($v) ? wp_unslash($v) : []); }]);
        add_filter('option_page_capability_bbmc_cmp', function () { return 'manage_options'; });
    }
    public static function reset() {
        if (!current_user_can('manage_options') || !check_admin_referer('bbmc_cmp_reset')) { wp_die('Keine Berechtigung.'); }
        delete_option(self::SEEN);
        wp_safe_redirect(admin_url('options-general.php?page=bb-consent'));
        exit;
    }
    public static function screen() {
        if (!current_user_can('manage_options')) { return; }
        echo '<hr><h2 id="cookie-banner">Cookie-Banner für Tracking</h2>';
        $other = self::other_cmp();
        if ($other) { echo '<div class="notice notice-warning inline"><p>Ein anderes Cookie-Plugin ist aktiv (' . esc_html($other) . '). Das Banner bleibt aus, bis es deaktiviert ist.</p></div>'; }
        echo '<p>Erkennt Google Analytics, Tag Manager, Google Ads, Meta-Pixel, LinkedIn, TikTok, Matomo, Hotjar, Mouseflow und Clarity im ausgelieferten HTML, lädt sie erst nach Zustimmung und zeigt dafür ein Banner. Externe Inhalte wie Karten und Videos behalten ihre Platzhalter. Im Footer <code>[bb_consent_settings]</code> oder einen Menülink auf <code>#bb-consent-settings</code> einsetzen.</p><form method="post" action="options.php">';
        settings_fields('bbmc_cmp');
        echo '<label><input type="checkbox" name="' . self::OPTION . '" value="1"' . checked((bool) get_option(self::OPTION, false), true, false) . '> Cookie-Banner aktivieren</label>';
        $address = get_option(self::ADDRESS, 'sie');
        echo '<p><label for="bbmc_cmp_address">Anrede</label> <select id="bbmc_cmp_address" name="' . self::ADDRESS . '"><option value="sie"' . selected($address, 'sie', false) . '>Sie</option><option value="du"' . selected($address, 'du', false) . '>Du</option></select></p>';
        echo '<h3>Eigene Texte</h3><p>Leere Felder nutzen die Standardtexte in der gewählten Anrede. Erlaubt sind Links und Fettschrift. Platzhalter: <code>{datenschutz}</code> (Link zur Datenschutzerklärung), <code>{impressum}</code>, <code>{einstellungen}</code> (Link, der die Einstellungen öffnet), <code>{kategorien}</code> (z. B. „Statistik- und Marketingdienste“), <code>{dienste}</code> (erkannte Dienste der Kategorie).</p><table class="form-table" role="presentation">';
        $texts = (array) get_option(self::TEXTS, []);
        foreach (self::TEXT_FIELDS as $key => $label) {
            $id = 'bbmc_cmp_text_' . $key;
            $value = isset($texts[$key]) ? (string) $texts[$key] : '';
            $long = in_array($key, ['description', 'intro', 'necessary', 'analytics', 'marketing'], true);
            echo '<tr><th scope="row"><label for="' . $id . '">' . esc_html($label) . '</label></th><td>' . ($long
                ? '<textarea id="' . $id . '" name="' . self::TEXTS . '[' . $key . ']" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>'
                : '<input id="' . $id . '" name="' . self::TEXTS . '[' . $key . ']" type="text" class="regular-text" value="' . esc_attr($value) . '">') . '</td></tr>';
        }
        echo '</table>';
        submit_button('Speichern');
        echo '</form>';
        $seen = (array) get_option(self::SEEN, []);
        $labels = array_map(function ($id) { return self::SERVICES[$id][0] . ' (' . self::CATEGORIES[self::SERVICES[$id][1]] . ')'; }, array_intersect($seen, array_keys(self::SERVICES)));
        echo '<p><strong>Bisher erkannt:</strong> ' . ($labels ? esc_html(implode(', ', $labels)) : 'noch nichts (Seiten im Frontend aufrufen)') . '</p>';
        if ($seen) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="bbmc_cmp_reset">';
            wp_nonce_field('bbmc_cmp_reset');
            submit_button('Erkannte Dienste zurücksetzen', 'secondary', 'submit', false);
            echo ' <span class="description">Nach dem Entfernen eines Trackers. Danach Seiten-Cache leeren.</span></form>';
        }
    }
}
BBMC_Cmp::boot();
