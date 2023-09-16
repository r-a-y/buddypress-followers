<?php
/**
 * BP Follow Hooks
 *
 * Functions in this file allow this component to hook into BuddyPress so it
 * interacts seamlessly with the interface and existing core components.
 *
 * @package BP-Follow
 * @subpackage Hooks
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** USER NAV *************************************************************/

/**
 * Setup profile / BuddyBar navigation.
 *
 * This function was moved from {@link BP_Follow_Component} in v1.3.0 due
 * to the users module being toggleable.
 *
 * @since 1.3.0
 */
function bp_follow_user_setup_nav( $main_nav = array(), $sub_nav = array() ) {
	$bp = $GLOBALS['bp'];

	// If we're in the admin area and we're using the WP toolbar, we don't need
	// to run the rest of this method.
	if ( defined( 'WP_NETWORK_ADMIN' ) && bp_use_wp_admin_bar() ) {
		return;
	}

	// Need to change the user ID, so if we're not on a member page, $counts variable is still calculated.
	$user_id = bp_is_user() ? bp_displayed_user_id() : bp_loggedin_user_id();

	/** FOLLOWING NAV ************************************************/

	bp_core_new_nav_item(
		array(
			'name'                => sprintf(
				__( 'Following %s', 'buddypress-followers' ),
				sprintf(
					'<span class="count">%s</span>',
					esc_html( bp_core_number_format( bp_follow_get_the_following_count( array( 'user_id' => $user_id ) ) ) )
				)
			),
			'slug'                => $bp->follow->following->slug,
			'position'            => $bp->follow->params['adminbar_myaccount_order'],
			'screen_function'     => 'bp_follow_screen_following',
			'default_subnav_slug' => 'following',
			'item_css_id'         => 'members-following',
		)
	);

	/** FOLLOWERS NAV ************************************************/

	bp_core_new_nav_item(
		array(
			'name'                => sprintf(
				__( 'Followers %s', 'buddypress-followers' ),
				sprintf(
					'<span class="count">%s</span>',
					esc_html( bp_core_number_format( bp_follow_get_the_followers_count( array( 'user_id' => $user_id ) ) ) )
				)
			),
			'slug'                => $bp->follow->followers->slug,
			'position'            => apply_filters( 'bp_follow_followers_nav_position', 62 ),
			'screen_function'     => 'bp_follow_screen_followers',
			'default_subnav_slug' => 'followers',
			'item_css_id'         => 'members-followers',
		)
	);

	/** ACTIVITY SUBNAV **********************************************/

	// Add activity sub nav item.
	if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_show_activity_subnav', true ) ) {
		bp_core_new_subnav_item(
			array(
				'name'            => _x( 'Following', 'Activity subnav tab', 'buddypress-followers' ),
				'slug'            => constant( 'BP_FOLLOWING_SLUG' ),
				'parent_url'      => bp_follow_get_user_url( $user_id, array( bp_get_activity_slug() ) ),
				'parent_slug'     => bp_get_activity_slug(),
				'screen_function' => 'bp_follow_screen_activity_following',
				'position'        => 21,
				'item_css_id'     => 'activity-following',
			)
		);
	}

	// BuddyBar compatibility.
	add_action( 'bp_adminbar_menus', 'bp_follow_group_buddybar_items' );
}
add_action( 'bp_follow_setup_nav', 'bp_follow_user_setup_nav', 10, 2 );

/**
 * Set up WP Toolbar / Admin Bar.
 *
 * This function was moved from {@link BP_Follow_Component} in v1.3.0 due
 * to the users module being toggleable.
 *
 * @since 1.3.0
 */
function bp_follow_user_setup_toolbar() {

	if ( ! is_user_logged_in() ) {
		return;
	}

	global $wp_admin_bar, $bp;

	$following_url = bp_follow_get_user_url( bp_loggedin_user_id(), array( $bp->follow->following->slug ) );

	// "Follow" parent nav menu
	$wp_admin_nav[] = array(
		'parent' => $bp->my_account_menu_id,
		'id'     => 'my-account-' . $bp->follow->id,
		'title'  => _x( 'Follow', 'Adminbar main nav', 'buddypress-followers' ),
		'href'   => $following_url,
	);

	// "Following" subnav item
	$wp_admin_nav[] = array(
		'parent' => 'my-account-' . $bp->follow->id,
		'id'     => 'my-account-' . $bp->follow->id . '-following',
		'title'  => _x( 'Following', 'Adminbar follow subnav', 'buddypress-followers' ),
		'href'   => $following_url,
	);

	// "Followers" subnav item
	$wp_admin_nav[] = array(
		'parent' => 'my-account-' . $bp->follow->id,
		'id'     => 'my-account-' . $bp->follow->id . '-followers',
		'title'  => _x( 'Followers', 'Adminbar follow subnav', 'buddypress-followers' ),
		'href'   => bp_follow_get_user_url( bp_loggedin_user_id(), array( $bp->follow->followers->slug ) ),
	);

	// Add each admin menu.
	foreach ( apply_filters( 'bp_follow_toolbar', $wp_admin_nav ) as $admin_menu ) {
		$wp_admin_bar->add_menu( $admin_menu );
	}
}
add_action( 'bp_follow_setup_admin_bar', 'bp_follow_user_setup_toolbar' );

/**
 * Inject "Following" nav item to WP adminbar's "Activity" main nav.
 *
 * This function was moved from {@link BP_Follow_Component} in v1.3.0 due
 * to the users module being toggleable.
 *
 * @param array $retval
 * @return array
 */
function bp_follow_user_activity_admin_nav_toolbar( $retval ) {

	if ( ! is_user_logged_in() ) {
		return $retval;
	}

	if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_show_activity_subnav', true ) ) {
		$new_item = array(
			'parent' => 'my-account-activity',
			'id'     => 'my-account-activity-following',
			'title'  => _x( 'Following', 'Adminbar activity subnav', 'buddypress-followers' ),
			'href'   => bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOWING_SLUG' ) ) ),
		);

		$inject = array();
		$offset = 3;

		$inject[ $offset ] = $new_item;
		$retval = array_merge(
			array_slice( $retval, 0, $offset, true ),
			$inject,
			array_slice( $retval, $offset, null, true )
		);
	}

	return $retval;
}
add_action( 'bp_activity_admin_nav', 'bp_follow_user_activity_admin_nav_toolbar' );

/**
 * Groups follow nav items together in the BuddyBar.
 *
 * For BP Follow, we use separate nav items for the "Following" and
 * "Followers" pages, but for the BuddyBar, we want to group them together.
 *
 * Because of the way BuddyPress renders both the BuddyBar and profile nav
 * with the same code, to alter just the BuddyBar, you need to resort to
 * hacking the $bp global later on.
 *
 * This will probably break in future versions of BP, when that happens we'll
 * remove this entirely.
 *
 * If the WP Toolbar is in use, this method is skipped.
 *
 * This function was moved from {@link BP_Follow_Component} in v1.3.0 due
 * to the users module being toggleable.
 *
 * @since 1.3.0
 */
function bp_follow_group_buddybar_items() {
	// don't do this if we're using the WP Admin Bar / Toolbar.
	if ( defined( 'BP_USE_WP_ADMIN_BAR' ) && BP_USE_WP_ADMIN_BAR ) {
		return;
	}

	if ( ! bp_loggedin_user_id() ) {
		return;
	}

	$bp = $GLOBALS['bp'];

	// get follow nav positions.
	$following_position = $bp->follow->params['adminbar_myaccount_order'];
	$followers_position = apply_filters( 'bp_follow_followers_nav_position', 62 );

	// clobberin' time!
	unset( $bp->bp_nav[ $following_position ] );
	unset( $bp->bp_nav[ $followers_position ] );
	unset( $bp->bp_options_nav['following'] );
	unset( $bp->bp_options_nav['followers'] );

	$following_url = bp_follow_get_user_url( bp_loggedin_user_id(), array( $bp->follow->following->slug ) );

	// Add the "Follow" nav menu.
	$bp->bp_nav[ $following_position ] = array(
		'name'                    => _x( 'Follow', 'Adminbar main nav', 'buddypress-followers' ),
		'link'                    => $following_url,
		'slug'                    => 'follow',
		'css_id'                  => 'follow',
		'position'                => $following_position,
		'show_for_displayed_user' => 1,
		'screen_function'         => 'bp_follow_screen_followers',
	);

	// "Following" subnav item
	$bp->bp_options_nav['follow'][10] = array(
		'name'            => _x( 'Following', 'Adminbar follow subnav', 'buddypress-followers' ),
		'link'            => $following_url,
		'slug'            => $bp->follow->following->slug,
		'css_id'          => 'following',
		'position'        => 10,
		'user_has_access' => 1,
		'screen_function' => 'bp_follow_screen_followers',
	);

	// "Followers" subnav item
	$bp->bp_options_nav['follow'][20] = array(
		'name'            => _x( 'Followers', 'Adminbar follow subnav', 'buddypress-followers' ),
		'link'            => bp_follow_get_user_url( bp_loggedin_user_id(), array( $bp->follow->followers->slug ) ),
		'slug'            => $bp->follow->followers->slug,
		'css_id'          => 'followers',
		'position'        => 20,
		'user_has_access' => 1,
		'screen_function' => 'bp_follow_screen_followers',
	);

	// Resort the nav items to account for the late change made above.
	ksort( $bp->bp_nav );
}

/** LOOP INJECTION *******************************************************/

/**
 * Inject $members_template global with follow status for each member in the
 * members loop.
 *
 * Once the members loop has queried and built a $members_template object,
 * fetch all of the member IDs in the object and bulk fetch the following
 * status for all the members in one query.
 *
 * This is significantly more efficient that querying for every member inside
 * of the loop.
 *
 * @since 1.0
 * @todo Use {@link BP_User_Query} introduced in BP 1.7 in a future version
 *
 * @global $members_template The members template object containing all fetched members in the loop
 * @uses BP_Follow::bulk_check_follow_status() Check the following status for more than one member
 * @param string $has_members Whether any members where actually returned in the loop.
 * @return $has_members Return the original $has_members param as this is a filter function.
 */
function bp_follow_inject_member_follow_status( $has_members ) {
	global $members_template;

	if ( empty( $has_members ) ) {
		return $has_members;
	}

	$user_ids = array();

	foreach ( (array) $members_template->members as $i => $member ) {
		if ( bp_loggedin_user_id() !== $member->id ) {
			$user_ids[] = $member->id;
		}

		$members_template->members[ $i ]->is_following = false;
	}

	if ( empty( $user_ids ) ) {
		return $has_members;
	}

	$following = BP_Follow::bulk_check_follow_status( $user_ids );

	if ( empty( $following ) ) {
		return $has_members;
	}

	foreach ( (array) $following as $is_following ) {
		foreach ( (array) $members_template->members as $i => $member ) {
			if ( $is_following->leader_id === $member->id ) {
				$members_template->members[ $i ]->is_following = true;
			}
		}
	}

	return $has_members;
}
add_filter( 'bp_has_members', 'bp_follow_inject_member_follow_status' );

/**
 * Inject $members_template global with follow status for each member in the
 * group members loop.
 *
 * Once the group members loop has queried and built a $members_template
 * object, fetch all of the member IDs in the object and bulk fetch the
 * following status for all the group members in one query.
 *
 * This is significantly more efficient that querying for every member inside
 * of the loop.
 *
 * @author r-a-y
 * @since 1.1
 *
 * @global $members_template The members template object containing all fetched members in the loop
 * @uses BP_Follow::bulk_check_follow_status() Check the following status for more than one member
 * @param string $has_members - Whether any members where actually returned in the loop.
 * @return $has_members - Return the original $has_members param as this is a filter function.
 */
function bp_follow_inject_group_member_follow_status( $has_members ) {
	global $members_template;

	if ( empty( $has_members ) ) {
		return $has_members;
	}

	$user_ids = array();

	foreach ( (array) $members_template->members as $i => $member ) {
		if ( bp_loggedin_user_id() !== $member->user_id ) {
			$user_ids[] = $member->user_id;
		}

		$members_template->members[ $i ]->is_following = false;
	}

	if ( empty( $user_ids ) ) {
		return $has_members;
	}

	$following = BP_Follow::bulk_check_follow_status( $user_ids );

	if ( empty( $following ) ) {
		return $has_members;
	}

	foreach ( (array) $following as $is_following ) {
		foreach ( (array) $members_template->members as $i => $member ) {
			if ( $is_following->leader_id === $member->user_id ) {
				$members_template->members[ $i ]->is_following = true;
			}
		}
	}

	return $has_members;
}
add_filter( 'bp_group_has_members', 'bp_follow_inject_group_member_follow_status' );

/** BUTTONS **************************************************************/

/**
 * Add a "Follow User/Stop Following" button to the profile header for a user.
 *
 * @uses bp_follow_is_following() Check the following status for a user
 * @uses bp_is_my_profile() Return true if you are looking at your own profile when logged in.
 * @uses is_user_logged_in() Return true if you are logged in.
 */
function bp_follow_add_profile_follow_button() {
	if ( bp_is_my_profile() ) {
		return;
	}

	bp_follow_add_follow_button();
}
add_action( 'bp_member_header_actions', 'bp_follow_add_profile_follow_button' );

/**
 * Add a "Follow User/Stop Following" button to each member shown in the
 * members loop.
 *
 * @global $members_template The members template object containing all fetched members in the loop
 * @uses is_user_logged_in() Return true if you are logged in.
 */
function bp_follow_add_listing_follow_button() {
	global $members_template;

	if ( bp_loggedin_user_id() === $members_template->member->id ) {
		return;
	}

	bp_follow_add_follow_button( 'leader_id=' . $members_template->member->id );
}
add_action( 'bp_directory_members_actions', 'bp_follow_add_listing_follow_button' );

/**
 * Add a "Follow User/Stop Following" button to each member shown in a group
 * members loop.
 *
 * @author r-a-y
 * @since 1.1
 *
 * @global $members_template The members template object containing all fetched members in the loop
 */
function bp_follow_add_group_member_follow_button() {
	global $members_template;

	if ( bp_loggedin_user_id() === $members_template->member->user_id || ! bp_loggedin_user_id() ) {
		return;
	}

	bp_follow_add_follow_button( 'leader_id=' . $members_template->member->user_id );
}
add_action( 'bp_group_members_list_item_action', 'bp_follow_add_group_member_follow_button' );

/** CACHE / DELETION ****************************************************/

/**
 * Set up global cachegroups for users module in BP Follow.
 *
 * @since 1.3.0
 */
function bp_follow_users_setup_global_cachegroups() {
	$bp = $GLOBALS['bp'];

	// user counts.
	$bp->follow->global_cachegroups[] = 'bp_follow_user_followers_count';
	$bp->follow->global_cachegroups[] = 'bp_follow_user_following_count';

	// user data query.
	$bp->follow->global_cachegroups[] = 'bp_follow_followers';
	$bp->follow->global_cachegroups[] = 'bp_follow_following';
}
add_action( 'bp_follow_setup_globals', 'bp_follow_users_setup_global_cachegroups' );

/**
 * Removes follow relationships for all users from a user who is deleted or spammed
 *
 * @since 1.0.0
 *
 * @uses BP_Follow::delete_all_for_user() Deletes user ID from all following / follower records
 */
function bp_follow_remove_data( $user_id ) {
	do_action( 'bp_follow_before_remove_data', $user_id );

	BP_Follow::delete_all_for_user( $user_id );

	do_action( 'bp_follow_remove_data', $user_id );
}
add_action( 'wpmu_delete_user', 'bp_follow_remove_data' );
add_action( 'delete_user', 'bp_follow_remove_data' );
add_action( 'make_spam_user', 'bp_follow_remove_data' );

/**
 * Clear cache when a user follows / unfollows another user.
 *
 * @since 1.3.0
 *
 * @param BP_Follow $follow
 */
function bp_follow_clear_cache_on_follow( BP_Follow $follow ) {
	// clear follow cache.
	wp_cache_delete( $follow->leader_id,   'bp_follow_user_followers_count' );
	wp_cache_delete( $follow->follower_id, 'bp_follow_user_following_count' );
	wp_cache_delete( $follow->leader_id,   'bp_follow_user_followers_query' );
	wp_cache_delete( $follow->follower_id, 'bp_follow_user_following_query' );

	// clear follow relationship.
	wp_cache_delete( "{$follow->leader_id}:{$follow->follower_id}:", 'bp_follow_data' );
}
add_action( 'bp_follow_start_following', 'bp_follow_clear_cache_on_follow' );
add_action( 'bp_follow_stop_following',  'bp_follow_clear_cache_on_follow' );

/**
 * Clear follow cache when a user is deleted.
 *
 * @since 1.3.0
 *
 * @param int $user_id The ID of the user being deleted.
 */
function bp_follow_clear_cache_on_user_delete( $user_id ) {
	// delete follow cache.
	wp_cache_delete( $user_id, 'bp_follow_user_following_count' );
	wp_cache_delete( $user_id, 'bp_follow_user_followers_count' );
	wp_cache_delete( $user_id, 'bp_follow_user_following_query' );
	wp_cache_delete( $user_id, 'bp_follow_user_followers_query' );

	// delete each user's followers count that the user was following.
	$users = BP_Follow::get_following( $user_id );
	if ( ! empty( $users ) ) {
		foreach ( $users as $user ) {
			wp_cache_delete( $user, 'bp_follow_user_followers_count' );

			// clear follow relationship.
			wp_cache_delete( "{$user_id}:{$user}:", 'bp_follow_data' );
		}
	}
}
add_action( 'bp_follow_before_remove_data', 'bp_follow_clear_cache_on_user_delete' );

/** DIRECTORIES **********************************************************/

/**
 * Adds a "Following (X)" tab to the activity directory.
 *
 * This is so the logged-in user can filter the activity stream to only users
 * that the current user is following.
 *
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 */
function bp_follow_add_activity_tab() {

	$count = bp_follow_get_the_following_count();

	if ( empty( $count ) ) {
		return;
	}

	$follow_activity_url = bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOWING_SLUG' ) ) );
?>

	<li id="activity-following"><a href="<?php echo esc_url( $follow_activity_url ); ?>" title="<?php esc_html_e( 'The public activity for everyone you are following on this site.', 'buddypress-followers' ) ?>"><?php printf( esc_html__( 'Following %s', 'buddypress-followers' ), '<span>' . esc_html( bp_core_number_format( $count ) ) . '</span>' ); ?></a></li>

<?php
}
add_action( 'bp_before_activity_type_tab_friends', 'bp_follow_add_activity_tab' );

/**
 * Add a "Following (X)" tab to the members directory.
 *
 * This is so the logged-in user can filter the members directory to only
 * users that the current user is following.
 *
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 */
function bp_follow_add_following_tab() {

	if ( ! is_user_logged_in() ) {
		return;
	}

	$count = bp_follow_get_the_following_count();

	if ( empty( $count ) ) {
		return;
	}

	$following_url = bp_follow_get_user_url( bp_loggedin_user_id(), array( constant( 'BP_FOLLOWING_SLUG' ) ) );
?>

	<li id="members-following"><a href="<?php echo esc_url( $following_url ); ?>"><?php printf( esc_html__( 'Following %s', 'buddypress-followers' ), '<span>' . esc_html( bp_core_number_format( $count ) ) . '</span>' ); ?></a></li>

<?php
}
add_action( 'bp_members_directory_member_types', 'bp_follow_add_following_tab' );

/** USER QUERY ***********************************************************/

/**
 * Override the BP User Query when our special follow type is in use.
 *
 * @since 1.3.0
 *
 * @param BP_User_Query $q
 */
function bp_follow_pre_user_query( $q ) {
	if ( 'oldest-follows' !== $q->query_vars['type'] && 'newest-follows' !== $q->query_vars['type'] ) {
		return;
	}

	$q->total_users = count( $q->query_vars['include'] );

	// oldest follows.
	if ( 'oldest-follows' === $q->query_vars['type'] ) {
		// flip the order.
		$q->query_vars['user_ids'] = array_reverse( wp_parse_id_list( $q->query_vars['include'] ) );

	// newest follows.
	} elseif ( 'newest-follows' === $q->query_vars['type'] ) {
		$q->query_vars['user_ids'] = $q->query_vars['include'];
	}

	// Manual pagination. Eek!
	if ( ! empty( $q->query_vars['page'] ) ) {
		$q->query_vars['user_ids'] = array_splice( $q->query_vars['user_ids'], $q->query_vars['per_page'] * ( $q->query_vars['page'] - 1 ), $q->query_vars['per_page'] );
	}
}
add_action( 'bp_pre_user_query_construct', 'bp_follow_pre_user_query' );

/** AJAX MANIPULATION ****************************************************/

/**
 * Set up activity arguments for use with the 'following' scope.
 *
 * For details on the syntax, see {@link BP_Activity_Query}.
 *
 * Only applicable to BuddyPress 2.2+.  Older BP installs uses the code
 * available in /backpat/activity-scope.php.
 *
 * @since 1.3.0
 *
 * @param array $retval Empty array by default.
 * @param array $filter Current activity arguments.
 * @return array
 */
function bp_follow_users_filter_activity_scope( $retval = array(), $filter = array() ) {
	$bp = $GLOBALS['bp'];

	// Determine the user_id.
	if ( ! empty( $filter['user_id'] ) ) {
		$user_id = $filter['user_id'];
	} else {
		$user_id = bp_displayed_user_id()
			? bp_displayed_user_id()
			: bp_loggedin_user_id();
	}

	// Determine friends of user.
	$following_ids = bp_follow_get_following( array(
		'user_id' => $user_id,
	) );
	if ( empty( $following_ids ) ) {
		$following_ids = array( 0 );
	}

	/**
	 * Since BP Follow supports down to BP 1.5, BP 1.5 lacks the third parameter
	 * for the 'bp_has_activities' filter. So we must resort to this to mark that
	 * our 'following' scope is in effect
	 *
	 * Primarily used to alter the 'no activity found' text.
	 */
	$bp->follow->activity_scope_set = 1;

	$retval = array(
		'relation' => 'AND',
		array(
			'column'  => 'user_id',
			'compare' => 'IN',
			'value'   => (array) $following_ids,
		),

		// we should only be able to view sitewide activity content for those the user
		// is following.
		array(
			'column' => 'hide_sitewide',
			'value'  => 0,
		),

		// overrides.
		'override' => array(
			'filter'      => array(
				'user_id' => 0,
			),
			'show_hidden' => true,
		),
	);

	return $retval;
}
add_filter( 'bp_activity_set_following_scope_args', 'bp_follow_users_filter_activity_scope', 10, 2 );

/**
 * Filter the members loop on a follow page.
 *
 * This is done so we can return the users that:
 *   - the current user is following (on a user page or member directory); or
 *   - are following the displayed user on the displayed user's followers page
 *
 * @author r-a-y
 * @since 1.2
 *
 * @param array|string $qs     The querystring for the BP loop.
 * @param str          $object The current object for the querystring.
 * @return array|string Modified querystring
 */
function bp_follow_add_member_scope_filter( $qs, $object ) {

	// not on the members object? stop now!
	if ( 'members' !== $object ) {
		return $qs;
	}

	// Parse querystring into array.
	$r = wp_parse_args( $qs );

	$set = false;

	// members directory
	// can't use bp_is_members_directory() yet since that's a BP 2.0 function.
	if ( ! bp_is_user() && bp_is_members_component() ) {
		// Check for existing scope.
		$scope = ! empty( $r['scope'] ) && 'following' === $r['scope'] ? true : false;

		// check if members scope is following before manipulating.
		if ( $scope || ( isset( $_COOKIE['bp-members-scope'] ) && 'following' === $_COOKIE['bp-members-scope'] ) ) {
			$set = true;
			$action = 'following';
		}

	// user page
	} elseif ( bp_is_user() ) {
		$set = true;
		$action = bp_current_action();
	}

	// not on a user page? stop now!
	if ( ! $set ) {
		return $qs;
	}

	// filter the members loop based on the current page.
	switch ( $action ) {
		case 'following':
			$r['include'] = bp_follow_get_following( array(
				'user_id' => bp_displayed_user_id() ? bp_displayed_user_id() : bp_loggedin_user_id(),
			) );

			break;

		case 'followers':
			$r['include'] = bp_follow_get_followers();

			break;
	}

	if ( in_array( $action, array( 'following', 'followers' ), true ) && ! $r['include'] ) {
		$r['include'] = array( 0 );
	}

	/**
	 * Number of users to display on a user's Following or Followers page.
	 *
	 * @since 1.2.2
	 *
	 * @param int $retval
	 */
	$r['per_page'] = apply_filters( 'bp_follow_per_page', 20 );

	return $r;
}
add_filter( 'bp_ajax_querystring', 'bp_follow_add_member_scope_filter', 20, 2 );

/**
 * Set pagination parameters when on a user Follow page for Nouveau.
 *
 * Nouveau has its own pagination routine...
 *
 * @since 1.3.0
 *
 * @param  array  $r    Current pagination arguments.
 * @param  string $type Pagination type.
 * @return array
 */
function bp_follow_set_pagination_for_nouveau( $r, $type ) {
	if ( $GLOBALS['bp']->follow->following->slug !== $type && $GLOBALS['bp']->follow->followers->slug !== $type ) {
		return $r;
	}

	return array(
		'pag_count' => bp_get_members_pagination_count(),
		'pag_links' => bp_get_members_pagination_links(),
		'page_arg'  => $GLOBALS['members_template']->pag_arg
	);
}
add_filter( 'bp_nouveau_pagination_params', 'bp_follow_set_pagination_for_nouveau', 10, 2 );

/**
 * Set some default parameters for a member loop.
 *
 * If we're on a user's following or followers page, set the member filter
 * so users are sorted by newest follows instead of last active.
 *
 * If we're on a user's friends page or the members directory, reset the
 * members filter to last active.
 *
 * Only applicable for BuddyPress 1.7+.
 *
 * @since 1.3.0
 *
 * @see bp_follow_add_members_dropdown_filter()
 */
function bp_follow_set_members_scope_default() {
	// don't do this for older versions of BP.
	if ( ! class_exists( 'BP_User_Query' ) ) {
		return;
	}

	// set default members filter to 'newest-follows' on member follow pages.
	if ( bp_is_user() && ( bp_is_current_action( 'following' ) || bp_is_current_action( 'followers' ) ) ) {
		// set the members filter to 'newest-follows' by faking an ajax request (loophole!)
		$_POST['cookie'] = 'bp-members-filter%3Dnewest-follows';

		// reset the dropdown menu to 'Newest Follows'.
		@setcookie( 'bp-members-filter', 'newest-follows', 0, '/' );

	// reset members filter on the user friends and members directory page
	// this is done b/c the 'newest-follows' filter does not apply on these pages.
	} elseif ( bp_is_user_friends() || ( ! bp_is_user() && bp_is_members_component() ) ) {
		// set the members filter to 'newest' by faking an ajax request (loophole!).
		$_POST['cookie'] = 'bp-members-filter%3Dactive';

		// reset the dropdown menu to 'Last Active'.
		@setcookie( 'bp-members-filter', 'active', 0, '/' );
	}
}
add_action( 'bp_screens', 'bp_follow_set_members_scope_default' );

/**
 * Sets the "RSS" feed URL for the tab on the Sitewide Activity page.
 *
 * This occurs when the "Following" tab is clicked on the Sitewide Activity
 * page or when the activity scope is already set to "following".
 *
 * Only do this for BuddyPress 1.8+.
 *
 * @since 1.2.1
 *
 * @author r-a-y
 * @param string $retval The feed URL.
 * @return string The feed URL.
 */
function bp_follow_alter_activity_feed_url( $retval ) {
	// only available in BP 1.8+
	if ( ! class_exists( 'BP_Activity_Feed' ) ) {
		return $retval;
	}

	// this is done b/c we're filtering 'bp_get_sitewide_activity_feed_link' and
	// we only want to alter the feed link for the "RSS" tab.
	if ( ! defined( 'DOING_AJAX' ) && ! did_action( 'bp_before_directory_activity' ) ) {
		return $retval;
	}

	// get the activity scope.
	$scope = ! empty( $_COOKIE['bp-activity-scope'] ) ? $_COOKIE['bp-activity-scope'] : false;

	if ( 'following' === $scope && bp_loggedin_user_id() ) {
		$retval = bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOWING_SLUG' ), array( 'feed' ) ) );
	}

	return $retval;
}
add_filter( 'bp_get_sitewide_activity_feed_link', 'bp_follow_alter_activity_feed_url' );
add_filter( 'bp_dtheme_activity_feed_url',        'bp_follow_alter_activity_feed_url' );
add_filter( 'bp_legacy_theme_activity_feed_url',  'bp_follow_alter_activity_feed_url' );

/** GETTEXT **************************************************************/

/**
 * Add gettext filter when no activities are found and when using follow scope.
 *
 * @since 1.2.1
 *
 * @author r-a-y
 * @param bool $has_activities Whether the current activity loop has activities.
 * @return bool
 */
function bp_follow_has_activities( $has_activities ) {
	$bp = $GLOBALS['bp'];

	if ( ! empty( $bp->follow->activity_scope_set ) && ! $has_activities ) {
		add_filter( 'gettext', 'bp_follow_no_activity_text', 10, 2 );
	}

	return $has_activities;
}
add_filter( 'bp_has_activities', 'bp_follow_has_activities', 10, 2 );

/**
 * Modifies 'no activity found' text to be more specific to follow scope.
 *
 * @since 1.2.1
 *
 * @author r-a-y
 * @see bp_follow_has_activities()
 * @param string $translated_text The translated text.
 * @param string $untranslated_text The unmodified text.
 * @return string
 */
function bp_follow_no_activity_text( $translated_text, $untranslated_text ) {
	if ( 'Sorry, there was no activity found. Please try a different filter.' === $untranslated_text ) {
		if ( ! bp_is_user() || bp_is_my_profile() ) {
			$follow_counts = bp_follow_total_follow_counts( array(
				'user_id' => bp_loggedin_user_id(),
			) );

			if ( $follow_counts['following'] ) {
				return __( "You are following some users, but they haven't posted yet.", 'buddypress-followers' );
			} else {
				return __( "You are not following anyone yet.", 'buddypress-followers' );
			}
		} else {
			$follow_counts = bp_follow_total_follow_counts( array(
				'user_id' => bp_displayed_user_id(),
			) );

			if ( ! empty( $follow_counts['following'] ) ) {
				return __( "This user is following some users, but they haven't posted yet.", 'buddypress-followers' );
			} else {
				return __( "This user isn't following anyone yet.", 'buddypress-followers' );
			}
		}
	}

	return $translated_text;
}

/**
 * Removes custom gettext filter when using follow scope.
 *
 * @since 1.2.1
 *
 * @author r-a-y
 * @see bp_follow_has_activities()
 */
function bp_follow_after_activity_loop() {
	$bp = $GLOBALS['bp'];

	if ( ! empty( $bp->follow->activity_scope_set ) ) {
		remove_filter( 'gettext', 'bp_follow_no_activity_text', 10, 2 );
		unset( $bp->follow->activity_scope_set );
	}
}
add_action( 'bp_after_activity_loop', 'bp_follow_after_activity_loop' );

/** SUGGESTIONS *********************************************************/

/**
 * Override BP's friend suggestions with followers.
 *
 * This takes effect for private messages currently. Available in BP 2.1+.
 *
 * @since 1.3.0
 *
 * @param array $retval Parameters for the user query.
 */
function bp_follow_user_suggestions_args( $retval ) {
	$bp = $GLOBALS['bp'];

	// if only friends, override with followers instead.
	if ( true === (bool) $retval['only_friends'] ) {
		// set marker.
		$bp->follow->only_friends_override = 1;

		// we set 'only_friends' to 0 to bypass friends component check.
		$retval['only_friends'] = 0;

		// add our user query filter.
		add_filter( 'bp_members_suggestions_query_args', 'bp_follow_user_follow_suggestions' );
	}

	return $retval;
}
add_filter( 'bp_members_suggestions_args', 'bp_follow_user_suggestions_args' );

/**
 * Filters the user suggestions query to limit by followers only.
 *
 * Only available in BP 2.1+.
 *
 * @since 1.3.0
 *
 * @see bp_follow_user_suggestions_args()
 * @param array $user_query User query arguments. See {@link BP_User_Query}.
 */
function bp_follow_user_follow_suggestions( $user_query ) {
	$bp = $GLOBALS['bp'];

	if ( isset( $bp->follow->only_friends_override ) ) {
		unset( $bp->follow->only_friends_override );

		// limit suggestions to followers.
		$user_query['include'] = bp_follow_get_followers( array(
			'user_id' => bp_loggedin_user_id(),
		) );

		// No followers, so don't return any suggestions.
		if ( empty( $user_query['include'] ) && false === is_super_admin( bp_loggedin_user_id() ) ) {
			$user_query['include'] = (array) 0;
		}
	}

	return $user_query;
}

/**
 * Remove at-mention primed results for the friends component.
 *
 * We'll use a list of members the logged-in user is following instead.
 *
 * @see bp_follow_prime_mentions_results()
 */
remove_action( 'bp_activity_mentions_prime_results', 'bp_friends_prime_mentions_results' );

/**
 * Set up a list of members the current user is following for at-mention use.
 *
 * This is intended to speed up at-mention lookups for a majority of use cases.
 *
 * @since 1.3.0
 *
 * @see bp_activity_mentions_script()
 */
function bp_follow_prime_mentions_results() {
	if ( ! bp_activity_maybe_load_mentions_scripts() ) {
		return;
	}

	// Bail out if the site has a ton of users.
	if ( is_multisite() && wp_is_large_network( 'users' ) ) {
		return;
	}

	$following = bp_follow_get_following( array(
		'user_id' => bp_loggedin_user_id(),
	) );

	if ( empty( $following ) ) {
		return;
	}

	$followers_query = new BP_User_Query( array(
		'count_total'     => '', // Prevents total count.
		'populate_extras' => false,
		'type'            => 'alphabetical',
		'include'         => $following,
	) );
	$results = array();

	foreach ( $followers_query->results as $user ) {
		$result        = new stdClass();
		$result->ID    = $user->user_nicename;
		$result->name  = bp_core_get_user_displayname( $user->ID );
		$result->image = bp_core_fetch_avatar( array(
			'html' => false,
			'item_id' => $user->ID,
		) );

		$results[] = $result;
	}

	wp_localize_script( 'bp-mentions', 'BP_Suggestions', array(
		'friends' => $results,
	) );
}
add_action( 'bp_activity_mentions_prime_results', 'bp_follow_prime_mentions_results' );
