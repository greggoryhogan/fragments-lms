(function($) {
    
    $(document.body).on('updated_wc_div',function() {
        $( '#flms-cart-credits-summary' ).block();
        $.ajax({
            url: flms_woocommerce.ajax_url,
            type: 'get',
            data: {
                action: 'update_flms_woocommerce_checkout',
            },
            success: function(data) {
               $('#flms-cart-credits-summary').html(data.cart_credits);
               $( '#flms-cart-credits-summary' ).unblock(); //unneccessary since content changed
            }
        });
    });

})( jQuery );