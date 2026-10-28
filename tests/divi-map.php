<?php
require __DIR__.'/divi-key.php';
function wp_strip_all_tags($value) { return strip_tags($value); }
function map_config($output) { preg_match('/data-bbmc="([^"]+)"/', $output, $m); return json_decode(html_entity_decode($m[1], ENT_QUOTES),true); }
$native='<div id="location" class="et_pb_module et_pb_fullwidth_map_0 et_pb_map_container"><div class="et_pb_map" data-center-lat="49.4210023" data-center-lng="8.6588714" data-zoom="15"></div><div class="et_pb_map_pin" data-lat="49.4210023" data-lng="8.6588714" data-title="SV Nikar Heidelberg"><h3>SV Nikar Heidelberg</h3><div class="infowindow"><p>Tiergartenstraße 13/2<br>69121 Heidelberg</p></div></div></div>';
$out=BB_Map_Consent::divi($native,'et_pb_fullwidth_map',[],[]);
$c=map_config($out);
if ($c['lat']!==49.4210023 || $c['lng']!==8.6588714 || $c['zoom']!==15 || count($c['pins'])!==1 || $c['pins'][0]['lat']!==49.4210023 || strpos($c['pins'][0]['text'],'69121 Heidelberg')===false || strpos($out,'id="location"')===false) throw new Exception('Heidelberg map regression');
$other=str_replace(['49.4210023','8.6588714','data-zoom="15"'],['49.4227246','8.6601764','data-zoom="18"'],$native);
$c=map_config(BB_Map_Consent::divi($other,'et_pb_map',[],[]));
if ($c['lat']!==49.4227246 || $c['lng']!==8.6601764 || $c['zoom']!==18) throw new Exception('Second map regression');
$c=map_config(BB_Map_Consent::divi('<div class="et_pb_map"></div>','et_pb_map',[],[]));
if ($c['lat']!==null || $c['lng']!==null) throw new Exception('Missing coordinates must not default to ocean');
if (BB_Map_Consent::divi('normal','et_pb_text',[],[])!=='normal') throw new Exception('Unrelated module regression');
echo "Divi maps: real Heidelberg coordinates, both module types, pins, zoom, descriptions, wrapper and missing coordinates passed\n";
if (getenv('BBMC_MAP_FIXTURE')) file_put_contents(getenv('BBMC_MAP_FIXTURE'), '<!doctype html><html><body>'.$out.'<script src="https://test.local/maps.js"></script></body></html>');
