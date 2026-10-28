<?php
require __DIR__.'/filter.php';
function get_option($name, $default = false) { return $GLOBALS['settings'] ?? $default; }
function et_get_option($name, $default = '') { return $GLOBALS['legacy_key'] ?? $default; }
function key_check($expected) { if (BB_Map_Consent::divi_api_key() !== $expected) throw new Exception('Divi key regression'); }
$GLOBALS['settings']=['api_key'=>' global-test-key ']; key_check('global-test-key');
$GLOBALS['settings']=false; key_check('');
$GLOBALS['settings']=['api_key'=>['invalid']]; key_check('');
$GLOBALS['legacy_key']='legacy-test-key'; key_check('legacy-test-key');
// Declare at runtime to test environments both with and without Divi's helper.
if (true) { function et_pb_get_google_api_key() { return $GLOBALS['helper_key'] ?? ''; } }
$GLOBALS['helper_key']=' helper-test-key '; key_check('helper-test-key');
$GLOBALS['helper_key']=''; $GLOBALS['settings']=['api_key'=>'global-test-key']; key_check('global-test-key');
$GLOBALS['helper_key']=false; key_check('global-test-key');
$box=BB_Map_Consent::divi(false,'et_pb_map',['map_center_lat'=>'49.4','map_center_lng'=>'8.6'],[]);
$config=json_decode(BB_Map_Consent::attribute($box,'data-bbmc'),true);
if ($config['key']!=='global-test-key' || strpos($box,'<script')!==false) throw new Exception('Divi config regression');
echo "Divi API key: helper, global option, legacy, empty/invalid values and blocked map config passed\n";
