<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
final class Client {
    public function request($method, $resource, $data = null) {
        $endpoint = Store::get('endpoint', 'https://legal.bbkd.de');
        if (!preg_match('~^https://[^/?#]+(?:/[^?#]*)?$~', $endpoint) || !wp_http_validate_url($endpoint)) { throw new \RuntimeException('Ungültige HTTPS-Adresse der Zentrale.'); }
        $site = Store::get('site_id', ''); $key = Store::key();
        if (!preg_match('/^[a-f0-9]{24}$/', $site) || $key === '') { throw new \RuntimeException('Verbindungsdaten fehlen.'); }
        $url = rtrim($endpoint, '/') . '/api/v1/sites/' . $site . '/' . $resource;
        if ($method === 'GET') { $url .= '?bb_legal_request=' . bin2hex(random_bytes(12)); }
        $parts = wp_parse_url($url); $target = $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $body = $data === null ? '' : wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) { throw new \RuntimeException('Daten konnten nicht kodiert werden.'); }
        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $method . "\n" . $target . "\n" . $timestamp . "\n" . $body, $key);
        $result = wp_safe_remote_request($url, ['method'=>$method,'timeout'=>15,'redirection'=>0,'limit_response_size'=>2097153,'headers'=>[
            'Content-Type'=>'application/json','X-BB-Site'=>$site,'X-BB-Timestamp'=>$timestamp,'X-BB-Signature'=>$signature,
            // Lets the Zentrale push new texts; page caches often keep WP-Cron from polling.
            'X-BB-Push-Url'=>rest_url('bb-legal/v1/push')
        ],'body'=>$body,'sslverify'=>true]);
        if (is_wp_error($result)) { throw new \RuntimeException('Zentrale nicht erreichbar ('.$result->get_error_message().'). Letzte lokale Fassung bleibt erhalten.'); }
        $code = wp_remote_retrieve_response_code($result); $raw = wp_remote_retrieve_body($result);
        if ($code !== 200 || strlen($raw) > 2097152) { throw new \RuntimeException('Zentrale meldet HTTP ' . (int)$code . '. Letzte lokale Fassung bleibt erhalten.'); }
        $json = json_decode($raw, true);
        if (!is_array($json) || json_last_error() !== JSON_ERROR_NONE) { throw new \RuntimeException('Ungültige Antwort der Zentrale.'); }
        return $json;
    }
    public function sync() {
        if (!Store::lock()) { return false; }
        try {
            $manifest = $this->request('GET', 'legal');
            if (!Store::validManifest($manifest['outputs'] ?? null)) { throw new \RuntimeException('Antwort der Zentrale ist unvollständig oder die Prüfsumme stimmt nicht.'); }
            $old = Store::get('cache');
            $next = ['identity'=>Store::identity(),'outputs'=>$manifest['outputs'],'fetched_at'=>time()];
            Store::put('cache', $next); Store::put('schema_version', Store::SCHEMA);
            $changed = ($old['identity'] ?? '') !== $next['identity'] || wp_json_encode($old['outputs'] ?? []) !== wp_json_encode($next['outputs']);
            if ($changed) {
                Store::put('observed', []);
                do_action('bb_legal_outputs_updated', $next, $old);
            }
            // Inputs are independent of the published output cache; unsent edits are never overwritten.
            try {
                $inputs = $this->request('GET', 'inputs');
                if (!is_array($inputs['inputs'] ?? null) || !is_array($inputs['schema']['payload']['fields'] ?? null) || !array_key_exists('version', $inputs)) { throw new \RuntimeException('Fragebogen ungültig.'); }
                Store::put('remote_inputs', $inputs + ['identity'=>Store::identity()]);
            } catch (\Throwable $e) { Store::status('outputs_only'); return true; }
            Store::status('synced'); return true;
        } catch (\Throwable $e) { Store::status('sync_failed', ['message'=>$e->getMessage()]); return false; }
        finally { Store::unlock(); }
    }
    public function submit($inputs, $base) {
        Store::put('draft', ['inputs'=>$inputs,'base_version_id'=>$base,'at'=>time(),'identity'=>Store::identity()]);
        try {
            $result = $this->request('PUT', 'inputs', ['inputs'=>$inputs,'base_version_id'=>$base]);
            if (!in_array($result['status'] ?? '', ['ok','merged','conflict','pending'], true)) { throw new \RuntimeException('Unbekannter Synchronisationsstatus.'); }
            if ($result['status'] !== 'pending' && (!is_array($result['inputs'] ?? null) || !isset($result['version']))) { throw new \RuntimeException('Unvollständiger Eingabestand.'); }
            Store::put('last_submission', ['status'=>$result['status'],'conflicts'=>$result['conflicts'] ?? [],'render_errors'=>!empty($result['render_errors']),'at'=>time()]);
            delete_option('bb_legal_draft');
            $this->sync();
            return $result['status'];
        } catch (\Throwable $e) { Store::status('draft_saved'); return 'draft'; }
    }
    /** Bausteinfolge eines Textes live aus der Zentrale (für den Editor). */
    public function segments($type, $lang) {
        if (!in_array($type, Store::TYPES, true) || !in_array($lang, ['de','en'], true)) { throw new \RuntimeException('Unbekannter Text.'); }
        $reply = $this->request('GET', 'segments/' . $type . '/' . $lang);
        if (!is_array($reply['segments'] ?? null)) { throw new \RuntimeException('Ungültige Antwort der Zentrale.'); }
        return $reply['segments'];
    }
    /**
     * Geänderte Bausteine, eigene Texte und Rücksetzungen senden. Unveränderte Bausteine erkennt die Zentrale selbst.
     * Ist sie nicht erreichbar, bleibt alles als lokaler Entwurf erhalten.
     */
    public function saveSegments($type, $lang, $blocks, $inserts, $reset) {
        $key = $type . '/' . $lang;
        $drafts = Store::get('segment_drafts'); $drafts = is_array($drafts) ? $drafts : [];
        $drafts[$key] = ['blocks'=>$blocks,'inserts'=>$inserts,'reset'=>$reset,'at'=>time(),'identity'=>Store::identity()];
        Store::put('segment_drafts', $drafts);
        try {
            $result = $this->request('PUT', 'segments/' . $type . '/' . $lang, ['blocks'=>(object)$blocks,'inserts'=>(object)$inserts,'reset'=>array_values($reset)]);
            if (!in_array($result['status'] ?? '', ['ok','unchanged'], true)) { throw new \RuntimeException('Unbekannter Status.'); }
            unset($drafts[$key]); Store::put('segment_drafts', $drafts);
            $this->sync();
            return 'segments_' . $result['status'];
        } catch (\Throwable $e) { Store::status('draft_saved'); return 'segments_draft'; }
    }
    public function acknowledge() {
        $observed = Store::get('observed'); $cache = Store::get('cache');
        if (($cache['identity'] ?? '') !== Store::identity() || !$observed) { return false; }
        $allowed = array_column($cache['outputs'] ?? [], 'id');
        $ids = array_values(array_intersect($allowed, array_map('intval', array_keys($observed))));
        if (!$ids) { return false; }
        $previous=Store::get('acknowledged'); if(($previous['identity']??'')===Store::identity() && !array_diff($ids,$previous['ids']??[])) { return true; }
        try {
            $reply = $this->request('POST', 'ack', ['output_ids'=>$ids]);
            if (($reply['status'] ?? '') !== 'ok') { return false; }
            Store::put('acknowledged', ['ids'=>$ids,'at'=>time(),'identity'=>Store::identity()]); return true;
        } catch (\Throwable $e) { Store::status('ack_failed'); return false; }
    }
}
