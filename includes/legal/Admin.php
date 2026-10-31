<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
final class Admin {
    const TYPES = ['imprint'=>'Impressum','privacy_policy'=>'Datenschutzerklärung','social_media'=>'Social-Media-Hinweise','terms'=>'Allgemeine Geschäftsbedingungen','withdrawal'=>'Widerrufsbelehrung'];
    public static function menu() {
        $hook=add_options_page('Rechtstexte','Rechtstexte','manage_options','bb-legal',[__CLASS__,'screen']);
        add_action('admin_enqueue_scripts',function($current)use($hook){
            if($current!==$hook) { return; }
            wp_enqueue_style('bb-ui-admin',Module::url().'/admin.css',[],Module::version());
            wp_enqueue_script('bb-legal-admin',Module::url().'/admin.js',[],Module::version(),true);
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
    private static function url($args=[]) { return add_query_arg(array_merge(['page'=>'bb-legal'],$args),admin_url('options-general.php')); }
    public static function screen() {
        if (!current_user_can('manage_options')) { return; }
        $messages=['saved'=>'Einstellungen gespeichert.','connected'=>'Verbunden. Texte und Angaben wurden aus der Zentrale abgerufen, Vorschau unter Rechtsinhalte.','connected_failed'=>'Verbindung gespeichert, aber der Abruf ist fehlgeschlagen. Code prüfen oder später „Texte und Angaben jetzt abrufen“.','connect_failed'=>'Verbinden hat nicht geklappt, der Link war abgelaufen oder schon benutzt. Bitte erneut auf „Mit Zentrale verbinden“ klicken.','connection_empty'=>'Nichts eingetragen. Am einfachsten oben auf „Mit Zentrale verbinden“ klicken.','code_invalid'=>'Der Verbindungscode ist unvollständig oder ungültig. Bitte in der Zentrale neu kopieren.','switch_blocked'=>'Diese Website ist schon mit einem anderen Projekt verbunden. Erst das Rechtstext-Modul ausschalten, dann neu verbinden.','synced'=>'Texte und Angaben aus der Zentrale abgerufen.','failed'=>'Abruf fehlgeschlagen. Die letzte gültige lokale Fassung bleibt erhalten.','enabled'=>'Modul vorbereitet. Bei einem Fremdanbieter erfolgt noch keine Übernahme.','disabled'=>'Rechtstext-Modul ausgeschaltet. Lokale Daten bleiben erhalten.','draft'=>'Angaben lokal als Entwurf gespeichert. Erneut senden, sobald die Verbindung funktioniert.','ok'=>'Angaben gespeichert und an die Zentrale gesendet.','merged'=>'Gespeichert. Parallele Änderungen wurden zusammengeführt.','conflict'=>'Gespeichert, aber einige Felder wurden parallel geändert. Bitte unten prüfen.','pending'=>'Änderung wartet in der Zentrale auf Freigabe.','invalid'=>'Angaben ungültig oder Formular veraltet. Es wurde nichts umgeschaltet.','segments_ok'=>'Gespeichert. Geänderte Bausteine werden ab jetzt nicht mehr automatisch aktualisiert, alle anderen schon.','segments_unchanged'=>'Keine Änderungen.','segments_draft'=>'Zentrale nicht erreichbar. Deine Änderungen sind lokal gespeichert und stehen beim nächsten Öffnen wieder im Editor.','selbst_an'=>'Der Text ist vom Abgleich ausgeschlossen und wird hier selbst gepflegt.','selbst_aus'=>'Der Text kommt wieder aus der Rechtstexte-Zentrale.','selbst_gespeichert'=>'Gespeichert. Der Text ist sofort auf der Website.'];
        $problem=['connect_failed'=>'error','connection_empty'=>'warning','failed'=>'error','invalid'=>'error','code_invalid'=>'error','switch_blocked'=>'error','connected_failed'=>'warning','draft'=>'warning','segments_draft'=>'warning','conflict'=>'warning','pending'=>'warning'];
        $tab=isset($_GET['tab'])&&in_array($_GET['tab'],['settings','inputs'],true)?$_GET['tab']:'texts';
        $edit=isset($_GET['edit'])&&is_string($_GET['edit'])&&preg_match('~^(imprint|privacy_policy|social_media|terms|withdrawal)/(de|en)$~',wp_unslash($_GET['edit']))?wp_unslash($_GET['edit']):'';
        echo '<div class="wrap bb-ui"><h1>Rechtstexte</h1><nav class="nav-tab-wrapper">';
        foreach(['texts'=>'Rechtsinhalte','inputs'=>'Angaben','settings'=>'Verbindung'] as $key=>$name) { echo '<a class="nav-tab'.($tab===$key?' nav-tab-active':'').'" href="'.esc_url(self::url($key==='texts'?[]:['tab'=>$key])).'">'.$name.'</a>'; }
        echo '</nav>';
        $message=isset($_GET['bb_legal_result'])?sanitize_key(wp_unslash($_GET['bb_legal_result'])):'';
        if(isset($messages[$message])) { echo '<div class="notice notice-'.($problem[$message]??'success').'"><p>'.esc_html($messages[$message]).'</p></div>'; }
        if($tab==='settings') { self::settings(); }
        elseif($tab==='inputs') { self::inputs(); }
        elseif($edit!=='') { self::editor($edit); }
        else { self::texts(); }
        echo '</div>';
    }
    /** Übersicht wie in bb-cms: Seite, Stand, Status, Bearbeiten | Ansehen. */
    private static function texts() {
        $cache=Store::get('cache');
        echo '<p>Impressum und Datenschutzerklärung kommen aus Bausteinen der Rechtstexte-Zentrale und werden automatisch aktuell gehalten. Du kannst einzelne Bausteine anpassen oder eigenen Text ergänzen. Angepasste Bausteine werden danach nicht mehr automatisch aktualisiert.</p>';
        if(($cache['identity']??'')!==Store::identity()||empty($cache['outputs'])) { echo '<p>Noch keine Texte abgerufen. Unter <a href="'.esc_url(self::url(['tab'=>'settings'])).'">Verbindung</a> die Zentrale einrichten und Texte abrufen.</p>'; return; }
        echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Seite</th><th>Stand</th><th>Anpassungen</th></tr></thead><tbody>';
        foreach($cache['outputs'] as $row) {
            $v=$row['payload'];$key=$v['type'].'/'.$v['language'];
            $custom=count($v['customized_blocks']??[]);$inserts=count($v['inserts']??[]);
            $info=self::selbst($key)!==null?'Selbst gepflegt, kein Abgleich':($custom||$inserts?trim(($custom?$custom.' Baustein'.($custom===1?'':'e').' angepasst':'').($custom&&$inserts?', ':'').($inserts?$inserts.' eigene'.($inserts===1?'r':'').' Text'.($inserts===1?'':'e'):'')):'Keine');
            echo '<tr><td><strong>'.esc_html((self::TYPES[$v['type']]??$v['title']).($v['language']!=='de'?' ('.strtoupper($v['language']).')':'')).'</strong><div class="row-actions visible"><a href="'.esc_url(self::url(['edit'=>$key])).'">Bearbeiten</a> | <a href="#bb-legal-view-'.esc_attr(str_replace('/','-',$key)).'">Ansehen</a></div></td><td>'.esc_html(wp_date('d.m.Y',strtotime($v['stand']))).' · Version '.(int)$row['id'].'</td><td>'.esc_html($info).'</td></tr>';
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
    /** Ein Text als Folge von Bausteinen: jeder Baustein editierbar, dazwischen eigener Text. */
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
        try { $segments=(new Client())->segments($type,$lang); }
        catch(\Throwable $e) { echo '<div class="notice notice-error"><p>Die Bausteine konnten nicht aus der Zentrale geladen werden. Bitte später erneut versuchen.</p></div>'; self::selbstKnopf($key); return; }
        $drafts=Store::get('segment_drafts');$draft=(is_array($drafts)&&isset($drafts[$key])&&($drafts[$key]['identity']??'')===Store::identity())?$drafts[$key]:null;
        if($draft) { echo '<div class="notice notice-warning inline"><p>Ungesendeter Entwurf vom '.esc_html(wp_date('d.m.Y H:i',$draft['at'])).' ist unten eingesetzt. Mit „Speichern“ erneut senden.</p></div>'; }
        $inserts=[];foreach($segments as $s) { if(($s['kind']??'')==='insert') { $inserts[$s['key']]=$s['html']; } }
        if($draft) { $inserts=array_merge($inserts,$draft['inserts']); }
        echo '<p style="max-width:900px">Ändere einen Baustein nur, wenn du ihn wirklich anders haben willst: Ab dann wird genau dieser Baustein nicht mehr automatisch aktualisiert (z. B. bei Gesetzesänderungen). Unveränderte Bausteine bleiben aktuell. Eigenen Text kannst du am Anfang, nach jedem Baustein und am Ende einfügen.</p>';
        self::form('segments');
        echo '<input type="hidden" name="text_key" value="'.esc_attr($key).'"><div style="max-width:960px">';
        self::insertField('_start','Eigener Text am Anfang',$inserts['_start']??'');
        foreach($segments as $s) {
            if(($s['kind']??'')==='fixed') {
                // Fester Text ohne Bausteine (z. B. Impressum): nur anzeigen, Ergänzungen davor und danach.
                echo '<div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;padding:12px 16px;margin:14px 0"><p style="margin:0 0 8px"><strong>Text der Zentrale</strong> <span style="color:#646970;font-size:12px">fester Text, Änderungen in der Zentrale</span></p>'.wp_kses_post($s['html']).'</div>';
                continue;
            }
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
        self::selbstKnopf($key);
    }
    private static function selbstKnopf($key) {
        echo '<hr style="max-width:960px;margin:24px 0">';self::form('selbst');echo '<input type="hidden" name="text_key" value="'.esc_attr($key).'"><input type="hidden" name="an" value="1">';
        submit_button('Vom Abgleich ausschließen und selbst pflegen','secondary','submit',false);echo ' <span class="description">Danach änderst du den ganzen Text frei. Die Zentrale aktualisiert ihn nicht mehr, auch nicht bei Gesetzesänderungen.</span></form>';
    }
    private static function insertField($key,$label,$html) {
        echo '<details style="margin:6px 0 6px 16px"'.($html!==''?' open':'').'><summary style="cursor:pointer;color:#2271b1">+ '.esc_html($label).'</summary><div style="margin-top:6px">';
        wp_editor($html,'bbi_'.md5($key),['textarea_name'=>'inserts['.$key.']','textarea_rows'=>4,'media_buttons'=>false,'tinymce'=>false,'quicktags'=>true]);
        echo '<p class="description">Leer lassen oder leeren, um nichts einzufügen.</p></div></details>';
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
    /** Schritte des Angaben-Assistenten: [Titel, Untertitel, Abschnitte]; Abschnitt: [Titel, Hinweis, [[Feld-ID, Breite von 6]], aufklappbar]. Unbekannte Felder landen unter „Weitere Angaben“. */
    const STEPS = [
        ['Unternehmen','Firma, Anschrift, Kontakt',[
            ['Firma','',[['company',4],['legal_form',2]]],
            ['Anschrift','',[['street',3],['postal_code',1],['city',2],['country',2]]],
            ['Kontakt','',[['email',3],['phone',3]]],
            ['Vertretungsberechtigte','Geschäftsführung, Inhaber oder Vorstand.',[['representatives',6]]],
            ['Tätigkeit','Kurz beschreiben, was das Unternehmen macht.',[['description',6]]],
        ]],
        ['Register und Recht','Register, Steuern, Beruf',[
            ['Handelsregister','Leer lassen, wenn kein Registereintrag besteht.',[['register_court',3],['register_number',3]]],
            ['Steuern','',[['vat_id',3],['business_id',3]]],
            ['Verbraucherstreitbeilegung','',[['dispute_resolution',6]]],
            ['Redaktionell verantwortlich','Nur bei journalistisch-redaktionellen Inhalten, z. B. Blog oder Magazin.',[['editor_name',3],['editor_address',3]],true],
            ['Reglementierter Beruf','Nur bei Berufen mit Kammer, z. B. Ärzte, Architekten, Steuerberater.',[['professional_title',3],['awarding_country',3],['chamber',3],['supervisory_authority',3],['professional_rules',3],['professional_rules_url',3]],true],
        ]],
        ['Datenschutz','Verantwortliche Stelle, Beauftragter',[
            ['Verantwortliche Stelle','Name und Anschrift, wie sie in der Datenschutzerklärung stehen sollen.',[['responsible_address',6]]],
            ['Datenschutzbeauftragter','',[['dpo_enabled',6],['dpo_address',6],['dpo_email',3],['dpo_phone',3]]],
        ]],
        ['Dienste','Hosting, Tools, Einbindungen',[
            ['Verwendete Dienste','Alles auswählen, was auf der Website eingesetzt wird.',[['services',6]]],
            ['Einstellungen zu Diensten','',[['matomo_cookies',6],['hosting_other_address',6],['social_enabled',6]]],
            ['Ohne Vertrag zur Auftragsverarbeitung','Nur Dienste markieren, mit denen kein Auftragsverarbeitungsvertrag besteht.',[['services_without_dpa',6]]],
        ]],
    ];
    /** Doppelte Felder älterer Fragebögen: werden aus dem Nachfolger abgeleitet und nicht mehr angezeigt. */
    const SUPERSEDED = ['dpo_name'=>'dpo_address','dpo_type'=>'dpo_enabled'];
    /** Felder, die nur bei einer Auswahl erscheinen. */
    const SHOW_IF = ['dpo_address'=>'dpo_enabled','dpo_email'=>'dpo_enabled','dpo_phone'=>'dpo_enabled','matomo_cookies'=>'services:matomo','hosting_other_address'=>'services:hosting_other'];
    /** Gruppen für die Dienstauswahl (Präfix bzw. ID); nicht erfasste Dienste stehen unter „Weitere“. */
    const SERVICE_GROUPS = [
        'Hosting'=>['hosting_'],
        'Website-Funktionen'=>['contact_form','contact_request','cookies','server_logs','registration','comments','payment_ssl','advertising_objection','third_country_notice','job_applications','job_applicant_pool','borlabs','safe_sharing'],
        'Statistik'=>['matomo','google_analytics','google_tag_manager','hotjar','mouseflow','wp_statistics'],
        'Werbung und Tracking'=>['google_ads','google_remarketing','google_conversion','meta_','tiktok_pixel','linkedin_insight'],
        'Schriften, Karten, Videos'=>['google_fonts_','font_awesome_','google_maps','youtube','youtube_standard','vimeo','vimeo_dnt'],
        'Formularschutz'=>['recaptcha','hcaptcha','friendly_captcha','cloudflare_turnstile'],
        'Newsletter und CRM'=>['newsletter','mailchimp','brevo','hubspot_crm'],
        'Termine und Meetings'=>['calendly','doctolib','google_calendar','microsoft_bookings','zoom','microsoft_teams','google_meet'],
        'Shop und Zahlung'=>['contract_services','contract_shop','paypal','klarna','sofort'],
        'Social Media und Login'=>['facebook','instagram','x_twitter','pinterest','linkedin','xing','whatsapp','google_login','facebook_connect'],
    ];
    public static function serviceGroup($id) {
        foreach(self::SERVICE_GROUPS as $group=>$ids) { foreach($ids as $p) { if($id===$p||(substr($p,-1)==='_'&&strpos($id,$p)===0)) { return $group; } } }
        return 'Weitere';
    }
    /** Felder des Fragebogens auf die Schritte verteilen. */
    public static function layout(array $fields) {
        $byId=[];foreach($fields as $f) { if(isset($f['id'])) { $byId[$f['id']]=$f; } }
        foreach(self::SUPERSEDED as $old=>$new) { if(isset($byId[$new])) { unset($byId[$old]); } }
        $steps=[];$placed=[];
        foreach(self::STEPS as [$title,$sub,$sections]) {
            $out=[];
            foreach($sections as $s) {
                $items=[];foreach($s[2] as [$fid,$w]) { if(isset($byId[$fid])) { $items[]=[$byId[$fid],$w];$placed[$fid]=true; } }
                if($items) { $out[]=['title'=>$s[0],'hint'=>$s[1],'items'=>$items,'collapsible'=>!empty($s[3])]; }
            }
            $steps[]=['title'=>$title,'sub'=>$sub,'sections'=>$out];
        }
        foreach($byId as $fid=>$f) {
            if(isset($placed[$fid])) { continue; }
            $n=($f['group']??'')==='Datenschutz'?2:0;
            $last=count($steps[$n]['sections'])-1;
            if($last<0||$steps[$n]['sections'][$last]['title']!=='Weitere Angaben') { $steps[$n]['sections'][]=['title'=>'Weitere Angaben','hint'=>'','items'=>[],'collapsible'=>false];$last++; }
            $steps[$n]['sections'][$last]['items'][]=[$f,in_array($f['type']??'',['textarea','list','multiselect','bool'],true)?6:3];
        }
        return array_values(array_filter($steps,function($s){return $s['sections'];}));
    }
    /** Angaben als Assistent in Schritten, ein Formular für alle Schritte. */
    private static function inputs() {
        $remote=Store::get('remote_inputs');
        if(($remote['identity']??'')!==Store::identity() || !isset($remote['schema']['payload']['fields'])) {
            echo '<section class="bb-card"><div class="bb-card-body"><p>Die Angaben werden aus der Rechtstexte-Zentrale geladen. '.(Store::get('site_id','')===''?'Zuerst unter <a href="'.esc_url(self::url(['tab'=>'settings'])).'">Verbindung</a> die Zentrale eintragen.':'Noch nicht abgerufen.').'</p></div>';
            if(Store::get('site_id','')!=='') { echo '<div class="bb-card-foot">';self::form('sync');echo '<input type="hidden" name="back" value="inputs">';submit_button('Angaben abrufen','primary','submit',false);echo '</form></div>'; }
            echo '</section>';return;
        }
        $draft=Store::get('draft'); $state=$remote;
        if(!empty($draft) && ($draft['identity']??'')===Store::identity()) { $state['inputs']=$draft['inputs'];$state['version']=$draft['base_version_id'];echo '<div class="notice notice-warning inline"><p>Ungesendeter Entwurf: Die Werte unten stammen aus deinem lokalen Entwurf. Mit „Speichern“ erneut senden.</p></div>'; }
        $last=Store::get('last_submission');
        if(!empty($last['conflicts'])) { echo '<div class="notice notice-warning inline"><p>Parallel geändert, bitte prüfen: '.esc_html(implode(', ',$last['conflicts'])).'</p></div>'; }
        if(!empty($last['render_errors'])) { echo '<div class="notice notice-error inline"><p>Die Zentrale meldet Renderfehler. Bis zur Korrektur bleibt die bisherige Ausgabe veröffentlicht.</p></div>'; }
        $formToken=wp_generate_password(32,false,false);
        set_transient('bb_legal_form_'.$formToken,['inputs'=>$state['inputs'],'version'=>$state['version'],'schema'=>$remote['schema']['payload'],'identity'=>Store::identity(),'user'=>get_current_user_id()],HOUR_IN_SECONDS);
        $steps=self::layout($remote['schema']['payload']['fields']);
        $step=isset($_GET['step'])?min(max((int)$_GET['step'],0),count($steps)-1):0;
        echo '<p class="bb-lead">Diese Angaben fließen in Impressum und Datenschutzerklärung. Gespeichert wird alles auf einmal, egal in welchem Schritt du bist.</p>';
        echo '<div data-bb-wizard data-bb-step="'.$step.'">';
        echo '<ol class="bb-steps">';
        foreach($steps as $n=>$s) { echo '<li'.($n===$step?' class="is-active"':'').'><a href="#bb-step-'.$n.'"><span class="bb-num">'.($n+1).'</span><span class="bb-step-text"><strong>'.esc_html($s['title']).'</strong><small>'.esc_html($s['sub']).'</small></span></a></li>'; }
        echo '</ol>';
        self::form('inputs');echo '<input type="hidden" name="form_token" value="'.esc_attr($formToken).'"><input type="hidden" name="step" value="'.$step.'">';
        foreach($steps as $n=>$s) {
            echo '<section class="bb-card bb-step'.($n===$step?' is-active':'').'" id="bb-step-'.$n.'"><header><h2>'.($n+1).'. '.esc_html($s['title']).'</h2></header><div class="bb-card-body">';
            foreach($s['sections'] as $sec) {
                $open=!$sec['collapsible'];
                if(!$open) { foreach($sec['items'] as [$f]) { $v=$state['inputs'][$f['id']]??''; if($v!==''&&$v!==[]&&$v!==false&&$v!==null) { $open=true; } } }
                if($sec['collapsible']) { echo '<details class="bb-section"'.($open?' open':'').'><summary>'.esc_html($sec['title']).($sec['hint']!==''?' <span class="bb-hint">'.esc_html($sec['hint']).'</span>':'').'</summary>'; }
                else { echo '<div class="bb-section"><h3>'.esc_html($sec['title']).'</h3>'.($sec['hint']!==''?'<p class="bb-hint">'.esc_html($sec['hint']).'</p>':''); }
                if($sec['items'][0][0]['id']==='responsible_address') { echo '<p style="margin:0 0 8px"><button type="button" class="bb-link-btn" data-bb-copy-address="responsible_address">Aus den Unternehmensangaben übernehmen</button></p>'; }
                echo '<div class="bb-grid">';
                foreach($sec['items'] as [$f,$w]) { self::field($f,$state['inputs'],$w,$remote['schema']['payload']['fields']); }
                echo '</div>'.($sec['collapsible']?'</details>':'</div>');
            }
            echo '</div><div class="bb-card-foot"><button type="button" class="button" data-bb-prev>Zurück</button><span class="bb-spacer"></span><button type="button" class="button" data-bb-next>Weiter</button>';
            submit_button('Speichern und senden','primary','submit',false);
            echo '</div></section>';
        }
        echo '</form></div>';
    }
    private static function label($f) { return is_array($f['label']??null)?($f['label']['de']??$f['id']):($f['label']??$f['id']); }
    private static function field($f,$values,$width=6,$all=[]) {
        $id=$f['id'];$v=$values[$id]??'';$label=self::label($f);$type=$f['type']??'text';$name='fields['.esc_attr($id).']';$dom='bb-f-'.esc_attr($id);
        $req=!empty($f['required']);$star=$req?'<span class="bb-req" title="Pflichtfeld">*</span>':'';$reqAttr=$req?' data-bb-required':'';
        $show=isset(self::SHOW_IF[$id])?' data-bb-show="'.esc_attr(self::SHOW_IF[$id]).'"':'';
        $help=is_array($f['help']??null)?($f['help']['de']??''):($f['help']??'');$help=$help!==''?'<span class="bb-help">'.esc_html($help).'</span>':'';
        if(!empty($f['localized'])) {
            foreach(['de'=>'Deutsch','en'=>'Englisch'] as $lang=>$langLabel) {
                $val=is_array($v)?($v[$lang]??''):$v;
                echo '<div class="bb-f w3"'.$show.'><label for="'.$dom.'-'.$lang.'">'.esc_html($label).' ('.$langLabel.')'.$star.'</label>';
                echo $type==='textarea'?'<textarea id="'.$dom.'-'.$lang.'" rows="2" name="'.$name.'['.$lang.']">'.esc_textarea($val).'</textarea>':'<input type="text" id="'.$dom.'-'.$lang.'" name="'.$name.'['.$lang.']" value="'.esc_attr($val).'">';
                echo $help.'</div>';
            }
            return;
        }
        echo '<div class="bb-f w'.(int)$width.'"'.$show.'>';
        if($type==='bool') {
            echo '<label class="bb-switch"><input type="checkbox" name="'.$name.'" value="1" '.checked((bool)$v,true,false).'><span><strong>'.esc_html($label).'</strong>'.($help!==''?'<small>'.esc_html(wp_strip_all_tags($help)).'</small>':'').'</span></label>';
        } elseif($type==='multiselect') {
            $options=(array)($f['options']??[]);$selected=is_array($v)?$v:[];
            if($id==='services_without_dpa'&&isset(array_column($all,null,'id')['services'])) {
                echo '<div data-bb-subset="services"><p class="bb-empty" hidden>Noch keine Dienste ausgewählt.</p><div class="bb-tiles">';
                foreach($options as $key=>$optLabel) { echo self::tile($name.'[]',$key,$optLabel,in_array($key,$selected,true)); }
                echo '</div></div>';
            } else {
                $groups=[];foreach($options as $key=>$optLabel) { $groups[self::serviceGroup((string)$key)][$key]=$optLabel; }
                $order=array_merge(array_keys(self::SERVICE_GROUPS),['Weitere']);uksort($groups,function($a,$b)use($order){return array_search($a,$order,true)<=>array_search($b,$order,true);});
                if(count($options)>12) { echo '<div class="bb-tools"><input type="search" placeholder="Dienst suchen" data-bb-filter="'.$dom.'-box" aria-label="Dienst suchen"><span class="bb-count">'.count($selected).' von '.count($options).' ausgewählt</span></div>'; }
                echo '<div id="'.$dom.'-box">';
                foreach($groups as $group=>$opts) {
                    echo '<div data-bb-group>'.(count($groups)>1?'<p class="bb-group-title">'.esc_html($group).'</p>':'').'<div class="bb-tiles">';
                    foreach($opts as $key=>$optLabel) { echo self::tile($name.'[]',$key,$optLabel,in_array($key,$selected,true)); }
                    echo '</div></div>';
                }
                echo '</div>';
            }
        } elseif($type==='select') {
            echo '<span class="bb-label">'.esc_html($label).$star.'</span>';
            $options=(array)($f['options']??[]);
            if(count($options)<=4) {
                echo '<div class="bb-seg" role="radiogroup" aria-label="'.esc_attr($label).'">';
                foreach($options as $key=>$optLabel) { echo '<label><input type="radio" name="'.$name.'" value="'.esc_attr($key).'" '.checked($v===(string)$key,true,false).'><span>'.esc_html($optLabel).'</span></label>'; }
                echo '</div>';
            } else {
                echo '<select name="'.$name.'"'.$reqAttr.'><option value="">Bitte wählen</option>';
                foreach($options as $key=>$optLabel) { echo '<option value="'.esc_attr($key).'" '.selected($v===(string)$key,true,false).'>'.esc_html($optLabel).'</option>'; }
                echo '</select>';
            }
        } elseif($type==='list') {
            $sub=array_values(array_filter($f['fields']??[],function($s){return ($s['id']??'')!=='id';}));
            if(!$sub) { $sub=[['id'=>'name']]; }
            $row=function($i,$item)use($name,$sub){
                $out='<div class="bb-row"><input type="hidden" name="'.$name.'['.$i.'][id]" value="'.esc_attr(is_array($item)?($item['id']??''):'').'">';
                foreach($sub as $s) { $out.='<input type="text" name="'.$name.'['.$i.']['.esc_attr($s['id']).']" value="'.esc_attr(is_array($item)?($item[$s['id']]??''):'').'" placeholder="'.esc_attr($s['id']==='name'?'Vor- und Nachname':self::label($s)).'" aria-label="'.esc_attr($s['id']==='name'?'Name':self::label($s)).'">'; }
                return $out.'<button type="button" class="bb-icon-btn" data-bb-remove aria-label="Entfernen" title="Entfernen">&times;</button></div>';
            };
            $items=is_array($v)?array_values($v):[];
            echo '<div data-bb-list><div class="bb-rows">';
            foreach($items as $i=>$item) { echo $row($i,$item); }
            if(!$items) { echo $row(0,[]); }
            echo '</div><template>'.$row('__i__',[]).'</template><button type="button" class="bb-link-btn" data-bb-add>+ Person hinzufügen</button></div>';
        } elseif($type==='textarea') {
            echo '<label for="'.$dom.'">'.esc_html($label).$star.'</label><textarea id="'.$dom.'" rows="3" name="'.$name.'"'.$reqAttr.'>'.esc_textarea(is_string($v)?$v:'').'</textarea>';
        } else {
            echo '<label for="'.$dom.'">'.esc_html($label).$star.'</label><input type="'.esc_attr(in_array($type,['email','url'],true)?$type:'text').'" id="'.$dom.'" name="'.$name.'" value="'.esc_attr(is_string($v)?$v:'').'"'.$reqAttr.'>';
        }
        echo $type==='bool'?'':$help;
        echo '</div>';
    }
    private static function tile($name,$value,$label,$on) {
        return '<label class="bb-tile"><input type="checkbox" name="'.$name.'" value="'.esc_attr($value).'" '.checked($on,true,false).'><span>'.esc_html($label).'</span></label>';
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
            if(isset(self::SUPERSEDED[$id])&&$v===null&&in_array(self::SUPERSEDED[$id],array_column($schema['fields'],'id'),true)) { continue; }
            if(!empty($f['localized'])) {
                if(!is_array($v)) { throw new \RuntimeException('Sprachwerte fehlen'); }
                $result[$id]=[];foreach(['de','en'] as $lang) { if(isset($v[$lang])&&!is_string($v[$lang])) { throw new \RuntimeException('Ungültiger Sprachwert'); }$result[$id][$lang]=$v[$lang]??''; }
            } elseif($f['type']==='bool') { $result[$id]=$v==='1'; }
            elseif($f['type']==='list') { $result[$id]=self::parseList($v); }
            elseif($f['type']==='multiselect') { if($v!==null&&!is_array($v)) { throw new \RuntimeException('Ungültige Auswahl'); }$result[$id]=array_values(array_filter($v??[],'is_string')); }
            else { if($v!==null&&!is_string($v)) { throw new \RuntimeException('Text erwartet'); }$result[$id]=$v??''; }
        }
        // Ausgeblendete Doppelfelder aus dem Nachfolger ableiten, damit die Zentrale nichts Widersprüchliches zeigt.
        $ids=array_column($schema['fields']??[],'id');
        if(in_array('dpo_type',$ids,true)&&in_array('dpo_enabled',$ids,true)&&!isset($posted['dpo_type'])) { $result['dpo_type']=!empty($result['dpo_enabled'])?(in_array($original['dpo_type']??'',['internal','external'],true)?$original['dpo_type']:'external'):'none'; }
        if(in_array('dpo_name',$ids,true)&&in_array('dpo_address',$ids,true)&&!isset($posted['dpo_name'])) { $result['dpo_name']=trim(strtok((string)($result['dpo_address']??'')."\n","\n")); }
        return $result;
    }
    /** Liste aus Formularzeilen (oder JSON älterer Formulare): leere Zeilen fallen weg, neue Zeilen bekommen eine feste id. */
    public static function parseList($v) {
        if($v===null) { return []; }
        if(is_string($v)) { $list=json_decode($v,true);if(!is_array($list)||array_values($list)!==$list) { throw new \RuntimeException('Ungültige Liste'); }return $list; }
        if(!is_array($v)) { throw new \RuntimeException('Ungültige Liste'); }
        $list=[];
        foreach($v as $row) {
            if(!is_array($row)) { throw new \RuntimeException('Ungültige Liste'); }
            $item=[];foreach($row as $k=>$val) { if(!is_string($k)||!is_string($val)) { throw new \RuntimeException('Ungültige Liste'); }$item[$k]=trim($val); }
            $content=$item;unset($content['id']);
            if(implode('',$content)==='') { continue; }
            if(($item['id']??'')==='') { $item['id']='person-'.bin2hex(random_bytes(4)); }
            $list[]=['id'=>$item['id']]+$content;
        }
        return $list;
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
            } elseif($operation==='segments') {
                $args=[];
                $key=isset($_POST['text_key'])&&is_string($_POST['text_key'])?wp_unslash($_POST['text_key']):'';
                if(!preg_match('~^(imprint|privacy_policy|social_media|terms|withdrawal)/(de|en)$~',$key)) { throw new \RuntimeException('Unbekannter Text'); }
                [$type,$lang]=explode('/',$key);
                [$blocks,$inserts,$reset]=self::parseSegments(wp_unslash($_POST['blocks']??[]),wp_unslash($_POST['inserts']??[]),wp_unslash($_POST['reset']??[]));
                $result=(new Client())->saveSegments($type,$lang,$blocks,$inserts,$reset);
                if($result==='segments_draft') { $args=['edit'=>$key]; }
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
            } elseif($operation==='inputs') {
                $args=['tab'=>'inputs','step'=>isset($_POST['step'])&&is_string($_POST['step'])?min(max((int)$_POST['step'],0),9):0];
                $token=isset($_POST['form_token'])&&is_string($_POST['form_token'])?wp_unslash($_POST['form_token']):'';
                if(!preg_match('/^[A-Za-z0-9]{32}$/',$token)) { throw new \RuntimeException('Formular veraltet'); }
                $state=get_transient('bb_legal_form_'.$token);
                if(!$state||$state['user']!==get_current_user_id()||$state['identity']!==Store::identity()) { throw new \RuntimeException('Formular veraltet'); }
                $values=self::parseFields($state['schema'],wp_unslash($_POST['fields']??[]),$state['inputs']);
                $result=(new Client())->submit($values,$state['version']);delete_transient('bb_legal_form_'.$token);
            }
        } catch(\Throwable $e) { if(!in_array($result,['code_invalid','switch_blocked','connection_empty'],true)) { $result='invalid'; } }
        wp_safe_redirect(self::url(array_merge($args,['bb_legal_result'=>$result])));exit;
    }
}
