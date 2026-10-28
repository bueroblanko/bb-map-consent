<?php
require __DIR__.'/filter.php';
function plugin_basename($path) { return ($GLOBALS['installed_directory'] ?? 'bb-map-consent') . '/bb-map-consent.php'; }
class WP_Error { public $code; public function __construct($code,$message) { $this->code=$code; } }
class BBMC_Test_FS {
 public $moves=[]; public $conflict=false; public $fail=false;
 public function exists($path) { return $this->conflict; }
 public function move($from,$to) { $this->moves[]=[$from,$to]; return !$this->fail; }
}
function directory_check($condition) { if (!$condition) throw new Exception('Update directory regression'); }
$root='/wordpress/wp-content/upgrade/release/';
foreach (['bb-consent','bb-map-consent','bb-map-consent-main','bb-map-consent-0.5.3'] as $directory) {
 $GLOBALS['installed_directory']=$directory; $wp_filesystem=new BBMC_Test_FS();
 $hook=['plugin'=>$directory.'/bb-map-consent.php'];
 $result=BBMC_Updater::preserve_directory($root.'bb-map-consent/',$root,null,$hook);
 directory_check($result===$root.$directory.'/');
 directory_check(count($wp_filesystem->moves)===($directory==='bb-map-consent'?0:1));
}
$hook=['plugin'=>'bb-map-consent-0.5.3/bb-map-consent.php'];
$wp_filesystem->conflict=true;
directory_check(BBMC_Updater::preserve_directory($root.'bb-map-consent/',$root,null,$hook) instanceof WP_Error);
$wp_filesystem=new BBMC_Test_FS(); $wp_filesystem->fail=true;
directory_check(BBMC_Updater::preserve_directory($root.'bb-map-consent/',$root,null,$hook) instanceof WP_Error);
directory_check(BBMC_Updater::preserve_directory('original',$root,null,['plugin'=>'other/plugin.php'])==='original');
$error=new WP_Error('existing','Existing failure');
directory_check(BBMC_Updater::preserve_directory($error,$root,null,$hook)===$error);
echo "Update folders: canonical, GitHub-main, versioned installations, conflicts, move failure, other plugins and existing errors passed\n";
