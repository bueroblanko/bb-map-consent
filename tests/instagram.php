<?php
require __DIR__.'/filter.php';
function ig_check($condition) { if (!$condition) throw new Exception('Instagram regression failed'); }
foreach (['p','reel','tv'] as $type) {
 $url='https://www.instagram.com/'.$type.'/ABC_123-/';
 ig_check(BB_Map_Consent::instagram_embed($url.'?utm_source=x')===$url.'embed/');
 $out=BB_Map_Consent::filter_html('<blockquote class="instagram-media" data-instgrm-permalink="'.$url.'"><img src="https://cdninstagram.com/remote.jpg"><p>Preview</p></blockquote><script async src="//www.instagram.com/embed.js"></script>');
 ig_check(strpos($out,'data-service="instagram"')!==false);
 ig_check(strpos($out,'<img')===false && strpos($out,'<script')===false && strpos($out,'<blockquote')===false);
 $out=BB_Map_Consent::filter_html('<iframe src="'.$url.'embed/captioned/"></iframe>');
 ig_check(strpos($out,'<iframe')===false && strpos($out,'data-service="instagram"')!==false);
}
$out=BB_Map_Consent::filter_html('<blockquote class="instagram-media"><a href="https://instagram.com/p/ABC/">View</a></blockquote>');
ig_check(strpos(str_replace('\\/', '/', $out),'/p/ABC/embed/')!==false);
ig_check(BB_Map_Consent::instagram_embed('https://instagram.com.evil.example/p/ABC/')==='');
ig_check(BB_Map_Consent::instagram_embed('https://instagram.com/user/')==='');
ig_check(BB_Map_Consent::filter_html('<blockquote>Normal quote</blockquote>')==='<blockquote>Normal quote</blockquote>');
$out=BB_Map_Consent::filter_html('<blockquote class="instagram-media" data-instgrm-permalink="https://instagram.com/user/"><img src="https://example.com/image.jpg"></blockquote>');
ig_check(strpos($out,'<img')===false && strpos($out,'data-service="instagram"')!==false);
ig_check(BB_Map_Consent::filter_html('<script src="https://www.instagram.com/embed.js?v=1"></script>')==='');
echo "Instagram: posts/reels/TV, previews, SDK, iframe, fallback link, unsupported embeds and domain validation passed\n";
