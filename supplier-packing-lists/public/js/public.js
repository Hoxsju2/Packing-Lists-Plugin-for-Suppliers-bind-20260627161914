jQuery(document).ready(function($) {
    
    // Toggle New PL Form
    $('#spl-show-form-btn').on('click', function(e) {
        e.preventDefault();
        $('#spl-new-form-wrapper').slideToggle();
    });

    $('#spl-cancel-btn').on('click', function(e) {
        e.preventDefault();
        $('#spl-new-form-wrapper').slideUp();
        $('#spl-packing-list-form')[0].reset();
        resetItems();
    });

    // Add new item row
    $('#spl-add-item-btn').on('click', function(e) {
        e.preventDefault();
        var newRow = $('.spl-item-row:first').clone();
        newRow.find('input').val('');
        $('#spl-items-body').append(newRow);
        calculateTotals();
    });

    // Remove item row
    $(document).on('click', '.spl-remove-item', function(e) {
        e.preventDefault();
        if ($('.spl-item-row').length > 1) {
            $(this).closest('tr').remove();
            calculateTotals();
        } else {
            alert('You must have at least one item.');
        }
    });

    // Calculate totals on input change
    $(document).on('input', '.calc-input', function() {
        calculateTotals();
    });

    function calculateTotals() {
        var totalQty = 0;
        var totalNW = 0;
        var totalGW = 0;
        var totalCBM = 0;

        $('.spl-item-row').each(function() {
            var qty = parseFloat($(this).find('.item-qty').val()) || 0;
            var nw = parseFloat($(this).find('.item-nw').val()) || 0;
            var gw = parseFloat($(this).find('.item-gw').val()) || 0;
            var cbm = parseFloat($(this).find('.item-cbm').val()) || 0;

            totalQty += qty;
            totalNW += nw;
            totalGW += gw;
            totalCBM += cbm;
        });

        $('#spl-total-qty').text(totalQty.toFixed(2));
        $('#spl-total-nw').text(totalNW.toFixed(2));
        $('#spl-total-gw').text(totalGW.toFixed(2));
        $('#spl-total-cbm').text(totalCBM.toFixed(4));
    }

    function resetItems() {
        $('.spl-item-row').not(':first').remove();
        $('.spl-item-row:first input').val('');
        calculateTotals();
    }

    // Form Submission
    $('#spl-packing-list-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalBtnText = $submitBtn.text();
        
        $submitBtn.prop('disabled', true).text('Saving...');
        
        // Since we don't have a direct AJAX endpoint in this snippet, 
        // normally you would serialize and send via WP AJAX.
        // As a fallback for traditional POST without ajaxurl on public:
        $form[0].submit();
    });
});
