(function($) {
    
    var current_name = $('#flms-group-owner').val();
    var current_id = $('#flms-group-owner-id').val();

    $(document).on('focus, keyup','#flms-group-owner', function() {
        var $this = $(this);
        if ($(this).hasClass('ui-autocomplete-input')) {
            $(this).autocomplete('destroy')
        }
        $(this).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: flms_admin_group.ajax_url,
                    dataType: 'json',
                    data: {
                        action : 'search_flms_users',
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
                current_name = ui.item.label;
                current_id = ui.item.value;
                $('#flms-group-owner').val(current_name);
                $('#flms-group-owner-id').val(current_id);
                return false;
            },
            change: function (event, ui) {
                if (ui.item === undefined) {
                    $('#flms-group-owner').val(current_name);
                    $('#flms-group-owner-id').val(current_id);
                }
            }
        });
        $(this).autocomplete('search', $(this).val());
    });

    $(document).on('click', '#check-group-code', function(e) {
        e.preventDefault();
        var group_code = $('#flms-group-code').val();
        var $this = $(this).parent();
        if(!$this.hasClass('is-processing')) {
            $this.addClass('is-processing');
            $('#flms-group-code-validator').removeClass('is-invalid').removeClass('is-valid');
            $.ajax({
                url: flms_admin_group.ajax_url,
                data: {
                    action : 'check_group_code',
                    post_id: flms_admin_group.post_id,
                    group_code: group_code,
                },
                success: function(data) {
                    if(data.valid == 1) {
                        $('#flms-group-code-validator').removeClass('is-invalid').addClass('is-valid');
                    } else {
                        $('#flms-group-code-validator').removeClass('is-valid').addClass('is-invalid');
                    }
                    $this.removeClass('is-processing');
                }
            });
        }
    });

    $(document).on('click', '#generate-new-group-code', function(e) {
        e.preventDefault();
        var $this = $(this).parent();
        if(!$this.hasClass('is-processing')) {
            $this.addClass('is-processing');
            $.ajax({
                url: flms_admin_group.ajax_url,
                data: {
                    action : 'generate_group_code',
                    post_id: flms_admin_group.post_id,
                },
                success: function(data) {
                    $('#flms-group-code').val(data.group_code);
                    $('#flms-group-code-validator').removeClass('is-invalid').addClass('is-valid');
                    $this.removeClass('is-processing');
                }
            });
        }
    });

    $(document).on('click', '.group-enroll-user', function(e) {
        e.preventDefault();
        var $this = $(this);
        var user_email = $this.parent().find('.group-enroll-user-email').val();
        var index = $(this).attr('data-course-index');
        $('.enroll-response-'+index).html('');
        if(user_email == '') {
            $('.enroll-response-'+index).html('<p class="description">Please enter a user email.</p>');
            return false;
        }
        var course_container = $this.closest('.flms-course');
        
        if(!$this.hasClass('is-processing')) {
            course_container.addClass('is-processing');
            $.ajax({
                url: flms_admin_group.ajax_url,
                data: {
                    action : 'user_group_enroll',
                    group_id : flms_admin_group.post_id,
                    index : index,
                    user_email : user_email
                },
                success: function(data) {
                    if(data.success == 1) {
                        $('#group-courses').html('<div class="setting-area-group-courses"><div class="setting-area-fields">'+data.new_html+'</div></div>');
                        $('#group-members').html('<div class="setting-area-group-members"><div class="setting-area-fields">'+data.new_member_html+'</div></div>');
                            
                        //$this.parent().html('<em>Enrolled</em>');
                    } 
                    if(data.errors != '') {
                        $('.enroll-response-'+index).html('<p class="description">'+data.errors+'</p>');
                    }
                    $this.parent().find('.group-enroll-user-email').val('');
                    course_container.removeClass('is-processing');
                }
            });
            return false;
        }
    });

    $(document).on('focus keyup', '#group-course-search', function() {
        var input = $(this);
        
        if (input.hasClass('ui-autocomplete-input')) {
            input.autocomplete('destroy');
        }
    
        var currentPage = 1;
        
        input.autocomplete({
            source: function(request, response) {
                if(request.term != '') {
                    $.ajax({
                        url: flms_admin_group.ajax_url,
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
                $('#group-course-search')
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

    $(document).on('click','#group-enroll-course', function(e) {
        e.preventDefault();
        if(!$('.group-add-course-form').hasClass('processing')) {
            $('.group-add-course-form').addClass('processing');
            var course_id = $('#group-course-search').attr('data-course');
            var seats = $('#group-course-seats').val();
            var course_version = $('#group-course-search').attr('data-version');
            var paid_fees_checked = $('#group-course-paid-fees').is(":checked");
            var paid_fees = 0;
            if(paid_fees_checked) {
                paid_fees = 1;
            }
            if(course_id == -1 || course_version == -1) {
                alert('Please select a course');
                $('.group-add-course-form').removeClass('processing');
                return false;
            }
            $.ajax({
                url: flms_admin_group.ajax_url,
                data: {
                    action : 'add_course_to_group',
                    group_id : flms_admin_group.post_id,
                    course_id : course_id,
                    course_version : course_version,
                    paid_fees : paid_fees,
                    seats : seats
                },
                success: function(data) {
                    if(data.success == 1) {
                        $('#group-courses').html('<div class="setting-area-group-courses"><div class="setting-area-fields">'+data.new_html+'</div></div>');
                    } 
                }
            });
        }
    });

    $(document).on('click','.flms-remove-from-group', function() {
        var $this = $(this);
        var r = confirm("Are you sure you want to remove the user from this group? They will be unenrolled from all the group courses and their course progress will be reset.");
        if (r == true) {
            var course_container = $this.closest('.flms-course');
            if(!course_container.hasClass('processing')) {
                course_container.addClass('processing');
                var user_id = $this.attr('data-user');
                $.ajax({
                    url: flms_admin_group.ajax_url,
                    data: {
                        action : 'leave_group',
                        post_id : flms_admin_group.post_id,
                        user_id : user_id,
                        is_admin : true,
                    },
                    success: function(data) {
                        if(data.success == 1) {
                            $('#group-members').html('<div class="setting-area-group-members"><div class="setting-area-fields">'+data.new_html+'</div></div>');
                            $('#group-courses').html('<div class="setting-area-group-courses"><div class="setting-area-fields">'+data.new_course_html+'</div></div>');
                        } 
                    }
                });
            }
        }
    });

    $(document).on('click','.admin-remove-course-from-group', function(e) {
        e.preventDefault();
        var $this = $(this);

        var r = confirm("Are you sure you want to remove this group? All users will be unenrolled and their course progress reset.");
        if (r == true) {
            var course_container = $this.closest('.flms-course');
            if(!course_container.hasClass('processing')) {
                course_container.addClass('processing');
                var course_index = $this.attr('data-course-index')
                $.ajax({
                    url: flms_admin_group.ajax_url,
                    data: {
                        action : 'remove_course_from_group',
                        group_id : flms_admin_group.post_id,
                        course_index : course_index
                    },
                    success: function(data) {
                        if(data.success == 1) {
                            $('#group-courses').html('<div class="setting-area-group-courses"><div class="setting-area-fields">'+data.new_html+'</div></div>');
                        } 
                    }
                });
            }
        }
    });

    $(document).on('click','.admin-remove-user-from-course', function(e) {
        e.preventDefault();
        var $this = $(this);
        var course_index = $this.attr('data-course-index');
        var user_id = $this.attr('data-user');
        var r = confirm("Are you sure you want to remove this user? They will be unenrolled and their course progress reset.");
        if (r == true) {
            var course_container = $this.closest('.flms-course');
            if(!course_container.hasClass('processing')) {
                course_container.addClass('processing');
                var course_index = $this.attr('data-course-index')
                $.ajax({
                    url: flms_admin_group.ajax_url,
                    data: {
                        action : 'remove_user_course_from_group',
                        group_id : flms_admin_group.post_id,
                        course_index : course_index,
                        user_id : user_id
                    },
                    success: function(data) {
                        if(data.success == 1) {
                            $('#group-courses').html('<div class="setting-area-group-courses"><div class="setting-area-fields">'+data.new_html+'</div></div>');
                        } 
                    }
                });
            }
        }
    });


})( jQuery );