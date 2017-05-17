<?php
/**
 * BP Follow AJAX Functions
 *
 * @package BP-Follow
 * @subpackage AJAX
 */

/**
 * AJAX callback when clicking on the "Follow" button to follow a user.
 *
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_start_following() Starts a user following another user.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 */
function bp_follow_ajax_action_start() {

	check_admin_referer( 'start_following' );

	$link_class = ! empty( $_POST['link_class'] ) ? str_replace( 'follow ', '', $_POST['link_class'] ) : false;

	// successful follow
	if ( bp_follow_start_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => bp_loggedin_user_id() ) ) ) {
		// output unfollow button
		$output = bp_follow_get_add_follow_button( array(
			'leader_id'   => $_POST['uid'],
			'follower_id' => bp_loggedin_user_id(),
			'wrapper'     => false,
			'link_class'  => $link_class
		) );

	// failed follow
	} else {
		// output fallback invalid button
		$args = array(
			'id'         => 'invalid',
			'link_href'  => 'javascript:;',
			'component'  => 'follow',
			'wrapper'    => false,
			'link_class' => $link_class
		);

		if ( bp_follow_is_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => bp_loggedin_user_id() ) ) ) {
			$output = bp_get_button( array_merge(
				array( 'link_text' => __( 'Already following', 'buddypress-followers' ) ),
				$args
			) );
		} else {
			$output = bp_get_button( array_merge(
				array( 'link_text' => __( 'Error following user', 'buddypress-followers' ) ),
				$args
			) );
		}
	}

	echo $output;

	exit();
}
add_action( 'wp_ajax_bp_follow', 'bp_follow_ajax_action_start' );

/**
 * AJAX callback when clicking on the "Unfollow" button to unfollow a user.
 *
 * @uses check_admin_referer() Checks to make sure the WP security nonce matches.
 * @uses bp_follow_stop_following() Stops a user following another user.
 * @uses bp_follow_is_following() Checks to see if a user is following another user already.
 */
function bp_follow_ajax_action_stop() {

	check_admin_referer( 'stop_following' );

	$link_class = ! empty( $_POST['link_class'] ) ? str_replace( 'unfollow ', '', $_POST['link_class'] ) : false;

	// successful unfollow
	if ( bp_follow_stop_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => bp_loggedin_user_id() ) ) ) {
		// output follow button
		$output = bp_follow_get_add_follow_button( array(
			'leader_id'   => $_POST['uid'],
			'follower_id' => bp_loggedin_user_id(),
			'wrapper'     => false,
			'link_class'  => $link_class
		) );

	// failed unfollow
	} else {
		// output fallback invalid button
		$args = array(
			'id'         => 'invalid',
			'link_href'  => 'javascript:;',
			'component'  => 'follow',
			'wrapper'    => false,
			'link_class' => $link_class
		);

		if ( ! bp_follow_is_following( array( 'leader_id' => $_POST['uid'], 'follower_id' => bp_loggedin_user_id() ) ) ) {
			$output = bp_get_button( array_merge(
				array( 'link_text' => __( 'Not following', 'buddypress-followers' ) ),
				$args
			) );

		} else {
			$output = bp_get_button( array_merge(
				array( 'link_text' => __( 'Error unfollowing user', 'buddypress-followers' ) ),
				$args
			) );

		}
	}

	echo $output;

	exit();
}
add_action( 'wp_ajax_bp_unfollow', 'bp_follow_ajax_action_stop' );