<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$pl_id = isset($_GET['pl_id']) ? intval($_GET['pl_id']) : 0;
$post = get_post($pl_id);

if (!$post || $post->post_type !== 'packing_list') {
    wp_die(esc_html__('Invalid packing list.', 'supplier-packing-lists'));
}

$invoice_no = get_post_meta($pl_id, '_spl_invoice_no', true);
$incoterm = get_post_meta($pl_id, '_spl_incoterm', true);
$pl_date = get_post_meta($pl_id, '_spl_date', true);
$status = get_post_meta($pl_id, '_spl_status', true);
$supplier_id = get_post_meta($pl_id, '_spl_supplier_id', true);
$supplier = get_userdata($supplier_id);
$supplier_name = $supplier ? get_user_meta($supplier_id, 'company_name', true) ?: $supplier->display_name : 'Unknown';

// Fetch items
$items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}supplier_pl_items WHERE pl_id = %d ORDER BY id ASC",
    $pl_id
));
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Edit Packing List #<?php echo esc_html($pl_id); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=supplier-packing-lists-admin'); ?>" class="page-title-action">Back to All Packing Lists</a>
    <hr class="wp-header-end">

    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px;">
        <p><strong>Supplier:</strong> <?php echo esc_html($supplier_name); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('spl_edit_pl_' . $pl_id, 'spl_edit_nonce'); ?>
            <input type="hidden" name="pl_id" value="<?php echo esc_attr($pl_id); ?>">

            <table class="form-table">
                <tr>
                    <th><label for="invoice_no">Invoice Number</label></th>
                    <td><input type="text" name="invoice_no" id="invoice_no" value="<?php echo esc_attr($invoice_no); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="pl_date">Date</label></th>
                    <td><input type="date" name="pl_date" id="pl_date" value="<?php echo esc_attr($pl_date); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="incoterm">Incoterm</label></th>
                    <td><input type="text" name="incoterm" id="incoterm" value="<?php echo esc_attr($incoterm); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="status">Status</label></th>
                    <td>
                        <select name="status" id="status">
                            <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                            <option value="approved" <?php selected($status, 'approved'); ?>>Approved</option>
                            <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top: 30px;">Items</h2>
            <table class="wp-list-table widefat fixed striped" id="edit-items-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Description</th>
                        <th>Quantity</th>
                        <th>N.W (kg)</th>
                        <th>G.W (kg)</th>
                        <th>CBM</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><input type="text" name="items[desc][]" value="<?php echo esc_attr($item->item_desc); ?>" style="width: 100%;" required></td>
                            <td><input type="number" step="0.01" name="items[qty][]" value="<?php echo esc_attr($item->quantity); ?>" style="width: 100%;" required></td>
                            <td><input type="number" step="0.01" name="items[nw][]" value="<?php echo esc_attr($item->nw); ?>" style="width: 100%;" required></td>
                            <td><input type="number" step="0.01" name="items[gw][]" value="<?php echo esc_attr($item->gw); ?>" style="width: 100%;" required></td>
                            <td><input type="number" step="0.0001" name="items[cbm][]" value="<?php echo esc_attr($item->cbm); ?>" style="width: 100%;" required></td>
                            <td><button type="button" class="button remove-item-btn" style="color: #d63638; border-color: #d63638;">X</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p>
                <button type="button" class="button" id="add-item-btn">+ Add Item</button>
            </p>

            <p class="submit">
                <input type="submit" name="spl_submit_edit_pl" class="button button-primary" value="Update Packing List">
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#add-item-btn').on('click', function() {
        var row = '<tr>' +
            '<td><input type="text" name="items[desc][]" style="width: 100%;" required></td>' +
            '<td><input type="number" step="0.01" name="items[qty][]" style="width: 100%;" required></td>' +
            '<td><input type="number" step="0.01" name="items[nw][]" style="width: 100%;" required></td>' +
            '<td><input type="number" step="0.01" name="items[gw][]" style="width: 100%;" required></td>' +
            '<td><input type="number" step="0.0001" name="items[cbm][]" style="width: 100%;" required></td>' +
            '<td><button type="button" class="button remove-item-btn" style="color: #d63638; border-color: #d63638;">X</button></td>' +
            '</tr>';
        $('#edit-items-table tbody').append(row);
    });

    $(document).on('click', '.remove-item-btn', function() {
        if ($('#edit-items-table tbody tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            alert('A packing list must have at least one item.');
        }
    });
});
</script>
