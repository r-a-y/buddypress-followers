<?php
/**
 * BP Follow Actions
 *
 * @package BP-Follow
 * @subpackage Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Catches clicks on a "Follow" button and tries to make that happen.
 *
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 * @uses bp_follow_start_following() Starts a user following another user.
 * @uses bp_core_add_message() Adds an error/success message to be displayed after redirect.
 * @uses bp_core_redirect() Safe redirects the user to a particular URL.
 */
function bp_follow_action_start() {
	$bp = $GLOBALS['bp'];

	if ( ! bp_is_current_component( $bp->follow->followers->slug ) || ! bp_is_current_action( 'start' ) ) {
		return;
	}

	if ( bp_displayed_user_id() === bp_loggedin_user_id() ) {
		return;
	}

	check_admin_referer( 'start_following' );

	if ( bp_follow_is_following( array( 'leader_id' => bp_displayed_user_id(), 'follower_id' => bp_loggedin_user_id() ) ) ) {
		bp_core_add_message( sprintf( __( 'You are already following %s.', 'buddypress-followers' ), bp_get_displayed_user_fullname() ), 'error' );

	} else {
		if ( ! bp_follow_start_following( array( 'leader_id' => bp_displayed_user_id(), 'follower_id' => bp_loggedin_user_id() ) ) ) {
			bp_core_add_message( sprintf( __( 'There was a problem when trying to follow %s, please try again.', 'buddypress-followers' ), bp_get_displayed_user_fullname() ), 'error' );
		} else {
			bp_core_add_message( sprintf( __( 'You are now following %s.', 'buddypress-followers' ), bp_get_displayed_user_fullname() ) );
		}
	}

	// it's possible that wp_get_referer() returns false, so let's fallback to the displayed user's page.
	$redirect = wp_get_referer() ? wp_get_referer() : bp_displayed_user_domain();
	bp_core_redirect( $redirect );
}
add_action( 'bp_actions', 'bp_follow_action_start' );

/**
 * Catches clicks on a "Unfollow" button and tries to make that happen.
 *
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 * @uses bp_follow_stop_following() Stops a user following another user.
 * @uses bp_core_add_message() Adds an error/success message to be displayed after redirect.
 * @uses bp_core_redirect() Safe redirects the user to a particular URL.
 */
function bp_follow_action_stop() {
	$bp = $GLOBALS['bp'];

	if ( ! bp_is_current_component( $bp->follow->followers->slug ) || ! bp_is_current_action( 'stop' ) ) {
		return;
	}

	if ( bp_displayed_user_id() === bp_loggedin_user_id() ) {
		return;
	}

	check_admin_referer( 'stop_following' );

	if ( ! bp_follow_is_following( array( 'leader_id' => bp_displayed_user_id(), 'follower_id' => bp_loggedin_user_id() ) ) ) {
		bp_core_add_message( sprintf( __( 'You are not following %s.', 'buddypress-followers' ), bp_get_displayed_user_fullname() ), 'error' );

	} else {
		if ( ! bp_follow_stop_following( array( 'leader_id' => bp_displayed_user_id(), 'follower_id' => bp_loggedin_user_id() ) ) ) {
			bp_core_add_message( sprintf( __( 'There was a problem when trying to stop following %s, please try again.', 'buddypress-followers' ), bp_get_displayed_user_fullname() ), 'error' );
		} else {
			bp_core_add_message( sprintf( __( 'You are no longer following %s.', 'buddypress-followers' ), bp_get_displayed_user_fullname() ) );
		}
	}

	// it's possible that wp_get_referer() returns false, so let's fallback to the displayed user's page.
	$redirect = wp_get_referer() ? wp_get_referer() : bp_displayed_user_domain();
	bp_core_redirect( $redirect );
}
add_action( 'bp_actions', 'bp_follow_action_stop' );

/**
 * Add RSS feed support for a user's following activity.
 *
 * Ex.: example.com/members/USERNAME/activity/following/feed/
 *
 * Only available in BuddyPress 1.8+.
 *
 * @since 1.2.1
 * @author r-a-y
 */
function bp_follow_my_following_feed() {
	// only available in BP 1.8+.
	if ( ! class_exists( 'BP_Activity_Feed' ) ) {
		return;
	}

	if ( ! bp_is_user_activity() || ! bp_is_current_action( constant( 'BP_FOLLOWING_SLUG' ) ) || ! bp_is_action_variable( 'feed', 0 ) ) {
		return false;
	}

	$bp = $GLOBALS['bp'];

	// setup the feed.
	$bp->activity->feed = new BP_Activity_Feed( array(
		'id'            => 'myfollowing',

		/* translators: User's following activity RSS title - "[Site Name] | [User Display Name] | Following Activity" */
		'title'         => sprintf( __( '%1$s | %2$s | Following Activity', 'buddypress-followers' ), bp_get_site_name(), bp_get_displayed_user_fullname() ),

		'link'          => bp_follow_get_user_url( bp_displayed_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOWING_SLUG' ) ) ),
		'description'   => sprintf( __( "Activity feed for people that %s is following.", 'buddypress' ), bp_get_displayed_user_fullname() ),
		'activity_args' => array(
			'user_id'  => bp_get_following_ids(),
			'display_comments' => 'threaded'
		)
	) );
}
add_action( 'bp_actions', 'bp_follow_my_following_feed' );
