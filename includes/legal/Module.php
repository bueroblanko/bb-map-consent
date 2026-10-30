<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
final class Module {
    private static $started = false;
    private static $owner = null;
    private static $admin = null;
    private static $active = false;
    private static $runtime = null;
    public static function choose(array $providers, $schema, $ready) {
        if ((int)$schema !== Store::SCHEMA) { return null; }
        $eligible = array_filter($providers, function ($p) use ($ready) {
            return ($p['schema'] ?? 0) === Store::SCHEMA && !empty($p['enabled']) && (!isset($p['ready']) || $p['ready']===true) && $ready;
        });
        usort($eligible, function ($a, $b) {
            $priority = (($b['id'] === 'basic') ? 1 : 0) <=> (($a['id'] === 'basic') ? 1 : 0);
            return $priority ?: version_compare($b['version'], $a['version']);
        });
        return $eligible ? reset($eligible) : null;
    }
    public static function boot(array $providers, $runtime = null) {
        if (self::$started) { return; } self::$started = true; self::$runtime = $runtime ?: reset($providers);
        self::$owner = self::choose($providers, Store::get('schema_version', 1), Store::ready());
        self::$admin = self::$owner;
        if(!self::$admin) { foreach($providers as $candidate) { if($candidate['id']==='consent') { self::$admin=$candidate;break; } } }
        self::$admin = self::$admin ?: reset($providers);
        add_action('admin_menu', function () { Admin::menu(); });
        add_action('admin_post_bb_legal_save', ['BB\\Legal\\V1\\Admin', 'save']);
        add_action('admin_notices', ['BB\\Legal\\V1\\Admin', 'notice']);
        add_filter('cron_schedules', function ($s) { $s['bb_legal_quarter_hour'] = ['interval'=>900,'display'=>'BB Legal: alle 15 Minuten']; return $s; });
        add_action('bb_legal_clear_replay', function ($sig) { delete_option('bb_legal_replay_' . $sig); });
        add_action('wp_loaded', [__CLASS__, 'activate'], 100);
        add_action('elementor/widgets/register', [__CLASS__, 'elementor']);
    }
    public static function owner() { return self::$owner; }
    /** URL und Version des laufenden Modul-Ordners (für Admin-CSS/JS). */
    public static function url() { return self::$runtime['url']; }
    public static function version() { return self::$runtime['version']; }
    public static function adminProvider() { return self::$admin; }
    public static function active() { return self::$active; }
    public static function conflicts() {
        global $shortcode_tags;
        foreach (['erecht24','erecht24_widget','impressum','datenschutz'] as $name) {
            if (isset($shortcode_tags[$name]) && $shortcode_tags[$name] !== [__CLASS__, 'shortcode']) { return true; }
        }
        foreach (['erecht24/erecht24','erecht24/legal-text'] as $name) {
            if (\WP_Block_Type_Registry::get_instance()->is_registered($name)) { $block=\WP_Block_Type_Registry::get_instance()->get_registered($name); if($block->render_callback!==[__CLASS__,'shortcode']) { return true; } }
        }
        return false;
    }
    public static function activate() {
        self::$active = false;
        if (!self::$owner || self::conflicts()) {
            if (self::$owner) { Store::status('foreign_provider'); }
            wp_clear_scheduled_hook('bb_legal_poll'); wp_clear_scheduled_hook('bb_legal_ack'); wp_clear_scheduled_hook('bb_legal_push_sync');
            return;
        }
        self::$active = true;
        foreach (['erecht24','erecht24_widget','impressum','datenschutz'] as $name) { add_shortcode($name, [__CLASS__, 'shortcode']); }
        wp_register_script('bb-legal-editor', self::$runtime['url'] . '/editor.js', ['wp-blocks','wp-element','wp-components','wp-block-editor','wp-server-side-render'], self::$owner['version'], true);
        foreach (['erecht24/erecht24','erecht24/legal-text'] as $name) {
            register_block_type($name, ['api_version'=>2,'title'=>'Rechtstext','category'=>'widgets','editor_script'=>'bb-legal-editor','attributes'=>[
                'type'=>['type'=>'string','default'=>'imprint'],'lang'=>['type'=>'string','default'=>'de'],'strip_title'=>['type'=>'boolean','default'=>false]
            ],'render_callback'=>[__CLASS__, 'shortcode']]);
        }
        add_action('rest_api_init', function () {
            register_rest_route('bb-legal/v1', '/push', ['methods'=>'POST','permission_callback'=>[__CLASS__, 'verifyPush'],'callback'=>[__CLASS__, 'push']]);
        });
        add_action('bb_legal_poll', [__CLASS__, 'poll']);
        add_action('bb_legal_push_sync', [__CLASS__, 'poll']);
        add_action('bb_legal_ack', [__CLASS__, 'ack']);
        if (!wp_next_scheduled('bb_legal_poll')) { wp_schedule_event(time()+900, 'bb_legal_quarter_hour', 'bb_legal_poll'); }
        $last = Store::get('owner', '');
        if ($last !== self::$owner['id']) { Store::put('owner', self::$owner['id']); }
    }
    public static function poll() { if (self::$active) { (new Client())->sync(); (new Client())->acknowledge(); } }
    public static function ack() { if (self::$active) { (new Client())->acknowledge(); } }
    public static function shortcode($attributes = [], $content = null, $tag = '') {
        if (!self::$active) { return ''; }
        $a = shortcode_atts(['type'=>'imprint','lang'=>'de','strip_title'=>false], is_array($attributes) ? $attributes : [], 'erecht24');
        if ($tag === 'datenschutz') { $a['type']='privacy_policy'; }
        if ($tag === 'impressum') { $a['type']='imprint'; }
        if ($a['type'] === 'privacy_policy_social_media') { $a['type']='social_media'; }
        if (!is_string($a['type']) || !is_string($a['lang']) || !in_array($a['type'], ['imprint','privacy_policy','social_media'], true) || !in_array($a['lang'], ['de','en'], true)) { return '<p>Der angeforderte Rechtstext ist nicht verfügbar.</p>'; }
        $cache = Store::get('cache');
        // Selbst gepflegt: vom Abgleich ausgeschlossen, der Text der Website gilt.
        $selbst = Store::get('selbst');
        if (is_array($selbst) && isset($selbst[$a['type'] . '/' . $a['lang']])) {
            $html = wp_kses_post($selbst[$a['type'] . '/' . $a['lang']]);
            if (!wp_validate_boolean($a['strip_title'])) { $html = '<h1>' . esc_html(Admin::TYPES[$a['type']]) . '</h1>' . $html; }
            return '<div class="bb-legal-text erecht24-legal-texts erecht24-legal-texts-' . esc_attr($a['type']) . '">' . $html . '</div>';
        }
        if (($cache['identity'] ?? '') !== Store::identity()) { return '<p>Der Rechtstext ist derzeit nicht verfügbar.</p>'; }
        foreach ($cache['outputs'] ?? [] as $row) {
            $p = $row['payload'];
            if ($p['type'] !== $a['type'] || $p['language'] !== $a['lang']) { continue; }
            // Ack means this exact version was rendered publicly, never merely fetched or previewed.
            if (!(defined('WP_CLI') && WP_CLI) && !is_admin() && !(defined('REST_REQUEST') && REST_REQUEST) && !is_preview() && !is_feed()) {
                $observed = Store::get('observed'); $observed[(int)$row['id']] = time(); Store::put('observed', $observed);
                $acked=Store::get('acknowledged');
                if (!in_array((int)$row['id'], $acked['ids'] ?? [], true) && !wp_next_scheduled('bb_legal_ack')) { wp_schedule_single_event(time()+10, 'bb_legal_ack'); }
            }
            $html = wp_kses_post($p['html']);
            if (!wp_validate_boolean($a['strip_title'])) { $html = '<h1>' . esc_html($p['title']) . '</h1>' . $html; }
            return '<div class="bb-legal-text erecht24-legal-texts erecht24-legal-texts-' . esc_attr($a['type']) . '">' . $html . '</div>';
        }
        return '<p>Für diesen Rechtstext liegt noch keine veröffentlichte Fassung vor.</p>';
    }
    public static function verifyPush($request) {
        $timestamp = $request->get_header('x-bb-timestamp'); $signature = $request->get_header('x-bb-signature');
        $site = $request->get_header('x-bb-site'); $body = $request->get_body();
        if (!self::$active || !is_string($timestamp) || !ctype_digit($timestamp) || abs(time()-(int)$timestamp)>300 || !preg_match('/^[a-f0-9]{64}$/', (string)$signature) || $site !== Store::get('site_id','') || strlen($body)>8192) { return new \WP_Error('bb_legal_unauthorized','Signatur ungültig.',['status'=>401]); }
        $key = Store::key(); $target = $_SERVER['REQUEST_URI'] ?? '';
        $expected = hash_hmac('sha256', "POST\n" . $target . "\n" . $timestamp . "\n" . $body, $key);
        $json = json_decode($body,true);
        if ($key === '' || !hash_equals($expected,$signature) || !is_array($json) || ($json['site_id'] ?? '') !== $site) { return new \WP_Error('bb_legal_unauthorized','Signatur ungültig.',['status'=>401]); }
        if (!add_option('bb_legal_replay_' . $signature,time(),'',false)) { return new \WP_Error('bb_legal_replay','Anfrage bereits verarbeitet.',['status'=>409]); }
        wp_schedule_single_event(time()+600,'bb_legal_clear_replay',[$signature]);
        return true;
    }
    public static function push($request) {
        // Sofort abrufen: Seiten mit wenig Besuch führen WP-Cron oft stundenlang nicht aus.
        try { self::poll(); } catch (\Throwable $e) {}
        if (!wp_next_scheduled('bb_legal_push_sync')) { wp_schedule_single_event(time()+1,'bb_legal_push_sync'); }
        return new \WP_REST_Response(['status'=>'queued'],202);
    }
    public static function elementor($manager) {
        if (!self::$owner || !class_exists('Elementor\\Widget_Base') || class_exists('ERecht24LegalText\\Frontend\\Shortcodes', false)) { return; }
        require_once __DIR__ . '/Elementor.php';
        foreach (['erecht24_legal_text','erecht24','erecht24_widget'] as $name) {
            if (!$manager->get_widget_types($name)) { $manager->register(new Elementor($name)); }
        }
    }
}
