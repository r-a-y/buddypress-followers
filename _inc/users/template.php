<?php
/**
 * BP Follow User Template Functions
 *
 * @package BP-Follow
 * @subpackage Template
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Output a follow / unfollow button for a given user depending on the follower status.
 *
 * @param mixed $args See bp_follow_get_add_follow_button() for full arguments.
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
	 * Checks to see if the follower is already following the leader.  If is following, returns
	 * "Stop following" button; if not following, returns "Follow" button.
	 *
	 * @param array $args {
	 *     Array of arguments.
	 *     @type int $leader_id The user ID of the person we want to follow.
	 *     @type int $follower_id The user ID initiating the follow request.
	 *     @type string $link_text The anchor text for the link.
	 *     @type string $link_title The title attribute for the link.
	 *     @type string $wrapper_class CSS class for the wrapper container.
	 *     @type string $link_class CSS class for the link.
	 *     @type string $wrapper The element for the wrapper container. Defaults to 'div'.
	 * }
	 * @return mixed String of the button on success.  Boolean false on failure.
	 * @uses bp_get_button() Renders a button using the BP Button API
	 * @author r-a-y
	 * @since 1.1
	 */
	function bp_follow_get_add_follow_button( $args = '' ) {
		global $members_template;

		$bp = $GLOBALS['bp'];

		$r = wp_parse_args( $args, array(
			'leader_id'     => bp_displayed_user_id(),
			'follower_id'   => bp_loggedin_user_id(),
			'link_text'     => '',
			'link_title'    => '',
			'wrapper_class' => '',
			'link_class'    => '',
			'wrapper'       => 'div',
		) );

		if ( ! $r['leader_id'] || ! $r['follower_id'] ) {
			return false;
		}

		// if we're checking during a members loop, then follow status is already
		// queried via bp_follow_inject_member_follow_status().
		if ( ! empty( $members_template->in_the_loop ) && $r['follower_id'] === bp_loggedin_user_id() && $r['leader_id'] === bp_get_member_user_id() ) {
			$is_following = $members_template->member->is_following;

		// else we manually query the follow status
		} else {
			$is_following = bp_follow_is_following( array(
				'leader_id'   => $r['leader_id'],
				'follower_id' => $r['follower_id'],
			) );
		}

		$logged_user_id = bp_loggedin_user_id();

		// if the logged-in user is the leader, use already-queried variables.
		if ( $logged_user_id && $logged_user_id === $r['leader_id'] ) {
			$leader_fullname = bp_get_loggedin_user_fullname();

		// else we do a lookup for the user domain and display name of the leader.
		} else {
			$leader_fullname = bp_core_get_user_displayname( $r['leader_id'] );
		}

		// setup some variables.
		if ( $is_following ) {
			$id        = 'following';
			$action    = 'stop';
			$class     = 'unfollow';
			$link_text = sprintf( _x( 'Unfollow', 'Button', 'buddypress-followers' ), apply_filters( 'bp_follow_leader_name', bp_get_user_firstname( $leader_fullname ), $r['leader_id'] ) );

			if ( empty( $r['link_text'] ) ) {
				$r['link_text'] = $link_text;
			}

		} else {
			$id        = 'not-following';
			$action    = 'start';
			$class     = 'follow';
			$link_text = sprintf( _x( 'Follow', 'Button', 'buddypress-followers' ), apply_filters( 'bp_follow_leader_name', bp_get_user_firstname( $leader_fullname ), $r['leader_id'] ) );

			if ( empty( $r['link_text'] ) ) {
				$r['link_text'] = $link_text;
			}

		}

		$wrapper_class = 'follow-button ' . $id;

		if ( ! empty( $r['wrapper_class'] ) ) {
			$wrapper_class .= ' ' . esc_attr( $r['wrapper_class'] );
		}

		$link_class = $class;

		if ( ! empty( $r['link_class'] ) ) {
			$link_class .= ' ' . esc_attr( $r['link_class'] );
		}

		// make sure we can view the button if a user is on their own page.
		$block_self = empty( $members_template->member ) ? true : false;

		// if we're using AJAX and a user is on their own profile, we need to set
		// block_self to false so the button shows up.
		if ( bp_follow_is_doing_ajax() && bp_is_my_profile() ) {
			$block_self = false;
		}

		// setup the button arguments.
		$button = array(
			'id'                => $id,
			'component'         => 'follow',
			'must_be_logged_in' => true,
			'block_self'        => $block_self,
			'wrapper_class'     => $wrapper_class,
			'wrapper_id'        => 'follow-button-' . (int) $r['leader_id'],
			'link_href'         => wp_nonce_url( bp_follow_get_user_url( $r['leader_id'], array( $bp->follow->followers->slug, $action ) ), $action . '_following' ),
			'link_text'         => esc_attr( $r['link_text'] ),
			'link_title'        => esc_attr( $r['link_title'] ),
			'link_id'           => $class . '-' . (int) $r['leader_id'],
			'link_class'        => $link_class,
			'wrapper'           => ! empty( $r['wrapper'] ) ? esc_attr( $r['wrapper'] ) : false,
		);

		// BP Nouveau-specific button arguments.
		if ( function_exists( 'bp_nouveau' ) ) {
			if ( $button['wrapper'] && ! bp_is_group() ) {
				$button['parent_element'] = 'li';
			}
			$button['link_class']    .= ' button';
		}

		// Filter and return the HTML button.
		return bp_get_button( apply_filters( 'bp_follow_get_add_follow_button', $button, $r['leader_id'], $r['follower_id'] ) );
	}
