<?php
if (!defined('ABSPATH')) { exit; }
/** Site-wide styling plus optional overrides for individual pages. */
final class BBMC_Custom_CSS {
    const OPTION = 'bbmc_custom_css';
    const META = '_bbmc_custom_css';
    public static function boot() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('add_meta_boxes_page', [__CLASS__, 'meta_box']);
        add_action('save_post_page', [__CLASS__, 'save_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 30);
        add_filter('plugin_action_links', [__CLASS__, 'settings_link'], 10, 2);
    }
    public static function sanitize($css) {
        if (!is_string($css)) { return ''; }
        // CSS is printed in a style element. Never allow HTML markup or a closing style tag.
        return trim(str_replace(['<', '>'], '', wp_strip_all_tags($css)));
    }
    public static function register() {
        register_setting('bbmc_design', self::OPTION, ['type' => 'string', 'default' => '', 'sanitize_callback' => [__CLASS__, 'sanitize']]);
        add_filter('option_page_capability_bbmc_design', function () { return 'edit_theme_options'; });
    }
    public static function menu() {
        add_options_page('BB Consent', 'BB Consent', 'edit_theme_options', 'bb-consent', [__CLASS__, 'screen']);
    }
    public static function settings_link($links, $file) {
        if ($file !== plugin_basename(dirname(__DIR__) . '/bb-map-consent.php')) { return $links; }
        if (current_user_can('edit_theme_options')) { array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=bb-consent')) . '">Einstellungen</a>'); }
        return $links;
    }
    public static function screen() {
        if (!current_user_can('edit_theme_options')) { return; }
        echo '<div class="wrap"><h1>BB Consent – Gestaltung</h1><p>Dieses CSS gilt für die Content-Blocker auf der gesamten Website. Abweichungen können Sie direkt beim Bearbeiten einer einzelnen WordPress-Seite eintragen.</p><form method="post" action="options.php">';
        settings_fields('bbmc_design');
        echo '<h2><label for="bbmc_custom_css">Eigenes CSS</label></h2><p id="bbmc-css-help">Nutzen Sie <code>.bbmc</code> für den gesamten Blocker, <code>.bbmc-placeholder</code> für den Platzhalter und <code>.bbmc-allow</code> für den Freigabebutton. Beispiel: <code>.bbmc { --bbmc-accent: #113262; }</code></p><textarea id="bbmc_custom_css" name="' . self::OPTION . '" rows="18" class="large-text code" spellcheck="false" aria-describedby="bbmc-css-help">' . esc_textarea(self::sanitize(get_option(self::OPTION, ''))) . '</textarea>';
        echo '<p>Das CSS wird nach den Plugin-Styles geladen. Seitenbezogenes CSS folgt danach; bei gleicher CSS-Spezifität hat es Vorrang. Änderungen können einen geleerten Seiten-Cache erfordern.</p>';
        submit_button('CSS speichern');
        echo '</form></div>';
    }
    public static function meta_box() {
        if (current_user_can('edit_theme_options')) { add_meta_box('bbmc-page-css', 'BB Consent – Seiten-CSS', [__CLASS__, 'page_field'], 'page', 'normal', 'default'); }
    }
    public static function page_field($post) {
        wp_nonce_field('bbmc_page_css_' . $post->ID, 'bbmc_page_css_nonce');
        echo '<p><label for="bbmc_page_css">Zusätzliches CSS für die Content-Blocker nur auf dieser Seite. Leer lassen, um ausschließlich das Website-CSS zu verwenden.</label></p><textarea id="bbmc_page_css" name="bbmc_page_css" rows="10" class="widefat code" spellcheck="false">' . esc_textarea(self::sanitize(get_post_meta($post->ID, self::META, true))) . '</textarea><p>Beispiel: <code>.bbmc-placeholder { background: #f5f8fd; }</code></p>';
    }
    public static function save_page($post_id) {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) { return; }
        if (!current_user_can('edit_theme_options') || !current_user_can('edit_post', $post_id)) { return; }
        if (!isset($_POST['bbmc_page_css_nonce'], $_POST['bbmc_page_css']) || !is_string($_POST['bbmc_page_css_nonce']) || !is_string($_POST['bbmc_page_css'])) { return; }
        if (!wp_verify_nonce(wp_unslash($_POST['bbmc_page_css_nonce']), 'bbmc_page_css_' . $post_id)) { return; }
        $css = self::sanitize(wp_unslash($_POST['bbmc_page_css']));
        if ($css === '') { delete_post_meta($post_id, self::META); }
        else { update_post_meta($post_id, self::META, $css); }
    }
    public static function enqueue() {
        $css = self::sanitize(get_option(self::OPTION, ''));
        if (is_singular('page')) {
            $page_css = self::sanitize(get_post_meta(get_queried_object_id(), self::META, true));
            if ($page_css !== '') { $css .= "\n" . $page_css; }
        }
        if (trim($css) !== '') { wp_add_inline_style('bb-map-consent', $css); }
    }
}
BBMC_Custom_CSS::boot();
