<?php
/**
 * BP Follow AJAX Functions
 *
 * @package BP-Follow
 * @subpackage AJAX
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Registers the BP Follow Ajax actions.
 *
 * @since 1.3.0
 */
function bp_follow_register_ajax_action() {
	if ( ! function_exists( 'bp_ajax_register_action' ) ) {
		return;
	}

	bp_ajax_register_action( 'bp_follow' );
	bp_ajax_register_action( 'bp_unfollow' );
}
add_action( 'bp_init', 'bp_follow_register_ajax_action' );

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

	// successful follow.
	if ( bp_follow_start_following( array(
		'leader_id' => $_POST['uid'],
		'follower_id' => bp_loggedin_user_id(),
	) ) ) {
		// output unfollow button.
		$output = bp_follow_get_add_follow_button( array(
			'leader_id'   => $_POST['uid'],
			'follower_id' => bp_loggedin_user_id(),
			'wrapper'     => false,
			'link_class'  => $link_class,
		) );

	// failed follow
	} else {
		// output fallback invalid button.
		$args = array(
			'id'         => 'invalid',
			'link_href'  => 'javascript:;',
			'component'  => 'follow',
			'wrapper'    => false,
			'link_class' => $link_class,
		);

		if ( bp_follow_is_following( array(
			'leader_id' => $_POST['uid'],
			'follower_id' => bp_loggedin_user_id(),
		) ) ) {
			$output = bp_get_button( array_merge(
				array(
					'link_text' => __( 'Already following', 'buddypress-followers' ),
				),
				$args
			) );
		} else {
			$output = bp_get_button( array_merge(
				array(
					'link_text' => __( 'Error following user', 'buddypress-followers' ),
				),
				$args
			) );
		}
	}

	/**
	 * Filter the JSON response for the AJAX start action.
	 *
	 * @since 1.3.0
	 *
	 * @param array $response {
	 *     An array of parameters. You can use this filter to add custom parameters as
	 *     array keys.
	 *     @type string $button The AJAX button to render after unfollowing a user.
	 * }
	 * @param int $leader_id The user ID of the person being followed.
	 */
	$output = apply_filters( 'bp_follow_ajax_action_start_response', array(
		'button' => $output,
	), $_POST['uid'] );

	wp_send_json_success( $output );
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

	// successful unfollow.
	if ( bp_follow_stop_following( array(
		'leader_id' => $_POST['uid'],
		'follower_id' => bp_loggedin_user_id(),
	) ) ) {
		// output follow button.
		$output = bp_follow_get_add_follow_button( array(
			'leader_id'   => $_POST['uid'],
			'follower_id' => bp_loggedin_user_id(),
			'wrapper'     => false,
			'link_class'  => $link_class,
		) );

	// failed unfollow
	} else {
		// output fallback invalid button.
		$args = array(
			'id'         => 'invalid',
			'link_href'  => 'javascript:;',
			'component'  => 'follow',
			'wrapper'    => false,
			'link_class' => $link_class,
		);

		if ( ! bp_follow_is_following( array(
			'leader_id' => $_POST['uid'],
			'follower_id' => bp_loggedin_user_id(),
		) ) ) {
			$output = bp_get_button( array_merge(
				array(
					'link_text' => __( 'Not following', 'buddypress-followers' ),
				),
				$args
			) );

		} else {
			$output = bp_get_button( array_merge(
				array(
					'link_text' => __( 'Error unfollowing user', 'buddypress-followers' ),
				),
				$args
			) );

		}
	}

	/**
	 * Filter the JSON response for the AJAX stop action.
	 *
	 * @since 1.3.0
	 *
	 * @param array $response {
	 *     An array of parameters. You can use this filter to add custom parameters as
	 *     array keys.
	 *     @type string $button The AJAX button to render after unfollowing a user.
	 * }
	 * @param int $leader_id The user ID of the person being unfollowed.
	 */
	$output = apply_filters( 'bp_follow_ajax_action_stop_response', array(
		'button' => $output,
	), $_POST['uid'] );

	wp_send_json_success( $output );
}
add_action( 'wp_ajax_bp_unfollow', 'bp_follow_ajax_action_stop' );
