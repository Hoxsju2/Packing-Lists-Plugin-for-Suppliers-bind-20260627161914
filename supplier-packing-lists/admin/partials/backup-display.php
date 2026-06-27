<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Backup & Import Data</h1>
    <p>Use this tool to export or import your packing lists groups, configurations, and settings safely.</p>
    
    <div style="display: flex; gap: 30px; margin-top: 30px;">
        <!-- Export Section -->
        <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h2><span class="dashicons dashicons-download" style="margin-top: 2px;"></span> Export System Data</h2>
            <p>Download all group structures, settings configurations, and assignments as a JSON backup file.</p>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('spl_export_data', 'spl_export_nonce'); ?>
                <input type="hidden" name="action" value="spl_export_system">
                <button type="button" class="button button-primary" onclick="alert('Export functionality relies on the WP admin-post hooks. Please link this to the server exporter.');">Download Backup (.json)</button>
            </form>
        </div>

        <!-- Import Section -->
        <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h2><span class="dashicons dashicons-upload" style="margin-top: 2px;"></span> Import System Data</h2>
            <p>Upload a previously exported JSON backup file to restore group structures and settings configurations.</p>
            <form method="post" enctype="multipart/form-data" action="">
                <?php wp_nonce_field('spl_import_data', 'spl_import_nonce'); ?>
                <input type="file" name="spl_import_file" accept=".json" style="margin-bottom: 15px;">
                <br>
                <button type="button" class="button" onclick="alert('Import processing will be executed here.');">Run Import</button>
            </form>
        </div>
    </div>
</div>
