<?php
/**
 * BP Follow Core
 *
 * @package BP-Follow
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core class for BP Follow.
 *
 * Extends the {@link BP_Component} class.
 *
 * @package BP-Follow
 * @subpackage Classes
 *
 * @since 1.2
 */
class BP_Follow_Component extends BP_Component {

	/**
	 * Constructor.
	 *
	 * @global obj $bp BuddyPress instance
	 */
	public function __construct() {
		global $bp;

		// setup misc parameters
		$this->params = array(
			'adminbar_myaccount_order' => apply_filters( 'bp_follow_following_nav_position', 61 )
		);

		// let's start the show!
		parent::start(
			'follow',
			__( 'Follow', 'bp-follow' ),
			constant( 'BP_FOLLOW_DIR' ) . '/_inc',
			$this->params
		);

		// include our files
		$this->includes();

		// setup hooks
		$this->setup_hooks();

		// register our component as an active component in BP
		$bp->active_components[$this->id] = '1';
	}

	/**
	 * Includes.
	 */
	public function includes( $includes = array() ) {

		// Backpat functions for BP < 1.7
		if ( ! class_exists( 'BP_Theme_Compat' ) )
			require( $this->path . '/bp-follow-backpat.php' );

		require( $this->path . '/bp-follow-classes.php' );
		require( $this->path . '/bp-follow-functions.php' );
		require( $this->path . '/bp-follow-screens.php' );
		require( $this->path . '/bp-follow-actions.php' );
		require( $this->path . '/bp-follow-hooks.php' );
		require( $this->path . '/bp-follow-templatetags.php' );
		require( $this->path . '/bp-follow-notifications.php' );
		require( $this->path . '/bp-follow-widgets.php' );

	}

	/**
	 * Setup globals.
	 *
	 * @global obj $bp BuddyPress instance
	 */
	public function setup_globals( $args = array() ) {
		global $bp;

		if ( ! defined( 'BP_FOLLOWERS_SLUG' ) )
			define( 'BP_FOLLOWERS_SLUG', 'followers' );

		if ( ! defined( 'BP_FOLLOWING_SLUG' ) )
			define( 'BP_FOLLOWING_SLUG', 'following' );

		// Set up the $globals array
		$globals = array(
			'notification_callback' => 'bp_follow_format_notifications',
			'global_tables'         => array(
				'table_name' => $bp->table_prefix . 'bp_follow',
			)
		);

		// Let BP_Component::setup_globals() do its work.
		parent::setup_globals( $globals );

		// register other globals since BP isn't really flexible enough to add it
		// in the setup_globals() method
		//
		// would rather do away with this, but keeping it for backpat
		$bp->follow->followers = new stdClass;
		$bp->follow->following = new stdClass;
		$bp->follow->followers->slug = constant( 'BP_FOLLOWERS_SLUG' );
		$bp->follow->following->slug = constant( 'BP_FOLLOWING_SLUG' );

		// locally cache total count values for logged-in user
		if ( is_user_logged_in() ) {
			$bp->loggedin_user->total_follow_counts = bp_follow_total_follow_counts( array(
				'user_id' => bp_loggedin_user_id()
			) );
		}

		// locally cache total count values for displayed user
		if ( bp_is_user() && ( bp_loggedin_user_id() != bp_displayed_user_id() ) ) {
			$bp->displayed_user->total_follow_counts = bp_follow_total_follow_counts( array(
				'user_id' => bp_displayed_user_id()
			) );
		}

	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		// javascript hook
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 11 );
	}

	/**
	 * Setup profile / BuddyBar navigation
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {
		global $bp;

		// Need to change the user ID, so if we're not on a member page, $counts variable is still calculated
		$user_id = bp_is_user() ? bp_displayed_user_id() : bp_loggedin_user_id();
		$counts  = bp_follow_total_follow_counts( array( 'user_id' => $user_id ) );

		// BuddyBar compatibility
		$domain = bp_displayed_user_domain() ? bp_displayed_user_domain() : bp_loggedin_user_domain();

		/** FOLLOWERS NAV ************************************************/

		bp_core_new_nav_item( array(
			'name'                => sprintf( __( 'Following <span>%d</span>', 'bp-follow' ), $counts['following'] ),
			'slug'                => $bp->follow->following->slug,
			'position'            => $this->params['adminbar_myaccount_order'],
			'screen_function'     => 'bp_follow_screen_following',
			'default_subnav_slug' => 'following',
			'item_css_id'         => 'members-following'
		) );

		/** FOLLOWING NAV ************************************************/

		bp_core_new_nav_item( array(
			'name'                => sprintf( __( 'Followers <span>%d</span>', 'bp-follow' ), $counts['followers'] ),
			'slug'                => $bp->follow->followers->slug,
			'position'            => apply_filters( 'bp_follow_followers_nav_position', 62 ),
			'screen_function'     => 'bp_follow_screen_followers',
			'default_subnav_slug' => 'followers',
			'item_css_id'         => 'members-followers'
		) );

		/** ACTIVITY SUBNAV **********************************************/

		// Add activity sub nav item
		if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_show_activity_subnav', true ) ) {

			bp_core_new_subnav_item( array(
				'name'            => _x( 'Following', 'Activity subnav tab', 'bp-follow' ),
				'slug'            => constant( 'BP_FOLLOWING_SLUG' ),
				'parent_url'      => trailingslashit( $domain . bp_get_activity_slug() ),
				'parent_slug'     => bp_get_activity_slug(),
				'screen_function' => 'bp_follow_screen_activity_following',
				'position'        => 21,
				'item_css_id'     => 'activity-following'
			) );
		}

		// BuddyBar compatibility
		add_action( 'bp_adminbar_menus', array( $this, 'group_buddybar_items' ), 3 );

		do_action( 'bp_follow_setup_nav' );

	}

	/**
	 * Set up WP Toolbar / Admin Bar.
	 *
	 * @global obj $bp BuddyPress instance
	 */
	public function setup_admin_bar( $wp_admin_nav = array() ) {

		// Menus for logged in user
		if ( is_user_logged_in() ) {
			global $bp;

			// "Follow" parent nav menu
			$wp_admin_nav[] = array(
				'parent' => $bp->my_account_menu_id,
				'id'     => 'my-account-' . $this->id,
				'title'  => _x( 'Follow', 'Adminbar main nav', 'bp-follow' ),
				'href'   => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug )
			);

			// "Following" subnav item
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-following',
				'title'  => _x( 'Following', 'Adminbar follow subnav', 'bp-follow' ),
				'href'   => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug )
			);

			// "Followers" subnav item
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->id,
				'id'     => 'my-account-' . $this->id . '-followers',
				'title'  => _x( 'Followers', 'Adminbar follow subnav', 'bp-follow' ),
				'href'   => trailingslashit( bp_loggedin_user_domain() . $bp->follow->followers->slug )
			);

			// "Activity > Following" subnav item
			if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_show_activity_subnav', true ) ) {
				$wp_admin_nav[] = array(
					'parent' => 'my-account-activity',
					'id'     => 'my-account-activity-following',
					'title'  => _x( 'Following', 'Adminbar activity subnav', 'bp-follow' ),
					'href'   => trailingslashit( bp_loggedin_user_domain() . bp_get_activity_slug() . '/' . $bp->follow->following->slug )
				);
			}

		}

		parent::setup_admin_bar( apply_filters( 'bp_follow_toolbar', $wp_admin_nav ) );
	}

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
	 * @global object $bp BuddyPress global settings
	 * @uses bp_follow_total_follow_counts() Get the following/followers counts for a user.
	 */
	public function group_buddybar_items() {
		// don't do this if we're using the WP Admin Bar / Toolbar
		if ( defined( 'BP_USE_WP_ADMIN_BAR' ) && BP_USE_WP_ADMIN_BAR )
			return;

		if ( ! bp_loggedin_user_id() )
			return;

		global $bp;

		// get follow nav positions
		$following_position = $this->params['adminbar_myaccount_order'];
		$followers_position = apply_filters( 'bp_follow_followers_nav_position', 62 );

		// clobberin' time!
		unset( $bp->bp_nav[$following_position] );
		unset( $bp->bp_nav[$followers_position] );
		unset( $bp->bp_options_nav['following'] );
		unset( $bp->bp_options_nav['followers'] );

		// Add the "Follow" nav menu
		$bp->bp_nav[$following_position] = array(
			'name'                    => _x( 'Follow', 'Adminbar main nav', 'bp-follow' ),
			'link'                    => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug ),
			'slug'                    => 'follow',
			'css_id'                  => 'follow',
			'position'                => $following_position,
			'show_for_displayed_user' => 1,
			'screen_function'         => 'bp_follow_screen_followers'
		);

		// "Following" subnav item
		$bp->bp_options_nav['follow'][10] = array(
			'name'            => _x( 'Following', 'Adminbar follow subnav', 'bp-follow' ),
			'link'            => trailingslashit( bp_loggedin_user_domain() . $bp->follow->following->slug ),
			'slug'            => $bp->follow->following->slug,
			'css_id'          => 'following',
			'position'        => 10,
			'user_has_access' => 1,
			'screen_function' => 'bp_follow_screen_followers'
		);

		// "Followers" subnav item
		$bp->bp_options_nav['follow'][20] = array(
			'name'            => _x( 'Followers', 'Adminbar follow subnav', 'bp-follow' ),
			'link'            => trailingslashit( bp_loggedin_user_domain() . $bp->follow->followers->slug ),
			'slug'            => $bp->follow->followers->slug,
			'css_id'          => 'followers',
			'position'        => 20,
			'user_has_access' => 1,
			'screen_function' => 'bp_follow_screen_followers'
		);

		// Resort the nav items to account for the late change made above
		ksort( $bp->bp_nav );
	}

	/**
	 * Enqueues the javascript.
	 *
	 * The JS is used to add AJAX functionality when clicking on the follow button.
	 */
	public function enqueue_scripts() {
		// Do not enqueue if no user is logged in
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Do not enqueue on multisite if not on multiblog and not on root blog
		if( ! bp_is_multiblog_mode() && ! bp_is_root_blog() ) {
			return;
		}

		wp_enqueue_script( 'bp-follow-js', constant( 'BP_FOLLOW_URL' ) . '_inc/bp-follow.js', array( 'jquery' ) );
	}

}

/**
 * Loads the Follow component into the $bp global
 *
 * @package BP-Follow
 * @global obj $bp BuddyPress instance
 * @since 1.2
 */
function bp_follow_setup_component() {
	global $bp;

	$bp->follow = new BP_Follow_Component;
}
add_action( 'bp_loaded', 'bp_follow_setup_component' );
