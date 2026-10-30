<?php
/** Portable loader shared by BB Consent and a future BB Basic copy. */
if (!defined('ABSPATH')) { exit; }
if (!function_exists('bb_legal_register_provider')) {
    function bb_legal_register_provider(array $provider) {
        if (empty($provider['id']) || empty($provider['path']) || empty($provider['version'])) { return; }
        $GLOBALS['bb_legal_providers'][$provider['id']] = $provider;
    }
    function bb_legal_resolve() {
        $providers = array_values($GLOBALS['bb_legal_providers'] ?? []);
        usort($providers, function ($a, $b) { return version_compare($b['version'], $a['version']); });
        foreach ($providers as $provider) {
            if ((int)($provider['schema'] ?? 0) !== 1) { continue; }
            $file = $provider['path'] . '/Module.php';
            if (!is_file($file)) { continue; }
            if (!class_exists('BB\\Legal\\V1\\Module', false)) {
                foreach (['Store', 'Client', 'Module', 'Admin', 'Deactivate'] as $class) { require_once $provider['path'] . '/' . $class . '.php'; }
            }
            \BB\Legal\V1\Module::boot($providers, $provider);
            return;
        }
    }
    add_action('plugins_loaded', 'bb_legal_resolve', 100);
}
