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

})( jQuery );