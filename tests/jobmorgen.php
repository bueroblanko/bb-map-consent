<?php
require __DIR__.'/filter.php';
function jm_check($v,$m){ if(!$v) throw new Exception('Jobmorgen: '.$m); }
$loader = '<script id="Update-regio-power-partner-box" async>(function(d,t){var j=d.getElementById("PartnerBox-1"),f=d.createElement(\'script\');f.src=\'https://www.jobmorgen.de/partnerbox.js?n=\'+t.getTime();f.onload=function(e){RegioPowerPartnerBox({"apiUrl":"https://www.jobmorgen.de:443/Partner/BoxApi"},e)};j.parentElement.insertBefore(f,j);})(document,new Date());</script>';
$box = '<div id="PartnerBox-1" class="regio-power-partner-box DynamischeSuchbox"><a href="https://www.jobmorgen.de">Stellenangebote via jobmorgen.de</a></div>';
$page = BB_Map_Consent::filter_html('<body><p>Jobs</p>'.$box.$loader.'</body>');
jm_check(strpos($page,'partnerbox.js')===false && strpos($page,'data-service="jobmorgen" data-script=')!==false, 'loader held back');
jm_check(preg_match('~<section class="bbmc" data-service="jobmorgen"[^>]*>.*?</section><div id="PartnerBox-1"~s',$page)===1, 'placeholder before container');
$other = BB_Map_Consent::filter_html('<body><p>Start</p>'.$loader.'</body>');
jm_check(strpos($other,'class="bbmc"')===false && strpos($other,'partnerbox.js')===false, 'no container: script held, no placeholder');
jm_check(BB_Map_Consent::filter_html('<link rel="preconnect" href="https://www.jobmorgen.de">')==='', 'resource hint removed');
jm_check(BB_Map_Consent::filter_html('<script src="https://www.jobmorgen.de/partnerbox.js"></script>')!=='<script src="https://www.jobmorgen.de/partnerbox.js"></script>', 'direct script held');
jm_check(BB_Map_Consent::filter_html('<script>var jobs = "jobmorgen";</script>')==='<script>var jobs = "jobmorgen";</script>', 'unrelated script untouched');
echo "Jobmorgen: loader held back, placeholder at container, pages without container, hints and unrelated scripts passed\n";
