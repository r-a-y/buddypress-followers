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
 * bp_follower_ids() [echo] / bp_get_follower_ids() [return]
 *
 * Fetch a comma separated list of user_ids for the users follow a user. This
 * can then be passed directly into the members loop querystring.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @param $args/user_id - The user ID to fetch a followers list for.
 * @uses bp_follow_get_followers() Return an array of user_ids for the followers of a user.
 */
function bp_follower_ids() {
	echo bp_get_follower_ids();
}
	function bp_get_follower_ids( $args = '' ) {

		$defaults = array(
			'user_id' => bp_displayed_user_id()
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );
		
		$ids = implode( ',', (array)bp_follow_get_followers( array( 'user_id' => $user_id ) ) );
		
		$ids = empty( $ids ) ? 0 : $ids;

 		return apply_filters( 'bp_get_follower_ids', $ids, $user_id );
	}

/**
 * bp_following_ids() [echo] / bp_get_following_ids() [return]
 *
 * Fetch a comma separated list of user_ids for the users that a user is following. This
 * can then be passed directly into the members loop querystring.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @param $args/user_id - The user ID to fetch a following list for.
 * @uses bp_follow_get_following() Return an array of user_ids for the users a user is following.
 */
function bp_following_ids() {
	echo bp_get_following_ids();
}
	function bp_get_following_ids( $args = '' ) {

		$defaults = array(
			'user_id' => bp_displayed_user_id()
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$ids = implode( ',', (array)bp_follow_get_following( array( 'user_id' => $user_id ) ) );
		
		$ids = empty( $ids ) ? 0 : $ids;

 		return apply_filters( 'bp_get_following_ids', $ids, $user_id );
	}

/**
 * Output a follow / unfollow button for a given user depending on the follower status.
 *
 * @param int $leader_id The user you want to follow
 * @param int $follower_id The user who is initiating the follow request
 * @uses bp_follow_get_add_follow_button() Returns the follow / unfollow button
 * @author r-a-y
 * @since 1.1
 */
function bp_follow_add_follow_button( $args = '' ) {
	echo bp_follow_get_add_follow_button( $args );
}
	/**
	 * Returns a follow / unfollow button for a given user depending on the follower status.
	 *
	 * Checks to see if the follower is already following the leader.  If following, returns
	 * "Stop following" button; if not following, returns "Follow" button.
	 *
	 * @param int $leader_id The user you want to follow
	 * @param int $follower_id The user who is initiating the follow request
	 * @return mixed String of the button on success.  Boolean false on failure.
	 * @uses bp_get_button() Renders a button using the BP Button API
	 * @author r-a-y
	 * @since 1.1
	 */
	function bp_follow_get_add_follow_button( $args = '' ) {
		global $bp, $members_template;

		$defaults = array(
			'leader_id'   => bp_displayed_user_id(),
			'follower_id' => bp_loggedin_user_id()
		);
	
		$r = wp_parse_args( $args, $defaults );
		extract( $r );

		if ( !$leader_id || !$follower_id )
			return false;

		// if we're checking during a members loop, then follow status is already queried via bp_follow_inject_member_follow_status()
		if ( !empty( $members_template->member ) && $follower_id == bp_loggedin_user_id() && $follower_id == bp_displayed_user_id() ) {
			$is_following = $members_template->member->is_following;
		}
		// else we manually query the follow status
		else {
			$is_following = bp_follow_is_following( array( 'leader_id' => $leader_id, 'follower_id' => $follower_id ) );
		}

		// if the logged-in user is the leader, use already-queried variables
		if ( !bp_loggedin_user_id() && $leader_id == bp_loggedin_user_id() ) {
			$leader_domain   = bp_loggedin_user_domain();
			$leader_fullname = bp_get_loggedin_user_fullname();
		}
		// else we do a lookup for the user domain and display name of the leader
		else {
			$leader_domain   = bp_core_get_user_domain( $leader_id );
			$leader_fullname = bp_core_get_user_displayname( $leader_id );
		}

		// setup some variables
		if ( $is_following ) {
			$id        = 'following';
			$action    = 'stop';
			$class     = 'unfollow';
			$link_text = $link_title = sprintf( __( 'Stop Following %s', 'bp-follow' ), bp_get_user_firstname( $leader_fullname ) );
		}
		else {
			$id        = 'not-following';
			$action    = 'start';
			$class     = 'follow';
			$link_text = $link_title = sprintf( __( 'Follow %s', 'bp-follow' ), bp_get_user_firstname( $leader_fullname ) );
		}

		// setup the button arguments
		$button = array(
			'id'                => $id,
			'component'         => 'follow',
			'must_be_logged_in' => true,
			'block_self'        => empty( $members_template->member ) ? true : false,
			'wrapper_class'     => 'follow-button ' . $id,
			'wrapper_id'        => 'follow-button-' . $leader_id,
			'link_href'         => wp_nonce_url( $leader_domain . $bp->follow->followers->slug . '/' . $action .'/', $action . '_following' ),
			'link_text'         => $link_text,
			'link_title'        => $link_title,
			'link_id'           => $class . '-' . $leader_id,
			'link_class'        => $class
		);

		// Filter and return the HTML button
		return bp_get_button( apply_filters( 'bp_follow_get_add_follow_button', $button, $leader_id, $follower_id ) );
	}
?>