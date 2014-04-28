<?php

/**
 * BuddyPress Follow Activity Functions
 *
 * These functions handle the recording and deleting of activity
 * for the user for this component.
 *
 * @package BP-Follow
 * @subpackage Activity
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Record an activity item related to the Follow component.
 */
function follow_record_activity( $args = '' ) {
	
	if ( ! bp_is_active( 'activity' ) ) {
		return false;
	}
	
	global $bp;

	$r = wp_parse_args( $args, array(
		'user_id'           => bp_loggedin_user_id(),
		'action'            => '',
		'content'           => '',
		'primary_link'      => '',
		'component'         => $bp->follow->id,
		'type'              => false,
		'item_id'           => false,
		'secondary_item_id' => false,
		'recorded_time'     => bp_core_current_time(),
		'hide_sitewide'     => false
	) );

	return bp_activity_add( $r );
}

/**
 * Delete an activity item related to the Followers component.
 */
function follow_delete_activity( $follow ) {
		
	if ( ! bp_is_active( 'activity' ) ) {
		return;
	}
	
	global $bp;
	
	// Set some variables
	$follow_id 			= $follow->id;
	$leader_user_id 	= $follow->leader_id;
	$follower_user_id 	= $follow->follower_id;

	bp_activity_delete_by_item_id( array(
		'component' => $bp->follow->id,
		'item_id'   => $follow_id,
		'type'      => 'new_follow',
		'user_id'   => $follower_user_id
	) );
}
add_action ( 'bp_follow_stop_following', 'follow_delete_activity' );

/**
 * Register the activity actions for bp-follow.
 */
function follow_register_activity_actions() {
	
	if ( ! bp_is_active( 'activity' ) ) {
		return false;
	}

	global $bp;

	bp_activity_set_action( $bp->follow->id, 'new_follow', __( 'New follow', 'buddypress' ) );

	do_action( 'follow_register_activity_actions' );
}
add_action( 'bp_register_activity_actions', 'follow_register_activity_actions' );

/**
 * Add activity stream items when a member follows another member.
 */
function bp_new_follow_activity( $follow ) {

	// Bail if Activity component is not active
	if ( ! bp_is_active( 'activity' ) ) {
		return;
	}
	
	// Set some variables
	$follow_id 			= $follow->id;
	$leader_user_id 	= $follow->leader_id;
	$follower_user_id 	= $follow->follower_id;

	// Get links to both members profiles
	$follower_link = bp_core_get_userlink( $follower_user_id );
	$leader_link   = bp_core_get_userlink( $leader_user_id );

	// Record in activity streams for the follower
	follow_record_activity( array(
		'user_id'           => $follower_user_id,
		'type'              => 'new_follow',
		'action'            => apply_filters( 'new_follow_activity_action', sprintf( __( '%1$s is now following %2$s', 'bp-follow' ), $follower_link, $leader_link ), $follow ),
		'item_id'           => $follow_id,
		'secondary_item_id' => $leader_user_id
	) );
}
add_action( 'bp_follow_start_following', 'bp_new_follow_activity' );
?>