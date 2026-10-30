<?php
namespace BB\Legal\V1;
if (!defined('ABSPATH')) { exit; }
class Elementor extends \Elementor\Widget_Base {
    private $legacyName;
    public function __construct($name, $data = [], $args = null) { $this->legacyName=$name; parent::__construct($data,$args); }
    public function get_name() { return $this->legacyName; }
    public function get_title() { return 'Rechtstext'; }
    public function get_icon() { return 'eicon-document-file'; }
    public function get_categories() { return ['general']; }
    protected function register_controls() {
        $this->start_controls_section('content_section',['label'=>'Rechtstext']);
        $this->add_control('type',['label'=>'Text','type'=>\Elementor\Controls_Manager::SELECT,'default'=>'imprint','options'=>['imprint'=>'Impressum','privacy_policy'=>'Datenschutz','social_media'=>'Social Media','privacy_policy_social_media'=>'Social Media (alt)']]);
        $this->add_control('lang',['label'=>'Sprache','type'=>\Elementor\Controls_Manager::SELECT,'default'=>'de','options'=>['de'=>'Deutsch','en'=>'Englisch']]);
        $this->add_control('strip_title',['label'=>'Titel ausblenden','type'=>\Elementor\Controls_Manager::SWITCHER,'return_value'=>'true']);
        $this->end_controls_section();
    }
    protected function render() { echo Module::shortcode($this->get_settings_for_display()); }
}
