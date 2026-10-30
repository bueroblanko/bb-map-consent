<?php
/** Standalone module regressions; only synthetic fixtures, no WordPress installation required. */
define('ABSPATH', __DIR__); define('HOUR_IN_SECONDS',3600);
$GLOBALS['options']=[];$GLOBALS['autoloads']=[];$GLOBALS['events']=[];$GLOBALS['actions']=[];$GLOBALS['requests']=[];
function get_option($name,$default=false){return array_key_exists($name,$GLOBALS['options'])?$GLOBALS['options'][$name]:$default;}
function add_option($name,$value,$unused='',$autoload=true){if(array_key_exists($name,$GLOBALS['options']))return false;$GLOBALS['options'][$name]=$value;$GLOBALS['autoloads'][$name]=$autoload;return true;}
function update_option($name,$value,$autoload=null){$GLOBALS['options'][$name]=$value;$GLOBALS['autoloads'][$name]=$autoload;return true;}
function delete_option($name){unset($GLOBALS['options'][$name]);return true;}
function wp_salt($scheme){return $GLOBALS['test_salt']??'Synthetic test installation salt';}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function wp_strip_all_tags($v){return strip_tags($v);}
function wp_parse_url($u,$part=-1){return parse_url($u,$part);}
function wp_http_validate_url($u){return strpos($u,'https://legal.example.test')===0?$u:false;}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error{public $code;public function __construct($code,$msg='',$data=[]){$this->code=$code;}}
class WP_REST_Response{public $status;public function __construct($data,$status=200){$this->status=$status;}}
function wp_remote_retrieve_response_code($r){return $r['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
function wp_safe_remote_request($u,$args){$GLOBALS['requests'][]=[$u,$args];$next=array_shift($GLOBALS['http']);return $next??new WP_Error('offline');}
function add_action($name,$callback,$priority=10,$args=1){$GLOBALS['actions'][$name][]=[$callback,$priority];}
function add_filter(...$args){}function do_action($name,...$args){$GLOBALS['fired'][]=$name;}
function add_shortcode($name,$callback){$GLOBALS['shortcode_tags'][$name]=$callback;}
function wp_next_scheduled($name){return $GLOBALS['events'][$name]??false;}
function wp_schedule_single_event($time,$name,$args=[]){$GLOBALS['events'][$name]=$time;}
function wp_schedule_event($time,$schedule,$name){$GLOBALS['events'][$name]=$time;}
function wp_clear_scheduled_hook($name){unset($GLOBALS['events'][$name]);}
function wp_register_script(...$args){}function register_block_type($name,$args){WP_Block_Type_Registry::get_instance()->blocks[$name]=(object)$args;}
class WP_Block_Type_Registry{public $blocks=[];public static function get_instance(){static $i;return $i??($i=new self());}public function is_registered($n){return isset($this->blocks[$n]);}public function get_registered($n){return $this->blocks[$n]??null;}}
function shortcode_atts($defaults,$actual,$tag=''){return array_merge($defaults,array_intersect_key($actual,$defaults));}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}function esc_attr($v){return esc_html($v);}function wp_kses_post($v){return preg_replace('~<script.*?</script>~s','',$v);}
function wp_validate_boolean($v){return !in_array($v,[false,'false','0','',0],true);}
function is_admin(){return $GLOBALS['admin']??false;}function is_preview(){return $GLOBALS['preview']??false;}function is_feed(){return false;}
foreach(['Store','Client','Module','Admin'] as $file)require __DIR__.'/../includes/legal/'.$file.'.php';
use BB\Legal\V1\{Store,Client,Module,Admin};
$passed=0;
function check($v,$message){global $passed;if(!$v)throw new Exception($message);$passed++;echo 'OK '.$message."\n";}
function row($id,$type='imprint',$lang='de'){
 $p=['site_id'=>Store::get('site_id'),'type'=>$type,'language'=>$lang,'title'=>'Demo','html'=>'<p>Neutraler Demo-Text</p>','stand'=>'2026-10-29','input_version_id'=>1,'questionnaire_version_id'=>1,'block_versions'=>[1]];
 $r=['id'=>$id,'entity'=>$p['site_id'].'/'.$type.'/'.$lang,'created_at'=>'2026-10-29T12:00:00Z','prev_hash'=>'','payload'=>$p];$r['hash']=hash('sha256',Store::canonical(['outputs',$r['entity'],$r['created_at'],$r['prev_hash'],$p]));return $r;
}
function reply($data){return ['code'=>200,'body'=>json_encode($data)];}
function queueSync($rows){$GLOBALS['http']=[reply(['outputs'=>$rows]),reply(['inputs'=>['company'=>'Demo'],'version'=>1,'schema'=>['payload'=>['fields'=>[['id'=>'company','type'=>'text']]]]])];}
Store::put('endpoint','https://legal.example.test');Store::put('site_id',str_repeat('a',24));$key=bin2hex(random_bytes(32));Store::key($key);
check(Store::key()===$key,'Schlüssel entschlüsselbar');check(strpos(Store::get('site_key'),$key)===false,'Kein Klartextschlüssel in Optionen');check(!array_filter($GLOBALS['autoloads']),'Moduloptionen ohne Autoload');
$rows=[row(1),row(2,'privacy_policy')];check(Store::validManifest($rows),'Vollständiges Manifest gültig');$bad=$rows;$bad[0]['payload']['html']='Manipuliert';check(!Store::validManifest($bad),'Beschädigter Hash abgelehnt');check(!Store::validManifest([$rows[0]]),'Unvollständige Sprache abgelehnt');
queueSync($rows);$c=new Client();check($c->sync(),'Synchronisation erfolgreich');check(Store::ready(),'Lokaler Stand bereit');check(!Store::get('acknowledged'),'Abruf ist keine Anzeige-Bestätigung');
[$url,$args]=$GLOBALS['requests'][0];$p=parse_url($url);$target=$p['path'].'?'.$p['query'];$h=$args['headers'];check(hash_equals($h['X-BB-Signature'],hash_hmac('sha256',"GET\n".$target."\n".$h['X-BB-Timestamp']."\n",$key)),'Exakte Pfad-/Query-Signatur');check($args['redirection']===0&&$args['sslverify']===true,'Keine Weiterleitung und TLS-Prüfung');
$before=Store::get('cache');$GLOBALS['http']=[reply(['outputs'=>[$rows[0]]])];check(!$c->sync()&&Store::get('cache')===$before,'Ungültiges Manifest erhält Cache');$GLOBALS['http']=[new WP_Error('offline')];check(!$c->sync()&&Store::get('cache')===$before,'Netzfehler erhält Cache');
Store::put('draft',['inputs'=>['company'=>'Ungesendet'],'base_version_id'=>1]);queueSync($rows);$c->sync();check(Store::get('draft')['inputs']['company']==='Ungesendet','Abruf überschreibt keinen Entwurf');
$GLOBALS['http']=[new WP_Error('offline')];check($c->submit(['company'=>'Offline'],1)==='draft','Offline-Eingaben bleiben als Entwurf');check(Store::get('draft')['base_version_id']===1,'Entwurf behält Basisversion');
$GLOBALS['http']=[reply(['status'=>'conflict','inputs'=>['company'=>'Neu'],'version'=>3,'conflicts'=>['company']]),reply(['outputs'=>$rows]),reply(['inputs'=>['company'=>'Neu'],'version'=>3,'schema'=>['payload'=>['fields'=>[]]]])];check($c->submit(['company'=>'Neu'],1)==='conflict','Feldkonflikt sichtbar');check(Store::get('last_submission')['conflicts']===['company'],'Konfliktfelder gespeichert');
$consent=['id'=>'consent','schema'=>1,'version'=>'1.0.0','enabled'=>true,'path'=>__DIR__.'/../includes/legal','url'=>'https://local.test/plugin/legal'];$basic=$consent;$basic['id']='basic';
check(Module::choose([$consent,$basic],1,true)['id']==='basic','Basic übernimmt bei betriebsbereitem aktiviertem Modul');check(Module::choose([$basic,$consent],1,true)['id']==='basic','Ladereihenfolge unabhängig');$basic['enabled']=false;check(Module::choose([$basic,$consent],1,true)['id']==='consent','Basic ohne aktiviertes Legal-Modul übernimmt nicht');check(Module::choose([$consent],2,true)===null,'Inkompatible Datenversion wird nicht zurückmigriert');check(Module::choose([$consent],1,false)===null,'Kein Übernehmen ohne gültigen lokalen Textbestand');
Module::boot([$consent]);Module::activate();check(Module::active(),'Modul einzeln aktiv');$html=Module::shortcode(['strip_title'=>'true']);check(strpos($html,'<h1>')===false&&strpos($html,'Demo-Text')!==false,'Shortcode ohne Titel');check(strpos(Module::shortcode([]),'<h1>Demo</h1>')!==false,'Shortcode mit Titel');check(isset($GLOBALS['events']['bb_legal_poll']),'Genau ein Polling-Hook');check(!Module::conflicts(),'Eigene Handler sind kein Fremdkonflikt');
check(!empty(Store::get('observed'))&&!Store::get('acknowledged'),'Öffentliches Rendern vorgemerkt');$GLOBALS['http']=[reply(['status'=>'ok'])];check($c->acknowledge(),'Anzeige nach Rendern bestätigt');check(Store::get('acknowledged')['ids']===[1],'Nur wirklich gerenderte Version bestätigt');
$GLOBALS['admin']=true;Module::shortcode(['type'=>'privacy_policy']);check(count(Store::get('observed'))===1,'Admin-Vorschau bestätigt nichts');$GLOBALS['admin']=false;
class PushRequest{private $body;private $headers;public function __construct($body,$headers){$this->body=$body;$this->headers=$headers;}public function get_body(){return $this->body;}public function get_header($name){return $this->headers[$name]??'';}}
$_SERVER['REQUEST_URI']='/wp-json/bb-legal/v1/push';$body=json_encode(['site_id'=>Store::get('site_id'),'output_id'=>2]);$timestamp=(string)time();$signature=hash_hmac('sha256',"POST\n".$_SERVER['REQUEST_URI']."\n".$timestamp."\n".$body,$key);$request=new PushRequest($body,['x-bb-site'=>Store::get('site_id'),'x-bb-timestamp'=>$timestamp,'x-bb-signature'=>$signature]);check(Module::verifyPush($request)===true,'Signierter Push akzeptiert');check(Module::verifyPush($request) instanceof WP_Error,'Push-Replay abgewehrt');check(Module::push($request)->status===202,'Push plant asynchronen Abruf');
check(Admin::parseFields(['fields'=>[['id'=>'enabled','type'=>'bool'],['id'=>'services','type'=>'multiselect']]],['enabled'=>'1','services'=>['youtube']],['other'=>'Erhalten'])===['other'=>'Erhalten','enabled'=>true,'services'=>['youtube']],'Fragebogen behält unbekannte bestehende Felder');
$GLOBALS['shortcode_tags']['erecht24']='foreign_callback';check(Module::conflicts(),'Fremde Handler erkannt');Module::activate();check($GLOBALS['shortcode_tags']['erecht24']==='foreign_callback','Fremde Handler nicht überschrieben');
$GLOBALS['requests']=[];queueSync([row(5),row(2,'privacy_policy')]);array_unshift($GLOBALS['http'],reply(['status'=>'ok','detached'=>true]));
check((new Client())->editText('imprint','de','Impressum','<p>Kundentext</p>',1)==='text_ok','Freier Text an die Zentrale gesendet');
[$url,$args]=$GLOBALS['requests'][0];$sent=json_decode($args['body'],true);
check($args['method']==='PUT'&&str_ends_with(parse_url($url,PHP_URL_PATH),'/texts/imprint/de')&&$sent['html']==='<p>Kundentext</p>'&&$sent['base_output_id']===1,'Text mit Grundlage signiert per PUT');
check(empty(Store::get('text_drafts')['imprint/de']),'Gesendeter Text hinterlässt keinen Entwurf');
check(Store::get('cache')['outputs'][0]['id']===5,'Nach dem Senden neue Fassung abgeholt');
$GLOBALS['http']=[new WP_Error('offline')];check((new Client())->editText('privacy_policy','de','Datenschutz','<p>Offline</p>',2)==='text_draft','Offline bleibt der Text als Entwurf');
check(Store::get('text_drafts')['privacy_policy/de']['html']==='<p>Offline</p>','Entwurf enthält den bearbeiteten Text');
echo "$passed Legal-Regressionsprüfungen bestanden\n";
