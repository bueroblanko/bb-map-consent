<?php
require __DIR__.'/filter.php';
function elfsight_check($condition) { if (!$condition) throw new Exception('Elfsight regression failed'); }
$script='<script src="https://static.elfsight.com/platform/platform.js" data-use-service-core defer></script>';
$widget='<div class="elfsight-app-12345678-abcd-1234-abcd-123456789abc" data-elfsight-app-lazy><div><img src="https://pbs.twimg.com/preview.jpg"></div></div>';
$html='<!doctype html><html><head>'.$script.'</head><body><p>Before</p>'.$widget.'<p>After</p>'.$script.'</body></html>';
$out=BB_Map_Consent::filter_html($html);
elfsight_check(strpos($out,'<script')===false && strpos($out,'<img')===false && strpos($out,'class="elfsight-app-')===false);
elfsight_check(substr_count($out,'class="bbmc-feed-script"')===1);
elfsight_check(substr_count($out,'data-bbmc=')===1 && strpos($out,'data-service="elfsight"')!==false);
preg_match('/data-bbmc="([^"]+)"/',$out,$m);$c=json_decode(html_entity_decode($m[1],ENT_QUOTES),true);
elfsight_check($c['kind']==='html-scripts' && base64_decode($c['html'])===$widget);
elfsight_check(strpos($out,'<p>Before</p>')!==false && strpos($out,'<p>After</p>')!==false);
$only=BB_Map_Consent::filter_html('<html><head>'.$script.'</head><body>Content</body></html>');
elfsight_check(strpos($only,'data-bbmc=')>strpos($only,'</head>') && substr_count($only,'data-bbmc=')===1);
$foreign='<script src="https://static.elfsight.com.evil.example/platform/platform.js"></script>';
elfsight_check(BB_Map_Consent::filter_html($foreign)===$foreign);
elfsight_check(BB_Map_Consent::filter_html('<link rel="preload" as="script" href="https://static.elfsight.com/platform/platform.js">')==='');
elfsight_check(BB_Map_Consent::filter_html('<script>const sample="'.$widget.'";</script>')==='<script>const sample="'.$widget.'";</script>');
echo "Elfsight: nested widget, media, SDK, duplicate loaders, head-only loader, hints and unrelated code passed\n";
if (getenv('BBMC_ELFSIGHT_FIXTURE')) file_put_contents(getenv('BBMC_ELFSIGHT_FIXTURE'),str_replace('</body>','<script src="https://test.local/maps.js"></script></body>',$out));
