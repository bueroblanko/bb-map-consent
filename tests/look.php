<?php
ob_start();
require __DIR__.'/filter.php';
ob_end_clean();
function look_check($ok, $what) { if (!$ok) throw new Exception('Look regression: '.$what); }
$options = []; $divi = [];
function get_option($k, $d = false) { global $options; return array_key_exists($k, (array) $options) ? $options[$k] : $d; }
function et_get_option($k, $d = '') { global $divi; return $divi[$k] ?? $d; }
function wp_add_inline_style($h, $css) { $GLOBALS['inline'][] = [$h, $css]; }
// Settings: unknown values fall back to the defaults, colours are normalised.
look_check(BBMC_Look::settings() === BBMC_Look::DEFAULTS, 'defaults');
look_check(BBMC_Look::sanitize(['accent_mode' => 'own', 'accent' => '#ABC', 'font' => 'x', 'radius' => 'round', 'scheme' => 'dark']) === ['accent_mode' => 'own', 'accent' => '#aabbcc', 'font' => 'site', 'radius' => 'round', 'scheme' => 'dark'], 'sanitize');
look_check(BBMC_Look::sanitize('x') === BBMC_Look::DEFAULTS, 'sanitize non-array');
look_check(BBMC_Look::hex('red') === '' && BBMC_Look::hex('#12345') === '' && BBMC_Look::hex('#fff;}body{') === '', 'hex rejects');
// Divi: accent and button radius; the untouched Divi default is not the brand colour.
$divi = ['accent_color' => '#00A8EA', 'all_buttons_border_radius' => '0'];
look_check(BBMC_Look::detect() === ['accent' => '#00a8ea', 'radius' => 0, 'source' => 'Divi'], 'divi');
$divi = ['accent_color' => '#2ea3f2'];
look_check(BBMC_Look::detect() === ['accent' => '', 'radius' => null, 'source' => 'Divi'], 'divi default');
look_check(BBMC_Look::css(BBMC_Look::resolve(BBMC_Look::settings(), BBMC_Look::detect())) === '#cc-main .cm__title,#cc-main .pm__title{font-family:var(--bbmc-heading-font,inherit)}', 'nothing found keeps banner defaults');
// Elementor kit and block theme palette.
look_check(BBMC_Look::from_elementor(['system_colors' => [['_id' => 'primary', 'color' => '#112233'], ['_id' => 'accent', 'color' => '#61CE70']], 'button_border_radius' => ['top' => '5']]) === ['accent' => '#61ce70', 'radius' => 5, 'source' => 'Elementor'], 'elementor');
look_check(BBMC_Look::from_elementor(['system_colors' => [['_id' => 'primary', 'color' => '#112233']]])['accent'] === '#112233' && BBMC_Look::from_elementor('')['accent'] === '', 'elementor fallback');
look_check(BBMC_Look::from_palette([['slug' => 'base', 'color' => '#fff'], ['slug' => 'accent', 'color' => '#AA0000']])['accent'] === '#aa0000' && BBMC_Look::from_palette(null)['accent'] === '', 'palette');
// Overrides win over the site, radius presets map to button/dialog.
$site = ['accent' => '#00a8ea', 'radius' => 3, 'source' => 'Divi'];
look_check(BBMC_Look::resolve(BBMC_Look::DEFAULTS, $site) === ['accent' => '#00a8ea', 'radius' => [3, 6], 'scheme' => 'light'], 'resolve site');
look_check(BBMC_Look::resolve(['accent_mode' => 'own', 'accent' => '#7a4b24', 'radius' => 'round', 'scheme' => 'dark'] + BBMC_Look::DEFAULTS, $site) === ['accent' => '#7a4b24', 'radius' => [999, 20], 'scheme' => 'dark'], 'resolve own');
look_check(BBMC_Look::resolve(['radius' => 'site'] + BBMC_Look::DEFAULTS, ['accent' => '', 'radius' => 30, 'source' => 'Divi'])['radius'] === [30, 16], 'dialog radius capped');
// CSS: readable text on light/dark accents, hover, links only on the light banner.
$css = BBMC_Look::css(['accent' => '#c8f560', 'radius' => [0, 0], 'scheme' => 'light']);
look_check(strpos($css, 'html:root{--cc-btn-primary-bg:#c8f560;') === 0 && strpos($css, '--cc-btn-primary-color:#111;') && strpos($css, '--bbmc-accent-text:#111;') && strpos($css, '--cc-link-color:#c8f560;') && strpos($css, '--bbmc-btn-radius:0px;'), 'light accent css');
$css = BBMC_Look::css(['accent' => '#7a4b24', 'radius' => null, 'scheme' => 'dark']);
look_check(strpos($css, '--cc-btn-primary-color:#fff;') && strpos($css, '--cc-btn-primary-hover-bg:' . BBMC_Look::hover('#7a4b24')) && strpos($css, 'cc-link-color') === false && strpos($css, 'radius') === false, 'dark accent css');
look_check(BBMC_Look::hover('#000000') === '#000000' && BBMC_Look::hover('#ffffff') === '#ffffff' && BBMC_Look::hover('#7a4b24') === '#643e1e', 'hover');
// Front end: inline CSS on the placeholder stylesheet (before own CSS); banner config.
$divi = ['accent_color' => '#00a8ea']; $GLOBALS['inline'] = [];
BBMC_Look::enqueue();
look_check($GLOBALS['inline'][0][0] === 'bb-map-consent' && strpos($GLOBALS['inline'][0][1], '--bbmc-site-accent:#00a8ea;') !== false, 'enqueue');
$options['bbmc_look'] = ['font' => 'system', 'scheme' => 'dark'];
look_check(BBMC_Look::client() === ['font' => 'system', 'scheme' => 'dark'], 'client');
echo "Look: settings, Divi/Elementor/theme detection, overrides, contrast, hover and inline CSS passed\n";
