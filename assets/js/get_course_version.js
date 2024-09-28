//wp.domReady(() => {
  fetch('/wp-json/flms/v1/get-course-version/'+get_course_version.course_id+'/'+get_course_version.version)
  .then(response => response.json())
  .then(data => {
    const testFieldMeta = data.versioned_content;
    wp.data.dispatch('core/editor').editPost({ content: testFieldMeta });
    
    // Check if there are unsaved changes
    const unsavedChanges = wp.data.select('core/editor').isEditedPostDirty();

    if (unsavedChanges) {
        wp.data.dispatch('core/editor').savePost();
    } else {
        console.log('There are no unsaved changes.');
    }
  });
//});