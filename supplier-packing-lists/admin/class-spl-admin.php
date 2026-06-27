<?php
class SPL_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), $this->version, false);
        wp_localize_script($this->plugin_name, 'spl_admin_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spl_admin_nonce')
        ));
    }

    public function add_plugin_admin_menu() {
        // Main Menu
        add_menu_page(
            'Supplier Packing Lists',
            'Packing Lists',
            'manage_options',
            'supplier-packing-lists-admin',
            array($this, 'display_plugin_admin_page'),
            'dashicons-clipboard',
            26
        );

        // Submenus
        add_submenu_page(
            'supplier-packing-lists-admin',
            'All Packing Lists',
            'All Packing Lists',
            'manage_options',
            'supplier-packing-lists-admin',
            array($this, 'display_plugin_admin_page')
        );

        add_submenu_page(
            'supplier-packing-lists-admin',
            'Suppliers',
            'Suppliers',
            'manage_options',
            'spl-suppliers',
            array($this, 'display_supplier_management_page')
        );

        add_submenu_page(
            'supplier-packing-lists-admin',
            'Settings',
            'Settings',
            'manage_options',
            'spl-settings',
            array($this, 'display_plugin_settings_page')
        );

        add_submenu_page(
            'supplier-packing-lists-admin',
            'Backup & Import',
            'Backup & Import',
            'manage_options',
            'spl-backup',
            array($this, 'display_backup_page')
        );
    }

    public function process_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle Supplier Code Updates
        if (isset($_POST['spl_submit_suppliers'])) {
            if (!isset($_POST['spl_suppliers_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['spl_suppliers_nonce']), 'spl_update_suppliers')) {
                wp_die(esc_html__('Security check failed.', 'supplier-packing-lists'));
            }
            if (isset($_POST['suppliers']) && is_array($_POST['suppliers'])) {
                foreach ($_POST['suppliers'] as $user_id => $data) {
                    $user_id = intval($user_id);
                    if (isset($data['company_name'])) {
                        update_user_meta($user_id, 'company_name', sanitize_text_field($data['company_name']));
                    }
                    if (isset($data['supplier_code'])) {
                        update_user_meta($user_id, 'supplier_code', sanitize_text_field($data['supplier_code']));
                    }
                }
                add_settings_error('spl_messages', 'spl_suppliers_updated', 'Suppliers updated successfully.', 'updated');
            }
        }

        // Handle Settings Save
        if (isset($_POST['spl_submit_settings'])) {
            if (!isset($_POST['spl_settings_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['spl_settings_nonce']), 'spl_save_settings')) {
                wp_die(esc_html__('Security check failed.', 'supplier-packing-lists'));
            }
            update_option('spl_weight_unit', sanitize_text_field($_POST['spl_weight_unit']));
            update_option('spl_require_incoterm', isset($_POST['spl_require_incoterm']) ? 'yes' : 'no');
            add_settings_error('spl_messages', 'spl_settings_updated', 'Settings saved successfully.', 'updated');
        }

        // Handle PL Edit Save
        if (isset($_POST['spl_submit_edit_pl']) && isset($_POST['pl_id'])) {
            $pl_id = intval($_POST['pl_id']);
            if (!isset($_POST['spl_edit_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['spl_edit_nonce']), 'spl_edit_pl_' . $pl_id)) {
                wp_die(esc_html__('Security check failed.', 'supplier-packing-lists'));
            }
            
            update_post_meta($pl_id, '_spl_invoice_no', sanitize_text_field($_POST['invoice_no']));
            update_post_meta($pl_id, '_spl_incoterm', sanitize_text_field($_POST['incoterm']));
            update_post_meta($pl_id, '_spl_date', sanitize_text_field($_POST['pl_date']));
            update_post_meta($pl_id, '_spl_status', sanitize_text_field($_POST['status']));

            global $wpdb;
            $table_items = $wpdb->prefix . 'supplier_pl_items';
            
            // Delete and re-insert for deep edit handling
            if (isset($_POST['items']['desc']) && is_array($_POST['items']['desc'])) {
                $wpdb->delete($table_items, array('pl_id' => $pl_id));
                
                foreach ($_POST['items']['desc'] as $key => $desc) {
                    $desc = sanitize_textarea_field($desc);
                    if (empty($desc)) continue;
                    
                    $wpdb->insert($table_items, array(
                        'pl_id' => $pl_id,
                        'item_desc' => $desc,
                        'quantity' => floatval($_POST['items']['qty'][$key]),
                        'nw' => floatval($_POST['items']['nw'][$key]),
                        'gw' => floatval($_POST['items']['gw'][$key]),
                        'cbm' => floatval($_POST['items']['cbm'][$key])
                    ));
                }
            }
            
            wp_redirect(add_query_arg(array('page' => 'supplier-packing-lists-admin', 'updated' => 'true'), admin_url('admin.php')));
            exit;
        }
    }

    public function display_plugin_admin_page() {
        if (isset($_GET['action']) && $_GET['action'] === 'edit_pl' && isset($_GET['pl_id'])) {
            require_once plugin_dir_path(__FILE__) . 'partials/edit-pl-form.php';
        } else {
            require_once plugin_dir_path(__FILE__) . 'partials/admin-display.php';
        }
    }

    public function display_supplier_management_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/supplier-management.php';
    }

    public function display_plugin_settings_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/settings-display.php';
    }

    public function display_backup_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/backup-display.php';
    }
}
