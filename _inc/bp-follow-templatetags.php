<?php
/**
 * BP Follow Template Tags
 *
 * @package BP-Follow
 * @subpackage Template
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Output a comma-separated list of user_ids for a given user's followers.
 *
 * @param array $args See bp_get_follower_ids().
 */
function bp_follower_ids( $args = '' ) {
	echo bp_get_follower_ids( $args );
}
	/**
	 * Returns a comma separated list of user_ids for a given user's followers.
	 *
	 * On failure, returns an integer of zero. Needed when used in a members loop to prevent SQL errors.
	 *
	 * @param array $args {
	 *     Array of arguments.
	 *     @type int $user_id The user ID you want to check for followers.
	 *     @type string $follow_type The follow type
	 * }
	 * @return string|int Comma-seperated string of user IDs on success. Integer zero on failure.
	 */
	function bp_get_follower_ids( $args = '' ) {

		$r = wp_parse_args( $args, array(
			'user_id' => bp_displayed_user_id()
		) );

		$ids = implode( ',', (array) bp_follow_get_followers( array( 'user_id' => $r['user_id'] ) ) );

		$ids = empty( $ids ) ? 0 : $ids;

 		return apply_filters( 'bp_get_follower_ids', $ids, $r['user_id'] );
	}

/**
 * Output a comma-separated list of user_ids for a given user's following.
 *
 * @param array $args See bp_get_following_ids().
 */
function bp_following_ids( $args = '' ) {
	echo bp_get_following_ids( $args );
}
	/**
	 * Returns a comma separated list of IDs for a given user's following.
	 *
	 * On failure, returns integer zero. Needed when used in a members loop to prevent SQL errors.
	 *
	 * @param array $args {
	 *     Array of arguments.
	 *     @type int $user_id The user ID to fetch following user IDs for.
	 *     @type string $follow_type The follow type
	 * }
	 * @return string|int Comma-seperated string of user IDs on success. Integer zero on failure.
	 */
	function bp_get_following_ids( $args = '' ) {

		$r = wp_parse_args( $args, array(
			'user_id'     => bp_displayed_user_id(),
			'follow_type' => '',
		) );

		$ids = implode( ',', (array) bp_follow_get_following( array(
			'user_id'     => $r['user_id'],
			'follow_type' => $r['follow_type'],
		) ) );

		$ids = empty( $ids ) ? 0 : $ids;

 		return apply_filters( 'bp_get_following_ids', $ids, $r['user_id'], $r );
	}
