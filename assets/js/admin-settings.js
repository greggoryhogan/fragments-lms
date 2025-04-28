(function($) {
    
    //color picker
    $('.flms-color-picker').wpColorPicker();

    //Update active tab in settings page
    $('.setting-group-button').on('click',function(e) {
        e.preventDefault();
        var group = $(this).attr('data-group');
        update_tabs(group)
        Cookies.set('flms_current_tab', group, { path: '' });
    });

    //select tab on load
    if($('.setting-group-button').length) {
        var tab = Cookies.get('flms_current_tab');
        if(tab !== null) {
            if($('#'+tab).length) {
                update_tabs(tab);
            }
        }
    }

    function update_tabs(group) {
        $('.fragment-settings .setting-area-content.is-active, .fragment-settings .tab-selector .is-active').removeClass('is-active')
        $('.fragment-settings button').removeClass('wp-ui-notification');
        $('.setting-group-button[data-group="'+group+'"]').parent().addClass('is-active').find('button').addClass('wp-ui-notification');
        $('#'+group).addClass('is-active');
    }
    
    //Update active tab in settings page
    $('.exam-tabs .tab').on('click',function(e) {
        e.preventDefault();
        var group = $(this).attr('data-tab').replace('#','');
        update_exam_tabs(group)
        Cookies.set('flms_current_exam_tab', group, { path: '' });
    });

    //select tab on load
    if($('.exam-tabs').length) {
        var tab = Cookies.get('flms_current_exam_tab');
        if(tab !== null) {
            if($('#'+tab).length) {
                update_exam_tabs(tab);
            }
        }
    }

    function update_exam_tabs(group) {
        $('.exam-tabs .tab, .flms-tab-section').removeClass('is-active')
        $('#'+group).addClass('is-active');
        $('.exam-tabs [data-tab="#'+group+'"]').addClass('is-active');
    }
    
    //Callbacks for flags in modules
    var flms_settings = flms_admin_settings.settings;
    $(document).on('change','[data-flag]',function(e) {
        var $this = $(this);
        var flag = $this.attr('data-flag');
        var inputName = $this.attr('name');
        switch(flag) {
            case 'check_for_active_versions':
                //Check if there are active versions of courses. If there are and the user is changing to the versions module to inactive, set notice
                var activeVersions = flms_settings.global_flags.has_course_versions;
                var selectOption = $('input[name="'+ inputName + '"]:checked').val();
                $this.parent().find('.flag-notice').text('');
                if(selectOption == 'inactive' && activeVersions) {
                    $this.parent().find('.flag-notice').text('You have active course versions. By disabling this module, all versions will be deleted and enrollees of those courses will lose their data.');
                }
                break;
        }
    });

    function update_radio_toggles() {
        $('.radio-toggle input[type="radio"]:checked').each(function() {
            var toggle = $(this).attr('data-toggle');
            var parent = $(this).closest('.has-toggleable-content');
            parent.find('.conditional-toggle').each(function() {
                $(this).addClass('is-hidden');
            })
            $(toggle).removeClass('is-hidden');
        });
    }

    
    /**
     * Toggle visibility of toggleable content
     */
    update_radio_toggles();
    $(document).on('change','.radio-toggle input',function(e) {
        update_radio_toggles();
    });
    
    $('[data-conditional-toggle] select').each(function() {
        var $this = $(this);
        updateConditional($this);
    });

    $(document).on('change', '[data-conditional-toggle] select', function() {
        var $this = $(this);
        updateConditional($this);
    });

    function updateConditional($this) {
        var parent = $this.closest('.settings-field');
        var field = parent.attr('data-conditional-field');
        var condition = parent.attr('data-condition');
        //alert(condition);
        var val = parent.attr('data-conditional-toggle-val');
        
        //console.log(field);
        if($this.val() == val) {
            if(condition == 'show') {
                $(field).show();
            } else {
                $(field).hide();
            }
        } else {
            if(condition == 'show') {
                $(field).hide();
            } else {
                $(field).show();
            }
        }
    }

    $( '.setting-area-course_credits .setting-area-fields' ).sortable({
        items : '> :not(.create-course-credit-field)',
        forcePlaceholderSize: true,
        placeholder: "ui-sortable-placeholder",
        handle: '.handle'
    });

    $( '.setting-area-course_taxonomies .setting-area-fields' ).sortable({
        items : '> :not(.create-course-taxonomy-field)',
        forcePlaceholderSize: true,
        placeholder: "ui-sortable-placeholder",
        handle: '.handle'
    });

    $( '.setting-area-course_metadata .setting-area-fields' ).sortable({
        items : '> :not(.create-course-metadata-field)',
        forcePlaceholderSize: true,
        placeholder: "ui-sortable-placeholder",
        handle: '.handle'
    });

    $('.currency-input').on('change', function(){
        $(this).val(parseFloat($(this).val()).toFixed(2));
    });

    $(document).on('click','#create-course-credit-field', function(e) {
        e.preventDefault();
        var name = $('input[name="flms_settings[tmp_create_course_credits][tmp-course-credit-name]"]').val();
        if(name == '') {
            alert('Please specify a name');
            return;
        }
        var status = $('input[name="flms_settings[tmp_create_course_credits][tmp-course-credit-status]"]:checked').val();
        var license = $('select[name="flms_settings[tmp_create_course_credits][tmp-course-credit-license-required]"]').val();
        //console.log(license);
        var fee_type = $('select[name="flms_settings[tmp_create_course_credits][tmp-course-credit-reporting-fee-status]"]').val();
        var fee = $('input[name="flms_settings[tmp_create_course_credits][tmp-course-credit-reporting-fee]"]').val();
        var description = $('input[name="flms_settings[tmp_create_course_credits][tmp-course-credit-reporting-fee-description]"]').val();
        var parent = $('select[name="flms_settings[tmp_create_course_credits][tmp-course-credit-parent]"]').val();
        $.ajax({
            url: flms_admin_settings.ajax_url,
            type: 'post',
            data: {
                action: 'create_custom_credit_type',
                name : name,
                status : status,
                license : license,
                fee_type: fee_type,
                fee : fee,
                description : description,
                parent : parent
            },
            success: function(data) {
                //$('#course_credits .setting-area-fields.ui-sortable').append(data.new_credit);
                $(data.new_credit).insertAfter( '.create-course-credit-field' );
                $('input[name="flms_settings[tmp_create_course_credits][tmp-course-credit-name]"]').val('');
                $('input[name="flms_settings[tmp_create_course_credits][tmp-course-credit-reporting-fee]"]').val('0.00');
                $('input[name="flms_settings[tmp_create_course_credits][tmp-course-credit-reporting-fee-description]"]').val('');
            }
        });
    });

    //delete custom credit field
    $(document).on('click','.delete-field',function(e) {
        e.preventDefault();
        var group_id = $(this).attr('data-group');
        var label = $('#'+group_id).attr('data-label');
        //console.log(label);
        var r = confirm("Are you sure you want to delete "+label+"?");
        if (r == true) {
            $('#'+group_id).remove();
            unsaved_settings = true;
        }
    });

    //taxonomies
    $(document).on('click','#create-course-taxonomy-field', function(e) {
        e.preventDefault();
        var singular_name = $('input[name="flms_settings[tmp_create_course_taxonomy][tmp-course-taxonomy-name-singular]"]').val();
        var plural_name = $('input[name="flms_settings[tmp_create_course_taxonomy][tmp-course-taxonomy-name-plural]"]').val();
        if(singular_name == '' || plural_name == '') {
            alert('Please set all taxonomy names');
            return;
        }
        var slug = $('input[name="flms_settings[tmp_create_course_taxonomy][tmp-course-taxonomy-slug]"]').val();
        if(slug == '') {
            slug = plural_name;
        }
        var status = $('input[name="flms_settings[tmp_create_course_taxonomy][tmp-course-taxonomy-status]"]:checked').val();
        var hierarchal = $('select[name="flms_settings[tmp_create_course_taxonomy][tmp-course-taxonomy-hierarchal]"]').val();
        
        $.ajax({
            url: flms_admin_settings.ajax_url,
            type: 'post',
            data: {
                action: 'create_custom_course_taxonomy',
                singular_name : singular_name,
                plural_name : plural_name,
                slug : slug,
                hierarchal : hierarchal,
                status : status,
            },
            success: function(data) {
                //console.log(data);
                //$('#course_credits .setting-area-fields.ui-sortable').append(data.new_credit);
                $(data.new_taxonomy).insertAfter( '.create-course-taxonomies-field' );
                $('input[name="flms_settings[tmp_create_course_credits][tmp-course-taxonomy-name-singular]"]').val('');
                $('input[name="flms_settings[tmp_create_course_credits][tmp-course-taxonomy-name-plural]"]').val('');
                $('input[name="flms_settings[tmp_create_course_credits][tmp-course-taxonomy-name-slug]"]').val('');
            }
        });
    });

    //taxonomies
    $(document).on('click','#create-course-metadata-field', function(e) {
        e.preventDefault();
        var singular_name = $('input[name="flms_settings[tmp_create_course_metadata][tmp-course-metadata-name]"]').val();
        if(singular_name == '') {
            alert('Please set the metadata name');
            return;
        }
        var slug = $('input[name="flms_settings[tmp_create_course_metadata][tmp-course-metadata-slug]"]').val();
        if(slug == '') {
            slug = singular_name;
        }
        var status = $('input[name="flms_settings[tmp_create_course_metadata][tmp-course-metadata-status]"]:checked').val();

        var description = $('input[name="flms_settings[tmp_create_course_metadata][tmp-course-metadata-description]"]').val();
        
        $.ajax({
            url: flms_admin_settings.ajax_url,
            type: 'post',
            data: {
                action: 'create_custom_course_metadata',
                name : singular_name,
                slug : slug,
                description : description,
                status : status,
            },
            success: function(data) {
                //console.log(data);
                //$('#course_credits .setting-area-fields.ui-sortable').append(data.new_credit);
                $(data.new_taxonomy).insertAfter( '.create-course-metadata-field' );
                $('input[name="flms_settings[tmp_create_course_metadata][tmp-course-metadata-name]"]').val('');
                $('input[name="flms_settings[tmp_create_course_metadata][tmp-course-metadata-slug]"]').val('');
                $('input[name="flms_settings[tmp_create_course_metadata][tmp-course-metadata-description]"]').val('');
            }
        });
    });

    if($('#fragment-settings').length) {
        var unsaved_settings = false;
        $(document).on('change','#fragment-settings input, #fragment-settings select', function() {
            unsaved_settings = true;
        });
        $('#fragment-settings #submit').on('click',function() {
            unsaved_settings = false;
        });
        
        $(window).on('beforeunload', function(){
            if(unsaved_settings) {
                return 'You have unsaved changed, are you sure you want to leave?';
            }
        }); 
    }

    $(document).on('change', '#modules .settings-field input', function() {
        var field = $(this).closest('.settings-field');
        var val = $(this).val();
        $(field).removeClass('inactive').removeClass('active').addClass(val);

    });    

   

})( jQuery );