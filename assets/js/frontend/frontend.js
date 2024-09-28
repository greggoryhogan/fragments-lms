(function($) {

    let searchParams = new URLSearchParams(window.location.search);

    //set redirects
    //flms-login-redirect
    if(searchParams.has('flms-login-redirect')) {
        //console.log(searchParams.get('flms-login-redirect'));
        Cookies.set('flms_login_redirect', searchParams.get('flms-login-redirect'));
    }

    //get redirect
    var needs_redirect = Cookies.get('flms_login_redirect');
    if(needs_redirect) {
        console.log(needs_redirect);
        if(flms_frontend.current_user_id > 0) {
            Cookies.remove('flms_login_redirect');
            $('body').append('<div id="flms-redirect-notice"><div class="notice" data-notice="Redirecting..."></div></div>');
            window.location = needs_redirect;
        }
    }

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

    $('.lesson-toggle').on('click', function(){
        $(this).parent().parent().toggleClass('open'); // optionally add an open state to the toggle button
        $(this).parent().parent().find('.to-toggle').each(heightopenclose);
    });

    $('.flms-accordion-heading').on('click', function(){
        $(this).closest('.flms-accordion-section').toggleClass('open'); // optionally add an open state to the toggle button
        $(this).closest('.flms-accordion-section').find('.to-toggle').each(heightopenclose);
    });

    //Enroll in course
    $(document).on('click','#flms-enroll',function() {
        $('#enroll-response').text('Enrolling, please wait...');
        $.ajax({
            url: flms_frontend.ajax_url,
            type: 'post',
            data: {
                action : 'enroll_user_in_course',
                user_id : flms_frontend.current_user_id,
                course_id : flms_frontend.course_id,
                version : flms_frontend.version_index,
            },
            success: function(data) {
                $('#enroll-response').text(data.response);
                //reload
                if(data.success == 1) {
                    window.location = window.location;
                }
            }
        });
    });

    //no access tooltip
    $(document).on('mouseenter','.flms-no-access',function() {
        $('.flms-tooltip-content').remove();
        var tooltip = 'You do not have access to this content';
        $('body').append('<div class="flms-tooltip-content flms-background flms-primary-bg">'+tooltip+'</div>');
        var offset = $(this).offset();
        var top = offset.top - $('.flms-tooltip-content').outerHeight() - 10;
        $('.flms-tooltip-content').css({'top':top, 'left': offset.left});
    });
    $(document).on('mouseleave','.flms-no-access',function() {
        $('.flms-tooltip-content').remove();
    });

    //disabled tooltip
    $(document).on('pointerenter','[data-flms-tooltip]',function() {
        var tipClasses = 'flms-tooltip-content flms-background flms-primary-bg';
        if($(this).hasClass('tooltip-right')) {
            tipClasses += ' tooltip-right';
        }
        $('.flms-tooltip-content').remove();
        var tooltip = $(this).attr('data-flms-tooltip');
        $('body').append('<div class="'+tipClasses+'">'+tooltip+'</div>');
        var offset = $(this).offset();
        if($(this).hasClass('tooltip-right')) {
            var top = offset.top - 7;
            var left = offset.left + $(this).innerWidth() + 18;
        } else {
            var top = offset.top - $('.flms-tooltip-content').outerHeight() - 10;
            var left = offset.left;
        }
        $('.flms-tooltip-content').css({'top':top, 'left': left});
    });
    $(document).on('pointerleave','[data-flms-tooltip]',function() {
        $('.flms-tooltip-content').remove();
    });
    

    $(document).on('click','.flms-share-link', function() {
        var $this = $(this);
        navigator.clipboard.writeText($this.text().trim());
        $('.flms-tooltip-content').text('Copied!');
    });

    $(document).on('click','.flms-button-has-link', function(e) {
        e.preventDefault();
        var link = $(this).attr('data-flms-button-link');
        var name = $(this).attr('data-name');
        window.open(link, name); 
    });
    
    //Enroll in course
    $(document).on('click','#flms-complete-step',function(e) {
        e.preventDefault();
        if($(this).hasClass('is-loading') || $(this).is(":disabled")) {
            return false;
        }
        $(this).addClass('is-loading');
        var redirect = $(this).attr('data-redirect');
        //console.log(flms_frontend);
        $.ajax({
            url: flms_frontend.ajax_url,
            type: 'post',
            data: {
                action : 'complete_step',
                current_post : flms_frontend.current_post,
                course_id : flms_frontend.course_id,
                version : flms_frontend.version_index,
                redirect : redirect
            },
            success: function(data) {
                $(this).removeClass('is-loading');
                //reload
                if(data.success == 1) {
                    //console.log(data);
                    //alert('Done');
                    window.location = data.redirect;
                } else {
                    alert('Something went wrong');
                }
            }
        });
    });

    $('form').on('click', '.flms-conditional-checkbox', function() {
        if($(this).is(":checked")) {
            $(this).parent().find('.needs-checkbox-checked').prop("disabled", false).removeClass('flms-disabled');
        } else {
            $(this).parent().find('.needs-checkbox-checked').prop("disabled", true).addClass('flms-disabled');
        }
    });
    
    $('.select2').select2({
        width: 'style',
    });

    /*$('.select2tags').select2({
        width: 'style',
        tags: true,
        tokenSeparators: [',', ' '],
        createTag: function (params) {
            // Don't offset to create a tag if there is no @ symbol
            if (params.term.indexOf('@') === -1) {
              // Return null to disable tag creation
              //return null;
            }
        
            return {
              id: params.term,
              text: params.term
            }
          }
    });*/

    $('.select2-no-search').select2({
        width: 'style',
        minimumResultsForSearch: -1
    });

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
    
    $(document).on('click', '.flms-alert .dismissable', function(){
        $(this).parent().remove();
        window.history.replaceState(null, '', window.location.origin + window.location.pathname);
    });

})( jQuery );