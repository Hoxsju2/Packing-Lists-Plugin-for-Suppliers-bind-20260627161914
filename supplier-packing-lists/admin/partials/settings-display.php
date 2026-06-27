<?php
if (!defined('ABSPATH')) {
    exit;
}

$weight_unit = get_option('spl_weight_unit', 'kg');
$require_incoterm = get_option('spl_require_incoterm', 'no');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Packing List Settings</h1>
    
    <?php settings_errors('spl_messages'); ?>

    <!-- Shortcode Display Section -->
    <div class="spl-shortcode-section" style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #0073aa; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2 style="margin-top: 0;">📋 Plugin Shortcode</h2>
        <p>Use the following shortcode to display the <strong>Supplier Portal</strong> on any WordPress page or post. Suppliers must be logged in to view their dashboard.</p>

        <div class="spl-shortcode-box spl-main-shortcode" style="display: inline-flex; align-items: center; gap: 15px; background: #f0f8ff; border: 1px solid #c3e0f9; padding: 10px 15px; border-radius: 4px;">
            <code id="spl-portal-shortcode" style="font-size: 16px; background: none; font-weight: bold; color: #0073aa;">[supplier_packing_lists]</code>
            <button type="button" class="button button-primary" onclick="copySplShortcode('spl-portal-shortcode', this)" style="background: #0073aa; border-color: #0073aa; color: white;">Copy</button>
        </div>
        <p class="description" style="margin-top: 10px;"><em>Note: Currently, this is the only frontend shortcode provided by the plugin.</em></p>
    </div>

    <!-- Settings Form -->
    <form method="post" action="" style="margin-top: 20px;">
        <?php wp_nonce_field('spl_save_settings', 'spl_settings_nonce'); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="spl_weight_unit">Default Weight Unit</label></th>
                    <td>
                        <select name="spl_weight_unit" id="spl_weight_unit">
                            <option value="kg" <?php selected($weight_unit, 'kg'); ?>>Kilograms (kg)</option>
                            <option value="lbs" <?php selected($weight_unit, 'lbs'); ?>>Pounds (lbs)</option>
                        </select>
                        <p class="description">Select the default weight metric displayed on exports and forms.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Requirements</th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span>Incoterm Requirement</span></legend>
                            <label for="spl_require_incoterm">
                                <input name="spl_require_incoterm" type="checkbox" id="spl_require_incoterm" value="1" <?php checked($require_incoterm, 'yes'); ?>>
                                Require "Incoterm" field upon creation
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="spl_submit_settings" id="submit" class="button button-primary" value="Save Settings">
        </p>
    </form>
</div>

<script>
function copySplShortcode(elementId, button) {
    var textToCopy = document.getElementById(elementId).innerText;
    
    // Modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textToCopy).then(() => {
            showCopied(button);
        });
    } else {
        // Fallback for older browsers or non-HTTPS local environments
        var textArea = document.createElement("textarea");
        textArea.value = textToCopy;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showCopied(button);
        } catch (err) {
            console.error('Copy fallback failed', err);
        }
        textArea.remove();
    }
    
    function showCopied(btn) {
        var originalText = btn.innerText;
        btn.innerText = 'Copied!';
        setTimeout(function() {
            btn.innerText = originalText;
        }, 2000);
    }
}
</script>
