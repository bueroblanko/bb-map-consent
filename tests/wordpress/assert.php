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
 verify(strpos($list,'Rechtsinhalte')!==false&&strpos($list,'edit=imprint')!==false&&strpos($list,'tab=inputs')!==false&&strpos($list,'tab=settings')!==false,'Übersicht mit Bearbeiten-Link und Reitern Angaben und Verbindung');
 $_GET['edit']='imprint/de';ob_start();\BB\Legal\V1\Admin::screen();$editor=ob_get_clean();unset($_GET['edit']);
 verify(strpos($editor,'name="blocks[imprint_company/de]"')!==false&&strpos($editor,'name="inserts[_start]"')!==false&&strpos($editor,'name="inserts[_end]"')!==false,'Editor zeigt Bausteine und Einfügestellen');
 verify(strpos($editor,'angepasst – wird nicht mehr aktualisiert')!==false&&strpos($editor,'name="reset[]"')!==false,'Angepasster Baustein markiert und zurücksetzbar');
 verify(strpos($editor,'Eigener Anfang')!==false,'Bestehender eigener Text vorbelegt');
 $_GET['tab']='settings';ob_start();\BB\Legal\V1\Admin::screen();$settings=ob_get_clean();unset($_GET['tab']);
 verify(strpos($settings,'name="site_key"')!==false&&strpos($settings,'name="enabled"')!==false,'Einstellungen mit Seitenschlüssel und Aktivierung');
 $_GET['tab']='inputs';ob_start();\BB\Legal\V1\Admin::screen();$inputs=ob_get_clean();unset($_GET['tab']);
 verify(strpos($inputs,'data-bb-wizard')!==false&&strpos($inputs,'name="fields[company]"')!==false&&strpos($inputs,'name="fields[services][]" value="youtube"')!==false,'Angaben als Assistent mit Feldern und Dienstauswahl');
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
}else{throw new Exception('Unbekannter Testfall');}
