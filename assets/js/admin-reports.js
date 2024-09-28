(function($) {
    
    var autoloadreport = 0;
    $('#reports-settings').on('submit',function(e) {
        autoloadreport = 0;
        found = [];
        e.preventDefault();
        var type = $('#report-type').val();
        if(type == 0) {
            alert('Please select a report type.');
            return false;
        }
        if(type == 'royalties') {
            if($('#flms-taxonomy-select').val() == -1 || $('#flms-selected-taxonomy').val() == -1) {
                alert('Please fill out all fields.');
                return false;
            }
        }
        if(type == 'course_credits') {
            if($('#flms-course-credit-select').val() == -1) {
                alert('Please fill out all fields.');
                return false;
            }
        }
        if(type == 'answers') {
            if($('#flms-course-select').val() == 0 || $('#flms-version-select').val() == -1 || $('#flms-exam-select').val() == 0) {
                alert('Please fill out all fields.');
                return false;
            }
        }
        $('#load-report').val('-1');
        $('#delete_report').removeClass('is-active');
        $('#save-report').removeClass('is-active');
        $('#report-results').removeClass('is-active');
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
            url: flms_reports.ajax_url,
            type: 'post',
            data: {
                action: 'generate_report',
                fields: $("#reports-settings").serialize()
            },
            success: function(data) {
                //alert('done');
                clearTimeout(t);
                $('.progress-loading .progress').width('100%');
                setTimeout(function() {
                    $('#report-name').val('');
                    $('#save_status').text('');
                    $('#active-report').val('-1');
                    $('.progress-loading').removeClass('is-active');
                    $('#report-results').html(data.report_content).addClass('is-active');
                    $('#save-report').find('button').text('Save Report');
                    $('#report-data-breakdown').html('');
                    $('#save-report').addClass('is-active');
                    $('#report-data').val(data.report_data);
                }, 500);
                
            }
        });
    });

    $('#load-reports').on('submit',function(e) {
        e.preventDefault();
        autoloadreport = 0;
        found = [];
        var key = $('#load-report').val();
        if(key == -1) {
            alert('Please select a report.');
            return false;
        }
        $('#save-report').removeClass('is-active');
        $('#save_status').text('');
        $('#report-results').removeClass('is-active');
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
            url: flms_reports.ajax_url,
            type: 'post',
            data: {
                action: 'get_saved_report',
                key: key,
            },
            success: function(data) {
                //console.log(data);
                //alert('done');
                clearTimeout(t);
                $('.progress-loading .progress').width('100%');
                setTimeout(function() {
                    $('#active-report').val(key);
                    $('.progress-loading').removeClass('is-active');
                    $('#report-results').html(data.report_content).addClass('is-active');
                    $('#save-report').find('button').text('Update Report Name');
                    $('#save-report').addClass('is-active');
                    $('#report-data').val(data.report_data);
                    $('#report-name').val(data.report_name);
                    $('#delete_report').addClass('is-active');
                    $('#report-data-breakdown').html(data.report_information);
                    //get saved report type
                    var savedreportype = $('#report-data-breakdown div').attr('data-report-type');
                    $('#appended-report-data').html('');
                    $('#report-type').val(savedreportype);
                    autoloadreport = 1;
                    $('#report-type').trigger('change');
                    
                }, 500)
                
            }
        });
    });

    //expand/reduce report data
    $(document).on('click', '#expand-report-toggle', function() {
        $(this).toggleClass('is-active');
        $('#report-results').toggleClass('expanded-data');
    });

    var found = [];
    $( document ).on( "ajaxComplete", function(event,xhr,options) {
        if($('#appended-report-data').length) {
            if(autoloadreport == 1) {
                $('#appended-report-data').find('select').each(function() {
                    var name = $(this).attr('id');
                    if($.inArray(name.toString(), found) == -1) {
                        found.push(name.toString());
                        var savedval = $('#report-data-breakdown').find('div[data-'+name+']').attr('data-'+name);
                        $('#'+name).val(savedval).trigger('change');   
                    }     
                });
                $('#appended-report-data').find('input[type=date]').each(function() {
                    var name = $(this).attr('id');
                    if($.inArray(name.toString(), found) == -1) {
                        found.push(name.toString());
                        var savedval = $('#report-data-breakdown').find('div[data-'+name+']').attr('data-'+name);
                        $('#'+name).val(savedval).trigger('change');   
                    }     
                });
            }
        }
    });

    $('#save_report').on('click',function(e) {
        e.preventDefault();
        var name = $('#report-name').val();
        if(name == '') {
            alert('Please specify a report name.');
            return false;
        }
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
        var report_data = $('#report-data').val();
        var active_report = $('#active-report').val();
        //console.log(active_report);
        $.ajax({
            url: flms_reports.ajax_url,
            type: 'post',
            data: {
                action: 'save_report',
                report_name: name,
                report_data : report_data,
                active_report: active_report,
            },
            success: function(data) {
                //alert('done');
                clearTimeout(t);
                $('.progress-loading .progress').width('100%');
                setTimeout(function() {
                    $('.progress-loading').removeClass('is-active');
                    $('#save_status').text('Saved!');
                    //update dropdown
                    $('#delete_report').addClass('is-active');
                    $('#load-reports option').each(function() {
                        if($(this).val() == -1) {
                            $(this).text('Load report');
                        }
                        $(this).attr('checked',false);
                    });
                    if(active_report >= 0) {
                        $('#load-report option[value="'+active_report+'"]').text(name);
                    } else {    
                        var report_count =  $('#load-reports option').length - 1; //because it starts at -1
                        $('#load-report').append($('<option>', { 
                            value: report_count,
                            text : name,
                            selected: 'selected'
                        }));
                    }
                }, 500);
                
                
            }
        });
    });

    $('#delete_report').on('click',function(e) {
        e.preventDefault();
        var r = confirm("Are you sure you want to delete this report?");
        if (r == true) {
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
            var active_report = $('#load-report').val();
            $.ajax({
                url: flms_reports.ajax_url,
                type: 'post',
                data: {
                    action: 'delete_report',
                    active_report: active_report,
                },
                success: function(data) {
                    //alert('done');
                    clearTimeout(t);
                    $('.progress-loading .progress').width('100%');
                    setTimeout(function() {
                        $('.progress-loading').removeClass('is-active');
                        window.location = window.location;
                    }, 500);    
                }
            });
        }
    });

    $(document).on('click', '#export-report', function(e) {
        e.preventDefault();
        var $this = $(this);
        var temptext = $this.text();
        $this.text('Exporting...');
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
        var report_data = $('#report-data').val();
        $.ajax({
            url: flms_reports.ajax_url,
            type: 'post',
            data: {
                action: 'export_report',
                report_data : report_data,
            },
            success: function(data) {
                clearTimeout(t);
                $('.progress-loading .progress').width('100%');
                if(data.success == 1) {
                    var a = document.createElement('a');
                    a.href= data.filepath;
                    a.target = '_blank';
                    a.download = data.filename;
                    a.click();
                    $.ajax({
                        url: flms_reports.ajax_url,
                        type: 'post',
                        data: {
                            action: 'delete_export',
                            path : data.filename,
                        } 
                    });
                } else {
                    alert('An error ocurred!');
                }
                $this.text(temptext);
                setTimeout(function() {
                    $('.progress-loading').removeClass('is-active');
                    $('.export_content').removeClass('is-processing');
                }, 500);
            }
        });
    });

    $(document).on('change', '#report-type', function(e) {
        e.preventDefault();
        $('#reports-settings').addClass('is-loading');
        var type = $(this).val();
        $.ajax({
            url: flms_reports.ajax_url,
            type: 'get',
            data: {
                action: 'get_report_type_fields',
                type : type
            },
            success: function(data) {
                $('#appended-report-data').html(data.report_fields);
                $('#reports-settings').removeClass('is-loading');
            }
        });
    });

    $(document).on('change', '#flms-course-select', function(e) {
        e.preventDefault();
        $('#reports-settings').addClass('is-loading');
        var course = $(this).val();
        $.ajax({
            url: flms_reports.ajax_url,
            type: 'get',
            data: {
                action: 'get_reporting_course_versions',
                course: course,
            },
            success: function(data) {
                $('#reporting-fee-container').remove();
                $('#start-date-container').remove();
                $('#end-date-container').remove();
                $('#flms-report-course-version').remove();
                $('#flms-course-progress-status').remove();
                $('#appended-report-data').append(data.report_fields);
                $('#reports-settings').removeClass('is-loading');
            }
        });
    });
    
    $(document).on('change', '#flms-version-select', function(e) {
        e.preventDefault();
        $('#reports-settings').addClass('is-loading');
        var primary_action = $('#report-type').val();
        var course = $('#flms-course-select').val();
        var version = $('#flms-version-select').val();
        $.ajax({
            url: flms_reports.ajax_url,
            type: 'get',
            data: {
                action: 'get_course_reporting_fields',
                primary_action: primary_action,
                course: course,
                version : version
            },
            success: function(data) {
                $('#flms-report-exam-version').remove();
                $('#flms-report-exam-select').remove();
                $('#appended-report-data').append(data.report_fields);
                $('#reports-settings').removeClass('is-loading');
            }
        });
    });

    $(document).on('change', '#flms-course-status-select', function(e) {
        e.preventDefault();
        $('#reports-settings').addClass('is-loading');
        var primary_action = $('#report-type').val();
        var course = $('#flms-course-select').val();
        var version = $('#flms-version-select').val();
        var status = $('#flms-course-status-select').val();
        $.ajax({
            url: flms_reports.ajax_url,
            type: 'get',
            data: {
                action: 'get_course_status_reporting_fields',
                primary_action: primary_action,
                course: course,
                version : version,
                status : status
            },
            success: function(data) {
                $('#start-date-container').remove();
                $('#end-date-container').remove();
                $('#appended-report-data').append(data.report_fields);
                $('#reports-settings').removeClass('is-loading');
                
            }
        });
    });

    $(document).on('change', '#flms-course-credit-select', function(e) {
        e.preventDefault();
        $('#reports-settings').addClass('is-loading');
        var credit_type = $(this).val();
        $.ajax({
            url: flms_reports.ajax_url,
            type: 'get',
            data: {
                action: 'get_reporting_course_credit_options',
                credit_type: credit_type,
            },
            success: function(data) {
                $('#reporting-fee-container').remove();
                $('#start-date-container').remove();
                $('#end-date-container').remove();
                $('#appended-report-data').append(data.report_fields);
                $('#reports-settings').removeClass('is-loading');
            }
        });
    });

    $(document).on('click','.toggle-other-answers',function() {
        $(this).parent().toggleClass('is-active');
    });

    $(document).on('change', '#flms-taxonomy-select', function(e) {
        e.preventDefault();
        $('#reports-settings').addClass('is-loading');
        var taxonomy = $(this).val();
        $.ajax({
            url: flms_reports.ajax_url,
            type: 'get',
            data: {
                action: 'get_reporting_royalty_by_taxonomy_options',
                taxonomy_slug : taxonomy
            },
            success: function(data) {
                $('#reporting-fee-container').remove();
                $('#start-date-container').remove();
                $('#end-date-container').remove();
                $('#appended-report-data').append(data.report_fields);
                $('#reports-settings').removeClass('is-loading');
            }
        });
    });

})( jQuery );