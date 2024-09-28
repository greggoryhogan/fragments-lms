(function($) {

    var type = '';
    var file = '';
    var action = '';
    var default_import_text = $('#import-content-column-map').text();
    var new_import_button = '<button class="button button-primary" id="new-import">Run New Import</button>';
    var files_to_unlink = [];

    $('#import-file-upload,#settings-import-file-upload').change( function() {
        if ( this.files.length ) {
            const file = this.files[0];
            
            if(file.size > flms_importer.max_upload_size) {
                alert("File is too large");
                this.value = "";
                return false;
            }
            
            const formData = new FormData();
            formData.append( 'import_file', file );
            formData.append('action','flms_upload_file');
            $.ajax({
                url: flms_importer.ajax_url,
                type: 'POST',
                data: formData,
                contentType: false,
                enctype: 'multipart/form-data',
                processData: false,
                success: function ( response ) {
                    if(response.success == 1) {
                        if(response.error !== false) {
                            alert('There was an error uploading your file.');	
                        } else {
                            $( '#flms_import_file' ).val( response.file );
                            files_to_unlink.push(response.file);
                            $('#import-content-submit').removeClass('is-processing');
                            $('#import-content-column-map').removeClass('is-processing');
                        }
                        
                    }
                }
            });
        }
    });

    $(document).on('click','#new-import', function(e) {
        e.preventDefault();
        location.reload();
    });
    
    $(document).on('click','#import-content-column-map', function(e) {
        e.preventDefault();
        if($(this).hasClass('is-processing')) {
            return;
        }
        if($('#import-type').val() == -1) {
            alert('Please select an import type.');
            return;
        } else {
            type = $('#import-type').val();
        }
        var import_action = wp.hooks.applyFilters('flms_import_action', $('#import-action').val(), type);
        if(import_action == -1) {
            alert('Please select an import action.');
            return;
        }
        
        var import_action_text = $( "#import-action option:selected" ).text();
        var type_text = $( "#import-type option:selected" ).text();
        var button_label = import_action_text + ' '+ type_text;
        var button_text = wp.hooks.applyFilters('flms_import_button_text', button_label, type, import_action);
        var label = wp.hooks.applyFilters('flms_import_prefix_label', $( "#import-type option:selected" ).attr('data-label'), type);
        file = $('#flms_import_file').val();
    
        
        $('.importer-nav .step').removeClass('is-active');
        $('.importer-nav .is-mapping').addClass('is-active');
        $('#import-content-column-map').addClass('is-processing').text('Please wait');
        $('#map-fields-response').html('');
        $.ajax({
            url: flms_importer.ajax_url,
            type: 'post',
            data: {
                action: 'import_map_columns',
                type : type,
                file : file,
                import_action : import_action,
                label : label,
            },
            success: function(data) {
                if(data.success == 0) {
                    alert(data.message);
                } else {
                    $('#import-step-1').removeClass('is-active');
                    $('#map-fields-response').html(data.column_mapping_options).addClass('is-active');
                    $('#import-column-map-container').removeClass('is-active');
                    $('#import-content-submit').text(button_text);
                    $('#import-submit-container').addClass('is-active');
                    $('#import-content-back').removeClass('flms-is-hidden');
                }
                $('#import-content-column-map').removeClass('is-processing').text('Next');
                //$('#import-settings-content').html(data.columns);
            },
        });
    });

    $(document).on('click', '#import-content-back', function(e) {
        e.preventDefault();
        $('#map-fields-response').removeClass('is-active');
        $('#import-submit-container').removeClass('is-active');
        $('#import-content-back').addClass('flms-is-hidden');

        $('#import-column-map-container').addClass('is-active');
        $('#import-content-submit').text('Import');
        $('#import-step-1').addClass('is-active');
        $('.importer-nav .step').removeClass('is-active');
        $('.importer-nav .is-settings').addClass('is-active');
    });

    var is_importing = false;
    $(document).on('click','#import-content-submit', function(e) {
        e.preventDefault();
        if(is_importing) {
            return;
        }
        var continue_processing = true;
        if($('#import-type').val() == -1) {
            alert('Please select an import type.');
            return;
        }
        type = $('#import-type').val();
        file = $('#flms_import_file').val();
        var import_action = '';
        if(type != 'plugin-settings') {
            import_action = $('#import-action').val();
        }
        var map_array = {};
        if($('#map-fields-response select').length) {
            $('#map-fields-response select').each(function(){
                if(this.value == -1) {
                    alert('Please map all columns');
                    continue_processing = false;
                    return false;
                }
                var field_name = $(this).attr('data-field');
                map_array[field_name] = this.value;
            });
            //console.log(map_array);
        }
        //dbl check to continue
        if(!continue_processing) {
            return false;
        }
        var r = confirm("Are you sure you want to continue with the import?");
        if (r == true) {
            is_importing = true;
            $('.importer-nav .step').removeClass('is-active');
            $('.importer-nav .is-importing').addClass('is-active');
            $('#import-settings-content').html('<div class="progress-loading"><div class="progress" style="width: 0%;"></div></div>');
            $('.progress-loading').addClass('is-active');
            var i = 0;
            var timeout = 50;
            var t;
            (function progressbar() {
                i++;
                if(i < 1000) {
                    // some code to make the progress bar move in a loop with a timeout to 
                    // control the speed of the bar
                    var width = parseInt((i / 1000) * 100);
                    $('.progress-loading .progress').width(width +'%');
                    t = setTimeout(progressbar, timeout);
                }
            })();
            $.ajax({
                url: flms_importer.ajax_url,
                type: 'post',
                data: {
                    action: 'import_content',
                    type : type,
                    file : file,
                    import_action : import_action,
                    field_indexes : map_array,
                    files_to_unlink : files_to_unlink,
                    user_id : flms_importer.user_id
                },
                success: function(data) {
                    is_importing = false;
                    clearTimeout(t);
                    $('.importer-nav .step').removeClass('is-active');
                    $('.importer-nav .is-done').addClass('is-active');
                    $('.progress-loading .progress').width('100%');
                    setTimeout(function() {
                        $('.progress-loading').removeClass('is-active');
                        if(data.success == 1) {
                            var default_message = '<h2 style="text-align: center;">Import started.</h2><p style="text-align: center;">You will be notified when your import completes.</p>';
                            var message = wp.hooks.applyFilters('flms_import_success_message', default_message, type);
                            $('#import-settings-content').html(message+new_import_button);
                        } else {
                            $('#import-settings-content').html('<h2 style="text-align: center;">An error occurred importing the content</h2>'+new_import_button);
                        }
                    }, 500);
                }, error: function(xhr, ajaxOptions, thrownError) {
                    is_importing = false;
                    clearTimeout(t);
                    $('.importer-nav .step').removeClass('is-active');
                    $('.importer-nav .is-done').addClass('is-active');
                    $('.progress-loading .progress').width('100%');
                    setTimeout(function() {
                        $('.progress-loading').removeClass('is-active');
                        $('#import-settings-content').html('<h2 style="text-align: center;">An error occurred importing the content</h2><div style="text-align:center;">'+xhr.responseText+'</div>'+new_import_button);
                    }, 500);
                }
            });   
        }
    });

})( jQuery );