<?php
/**
 * Follow Activity Module.
 *
 * @since 1.3.0
 *
 * @package BP-Follow
 * @subpackage Activity Module
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Follow Activity module class.
 *
 * @since 1.3.0
 */
class BP_Follow_Activity_Module {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Component hooks.
		add_action( 'bp_follow_setup_globals', array( $this, 'constants' ) );
		add_action( 'bp_follow_setup_globals', array( $this, 'setup_global_cachegroups' ) );
		add_action( 'bp_follow_setup_nav',     array( $this, 'setup_nav' ) );
		add_action( 'bp_activity_admin_nav',   array( $this, 'activity_admin_nav' ) );

		// Loop filtering.
		add_action( 'bp_before_activity_type_tab_favorites', array( $this, 'add_activity_directory_tab' ) );
		add_filter( 'bp_activity_set_follow_scope_args', array( $this, 'filter_activity_scope' ), 10, 2 );
		add_filter( 'bp_has_activities',      array( $this, 'bulk_inject_follow_status' ) );
		add_action( 'bp_activity_entry_meta', array( $this, 'add_follow_button' ) );

		// Cache invalidation.
		add_action( 'bp_follow_start_following_activity', array( $this, 'clear_cache_on_follow' ) );
		add_action( 'bp_follow_stop_following_activity',  array( $this, 'clear_cache_on_follow' ) );
		add_action( 'bp_follow_before_remove_data',       array( $this, 'clear_cache_on_user_delete' ) );
		add_action( 'bp_activity_after_delete',           array( $this, 'on_activity_delete' ) );

		// RSS.
		add_action( 'bp_actions', array( $this, 'rss_handler' ) );
		add_filter( 'bp_get_sitewide_activity_feed_link', array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_dtheme_activity_feed_url',        array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_legacy_theme_activity_feed_url',  array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_get_activities_member_rss_link',  array( $this, 'activity_feed_url' ) );
	}

	/** COMPONENT HOOKS ******************************************************/

	/**
	 * Constants.
	 */
	public function constants() {
		// /members/admin/activity/[FOLLOW]
		if ( ! defined( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ) ) {
			define( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG', 'follow' );
		}
	}

	/**
	 * Set up global cachegroups.
	 */
	public function setup_global_cachegroups() {
		$bp = $GLOBALS['bp'];

		// Counts.
		$bp->follow->global_cachegroups[] = 'bp_follow_user_activity_following_count';
		$bp->follow->global_cachegroups[] = 'bp_follow_activity_followers_count';

		// Query.
		$bp->follow->global_cachegroups[] = 'bp_follow_user_activity_following_query';
		$bp->follow->global_cachegroups[] = 'bp_follow_activity_followers_query';
	}

	/**
	 * Setup profile nav.
	 */
	public function setup_nav() {
		// Determine user to use.
		if ( bp_displayed_user_domain() ) {
			$user_id = bp_displayed_user_id();
		} elseif ( bp_loggedin_user_domain() ) {
			$user_id = bp_loggedin_user_id();
		} else {
			return;
		}

		// Add activity sub nav item.
		if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_activity_show_activity_subnav', true ) ) {
			bp_core_new_subnav_item( array(
				'name'            => _x( 'Followed Activity', 'Activity subnav tab', 'buddypress-followers' ),
				'slug'            => constant( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ),
				'parent_url'      => bp_follow_get_user_url( $user_id, array( bp_get_activity_slug() ) ),
				'parent_slug'     => bp_get_activity_slug(),
				'screen_function' => 'bp_activity_screen_my_activity',
				'position'        => 21,
				'item_css_id'     => 'activity-follow',
			) );
		}
	}

	/**
	 * Inject "Followed Sites" nav item to WP adminbar's "Activity" main nav.
	 *
	 * @param array $retval
	 * @return array
	 */
	public function activity_admin_nav( $retval ) {
		if ( ! is_user_logged_in() ) {
			return $retval;
		}

		if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_show_activity_subnav', true ) ) {
			$new_item = array(
				'parent' => 'my-account-activity',
				'id'     => 'my-account-activity-followactivity',
				'title'  => _x( 'Followed Activity', 'Adminbar activity subnav', 'buddypress-followers' ),
				'href'   => bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ) ) ),
			);

			$inject = array();
			$offset = 4;

			$inject[ $offset ] = $new_item;
			$retval = array_merge(
				array_slice( $retval, 0, $offset, true ),
				$inject,
				array_slice( $retval, $offset, null, true )
			);
		}

		return $retval;
	}

	/** LOOP FILTERING *******************************************************/

	/**
	 * Adds a "Followed Sites (X)" tab to the activity directory.
	 *
	 * This is so the logged-in user can filter the activity stream to only sites
	 * that the current user is following.
	 */
	public function add_activity_directory_tab() {
		/*
		 * Adding a count is confusing when you can follow comments of activity items...
		 * $count = bp_follow_get_the_following_count( array(
		 *	'user_id'     => bp_loggedin_user_id(),
		 *	'follow_type' => 'activity',
		 * ) );
		 *
		 * if ( empty( $count ) ) {
		 *	return;
		 * }
		 */
		$activity_follow_url =  bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ) ) );
		?>
		<li id="activity-follow"><a href="<?php echo esc_url( $activity_follow_url ); ?>"><?php esc_html_e( 'My Followed Activity', 'buddypress-followers' ); ?></a></li><?php
	}

	public function bulk_inject_follow_status( $retval ) {
		global $activities_template;

		if ( empty( $retval ) ) {
			return $retval;
		}

		if ( ! is_user_logged_in() ) {
			return $retval;
		}

		$activity_ids = array();

		foreach ( (array) $activities_template->activities as $i => $activity ) {
			// add blog ID to array.
			$activity_ids[] = $activity->id;

			// set default follow status to false.
			$activities_template->activities[ $i ]->is_following = false;
		}

		if ( empty( $activity_ids ) ) {
			return $retval;
		}

		$following = BP_Follow::bulk_check_follow_status( $activity_ids, bp_loggedin_user_id(), 'activity' );

		if ( empty( $following ) ) {
			return $retval;
		}

		foreach ( (array) $following as $is_following ) {
			foreach ( (array) $activities_template->activities as $i => $activity ) {
				// set follow status to true if the logged-in user is following.
				if ( $is_following->leader_id == $activity->id ) {
					$activities_template->activities[$i]->is_following = true;
				}
			}
		}

		return $retval;
	}

	/**
	 * Set up activity arguments for use with the 'followblogs' scope.
	 *
	 * For details on the syntax, see {@link BP_Activity_Query}.
	 *
	 * Only applicable to BuddyPress 2.2+.  Older BP installs uses the code
	 * available in /modules/blogs-backpat.php.
	 *
	 * @since 1.3.0
	 *
	 * @param array $retval Empty array by default.
	 * @param array $filter Current activity arguments.
	 * @return array
	 */
	public function filter_activity_scope( $retval = array(), $filter = array() ) {
		// Determine the user_id.
		if ( ! empty( $filter['user_id'] ) ) {
			$user_id = $filter['user_id'];
		} else {
			$user_id = bp_displayed_user_id()
				? bp_displayed_user_id()
				: bp_loggedin_user_id();
		}

		// Get activity IDs that the user is following.
		$following_ids = bp_follow_get_following( array(
			'user_id'     => $user_id,
			'follow_type' => 'activity',
		) );

		// If no activity, pass largest int value to denote no blogs... sigh.
		if ( empty( $following_ids ) ) {
			$following_ids = array( 0 );
		}

		// Should we show all items regardless of sitewide visibility?
		$show_hidden = array();
		if ( ! empty( $user_id ) && ( bp_loggedin_user_id() !== $user_id ) ) {
			$show_hidden = array(
				'column' => 'hide_sitewide',
				'value'  => 0,
			);
		}

		$clause = array(
			'relation' => 'OR',

			// general blog activity items.
			array(
				'column'  => 'id',
				'compare' => 'IN',
				'value'   => $following_ids,
			),

			// groupblog posts.
			array(
				'relation' => 'AND',
				array(
					'column' => 'type',
					'value'  => 'activity_comment',
				),
				array(
					'column'  => 'item_id',
					'compare' => 'IN',
					'value'   => $following_ids,
				),
			),
		);

		$retval = array(
			'relation' => 'AND',
			$clause,
			$show_hidden,

			// overrides.
			'override' => array(
				'display_comments' => 'stream',
				'filter'      => array(
					'user_id' => 0,
				),
				'show_hidden' => true,
			),
		);

		return $retval;
	}

	/**
	 * Add 'Follow' button in activity loop.
	 */
	public function add_follow_button() {
		if ( false === bp_follow_activity_can_follow() ) {
			return;
		}

		bp_follow_activity_button();
	}

	/** CACHE **************************************************************/

	/**
	 * Clear count cache when a user follows / unfolows an activity item.
	 *
	 * @param BP_Follow $follow
	 */
	public function clear_cache_on_follow( BP_Follow $follow ) {
		// clear followers count for activity.
		wp_cache_delete( $follow->leader_id,   'bp_follow_activity_followers_count' );

		// clear following activity count for user.
		wp_cache_delete( $follow->follower_id, 'bp_follow_user_activity_following_count' );

		// clear queried followers / following.
		wp_cache_delete( $follow->leader_id,   'bp_follow_activity_followers_query' );
		wp_cache_delete( $follow->follower_id, 'bp_follow_user_activity_following_query' );
	}

	/**
	 * Clear activity cache when a user is deleted.
	 *
	 * @param int $user_id The user ID being deleted.
	 */
	public function clear_cache_on_user_delete( $user_id = 0 ) {
		// delete user's blog follow count.
		wp_cache_delete( $user_id, 'bp_follow_user_activity_following_count' );

		// delete queried blogs that user was following.
		wp_cache_delete( $user_id, 'bp_follow_user_activity_following_query' );

		// delete each blog's followers count that the user was following.
		$aids = BP_Follow::get_following( $user_id, 'activity' );
		if ( ! empty( $aids ) ) {
			foreach ( $aids as $aid ) {
				wp_cache_delete( $aid, 'bp_follow_activity_followers_count' );
			}
		}
	}

	/**
	 * Clear cache when activity item is deleted.
	 *
	 * @param array $activities An array of activities objects.
	 */
	public function on_activity_delete( $activities ) {
		$bp = $GLOBALS['bp'];

		// Pluck the activity IDs out of the $activities array.
		$activity_ids = wp_parse_id_list( wp_list_pluck( $activities, 'id' ) );

		// See if any of the deleted activity IDs were being followed.
		$sql  = 'SELECT leader_id, follower_id FROM ' . esc_sql( $bp->follow->table_name ) . ' ';
		$sql .= 'WHERE leader_id IN (' . implode( ',', wp_parse_id_list( $activity_ids ) ) . ') ';
		$sql .= "AND follow_type = 'activity'";

		$followed_ids = $GLOBALS['wpdb']->get_results( $sql );

		foreach ( $followed_ids as $activity ) {
			// clear followers count for activity item.
			wp_cache_delete( $activity->leader_id, 'bp_follow_activity_followers_count' );

			// clear queried followers for activity item.
			wp_cache_delete( $activity->leader_id, 'bp_follow_activity_followers_query' );

			// delete user's activity follow count.
			wp_cache_delete( $activity->follower_id, 'bp_follow_user_activity_following_count' );

			// delete queried activity that user was following.
			wp_cache_delete( $activity->follower_id, 'bp_follow_user_activity_following_query' );

			// Delete the follow entry
			// @todo Need a mass bulk-delete method.
			bp_follow_stop_following( array(
				'leader_id'   => $activity->leader_id,
				'follower_id' => $activity->follower_id,
				'follow_type' => 'activity',
			) );
		}
	}

	/** RSS ******************************************************************/

	/**
	 * Sets the "RSS" feed URL for the tab on the Sitewide Activity page.
	 *
	 * This occurs when the "Followed Activity" tab is clicked on the Sitewide
	 * Activity page or when the activity scope is already set to "followblogs".
	 *
	 * Only do this for BuddyPress 1.8+.
	 *
	 * @param string $retval The feed URL.
	 * @return string The feed URL.
	 */
	public function activity_feed_url( $retval ) {
		// only available in BP 1.8+
		if ( ! class_exists( 'BP_Activity_Feed' ) ) {
			return $retval;
		}

		// This filters the RSS link when on a user's "Activity > Papers" page.
		if ( 'bp_get_activities_member_rss_link' === current_filter() && '' == $retval && bp_is_current_action( constant( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ) ) ) {
			return esc_url( bp_follow_get_user_url( bp_displayed_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ), array( 'feed' ) ) ) );
		}

		// this is done b/c we're filtering 'bp_get_sitewide_activity_feed_link' and
		// we only want to alter the feed link for the "RSS" tab
		if ( ! defined( 'DOING_AJAX' ) && ! did_action( 'bp_before_directory_activity' ) ) {
			return $retval;
		}

		// get the activity scope.
		$scope = ! empty( $_COOKIE['bp-activity-scope'] ) ? $_COOKIE['bp-activity-scope'] : false;

		if ( 'follow' === $scope && bp_loggedin_user_id() ) {
			$retval = bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ), array( 'feed' ) ) );
		}

		return esc_url( $retval );
	}

	/**
	 * RSS handler for a user's followed sites.
	 *
	 * When a user lands on /members/USERNAME/activity/followblogs/feed/, this
	 * method generates the RSS feed for their followed sites.
	 */
	public function rss_handler() {
		// only available in BP 1.8+
		if ( ! class_exists( 'BP_Activity_Feed' ) ) {
			return;
		}

		if ( ! bp_is_user_activity() || ! bp_is_current_action( constant( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ) ) || ! bp_is_action_variable( 'feed', 0 ) ) {
			return;
		}

		$bp = $GLOBALS['bp'];

		$args = array(
			'user_id' => bp_displayed_user_id(),
			'scope'   => 'follow',
		);

		// setup the feed.
		$bp->activity->feed = new BP_Activity_Feed( array(
			'id'            => 'followedactivity',

			/* translators: User's following activity RSS title - "[Site Name] | [User Display Name] | Followed Activity" */
			'title'         => sprintf( __( '%1$s | %2$s | Followed Activity', 'buddypress-followers' ), bp_get_site_name(), bp_get_displayed_user_fullname() ),

			'link'          => esc_url( bp_follow_get_user_url( bp_displayed_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOW_ACTIVITY_USER_ACTIVITY_SLUG' ) ) ) ),
			'description'   => sprintf( __( "Feed for activity that %s is following.", 'buddypress' ), bp_get_displayed_user_fullname() ),
			'activity_args' => $args,
		) );
	}
}
