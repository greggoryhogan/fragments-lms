
(function($) {
    
    /**
     * Autocomplete when adding content
     */
    $(document).on('focus, keyup','.search-existing-content', function() {
        var post_type = $(this).attr('data-type');
        var course_id = $(this).attr('data-course');
        var input = $(this);
        if ($(this).hasClass('ui-autocomplete-input')) {
            $(this).autocomplete('destroy')
        }
        $(this).autocomplete({
            source: function(request, response) {
                var exclude = [];
                $('.setting-area-content.is-active input').each(function() {
                    exclude.push($(this).val());
                });
                //console.log(exclude);
                $('#deselected-'+post_type+' input').each(function() {
                    exclude.push($(this).val());
                });
                $.ajax({
                    url: flms_admin_course_manager.ajax_url,
                    dataType: 'json',
                    data: {
                        action : 'search_existing_content',
                        term : request.term,
                        course_id : course_id,
                        exclude : exclude,
                        post_type : post_type,
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
                if(ui.item.value > 0) {
                    var html = flms_admin_course_manager.lesson_list_html;
                    var list = $('#flms-content-list-'+post_type+'-'+course_id);
                    
                    $.ajax({
                        url: flms_admin_course_manager.ajax_url,
                        type: 'post',
                        data: {
                            action : 'get_lesson_list_html',
                            post_id : ui.item.value,
                            course_id : course_id,
                            post_type : post_type,
                        },
                        success: function(data) {
                            list.append(data.html);
                            update_sortable_flms_content();
                        }
                    });
                }
                // Clear the input field
                input.val('');
                $('#add-'+ course_id + '-'+post_type+'-content').removeClass('is-active');
                return false;
            }
        });
        $(this).autocomplete('search', $(this).val());
    });

    /**
     * Autocomplete when adding content
     */
    $(document).on('focus keyup','#associated-content-search', function() {
        var post_type = $(this).attr('data-type');
        var post_id = $(this).attr('data-post');
        var input = $(this);
        if ($(this).hasClass('ui-autocomplete-input')) {
            $(this).autocomplete('destroy')
        }
        $(this).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: flms_admin_course_manager.ajax_url,
                    dataType: 'json',
                    data: {
                        action : 'associate_content_search',
                        term : request.term,
                        post_id : post_id,
                        post_type : post_type,
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
                if(ui.item.value > 0) {
                    input.val(ui.item.label);
                    input.attr('data-content-id',ui.item.value);    
                }
                update_sortable_flms_content();
                return false;
            }
        });
        $(this).autocomplete('search', $(this).val());
    });

    /**
     * Save associated content
     */
    $(document).on('focus','#saveassociatedcontent', function() {
        var post_id = $('#associated-content-search').attr('data-post');
        var associate_post_id = $('#associated-content-search').attr('data-content-id');
        
        $.ajax({
            url: flms_admin_course_manager.ajax_url,
            type: 'post',
            data: {
                action : 'associate_content_save',
                post_id : post_id,
                associate_post_id : associate_post_id,
            },
            success: function(data) {
                location.reload();
            }
        });
    
    });

    /**
     * Show modal
     */
    $(document).on('click','[data-modal-trigger]',function(e) {
        e.preventDefault();
        var modal = $(this).attr('data-modal-trigger');
        $(modal).addClass('is-active');
        var input = $(modal + ' input:first-of-type').attr('id');
        if(input) {
            $('.modal.is-active #'+input).focus();
        }
    });

    /**
     * Close modal
     */
    $(document).on('click','.modal .cancel',function(e) {
        e.preventDefault();
        $('.modal').removeClass('is-active');
    });

    /**
     * Close modal on esc
     */
    $(document).on('keyup', function(e) {
        if (e.keyCode == 27) {
            //esc
            $('.modal').removeClass('is-active');
        }
    });

    /**
     * Create content from insert modal
     */
    $(document).on('click','.create-content-submit',function(e) {
        e.preventDefault();
        var $this = $(this);
        $this.attr('disabled','disabled');
        $this.parent().find('.spinner').addClass('flms-is-loading');
        var course_id = $this.attr('data-course-id');
        var post_type = $this.attr('data-post-type');
        var lesson_title = $('.modal.is-active #new-'+post_type+'-name').val();
        $.ajax({
            url: flms_admin_course_manager.ajax_url,
            type: 'post',
            data: {
                action: 'insert_flms_content',
                course_id : course_id,
                lesson_title: lesson_title,
                post_type : post_type,
            },
            success: function(data) {
                $('.modal').removeClass('is-active');
                $('#flms-content-list-'+post_type+'-'+course_id).append(data.lesson_response);
                $('.flms-is-loading').removeClass('flms-is-loading');
                $this.removeAttr('disabled');
                $('#new-flms-lessons-name').val('');
                sort_flms_content();
            }
        });
    });
    
    function update_sortable_flms_content() {
        sort_flms_content();
    }

    function sort_flms_content() {
        /**
         * Sorting lessons
         */
        $( '.sortable-lessons' ).sortable({
            forcePlaceholderSize: true,
            placeholder: "ui-sortable-placeholder",
            handle: '.handle'
        });

        /**
         * Sorting topics
         */
        if($('.sortable-topics').length) {
            var sorts = [];
            $('.sortable-topics').each(function() {
                if($(this).hasClass('ui-sortable')) {
                    $(this).sortable("refresh");
                } else {
                    sorts.push('#'+$(this).attr('id'));
                }
            });
            $(sorts.toString()).sortable({
                connectWith: ".sortable-topics",
                forcePlaceholderSize: true,
                placeholder: "ui-sortable-placeholder",
                handle: '.handle',
                update: function( event, ui ) {
                    $('.sortable-topics').each(function() {
                        var parent_id = $(this).attr('data-parent-id');
                        $(this).find('li').each(function() {
                            if($(this).hasClass('to-be-removed')) {
                                $(this).find('input').attr('name','deselected-flms-topics['+parent_id+'][]');
                            } else {
                                $(this).find('input').attr('name','selected-flms-topics['+parent_id+'][]');    
                            }
                        });
                    });
                }
            });
        }

        /**
         * Sorting Exams
         */
        if($('.sortable-exams').length) {
            var sorts = [];
            $('.sortable-exams').each(function() {
                if($(this).hasClass('ui-sortable')) {
                    $(this).sortable("refresh");
                } else {
                    sorts.push('#'+$(this).attr('id'));
                }
            });
            $(sorts.toString()).sortable({
                connectWith: ".sortable-exams",
                forcePlaceholderSize: true,
                placeholder: "ui-sortable-placeholder",
                handle: '.handle',
                update: function( event, ui ) {
                    $('.sortable-exams').each(function() {
                        var parent_id = $(this).attr('data-parent-id');
                        $(this).find('li').each(function() {
                            if($(this).hasClass('to-be-removed')) {
                                $(this).find('input').attr('name','deselected-flms-exams['+parent_id+'][]');
                            } else {
                                $(this).find('input').attr('name','selected-flms-exams['+parent_id+'][]');    
                            }
                        });
                    });
                }
            });
        }
    }
    sort_flms_content();
    
    //Toggle content visibility in course manager
    $(document).on('click', '.item-header .title', function() {
        var $this = $(this).parent().parent();
        $this.toggleClass('is-active');
    });

    //Removing lesson, topic, exam from course
    $(document).on('click', '.remove-post-from-course', function() {
        var $this = $(this).parent().parent();
        $this.toggleClass('to-be-removed');
        if($this.hasClass('to-be-removed')) {
            $this.find('input').attr('name',$this.find('input').attr('name').replace('selected','deselected'));
        } else {
            $this.find('input').attr('name',$this.find('input').attr('name').replace('deselected','selected'));
        }
    });

    /*
    $(document).on('click', '.remove-post-from-course', function() {
        var $this = $(this).parent().parent();
        $this.toggleClass('to-be-removed');
        if($this.hasClass('to-be-removed')) {
            //console.log($this.find('input').attr('name'));
            $this.find('input').each(function() {
                if($(this).attr('type') == 'hidden') {
                    var newname = $(this).attr('name').replace('selected','deselected');
                    $(this).attr('name',newname);
                }
                
            });
        } else {
            $this.find('input').each(function() {
                if($(this).attr('type') == 'hidden') {
                    var newname = $(this).attr('name').replace('deselected','selected');
                    $(this).attr('name',newname);
                }
                
            });
        }
    });*/

    //course correlation
    /*$(document).on('focus, keyup','.flms_course_select', function() {
        var $this = $(this);
        if ($(this).hasClass('ui-autocomplete-input')) {
            $(this).autocomplete('destroy')
        }
        $(this).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: flms_admin_course_manager.ajax_url,
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
                if(ui.item.value > 0) {
                    //$this.
                    var html = flms_admin_course_manager.lesson_list_html;
                    var list = $('#flms-content-list-'+post_type+'-'+course_id);
                    
                    $.ajax({
                        url: flms_admin_course_manager.ajax_url,
                        type: 'post',
                        data: {
                            action : 'get_lesson_list_html',
                            post_id : ui.item.value,
                            course_id : course_id,
                            post_type : post_type,
                        },
                        success: function(data) {
                            list.append(data.html);
                            update_sortable_flms_content();
                        }
                    });
                }
                // Clear the input field
                input.val('');
                $('#add-'+ course_id + '-'+post_type+'-content').removeClass('is-active');
                return false;
            }
        });
        $(this).autocomplete('search', $(this).val());
    });*/

    //hide purchase options on course settings change
    if($('select[name="course_access"]').length) {
        var course_access = $('select[name="course_access"]').val();
        if(course_access == 'open') {
            if($('.tab-selector #product_options').hasClass('is-active')) {
                $(this).removeClass('is-active');
                $('.tab-selector #course-content').addClass('is-active');
            }
            $('.tab-selector #tab-select-product_options').addClass('flms-is-hidden');
        }
        $(document).on('change', 'select[name="course_access"]', function() {
            var course_access = $('select[name="course_access"]').val();
            if(course_access == 'open') {
                $('.tab-selector #tab-select-product_options').addClass('flms-is-hidden');
            } else {
                $('.tab-selector #tab-select-product_options').removeClass('flms-is-hidden');
            }
        });
    }

    
    // product variations checkbox generate variations options
    $(document).on('click','#update-flms-variations', function(e) {
        e.preventDefault();
        var map_array = [];
        $('.flms-course-product-attribute').each(function(){
            if($(this).is(':checked')) {
                map_array.push($(this).val());
            }
        });
        if(map_array.length == 0) {
            $('#course_product_variations').html('<div><em>Select at least one attribute to get started.</em></div>');
        } /*else if (map_array.length == 1) {
            $('#course_product_variations').html('<div><em>Not enough attributes to create variations. Consider setting this course as a simple product type.</em></div>');
        }*/ else {
            $('.course-product-variations').addClass('is-loading');
            $.ajax({
                url: flms_admin_course_manager.ajax_url,
                data: {
                    action : 'get_course_product_variation_options',
                    post_id : flms_admin_course_manager.post_id,
                    terms : map_array
                },
                success: function(data) {
                    $('#course_product_variations').html(data.variations);
                    $('.course-product-variations').removeClass('is-loading');
                }
            });
        }
    });

    //toggle price settings
    $(document).on('change','#flms-course-product-type', function(e) {
        var val = $(this).val();
        if(val == 'simple') {
            $('.flms-variable-price-settings').addClass('flms-is-hidden');
            $('.flms-simple-price-settings').removeClass('flms-is-hidden');
        } else {
            $('.flms-simple-price-settings').addClass('flms-is-hidden');
            $('.flms-variable-price-settings').removeClass('flms-is-hidden');
        }
    });

    //course questions category highlight
    $(document).on('change','.flms-question-categories input[type="checkbox"]',function() {
        if($(this).is(':checked')) {
            $(this).closest('li').addClass('flms-highlighted');
        } else {
            $(this).closest('li').removeClass('flms-highlighted');
        }
    })

})( jQuery );