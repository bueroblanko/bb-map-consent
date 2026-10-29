<?php
/** Plugin Name: BB Legal Basic Probe (isolated tests only) */
if(!defined('BB_LEGAL_TEST_ENV') || BB_LEGAL_TEST_ENV!==true) { return; }
require_once __DIR__.'/includes/legal/bootstrap.php';
add_action('plugins_loaded',function(){bb_legal_register_provider([
 'id'=>'basic','schema'=>1,'version'=>'1.0.0','path'=>__DIR__.'/includes/legal','url'=>plugins_url('includes/legal',__FILE__),'enabled'=>(bool)get_option('bb_legal_enabled_basic',false)
]);},1);
