<?php

/**
 * bp_follower_ids() [echo] / bp_get_follower_ids() [return]
 *
 * Fetch a comma separated list of user_ids for the users follow a user. This
 * can then be passed directly into the members loop querystring.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @param $args/user_id - The user ID to fetch a followers list for.
 * @uses bp_follow_get_followers() Return an array of user_ids for the followers of a user.
 */
function bp_follower_ids() {
	echo bp_get_follower_ids();
}
	function bp_get_follower_ids( $args = '' ) {
		global $bp;

		$defaults = array(
			'user_id' => $bp->displayed_user->id
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

 		return apply_filters( 'bp_get_follower_ids', implode( ',', (array)bp_follow_get_followers( array( 'user_id' => $user_id ) ) ) );
	}

/**
 * bp_following_ids() [echo] / bp_get_following_ids() [return]
 *
 * Fetch a comma separated list of user_ids for the users that a user is following. This
 * can then be passed directly into the members loop querystring.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @param $args/user_id - The user ID to fetch a following list for.
 * @uses bp_follow_get_following() Return an array of user_ids for the users a user is following.
 */
function bp_following_ids() {
	echo bp_get_follower_ids();
}
	function bp_get_following_ids( $args = '' ) {
		global $bp;

		$defaults = array(
			'user_id' => $bp->displayed_user->id
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

 		return apply_filters( 'bp_get_following_ids', implode( ',', (array)bp_follow_get_following( array( 'user_id' => $user_id ) ) ) );
	}
?>