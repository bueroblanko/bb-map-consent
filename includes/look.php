<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Look of cookie banner and placeholders. Default: taken from the site (accent colour and button
 * corners from Divi, Elementor or theme.json; fonts are read in the browser by cmp.js).
 * Four settings under BB Consent → Gestaltung override it. Own CSS still wins: it is printed later
 * and sets variables on #cc-main or .bbmc directly, while this module only sets them on :root.
 */
final class BBMC_Look {
    const OPTION = 'bbmc_look';
    const DEFAULTS = ['accent_mode' => 'site', 'accent' => '', 'font' => 'site', 'radius' => 'site', 'scheme' => 'light'];
    /** Divi's built-in accent colour; a site that never changed it does not use it as its brand colour. */
    const DIVI_DEFAULT_ACCENT = '#2ea3f2';
    /** [button radius, dialog radius] in px. */
    const RADII = ['square' => [0, 0], 'soft' => [6, 12], 'round' => [999, 20]];

    public static function boot() {
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 20);
    }
    public static function register() {
        register_setting('bbmc_design', self::OPTION, ['type' => 'array', 'default' => self::DEFAULTS, 'sanitize_callback' => [__CLASS__, 'sanitize']]);
    }
    public static function settings() {
        $saved = get_option(self::OPTION, []);
        return self::sanitize(is_array($saved) ? $saved : []);
    }
    public static function sanitize($value) {
        $value = is_array($value) ? $value : [];
        $pick = function ($key, array $allowed) use ($value) {
            return isset($value[$key]) && in_array($value[$key], $allowed, true) ? $value[$key] : self::DEFAULTS[$key];
        };
        return [
            'accent_mode' => $pick('accent_mode', ['site', 'own']),
            'accent' => isset($value['accent']) ? self::hex($value['accent']) : '',
            'font' => $pick('font', ['site', 'system']),
            'radius' => $pick('radius', ['site', 'square', 'soft', 'round']),
            'scheme' => $pick('scheme', ['light', 'dark']),
        ];
    }
    /** #rrggbb in lower case, or '' (also accepts #rgb). */
    public static function hex($color) {
        $color = strtolower(trim((string) $color));
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m)) { return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3]; }
        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '';
    }
    /** What the site itself uses: ['accent' => hex|'', 'radius' => px|null, 'source' => label]. */
    public static function detect() {
        if (function_exists('et_get_option')) {
            $accent = self::hex(et_get_option('accent_color', ''));
            $radius = et_get_option('all_buttons_border_radius', '');
            return ['accent' => $accent === self::DIVI_DEFAULT_ACCENT ? '' : $accent, 'radius' => is_numeric($radius) ? max(0, (int) $radius) : null, 'source' => 'Divi'];
        }
        $kit = (int) get_option('elementor_active_kit', 0);
        if ($kit) { return self::from_elementor(get_post_meta($kit, '_elementor_page_settings', true)); }
        if (function_exists('wp_get_global_settings')) {
            $found = self::from_palette(wp_get_global_settings(['color', 'palette', 'theme']));
            if ($found['accent'] !== '') { return $found; }
        }
        return ['accent' => '', 'radius' => null, 'source' => ''];
    }
    /** Elementor kit settings: global colour "accent", else "primary"; button radius. */
    public static function from_elementor($settings) {
        $settings = is_array($settings) ? $settings : [];
        $colors = [];
        foreach (isset($settings['system_colors']) && is_array($settings['system_colors']) ? $settings['system_colors'] : [] as $color) {
            if (isset($color['_id'], $color['color'])) { $colors[$color['_id']] = self::hex($color['color']); }
        }
        $radius = isset($settings['button_border_radius']['top']) && is_numeric($settings['button_border_radius']['top']) ? max(0, (int) $settings['button_border_radius']['top']) : null;
        return ['accent' => ($colors['accent'] ?? '') ?: ($colors['primary'] ?? ''), 'radius' => $radius, 'source' => 'Elementor'];
    }
    /** theme.json palette of a block theme: first of the usual brand slugs. */
    public static function from_palette($palette) {
        foreach (['primary', 'accent', 'accent-1', 'contrast-2'] as $slug) {
            foreach (is_array($palette) ? $palette : [] as $entry) {
                if (isset($entry['slug'], $entry['color']) && $entry['slug'] === $slug && self::hex($entry['color']) !== '') { return ['accent' => self::hex($entry['color']), 'radius' => null, 'source' => 'Theme']; }
            }
        }
        return ['accent' => '', 'radius' => null, 'source' => ''];
    }
    /** Effective values: ['accent' => hex|'', 'radius' => [button, dialog]|null]. */
    public static function resolve(array $settings, array $detected) {
        $accent = $settings['accent_mode'] === 'own' ? $settings['accent'] : $detected['accent'];
        if (isset(self::RADII[$settings['radius']])) { $radius = self::RADII[$settings['radius']]; }
        elseif ($detected['radius'] !== null) { $radius = [$detected['radius'], min(16, $detected['radius'] * 2)]; }
        else { $radius = null; }
        return ['accent' => $accent, 'radius' => $radius, 'scheme' => $settings['scheme']];
    }
    /** Dark text on light accents (WCAG relative luminance). */
    public static function light($hex) {
        $l = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $l[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2] > 0.45;
    }
    /** Mix towards black (dark accents) or white (light accents) for the hover state. */
    public static function hover($hex) {
        $target = self::light($hex) ? 255 : 0;
        $out = '#';
        foreach ([1, 3, 5] as $i) { $out .= sprintf('%02x', (int) round(hexdec(substr($hex, $i, 2)) * 0.82 + $target * 0.18)); }
        return $out;
    }
    public static function css(array $look) {
        $vars = [];
        if ($look['accent'] !== '') {
            $text = self::light($look['accent']) ? '#111' : '#fff';
            $hover = self::hover($look['accent']);
            $vars = ['--cc-btn-primary-bg' => $look['accent'], '--cc-btn-primary-border-color' => $look['accent'], '--cc-btn-primary-color' => $text,
                '--cc-btn-primary-hover-bg' => $hover, '--cc-btn-primary-hover-border-color' => $hover, '--cc-btn-primary-hover-color' => $text,
                '--cc-toggle-on-bg' => $look['accent'], '--bbmc-site-accent' => $look['accent'], '--bbmc-accent-text' => $text];
        }
        // Links in the accent colour only on the light banner; on dark they keep the readable default.
        if ($look['accent'] !== '' && ($look['scheme'] ?? 'light') !== 'dark') { $vars['--cc-link-color'] = $look['accent']; }
        if ($look['radius'] !== null) {
            $vars += ['--cc-btn-border-radius' => $look['radius'][0] . 'px', '--cc-modal-border-radius' => $look['radius'][1] . 'px',
                '--bbmc-btn-radius' => $look['radius'][0] . 'px', '--bbmc-radius' => min(12, $look['radius'][1]) . 'px'];
        }
        // Banner title in the heading font of the site (cmp.js sets the variable).
        $heading = '#cc-main .cm__title,#cc-main .pm__title{font-family:var(--bbmc-heading-font,inherit)}';
        if (!$vars) { return $heading; }
        $out = '';
        foreach ($vars as $name => $value) { $out .= $name . ':' . $value . ';'; }
        // html:root outranks the :root defaults of cookieconsent.css, which load later in the page.
        return 'html:root{' . $out . '}' . $heading;
    }
    public static function enqueue() {
        $css = self::css(self::resolve(self::settings(), self::detect()));
        if ($css !== '') { wp_add_inline_style('bb-map-consent', $css); }
    }
    /** Part of the banner config for cmp.js. */
    public static function client() {
        $settings = self::settings();
        return ['font' => $settings['font'], 'scheme' => $settings['scheme']];
    }
    /** Settings card on the "Gestaltung" tab (inside its form). */
    public static function field() {
        $s = self::settings();
        $d = self::detect();
        $seg = function ($key, array $choices) use ($s) {
            $out = '<div class="bb-seg" role="radiogroup">';
            foreach ($choices as $value => $label) { $out .= '<label><input type="radio" name="' . self::OPTION . '[' . $key . ']" value="' . esc_attr($value) . '"' . checked($s[$key], $value, false) . '><span>' . esc_html($label) . '</span></label>'; }
            return $out . '</div>';
        };
        $found = $d['accent'] !== '' ? '<span class="bb-swatch" style="background:' . esc_attr($d['accent']) . '"></span> erkannt: ' . esc_html($d['accent']) . ' (' . esc_html($d['source']) . ')' : 'Keine Akzentfarbe erkannt, das Banner bleibt neutral grau.';
        $corners = $d['radius'] !== null ? 'erkannt: Buttons ' . (int) $d['radius'] . ' px (' . esc_html($d['source']) . ')' : 'Keine Rundung erkannt, Standard des Banners.';
        echo '<section class="bb-card"><header><h2>Aussehen von Banner und Platzhaltern</h2><p>Standard: übernimmt Schrift, Akzentfarbe und Ecken der Website. Nur ändern, wenn das Ergebnis nicht passt.</p></header><div class="bb-card-body"><div class="bb-grid">';
        echo '<div class="bb-f w3"><span class="bb-label">Akzentfarbe</span>' . $seg('accent_mode', ['site' => 'Wie die Website', 'own' => 'Eigene']) . ' <input type="color" name="' . self::OPTION . '[accent]" value="' . esc_attr($s['accent'] ?: ($d['accent'] ?: '#30363c')) . '" aria-label="Eigene Akzentfarbe" class="bb-color"><span class="bb-help">' . $found . '</span></div>';
        echo '<div class="bb-f w3"><span class="bb-label">Schrift</span>' . $seg('font', ['site' => 'Wie die Website', 'system' => 'Systemschrift']) . '<span class="bb-help">Banner in der Schrift des Fließtexts, Titel in der Schrift der Überschriften.</span></div>';
        echo '<div class="bb-f w3"><span class="bb-label">Ecken</span>' . $seg('radius', ['site' => 'Wie die Website', 'square' => 'Eckig', 'soft' => 'Leicht rund', 'round' => 'Rund']) . '<span class="bb-help">' . $corners . '</span></div>';
        echo '<div class="bb-f w3"><span class="bb-label">Farbschema Banner</span>' . $seg('scheme', ['light' => 'Hell', 'dark' => 'Dunkel']) . '</div>';
        echo '</div></div><div class="bb-card-foot">';
        submit_button('Speichern', 'primary', 'submit', false);
        echo '<span class="bb-small">Eigenes CSS (unten) hat weiterhin Vorrang. Danach eventuell den Seiten-Cache leeren.</span></div></section>';
    }
}
BBMC_Look::boot();
