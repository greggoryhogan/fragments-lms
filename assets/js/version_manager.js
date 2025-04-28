
(function($) {

    //Show modal if no course id
    if(version_manager.course_id == 0) {
        console.log(version_manager.course_id);
        $('#wpbody').append(version_manager.no_course_id_notice);
    }

    //Set current version when clicking a version in metabox
    $(document).on('click','.flms-set-version', function(e) {
        e.preventDefault();
        // Check if there are unsaved changes
        var unsavedChanges = wp.data.select('core/editor').isEditedPostDirty();
        if (unsavedChanges) {
            var r = confirm("You have unsaved changes. Are you sure you want to switch versions?");
        }
        if (r == true || !unsavedChanges) {
            var version = $(this).attr('data-version');
            $.ajax({
                url: version_manager.ajax_url,
                type: 'post',
                data: {
                    'action': 'set_currently_editing_version',
                    'post_id' : version_manager.course_id,
                    'version' : version,
                    
                }, success: function( data ) {
                    var response = JSON.parse(data);
                    if(response.version_updated) {
                        //location.reload(true);
                        window.location.href = window.location.href;
                    } else {
                        alert('error moving to new version...');
                    }
                }
            });
        }
    });

    //set current version when changing select box
    $(document).on('change','#switch-to-version',function(e) {
        var unsavedChanges = wp.data.select('core/editor').isEditedPostDirty();
        if (unsavedChanges) {
            var r = confirm("You have unsaved changes. Are you sure you want to switch versions?");
        }
        if (r == true || !unsavedChanges) {
            var version = $(this).val();
            $.ajax({
                url: version_manager.ajax_url,
                type: 'post',
                data: {
                    'action': 'set_currently_editing_version',
                    'post_id' : version_manager.course_id,
                    'version' : version,
                    
                }, success: function( data ) {
                    var response = JSON.parse(data);
                    if(response.version_updated) {
                        //location.reload(true);
                        window.location.href = window.location.href;
                    } else {
                        alert('error moving to new version...');
                    }
                }
            });
        }
    });

    //Create new version when clicking button in meta box
    $(document).on('click','#flms-new-version', function(e) {
        e.preventDefault();
        // Check if there are unsaved changes
        var unsavedChanges = wp.data.select('core/editor').isEditedPostDirty();
        if (unsavedChanges) {
            var r = confirm("You have unsaved changes. Are you sure you want to create a new version?");
        }
        if (r == true || !unsavedChanges) {
            var version_name = $('#version-name').val();
            var version_permalink = $('#version-permalink').val();
            var copy_version = 0;
            var source = '';
            var count = $('#version-count').val();
            if ($("#copy-course-content").is(":checked")) {
                copy_version = 1;
                source = $('#copy-version-from').val();
            }
            
            $.ajax({
                url: version_manager.ajax_url,
                type: 'post',
                data: {
                    'action': 'create_new_course_version',
                    'post_id' : version_manager.course_id,
                    'version_name' : version_name,
                    'version_permalink' : version_permalink,
                    'copy_version' : copy_version,
                    'source' : source,
                    'version-count' : count,
                    
                }, success: function( data ) {
                    var response = JSON.parse(data);
                    if(response.version_updated) {
                        window.location.href = window.location.href;
                    } else {
                        alert('error saving...');
                    }
                }
            });
        }
    });

    //Create new version when clicking button in meta box
    $(document).on('click','#delete-flms-version', function(e) {
        e.preventDefault();
        // Check if there are unsaved changes
        var r = confirm("Are you sure you want to delete this course version?");
        
        if (r == true) {
            var version = $('#delete-flms-version').attr('data-version');
            $.ajax({
                url: version_manager.ajax_url,
                type: 'post',
                data: {
                    'action': 'delete_course_version',
                    'post_id' : version_manager.course_id,
                    'version' : version,
                }, success: function( data ) {
                    var response = JSON.parse(data);
                    if(response.version_updated) {
                        window.location.href = window.location.href;
                    } else {
                        alert('error deleting version...');
                    }
                }
            });
        }
    });

    //copy version of course
    $(document).on('click','#copy-version-content', function(e) {
        e.preventDefault();
        var version = $('#copy-version-content-select').val();
        if(version == -1) {
            alert('Please select a version to copy.');
            return;
        }
        // Check if there are unsaved changes
        var r = confirm("Are you sure you want to copy this course version? This action cannot be undone.");
        
        if (r == true) {
            var active_version = $('#copy-version-content').attr('data-active-version');
            $.ajax({
                url: version_manager.ajax_url,
                type: 'post',
                data: {
                    'action': 'copy_versioned_content',
                    'post_id' : version_manager.course_id,
                    'version' : version,
                    'active_version' : active_version,
                }, success: function( data ) {
                    var response = JSON.parse(data);
                    if(response.version_updated) {
                        window.location.href = window.location.href;
                    } else {
                        alert('error copying version...');
                    }
                }
            });
        }
    });
})( jQuery );