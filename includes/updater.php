<?php
if (!defined('ABSPATH')) { exit; }
/** Public release assets only. Never place GitHub credentials on customer sites. */
final class BBMC_Updater {
    const REPO = 'bueroblanko/bb-map-consent';
    const URI = 'https://github.com/bueroblanko/bb-map-consent';
    const CACHE = 'bbmc_release_v1';
    public static function boot() {
        add_filter('upgrader_source_selection', [__CLASS__, 'preserve_directory'], 10, 4);
        add_filter('update_plugins_github.com', [__CLASS__, 'update'], 10, 4);
        add_filter('plugin_row_meta', [__CLASS__, 'links'], 10, 2);
        add_action('admin_post_bbmc_check_updates', [__CLASS__, 'check_now']);
        add_action('admin_notices', [__CLASS__, 'notice']);
        add_action('network_admin_notices', [__CLASS__, 'notice']);
    }
    public static function preserve_directory($source, $remote_source, $upgrader, $hook_extra) {
        $file = plugin_basename(dirname(__DIR__) . '/bb-map-consent.php');
        if (($hook_extra['plugin'] ?? '') !== $file || !is_string($source)) { return $source; }
        $directory = dirname($file);
        if ($directory === '.' || basename(rtrim($source, '/\\')) === $directory) { return $source; }
        global $wp_filesystem;
        if (!$wp_filesystem) { return new WP_Error('bbmc_filesystem', 'BB Consent: Dateisystem nicht verfügbar. Update abgebrochen.'); }
        $target = rtrim($remote_source, '/\\') . '/' . $directory . '/';
        if ($wp_filesystem->exists($target) || !$wp_filesystem->move($source, $target)) {
            return new WP_Error('bbmc_update_directory', 'BB Consent: Installationsordner konnte nicht erhalten werden. Update abgebrochen.');
        }
        return $target;
    }
    public static function links($links, $file) {
        if ($file === plugin_basename(dirname(__DIR__) . '/bb-map-consent.php') && current_user_can('update_plugins')) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=bbmc_check_updates'), 'bbmc_check_updates');
            $links[] = '<a href="' . esc_url($url) . '">Nach Updates suchen</a>';
        }
        return $links;
    }
    public static function check_now() {
        if (!current_user_can('update_plugins')) { wp_die('Keine Berechtigung.'); }
        check_admin_referer('bbmc_check_updates');
        delete_site_transient(self::CACHE);
        delete_site_transient('update_plugins');
        wp_update_plugins();
        $release = get_site_transient(self::CACHE);
        $state = !$release ? 'error' : (version_compare($release['version'], BBMC_VERSION, '>') ? 'available' : 'current');
        wp_safe_redirect(add_query_arg('bbmc_update_check', $state, self_admin_url('plugins.php')));
        exit;
    }
    public static function notice() {
        if (!current_user_can('update_plugins')) { return; }
        $state = isset($_GET['bbmc_update_check']) && is_string($_GET['bbmc_update_check']) ? $_GET['bbmc_update_check'] : '';
        $messages = [
            'error' => 'BB Consent: GitHub konnte nicht geprüft werden. Bitte später erneut versuchen.',
            'available' => 'BB Consent: Eine neue Version ist verfügbar. Sie können das Update jetzt in der Plugin-Liste installieren.',
            'current' => 'BB Consent: Die installierte Version ist aktuell.'
        ];
        if (isset($messages[$state])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($messages[$state]) . '</p></div>';
        }
    }
    public static function release() {
        $cached = get_site_transient(self::CACHE);
        if (is_array($cached)) { return $cached; }
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/releases/latest', [
            'timeout' => 10, 'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'BB-Map-Consent-Updater']
        ]);
        $data = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200
            ? json_decode(wp_remote_retrieve_body($response), true) : null;
        $result = self::parse_release($data);
        set_site_transient(self::CACHE, $result, $result ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
        return $result;
    }
    public static function parse_release($data) {
        if (!is_array($data) || !empty($data['draft']) || !empty($data['prerelease']) ||
            !preg_match('/^v?(\d+\.\d+\.\d+)$/', $data['tag_name'] ?? '', $m)) { return []; }
        $version = $m[1];
        $base = self::URI . '/releases/download/' . $data['tag_name'] . '/';
        $expected = [$base . 'bb-consent-' . $version . '.zip', $base . 'bb-map-consent-' . $version . '.zip'];
        foreach (($data['assets'] ?? []) as $asset) {
            if (($asset['state'] ?? '') === 'uploaded' && in_array(($asset['browser_download_url'] ?? ''), $expected, true)) {
                return ['id' => self::URI, 'slug' => 'bb-map-consent', 'version' => $version,
                    'url' => self::URI . '/releases/tag/' . $data['tag_name'], 'package' => $asset['browser_download_url']];
            }
        }
        return [];
    }
    public static function update($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== plugin_basename(dirname(__DIR__) . '/bb-map-consent.php') ||
            ($plugin_data['UpdateURI'] ?? '') !== self::URI) { return $update; }
        $release = self::release();
        return $release ?: $update;
    }
}
BBMC_Updater::boot();
