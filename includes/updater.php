<?php
if (!defined('ABSPATH')) { exit; }
/** Public release assets only. Never place GitHub credentials on customer sites. */
final class BBMC_Updater {
    const REPO = 'bueroblanko/bb-map-consent';
    const URI = 'https://github.com/bueroblanko/bb-map-consent';
    const CACHE = 'bbmc_release_v1';
    public static function boot() {
        add_filter('update_plugins_github.com', [__CLASS__, 'update'], 10, 4);
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
        $expected = self::URI . '/releases/download/' . $data['tag_name'] . '/bb-map-consent-' . $version . '.zip';
        foreach (($data['assets'] ?? []) as $asset) {
            if (($asset['state'] ?? '') === 'uploaded' && ($asset['browser_download_url'] ?? '') === $expected) {
                return ['id' => self::URI, 'slug' => 'bb-map-consent', 'version' => $version,
                    'url' => self::URI . '/releases/tag/' . $data['tag_name'], 'package' => $expected];
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
