(function($) {
    $( '#flms-course-certificates' ).select2();

    $('.flms-tabs .tab, .tab-selector li').on('click', function() {
        $("s#flms-course-certificates").select2("destroy").select2();
    });

    $('select').on('select2:opening select2:closing', function( event ) {
        var $searchfield = $( '#'+event.target.id ).parent().find('.select2-search__field');
        $searchfield.prop('disabled', true);
    });
    

    $("#select2-flms-course-certificates-results").hover(function () {
        // Un-highlight all options
        $('.select2-results__option').removeClass('select2-results__option--highlighted');
    }); 
})( jQuery );