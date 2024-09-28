(function($) {
    $(window).on('load',function() {
        var bg = $('.flms-color-picker.background-color').val();
        $('.mce-edit-area iframe').contents().find('body').css({'background-color':bg});
    });
    
    $('.flms-color-picker').wpColorPicker();

    jQuery('.flms-color-picker.background-color').wpColorPicker({
        /**
         * @param {Event} event - standard jQuery event, produced by whichever
         * control was changed.
         * @param {Object} ui - standard jQuery UI object, with a color member
         * containing a Color.js object.
         */
        change: function (event, ui) {
            var element = event.target;
            var color = ui.color.toString();
            $('.mce-edit-area iframe').contents().find('body').css({'background-color':color});
        },
    
    });

    $('#flms-certificate-preview-course').on('change',function() {
        var course_identifier = $(this).val();
        $.ajax({
            url: flms_admin_certificates.ajax_url,
            type: 'get',
            data: {
                action: 'get_course_completed_users',
                course_identifier : course_identifier,
            },
            success: function(data) {
                if(data.success == 1) {
                    $('#certificate-users-response').html(data.users);
                    $('#preview-certificate').attr('data-course',data.course);
                    $('#preview-certificate').attr('data-course-version',data.course_version);
                    $('#preview-certificate').show();
                } else {
                    $('#certificate-users-response').html(data.message);
                    $('#preview-certificate').attr('data-course',data.course);
                    $('#preview-certificate').attr('data-course-version',data.course_version);
                    $('#preview-certificate').hide();
                }
            }
        });
    });

    $(document).on('click', '#preview-certificate', function(e) {
        e.preventDefault();
        var course = $(this).attr('data-course');
        var course_version = $(this).attr('data-course-version');
        var user = $('#course-completed-users').val();
        var link = flms_admin_certificates.certificate_link + '/' + course + '/' + course_version + '/' + user;
        window.open(link, 'Certificate Preview'); 

    });

})( jQuery );