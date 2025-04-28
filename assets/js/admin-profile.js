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

    $(document).on('click','.profile-delete-entry',function(e) {
        e.preventDefault();
        var button = $(this);
        var r = confirm("Are you sure you want to delete this entry? This action cannot be undone");
        if (r == true) {
            var text = button.text();
            button.text('Deleting...');
            var entry_id = $(this).attr('data-entry-id');
            $.ajax({
                url: flms_admin_profile.ajax_url,
                type: 'post',
                data: {
                    action: 'delete_course_history_entry',
                    entry_id : entry_id,
                },
                success: function(data) {
                    window.location.href = window.location+'#user-active-courses';
                    location.reload();
                }
            });
        }
    });

    $(document).on('click','.profile-change-completion-date',function(e) {
        e.preventDefault();
        var button = $(this);
        var entry_id = button.attr('data-entry-id');
        $('#new-completion-date-value').attr('data-entry-id', entry_id);
        var entry_date = button.attr('data-entry-date');
        $('#new-completion-date-value').val(entry_date);
        $('#completion-date-update').addClass('flms-is-active');
        var course_name = $('.flms-entry-id-'+entry_id+'-course-name').text();
        console.log(course_name);
        $('#completion-date-update .course-info').html(course_name);
    });

    $(document).on('click','#cancel-completion-date-change',function(e) {
        e.preventDefault();
        $('#completion-date-update').removeClass('flms-is-active');
        
    });
    $(document).on('click','#save-completion-date',function(e) {
        e.preventDefault();
        var entry_id = $('#new-completion-date-value').attr('data-entry-id');
        var date = $('#new-completion-date-value').val();
        $('#completion-date-update').removeClass('flms-is-active');
        $.ajax({
            url: flms_admin_profile.ajax_url,
            type: 'post',
            data: {
                action: 'update_completion_date',
                entry_id : entry_id,
                date : date,
            },
            success: function(data) {
                if(data.success == 1) {
                    //var url = window.location+'#user-active-courses';
                    //$(location).attr('href',url); 
                    window.location.href = window.location+'#user-active-courses';
                    location.reload();
                } else {
                    alert(data.response);
                }
            }
        });
        
    });

    

    var allResults = []; // Store results across pages

$(document).on('focus keyup', '#user-profile-course-search', function() {
    var input = $(this);
    

    if (input.hasClass('ui-autocomplete-input')) {
        input.autocomplete('destroy');
    }

    var currentPage = 1;
    
    input.autocomplete({
        source: function(request, response) {
            if(request.term != '') {
                $.ajax({
                    url: flms_admin_profile.ajax_url,
                    dataType: 'json',
                    data: {
                        action : 'search_flms_courses',
                        term : request.term,
                        page: currentPage,
                    },
                    success: function(data) {
                        
                        if (currentPage === 1) {
                            allResults = data.results;
                        } else {
                            allResults = allResults.concat(data.results);
                        }
                        
                        response(allResults);

                        // Automatically request the next page if more pages exist
                        if (data.total_pages > currentPage) {
                            currentPage++;
                            input.autocomplete("search", request.term);
                        }
                    }
                });
            }
        },
        minLength: 0,
        select: function(event, ui) {
            console.log(ui.item);
            $('#user-profile-course-search')
                .val(ui.item.label)
                .attr('data-course', ui.item.course_id)
                .attr('data-version', ui.item.version);
            return false;
        }
    });

    setTimeout(function () {
        if (input.val() !== '' && input.val() !== input.attr('searchString')) {
            input.attr('searchString', input.val());
            input.autocomplete('search', input.val());
            
        }
    }, 2500);
    
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
        var accepts_reporting = 0;
        if($('#flms-enrollment-accept-reporting-fee-override').length) {
            if($('#flms-enrollment-accept-reporting-fee-override').is(":checked")) {
                accepts_reporting = 1;
            }
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
                    accepts_reporting : accepts_reporting,
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