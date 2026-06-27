<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle bulk actions with Security Nonce Validation
if (isset($_POST['bulk_action']) && $_POST['bulk_action'] !== '-1' && !empty($_POST['selected_pls'])) {
    // Validate Nonce
    if (!isset($_POST['spl_bulk_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['spl_bulk_nonce']), 'spl_bulk_actions')) {
        wp_die(esc_html__('Security check failed.', 'supplier-packing-lists'));
    }

    $selected_ids = array_map('intval', $_POST['selected_pls']);
    $action = sanitize_text_field($_POST['bulk_action']);
    
    switch ($action) {
        case 'delete':
            foreach ($selected_ids as $pl_id) {
                wp_delete_post($pl_id, true);
                $wpdb->delete($wpdb->prefix . 'supplier_pl_items', array('pl_id' => $pl_id));
            }
            echo '<div class="notice notice-success"><p>' . esc_html(count($selected_ids)) . ' packing list(s) deleted successfully.</p></div>';
            break;
            
        case 'approve':
            foreach ($selected_ids as $pl_id) {
                update_post_meta($pl_id, '_spl_status', 'approved');
            }
            echo '<div class="notice notice-success"><p>' . esc_html(count($selected_ids)) . ' packing list(s) approved successfully.</p></div>';
            break;
            
        case 'pending':
            foreach ($selected_ids as $pl_id) {
                update_post_meta($pl_id, '_spl_status', 'pending');
            }
            echo '<div class="notice notice-success"><p>' . esc_html(count($selected_ids)) . ' packing list(s) set to pending.</p></div>';
            break;
            
        case 'cancel':
            foreach ($selected_ids as $pl_id) {
                update_post_meta($pl_id, '_spl_status', 'cancelled');
            }
            echo '<div class="notice notice-success"><p>' . esc_html(count($selected_ids)) . ' packing list(s) cancelled.</p></div>';
            break;
            
        case 'assign_group':
            if (!empty($_POST['assign_group_id'])) {
                $groups_manager = new SPL_Groups();
                $group_id = intval($_POST['assign_group_id']);
                $success_count = 0;
                
                foreach ($selected_ids as $pl_id) {
                    if ($groups_manager->assign_pl_to_group($pl_id, $group_id)) {
                        $success_count++;
                    }
                }
                
                echo '<div class="notice notice-success"><p>' . esc_html($success_count) . ' packing list(s) assigned to group successfully.</p></div>';
            }
            break;
    }
}

// Handle individual status changes with Nonce Validation
if (isset($_GET['action']) && isset($_GET['pl_id']) && in_array($_GET['action'], array('approve', 'pending', 'cancel', 'delete'))) {
    $pl_id = intval($_GET['pl_id']);
    $action = sanitize_text_field($_GET['action']);
    
    // Validate Nonce for the specific packing list action
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'spl_single_action_' . $pl_id)) {
        wp_die(esc_html__('Security check failed for this action.', 'supplier-packing-lists'));
    }

    switch ($action) {
        case 'approve':
            update_post_meta($pl_id, '_spl_status', 'approved');
            echo '<div class="notice notice-success"><p>Packing list approved successfully.</p></div>';
            break;
        case 'pending':
            update_post_meta($pl_id, '_spl_status', 'pending');
            echo '<div class="notice notice-success"><p>Packing list set to pending.</p></div>';
            break;
        case 'cancel':
            update_post_meta($pl_id, '_spl_status', 'cancelled');
            echo '<div class="notice notice-success"><p>Packing list cancelled.</p></div>';
            break;
        case 'delete':
            wp_delete_post($pl_id, true);
            $wpdb->delete($wpdb->prefix . 'supplier_pl_items', array('pl_id' => $pl_id));
            echo '<div class="notice notice-success"><p>Packing list deleted successfully.</p></div>';
            break;
    }
}

// Get filter parameters (Sanitized)
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$supplier_filter = isset($_GET['supplier']) ? intval($_GET['supplier']) : '';
$group_filter = isset($_GET['group']) ? intval($_GET['group']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Build query
$meta_query = array('relation' => 'AND');
if ($status_filter) {
    $meta_query[] = array(
        'key' => '_spl_status',
        'value' => $status_filter,
        'compare' => '='
    );
}
if ($supplier_filter) {
    $meta_query[] = array(
        'key' => '_spl_supplier_id',
        'value' => $supplier_filter,
        'compare' => '='
    );
}

$date_query = array();
if ($date_from) {
    $date_query[] = array(
        'after' => $date_from,
        'inclusive' => true
    );
}
if ($date_to) {
    $date_query[] = array(
        'before' => $date_to,
        'inclusive' => true
    );
}

$args = array(
    'post_type' => 'packing_list',
    'post_status' => 'publish',
    'posts_per_page' => 20,
    'paged' => get_query_var('paged') ?: 1,
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => $meta_query
);

if (!empty($date_query)) {
    $args['date_query'] = $date_query;
}

// Handle group filter
if ($group_filter) {
    $group_pl_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT pl_id FROM {$wpdb->prefix}spl_group_pls WHERE group_id = %d",
        $group_filter
    ));
    
    if (!empty($group_pl_ids)) {
        $args['post__in'] = $group_pl_ids;
    } else {
        $args['post__in'] = array(0); // No results
    }
}

$query = new WP_Query($args);
$packing_lists = $query->posts;

// Get suppliers for filter
$suppliers = get_users(array(
    'role' => 'supplier',
    'fields' => array('ID', 'display_name')
));

// Get groups for filter and management
$groups_manager = new SPL_Groups();
$groups = $groups_manager->get_all_groups();

// Get statistics
$stats = array(
    'total' => wp_count_posts('packing_list')->publish,
    'pending' => count(get_posts(array(
        'post_type' => 'packing_list',
        'meta_key' => '_spl_status',
        'meta_value' => 'pending',
        'posts_per_page' => -1
    ))),
    'approved' => count(get_posts(array(
        'post_type' => 'packing_list',
        'meta_key' => '_spl_status',
        'meta_value' => 'approved',
        'posts_per_page' => -1
    ))),
    'cancelled' => count(get_posts(array(
        'post_type' => 'packing_list',
        'meta_key' => '_spl_status',
        'meta_value' => 'cancelled',
        'posts_per_page' => -1
    )))
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Supplier Packing Lists</h1>
    
    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #3b82f6;">📋 Total</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #1f2937;"><?php echo esc_html($stats['total']); ?></p>
        </div>
        <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #f59e0b;">⏳ Pending</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #1f2937;"><?php echo esc_html($stats['pending']); ?></p>
        </div>
        <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #10b981;">✅ Approved</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #1f2937;"><?php echo esc_html($stats['approved']); ?></p>
        </div>
        <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #ef4444;">❌ Cancelled</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 0; color: #1f2937;"><?php echo esc_html($stats['cancelled']); ?></p>
        </div>
    </div>
    
    <!-- Group Management Section -->
    <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: #0073aa;">🏷️ Group Management</h2>
            <button type="button" id="create-group-btn" class="button button-primary">
                ➕ Create New Group
            </button>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
            <?php foreach ($groups as $group): ?>
                <div class="group-card" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #f9f9f9;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <div>
                            <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo esc_attr($group->color); ?>;"></span>
                                <?php echo esc_html($group->name); ?>
                            </h4>
                            <?php if ($group->description): ?>
                                <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;"><?php echo esc_html($group->description); ?></p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="delete-group-btn button button-link-delete" 
                                data-group-id="<?php echo intval($group->id); ?>"
                                onclick="return confirm('Are you sure you want to delete this group? All PLs will be unassigned.')">
                            🗑️
                        </button>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid #ddd;">
                        <span style="color: #666; font-size: 13px;">
                            <?php echo intval($group->pl_count); ?> PL(s)
                        </span>
                        <?php if ($group->pl_count > 0): ?>
                            <button type="button" class="view-group-pls button button-small" 
                                    data-group-id="<?php echo intval($group->id); ?>"
                                    data-group-name="<?php echo esc_attr($group->name); ?>"
                                    data-group-color="<?php echo esc_attr($group->color); ?>">
                                👁️ View PLs
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Filters -->
    <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">
        <form method="get" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
            <input type="hidden" name="page" value="supplier-packing-lists-admin">
            
            <div>
                <label><strong>Status:</strong></label>
                <select name="status" style="padding: 5px;">
                    <option value="">All Status</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                    <option value="approved" <?php selected($status_filter, 'approved'); ?>>Approved</option>
                    <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>Cancelled</option>
                </select>
            </div>
            
            <div>
                <label><strong>Supplier:</strong></label>
                <select name="supplier" style="padding: 5px;">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <?php $company_name = get_user_meta($supplier->ID, 'company_name', true); ?>
                        <option value="<?php echo esc_attr($supplier->ID); ?>" <?php selected($supplier_filter, $supplier->ID); ?>>
                            <?php echo esc_html($company_name ?: $supplier->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label><strong>Group:</strong></label>
                <select name="group" style="padding: 5px;">
                    <option value="">All Groups</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo esc_attr($group->id); ?>" <?php selected($group_filter, $group->id); ?>>
                            <?php echo esc_html($group->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label><strong>From:</strong></label>
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="padding: 5px;">
            </div>
            
            <div>
                <label><strong>To:</strong></label>
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="padding: 5px;">
            </div>
            
            <button type="submit" class="button button-primary">Filter</button>
            
            <?php if ($status_filter || $supplier_filter || $group_filter || $date_from || $date_to): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=supplier-packing-lists-admin')); ?>" class="button">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Bulk Actions Form -->
    <form method="post" id="packing-lists-form">
        <?php wp_nonce_field('spl_bulk_actions', 'spl_bulk_nonce'); ?>
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1">Bulk Actions</option>
                    <option value="approve">Approve</option>
                    <option value="pending">Set to Pending</option>
                    <option value="cancel">Cancel</option>
                    <option value="assign_group">Assign to Group</option>
                    <option value="delete">Delete</option>
                </select>
                
                <select name="assign_group_id" id="assign-group-selector" style="display: none;">
                    <option value="">Select Group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo esc_attr($group->id); ?>"><?php echo esc_html($group->name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <input type="submit" class="button action" value="Apply" onclick="return confirm('Are you sure you want to perform this action?');">
            </div>
            
            <div class="alignright actions">
                <button type="button" id="export-selected-pdf" class="button" disabled>📄 Export Selected PDF</button>
                <button type="button" id="export-selected-csv" class="button" disabled>📊 Export Selected Excel</button>
                <a href="<?php echo esc_url(add_query_arg(array('action' => 'download_pdf', 'pl_ids' => implode(',', wp_list_pluck($packing_lists, 'ID'))), admin_url('admin.php?page=supplier-packing-lists-admin'))); ?>" 
                   class="button">📄 Download All PDFs</a>
                <a href="<?php echo esc_url(add_query_arg(array('action' => 'download_csv', 'pl_ids' => implode(',', wp_list_pluck($packing_lists, 'ID'))), admin_url('admin.php?page=supplier-packing-lists-admin'))); ?>" 
                   class="button">📊 Download All Excel</a>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th class="manage-column">PL ID</th>
                    <th class="manage-column">Date</th>
                    <th class="manage-column">Invoice No.</th>
                    <th class="manage-column">Supplier</th>
                    <th class="manage-column">Supplier Code</th>
                    <th class="manage-column">Group</th>
                    <th class="manage-column">Status</th>
                    <th class="manage-column">Items</th>
                    <th class="manage-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($packing_lists)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px;">
                            <p>No packing lists found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($packing_lists as $pl): ?>
                        <?php
                        $supplier_id = get_post_meta($pl->ID, '_spl_supplier_id', true);
                        $supplier = get_userdata($supplier_id);
                        $supplier_name = get_user_meta($supplier_id, 'company_name', true);
                        $supplier_code = get_user_meta($supplier_id, 'supplier_code', true);
                        $invoice_no = get_post_meta($pl->ID, '_spl_invoice_no', true);
                        $pl_date = get_post_meta($pl->ID, '_spl_date', true);
                        $incoterm = get_post_meta($pl->ID, '_spl_incoterm', true);
                        $status = get_post_meta($pl->ID, '_spl_status', true) ?: 'pending';
                        
                        // Get group for this PL
                        $pl_group = $groups_manager->get_pl_group($pl->ID);
                        
                        $item_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}supplier_pl_items WHERE pl_id = %d",
                            $pl->ID
                        ));
                        
                        $status_colors = array(
                            'pending' => '#f59e0b',
                            'approved' => '#10b981', 
                            'cancelled' => '#ef4444'
                        );
                        
                        $status_color = $status_colors[$status] ?? '#6b7280';
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="selected_pls[]" value="<?php echo esc_attr($pl->ID); ?>" class="pl-checkbox">
                            </th>
                            <td><strong>#<?php echo esc_html($pl->ID); ?></strong></td>
                            <td><?php echo esc_html(date('M d, Y', strtotime($pl_date ?: $pl->post_date))); ?></td>
                            <td><?php echo esc_html($invoice_no ?: '-'); ?></td>
                            <td><?php echo esc_html($supplier_name ?: ($supplier ? $supplier->display_name : 'Unknown')); ?></td>
                            <td>
                                <?php if ($supplier_code): ?>
                                    <code style="background: #f0f8ff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        <?php echo esc_html($supplier_code); ?>
                                    </code>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pl_group): ?>
                                    <button type="button" class="view-group-pls button button-small" 
                                            data-group-id="<?php echo esc_attr($pl_group->id); ?>"
                                            data-group-name="<?php echo esc_attr($pl_group->name); ?>"
                                            data-group-color="<?php echo esc_attr($pl_group->color); ?>"
                                            style="background: <?php echo esc_attr($pl_group->color); ?>; color: white; border: none; font-size: 11px; padding: 4px 8px;">
                                        <?php echo esc_html($pl_group->name); ?>
                                    </button>
                                <?php else: ?>
                                    <select class="assign-group-select" data-pl-id="<?php echo esc_attr($pl->ID); ?>" style="font-size: 11px; padding: 2px;">
                                        <option value="">No Group</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo esc_attr($group->id); ?>"><?php echo esc_html($group->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background: <?php echo esc_attr($status_color); ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; text-transform: uppercase;">
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td><?php echo intval($item_count); ?> items</td>
                            <td>
                                <div style="display: flex; gap: 3px; flex-wrap: wrap;">
                                    <!-- Individual Download Actions -->
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'download_pdf', 'pl_ids' => $pl->ID), admin_url('admin.php?page=supplier-packing-lists-admin'))); ?>" 
                                       class="button button-small" title="Download PDF">📄</a>
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'download_csv', 'pl_ids' => $pl->ID), admin_url('admin.php?page=supplier-packing-lists-admin'))); ?>" 
                                       class="button button-small" title="Download Excel">📊</a>
                                    
                                    <!-- Status Actions (Nonces protected) -->
                                    <?php if ($status !== 'approved'): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'approve', 'pl_id' => $pl->ID)), 'spl_single_action_' . $pl->ID)); ?>" 
                                           class="button button-small" style="background: #10b981; color: white; border-color: #10b981;"
                                           title="Approve" onclick="return confirm('Approve this packing list?')">✓</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($status !== 'pending'): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'pending', 'pl_id' => $pl->ID)), 'spl_single_action_' . $pl->ID)); ?>" 
                                           class="button button-small" style="background: #f59e0b; color: white; border-color: #f59e0b;"
                                           title="Set to Pending" onclick="return confirm('Set this packing list to pending?')">⏳</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($status !== 'cancelled'): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'cancel', 'pl_id' => $pl->ID)), 'spl_single_action_' . $pl->ID)); ?>" 
                                           class="button button-small" style="background: #ef4444; color: white; border-color: #ef4444;"
                                           title="Cancel" onclick="return confirm('Cancel this packing list?')">✕</a>
                                    <?php endif; ?>
                                    
                                    <!-- View/Edit Actions -->
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit_pl', 'pl_id' => $pl->ID))); ?>" 
                                       class="button button-small" title="Edit">📝</a>
                                    
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'pl_id' => $pl->ID)), 'spl_single_action_' . $pl->ID)); ?>" 
                                       class="button button-small" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this packing list? This action cannot be undone.')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($query->max_num_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $query->max_num_pages,
                        'current' => max(1, get_query_var('paged'))
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Modals are kept below exactly the same as previously, preserving JS mapping -->
<div id="create-group-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
    <!-- [Rest of the modal code is unmodified, JS takes care of AJAX nonces] -->
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; width: 90%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="padding: 20px; border-bottom: 1px solid #ddd; background: #f9f9f9; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; color: #0073aa;">➕ Create New Group</h2>
            <button type="button" id="close-create-group-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        
        <form id="create-group-form" style="padding: 20px;">
            <div style="margin-bottom: 15px;">
                <label for="group_name" style="display: block; font-weight: bold; margin-bottom: 5px;">Group Name *</label>
                <input type="text" id="group_name" name="group_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="group_description" style="display: block; font-weight: bold; margin-bottom: 5px;">Description</label>
                <textarea id="group_description" name="group_description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="group_color" style="display: block; font-weight: bold; margin-bottom: 5px;">Color</label>
                <input type="color" id="group_color" name="group_color" value="#0073aa" style="width: 50px; height: 35px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="cancel-create-group" class="button">Cancel</button>
                <button type="submit" class="button button-primary">Create Group</button>
            </div>
        </form>
    </div>
</div>

<!-- Group PLs Modal -->
<div id="group-pls-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; width: 95%; max-width: 1200px; max-height: 80vh; overflow: hidden; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="padding: 20px; border-bottom: 1px solid #ddd; background: #f9f9f9; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <span id="group-color-indicator" style="display: inline-block; width: 16px; height: 16px; border-radius: 50%;"></span>
                Group: <span id="modal-group-name">Group Name</span>
            </h2>
            <button type="button" id="close-group-pls-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        
        <div id="group-pls-content" style="padding: 20px; overflow-y: auto; max-height: 60vh;">
            <div style="text-align: center; padding: 40px;">
                <span class="spinner is-active"></span>
                <p>Loading group packing lists...</p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide assign group selector based on bulk action
    $('#bulk-action-selector-top').on('change', function() {
        if ($(this).val() === 'assign_group') {
            $('#assign-group-selector').show();
        } else {
            $('#assign-group-selector').hide();
        }
    });
    
    // Select all checkbox functionality
    const selectAll = document.getElementById('cb-select-all-1');
    const checkboxes = document.querySelectorAll('input[name="selected_pls[]"]');
    
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        toggleExportButtons();
    });
    
    // Individual checkbox change
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('input[name="selected_pls[]"]:checked').length;
            selectAll.checked = checkedCount === checkboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            toggleExportButtons();
        });
    });
    
    // Toggle export buttons based on selection
    function toggleExportButtons() {
        const selectedCount = document.querySelectorAll('input[name="selected_pls[]"]:checked').length;
        const exportPdfBtn = document.getElementById('export-selected-pdf');
        const exportCsvBtn = document.getElementById('export-selected-csv');
        
        if (selectedCount > 0) {
            exportPdfBtn.disabled = false;
            exportCsvBtn.disabled = false;
            exportPdfBtn.textContent = `📄 Export Selected (${selectedCount}) PDF`;
            exportCsvBtn.textContent = `📊 Export Selected (${selectedCount}) Excel`;
        } else {
            exportPdfBtn.disabled = true;
            exportCsvBtn.disabled = true;
            exportPdfBtn.textContent = '📄 Export Selected PDF';
            exportCsvBtn.textContent = '📊 Export Selected Excel';
        }
    }
    
    // Handle selected export buttons
    $('#export-selected-pdf').on('click', function() {
        const selectedIds = [];
        $('input[name="selected_pls[]"]:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length > 0) {
            const url = '<?php echo admin_url('admin.php?page=supplier-packing-lists-admin'); ?>&action=download_pdf&pl_ids=' + selectedIds.join(',');
            window.location.href = url;
        }
    });
    
    $('#export-selected-csv').on('click', function() {
        const selectedIds = [];
        $('input[name="selected_pls[]"]:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length > 0) {
            const url = '<?php echo admin_url('admin.php?page=supplier-packing-lists-admin'); ?>&action=download_csv&pl_ids=' + selectedIds.join(',');
            window.location.href = url;
        }
    });
    
    // Create Group Modal
    $('#create-group-btn').on('click', function() {
        $('#create-group-modal').show();
    });
    
    $('#close-create-group-modal, #cancel-create-group').on('click', function() {
        $('#create-group-modal').hide();
        // Clear form
        $('#create-group-form')[0].reset();
    });
    
    // Click outside modal to close
    $('#create-group-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
            $('#create-group-form')[0].reset();
        }
    });
    
    $('#create-group-form').on('submit', function(e) {
        e.preventDefault();
        
        const name = $('#group_name').val().trim();
        const description = $('#group_description').val().trim();
        const color = $('#group_color').val();
        
        if (!name) {
            alert('Group name is required');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Creating...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'spl_create_group',
                name: name,
                description: description,
                color: color,
                nonce: '<?php echo wp_create_nonce('spl_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Group created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to create group. Please try again. Error: ' + error);
            },
            complete: function() {
                // Reset button state
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Delete Group
    $('.delete-group-btn').on('click', function() {
        const groupId = $(this).data('group-id');
        
        $.post(ajaxurl, {
            action: 'spl_delete_group',
            group_id: groupId,
            nonce: '<?php echo wp_create_nonce('spl_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Group deleted successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Assign PL to Group
    $('.assign-group-select').on('change', function() {
        const plId = $(this).data('pl-id');
        const groupId = $(this).val();
        
        if (groupId) {
            $.post(ajaxurl, {
                action: 'spl_assign_pl_to_group',
                pl_id: plId,
                group_id: groupId,
                nonce: '<?php echo wp_create_nonce('spl_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
    });
    
    // View Group PLs
    $('.view-group-pls').on('click', function() {
        const groupId = $(this).data('group-id');
        const groupName = $(this).data('group-name');
        const groupColor = $(this).data('group-color');
        
        $('#modal-group-name').text(groupName);
        $('#group-color-indicator').css('background-color', groupColor);
        $('#group-pls-modal').show();
        
        $.post(ajaxurl, {
            action: 'spl_get_group_pls',
            group_id: groupId,
            nonce: '<?php echo wp_create_nonce('spl_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                let html = '';
                
                if (response.data.pls.length === 0) {
                    html = '<div style="text-align: center; padding: 40px; color: #666;"><h3>No Packing Lists in Group</h3><p>This group is empty.</p></div>';
                } else {
                    html = '<div style="overflow-x: auto;">';
                    html += '<table class="wp-list-table widefat fixed striped" style="margin: 0;">';
                    html += '<thead><tr>';
                    html += '<th>PL ID</th><th>Date</th><th>Supplier</th><th>Invoice</th><th>Status</th><th>Items</th><th>Actions</th>';
                    html += '</tr></thead><tbody>';
                    
                    response.data.pls.forEach(function(pl) {
                        let statusColor = '#6b7280';
                        if (pl.status === 'pending') statusColor = '#f59e0b';
                        if (pl.status === 'approved') statusColor = '#10b981';
                        if (pl.status === 'cancelled') statusColor = '#ef4444';
                        
                        html += '<tr>';
                        html += '<td><strong>#' + pl.id + '</strong></td>';
                        html += '<td>' + pl.date + '</td>';
                        html += '<td>' + pl.supplier_name + '</td>';
                        html += '<td>' + (pl.invoice_no || '-') + '</td>';
                        html += '<td><span style="background: ' + statusColor + '; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">' + pl.status + '</span></td>';
                        html += '<td>' + pl.item_count + '</td>';
                        html += '<td>';
                        html += '<a href="' + pl.edit_url + '" class="button button-primary button-small">✏️ Edit</a> ';
                        html += '<a href="' + pl.pdf_url + '" class="button button-secondary button-small">📄</a> ';
                        html += '<a href="' + pl.csv_url + '" class="button button-secondary button-small">📊</a>';
                        html += '</td></tr>';
                    });
                    
                    html += '</tbody></table></div>';
                }
                
                $('#group-pls-content').html(html);
            } else {
                $('#group-pls-content').html('<div style="text-align: center; padding: 40px; color: #d63638;"><h3>Error</h3><p>' + response.data + '</p></div>');
            }
        });
    });
    
    // Close Group PLs Modal
    $('#close-group-pls-modal, #group-pls-modal').on('click', function(e) {
        if (e.target === this) {
            $('#group-pls-modal').hide();
        }
    });
    
    // Initial call to set button state
    toggleExportButtons();
});
</script>
