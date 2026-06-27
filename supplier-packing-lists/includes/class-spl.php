<?php
class SPL {
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        if (defined('SPL_VERSION')) {
            $this->version = SPL_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'supplier-packing-lists';
        
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spl-loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spl-groups.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spl-ajax.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-spl-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-spl-public.php';

        $this->loader = new SPL_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new SPL_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        
        // Process admin actions (Form submissions with headers redirects)
        $this->loader->add_action('admin_init', $plugin_admin, 'process_admin_actions');
        
        $ajax_handler = new SPL_Ajax();
        $this->loader->add_action('wp_ajax_spl_create_group', $ajax_handler, 'create_group');
        $this->loader->add_action('wp_ajax_spl_delete_group', $ajax_handler, 'delete_group');
        $this->loader->add_action('wp_ajax_spl_assign_pl_to_group', $ajax_handler, 'assign_pl_to_group');
        $this->loader->add_action('wp_ajax_spl_get_group_pls', $ajax_handler, 'get_group_pls');
        
        $this->loader->add_action('init', $this, 'register_post_type');
    }

    private function define_public_hooks() {
        $plugin_public = new SPL_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_shortcode('supplier_packing_lists', $plugin_public, 'render_shortcode');
    }
    
    public function register_post_type() {
        register_post_type('packing_list', array(
            'labels' => array(
                'name' => 'Packing Lists',
                'singular_name' => 'Packing List'
            ),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title')
        ));
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }
}
