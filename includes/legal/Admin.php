<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
final class Admin {
    const TYPES = ['imprint'=>'Impressum','privacy_policy'=>'Datenschutzerklärung','social_media'=>'Social-Media-Hinweise'];
    public static function menu() { add_options_page('Rechtstexte','Rechtstexte','manage_options','bb-legal',[__CLASS__,'screen']); }
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
    private static function url($args=[]) { return add_query_arg(array_merge(['page'=>'bb-legal'],$args),admin_url('options-general.php')); }
    public static function screen() {
        if (!current_user_can('manage_options')) { return; }
        $messages=['saved'=>'Einstellungen gespeichert.','synced'=>'Veröffentlichte Texte lokal gespeichert; noch keine Anzeige-Bestätigung gesendet.','failed'=>'Abruf fehlgeschlagen. Die letzte gültige lokale Fassung bleibt erhalten.','enabled'=>'Modul vorbereitet. Bei einem Fremdanbieter erfolgt noch keine Übernahme.','disabled'=>'Rechtstext-Modul ausgeschaltet. Lokale Daten bleiben erhalten.','draft'=>'Eingaben lokal als Entwurf gespeichert. Erneut senden, sobald die Verbindung funktioniert.','ok'=>'Eingaben synchronisiert.','merged'=>'Parallele Änderungen wurden zusammengeführt.','conflict'=>'Eingaben synchronisiert; Feldkonflikte siehe unten.','pending'=>'Änderung wartet in der Zentrale auf Freigabe.','invalid'=>'Angaben ungültig oder Formular veraltet. Es wurde nichts umgeschaltet.','segments_ok'=>'Gespeichert. Geänderte Bausteine werden ab jetzt nicht mehr automatisch aktualisiert, alle anderen schon.','segments_unchanged'=>'Keine Änderungen.','segments_draft'=>'Zentrale nicht erreichbar. Deine Änderungen sind lokal gespeichert und stehen beim nächsten Öffnen wieder im Editor.'];
        $tab=isset($_GET['tab'])&&$_GET['tab']==='settings'?'settings':'texts';
        $edit=isset($_GET['edit'])&&is_string($_GET['edit'])&&preg_match('~^(imprint|privacy_policy|social_media)/(de|en)$~',wp_unslash($_GET['edit']))?wp_unslash($_GET['edit']):'';
        echo '<div class="wrap"><h1>Rechtstexte</h1>';
        echo '<nav class="nav-tab-wrapper"><a class="nav-tab'.($tab==='texts'?' nav-tab-active':'').'" href="'.esc_url(self::url()).'">Rechtsinhalte</a><a class="nav-tab'.($tab==='settings'?' nav-tab-active':'').'" href="'.esc_url(self::url(['tab'=>'settings'])).'">Einstellungen</a></nav>';
        $message=isset($_GET['bb_legal_result'])?sanitize_key(wp_unslash($_GET['bb_legal_result'])):'';
        if(isset($messages[$message])) { echo '<div class="notice notice-info"><p>'.esc_html($messages[$message]).'</p></div>'; }
        if($tab==='settings') { self::settings(); }
        elseif($edit!=='') { self::editor($edit); }
        else { self::texts(); }
        echo '</div>';
    }
    /** Übersicht wie in bb-cms: Seite, Stand, Status, Bearbeiten | Ansehen. */
    private static function texts() {
        $cache=Store::get('cache');
        echo '<p>Impressum und Datenschutzerklärung kommen aus Bausteinen der Rechtstexte-Zentrale und werden automatisch aktuell gehalten. Du kannst einzelne Bausteine anpassen oder eigenen Text ergänzen. Angepasste Bausteine werden danach nicht mehr automatisch aktualisiert.</p>';
        if(($cache['identity']??'')!==Store::identity()||empty($cache['outputs'])) { echo '<p>Noch keine Texte abgerufen. Unter <a href="'.esc_url(self::url(['tab'=>'settings'])).'">Einstellungen</a> die Verbindung einrichten und Texte abrufen.</p>'; return; }
        echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Seite</th><th>Stand</th><th>Anpassungen</th></tr></thead><tbody>';
        foreach($cache['outputs'] as $row) {
            $v=$row['payload'];$key=$v['type'].'/'.$v['language'];
            $custom=count($v['customized_blocks']??[]);$inserts=count($v['inserts']??[]);
            $info=$custom||$inserts?trim(($custom?$custom.' Baustein'.($custom===1?'':'e').' angepasst':'').($custom&&$inserts?', ':'').($inserts?$inserts.' eigene'.($inserts===1?'r':'').' Text'.($inserts===1?'':'e'):'')):'Keine';
            echo '<tr><td><strong>'.esc_html((self::TYPES[$v['type']]??$v['title']).($v['language']!=='de'?' ('.strtoupper($v['language']).')':'')).'</strong><div class="row-actions visible"><a href="'.esc_url(self::url(['edit'=>$key])).'">Bearbeiten</a> | <a href="#bb-legal-view-'.esc_attr(str_replace('/','-',$key)).'">Ansehen</a></div></td><td>'.esc_html(wp_date('d.m.Y',strtotime($v['stand']))).' · Version '.(int)$row['id'].'</td><td>'.esc_html($info).'</td></tr>';
        }
        echo '</tbody></table>';
        foreach($cache['outputs'] as $row) {
            $v=$row['payload'];
            echo '<details id="bb-legal-view-'.esc_attr($v['type'].'-'.$v['language']).'" style="max-width:1100px;margin-top:16px"><summary><strong>'.esc_html($v['title']).'</strong> ansehen</summary><div style="background:#fff;border:1px solid #dcdcde;padding:16px 24px;margin-top:8px">'.wp_kses_post($v['html']).'</div></details>';
        }
    }
    /** Ein Text als Folge von Bausteinen: jeder Baustein editierbar, dazwischen eigener Text. */
    private static function editor($key) {
        [$type,$lang]=explode('/',$key);
        echo '<p><a href="'.esc_url(self::url()).'">&larr; Zurück zu den Rechtsinhalten</a></p><h2>'.esc_html(self::TYPES[$type].($lang!=='de'?' ('.strtoupper($lang).')':'')).' bearbeiten</h2>';
        try { $segments=(new Client())->segments($type,$lang); }
        catch(\Throwable $e) { echo '<div class="notice notice-error"><p>Die Bausteine konnten nicht aus der Zentrale geladen werden. Bitte später erneut versuchen.</p></div>'; return; }
        $drafts=Store::get('segment_drafts');$draft=(is_array($drafts)&&isset($drafts[$key])&&($drafts[$key]['identity']??'')===Store::identity())?$drafts[$key]:null;
        if($draft) { echo '<div class="notice notice-warning inline"><p>Ungesendeter Entwurf vom '.esc_html(wp_date('d.m.Y H:i',$draft['at'])).' ist unten eingesetzt. Mit „Speichern“ erneut senden.</p></div>'; }
        $inserts=[];foreach($segments as $s) { if(($s['kind']??'')==='insert') { $inserts[$s['key']]=$s['html']; } }
        if($draft) { $inserts=array_merge($inserts,$draft['inserts']); }
        echo '<p style="max-width:900px">Ändere einen Baustein nur, wenn du ihn wirklich anders haben willst: Ab dann wird genau dieser Baustein nicht mehr automatisch aktualisiert (z. B. bei Gesetzesänderungen). Unveränderte Bausteine bleiben aktuell. Eigenen Text kannst du am Anfang, nach jedem Baustein und am Ende einfügen.</p>';
        self::form('segments');
        echo '<input type="hidden" name="text_key" value="'.esc_attr($key).'"><div style="max-width:960px">';
        self::insertField('_start','Eigener Text am Anfang',$inserts['_start']??'');
        foreach($segments as $s) {
            if(($s['kind']??'')!=='block') { continue; }
            $html=$draft['blocks'][$s['key']]??$s['html'];
            preg_match('~<h[1-6][^>]*>(.*?)</h[1-6]>~is',$s['html'],$m);$label=$m?wp_strip_all_tags($m[1]):preg_replace('~/(de|en)$~','',$s['key']);
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid '.(!empty($s['customized'])?'#8c5cf5':'#2271b1').';padding:12px 16px;margin:14px 0">';
            echo '<p style="margin:0 0 8px;display:flex;gap:10px;align-items:center"><strong>'.esc_html($label).'</strong>'.(!empty($s['customized'])?'<span style="background:#f0e9ff;color:#5b2bb5;border-radius:10px;padding:1px 8px;font-size:12px">angepasst – wird nicht mehr aktualisiert</span>':'<span style="color:#646970;font-size:12px">aktuell aus der Zentrale</span>').'</p>';
            wp_editor($html,'bbl_'.md5($s['key']),['textarea_name'=>'blocks['.$s['key'].']','textarea_rows'=>8,'media_buttons'=>false,'teeny'=>true]);
            if(!empty($s['customized'])) { echo '<p style="margin:8px 0 0"><label><input type="checkbox" name="reset[]" value="'.esc_attr($s['key']).'"> Auf den Standardtext zurücksetzen (wird dann wieder automatisch aktualisiert)</label></p>'; }
            echo '</div>';
            self::insertField($s['key'],'Eigenen Text nach „'.$label.'“ einfügen',$inserts[$s['key']]??'');
        }
        self::insertField('_end','Eigener Text am Ende',$inserts['_end']??'');
        echo '</div>';submit_button('Speichern');echo '</form>';
    }
    private static function insertField($key,$label,$html) {
        echo '<details style="margin:6px 0 6px 16px"'.($html!==''?' open':'').'><summary style="cursor:pointer;color:#2271b1">+ '.esc_html($label).'</summary><div style="margin-top:6px">';
        wp_editor($html,'bbi_'.md5($key),['textarea_name'=>'inserts['.$key.']','textarea_rows'=>4,'media_buttons'=>false,'tinymce'=>false,'quicktags'=>true]);
        echo '<p class="description">Leer lassen oder leeren, um nichts einzufügen.</p></div></details>';
    }
    private static function settings() {
        $p=Module::adminProvider(); $id=$p['id']; $status=Store::get('status'); $owner=Module::owner();
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
        $ack=Store::get('acknowledged');echo '<p>Zuletzt nach öffentlichem Rendern bestätigt: '.esc_html(implode(', ',$ack['ids']??[])?:'Noch keine Version').'</p>';
        echo '<p>Bei Seiten-Caches: Nach einer Veröffentlichung den Seiten-/CDN-Cache leeren. Die Bestätigung beschreibt den lokal gerenderten Stand, nicht jede bereits gespeicherte Cache-Kopie.</p>';
        self::inputs();
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
    /** Skript- und Stilblöcke samt Inhalt entfernen (wp_kses_post ließe deren Text stehen), danach normale WordPress-Filterung. */
    public static function cleanText($html) {
        return wp_kses_post(preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>#is','',(string)$html));
    }
    /** Formularwerte des Baustein-Editors: nur gültige Schlüssel, HTML gefiltert. */
    public static function parseSegments($blocks,$inserts,$reset) {
        $validKey=function($k){return is_string($k)&&preg_match('~^[a-z0-9_]+/(de|en)$~',$k);};
        $outBlocks=[];foreach((array)$blocks as $k=>$html) { if($validKey($k)&&is_string($html)) { $outBlocks[$k]=self::cleanText($html); } }
        $outInserts=[];foreach((array)$inserts as $k=>$html) { if(($k==='_start'||$k==='_end'||$validKey($k))&&is_string($html)) { $outInserts[$k]=self::cleanText($html); } }
        $outReset=array_values(array_filter((array)$reset,$validKey));
        foreach($outReset as $k) { unset($outBlocks[$k]); }
        return [$outBlocks,$outInserts,$outReset];
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
        $result='invalid';$provider=Module::adminProvider();$args=['tab'=>'settings'];
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
                if($change) { foreach(['remote_inputs','draft','segment_drafts','last_submission','observed','acknowledged'] as $name) { delete_option('bb_legal_'.$name); } }
                Store::put('endpoint',$endpoint);Store::put('site_id',$site);$result='saved';
            } elseif($operation==='sync') { $result=(new Client())->sync()?'synced':'failed'; }
            elseif($operation==='toggle') {
                $enabled=isset($_POST['enabled'])&&$_POST['enabled']==='1';
                if($enabled&&!Store::ready()) { throw new \RuntimeException('Zuerst vollständige Texte abrufen'); }
                Store::put('enabled_'.$provider['id'],$enabled);$result=$enabled?'enabled':'disabled';
            } elseif($operation==='segments') {
                $args=[];
                $key=isset($_POST['text_key'])&&is_string($_POST['text_key'])?wp_unslash($_POST['text_key']):'';
                if(!preg_match('~^(imprint|privacy_policy|social_media)/(de|en)$~',$key)) { throw new \RuntimeException('Unbekannter Text'); }
                [$type,$lang]=explode('/',$key);
                [$blocks,$inserts,$reset]=self::parseSegments(wp_unslash($_POST['blocks']??[]),wp_unslash($_POST['inserts']??[]),wp_unslash($_POST['reset']??[]));
                $result=(new Client())->saveSegments($type,$lang,$blocks,$inserts,$reset);
                if($result==='segments_draft') { $args=['edit'=>$key]; }
            } elseif($operation==='inputs') {
                $token=isset($_POST['form_token'])&&is_string($_POST['form_token'])?wp_unslash($_POST['form_token']):'';
                if(!preg_match('/^[A-Za-z0-9]{32}$/',$token)) { throw new \RuntimeException('Formular veraltet'); }
                $state=get_transient('bb_legal_form_'.$token);
                if(!$state||$state['user']!==get_current_user_id()||$state['identity']!==Store::identity()) { throw new \RuntimeException('Formular veraltet'); }
                $values=self::parseFields($state['schema'],wp_unslash($_POST['fields']??[]),$state['inputs']);
                $result=(new Client())->submit($values,$state['version']);delete_transient('bb_legal_form_'.$token);
            }
        } catch(\Throwable $e) { $result='invalid'; }
        wp_safe_redirect(self::url(array_merge($args,['bb_legal_result'=>$result])));exit;
    }
}
