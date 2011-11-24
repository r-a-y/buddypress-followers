jQuery(document).ready( function() {
	jQuery("a.follow, a.unfollow").live( 'click', function() {
		var link = jQuery(this);
		var type = link.attr('class');
		var uid = link.attr('id');
		var nonce = link.attr('href');
		var action = '';

		// add the loading class for BP 1.2.x only
		if ( BP_DTheme.mention_explain )
			link.addClass('loading');

		uid = uid.split('-');
		action = uid[0];
		uid = uid[1];

		nonce = nonce.split('?_wpnonce=');
		nonce = nonce[1].split('&');
		nonce = nonce[0];

		jQuery.post( ajaxurl, {
			action: 'bp_' + action,
			'cookie': encodeURIComponent(document.cookie),
			'uid': uid,
			'_wpnonce': nonce
		},
		function(response) {
			jQuery(link.parent()).fadeOut(200, function() {
				link.html( response );

				// remove the loading class for BP 1.2.x only
				if ( BP_DTheme.mention_explain )
					link.removeClass('loading');

				link.removeClass('follow');
				link.removeClass('unfollow');
				link.parent().addClass('pending');
				link.addClass('disabled');
				jQuery(this).fadeIn(200);
			});
		});
		return false;
	} );

	jQuery("a.disabled").live( 'click', function() {
		return false;
	});
} );
