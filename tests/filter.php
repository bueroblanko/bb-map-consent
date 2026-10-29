<?php
 define('ABSPATH', __DIR__);
 function add_action(...$x) {} function add_filter(...$x) {} function add_shortcode(...$x) {}
 function wp_parse_url($u,$p) {return parse_url($u,$p);} function get_privacy_policy_url(){return '/datenschutz/';}
 function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);} function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES);} function wp_json_encode($s){return json_encode($s);}
 require __DIR__.'/../bb-map-consent.php';
 $cases = [
 ['<iframe src="https://snazzymaps.com/embed/507314" width="100%" height="400px" style="border:none;"></iframe>', 'data-bbmc=', '<iframe'],
 ['<iframe src="//www.snazzymaps.com/embed/507314"></iframe>', 'data-bbmc=', '<iframe'],
 ['<iframe src="https://snazzymaps.com.evil.example/embed/507314"></iframe>', '<iframe', 'data-bbmc='],
 ['<iframe src="https://www.google.com/maps/embed?pb=abc" height="450"></iframe>', 'data-bbmc=', '<iframe'],
 ["<iframe src='//maps.google.de/maps?q=Berlin&amp;output=embed'></iframe>", 'data-bbmc=', '<iframe'],
 ['<iframe data-src="https://www.google.com/maps/embed?pb=abc"></iframe>', 'data-bbmc=', '<iframe'],
 ['<script src="https://maps.googleapis.com/maps/api/js?key=x"></script><p>Hallo</p>', '<p>Hallo</p>', '<script'],
 ['<link rel="preconnect" href="https://maps.gstatic.com"><p>OK</p>', '<p>OK</p>', '<link'],
 ['<iframe src="https://player.vimeo.com/video/123"></iframe>', 'player.vimeo.com', 'data-bbmc='],
 ['<script>const x = "<iframe src=bad>";</script>', 'const x', 'data-bbmc='],
 ['<!-- <iframe src="https://google.com/maps/embed"></iframe> -->', '<!--', 'data-bbmc=']
 ];
 $small=BB_Map_Consent::filter_html('<iframe src="https://www.google.com/maps/embed?pb=abc" height="170"></iframe>');
 if(strpos($small,'class="bbmc bbmc-compact"')===false || strpos($small,'style="--bbmc-h:170px;"')===false || strpos($small,'class="bbmc-short"')===false){throw new Exception('Compact placeholder failed: '.$small);}
 if(strpos(BB_Map_Consent::filter_html('<iframe src="https://www.google.com/maps/embed?pb=abc" height="450"></iframe>'),'bbmc-compact')!==false){throw new Exception('Large map marked compact');}
 foreach($cases as $i=>$c){$o=BB_Map_Consent::filter_html($c[0]); if(strpos($o,$c[1])===false || strpos($o,$c[2])!==false){throw new Exception('Case '.$i.' failed: '.$o);}}
 echo count($cases)." server-side regression checks passed\n";
