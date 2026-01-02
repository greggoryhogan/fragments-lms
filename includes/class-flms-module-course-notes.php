<?php

class FLMS_Module_Course_Notes {

    public $class_name = 'FLMS_Module_Course_Notes';

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}
    
    public function add_meta_box( $post_type ) {
		// Limit meta box to certain post types.
		$post_types = array( 'flms-courses' );

		if ( in_array( $post_type, $post_types ) ) {
			add_meta_box(
				'flms_course_notes',
				__( 'Course Notes', 'flms' ),
				array( $this, 'course_notes_metabox_content' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Save the meta when the post is saved. This is triggered from FLMS_Course_Manager()->save_course_content();
	 */
	public function update_course_notes( $post_id, $active_version, $data ) {

		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $data['flms_course_notes_nonce'] ) ) {
			return $post_id;
		}

		$nonce = $_POST['flms_course_notes_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'flms_course_notes_metabox' ) ) {
			return $post_id;
		}

		/*
		 * If this is an autosave, our form has not been submitted,
		 * so we don't want to do anything.
		 */
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return $post_id;
		}

		/* OK, it's safe for us to save the data now. */

		// Sanitize the user input.
		$course_notes = sanitize_textarea_field( $data['flms_course_notes'] );

		// Update the meta field.
		update_post_meta( $post_id, 'flms_course_notes', $course_notes );

        $course_version_notes = sanitize_textarea_field( $data['flms_course_version_notes'] );
        $course_versioned_content = get_post_meta($post_id,'flms_version_content',true);
		if(!is_array($course_versioned_content)) {
			$course_versioned_content = array();
		}
		$course_versioned_content["{$active_version}"]['course_notes'] = $course_version_notes;
		update_post_meta($post_id,'flms_version_content',$course_versioned_content);

	}

	public function course_notes_metabox_content( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'flms_course_notes_metabox', 'flms_course_notes_nonce' );

        $course = new FLMS_Course($post->ID);
        global $flms_active_version, $flms_course_version_content;
        //echo '<pre>'.print_r($flms_course_version_content,true).'</pre>';
		// Use get_post_meta to retrieve an existing value from the database.
		$course_notes = get_post_meta( $post->ID, 'flms_course_notes', true );
        
		// Display the form, using the current value.
		?>
        <label for="course_notes"><?php echo get_the_title(); ?> Global Notes</label>
        <p class="description">Notes here will display for any version of the course you are editing</p>
		<textarea area-label="Course Notes" id="course_notes" name="flms_course_notes" class="full-width-input" rows="6"><?php echo nl2br( $course_notes ); ?></textarea>

        <div class="flms-spacer"></div>

        <?php $course_version_notes = '';
        if(isset($flms_course_version_content[$flms_active_version]['course_notes'])) {
            $course_version_notes = $flms_course_version_content[$flms_active_version]['course_notes'];
        } ?>
        <label for="course_notes"><?php echo $course->get_course_version_name($flms_active_version); ?> Notes</label>
        <p class="description">Notes here will display for the specific version of the course you are editing</p>
        <textarea area-label="Course Version Notes" id="course_version_notes" name="flms_course_version_notes" class="full-width-input" rows="6"><?php echo nl2br( $course_version_notes ); ?></textarea>
		<?php
	}

}
new FLMS_Module_Course_Notes();