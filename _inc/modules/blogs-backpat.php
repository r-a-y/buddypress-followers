<?php
/**
 * Blogs activity scope backwards compatibililty functions for < BP 2.2
 *
 * BuddyPress 2.2.0 includes advanced activity parsing.  BP Follow now uses
 * this functionality.  This file uses the old method of loading up the
 * 'followblogs' activity scope for those using BP 2.0 - 2.1.
 *
 * @since 1.3.0
 *
 * @package BP-Follow
 * @subpackage Backpat
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Set activity scope on a user's "Activity > Followed Sites" page
 */
function bp_follow_blogs_set_activity_scope_on_user_activity() {
	if ( ! bp_is_current_action( constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) ) {
		return;
	}

	$scope = 'followblogs';

	// if we have a post value already, let's add our scope to the existing cookie value.
	if ( ! empty( $_POST['cookie'] ) ) {
		$_POST['cookie'] .= "%3B%20bp-activity-scope%3D{$scope}";
	} else {
		$_POST['cookie'] = "bp-activity-scope%3D{$scope}";
	}

	// set the activity scope by faking an ajax request (loophole!).
	if ( ! defined( 'DOING_AJAX' ) ) {
		$_POST['cookie'] .= "%3B%20bp-activity-filter%3D-1";

		// reset the selected tab.
		@setcookie( 'bp-activity-scope',  $scope, 0, '/' );

		//reset the dropdown menu to 'Everything'.
		@setcookie( 'bp-activity-filter', '-1',   0, '/' );
	}
}
add_action( 'bp_before_activity_loop', 'bp_follow_blogs_set_activity_scope_on_user_activity' );

/**
 * Filter the activity loop.
 *
 * Specifically, when on the activity directory and clicking on the "Followed
 * Sites" tab.
 *
 * @param str $qs The querystring for the BP loop
 * @param str $object The current object for the querystring
 * @return str Modified querystring
 */
function bp_follow_blogs_add_activity_scope_filter( $qs, $object ) {
	// not on the blogs object? stop now!
	if ( 'activity' !== $object ) {
		return $qs;
	}

	// parse querystring into an array.
	$r = wp_parse_args( $qs );

	if ( bp_is_current_action( constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) ) {
		$r['scope'] = 'followblogs';
	}

	if ( ! isset( $r['scope'] ) ) {
		return $qs;
	}

	if ( 'followblogs' !== $r['scope'] ) {
		return $qs;
	}

	// get blog IDs that the user is following.
	$following_ids = bp_get_following_ids( array(
		'user_id'     => bp_displayed_user_id() ? bp_displayed_user_id() : bp_loggedin_user_id(),
		'follow_type' => 'blogs',
	) );

	// if $following_ids is empty, pass a negative number so no blogs can be found.
	$following_ids = empty( $following_ids ) ? -1 : $following_ids;

	$args = array(
		'user_id'    => 0,
		'object'     => 'blogs',
		'primary_id' => $following_ids,
	);

	// make sure we add a separator if we have an existing querystring.
	if ( ! empty( $qs ) ) {
		$qs .= '&';
	}

	// add our follow parameters to the end of the querystring.
	$qs .= build_query( $args );

	// support BP Groupblog
	// We need to filter the WHERE SQL conditions to do this.
	if ( function_exists( 'bp_groupblog_init' ) ) {
		add_filter( 'bp_activity_get_where_conditions', 'bp_follow_blogs_groupblog_activity_where_conditions', 10, 2 );
	}

	return $qs;
}
add_filter( 'bp_ajax_querystring', 'bp_follow_blogs_add_activity_scope_filter', 20, 2 );

/**
 * Filter the activity WHERE SQL conditions to support groupblog entries.
 *
 * @param array $retval Current MySQL WHERE conditions.
 * @param array $r Current activity get arguments.
 * @return array
 */
function bp_follow_blogs_groupblog_activity_where_conditions( $retval, $r ) {
	$bp = $GLOBALS['bp'];

	// support heartbeat in groupblog query.
	$extra = '';
	if ( ! empty( $r['filter']['since'] ) ) {
		$extra = BP_Activity_Activity::get_filter_sql( array( 'since' => $r['filter']['since'] ) );
		$extra = ' AND ' . $extra;
	}

	// For BP Groupblog, we need to grab the group IDs that are connected to blogs
	// This is what this query is for, which will form our groupblog subquery.
	$group_ids_connected_to_blogs_subquery = "SELECT group_id FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'groupblog_blog_id' AND meta_value IN ( " . $r['filter']['primary_id'] . " )";

	$retval['filter_sql'] = "( ( {$retval['filter_sql']} ) OR ( component = 'groups' AND item_id IN ( {$group_ids_connected_to_blogs_subquery} ) AND type = 'new_groupblog_post'{$extra} ) )";

	remove_filter( 'bp_activity_get_where_conditions', 'bp_follow_blogs_groupblog_activity_where_conditions', 10, 2 );
	return $retval;
}
