<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
/**
 * Beim Deaktivieren fragen, ob der letzte Stand der Rechtstexte fest in die Seiten geschrieben wird.
 * Ohne das Plugin zeigen Shortcodes sonst nur „[erecht24 …]“, Blöcke und Elementor-Widgets bleiben leer.
 */
final class Deactivate {
    const SHORTCODES = ['erecht24','erecht24_widget','impressum','datenschutz'];
    const WIDGETS = ['erecht24_legal_text','erecht24','erecht24_widget'];
    public static function boot() {
        add_filter('plugin_action_links', [__CLASS__, 'link'], 20, 2);
        add_action('admin_notices', [__CLASS__, 'notice']);
        add_action('admin_post_bb_legal_freeze', [__CLASS__, 'freeze']);
        add_action('admin_post_bb_legal_unfreeze', [__CLASS__, 'unfreeze']);
    }
    private static function plugin() { $p = Module::owner(); return is_array($p) ? (string)($p['plugin'] ?? '') : ''; }
    private static function allowed() { return Module::active() && self::plugin() !== '' && current_user_can('activate_plugins'); }
    private static function deactivateUrl($file) { return wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=' . urlencode($file)), 'deactivate-plugin_' . $file); }
    /** „Deaktivieren“ führt zuerst zur Abfrage, solange Seiten Rechtstexte aus dem Modul anzeigen. */
    public static function link($links, $file) {
        if (!isset($links['deactivate']) || $file !== self::plugin() || !self::allowed() || !self::usages()) { return $links; }
        $links['deactivate'] = '<a href="' . esc_url(admin_url('plugins.php?bb_legal_deactivate=1')) . '">Deaktivieren</a>';
        return $links;
    }
    /** Beiträge (alle Typen), deren Inhalt oder Elementor-Daten einen Rechtstext einbinden. */
    public static function usages() {
        global $wpdb;
        $like = [];
        foreach (['[erecht24', '[impressum', '[datenschutz', '<!-- wp:erecht24/'] as $needle) { $like[] = $wpdb->prepare('p.post_content LIKE %s', '%' . $wpdb->esc_like($needle) . '%'); }
        $live = "p.post_type <> 'revision' AND p.post_status NOT IN ('trash','auto-draft','inherit')";
        $ids = $wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p WHERE $live AND (" . implode(' OR ', $like) . ')');
        $elementor = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE $live AND m.meta_key='_elementor_data' AND m.meta_value LIKE %s", '%' . $wpdb->esc_like('"widgetType":"erecht24') . '%'));
        $all = array_values(array_unique(array_map('intval', array_merge($ids, $elementor))));
        sort($all);
        return $all;
    }
    /** Genau das HTML, das der Besucher gerade sieht; null, wenn kein gültiger Text vorliegt. */
    private static function render($attributes, $tag = '') {
        $html = Module::shortcode(is_array($attributes) ? $attributes : [], null, $tag);
        return strpos($html, 'class="bb-legal-text') !== false ? $html : null;
    }
    /** Ersetzt Shortcodes und Blöcke im Inhalt. $failed zählt Stellen ohne gültigen Text. */
    public static function staticContent($content, &$failed) {
        $pattern = get_shortcode_regex(self::SHORTCODES);
        $content = preg_replace_callback('/' . $pattern . '/', function ($m) use (&$failed) {
            if ($m[1] === '[' && $m[6] === ']') { return $m[0]; }
            $html = self::render(shortcode_parse_atts($m[3]), $m[2]);
            if ($html === null) { $failed++; return $m[0]; }
            return $m[1] . $html . $m[6];
        }, $content);
        return preg_replace_callback('~<!--\s+wp:erecht24/(?:erecht24|legal-text)(?:\s+(\{.*?\}))?\s+/-->~s', function ($m) use (&$failed) {
            $html = self::render(isset($m[1]) ? json_decode($m[1], true) : []);
            if ($html === null) { $failed++; return $m[0]; }
            return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
        }, $content);
    }
    /** Elementor: Rechtstext-Widgets werden zu normalen Text-Widgets mit festem Inhalt. */
    public static function staticElementor(array $elements, &$failed, &$changed) {
        foreach ($elements as $i => $element) {
            if (!is_array($element)) { continue; }
            if (($element['elType'] ?? '') === 'widget' && in_array($element['widgetType'] ?? '', self::WIDGETS, true)) {
                $html = self::render($element['settings'] ?? []);
                if ($html === null) { $failed++; continue; }
                $elements[$i] = ['id'=>$element['id'] ?? '', 'elType'=>'widget', 'widgetType'=>'text-editor', 'settings'=>['editor'=>$html], 'elements'=>[]];
                $changed = true;
                continue;
            }
            if (!empty($element['elements']) && is_array($element['elements'])) { $elements[$i]['elements'] = self::staticElementor($element['elements'], $failed, $changed); }
        }
        return $elements;
    }
    /** Alle Änderungen vorbereiten; gespeichert wird nur, wenn jede Stelle einen gültigen Text hat. */
    public static function plan() {
        $plan = []; $problems = [];
        foreach (self::usages() as $id) {
            $post = get_post($id); if (!$post) { continue; }
            $failed = 0; $entry = [];
            $content = self::staticContent($post->post_content, $failed);
            if ($content !== $post->post_content) { $entry['content'] = $content; }
            $raw = get_post_meta($id, '_elementor_data', true);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($data)) { $changed = false; $data = self::staticElementor($data, $failed, $changed); if ($changed) { $entry['elementor'] = $data; } }
            if ($failed) { $problems[] = $id; continue; }
            if ($entry) { $plan[$id] = $entry; }
        }
        return [$plan, $problems];
    }
    public static function apply(array $plan) {
        // Merkt sich Original und eingesetzten Stand, damit das Wiederaktivieren die Seiten zurückverbinden kann.
        $frozen = Store::get('frozen', []); if (!is_array($frozen)) { $frozen = []; }
        foreach ($plan as $id => $entry) {
            $post = get_post($id); if (!$post) { continue; }
            $memo = $frozen[$id] ?? [];
            // wp_update_post legt eine Revision an: Die Fassung mit Shortcode bleibt wiederherstellbar.
            if (isset($entry['content'])) {
                if (!isset($memo['content'])) { $memo['content'] = $post->post_content; }
                wp_update_post(wp_slash(['ID'=>$id, 'post_content'=>$entry['content']]));
                $memo['content_hash'] = md5(get_post($id)->post_content);
            }
            if (isset($entry['elementor'])) {
                if (!isset($memo['elementor'])) { $memo['elementor'] = (string)get_post_meta($id, '_elementor_data', true); }
                update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($entry['elementor']))); delete_post_meta($id, '_elementor_css');
                $memo['elementor_hash'] = md5((string)get_post_meta($id, '_elementor_data', true));
            }
            $frozen[$id] = $memo;
        }
        Store::put('frozen', $frozen);
        self::clearElementor($plan);
    }
    /** Fest eingesetzte Seiten wieder auf Shortcode, Block oder Widget stellen, nur wenn sie seitdem unverändert sind. */
    public static function restore() {
        $frozen = Store::get('frozen', []); $restored = []; $changed = [];
        foreach (is_array($frozen) ? $frozen : [] as $id => $memo) {
            $id = (int)$id; $post = get_post($id); if (!$post || $post->post_status === 'trash') { continue; }
            $same = (!isset($memo['content']) || md5($post->post_content) === ($memo['content_hash'] ?? ''))
                && (!isset($memo['elementor']) || md5((string)get_post_meta($id, '_elementor_data', true)) === ($memo['elementor_hash'] ?? ''));
            if (!$same) { $changed[] = $id; continue; }
            if (isset($memo['content'])) { wp_update_post(wp_slash(['ID'=>$id, 'post_content'=>$memo['content']])); }
            if (isset($memo['elementor'])) { update_post_meta($id, '_elementor_data', wp_slash($memo['elementor'])); delete_post_meta($id, '_elementor_css'); }
            $restored[] = $id;
        }
        delete_option('bb_legal_frozen');
        self::clearElementor($restored);
        return [$restored, $changed];
    }
    public static function unfreeze() {
        if (!Module::active() || !current_user_can('activate_plugins')) { wp_die('Keine Berechtigung.', 403); }
        check_admin_referer('bb_legal_unfreeze');
        if (!empty($_POST['keep'])) { delete_option('bb_legal_frozen'); $result = ['keep'=>true]; }
        else { list($restored, $changed) = self::restore(); $result = ['restored'=>$restored, 'changed'=>$changed]; }
        set_transient('bb_legal_unfreeze_result_' . get_current_user_id(), $result, 600);
        wp_safe_redirect(admin_url('plugins.php')); exit;
    }
    private static function clearElementor($changed) {
        if ($changed && class_exists('Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch (\Throwable $e) {}
        }
    }
    public static function freeze() {
        if (!self::allowed()) { wp_die('Keine Berechtigung.', 403); }
        check_admin_referer('bb_legal_freeze');
        $file = self::plugin();
        list($plan, $problems) = self::plan();
        if ($problems) {
            set_transient('bb_legal_freeze_problems_' . get_current_user_id(), $problems, 600);
            wp_safe_redirect(admin_url('plugins.php?bb_legal_deactivate=1&bb_legal_freeze=failed')); exit;
        }
        self::apply($plan);
        deactivate_plugins($file);
        wp_safe_redirect(admin_url('plugins.php?deactivate=true')); exit;
    }
    private static function titles(array $ids) {
        $out = [];
        foreach ($ids as $id) {
            $title = get_the_title($id);
            $out[] = '<a href="' . esc_url(get_edit_post_link($id) ?: get_permalink($id)) . '">' . esc_html($title !== '' ? $title : '#' . $id) . '</a>';
        }
        return implode(', ', $out);
    }
    public static function notice() {
        if (($GLOBALS['pagenow'] ?? '') !== 'plugins.php' || !Module::active() || !current_user_can('activate_plugins')) { return; }
        self::reconnectNotice();
        if (empty($_GET['bb_legal_deactivate']) || !self::allowed()) { return; }
        $file = self::plugin();
        if (!empty($_GET['bb_legal_freeze'])) {
            $problems = get_transient('bb_legal_freeze_problems_' . get_current_user_id());
            delete_transient('bb_legal_freeze_problems_' . get_current_user_id());
            echo '<div class="notice notice-error"><p><strong>Nichts geändert, das Plugin ist noch aktiv.</strong> Für diese Seiten liegt kein gültiger Rechtstext vor: ' . (is_array($problems) ? self::titles($problems) : '') . '. Bitte unter <a href="' . esc_url(admin_url('options-general.php?page=bb-legal')) . '">Rechtstexte</a> die Texte abrufen und erneut versuchen.</p></div>';
        }
        $ids = self::usages();
        if (!$ids) { return; }
        echo '<div class="notice notice-warning"><h2 style="margin:.6em 0 .4em">Rechtstexte vor dem Deaktivieren sichern?</h2>';
        echo '<p>Diese Seiten zeigen Rechtstexte aus BB Consent: ' . self::titles($ids) . '. Ohne das Plugin wären sie leer oder zeigen nur den Shortcode.</p>';
        echo '<p>Beim Sichern wird der aktuelle Stand als fester Text in die Seiten geschrieben. Er wird danach nicht mehr automatisch aktualisiert. Die bisherige Fassung bleibt in den Revisionen der Seite.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 1em">';
        echo '<input type="hidden" name="action" value="bb_legal_freeze">'; wp_nonce_field('bb_legal_freeze');
        echo '<button type="submit" class="button button-primary">Letzten Stand fest einsetzen und deaktivieren</button> ';
        echo '<a class="button" href="' . esc_url(self::deactivateUrl($file)) . '">Ohne Sichern deaktivieren</a> ';
        echo '<a class="button-link" href="' . esc_url(admin_url('plugins.php')) . '">Abbrechen</a>';
        echo '</form></div>';
    }
    /** Nach dem Wiederaktivieren: Ergebnis zeigen bzw. fragen, ob fest eingesetzte Seiten zurückverbunden werden. */
    private static function reconnectNotice() {
        $key = 'bb_legal_unfreeze_result_' . get_current_user_id();
        $result = get_transient($key);
        if (is_array($result)) {
            delete_transient($key);
            if (!empty($result['keep'])) { echo '<div class="notice notice-success"><p>Fester Text bleibt auf den Seiten. Sie werden nicht mehr aus der Zentrale aktualisiert.</p></div>'; }
            else {
                if (!empty($result['restored'])) { echo '<div class="notice notice-success"><p>Wieder mit der Zentrale verbunden: ' . self::titles($result['restored']) . '.</p></div>'; }
                if (!empty($result['changed'])) { echo '<div class="notice notice-error"><p>Nicht zurückgestellt, weil die Seite seit dem Fest-Einsetzen geändert wurde: ' . self::titles($result['changed']) . '. Den Rechtstext dort bitte per Shortcode oder über die Revision wieder einsetzen.</p></div>'; }
            }
        }
        $frozen = Store::get('frozen', []);
        if (!is_array($frozen) || !$frozen) { return; }
        echo '<div class="notice notice-warning"><h2 style="margin:.6em 0 .4em">Rechtstexte wieder mit der Zentrale verbinden?</h2>';
        echo '<p>Beim letzten Deaktivieren wurde auf diesen Seiten ein fester Text eingesetzt: ' . self::titles(array_map('intval', array_keys($frozen))) . '. Beim Verbinden kommen Shortcode, Block oder Widget zurück, danach gilt wieder der aktuelle Stand aus der Zentrale. Seiten, die seitdem geändert wurden, bleiben unverändert.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 1em">';
        echo '<input type="hidden" name="action" value="bb_legal_unfreeze">'; wp_nonce_field('bb_legal_unfreeze');
        echo '<button type="submit" class="button button-primary">Wieder verbinden</button> ';
        echo '<button type="submit" name="keep" value="1" class="button">Festen Text behalten</button>';
        echo '</form></div>';
    }
}
