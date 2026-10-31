<?php
require __DIR__.'/filter.php';
function rc_check($v,$m){ if(!$v) throw new Exception('Rechner: '.$m); }
// clever-PV savings calculator (iframe)
$frame = '<iframe id="cpv-rechner" src="https://integrations.clever-pv.com/calculator?color=D2921D&amp;contact=https%3A%2F%2Fexample.de%2Fkontakt%2F" width="100%" frameborder="0" scrolling="no" title="clever-PV Einsparungsrechner" style="width:100%;height:1200px;border:none;display:block"></iframe>';
$page = BB_Map_Consent::filter_html('<body>'.$frame.'</body>');
rc_check(strpos($page,'<iframe')===false && strpos($page,'data-service="cleverpv"')!==false, 'clever-PV iframe replaced');
rc_check(strpos($page,'&quot;height&quot;:1200')!==false && strpos($page,'&quot;id&quot;:&quot;cpv-rechner&quot;')!==false, 'clever-PV height from style and frame id');
rc_check(strpos($page,'clever-PV GmbH')!==false && strpos($page,'clever-PV-Rechner laden')!==false, 'clever-PV texts');
rc_check(BB_Map_Consent::filter_html('<link rel="preconnect" href="https://integrations.clever-pv.com">')==='', 'clever-PV hint removed');
// Autarkierechner (Sunbeam loader + container)
$loader = '<script src="https://pv-system-tech.com/silithium_2019/js/cls.sbloader.js"></script>';
$box = '<div id="sb-anlagenplaner" data-tmpl="ergebnis"></div>';
$page = BB_Map_Consent::filter_html('<body><p>Rechner</p>'.$box.$loader.'</body>');
rc_check(strpos($page,'cls.sbloader.js')===false && strpos($page,'data-service="anlagenplaner" data-script=')!==false, 'loader held back');
rc_check(preg_match('~<section class="bbmc" data-service="anlagenplaner"[^>]*>.*?</section><div id="sb-anlagenplaner"~s',$page)===1, 'placeholder before container');
rc_check(strpos($page,'Google Maps')!==false && strpos($page,'Sunbeam Communications GmbH')!==false, 'Autarkierechner texts');
$cls = BB_Map_Consent::filter_html('<body>'.$loader.'<div class="x sb-anlagenplaner"></div></body>');
rc_check(preg_match('~</section><div class="x sb-anlagenplaner"~',$cls)===1, 'container by class, script first');
$other = BB_Map_Consent::filter_html('<body><p>Start</p>'.$loader.'</body>');
rc_check(strpos($other,'class="bbmc"')===false && strpos($other,'cls.sbloader.js')===false, 'no container: script held, no placeholder');
rc_check(BB_Map_Consent::filter_html('<link rel="dns-prefetch" href="//pv-system-tech.com">')==='', 'Sunbeam hint removed');
rc_check(BB_Map_Consent::filter_html('<script>var t = "anlagenplaner";</script>')==='<script>var t = "anlagenplaner";</script>', 'unrelated script untouched');
echo "Rechner: clever-PV iframe, Autarkierechner loader and container, hints passed\n";
