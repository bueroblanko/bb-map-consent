<?php
/**
 * Plugin Name: BB Consent
 * Description: Lokale Einwilligung für Google Maps, Snazzy Maps, klassische Divi-Karten, Instagram, DealerWeb-Aktionsbanner und Planity-Terminbuchung.
 * Version: 0.5.1
 * Update URI: https://github.com/bueroblanko/bb-map-consent
 * Plugin URI: https://github.com/bueroblanko/bb-map-consent
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Büro Blanko
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
define('BBMC_VERSION', '0.5.1');
final class BB_Map_Consent {
    public static function boot() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('pre_do_shortcode_tag', [__CLASS__, 'divi'], 10, 4);
        add_action('template_redirect', function () {
            if (!is_feed() && !is_admin() && !wp_doing_ajax()) { ob_start([__CLASS__, 'filter_html']); }
        }, 0);
        add_shortcode('bb_instagram_revoke', function () { return '<button type="button" class="bbmc-revoke" data-service="instagram">Instagram-Einwilligung widerrufen</button>'; });
        add_shortcode('bb_maps_revoke', function () { return '<button type="button" class="bbmc-revoke">Google-Maps-Einwilligung widerrufen</button>'; });
        add_shortcode('bb_planity_revoke', function () { return '<button type="button" class="bbmc-revoke" data-service="planity">Einwilligung für die Online-Terminbuchung widerrufen</button>'; });
        add_shortcode('bb_dealerweb_revoke', function () { return '<button type="button" class="bbmc-revoke" data-service="dealerweb">Aktionsbanner-Einwilligung widerrufen</button>'; });
    }
    public static function assets() {
        wp_enqueue_style('bb-map-consent', plugins_url('assets/maps.css', __FILE__), [], '0.5.1');
        wp_enqueue_script('bb-map-consent', plugins_url('assets/maps.js', __FILE__), [], '0.5.1', true);
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
    public static function box($config) {
        $service = $config['service'] ?? 'maps';
        $texts = [
            'maps' => ['Google Maps', 'Mit dem Laden stimmen Sie zu, dass Google Maps eingebunden wird (bei einer Snazzy-Maps-Karte auch über Snazzy Maps). Dabei wird Ihre IP-Adresse an Google übermittelt; Daten können auch in den USA verarbeitet werden.', 'Beim Laden werden Daten an Google übermittelt.'],
            'instagram' => ['Instagram', 'Mit dem Laden stimmen Sie zu, dass Inhalte von Instagram (Meta) eingebunden werden. Dabei wird Ihre IP-Adresse an Meta übermittelt; Daten können auch in den USA verarbeitet werden.', 'Beim Laden werden Daten an Meta übermittelt.'],
            'planity' => ['Online-Terminbuchung', 'Mit dem Laden stimmen Sie zu, dass das Buchungssystem von Planity (Planity SAS, Frankreich) eingebunden wird. Dabei werden Ihre IP-Adresse und Ihre Eingaben an Planity und von Planity eingesetzte Dienstleister wie Amazon Web Services übermittelt; Daten können auch in den USA verarbeitet werden.', 'Beim Laden werden Daten an Planity übermittelt.'],
            'dealerweb' => ['Aktionsbanner', 'Mit dem Laden stimmen Sie zu, dass ein Aktionsbanner der COmarketing GmbH (dealerweb-comarketing.de) eingebunden wird, das über die Website-Plattform Wix ausgeliefert wird. Dabei wird Ihre IP-Adresse an COmarketing und Wix übermittelt; Daten können auch außerhalb der EU verarbeitet werden.', 'Beim Laden werden Daten an COmarketing und Wix übermittelt.'],
        ];
        [$name, $notice, $short] = $texts[$service];
        // Small embeds (e.g. a footer map) get the compact layout before any JavaScript runs.
        $height = (int) ($config['height'] ?? 0);
        $width = (int) ($config['width'] ?? 0);
        // Fixed-width embeds such as banners stay centred like the original inline iframe.
        $styles = ($height ? '--bbmc-h:' . $height . 'px;' : '') . ($width ? 'max-width:' . $width . 'px;margin-left:auto;margin-right:auto;' : '');
        $size = $styles ? ' style="' . $styles . '"' : '';
        $compact = $height && $height < 260 ? ' bbmc-compact' : '';
        $privacy = get_privacy_policy_url();
        $link = $privacy ? ' <a href="' . esc_url($privacy) . '">Datenschutzerklärung</a>' : '';
        return '<section class="bbmc' . $compact . '" data-service="' . esc_attr($service) . '"' . $size . ' data-bbmc="' . esc_attr(wp_json_encode($config)) . '"><div class="bbmc-placeholder"><strong>' . $name . ' ist deaktiviert</strong><p class="bbmc-long">' . $notice . $link . '</p><p class="bbmc-short">' . $short . $link . '</p><label><input type="checkbox" class="bbmc-remember"> <span class="bbmc-long">Freigabe für 30 Tage auf diesem Gerät merken</span><span class="bbmc-short">30 Tage merken</span></label><button type="button" class="bbmc-allow">' . $name . ' laden</button><noscript>Zum Freigeben des Inhalts ist JavaScript erforderlich.</noscript></div><div class="bbmc-target" hidden></div><button type="button" class="bbmc-revoke" hidden>Einwilligung widerrufen</button><p class="bbmc-status" role="status"></p></section>';
    }
    public static function divi($return, $tag, $attrs, $match) {
        if (!in_array($tag, ['et_pb_map', 'et_pb_fullwidth_map'], true) || $return !== false) { return $return; }
        $pins = [];
        preg_match_all('/\[et_pb_map_pin\b([^\]]*)\](.*?)\[\/et_pb_map_pin\]/s', $match[5] ?? '', $matches, PREG_SET_ORDER);
        foreach ($matches as $pin) {
            $a = shortcode_parse_atts($pin[1]);
            if (isset($a['map_pin_address_lat'], $a['map_pin_address_lng']) && is_numeric($a['map_pin_address_lat']) && is_numeric($a['map_pin_address_lng'])) {
                $pins[] = ['lat' => (float)$a['map_pin_address_lat'], 'lng' => (float)$a['map_pin_address_lng'], 'title' => sanitize_text_field($a['title'] ?? ''), 'text' => wp_strip_all_tags($pin[2])];
            }
        }
        $key = function_exists('et_get_option') ? et_get_option('divi_google_api_key', '') : '';
        $config = ['kind' => 'divi', 'key' => $key, 'pins' => $pins, 'lat' => (float)($attrs['map_center_lat'] ?? 0), 'lng' => (float)($attrs['map_center_lng'] ?? 0), 'zoom' => max(1, min(20, (int)($attrs['zoom_level'] ?? 14)))];
        return self::box($config);
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
    public static function filter_html($html) {
        [$html, $feeds] = self::feeds($html);
        $planity = false;
        // Tokenise tags without reserialising the document or touching script strings.
        return preg_replace_callback('~<!--.*?-->|<script\b[^>]*>.*?</script\s*>|<blockquote\b[^>]*>.*?</blockquote\s*>|<iframe\b[^>]*>.*?</iframe\s*>|<link\b[^>]*>|<[a-z][a-z0-9]*\b[^>]*\sdata-et-multi-view\s*=[^>]*>~is', function ($m) use ($feeds, &$planity) {
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
                if ($feeds && (preg_match('~/(?:instagram-feed|instagram-feed-pro)/.*\.js(?:[?]|$)~i', $src) || preg_match('/^sbi_[a-z0-9_-]+$/i', $id))) {
                    return '<template class="bbmc-feed-script" data-service="instagram" data-script="' . esc_attr(base64_encode($tag)) . '"></template>';
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
                if ((self::google($href) || self::instagram_host($href) || self::dealerweb_url($href) || self::host($href) === 'd2skjte8udjqxw.cloudfront.net') && preg_match('/preconnect|dns-prefetch|preload|prefetch/i', $rel)) { return ''; }
                return $tag;
            }
            $src = self::attribute($tag, 'src');
            if (!$src) { $src = self::attribute($tag, 'data-src'); }
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
    public static function attribute($tag, $name) {
        $opening = substr($tag, 0, strpos($tag, '>') + 1);
        if (preg_match('~\s' . preg_quote($name, '~') . '\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+))~i', $opening, $m)) {
            return html_entity_decode($m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }
}
require_once __DIR__ . '/includes/updater.php';
BB_Map_Consent::boot();
