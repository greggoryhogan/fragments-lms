(function($) {

    //Tooltip
    $(document).on('click','.flms-tooltip',function() {
        $(this).toggleClass('is-active');
        if($(this).hasClass('is-active')) {
            $('.flms-tooltip-content').remove();
            var tooltip = $(this).attr('data-tooltip');
            var offset = $(this).offset();
            $('body').append('<div class="flms-tooltip-content">'+tooltip+'</div>');
            $('.flms-tooltip-content').css({'top':offset.top, 'left': offset.left});
        } else {
            $('.flms-tooltip-content').remove();
        }
    });
    
    //Clear tooltip
    $(document).on('mouseup', function(e)  {
        var tooltip = $('.flms-tooltip');

        // if the target of the click isn't the container nor a descendant of the container
        if (!tooltip.is(e.target) && tooltip.has(e.target).length === 0) {
            $('.flms-tooltip-content').remove();
            $('.flms-tooltip').removeClass('is-active');
        }
    });

    /**
     * Toggle visibility of toggleable content
     */
    $(document).on('click','[data-toggle-trigger]',function(e) {
        e.preventDefault();
        var toggle = $(this).attr('data-toggle-trigger');
        $(toggle).toggleClass('is-active');
        if (!$(this).attr('data-toggle-text')) {
            $(this).attr('data-toggle-text',$(this).text());
            $(this).text('Cancel');
        } else {
            var text = $(this).text();
            $(this).text($(this).attr('data-toggle-text'));
            $(this).attr('data-toggle-text',text);
            //$(this).removeAttr('data-toggle-text');
        }
        if($(toggle).hasClass('is-active')) {
            $(toggle +' input').not('.autocomplete').focus();
        }
        $(this).toggleClass('is-active');
    });

    

    /**
     * Toggle visibility of toggleable content
     */
    $(document).on('change','[data-checkbox-toggle]',function(e) {
        var toggle = $(this).attr('data-checkbox-toggle');
        $(toggle).toggleClass('is-active');
        if($(toggle).hasClass('is-active')) {
            $(toggle +' input').not('.autocomplete').focus();
        }
    });

     /**
     * Toggle visibility of toggleable content
     */
     $(document).on('change','.select-toggle',function(e) {
        var toggle = $(this).find(":selected").attr('data-select-toggle');
        $('.select-toggle-div').removeClass('is-active');
        $(toggle).addClass('is-active');
    });
    
    //});

    //admin tabs
    $(document).on('click','.flms-tabs .tab', function(e) {
        e.preventDefault();
        var tab = $(this).attr('data-tab');
        $('.flms-tabs .is-active, .flms-tab-section.is-active').removeClass('is-active');
        $(this).addClass('is-active');
        $(tab).addClass('is-active');
        //console.log(tab);
    });

    $(document).on('click', '.flms-conditional-checkbox', function() {
        if($(this).is(":checked")) {
            $(this).parent().find('.needs-checkbox-checked').prop("disabled", false).removeClass('flms-disabled');;
        } else {
            $(this).parent().find('.needs-checkbox-checked').prop("disabled", true).addClass('flms-disabled');;
        }
    });

    // open --------------
    function heightopen(){
        $(this).height($(this).get(0).scrollHeight).addClass('open'); // get height and open
        $(this).one('transitionend', function(){ // after transition complete
            $(this).height(''); // revert to CSS-set height
        });
    }

    // close --------------
    function heightclose(){
        $(this).height($(this).get(0).scrollHeight).height('').removeClass('open'); // get height and close
    }
    
    // open & close based on open state --------------
    function heightopenclose(){
        if($(this).hasClass('open')) {
            $(this).each(heightclose); // close
        }
        else {
            $(this).each(heightopen); // open
        }
    }

    
    $(document).on('click', '.flms-accordion-heading', function(){
        $(this).closest('.flms-accordion-section').toggleClass('open'); // optionally add an open state to the toggle button
        $(this).closest('.flms-accordion-section').find('.to-toggle').each(heightopenclose);
    });

})( jQuery );