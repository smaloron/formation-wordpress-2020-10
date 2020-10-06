/* global RegenThumbs */
jQuery(document).ready(function($) {
	var handle 	= $('#post-regen-thumbs'),
		data 	= {
		post_id : handle.data('post_id'),
		action  : 'regen_thumbs',
		nonce 	: RegenThumbs.nonce
	};

	handle.on('click', function(e) {
		e.preventDefault();

		handle.attr('disabled', 'disabled');

		$.ajax({
			url: 	RegenThumbs.ajax_url,
			data: 	data,
			type: 	'POST',
			success: function() {
				handle.removeAttr('disabled');
			},
			error: function (jqXHR, textStatus) {
				handle.removeAttr('disabled');
				RegenThumbs.debug && window.console.log(textStatus);
			}
		});
	});
});