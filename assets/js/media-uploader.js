(function( $ ) {
    $( document ).ready( function() {
        if( $( '#flms-upload-media' ).length ) { //checks if the button exists
            var metaImageFrame;
            $( 'body' ).click( function( e ) {
                var btn = e.target;
                if ( !btn || !$( btn ).attr( 'data-media-uploader-target' ) ) return;
                var field = $( btn ).data( 'media-uploader-target' );
                e.preventDefault();
                metaImageFrame = wp.media.frames.metaImageFrame = wp.media( {
                    button: { text:  'Use this file' },
                } );
                metaImageFrame.on( 'select', function() {
                    var media_attachment = metaImageFrame.state().get( 'selection' ).first().toJSON();
                    $( field ).val( media_attachment.url );
                    update_preview_image();
                } );
                metaImageFrame.open();
            } );
        }

        function update_preview_image() {
            if($('#flms-media-uploaded-image').length) {
                var val = $('.flms-update-media-upload-preview-image').val();
                //if(val != current) {
                    if(val != '') {
                        $('#flms-media-uploaded-image').html('<img src="'+val+'" />');
                    } else {
                        $('#flms-media-uploaded-image').html('');
                    }    
                //}
            }
        }
        if($('#addtag').length) {
            $(document).on('change','.flms-update-media-upload-preview-image',function() {
                update_preview_image();
            });
        }
        $('#addtag #submit').click(function () {
            // Look for a div WordPress produces for an invalid form element
            if (!$('#addtag .form-invalid').length) {
                $('#flms-media-uploaded-image').html('');
            }
        });

        

    } );
} )( jQuery );