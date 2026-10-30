<?php
/** Run exclusively via WP CLI in the isolated integration sandbox. */
if(!defined('WP_CLI')||!WP_CLI||!defined('BB_LEGAL_TEST_ENV')||!BB_LEGAL_TEST_ENV)throw new Exception('Isolierte Testumgebung erforderlich');
use BB\Legal\V1\{Module,Store,Client};
$case=getenv('BB_LEGAL_SCENARIO');
function verify($condition,$message){if(!$condition)throw new Exception($message);echo 'OK '.$message."\n";}
if($case==='off'){
 verify(!shortcode_exists('erecht24'),'Modul nach Plugin-Aktivierung ausgeschaltet');verify(shortcode_exists('bb_maps_revoke'),'Bestehender Maps-Blocker weiterhin aktiv');
}elseif($case==='prepare'){
 Store::put('endpoint','https://legal.bbkd.de');Store::put('site_id',bin2hex(random_bytes(12)));Store::key(bin2hex(random_bytes(32)));
 $rows=[];foreach(['imprint','privacy_policy','social_media'] as $i=>$type){$p=['site_id'=>Store::get('site_id'),'type'=>$type,'language'=>'de','title'=>'Demo '.$type,'html'=>'<p>Neutraler Testtext '.$type.'</p>','stand'=>'2026-10-29','input_version_id'=>1,'questionnaire_version_id'=>1,'block_versions'=>[1]];$r=['id'=>$i+1,'entity'=>$p['site_id'].'/'.$type.'/de','created_at'=>'2026-10-29T12:00:00Z','prev_hash'=>'','payload'=>$p];$r['hash']=hash('sha256',Store::canonical(['outputs',$r['entity'],$r['created_at'],$r['prev_hash'],$p]));$rows[]=$r;}
 update_option('bb_legal_test_outputs',$rows,false);verify((new Client())->sync(),'Texte über echte WordPress-HTTP-Schicht mit synthetischer Zentrale abgerufen');verify(!Store::get('acknowledged'),'Abruf allein ist keine Bestätigung');Store::put('enabled_consent',true);
 update_option('bb_legal_test_key_fingerprint',hash('sha256',Store::key()),false);
}elseif($case==='active'){
 verify(Module::active()&&Module::owner()['id']==='consent','Consent ist alleiniger Rechtstext-Anbieter');
 verify(strpos(do_shortcode('[erecht24 type="imprint" strip_title="true"]'),'Neutraler Testtext imprint')!==false,'Originaler Shortcode rendert lokal');
 verify(strpos(do_shortcode('[datenschutz]'),'Neutraler Testtext privacy_policy')!==false,'Alter Datenschutz-Alias rendert');
 verify(strpos(do_shortcode('[erecht24 type="privacy_policy_social_media"]'),'Neutraler Testtext social_media')!==false,'Social-Media-Typalias kompatibel');
 verify(strpos(do_blocks('<!-- wp:erecht24/erecht24 {"type":"privacy_policy","strip_title":true} /-->'),'Neutraler Testtext privacy_policy')!==false,'Alter Gutenberg-Block rendert');
 verify(strpos(do_blocks('<!-- wp:erecht24/legal-text {"type":"imprint"} /-->'),'Neutraler Testtext imprint')!==false,'Neuer Gutenberg-Block rendert');
 verify(!Store::get('observed'),'CLI-Rendern bestätigt keine öffentliche Anzeige');
 $all=wp_load_alloptions();verify(!isset($all['bb_legal_site_key'])&&!isset($all['bb_legal_cache']),'Schlüssel und Texte ohne Autoload');
 $cache=Store::get('cache');update_option('bb_legal_test_offline',true,false);verify(!(new Client())->sync()&&Store::get('cache')===$cache,'Verbindungsausfall erhält lokale Texte');delete_option('bb_legal_test_offline');
 $request=new WP_REST_Request('POST','/bb-legal/v1/push');$body=wp_json_encode(['site_id'=>Store::get('site_id'),'output_id'=>2]);$request->set_body($body);$timestamp=(string)time();$_SERVER['REQUEST_URI']='/wp-json/bb-legal/v1/push';$request->set_header('x-bb-site',Store::get('site_id'));$request->set_header('x-bb-timestamp',$timestamp);$request->set_header('x-bb-signature',hash_hmac('sha256',"POST\n".$_SERVER['REQUEST_URI']."\n".$timestamp."\n".$body,Store::key()));verify(Module::verifyPush($request)===true,'WordPress-REST-Push-Signatur akzeptiert');verify(is_wp_error(Module::verifyPush($request)),'WordPress-Push-Replay verhindert');
}elseif($case==='editor'){
 require_once ABSPATH.'wp-admin/includes/template.php';require_once ABSPATH.'wp-admin/includes/plugin.php';wp_set_current_user(1);
 ob_start();\BB\Legal\V1\Admin::screen();$list=ob_get_clean();
 verify(strpos($list,'Rechtsinhalte')!==false&&strpos($list,'edit=imprint')!==false&&strpos($list,'Einstellungen')!==false,'Übersicht mit Bearbeiten-Link und Reiter Einstellungen');
 $_GET['edit']='imprint/de';ob_start();\BB\Legal\V1\Admin::screen();$editor=ob_get_clean();unset($_GET['edit']);
 verify(strpos($editor,'name="blocks[imprint_company/de]"')!==false&&strpos($editor,'name="inserts[_start]"')!==false&&strpos($editor,'name="inserts[_end]"')!==false,'Editor zeigt Bausteine und Einfügestellen');
 verify(strpos($editor,'angepasst – wird nicht mehr aktualisiert')!==false&&strpos($editor,'name="reset[]"')!==false,'Angepasster Baustein markiert und zurücksetzbar');
 verify(strpos($editor,'Eigener Anfang')!==false,'Bestehender eigener Text vorbelegt');
 $_GET['tab']='settings';ob_start();\BB\Legal\V1\Admin::screen();$settings=ob_get_clean();unset($_GET['tab']);
 verify(strpos($settings,'name="site_key"')!==false&&strpos($settings,'name="enabled"')!==false,'Einstellungen mit Seitenschlüssel und Aktivierung');
 verify((new Client())->saveSegments('imprint','de',['imprint_company/de'=>'<p>Geändert</p>'],['_end'=>'<p>Schluss</p>'],[])==='segments_ok'&&get_option('bb_legal_test_last_segments')['inserts']['_end']==='<p>Schluss</p>','Änderungen signiert an die Zentrale gesendet');
}elseif($case==='ack'){
 verify((new Client())->acknowledge(),'Öffentlich gerenderte Ausgaben bestätigt');verify(count(get_option('bb_legal_test_last_ack')['output_ids'])===3,'Bestätigung umfasst nur öffentlich gerenderte Ausgaben');
}elseif($case==='foreign'){
 verify(!Module::active(),'Bei Fremdanbieter keine konkurrierende Ausgabe');verify(do_shortcode('[erecht24]')==='<p>Fremder Demo-Anbieter</p>','Fremder Shortcode unverändert');
}elseif($case==='basic'){
 verify(Module::active()&&Module::owner()['id']==='basic','Aktiviertes Basic-Modul übernimmt');verify(hash('sha256',Store::key())===get_option('bb_legal_test_key_fingerprint'),'Seitenschlüssel unverändert übernommen');verify(strpos(do_shortcode('[erecht24]'),'Neutraler Testtext')!==false,'Lokale Texte bei Übergabe erhalten');verify(wp_next_scheduled('bb_legal_poll')!==false,'Gemeinsamer Cron-Hook besteht');
}elseif($case==='fallback'){
 verify(Module::active()&&Module::owner()['id']==='consent','Consent übernimmt bei ausgeschaltetem Basic-Legal-Modul');verify(hash('sha256',Store::key())===get_option('bb_legal_test_key_fingerprint'),'Schlüssel beim Rückwechsel erhalten');
}elseif($case==='incompatible'){
 verify(!Module::active(),'Inkompatibles Schema wird nicht zurückmigriert');verify(count(Store::get('cache')['outputs'])===3,'Daten trotz inkompatiblem Schema erhalten');
}elseif($case==='freeze'){
 require_once ABSPATH.'wp-admin/includes/plugin.php';wp_set_current_user(1);$D='BB\Legal\V1\Deactivate';
 $short=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Impressum','post_content'=>'<p>Vorher</p>[et_pb_text][impressum][/et_pb_text]']);
 $block=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Datenschutz','post_content'=>'<!-- wp:erecht24/erecht24 {"type":"privacy_policy","strip_title":true} /-->']);
 $el=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Elementor','post_content'=>'']);
 update_post_meta($el,'_elementor_data',wp_slash(wp_json_encode([['id'=>'a1','elType'=>'section','elements'=>[['id'=>'b2','elType'=>'widget','widgetType'=>'erecht24_legal_text','settings'=>['type'=>'social_media','strip_title'=>'true'],'elements'=>[]]]]])));
 $plain=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Ohne','post_content'=>'<p>Kein Rechtstext</p>']);
 $found=$D::usages();verify(in_array($short,$found,true)&&in_array($block,$found,true)&&in_array($el,$found,true)&&!in_array($plain,$found,true),'Seiten mit Shortcode, Block und Elementor-Widget gefunden');
 $links=apply_filters('plugin_action_links',['deactivate'=>'<a href="x">Deaktivieren</a>'],plugin_basename(WP_PLUGIN_DIR.'/bb-consent/bb-map-consent.php'));verify(strpos($links['deactivate'],'bb_legal_deactivate=1')!==false,'Deaktivieren führt zuerst zur Abfrage');
 $other=apply_filters('plugin_action_links',['deactivate'=>'<a href="x">Deaktivieren</a>'],'akismet/akismet.php');verify($other['deactivate']==='<a href="x">Deaktivieren</a>','Andere Plugins unverändert');
 $GLOBALS['pagenow']='plugins.php';$_GET['bb_legal_deactivate']='1';ob_start();$D::notice();$notice=ob_get_clean();
 verify(strpos($notice,'Letzten Stand fest einsetzen und deaktivieren')!==false&&strpos($notice,'Ohne Sichern deaktivieren')!==false&&strpos($notice,'action=deactivate')!==false,'Abfrage mit beiden Wegen');
 $cache=Store::get('cache');Store::put('cache',[]);list($plan,$problems)=$D::plan();Store::put('cache',$cache);verify(count($problems)===3&&!$plan,'Ohne gültige Texte wird nichts geändert');
 list($plan,$problems)=$D::plan();verify(!$problems&&count($plan)===3,'Alle Stellen vorbereitet');$D::apply($plan);
 $c=get_post($short)->post_content;verify(strpos($c,'<p>Vorher</p>[et_pb_text]<div class="bb-legal-text')!==false&&strpos($c,'Neutraler Testtext imprint')!==false&&substr($c,-13)==='[/et_pb_text]','Shortcode fest eingesetzt, Divi-Hülle bleibt');
 $c=get_post($block)->post_content;verify(strpos($c,'<!-- wp:html -->')===0&&strpos($c,'Neutraler Testtext privacy_policy')!==false&&strpos($c,'<h1>')===false,'Block wird HTML-Block, Titel ausgeblendet wie vorher');
 $data=json_decode(get_post_meta($el,'_elementor_data',true),true);$w=$data[0]['elements'][0];verify($w['widgetType']==='text-editor'&&$w['id']==='b2'&&strpos($w['settings']['editor'],'Neutraler Testtext social_media')!==false,'Elementor-Widget wird Text-Widget');
 verify(count(wp_get_post_revisions($short))>=1,'Vorherige Fassung als Revision erhalten');
 verify(!array_intersect([$short,$block,$el],$D::usages()),'Danach keine Seite mehr vom Plugin abhängig');
 $links=apply_filters('plugin_action_links',['deactivate'=>'<a href="x">Deaktivieren</a>'],plugin_basename(WP_PLUGIN_DIR.'/bb-consent/bb-map-consent.php'));verify($links['deactivate']==='<a href="x">Deaktivieren</a>','Ohne Rechtstext-Seiten normales Deaktivieren');
 foreach([$short,$block,$el,$plain] as $id){wp_delete_post($id,true);}
}else{throw new Exception('Unbekannter Testfall');}
