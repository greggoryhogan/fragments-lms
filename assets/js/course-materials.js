(function($) {

    $(document).on('click','.add-course-material-media', function(e) {
        var parent = $(this).parent();
        e.preventDefault();
        var image_frame;
        if(image_frame){
            image_frame.open();
        }
        // Define image_frame as wp.media object
        image_frame = wp.media({
            title: 'Select Media',
            multiple : false,
            /*library : {
                type : 'image',
            }*/
        });

        image_frame.on('close',function() {
            var selection = image_frame.state().get('selection');
            if(selection.length === 0) {
                console.log('bh');
                return true;
            } else {
                selection.each(function(attachment) {
                    if(attachment.id) {
                        var url = wp.media.attachment(attachment.id).get("url");
                        var $this = parent.find('.course-material-media-url');
                        $this.val(url);
                        update_material_data($this, 'file', url);
                        return true;
                    }
                });
            }
        });
        //open the media selector
        image_frame.open();
    });

    //flms_course_materials
    $(document).on('click','#insert-course-material', function(e) {
        e.preventDefault();
        var count = $('#course-materials-list .course-material-item-container').length;
        var title = $('#course-materials-form .material-title').val();
        var status = $('#course-materials-form .material-availability').find('option:selected').val();
        var file_path = $('#course-materials-form .course-material-media-url').val();
        $.ajax({
            url: flms_course_materials.ajax_url,
            type: 'post',
            data: {
                action: 'insert_course_material',
                count : count,
                title : title,
                status : status,
                file : file_path
            },
            success: function(data) {
                $('#course-materials-list').append(data.listing);
                $('#course-materials-form .material-title').val('');
                $('#course-materials-form .material-availability').val('any');
                $('#course-materials-form .course-material-media-url').val('');
            },
        });
    });

    $(document).on('input','#course-materials-list input', function() {
        var $this = $(this);
        var val = $(this).val();
        var field = $(this).attr('data-field');
        update_material_data($this, field, val);
    });
    $(document).on('change','#course-materials-list select', function() {
        var $this = $(this);
        var val = $(this).find('option:selected').val();
        var field = $(this).attr('data-field');
        update_material_data($this, field, val);
    });

    function update_material_data($this, field, val) {
        var item = $this.closest('.course-material-item-container').find('input[data-field="'+field+'"]').val(val);
    }

    $( '#course-materials-list' ).sortable({
        items: ".course-material-item-container",
        forcePlaceholderSize: true,
        placeholder: "ui-sortable-placeholder",
        handle: '.flms-handle',
    });

    $(document).on('click', '.course-material-item-container .flms-remove', function() {
        var question = $(this).closest('.course-material-item-container');
        question.toggleClass('to-be-removed');
        if(question.hasClass('to-be-removed')) {
            question.find('input[type="hidden"]').each(function() {
                var name = $(this).attr('name').replace('flms_course_materials','deselected_flms_course_materials');
                ///flms_course_materials[
                $(this).attr('name',name);
            });
        } else {
            question.find('input[type="hidden"]').each(function() {
                var name = $(this).attr('name').replace('deselected_flms_course_materials','flms_course_materials');
                ///flms_course_materials[
                $(this).attr('name',name);
            });
        }
    });

})( jQuery );