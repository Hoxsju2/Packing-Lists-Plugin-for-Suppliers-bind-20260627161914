<?php
class SPL_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/public.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/public.js', array('jquery'), $this->version, false);
    }
    
    public function render_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to view packing lists.</p>';
        }
        
        $current_user = wp_get_current_user();
        if (!in_array('supplier', (array) $current_user->roles) && !current_user_can('manage_options')) {
            return '<p>You do not have permission to access this area.</p>';
        }

        ob_start();
        require_once plugin_dir_path(__FILE__) . 'partials/shortcode-display.php';
        return ob_get_clean();
    }
}
