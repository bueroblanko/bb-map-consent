<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Cookie banner for tracking (CookieConsent 3, bundled locally). Default off.
 * Known trackers are recognised in the finished HTML and turned into inactive
 * <script type="text/plain" data-category="…"> tags; CookieConsent runs them after consent.
 * External content (maps, videos, …) keeps its placeholders; services seen in them form the
 * category "external", so a choice in the banner and a click in a placeholder stay in sync.
 */
final class BBMC_Cmp {
    const OPTION = 'bbmc_cmp_enabled';
    const SEEN = 'bbmc_cmp_seen';
    const TEXTS = 'bbmc_cmp_texts';
    /** Links for sites without own legal pages (e.g. a group's privacy policy); empty = WordPress pages. */
    const LINKS = 'bbmc_cmp_links';
    const ADDRESS = 'bbmc_cmp_address';
    const TRACKING = 'bbmc_cmp_tracking';
    /**
     * Tracking IDs BB Consent writes itself (e.g. after Borlabs, which used to output them).
     * key => [label, pattern, placeholder, upper-case]. The code is added to the page before the
     * filter runs, so it is blocked like any other tracker and only ever written while the banner is active.
     */
    const TRACKING_FIELDS = [
        'ga4' => ['Google Analytics 4', '~^G-[A-Z0-9]{6,14}$~', 'G-XXXXXXXXXX', true],
        'gtm' => ['Google Tag Manager', '~^GTM-[A-Z0-9]{4,10}$~', 'GTM-XXXXXXX', true],
        'ads' => ['Google Ads', '~^AW-\d{6,12}$~', 'AW-123456789', true],
        'meta' => ['Meta-Pixel', '~^\d{10,20}$~', 'Pixel-ID (nur Ziffern)', false],
        'mouseflow' => ['Mouseflow', '~^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$~', 'Website-ID', false],
        'hotjar' => ['Hotjar', '~^\d{5,10}$~', 'Site-ID (nur Ziffern)', false],
        'clarity' => ['Microsoft Clarity', '~^[a-z0-9]{8,12}$~', 'Projekt-ID', false],
        'tiktok' => ['TikTok-Pixel', '~^(?=[A-Z]*\d)[A-Z0-9]{16,24}$~', 'Pixel-ID', true],
        'spotify' => ['Spotify Ads Pixel', '~^[a-f0-9]{32}$~', 'Pixel-Key (32 Zeichen)', false],
    ];
    /** Own texts per site (e.g. from eRecht24); empty fields keep the neutral defaults in cmp.js. */
    const TEXT_FIELDS = ['title' => 'Titel', 'description' => 'Banner-Text', 'accept_all' => 'Button „Alle akzeptieren“', 'accept_necessary' => 'Button „Nur notwendige“', 'preferences' => 'Button „Einstellungen“', 'save' => 'Button „Auswahl speichern“', 'intro' => 'Einleitung in den Einstellungen', 'necessary' => 'Beschreibung Notwendig', 'analytics' => 'Beschreibung Statistik', 'marketing' => 'Beschreibung Marketing', 'external' => 'Beschreibung Externe Medien', 'more' => 'Weitere Informationen'];
    const CATEGORIES = ['analytics' => 'Statistik', 'marketing' => 'Marketing', 'external' => 'Externe Medien'];
    /** Placeholder services of BB Consent (data-service of .bbmc) => label in the banner. Seen ids carry the prefix ext_. */
    const EXTERNAL = ['youtube' => 'YouTube', 'maps' => 'Google Maps', 'instagram' => 'Instagram', 'elfsight' => 'Elfsight', 'tramino' => 'Tramino', 'bookings' => 'Microsoft Bookings', 'planity' => 'Planity', 'dealerweb' => 'Aktionsbanner (COmarketing)', 'jobmorgen' => 'Jobmorgen', 'cleverpv' => 'clever-PV-Rechner', 'anlagenplaner' => 'Autarkierechner'];
    /** id => [label, category, script/link URL pattern, inline code pattern, noscript pattern]. Order matters: first match wins. */
    const SERVICES = [
        'google_tag_manager' => ['Google Tag Manager', 'marketing', '~googletagmanager\.com/gtm\.js~i', '~googletagmanager\.com/gtm\.js~i', '~googletagmanager\.com/ns\.html~i'],
        'google_ads' => ['Google Ads', 'marketing', '~googletagmanager\.com/gtag/js\?[^"\'\s>]*id=(?:AW|DC|GT)-|googleadservices\.com|googlesyndication\.com|doubleclick\.net~i', '~gtag\(\s*[\'"]config[\'"]\s*,\s*[\'"](?:AW|DC|GT)-|googleadservices\.com~i', '~googleadservices\.com|doubleclick\.net~i'],
        'google_analytics' => ['Google Analytics', 'analytics', '~googletagmanager\.com/gtag/js|google-analytics\.com|analytics\.google\.com~i', '~\bgtag\s*\(|google-analytics\.com|GoogleAnalyticsObject|__gtagTracker~', '~google-analytics\.com~i'],
        'meta_pixel' => ['Meta-Pixel', 'marketing', '~connect\.facebook\.net~i', '~\bfbq\s*\(|connect\.facebook\.net~', '~facebook\.com/tr~i'],
        'linkedin' => ['LinkedIn Insight Tag', 'marketing', '~snap\.licdn\.com~i', '~_linkedin_partner_id|snap\.licdn\.com~', '~px\.ads\.linkedin\.com~i'],
        'tiktok' => ['TikTok-Pixel', 'marketing', '~analytics\.tiktok\.com~i', '~analytics\.tiktok\.com|\bttq\.(?:load|page)\s*\(~', '~analytics\.tiktok\.com~i'],
        'matomo' => ['Matomo', 'analytics', '~/(?:matomo|piwik)\.js(?:[?#"\']|$)|/container_[a-z0-9]+\.js~i', '~\b_paq\b|\b_mtm\b~', '~/(?:matomo|piwik)\.php~i'],
        'spotify' => ['Spotify Ads Pixel', 'marketing', '~pixel\.byspotify\.com~i', '~pixel\.byspotify\.com|\bspdt\s*\(~', ''],
        'hotjar' => ['Hotjar', 'analytics', '~(?:static|script)\.hotjar\.com~i', '~_hjSettings|static\.hotjar\.com~', ''],
        'mouseflow' => ['Mouseflow', 'analytics', '~cdn\.mouseflow\.com~i', '~\b_mfq\b|cdn\.mouseflow\.com~', ''],
        'clarity' => ['Microsoft Clarity', 'analytics', '~clarity\.ms~i', '~clarity\.ms~', ''],
    ];
    /**
     * Provider and its privacy policy per service (tracker id or placeholder id), shown next to the
     * service in the settings window. Self-hosted Matomo and services without a known policy URL have none.
     */
    const PROVIDERS = [
        'google_tag_manager' => ['Google Ireland Limited, Irland', 'https://policies.google.com/privacy'],
        'google_ads' => ['Google Ireland Limited, Irland', 'https://policies.google.com/privacy'],
        'google_analytics' => ['Google Ireland Limited, Irland', 'https://policies.google.com/privacy'],
        'meta_pixel' => ['Meta Platforms Ireland Limited, Irland', 'https://www.facebook.com/privacy/policy/'],
        'linkedin' => ['LinkedIn Ireland Unlimited Company, Irland', 'https://www.linkedin.com/legal/privacy-policy'],
        'tiktok' => ['TikTok Technology Limited, Irland', 'https://www.tiktok.com/legal/page/eea/privacy-policy/de'],
        'spotify' => ['Spotify AB, Schweden', 'https://www.spotify.com/de/legal/privacy-policy/'],
        'hotjar' => ['Hotjar (Contentsquare)', 'https://www.hotjar.com/legal/policies/privacy/'],
        'mouseflow' => ['Mouseflow ApS, Dänemark', 'https://mouseflow.com/legal/privacy-policy/'],
        'clarity' => ['Microsoft Ireland Operations Limited, Irland', 'https://www.microsoft.com/de-de/privacy/privacystatement'],
        'ext_youtube' => ['Google Ireland Limited, Irland', 'https://policies.google.com/privacy'],
        'ext_maps' => ['Google Ireland Limited, Irland', 'https://policies.google.com/privacy'],
        'ext_instagram' => ['Meta Platforms Ireland Limited, Irland', 'https://privacycenter.instagram.com/policy'],
        'ext_bookings' => ['Microsoft Ireland Operations Limited, Irland', 'https://www.microsoft.com/de-de/privacy/privacystatement'],
        'ext_elfsight' => ['Elfsight', 'https://elfsight.com/privacy-policy/'],
        'ext_cleverpv' => ['clever-PV GmbH, Landsberg am Lech', 'https://clever-pv.com/de/datenschutzerklaerung/'],
        'ext_anlagenplaner' => ['Sunbeam Communications GmbH, Berlin, nutzt Google Maps', 'https://sunbeam-communications.com/impressum/'],
    ];
    /** Other consent plugins; the banner stays off while one is active so two never run in parallel. */
    const OTHER_CMPS = ['borlabs-cookie', 'complianz-gdpr', 'real-cookie-banner', 'cookie-law-info', 'cookiebot', 'cookie-notice', 'gdpr-cookie-compliance', 'uk-cookie-consent', 'cookie-script'];

    public static function boot() {
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('bbmc_settings_screen', [__CLASS__, 'screen']);
        add_action('admin_post_bbmc_cmp_reset', [__CLASS__, 'reset']);
        add_action('admin_post_bbmc_cmp_import', [__CLASS__, 'import']);
        add_shortcode('bb_consent_settings', [__CLASS__, 'shortcode']);
        add_action('init', function () { if (!shortcode_exists('borlabs-cookie')) { add_shortcode('borlabs-cookie', [__CLASS__, 'borlabs_shortcode']); } }, 99);
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
        // Matomo with disableCookies stores nothing on the device and runs without consent.
        $cookieless = self::matomo_cookieless($html);
        $html = preg_replace_callback('~<!--.*?-->|<script\b[^>]*>.*?</script\s*>|<noscript\b[^>]*>.*?</noscript\s*>|<link\b[^>]*>~is', function ($m) use (&$found, $cookieless) {
            $tag = $m[0];
            if (strpos($tag, '<!--') === 0) { return $tag; }
            if (stripos($tag, '<script') === 0) {
                $id = self::classify_script($tag);
                if ($id === '' || ($id === 'matomo' && $cookieless)) { return $tag; }
                $found[$id] = true;
                $open = substr($tag, 0, strpos($tag, '>') + 1);
                $inactive = preg_replace('~\stype\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27|[^\s>]+)~i', '', $open);
                $inactive = '<script type="text/plain" data-category="' . self::SERVICES[$id][1] . '" data-bbmc-service="' . $id . '"' . substr($inactive, 7);
                return $inactive . substr($tag, strlen($open));
            }
            if (stripos($tag, '<noscript') === 0) {
                // Tracking pixels in noscript would load without JavaScript and therefore without consent.
                foreach (self::SERVICES as $id => $service) {
                    if ($service[4] !== '' && preg_match($service[4], $tag)) {
                        if ($id === 'matomo' && $cookieless) { return $tag; }
                        $found[$id] = true;
                        return '';
                    }
                }
                return $tag;
            }
            if (preg_match('/preconnect|dns-prefetch|preload|prefetch/i', BB_Map_Consent::attribute($tag, 'rel'))) {
                $href = BB_Map_Consent::attribute($tag, 'href');
                foreach (self::SERVICES as $id => $service) {
                    if ($href !== '' && preg_match($service[2], $href)) { return $id === 'matomo' && $cookieless ? $tag : ''; }
                }
                if (preg_match('~^(?:https?:)?//(?:www\.)?(?:googletagmanager\.com|google-analytics\.com|connect\.facebook\.net|snap\.licdn\.com|static\.hotjar\.com|cdn\.mouseflow\.com|www\.clarity\.ms)/?$~i', $href)) { return ''; }
            }
            return $tag;
        }, $html);
        // Placeholders written by BB_Map_Consent (its buffer runs inside this one).
        if (preg_match_all('~<section class="bbmc[^"]*" data-service="([a-z]+)"~', $html, $m)) {
            foreach ($m[1] as $service) { if (isset(self::EXTERNAL[$service])) { $found['ext_' . $service] = true; } }
        }
        return [$html, array_keys($found)];
    }
    /** A Matomo tracking code (inline _paq) on the page that calls disableCookies. */
    public static function matomo_cookieless($html) {
        if (stripos($html, 'disableCookies') === false || !preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $m)) { return false; }
        foreach ($m[1] as $code) {
            if (strpos($code, '_paq') !== false && preg_match('~_paq\.push\(\s*\[\s*[\'"]disableCookies[\'"]\s*\]\s*\)~', $code)) { return true; }
        }
        return false;
    }
    /** Services seen anywhere on the site so far; the banner always lists all of them. $drop removes ids that no longer need consent. */
    public static function remember(array $found, array $drop = []) {
        $known = array_merge(array_keys(self::SERVICES), array_map(function ($id) { return 'ext_' . $id; }, array_keys(self::EXTERNAL)));
        $seen = array_values(array_intersect($known, array_diff(array_unique(array_merge((array) get_option(self::SEEN, []), $found)), $drop)));
        if ($seen !== array_values((array) get_option(self::SEEN, []))) { update_option(self::SEEN, $seen, true); }
        return $seen;
    }
    public static function config(array $seen, $privacy, array $texts = [], $address = 'sie', $imprint = '') {
        $categories = [];
        $external = [];
        $providers = [];
        foreach ($seen as $id) {
            $label = strpos($id, 'ext_') === 0 ? self::EXTERNAL[substr($id, 4)] : self::SERVICES[$id][0];
            if (isset(self::PROVIDERS[$id])) { $providers[$label] = self::PROVIDERS[$id]; }
            if (strpos($id, 'ext_') === 0) { $external[substr($id, 4)] = $label; continue; }
            $categories[self::SERVICES[$id][1]][] = $label;
        }
        if ($external) { $categories['external'] = array_values($external); }
        // A newly seen service changes the revision, so earlier decisions are asked for again.
        return ['privacy' => $privacy, 'imprint' => $imprint, 'categories' => $categories, 'texts' => (object) self::clean_texts($texts), 'address' => $address === 'du' ? 'du' : 'sie', 'external' => (object) $external, 'providers' => (object) $providers, 'revision' => $seen ? (int) sprintf('%u', crc32(implode(',', $seen))) % 1000000 + 1 : 0];
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
        $html = self::add_tracking($html, (array) get_option(self::TRACKING, []));
        $cookieless = self::matomo_cookieless($html);
        [$html, $found] = self::filter($html);
        $seen = self::remember($found, $cookieless ? ['matomo'] : []);
        if (!$seen) { return self::hide_settings_links($html); }
        $config = self::config($seen, self::privacy_url(), (array) get_option(self::TEXTS, []), get_option(self::ADDRESS, 'sie'), self::imprint_url());
        if (class_exists('BBMC_Look')) { $config['look'] = BBMC_Look::client(); }
        return self::inject($html, $config, plugins_url('', __FILE__) . '/', BBMC_VERSION);
    }
    /** Valid IDs only, normalised; $invalid collects labels of entries that were dropped. */
    public static function clean_tracking(array $ids, array &$invalid = []) {
        $clean = [];
        foreach (self::TRACKING_FIELDS as $key => [$label, $pattern, , $upper]) {
            $value = isset($ids[$key]) && is_scalar($ids[$key]) ? preg_replace('~\s+~', '', (string) $ids[$key]) : '';
            if ($value === '') { continue; }
            $value = $upper ? strtoupper($value) : strtolower($value);
            if (preg_match($pattern, $value)) { $clean[$key] = $value; } else { $invalid[] = $label; }
        }
        return $clean;
    }
    /** Standard code for one tracking ID, in the form the service publishes it. */
    public static function snippet($key, $id) {
        $js = function ($v) { return wp_json_encode((string) $v); };
        $gtag = "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());";
        switch ($key) {
            case 'ga4':
            case 'ads':
                return '<script async src="https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($id) . '"></script><script>' . $gtag . 'gtag(\'config\',' . $js($id) . ');</script>';
            case 'gtm':
                return "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer'," . $js($id) . ');</script>';
            case 'meta':
                return "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init'," . $js($id) . ");fbq('track','PageView');</script>";
            case 'mouseflow':
                return '<script>window._mfq=window._mfq||[];(function(){var mf=document.createElement("script");mf.type="text/javascript";mf.defer=true;mf.src="//cdn.mouseflow.com/projects/"+' . $js($id) . '+".js";document.getElementsByTagName("head")[0].appendChild(mf);})();</script>';
            case 'hotjar':
                return "<script>(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:" . (int) $id . ",hjsv:6};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');</script>";
            case 'clarity':
                return '<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script",' . $js($id) . ');</script>';
            case 'tiktok':
                return '<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=d.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=d.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};ttq.load(' . $js($id) . ');ttq.page();}(window,document,"ttq");</script>';
            case 'spotify':
                return "<script>(function(w,d){var id='spdt-capture',n='script';if(!d.getElementById(id)){w.spdt=w.spdt||function(){(w.spdt.q=w.spdt.q||[]).push(arguments);};var e=d.createElement(n);e.id=id;e.async=1;e.src='https://pixel.byspotify.com/ping.min.js';var s=d.getElementsByTagName(n)[0];s.parentNode.insertBefore(e,s);}w.spdt('conf',{key:" . $js($id) . "});w.spdt('view');})(window,document);</script>";
        }
        return '';
    }
    /** Adds the configured codes to <head>; an ID already on the page (theme, plugin) is not added twice. */
    public static function add_tracking($html, array $ids) {
        $code = '';
        foreach (self::clean_tracking($ids) as $key => $id) {
            if (stripos($html, $id) === false) { $code .= self::snippet($key, $id); }
        }
        if ($code === '') { return $html; }
        $head = stripos($html, '</head>');
        return $head === false ? $code . $html : substr_replace($html, $code, $head, 0);
    }
    /** Tracking IDs in a text (Borlabs settings or its public config). Meta, Mouseflow, Hotjar, TikTok and Spotify only next to their names. */
    public static function find_ids($text) {
        $text = (string) $text;
        // Borlabs 3 keeps each service's code base64-encoded in its public config (optInCode …); some IDs only appear there.
        if (preg_match_all('~"(?:optInCode|optOutCode|fallbackCode)"\s*:\s*"([A-Za-z0-9+/=\\\\]{16,})"~', $text, $codes)) {
            foreach ($codes[1] as $code) {
                $decoded = base64_decode(str_replace('\\/', '/', $code), true);
                if (is_string($decoded) && $decoded !== '') { $text .= "\n" . $decoded; }
            }
        }
        // Several candidates per service: Borlabs templates carry example values (G-123456789, AW-123456789) next to real ones.
        $patterns = [
            'ga4' => ['~\b(G-(?=[A-Z]*\d)[A-Z0-9]{8,12})\b~'],
            'gtm' => ['~\b(GTM-[A-Z0-9]{5,9})\b~'],
            'ads' => ['~\b(AW-\d{8,12})\b~'],
            // Borlabs 3 stores the pixel as "pixel-id" (JSON in its config, serialised in the database).
            'meta' => ['~pixel[-_]?id(?:\W{0,4}s:\d+:)?\W{0,12}(\d{15,16})\b~i', '~(?:facebook|meta[-_ ]?pixel|fbq)[^\d]{0,400}?\b(\d{15,16})\b~is'],
            'mouseflow' => ['~mouseflow[^\n]{0,400}?\b([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})\b~is'],
            'hotjar' => ['~(?:hotjar|hjid)[^\d]{0,200}?\b(\d{6,9})\b~is'],
            'tiktok' => ['~(?:tiktok|ttq)[^\n]{0,400}?\b((?=[A-Z]*\d)[A-Z0-9]{20})\b~s'],
            'spotify' => ['~spdt\(\s*[\'"]conf[\'"]\s*,\s*\{\s*key\s*:\s*[\'"]([a-f0-9]{32})[\'"]~i', '~(?:spotify|spdt)[^\n]{0,400}?\b([a-f0-9]{32})\b~is'],
        ];
        $found = [];
        foreach ($patterns as $key => $list) {
            foreach ($list as $pattern) {
                if (!preg_match_all($pattern, $text, $m)) { continue; }
                foreach ($m[1] as $id) {
                    if (!self::example_id($id)) { $found[$key] = in_array($key, ['mouseflow', 'spotify'], true) ? strtolower($id) : $id; break 2; }
                }
            }
        }
        return self::clean_tracking($found);
    }
    /** Example values from templates and documentation: counting digits (123456789), repeated characters (X…, 0…). */
    public static function example_id($id) {
        $core = strtoupper(preg_replace('~^(?:G|GTM|AW)-~i', '', (string) $id));
        $plain = str_replace('-', '', $core);
        if (preg_match('~^(.)\1+$~', $plain) || preg_match('~^X+$~', $plain) || strpos($plain, 'XXXX') !== false) { return true; }
        foreach (['1234567', '9876543', 'ABCDEF1234', '0000000'] as $run) { if (strpos($plain, $run) !== false) { return true; } }
        return false;
    }
    /** IDs stored by Borlabs Cookie (its database tables and the config files it publishes). */
    public static function borlabs_ids() {
        global $wpdb;
        $text = '';
        if (isset($wpdb) && is_object($wpdb)) {
            $tables = (array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'borlabs_cookie') . '%'));
            foreach ($tables as $table) {
                if (!preg_match('~(?:service|cookie|content_blocker|script_blocker|provider|package)~', substr($table, strlen($wpdb->prefix . 'borlabs_cookie')))) { continue; }
                foreach ((array) $wpdb->get_results('SELECT * FROM `' . esc_sql($table) . '` LIMIT 500', ARRAY_A) as $row) { $text .= implode("\n", array_map('strval', array_filter((array) $row, 'is_scalar'))) . "\n"; }
            }
        }
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : [];
        if (!empty($uploads['basedir'])) {
            // Borlabs 3 writes its public config to wp-content/cache/borlabs-cookie/<blog>/, older versions to uploads.
            $content = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname($uploads['basedir']);
            foreach (array_merge((array) glob($uploads['basedir'] . '/borlabs-cookie/*.js'), (array) glob($uploads['basedir'] . '/borlabs-cookie/*/*.js'), (array) glob($content . '/cache/borlabs-cookie/*.js'), (array) glob($content . '/cache/borlabs-cookie/*/*.js')) as $file) {
                if (is_file($file) && filesize($file) < 2000000) { $text .= file_get_contents($file) . "\n"; }
            }
        }
        return self::find_ids($text);
    }
    /** Takes IDs found in Borlabs into empty fields; existing entries stay. */
    public static function import() {
        if (!current_user_can('manage_options') || !check_admin_referer('bbmc_cmp_import')) { wp_die('Keine Berechtigung.'); }
        $current = self::clean_tracking((array) get_option(self::TRACKING, []));
        $found = self::borlabs_ids();
        $new = array_diff_key($found, $current);
        update_option(self::TRACKING, $current + $found);
        wp_safe_redirect(admin_url('options-general.php?page=bb-consent&tab=banner&bbmc_import=' . count($new)));
        exit;
    }
    /** Without a service that needs consent there is no banner, so links to its settings would do nothing: hide them. */
    public static function hide_settings_links($html) {
        if (strpos($html, 'bb-consent-settings') === false) { return $html; }
        $css = '<style id="bbmc-cmp-nolinks">.bbmc-cmp-settings,a[href$="#bb-consent-settings"],li:has(>a[href$="#bb-consent-settings"]){display:none!important}</style>';
        $head = stripos($html, '</head>');
        return $head === false ? $css . $html : substr_replace($html, $css, $head, 0);
    }
    /** An own https link (privacy or imprint) from the settings, else ''. */
    public static function link($key) {
        $links = function_exists('get_option') ? (array) get_option(self::LINKS, []) : [];
        $url = isset($links[$key]) ? (string) $links[$key] : '';
        return preg_match('~^https://[^\s"<>]+$~i', $url) ? $url : '';
    }
    /** Privacy policy for banner and placeholders: own link, WordPress privacy page, else /datenschutz. */
    public static function privacy_url() {
        return self::link('privacy') ?: (get_privacy_policy_url() ?: home_url('/datenschutz'));
    }
    /** WordPress has no imprint setting; a published page with the slug impressum or imprint, else /impressum. */
    public static function imprint_url() {
        $own = self::link('imprint');
        if ($own) { return $own; }
        foreach (['impressum', 'imprint'] as $slug) {
            $page = get_page_by_path($slug);
            if ($page && $page->post_status === 'publish') { return get_permalink($page); }
        }
        return home_url('/impressum');
    }
    /** [bb_consent_settings] or [bb_consent_settings label="…"]; default label „Cookies“. */
    public static function shortcode($atts = []) {
        $label = is_array($atts) && isset($atts['label']) && trim((string) $atts['label']) !== '' ? (string) $atts['label'] : 'Cookies';
        return '<a href="#bb-consent-settings" class="bbmc-cmp-settings">' . esc_html($label) . '</a>';
    }
    /** Leftover Borlabs shortcodes after Borlabs is gone: the preferences button becomes our settings link, wrapped content stays (BB Consent blocks embeds itself), everything else disappears instead of showing as raw text. */
    public static function borlabs_shortcode($atts = [], $content = null) {
        $atts = is_array($atts) ? $atts : [];
        $type = isset($atts['type']) ? strtolower((string) $atts['type']) : '';
        if (strpos($type, 'btn-') === 0 || strpos($type, 'preference') !== false) {
            return self::shortcode(['label' => isset($atts['title']) && trim(wp_strip_all_tags((string) $atts['title'])) !== '' ? wp_strip_all_tags((string) $atts['title']) : 'Cookies']);
        }
        return $content !== null && $content !== '' ? do_shortcode($content) : '';
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
        register_setting('bbmc_cmp', self::LINKS, ['type' => 'array', 'default' => [], 'sanitize_callback' => function ($v) {
            $clean = [];
            foreach (['privacy', 'imprint'] as $key) {
                $url = is_array($v) && isset($v[$key]) ? trim((string) $v[$key]) : '';
                if ($url === '') { continue; }
                if (preg_match('~^https://[^\s"<>]+$~i', $url)) { $clean[$key] = esc_url_raw($url); } else { add_settings_error(self::LINKS, 'bbmc_links', 'Eigene Links müssen mit https:// beginnen.'); }
            }
            return $clean;
        }]);
        register_setting('bbmc_cmp', self::TEXTS, ['type' => 'array', 'default' => [], 'sanitize_callback' => function ($v) { return self::clean_texts(is_array($v) ? wp_unslash($v) : []); }]);
        register_setting('bbmc_cmp', self::TRACKING, ['type' => 'array', 'default' => [], 'sanitize_callback' => function ($v) {
            $invalid = [];
            $clean = self::clean_tracking(is_array($v) ? wp_unslash($v) : [], $invalid);
            if ($invalid) { add_settings_error(self::TRACKING, 'bbmc_tracking', 'Nicht gespeichert, Format passt nicht: ' . implode(', ', $invalid) . '.', 'error'); }
            return $clean;
        }]);
        add_filter('option_page_capability_bbmc_cmp', function () { return 'manage_options'; });
    }
    public static function reset() {
        if (!current_user_can('manage_options') || !check_admin_referer('bbmc_cmp_reset')) { wp_die('Keine Berechtigung.'); }
        delete_option(self::SEEN);
        wp_safe_redirect(admin_url('options-general.php?page=bb-consent&bbmc_reset=1'));
        exit;
    }
    /** Settings page sections: [title, hint, [[key, width of 6]]]. */
    const TEXT_SECTIONS = [
        ['Banner', 'Erscheint beim ersten Besuch.', [['title', 6], ['description', 6]]],
        ['Buttons', '', [['accept_all', 3], ['accept_necessary', 3], ['preferences', 3], ['save', 3]]],
        ['Einstellungen-Fenster', 'Öffnet sich über „Einstellungen“ oder den Link im Footer.', [['intro', 6], ['necessary', 6], ['analytics', 3], ['marketing', 3], ['external', 6], ['more', 6]]],
    ];
    public static function screen() {
        if (!current_user_can('manage_options')) { return; }
        if (isset($_GET['bbmc_reset'])) { echo '<div class="notice notice-success is-dismissible"><p>Erkannte Dienste zurückgesetzt. Sie werden beim nächsten Seitenaufruf neu erkannt.</p></div>'; }
        $other = self::other_cmp();
        if ($other) { echo '<div class="notice notice-warning inline"><p>Ein anderes Cookie-Plugin ist aktiv (' . esc_html($other) . '). Das Banner bleibt aus, bis es deaktiviert ist.</p></div>'; }
        if (isset($_GET['bbmc_import'])) { echo '<div class="notice notice-success is-dismissible"><p>' . ((int) $_GET['bbmc_import'] > 0 ? (int) $_GET['bbmc_import'] . ' Tracking-ID(s) aus Borlabs übernommen. Bitte unten prüfen.' : 'Keine neuen Tracking-IDs in Borlabs gefunden.') . '</p></div>'; }
        $tracking = self::clean_tracking((array) get_option(self::TRACKING, []));
        $borlabs = array_diff_key(self::borlabs_ids(), $tracking);
        if ($borlabs) {
            $list = [];
            foreach ($borlabs as $key => $id) { $list[] = self::TRACKING_FIELDS[$key][0] . ' ' . $id; }
            echo '<div class="notice notice-info inline"><p><strong>In Borlabs gefunden:</strong> ' . esc_html(implode(', ', $list)) . '. Borlabs gibt diese Codes selbst aus. Ohne Übernahme fehlen sie, sobald Borlabs entfernt ist.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 8px"><input type="hidden" name="action" value="bbmc_cmp_import">';
            wp_nonce_field('bbmc_cmp_import');
            submit_button('In BB Consent übernehmen', 'primary', 'submit', false);
            echo '</form></div>';
        }
        echo '<form method="post" action="options.php">';
        settings_fields('bbmc_cmp');
        $address = get_option(self::ADDRESS, 'sie');
        echo '<section class="bb-card"><header><h2>Cookie-Banner für Tracking</h2><p>Erkennt Google Analytics, Tag Manager, Google Ads, Meta-Pixel, LinkedIn, TikTok, Spotify, Matomo, Hotjar, Mouseflow und Clarity und lädt sie erst nach Zustimmung. Matomo mit <code>disableCookies</code> setzt keine Cookies und läuft ohne Zustimmung. Karten und Videos behalten ihre Platzhalter und erscheinen im Banner als „Externe Medien“. Die Auswahl im Banner und die Freigabe im Platzhalter sind verbunden.</p></header><div class="bb-card-body"><div class="bb-grid">';
        echo '<div class="bb-f w4"><label class="bb-switch"><input type="checkbox" name="' . self::OPTION . '" value="1"' . checked((bool) get_option(self::OPTION, false), true, false) . '><span><strong>Banner aktivieren</strong><small>Im Footer <code>[bb_consent_settings]</code> oder einen Menülink auf <code>#bb-consent-settings</code> einsetzen, damit Besucher ihre Auswahl ändern können.</small></span></label></div>';
        echo '<div class="bb-f w2"><span class="bb-label">Anrede</span><div class="bb-seg" role="radiogroup" aria-label="Anrede">';
        foreach (['sie' => 'Sie', 'du' => 'Du'] as $key => $label) { echo '<label><input type="radio" name="' . self::ADDRESS . '" value="' . $key . '"' . checked($address, $key, false) . '><span>' . $label . '</span></label>'; }
        echo '</div></div>';
        $links = (array) get_option(self::LINKS, []);
        foreach (['privacy' => ['Eigener Link Datenschutzerklärung', 'leer = Datenschutzseite dieser Website'], 'imprint' => ['Eigener Link Impressum', 'leer = Seite /impressum dieser Website']] as $key => [$label, $hint]) {
            $id = 'bbmc_cmp_link_' . $key;
            echo '<div class="bb-f w2"><label for="' . $id . '">' . esc_html($label) . '</label><input id="' . $id . '" name="' . self::LINKS . '[' . $key . ']" type="url" value="' . esc_attr(isset($links[$key]) ? $links[$key] : '') . '" placeholder="' . esc_attr($hint) . '" spellcheck="false" autocomplete="off"></div>';
        }
        echo '</div></div><div class="bb-card-foot">';
        submit_button('Speichern', 'primary', 'submit', false);
        echo '</div></section>';
        echo '<section class="bb-card"><header><h2>Tracking-Codes</h2><p>Nur für Codes, die sonst nirgends eingebaut sind, zum Beispiel weil Borlabs sie bisher ausgegeben hat. BB Consent schreibt den Standard-Code und lädt ihn erst nach Zustimmung. Ist das Banner aus, wird nichts ausgegeben. Steht dieselbe ID schon im Theme oder einem Plugin, wird sie nicht doppelt eingebaut.</p></header><div class="bb-card-body"><div class="bb-grid">';
        foreach (self::TRACKING_FIELDS as $key => [$label, , $placeholder]) {
            $id = 'bbmc_cmp_tracking_' . $key;
            echo '<div class="bb-f w2"><label for="' . $id . '">' . esc_html($label) . '</label><input id="' . $id . '" name="' . self::TRACKING . '[' . $key . ']" type="text" value="' . esc_attr(isset($tracking[$key]) ? $tracking[$key] : '') . '" placeholder="' . esc_attr($placeholder) . '" spellcheck="false" autocomplete="off"></div>';
        }
        echo '</div></div><div class="bb-card-foot">';
        submit_button('Tracking speichern', 'primary', 'submit', false);
        echo '</div></section>';
        $texts = (array) get_option(self::TEXTS, []);
        echo '<details class="bb-card"' . (array_filter($texts) ? ' open' : '') . '><summary class="bb-card-body" style="cursor:pointer"><strong>Eigene Texte</strong> <span class="bb-small">' . (array_filter($texts) ? count(array_filter($texts)) . ' angepasst' : 'leer = Standardtexte in der gewählten Anrede') . '</span></summary><div class="bb-card-body" style="padding-top:0">';
        echo '<p class="bb-small" style="margin-top:0">Erlaubt sind Links und Fettschrift. Platzhalter:</p><p class="bb-chips"><code>{datenschutz}</code><code>{impressum}</code><code>{einstellungen}</code><code>{kategorien}</code><code>{dienste}</code></p>';
        foreach (self::TEXT_SECTIONS as [$title, $hint, $fields]) {
            echo '<div class="bb-section"><h3>' . esc_html($title) . '</h3>' . ($hint !== '' ? '<p class="bb-hint">' . esc_html($hint) . '</p>' : '') . '<div class="bb-grid">';
            foreach ($fields as [$key, $width]) {
                $id = 'bbmc_cmp_text_' . $key;
                $value = isset($texts[$key]) ? (string) $texts[$key] : '';
                $long = in_array($key, ['description', 'intro', 'necessary', 'analytics', 'marketing', 'external', 'more'], true);
                echo '<div class="bb-f w' . $width . '"><label for="' . $id . '">' . esc_html(self::TEXT_FIELDS[$key]) . '</label>' . ($long
                    ? '<textarea id="' . $id . '" name="' . self::TEXTS . '[' . $key . ']" rows="' . ($key === 'description' || $key === 'intro' ? 4 : 2) . '" placeholder="Standardtext">' . esc_textarea($value) . '</textarea>'
                    : '<input id="' . $id . '" name="' . self::TEXTS . '[' . $key . ']" type="text" value="' . esc_attr($value) . '" placeholder="Standardtext">') . '</div>';
            }
            echo '</div></div>';
        }
        echo '</div><div class="bb-card-foot">';
        submit_button('Texte speichern', 'primary', 'submit', false);
        echo '</div></details></form>';
        $seen = (array) get_option(self::SEEN, []);
        $labels = array_map(function ($id) { return self::SERVICES[$id][0] . ' · ' . self::CATEGORIES[self::SERVICES[$id][1]]; }, array_intersect($seen, array_keys(self::SERVICES)));
        foreach ($seen as $id) { if (strpos($id, 'ext_') === 0 && isset(self::EXTERNAL[substr($id, 4)])) { $labels[] = self::EXTERNAL[substr($id, 4)] . ' · ' . self::CATEGORIES['external']; } }
        echo '<section class="bb-card"><header><h2>Erkannte Dienste</h2><p>Das Banner nennt alle Dienste, die bisher auf einer Seite gefunden wurden.</p></header><div class="bb-card-body">' . ($labels ? '<p class="bb-chips"><span>' . implode('</span><span>', array_map('esc_html', $labels)) . '</span></p>' : '<p class="bb-empty">Noch nichts erkannt. Seiten im Frontend aufrufen.</p>') . '</div>';
        if ($seen) {
            echo '<div class="bb-card-foot"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="bbmc_cmp_reset">';
            wp_nonce_field('bbmc_cmp_reset');
            submit_button('Erkannte Dienste zurücksetzen', 'secondary', 'submit', false);
            echo '</form><span class="bb-small">Nach dem Entfernen eines Trackers. Danach Seiten-Cache leeren.</span></div>';
        }
        echo '</section>';
    }
}
BBMC_Cmp::boot();
