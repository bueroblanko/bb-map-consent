<?php
/**
 * Plugin Name: BB Map Consent
 * Description: Lokale Einwilligung für Google Maps, Snazzy Maps, klassische Divi-Karten und Instagram.
 * Version: 0.3.0
 * Update URI: https://github.com/bueroblanko/bb-map-consent
 * Plugin URI: https://github.com/bueroblanko/bb-map-consent
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Büro Blanko
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
final class BB_Map_Consent {
    public static function boot() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('pre_do_shortcode_tag', [__CLASS__, 'divi'], 10, 4);
        add_action('template_redirect', function () {
            if (!is_feed() && !is_admin() && !wp_doing_ajax()) { ob_start([__CLASS__, 'filter_html']); }
        }, 0);
        add_shortcode('bb_instagram_revoke', function () { return '<button type="button" class="bbmc-revoke" data-service="instagram">Instagram-Einwilligung widerrufen</button>'; });
        add_shortcode('bb_maps_revoke', function () { return '<button type="button" class="bbmc-revoke">Google-Maps-Einwilligung widerrufen</button>'; });
    }
    public static function assets() {
        wp_enqueue_style('bb-map-consent', plugins_url('assets/maps.css', __FILE__), [], '0.3.0');
        wp_enqueue_script('bb-map-consent', plugins_url('assets/maps.js', __FILE__), [], '0.3.0', true);
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
    public static function box($config) {
        $service = $config['service'] ?? 'maps';
        $instagram = $service === 'instagram';
        $name = $instagram ? 'Instagram' : 'Google Maps';
        $notice = $instagram
            ? 'Mit dem Laden stimmen Sie zu, dass Inhalte von Instagram (Meta) eingebunden werden. Dabei wird Ihre IP-Adresse an Meta übermittelt; Daten können auch in den USA verarbeitet werden.'
            : 'Mit dem Laden stimmen Sie zu, dass Google Maps eingebunden wird (bei einer Snazzy-Maps-Karte auch über Snazzy Maps). Dabei wird Ihre IP-Adresse an Google übermittelt; Daten können auch in den USA verarbeitet werden.';
        $privacy = get_privacy_policy_url();
        $link = $privacy ? ' <a href="' . esc_url($privacy) . '">Datenschutzerklärung</a>' : '';
        return '<section class="bbmc" data-service="' . esc_attr($service) . '" data-bbmc="' . esc_attr(wp_json_encode($config)) . '"><div class="bbmc-placeholder"><strong>' . $name . ' ist deaktiviert</strong><p>' . $notice . $link . '</p><label><input type="checkbox" class="bbmc-remember"> Freigabe für 30 Tage auf diesem Gerät merken</label><button type="button" class="bbmc-allow">' . $name . ' laden</button><noscript>Zum Freigeben des Inhalts ist JavaScript erforderlich.</noscript></div><div class="bbmc-target" hidden></div><button type="button" class="bbmc-revoke" hidden>Einwilligung widerrufen</button><p class="bbmc-status" role="status"></p></section>';
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
    public static function filter_html($html) {
        // Tokenise tags without reserialising the document or touching script strings.
        return preg_replace_callback('~<!--.*?-->|<script\b[^>]*>.*?</script\s*>|<blockquote\b[^>]*>.*?</blockquote\s*>|<iframe\b[^>]*>.*?</iframe\s*>|<link\b[^>]*>~is', function ($m) {
            $tag = $m[0];
            if (strpos($tag, '<!--') === 0) { return $tag; }
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
                if (self::map_url($src) || (self::instagram_host($src) && preg_match('~^/embed\.js(?:[?#]|$)~', (string) wp_parse_url(strpos($src, '//') === 0 ? 'https:' . $src : $src, PHP_URL_PATH)))) { return ''; }
                return $tag;
            }
            if (stripos($tag, '<link') === 0) {
                $rel = self::attribute($tag, 'rel');
                if ((self::google(self::attribute($tag, 'href')) || self::instagram_host(self::attribute($tag, 'href'))) && preg_match('/preconnect|dns-prefetch|preload|prefetch/i', $rel)) { return ''; }
                return $tag;
            }
            $src = self::attribute($tag, 'src');
            if (!$src) { $src = self::attribute($tag, 'data-src'); }
            if (self::instagram_host($src)) {
                return self::box(['service' => 'instagram', 'kind' => 'iframe', 'src' => self::instagram_embed($src), 'title' => 'Instagram-Beitrag', 'height' => 620]);
            }
            if (self::iframe_url($src)) {
                return self::box(['kind' => 'iframe', 'src' => $src, 'title' => self::attribute($tag, 'title') ?: 'Google Maps', 'height' => max(200, min(1000, (int)(self::attribute($tag, 'height') ?: 400)))]);
            }
            return $tag;
        }, $html);
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
