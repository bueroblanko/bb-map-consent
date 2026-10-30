<?php
/** Local test fixture, never distributed. Intercepts every legal central request. */
if(!defined('BB_LEGAL_TEST_ENV') || BB_LEGAL_TEST_ENV!==true) { return; }
add_action('init',function(){ if(get_option('bb_legal_test_foreign',false)) { add_shortcode('erecht24',function(){return '<p>Fremder Demo-Anbieter</p>';}); } },99);
add_filter('pre_http_request',function($pre,$args,$url){
 if(strpos($url,'https://legal.bbkd.de/')!==0)return $pre;
 if(get_option('bb_legal_test_offline'))return new WP_Error('offline','Synthetic offline test');
 $site=get_option('bb_legal_site_id');$key=BB\Legal\V1\Store::key();$parts=parse_url($url);$target=$parts['path'].(isset($parts['query'])?'?'.$parts['query']:'');$h=$args['headers'];$expected=hash_hmac('sha256',$args['method']."\n".$target."\n".$h['X-BB-Timestamp']."\n".$args['body'],$key);
 if(!hash_equals($expected,$h['X-BB-Signature']))return new WP_Error('signature','Synthetic signature error');
 $resource=basename($parts['path']);
 if(strpos($parts['path'],'/segments/')!==false){
  if($args['method']==='PUT'){update_option('bb_legal_test_last_segments',json_decode($args['body'],true),false);$json=['status'=>'ok'];}
  else $json=['segments'=>[['kind'=>'insert','key'=>'_start','html'=>'<p>Eigener Anfang</p>'],['kind'=>'block','key'=>'imprint_company/de','id'=>1,'html'=>'<h2>Anbieter</h2><p>Neutraler Testtext imprint</p>','customized'=>false],['kind'=>'block','key'=>'imprint_contact/de','id'=>2,'html'=>'<p>Kunde hat das angepasst</p>','customized'=>true]],'customization'=>['blocks'=>[],'inserts'=>[]]];
 }
 elseif($resource==='legal')$json=['outputs'=>get_option('bb_legal_test_outputs',[])];
 elseif($resource==='ack'){$json=['status'=>'ok'];update_option('bb_legal_test_last_ack',json_decode($args['body'],true));}
 elseif($resource==='inputs'&&$args['method']==='PUT'){$body=json_decode($args['body'],true);$json=['status'=>'merged','version'=>4,'inputs'=>$body['inputs'],'conflicts'=>[]];}
 else $json=['inputs'=>['company'=>'Demo Betrieb','services'=>[]],'version'=>1,'schema'=>['payload'=>['fields'=>[['id'=>'company','label'=>['de'=>'Firma'],'type'=>'text'],['id'=>'services','label'=>['de'=>'Dienste'],'type'=>'multiselect','options'=>['youtube'=>'YouTube']]]]]];
 return ['headers'=>[],'response'=>['code'=>200,'message'=>'OK'],'body'=>wp_json_encode($json),'cookies'=>[]];
},10,3);
