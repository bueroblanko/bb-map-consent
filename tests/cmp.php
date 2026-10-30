<?php
ob_start();
require __DIR__.'/filter.php';
ob_end_clean();
function cmp_check($ok, $what) { if (!$ok) throw new Exception('CMP regression: '.$what); }
$options = [];
function get_option($k, $d = false) { global $options; return array_key_exists($k, $options) ? $options[$k] : $d; }
function update_option($k, $v) { global $options; $options[$k] = $v; return true; }
function wp_kses($s, $allowed) { return strip_tags($s, '<'.implode('><', array_keys($allowed)).'>'); }
// Trackers become inactive scripts with the right category.
$cases = [
 ['<script async src="https://www.googletagmanager.com/gtag/js?id=G-TEST123"></script>', 'google_analytics', 'analytics'],
 ["<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-TEST123');</script>", 'google_analytics', 'analytics'],
 ['<script async src="https://www.googletagmanager.com/gtag/js?id=AW-123"></script>', 'google_ads', 'marketing'],
 ["<script>gtag('config', 'AW-123');gtag('config','G-1');</script>", 'google_ads', 'marketing'],
 ["<script>(function(w,d,s,l,i){j.src='https://www.googletagmanager.com/gtm.js?id='+i})(window,document,'script','dataLayer','GTM-X');</script>", 'google_tag_manager', 'marketing'],
 ["<script type=\"text/javascript\">!function(f,b,e,v,n,t,s){t.src='https://connect.facebook.net/en_US/fbevents.js'}(window);fbq('init','1');</script>", 'meta_pixel', 'marketing'],
 ["<script>var _paq = window._paq = window._paq || [];_paq.push(['trackPageView']);</script>", 'matomo', 'analytics'],
 ['<script src="https://example.org/matomo/matomo.js"></script>', 'matomo', 'analytics'],
 ["<script>(function(h,o,t,j,a,r){h._hjSettings={hjid:1};})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');</script>", 'hotjar', 'analytics'],
 ['<script src="//cdn.mouseflow.com/projects/abc.js"></script>', 'mouseflow', 'analytics'],
 ['<script>(function(c,l,a,r,i,t,y){t.src="https://www.clarity.ms/tag/"+i;})(window,document,"clarity","script","x");</script>', 'clarity', 'analytics'],
 ['<script>_linkedin_partner_id = "1";</script>', 'linkedin', 'marketing'],
 ['<script>ttq.load("X");ttq.page();</script>', 'tiktok', 'marketing'],
];
foreach ($cases as $i => [$in, $id, $cat]) {
 [$out, $found] = BBMC_Cmp::filter($in);
 cmp_check($found === [$id], "case $i found ".json_encode($found));
 cmp_check(strpos($out, '<script type="text/plain" data-category="'.$cat.'" data-bbmc-service="'.$id.'"') === 0, "case $i output $out");
 cmp_check(substr_count($out, 'type=') === 1, "case $i keeps one type attribute");
}
// Not touched: ordinary scripts, JSON, own scripts, already blocked scripts, comments, links in text.
foreach ([
 '<script src="/wp-includes/js/jquery/jquery.min.js"></script>',
 '<script type="application/ld+json">{"url":"https://www.google-analytics.com"}</script>',
 '<script type="text/plain" data-borlabs-cookie-script-blocker-id="x">gtag("config","G-1")</script>',
 '<script data-bbmc-cmp="1" src="/cmp.js"></script>',
 '<!-- <script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script> -->',
 '<p>Wir nutzen Google Analytics (google-analytics.com).</p>',
 '<script>var gtagLabel = "x";</script>',
] as $i => $in) { [$out, $found] = BBMC_Cmp::filter($in); cmp_check($out === $in && !$found, "untouched $i"); }
// noscript pixels and resource hints are removed.
[$out, $found] = BBMC_Cmp::filter('<noscript><img src="https://www.facebook.com/tr?id=1&ev=PageView"></noscript><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-X"></iframe></noscript><noscript><p>JS aus</p></noscript>');
cmp_check($out === '<noscript><p>JS aus</p></noscript>' && $found === ['meta_pixel', 'google_tag_manager'], 'noscript '.$out);
[$out] = BBMC_Cmp::filter('<link rel="dns-prefetch" href="//www.googletagmanager.com"><link rel="preconnect" href="https://connect.facebook.net"><link rel="stylesheet" href="/style.css">');
cmp_check($out === '<link rel="stylesheet" href="/style.css">', 'hints '.$out);
// Seen services accumulate site-wide; revision changes when a new one appears.
$seen = BBMC_Cmp::remember(['matomo']);
$r1 = BBMC_Cmp::config($seen, '/datenschutz/')['revision'];
$seen = BBMC_Cmp::remember([]);
cmp_check($seen === ['matomo'] && BBMC_Cmp::config($seen, '')['revision'] === $r1 && $r1 > 0, 'remember keeps services');
$seen = BBMC_Cmp::remember(['meta_pixel', 'unknown']);
$config = BBMC_Cmp::config($seen, '/datenschutz/');
cmp_check($seen === ['meta_pixel', 'matomo'] && $config['revision'] !== $r1, 'new service bumps revision');
cmp_check($config['categories'] === ['marketing' => ['Meta-Pixel'], 'analytics' => ['Matomo']], 'categories '.json_encode($config));
cmp_check(BBMC_Cmp::config([], '')['revision'] === 0, 'empty config');
cmp_check(BBMC_Cmp::config(['matomo'], '')['address'] === 'sie' && BBMC_Cmp::config(['matomo'], '', [], 'du')['address'] === 'du' && BBMC_Cmp::config(['matomo'], '', [], 'x')['address'] === 'sie', 'address');
// Own texts: known keys only, empty dropped, scripts stripped; JSON stays an object.
$texts = BBMC_Cmp::clean_texts(['title' => ' Cookies ', 'description' => 'Text <a href="/x">Link</a><script>alert(1)</script>', 'analytics' => '', 'evil' => 'x']);
cmp_check($texts === ['title' => 'Cookies', 'description' => 'Text <a href="/x">Link</a>alert(1)'], 'texts '.json_encode($texts));
cmp_check(json_encode(BBMC_Cmp::config(['matomo'], '', [])['texts']) === '{}' && BBMC_Cmp::config(['matomo'], '', ['title' => 'X'])['texts']->title === 'X', 'texts in config');
// Assets go into head/body.
$page = BBMC_Cmp::inject('<html><head></head><body><p>x</p></body></html>', $config, '/p/', '1');
cmp_check(strpos($page, 'cookieconsent.css?ver=1"') < strpos($page, '</head>') && strpos($page, 'id="bbmc-cmp-config"') > strpos($page, '<p>x</p>') && strpos($page, '/p/cmp.js?ver=1') < strpos($page, '</body>'), 'inject');
// Other consent plugins switch the banner off.
$options['bbmc_cmp_enabled'] = true;
$options['active_plugins'] = ['divi-builder/divi-builder.php'];
cmp_check(BBMC_Cmp::active(), 'active');
$options['active_plugins'][] = 'borlabs-cookie/borlabs-cookie.php';
cmp_check(!BBMC_Cmp::active() && BBMC_Cmp::other_cmp() === 'borlabs-cookie/borlabs-cookie.php', 'borlabs blocks');
$options['active_plugins'] = [];
$options['bbmc_cmp_enabled'] = false;
cmp_check(!BBMC_Cmp::active(), 'default off');
echo "CMP: ".count($cases)." trackers, untouched scripts, noscript, hints, revision, injection and other-CMP guard passed\n";
