(function($) {
    
    $( '.flms-woo-select' ).select2();

    $('#product-type').on('change',function() {
        $( '.flms-woo-select' ).selectWoo();
    });

    $( document ).on('woocommerce_variations_loaded',function() {
        $( '.flms-woo-select' ).each(function() {
            $(this).selectWoo();
        })
    });

})( jQuery );