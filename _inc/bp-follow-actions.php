<?php
/**
 * BP Follow Actions
 *
 * @package BP-Follow
 * @subpackage Actions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

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

/** AJAX ACTIONS ***************************************************/

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
