function sirsc_imgseo_process_bulk() {
	jQuery('.js-sirsc_imgseo_processing_title').html(SIRSC_IMGSEO.isProcessing);
	var $frm = jQuery('#sirsc_imgseo-images-process-frm');
	var $tar = jQuery('#sirsc_imgseo-images-process-wrap');
	$tar.addClass('processing');
	var data = $frm.serialize();
	jQuery.post(SIRSC_IMGSEO.ajaxUrl, data,  function(response) {
		if (response) {
			$tar.html(response);
		}
		$tar.removeClass('processing');
		jQuery('html, body').animate({ scrollTop: jQuery('#bulk-rename-result').offset().top - 42 }, 500);
	}).fail(function(response) {
		if (response) {
			$tar.html('error' + response);
		}
		$tar.removeClass('processing');
		jQuery('html, body').animate({ scrollTop: jQuery('#bulk-rename-result').offset().top - 42 }, 500);
	}, 'html');
}

function sirsc_imgseo_process_bulk_continue(id,proc) {
	jQuery('#sirsc_imgseo-images-process-frm #sirsc_imgseo_last_id').val(id);
	jQuery('#sirsc_imgseo-images-process-frm #sirsc_imgseo_processed').val(proc);
	setTimeout(function() {
		sirsc_imgseo_process_bulk();
	}, 500);
}
function sirsc_imgseo_process_bulk_stop() {
	jQuery('.js-sirsc_imgseo_stop_bulk').hide();
	jQuery('.js-sirsc_imgseo_processing_title').html(SIRSC_IMGSEO.allDone);
	jQuery('#sirsc_imgseo-images-process-wrap').html('<div class="sirsc_imgseo-action-wrap">' + SIRSC_IMGSEO.allProcessed + '</div>');
}
