<?php

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
	return apply_filters( 'bp_follow_is_following', (int)$follow->id, $follow );
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
