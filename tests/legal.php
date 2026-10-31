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
function wp_parse_url($u,$part=-1){return parse_url($u,$part);}function rest_url($p=''){return 'https://kunde.example/wp-json/'.$p;}
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
foreach(['Store','Client','Module','Admin','Deactivate'] as $file)require __DIR__.'/../includes/legal/'.$file.'.php';
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
[$url,$args]=$GLOBALS['requests'][0];$p=parse_url($url);$target=$p['path'].'?'.$p['query'];$h=$args['headers'];check(hash_equals($h['X-BB-Signature'],hash_hmac('sha256',"GET\n".$target."\n".$h['X-BB-Timestamp']."\n",$key)),'Exakte Pfad-/Query-Signatur');check($args['redirection']===0&&$args['sslverify']===true,'Keine Weiterleitung und TLS-Prüfung');check($h['X-BB-Push-Url']==='https://kunde.example/wp-json/bb-legal/v1/push','Push-Adresse wird mitgeschickt');
$before=Store::get('cache');$GLOBALS['http']=[reply(['outputs'=>[$rows[0]]])];check(!$c->sync()&&Store::get('cache')===$before,'Ungültiges Manifest erhält Cache');$GLOBALS['http']=[new WP_Error('offline')];check(!$c->sync()&&Store::get('cache')===$before,'Netzfehler erhält Cache');
Store::put('draft',['inputs'=>['company'=>'Ungesendet'],'base_version_id'=>1]);queueSync($rows);$c->sync();check(Store::get('draft')['inputs']['company']==='Ungesendet','Abruf überschreibt keinen Entwurf');
$GLOBALS['http']=[new WP_Error('offline')];check($c->submit(['company'=>'Offline'],1)==='draft','Offline-Eingaben bleiben als Entwurf');check(Store::get('draft')['base_version_id']===1,'Entwurf behält Basisversion');
$GLOBALS['http']=[reply(['status'=>'conflict','inputs'=>['company'=>'Neu'],'version'=>3,'conflicts'=>['company']]),reply(['outputs'=>$rows]),reply(['inputs'=>['company'=>'Neu'],'version'=>3,'schema'=>['payload'=>['fields'=>[]]]])];check($c->submit(['company'=>'Neu'],1)==='conflict','Feldkonflikt sichtbar');check(Store::get('last_submission')['conflicts']===['company'],'Konfliktfelder gespeichert');
$consent=['id'=>'consent','schema'=>1,'version'=>'1.0.0','enabled'=>true,'path'=>__DIR__.'/../includes/legal','url'=>'https://local.test/plugin/legal'];$basic=$consent;$basic['id']='basic';
check(Module::choose([$consent,$basic],1,true)['id']==='basic','Basic übernimmt bei betriebsbereitem aktiviertem Modul');check(Module::choose([$basic,$consent],1,true)['id']==='basic','Ladereihenfolge unabhängig');$basic['enabled']=false;check(Module::choose([$basic,$consent],1,true)['id']==='consent','Basic ohne aktiviertes Legal-Modul übernimmt nicht');check(Module::choose([$consent],2,true)===null,'Inkompatible Datenversion wird nicht zurückmigriert');check(Module::choose([$consent],1,false)===null,'Kein Übernehmen ohne gültigen lokalen Textbestand');
Module::boot([$consent]);Module::activate();check(Module::active(),'Modul einzeln aktiv');$html=Module::shortcode(['strip_title'=>'true']);check(strpos($html,'<h1>')===false&&strpos($html,'Demo-Text')!==false,'Shortcode ohne Titel');check(strpos(Module::shortcode([]),'<h1>Demo</h1>')!==false,'Shortcode mit Titel');check(isset($GLOBALS['events']['bb_legal_poll']),'Genau ein Polling-Hook');check(!Module::conflicts(),'Eigene Handler sind kein Fremdkonflikt');
Store::put('selbst',['imprint/de'=>'<p>Selbst gepflegt</p>']);$html=Module::shortcode(['type'=>'imprint']);
check(Module::active()&&strpos($html,'Selbst gepflegt')!==false&&strpos($html,'<h1>Impressum</h1>')!==false,'Selbst gepflegt: eigener Text statt Text der Zentrale');
check(strpos(Module::shortcode(['type'=>'imprint','lang'=>'en']),'Selbst gepflegt')===false,'Selbst gepflegt gilt nur für diesen Text');
delete_option('bb_legal_selbst');
check(!empty(Store::get('observed'))&&!Store::get('acknowledged'),'Öffentliches Rendern vorgemerkt');$GLOBALS['http']=[reply(['status'=>'ok'])];check($c->acknowledge(),'Anzeige nach Rendern bestätigt');check(Store::get('acknowledged')['ids']===[1],'Nur wirklich gerenderte Version bestätigt');
$GLOBALS['admin']=true;Module::shortcode(['type'=>'imprint','lang'=>'en']);check(count(Store::get('observed'))===1,'Admin-Vorschau bestätigt nichts');$GLOBALS['admin']=false;
class PushRequest{private $body;private $headers;public function __construct($body,$headers){$this->body=$body;$this->headers=$headers;}public function get_body(){return $this->body;}public function get_header($name){return $this->headers[$name]??'';}}
$_SERVER['REQUEST_URI']='/wp-json/bb-legal/v1/push';$body=json_encode(['site_id'=>Store::get('site_id'),'output_id'=>2]);$timestamp=(string)time();$signature=hash_hmac('sha256',"POST\n".$_SERVER['REQUEST_URI']."\n".$timestamp."\n".$body,$key);$request=new PushRequest($body,['x-bb-site'=>Store::get('site_id'),'x-bb-timestamp'=>$timestamp,'x-bb-signature'=>$signature]);check(Module::verifyPush($request)===true,'Signierter Push akzeptiert');check(Module::verifyPush($request) instanceof WP_Error,'Push-Replay abgewehrt');check(Module::push($request)->status===202,'Push plant asynchronen Abruf');
check(Admin::parseFields(['fields'=>[['id'=>'enabled','type'=>'bool'],['id'=>'services','type'=>'multiselect']]],['enabled'=>'1','services'=>['youtube']],['other'=>'Erhalten'])===['other'=>'Erhalten','enabled'=>true,'services'=>['youtube']],'Fragebogen behält unbekannte bestehende Felder');
$GLOBALS['shortcode_tags']['erecht24']='foreign_callback';check(Module::conflicts(),'Fremde Handler erkannt');Module::activate();check($GLOBALS['shortcode_tags']['erecht24']==='foreign_callback','Fremde Handler nicht überschrieben');
$GLOBALS['requests']=[];queueSync([row(5),row(2,'privacy_policy')]);array_unshift($GLOBALS['http'],reply(['status'=>'ok']));
check((new Client())->saveSegments('imprint','de',['imprint_company/de'=>'<p>Kunde</p>'],['_end'=>'<p>Ende</p>'],[])==='segments_ok','Bausteine an die Zentrale gesendet');
[$url,$args]=$GLOBALS['requests'][0];$sent=json_decode($args['body'],true);$path=parse_url($url,PHP_URL_PATH);
check($args['method']==='PUT'&&substr($path,-strlen('/segments/imprint/de'))==='/segments/imprint/de'&&$sent['blocks']['imprint_company/de']==='<p>Kunde</p>'&&$sent['inserts']['_end']==='<p>Ende</p>','Bausteine und eigener Text signiert per PUT');
check(empty(Store::get('segment_drafts')['imprint/de']),'Gesendet hinterlässt keinen Entwurf');
check(Store::get('cache')['outputs'][0]['id']===5,'Nach dem Senden neue Fassung abgeholt');
$GLOBALS['http']=[new WP_Error('offline')];check((new Client())->saveSegments('privacy_policy','de',['x/de'=>'<p>Offline</p>'],[],[])==='segments_draft','Offline bleibt als Entwurf');
check(Store::get('segment_drafts')['privacy_policy/de']['blocks']['x/de']==='<p>Offline</p>','Entwurf enthält die Änderungen');
check(Admin::parseSegments(['a/de'=>'<p>x</p>','bad key'=>'z'],['_start'=>'<p>s</p><script>q</script>','evil'=>'q'],['a/de'])===[[],['_start'=>'<p>s</p>'],['a/de']],'Editor-Werte gefiltert, Zurücksetzen gewinnt');
$schema=['fields'=>[['id'=>'company','type'=>'text'],['id'=>'representatives','type'=>'list','fields'=>[['id'=>'id'],['id'=>'name']]],['id'=>'dpo_name','type'=>'text'],['id'=>'dpo_type','type'=>'select'],['id'=>'dpo_enabled','type'=>'bool'],['id'=>'dpo_address','type'=>'textarea'],['id'=>'neu','type'=>'text','group'=>'Datenschutz']]];
$parsed=Admin::parseFields($schema,['company'=>'A','representatives'=>[['id'=>'person-1','name'=>' Eva '],['id'=>'','name'=>'Neu'],['id'=>'','name'=>'']],'dpo_enabled'=>'1','dpo_address'=>"Max Muster\nWeg 1",'neu'=>'x'],['dpo_name'=>'Alt','dpo_type'=>'internal']);
check($parsed['representatives'][0]===['id'=>'person-1','name'=>'Eva']&&count($parsed['representatives'])===2&&preg_match('/^person-[a-f0-9]{8}$/',$parsed['representatives'][1]['id']),'Personenliste aus Zeilen, leere Zeile entfällt, neue id');
check(Admin::parseFields(['fields'=>[['id'=>'r','type'=>'list']]],['r'=>'[{"id":"a","name":"B"}]'],[])['r']===[['id'=>'a','name'=>'B']],'Liste als JSON weiter lesbar');
check($parsed['dpo_type']==='internal'&&$parsed['dpo_name']==='Max Muster','Doppelfelder aus Nachfolger abgeleitet');
check(Admin::parseFields($schema,['dpo_address'=>''],['dpo_type'=>'external'])['dpo_type']==='none','DSB aus setzt Typ auf keiner');
$steps=Admin::layout($schema['fields']);$ids=[];foreach($steps as $st)foreach($st['sections'] as $sec)foreach($sec['items'] as $it)$ids[]=$it[0]['id'];
check(!in_array('dpo_name',$ids,true)&&!in_array('dpo_type',$ids,true)&&in_array('neu',$ids,true)&&count($ids)===5,'Assistent ohne Doppelfelder, unbekannte Felder unter Weitere Angaben');
check(Admin::serviceGroup('hosting_ionos')==='Hosting'&&Admin::serviceGroup('meta_pixel')==='Werbung und Tracking'&&Admin::serviceGroup('unbekannt')==='Weitere','Dienste gruppiert');
check(Admin::cleanText('<p>Text</p><script>alert(1)</script><style>p{}</style>')==='<p>Text</p>','Skript- und Stilblöcke samt Inhalt entfernt');
$shop=[row(1),row(2,'privacy_policy'),row(30,'terms'),row(31,'withdrawal')];check(Store::validManifest($shop),'Manifest mit AGB und Widerruf gültig');
check(Store::validManifest([row(1),row(2,'privacy_policy'),row(32,'kuenftig')]),'Unbekannte Textart wird übersprungen');check(!Store::validManifest([row(32,'kuenftig')]),'Nur unbekannte Textarten abgelehnt');
$GLOBALS['admin']=false;queueSync($shop);check((new Client())->sync(),'Abgleich mit AGB und Widerruf');unset($GLOBALS['shortcode_tags']['erecht24']);Module::activate();check(strpos(Module::shortcode(['type'=>'terms']),'<h1>Demo</h1>')!==false&&strpos(Module::shortcode(['type'=>'withdrawal','strip_title'=>'true']),'Demo-Text')!==false,'Shortcode zeigt AGB und Widerruf');
$mk=function($d){return 'BBL1-'.rtrim(strtr(base64_encode(json_encode($d,JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');};$k64=str_repeat('ab',32);$sid=str_repeat('c',24);
check(Admin::parseCode($mk(['u'=>'https://legal.example.test/','s'=>$sid,'k'=>$k64]))===['https://legal.example.test',$sid,$k64],'Verbindungscode liefert Adresse, Projekt-ID und Schlüssel');
check(Admin::parseCode(" ".substr($c=$mk(['u'=>'https://legal.example.test','s'=>$sid,'k'=>$k64]),0,20)."\n".substr($c,20)." ")!==null,'Verbindungscode mit Umbruch und Leerzeichen');
check(Admin::parseCode($mk(['u'=>'http://legal.example.test','s'=>$sid,'k'=>$k64]))===null&&Admin::parseCode($mk(['u'=>'https://legal.example.test','s'=>'x','k'=>$k64]))===null&&Admin::parseCode($mk(['u'=>'https://legal.example.test','s'=>$sid,'k'=>'kurz']))===null&&Admin::parseCode('BBL1-%%%')===null&&Admin::parseCode($k64)===null,'Ungültige Verbindungscodes abgelehnt');
echo "$passed Legal-Regressionsprüfungen bestanden\n";
