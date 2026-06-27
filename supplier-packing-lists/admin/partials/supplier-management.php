<?php
if (!defined('ABSPATH')) {
    exit;
}

// Fetch all users with 'supplier' role
$suppliers = get_users(array(
    'role' => 'supplier',
    'orderby' => 'display_name',
    'order' => 'ASC'
));
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Supplier Management</h1>
    <p>Manage your registered suppliers, assign company names, and supplier codes.</p>

    <?php settings_errors('spl_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('spl_update_suppliers', 'spl_suppliers_nonce'); ?>
        
        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th scope="col" id="username" class="manage-column column-username">Username / Email</th>
                    <th scope="col" id="company_name" class="manage-column column-company_name">Company Name</th>
                    <th scope="col" id="supplier_code" class="manage-column column-supplier_code">Supplier Code</th>
                    <th scope="col" id="pl_count" class="manage-column column-pl_count">Total Packing Lists</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">No suppliers found. Ensure users are assigned the "Supplier" role.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $supplier): 
                        $company_name = get_user_meta($supplier->ID, 'company_name', true);
                        $supplier_code = get_user_meta($supplier->ID, 'supplier_code', true);
                        
                        $args = array(
                            'post_type' => 'packing_list',
                            'meta_key' => '_spl_supplier_id',
                            'meta_value' => $supplier->ID,
                            'fields' => 'ids'
                        );
                        $pl_count = count(get_posts($args));
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($supplier->display_name); ?></strong><br>
                                <a href="mailto:<?php echo esc_attr($supplier->user_email); ?>"><?php echo esc_html($supplier->user_email); ?></a>
                            </td>
                            <td>
                                <input type="text" name="suppliers[<?php echo esc_attr($supplier->ID); ?>][company_name]" 
                                       value="<?php echo esc_attr($company_name); ?>" class="regular-text" placeholder="Company Name">
                            </td>
                            <td>
                                <input type="text" name="suppliers[<?php echo esc_attr($supplier->ID); ?>][supplier_code]" 
                                       value="<?php echo esc_attr($supplier_code); ?>" class="regular-text" placeholder="e.g. SUP-001">
                            </td>
                            <td>
                                <span style="display: inline-block; padding: 4px 10px; background: #f0f0f1; border-radius: 12px; font-weight: bold;">
                                    <?php echo intval($pl_count); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($suppliers)): ?>
            <p class="submit">
                <input type="submit" name="spl_submit_suppliers" id="submit" class="button button-primary" value="Save Supplier Details">
            </p>
        <?php endif; ?>
    </form>
</div>
