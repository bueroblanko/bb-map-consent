<?php
if (!defined('ABSPATH')) { exit; }
/**
 * [bb_karte]: map in the colours of the site (MapLibre + OpenFreeMap) behind a vector placeholder.
 * The placeholder shows the real streets of the place. The server builds it once from the same
 * tiles the live map uses and serves it from the uploads folder, so visitors have no contact with
 * the tile server before they click. Front end: assets/karte/bb-map.js, the same file static sites use.
 */
final class BBMC_Karte {
    const DIR = 'bb-consent/karten';
    const HOOK = 'bbmc_karte_bauen';
    const TILES = 'https://tiles.openfreemap.org/planet';
    /** Drawn once and set in 1:1 behind the map, large enough for wide screens. */
    const W = 2800;
    const H = 900;
    /** Stroke width per road class at zoom 13; same table as STRASSEN in bb-map.js. */
    const ROADS = [['minor', ['minor'], 'nebenstrasse', 0.7], ['tertiary', ['tertiary'], 'strasse', 1.1], ['secondary', ['secondary'], 'strasse', 1.6], ['primary', ['primary', 'trunk'], 'strasse', 2.2], ['motorway', ['motorway'], 'strasse', 3]];

    public static function boot() {
        add_shortcode('bb_karte', [__CLASS__, 'shortcode']);
        add_shortcode('bb_karte_revoke', function () { return '<button type="button" class="bbmc-revoke-karte" data-bb-map-widerruf>Karten-Einwilligung widerrufen</button>'; });
        add_action(self::HOOK, [__CLASS__, 'build'], 10, 4);
    }
    public static function hex($color) {
        $color = strtolower(trim((string) $color));
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m)) { return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3]; }
        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '';
    }
    public static function mix($a, $b, $t) {
        $out = '#';
        foreach ([1, 3, 5] as $i) {
            $x = hexdec(substr($a, $i, 2));
            $y = hexdec(substr($b, $i, 2));
            $out .= sprintf('%02x', (int) floor($x + ($y - $x) * $t + 0.5));
        }
        return $out;
    }
    public static function light($hex) {
        $l = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            $l[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2] > 0.45;
    }
    /** Full palette from up to four given colours; same calculation as farben() in bb-map.js. */
    public static function colours(array $in) {
        $get = function ($key) use ($in) { return self::hex($in[$key] ?? ''); };
        $land = $get('land') ?: '#f2f1ed';
        $h = self::light($land);
        $text = $get('schrift') ?: ($h ? '#2b2b2b' : '#ffffff');
        $road = $get('strasse') ?: ($h ? '#ffffff' : self::mix($land, '#ffffff', 0.35));
        return [
            'land' => $land,
            'wasser' => $get('wasser') ?: self::mix($land, $h ? '#7fa6c4' : '#8fa3c8', 0.45),
            'gruen' => $get('gruen') ?: self::mix($land, $h ? '#7fa56b' : '#ffffff', $h ? 0.18 : 0.05),
            'strasse' => $road,
            'nebenstrasse' => $get('nebenstrasse') ?: self::mix($land, $road, 0.4),
            'bahn' => self::mix($land, $text, 0.2),
        ];
    }
    public static function shortcode($atts) {
        $a = shortcode_atts(['lat' => '', 'lng' => '', 'zoom' => '13', 'hoehe' => '400', 'titel' => '', 'route' => '',
            'land' => '', 'wasser' => '', 'strasse' => '', 'akzent' => '', 'schrift' => '', 'gruen' => '', 'nebenstrasse' => ''], $atts, 'bb_karte');
        if (!is_numeric($a['lat']) || !is_numeric($a['lng']) || abs((float) $a['lat']) > 85 || abs((float) $a['lng']) > 180) {
            return current_user_can('edit_posts') ? '<p><strong>[bb_karte]</strong> braucht lat und lng, zum Beispiel [bb_karte lat="50.1195" lng="8.6827"].</p>' : '';
        }
        $lat = round((float) $a['lat'], 6);
        $lng = round((float) $a['lng'], 6);
        $zoom = max(5, min(16, round((float) $a['zoom'], 1)));
        $given = [];
        $style = '--bb-map-h:' . max(150, min(1000, (int) $a['hoehe'])) . 'px;';
        foreach (['land', 'wasser', 'strasse', 'akzent', 'schrift', 'gruen', 'nebenstrasse'] as $key) {
            $given[$key] = self::hex($a[$key]);
            if ($given[$key] !== '') { $style .= '--bb-map-' . $key . ':' . $given[$key] . ';'; }
        }
        // Without an own accent the pin and button take the accent colour of the site (BB Consent → Gestaltung).
        if ($given['akzent'] === '') { $style .= '--bb-map-akzent:var(--bbmc-site-accent);'; }
        $base = plugins_url('assets/karte/', dirname(__FILE__));
        wp_enqueue_script('bb-karte', $base . 'bb-map.js', [], (string) @filemtime(dirname(__DIR__) . '/assets/karte/bb-map.js'), true);
        $privacy = class_exists('BBMC_Cmp') ? BBMC_Cmp::privacy_url() : (get_privacy_policy_url() ?: home_url('/datenschutz'));
        $route = esc_url($a['route'], ['https']);
        $html = '<bb-map lat="' . esc_attr($lat) . '" lng="' . esc_attr($lng) . '" zoom="' . esc_attr($zoom) . '" basis="' . esc_url($base) . '" datenschutz="' . esc_url($privacy) . '"';
        $image = self::url($lat, $lng, $zoom, $given);
        if ($image !== '') { $html .= ' platzhalter="' . esc_url($image) . '"'; }
        if ($route !== '') { $html .= ' route="' . $route . '"'; }
        if ($a['titel'] !== '') { $html .= ' titel="' . esc_attr($a['titel']) . '"'; }
        if (get_option('bbmc_cmp_address', 'sie') === 'du') { $html .= ' anrede="du"'; }
        // Without JavaScript only the route link remains.
        return $html . ' style="' . esc_attr($style) . '">' . ($route !== '' ? '<a href="' . $route . '" rel="noopener">Route planen</a>' : '') . '</bb-map>';
    }
    public static function name($lat, $lng, $zoom, array $given) {
        return 'karte-' . substr(md5(wp_json_encode([$lat, $lng, $zoom, self::colours($given), self::W, self::H, 1])), 0, 16) . '.svg';
    }
    /** Local URL of the placeholder, or '' while it does not exist yet. */
    public static function url($lat, $lng, $zoom, array $given) {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error'])) { return ''; }
        $name = self::name($lat, $lng, $zoom, $given);
        $url = $uploads['baseurl'] . '/' . self::DIR . '/' . $name;
        if (is_file($uploads['basedir'] . '/' . self::DIR . '/' . $name)) { return $url; }
        if (get_transient('bbmc_karte_fail_' . md5($name))) { return ''; }
        // Editors get the picture at once, visitors never wait for it: for them it is built in the background.
        if (current_user_can('edit_posts')) { return self::build($lat, $lng, $zoom, $given) ? $url : ''; }
        $args = [$lat, $lng, $zoom, $given];
        if (!wp_next_scheduled(self::HOOK, $args)) { wp_schedule_single_event(time(), self::HOOK, $args); }
        return '';
    }
    public static function build($lat, $lng, $zoom, $given) {
        $given = is_array($given) ? $given : [];
        $uploads = wp_upload_dir(null, false);
        $name = self::name($lat, $lng, $zoom, $given);
        $file = $uploads['basedir'] . '/' . self::DIR . '/' . $name;
        if (is_file($file)) { return true; }
        try {
            $svg = self::svg((float) $lat, (float) $lng, (float) $zoom, self::colours($given));
            if (!wp_mkdir_p(dirname($file)) || file_put_contents($file, $svg) === false) { throw new RuntimeException('write'); }
            return true;
        } catch (Throwable $e) {
            set_transient('bbmc_karte_fail_' . md5($name), 1, HOUR_IN_SECONDS);
            return false;
        }
    }
    public static function fetch($url) {
        $response = wp_safe_remote_get($url, ['timeout' => 8, 'redirection' => 0, 'limit_response_size' => 4194304]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { throw new RuntimeException('fetch'); }
        $body = (string) wp_remote_retrieve_body($response);
        // Tiles may arrive still gzipped when the server stores them compressed.
        if (strncmp($body, "\x1f\x8b", 2) === 0 && function_exists('gzdecode')) { $body = (string) gzdecode($body); }
        return $body;
    }
    public static function svg($lat, $lng, $zoom, array $c) {
        $info = json_decode(self::fetch(self::TILES), true);
        $template = is_array($info) && isset($info['tiles'][0]) ? (string) $info['tiles'][0] : '';
        if (!preg_match('~^https://tiles\.openfreemap\.org/[A-Za-z0-9_./-]*\{z\}/\{x\}/\{y\}\.pbf$~', $template)) { throw new RuntimeException('template'); }
        $tz = (int) min(14, floor($zoom));
        $world = 512 * pow(2, $zoom);
        $size = 512 * pow(2, $zoom - $tz);
        $mx = ($lng + 180) / 360 * $world;
        $s = sin(deg2rad($lat));
        $my = (0.5 - log((1 + $s) / (1 - $s)) / (4 * M_PI)) * $world;
        $x0 = $mx - self::W / 2;
        $y0 = $my - self::H / 2;
        $paths = ['gruen' => '', 'wasser' => '', 'bach' => '', 'bahn' => ''];
        $class = [];
        foreach (self::ROADS as $road) {
            $paths[$road[0]] = '';
            foreach ($road[1] as $name) { $class[$name] = $road[0]; }
        }
        $max = (1 << $tz) - 1;
        for ($tx = (int) floor($x0 / $size); $tx <= (int) floor(($x0 + self::W) / $size); $tx++) {
            for ($ty = (int) floor($y0 / $size); $ty <= (int) floor(($y0 + self::H) / $size); $ty++) {
                if ($tx < 0 || $ty < 0 || $tx > $max || $ty > $max) { continue; }
                $tile = self::fetch(strtr($template, ['{z}' => $tz, '{x}' => $tx, '{y}' => $ty]));
                foreach (self::layers($tile) as $layer) {
                    $scale = $size / $layer['extent'];
                    $ox = $tx * $size - $x0;
                    $oy = $ty * $size - $y0;
                    foreach ($layer['features'] as $feature) {
                        [$type, $kind, $geometry] = $feature;
                        $target = '';
                        if ($layer['name'] === 'park' && $type === 3) { $target = 'gruen'; }
                        elseif ($layer['name'] === 'landcover' && $type === 3 && in_array($kind, ['wood', 'grass'], true)) { $target = 'gruen'; }
                        elseif ($layer['name'] === 'water' && $type === 3) { $target = 'wasser'; }
                        elseif ($layer['name'] === 'waterway' && $type === 2) { $target = 'bach'; }
                        elseif ($layer['name'] === 'transportation' && $type === 2) { $target = $kind === 'rail' ? 'bahn' : ($class[$kind] ?? ''); }
                        if ($target !== '') { $paths[$target] .= self::path(self::rings($geometry), $ox, $oy, $scale, $type === 3); }
                    }
                }
            }
        }
        // Stroke widths like the live map: stops 8 → 0.3·w, 13 → w, 18 → 12·w, exponential with base 1.6.
        [$z0, $v0, $z1, $v1] = $zoom <= 13 ? [8, 0.3, 13, 1] : [13, 1, 18, 12];
        $factor = $v0 + (pow(1.6, min($z1, max($z0, $zoom)) - $z0) - 1) / (pow(1.6, $z1 - $z0) - 1) * ($v1 - $v0);
        $line = function ($key, $colour, $width) use ($paths, $factor) {
            return '<path d="' . $paths[$key] . '" fill="none" stroke="' . $colour . '" stroke-width="' . (floor($width * $factor * 100 + 0.5) / 100) . '" stroke-linecap="round" stroke-linejoin="round"/>';
        };
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . self::W . '" height="' . self::H . '" viewBox="0 0 ' . self::W . ' ' . self::H . '">'
            . '<rect width="' . self::W . '" height="' . self::H . '" fill="' . $c['land'] . '"/>'
            . '<path d="' . $paths['gruen'] . '" fill="' . $c['gruen'] . '"/><path d="' . $paths['wasser'] . '" fill="' . $c['wasser'] . '"/>'
            . $line('bach', $c['wasser'], 1) . $line('bahn', $c['bahn'], 0.8);
        foreach (self::ROADS as $road) { $svg .= $line($road[0], $c[$road[2]], $road[3]); }
        return $svg . '</svg>';
    }

    /* Mapbox Vector Tile (protobuf), only what the placeholder needs. */

    private static function varint($s, &$p) {
        $result = 0;
        $shift = 0;
        $len = strlen($s);
        do {
            if ($p >= $len || $shift > 63) { throw new RuntimeException('varint'); }
            $byte = ord($s[$p++]);
            $result |= ($byte & 0x7f) << $shift;
            $shift += 7;
        } while ($byte & 0x80);
        return $result;
    }
    /** [[field, value], …] of one message; length-delimited values as strings. */
    private static function fields($s) {
        $out = [];
        $p = 0;
        $len = strlen($s);
        while ($p < $len) {
            $key = self::varint($s, $p);
            $wire = $key & 7;
            if ($wire === 0) { $value = self::varint($s, $p); }
            elseif ($wire === 2) {
                $n = self::varint($s, $p);
                if ($n < 0 || $p + $n > $len) { throw new RuntimeException('length'); }
                $value = substr($s, $p, $n);
                $p += $n;
            }
            elseif ($wire === 5) { $value = null; $p += 4; }
            elseif ($wire === 1) { $value = null; $p += 8; }
            else { throw new RuntimeException('wire'); }
            $out[] = [$key >> 3, $value];
        }
        return $out;
    }
    private static function packed($s) {
        $out = [];
        $p = 0;
        $len = strlen($s);
        while ($p < $len) { $out[] = self::varint($s, $p); }
        return $out;
    }
    /** Layers we draw: [['name', 'extent', 'features' => [[type, class, geometry ints], …]], …]. */
    public static function layers($tile) {
        $wanted = ['park', 'landcover', 'water', 'waterway', 'transportation'];
        $layers = [];
        foreach (self::fields($tile) as [$field, $bytes]) {
            if ($field !== 3 || !is_string($bytes)) { continue; }
            $name = '';
            $extent = 4096;
            $keys = [];
            $values = [];
            $raw = [];
            foreach (self::fields($bytes) as [$f, $v]) {
                if ($f === 1) { $name = (string) $v; }
                elseif ($f === 2) { $raw[] = $v; }
                elseif ($f === 3) { $keys[] = (string) $v; }
                elseif ($f === 4) {
                    $text = null;
                    foreach (self::fields((string) $v) as [$vf, $vv]) { if ($vf === 1) { $text = (string) $vv; } }
                    $values[] = $text;
                }
                elseif ($f === 5) { $extent = max(1, (int) $v); }
            }
            if (!in_array($name, $wanted, true)) { continue; }
            $features = [];
            foreach ($raw as $feature) {
                $type = 0;
                $kind = '';
                $geometry = [];
                foreach (self::fields((string) $feature) as [$f, $v]) {
                    if ($f === 3) { $type = (int) $v; }
                    elseif ($f === 4) { $geometry = self::packed((string) $v); }
                    elseif ($f === 2) {
                        $tags = self::packed((string) $v);
                        for ($i = 0; $i + 1 < count($tags); $i += 2) {
                            if (($keys[$tags[$i]] ?? '') === 'class') { $kind = (string) ($values[$tags[$i + 1]] ?? ''); }
                        }
                    }
                }
                $features[] = [$type, $kind, $geometry];
            }
            $layers[] = ['name' => $name, 'extent' => $extent, 'features' => $features];
        }
        return $layers;
    }
    /** Geometry commands to rings of [x, y] in tile units. */
    public static function rings(array $g) {
        $rings = [];
        $ring = [];
        $x = 0;
        $y = 0;
        $n = count($g);
        for ($i = 0; $i < $n;) {
            $command = $g[$i] & 7;
            $count = $g[$i] >> 3;
            $i++;
            if ($command === 7) { continue; }
            if ($command !== 1 && $command !== 2) { throw new RuntimeException('geometry'); }
            for ($k = 0; $k < $count; $k++) {
                if ($i + 1 >= $n) { throw new RuntimeException('geometry'); }
                $dx = $g[$i++];
                $dy = $g[$i++];
                $x += ($dx >> 1) ^ -($dx & 1);
                $y += ($dy >> 1) ^ -($dy & 1);
                if ($command === 1 && $ring) { $rings[] = $ring; $ring = []; }
                $ring[] = [$x, $y];
            }
        }
        if ($ring) { $rings[] = $ring; }
        return $rings;
    }
    /** Douglas-Peucker without recursion: drops points closer than $t px to the line. */
    public static function thin(array $p, $t) {
        $n = count($p);
        if ($n < 3) { return $p; }
        $keep = array_fill(0, $n, false);
        $keep[0] = $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];
        while ($stack) {
            [$a, $b] = array_pop($stack);
            $dx = $p[$b][0] - $p[$a][0];
            $dy = $p[$b][1] - $p[$a][1];
            $length = sqrt($dx * $dx + $dy * $dy);
            $max = 0;
            $index = 0;
            for ($i = $a + 1; $i < $b; $i++) {
                $d = $length > 0 ? abs(($p[$i][0] - $p[$a][0]) * $dy - ($p[$i][1] - $p[$a][1]) * $dx) / $length : sqrt(pow($p[$i][0] - $p[$a][0], 2) + pow($p[$i][1] - $p[$a][1], 2));
                if ($d > $max) { $max = $d; $index = $i; }
            }
            if ($max > $t) { $stack[] = [$a, $index]; $stack[] = [$index, $b]; $keep[$index] = true; }
        }
        $out = [];
        foreach ($p as $i => $point) { if ($keep[$i]) { $out[] = $point; } }
        return $out;
    }
    public static function path(array $rings, $ox, $oy, $scale, $close) {
        $d = '';
        foreach ($rings as $ring) {
            $points = [];
            $inside = false;
            foreach ($ring as $q) {
                $px = $ox + $q[0] * $scale;
                $py = $oy + $q[1] * $scale;
                if ($px > -20 && $px < self::W + 20 && $py > -20 && $py < self::H + 20) { $inside = true; }
                $points[] = [$px, $py];
            }
            if (!$inside) { continue; }
            if ($close) {
                // Areas below 6 px are invisible and only cost file size.
                $xs = array_column($points, 0);
                $ys = array_column($points, 1);
                if (max($xs) - min($xs) < 6 && max($ys) - min($ys) < 6) { continue; }
            }
            $lx = $ly = null;
            foreach (self::thin($points, $close ? 1.2 : 0.5) as $i => $point) {
                $px = (int) floor($point[0] + 0.5);
                $py = (int) floor($point[1] + 0.5);
                if ($i && $px === $lx && $py === $ly) { continue; }
                $d .= $i ? 'l' . ($px - $lx) . ($py - $ly < 0 ? '' : ' ') . ($py - $ly) : 'M' . $px . ' ' . $py;
                $lx = $px;
                $ly = $py;
            }
            if ($close) { $d .= 'z'; }
        }
        return $d;
    }
}
BBMC_Karte::boot();
