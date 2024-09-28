(function($) {

    //tagify group managers
    var input = document.querySelector('input[name=flms-group-managers]');
    // initialize Tagify on the above input node reference
    var managers = new Tagify(input);

    function toggle_group_purchase_settings() {
        var enabled = $('#group_purchase_enabled').val();
        if(enabled == 1) {
            $('.group_seats_select').addClass('is-active');
        } else {
            $('.group_seats_select').removeClass('is-active');
        }
    }
    $(document).on('change','#group_purchase_enabled', function() {
        toggle_group_purchase_settings();
    });

    var seats_changed = false;
    var default_price = parseFloat($('.woocommerce-Price-amount.amount').text().replace(/[^\d.-]/g, '')).toFixed(2);
    $(document).on('change','.variations input, .variations select', function() {
        default_price = parseFloat($('.woocommerce-Price-amount.amount').text().replace(/[^\d.-]/g, '')).toFixed(2);
        if(seats_changed) {
            update_seats_display();
        }
    });

    function update_seats_display() {
        var seats_changed = true;
        var val = parseInt($('#group_seats').val());
        //console.log(flms_groups.min_seats);
        /*if(val >= flms_groups.min_seats) {
            

        } else {
            $('.woocommerce-Price-amount.amount').text(default_price * val);
        }*/
        if($('.flms-reporting-fee-value').length) {
            //update reporting fee label
            var fee = parseFloat($('.flms-reporting-fee-value').attr('data-default-fee'));
            var new_fee = fee * val;
            var newText = 'Accept '+ flms_groups.price_prefix + new_fee.toFixed(2) + ' '+flms_groups.reporting_label;
            $('.flms-reporting-fee-value').text(newText);
        }
    }
    
    $(document).on('change','#group_seats', function() {
        update_seats_display();
    });

    $(document).on('click', '#check-group-code', function(e) {
        e.preventDefault();
        var group_code = $('#flms-group-code').val();
        var $this = $(this).parent();
        if(!$this.hasClass('is-processing')) {
            $this.addClass('is-processing');
            $('#flms-group-code-validator').removeClass('is-invalid').removeClass('is-valid');
            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'check_group_code',
                    post_id: flms_groups.post_id,
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
                url: flms_groups.ajax_url,
                data: {
                    action : 'generate_group_code',
                    post_id: flms_groups.post_id,
                },
                success: function(data) {
                    $('#flms-group-code').val(data.group_code);
                    $('#flms-group-code-validator').removeClass('is-invalid').addClass('is-valid');
                    $this.removeClass('is-processing');
                }
            });
        }
    });

    $(document).on('click', '#create-group', function(e) {
        e.preventDefault();
        $(this).addClass('flms-d-none');
        $('#assign-seats-toggle').addClass('flms-d-none');
        $('#flms-groups-buttons').addClass('flms-d-none');
        $('#flms-group-form').removeClass('flms-d-none');
    });

    $(document).on('click', '#cancel-new-group', function(e) {
        e.preventDefault();
        $('#flms-group-form').addClass('flms-d-none');
        $('#create-group').removeClass('flms-d-none');
        $('#flms-groups-buttons').removeClass('flms-d-none');
        $('#assign-seats-toggle').removeClass('flms-d-none');
    });

    $(document).on('click', '#create-new-group', function(e) {
        e.preventDefault();
        var $this = $('#flms-group-form');
        if(!$this.hasClass('is-processing')) {
            $this.addClass('is-processing');
            $('.flms-form-feedback').text('');
            if($('#flms-group-name').val() == '') {
                $('#name-feedback').text('Please enter a valid name');
                $this.removeClass('is-processing');
                return false;
            }
            if($('#flms-group-code').val() == '') {
                $('#group-code-feedback').text('Please enter a valid code');
                $this.removeClass('is-processing');
                return false;
            }
            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'create_group_frontend',
                    name : $('#flms-group-name').val(),
                    code : $('#flms-group-code').val(),
                    user_id : flms_groups.user_id
                },
                success: function(data) {
                    if(data.success == 1) {
                        $('#flms-groups-container').html(data.new_group);
                        /*$('#no-flms-groups').addClass('flms-d-none');
                        $('.my-groups-list').append(data.new_group);
                        $('.my-groups-list').removeClass('flms-d-none');
                        $('#flms-group-form').addClass('flms-d-none');
                        $('#create-group').removeClass('flms-d-none');
                        $('#generate-new-group-code').trigger('click');
                        $('#flms-group-name').val('');
                        $('.flms-form-feedback').text('');*/
                    } else {
                        alert('There was an error creating your group');
                    }
                    $this.removeClass('is-processing');
                }
            });
            
        }
    });

    $(document).on('click', '#edit-group-details', function(e) {
        e.preventDefault();
        $(this).addClass('flms-d-none');
        $('#flms-group-form').removeClass('flms-d-none');
        $('#invite-managers').addClass('flms-d-none');
        $('#toggle_invite_managers').removeClass('flms-d-none');
    });
    $(document).on('click', '#cancel-existing-group', function(e) {
        e.preventDefault();
        $('#flms-group-form').addClass('flms-d-none');
        $('#edit-group-details').removeClass('flms-d-none');
    });

    $(document).on('click', '#toggle_invite_managers', function(e) {
        e.preventDefault();
        $(this).addClass('flms-d-none');
        $('#invite-managers').removeClass('flms-d-none');
        $('#flms-group-form').addClass('flms-d-none');
        $('#edit-group-details').removeClass('flms-d-none');
    });
    $(document).on('click', '#cancel-send-manager-invitation', function(e) {
        e.preventDefault();
        $('#invite-managers').addClass('flms-d-none');
        $('#toggle_invite_managers').removeClass('flms-d-none');
    });

    function isEmail(email) {
        var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        return regex.test(email);
      }

      
    $(document).on('click', '#update-existing-group', function(e) {
        e.preventDefault();
        var $this = $('#flms-group-form');
        if(!$this.hasClass('is-processing')) {
            $this.addClass('is-processing');
            $('.flms-form-feedback').text('');
            if($('#flms-group-name').val() == '') {
                $('#name-feedback').text('Please enter a valid name');
                $this.removeClass('is-processing');
                return false;
            }
            if($('#flms-group-owner').val() == '') {
                $('#owner-feedback').text('Please enter a valid email');
                $this.removeClass('is-processing');
                return false;
            }
            if(!isEmail(($('#flms-group-owner').val()))) {
                $('#owner-feedback').text('Please enter a valid email');
                $this.removeClass('is-processing');
                return false;
            }
            if($('#flms-group-code').val() == '') {
                $('#group-code-feedback').text('Please enter a valid code');
                $this.removeClass('is-processing');
                return false;
            }
            var manager_emails = managers.value;

            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'update_group_frontend',
                    name : $('#flms-group-name').val(),
                    code : $('#flms-group-code').val(),
                    owner_email : $('#flms-group-owner').val(),
                    manager_emails : manager_emails,
                    user_id : flms_groups.user_id,
                    post_id : flms_groups.post_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        window.location = data.redirect;
                    } else {
                        alert('There was an error creating your group');
                    }
                    $this.removeClass('is-processing');
                }
            });
            
        }
    });

    //cancel assign seats
    $(document).on('click', '#cancel-assign-seats', function(e) {
        e.preventDefault();
        $('#assign-seats-form').addClass('flms-d-none');
        $('#create-group').removeClass('flms-d-none');
        $('#assign-seats-toggle').removeClass('flms-d-none');
        $('#flms-groups-buttons').removeClass('flms-d-none');
    });

    $(document).on('click', '#assign-seats-toggle', function(e) {
        e.preventDefault();
        $('#assign-seats-toggle').addClass('flms-d-none');
        $('#flms-groups-buttons').addClass('flms-d-none');
        $('#assign-seats-form').removeClass('flms-d-none');
        $('#create-group').addClass('flms-d-none');
    });

    //assign seats
    $(document).on('click', '#assign-seats', function(e) {
        e.preventDefault();
        var $this = $('#assign-seats-form');
        //console.log( $this.serializeArray() );
        var inputValues = {};
        $('#assign-seats-form select').each(function(){
            inputValues[$(this).attr('name')] = $(this).val();
        });
        if(!$this.hasClass('is-processing')) {
            $this.addClass('is-processing');
            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'assign_seats_frontend',
                    assignments : inputValues,
                    user_id : flms_groups.user_id
                },
                success: function(data) {
                    if(data.success == 1) {
                        $('#flms-groups-container').html(data.new_group);
                        $this.removeClass('is-processing');
                        $('.select2').select2({
                            width: 'style',
                        });
                        $('.select2-no-search').select2({
                            width: 'style',
                            minimumResultsForSearch: -1
                        });
                    } else {
                        $this.removeClass('is-processing');
                        alert('There was an error assigning seats');
                    }
                    
                }
            });
            
        }
    });

    $(document).on('click', '.group-course-enroll', function(e) {
        e.preventDefault();
        var $this = $(this);
        var course_container = $this.closest('.flms-course');
        var index = $(this).attr('data-course-index');
        if(!$this.hasClass('is-processing')) {
            course_container.addClass('is-processing');
            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'user_group_enroll',
                    group_id : flms_groups.post_id,
                    index : index,
                    user_id : flms_groups.user_id
                },
                success: function(data) {
                    if(data.success == 1) {
                        $('#flms-group-member-content-container').html(data.new_html);
                        //$this.parent().html('<em>Enrolled</em>');
                    } else {
                        alert('An error occurred');
                    }
                    course_container.removeClass('is-processing');
                }
            });
            return false;
        }
    });

    //assign seats
    $(document).on('submit', '#flms-join-group', function(e) {
        e.preventDefault();
        var $this = $(this);
        $('#join_group_feedback').text('')
        var code = $('#group_code').val();
        if(code == '') {
            $('#join_group_feedback').text(flms_groups.invalid_code);
            return false;
        }
        if(!$this.hasClass('is-processing')) {
            $this.addClass('is-processing');
            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'check_join_group_code',
                    code : code,
                    user_id : flms_groups.user_id
                },
                success: function(data) {
                    if(data.success == 1) {
                        $('#join_group_feedback').html(data.message);
                        if(data.redirect != '') {
                            setTimeout(function() {
                                window.location = data.redirect;
                            }, 1000);
                        }
                    } else {
                        $('#join_group_feedback').html(flms_groups.invalid_code);
                        
                        
                    }
                    $this.removeClass('is-processing');
                }
            });
            return false;
        }
    });

    $(document).on('click', '#flms-delete-group', function(e) {
        e.preventDefault();
        var $this = $('body');
        var r = confirm('Are you sure you want to delete this group?');
        if(r == true) {
            $this.addClass('is-processing');
            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'delete_group',
                    post_id : flms_groups.post_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        window.location = data.redirect;
                    } else {
                        $this.removeClass('is-processing');
                        alert('An error occurred');
                    }
                }
            });
        }
    });

    $(document).on('click', '#flms-leave-group', function(e) {
        e.preventDefault();
        var $this = $('body');
        var r = confirm('Are you sure you want to leave this group? You will be unenrolled from all the group courses and your course progress will be reset.');
        if(r == true) {
            $this.addClass('is-processing');
            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'leave_group',
                    post_id : flms_groups.post_id,
                    user_id : flms_groups.user_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        window.location = data.redirect;
                    } else {
                        $this.removeClass('is-processing');
                        alert('An error occurred');
                    }
                }
            });
        }
    });

    $(document).on('click', '.flms-remove-from-group', function(e) {
        e.preventDefault();
        var $this = $(this);
        var user_id = $this.attr('data-user');
        var $body = $('body');
        var r = confirm('Are you sure you want to remove this user from the group? They will be unenrolled from all the group courses and their course progress will be reset.');
        if(r == true) {

            $body.addClass('is-processing');
            $.ajax({
                url: flms_groups.ajax_url,
                data: {
                    action : 'leave_group',
                    post_id : flms_groups.post_id,
                    user_id : user_id,
                },
                success: function(data) {
                    if(data.success == 1) {
                        $this.closest('.member-row').addClass('is-processing').addClass('flms-strike-through');
                        $this.parent().html('<em>Removed</em>');
                        $body.removeClass('is-processing');
                    } else {
                        alert('An error occurred');
                    }
                }
            });
        }
    });

    $(document).on('click','.toggle-group-users-progress', function(e) {
        e.preventDefault();
        var toggle = $(this).attr('href');
        $(toggle).toggleClass('flms-d-none');
        var text = $(this).text();
        $(this).text($(this).attr('data-toggle-text'));
        $(this).attr('data-toggle-text',text);
        //$(this).removeAttr('data-toggle-text');
        $(this).toggleClass('is-active');    
    });

    $(document).on('click','#send-manager-invitation', function(e) {
        e.preventDefault();
        var message = $('#manager-invite-text').val();
        var $body = $('body');
        $body.addClass('is-processing');
        $.ajax({
            url: flms_groups.ajax_url,
            data: {
                action : 'manager_invitation',
                post_id : flms_groups.post_id,
                user_id : flms_groups.user_id,
                message : message
            },
            success: function(data) {
                window.location = data.redirect;
            }
        });
    });

})( jQuery );