<?php
require __DIR__.'/filter.php';
function pl_check($condition, $label) { if (!$condition) throw new Exception('Planity regression failed: '.$label); }
$config="<script>\nvar container = document.getElementById('myContainerId');\nwindow.planity = {key: 'abc', container: container};\n</script>";
$polyfills='<script src="https://d2skjte8udjqxw.cloudfront.net/widget/production/2/polyfills.latest.js"></script>';
$app='<script src="https://d2skjte8udjqxw.cloudfront.net/widget/production/2/app.latest.js"></script>';
$widget='<div id="myContainerId"></div><style>#planitywl{color:#000}</style>'.$config."\n".$polyfills."\n".$app;
$out=BB_Map_Consent::filter_html('<main>'.$widget.'</main>');
pl_check(strpos($out,'<script')===false && strpos($out,'cloudfront.net')===false, 'scripts held back');
pl_check(substr_count($out,'data-service="planity"')===4 && substr_count($out,'<section class="bbmc"')===1, 'one placeholder, three holders');
pl_check(strpos($out,'<div id="myContainerId"></div><style>#planitywl{color:#000}</style><section class="bbmc"')===0 + strlen('<main>'), 'container and placeholder position');
preg_match_all('/data-service="planity" data-script="([^"]*)"/', $out, $m);
pl_check(array_map(function ($v) { return base64_decode(html_entity_decode($v, ENT_QUOTES)); }, $m[1])===[$config,$polyfills,$app], 'exact scripts in original order');
pl_check(BB_Map_Consent::filter_html('<link rel="preconnect" href="https://d2skjte8udjqxw.cloudfront.net"><p>OK</p>')==='<p>OK</p>', 'resource hint removed');
$other='<script src="https://d2skjte8udjqxw.cloudfront.net/other/app.js"></script><script src="https://example.cloudfront.net/widget/app.js"></script><script>var planity = 1;</script>';
pl_check(BB_Map_Consent::filter_html($other)===$other, 'unrelated scripts unchanged');
// Smash Balloon holders stay assigned to Instagram.
$feed=BB_Map_Consent::filter_html('<div id="sb_instagram"></div><script id="sbi_scripts-js-extra">var x;</script>'.$app);
pl_check(strpos($feed,'class="bbmc-feed-script" data-service="instagram"')!==false && strpos($feed,'data-service="planity" data-script=')!==false, 'services kept apart');
echo "Planity: inline configuration, loaders, order, placeholder position, resource hints and unrelated scripts passed\n";
