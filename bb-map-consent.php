<?php
/**
 * Plugin Name: BB Consent
 * Description: Lokale Einwilligung für Google Maps, Snazzy Maps, klassische Divi-Karten, Instagram, DealerWeb-Aktionsbanner, Planity-Terminbuchung, Elfsight, YouTube, Tramino, Microsoft Bookings und die Jobmorgen-Stellensuche.
 * Version: 0.10.21
 * Update URI: https://github.com/bueroblanko/bb-map-consent
 * Plugin URI: https://github.com/bueroblanko/bb-map-consent
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Büro Blanko
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
define('BBMC_VERSION', '0.10.21');
final class BB_Map_Consent {
    public static function boot() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('do_shortcode_tag', [__CLASS__, 'divi'], 10, 4);
        add_action('template_redirect', function () {
            if (!is_feed() && !is_admin() && !wp_doing_ajax()) { ob_start([__CLASS__, 'filter_html']); }
        }, 0);
        // Content loaded later by the front end (e.g. Divi FilterGrid "load more") arrives via admin-ajax.
        add_action('admin_init', function () {
            if (wp_doing_ajax() && self::frontend_ajax()) { ob_start([__CLASS__, 'filter_ajax']); }
        }, 0);
        foreach (['youtube' => 'YouTube', 'tramino' => 'Tramino', 'bookings' => 'Microsoft Bookings', 'cleverpv' => 'clever-PV-Rechner', 'anlagenplaner' => 'Autarkierechner'] as $service => $label) {
            add_shortcode('bb_' . $service . '_revoke', function () use ($service, $label) { return '<button type="button" class="bbmc-revoke" data-service="' . esc_attr($service) . '">' . $label . '-Einwilligung widerrufen</button>'; });
        }
        add_shortcode('bb_elfsight_revoke', function () { return '<button type="button" class="bbmc-revoke" data-service="elfsight">Elfsight-Einwilligung widerrufen</button>'; });
        add_shortcode('bb_instagram_revoke', function () { return '<button type="button" class="bbmc-revoke" data-service="instagram">Instagram-Einwilligung widerrufen</button>'; });
        add_shortcode('bb_maps_revoke', function () { return '<button type="button" class="bbmc-revoke">Google-Maps-Einwilligung widerrufen</button>'; });
        add_shortcode('bb_planity_revoke', function () { return '<button type="button" class="bbmc-revoke" data-service="planity">Einwilligung für die Online-Terminbuchung widerrufen</button>'; });
        add_shortcode('bb_dealerweb_revoke', function () { return '<button type="button" class="bbmc-revoke" data-service="dealerweb">Aktionsbanner-Einwilligung widerrufen</button>'; });
    }
    public static function assets() {
        wp_enqueue_style('bb-map-consent', plugins_url('assets/maps.css', __FILE__), [], '0.10.21');
        wp_enqueue_script('bb-map-consent', plugins_url('assets/maps.js', __FILE__), [], '0.10.21', true);
    }
    public static function host($url) {
        return strtolower((string) wp_parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_HOST));
    }
    public static function google($url) {
        $host = self::host(strpos($url, '//') === 0 ? 'https:' . $url : $url);
        return (bool) preg_match('/(^|\.)(google\.[a-z.]+|googleapis\.com|gstatic\.com|googleusercontent\.com)$/i', $host);
    }
    public static function map_url($url) {
        return self::google($url) && (bool) preg_match('~(/maps(?:[/?]|$)|[?&]output=embed)~i', html_entity_decode($url));
    }
    public static function iframe_url($url) {
        $normal = strpos($url, '//') === 0 ? 'https:' . $url : $url;
        return self::map_url($url) ||
            (in_array(self::host($normal), ['snazzymaps.com', 'www.snazzymaps.com'], true)
            && preg_match('~^/embed/[0-9]+/?$~', (string) wp_parse_url($normal, PHP_URL_PATH)));
    }
    public static function instagram_host($url) {
        $normal = strpos($url, '//') === 0 ? 'https:' . $url : $url;
        return in_array(self::host($normal), ['instagram.com', 'www.instagram.com'], true);
    }
    public static function instagram_embed($url) {
        $normal = strpos($url, '//') === 0 ? 'https:' . $url : $url;
        if (!self::instagram_host($normal) || !in_array(strtolower((string) wp_parse_url($normal, PHP_URL_SCHEME)), ['https', 'http'], true)) { return ''; }
        if (preg_match('~^/(p|reel|tv)/([A-Za-z0-9_-]+)(?:/(?:embed(?:/captioned)?)?)?/?$~', (string) wp_parse_url($normal, PHP_URL_PATH), $m)) {
            return 'https://www.instagram.com/' . $m[1] . '/' . $m[2] . '/embed/';
        }
        return '';
    }
    /** DealerWeb co-marketing banners (COmarketing GmbH), normalised to HTTPS. */
    public static function dealerweb_url($url) {
        $normal = strpos($url, '//') === 0 ? 'https:' . $url : $url;
        if (!in_array(self::host($normal), ['dealerweb-comarketing.de', 'www.dealerweb-comarketing.de'], true)
            || !in_array(strtolower((string) wp_parse_url($normal, PHP_URL_SCHEME)), ['https', 'http'], true)) { return ''; }
        return preg_replace('~^http:~i', 'https:', html_entity_decode($normal, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    /** Planity booking widget: its CloudFront loader scripts or the inline window.planity configuration. */
    public static function planity_script($tag) {
        $src = self::attribute($tag, 'src');
        if ($src === '') { return (bool) preg_match('~\bwindow\.planity\s*=~', $tag); }
        $normal = strpos($src, '//') === 0 ? 'https:' . $src : $src;
        return self::host($normal) === 'd2skjte8udjqxw.cloudfront.net' && strpos((string) wp_parse_url($normal, PHP_URL_PATH), '/widget/') === 0;
    }
    /** Jobmorgen partner box (MANNHEIMER MORGEN GmbH): its inline loader or partnerbox.js itself. */
    public static function jobmorgen_script($tag) {
        $src = self::attribute($tag, 'src');
        if ($src === '') { return (bool) preg_match('~jobmorgen\.de/partnerbox\.js|RegioPowerPartnerBox\s*\(~', $tag); }
        $normal = strpos($src, '//') === 0 ? 'https:' . $src : $src;
        return in_array(self::host($normal), ['jobmorgen.de', 'www.jobmorgen.de'], true);
    }
    /** Autarkierechner / Anlagenplaner of Sunbeam Communications: its loader from pv-system-tech.com. */
    public static function anlagenplaner_script($tag) {
        $src = self::attribute($tag, 'src');
        if ($src === '') { return (bool) preg_match('~pv-system-tech\.com/[^"\'\s]*sbloader~i', $tag); }
        return self::anlagenplaner_host($src);
    }
    public static function anlagenplaner_host($url) {
        return (bool) preg_match('/(?:^|\.)pv-system-tech\.com$/', self::host(strpos($url, '//') === 0 ? 'https:' . $url : $url));
    }
    /** YouTube embeds load from youtube-nocookie.com (privacy-enhanced mode), as WordPress oEmbed uses youtube.com. */
    public static function youtube_nocookie($src) {
        return preg_replace('~^((?:https?:)?//)(?:www\.)?youtube\.com/embed/~i', '$1www.youtube-nocookie.com/embed/', $src);
    }
    public static function extra_iframe_service($url) {
        $normal = strpos($url, '//') === 0 ? 'https:' . $url : $url;
        if (!in_array(strtolower((string) wp_parse_url($normal, PHP_URL_SCHEME)), ['https', 'http'], true)) { return ''; }
        $host = self::host($normal);
        $path = (string) wp_parse_url($normal, PHP_URL_PATH);
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'], true)
            && preg_match('~^/embed/[A-Za-z0-9_-]+(?:/|$)~', $path)) { return 'youtube'; }
        if (preg_match('/(?:^|\.)tramino\.de$/', $host)) { return 'tramino'; }
        if ($host === 'integrations.clever-pv.com') { return 'cleverpv'; }
        if (in_array($host, ['outlook.office365.com', 'outlook.office.com'], true)
            && preg_match('~^/(?:owa/calendar/[^/]+/bookings(?:/|$)|book/[^/]+(?:/|$))~i', $path)) { return 'bookings'; }
        return '';
    }
    public static function box($config) {
        $service = $config['service'] ?? 'maps';
        $texts = [
            'maps' => ['Google Maps', 'Mit dem Laden stimmen Sie zu, dass Google Maps eingebunden wird (bei einer Snazzy-Maps-Karte auch über Snazzy Maps). Dabei wird Ihre IP-Adresse an Google übermittelt; Daten können auch in den USA verarbeitet werden.', 'Beim Laden werden Daten an Google übermittelt.'],
            'youtube' => ['YouTube', 'Mit dem Laden stimmen Sie zu, dass ein Video von YouTube (Google) eingebunden wird. Dabei wird Ihre IP-Adresse an Google übermittelt; Daten können auch in den USA verarbeitet werden.', 'Beim Laden werden Daten an YouTube / Google übermittelt.'],
            'bookings' => ['Microsoft Bookings', 'Mit dem Laden stimmen Sie zu, dass das Terminbuchungssystem Microsoft Bookings eingebunden wird. Dabei wird Ihre IP-Adresse an Microsoft übermittelt. Bei einer Buchung werden auch die von Ihnen eingegebenen Daten verarbeitet; Daten können außerhalb der EU verarbeitet werden.', 'Beim Laden werden Daten an Microsoft übermittelt.'],
            'tramino' => ['Tramino-Inhalte', 'Mit dem Laden stimmen Sie zu, dass externe Inhalte wie Bewertungen oder Buchungswidgets von Tramino eingebunden werden. Dabei wird Ihre IP-Adresse an den Anbieter übermittelt.', 'Beim Laden werden Daten an Tramino übermittelt.'],
            'elfsight' => ['Elfsight-Inhalte (z. B. Twitter / X)', 'Mit dem Laden stimmen Sie zu, dass externe Inhalte über Elfsight eingebunden werden. Dabei wird Ihre IP-Adresse an Elfsight und die Anbieter der Inhalte, bei einem Twitter-/X-Feed an X, übermittelt; Daten können auch außerhalb der EU verarbeitet werden.', 'Beim Laden werden Daten an Elfsight und den Inhaltsanbieter übermittelt.'],
            'instagram' => ['Instagram', 'Mit dem Laden stimmen Sie zu, dass Inhalte von Instagram (Meta) eingebunden werden. Dabei wird Ihre IP-Adresse an Meta übermittelt; Daten können auch in den USA verarbeitet werden.', 'Beim Laden werden Daten an Meta übermittelt.'],
            'planity' => ['Online-Terminbuchung', 'Mit dem Laden stimmen Sie zu, dass das Buchungssystem von Planity (Planity SAS, Frankreich) eingebunden wird. Dabei werden Ihre IP-Adresse und Ihre Eingaben an Planity und von Planity eingesetzte Dienstleister wie Amazon Web Services übermittelt; Daten können auch in den USA verarbeitet werden.', 'Beim Laden werden Daten an Planity übermittelt.'],
            'jobmorgen' => ['Stellensuche von jobmorgen.de', 'Mit dem Laden stimmen Sie zu, dass die Stellensuche von jobmorgen.de (MANNHEIMER MORGEN GmbH, Mannheim) eingebunden wird. Dabei werden Ihre IP-Adresse und bei einer Suche Ihre Eingaben an den Anbieter übermittelt.', 'Beim Laden werden Daten an jobmorgen.de übermittelt.'],
            'cleverpv' => ['clever-PV-Rechner', 'Mit dem Laden stimmen Sie zu, dass der Einsparungsrechner der clever-PV GmbH (Landsberg am Lech) eingebunden wird. Dabei werden Ihre IP-Adresse und Ihre Eingaben an clever-PV übermittelt.', 'Beim Laden werden Daten an clever-PV übermittelt.'],
            'anlagenplaner' => ['Autarkierechner', 'Mit dem Laden stimmen Sie zu, dass der Autarkierechner der Sunbeam Communications GmbH (Berlin) eingebunden wird. Dabei werden Ihre IP-Adresse und Ihre Eingaben an Sunbeam übermittelt. Der Rechner nutzt außerdem Google Maps, dabei wird Ihre IP-Adresse an Google übermittelt; Daten können auch in den USA verarbeitet werden.', 'Beim Laden werden Daten an Sunbeam und Google übermittelt.'],
            'dealerweb' => ['Aktionsbanner', 'Mit dem Laden stimmen Sie zu, dass ein Aktionsbanner der COmarketing GmbH (dealerweb-comarketing.de) eingebunden wird, das über die Website-Plattform Wix ausgeliefert wird. Dabei wird Ihre IP-Adresse an COmarketing und Wix übermittelt; Daten können auch außerhalb der EU verarbeitet werden.', 'Beim Laden werden Daten an COmarketing und Wix übermittelt.'],
        ];
        [$name, $notice, $short] = $texts[$service];
        // Same form of address as the cookie banner (BB Consent setting „Anrede“).
        if (function_exists('get_option') && get_option('bbmc_cmp_address', 'sie') === 'du') {
            $notice = strtr($notice, ['stimmen Sie zu' => 'stimmst du zu', 'von Ihnen' => 'von dir', 'Ihre ' => 'deine ']);
        }
        // Small embeds (e.g. a footer map) get the compact layout before any JavaScript runs.
        $height = (int) ($config['height'] ?? 0);
        $width = (int) ($config['width'] ?? 0);
        // Fixed-width embeds such as banners stay centred like the original inline iframe.
        $ratio = preg_match('~^\d+/\d+$~', (string) ($config['ratio'] ?? '')) ? $config['ratio'] : '';
        $styles = ($ratio ? '--bbmc-ratio:' . $ratio . ';' : ($height ? '--bbmc-h:' . $height . 'px;' : '')) . ($width ? 'max-width:' . $width . 'px;margin-left:auto;margin-right:auto;' : '');
        $thumb = $service === 'youtube' && class_exists('BBMC_Thumbs') ? BBMC_Thumbs::url((string) ($config['src'] ?? '')) : '';
        $size = $styles ? ' style="' . $styles . '"' : '';
        $compact = $height && $height < 260 ? ' bbmc-compact' : '';
        $privacy = class_exists('BBMC_Cmp') ? BBMC_Cmp::privacy_url() : (get_privacy_policy_url() ?: home_url('/datenschutz'));
        $link = $privacy ? ' <a href="' . esc_url($privacy) . '">Datenschutzerklärung</a>' : '';
        return '<section class="bbmc' . $compact . '" data-service="' . esc_attr($service) . '"' . $size . ' data-bbmc="' . esc_attr(wp_json_encode($config)) . '"><div class="bbmc-placeholder' . ($thumb ? ' bbmc-thumb" style="--bbmc-thumb:url(' . esc_url($thumb) . ')' : '') . '"><strong>' . $name . ' ist deaktiviert</strong><p class="bbmc-long">' . $notice . $link . '</p><p class="bbmc-short">' . $short . $link . '</p><label><input type="checkbox" class="bbmc-remember"> <span class="bbmc-long">Freigabe für 30 Tage auf diesem Gerät merken</span><span class="bbmc-short">30 Tage merken</span></label><button type="button" class="bbmc-allow">' . $name . ' laden</button><noscript>Zum Freigeben des Inhalts ist JavaScript erforderlich.</noscript></div><div class="bbmc-target" hidden></div><button type="button" class="bbmc-revoke" hidden>Einwilligung widerrufen</button><p class="bbmc-status" role="status"></p></section>';
    }
    public static function divi_api_key() {
        // Use the same accessor as Divi itself; its setting is global, not theme-prefixed.
        $key = function_exists('et_pb_get_google_api_key') ? et_pb_get_google_api_key() : '';
        if (is_string($key) && trim($key) !== '') { return trim($key); }
        $settings = get_option('et_google_api_settings', []);
        $key = is_array($settings) ? ($settings['api_key'] ?? '') : '';
        if (is_string($key) && trim($key) !== '') { return trim($key); }
        $key = function_exists('et_get_option') ? et_get_option('divi_google_api_key', '') : '';
        return is_string($key) ? trim($key) : '';
    }
    public static function divi($output, $tag, $attrs, $match) {
        if (!in_array($tag, ['et_pb_map', 'et_pb_fullwidth_map'], true) || !is_string($output)) { return $output; }
        // Read Divi's resolved HTML, including presets and its actual coordinate field names.
        preg_match_all('~<div\b[^>]*>~i', $output, $tags, PREG_OFFSET_CAPTURE);
        $center = null;
        $pins = [];
        foreach ($tags[0] as $entry) {
            [$element, $offset] = $entry;
            $class = self::attribute($element, 'class');
            if (preg_match('/(?:^|\s)et_pb_map(?:\s|$)/', $class)) {
                $lat = self::attribute($element, 'data-center-lat');
                $lng = self::attribute($element, 'data-center-lng');
                if (is_numeric($lat) && is_numeric($lng)) {
                    $center = ['lat' => (float)$lat, 'lng' => (float)$lng, 'zoom' => max(1, min(20, (int) self::attribute($element, 'data-zoom')))];
                }
            }
            if (preg_match('/(?:^|\s)et_pb_map_pin(?:\s|$)/', $class)) {
                $lat = self::attribute($element, 'data-lat');
                $lng = self::attribute($element, 'data-lng');
                if (!is_numeric($lat) || !is_numeric($lng)) { continue; }
                $text = '';
                $tail = substr($output, $offset + strlen($element));
                if (preg_match('~^\s*(?:<h3\b[^>]*>.*?</h3>\s*)?<div\b[^>]*class=["\x27]infowindow["\x27][^>]*>(.*?)</div>~is', $tail, $body)) {
                    $text = html_entity_decode(wp_strip_all_tags(preg_replace('~<br\s*/?>~i', "\n", $body[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $pins[] = ['lat' => (float)$lat, 'lng' => (float)$lng, 'title' => self::attribute($element, 'data-title'), 'text' => $text];
            }
        }
        if ($center === null && $pins) { $center = ['lat' => $pins[0]['lat'], 'lng' => $pins[0]['lng'], 'zoom' => 14]; }
        if ($center === null) {
            return self::box(['kind' => 'divi', 'key' => self::divi_api_key(), 'pins' => [], 'lat' => null, 'lng' => null, 'zoom' => 14]);
        }
        $config = array_merge(['kind' => 'divi', 'key' => self::divi_api_key(), 'pins' => $pins], $center);
        // Retain Divi's module classes and ID so existing layout selectors still apply.
        $box = self::box($config);
        if (preg_match('~^\s*(<div\b[^>]*>)~i', $output, $wrapper)) { return $wrapper[1] . $box . '</div>'; }
        return $box;
    }
    /** Replace complete Smash Balloon feed containers before the browser sees any media. */
    public static function feeds($html) {
        preg_match_all('~<!--.*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>|</?div\b[^>]*>~is', $html, $tokens, PREG_OFFSET_CAPTURE);
        $ranges = [];
        $start = null;
        $depth = 0;
        foreach ($tokens[0] as $token) {
            [$tag, $offset] = $token;
            if (!preg_match('~^</?div\b~i', $tag)) { continue; }
            $closing = stripos($tag, '</div') === 0;
            if ($start === null && !$closing && self::attribute($tag, 'id') === 'sb_instagram') {
                $start = $offset;
                $depth = 1;
            } elseif ($start !== null) {
                $depth += $closing ? -1 : 1;
                if ($depth === 0) {
                    $ranges[] = [$start, $offset + strlen($tag) - $start];
                    $start = null;
                }
            }
        }
        // Work backwards so recorded byte offsets remain valid.
        foreach (array_reverse($ranges) as $range) {
            [$offset, $length] = $range;
            $feed = substr($html, $offset, $length);
            $html = substr_replace($html, self::box(['service' => 'instagram', 'kind' => 'smashballoon', 'html' => base64_encode($feed)]), $offset, $length);
        }
        return [$html, count($ranges)];
    }
    public static function elfsight_script($tag) {
        $src = self::attribute($tag, 'src');
        $normal = strpos($src, '//') === 0 ? 'https:' . $src : $src;
        return self::host($normal) === 'static.elfsight.com'
            && wp_parse_url($normal, PHP_URL_PATH) === '/platform/platform.js';
    }
    public static function elfsight_containers($html) {
        preg_match_all('~<!--.*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>|</?div\b[^>]*>~is', $html, $tokens, PREG_OFFSET_CAPTURE);
        $ranges = []; $start = null; $depth = 0;
        foreach ($tokens[0] as $token) {
            [$tag, $offset] = $token;
            if (!preg_match('~^</?div\b~i', $tag)) { continue; }
            $closing = stripos($tag, '</div') === 0;
            if ($start === null && !$closing && preg_match('/(?:^|\s)elfsight-app-[a-f0-9-]+(?:\s|$)/i', self::attribute($tag, 'class'))) {
                $start = $offset; $depth = 1;
            } elseif ($start !== null) {
                $depth += $closing ? -1 : 1;
                if ($depth === 0) { $ranges[] = [$start, $offset + strlen($tag) - $start]; $start = null; }
            }
        }
        foreach (array_reverse($ranges) as $range) {
            [$offset, $length] = $range;
            $widget = substr($html, $offset, $length);
            $html = substr_replace($html, self::box(['service' => 'elfsight', 'kind' => 'html-scripts', 'html' => base64_encode($widget)]), $offset, $length);
        }
        return [$html, count($ranges)];
    }
    public static function filter_html($html) {
        [$html, $elfsight_widgets] = self::elfsight_containers($html);
        $elfsight_loader = false;
        [$html, $feeds] = self::feeds($html);
        $planity = false;
        $jobmorgen = false;
        $anlagenplaner = false;
        // Tokenise tags without reserialising the document or touching script strings.
        $html = preg_replace_callback('~<!--.*?-->|<script\b[^>]*>.*?</script\s*>|<blockquote\b[^>]*>.*?</blockquote\s*>|<iframe\b[^>]*>.*?</iframe\s*>|<link\b[^>]*>|<[a-z][a-z0-9]*\b[^>]*\sdata-et-multi-view\s*=[^>]*>~is', function ($m) use ($feeds, &$planity, &$elfsight_loader, &$jobmorgen, &$anlagenplaner) {
            $tag = $m[0];
            if (strpos($tag, '<!--') === 0) { return $tag; }
            if (!preg_match('~^<(?:script|blockquote|iframe|link)\b~i', $tag)) { return self::multi_view($tag); }
            if (stripos($tag, '<blockquote') === 0) {
                if (!preg_match('/(?:^|\s)instagram-media(?:\s|$)/', self::attribute($tag, 'class'))) { return $tag; }
                $url = self::attribute($tag, 'data-instgrm-permalink');
                if (!$url && preg_match_all('~<a\b[^>]*>~i', $tag, $anchors)) {
                    foreach ($anchors[0] as $anchor) {
                        $candidate = self::attribute($anchor, 'href');
                        if (self::instagram_embed($candidate)) { $url = $candidate; break; }
                    }
                }
                $embed = self::instagram_embed($url);
                // Unsupported Instagram embeds fail closed, including remote preview images.
                return self::box(['service' => 'instagram', 'kind' => 'iframe', 'src' => $embed, 'title' => 'Instagram-Beitrag', 'height' => 620]);
            }
            if (stripos($tag, '<script') === 0) {
                $src = self::attribute($tag, 'src');
                $id = self::attribute($tag, 'id');
                if (self::elfsight_script($tag)) {
                    if ($elfsight_loader) { return ''; }
                    $elfsight_loader = true;
                    return '<template class="bbmc-feed-script" data-service="elfsight" data-script="' . esc_attr(base64_encode($tag)) . '"></template>';
                }
                if ($feeds && (preg_match('~/(?:instagram-feed|instagram-feed-pro)/.*\.js(?:[?]|$)~i', $src) || preg_match('/^sbi_[a-z0-9_-]+$/i', $id))) {
                    return '<template class="bbmc-feed-script" data-service="instagram" data-script="' . esc_attr(base64_encode($tag)) . '"></template>';
                }
                if (self::jobmorgen_script($tag)) {
                    // The placeholder goes next to the partner box container below; pages without one only hold the script.
                    $jobmorgen = true;
                    return '<template class="bbmc-feed-script" data-service="jobmorgen" data-script="' . esc_attr(base64_encode($tag)) . '"></template>';
                }
                if (self::anlagenplaner_script($tag)) {
                    // Like Jobmorgen: the placeholder goes before the calculator's container below.
                    $anlagenplaner = true;
                    return '<template class="bbmc-feed-script" data-service="anlagenplaner" data-script="' . esc_attr(base64_encode($tag)) . '"></template>';
                }
                if (self::planity_script($tag)) {
                    // Keep configuration and loaders in order; one placeholder sits where the widget code started.
                    $holder = '<template class="bbmc-feed-script" data-service="planity" data-script="' . esc_attr(base64_encode($tag)) . '"></template>';
                    if ($planity) { return $holder; }
                    $planity = true;
                    return self::box(['service' => 'planity', 'kind' => 'scripts']) . $holder;
                }
                if (self::map_url($src) || (self::instagram_host($src) && preg_match('~^/embed\.js(?:[?#]|$)~', (string) wp_parse_url(strpos($src, '//') === 0 ? 'https:' . $src : $src, PHP_URL_PATH)))) { return ''; }
                return $tag;
            }
            if (stripos($tag, '<link') === 0) {
                $rel = self::attribute($tag, 'rel');
                $href = self::attribute($tag, 'href');
                if ((self::host(strpos($href, '//') === 0 ? 'https:' . $href : $href) === 'static.elfsight.com' || in_array(self::host($href), ['outlook.office365.com', 'outlook.office.com'], true) || self::extra_iframe_service($href) || preg_match('/(?:^|\.)(youtube(?:-nocookie)?\.com|ytimg\.com|googlevideo\.com|tramino\.de)$/', self::host(strpos($href, '//') === 0 ? 'https:' . $href : $href)) || self::google($href) || self::instagram_host($href) || self::dealerweb_url($href) || self::anlagenplaner_host($href) || self::host($href) === 'd2skjte8udjqxw.cloudfront.net' || in_array(self::host(strpos($href, '//') === 0 ? 'https:' . $href : $href), ['jobmorgen.de', 'www.jobmorgen.de'], true)) && preg_match('/preconnect|dns-prefetch|preload|prefetch/i', $rel)) { return ''; }
                return $tag;
            }
            $src = self::attribute($tag, 'src');
            if (!$src) { $src = self::attribute($tag, 'data-src'); }
            $extra_service = self::extra_iframe_service($src);
            if ($extra_service) {
                $titles = ['youtube' => 'YouTube-Video', 'tramino' => 'Tramino-Inhalte', 'bookings' => 'Microsoft Bookings', 'cleverpv' => 'clever-PV-Rechner'];
                $heights = ['youtube' => 450, 'tramino' => 400, 'bookings' => 800, 'cleverpv' => 800];
                if ($extra_service === 'youtube') { $src = self::youtube_nocookie($src); }
                $config = ['service' => $extra_service, 'kind' => 'iframe', 'src' => $src,
                    'title' => self::attribute($tag, 'title') ?: $titles[$extra_service],
                    'height' => max(150, min(1200, (int)(self::attribute($tag, 'height') ?: $heights[$extra_service])))];
                if ($extra_service === 'cleverpv') {
                    // The calculator sets its height via inline style; its resize script finds the frame by id.
                    if (!self::attribute($tag, 'height') && preg_match('~(?:^|;)\s*height\s*:\s*(\d+)px~i', self::attribute($tag, 'style'), $sh)) { $config['height'] = max(150, min(2000, (int) $sh[1])); }
                    $frame_id = self::attribute($tag, 'id');
                    if (preg_match('~^[A-Za-z][A-Za-z0-9_-]{0,63}$~', $frame_id)) { $config['id'] = $frame_id; }
                }
                if ($extra_service === 'youtube') {
                    // Videos scale like Divi/FitVids: keep the embed's aspect ratio instead of its fixed height.
                    $w = (int) self::attribute($tag, 'width');
                    $h = (int) self::attribute($tag, 'height');
                    $config['ratio'] = $w > 0 && $h > 0 && preg_match('~^\s*\d+\s*(?:px)?\s*$~i', self::attribute($tag, 'width')) ? $w . '/' . $h : '16/9';
                }
                return self::box($config);
            }
            if (self::instagram_host($src)) {
                return self::box(['service' => 'instagram', 'kind' => 'iframe', 'src' => self::instagram_embed($src), 'title' => 'Instagram-Beitrag', 'height' => 620]);
            }
            if (self::dealerweb_url($src)) {
                // Only fixed pixel widths limit the banner; "100%" and similar stay full width.
                $width = preg_match('~^\s*(\d+)\s*(?:px)?\s*$~i', self::attribute($tag, 'width'), $w) ? (int) $w[1] : 0;
                return self::box(['service' => 'dealerweb', 'kind' => 'iframe', 'src' => self::dealerweb_url($src), 'title' => self::attribute($tag, 'title') ?: 'Aktionsbanner', 'height' => max(100, min(1000, (int)(self::attribute($tag, 'height') ?: 300))), 'width' => $width >= 100 ? min(2000, $width) : 0]);
            }
            if (self::iframe_url($src)) {
                return self::box(['kind' => 'iframe', 'src' => $src, 'title' => self::attribute($tag, 'title') ?: 'Google Maps', 'height' => max(150, min(1000, (int)(self::attribute($tag, 'height') ?: 400)))]);
            }
            return $tag;
        }, $html);
        if ($jobmorgen) {
            $html = preg_replace('~<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bregio-power-partner-box\b~i', self::box(['service' => 'jobmorgen', 'kind' => 'scripts', 'height' => 360]) . '$0', $html, 1);
        }
        if ($anlagenplaner) {
            $html = preg_replace('~<div\b[^>]*\b(?:id|class)\s*=\s*["\'][^"\']*\bsb-anlagenplaner\b~i', self::box(['service' => 'anlagenplaner', 'kind' => 'scripts', 'height' => 500]) . '$0', $html, 1);
        }
        if ($elfsight_loader && !$elfsight_widgets) {
            // A loader in <head> must not insert a visible section into the head.
            $box = self::box(['service' => 'elfsight', 'kind' => 'scripts']);
            $pos = strripos($html, '</body>');
            $html = $pos === false ? $html . $box : substr_replace($html, $box, $pos, 0);
        }
        return $html;
    }
    /**
     * Divi keeps per-device module content as JSON in data-et-multi-view and writes it back
     * with innerHTML on breakpoint changes, so embedded iframes there must be blocked too.
     */
    public static function multi_view($tag) {
        return preg_replace_callback('~(\sdata-et-multi-view\s*=\s*)(?:"([^"]*)"|\x27([^\x27]*)\x27)~i', function ($m) {
            $data = json_decode(html_entity_decode($m[2] !== '' ? $m[2] : ($m[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (!is_array($data)) { return $m[0]; }
            $changed = false;
            array_walk_recursive($data, function (&$value) use (&$changed) {
                if (!is_string($value) || !preg_match('~<(?:iframe|script|blockquote|link)\b~i', $value)) { return; }
                $filtered = self::filter_html($value);
                if ($filtered !== $value) { $value = $filtered; $changed = true; }
            });
            return $changed ? $m[1] . '"' . esc_attr(wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) . '"' : $m[0];
        }, $tag);
    }
    /** An admin-ajax request sent from a public page, not from wp-admin or the Divi builder. */
    public static function frontend_ajax() {
        $referer = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';
        if ($referer === '' || self::host($referer) !== self::host(home_url())) { return false; }
        return strpos((string) wp_parse_url($referer, PHP_URL_PATH), '/wp-admin') === false && strpos($referer, 'et_fb=') === false && empty($_REQUEST['et_fb']);
    }
    /** Filters HTML in an ajax response, whether sent as JSON (strings inside) or as plain HTML. */
    public static function filter_ajax($output) {
        // JSON may escape "<" as \u003C and "/" as \/; a cheap check skips responses without embeds.
        if (!preg_match('~(?:<|u003C)(?:iframe|script|blockquote)\b~i', $output)) { return $output; }
        $trimmed = ltrim($output);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $data = json_decode($output, true);
            if (!is_array($data)) { return $output; }
            $changed = false;
            array_walk_recursive($data, function (&$value) use (&$changed) {
                if (!is_string($value) || !preg_match('~<(?:iframe|script|blockquote)\b~i', $value)) { return; }
                $filtered = self::filter_html($value);
                if ($filtered !== $value) { $value = $filtered; $changed = true; }
            });
            return $changed ? wp_json_encode($data) : $output;
        }
        return self::filter_html($output);
    }
    public static function attribute($tag, $name) {
        $opening = substr($tag, 0, strpos($tag, '>') + 1);
        if (preg_match('~\s' . preg_quote($name, '~') . '\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+))~i', $opening, $m)) {
            return html_entity_decode($m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }
}
require_once __DIR__ . '/includes/updater.php';
require_once __DIR__ . '/includes/thumbs.php';
require_once __DIR__ . '/includes/custom-css.php';
require_once __DIR__ . '/includes/look.php';
// Default-off cookie banner for tracking (CookieConsent, bundled).
require_once __DIR__ . '/includes/cmp/Cmp.php';
BB_Map_Consent::boot();

// Default-off legal module; registration does not enable or replace any existing text provider.
require_once __DIR__ . '/includes/legal/bootstrap.php';
add_action('plugins_loaded', function () { bb_legal_register_provider([
    'id'=>'consent','version'=>'1.0.0','schema'=>1,
    'path'=>__DIR__ . '/includes/legal','url'=>plugins_url('includes/legal',__FILE__),'plugin'=>plugin_basename(__FILE__),
    'enabled'=>(bool)get_option('bb_legal_enabled_consent',false),
]); }, 1);
