<?php
/**
 * Functions in this file allow this component to hook into BuddyPress so it interacts
 * seamlessly with the interface and existing core components.
 */

/**
 * bp_follow_inject_member_follow_status()
 *
 * Once the members loop has queried and built a members_template object, fetch
 * all of the member IDs in the object and bulk fetch the following status for all the
 * members in one query. This is significantly more efficient that querying for every
 * member inside of the loop.
 *
 * @package BP-Follow
 * @global $members_template The members template object containing all fetched members in the loop
 * @uses bulk_check_follow_status() Check the following status for more than one member
 * @param $has_members - Whether any members where actually returned in the loop
 * @return $has_members - Return the original $has_members param as this is a filter function.
 */
function bp_follow_inject_member_follow_status( $has_members ) {
	global $members_template, $bp;

	if ( empty( $has_members ) )
		return $has_members;

	$user_ids = array();
	foreach( (array)$members_template->members as $i => $member ) {
		if ( $member->id != $bp->loggedin_user->id )
			$user_ids[] = $member->id;

		$members_template->members[$i]->is_following = false;
	}

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
 * bp_follow_add_profile_follow_button()
 *
 * Add a "Follow User/Stop Following" button to the profile header for a user.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_follow_is_following() Check the following status for a user
 * @uses bp_is_my_profile() Return true if you are looking at your own profile when logged in.
 * @uses is_user_logged_in() Return true if you are logged in.
 */
function bp_follow_add_profile_follow_button() {
	global $bp;

	if ( empty( $bp->displayed_user->id ) || bp_is_my_profile() || !is_user_logged_in() )
		return false;

	if ( !bp_follow_is_following( array( 'leader_id' => $bp->displayed_user->id, 'follower_id' => $bp->loggedin_user->id ) ) ) { ?>
		<div class="generic-button"><a href="<?php echo wp_nonce_url( $bp->displayed_user->domain . $bp->follow->followers->slug . '/start/', 'start_following' ) ?>"><?php printf( __( 'Follow %s' ), bp_get_user_firstname( $bp->displayed_user->fullname ) ) ?></a></div><?php
	} else { ?>
		<div class="generic-button"><a href="<?php echo wp_nonce_url( $bp->displayed_user->domain . $bp->follow->followers->slug . '/stop/', 'stop_following' ) ?>"><?php printf( __( 'Stop Following %s' ), bp_get_user_firstname( $bp->displayed_user->fullname ) ) ?></a></div><?php
	}
}
add_action( 'bp_profile_header_meta', 'bp_follow_add_profile_follow_button' );

/**
 * bp_follow_add_listing_follow_button()
 *
 * Add a "Follow User/Stop Following" button to each member shown in a member listing
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @global $members_template The members template object containing all fetched members in the loop
 * @uses is_user_logged_in() Return true if you are logged in.
 */
function bp_follow_add_listing_follow_button() {
	global $bp, $members_template;

	if ( $members_template->member->id == $bp->loggedin_user->id || !is_user_logged_in() )
		return false;

	if ( !$members_template->member->is_following ) { ?>
		<div class="generic-button"><a href="<?php echo wp_nonce_url( bp_core_get_user_domain( $members_template->member->id, $members_template->member->user_nicename, $members_template->member->user_login ) . $bp->follow->followers->slug . '/start/', 'start_following' ) ?>" class="follow" id="follow-<?php echo $members_template->member->id ?>"><?php printf( __( 'Follow %s' ), bp_get_user_firstname( $members_template->member->fullname ) ) ?></a></div><?php
	} else { ?>
		<div class="generic-button"><a href="<?php echo wp_nonce_url( bp_core_get_user_domain( $members_template->member->id, $members_template->member->user_nicename, $members_template->member->user_login ) . $bp->follow->followers->slug . '/stop/', 'stop_following' )  ?>" class="unfollow" id="unfollow-<?php echo $members_template->member->id ?>"><?php printf( __( 'Stop Following %s' ), bp_get_user_firstname( $members_template->member->fullname ) ) ?></a></div><?php
	}
}
add_action( 'bp_directory_members_actions', 'bp_follow_add_listing_follow_button' );
add_action( 'bp_followers_list_item_actions', 'bp_follow_add_listing_follow_button' );
add_action( 'bp_following_list_item_actions', 'bp_follow_add_listing_follow_button' );

/* Hook into the activity stream tabs and scope */

/**
 * bp_follow_add_activity_tab()
 *
 * Adds a "Following (X)" tab to the activity stream so that users can select to filter on only
 * users they are following.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 */
function bp_follow_add_activity_tab() {
	global $bp;

	$counts = bp_follow_total_follow_counts( array( 'user_id' => $bp->loggedin_user->id ) );

	if ( empty( $counts['following'] ) )
		return false;
	?>
	<li id="activity-following"><a href="<?php echo bp_loggedin_user_domain() . BP_ACTIVITY_SLUG . '/' . BP_FOLLOWING_SLUG . '/' ?>" title="<?php _e( 'The public activity for everyone you are following on this site.', 'buddypress' ) ?>"><?php printf( __( 'Following (%s)', 'buddypress' ), (int)$counts['following'] ) ?></a></li><?php
}
add_action( 'bp_before_activity_type_tab_friends', 'bp_follow_add_activity_tab' );

/**
 * bp_follow_add_activity_scope_filter()
 *
 * Modify the querystring passed to the activity loop so we return only users that the
 * current user is following.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_get_following_ids() Get the user_ids of all users a user is following.
 */
function bp_follow_add_activity_scope_filter( $qs, $object, $filter, $scope, $page, $search_terms, $extras ) {
	global $bp;

	/* Only filter on directory pages (no action) and the following scope on activity object. */
	if ( ( 'following' != $scope && 'following' != $bp->current_action ) || 'activity' != $object )
		return $qs;

	$querystring = array();
	$user_id = ( $bp->displayed_user->id ) ? $bp->displayed_user->id : $bp->loggedin_user->id;
	$querystring[] = 'user_id=' . bp_get_following_ids( array( 'user_id' => $user_id ) );

	if ( !empty( $page ) )
		$querystring[] = 'page=' . $page;

	if ( '-1' != $filter )
		$querystring[] = 'filter=' . $filter;

	if ( !empty( $search_terms ) )
		$querystring[] = 'search_terms=' . $search_terms;

	return apply_filters( 'bp_follow_add_activity_scope_filter', join( '&', (array)$querystring ) );
}
add_filter( 'bp_dtheme_ajax_querystring', 'bp_follow_add_activity_scope_filter', 10, 7 );


/* Hook into the member directory tabs and filtering */

/**
 * bp_follow_add_following_tab()
 *
 * Add a "Following (X)" tab to the members directory so that only users that a user
 * is following will show in the listing.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
 */
function bp_follow_add_following_tab() {
	global $bp;

	if ( $bp->displayed_user->id )
		return false;

	$counts = bp_follow_total_follow_counts( array( 'user_id' => $bp->loggedin_user->id ) );

	if ( empty( $counts['following'] ) )
		return false;
	?>
	<li id="members-following"><a href="<?php echo bp_loggedin_user_domain() . BP_FOLLOWING_SLUG ?>"><?php printf( __( 'Following (%s)', 'buddypress' ), $counts['following'] ) ?></a></li><?php
}
add_action( 'bp_members_directory_member_types', 'bp_follow_add_following_tab' );

/**
 * bp_follow_add_member_directory_filter()
 *
 * Modify the querystring passed to the members loop so we return only users that the
 * current user is following.
 *
 * @package BP-Follow
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_get_following_ids() Get the user_ids of all users a user is following.
 */
function bp_follow_add_member_directory_filter( $qs, $object, $filter, $scope  ) {
	global $bp;

	/* Only filter on directory pages (no action) and the following scope on members object. */
	if ( !empty( $bp->current_action ) || 'following' != $scope || 'members' != $object )
		return $qs;

	$qs .= '&include=' . bp_get_following_ids( array( 'user_id' => $bp->loggedin_user->id ) );

	return apply_filters( 'bp_follow_add_activity_scope_filter', $qs );
}
add_filter( 'bp_dtheme_ajax_querystring', 'bp_follow_add_member_directory_filter', 10, 4 );


?>