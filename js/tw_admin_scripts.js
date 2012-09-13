jQuery(document).ready(function() {
	console.log('hello Twitter Wings');
	
	// CACHE
	jQuery('p.cache-time').hide();

	if (jQuery('p.cache input').prop("checked")) {
		jQuery('p.cache-time').show();
	}
	
	jQuery('p.cache input').change(function() {
		jQuery('p.cache-time').toggle('fast');
	});
	
	// HASHTAG FILTER
	jQuery('p.hash-terms').hide();

	if (jQuery('p.hashtag input').prop("checked")) {
		jQuery('p.hash-terms').show();
	}
	
	jQuery('p.hashtag input').change(function() {
		if (jQuery('p.hashtag input').prop("checked") == false) {
			jQuery('p.hash-terms input').val('');
		}
		jQuery('p.hash-terms').toggle('fast');
	});
	
	// DISPLAY NAME
	jQuery('p.display-name').hide();

	if (jQuery('p.username input').prop("checked")) {
		jQuery('p.display-name').show();
	}
	
	jQuery('p.username input').change(function() {
		jQuery('p.display-name').toggle('fast');
	});
	
	// FOLLOW BUTTON
	jQuery('div.follow-block').hide();

	if (jQuery('p.add-follow input').prop("checked")) {
		jQuery('div.follow-block').show();
	}
	
	jQuery('p.add-follow input').change(function() {
		jQuery('div.follow-block').toggle('fast');
	});
	
	jQuery('.tw-next').bind('click', function() {
	
		var fullWidth = 0;
		jQuery('.nav-tabs-wrapper div a').each(function() {
			fullWidth += jQuery(this).outerWidth() + 4;
		});
		var queMargin = jQuery('.nav-tabs-wrapper div').css('margin-left').replace("px", "") - 150;
		
		if (fullWidth - (-1 * queMargin) < 250) {
			return false;
		} else {
			queMargin = queMargin + 'px';
		}
		
		jQuery('.nav-tabs-wrapper div').animate({
			marginLeft: queMargin 
		}, 500);
		return false;
	});
	
	jQuery('.tw-prev').bind('click', function() {
		jQuery('.nav-tabs-wrapper div').animate({
			marginLeft: "0"
		}, 500);
		return false;
	});

}); /* End Document Ready */