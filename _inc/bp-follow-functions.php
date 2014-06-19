<?php
/**
 * BP Follow Functions
 *
 * @package BP-Follow
 * @subpackage Functions
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Start following a user's activity.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $leader_id The user ID of the person we want to follow.
 *     @type int $follower_id The user ID initiating the follow request.
 * }
 * @return bool
 */
function bp_follow_start_following( $args = '' ) {
	global $bp;

	$r = wp_parse_args( $args, array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$follow = new BP_Follow( $r['leader_id'], $r['follower_id'], $r['follow_type'] );

	// existing follow already exists
	if ( ! empty( $follow->id ) ) {
		return false;
	}

	if ( ! $follow->save() ) {
		return false;
	}

	if ( empty( $r['follow_type'] ) ) {
		do_action_ref_array( 'bp_follow_start_following', array( &$follow ) );
	} else {
		do_action_ref_array( 'bp_follow_start_following_' . $r['follow_type'], array( &$follow ) );	
	}

	return true;
}

/**
 * Stop following a user's activity.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $leader_id The user ID of the person we want to stop following.
 *     @type int $follower_id The user ID initiating the unfollow request.
 * }
 * @return bool
 */
function bp_follow_stop_following( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$follow = new BP_Follow( $r['leader_id'], $r['follower_id'], $r['follow_type'] );

	if ( ! $follow->delete() ) {
		return false;
	}

	if ( empty( $r['follow_type'] ) ) {
		do_action_ref_array( 'bp_follow_stop_following', array( &$follow ) );
	} else {
		do_action_ref_array( 'bp_follow_stop_following_' . $r['follow_type'], array( &$follow ) );	
	}

	return true;
}

/**
 * Check if a user is already following another user.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $leader_id The user ID of the person we want to check.
 *     @type int $follower_id The user ID initiating the follow request.
 * }
 * @return bool
 */
function bp_follow_is_following( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$follow = new BP_Follow( $r['leader_id'], $r['follower_id'], $r['follow_type'] );

	if ( empty( $r['follow_type'] ) ) {
		$retval = apply_filters( 'bp_follow_is_following', (int) $follow->id, $follow );
	} else {
		$retval = apply_filters( 'bp_follow_is_following_' . $r['follow_type'], (int) $follow->id, $follow );	
	}

	return $retval;
}

/**
 * Fetch the user IDs of all the followers of a particular user.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $user_id The user ID to get followers for.
 * }
 * @return array
 */
function bp_follow_get_followers( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'user_id' => bp_displayed_user_id()
	) );

	return apply_filters( 'bp_follow_get_followers', BP_Follow::get_followers( $r['user_id'] ) );
}

/**
 * Fetch all IDs that a particular user is following.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $user_id The user ID to fetch following user IDs for.
 *     @type string $follow_type The follow type
 * }
 * @return array
 */
function bp_follow_get_following( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'user_id'     => bp_displayed_user_id(),
		'follow_type' => '',
	) );

	if ( empty( $r['follow_type'] ) ) {
		$retval = apply_filters( 'bp_follow_get_following', BP_Follow::get_following( $r['user_id'], $r['follow_type'] ) );
	} else {
		$retval = apply_filters( 'bp_follow_get_following_' .  $r['follow_type'], BP_Follow::get_following( $r['user_id'], $r['follow_type'] ) );
	}

	return $retval;
}

/**
 * Get the total followers and total following counts for a user.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $user_id The user ID to grab follow counts for.
 *     @type string $follow_type The follow type
 * }
 * @return array [ followers => int, following => int ]
 */
function bp_follow_total_follow_counts( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'user_id'     => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$counts = BP_Follow::get_counts( $r['user_id'], $r['follow_type'] );

	if ( empty( $r['follow_type'] ) ) {
		$retval = apply_filters( 'bp_follow_total_follow_counts', $counts, $r['user_id'] );
	} else {
		$retval = apply_filters( 'bp_follow_total_follow_' . $r['follow_type'] . '_counts', $counts, $r['user_id'] );
	}

	return $retval;
}

/**
 * Removes follow relationships for all users from a user who is deleted or spammed
 *
 * @since 1.0.0
 *
 * @uses BP_Follow::delete_all_for_user() Deletes user ID from all following / follower records
 */
function bp_follow_remove_data( $user_id ) {
	do_action( 'bp_follow_before_remove_data', $user_id );

	BP_Follow::delete_all_for_user( $user_id );

	do_action( 'bp_follow_remove_data', $user_id );
}
add_action( 'wpmu_delete_user',	'bp_follow_remove_data' );
add_action( 'delete_user',	'bp_follow_remove_data' );
add_action( 'make_spam_user',	'bp_follow_remove_data' );

/**
 * Is an AJAX request currently taking place?
 *
 * Since BP Follow still supports BP 1.5, we can't simply use the DOING_AJAX
 * constant because BP 1.5 doesn't use admin-ajax.php for AJAX requests.  A
 * workaround is checking the "HTTP_X_REQUESTED_WITH" server variable.
 *
 * Once BP Follow drops support for BP 1.5, we can use the DOING_AJAX constant
 * as intended.
 *
 * @since 1.3.0
 *
 * @return bool
 */
function bp_follow_is_doing_ajax() {
	return ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' );
}