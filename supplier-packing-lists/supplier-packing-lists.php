<?php
/**
 * Plugin Name: Supplier Packing Lists
 * Plugin URI: https://example.com/supplier-packing-lists
 * Description: A comprehensive plugin for suppliers to create and manage packing lists with PDF and CSV export functionality.
 * Version: 1.8.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: supplier-packing-lists
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('SPL_VERSION', '1.8.1');
define('SPL_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SPL_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_supplier_packing_lists() {
    require_once SPL_PLUGIN_PATH . 'includes/class-spl-activator.php';
    SPL_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_supplier_packing_lists() {
    require_once SPL_PLUGIN_PATH . 'includes/class-spl-deactivator.php';
    SPL_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_supplier_packing_lists');
register_deactivation_hook(__FILE__, 'deactivate_supplier_packing_lists');

/**
 * Check for database updates on plugin load
 */
function spl_check_database_updates() {
    $current_db_version = get_option('spl_db_version', '0');
    
    if (version_compare($current_db_version, SPL_VERSION, '<')) {
        require_once SPL_PLUGIN_PATH . 'includes/class-spl-activator.php';
        SPL_Activator::activate();
        update_option('spl_db_version', SPL_VERSION);
    }
}
add_action('plugins_loaded', 'spl_check_database_updates');

/**
 * The core plugin class.
 */
require SPL_PLUGIN_PATH . 'includes/class-spl.php';

/**
 * Begins execution of the plugin.
 */
function run_supplier_packing_lists() {
    $plugin = new SPL();
    $plugin->run();
}

run_supplier_packing_lists();
