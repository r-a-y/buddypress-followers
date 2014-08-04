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

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

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
 * @param $has_members Whether any members where actually returned in the loop
 * @return $has_members Return the original $has_members param as this is a filter function.
 */
function bp_follow_inject_member_follow_status( $has_members ) {
	global $members_template;

	if ( empty( $has_members ) )
		return $has_members;

	$user_ids = array();

	foreach( (array)$members_template->members as $i => $member ) {
		if ( $member->id != bp_loggedin_user_id() )
			$user_ids[] = $member->id;

		$members_template->members[$i]->is_following = false;
	}

	if ( empty( $user_ids ) )
		return $has_members;

	$following = BP_Follow::bulk_check_follow_status( $user_ids );

	if ( empty( $following ) )
		return $has_members;

	foreach( (array)$following as $is_following ) {
		foreach( (array)$members_template->members as $i => $member ) {
			if ( $is_following->leader_id == $member->id )
				$members_template->members[$i]->is_following = true;
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
 * @param $has_members - Whether any members where actually returned in the loop
 * @return $has_members - Return the original $has_members param as this is a filter function.
 */
function bp_follow_inject_group_member_follow_status( $has_members ) {
	global $members_template;

	if ( empty( $has_members ) )
		return $has_members;

	$user_ids = array();

	foreach( (array)$members_template->members as $i => $member ) {
		if ( $member->user_id != bp_loggedin_user_id() )
			$user_ids[] = $member->user_id;

		$members_template->members[$i]->is_following = false;
	}

	if ( empty( $user_ids ) )
		return $has_members;

	$following = BP_Follow::bulk_check_follow_status( $user_ids );

	if ( empty( $following ) )
		return $has_members;

	foreach( (array)$following as $is_following ) {
		foreach( (array)$members_template->members as $i => $member ) {
			if ( $is_following->leader_id == $member->user_id )
				$members_template->members[$i]->is_following = true;
		}
	}

	return $has_members;
}
add_filter( 'bp_group_has_members', 'bp_follow_inject_group_member_follow_status' );

/** BUTTONS **************************************************************/

/**
 * Add a "Follow User/Stop Following" button to the profile header for a user.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
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
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @global $members_template The members template object containing all fetched members in the loop
 * @uses is_user_logged_in() Return true if you are logged in.
 */
function bp_follow_add_listing_follow_button() {
	global $members_template;

	if ( $members_template->member->id == bp_loggedin_user_id() )
		return false;

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
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @global $members_template The members template object containing all fetched members in the loop
 */
function bp_follow_add_group_member_follow_button() {
	global $members_template;

	if ( $members_template->member->user_id == bp_loggedin_user_id() || !bp_loggedin_user_id() )
		return false;

	bp_follow_add_follow_button( 'leader_id=' . $members_template->member->user_id );
}
add_action( 'bp_group_members_list_item_action', 'bp_follow_add_group_member_follow_button' );

/** CACHE ****************************************************************/

/**
 * Clear cache when a user follows / unfollows another user.
 *
 * @since BuddyPress (1.3.0)
 *
 * @param BP_Follow $follow
 */
function bp_follow_clear_cache_on_follow( BP_Follow $follow ) {
	// clear user count cache
	wp_cache_delete( $follow->leader_id,   'bp_follow_followers_count' );
	wp_cache_delete( $follow->follower_id, 'bp_follow_following_count' );
}
add_action( 'bp_follow_start_following', 'bp_follow_clear_cache_on_follow' );
add_action( 'bp_follow_stop_following',  'bp_follow_clear_cache_on_follow' );

/**
 * Clear follow cache when a user is deleted.
 *
 * @since BuddyPress (1.3.0)
 *
 * @param int $user_id The ID of the user being deleted
 */
function bp_follow_clear_cache_on_user_delete( $user_id ) {
	// delete user's follow count
	wp_cache_delete( $user_id, 'bp_follow_following_count' );
	wp_cache_delete( $user_id, 'bp_follow_followers_count' );

	// delete each user's followers count that the user was following
	$users = BP_Follow::get_following( $user_id );
	if ( ! empty( $users ) ) {
		foreach ( $users as $user ) {
			wp_cache_delete( $user, 'bp_follow_followers_count' );
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
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 */
function bp_follow_add_activity_tab() {

	$counts = bp_follow_total_follow_counts( array( 'user_id' => bp_loggedin_user_id() ) );

	if ( empty( $counts['following'] ) )
		return false;
	?>
	<li id="activity-following"><a href="<?php echo bp_loggedin_user_domain() . BP_ACTIVITY_SLUG . '/' . BP_FOLLOWING_SLUG . '/' ?>" title="<?php _e( 'The public activity for everyone you are following on this site.', 'bp-follow' ) ?>"><?php printf( __( 'Following <span>%d</span>', 'bp-follow' ), (int)$counts['following'] ) ?></a></li><?php
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

	$counts = bp_follow_total_follow_counts( array( 'user_id' => bp_loggedin_user_id() ) );

	if ( empty( $counts['following'] ) )
		return false;
	?>
	<li id="members-following"><a href="<?php echo bp_loggedin_user_domain() . BP_FOLLOWING_SLUG ?>"><?php printf( __( 'Following <span>%d</span>', 'bp-follow' ), $counts['following'] ) ?></a></li><?php
}
add_action( 'bp_members_directory_member_types', 'bp_follow_add_following_tab' );

/** USER QUERY ***********************************************************/

/**
 * Override the BP User Query when our special follow type is in use.
 *
 * @since 1.3.0
 *
 * @param BP_User_Query $query
 */
function bp_follow_pre_user_query( $query ) {
	// oldest follows
	if ( 'oldest-follows' === $query->query_vars['type'] ) {
		// flip the order
		$query->query_vars['user_ids'] = array_reverse( wp_parse_id_list( $query->query_vars['include'] ) );

	// newest follows
	} elseif ( 'newest-follows' === $query->query_vars['type'] ) {
		$query->query_vars['user_ids'] = $query->query_vars['include'];
	}
}
add_action( 'bp_pre_user_query_construct', 'bp_follow_pre_user_query' );

/** AJAX MANIPULATION ****************************************************/

/**
 * Modify the querystring passed to the activity loop to return only users
 * that the current user is following.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_get_following_ids() Get the user_ids of all users a user is following.
 */
function bp_follow_add_activity_scope_filter( $qs, $object, $filter, $scope ) {
	global $bp;

	// Only filter on directory pages (no action) and the following scope on activity object.
	if ( ( !empty( $bp->current_action ) && !bp_is_current_action( 'following' ) ) || 'following' != $scope || 'activity' != $object )
		return $qs;

	// set internal marker noting that our activity scope is applied
	$bp->follow->activity_scope_set = 1;

	$user_id = bp_displayed_user_id() ? bp_displayed_user_id() : bp_loggedin_user_id();

	$following_ids = bp_get_following_ids( array( 'user_id' => $user_id ) );

	// if $following_ids is empty, pass a negative number so no activity can be found
	$following_ids = empty( $following_ids ) ? -1 : $following_ids;

	$qs .= '&user_id=' . $following_ids;

	return apply_filters( 'bp_follow_add_activity_scope_filter', $qs, $filter );
}
add_filter( 'bp_dtheme_ajax_querystring',       'bp_follow_add_activity_scope_filter', 10, 4 );
add_filter( 'bp_legacy_theme_ajax_querystring', 'bp_follow_add_activity_scope_filter', 10, 4 );

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
 * @param array|string $qs The querystring for the BP loop
 * @param str $object The current object for the querystring
 * @return array|string Modified querystring
 */
function bp_follow_add_member_scope_filter( $qs, $object ) {

	// not on the members object? stop now!
	if ( $object != 'members' ) {
		return $qs;
	}

	$set = false;

	// members directory
	// can't use bp_is_members_directory() yet since that's a BP 2.0 function
	if ( ! bp_is_user() && bp_is_members_component() ) {
		// check if members scope is following before manipulating
		if ( isset( $_COOKIE['bp-members-scope'] ) && 'following' === $_COOKIE['bp-members-scope'] ) {
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

	// filter the members loop based on the current page
	switch ( $action ) {
		case 'following':
			// parse querystring into an array
			$qs = wp_parse_args( $qs );

			$qs['include'] = bp_get_following_ids( array(
				'user_id' => bp_displayed_user_id() ? bp_displayed_user_id() : bp_loggedin_user_id(),
			) );
			$qs['per_page'] = apply_filters( 'bp_follow_per_page', 20 );

			return $qs;

			break;

		case 'followers' :
			// parse querystring into an array
			$qs = wp_parse_args( $qs );

			$qs['include'] = bp_get_follower_ids();
			$qs['per_page'] = apply_filters( 'bp_follow_per_page', 20 );

			return $qs;

			break;

		default :
			return $qs;

			break;
	}

}
add_filter( 'bp_ajax_querystring', 'bp_follow_add_member_scope_filter', 20, 2 );

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
	// don't do this for older versions of BP
	if ( ! class_exists( 'BP_User_Query' ) ) {
		return;
	}

	// set default members filter to 'newest-follows' on member follow pages
	if ( bp_is_user() && ( bp_is_current_action( 'following' ) || bp_is_current_action( 'followers' ) ) ) {
		// set the members filter to 'newest-follows' by faking an ajax request (loophole!)
		$_POST['cookie'] = 'bp-members-filter%3Dnewest-follows';

		// reset the dropdown menu to 'Newest Follows'
		@setcookie( 'bp-members-filter', 'newest-follows', 0, '/' );

	// reset members filter on the user friends and members directory page
	// this is done b/c the 'newest-follows' filter does not apply on these pages
	} elseif ( bp_is_user_friends() || ( ! bp_is_user() && bp_is_members_component() ) ) {
		// set the members filter to 'newest' by faking an ajax request (loophole!)
		$_POST['cookie'] = 'bp-members-filter%3Dactive';

		// reset the dropdown menu to 'Last Active'
		@setcookie( 'bp-members-filter', 'active', 0, '/' );
	}
}
add_action( 'bp_screens', 'bp_follow_set_members_scope_default' );

/**
 * On a user's "Activity > Following" page, set the activity scope to
 * "following".
 *
 * Unfortunately for 3rd-party components, this is the only way to set the
 * scope in {@link bp_dtheme_ajax_querystring()} due to the way that function
 * handles cookies.
 *
 * Yes, this is considered a hack, or more appropriately, a loophole!
 *
 * @author r-a-y
 * @since 1.1.1
 */
function bp_follow_set_activity_following_scope() {
	// set the activity scope to 'following' by faking an ajax request (loophole!)
	$_POST['cookie'] = 'bp-activity-scope%3Dfollowing%3B%20bp-activity-filter%3D-1';

	// reset the dropdown menu to 'Everything'
	@setcookie( 'bp-activity-filter', '-1', 0, '/' );
}
add_action( 'bp_activity_screen_following', 'bp_follow_set_activity_following_scope' );

/**
 * On a user's "Activity > Following" screen, set the activity scope to
 * "following" during AJAX requests ("Load More" button or via activity
 * dropdown filter menu).
 *
 * Unfortunately for 3rd-party components, this is the only way to set the
 * scope in {@link bp_dtheme_ajax_querystring()} due to the way that function
 * handles cookies.
 *
 * Yes, this is considered a hack, or more appropriately, a loophole!
 *
 * @author r-a-y
 * @since 1.1.1
 */
function bp_follow_set_activity_following_scope_on_ajax() {

	// set the activity scope to 'following'
	if ( bp_is_current_action( 'following' ) && bp_follow_is_doing_ajax() ) {
		// if we have a post value already, let's add our scope to the existing cookie value
		if ( !empty( $_POST['cookie'] ) )
			$_POST['cookie'] .= '%3B%20bp-activity-scope%3Dfollowing';
		else
			$_POST['cookie'] .= 'bp-activity-scope%3Dfollowing';
	}
}
add_action( 'bp_before_activity_loop', 'bp_follow_set_activity_following_scope_on_ajax' );

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
	// we only want to alter the feed link for the "RSS" tab
	if ( ! defined( 'DOING_AJAX' ) && ! did_action( 'bp_before_directory_activity' ) ) {
		return $retval;
	}

	// get the activity scope
	$scope = ! empty( $_COOKIE['bp-activity-scope'] ) ? $_COOKIE['bp-activity-scope'] : false;

	if ( $scope == 'following' && bp_loggedin_user_id() ) {
		$retval = bp_loggedin_user_domain() . bp_get_activity_slug() . '/' . constant( 'BP_FOLLOWING_SLUG' ) . '/feed/';
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
	global $bp;

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
	if ( $untranslated_text == 'Sorry, there was no activity found. Please try a different filter.' ) {
		if ( ! bp_is_user() || bp_is_my_profile() ) {
			$follow_counts = bp_follow_total_follow_counts( array(
				'user_id' => bp_loggedin_user_id()
			) );

			if ( $follow_counts['following'] ) {
				return __( "You are following some users, but they haven't posted yet.", 'bp-follow' );
			} else {
				return __( "You are not following anyone yet.", 'bp-lists' );
			}
		} else {
			$follow_counts = bp_follow_total_follow_counts( array(
				'user_id' => bp_displayed_user_id()
			) );

			if ( ! empty( $follow_counts['following'] ) ) {
				return __( "This user is following some users, but they haven't posted yet.", 'bp-follow' );
			} else {
				return __( "This user isn't following anyone yet.", 'bp-follow' );
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
	global $bp;

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
	// if only friends, override with followers instead
	if ( true === (bool) $retval['only_friends'] ) {
		// set marker
		buddypress()->follow->only_friends_override = 1;

		// we set 'only_friends' to 0 to bypass friends component check
		$retval['only_friends'] = 0;

		// add our user query filter
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
	if ( isset( buddypress()->follow->only_friends_override ) ) {
		unset( buddypress()->follow->only_friends_override );

		// limit suggestions to followers
		$user_query['include'] = bp_follow_get_followers( array( 'user_id' => bp_loggedin_user_id() ) );
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

	$followers_query = new BP_User_Query( array(
		'count_total'     => '', // Prevents total count
		'populate_extras' => false,
		'type'            => 'alphabetical',
		'include'         => bp_follow_get_following( array( 'user_id' => bp_loggedin_user_id() ) )
	) );
	$results = array();

	foreach ( $followers_query->results as $user ) {
		$result        = new stdClass();
		$result->ID    = $user->user_nicename;
		$result->image = bp_core_fetch_avatar( array( 'html' => false, 'item_id' => $user->ID ) );
		$result->name  = bp_core_get_user_displayname( $user->ID );

		$results[] = $result;
	}

	wp_localize_script( 'bp-mentions', 'BP_Suggestions', array(
		'friends' => $results,
	) );
}
add_action( 'bp_activity_mentions_prime_results', 'bp_follow_prime_mentions_results' );