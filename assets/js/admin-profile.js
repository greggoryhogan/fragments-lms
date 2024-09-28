(function($) {
    
    $(document).on('click','.profile-reset-user-progress',function(e) {
        e.preventDefault();
        var button = $(this);
        var r = confirm("Are you sure you want to reset course progress?");
        if (r == true) {
            var text = button.text();
            button.text('Resetting...');
            var user_id = $(this).attr('data-user');
            var course_id = $(this).attr('data-course');
            var version = $(this).attr('data-version');
            $.ajax({
                url: flms_admin_profile.ajax_url,
                type: 'post',
                data: {
                    action: 'reset_user_course_progress',
                    course_id : course_id,
                    version : version,
                    user_id : user_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        //var url = window.location+'#user-active-courses';
                        //$(location).attr('href',url); 
                        window.location.href = window.location+'#user-active-courses';
                        location.reload();
                    } else {
                        button.text(text);
                        alert(data.response);
                    }
                }
            });
        }
    });

    $(document).on('click','.profile-unenroll-user',function(e) {
        e.preventDefault();
        var button = $(this);
        var r = confirm("Are you sure you want to unenroll?");
        if (r == true) {
            var text = button.text();
            button.text('Unenrolling...');
            var user_id = $(this).attr('data-user');
            var course_id = $(this).attr('data-course');
            var version = $(this).attr('data-version');
            $.ajax({
                url: flms_admin_profile.ajax_url,
                type: 'post',
                data: {
                    action: 'unenroll_user_in_course',
                    course_id : course_id,
                    version : version,
                    user_id : user_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        //var url = window.location+'#user-active-courses';
                        //$(location).attr('href',url); 
                        window.location.href = window.location+'#user-active-courses';
                        location.reload();
                    } else {
                        button.text(text);
                        alert(data.response);
                    }
                }
            });
        }
    });

    $(document).on('click','.profile-reset-completed-course',function(e) {
        e.preventDefault();
        var button = $(this);
        var r = confirm("Are you sure you want to reset their progress?");
        if (r == true) {
            var text = button.text();
            button.text('Resetting...');
            var user_id = $(this).attr('data-user');
            var course_id = $(this).attr('data-course');
            var version = $(this).attr('data-version');
            $.ajax({
                url: flms_admin_profile.ajax_url,
                type: 'post',
                data: {
                    action: 'reset_completed_course',
                    course_id : course_id,
                    version : version,
                    user_id : user_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        //var url = window.location+'#user-active-courses';
                        //$(location).attr('href',url); 
                        window.location.href = window.location+'#user-active-courses';
                        location.reload();
                    } else {
                        button.text(text);
                        alert(data.response);
                    }
                }
            });
        }
    });

    $(document).on('click','.profile-complete-course',function(e) {
        e.preventDefault();
        var button = $(this);
        var r = confirm("Are you sure you want to complete the course for this user?");
        if (r == true) {
            var text = button.text();
            button.text('Completing...');
            var user_id = $(this).attr('data-user');
            var course_id = $(this).attr('data-course');
            var version = $(this).attr('data-version');
            $.ajax({
                url: flms_admin_profile.ajax_url,
                type: 'post',
                data: {
                    action: 'complete_course',
                    course_id : course_id,
                    version : version,
                    user_id : user_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        //var url = window.location+'#user-active-courses';
                        //$(location).attr('href',url); 
                        window.location.href = window.location+'#user-active-courses';
                        location.reload();
                    } else {
                        button.text(text);
                        alert(data.response);
                    }
                }
            });
        }
    });

    $(document).on('focus, keyup','#user-profile-course-search', function() {
        var $this = $(this);
        if ($(this).hasClass('ui-autocomplete-input')) {
            $(this).autocomplete('destroy')
        }
        $(this).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: flms_admin_profile.ajax_url,
                    dataType: 'json',
                    data: {
                        action : 'search_flms_courses',
                        term : request.term,
                        page: 1, // Initial page
                    },
                    success: function(data) {
                        response(data.results);
                        // Implement server-side pagination based on the total number of pages
                        if (data.total_pages > 1) {
                            input.autocomplete("option", "appendTo", input);
                            input.autocomplete("option", "position", { my: "left top", at: "left bottom" });
                        }
                    }
                });
            },
            minLength: 0,
            select: function(event, ui) {
                console.log(ui.item);
                $('#user-profile-course-search').val(ui.item.label).attr('data-course',ui.item.course_id).attr('data-version',ui.item.version);
                return false;
            }
        });
        $(this).autocomplete('search', $(this).val());
    });

    $(document).on('click','#profile-enroll-user',function(e) {
        e.preventDefault();
        var button = $(this);
        var input = $('#user-profile-course-search');
        var course_id = input.attr('data-course');
        var version = input.attr('data-version');
        if(course_id < 0 || version < 0) {
            alert('Please select a course');
            return false;
        }
        var r = confirm("Are you sure you want to enroll the user?");
        if (r == true) {
            var text = button.text();
            button.text('Enrolling...');
            var user_id = $(this).attr('data-user');
            $.ajax({
                url: flms_admin_profile.ajax_url,
                type: 'post',
                data: {
                    action: 'enroll_user_in_course',
                    course_id : course_id,
                    version : version,
                    user_id : user_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        //var url = window.location+'#user-active-courses';
                        //$(location).attr('href',url); 
                        window.location.href = window.location+'#user-active-courses';
                        location.reload();
                    } else {
                        button.text(text);
                        alert(data.response);
                    }
                }
            });
        }
    });

})( jQuery );