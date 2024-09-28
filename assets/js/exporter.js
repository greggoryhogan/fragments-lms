(function($) {

    //show necessary fields on change
    $(document).on('change', '#export-type', function(e) {
        e.preventDefault();
        $('#export-settings').addClass('is-loading');
        var type = $(this).val();
        $.ajax({
            url: flms_admin_settings.ajax_url,
            type: 'get',
            data: {
                action: 'get_export_type_fields',
                type : type
            },
            success: function(data) {
                $('#appended-export-data').html(data.report_fields);
                $('#export-settings').removeClass('is-loading');
            }
        });
    });

    //bulk select items in export
    $(document).on('click', '#export-check-all', function(e) {
        e.preventDefault();
        $(this).toggleClass('is-toggled');
        if($(this).hasClass('is-toggled')) {
            $('.has-check-all input').each(function() {
                $(this).prop('checked',true);
            });
        } else {
            $('.has-check-all input').each(function() {
                $(this).prop('checked',false);
            });
        }
    });

    //allow bulk select when clicking parent
    $(document).on('click', '.bulk-select input', function() {
        
        if($(this).is(":checked")){
            $(this).parent().find('input').each(function() {
                $(this).prop('checked',true);
            });
        } else {
            $(this).parent().find('input').each(function() {
                $(this).prop('checked',false);
            });
        }
    
    });

    //send event to ajax
    $('#export-settings').on('submit',function(e) {
        e.preventDefault();
        if($('.export_content').hasClass('is-processing')) {
            return;
        }
        var type = $('#export-type').val();
        if(type == -1) {
            alert('Please select an export type.');
            return false;
        }
        var items = [];
        if(type == 'courses' || type == 'lessons' || type == 'topics' || type == 'exams' || type == 'questions' || type == 'user-data') {
            $('.bulk-select input:checked').each(function() {
               items.push(this.value); 
            });
            if(items.length == 0){
                alert('Please select one or more items to export.');
                return false;
            }
        } 
        $('.page-exporter .response').text('Exporting...');
        $('.export_content').addClass('is-processing');
        $('.progress-loading .progress').width('0%');
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
            url: flms_exporter.ajax_url,
            type: 'post',
            data: {
                action: 'export_content',
                type : type,
                items : items
            },
            success: function(data) {
                clearTimeout(t);
                $('.progress-loading .progress').width('100%');
                $('#export-history').html(data.export_list);
                $('#delete-all-exports').removeClass('inactive')
                var a = document.createElement('a');
                a.href= data.filepath;
                a.target = '_blank';
                a.download = data.filename;
                a.click();
                $('.page-exporter .response').text('Done!');
                setTimeout(function() {
                    $('.progress-loading').removeClass('is-active');
                    $('.export_content').removeClass('is-processing');
                }, 500);
                setTimeout(function() {
                    $('.page-exporter .response').text('');
                }, 1500);
            },
            error: function(xhr, ajaxOptions, thrownError) {
                is_importing = false;
                clearTimeout(t);
                alert(xhr.responseText);
                //alert('Error exporting!');
                $('.page-exporter .response').text('Error!');
                setTimeout(function() {
                    $('.progress-loading').removeClass('is-active');
                    $('.export_content').removeClass('is-processing');
                }, 500);
                setTimeout(function() {
                    $('.page-exporter .response').text('');
                }, 1500);
            }
        });
    });

    $(document).on('click', '.export-list .action span', function() {
        var r = confirm("Are you sure you want to delete this export?");
        if (r == true) {
            var $this = $(this);
            var path = $(this).attr('data-path');
            $.ajax({
                url: flms_exporter.ajax_url,
                type: 'post',
                data: {
                    action: 'delete_export',
                    path : path
                },
                success: function(data) {
                    if(data.deleted == 1) {
                        $this.parent().parent().remove();
                        if(!$('.export-list .action').length) {
                            $('#export-history').html('No exports to show.');
                        }
                    } else {
                        alert('There was an error deleting this export');
                    }
                },
            });   
        }
    });

    $(document).on('click', '#delete-all-exports', function() {
        var r = confirm("Are you sure you want to delete all exports?");
        if (r == true) {
            var $this = $(this);
            $('#export-history').addClass('is-processing');
            $.ajax({
                url: flms_exporter.ajax_url,
                type: 'get',
                data: {
                    action: 'delete_all_exports',
                },
                success: function(data) {
                    //window.location.href = window.location+'#user-active-courses';
                    location.reload();
                },
            });   
        }
    });

})( jQuery );