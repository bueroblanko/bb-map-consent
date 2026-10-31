<?php
// YouTube preview images: off by default, fetched once server-side, served locally.
define('ABSPATH', __DIR__); define('DAY_IN_SECONDS', 86400);
$GLOBALS['opt'] = []; $GLOBALS['tr'] = []; $GLOBALS['calls'] = []; $GLOBALS['reply'] = "\xFF\xD8\xFFjpeg";
$dir = sys_get_temp_dir() . '/bbmc-thumbs-' . getmypid();
function add_action(...$x) {} function add_filter(...$x) {} function add_shortcode(...$x) {}
function wp_parse_url($u,$p) {return parse_url($u,$p);} function get_privacy_policy_url(){return '/datenschutz/';}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);} function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES);} function wp_json_encode($s){return json_encode($s);}
function get_option($k,$d=false){return $GLOBALS['opt'][$k] ?? $d;}
function wp_upload_dir(){global $dir; return ['basedir'=>$dir,'baseurl'=>'https://site.test/wp-content/uploads','error'=>false];}
function get_transient($k){return $GLOBALS['tr'][$k] ?? false;} function set_transient($k,$v,$t){$GLOBALS['tr'][$k]=$v;}
function wp_safe_remote_get($u,$a){$GLOBALS['calls'][]=$u; return ['code'=>$GLOBALS['reply'] ? 200 : 404,'body'=>(string)$GLOBALS['reply']];}
function is_wp_error($r){return false;} function wp_remote_retrieve_body($r){return $r['body'];} function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_mkdir_p($d){return is_dir($d) || mkdir($d,0777,true);}
require __DIR__.'/../bb-map-consent.php';
function thumb_check($v,$m){ if(!$v) throw new Exception('Thumbs: '.$m); }
$iframe = '<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/GlKQB-2m4Wg?rel=0"></iframe>';
thumb_check(strpos(BB_Map_Consent::filter_html($iframe),'bbmc-thumb')===false && !$GLOBALS['calls'], 'off by default');
$GLOBALS['opt']['bbmc_yt_thumbs'] = true;
$out = BB_Map_Consent::filter_html($iframe);
thumb_check(strpos($out,'class="bbmc-placeholder bbmc-thumb" style="--bbmc-thumb:url(https://site.test/wp-content/uploads/bb-consent/yt/GlKQB-2m4Wg.jpg)"')!==false, 'local url');
thumb_check($GLOBALS['calls']===['https://i.ytimg.com/vi/GlKQB-2m4Wg/hqdefault.jpg'] && is_file($dir.'/bb-consent/yt/GlKQB-2m4Wg.jpg'), 'fetched once');
BB_Map_Consent::filter_html($iframe);
thumb_check(count($GLOBALS['calls'])===1, 'cached file reused');
$GLOBALS['reply'] = '<html>not an image';
$bad = BB_Map_Consent::filter_html('<iframe src="https://www.youtube.com/embed/abcdefgh"></iframe>');
thumb_check(strpos($bad,'bbmc-thumb')===false && !empty($GLOBALS['tr']['bbmc_yt_fail_abcdefgh']), 'non-image rejected');
BB_Map_Consent::filter_html('<iframe src="https://www.youtube.com/embed/abcdefgh"></iframe>');
thumb_check(count($GLOBALS['calls'])===2, 'failure remembered');
thumb_check(strpos(BB_Map_Consent::filter_html('<iframe src="https://www.google.com/maps/embed?pb=abc"></iframe>'),'bbmc-thumb')===false, 'maps untouched');
array_map('unlink', glob($dir.'/bb-consent/yt/*')); @rmdir($dir.'/bb-consent/yt'); @rmdir($dir.'/bb-consent'); @rmdir($dir);
echo "Thumbs: off by default, local copy, single fetch, non-image and failure handling passed\n";
