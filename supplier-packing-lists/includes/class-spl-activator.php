<?php
class SPL_Activator {
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Custom Post Type is registered on init, but we create custom tables here
        $table_name_items = $wpdb->prefix . 'supplier_pl_items';
        $sql_items = "CREATE TABLE $table_name_items (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pl_id mediumint(9) NOT NULL,
            item_desc text NOT NULL,
            quantity decimal(10,2) NOT NULL,
            nw decimal(10,2) NOT NULL,
            gw decimal(10,2) NOT NULL,
            cbm decimal(10,4) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $table_name_groups = $wpdb->prefix . 'spl_groups';
        $sql_groups = "CREATE TABLE $table_name_groups (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            color varchar(7) DEFAULT '#0073aa',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $table_name_group_pls = $wpdb->prefix . 'spl_group_pls';
        $sql_group_pls = "CREATE TABLE $table_name_group_pls (
            group_id mediumint(9) NOT NULL,
            pl_id mediumint(9) NOT NULL,
            PRIMARY KEY  (group_id, pl_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_items);
        dbDelta($sql_groups);
        dbDelta($sql_group_pls);

        // Add supplier role
        add_role('supplier', 'Supplier', array(
            'read' => true,
        ));
    }
}
