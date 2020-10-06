function sirsc_improf_ajax_done($tar) {
	jQuery(document).trigger('js-sirsc-done');
	$tar.removeClass('js-sirsc-adon-improf processing');
}
function sirsc_improf_ajax_continue($tar) {
	jQuery(document).trigger('js-sirsc-done');
}
function sirsc_improf_ajax_start($tar) {
	$tar.addClass('js-sirsc-adon-improf processing');
}

function sirsc_improf_continue_each_folder_assess() {
	var $tar = jQuery('#js-sirsc-compsize');
	sirsc_improf_ajax_start($tar);
	jQuery.post(SIRSC_Adons_Improf.ajaxUrl, {
		action:'sirsc_impro_assess_images_in_folder',
		data:'compute',
	}, function(response) {
		if (response) {
			$tar.html(response);
		}
		sirsc_improf_ajax_continue($tar);
	}).fail(function(response) {
		if (response) {
			$tar.html('error' + response);
		}
		sirsc_improf_ajax_continue($tar);
	}, 'html');
}

function sirsc_improf_refresh_summary() {
	var $tar = jQuery('#js-sirsc-compsize');
	sirsc_improf_ajax_start($tar);
	jQuery.post(SIRSC_Adons_Improf.ajaxUrl, {
		action:'sirsc_reset_assess',
		data:'compute',
	}, function(response) {
		if (response) {
			$tar.html(response);
		}
		sirsc_improf_ajax_done($tar);
	}).fail(function(response) {
		if (response) {
			$tar.html('error' + response);
		}
		sirsc_improf_ajax_done($tar);
	}, 'html');
}

function sirsc_improf_load_list_page(aid,page) {
	var $ele = jQuery('#' + aid);
	var $tar = jQuery('#js-sirsc-adon-improf-list-wrap-target');
	sirsc_improf_ajax_start($tar);
	jQuery.post(SIRSC_Adons_Improf.ajaxUrl, {
		action:'sirsc_impro_load_list_page',
		page:page,
		maxpage:$ele.data('maxpage'),
		sizename:$ele.data('sizename'),
		mimetype:$ele.data('mimetype'),
		valid:$ele.data('valid'),
		aid:aid,
		title:$ele.data('title'),
	}, function(response) {
		if (response) {
			$tar.html(response);
		}
		sirsc_improf_ajax_done($tar);
	}).fail(function(response) {
		if (response) {
			$tar.html('error' + response);
		}
		sirsc_improf_ajax_done($tar);
	}, 'html');
}
function bind_sirsc_adon_improf_listing() {
	jQuery('.js-sirsc-adon-improf-list').unbind('click');
	jQuery('.js-sirsc-adon-improf-list').on('click', function(e) {
		e.preventDefault();
		var aid = jQuery(this).attr('id');
		sirsc_adon_improf_listing(aid,1);
	} );

	jQuery('.js-sirsc-adon-improf-list-pagination').unbind('click');
	jQuery('.js-sirsc-adon-improf-list-pagination').on('click', function(e) {
		e.preventDefault();
		var aid = jQuery(this).data('parentaid');
		var pag = jQuery(this).html();
		sirsc_adon_improf_listing(aid,pag);
	} );
}
function bind_sirsc_adon_improf_listing_pagination() {
	jQuery('.js-sirsc-adon-improf-list-pagination').unbind('click');
	jQuery('.js-sirsc-adon-improf-list-pagination').on('click', function(e) {
		e.preventDefault();
		var aid = jQuery(this).data('parentaid');
		var pag = jQuery(this).html();
		sirsc_adon_improf_listing(aid,pag);
	} );
}
function sirsc_adon_improf_listing(aid,pag) {
	var $tar = jQuery('#js-sirsc-adon-improf-list-wrap');
	if (! $tar.length ) {
		jQuery('#js-sirsc-compsize').append('<br><div id="js-sirsc-adon-improf-list-wrap"><div class="sirsc_folders-info-menu-buttons"><a class="button button-primary">' + SIRSC_Adons_Improf.listBoxTitle + '</a></div><div class="sirsc-adon-folders-info-wrap"><div id="js-sirsc-adon-improf-list-wrap-target"></div></div></div>');
	}
	sirsc_improf_load_list_page(aid,pag);
}

jQuery(document).ready(function() {
	jQuery('#js-sirsc-improf-trigger-assess').on('click', function() {
		sirsc_improf_continue_each_folder_assess();
	});
	jQuery('#js-sirsc-improf-trigger-summary').on('click', function() {
		sirsc_improf_refresh_summary();
	});

	bind_sirsc_adon_improf_listing();
	bind_sirsc_adon_improf_listing_pagination();
	jQuery(document).on('js-sirsc-done', function() {
		bind_sirsc_adon_improf_listing();
		bind_sirsc_adon_improf_listing_pagination();
	});

});
