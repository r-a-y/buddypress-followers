<?php
require ( dirname( __FILE__ ) . '/_inc/bp-follow-templatetags.php' );
require ( dirname( __FILE__ ) . '/_inc/bp-follow-classes.php' );
require ( dirname( __FILE__ ) . '/_inc/bp-follow-hooks.php' );
require ( dirname( __FILE__ ) . '/_inc/bp-follow-widgets.php' );

/**
 * bp_follow_setup_globals()
 *
 * Append the globals this component will use to the $bp global.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @global $wpdb The global WordPress database access object.
 */
function bp_follow_setup_globals() {
	global $bp, $wpdb;

	if ( !defined( 'BP_FOLLOWERS_SLUG' ) )
		define( 'BP_FOLLOWERS_SLUG', 'followers' );

	if ( !defined( 'BP_FOLLOWING_SLUG' ) )
		define( 'BP_FOLLOWING_SLUG', 'following' );

	/* For internal identification */
	$bp->follow->id = 'follow';

	$bp->follow->table_name = $wpdb->base_prefix . 'bp_follow';
	$bp->follow->format_notification_function = 'bp_follow_format_notifications';
	$bp->follow->followers->slug = BP_FOLLOWERS_SLUG;
	$bp->follow->following->slug = BP_FOLLOWING_SLUG;

	/* Register this in the active components array */
	$bp->active_components[$bp->follow->followers->slug] = $bp->follow->id;
}
add_action( 'init', 'bp_follow_setup_globals', 9 );
add_action( 'admin_init', 'bp_follow_setup_globals', 9 );

/**
 * bp_follow_setup_nav()
 *
 * Add the "Following (X)", "Followers (X)" nav elements to user profiles and a "Following" sub
 * nav item to the activity tab on user profiles.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 * @uses bp_core_new_nav_item() Create a new top level navigation tab on user profile pages.
 * @uses bp_core_new_subnav_item() Create a new sub level navigation tab on user profile pages.
 * @uses bp_is_active() Check if a core component is active or not.
 */
function bp_follow_setup_nav() {
	global $bp;

	$counts = bp_follow_total_follow_counts( array( 'user_id' => $bp->displayed_user->id ) );

	if ( !empty( $counts['followers'] ) ) {
		bp_core_new_nav_item( array( 'name' => sprintf( __( 'Followers <span>(%d)</span>', 'buddypress' ), $counts['followers'] ), 'slug' => $bp->follow->followers->slug, 'position' => 60, 'screen_function' => 'bp_follow_screen_my_followers', 'default_subnav_slug' => 'followers', 'item_css_id' => $bp->follow->id ) );
		bp_core_new_subnav_item( array( 'name' => __( 'Followers', 'buddypress' ), 'slug' => 'followers', 'parent_url' =>  $bp->loggedin_user->domain . $bp->follow->followers->slug . '/', 'parent_slug' => $bp->follow->followers->slug, 'screen_function' => 'bp_follow_screen_my_followers', 'position' => 10, 'item_css_id' => 'followers' ) );
	}

	if ( !empty( $counts['following'] ) ) {
		bp_core_new_nav_item( array( 'name' => sprintf( __( 'Following <span>(%d)</span>', 'buddypress' ), $counts['following'] ), 'slug' => $bp->follow->following->slug, 'position' => 61, 'screen_function' => 'bp_follow_screen_following', 'default_subnav_slug' => 'following', 'item_css_id' => 'following' ) );
		bp_core_new_subnav_item( array( 'name' => __( 'Following', 'buddypress' ), 'slug' => 'following', 'parent_url' =>  $bp->loggedin_user->domain . $bp->follow->following->slug . '/', 'parent_slug' => $bp->follow->following->slug, 'screen_function' => 'bp_follow_screen_following', 'position' => 10, 'item_css_id' => 'following' ) );
	}

	/* Add activity sub nav item */
	if ( bp_is_active( 'activity' ) && !empty( $counts['following'] ) ) {
		$user_domain = ( !empty( $bp->displayed_user->domain ) ) ? $bp->displayed_user->domain : $bp->loggedin_user->domain;
		bp_core_new_subnav_item( array( 'name' => __( 'Following', 'buddypress' ), 'slug' => BP_FOLLOWING_SLUG, 'parent_url' => $user_domain . $bp->activity->slug . '/', 'parent_slug' => $bp->activity->slug, 'screen_function' => 'bp_follow_screen_activity_following', 'position' => 21, 'item_css_id' => 'activity-following' ) );
	}

	do_action( 'bp_follow_setup_nav' );
}
add_action( 'init', 'bp_follow_setup_nav' );
add_action( 'admin_init', 'bp_follow_setup_nav' );

/**
 * bp_follow_load_template_filter()
 *
 * Filter the template location so that templates can be stored in the plugin folder, but
 * overridden by templates of the same name and sub folder location in the theme.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_load_template_filter( $found_template, $templates ) {
	global $bp;

	/**
	 * Only filter the template location when we're on the follow component pages.
	 */
	if ( $bp->current_component != $bp->follow->followers->slug && $bp->current_component != $bp->follow->following->slug )
		return $found_template;

	foreach ( (array) $templates as $template ) {
		if ( file_exists( STYLESHEETPATH . '/' . $template ) )
			$filtered_templates[] = STYLESHEETPATH . '/' . $template;
		else
			$filtered_templates[] = dirname( __FILE__ ) . '/_inc/templates/' . $template;
	}

	$found_template = $filtered_templates[0];

	return apply_filters( 'bp_example_load_template_filter', $found_template );
}
add_filter( 'bp_located_template', 'bp_follow_load_template_filter', 10, 2 );

/**
 * bp_follow_add_js()
 *
 * Enqueue the javascript so it is output in the <head> of the page. The JS is used to add AJAX
 * functionality like clicking follow buttons and saving a page refresh.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_add_js() {
	wp_enqueue_script( 'bp-follow-js', str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, dirname( __FILE__ ) . '/_inc/bp-follow.js' ), array( 'jquery' ) );
}
add_action( 'init', 'bp_follow_add_js' );

/********************************************************************************
 * Notification Functions
 */

/**
 * bp_follow_screen_notification_settings()
 *
 * Adds user configurable notification settings for the component.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_screen_notification_settings() {
	global $bp; ?>
	<table class="notification-settings" id="follow-notification-settings">
		<tr>
			<th class="icon"></th>
			<th class="title"><?php _e( 'Followers/Following', 'buddypress' ) ?></th>
			<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
			<th class="no"><?php _e( 'No', 'buddypress' )?></th>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'A member starts following your activity', 'buddypress' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[notification_starts_following]" value="yes" <?php if ( !get_usermeta( $bp->loggedin_user->id,'notification_starts_following') || 'yes' == get_usermeta( $bp->loggedin_user->id,'notification_starts_following') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[notification_starts_following]" value="no" <?php if ( get_usermeta( $bp->loggedin_user->id,'notification_starts_following') == 'no' ) { ?>checked="checked" <?php } ?>/></td>
		</tr>

		<?php do_action( 'bp_follow_screen_notification_settings' ); ?>
	</table>
<?php
}
add_action( 'bp_notification_settings', 'bp_follow_screen_notification_settings' );

/**
 * bp_follow_format_notifications()
 *
 * Format on screen notifications into something readable by users.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_format_notifications( $action, $item_id, $secondary_item_id, $total_items ) {
	global $bp;

	switch ( $action ) {
		case 'new_follow':
			if ( 1 == $total_items )
				return apply_filters( 'bp_follow_new_followers_notification', '<a href="' . $bp->loggedin_user->domain . $bp->follow->followers->slug . '/?new" title="' . __( 'Your list of followers', 'bp-follow' ) . '">' . __( '1 more user is now following you', 'bp-follow' ) . '</a>', $total_items );
			else
				return apply_filters( 'bp_follow_new_followers_notification', '<a href="' . $bp->loggedin_user->domain . $bp->follow->followers->slug . '/?new" title="' . __( 'Your list of followers', 'bp-follow' ) . '">' . sprintf( __( '%d more users are now following you', 'bp-follow' ), $total_items ) . '</a>', $total_items );
		break;
	}

	do_action( 'bp_follow_format_notifications', $action, $item_id, $secondary_item_id, $total_items );

	return false;
}

/**
 * bp_follow_new_follow_email_notification()
 *
 * Send an email to the leader when someone follows them.
 *
 * @package BP-Follow
 * @uses bp_core_get_user_displayname() Get the display name for a user
 * @uses bp_core_get_user_domain() Get the profile url for a user
 * @uses bp_core_get_core_userdata() Get the core userdata for a user without extra usermeta
 * @uses wp_mail() Send an email using the built in WP mail class
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_new_follow_email_notification( $args = '' ) {
	global $bp;

	$defaults = array(
		'leader_id' => $bp->displayed_user->id,
		'follower_id' => $bp->loggedin_user->id
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( 'no' == get_usermeta( (int)$leader_id, 'notification_starts_following' ) )
		return false;

	/* Check to see if this leader has already been notified of this follower before */
	$has_notified = get_usermeta( $follower_id, 'bp_follow_has_notified' );

	if ( in_array( $leader_id, (array)$has_notified ) )
		return false;

	/* Not been notified before, update usermeta and continue to mail */
	$has_notified[] = $leader_id;
	update_usermeta( $follower_id, 'bp_follow_has_notified', $has_notified );

	$follower_name = bp_core_get_user_displayname( $follower_id );
	$follower_link = bp_core_get_user_domain( $follower_id );

	$leader_ud = bp_core_get_core_userdata( $leader_id );
	$settings_link = bp_core_get_user_domain( $leader_id ) . BP_SETTINGS_SLUG . '/notifications/';

	// Set up and send the message
	$to = $leader_ud->user_email;
	$subject = '[' . get_option( 'blogname' ) . '] ' . sprintf( __( '%s is now following you', 'buddypress' ), $follower_name );

	$message = sprintf( __(
'%s is now following your activity.

To view %s\'s profile: %s

---------------------
', 'bp-follow' ), $follower_name, $follower_name, $follower_link );

	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

	/* Send the message */
	$to = apply_filters( 'bp_follow_notification_to', $to );
	$subject = apply_filters( 'bp_follow_notification_subject', $subject, $follower_name );
	$message = apply_filters( 'bp_follow_notification_message', $message, $follower_name, $follower_link );

	wp_mail( $to, $subject, $message );
}


/********************************************************************************
 * Screen Functions
 */

/**
 * bp_follow_screen_my_followers()
 *
 * Catches any visits to the "Followers (X)" tab on a users profile.
 *
 * @package BP-Follow
 * @uses bp_core_load_template() Loads a template file.
 */
function bp_follow_screen_my_followers() {
	global $bp;

	do_action( 'bp_follow_screen_my_followers' );

	if ( isset( $_GET['new'] ) )
		bp_core_delete_notifications_for_user_by_type( $bp->loggedin_user->id, $bp->follow->id, 'new_follow' );

	bp_core_load_template( 'members/single/followers' );
}

/**
 * bp_follow_screen_following()
 *
 * Catches any visits to the "Following (X)" tab on a users profile.
 *
 * @package BP-Follow
 * @uses bp_core_load_template() Loads a template file.
 */
function bp_follow_screen_following() {
	do_action( 'bp_follow_screen_following' );

	bp_core_load_template( 'members/single/following' );
}

function bp_follow_screen_activity_following() {
	do_action( 'bp_activity_screen_my_activity' );
	bp_core_load_template( apply_filters( 'bp_activity_template_my_activity', 'members/single/home' ) );
}


/********************************************************************************
 * Action Functions
 */

/**
 * bp_follow_action_start()
 *
 * Catches clicks on a "Follow User" button and tries to make that happen.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 * @uses bp_follow_start_following() Starts a user following another user.
 * @uses bp_core_add_message() Adds an error/success message to be displayed after redirect.
 * @uses bp_core_redirect() Safe redirects the user to a particular URL.
 * @return bool false
 */
function bp_follow_action_start() {
	global $bp;

	if ( $bp->current_component != $bp->follow->followers->slug || $bp->current_action != 'start' )
		return false;

	if ( $bp->displayed_user->id == $bp->loggedin_user->id )
		return false;

	check_admin_referer( 'start_following' );

	if ( bp_follow_is_following( array( 'leader_id' => $bp->displayed_user->id, 'follower_id' => $bp->loggedin_user->id ) ) )
		bp_core_add_message( sprintf( __( 'You are already following %s.', 'buddypress' ), $bp->displayed_user->fullname ), 'error' );
	else {
		if ( !bp_follow_start_following( array( 'leader_id' => $bp->displayed_user->id, 'follower_id' => $bp->loggedin_user->id ) ) )
			bp_core_add_message( sprintf( __( 'There was a problem when trying to follow %s, please try again.', 'buddypress' ), $bp->displayed_user->fullname ), 'error' );
		else
			bp_core_add_message( sprintf( __( 'You are now following %s.', 'buddypress' ), $bp->displayed_user->fullname ) );
	}

	bp_core_redirect( wp_get_referer() );

	return false;
}
add_action( 'wp', 'bp_follow_action_start', 3 );

/**
 * bp_follow_action_stop()
 *
 * Catches clicks on a "Stop Following User" button and tries to make that happen.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 * @uses bp_follow_stop_following() Stops a user following another user.
 * @uses bp_core_add_message() Adds an error/success message to be displayed after redirect.
 * @uses bp_core_redirect() Safe redirects the user to a particular URL.
 * @return bool false
 */
function bp_follow_action_stop() {
	global $bp;

	if ( $bp->current_component != $bp->follow->followers->slug || $bp->current_action != 'stop' )
		return false;

	if ( $bp->displayed_user->id == $bp->loggedin_user->id )
		return false;

	check_admin_referer( 'stop_following' );

	if ( !bp_follow_is_following( array( 'leader_id' => $bp->displayed_user->id, 'follower_id' => $bp->loggedin_user->id ) ) )
		bp_core_add_message( sprintf( __( 'You are not following %s.', 'buddypress' ), $bp->displayed_user->fullname ), 'error' );
	else {
		if ( !bp_follow_stop_following( array( 'leader_id' => $bp->displayed_user->id, 'follower_id' => $bp->loggedin_user->id ) ) )
			bp_core_add_message( sprintf( __( 'There was a problem when trying to stop following %s, please try again.', 'buddypress' ), $bp->displayed_user->fullname ), 'error' );
		else
			bp_core_add_message( sprintf( __( 'You are no longer following %s.', 'buddypress' ), $bp->displayed_user->fullname ) );
	}

	bp_core_redirect( wp_get_referer() );

	return false;
}
add_action( 'wp', 'bp_follow_action_stop', 3 );

/**
 * bp_follow_ajax_action_start()
 *
 * Allow a user to start following another user by catching an AJAX request.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 * @uses bp_follow_start_following() Starts a user following another user.
 * @return bool false
 */
function bp_follow_ajax_action_start() {
	global $bp;

	check_admin_referer( 'start_following' );

	if ( bp_follow_is_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => $bp->loggedin_user->id ) ) )
		$message = __( 'Already following', 'buddypress' );
	else {
		if ( !bp_follow_start_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => $bp->loggedin_user->id ) ) )
			$message = __( 'Error following user', 'buddypress' );
		else
			$message = __( 'You are now following', 'buddypress' );
	}

	echo $message;
}
add_action( 'wp_ajax_bp_follow', 'bp_follow_ajax_action_start' );

/**
 * bp_follow_ajax_action_stop()
 *
 * Allow a user to stop following another user by catching an AJAX request.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 * @uses bp_follow_stop_following() Stops a user following another user.
 * @return bool false
 */
function bp_follow_ajax_action_stop() {
	global $bp;

	check_admin_referer( 'stop_following' );

	if ( !bp_follow_is_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => $bp->loggedin_user->id ) ) )
		$message = __( 'Not following', 'buddypress' );
	else {
		if ( !bp_follow_stop_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => $bp->loggedin_user->id ) ) )
			$message = __( 'Error unfollowing user', 'buddypress' );
		else
			$message = __( 'Stopped following', 'buddypress' );
	}

	echo $message;
}
add_action( 'wp_ajax_bp_unfollow', 'bp_follow_ajax_action_stop' );


/********************************************************************************
 * Business Functions
 */

/**
 * bp_follow_start_following()
 *
 * Start following a user's activity
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/leader_id - user ID of user to follow
 * @param $args/follower_id - user ID of the user who follows
 * @return bool
 */
function bp_follow_start_following( $args = '' ) {
	global $bp;

	$defaults = array(
		'leader_id' => $bp->displayed_user->id,
		'follower_id' => $bp->loggedin_user->id
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$follow = new BP_Follow;
	$follow->leader_id = (int)$leader_id;
	$follow->follower_id = (int)$follower_id;

	if ( !$follow->save() )
		return false;

	/* Add a screen count notification */
	bp_core_add_notification( $follower_id, $leader_id, $bp->follow->id, 'new_follow' );

	/* Add a more specific email notification */
	bp_follow_new_follow_email_notification( array( 'leader_id' => $leader_id, 'follower_id' => $follower_id ) );

	do_action( 'bp_follow_start_following', &$follow );

	return true;
}

/**
 * bp_follow_stop_following()
 *
 * Stop following a user's activity
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/leader_id - user ID of user to stop following
 * @param $args/follower_id - user ID of the user who wants to stop following
 * @return bool
 */
function bp_follow_stop_following( $args = '' ) {
	global $bp;

	$defaults = array(
		'leader_id' => $bp->displayed_user->id,
		'follower_id' => $bp->loggedin_user->id
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$follow = new BP_Follow( $leader_id, $follower_id );

	if ( !$follow->delete() )
		return false;

	do_action( 'bp_follow_stop_following', &$follow );

	return true;
}

/**
 * bp_follow_is_following()
 *
 * Check if a user is already following another user.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/leader_id - user ID of user to check is being followed
 * @param $args/follower_id - user ID of the user who is doing the following
 * @return bool
 */
function bp_follow_is_following( $args = '' ) {
	global $bp;

	$defaults = array(
		'leader_id' => $bp->displayed_user->id,
		'follower_id' => $bp->loggedin_user->id
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$follow = new BP_Follow( $leader_id, $follower_id );
	return apply_filters( 'bp_follow_is_following', (int)$follow->id, &$follow );
}

/**
 * bp_follow_get_followers()
 *
 * Fetch the user_ids of all the followers of a particular user.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/user_id - the user ID of the user to get followers for.
 * @return array of user ids
 */
function bp_follow_get_followers( $args = '' ) {
	global $bp;

	$defaults = array(
		'user_id' => $bp->displayed_user->id
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return apply_filters( 'bp_follow_get_followers', BP_Follow::get_followers( $user_id ) );
}

/**
 * bp_follow_get_following()
 *
 * Fetch the user_ids of all the users a particular user is following.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/user_id - the user ID of the user to get a list of users followed for.
 * @return array of user ids
 */
function bp_follow_get_following( $args = '' ) {
	global $bp;

	$defaults = array(
		'user_id' => $bp->displayed_user->id
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return apply_filters( 'bp_follow_get_following', BP_Follow::get_following( $user_id ) );
}

/**
 * bp_follow_total_follow_counts()
 *
 * Get the total followers and total following counts for a user.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/user_id - the user ID of the user to get counts for.
 * @return array [ followers => int, following => int ]
 */
function bp_follow_total_follow_counts( $args = '' ) {
	global $bp;

	$defaults = array(
		'user_id' => $bp->loggedin_user->id
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return apply_filters( 'bp_follow_total_follow_counts', BP_Follow::get_counts( $user_id ) );
}


?>