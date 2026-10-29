<?php
require __DIR__.'/filter.php';
function embed_check($value) { if (!$value) throw new Exception('YouTube/Tramino regression'); }
$cases=[
 ['https://ferienwohnung-winkel.tramino.de/api/widgets/rating/','tramino',400],
 ['https://www.youtube-nocookie.com/embed/GlKQB-2m4Wg?feature=oembed','youtube',608],
 ['//www.youtube.com/embed/GlKQB-2m4Wg','youtube',608]
];
$html='<!doctype html><html><body>';
foreach ($cases as [$url,$service,$height]) {
 $iframe='<iframe loading="lazy" width="100%" height="'.$height.'" title="Test" src="'.$url.'" allowfullscreen></iframe>';
 $out=BB_Map_Consent::filter_html($iframe);
 embed_check(strpos($out,'<iframe')===false && strpos($out,'data-service="'.$service.'"')!==false);
 preg_match('/data-bbmc="([^"]+)"/',$out,$m);$c=json_decode(html_entity_decode($m[1],ENT_QUOTES),true);
 embed_check($c['src']===$url && $c['height']===$height && $c['title']==='Test');
 $html.=$out;
}
foreach(['https://www.youtube.com.evil.example/embed/123','https://tramino.de.evil.example/widget','javascript://test.tramino.de/widget'] as $url) embed_check(BB_Map_Consent::extra_iframe_service($url)==='');
embed_check(BB_Map_Consent::extra_iframe_service('https://www.youtube.com/watch?v=123')==='');
embed_check(BB_Map_Consent::filter_html('<link rel="preconnect" href="https://www.youtube-nocookie.com">')==='');
embed_check(BB_Map_Consent::filter_html('<link rel="preload" href="https://ferienwohnung-winkel.tramino.de/api/widgets/rating/">')==='');
// Videos keep their aspect ratio (Divi/FitVids behaviour) instead of a fixed portrait-prone height.
$video=BB_Map_Consent::filter_html('<iframe width="1080" height="608" src="https://www.youtube-nocookie.com/embed/GlKQB-2m4Wg?feature=oembed"></iframe>');
embed_check(strpos($video,'style="--bbmc-ratio:1080/608;"')!==false && strpos($video,'--bbmc-h')===false);
embed_check(strpos(BB_Map_Consent::filter_html('<iframe width="100%" height="400" src="https://www.youtube.com/embed/abc"></iframe>'),'--bbmc-ratio:16/9;')!==false);
embed_check(strpos(BB_Map_Consent::filter_html('<iframe height="400" src="https://ferienwohnung-winkel.tramino.de/api/widgets/rating/"></iframe>'),'--bbmc-ratio')===false);
$html.='<script src="https://test.local/maps.js"></script></body></html>';
if (getenv('BBMC_EXTRA_FIXTURE')) file_put_contents(getenv('BBMC_EXTRA_FIXTURE'),$html);
echo "YouTube/Tramino: actual FEWO URLs, separate services, preserved dimensions/title, protocols, host boundaries and resource hints passed\n";
