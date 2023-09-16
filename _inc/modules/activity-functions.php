<?php
/**
 * Follow Activity Functions.
 *
 * @since 1.3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Builds the activity directory url.
 *
 * @since 1.3.0
 *
 * @param array $path_chunks Path to add to the activity directory url.
 * @return string The activity directory url.
 */
function bp_follow_activity_get_directory_url( $path_chunks = array() ) {
	if ( function_exists( 'bp_rewrites_get_url' ) ) {
		$args = array(
			'component_id' => 'activity',
		);

		if ( $path_chunks ) {
			$args['single_item_action'] = array_shift( $path_chunks );

			if ( $path_chunks ) {
				$args['single_item_action_variables'] = array_shift( $path_chunks );
			}
		}

		$url = bp_rewrites_get_url( $args );
	} else {
		$url = bp_get_activity_directory_permalink();

		if ( $path_chunks ) {
			$action_variables = end( $path_chunks );
			if ( is_array( $action_variables ) ) {
				array_pop( $path_chunks );
				$path_chunks = array_merge( $path_chunks, $action_variables );
			}

			$url = trailingslashit( $url ) . trailingslashit( implode( '/', $path_chunks ) );
		}
	}

	return $url;
}

/**
 * Check if the current activity item in the activity loop can be followed.
 *
 * There are two ways to register your activity item to be followed. Please
 * see inline documentation for more details.
 *
 * @param  int $activity_id The activity ID to check.
 * @return bool
 */
function bp_follow_activity_can_follow( $activity_id = 0 ) {
	// There are two ways to register a follow button for your activity item:

	/**
	 * (1) Post type method.
	 *
	 * If you're registering a post type with BP activity support, and you want to
	 * show a 'Follow' button in the activity loop for this item, simply add the
	 * 'follow_button' key to the 'bp_activity' array.
	 *
	 * 'follow_type' is an optional argument, which is handy if you wanted to
	 * separate your post type from the generic followed activity tab/filter. If
	 * you do set this argument, you'll have to register your own activity nav
	 * items, adminbar menus, caching invalidation and activity scope manually.
	 *
	 * eg.
	 *	'bp_activity' => array(
	 *		'format_callback' => 'WHATEVER',
	 *              'follow_button' => true,
	 *
	 * 		// optional; defaults to 'activity' if not set.
	 * 		// handy if you want to separate
	 *		'follow_type' => 'UNIQUE_ID'
	 *	)
	 */

	/**
	 * (2) Activity action method.
	 *
	 * If you're registering an activity action and you want to show a 'Follow'
	 * button in the activity loop, set the 'follow_button' key for the $context
	 * parameter.
	 *
	 * 'follow_type' is an optional argument, which is handy if you wanted to
	 * separate your post type from the generic followed activity tab/filter. If
	 * you do set this argument, you'll have to register your own activity nav
	 * items, adminbar menus, caching invalidation and activity scope manually.
	 *
	 * eg.
	 *	bp_activity_set_action(
	 *		$bp->activity->id,
	 *		'activity_update',
	 *		__( 'Posted a status update', 'buddypress' ),
	 *		'bp_activity_format_activity_action_activity_update',
	 *		__( 'Updates', 'buddypress' ),
	 *
	 *		// Notice the change on the second line!
	 *		array( 'activity', 'group', 'member', 'member_groups',
	 *                     'follow_button' => true, 'follow_type' => 'UNIQUE_ID' )
	 *	);
	 */

	// If in activity loop, use already-queried activity action
	if ( ! empty( $GLOBALS['activities_template']->in_the_loop ) ) {
		$action = bp_activity_get_action( bp_get_activity_object_name(), bp_get_activity_type() );

	// Manually query for the activity action of a given activity item
	} else {
		// Do not do anything if empty
		if ( empty( $activity_id ) ) {
			return false;
		}

		$activity = new BP_Activity_Activity( $activity_id );

		if ( empty( $activity->type ) ) {
			return false;
		}

		$action = bp_activity_get_action( $activity->component, $activity->type );
	}

	// Success!
	if ( isset( $action['follow_button'] ) && ! empty( $action['follow_button'] ) ) {
		$can_follow = true;

	// Fallback to activity commenting setting
	} else {
		$can_follow = bp_activity_can_comment();
	}

	return apply_filters( 'bp_follow_can_follow_activity', $can_follow, $action );
}

/**
 * Get the follow type associated with an activity item.
 *
 * If no custom follow type is set, falls back to the generic 'activity' type.
 *
 * @param  int $activity_id The activity ID to check.
 * @return string
 */
function bp_follow_activity_get_type( $activity_id = 0 ) {
	// If in activity loop, use already-queried activity action
	if ( ! empty( $GLOBALS['activities_template']->in_the_loop ) ) {
		$action = bp_activity_get_action( bp_get_activity_object_name(), bp_get_activity_type() );

	// Manually query for the activity action of a given activity item
	} else {
		// Do not do anything if empty
		if ( empty( $activity_id ) ) {
			return false;
		}

		$activity = new BP_Activity_Activity( $activity_id );

		if ( empty( $activity->type ) ) {
			return false;
		}

		$action = bp_activity_get_action( $activity->component, $activity->type );
	}

	return isset( $action['follow_type'] ) ? $action['follow_type'] : 'activity';
}

/**
 * Output a 'Follow' activity button.
 *
 * @param $args {
 *      Array of arguments.  Also see other args via {@link BP_Button} class.
 *      @type int  $leader_id           Activity ID to follow.
 *      @type int  $follower_id         User ID initiating the follow request.
 *      @type bool $show_follower_count Should we show the follower count for this item? Default: false.
 * }
 */
function bp_follow_activity_button( $args = array() ) {
	global $activities_template;

	$r = bp_parse_args( $args, array(
		'leader_id'     => ! empty( $activities_template->in_the_loop ) ? bp_get_activity_id() : 0,
		'follower_id'   => bp_loggedin_user_id(),
		'link_text'     => '',
		'link_title'    => '',
		'wrapper_class' => '',
		'link_class'    => 'button bp-primary-action',
		'wrapper'       => false,

		// follow-related args.
		'show_follower_count' => false,
	), 'follow_activity_button' );

	if ( ! $r['leader_id'] || ! $r['follower_id'] ) {
		return;
	}

	$follow_type = bp_follow_activity_get_type( $r['leader_id'] );

	// if we're checking during an activity loop, then follow status is already
	// queried via bulk_inject_follow_activity_status()
	if ( ! empty( $activities_template->in_the_loop ) && $r['follower_id'] == bp_loggedin_user_id() && $r['leader_id'] == bp_get_activity_id() && 'activity' === $follow_type ) {
		$is_following = $activities_template->activity->is_following;

	// else we manually query the follow status
	} else {
		$is_following = bp_follow_is_following( array(
			'leader_id'   => $r['leader_id'],
			'follower_id' => $r['follower_id'],
			'follow_type' => $follow_type,
		) );
	}

	// setup some variables.
	if ( $is_following ) {
		$id     = 'following';
		$action = 'unfollow';
		/* @todo Maybe bring back the count for the 'unfollow' button?
		$count  = bp_follow_get_the_followers_count( array(
			'object_id'   => $r['leader_id'],
			'follow_type' => $follow_type
		) );
		*/
		$count = 0;

		if ( empty( $count ) ) {
			$link_text = _x( 'Unfollow', 'Follow activity button', 'buddypress-followers' );
		} else {
			$link_text = sprintf( _x( 'Unfollow %s', 'Follow activity button', 'buddypress-followers' ), '<span>' . $count . '</span>' );
		}

		if ( empty( $r['link_text'] ) ) {
			$r['link_text'] = $link_text;
		}

	} else {
		$id     = 'not-following';
		$action = 'follow';

		$count = 0;
		if ( true === $r['show_follower_count'] ) {
			$count  = bp_follow_get_the_followers_count( array(
				'object_id'   => $r['leader_id'],
				'follow_type' => $follow_type,
			) );
		}

		if ( empty( $count ) ) {
			$link_text = _x( 'Follow', 'Follow activity button', 'buddypress-followers' );
		} else {
			$link_text = sprintf( _x( 'Follow %s', 'Follow activity button', 'buddypress-followers' ), '<span>' . $count . '</span>' );
		}

		if ( empty( $r['link_text'] ) ) {
			$r['link_text'] = $link_text;
		}
	}

	$wrapper_class = 'follow-button ' . $id;

	if ( ! empty( $r['wrapper_class'] ) ) {
		$wrapper_class .= ' ' . esc_attr( $r['wrapper_class'] );
	}

	$link_class = $action;

	if ( ! empty( $r['link_class'] ) ) {
		$link_class .= ' ' . esc_attr( $r['link_class'] );
	}

	// setup the button arguments.
	$button = array(
		'id'                => $id,
		'component'         => 'follow',
		'must_be_logged_in' => true,
		'block_self'        => false,
		'wrapper_class'     => $wrapper_class,
		'wrapper_id'        => 'follow-button-' . (int) $r['leader_id'],
		'link_href'         => wp_nonce_url(
			bp_follow_activity_get_directory_url( array( $action, array( esc_attr( $r['leader_id'] ) ) ) ),
			"bp_follow_activity_{$action}"
		),
		'link_text'         => $r['link_text'],
		'link_title'        => esc_attr( $r['link_title'] ),
		'link_id'           => $action . '-' . (int) $r['leader_id'],
		'link_class'        => $link_class,
		'wrapper'           => ! empty( $r['wrapper'] ) ? esc_attr( $r['wrapper'] ) : false,
	);

	// Filter and output the HTML button.
	bp_button( apply_filters( 'bp_follow_activity_get_follow_button', $button, $r, $is_following ) );
}
