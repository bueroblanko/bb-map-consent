<?php
ob_start();
require __DIR__.'/filter.php';
ob_end_clean();
function cmp_check($ok, $what) { if (!$ok) throw new Exception('CMP regression: '.$what); }
$options = [];
function get_option($k, $d = false) { global $options; return array_key_exists($k, (array) $options) ? $options[$k] : $d; }
function update_option($k, $v) { global $options; $options[$k] = $v; return true; }
function wp_kses($s, $allowed) { return strip_tags($s, '<'.implode('><', array_keys($allowed)).'>'); }
// Trackers become inactive scripts with the right category.
$cases = [
 ['<script async src="https://pixel.byspotify.com/ping.min.js"></script>', 'spotify', 'marketing'],
 ["<script>window.spdt('lead');</script>", 'spotify', 'marketing'],
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
cmp_check(BBMC_Cmp::config(['matomo'], '/d/', [], 'sie', '/impressum/')['imprint'] === '/impressum/', 'imprint');
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
// Placeholders become the category "external", linked to the placeholder services.
[$withBox, $ext] = BBMC_Cmp::filter('<section class="bbmc" data-service="youtube" data-bbmc="{}"></section><section class="bbmc bbmc-compact" data-service="maps"></section><section class="bbmc" data-service="unknown"></section>');
cmp_check($ext === ['ext_youtube', 'ext_maps'], 'external found '.json_encode($ext));
$extConfig = BBMC_Cmp::config(['matomo', 'ext_youtube'], '/d/');
cmp_check($extConfig['categories']['external'] === ['YouTube'] && (array) $extConfig['external'] === ['youtube' => 'YouTube'], 'external config');
cmp_check($extConfig['revision'] !== BBMC_Cmp::config(['matomo'], '/d/')['revision'], 'external revision');
// Cookieless Matomo (disableCookies) stays active and leaves the banner.
$cl = '<script>var _paq = window._paq = window._paq || []; _paq.push(["disableCookies"]); _paq.push(["trackPageView"]);</script><noscript><img src="https://m.example/matomo.php?idsite=1"></noscript><link rel="preconnect" href="https://m.example/matomo.js">';
[$clHtml, $clFound] = BBMC_Cmp::filter($cl);
cmp_check($clHtml === $cl && $clFound === [], 'cookieless matomo untouched');
cmp_check(BBMC_Cmp::matomo_cookieless("<script>_paq.push(['disableCookies']);</script>") && !BBMC_Cmp::matomo_cookieless('<script>_paq.push(["trackPageView"]);</script><p>disableCookies</p>'), 'cookieless detection');
$options['bbmc_cmp_seen'] = ['matomo', 'ext_youtube'];
cmp_check(BBMC_Cmp::remember([], ['matomo']) === ['ext_youtube'], 'matomo dropped from seen');
if (!function_exists('esc_html')) { function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
cmp_check(strpos(BBMC_Cmp::shortcode(), '>Cookies</a>') !== false && strpos(BBMC_Cmp::shortcode(['label' => 'Cookie-Einstellungen']), '>Cookie-Einstellungen</a>') !== false && strpos(BBMC_Cmp::shortcode(''), '>Cookies</a>') !== false, 'settings link label');
// Tracking IDs written by BB Consent (e.g. formerly output by Borlabs).
$bad = [];
$ids = BBMC_Cmp::clean_tracking(['ga4' => ' g-jhkr8xw00c ', 'gtm' => 'GTM-WV2D9XCC', 'ads' => 'AW-721461199', 'meta' => '123456789012345', 'mouseflow' => 'ABCDEF12-3456-7890-ABCD-EF1234567890', 'hotjar' => 'x1', 'clarity' => 'abc"><script>', 'evil' => 'G-1'], $bad);
cmp_check($ids === ['ga4' => 'G-JHKR8XW00C', 'gtm' => 'GTM-WV2D9XCC', 'ads' => 'AW-721461199', 'meta' => '123456789012345', 'mouseflow' => 'abcdef12-3456-7890-abcd-ef1234567890'], 'clean tracking '.json_encode($ids));
cmp_check($bad === ['Hotjar', 'Microsoft Clarity'], 'invalid tracking '.json_encode($bad));
$expect = ['ga4' => ['google_analytics', 'analytics'], 'gtm' => ['google_tag_manager', 'marketing'], 'ads' => ['google_ads', 'marketing'], 'meta' => ['meta_pixel', 'marketing'], 'mouseflow' => ['mouseflow', 'analytics'], 'hotjar' => ['hotjar', 'analytics'], 'clarity' => ['clarity', 'analytics']];
$sample = $ids + ['hotjar' => '1234567', 'clarity' => 'abcd1234ef'];
foreach ($expect as $key => [$service, $cat]) {
 $code = BBMC_Cmp::snippet($key, $sample[$key]);
 cmp_check(strpos($code, $sample[$key]) !== false, "snippet $key has id");
 [$out, $found] = BBMC_Cmp::filter($code);
 cmp_check($found === [$service], "snippet $key found ".json_encode($found));
 cmp_check(strpos($out, '<script type="text/javascript"') === false && strpos($out, '<script async') === false && preg_match_all('~<script type="text/plain" data-category="'.$cat.'"~', $out) === substr_count($code, '<script'), "snippet $key blocked $out");
}
$page = '<html><head><title>x</title></head><body></body></html>';
$withIds = BBMC_Cmp::add_tracking($page, ['ga4' => 'G-JHKR8XW00C', 'gtm' => 'GTM-WV2D9XCC']);
cmp_check(strpos($withIds, 'G-JHKR8XW00C') < strpos($withIds, '</head>') && strpos($withIds, 'GTM-WV2D9XCC') < strpos($withIds, '</head>'), 'tracking in head');
cmp_check(BBMC_Cmp::add_tracking($page, []) === $page && BBMC_Cmp::add_tracking($page, ['ga4' => 'nope']) === $page, 'no tracking without ids');
$theme = '<html><head><script async src="https://www.googletagmanager.com/gtag/js?id=G-JHKR8XW00C"></script></head><body></body></html>';
cmp_check(substr_count(BBMC_Cmp::add_tracking($theme, ['ga4' => 'G-JHKR8XW00C', 'gtm' => 'GTM-WV2D9XCC']), 'G-JHKR8XW00C') === 1, 'no duplicate id');
// IDs from Borlabs settings or its public config.
$borlabs = 'a:2:{s:10:"trackingId";s:12:"G-R0NFPZV9TX";} {"gtmId":"GTM-TNKW54QQ"} "conversionId":"AW-721461199" s:9:"meta-pixel" {"pixelId":"615234087719342"} G-Suite 2024-01-01 1698765432';
cmp_check(BBMC_Cmp::find_ids($borlabs) === ['ga4' => 'G-R0NFPZV9TX', 'gtm' => 'GTM-TNKW54QQ', 'ads' => 'AW-721461199', 'meta' => '615234087719342'], 'find ids '.json_encode(BBMC_Cmp::find_ids($borlabs)));
cmp_check(BBMC_Cmp::find_ids('created 1698765432123456 G-SUITE') === [], 'no false ids');
cmp_check(BBMC_Cmp::find_ids('"tracking-id":"G-123456789" "conversion-id":"AW-123456789" GTM-XXXXXXX "pixel-id":"123456789012345"') === [], 'borlabs example values ignored');
cmp_check(BBMC_Cmp::find_ids('"tracking-id":"G-123456789" … "tracking-id":"G-R0NFPZV9TX" "AW-123456789" "AW-721461199"') === ['ga4' => 'G-R0NFPZV9TX', 'ads' => 'AW-721461199'], 'real id after example');
// TikTok and Spotify pixels from Borlabs: IDs only next to their names, standard snippets blocked like any tracker.
$tt = BBMC_Cmp::find_ids('{"key":"tiktok-pixel","settings":{"pixel-id":"C4ABCD1EFGH2IJKL3MN0"}} {"key":"spotify-pixel","settings":{"pixel-key":"A1B2C3D4E5F60718293A4B5C6D7E8F90"}}');
cmp_check($tt === ['tiktok' => 'C4ABCD1EFGH2IJKL3MN0', 'spotify' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90'], 'tiktok/spotify ids '.json_encode($tt));
cmp_check(BBMC_Cmp::find_ids('"hash":"a1b2c3d4e5f60718293a4b5c6d7e8f90" "ref":"C4ABCD1EFGH2IJKL3MN0"') === [], 'no ids without names');
$px = BBMC_Cmp::add_tracking($page, ['tiktok' => 'C4ABCD1EFGH2IJKL3MN0', 'spotify' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90']);
[$blockedPx, $seenPx] = BBMC_Cmp::filter($px);
cmp_check(in_array('tiktok', $seenPx, true) && in_array('spotify', $seenPx, true) && strpos($blockedPx, 'analytics.tiktok.com') !== false && preg_match('~<script(?![^>]*type="text/plain")[^>]*>[^<]*(?:ttq\.load|spdt\()~', $blockedPx) === 0, 'tiktok/spotify snippets blocked '.json_encode($seenPx));
cmp_check(BBMC_Cmp::find_ids('{\\"asynchronous-opt-out-code\\":\\"0\\",\\"pixel-id\\":\\"1152073978499337\\",\\"service-group\\"')['meta'] === '1152073978499337', 'borlabs config pixel-id');
cmp_check(BBMC_Cmp::find_ids('a:1:{s:8:"pixel-id";s:16:"1152073978499337";}')['meta'] === '1152073978499337', 'borlabs db pixel-id');
// No service needing consent: settings links are hidden instead of doing nothing.
$plain = '<html><head></head><body><a href="#bb-consent-settings" class="bbmc-cmp-settings">Cookies</a></body></html>';
$hidden = BBMC_Cmp::hide_settings_links($plain);
cmp_check(strpos($hidden, 'bbmc-cmp-nolinks') !== false && strpos($hidden, 'bbmc-cmp-nolinks') < strpos($hidden, '</head>'), 'hide links');
cmp_check(BBMC_Cmp::hide_settings_links('<html><head></head><body></body></html>') === '<html><head></head><body></body></html>', 'no links untouched');
// Providers with privacy links per service; self-hosted Matomo has none.
$prov = BBMC_Cmp::config(['mouseflow', 'matomo', 'ext_youtube'], '/d/');
cmp_check($prov['providers']->Mouseflow === ['Mouseflow ApS, Dänemark', 'https://mouseflow.com/legal/privacy-policy/'] && !isset($prov['providers']->Matomo) && $prov['providers']->YouTube[1] === 'https://policies.google.com/privacy', 'providers '.json_encode($prov['providers']));
foreach (BBMC_Cmp::PROVIDERS as $id => [$who, $url]) { cmp_check(isset(BBMC_Cmp::SERVICES[$id]) || (strpos($id, 'ext_') === 0 && isset(BBMC_Cmp::EXTERNAL[substr($id, 4)])), "provider id $id"); cmp_check(strpos($url, 'https://') === 0, "provider url $id"); }
// Borlabs 3 public config (wp-content/cache/borlabs-cookie/…): codes only base64-encoded, key far from the name.
$cfg = '{"services":{"spotify-pixel":{"name":"Spotify Ads Pixel","optInCode":"KGZ1bmN0aW9uKHcsZCl7dmFyIGlkPSdzcGR0LWNhcHR1cmUnLG49J3NjcmlwdCc7DQoNCg0KDQoNCg0KDQoNCg0KDQoNCg0KDQoNCg0KDQoNCg0KDQoNCncuc3BkdCgnY29uZicsIHsNCiAga2V5OiAnYTFiMmMzZDRlNWY2MDcxODI5M2E0YjVjNmQ3ZThmOTAnLA0KfSk7DQp3LnNwZHQoJ3ZpZXcnKTt9KSh3aW5kb3csZG9jdW1lbnQpOw==","settings":[]},"facebook-pixel":{"name":"Meta","optInCode":"PHNjcmlwdD5mYnEoJ2luaXQnLCAnNjE1MjM0MDg3NzE5MzQyJyk7ZmJxKCd0cmFjaycsJ1BhZ2VWaWV3Jyk7PC9zY3JpcHQ+"}}}';
$fromCfg = BBMC_Cmp::find_ids($cfg);
cmp_check(($fromCfg['spotify'] ?? '') === 'a1b2c3d4e5f60718293a4b5c6d7e8f90' && ($fromCfg['meta'] ?? '') === '615234087719342', 'borlabs base64 config '.json_encode($fromCfg));
// Own legal links for sites without own pages (e.g. a group's privacy policy); only https.
$options[BBMC_Cmp::LINKS] = ['privacy' => 'https://www.example.com/policy', 'imprint' => 'javascript:alert(1)'];
cmp_check(BBMC_Cmp::privacy_url() === 'https://www.example.com/policy' && BBMC_Cmp::link('imprint') === '', 'own links '.BBMC_Cmp::privacy_url());
$box = BB_Map_Consent::box(['service' => 'youtube', 'kind' => 'iframe', 'src' => 'https://www.youtube-nocookie.com/embed/x']);
cmp_check(strpos($box, 'href="https://www.example.com/policy"') !== false, 'placeholder uses own privacy link');
unset($options[BBMC_Cmp::LINKS]);
cmp_check(BBMC_Cmp::privacy_url() === '/datenschutz/', 'default privacy page');
echo "CMP: ".count($cases)." trackers, untouched scripts, noscript, hints, revision, injection and other-CMP guard, tracking IDs and Borlabs import passed\n";
