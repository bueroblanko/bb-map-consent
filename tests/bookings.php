<?php
require __DIR__.'/filter.php';
function bookings_check($value) { if (!$value) throw new Exception('Bookings regression'); }
$src='https://outlook.office365.com/owa/calendar/bookings@rn-systems.de/bookings/s/_7NnWi37H0CSZqf-yhDoEg2';
$raw="<iframe src='".$src."' width='100%' height='800px' scrolling='yes' style='border:0'></iframe>";
$out=BB_Map_Consent::filter_html($raw);
bookings_check(strpos($out,'<iframe')===false && strpos($out,'data-service="bookings"')!==false);
preg_match('/data-bbmc="([^"]+)"/',$out,$m);$c=json_decode(html_entity_decode($m[1],ENT_QUOTES),true);
bookings_check($c['src']===$src && $c['height']===800 && $c['title']==='Microsoft Bookings');
foreach(['https://outlook.office.com/book/test@example.com/','//outlook.office365.com/book/test@example.com/']as $url) bookings_check(BB_Map_Consent::extra_iframe_service($url)==='bookings');
foreach(['https://outlook.office365.com.evil.example/owa/calendar/a/bookings/','https://outlook.office.com/mail/','https://outlook.office.com/bookmark/test']as $url) bookings_check(BB_Map_Consent::extra_iframe_service($url)==='');
bookings_check(BB_Map_Consent::filter_html('<link rel="preconnect" href="https://outlook.office365.com">')==='');
$map=BB_Map_Consent::box(['kind'=>'iframe','src'=>'https://www.google.com/maps/embed?pb=test','height'=>160,'title'=>'Map']);
if(getenv('BBMC_BOOKINGS_FIXTURE')) file_put_contents(getenv('BBMC_BOOKINGS_FIXTURE'),'<!doctype html><html><body>'.$out.$map.'<script src="https://test.local/maps.js"></script></body></html>');
echo "Bookings: actual RN iframe, 800px height, modern and legacy URLs, other Outlook pages, malicious hosts and resource hints passed\n";
