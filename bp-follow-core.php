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
	 * @var string The current revision date.
	 */
	public $revision_date = '2014-08-07 22:00 UTC';

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

		/**  Backwards-compatibility ******************************************/

		// template stack for BP < 1.7
		if ( ! class_exists( 'BP_Theme_Compat' ) ) {
			require( $this->path . '/backpat/template-stack.php' );
		}

		// activity scope for BP < 2.2
		if ( ! class_exists( 'BP_Activity_Query' ) ) {
			require( $this->path . '/backpat/activity-scope.php' );
		}

		/** Core **************************************************************/
		require( $this->path . '/bp-follow-classes.php' );
		require( $this->path . '/bp-follow-functions.php' );

		// users module
		if ( true === (bool) apply_filters( 'bp_follow_enable_users', true ) ) {
			require( $this->path . '/users/screens.php' );
			require( $this->path . '/users/actions.php' );
			require( $this->path . '/users/hooks.php' );
			require( $this->path . '/users/template.php' );
			require( $this->path . '/users/notifications.php' );
			require( $this->path . '/users/widgets.php' );
		}

		// blogs module - on multisite and BP 2.0+ only
		if ( function_exists( 'bp_add_option' ) && bp_is_active( 'blogs' ) && is_multisite() && bp_is_network_activated() && apply_filters( 'bp_follow_enable_blogs', true ) ) {
			require( $this->path . '/modules/blogs.php' );
		}

		// updater
		if ( defined( 'WP_NETWORK_ADMIN' ) ) {
			require( $this->path . '/bp-follow-updater.php' );
		}
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
		}

		/**
		 * Register other globals here since BP isn't really flexible enough to add it
		 * in the parent::setup_globals() method
		 */
		// slugs; would rather do away with this, but keeping it for backpat
		$this->followers = new stdClass;
		$this->following = new stdClass;
		$this->followers->slug = constant( 'BP_FOLLOWERS_SLUG' );
		$this->following->slug = constant( 'BP_FOLLOWING_SLUG' );

		// Set up the $globals array
		$globals = array(
			'notification_callback' => 'bp_follow_format_notifications',
			'global_tables'         => array(
				'table_name' => $bp->table_prefix . 'bp_follow',
			)
		);

		// Let BP_Component::setup_globals() do its work.
		parent::setup_globals( $globals );
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		// javascript hook
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 11 );
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

		wp_enqueue_script( 'bp-follow-js', constant( 'BP_FOLLOW_URL' ) . '_inc/bp-follow.js', array( 'jquery' ), strtotime( $this->revision_date ) );
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

	// Load up the updater if we're in the admin area
	//
	// Checking the WP_NETWORK_ADMIN define is a more, reliable check to determine
	// if we're in the admin area
	if ( defined( 'WP_NETWORK_ADMIN' ) ) {
		$bp->follow->updater = new BP_Follow_Updater;
	}

	do_action( 'bp_follow_loaded' );
}
add_action( 'bp_loaded', 'bp_follow_setup_component' );
