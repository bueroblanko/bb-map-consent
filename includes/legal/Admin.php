<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
final class Admin {
    const TYPES = ['imprint'=>'Impressum','privacy_policy'=>'Datenschutzerklärung','social_media'=>'Social-Media-Hinweise','terms'=>'Allgemeine Geschäftsbedingungen','withdrawal'=>'Widerrufsbelehrung'];
    public static function menu() {
        $hook=add_options_page('Rechtstexte','Rechtstexte','manage_options','bb-legal',[__CLASS__,'screen']);
        add_action('admin_enqueue_scripts',function($current)use($hook){
            if($current!==$hook) { return; }
            // Version mit Dateistand, sonst haelt der Browser nach einem Update die alte Datei (ver blieb 1.0.0).
            $v=function($f){return Module::version().'.'.(int)@filemtime(__DIR__.'/'.$f);};
            wp_enqueue_style('bb-ui-admin',Module::url().'/admin.css',[],$v('admin.css'));
            wp_enqueue_script('bb-legal-form',Module::url().'/form.js',[],$v('form.js'),true);
        });
    }
    public static function notice() {
        if (!current_user_can('manage_options')) { return; }
        $p=Module::owner();
        if ($p && !Module::active()) { echo '<div class="notice notice-warning"><p>BB Legal ist vorbereitet, ein anderer Anbieter belegt noch die Rechtstext-Ausgabe. Bestehende Handler bleiben unverändert. <a href="'.esc_url(admin_url('options-general.php?page=bb-legal&tab=settings')).'">Status prüfen</a></p></div>'; }
        if ((int)Store::get('schema_version',1)!==Store::SCHEMA) { echo '<div class="notice notice-error"><p>Die gespeicherten Rechtstexte benötigen eine andere Modulversion. Keine automatische Rückmigration.</p></div>'; }
    }
    private static function form($action) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="bb_legal_save"><input type="hidden" name="operation" value="'.esc_attr($action).'">'; wp_nonce_field('bb_legal_'.$action);
    }
    /** Formular der Zentrale direkt hier (form.js), Endpunkt ist formAjax(). */
    private static function formular(array $types) {
        $types=array_values(array_filter($types,function($t){ return in_array($t,Client::FORM_TYPES,true)&&self::selbst($t.'/de')===null; }));
        if(!$types) { return; }
        $endpoint=add_query_arg(['action'=>'bb_legal_form','_wpnonce'=>wp_create_nonce('bb_legal_form')],admin_url('admin-ajax.php'));
        echo '<div style="margin:16px 0 24px" data-bb-legal-form="'.esc_attr(wp_json_encode(['endpunkt'=>$endpoint,'typen'=>$types])).'"><p>Formular wird geladen …</p></div>';
    }
    public static function formAjax() {
        if(!current_user_can('manage_options')) { wp_send_json(['fehler'=>'Keine Berechtigung.'],403); }
        if(!check_ajax_referer('bb_legal_form','_wpnonce',false)) { wp_send_json(['fehler'=>'Sitzung abgelaufen. Bitte die Seite neu laden.'],403); }
        $data=json_decode((string)file_get_contents('php://input'),true);
        $data=is_array($data)?$data:[];
        $type=is_string($data['typ']??null)?$data['typ']:'';
        if(!in_array($type,Client::FORM_TYPES,true)||self::selbst($type.'/de')!==null) { wp_send_json(['fehler'=>'Für diesen Text gibt es hier kein Formular.'],404); }
        try { wp_send_json((new Client())->form($type,$data)); }
        catch(\Throwable $e) { wp_send_json(['fehler'=>$e->getMessage()],409); }
    }
    private static function url($args=[]) { return add_query_arg(array_merge(['page'=>'bb-legal'],$args),admin_url('options-general.php')); }
    public static function screen() {
        if (!current_user_can('manage_options')) { return; }
        $messages=['saved'=>'Einstellungen gespeichert.','connected'=>'Verbunden. Texte und Angaben wurden aus der Zentrale abgerufen, Vorschau unter Rechtsinhalte.','connected_failed'=>'Verbindung gespeichert, aber der Abruf ist fehlgeschlagen. Code prüfen oder später „Texte und Angaben jetzt abrufen“.','connect_failed'=>'Verbinden hat nicht geklappt, der Link war abgelaufen oder schon benutzt. Bitte erneut auf „Mit Zentrale verbinden“ klicken.','connection_empty'=>'Nichts eingetragen. Am einfachsten oben auf „Mit Zentrale verbinden“ klicken.','code_invalid'=>'Der Verbindungscode ist unvollständig oder ungültig. Bitte in der Zentrale neu kopieren.','switch_blocked'=>'Diese Website ist schon mit einem anderen Projekt verbunden. Erst das Rechtstext-Modul ausschalten, dann neu verbinden.','synced'=>'Texte und Angaben aus der Zentrale abgerufen.','failed'=>'Abruf fehlgeschlagen. Die letzte gültige lokale Fassung bleibt erhalten.','enabled'=>'Modul vorbereitet. Bei einem Fremdanbieter erfolgt noch keine Übernahme.','disabled'=>'Rechtstext-Modul ausgeschaltet. Lokale Daten bleiben erhalten.','draft'=>'Angaben lokal als Entwurf gespeichert. Erneut senden, sobald die Verbindung funktioniert.','ok'=>'Angaben gespeichert und an die Zentrale gesendet.','merged'=>'Gespeichert. Parallele Änderungen wurden zusammengeführt.','conflict'=>'Gespeichert, aber einige Felder wurden parallel geändert. Bitte unten prüfen.','pending'=>'Änderung wartet in der Zentrale auf Freigabe.','invalid'=>'Angaben ungültig oder Formular veraltet. Es wurde nichts umgeschaltet.','segments_ok'=>'Gespeichert. Geänderte Bausteine werden ab jetzt nicht mehr automatisch aktualisiert, alle anderen schon.','segments_unchanged'=>'Keine Änderungen.','segments_draft'=>'Zentrale nicht erreichbar. Deine Änderungen sind lokal gespeichert und stehen beim nächsten Öffnen wieder im Editor.','selbst_an'=>'Der Text ist vom Abgleich ausgeschlossen und wird hier selbst gepflegt.','selbst_aus'=>'Der Text kommt wieder aus der Rechtstexte-Zentrale.','selbst_gespeichert'=>'Gespeichert. Der Text ist sofort auf der Website.'];
        $problem=['connect_failed'=>'error','connection_empty'=>'warning','failed'=>'error','invalid'=>'error','code_invalid'=>'error','switch_blocked'=>'error','connected_failed'=>'warning','draft'=>'warning','segments_draft'=>'warning','conflict'=>'warning','pending'=>'warning'];
        $tab=isset($_GET['tab'])&&$_GET['tab']==='settings'?'settings':'texts';
        $edit=isset($_GET['edit'])&&is_string($_GET['edit'])&&preg_match('~^(imprint|privacy_policy|social_media|terms|withdrawal)/(de|en)$~',wp_unslash($_GET['edit']))?wp_unslash($_GET['edit']):'';
        echo '<div class="wrap bb-ui"><h1>Rechtstexte</h1><nav class="nav-tab-wrapper">';
        foreach(['texts'=>'Rechtsinhalte','settings'=>'Verbindung'] as $key=>$name) { echo '<a class="nav-tab'.($tab===$key?' nav-tab-active':'').'" href="'.esc_url(self::url($key==='texts'?[]:['tab'=>$key])).'">'.$name.'</a>'; }
        echo '</nav>';
        $message=isset($_GET['bb_legal_result'])?sanitize_key(wp_unslash($_GET['bb_legal_result'])):'';
        if(isset($messages[$message])) { echo '<div class="notice notice-'.($problem[$message]??'success').'"><p>'.esc_html($messages[$message]).'</p></div>'; }
        if($tab==='settings') { self::settings(); }
        elseif($edit!=='') { self::editor($edit); }
        else { self::texts(); }
        echo '</div>';
    }
    /** Übersicht wie in bb-cms: Seite, Stand, Status, Bearbeiten | Ansehen. */
    private static function texts() {
        $cache=Store::get('cache');
        if(($cache['identity']??'')!==Store::identity()||empty($cache['outputs'])) { echo '<p>Noch keine Texte abgerufen. Unter <a href="'.esc_url(self::url(['tab'=>'settings'])).'">Verbindung</a> die Zentrale einrichten und Texte abrufen.</p>'; return; }
        echo '<p style="max-width:900px">Impressum und Datenschutzerklärung kommen aus Bausteinen der Rechtstexte-Zentrale und werden automatisch aktuell gehalten. Geändert wird alles hier im Formular, Schritt für Schritt mit Vorschau.</p>';
        self::formular(Client::FORM_TYPES);
        echo '<h2>Alle Texte</h2><table class="widefat striped" style="max-width:1100px"><thead><tr><th>Seite</th><th>Stand</th><th>Pflege</th></tr></thead><tbody>';
        foreach($cache['outputs'] as $row) {
            $v=$row['payload'];$key=$v['type'].'/'.$v['language'];
            $selbst=self::selbst($key)!==null;
            $info=$selbst?'Selbst gepflegt, kein Abgleich':'Aus der Zentrale, automatisch aktuell';
            echo '<tr><td><strong>'.esc_html((self::TYPES[$v['type']]??$v['title']).($v['language']!=='de'?' ('.strtoupper($v['language']).')':'')).'</strong><div class="row-actions visible"><a href="'.esc_url(self::url(['edit'=>$key])).'">'.($selbst?'Bearbeiten':'Selbst pflegen').'</a> | <a href="#bb-legal-view-'.esc_attr(str_replace('/','-',$key)).'">Ansehen</a></div></td><td>'.esc_html(wp_date('d.m.Y',strtotime($v['stand']))).' · Version '.(int)$row['id'].'</td><td>'.esc_html($info).'</td></tr>';
        }
        echo '</tbody></table>';
        foreach($cache['outputs'] as $row) {
            $v=$row['payload'];
            echo '<details id="bb-legal-view-'.esc_attr($v['type'].'-'.$v['language']).'" style="max-width:1100px;margin-top:16px"><summary><strong>'.esc_html($v['title']).'</strong> ansehen</summary><div style="background:#fff;border:1px solid #dcdcde;padding:16px 24px;margin-top:8px">'.wp_kses_post($v['html']).'</div></details>';
        }
    }
    /** Selbst gepflegter Text (vom Abgleich ausgeschlossen) oder null. */
    public static function selbst($key) { $s=Store::get('selbst'); return is_array($s)&&isset($s[$key])?(string)$s[$key]:null; }
    /** Text der Zentrale aus dem lokalen Stand, Ausgangspunkt fürs Selbstpflegen. */
    private static function ausgabe($key) {
        foreach(Store::get('cache')['outputs']??[] as $row) { if($row['payload']['type'].'/'.$row['payload']['language']===$key) { return (string)$row['payload']['html']; } }
        return '';
    }
    /** Bearbeiten-Seite eines Textes: Formular der Zentrale, oder offener Editor, wenn selbst gepflegt. */
    private static function editor($key) {
        [$type,$lang]=explode('/',$key);
        echo '<p><a href="'.esc_url(self::url()).'">&larr; Zurück zu den Rechtsinhalten</a></p><h2>'.esc_html(self::TYPES[$type].($lang!=='de'?' ('.strtoupper($lang).')':'')).' bearbeiten</h2>';
        $eigen=self::selbst($key);
        if($eigen!==null) {
            echo '<p style="max-width:900px">Selbst gepflegt: Dieser Text ist vom Abgleich mit der Rechtstexte-Zentrale ausgeschlossen. Was du hier speicherst, steht sofort auf der Website.</p>';
            self::form('selbst_text');echo '<input type="hidden" name="text_key" value="'.esc_attr($key).'"><div style="max-width:960px">';
            wp_editor($eigen,'bbs_'.md5($key),['textarea_name'=>'html','textarea_rows'=>20,'media_buttons'=>false]);
            echo '</div>';submit_button('Speichern');echo '</form>';
            self::form('selbst');echo '<input type="hidden" name="text_key" value="'.esc_attr($key).'"><input type="hidden" name="an" value="0">';
            submit_button('Wieder aus der Rechtstexte-Zentrale beziehen','secondary','submit',false);echo ' <span class="description">Der Text der Zentrale ersetzt den selbst gepflegten.</span></form>';
            return;
        }
        // Aus der Zentrale: geändert wird nur im Formular. Wer keinen Abgleich will, pflegt den Text selbst.
        echo '<p style="max-width:900px">Dieser Text kommt aus der Rechtstexte-Zentrale und bleibt automatisch aktuell, auch bei Gesetzesänderungen.'.(in_array($type,Client::FORM_TYPES,true)&&$lang==='de'?' Änderungen machst du hier im Formular.':' Änderungen macht die Agentur.').'</p>';
        if($lang==='de') { self::formular([$type]); }
        self::selbstKnopf($key);
    }
    private static function selbstKnopf($key) {
        echo '<hr style="max-width:960px;margin:24px 0">';self::form('selbst');echo '<input type="hidden" name="text_key" value="'.esc_attr($key).'"><input type="hidden" name="an" value="1">';
        submit_button('Vom Abgleich ausschließen und selbst pflegen','secondary','submit',false);echo ' <span class="description">Danach änderst du den ganzen Text frei. Die Zentrale aktualisiert ihn nicht mehr, auch nicht bei Gesetzesänderungen.</span></form>';
    }
    /** Verbindung: Status, Modul an/aus, Zugang zur Zentrale. */
    private static function settings() {
        $p=Module::adminProvider(); $id=$p['id']; $status=Store::get('status'); $owner=Module::owner();
        $labels=['synced'=>'Texte und Angaben abgerufen','outputs_only'=>'Texte abgerufen, Angaben fehlgeschlagen','sync_failed'=>'Abruf fehlgeschlagen','ack_failed'=>'Anzeige-Bestätigung fehlgeschlagen','draft_saved'=>'Ungesendeter lokaler Entwurf','foreign_provider'=>'Bisheriger Anbieter bleibt zuständig'];
        [$pill,$state]=Module::active()?['ok','Aktiv']:($owner?['warn','Wartet auf bisherigen Anbieter']:['off','Ausgeschaltet']);
        $ack=Store::get('acknowledged');
        echo '<section class="bb-card"><header><h2>Status</h2></header><div class="bb-card-body"><dl class="bb-badges">';
        echo '<div><dt>Ausgabe</dt><dd><span class="bb-pill '.$pill.'">'.esc_html($state).'</span></dd></div>';
        echo '<div><dt>Letzter Vorgang</dt><dd>'.(!empty($status['code'])?esc_html(($labels[$status['code']]??$status['code']).' · '.wp_date('d.m.Y H:i',$status['at'])):'Noch keiner').'</dd></div>';
        echo '<div><dt>Angezeigte Version</dt><dd>'.esc_html(implode(', ',$ack['ids']??[])?:'Noch keine').'</dd></div></dl>';
        if(!empty($status['message'])) { echo '<p class="bb-small"><strong>Grund:</strong> '.esc_html($status['message']).'</p>'; }
        if($owner && $owner['id']!==$id) { echo '<p class="bb-small">Verwaltung liegt bei '.esc_html($owner['id']).'.</p>'; }
        echo '</div><div class="bb-card-foot">';self::form('sync');echo '<input type="hidden" name="back" value="settings">';submit_button('Texte und Angaben jetzt abrufen','secondary','submit',false);echo '</form></div></section>';

        echo '<section class="bb-card"><header><h2>Rechtstext-Modul</h2><p>Erst einschalten, wenn die Texte abgerufen sind und die Vorschau unter Rechtsinhalte stimmt.</p></header>';
        self::form('toggle');echo '<div class="bb-card-body"><label class="bb-switch"><input type="checkbox" name="enabled" value="1" '.checked((bool)Store::get('enabled_'.$id,false),true,false).'><span><strong>Rechtstexte über '.esc_html($id==='consent'?'BB Consent':'BB Basic').' ausgeben</strong><small>Solange eRecht24 oder ein anderer Anbieter die Shortcodes belegt, bleibt dieser zuständig. Wird er deaktiviert, übernimmt das Modul beim nächsten Seitenaufruf. Bestehende Texte vorher sichern.</small></span></label></div>';
        echo '<div class="bb-card-foot">';submit_button('Speichern','primary','submit',false);echo '</div></form></section>';

        $site=Store::get('site_id','');$endpoint=Store::get('endpoint','https://legal.bbkd.de');
        $where=$site!==''?$endpoint.'/?view=site&id='.$site:$endpoint.'/?view=customers';
        echo '<section class="bb-card"><header><h2>Verbindung zur Zentrale</h2><p>'.($site!==''?'Verbunden mit Projekt <code>'.esc_html($site).'</code>.':'Noch nicht verbunden.').'</p></header>';
        self::form('connect_start');
        echo '<div class="bb-card-body"><p>Öffnet die Zentrale. Dort einmal bestätigen, danach geht es automatisch hierher zurück und die Texte werden abgerufen.</p></div><div class="bb-card-foot">';submit_button($site!==''?'Neu mit Zentrale verbinden':'Mit Zentrale verbinden',$site!==''?'secondary':'primary','submit',false);echo '</div></form>';
        echo '<details class="bb-card-body" style="border-top:1px solid #e5e7eb"><summary style="cursor:pointer">Andere Wege: Verbindungscode oder manuell</summary>';
        self::form('connection');
        echo '<div class="bb-grid" style="margin-top:12px">';
        echo '<div class="bb-f"><label for="bb-legal-code">Verbindungscode</label><textarea id="bb-legal-code" name="code" rows="2" spellcheck="false" autocomplete="off" style="font-family:monospace;word-break:break-all" placeholder="BBL1-…"></textarea><span class="bb-help">Aus der <a href="'.esc_url($where).'" target="_blank" rel="noopener">Zentrale</a>, bei der Website unter Anbindung. Oder stattdessen die drei Felder darunter.</span></div>';
        echo '<div class="bb-f w3"><label for="bb-legal-endpoint">Adresse der Zentrale</label><input type="url" id="bb-legal-endpoint" name="endpoint" value="'.esc_attr($endpoint).'"></div>';
        echo '<div class="bb-f w3"><label for="bb-legal-site">Projekt-ID</label><input type="text" id="bb-legal-site" name="site_id" value="'.esc_attr($site).'" pattern="[a-f0-9]{24}"></div>';
        echo '<div class="bb-f"><label for="bb-legal-key">Seitenschlüssel</label><input type="password" id="bb-legal-key" name="site_key" value="" autocomplete="new-password" placeholder="'.esc_attr($site!==''?'gespeichert, leer lassen zum Behalten':'').'"><span class="bb-help">Wird nicht angezeigt. Nur den Seitenschlüssel eintragen, niemals Admin- oder Freigabe-Token.</span></div>';
        echo '</div><p>';submit_button('Speichern','secondary','submit',false);echo '</p></form></details></section>';

        echo '<details class="bb-card"><summary class="bb-card-body" style="cursor:pointer"><strong>Technische Hinweise</strong></summary><div class="bb-card-body" style="padding-top:0"><p>Push-Adresse für das Projekt in der Zentrale: <code>'.esc_html(rest_url('bb-legal/v1/push')).'</code></p><p class="bb-small">Bei Seiten-Caches nach einer Veröffentlichung den Seiten- bzw. CDN-Cache leeren. Die Bestätigung beschreibt den lokal gerenderten Stand, nicht jede gespeicherte Cache-Kopie.</p></div></details>';
    }
    /** Skript- und Stilblöcke samt Inhalt entfernen (wp_kses_post ließe deren Text stehen), danach normale WordPress-Filterung. */
    public static function cleanText($html) {
        return wp_kses_post(preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>#is','',(string)$html));
    }
    /** Speichert eine Verbindung. Gibt 'saved' zurück oder wirft; bei anderem Projekt und aktivem Modul 'switch_blocked'. */
    private static function connectWith($endpoint,$site,$key) {
        if(!wp_http_validate_url($endpoint)||wp_parse_url($endpoint,PHP_URL_SCHEME)!=='https'||wp_parse_url($endpoint,PHP_URL_QUERY)||wp_parse_url($endpoint,PHP_URL_FRAGMENT)||!preg_match('/^[a-f0-9]{24}$/',$site)||($key!==''&&!preg_match('/^[a-f0-9]{64}$/',$key))) { throw new \RuntimeException('Ungültige Verbindung'); }
        $change=$site!==Store::get('site_id','')||$endpoint!==Store::get('endpoint','https://legal.bbkd.de');
        if($change&&(Module::owner()||Store::get('enabled_consent',false)||Store::get('enabled_basic',false))) { throw new \DomainException('switch_blocked'); }
        if($change&&$key==='') { throw new \RuntimeException('Neuer Seitenschlüssel erforderlich'); }
        if($key!=='') { Store::key($key); }
        if($change) { foreach(['remote_inputs','draft','segment_drafts','last_submission','observed','acknowledged'] as $name) { delete_option('bb_legal_'.$name); } }
        Store::put('endpoint',$endpoint);Store::put('site_id',$site);
        return 'saved';
    }
    /** Rückkehr aus der Zentrale nach „Mit Zentrale verbinden“: Einmal-Freigabe einlösen, verbinden, abrufen. */
    public static function connectReturn() {
        if(!isset($_GET['page'],$_GET['bb_legal_grant'],$_GET['bb_legal_state'])||$_GET['page']!=='bb-legal'||!current_user_can('manage_options')) { return; }
        $grant=is_string($_GET['bb_legal_grant'])?wp_unslash($_GET['bb_legal_grant']):'';$state=is_string($_GET['bb_legal_state'])?wp_unslash($_GET['bb_legal_state']):'';
        $pending=get_transient('bb_legal_connect_'.get_current_user_id());delete_transient('bb_legal_connect_'.get_current_user_id());
        $result='connect_failed';
        try {
            if(!is_array($pending)||!is_string($pending['state']??null)||!hash_equals($pending['state'],$state)||!preg_match('/^[a-f0-9]{64}$/',$grant)) { throw new \RuntimeException('Rückkehr ungültig'); }
            $response=wp_safe_remote_post($pending['endpoint'].'/api/v1/connect',['timeout'=>15,'redirection'=>0,'headers'=>['Content-Type'=>'application/json'],'body'=>wp_json_encode(['grant'=>$grant])]);
            if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200) { throw new \RuntimeException('Freigabe abgelehnt'); }
            $data=json_decode(wp_remote_retrieve_body($response),true);
            if(!is_array($data)||!is_string($data['site_id']??null)||!is_string($data['api_key']??null)) { throw new \RuntimeException('Antwort ungültig'); }
            self::connectWith($pending['endpoint'],$data['site_id'],$data['api_key']);
            $result=(new Client())->sync()?'connected':'connected_failed';
        } catch(\DomainException $e) { $result='switch_blocked'; } catch(\Throwable $e) { $result='connect_failed'; }
        wp_safe_redirect(self::url(['tab'=>'settings','bb_legal_result'=>$result]));exit;
    }
    /** Verbindungscode aus der Zentrale: „BBL1-“ + base64url von {"u":Adresse,"s":Website-ID,"k":Seitenschlüssel}. Gibt [Adresse, ID, Schlüssel] oder null zurück. */
    public static function parseCode($code) {
        $code=preg_replace('/\s+/','',(string)$code);
        if(!preg_match('/^BBL1-([A-Za-z0-9_-]+)$/',$code,$m)) { return null; }
        $raw=base64_decode(strtr($m[1],'-_','+/'),true);
        $data=$raw===false?null:json_decode($raw,true);
        if(!is_array($data)||!is_string($data['u']??null)||!is_string($data['s']??null)||!is_string($data['k']??null)) { return null; }
        $endpoint=rtrim($data['u'],'/');
        if(!preg_match('/^[a-f0-9]{24}$/',$data['s'])||!preg_match('/^[a-f0-9]{64}$/',$data['k'])||wp_parse_url($endpoint,PHP_URL_SCHEME)!=='https') { return null; }
        return [$endpoint,$data['s'],$data['k']];
    }
    public static function save() {
        if(!current_user_can('manage_options')) { wp_die('Keine Berechtigung.',403); }
        $operation=isset($_POST['operation'])&&is_string($_POST['operation'])?sanitize_key(wp_unslash($_POST['operation'])):'';
        check_admin_referer('bb_legal_'.$operation);
        $result='invalid';$provider=Module::adminProvider();$args=['tab'=>'settings'];
        try {
            if($operation==='connection') {
                $code=isset($_POST['code'])&&is_string($_POST['code'])?trim(wp_unslash($_POST['code'])):'';
                if($code!=='') {
                    $parsed=self::parseCode($code);
                    if(!$parsed) { $result='code_invalid'; throw new \RuntimeException('Ungültiger Verbindungscode'); }
                    [$endpoint,$site,$key]=$parsed;
                } else {
                    $endpoint=isset($_POST['endpoint'])&&is_string($_POST['endpoint'])?rtrim(trim(wp_unslash($_POST['endpoint'])),'/'):'';
                    $site=isset($_POST['site_id'])&&is_string($_POST['site_id'])?trim(wp_unslash($_POST['site_id'])):'';
                    $key=isset($_POST['site_key'])&&is_string($_POST['site_key'])?trim(wp_unslash($_POST['site_key'])):'';
                }
                if($code===''&&$site===''&&$key==='') { $result='connection_empty'; throw new \RuntimeException('Nichts eingetragen'); }
                try { $result=self::connectWith($endpoint,$site,$key); } catch(\DomainException $e) { $result='switch_blocked'; throw $e; }
                if($code!==''&&$result==='saved') { $result=(new Client())->sync()?'connected':'connected_failed'; }
            } elseif($operation==='connect_start') {
                $endpoint=rtrim(Store::get('endpoint','https://legal.bbkd.de'),'/');
                if(wp_parse_url($endpoint,PHP_URL_SCHEME)!=='https') { $endpoint='https://legal.bbkd.de'; }
                $state=wp_generate_password(32,false,false);
                set_transient('bb_legal_connect_'.get_current_user_id(),['state'=>$state,'endpoint'=>$endpoint],900);
                wp_redirect($endpoint.'/?'.http_build_query(['view'=>'connect','domain'=>wp_parse_url(home_url(),PHP_URL_HOST),'return'=>self::url(['tab'=>'settings']),'state'=>$state]));exit;
            } elseif($operation==='sync') { $result=(new Client())->sync()?'synced':'failed';if(($_POST['back']??'')==='inputs') { $args=['tab'=>'inputs']; } }
            elseif($operation==='toggle') {
                $enabled=isset($_POST['enabled'])&&$_POST['enabled']==='1';
                if($enabled&&!Store::ready()) { throw new \RuntimeException('Zuerst vollständige Texte abrufen'); }
                Store::put('enabled_'.$provider['id'],$enabled);$result=$enabled?'enabled':'disabled';
            } elseif($operation==='selbst'||$operation==='selbst_text') {
                $key=isset($_POST['text_key'])&&is_string($_POST['text_key'])?wp_unslash($_POST['text_key']):'';
                if(!preg_match('~^(imprint|privacy_policy|social_media|terms|withdrawal)/(de|en)$~',$key)) { throw new \RuntimeException('Unbekannter Text'); }
                $selbst=Store::get('selbst');$selbst=is_array($selbst)?$selbst:[];$args=['edit'=>$key];
                if($operation==='selbst_text') {
                    if(!isset($selbst[$key])||!isset($_POST['html'])||!is_string($_POST['html'])) { throw new \RuntimeException('Nicht selbst gepflegt'); }
                    $selbst[$key]=self::cleanText(wp_unslash($_POST['html']));$result='selbst_gespeichert';
                } elseif(($_POST['an']??'')==='1') { $selbst[$key]=$selbst[$key]??self::ausgabe($key);$result='selbst_an'; }
                else { unset($selbst[$key]);$result='selbst_aus'; }
                Store::put('selbst',$selbst);
            }
        } catch(\Throwable $e) { if(!in_array($result,['code_invalid','switch_blocked','connection_empty'],true)) { $result='invalid'; } }
        wp_safe_redirect(self::url(array_merge($args,['bb_legal_result'=>$result])));exit;
    }
}
