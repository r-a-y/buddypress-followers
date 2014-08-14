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
		'leader_id'     => bp_displayed_user_id(),
		'follower_id'   => bp_loggedin_user_id(),
		'follow_type'   => '',
		'date_recorded' => bp_core_current_time(),
	) );

	$follow = new BP_Follow( $r['leader_id'], $r['follower_id'], $r['follow_type'] );

	// existing follow already exists
	if ( ! empty( $follow->id ) ) {
		return false;
	}

	// add other properties before save
	$follow->date_recorded = $r['date_recorded'];

	// save!
	if ( ! $follow->save() ) {
		return false;
	}

	// hooks!
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
 * Fetch the IDs of all the followers of a particular item.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int $user_id The user ID to get followers for.
 *     @type string $follow_type The follow type
 *     @type array $query_args The query args.  See $query_args parameter in
 *           {@link BP_Follow::get_followers()}.
 * }
 * @return array
 */
function bp_follow_get_followers( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'user_id'     => bp_displayed_user_id(),
		'follow_type' => '',
		'query_args'  => array()
	) );

	if ( empty( $r['follow_type'] ) ) {
		$retval = apply_filters( 'bp_follow_get_followers', BP_Follow::get_followers( $r['user_id'], $r['follow_type'], $r['query_args'] ) );
	} else {
		$retval = apply_filters( 'bp_follow_get_followers_' .  $r['follow_type'], BP_Follow::get_followers( $r['user_id'], $r['follow_type'], $r['query_args'] ) );
	}

	return $retval;
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
 *     @type array $query_args The query args.  See $query_args parameter in
 *           {@link BP_Follow::get_following()}.
 * }
 * @return array
 */
function bp_follow_get_following( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'user_id'     => bp_displayed_user_id(),
		'follow_type' => '',
		'query_args'  => array()
	) );

	if ( empty( $r['follow_type'] ) ) {
		$retval = apply_filters( 'bp_follow_get_following', BP_Follow::get_following( $r['user_id'], $r['follow_type'], $r['query_args'] ) );
	} else {
		$retval = apply_filters( 'bp_follow_get_following_' .  $r['follow_type'], BP_Follow::get_following( $r['user_id'], $r['follow_type'], $r['query_args'] ) );
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

/** NOTIFICATIONS *******************************************************/

/**
 * Format on screen notifications into something readable by users.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
	global $bp;

	do_action( 'bp_follow_format_notifications', $action, $item_id, $secondary_item_id, $total_items, $format );

	switch ( $action ) {
		case 'new_follow':
			$link = $text = false;

			if ( 1 == $total_items ) {
				$text = sprintf( __( '%s is now following you', 'bp-follow' ), bp_core_get_user_displayname( $item_id ) );
				$link = bp_core_get_user_domain( $item_id  ) . '?bpf_read';

			} else {
				$text = sprintf( __( '%d more users are now following you', 'bp-follow' ), $total_items );

				if ( bp_is_active( 'notifications' ) ) {
					$link = bp_get_notifications_permalink();

					// filter notifications by 'new_follow' action
					if ( version_compare( BP_VERSION, '2.0.9' ) >= 0 ) {
						$link .= '?action=' . $action;
					}
				} else {
					$link = bp_loggedin_user_domain() . $bp->follow->followers->slug . '/?new';
				}
			}

		break;

		default :
			$link = apply_filters( 'bp_follow_extend_notification_link', false, $action, $item_id, $secondary_item_id, $total_items );
			$text = apply_filters( 'bp_follow_extend_notification_text', false, $action, $item_id, $secondary_item_id, $total_items );
		break;
	}

	if ( ! $link || ! $text ) {
		return false;
	}

	if ( 'string' == $format ) {
		return apply_filters( 'bp_follow_new_followers_notification', '<a href="' . $link . '">' . $text . '</a>', $total_items, $link, $text, $item_id, $secondary_item_id );

	} else {
		$array = array(
			'text' => $text,
			'link' => $link
		);

		return apply_filters( 'bp_follow_new_followers_return_notification', $array, $item_id, $secondary_item_id, $total_items );
	}
}