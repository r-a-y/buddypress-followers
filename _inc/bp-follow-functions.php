<?php
/**
 * BP Follow Functions
 *
 * @package BP-Follow
 * @subpackage Functions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Builds a user's BP URL.
 *
 * @since 1.3.0
 *
 * @param int $user_id The user ID.
 * @param array $path_chunks A list of path chunks.
 * @return string The user's BP URL.
 */
function bp_follow_get_user_url( $user_id = 0, $path_chunks = array() ) {
	$user_url = '';

	if ( ! $user_id ) {
		return $user_url;
	}

	if ( function_exists( 'bp_core_get_query_parser' ) ) {
		$user_url = bp_members_get_user_url( $user_id, bp_members_get_path_chunks( $path_chunks ) );
	} else {
		$user_url = bp_core_get_user_domain( $user_id );

		if ( $path_chunks ) {
			$action_variables = end( $path_chunks );
			if ( is_array( $action_variables ) ) {
				array_pop( $path_chunks );
				$path_chunks = array_merge( $path_chunks, $action_variables );
			}

			$user_url = trailingslashit( $user_url ) . trailingslashit( implode( '/', $path_chunks ) );
		}
	}

	return $user_url;
}

/**
 * Start following an item.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int    $leader_id     The object ID we want to follow. Defaults to the displayed user ID.
 *     @type int    $follower_id   The object ID creating the request. Defaults to the logged-in user ID.
 *     @type string $follow_type   The follow type. Leave blank to follow users. Default: ''
 *     @type string $date_recorded The date that this relationship is to be recorded.
 * }
 * @return bool
 */
function bp_follow_start_following( $args = '' ) {

	$r = wp_parse_args( $args, array(
		'leader_id'     => bp_displayed_user_id(),
		'follower_id'   => bp_loggedin_user_id(),
		'follow_type'   => '',
		'date_recorded' => bp_core_current_time(),
	) );

	$follow = new BP_Follow( $r['leader_id'], $r['follower_id'], $r['follow_type'] );

	// existing follow already exists.
	if ( ! empty( $follow->id ) ) {
		return false;
	}

	// add other properties before save.
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
 * Stop following an item.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int    $leader_id     The object ID we want to stop following. Defaults to the displayed user ID.
 *     @type int    $follower_id   The object ID stopping the request. Defaults to the logged-in user ID.
 *     @type string $follow_type   The follow type. Leave blank for users. Default: ''
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

	if ( empty( $follow->id ) || ! $follow->delete() ) {
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
 * Check if an item is already following an item.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int    $leader_id   The object ID of the item we want to check. Defaults to the displayed user ID.
 *     @type int    $follower_id The object ID creating the request. Defaults to the logged-in user ID.
 *     @type string $follow_type The follow type. Leave blank for users. Default: ''
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
 * Fetch the IDs for the followers of a particular item.
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

	$r = bp_follow_get_common_args( wp_parse_args( $args, array(
		'user_id' => bp_displayed_user_id(),
	) ) );

	$retval   = array();
	$do_query = true;

	// Set up filter name.
	if ( ! empty( $r['follow_type'] ) ) {
		$filter = 'bp_follow_get_followers_' . $r['object'];
	} else {
		$filter = 'bp_follow_get_followers';
	}

	// check for cache if 'query_args' is empty.
	if ( empty( $r['query_args'] ) ) {
		$retval = wp_cache_get( $r['object_id'], "bp_follow_{$r['object']}_followers_query" );

		if ( false !== $retval ) {
			$do_query = false;
		}
	}

	// query if necessary.
	if ( true === $do_query ) {
		$retval = BP_Follow::get_followers( $r['object_id'], $r['follow_type'], $r['query_args'] );

		// cache if no extra query args - we only cache default args for now.
		if ( empty( $r['query_args'] ) ) {
			wp_cache_set( $r['object_id'], $retval, "bp_follow_{$r['object']}_followers_query" );

			// cache count while we're at it.
			wp_cache_set( $r['object_id'], $GLOBALS['wpdb']->num_rows, "bp_follow_{$r['object']}_followers_count" );
		}
	}

	/**
	 * Dynamic filter for followers query.
	 *
	 * By default, the filter name is 'bp_follow_get_followers', which filters
	 * the displayed user's followers.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Filter is now dynamic.
	 *
	 * @param array $retval Array of follower IDs.
	 */
	return apply_filters( $filter, $retval );
}

/**
 * Fetch the IDs that a particular item is following.
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

	$r = bp_follow_get_common_args( wp_parse_args( $args, array(
		'user_id' => bp_displayed_user_id(),
	) ) );

	$retval   = array();
	$do_query = true;

	// setup some variables based on the follow type.
	if ( ! empty( $r['follow_type'] ) ) {
		$filter = 'bp_follow_get_following_' . $r['object'];
	} else {
		$filter = 'bp_follow_get_following';
	}

	// check for cache if 'query_args' is empty.
	if ( empty( $r['query_args'] ) ) {
		$retval = wp_cache_get( $r['object_id'], "bp_follow_{$r['object']}_following_query" );

		if ( false !== $retval ) {
			$do_query = false;
		}
	}

	// query if necessary.
	if ( true === $do_query ) {
		$retval = BP_Follow::get_following( $r['object_id'], $r['follow_type'], $r['query_args'] );

		// cache if no extra query args - we only cache default args for now.
		if ( empty( $r['query_args'] ) ) {
			wp_cache_set( $r['object_id'], $retval, "bp_follow_{$r['object']}_following_query" );

			// cache count while we're at it.
			wp_cache_set( $r['object_id'], $GLOBALS['wpdb']->num_rows, "bp_follow_{$r['object']}_following_count" );
		}
	}

	/**
	 * Dynamic filter for following query.
	 *
	 * By default, the filter name is 'bp_follow_get_following', which filters
	 * the displayed user's following.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Filter is now dynamic.
	 *
	 * @param array $retval Array of follower IDs.
	 */
	return apply_filters( $filter, $retval );
}

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
			'user_id' => bp_displayed_user_id(),
		) );

		$ids = implode( ',', (array) bp_follow_get_followers( array(
			'user_id' => $r['user_id'],
		) ) );

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

/**
 * Get the total followers and total following counts for a user.
 *
 * You shouldn't really use this function any more.
 *
 * @see bp_follow_get_the_following_count() To grab the following count.
 * @see bp_follow_get_the_followers_count() To grab the followers count.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int    $user_id     The user ID to grab follow counts for.
 *     @type string $follow_type The follow type. Default to '', which will query follow counts for users.
 *                               Passing a follow type such as 'blogs' will only return a 'following'
 *                               key and integer zero for the 'followers' key since a user can only follow
 *                               blogs.
 * }
 * @return array [ followers => int, following => int ]
 */
function bp_follow_total_follow_counts( $args = '' ) {
	$r = wp_parse_args( $args, array(
		'user_id'     => bp_loggedin_user_id(),
		'follow_type' => '',
	) );

	$retval = array();

	$retval['following'] = bp_follow_get_the_following_count( array(
		'user_id'     => $r['user_id'],
		'follow_type' => $r['follow_type'],
	) );

	/**
	 * Passing a follow type such as 'blogs' will only return a 'following'
	 * key and integer zero for the 'followers' key since a user can only follow
	 * blogs.
	 */
	if ( ! empty( $r['follow_type'] ) ) {
		$retval['followers'] = 0;
	} else {
		$retval['followers'] = bp_follow_get_the_followers_count( array(
			'user_id'     => $r['user_id'],
			'follow_type' => $r['follow_type'],
		) );
	}

	if ( empty( $r['follow_type'] ) ) {
		/**
		 * Filter the total follow counts for a user.
		 *
		 * @since 1.0.0
		 *
		 * @param array $retval  Array consisting of 'following' and 'followers' counts.
		 * @param int   $user_id The user ID. Defaults to logged-in user ID.
		 */
		$retval = apply_filters( 'bp_follow_total_follow_counts', $retval, $r['user_id'] );
	} else {
		/**
		 * Filter the total follow counts for a user given a specific follow type.
		 *
		 * @since 1.3.0
		 *
		 * @param array $retval  Array consisting of 'following' and 'followers' counts. Note: 'followers'
		 *                       is always going to be 0, since a user can only follow a given follow type.
		 * @param int   $user_id The user ID. Defaults to logged-in user ID.
		 */
		$retval = apply_filters( 'bp_follow_total_follow_' . $r['follow_type'] . '_counts', $retval, $r['user_id'] );
	}

	return $retval;
}

/**
 * Get the following count for a particular item.
 *
 * Defaults to the number of users the logged-in user is following.
 *
 * @since 1.3.0
 *
 * @param  array $args See bp_follow_get_common_args().
 * @return int
 */
function bp_follow_get_the_following_count( $args = array() ) {
	$r = bp_follow_get_common_args( $args );

	// fetch cache.
	$retval = wp_cache_get( $r['object_id'], "bp_follow_{$r['object']}_following_count" );

	// query if necessary.
	if ( false === $retval ) {
		$retval = BP_Follow::get_following_count( $r['object_id'], $r['follow_type'] );
		wp_cache_set( $r['object_id'], $retval, "bp_follow_{$r['object']}_following_count" );
	}

	/**
	 * Dynamic filter for the following count.
	 *
	 * Defaults to 'bp_follow_get_user_following_count'.
	 *
	 * @since 1.3.0
	 *
	 * @param int $retval    The following count.
	 * @param int $object_id The object ID.  Defaults to logged-in user ID.
	 */
	return apply_filters( "bp_follow_get_{$r['object']}_following_count", $retval, $r['object_id'] );
}

/**
 * Get the followers count for a particular item.
 *
 * Defaults to the number of users following the logged-in user.
 *
 * @since 1.3.0
 *
 * @param  array $args See bp_follow_get_common_args().
 * @return int
 */
function bp_follow_get_the_followers_count( $args = array() ) {
	$r = bp_follow_get_common_args( $args );

	// fetch cache.
	$retval = wp_cache_get( $r['object_id'], "bp_follow_{$r['object']}_followers_count" );

	// query if necessary.
	if ( false === $retval ) {
		$retval = BP_Follow::get_followers_count( $r['object_id'], $r['follow_type'] );
		wp_cache_set( $r['object_id'], $retval, "bp_follow_{$r['object']}_followers_count" );
	}

	/**
	 * Dynamic filter for the followers count.
	 *
	 * Defaults to 'bp_follow_get_user_followers_count'.
	 *
	 * @since 1.3.0
	 *
	 * @param int $retval    The followers count.
	 * @param int $object_id The object ID.  Defaults to logged-in user ID.
	 */
	return apply_filters( "bp_follow_get_{$r['object']}_followers_count", $retval, $r['object_id'] );
}

/**
 * Utility function to parse common arguments.
 *
 * Used quite a bit internally.
 *
 * @since 1.3.0
 *
 * @param array $args {
 *     Array of arguments.
 *     @type int    $user_id     The user ID. Defaults to logged-in user ID.
 *     @type int    $object_id   The object ID. If filled in, this takes precedence over the $user_id
 *                               parameter. Handy when using a different $follow_type. Default: ''.
 *     @type string $follow_type The follow type. Leave blank to query for users. Default: ''.
 *     @type array  $query_args  Query arguments. Only used when querying.
 * }
 * @return array
 */
function bp_follow_get_common_args( $args = array() ) {
	$r = wp_parse_args( $args, array(
		'user_id'     => bp_loggedin_user_id(),
		'follow_type' => '',
		'object_id'   => '',
		'query_args'  => array(),
	) );

	// Set up our object. $object is used for cache keys and filter names.
	if ( ! empty( $r['follow_type'] ) ) {
		// Append 'user' to the $object if a user ID is passed.
		if ( ! empty( $r['user_id'] ) && empty( $r['object_id'] ) ) {
			$object = "user_{$r['follow_type']}";
		} else {
			$object = $r['follow_type'];
		}

	// Defaults to 'user'.
	} else {
		$object = 'user';
	}

	if ( ! empty( $r['object_id'] ) ) {
		$object_id = (int) $r['object_id'];
	} else {
		$object_id = (int) $r['user_id'];
	}

	return array(
		'object'      => $object,
		'object_id'   => $object_id,
		'follow_type' => $r['follow_type'],
		'query_args'  => $r['query_args'],
	);
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
 * Show a 'Follow' block on a user's "Settings > Email" page.
 *
 * Used internally only.
 *
 * @since 1.3.0
 */
function bp_follow_notification_settings_content() {
?>
	<table class="notification-settings" id="follow-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php esc_html_e( 'Follow', 'buddypress-followers' ); ?></th>
				<th class="yes"><?php esc_html_e( 'Yes', 'buddypress-followers' ); ?></th>
				<th class="no"><?php esc_html_e( 'No', 'buddypress-followers' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php do_action( 'bp_follow_screen_notification_settings' ); ?>
		</tbody>
	</table>
<?php
}

/**
 * Format on screen notifications into something readable by users.
 */
function bp_follow_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {

	$bp = $GLOBALS['bp'];

	do_action( 'bp_follow_format_notifications', $action, $item_id, $secondary_item_id, $total_items, $format );

	switch ( $action ) {
		case 'new_follow':
			$text = false;
			$link = $text;

			if ( 1 === $total_items ) {
				$text = sprintf( __( '%s is now following you', 'buddypress-followers' ), bp_core_get_user_displayname( $item_id ) );
				$link = add_query_arg( 'bpf_read', 1, bp_follow_get_user_url( $item_id ) );

			} else {
				$text = sprintf( __( '%d more users are now following you', 'buddypress-followers' ), $total_items );

				if ( bp_is_active( 'notifications' ) ) {
					$link = bp_get_notifications_permalink();

					// filter notifications by 'new_follow' action.
					if ( version_compare( BP_VERSION, '2.0.9' ) >= 0 ) {
						$link = add_query_arg( 'action', $action, $link );
					}
				} else {
					$link = add_query_arg( 'new', 1, bp_follow_get_user_url( bp_loggedin_user_id(), array( $bp->follow->followers->slug ) ) );
				}
			}

			break;

		default:
			$link = apply_filters( 'bp_follow_extend_notification_link', false, $action, $item_id, $secondary_item_id, $total_items );
			$text = apply_filters( 'bp_follow_extend_notification_text', false, $action, $item_id, $secondary_item_id, $total_items );
			break;
	}

	if ( ! $link || ! $text ) {
		return false;
	}

	if ( 'string' === $format ) {
		return apply_filters( 'bp_follow_new_followers_notification', '<a href="' . $link . '">' . $text . '</a>', $total_items, $link, $text, $item_id, $secondary_item_id );

	} else {
		$array = array(
			'text' => $text,
			'link' => $link,
		);

		return apply_filters( 'bp_follow_new_followers_return_notification', $array, $item_id, $secondary_item_id, $total_items );
	}
}
