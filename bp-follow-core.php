<?php
/**
 * BP Follow Core
 *
 * @package BP-Follow
 * @subpackage Core
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

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
	 * Revision Date.
	 *
	 * @var string The current revision date.
	 */
	public $revision_date = '2014-08-07 22:00 UTC';

	/**
	 * Component parameters.
	 *
	 * @var array Misc parameters.
	 */
	public $params = array();

	/**
	 * Updater class.
	 *
	 * @var BP_Follow_Updater Updater class.
	 */
	public $updater;

	/**
	 * Follow Activity Class.
	 *
	 * @var BP_Follow_Activity_Core Follow Activity Class.
	 */
	public $activity;

	/**
	 * Follow Blogs Class.
	 *
	 * @var BP_Follow_Blogs Follow Blogs Class.
	 */
	public $blogs;

	/**
	 * Global cache groups.
	 *
	 * @var array Global cache groups.
	 */
	public $global_cachegroups = array();

	/**
	 * Used to globalize data about followers.
	 *
	 * @var object Globalized data about followers.
	 */
	public $followers;

	/**
	 * Used to globalize data about following.
	 *
	 * @var object Globalized data about following.
	 */
	public $following;

	/**
	 * Name of the component's DB table.
	 *
	 * @var string Name of the component's DB table.
	 */
	public $table_name = '';

	/**
	 * Whether activity scope is set or not.
	 *
	 * @var int 1 if activity scope is set, 0 otherwise.
	 */
	public $activity_scope_set = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$bp = $GLOBALS['bp'];

		// setup misc parameters.
		$this->params = array(
			'adminbar_myaccount_order' => apply_filters( 'bp_follow_following_nav_position', 61 ),
		);

		// let's start the show!
		parent::start(
			'follow',
			__( 'Follow', 'buddypress-followers' ),
			constant( 'BP_FOLLOW_DIR' ) . '/_inc',
			$this->params
		);

		// include our files.
		$this->includes();

		// setup hooks.
		$this->setup_hooks();

		// register our component as an active component in BP.
		$bp->active_components[ $this->id ] = '1';
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

		// users module.
		if ( true === (bool) apply_filters( 'bp_follow_enable_users', true ) ) {
			require( $this->path . '/users/hooks.php' );
			require( $this->path . '/users/template.php' );
			require( $this->path . '/users/notifications.php' );
			require( $this->path . '/users/widgets.php' );

			// Load AJAX code when an AJAX request is requested.
			add_action( 'admin_init', function() {
				if ( defined( 'DOING_AJAX' ) && true === DOING_AJAX && isset( $_POST['action'] ) && false !== strpos( $_POST['action'], 'follow' ) ) {
					require $this->path . '/users/ajax.php';
				}
			} );

			/**
			 * Conditional includes.
			 *
			 * bp_setup_canonical_stack() is a BP 2.1 function and we still support v1.5.
			 */
			if ( function_exists( 'bp_setup_canonical_stack' ) ) {
				$load_hook = 'bp_setup_canonical_stack';
				$priority  = 20;
			} else {
				$load_hook = 'bp_init';
				$priority  = 5;
			}
			add_action( $load_hook, function() {
				// Actions
				if ( bp_is_current_component( $this->followers->slug ) || bp_is_action_variable( 'feed', 0 ) ) {
					require_once $this->path . '/users/actions.php';
				}

				// Screens
				if ( bp_is_current_component( $this->following->slug ) || bp_is_current_component( $this->followers->slug ) ||
					( bp_is_current_component( 'activity' ) && bp_is_current_action( $this->following->slug ) )
				) {
					require_once $this->path . '/users/screens.php';
				}
			}, $priority );
		}

		// blogs module - on multisite and BP 2.0+ only.
		if ( function_exists( 'bp_add_option' ) && bp_is_active( 'blogs' ) && is_multisite() && bp_is_network_activated() && apply_filters( 'bp_follow_enable_blogs', true ) ) {
			require( $this->path . '/modules/blogs.php' );
		}

		// activity module - BP 2.2+ only.
		if ( class_exists( 'BP_Activity_Query' ) && bp_is_active( 'activity' ) ) {
			require( $this->path . '/modules/activity.php' );
		}

		// updater.
		if ( defined( 'WP_NETWORK_ADMIN' ) ) {
			require( $this->path . '/bp-follow-updater.php' );
		}
	}

	/**
	 * Setup globals.
	 *
	 * @since 1.3.0 Add 'global_cachegroups' property
	 */
	public function setup_globals( $args = array() ) {

		// Constants.
		if ( ! defined( 'BP_FOLLOWERS_SLUG' ) ) {
			define( 'BP_FOLLOWERS_SLUG', 'followers' );
		}

		if ( ! defined( 'BP_FOLLOWING_SLUG' ) ) {
			define( 'BP_FOLLOWING_SLUG', 'following' );
		}

		$bp = $GLOBALS['bp'];

		/**
		 * Register other globals here since BP isn't flexible enough to add them in
		 * the parent::setup_globals() method
		 */
		// global cachegroups.
		$this->global_cachegroups = array( 'bp_follow_data' );

		// slugs; would rather do away with this, but keeping it for backpat.
		$this->followers = new stdClass();
		$this->following = new stdClass();
		$this->followers->slug = constant( 'BP_FOLLOWERS_SLUG' );
		$this->following->slug = constant( 'BP_FOLLOWING_SLUG' );

		/** Core setup globals ************************************************/

		parent::setup_globals( array(
			'notification_callback' => 'bp_follow_format_notifications',
			'global_tables' => array(
				'table_name' => $bp->table_prefix . 'bp_follow',
			),
		) );
	}

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		// register global cachegroups.
		add_action( 'bp_init', array( $this, 'register_global_cachegroups' ), 5 );

		// register notification settings.
		add_action( 'bp_init', array( $this, 'register_notification_settings' ) );

		// javascript hook.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 11 );
	}

	/**
	 * Register global cachegroups.
	 *
	 * Replacement for {@link BP_Component::setup_cache_groups()}, made available in BP
	 * 2.2.0.  That class method runs too early.  This is an alternative way to
	 * register global cachegroups.
	 *
	 * @since 1.3.0
	 *
	 * @see BP_Follow_Component::setup_globals()
	 */
	public function register_global_cachegroups() {
		wp_cache_add_global_groups( (array) $this->global_cachegroups );
	}

	/**
	 * Registers notification settings block.
	 *
	 * Only shows if there are follow modules with notification settings enabled.
	 *
	 * @since 1.3.0
	 */
	public function register_notification_settings() {
		if ( has_action( 'bp_follow_screen_notification_settings' ) ) {
			add_action( 'bp_notification_settings', 'bp_follow_notification_settings_content' );
		}
	}

	/**
	 * Enqueues the javascript.
	 *
	 * The JS is used to add AJAX functionality when clicking on the follow button.
	 */
	public function enqueue_scripts() {
		// Do not enqueue if no user is logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Do not enqueue on multisite if not on multiblog and not on root blog.
		if ( ! bp_is_multiblog_mode() && ! bp_is_root_blog() ) {
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
	$bp = $GLOBALS['bp'];

	$bp->follow = new BP_Follow_Component();

	// Load up the updater if we're in the admin area
	//
	// Checking the WP_NETWORK_ADMIN define is a more, reliable check to determine
	// if we're in the admin area.
	if ( defined( 'WP_NETWORK_ADMIN' ) ) {
		$bp->follow->updater = new BP_Follow_Updater();
	}

	do_action( 'bp_follow_loaded' );
}
add_action( 'bp_loaded', 'bp_follow_setup_component' );
