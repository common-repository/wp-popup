function sizeReady() {
	jQuery(wppopup.messagebox).width(jQuery('#message').width());
	jQuery(wppopup.messagebox).height(jQuery('#message').height());

	jQuery(wppopup.messagebox).css('top', (jQuery(window).height() / 2) - (jQuery('#message').height() / 2) );
	jQuery(wppopup.messagebox).css('left', (jQuery(window).width() / 2) - (jQuery('#message').width() / 2) );
}

jQuery(window).load(sizeReady);