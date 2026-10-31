<?php
// Content loaded via admin-ajax from public pages (e.g. Divi FilterGrid "load more") gets placeholders too.
require __DIR__.'/filter.php';
function home_url($p=''){return 'https://site.test'.$p;} function wp_unslash($v){return $v;}
function ajax_check($v,$m){ if(!$v) throw new Exception('Ajax: '.$m); }
$iframe = '<figure class="dp-dfg-image entry-video"><iframe width="560" height="315" src="https://www.youtube.com/embed/abc123"></iframe></figure>';
$json = json_encode(['success'=>true,'data'=>['items'=>[['html'=>$iframe]],'count'=>1]]);
$out = BB_Map_Consent::filter_ajax($json);
$data = json_decode($out, true);
ajax_check(is_array($data) && $data['data']['count']===1 && $data['success']===true, 'json kept');
ajax_check(strpos($data['data']['items'][0]['html'],'<iframe')===false && strpos($data['data']['items'][0]['html'],'data-service="youtube"')!==false, 'iframe in json replaced');
ajax_check(strpos(BB_Map_Consent::filter_ajax($iframe),'data-service="youtube"')!==false, 'plain html replaced');
ajax_check(BB_Map_Consent::filter_ajax('{"ok":1}')==='{"ok":1}' && BB_Map_Consent::filter_ajax('0')==='0', 'unrelated untouched');
$_SERVER['HTTP_REFERER']='https://site.test/azubi-videos/'; ajax_check(BB_Map_Consent::frontend_ajax(), 'front referer');
$_SERVER['HTTP_REFERER']='https://site.test/wp-admin/post.php?post=1'; ajax_check(!BB_Map_Consent::frontend_ajax(), 'admin referer');
$_SERVER['HTTP_REFERER']='https://site.test/seite/?et_fb=1'; ajax_check(!BB_Map_Consent::frontend_ajax(), 'divi builder');
$_SERVER['HTTP_REFERER']='https://evil.test/'; ajax_check(!BB_Map_Consent::frontend_ajax(), 'other host');
unset($_SERVER['HTTP_REFERER']); ajax_check(!BB_Map_Consent::frontend_ajax(), 'no referer');
echo "Ajax: JSON and HTML responses, untouched other responses, front-end detection passed\n";
