<?php
/**
 * Activity scope backwards compatibililty functions for < BP 2.2
 *
 * BuddyPress 2.2.0 includes advanced activity parsing.  BP Follow now uses
 * this functionality.  View {@see bp_follow_users_filter_activity_scope()}
 * for more info.  This file uses the old method of loading up the 'following'
 * activity scope for those using BP < 2.2.
 *
 * @since 1.3.0
 *
 * @package BP-Follow
 * @subpackage Backpat
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Filter the activity loop when we're on a "Following" page
 *
 * This is done:
 *   - On the activity directory and clicking on the "Following" tab
 *   - On a user's "Activity > Following" page
 *
 * @since 1.0.0
 *
 * @param string|array Current activity querystring
 * @param string $object The current object or component
 * @return array
 */
function bp_follow_add_activity_scope_filter( $qs, $object ) {

	$bp = $GLOBALS['bp'];

	// not on the activity object? stop now!
	if ( $object != 'activity' ) {
		return $qs;
	}

	$set = false;

	// activity directory
	// can't use bp_is_activity_directory() yet since that's a BP 2.0 function.
	if ( ! bp_displayed_user_id() && bp_is_activity_component() && ! bp_current_action() ) {
		// check if activity scope is following before manipulating
		if ( isset( $_COOKIE['bp-activity-scope'] ) && 'following' === $_COOKIE['bp-activity-scope'] ) {
			$set = true;
		}

	// user's activity following page.
	} elseif ( bp_is_user_activity() && bp_is_current_action( 'following' ) ) {
		$set = true;
	}

	// not on a user page? stop now!
	if ( ! $set ) {
		return $qs;
	}

	// set internal marker noting that our activity scope is applied.
	$bp->follow->activity_scope_set = 1;

	$qs = wp_parse_args( $qs );

	$following_ids = bp_get_following_ids( array(
		'user_id' => bp_displayed_user_id() ? bp_displayed_user_id() : bp_loggedin_user_id(),
	) );

	// if $following_ids is empty, pass a negative number so no activity can be found.
	$following_ids = empty( $following_ids ) ? -1 : $following_ids;

	$qs['user_id'] = $following_ids;

	return apply_filters( 'bp_follow_add_activity_scope_filter', $qs, false );
}
add_filter( 'bp_ajax_querystring', 'bp_follow_add_activity_scope_filter', 20, 2 );

/**
 * On a user's "Activity > Following" page, set the activity scope to
 * "following".
 *
 * Unfortunately for 3rd-party components, this is the only way to set the
 * scope in {@link bp_dtheme_ajax_querystring()} due to the way that function
 * handles cookies.
 *
 * Yes, this is considered a hack, or more appropriately, a loophole!
 *
 * @author r-a-y
 * @since 1.1.1
 */
function bp_follow_set_activity_following_scope() {
	// set the activity scope to 'following' by faking an ajax request (loophole!)
	$_POST['cookie'] = 'bp-activity-scope%3Dfollowing%3B%20bp-activity-filter%3D-1';

	// reset the dropdown menu to 'Everything'
	@setcookie( 'bp-activity-filter', '-1', 0, '/' );
}
add_action( 'bp_activity_screen_following', 'bp_follow_set_activity_following_scope' );

/**
 * On a user's "Activity > Following" screen, set the activity scope to
 * "following" during AJAX requests ("Load More" button or via activity
 * dropdown filter menu).
 *
 * Unfortunately for 3rd-party components, this is the only way to set the
 * scope in {@link bp_dtheme_ajax_querystring()} due to the way that function
 * handles cookies.
 *
 * Yes, this is considered a hack, or more appropriately, a loophole!
 *
 * @author r-a-y
 * @since 1.1.1
 */
function bp_follow_set_activity_following_scope_on_ajax() {
	// set the activity scope to 'following'
	if ( bp_is_current_action( 'following' ) && bp_follow_is_doing_ajax() ) {
		// if we have a post value already, let's add our scope to the existing cookie value
		if ( !empty( $_POST['cookie'] ) )
			$_POST['cookie'] .= '%3B%20bp-activity-scope%3Dfollowing';
		else
			$_POST['cookie'] .= 'bp-activity-scope%3Dfollowing';
	}
}
add_action( 'bp_before_activity_loop', 'bp_follow_set_activity_following_scope_on_ajax' );
