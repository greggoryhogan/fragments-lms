( function( wp ) {
  const content = admin_content.content;
  wp.data.dispatch('core/notices').createNotice(
      'success', // Can be one of: success, info, warning, error.
      content,
      {
          isDismissible: false, // Whether the user can dismiss the notice.
          // Any actions the user can perform.
      }
  );
  } )( window.wp );
