<?php
require __DIR__ . '/filter.php';
function plugin_basename($path) { return 'bb-map-consent/' . basename($path); }
function get_site_transient($key) { return $GLOBALS['release_cache'] ?? false; }
function set_site_transient($key, $value, $ttl) { $GLOBALS['release_cache'] = $value; }
function wp_remote_get(...$args) { $GLOBALS['requests']++; return $GLOBALS['http']; }
function is_wp_error($r) { return $r instanceof Exception; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60);
function check($condition) { if (!$condition) { throw new Exception('Updater test failed'); } }
$release = ['tag_name'=>'v0.3.0','draft'=>false,'prerelease'=>false,'assets'=>[['state'=>'uploaded','browser_download_url'=>BBMC_Updater::URI.'/releases/download/v0.3.0/bb-map-consent-0.3.0.zip']]];
check(BBMC_Updater::parse_release($release)['version']==='0.3.0');
foreach (['draft','prerelease'] as $flag) { $bad=$release; $bad[$flag]=true; check(BBMC_Updater::parse_release($bad)===[]); }
$bad=$release; $bad['assets'][0]['browser_download_url']='https://evil.example/plugin.zip'; check(BBMC_Updater::parse_release($bad)===[]);
$bad=$release; $bad['assets']=[]; check(BBMC_Updater::parse_release($bad)===[]);
$bad=$release; $bad['tag_name']='v0.3.0-beta'; check(BBMC_Updater::parse_release($bad)===[]);
check(BBMC_Updater::parse_release(null)===[]);
$GLOBALS['requests']=0; $GLOBALS['http']=['code'=>200,'body'=>json_encode($release)];
$metadata=['UpdateURI'=>BBMC_Updater::URI];
check(BBMC_Updater::update(false,$metadata,'other/plugin.php',[])===false);
check($GLOBALS['requests']===0);
check(BBMC_Updater::update(false,$metadata,'bb-map-consent/bb-map-consent.php',[])['version']==='0.3.0');
BBMC_Updater::release(); check($GLOBALS['requests']===1);
unset($GLOBALS['release_cache']); $GLOBALS['http']=['code'=>403,'body'=>'{}']; check(BBMC_Updater::release()===[]);
unset($GLOBALS['release_cache']); $GLOBALS['http']=new Exception('Timeout'); check(BBMC_Updater::release()===[]);
echo "Updater: valid release, draft, prerelease, invalid asset/tag, foreign plugin, caching, HTTP failure and timeout passed\n";
function current_user_can($capability) { return $GLOBALS['can_update'] ?? false; }
function admin_url($path) { return 'https://example.com/wp-admin/'.$path; }
function wp_nonce_url($url, $action) { return $url.'&_wpnonce=test'; }
$GLOBALS['can_update']=false;
check(BBMC_Updater::links([], 'bb-map-consent/bb-map-consent.php')===[]);
$GLOBALS['can_update']=true;
check(count(BBMC_Updater::links([], 'bb-map-consent/bb-map-consent.php'))===1);
check(BBMC_Updater::links([], 'other/plugin.php')===[]);
echo "Manual update link: permissions and plugin identity passed\n";
