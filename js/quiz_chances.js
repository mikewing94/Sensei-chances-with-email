jQuery(document).ready(function($) {
  
  jQuery('#quiz_chances_taken_table tbody').on('click', 'input.quiz_chances_taken_reset_btn', function() {
    var $obj  = $(this);
    var data = {
      'action': 'reset_quiz_chances_taken',
      'user_id': ajax_object.user_id,      // We pass php values differently!
      'post_ids': [$obj.siblings('input.quiz_id').val()],
    };
    //console.log(jQuery(this).siblings('span'));
    jQuery.post(ajax_object.ajax_url, data, function(response) {
      $obj.siblings('span').html(response);
    });
  });
  /*
	// We can also pass the url value separately from ajaxurl for front end AJAX implementations
	jQuery.post(ajax_object.ajax_url, data, function(response) {
		alert('Got this from the server: ' + response);
	});
  */
});