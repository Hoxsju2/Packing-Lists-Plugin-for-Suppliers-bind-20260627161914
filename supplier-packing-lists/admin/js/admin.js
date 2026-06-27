$('#cb-select-all-1').prop('indeterminate', true);
        }
    });
    
    function updateSelectionSummary() {
        var checkedBoxes = $('.pl-checkbox:checked');
        
        if (checkedBoxes.length === 0) {
            $('#selection-summary').hide();
            return;
        }
        
        var totalQuantity = 0;
        var totalNw = 0;
        var totalGw = 0;
        var totalCbm = 0;
        
        checkedBoxes.each(function() {
            totalQuantity += parseFloat($(this).data('quantity')) || 0;
            totalNw += parseFloat($(this).data('nw')) || 0;
            totalGw += parseFloat($(this).data('gw')) || 0;
            totalCbm += parseFloat($(this).data('cbm')) || 0;
        });
        
        $('#selection-totals').html(
            '<strong>' + checkedBoxes.length + '</strong> items selected<br>' +
            'Qty: <strong>' + totalQuantity.toFixed(2) + '</strong> | ' +
            'N.W: <strong>' + totalNw.toFixed(2) + '</strong> kg | ' +
            'G.W: <strong>' + totalGw.toFixed(2) + '</strong> kg | ' +
            'CBM: <strong>' + totalCbm.toFixed(4) + '</strong>'
        );
        
        $('#selection-summary').show();
    }
});
