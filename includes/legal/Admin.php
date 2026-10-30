<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
final class Admin {
    public static function menu() { add_options_page('Rechtstexte','Rechtstexte','manage_options','bb-legal',[__CLASS__,'screen']); }
    public static function notice() {
        if (!current_user_can('manage_options')) { return; }
        $p=Module::owner();
        if ($p && !Module::active()) { echo '<div class="notice notice-warning"><p>BB Legal ist vorbereitet, ein anderer Anbieter belegt noch die Rechtstext-Ausgabe. Bestehende Handler bleiben unverändert. <a href="'.esc_url(admin_url('options-general.php?page=bb-legal')).'">Status prüfen</a></p></div>'; }
        if ((int)Store::get('schema_version',1)!==Store::SCHEMA) { echo '<div class="notice notice-error"><p>Die gespeicherten Rechtstexte benötigen eine andere Modulversion. Keine automatische Rückmigration.</p></div>'; }
    }
    private static function form($action) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="bb_legal_save"><input type="hidden" name="operation" value="'.esc_attr($action).'">'; wp_nonce_field('bb_legal_'.$action);
    }
    public static function screen() {
        if (!current_user_can('manage_options')) { return; }
        $p=Module::adminProvider(); $id=$p['id']; $cache=Store::get('cache'); $status=Store::get('status'); $owner=Module::owner();
        $messages=['saved'=>'Einstellungen gespeichert.','synced'=>'Veröffentlichte Texte lokal gespeichert; noch keine Anzeige-Bestätigung gesendet.','failed'=>'Abruf fehlgeschlagen. Die letzte gültige lokale Fassung bleibt erhalten.','enabled'=>'Modul vorbereitet. Bei einem Fremdanbieter erfolgt noch keine Übernahme.','disabled'=>'Rechtstext-Modul ausgeschaltet. Lokale Daten bleiben erhalten.','draft'=>'Eingaben lokal als Entwurf gespeichert. Erneut senden, sobald die Verbindung funktioniert.','ok'=>'Eingaben synchronisiert.','merged'=>'Parallele Änderungen wurden zusammengeführt.','conflict'=>'Eingaben synchronisiert; Feldkonflikte siehe unten.','pending'=>'Änderung wartet in der Zentrale auf Freigabe.','invalid'=>'Angaben ungültig oder Formular veraltet. Es wurde nichts umgeschaltet.','text_ok'=>'Text gespeichert. Er bekommt ab jetzt keine automatischen Aktualisierungen mehr.','text_draft'=>'Zentrale nicht erreichbar. Der Text ist lokal als Entwurf gespeichert und wird beim nächsten Speichern erneut gesendet.'];
        echo '<div class="wrap"><h1>Rechtstexte</h1><p>Verbindung zu legal.bbkd.de. Dieses Modul ist unabhängig vom Content-Blocker und standardmäßig ausgeschaltet.</p>';
        $message=isset($_GET['bb_legal_result'])?sanitize_key(wp_unslash($_GET['bb_legal_result'])):'';
        if(isset($messages[$message])) { echo '<div class="notice notice-info"><p>'.esc_html($messages[$message]).'</p></div>'; }
        echo '<h2>Status</h2><p>Ausgabe: <strong>'.esc_html(Module::active()?'Aktiv über '.$owner['id']:($owner?'Wartet auf Freigabe der Shortcodes durch den bisherigen Anbieter':'Ausgeschaltet / nicht bereit')).'</strong></p>';
        if($owner && $owner['id']!==$id) { echo '<p>Verwaltung liegt bei '.esc_html($owner['id']).'.</p>'; }
        $labels=['synced'=>'Texte und Eingaben synchronisiert','outputs_only'=>'Texte synchronisiert; Fragebogenabruf fehlgeschlagen','sync_failed'=>'Synchronisation fehlgeschlagen','ack_failed'=>'Anzeige-Bestätigung fehlgeschlagen','draft_saved'=>'Ungesendeter lokaler Entwurf','foreign_provider'=>'Bisheriger Anbieter bleibt zuständig'];
        if(!empty($status['code'])) { echo '<p>Letzter Vorgang: '.esc_html($labels[$status['code']]??$status['code']).' · '.esc_html(wp_date('d.m.Y H:i',$status['at'])).'</p>'; }
        echo '<p>Push-URL für das Projekt in der Zentrale: <code>'.esc_html(rest_url('bb-legal/v1/push')).'</code></p>';
        echo '<h2>Verbindung</h2>'; self::form('connection');
        echo '<table class="form-table"><tr><th><label for="bb-legal-endpoint">Zentrale</label></th><td><input class="regular-text" type="url" id="bb-legal-endpoint" name="endpoint" value="'.esc_attr(Store::get('endpoint','https://legal.bbkd.de')).'" required></td></tr><tr><th><label for="bb-legal-site">Projekt-ID</label></th><td><input class="regular-text" id="bb-legal-site" name="site_id" value="'.esc_attr(Store::get('site_id','')).'" pattern="[a-f0-9]{24}" required></td></tr><tr><th><label for="bb-legal-key">Seitenschlüssel</label></th><td><input class="regular-text" type="password" id="bb-legal-key" name="site_key" value="" autocomplete="new-password"><p class="description">Leer lassen, um den gespeicherten Schlüssel zu behalten. Er wird nicht angezeigt. Nur den Seitenschlüssel eintragen, niemals Admin- oder Freigabe-Token.</p></td></tr></table>';
        submit_button('Verbindung speichern'); echo '</form>';
        self::form('sync');submit_button('Texte und Eingaben abrufen','secondary');echo '</form>';
        self::form('toggle');echo '<label><input type="checkbox" name="enabled" value="1" '.checked((bool)Store::get('enabled_'.$id,false),true,false).'> Rechtstext-Modul in '.esc_html($id==='consent'?'BB Consent':'BB Basic').' aktivieren</label><p class="description">Erst nach Abruf und Vergleich der Vorschau aktivieren. Solange eRecht24 oder ein anderer Anbieter die Handler belegt, bleibt dieser zuständig. Beim kontrollierten Deaktivieren des bisherigen Anbieters übernimmt das vorbereitete Modul beim nächsten Seitenaufruf. Bestehende Texte zuvor sichern.</p>';submit_button('Aktivierung speichern');echo '</form>';
        echo '<h2>Lokale Vorschau</h2><p>Die Vorschau bestätigt noch keine öffentliche Anzeige.</p>';
        if(($cache['identity']??'')===Store::identity()) foreach($cache['outputs']??[] as $row) { self::textEditor($row); }
        $ack=Store::get('acknowledged');echo '<p>Zuletzt nach öffentlichem Rendern bestätigt: '.esc_html(implode(', ',$ack['ids']??[])?:'Noch keine Version').'</p>';
        echo '<p>Bei Seiten-Caches: Nach einer Veröffentlichung den Seiten-/CDN-Cache leeren. Die Bestätigung beschreibt den lokal gerenderten Stand, nicht jede bereits gespeicherte Cache-Kopie.</p>';
        self::inputs();echo '</div>';
    }
    /** Vorschau eines Textes und freie Bearbeitung mit dem WordPress-Editor. */
    private static function textEditor($row) {
        $v=$row['payload'];$key=$v['type'].'/'.$v['language'];
        $drafts=Store::get('text_drafts');$draft=(is_array($drafts)&&isset($drafts[$key])&&($drafts[$key]['identity']??'')===Store::identity())?$drafts[$key]:null;
        $custom=is_array($v['block_versions']??null)&&array_key_exists('individual',$v['block_versions']);
        echo '<details><summary><strong>'.esc_html($v['title']).'</strong> · '.esc_html(strtoupper($v['language']).' · Version '.$row['id']).($custom?' · <em>frei bearbeitet</em>':'').'</summary>';
        echo '<div style="max-width:900px;margin:10px 0 24px">';
        if($custom) { echo '<p>Dieser Text wurde frei bearbeitet und bekommt keine automatischen Aktualisierungen. Zurückstellen kann Büro Blanko in der Zentrale.</p>'; }
        if($draft) { echo '<div class="notice notice-warning inline"><p>Ungesendeter Entwurf vom '.esc_html(wp_date('d.m.Y H:i',$draft['at'])).'. Er steht unten im Editor und wird mit „Text speichern“ erneut gesendet.</p></div>'; }
        self::form('text');
        echo '<input type="hidden" name="text_type" value="'.esc_attr($v['type']).'"><input type="hidden" name="text_lang" value="'.esc_attr($v['language']).'"><input type="hidden" name="base_output_id" value="'.esc_attr((string)(int)$row['id']).'">';
        echo '<p><label>Titel<br><input class="regular-text" name="text_title" value="'.esc_attr($draft['title']??$v['title']).'" required maxlength="200"></label></p>';
        wp_editor($draft['html']??$v['html'],'bb_legal_text_'.preg_replace('/[^a-z]/','_',$key),['textarea_name'=>'text_html','textarea_rows'=>18,'media_buttons'=>false,'teeny'=>false,'quicktags'=>true]);
        echo '<p><label><input type="checkbox" name="text_confirm" value="1" required> Mir ist klar: Nach dem Speichern bekommt dieser Text keine automatischen Aktualisierungen mehr (z. B. bei Gesetzesänderungen).</label></p>';
        submit_button('Text speichern','primary','submit',false);echo '</form></div></details>';
    }
    private static function inputs() {
        $remote=Store::get('remote_inputs');if(($remote['identity']??'')!==Store::identity() || !isset($remote['schema']['payload']['fields'])) { return; }
        $draft=Store::get('draft'); $state=$remote;
        if(!empty($draft) && ($draft['identity']??'')===Store::identity()) { $state['inputs']=$draft['inputs'];$state['version']=$draft['base_version_id'];echo '<p><strong>Ungesendeter Entwurf:</strong> Die folgenden Werte stammen aus deinem lokalen Entwurf.</p>'; }
        $formToken=wp_generate_password(32,false,false);
        set_transient('bb_legal_form_'.$formToken,['inputs'=>$state['inputs'],'version'=>$state['version'],'schema'=>$remote['schema']['payload'],'identity'=>Store::identity(),'user'=>get_current_user_id()],HOUR_IN_SECONDS);
        echo '<h2>Eingaben</h2>';self::form('inputs');echo '<input type="hidden" name="form_token" value="'.esc_attr($formToken).'">';
        foreach($remote['schema']['payload']['fields'] as $field) { self::field($field,$state['inputs']); }
        $last=Store::get('last_submission');
        if(!empty($last['conflicts'])) { echo '<p>Konfliktfelder: '.esc_html(implode(', ',$last['conflicts'])).'</p>'; }
        if(!empty($last['render_errors'])) { echo '<p>Die Zentrale meldet Renderfehler. Bis zur Korrektur bleibt die bisherige Ausgabe veröffentlicht.</p>'; }
        submit_button('Eingaben synchronisieren');echo '</form>';
    }
    private static function field($f,$values) {
        $id=$f['id'];$v=$values[$id]??'';$label=is_array($f['label']??null)?($f['label']['de']??$id):($f['label']??$id);
        echo '<p><label><strong>'.esc_html($label).'</strong><br>';
        if(!empty($f['localized'])) {
            foreach(['de','en'] as $lang) { echo esc_html(strtoupper($lang)).' <input class="regular-text" name="fields['.esc_attr($id).']['.$lang.']" value="'.esc_attr(is_array($v)?($v[$lang]??''):$v).'"><br>'; }
        } elseif($f['type']==='bool') { echo '<input type="checkbox" name="fields['.esc_attr($id).']" value="1" '.checked((bool)$v,true,false).'>'; }
        elseif(in_array($f['type'],['select','multiselect'],true)) {
            $multi=$f['type']==='multiselect';echo '<select name="fields['.esc_attr($id).']'.($multi?'[]':'').'" '.($multi?'multiple size="6"':'').'>';
            if(!$multi) { echo '<option value="">Bitte wählen</option>'; }
            foreach($f['options']??[] as $key=>$label) { echo '<option value="'.esc_attr($key).'" '.selected($multi?(is_array($v)&&in_array($key,$v,true)):($v===$key),true,false).'>'.esc_html($label).'</option>'; }echo '</select>';
        } elseif(in_array($f['type'],['list','textarea'],true)) {
            echo '<textarea class="large-text" rows="5" name="fields['.esc_attr($id).']">'.esc_textarea($f['type']==='list'?wp_json_encode(is_array($v)?$v:[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE):$v).'</textarea>';
            if($f['type']==='list') { echo '<small>JSON-Liste; stabile id je Eintrag beibehalten. Felder: '.esc_html(implode(', ',array_column($f['fields']??[],'id'))).'</small>'; }
        } else { echo '<input class="regular-text" type="'.esc_attr(in_array($f['type'],['email','url'],true)?$f['type']:'text').'" name="fields['.esc_attr($id).']" value="'.esc_attr($v).'">'; }
        echo '</label></p>';
    }
    public static function parseFields($schema,$posted,$original) {
        if(!is_array($posted)) { throw new \RuntimeException('Ungültige Eingaben'); }
        $result=$original;
        foreach($schema['fields']??[] as $f) {
            $id=$f['id'];$v=$posted[$id]??null;
            if(!empty($f['localized'])) {
                if(!is_array($v)) { throw new \RuntimeException('Sprachwerte fehlen'); }
                $result[$id]=[];foreach(['de','en'] as $lang) { if(isset($v[$lang])&&!is_string($v[$lang])) { throw new \RuntimeException('Ungültiger Sprachwert'); }$result[$id][$lang]=$v[$lang]??''; }
            } elseif($f['type']==='bool') { $result[$id]=$v==='1'; }
            elseif($f['type']==='list') { if(!is_string($v)) { throw new \RuntimeException('Liste fehlt'); }$list=json_decode($v,true);if(!is_array($list)||array_values($list)!==$list) { throw new \RuntimeException('Ungültige Liste'); }$result[$id]=$list; }
            elseif($f['type']==='multiselect') { if($v!==null&&!is_array($v)) { throw new \RuntimeException('Ungültige Auswahl'); }$result[$id]=array_values($v??[]); }
            else { if($v!==null&&!is_string($v)) { throw new \RuntimeException('Text erwartet'); }$result[$id]=$v??''; }
        }
        return $result;
    }
    public static function save() {
        if(!current_user_can('manage_options')) { wp_die('Keine Berechtigung.',403); }
        $operation=isset($_POST['operation'])&&is_string($_POST['operation'])?sanitize_key(wp_unslash($_POST['operation'])):'';
        check_admin_referer('bb_legal_'.$operation);
        $result='invalid';$provider=Module::adminProvider();
        try {
            if($operation==='connection') {
                $endpoint=isset($_POST['endpoint'])&&is_string($_POST['endpoint'])?rtrim(trim(wp_unslash($_POST['endpoint'])),'/'):'';
                $site=isset($_POST['site_id'])&&is_string($_POST['site_id'])?trim(wp_unslash($_POST['site_id'])):'';
                $key=isset($_POST['site_key'])&&is_string($_POST['site_key'])?trim(wp_unslash($_POST['site_key'])):'';
                if(!wp_http_validate_url($endpoint)||wp_parse_url($endpoint,PHP_URL_SCHEME)!=='https'||wp_parse_url($endpoint,PHP_URL_QUERY)||wp_parse_url($endpoint,PHP_URL_FRAGMENT)||!preg_match('/^[a-f0-9]{24}$/',$site)||($key!==''&&!preg_match('/^[a-f0-9]{64}$/',$key))) { throw new \RuntimeException('Ungültige Verbindung'); }
                $change=$site!==Store::get('site_id','')||$endpoint!==Store::get('endpoint','https://legal.bbkd.de');
                if($change&&(Module::owner()||Store::get('enabled_consent',false)||Store::get('enabled_basic',false))) { throw new \RuntimeException('Vor Projektwechsel Modul ausschalten'); }
                if($change&&$key==='') { throw new \RuntimeException('Neuer Seitenschlüssel erforderlich'); }
                if($key!=='') { Store::key($key); }
                if($change) { foreach(['remote_inputs','draft','text_drafts','last_submission','observed','acknowledged'] as $name) { delete_option('bb_legal_'.$name); } }
                Store::put('endpoint',$endpoint);Store::put('site_id',$site);$result='saved';
            } elseif($operation==='sync') { $result=(new Client())->sync()?'synced':'failed'; }
            elseif($operation==='toggle') {
                $enabled=isset($_POST['enabled'])&&$_POST['enabled']==='1';
                if($enabled&&!Store::ready()) { throw new \RuntimeException('Zuerst vollständige Texte abrufen'); }
                Store::put('enabled_'.$provider['id'],$enabled);$result=$enabled?'enabled':'disabled';
            } elseif($operation==='text') {
                $type=isset($_POST['text_type'])&&is_string($_POST['text_type'])?sanitize_key(wp_unslash($_POST['text_type'])):'';
                $lang=isset($_POST['text_lang'])&&is_string($_POST['text_lang'])?sanitize_key(wp_unslash($_POST['text_lang'])):'';
                $title=isset($_POST['text_title'])&&is_string($_POST['text_title'])?sanitize_text_field(wp_unslash($_POST['text_title'])):'';
                $html=isset($_POST['text_html'])&&is_string($_POST['text_html'])?wp_kses_post(wp_unslash($_POST['text_html'])):'';
                $base=isset($_POST['base_output_id'])?(int)$_POST['base_output_id']:0;
                if(($_POST['text_confirm']??'')!=='1'||$title===''||trim(wp_strip_all_tags($html))==='') { throw new \RuntimeException('Angaben unvollständig'); }
                $result=(new Client())->editText($type,$lang,$title,$html,$base?:null);
            } elseif($operation==='inputs') {
                $token=isset($_POST['form_token'])&&is_string($_POST['form_token'])?wp_unslash($_POST['form_token']):'';
                if(!preg_match('/^[A-Za-z0-9]{32}$/',$token)) { throw new \RuntimeException('Formular veraltet'); }
                $state=get_transient('bb_legal_form_'.$token);
                if(!$state||$state['user']!==get_current_user_id()||$state['identity']!==Store::identity()) { throw new \RuntimeException('Formular veraltet'); }
                $values=self::parseFields($state['schema'],wp_unslash($_POST['fields']??[]),$state['inputs']);
                $result=(new Client())->submit($values,$state['version']);delete_transient('bb_legal_form_'.$token);
            }
        } catch(\Throwable $e) { $result='invalid'; }
        wp_safe_redirect(add_query_arg(['page'=>'bb-legal','bb_legal_result'=>$result],admin_url('options-general.php')));exit;
    }
}
