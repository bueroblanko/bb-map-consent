<?php
require __DIR__.'/filter.php';
function dw_check($condition, $label) { if (!$condition) throw new Exception('DealerWeb regression failed: '.$label); }
function dw_config($html) {
 preg_match('/data-bbmc="([^"]*)"/', $html, $m);
 return json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true);
}
$banner='<iframe src="https://www.dealerweb-comarketing.de/aktionsbanner/DE45FE" height="300px" width="800px" frameborder="0" scrolling="no"></iframe>';
$out=BB_Map_Consent::filter_html($banner);
$config=dw_config($out);
dw_check(strpos($out,'<iframe')===false && strpos($out,'data-service="dealerweb"')!==false, 'banner blocked');
dw_check($config['src']==='https://www.dealerweb-comarketing.de/aktionsbanner/DE45FE' && $config['height']===300 && $config['width']===800, 'banner config');
dw_check(dw_config(BB_Map_Consent::filter_html('<iframe src="http://dealerweb-comarketing.de/aktionsbanner/X1"></iframe>'))['src']==='https://dealerweb-comarketing.de/aktionsbanner/X1', 'http upgraded');
dw_check(strpos(BB_Map_Consent::filter_html('<iframe data-src="//www.dealerweb-comarketing.de/aktionsbanner/X1"></iframe>'),'<iframe')===false, 'protocol-relative lazy iframe');
$evil='<iframe src="https://www.dealerweb-comarketing.de.evil.example/aktionsbanner/X1"></iframe>';
dw_check(BB_Map_Consent::filter_html($evil)===$evil, 'foreign host unchanged');
dw_check(BB_Map_Consent::filter_html('<link rel="preconnect" href="https://www.dealerweb-comarketing.de"><p>OK</p>')==='<p>OK</p>', 'resource hint removed');

// Divi code module hidden on tablet/phone: the content is duplicated as JSON for innerHTML swaps.
$multi=json_encode(['schema'=>['content'=>['desktop'=>' '.$banner.' ','tablet'=>'','phone'=>'']],'slug'=>'et_pb_code']);
$module='<div class="et_pb_code_inner" data-et-multi-view="'.htmlspecialchars($multi,ENT_QUOTES).'" data-et-multi-view-load-tablet-hidden="true"> '.$banner.' </div>';
$out=BB_Map_Consent::filter_html($module);
preg_match('/data-et-multi-view="([^"]*)"/', $out, $m);
$data=json_decode(html_entity_decode($m[1],ENT_QUOTES),true);
$desktop=$data['schema']['content']['desktop'];
dw_check(strpos($out,'<iframe')===false && substr_count($out,'data-service="dealerweb"')===1, 'visible module content blocked');
dw_check(strpos($desktop,'<iframe')===false && strpos($desktop,'data-service="dealerweb"')!==false, 'multi-view content blocked');
dw_check(dw_config($desktop)['src']==='https://www.dealerweb-comarketing.de/aktionsbanner/DE45FE', 'multi-view payload intact');
dw_check($data['slug']==='et_pb_code' && $data['schema']['content']['phone']==='', 'other multi-view data intact');
dw_check(strpos($out,'data-et-multi-view-load-tablet-hidden="true"> <section')!==false, 'tag attributes preserved');
$maps=htmlspecialchars(json_encode(['schema'=>['content'=>['desktop'=>'<iframe src="https://www.google.com/maps/embed?pb=abc"></iframe>']]]),ENT_QUOTES);
preg_match('/data-et-multi-view="([^"]*)"/', BB_Map_Consent::filter_html('<div data-et-multi-view="'.$maps.'"></div>'), $m);
dw_check(strpos(json_decode(html_entity_decode($m[1],ENT_QUOTES),true)['schema']['content']['desktop'],'data-service="maps"')!==false, 'maps in multi-view blocked');
$plain='<div class="et_pb_text_inner" data-et-multi-view="'.htmlspecialchars(json_encode(['schema'=>['content'=>['desktop'=>'<p>Hallo</p>']]]),ENT_QUOTES).'"><p>Hallo</p></div>';
dw_check(BB_Map_Consent::filter_html($plain)===$plain, 'unrelated multi-view unchanged');
$comment='<!-- <div data-et-multi-view="'.htmlspecialchars($multi,ENT_QUOTES).'"></div> -->';
dw_check(BB_Map_Consent::filter_html($comment)===$comment, 'comment unchanged');
// Divi's tablet/phone variants use percentage widths: no pixel limit, banner stays full width.
$percent=BB_Map_Consent::filter_html('<iframe src="https://www.dealerweb-comarketing.de/aktionsbanner/DE45FE" height="150px" width="100%"></iframe>');
dw_check(dw_config($percent)['width']===0 && strpos($percent,'max-width')===false, 'percentage width is full width');
dw_check(strpos(BB_Map_Consent::filter_html($banner),'max-width:800px;margin-left:auto;margin-right:auto;')!==false, 'fixed banner centred');
echo "DealerWeb: banner iframe, URL variants, foreign hosts, resource hints and Divi multi-view content passed\n";
