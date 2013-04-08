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
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 */
function bp_follow_add_following_tab() {

	if ( bp_displayed_user_id() )
		return false;

	$counts = bp_follow_total_follow_counts( array( 'user_id' => bp_loggedin_user_id() ) );

	if ( empty( $counts['following'] ) )
		return false;
	?>
	<li id="members-following"><a href="<?php echo bp_loggedin_user_domain() . BP_FOLLOWING_SLUG ?>"><?php printf( __( 'Following <span>%d</span>', 'bp-follow' ), $counts['following'] ) ?></a></li><?php
}
add_action( 'bp_members_directory_member_types', 'bp_follow_add_following_tab' );

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
 * Modify the querystring passed to the members loop to return only users
 * that the current user is following.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_get_following_ids() Get the user_ids of all users a user is following.
 */
function bp_follow_add_member_directory_filter( $qs, $object, $filter, $scope  ) {
	global $bp;

	// Only filter on directory pages (no action) and the following scope on members object.
	if ( !empty( $bp->current_action ) || 'following' != $scope || 'members' != $object )
		return $qs;

	$qs .= '&include=' . bp_get_following_ids( array( 'user_id' => bp_loggedin_user_id() ) );

	return apply_filters( 'bp_follow_add_member_directory_filter', $qs, $filter );
}
add_filter( 'bp_dtheme_ajax_querystring',       'bp_follow_add_member_directory_filter', 10, 4 );
add_filter( 'bp_legacy_theme_ajax_querystring', 'bp_follow_add_member_directory_filter', 10, 4 );

/**
 * Filter the members loop on a user's "Following" or "Followers" page.
 *
 * This is done so we can return the users that:
 *   - the current user is following; or
 *   - the users that are following the current user
 *
 * @author r-a-y
 * @since 1.2
 *
 * @param str $qs The querystring for the BP loop
 * @param str $object The current object for the querystring
 * @return str Modified querystring
 */
function bp_follow_add_member_scope_filter( $qs, $object ) {

	// not on the members object? stop now!
	if ( $object != 'members' )
		return $qs;

	// not on a user page? stop now!
	if ( ! bp_is_user() )
		return $qs;

	// filter the members loop based on the current page
	switch ( bp_current_action() ) {
		// 'following' page
		case constant( 'BP_FOLLOWING_SLUG' ) :
			$args = array(
				'include'  => bp_get_following_ids(),
				'per_page' => apply_filters( 'bp_follow_per_page', 20 )
			);

			// make sure we add a separator if we have an existing querystring
			if ( ! empty( $qs ) )
				$qs .= '&';

			// add our follow parameters to the end of the querystring
			$qs .= build_query( $args );

			return $qs;

			break;

		// 'followers' page
		case constant( 'BP_FOLLOWERS_SLUG' ) :
			$args = array(
				'include'  => bp_get_follower_ids(),
				'per_page' => apply_filters( 'bp_follow_per_page', 20 )
			);

			// make sure we add a separator if we have an existing querystring
			if ( ! empty( $qs ) )
				$qs .= '&';

			// add our follow parameters to the end of the querystring
			$qs .= build_query( $args );

			return $qs;

			break;

		default :
			return $qs;

			break;
	}

}
add_filter( 'bp_ajax_querystring', 'bp_follow_add_member_scope_filter', 20, 2 );

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

	// are we in an ajax request?
	//
	// backpat for BP 1.5 as we can't check the DOING_AJAX constant b/c 1.5
	// doesn't use admin-ajax.php
	$is_ajax = ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' );

	// set the activity scope to 'following'
	if ( bp_is_current_action( 'following' ) && $is_ajax ) {
		// if we have a post value already, let's add our scope to the existing cookie value
		if ( !empty( $_POST['cookie'] ) )
			$_POST['cookie'] .= '%3B%20bp-activity-scope%3Dfollowing';
		else
			$_POST['cookie'] .= 'bp-activity-scope%3Dfollowing';
	}
}
add_action( 'bp_before_activity_loop', 'bp_follow_set_activity_following_scope_on_ajax' );
