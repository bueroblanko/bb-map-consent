<?php
require __DIR__.'/filter.php';
function wp_strip_all_tags($value) { return strip_tags(preg_replace('~<(script|style)[^>]*>.*?</\1>~is','',$value)); }
function get_option($name,$default='') { return $GLOBALS['site_css'] ?? $default; }
function get_post_meta($id,$name,$single) { return $GLOBALS['page_css'] ?? ''; }
function is_singular($type) { return $GLOBALS['is_page'] ?? false; }
function get_queried_object_id() { return 123; }
function wp_add_inline_style($handle,$css) { $GLOBALS['inline_styles'][]=[$handle,$css]; }
function wp_is_post_revision($id) { return false; }
function current_user_can($cap,$id=null) { return $GLOBALS['allowed'] ?? false; }
function wp_verify_nonce($nonce,$action) { return $nonce==='valid' && $action==='bbmc_page_css_123'; }
function wp_unslash($value) { return stripslashes($value); }
function update_post_meta($id,$name,$value) { $GLOBALS['saved']=$value; }
function delete_post_meta($id,$name) { $GLOBALS['deleted']=true; }
function css_check($v) { if (!$v) throw new Exception('Custom CSS regression'); }
css_check(BBMC_Custom_CSS::sanitize(['bad'])==='');
css_check(strpos(BBMC_Custom_CSS::sanitize('</style><script>alert(1)</script><style>'),'<')===false);
css_check(BBMC_Custom_CSS::sanitize('.a > .b { color: red; }') === '.a > .b { color: red; }');
$GLOBALS['site_css']='.bbmc { --bbmc-accent: #123456; }';
$GLOBALS['page_css']='.bbmc-placeholder { background: red; }';
$GLOBALS['is_page']=true;
BBMC_Custom_CSS::enqueue();
css_check($GLOBALS['inline_styles'][0]===['bb-map-consent',$GLOBALS['site_css']."\n".$GLOBALS['page_css']]);
$GLOBALS['inline_styles']=[]; $GLOBALS['is_page']=false; BBMC_Custom_CSS::enqueue();
css_check($GLOBALS['inline_styles'][0][1]===$GLOBALS['site_css']);
$_POST=['bbmc_page_css_nonce'=>'valid','bbmc_page_css'=>'.bbmc { color: red; }'];
BBMC_Custom_CSS::save_page(123); css_check(!isset($GLOBALS['saved']));
$GLOBALS['allowed']=true; $_POST['bbmc_page_css_nonce']='bad'; BBMC_Custom_CSS::save_page(123); css_check(!isset($GLOBALS['saved']));
$_POST['bbmc_page_css_nonce']='valid'; BBMC_Custom_CSS::save_page(123); css_check($GLOBALS['saved']==='.bbmc { color: red; }');
$_POST['bbmc_page_css']=''; BBMC_Custom_CSS::save_page(123); css_check($GLOBALS['deleted']===true);
unset($GLOBALS['deleted']); $_POST=[]; BBMC_Custom_CSS::save_page(123); css_check(!isset($GLOBALS['deleted']));
echo "Custom CSS: markup removal, site/page cascade, permissions, nonce, save, delete and untouched absent fields passed\n";
