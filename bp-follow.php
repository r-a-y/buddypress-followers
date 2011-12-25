<?php
/**
 * BP Follow Core
 *
 * @package BP-Follow
 * @subpackage Core
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( version_compare( BP_VERSION, '1.3' ) < 0 )
	require ( dirname( __FILE__ ) . '/_inc/bp-follow-backpat.php' );

require ( dirname( __FILE__ ) . '/_inc/bp-follow-templatetags.php' );
require ( dirname( __FILE__ ) . '/_inc/bp-follow-classes.php' );
require ( dirname( __FILE__ ) . '/_inc/bp-follow-hooks.php' );
require ( dirname( __FILE__ ) . '/_inc/bp-follow-widgets.php' );

/**
 * Append the globals this component will use to the $bp global.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @global $wpdb The global WordPress database access object.
 */
function bp_follow_setup_globals() {
	global $bp, $wpdb;

	if ( !defined( 'BP_FOLLOWERS_SLUG' ) )
		define( 'BP_FOLLOWERS_SLUG', 'followers' );

	if ( !defined( 'BP_FOLLOWING_SLUG' ) )
		define( 'BP_FOLLOWING_SLUG', 'following' );

	// For internal identification
	$bp->follow->id              = 'follow';

	$bp->follow->table_name      = $bp->table_prefix . 'bp_follow';
	$bp->follow->followers->slug = BP_FOLLOWERS_SLUG;
	$bp->follow->following->slug = BP_FOLLOWING_SLUG;

	/* Register this in the active components array */
	$bp->active_components[$bp->follow->id] = $bp->follow->id;

	// BP 1.2.x only
	if ( version_compare( BP_VERSION, '1.3' ) < 0 ) {
		$bp->follow->format_notification_function = 'bp_follow_format_notifications';
	}
	// BP 1.5-specific
	else {
		$bp->follow->notification_callback        = 'bp_follow_format_notifications';
	}
}
add_action( 'bp_setup_globals', 'bp_follow_setup_globals' );

/**
 * Add the "Following (X)", "Followers (X)" nav elements to user profiles and a "Following" sub
 * nav item to the activity tab on user profiles.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 * @uses bp_core_new_nav_item() Create a new top level navigation tab on user profile pages.
 * @uses bp_core_new_subnav_item() Create a new sub level navigation tab on user profile pages.
 * @uses bp_is_active() Check if a core component is active or not.
 */
function bp_follow_setup_nav() {
	global $bp;

	// Need to change the user ID, so if we're not on a member page, $counts variable is still calculated
	$user_id = bp_is_user() ? bp_displayed_user_id() : bp_loggedin_user_id();
	$counts  = bp_follow_total_follow_counts( array( 'user_id' => $user_id ) );

	bp_core_new_nav_item( array(
		'name'                => sprintf( __( 'Following <span>%d</span>', 'bp-follow' ), $counts['following'] ),
		'slug'                => $bp->follow->following->slug,
		'position'            => apply_filters( 'bp_follow_following_nav_position', 61 ),
		'screen_function'     => 'bp_follow_screen_following',
		'default_subnav_slug' => 'following',
		'item_css_id'         => 'following'
	) );

	bp_core_new_subnav_item( array(
		'name'                => __( 'Following', 'bp-follow' ),
		'slug'                => 'following',
		'parent_url'          => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug ),
		'parent_slug'         => $bp->follow->following->slug,
		'screen_function'     => 'bp_follow_screen_following',
		'position'            => 10,
		'item_css_id'         => 'following'
	) );

	bp_core_new_nav_item( array(
		'name'                => sprintf( __( 'Followers <span>%d</span>', 'bp-follow' ), $counts['followers'] ),
		'slug'                => $bp->follow->followers->slug,
		'position'            => apply_filters( 'bp_follow_followers_nav_position', 62 ),
		'screen_function'     => 'bp_follow_screen_followers',
		'default_subnav_slug' => 'followers',
		'item_css_id'         => 'followers'
	) );

	bp_core_new_subnav_item( array(
		'name'                => __( 'Followers', 'bp-follow' ),
		'slug'                => 'followers',
		'parent_url'          => trailingslashit( bp_loggedin_user_domain() . $bp->follow->followers->slug ),
		'parent_slug'         => $bp->follow->followers->slug,
		'screen_function'     => 'bp_follow_screen_followers',
		'position'            => 10,
		'item_css_id'         => 'followers'
	) );

	// Add activity sub nav item
	if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_show_activity_subnav', true ) ) {

		// Need to change the user domain, so if we're not on a member page,
		// the BuddyBar renders the activity subnav properly
		$user_domain = bp_is_user() ? bp_displayed_user_domain() : bp_loggedin_user_domain();

		bp_core_new_subnav_item( array(
			'name'            => __( 'Following', 'bp-follow' ),
			'slug'            => BP_FOLLOWING_SLUG,
			'parent_url'      => trailingslashit( $user_domain . $bp->activity->slug ),
			'parent_slug'     => $bp->activity->slug,
			'screen_function' => 'bp_follow_screen_activity_following',
			'position'        => 21,
			'item_css_id'     => 'activity-following'
		) );
	}

	do_action( 'bp_follow_setup_nav' );
}
add_action( 'bp_setup_nav', 'bp_follow_setup_nav' );

/**
 * Groups follow nav items together in the BuddyBar.
 *
 * Because of the way BuddyPress renders both the BuddyBar and profile nav with the same code,
 * to alter just the BuddyBar, you need to resort to hacking the $bp global later on.
 *
 * This will probably break in future versions of BP, but for now, this will have to do.
 * If you're using the WP Admin Bar, you don't have to worry about this at all!
 *
 * @global object $bp BuddyPress global settings
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 * @since 1.1
 * @author r-a-y
 */
function bp_follow_group_buddybar_items() {
	global $bp;

	// don't do this if we're using the WP Admin Bar / Toolbar
	if ( defined( 'BP_USE_WP_ADMIN_BAR' ) && BP_USE_WP_ADMIN_BAR )
		return;

	if ( !bp_loggedin_user_id() )
		return;

	// get follow nav positions
	$following_position = apply_filters( 'bp_follow_following_nav_position', 61 );
	$followers_position = apply_filters( 'bp_follow_followers_nav_position', 62 );

	// clobberin' time!
	unset( $bp->bp_nav[$following_position] );
	unset( $bp->bp_nav[$followers_position] );
	unset( $bp->bp_options_nav['following'] );
	unset( $bp->bp_options_nav['followers'] );

	// Add the "Follow" nav menu
	$bp->bp_nav[$following_position] = array(
		'name'                    => __( 'Follow', 'bp-follow' ),
		'link'                    => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug ),
		'slug'                    => 'follow',
		'css_id'                  => 'follow',
		'position'                => $following_position,
		'show_for_displayed_user' => 1,
		'screen_function'         => 'bp_follow_screen_followers'
	);

	// "Following" subnav item
	$bp->bp_options_nav['follow'][10] = array(
		'name'            => __( 'Following', 'bp-follow' ),
		'link'            => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug ),
		'slug'            => $bp->follow->following->slug,
		'css_id'          => 'following',
		'position'        => 10,
		'user_has_access' => 1,
		'screen_function' => 'bp_follow_screen_followers'
	);

	// "Followers" subnav item
	$bp->bp_options_nav['follow'][20] = array(
		'name'            => __( 'Followers', 'bp-follow' ),
		'link'            => trailingslashit( bp_loggedin_user_domain() . $bp->follow->followers->slug ),
		'slug'            => $bp->follow->followers->slug,
		'css_id'          => 'followers',
		'position'        => 20,
		'user_has_access' => 1,
		'screen_function' => 'bp_follow_screen_followers'
	);

	// Resort the nav items to account for the late change made above
	ksort( $bp->bp_nav );
}
add_action( 'bp_adminbar_menus', 'bp_follow_group_buddybar_items', 3 );

/**
 * Add WP Admin Bar support
 *
 * @global object $bp BuddyPress global settings
 * @global object $wp_admin_bar WP Admin Bar object
 * @since 1.1
 * @author r-a-y
 */
function bp_follow_setup_admin_bar() {
	global $bp, $wp_admin_bar;

	// Prevent debug notices
	$wp_admin_nav = array();

	// Menus for logged in user
	if ( is_user_logged_in() ) {

		// "Follow" parent nav menu
		$wp_admin_nav[] = array(
			'parent' => $bp->my_account_menu_id,
			'id'     => 'my-account-' . $bp->follow->id,
			'title'  => __( 'Follow', 'bp-follow' ),
			'href'   => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug )
		);

		// "Following" subnav item
		$wp_admin_nav[] = array(
			'parent' => 'my-account-' . $bp->follow->id,
			'id'     => 'my-account-' . $bp->follow->id . '-following',
			'title'  => __( 'Following', 'bp-follow' ),
			'href'   => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug )
		);

		// "Followers" subnav item
		$wp_admin_nav[] = array(
			'parent' => 'my-account-' . $bp->follow->id,
			'id'     => 'my-account-' . $bp->follow->id . '-followers',
			'title'  => __( 'Followers', 'bp-follow' ),
			'href'   => trailingslashit( bp_loggedin_user_domain() . $bp->follow->followers->slug )
		);

		// "Activity > Following" subnav item
		if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_show_activity_subnav', true ) ) {
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . BP_ACTIVITY_SLUG,
				'id'     => 'my-account-' . BP_ACTIVITY_SLUG . '-following',
				'title'  => __( 'Following', 'bp-follow' ),
				'href'   => trailingslashit( bp_loggedin_user_domain() . BP_ACTIVITY_SLUG . '/' . $bp->follow->following->slug )
			);
		}

		foreach( $wp_admin_nav as $admin_menu )
			$wp_admin_bar->add_menu( $admin_menu );

	}
}
add_action( 'bp_setup_admin_bar', 'bp_follow_setup_admin_bar' );

/**
 * Filter the template location so that templates can be stored in the plugin folder, but
 * overridden by templates of the same name and sub folder location in the theme.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_load_template_filter( $found_template, $templates ) {
	global $bp;

	/**
	 * Only filter the template location when we're on the follow component pages.
	 */
	if ( !bp_is_current_component( $bp->follow->followers->slug ) && !bp_is_current_component( $bp->follow->following->slug ) )
		return $found_template;

	foreach ( (array) $templates as $template ) {
		if ( file_exists( STYLESHEETPATH . '/' . $template ) )
			$filtered_templates[] = STYLESHEETPATH . '/' . $template;
		elseif ( is_child_theme() && file_exists( TEMPLATEPATH . '/' . $template ) )
			$filtered_templates[] = TEMPLATEPATH . '/' . $template;
		else
			$filtered_templates[] = dirname( __FILE__ ) . '/_inc/templates/' . $template;
	}

	$found_template = $filtered_templates[0];

	return apply_filters( 'bp_follow_load_template_filter', $found_template );
}
add_filter( 'bp_located_template', 'bp_follow_load_template_filter', 10, 2 );

/**
 * Enqueues the javascript.
 *
 * The JS is used to add AJAX functionality like clicking follow buttons and saving a page refresh.
 */
function bp_follow_add_js() {
	wp_enqueue_script( 'bp-follow-js', plugin_dir_url( __FILE__ ) . '_inc/bp-follow.js', array( 'jquery' ) );
}
add_action( 'wp_enqueue_scripts', 'bp_follow_add_js', 11 );

/********************************************************************************
 * Notification Functions
 */

/**
 * Adds user configurable notification settings for the component.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_screen_notification_settings() {
	if ( !$notify = bp_get_user_meta( bp_displayed_user_id(), 'notification_starts_following', true ) )
		$notify = 'yes';
?>

	<table class="notification-settings" id="follow-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php _e( 'Followers/Following', 'bp-follow' ) ?></th>
				<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
				<th class="no"><?php _e( 'No', 'buddypress' )?></th>
			</tr>
		</thead>

		<tbody>
			<tr>
				<td></td>
				<td><?php _e( 'A member starts following your activity', 'bp-follow' ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_starts_following]" value="yes" <?php checked( $notify, 'yes', true ) ?>/></td>
				<td class="no"><input type="radio" name="notifications[notification_starts_following]" value="no" <?php checked( $notify, 'no', true ) ?>/></td>
			</tr>
		</tbody>

		<?php do_action( 'bp_follow_screen_notification_settings' ); ?>
	</table>
<?php
}
add_action( 'bp_notification_settings', 'bp_follow_screen_notification_settings' );

/**
 * Format on screen notifications into something readable by users.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
	global $bp;

	do_action( 'bp_follow_format_notifications', $action, $item_id, $secondary_item_id, $total_items, $format );

	switch ( $action ) {
		case 'new_follow':
			$link = bp_loggedin_user_domain() . $bp->follow->followers->slug . '/?new';

			if ( 1 == $total_items ) {
				$text = __( '1 more user is now following you', 'bp-follow' );
			}
			else {
				$text = sprintf( __( '%d more users are now following you', 'bp-follow' ), $total_items );
			}
		break;

		default :
			$link = apply_filters( 'bp_follow_extend_notification_link', false, $action, $item_id, $secondary_item_id, $total_items );
			$text = apply_filters( 'bp_follow_extend_notification_text', false, $action, $item_id, $secondary_item_id, $total_items );
		break;
	}

	if ( !$link || !$text )
		return false;

	if ( 'string' == $format ) {
		return apply_filters( 'bp_follow_new_followers_notification', '<a href="' . $link . '" title="' . __( 'Your list of followers', 'bp-follow' ) . '">' . $text . '</a>', $total_items, $link, $text, $item_id, $secondary_item_id );
	}
	else {
		$array = array(
			'text' => $text,
			'link' => $link
		);

		return apply_filters( 'bp_follow_new_followers_return_notification', $array, $item_id, $secondary_item_id, $total_items );
	}
}

/**
 * Send an email to the leader when someone follows them.
 *
 * @uses bp_core_get_user_displayname() Get the display name for a user
 * @uses bp_core_get_user_domain() Get the profile url for a user
 * @uses bp_core_get_core_userdata() Get the core userdata for a user without extra usermeta
 * @uses wp_mail() Send an email using the built in WP mail class
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_new_follow_email_notification( $args = '' ) {

	$defaults = array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id()
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( 'no' == bp_get_user_meta( (int)$leader_id, 'notification_starts_following', true ) )
		return false;

	// Check to see if this leader has already been notified of this follower before
	$has_notified = bp_get_user_meta( $follower_id, 'bp_follow_has_notified', true );

	if ( in_array( $leader_id, (array)$has_notified ) )
		return false;

	// Not been notified before, update usermeta and continue to mail
	$has_notified[] = $leader_id;
	bp_update_user_meta( $follower_id, 'bp_follow_has_notified', $has_notified );

	$follower_name = bp_core_get_user_displayname( $follower_id );
	$follower_link = bp_core_get_user_domain( $follower_id );

	$leader_ud = bp_core_get_core_userdata( $leader_id );
	$settings_link = bp_core_get_user_domain( $leader_id ) . BP_SETTINGS_SLUG . '/notifications/';

	// Set up and send the message
	$to = $leader_ud->user_email;
	$subject = '[' . get_option( 'blogname' ) . '] ' . sprintf( __( '%s is now following you', 'bp-follow' ), $follower_name );

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
 * Catches any visits to the "Followers (X)" tab on a users profile.
 *
 * @uses bp_core_load_template() Loads a template file.
 */
function bp_follow_screen_followers() {
	global $bp;

	do_action( 'bp_follow_screen_followers' );

	if ( isset( $_GET['new'] ) )
		bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->follow->id, 'new_follow' );

	bp_core_load_template( 'members/single/followers' );
}

/**
 * Catches any visits to the "Following (X)" tab on a users profile.
 *
 * @uses bp_core_load_template() Loads a template file.
 */
function bp_follow_screen_following() {
	do_action( 'bp_follow_screen_following' );

	bp_core_load_template( 'members/single/following' );
}

/**
 * Catches any visits to the "Activity > Following" tab on a users profile.
 *
 * @uses bp_core_load_template() Loads a template file.
 */
function bp_follow_screen_activity_following() {
	bp_update_is_item_admin( is_super_admin(), 'activity' );
	do_action( 'bp_activity_screen_following' );
	bp_core_load_template( apply_filters( 'bp_activity_template_following', 'members/single/home' ) );
}


/********************************************************************************
 * Action Functions
 */

/**
 * Catches clicks on a "Follow User" button and tries to make that happen.
 *
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

	if ( !bp_is_current_component( $bp->follow->followers->slug ) || !bp_is_current_action( 'start' ) )
		return false;

	if ( bp_displayed_user_id() == bp_loggedin_user_id() )
		return false;

	check_admin_referer( 'start_following' );

	if ( bp_follow_is_following( array( 'leader_id' => bp_displayed_user_id(), 'follower_id' => bp_loggedin_user_id() ) ) )
		bp_core_add_message( sprintf( __( 'You are already following %s.', 'bp-follow' ), bp_get_displayed_user_fullname() ), 'error' );
	else {
		if ( !bp_follow_start_following( array( 'leader_id' => bp_displayed_user_id(), 'follower_id' => bp_loggedin_user_id() ) ) )
			bp_core_add_message( sprintf( __( 'There was a problem when trying to follow %s, please try again.', 'bp-follow' ), bp_get_displayed_user_fullname() ), 'error' );
		else
			bp_core_add_message( sprintf( __( 'You are now following %s.', 'bp-follow' ), bp_get_displayed_user_fullname() ) );
	}

	// it's possible that wp_get_referer() returns false, so let's fallback to the displayed user's page
	$redirect = wp_get_referer() ? wp_get_referer() : bp_displayed_user_domain();
	bp_core_redirect( $redirect );

	return false;
}
add_action( 'bp_actions', 'bp_follow_action_start' );

/**
 * Catches clicks on a "Stop Following User" button and tries to make that happen.
 *
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

	if ( !bp_is_current_component( $bp->follow->followers->slug ) || !bp_is_current_action( 'stop' ) )
		return false;

	if ( bp_displayed_user_id() == bp_loggedin_user_id() )
		return false;

	check_admin_referer( 'stop_following' );

	if ( !bp_follow_is_following( array( 'leader_id' => bp_displayed_user_id(), 'follower_id' => bp_loggedin_user_id() ) ) )
		bp_core_add_message( sprintf( __( 'You are not following %s.', 'bp-follow' ), bp_get_displayed_user_fullname() ), 'error' );
	else {
		if ( !bp_follow_stop_following( array( 'leader_id' => bp_displayed_user_id(), 'follower_id' => bp_loggedin_user_id() ) ) )
			bp_core_add_message( sprintf( __( 'There was a problem when trying to stop following %s, please try again.', 'bp-follow' ), bp_get_displayed_user_fullname() ), 'error' );
		else
			bp_core_add_message( sprintf( __( 'You are no longer following %s.', 'bp-follow' ), bp_get_displayed_user_fullname() ) );
	}

	// it's possible that wp_get_referer() returns false, so let's fallback to the displayed user's page
	$redirect = wp_get_referer() ? wp_get_referer() : bp_displayed_user_domain();
	bp_core_redirect( $redirect );

	return false;
}
add_action( 'bp_actions', 'bp_follow_action_stop' );

/**
 * Allow a user to start following another user by catching an AJAX request.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 * @uses bp_follow_start_following() Starts a user following another user.
 * @return bool false
 */
function bp_follow_ajax_action_start() {

	check_admin_referer( 'start_following' );

	if ( bp_follow_is_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => bp_loggedin_user_id() ) ) )
		$message = __( 'Already following', 'bp-follow' );
	else {
		if ( !bp_follow_start_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => bp_loggedin_user_id() ) ) )
			$message = __( 'Error following user', 'bp-follow' );
		else
			$message = __( 'You are now following', 'bp-follow' );
	}

	echo $message;

	exit();
}
add_action( 'wp_ajax_bp_follow', 'bp_follow_ajax_action_start' );

/**
 * Allow a user to stop following another user by catching an AJAX request.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 * @uses bp_follow_stop_following() Stops a user following another user.
 * @return bool false
 */
function bp_follow_ajax_action_stop() {

	check_admin_referer( 'stop_following' );

	if ( !bp_follow_is_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => bp_loggedin_user_id() ) ) )
		$message = __( 'Not following', 'bp-follow' );
	else {
		if ( !bp_follow_stop_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => bp_loggedin_user_id() ) ) )
			$message = __( 'Error unfollowing user', 'bp-follow' );
		else
			$message = __( 'Stopped following', 'bp-follow' );
	}

	echo $message;

	exit();
}
add_action( 'wp_ajax_bp_unfollow', 'bp_follow_ajax_action_stop' );


/********************************************************************************
 * Business Functions
 */

/**
 * Start following a user's activity
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/leader_id - user ID of user to follow
 * @param $args/follower_id - user ID of the user who follows
 * @return bool
 */
function bp_follow_start_following( $args = '' ) {
	global $bp;

	$defaults = array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id()
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

	do_action_ref_array( 'bp_follow_start_following', array( &$follow ) );

	return true;
}

/**
 * Stop following a user's activity
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/leader_id - user ID of user to stop following
 * @param $args/follower_id - user ID of the user who wants to stop following
 * @return bool
 */
function bp_follow_stop_following( $args = '' ) {

	$defaults = array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id()
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$follow = new BP_Follow( $leader_id, $follower_id );

	if ( !$follow->delete() )
		return false;

	do_action_ref_array( 'bp_follow_stop_following', array( &$follow ) );

	return true;
}

/**
 * Check if a user is already following another user.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/leader_id - user ID of user to check is being followed
 * @param $args/follower_id - user ID of the user who is doing the following
 * @return bool
 */
function bp_follow_is_following( $args = '' ) {

	$defaults = array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id()
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$follow = new BP_Follow( $leader_id, $follower_id );
	return apply_filters( 'bp_follow_is_following', (int)$follow->id, &$follow );
}

/**
 * Fetch the user_ids of all the followers of a particular user.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/user_id - the user ID of the user to get followers for.
 * @return array of user ids
 */
function bp_follow_get_followers( $args = '' ) {

	$defaults = array(
		'user_id' => bp_displayed_user_id()
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return apply_filters( 'bp_follow_get_followers', BP_Follow::get_followers( $user_id ) );
}

/**
 * Fetch the user_ids of all the users a particular user is following.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/user_id - the user ID of the user to get a list of users followed for.
 * @return array of user ids
 */
function bp_follow_get_following( $args = '' ) {

	$defaults = array(
		'user_id' => bp_displayed_user_id()
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return apply_filters( 'bp_follow_get_following', BP_Follow::get_following( $user_id ) );
}

/**
 * Get the total followers and total following counts for a user.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses wp_parse_args() Parses arguments from an array or request string.
 * @param $args/user_id - the user ID of the user to get counts for.
 * @return array [ followers => int, following => int ]
 */
function bp_follow_total_follow_counts( $args = '' ) {

	$defaults = array(
		'user_id' => bp_loggedin_user_id()
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return apply_filters( 'bp_follow_total_follow_counts', BP_Follow::get_counts( $user_id ) );
}

/**
 * Removes follow relationships for all users from a user who is deleted or spammed
 *
 * @uses BP_Follow::delete_all_for_user() Deletes user ID from all following / follower records
 */
function bp_follow_remove_data( $user_id ) {
	global $bp;

	do_action( 'bp_follow_before_remove_data', $user_id );

	BP_Follow::delete_all_for_user( $user_id );

	// Remove following notifications from user
	bp_core_delete_notifications_from_user( $user_id, $bp->follow->id, 'new_follow' );

	do_action( 'bp_follow_remove_data', $user_id );
}
add_action( 'wpmu_delete_user',	'bp_follow_remove_data' );
add_action( 'delete_user',	'bp_follow_remove_data' );
add_action( 'make_spam_user',	'bp_follow_remove_data' );

?>