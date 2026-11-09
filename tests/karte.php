<?php
// [bb_karte]: colours like bb-map.js, placeholder built once from vector tiles, visitors never fetch.
define('ABSPATH', __DIR__); define('DAY_IN_SECONDS', 86400); define('HOUR_IN_SECONDS', 3600);
$GLOBALS['opt'] = []; $GLOBALS['tr'] = []; $GLOBALS['calls'] = []; $GLOBALS['editor'] = false; $GLOBALS['cron'] = []; $GLOBALS['scripts'] = []; $GLOBALS['tile'] = '';
$dir = sys_get_temp_dir() . '/bbmc-karte-' . getmypid();
function add_action(...$x) {} function add_filter(...$x) {} function add_shortcode(...$x) {}
function shortcode_atts($d,$a){ $a=(array)$a; foreach($d as $k=>$v){ if(isset($a[$k])) $d[$k]=$a[$k]; } return $d; }
function wp_parse_url($u,$p) {return parse_url($u,$p);} function get_privacy_policy_url(){return 'https://site.test/datenschutz/';}
function home_url($p=''){return 'https://site.test'.$p;} function plugins_url($p,$f){return 'https://site.test/wp-content/plugins/bb-consent/'.$p;}
function esc_url($s,$p=null){ if($p && !preg_match('~^https://~',$s)) return ''; return htmlspecialchars($s,ENT_QUOTES);} function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES);} function wp_json_encode($s){return json_encode($s);}
function get_option($k,$d=false){return $GLOBALS['opt'][$k] ?? $d;}
function current_user_can($c){return $GLOBALS['editor'];}
function wp_enqueue_script($h,$s){$GLOBALS['scripts'][$h]=$s;}
function wp_upload_dir(){global $dir; return ['basedir'=>$dir,'baseurl'=>'https://site.test/wp-content/uploads','error'=>false];}
function get_transient($k){return $GLOBALS['tr'][$k] ?? false;} function set_transient($k,$v,$t){$GLOBALS['tr'][$k]=$v;}
function wp_next_scheduled($h,$a){return isset($GLOBALS['cron'][md5(json_encode($a))]);} function wp_schedule_single_event($t,$h,$a){$GLOBALS['cron'][md5(json_encode($a))]=$a;}
function wp_safe_remote_get($u,$a){
    $GLOBALS['calls'][]=$u;
    if ($u === 'https://tiles.openfreemap.org/planet') { return ['code'=>200,'body'=>json_encode(['tiles'=>[$GLOBALS['template']]])]; }
    return ['code'=>$GLOBALS['tile'] === '' ? 404 : 200,'body'=>$GLOBALS['tile']];
}
function is_wp_error($r){return false;} function wp_remote_retrieve_body($r){return $r['body'];} function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_mkdir_p($d){return is_dir($d) || mkdir($d,0777,true);}
require __DIR__.'/../bb-map-consent.php';
function karte_check($v,$m){ if(!$v) throw new Exception('Karte: '.$m); }

// Minimal protobuf writer for a synthetic tile.
function pv($n){ $o=''; do { $b=$n&0x7f; $n>>=7; $o.=chr($b|($n?0x80:0)); } while($n); return $o; }
function pf($field,$bytes){ return pv($field<<3|2).pv(strlen($bytes)).$bytes; }
function pn($field,$n){ return pv($field<<3).pv($n); }
function zz($n){ return ($n<<1)^($n>>63); }
function feature($type,$value,array $geometry){ return pf(2, pf(2,pv(0).pv($value)).pn(3,$type).pf(4,implode('',array_map('pv',$geometry)))); }
function layer($name,array $values,$features){ $v=''; foreach($values as $x){ $v.=pf(4,pf(1,$x)); } return pf(3, pf(1,$name).$features.pf(3,'class').$v.pn(5,4096).pn(15,2)); }
$line = [1<<3|1, zz(0), zz(2048), 2<<3|2, zz(4096), zz(0), zz(0), zz(100)];            // across the tile, then a short turn
$square = [1<<3|1, zz(1000), zz(1000), 3<<3|2, zz(2000), zz(0), zz(0), zz(2000), zz(-2000), zz(0), 15]; // closed ring
$GLOBALS['tile'] = layer('transportation', ['primary','path','rail'], feature(2,0,$line).feature(2,1,$line).feature(2,2,$line))
    . layer('water', ['lake'], feature(3,0,$square))
    . layer('poi', ['shop'], feature(1,0,[1<<3|1, zz(5), zz(5)]));
$GLOBALS['template'] = 'https://tiles.openfreemap.org/planet/20260101_000000_pt/{z}/{x}/{y}.pbf';

// Same palette as farben() in bb-map.js (values taken from the JavaScript).
karte_check(BBMC_Karte::colours(['land'=>'#18243e','wasser'=>'#4b5269','strasse'=>'#a48463']) === ['land'=>'#18243e','wasser'=>'#4b5269','gruen'=>'#242f48','strasse'=>'#a48463','nebenstrasse'=>'#504a4d','bahn'=>'#465065'], 'dark palette');
karte_check(BBMC_Karte::colours(['land'=>'javascript:x','strasse'=>'#FFF']) === ['land'=>'#f2f1ed','wasser'=>'#becfdb','gruen'=>'#dde3d6','strasse'=>'#ffffff','nebenstrasse'=>'#f7f7f4','bahn'=>'#cac9c6'], 'default palette, invalid colour ignored');

// Decoder.
$layers = BBMC_Karte::layers($GLOBALS['tile']);
karte_check(array_column($layers,'name') === ['transportation','water'], 'only drawn layers');
karte_check(array_column($layers[0]['features'],1) === ['primary','path','rail'], 'class per feature');
karte_check(BBMC_Karte::rings($square) === [[[1000,1000],[3000,1000],[3000,3000],[1000,3000]]], 'ring');
karte_check(BBMC_Karte::thin([[0,0],[5,0.1],[10,0],[10,10]],0.5) === [[0,0],[10,0],[10,10]], 'thinning');
$bad = false; try { BBMC_Karte::layers("\x1a\xff\xff"); } catch (Throwable $e) { $bad = true; }
karte_check($bad, 'truncated tile rejected');

// Visitor: nothing fetched, placeholder scheduled, element complete.
$atts = ['lat'=>'50.119483','lng'=>'8.682741','zoom'=>'13','land'=>'#18243e','strasse'=>'#a48463','titel'=>'A "B"','route'=>'https://maps.app.goo.gl/x'];
$out = BBMC_Karte::shortcode($atts);
karte_check(!$GLOBALS['calls'] && count($GLOBALS['cron']) === 1 && strpos($out,'platzhalter=') === false, 'visitor does not fetch');
karte_check(strpos($out,'<bb-map lat="50.119483" lng="8.682741" zoom="13" basis="https://site.test/wp-content/plugins/bb-consent/assets/karte/" datenschutz="https://site.test/datenschutz/"') === 0, 'element');
karte_check(strpos($out,'--bb-map-land:#18243e;--bb-map-strasse:#a48463;--bb-map-akzent:var(--bbmc-site-accent);') !== false && strpos($out,'titel="A &quot;B&quot;"') !== false, 'colours and escaped title');
karte_check(isset($GLOBALS['scripts']['bb-karte']) && strpos($out,'<a href="https://maps.app.goo.gl/x"') !== false, 'script and no-script route link');
BBMC_Karte::shortcode($atts);
karte_check(count($GLOBALS['cron']) === 1, 'scheduled once');

// Background build (or an editor) writes the SVG; afterwards it is served locally without fetching.
$job = array_values($GLOBALS['cron'])[0];
karte_check(BBMC_Karte::build(...$job), 'build');
$files = glob($dir.'/bb-consent/karten/karte-*.svg');
$svg = file_get_contents($files[0]);
karte_check(count($files) === 1 && strpos($svg,'<svg xmlns="http://www.w3.org/2000/svg" width="2800" height="900"') === 0, 'svg written');
karte_check(preg_match('~stroke="#a48463" stroke-width="2.2"~',$svg) && strpos($svg,'fill="#18243e"') !== false && preg_match('~<path d="M[^"]+z" fill="#[0-9a-f]{6}"/>~',$svg), 'road, land and water drawn');
karte_check(!preg_match('~<script|href|url\(~i',$svg), 'svg holds only shapes');
karte_check($GLOBALS['calls'][0] === 'https://tiles.openfreemap.org/planet' && strpos($GLOBALS['calls'][1],'https://tiles.openfreemap.org/planet/20260101_000000_pt/13/') === 0, 'tiles from the published template');
$n = count($GLOBALS['calls']);
$out = BBMC_Karte::shortcode($atts);
karte_check(count($GLOBALS['calls']) === $n && strpos($out,'platzhalter="https://site.test/wp-content/uploads/bb-consent/karten/'.basename($files[0]).'"') !== false, 'cached file reused');

// Other colours are another file; a foreign tile host is refused and remembered.
$GLOBALS['template'] = 'https://evil.example/{z}/{x}/{y}.pbf'; $GLOBALS['editor'] = true;
$out = BBMC_Karte::shortcode(['lat'=>'50.119483','lng'=>'8.682741','land'=>'#ffffff']);
karte_check(strpos($out,'platzhalter=') === false && count(glob($dir.'/bb-consent/karten/*')) === 1 && count($GLOBALS['tr']) === 1, 'foreign template refused');
$n = count($GLOBALS['calls']);
BBMC_Karte::shortcode(['lat'=>'50.119483','lng'=>'8.682741','land'=>'#ffffff']);
karte_check(count($GLOBALS['calls']) === $n, 'failure remembered');

// Bad input.
karte_check(strpos(BBMC_Karte::shortcode(['lat'=>'x','lng'=>'8']),'braucht lat und lng') !== false, 'editor hint');
$GLOBALS['editor'] = false;
karte_check(BBMC_Karte::shortcode(['lat'=>'95','lng'=>'8']) === '', 'visitor sees nothing on bad input');
karte_check(strpos(BBMC_Karte::shortcode(['lat'=>'50','lng'=>'8','route'=>'javascript:alert(1)','hoehe'=>'99999']),'route=') === false, 'unsafe route dropped');
$GLOBALS['opt']['bbmc_cmp_address'] = 'du';
karte_check(strpos(BBMC_Karte::shortcode(['lat'=>'50','lng'=>'8']),' anrede="du"') !== false, 'informal address');
// The page filter leaves the element and its local script alone.
$html = BBMC_Karte::shortcode($atts) . '<script src="https://site.test/wp-content/plugins/bb-consent/assets/karte/bb-map.js"></script>';
karte_check(BB_Map_Consent::filter_html($html) === $html, 'filter leaves the map alone');
array_map('unlink', glob($dir.'/bb-consent/karten/*')); @rmdir($dir.'/bb-consent/karten'); @rmdir($dir.'/bb-consent'); @rmdir($dir);
echo "Karte: palette, tile decoder, background build, local reuse, refused hosts and input checks passed\n";
