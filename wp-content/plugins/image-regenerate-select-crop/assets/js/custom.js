jQuery.fn.sirsc_center = function (ch) {
	var max_diff = 100;
	var max_padd = 50;
	var max_gap = 5;
	this.css('position', 'fixed');
	var windowH = jQuery(window).height();
	var currentH = this.outerHeight();
	if (currentH >= parseInt(windowH - max_diff)) {
		this.css('max-height', parseInt(windowH - max_diff) + 'px');
		this.css('height', parseInt(windowH - max_diff) + 'px');
		ch.css('max-height', parseInt(windowH - max_diff - max_padd - max_gap) + 'px');
		ch.css('height', parseInt(windowH - max_diff - max_padd - max_gap) + 'px');
		ch.css('overflow', 'scroll');
		ch.css('overflow-x', 'hidden');
		ch.css('z-index', '100');
		this.css('top', Math.ceil(max_diff / 2));
	} else {
		this.css('top', Math.ceil(( jQuery(window).height() - this.outerHeight() - max_padd ) / 2));
	}
	this.css('left', Math.ceil(jQuery(window).width() / 2) - (this.outerWidth() / 2));
	jQuery('body').css('position', '');
	return this;
}

function sirsc_arrange_center_element(el) {
	var ch = jQuery(el + ' .inside');
	jQuery(el).sirsc_center(ch);
	jQuery(window).resize(function() {
		jQuery(el).sirsc_center(ch);
	});
}

function sirsc_send_ajax_post_element(url, callback_action, elem, target) {
	var data = jQuery('#' + elem + ' :input').serializeArray();
	data.push({'name': 'callback', 'value': callback_action});
	var $t = jQuery('#' + target);
	if ($t.size()) {
		if (1 == $t.data('processing') ) {
			return;
		}
		$t.show();
		$t.find('.sirsc-error').remove();
		$t.prepend('<div class="spinner inline"></div>');
		$t.data('processing', 1);
	} else {
		return;
	}
	jQuery.post(ajaxurl, post_data = {
		action: url, // This is required so WordPress knows which func to use
		'sirsc_data': data // Post any variables you want here
	}, function(response) {
		if (response && $t.size()) {
			$t.html(response);
		}
		$t.data('processing', 0);
	}).fail(function(response) {
		$t.find('.sirsc-error').remove();
		$t.find('.spinner.inline').removeClass('inline').addClass('off');
		$t.data('processing', 0);
		if (callback_action == 'sirsc_ajax_process_image_sizes_on_request' && response.statusText ) {
			var text = response.statusText.replace(SIRSC_settings.upload_root_path,'');
			$t.prepend('<span class="sirsc-error">' + text + '</span>');
		}
		if (callback_action == 'sirsc_ajax_regenerate_image_sizes_on_request' && response.responseText) {
			var text = response.responseText.replace(SIRSC_settings.upload_root_path,'');
			$t.html(text + '</center></div></div></div>');
			var n = $t.find('#sirsc-fallback-id').val();
			var s = $t.find('#sirsc-fallback-size').val();
			var a = $t.find('#sirsc-fallback-action').val();
			var f = $t.find('#sirsc-fallback-filename').val();
			f = f.replace(SIRSC_settings.upload_root_path,'');
			$t.find('.inside center').append('<div class="inline-action-wrap">' + text + '<b class="spinner inline"></b></div><div class="file-reswrap error-msg"><div class="sirsc-regen-url"><b class="dashicons dashicons-dismiss"></b> ' + f + '</div></div>');
			jQuery(window).trigger('resize');
			sirsc_continue_regenerate(s,n);
		}
	});
}

function sirsc_show_ajax_action(callback_action, elem, target) {
	sirsc_send_ajax_post_element('sirsc_show_actions_result', callback_action, elem, target);
}
function sirsc_toggle_info(el) {
	jQuery(el).slideToggle();
}
function sirsc_load_post_type(el, url) {
	jQuery('#main_settings_block').hide();
	window.location = url + '&_sirsc_post_types=' + el.value;
}

function sirsc_initiate_regenerate(image_size_name) {
	if (confirm(SIRSC_settings.confirm_regenerate + '\n\n' + SIRSC_settings.time_warning + '\n' + SIRSC_settings.irreversible_operation)) {
		var frmid = '_sirsc_regenerate_initiated_for_' + image_size_name;
		var resid = '_sirsc_regenerate_initiated_for_' + image_size_name + '_result';
		var $resw = jQuery('[id="' + resid + '"]');
		$resw.data('processing', 0);
		$resw.html('');
		sirsc_show_ajax_action('sirsc_ajax_regenerate_image_sizes_on_request', frmid, resid);
		$resw.show();
	}
}
function sirsc_initiate_regenerate_resume(image_size_name) {
	if (confirm(SIRSC_settings.confirm_regenerate + '\n\n' + SIRSC_settings.time_warning + '\n' + SIRSC_settings.irreversible_operation)) {
		var lppid = jQuery('[id="_sirsc_initiate_regenerate_resume_' + image_size_name + '_id"]').val();
		var frmid = '_sirsc_regenerate_initiated_for_' + image_size_name;
		var resid = '_sirsc_regenerate_initiated_for_' + image_size_name + '_result';
		var $wrap = jQuery('[id="' + frmid + '"]');
		var $resw = jQuery('[id="' + resid + '"]');
		$resw.data('processing', 0);
		$resw.html('');
		$wrap.append('<input type="hidden" name="resume_from" id="resume_from' + image_size_name + '" value="' + lppid + '">');
		sirsc_show_ajax_action('sirsc_ajax_regenerate_image_sizes_on_request', frmid, resid);
		$resw.show();
		jQuery('[id="resume_from' + image_size_name + '"]').remove();
	}
}
function sirsc_initiate_cleanup(image_size_name) {
	if (confirm(SIRSC_settings.confirm_cleanup + '\n\n' + SIRSC_settings.time_warning + '\n' + SIRSC_settings.irreversible_operation)) {
		var frmid = '_sirsc_cleanup_initiated_for_' + image_size_name;
		var resid = '_sirsc_cleanup_initiated_for_' + image_size_name + '_result';
		var $resw = jQuery('[id="' + resid + '"]');
		$resw.data('processing', 0);
		$resw.html('');
		sirsc_show_ajax_action('sirsc_ajax_cleanup_image_sizes_on_request', frmid, resid);
		$resw.show();
	}
}
function sirsc_initiate_raw_cleanup(image_size_name) {
	if (confirm(SIRSC_settings.confirm_cleanup + '\n\n' + SIRSC_settings.time_warning + '\n' + SIRSC_settings.irreversible_operation)) {
		var frmid = '_sirsc_cleanup_initiated_for_' + image_size_name;
		var resid = '_sirsc_cleanup_initiated_for_' + image_size_name + '_result';
		var $resw = jQuery('[id="' + resid + '"]');
		$resw.data('processing', 0);
		$resw.html('');
		sirsc_show_ajax_action('sirsc_ajax_raw_cleanup_image_sizes_on_request', frmid, resid);
		$resw.show();
	}
}

function sirsc_continue_regenerate(image_size_name, next) {
	sirsc_arrange_center_element('.sirsc_image-size-selection-box');
	jQuery('#_sisrsc_regenerate_image_size_name_page' + image_size_name).val(next);
	setTimeout( function() {
		sirsc_show_ajax_action('sirsc_ajax_regenerate_image_sizes_on_request', '_sirsc_regenerate_initiated_for_' + image_size_name, '_sirsc_regenerate_initiated_for_' + image_size_name + '_result');
	}, 250);
}
function sirsc_continue_cleanup(image_size_name, next) {
	sirsc_arrange_center_element('.sirsc_image-size-selection-box');
	jQuery('#_sisrsc_image_size_name_page' + image_size_name).val(next);
	setTimeout( function() {
		sirsc_show_ajax_action('sirsc_ajax_cleanup_image_sizes_on_request', '_sirsc_cleanup_initiated_for_' + image_size_name, '_sirsc_cleanup_initiated_for_' + image_size_name + '_result');
	}, 250);
}
function sirsc_continue_raw_cleanup(image_size_name, next) {
	sirsc_arrange_center_element('.sirsc_image-size-selection-box');
	jQuery('#_sisrsc_image_size_name_page' + image_size_name).val(next);
	setTimeout( function() {
		sirsc_show_ajax_action('sirsc_ajax_raw_cleanup_image_sizes_on_request', '_sirsc_cleanup_initiated_for_' + image_size_name, '_sirsc_cleanup_initiated_for_' + image_size_name + '_result');
	}, 250);
}

function sirsc_finish_regenerate(image_size_name) {
	jQuery('[id="_sirsc_initiate_regenerate_resume_' + image_size_name + '"]').addClass('is-hidden');
	jQuery('[id="_sisrsc_regenerate_image_size_name_page' + image_size_name + '"]').val('0');
	var $resw = jQuery('[id="_sirsc_regenerate_initiated_for_' + image_size_name + '_result"]');
	$resw.data('processing', 1);
	$resw.html('');
	$resw.hide();
	jQuery('[id="sirsc-cleanup-button-for-' + image_size_name + '"]').show();
}
function sirsc_finish_cleanup(image_size_name) {
	jQuery('[id="_sisrsc_image_size_name_page' + image_size_name + '"]').val('0');
	var $resw = jQuery('[id="_sirsc_cleanup_initiated_for_' + image_size_name + '_result"]');
	$resw.data('processing', 1);
	$resw.html('');
	$resw.hide();
	jQuery('[id="sirsc-cleanup-button-for-' + image_size_name + '"]').hide();
}
function sirsc_finish_raw_cleanup(image_size_name) {
	jQuery('[id="_sisrsc_image_size_name_page' + image_size_name + '"]').val('0');
	var $resw = jQuery('[id="_sirsc_cleanup_initiated_for_' + image_size_name + '_result"]');
	$resw.data('processing', 1);
	$resw.html('');
	$resw.hide();
}

function sirsc_finish_regenerate_log(image_size_name) {
	setTimeout( function() {
		sirsc_arrange_center_element('.sirsc_image-size-selection-box');
		jQuery('[id="_sirsc_initiate_regenerate_resume_' + image_size_name + '"]').addClass('is-hidden');
		jQuery('[id="_sisrsc_regenerate_image_size_name_page' + image_size_name + '"]').val('0');
		var $resw = jQuery('[id="_sirsc_regenerate_initiated_for_' + image_size_name + '_result"]');
		$resw.data('processing', 1);
		$resw.html('<div class="sirsc_under-image-options"></div><div class="sirsc_image-size-selection-box"><div class="sirsc_options-title"><div class="sirsc_options-close-button-wrap"><a class="sirsc_options-close-button" onclick="sirsc_finish_regenerate(\'' + image_size_name  + '\'); "><span class="dashicons dashicons-dismiss"></span></a></div><h2>' + SIRSC_settings.regenerate_log_title + '</h2></div><div class="inside"><div class="custom-execution-wrap">' + jQuery('#_sirsc_regenerate_initiated_for_' + image_size_name + '_result #sirsc-result-log').val() + '</div></div></div></div>').show();
		sirsc_arrange_center_element('.sirsc_image-size-selection-box');
		sirsc_arrange_center_element('#_sirsc_regenerate_initiated_for_' + image_size_name);
		jQuery('[id="sirsc-cleanup-button-for-' + image_size_name + '"]').show();
		jQuery(window).trigger('resize');
	}, 250);
}
function sirsc_finish_cleanup_log(image_size_name) {
	setTimeout( function() {
		sirsc_arrange_center_element('.sirsc_image-size-selection-box');
		jQuery('[id="_sirsc_initiate_regenerate_resume_' + image_size_name + '"]').addClass('is-hidden');
		jQuery('[id="_sisrsc_image_size_name_page' + image_size_name + '"]').val('0');
		var $resw = jQuery('[id="_sirsc_cleanup_initiated_for_' + image_size_name + '_result"]');
		$resw.data('processing', 1);
		$resw.html('<div class="sirsc_under-image-options"></div><div class="sirsc_image-size-selection-box"><div class="sirsc_options-title"><div class="sirsc_options-close-button-wrap"><a class="sirsc_options-close-button" onclick="sirsc_finish_cleanup(\'' + image_size_name  + '\'); "><span class="dashicons dashicons-dismiss"></span></a></div><h2>' + SIRSC_settings.cleanup_log_title + '</h2></div><div class="inside"><div class="custom-execution-wrap">' + jQuery('#_sirsc_cleanup_initiated_for_' + image_size_name + '_result #sirsc-result-log').val() + '</div></div></div></div>').show();
		sirsc_arrange_center_element('.sirsc_image-size-selection-box');
		sirsc_arrange_center_element('#_sirsc_cleanup_initiated_for_' + image_size_name);
		jQuery('[id="sirsc-cleanup-button-for-' + image_size_name + '"]').hide();
		jQuery(window).trigger('resize');
	}, 250);
}

function sirsc_finish_raw_cleanup_log(image_size_name) {
	setTimeout( function() {
		sirsc_arrange_center_element('.sirsc_image-size-selection-box');
		jQuery('[id="_sirsc_initiate_regenerate_resume_' + image_size_name + '"]').addClass('is-hidden');
		jQuery('[id="_sisrsc_image_size_name_page' + image_size_name + '"]').val('0');
		var $resw = jQuery('[id="_sirsc_cleanup_initiated_for_' + image_size_name + '_result"]');
		$resw.data('processing', 1);
		$resw.html('<div class="sirsc_under-image-options"></div><div class="sirsc_image-size-selection-box"><div class="sirsc_options-title"><div class="sirsc_options-close-button-wrap"><a class="sirsc_options-close-button" onclick="sirsc_finish_cleanup(\'' + image_size_name  + '\'); "><span class="dashicons dashicons-dismiss"></span></a></div><h2>' + SIRSC_settings.cleanup_log_title + '</h2></div><div class="inside"><div class="custom-execution-wrap">' + jQuery('#_sirsc_cleanup_initiated_for_' + image_size_name + '_result #sirsc-result-log').val() + '</div></div></div></div>').show();
		sirsc_arrange_center_element('.sirsc_image-size-selection-box');
		sirsc_arrange_center_element('#_sirsc_cleanup_initiated_for_' + image_size_name);
		jQuery(window).trigger('resize');
	}, 250);
}

function sirsc_spinner_off(id) {
	jQuery('[id="sirsc_recordsArray_' + id + '_result"] spinner').removeClass('off');
}
function sirsc_open_details(id) {
	sirsc_spinner_off(id);
	sirsc_show_ajax_action('ajax_show_available_sizes', 'sirsc_recordsArray_' + id, 'sirsc_recordsArray_' + id + '_result');
}
function sirsc_start_regenerate(id) {
	sirsc_spinner_off(id);
	sirsc_show_ajax_action('sirsc_ajax_process_image_sizes_on_request', 'sirsc_recordsArray_' + id, 'sirsc_recordsArray_' + id + '_result');
}
function sirsc_start_delete(id) {
	sirsc_spinner_off(id);
	sirsc_show_ajax_action('sirsc_ajax_delete_image_sizes_on_request', 'sirsc_recordsArray_' + id, 'sirsc_recordsArray_' + id + '_result');
}
function sirsc_start_raw_cleanup_single(id) {
	sirsc_spinner_off(id);
	if (confirm(SIRSC_settings.confirm_raw_cleanup)) {
		sirsc_show_ajax_action('sirsc_ajax_raw_cleanup_single_on_request', 'sirsc_recordsArray_' + id, 'sirsc_recordsArray_' + id + '_result');
	}
}
function sirsc_maybe_refresh_sirsc_column_summary(id) {
	if (jQuery('.sirsc-column-summary').length) {
		sirsc_show_ajax_action('attachment_files_summary', 'sirsc-column-summary-' + id, 'sirsc-column-summary-' + id);
		sirsc_refresh_extra_info_footer(id)
	}
}
function sirsc_start_delete_file(id,fname,imgs,elid) {
	sirsc_spinner_off(id);
	jQuery('#' + elid).addClass('js-table-row processing');
	jQuery('#frm-sirsc-main-additional-info-wrap-' + id + ' input[name="filename"]').val(fname);
	jQuery('#frm-sirsc-main-additional-info-wrap-' + id + ' input[name="imagesize"]').val(imgs);
	jQuery('#frm-sirsc-main-additional-info-wrap-' + id + ' input[name="elementwrap"]').val(elid);
	sirsc_show_ajax_action('sirsc_ajax_delete_image_file_on_request', 'frm-sirsc-main-additional-info-wrap-' + id, elid + '_rez');
}
function sirsc_refresh_extra_info_footer(id,elid) {
	sirsc_spinner_off(id);
	sirsc_show_ajax_action('compute_all_gen_like_images', 'sirsc-extra-info-footer-' + id, 'sirsc-extra-info-footer-' + id);
}
function sirsc_refresh_attachment_files_summary(id,elid) {
	sirsc_spinner_off(id);
	sirsc_show_ajax_action('attachment_files_summary', elid, elid);
}
function sirsc_crop_position(id) {
	sirsc_spinner_off(id);
	sirsc_show_ajax_action('sirsc_ajax_process_image_sizes_on_request', 'sirsc_recordsArray_' + id, 'sirsc_recordsArray_' + id + '_result');
}
function sirsc_clear_result(id) {
	jQuery('#sirsc_recordsArray_' + id + '_result').html('');
	sirsc_maybe_refresh_sirsc_column_summary(id);
}
function sirsc_thumbnail_details(id, size, src, w, h, crop) {
	if (src != '') {
		jQuery('#idsrc' + id + size + '').html('<img src="' + src + '" /><div class="sirsc_clearAll"></div>' + SIRSC_settings.resolution + ': <b>' + w + '</b>x<b>' + h + '</b>px');
		jQuery('#idsrc' + id + size + '-url').html('<a href="' + src + '" target="_blank"><div class="dashicons dashicons-admin-links"></div></a>');
	} else {
		jQuery('#idsrc' + id + size + '').html('');
		jQuery('#idsrc' + id + size + '-url').html('');
	}
	if (crop != '') {
		jQuery('#sirsc_recordsArray_' + id + size + ' .sirsc_image-action-column').html(crop);
	}
}

function sirsc_autosubmit() {
	var $frm = jQuery('#sirsc_settings_frm');
	$frm.addClass('js-sirsc-general processing');

	var act = $frm.attr('action');
	$frm.remove('#sirsc_inline_custom_frm_action');
	$frm.append('<input type="hidden" name="action" id="sirsc_inline_custom_frm_action" value="sirsc_autosubmit_save">');

	$frm.remove('#sirsc_inline_custom_frm_btn');
	$frm.append('<input type="hidden" name="sirsc-settings-submit" id="sirsc_inline_custom_frm_btn" value="on">');

	jQuery.ajax({
		type: 'POST',
		url: ajaxurl,
		data: $frm.serialize(),
		cache: false,
	}).success(function (response) {
		$frm.removeClass('js-sirsc-general processing');
		$frm.attr('action', act);
	});
}

function sirsc_hookup_block_buttons(thid) {
	jQuery('.sirsc-block-action-details').each(function(e) {
		if (0 == thid) {
			thid = jQuery(this).data('sirsc-id');
		}
		jQuery(this).attr('onclick', 'sirsc_open_details(' + parseInt(thid) + ')');
	});
	jQuery('.sirsc-block-action-regenerate').each(function(e) {
		if (0 == thid) {
			thid = jQuery(this).data('sirsc-id');
		}
		jQuery(this).attr('onclick', 'sirsc_start_regenerate(' + parseInt(thid) + ')');
	});
}

function sirsc_hide_updated_response() {
	jQuery('.sirsc_successfullysaved').hide();
}

function sirsc_hookup_wcprodgall_change($) {
	var thegall_ids = $('#product_image_gallery').val();
	$('body').on('DOMSubtreeModified', '#product_images_container', function() {
		setTimeout(function() {
			if (thegall_ids != $('#product_image_gallery').val()) {
				var $itm = $('#product_images_container').find('ul.product_images > li.image');
				$itm.each(function() {
					var tid = $(this).data('attachment_id');
					var $maybe = $(this).find('.sirsc-image-generate-functionality');
					if (! $maybe.length) {
						$(this).append('<div class="sirsc-image-generate-functionality tiny"><div id="sirsc_recordsArray_' + tid + '"><input type="hidden" name="post_id" id="post_idthumb' + tid + '" value="' + tid + '"><div class="sirsc_button-regenerate"><div id="sirsc_inline_regenerate_sizes' + tid + '"><div class="button-primary button-large" onclick="sirsc_open_details(\'' + tid + '\')"><div class="dashicons dashicons-format-gallery" title="' + SIRSC_settings.button_options + '"></div> ' + SIRSC_settings.button_details + '</div><div class="button-primary button-large" onclick="sirsc_start_regenerate(\'' + tid + '\')"><div class="dashicons dashicons-update" title="' + SIRSC_settings.button_regenerate + '"></div> ' + SIRSC_settings.button_regenerate + '</div><div id="sirsc_recordsArray_' + tid + '_result" class="result"><span class="spinner inline off"></span></div></div></div><div class="sirsc_clearAll"></div> </div></div>');
					}
				});
				// Replace the known list.
				thegall_ids = $('#product_image_gallery').val();
			}
		}, 250);
	});
}

(function($) {
	$(document).ready(function() {
		var $notice = $('.notice.sirsc-plugin-notice');
		var $button = $notice.find('.notice-dismiss');
		$notice.unbind('click');
		$button.unbind('click');
		$notice.on('click', '.notice-dismiss', function(e) {
			$.get( $notice.data('dismissurl') );
		});

		// Listen for execution end.
		setInterval(sirsc_hide_updated_response, 3000);

		// When using WooCommerce product gallery.
		sirsc_hookup_wcprodgall_change($);
	});
})(jQuery);
