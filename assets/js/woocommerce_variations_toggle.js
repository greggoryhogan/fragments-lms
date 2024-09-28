(function($) {

    var p = 'p.price'
        q = $(p).html();

    $('form.cart').on('show_variation', function( event, data ) {
        if ( data.price_html ) {
            $(p).html(data.price_html);
        }
    }).on('hide_variation', function( event ) {
        $(p).html(q);
    });

    /*$( 'input.variation_id' ).change( function(){
        if( '' != $(this).val() ) {
           var var_id = $(this).val();
           alert('You just selected variation #' + var_id);
        }
     });*/

})( jQuery );