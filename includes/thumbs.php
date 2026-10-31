<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Optional YouTube preview images behind the placeholder. The server fetches each image once
 * and serves it from the uploads folder, so visitors never contact YouTube before consenting.
 */
final class BBMC_Thumbs {
    const OPTION = 'bbmc_yt_thumbs';
    const DIR = 'bb-consent/yt';
    /** Fetches per request, so a page with many new videos does not stall the first visit. */
    const BUDGET = 12;
    private static $fetched = 0;
    public static function boot() {
        add_action('admin_init', function () {
            register_setting('bbmc_design', self::OPTION, ['type' => 'boolean', 'default' => false, 'sanitize_callback' => function ($v) { return (bool) $v; }]);
        });
    }
    public static function video_id($src) {
        $path = (string) wp_parse_url(strpos($src, '//') === 0 ? 'https:' . $src : $src, PHP_URL_PATH);
        return preg_match('~^/embed/([A-Za-z0-9_-]{6,20})(?:/|$)~', $path, $m) ? $m[1] : '';
    }
    /** Local URL of the preview image, or '' when disabled or not available. */
    public static function url($src) {
        if (!function_exists('get_option') || !get_option(self::OPTION, false)) { return ''; }
        $id = self::video_id($src);
        $uploads = wp_upload_dir(null, false);
        if ($id === '' || !empty($uploads['error'])) { return ''; }
        $file = $uploads['basedir'] . '/' . self::DIR . '/' . $id . '.jpg';
        $url = $uploads['baseurl'] . '/' . self::DIR . '/' . $id . '.jpg';
        if (is_file($file)) { return $url; }
        if (self::$fetched >= self::BUDGET || get_transient('bbmc_yt_fail_' . $id)) { return ''; }
        self::$fetched++;
        $response = wp_safe_remote_get('https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg', ['timeout' => 3, 'redirection' => 0, 'limit_response_size' => 1048576]);
        $body = is_wp_error($response) ? '' : (string) wp_remote_retrieve_body($response);
        $ok = wp_remote_retrieve_response_code($response) === 200 && strncmp($body, "\xFF\xD8\xFF", 3) === 0;
        if (!$ok || !wp_mkdir_p(dirname($file)) || file_put_contents($file, $body) === false) {
            set_transient('bbmc_yt_fail_' . $id, 1, DAY_IN_SECONDS);
            return '';
        }
        return $url;
    }
    public static function field() {
        echo '<section class="bb-card"><header><h2>Vorschaubilder</h2><p>YouTube-Platzhalter zeigen das Vorschaubild des Videos. Der Server lädt jedes Bild einmal von YouTube und liefert es danach selbst aus. Besucher haben vor ihrer Zustimmung keinen Kontakt zu YouTube.</p></header><div class="bb-card-body">';
        echo '<label class="bb-switch"><input type="checkbox" name="' . self::OPTION . '" value="1"' . checked((bool) get_option(self::OPTION, false), true, false) . '><span><strong>Vorschaubilder für YouTube anzeigen</strong><small>Danach den Seiten-Cache leeren.</small></span></label>';
        echo '</div></section>';
    }
}
BBMC_Thumbs::boot();
