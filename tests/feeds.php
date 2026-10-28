<?php
require __DIR__.'/filter.php';
function feed_check($value) { if (!$value) throw new Exception('Feed regression failed'); }
$feed='<div id="sb_instagram"><div><img src="https://cdninstagram.com/pic.jpg"><div>Text</div></div></div>';
$html='<main>'.$feed.'<p>After</p></main><script id="sbi_scripts-js-extra">var settings={};</script><script src="https://example.com/wp-content/plugins/instagram-feed/js/sbi-scripts.min.js"></script>';
$out=BB_Map_Consent::filter_html($html);
feed_check(strpos($out,'<img')===false && strpos($out,'id="sb_instagram"')===false);
feed_check(strpos($out,'<p>After</p></main>')!==false);
feed_check(substr_count($out,'class="bbmc-feed-script"')===2);
preg_match('/data-bbmc="([^"]*)"/', $out, $m);
$config=json_decode(html_entity_decode($m[1], ENT_QUOTES),true);
feed_check($config['kind']==='smashballoon' && base64_decode($config['html'])===$feed);
$out=BB_Map_Consent::filter_html($feed.$feed);
feed_check(substr_count($out,'data-service="instagram"')===2);
$ordinary='<div><div>Not a feed</div></div><script id="sbi_scripts-js">normal</script>';
feed_check(BB_Map_Consent::filter_html($ordinary)===$ordinary);
echo "Feeds: nested containers, scripts, multiple feeds, unchanged surroundings and exact restore payload passed\n";
