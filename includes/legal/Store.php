<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
final class Store {
    const SCHEMA = 1;
    /** Textarten der Zentrale. AGB und Widerrufsbelehrung gibt es nur bei Shops und sind wie Social Media optional. */
    const TYPES = ['imprint','privacy_policy','social_media','terms','withdrawal'];
    public static function get($name, $default = []) { return get_option('bb_legal_' . $name, $default); }
    public static function put($name, $value) {
        $key = 'bb_legal_' . $name;
        if (false === get_option($key, false)) { add_option($key, $value, '', false); }
        else { update_option($key, $value, false); }
    }
    public static function identity() { return hash('sha256', self::get('endpoint', 'https://legal.bbkd.de') . '|' . self::get('site_id', '')); }
    public static function key($value = null) {
        $derived = hash('sha256', wp_salt('auth') . '|bb-legal-v1', true);
        if ($value !== null) {
            if (!function_exists('openssl_encrypt')) { throw new \RuntimeException('OpenSSL wird für die Schlüsselablage benötigt.'); }
            $iv = random_bytes(12);
            $cipher = openssl_encrypt($value, 'aes-256-gcm', $derived, OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher === false) { throw new \RuntimeException('Schlüssel konnte nicht geschützt gespeichert werden.'); }
            self::put('site_key', base64_encode($iv . $tag . $cipher));
            return;
        }
        $raw = base64_decode(self::get('site_key', ''), true);
        if (!$raw || strlen($raw) < 29 || !function_exists('openssl_decrypt')) { return ''; }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $derived, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
    public static function ready() {
        $schema = (int)self::get('schema_version', 1);
        $cache = self::get('cache');
        return $schema === self::SCHEMA && preg_match('/^[a-f0-9]{24}$/', self::get('site_id', ''))
            && ($cache['identity'] ?? '') === self::identity() && !empty($cache['outputs']) && self::validManifest($cache['outputs']);
    }
    public static function validManifest($outputs) {
        if (!is_array($outputs) || !$outputs) { return false; }
        $seen = []; $languages = [];
        foreach ($outputs as $row) {
            $p = $row['payload'] ?? null;
            // Unbekannte (neuere) Textarten überspringen statt das ganze Manifest abzulehnen.
            if (is_array($p) && is_string($p['type'] ?? null) && !in_array($p['type'], self::TYPES, true)) { continue; }
            if (!is_array($p) || ($p['site_id'] ?? '') !== self::get('site_id', '') || !is_int($row['id'] ?? null) || $row['id'] < 1
                || !in_array($p['type'] ?? '', self::TYPES, true)
                || !in_array($p['language'] ?? '', ['de','en'], true) || !is_string($p['html'] ?? null) || trim(wp_strip_all_tags($p['html'])) === ''
                || !is_string($p['title'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $row['hash'] ?? '')) { return false; }
            if (($row['entity'] ?? '') !== self::get('site_id','') . '/' . $p['type'] . '/' . $p['language'] || !is_string($row['created_at'] ?? null) || !is_string($row['prev_hash'] ?? null)) { return false; }
            $expected=hash('sha256',self::canonical(['outputs',$row['entity'],$row['created_at'],$row['prev_hash'],$p]));
            if (!hash_equals($expected,$row['hash'])) { return false; }
            $key = $p['type'] . '/' . $p['language'];
            if (isset($seen[$key])) { return false; } $seen[$key] = true; $languages[$p['language']] = true;
        }
        if (!$languages) { return false; }
        foreach ($languages as $lang => $unused) { if (!isset($seen['imprint/' . $lang], $seen['privacy_policy/' . $lang])) { return false; } }
        return true;
    }
    public static function canonical($value) {
        if(is_array($value)) {
            if(array_keys($value)!==range(0,count($value)-1) && $value!==[]) { ksort($value,SORT_STRING); }
            $value=array_map([__CLASS__,'canonicalValue'],$value);
        }
        return wp_json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
    }
    public static function canonicalValue($value) {
        if(!is_array($value)) { return $value; }
        if(array_keys($value)!==range(0,count($value)-1) && $value!==[]) { ksort($value,SORT_STRING); }
        return array_map([__CLASS__,'canonicalValue'],$value);
    }
    public static function status($code, $extra = []) { self::put('status', array_merge(['code'=>$code,'at'=>time()], $extra)); }
    public static function lock() {
        $now = time();
        if (add_option('bb_legal_sync_lock', $now, '', false)) { return true; }
        if ((int)self::get('sync_lock', $now) < $now - 180) { delete_option('bb_legal_sync_lock'); return add_option('bb_legal_sync_lock', $now, '', false); }
        return false;
    }
    public static function unlock() { delete_option('bb_legal_sync_lock'); }
}
