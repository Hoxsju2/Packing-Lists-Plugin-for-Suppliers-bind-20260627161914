<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
global $wpdb;

// Handle Form Submission securely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spl_submit_pl'])) {
    
    // Validate form nonce securely
    if (!isset($_POST['spl_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['spl_nonce']), 'spl_create_pl')) {
        wp_die(esc_html__('Security check failed.', 'supplier-packing-lists'));
    }

    $invoice_no = sanitize_text_field($_POST['invoice_no']);
    $incoterm = sanitize_text_field($_POST['incoterm']);
    $pl_date = sanitize_text_field($_POST['pl_date']);
    
    // Create the packing list post
    $pl_id = wp_insert_post(array(
        'post_title' => 'PL - ' . $invoice_no . ' - ' . date('Y-m-d'),
        'post_type' => 'packing_list',
        'post_status' => 'publish',
        'post_author' => $current_user->ID
    ));
    
    if ($pl_id) {
        update_post_meta($pl_id, '_spl_supplier_id', $current_user->ID);
        update_post_meta($pl_id, '_spl_invoice_no', $invoice_no);
        update_post_meta($pl_id, '_spl_incoterm', $incoterm);
        update_post_meta($pl_id, '_spl_date', $pl_date);
        update_post_meta($pl_id, '_spl_status', 'pending');
        
        // Deep array sanitization for items input
        $items = isset($_POST['items']) ? (array) $_POST['items'] : array();
        $desc_array = isset($items['desc']) ? (array) $items['desc'] : array();
        
        foreach ($desc_array as $key => $raw_desc) {
            $desc_clean = sanitize_textarea_field($raw_desc);
            
            if (empty($desc_clean)) continue;
            
            // Strictly cast numerical inputs to float value to prevent SQLi
            $qty = isset($items['qty'][$key]) ? floatval($items['qty'][$key]) : 0.0;
            $nw = isset($items['nw'][$key]) ? floatval($items['nw'][$key]) : 0.0;
            $gw = isset($items['gw'][$key]) ? floatval($items['gw'][$key]) : 0.0;
            $cbm = isset($items['cbm'][$key]) ? floatval($items['cbm'][$key]) : 0.0;

            $wpdb->insert(
                $wpdb->prefix . 'supplier_pl_items',
                array(
                    'pl_id' => $pl_id,
                    'item_desc' => $desc_clean,
                    'quantity' => $qty,
                    'nw' => $nw,
                    'gw' => $gw,
                    'cbm' => $cbm
                )
            );
        }
        
        echo '<div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 4px; margin-bottom: 20px;">Packing list submitted successfully and is pending approval.</div>';
    }
}

// Fetch user's packing lists
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$args = array(
    'post_type' => 'packing_list',
    'post_status' => 'publish',
    'posts_per_page' => 15,
    'paged' => $paged,
    'author' => $current_user->ID,
    'meta_query' => array(
        array(
            'key' => '_spl_supplier_id',
            'value' => $current_user->ID,
            'compare' => '='
        )
    )
);

$query = new WP_Query($args);
?>

<div class="spl-dashboard-container">
    <div class="spl-dashboard-header">
        <h2>My Packing Lists</h2>
        <button id="spl-show-form-btn" class="spl-btn">+ Create New Packing List</button>
    </div>

    <!-- New PL Form (Hidden by default) -->
    <div id="spl-new-form-wrapper" class="spl-form-container spl-hidden">
        <h3>Create Packing List</h3>
        <form id="spl-packing-list-form" method="POST" action="">
            <?php wp_nonce_field('spl_create_pl', 'spl_nonce'); ?>
            <input type="hidden" name="spl_submit_pl" value="1">
            
            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <div class="spl-form-group" style="flex: 1;">
                    <label>Invoice Number *</label>
                    <input type="text" name="invoice_no" class="spl-form-control" required>
                </div>
                <div class="spl-form-group" style="flex: 1;">
                    <label>Date *</label>
                    <input type="date" name="pl_date" class="spl-form-control" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                </div>
                <div class="spl-form-group" style="flex: 1;">
                    <label>Incoterm</label>
                    <input type="text" name="incoterm" class="spl-form-control" placeholder="e.g. FOB, CIF">
                </div>
            </div>

            <div class="spl-items-wrapper">
                <table class="spl-table">
                    <thead>
                        <tr>
                            <th>Description *</th>
                            <th>Quantity *</th>
                            <th>N.W (kg) *</th>
                            <th>G.W (kg) *</th>
                            <th>CBM *</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="spl-items-body">
                        <tr class="spl-item-row">
                            <td class="item-desc"><input type="text" name="items[desc][]" required placeholder="Item description"></td>
                            <td><input type="number" step="0.01" name="items[qty][]" class="calc-input item-qty" required></td>
                            <td><input type="number" step="0.01" name="items[nw][]" class="calc-input item-nw" required></td>
                            <td><input type="number" step="0.01" name="items[gw][]" class="calc-input item-gw" required></td>
                            <td><input type="number" step="0.0001" name="items[cbm][]" class="calc-input item-cbm" required></td>
                            <td><button type="button" class="spl-btn spl-btn-danger spl-remove-item">X</button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" id="spl-add-item-btn" class="spl-btn">Add Row</button>
            </div>

            <div class="spl-totals-bar">
                <span>Totals:</span>
                <span>Qty: <span id="spl-total-qty">0.00</span></span>
                <span>N.W: <span id="spl-total-nw">0.00</span> kg</span>
                <span>G.W: <span id="spl-total-gw">0.00</span> kg</span>
                <span>CBM: <span id="spl-total-cbm">0.0000</span></span>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="spl-cancel-btn" class="spl-btn" style="background-color: #666;">Cancel</button>
                <button type="submit" class="spl-btn" style="background-color: #00a32a;">Submit Packing List</button>
            </div>
        </form>
    </div>

    <!-- PL Table -->
    <?php if ($query->have_posts()) : ?>
        <table class="spl-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Invoice No.</th>
                    <th>Status</th>
                    <th>Items</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $pl_id = get_the_ID();
                    $status = get_post_meta($pl_id, '_spl_status', true) ?: 'pending';
                    $invoice = get_post_meta($pl_id, '_spl_invoice_no', true);
                    $date = get_post_meta($pl_id, '_spl_date', true);
                    $item_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}supplier_pl_items WHERE pl_id = %d", $pl_id));
                ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($pl_id); ?></strong></td>
                        <td><?php echo esc_html($date); ?></td>
                        <td><?php echo esc_html($invoice ?: '-'); ?></td>
                        <td><span class="spl-status spl-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status); ?></span></td>
                        <td><?php echo intval($item_count); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="spl-pagination">
            <?php
            echo paginate_links(array(
                'total' => $query->max_num_pages,
                'current' => $paged,
                'format' => '?paged=%#%',
            ));
            ?>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <div style="background: #f9f9f9; padding: 30px; text-align: center; border-radius: 4px;">
            <p>You haven't submitted any packing lists yet.</p>
        </div>
    <?php endif; ?>
</div>
