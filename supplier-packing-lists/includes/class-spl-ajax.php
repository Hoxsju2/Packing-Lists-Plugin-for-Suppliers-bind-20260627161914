<?php
class SPL_Ajax {
    public function create_group() {
        check_ajax_referer('spl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $color = sanitize_hex_color($_POST['color']);
        
        $groups = new SPL_Groups();
        $id = $groups->create_group($name, $description, $color);
        
        if ($id) {
            wp_send_json_success(array('id' => $id));
        } else {
            wp_send_json_error('Failed to create group');
        }
    }
    
    public function delete_group() {
        check_ajax_referer('spl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $group_id = intval($_POST['group_id']);
        
        $wpdb->delete($wpdb->prefix . 'spl_groups', array('id' => $group_id));
        $wpdb->delete($wpdb->prefix . 'spl_group_pls', array('group_id' => $group_id));
        
        wp_send_json_success();
    }
    
    public function assign_pl_to_group() {
        check_ajax_referer('spl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $pl_id = intval($_POST['pl_id']);
        $group_id = intval($_POST['group_id']);
        
        $groups = new SPL_Groups();
        if ($groups->assign_pl_to_group($pl_id, $group_id)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to assign group');
        }
    }
    
    public function get_group_pls() {
        check_ajax_referer('spl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $group_id = intval($_POST['group_id']);
        
        $pl_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT pl_id FROM {$wpdb->prefix}spl_group_pls WHERE group_id = %d",
            $group_id
        ));
        
        if (empty($pl_ids)) {
            wp_send_json_success(array('pls' => array()));
        }
        
        $pls = array();
        foreach ($pl_ids as $pl_id) {
            $post = get_post($pl_id);
            if (!$post) continue;
            
            $supplier_id = get_post_meta($pl_id, '_spl_supplier_id', true);
            $supplier = get_userdata($supplier_id);
            
            $pls[] = array(
                'id' => $pl_id,
                'date' => get_post_meta($pl_id, '_spl_date', true) ?: $post->post_date,
                'supplier_name' => $supplier ? $supplier->display_name : 'Unknown',
                'invoice_no' => get_post_meta($pl_id, '_spl_invoice_no', true),
                'status' => get_post_meta($pl_id, '_spl_status', true) ?: 'pending',
                'item_count' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}supplier_pl_items WHERE pl_id = %d", $pl_id)),
                'edit_url' => admin_url('admin.php?page=supplier-packing-lists-admin&action=edit_pl&pl_id=' . $pl_id),
                'pdf_url' => admin_url('admin.php?page=supplier-packing-lists-admin&action=download_pdf&pl_ids=' . $pl_id),
                'csv_url' => admin_url('admin.php?page=supplier-packing-lists-admin&action=download_csv&pl_ids=' . $pl_id)
            );
        }
        
        wp_send_json_success(array('pls' => $pls));
    }
}
