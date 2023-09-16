<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Follow Blogs Loader.
 *
 * @since 1.3.0
 */
function bp_follow_blogs_init() {
	$bp = $GLOBALS['bp'];

	$bp->follow->blogs = new BP_Follow_Blogs();

	do_action( 'bp_follow_blogs_loaded' );
}
add_action( 'bp_follow_loaded', 'bp_follow_blogs_init' );

/**
 * Follow Blogs module.
 *
 * @since 1.3.0
 */
class BP_Follow_Blogs {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// includes.
		$this->includes();

		// component hooks.
		add_action( 'bp_follow_setup_globals', array( $this, 'constants' ) );
		add_action( 'bp_follow_setup_globals', array( $this, 'setup_global_cachegroups' ) );
		add_action( 'bp_follow_setup_nav',     array( $this, 'setup_nav' ) );
		add_action( 'bp_activity_admin_nav',   array( $this, 'activity_admin_nav' ) );
		add_filter( 'bp_blogs_admin_nav',      array( $this, 'blogs_admin_nav' ) );

		// screen hooks.
		add_action( 'bp_after_member_blogs_content', 'BP_Follow_Blogs_Screens::user_blogs_inline_js' );
		add_action( 'bp_actions',                    'BP_Follow_Blogs_Screens::action_handler' );
		add_action( 'bp_actions',                    'BP_Follow_Blogs_Screens::rss_handler' );
		add_action( 'wp_ajax_bp_follow_blogs',       'BP_Follow_Blogs_Screens::ajax_handler' );

		// directory tabs.
		add_action( 'bp_before_activity_type_tab_favorites', array( $this, 'add_activity_directory_tab' ) );
		add_action( 'bp_blogs_directory_blog_types',         array( $this, 'add_blog_directory_tab' ) );

		// loop filtering.
		add_filter( 'bp_activity_set_followblogs_scope_args', array( $this, 'filter_activity_scope' ), 10, 2 );
		add_filter( 'bp_ajax_querystring', array( $this, 'add_blogs_scope_filter' ),    20, 2 );
		add_filter( 'bp_has_blogs',        array( $this, 'bulk_inject_blog_follow_status' ) );

		// button injection.
		add_action( 'bp_directory_blogs_actions', array( $this, 'add_follow_button_to_loop' ),   20 );
		add_action( 'wp_footer',                  array( $this, 'add_follow_button_to_footer' ) );
		add_action( 'wp_enqueue_scripts',         array( $this, 'enqueue_script' ) );

		// blog deletion.
		add_action( 'bp_blogs_remove_blog', array( $this, 'on_blog_delete' ) );

		// cache invalidation.
		add_action( 'bp_follow_start_following_blogs', array( $this, 'clear_cache_on_follow' ) );
		add_action( 'bp_follow_stop_following_blogs',  array( $this, 'clear_cache_on_follow' ) );
		add_action( 'bp_follow_before_remove_data',    array( $this, 'clear_cache_on_user_delete' ) );

		// rss feed link.
		add_filter( 'bp_get_sitewide_activity_feed_link', array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_dtheme_activity_feed_url',        array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_legacy_theme_activity_feed_url',  array( $this, 'activity_feed_url' ) );
	}

	/**
	 * Includes.
	 */
	protected function includes() {
		$bp = $GLOBALS['bp'];

		if ( ! class_exists( 'BP_Activity_Query' ) ) {
			require( $bp->follow->path . '/modules/blogs-backpat.php' );
		}
	}

	/**
	 * Constants.
	 */
	public function constants() {
		// /members/admin/blogs/[FOLLOWING]
		if ( ! defined( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) ) {
			define( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG', constant( 'BP_FOLLOWING_SLUG' ) );
		}

		// /members/admin/activity/[FOLLOWBLOGS]
		if ( ! defined( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) {
			define( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG', 'followblogs' );
		}
	}

	/**
	 * Set up global cachegroups.
	 */
	public function setup_global_cachegroups() {
		$bp = $GLOBALS['bp'];

		// blog counts.
		$bp->follow->global_cachegroups[] = 'bp_follow_user_blogs_following_count';
		$bp->follow->global_cachegroups[] = 'bp_follow_blogs_followers_count';

		// blog data query.
		$bp->follow->global_cachegroups[] = 'bp_follow_user_blogs_following_query';
		$bp->follow->global_cachegroups[] = 'bp_follow_blogs_followers_query';
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

		bp_core_new_subnav_item( array(
			'name'            => _x( 'Followed Sites', 'Sites subnav tab', 'buddypress-followers' ),
			'slug'            => constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ),
			'parent_url'      => bp_follow_get_user_url( $user_id, array( bp_get_blogs_slug() ) ),
			'parent_slug'     => bp_get_blogs_slug(),
			'screen_function' => 'BP_Follow_Blogs_Screens::user_blogs_screen',
			'position'        => 20,
			'item_css_id'     => 'blogs-following',
		) );

		// Add activity sub nav item
		if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_blogs_show_activity_subnav', true ) ) {
			bp_core_new_subnav_item( array(
				'name'            => _x( 'Followed Sites', 'Activity subnav tab', 'buddypress-followers' ),
				'slug'            => constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ),
				'parent_url'      => bp_follow_get_user_url( $user_id, array( bp_get_activity_slug() ) ),
				'parent_slug'     => bp_get_activity_slug(),
				'screen_function' => 'BP_Follow_Blogs_Screens::user_activity_screen',
				'position'        => 22,
				'item_css_id'     => 'activity-followblogs',
			) );
		}
	}

	/**
	 * Inject "Followed Sites" nav item to WP adminbar's "Activity" main nav.
	 *
	 * @param array $retval Return Value.
	 * @return array
	 */
	public function activity_admin_nav( $retval ) {
		if ( ! is_user_logged_in() ) {
			return $retval;
		}

		if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_show_activity_subnav', true ) ) {
			$new_item = array(
				'parent' => 'my-account-activity',
				'id'     => 'my-account-activity-followblogs',
				'title'  => _x( 'Followed Sites', 'Adminbar activity subnav', 'buddypress-followers' ),
				'href'   => bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) ),
			);

			$inject = array();
			$offset = 4;

			$inject[ $offset ] = $new_item;
			$retval = array_merge(
				array_slice( $retval, 0, $offset, true ),
				$inject,
				array_slice( $retval, $offset, NULL, true )
			);
		}

		return $retval;
	}

	/**
	 * Inject "Followed Sites" nav item to WP adminbar's "Sites" main nav.
	 *
	 * @param array $retval Return Value.
	 * @return array
	 */
	public function blogs_admin_nav( $retval ) {
		if ( ! is_user_logged_in() ) {
			return $retval;
		}

		$new_item = array(
			'parent' => 'my-account-blogs',
			'id'     => 'my-account-blogs-following',
			'title'  => _x( 'Followed Sites', 'Adminbar blogs subnav', 'buddypress-followers' ),
			'href'   => bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_blogs_slug(), constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) ) ),
		);

		$inject = array();
		$last   = end( $retval );

		// inject item in between "My Sites" and "Create a Site" subnav items.
		if ( 'my-account-blogs-create' === $last['id'] ) {
			$offset = key( $retval );

			$inject[ $offset ] = $new_item;

			$retval = array_merge( array_slice( $retval, 0, $offset, true ), $inject, array_slice( $retval, $offset, NULL, true ) );

		// "Create a Site" is disabled; just add nav item to the end
		} else {
			$inject = array();
			$inject[] = $new_item;
			$retval = array_merge( $retval, $inject );
		}

		return $retval;
	}

	/** DIRECTORY TABS ************************************************/

	/**
	 * Adds a "Followed Sites (X)" tab to the activity directory.
	 *
	 * This is so the logged-in user can filter the activity stream to only sites
	 * that the current user is following.
	 */
	public function add_activity_directory_tab() {
		$counts = bp_follow_total_follow_counts( array(
			'user_id'     => bp_loggedin_user_id(),
			'follow_type' => 'blogs',
		) );

		/*
		if ( empty( $counts['following'] ) ) {
			return false;
		}
		*/

		$following_blog_url = bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_blogs_slug(), constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) ) );
		?>
		<li id="activity-followblogs"><a href="<?php echo esc_url( $following_blog_url ); ?>"><?php printf( esc_html__( 'Followed Sites %s', 'buddypress-followers' ), '<span>' . esc_html( bp_core_number_format( $counts['following'] ) ) . '</span>' ); ?></a></li><?php
	}


	/**
	 * Add a "Following (X)" tab to the sites directory.
	 *
	 * This is so the logged-in user can filter the site directory to only
	 * sites that the current user is following.
	 */
	function add_blog_directory_tab() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$counts = bp_follow_total_follow_counts( array(
			'user_id'     => bp_loggedin_user_id(),
			'follow_type' => 'blogs',
		) );

		if ( empty( $counts['following'] ) ) {
			return false;
		}

		$following_blog_url = bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_blogs_slug(), constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) ) );
		?>
		<li id="blogs-following"><a href="<?php echo esc_url( $following_blog_url ); ?>"><?php printf( esc_html__( 'Following %s', 'buddypress-followers' ), '<span>' . esc_html( bp_core_number_format( $counts['following'] ) ) . '</span>' ); ?></a></li><?php
	}

	/** LOOP-FILTERING ************************************************/

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
	function filter_activity_scope( $retval = array(), $filter = array() ) {
		$bp = $GLOBALS['bp'];

		// Determine the user_id.
		if ( ! empty( $filter['user_id'] ) ) {
			$user_id = $filter['user_id'];
		} else {
			$user_id = bp_displayed_user_id()
				? bp_displayed_user_id()
				: bp_loggedin_user_id();
		}

		// Get blogs that the user is following.
		$following_ids = bp_follow_get_following( array(
			'user_id'     => $user_id,
			'follow_type' => 'blogs',
		) );
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

		// support BP Groupblog.
		if ( function_exists( 'bp_groupblog_init' ) && array( 0 ) !== $following_ids ) {
			global $wpdb;

			$bp = $GLOBALS['bp'];

			// comma-delimit the blog IDs.
			$delimited_ids = implode( ',', $following_ids );
			$group_ids_connected_to_blogs = $wpdb->get_col( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'groupblog_blog_id' AND meta_value IN ( " . $delimited_ids . " )" );

			$clause = array(
				'relation' => 'OR',

				// general blog activity items.
				array(
					'relation' => 'AND',
					array(
						'column' => 'component',
						'value'  => $bp->blogs->id,
					),
					array(
						'column'  => 'item_id',
						'compare' => 'IN',
						'value'   => (array) $following_ids,
					),
				),

				// groupblog posts.
				array(
					'relation' => 'AND',
					array(
						'column' => 'component',
						'value'  => $bp->groups->id,
					),
					array(
						'column'  => 'item_id',
						'compare' => 'IN',
						'value'   => (array) $group_ids_connected_to_blogs,
					),
					array(
						'column'  => 'type',
						'value'   => 'new_groupblog_post',
					),
				),
			);

		// Regular follow blog clause
		} else {
			$clause = array(
				'relation' => 'AND',
				array(
					'column' => 'component',
					'value'  => $bp->blogs->id,
				),
				array(
					'column'  => 'item_id',
					'compare' => 'IN',
					'value'   => (array) $following_ids,
				),
			);
		}

		$retval = array(
			'relation' => 'AND',
			$clause,
			$show_hidden,

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

	/**
	 * Filter the blogs loop.
	 *
	 * Specifically, filter when we're on:
	 *  - a user's "Followed Sites" page
	 *  - the Sites directory and clicking on the "Following" tab
	 *
	 * @param str $qs The querystring for the BP loop.
	 * @param str $object The current object for the querystring.
	 * @return str Modified querystring
	 */
	function add_blogs_scope_filter( $qs, $object ) {
		// not on the blogs object? stop now!
		if ( 'blogs' !== $object ) {
			return $qs;
		}

		// parse querystring into an array.
		$r = wp_parse_args( $qs );

		// set scope if a user is on a user's "Followed Sites" page.
		if ( bp_is_user_blogs() && bp_is_current_action( constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) ) ) {
			$r['scope'] = 'following';
		}

		if ( empty( $r['scope'] ) || 'following' !== $r['scope'] ) {
			return $qs;
		}

		// get blog IDs that the user is following.
		$following_ids = bp_get_following_ids( array(
			'user_id'     => bp_displayed_user_id() ? bp_displayed_user_id() : bp_loggedin_user_id(),
			'follow_type' => 'blogs',
		) );

		// if $following_ids is empty, pass the largest bigint(20) value to ensure
		// no blogs are matched.
		$following_ids = empty( $following_ids ) ? '18446744073709551615' : $following_ids;

		$args = array(
			'user_id'          => 0,
			'include_blog_ids' => $following_ids,
		);

		// make sure we add a separator if we have an existing querystring.
		if ( ! empty( $qs ) ) {
			$qs .= '&';
		}

		// add our follow parameters to the end of the querystring.
		$qs .= build_query( $args );

		return $qs;
	}

	/**
	 * Bulk-check the follow status of all blogs in a blogs loop.
	 *
	 * This is so we don't have query each follow blog status individually.
	 */
	public function bulk_inject_blog_follow_status( $has_blogs ) {
		global $blogs_template;

		if ( empty( $has_blogs ) ) {
			return $has_blogs;
		}

		if ( ! is_user_logged_in() ) {
			return $has_blogs;
		}

		$blog_ids = array();

		foreach ( (array) $blogs_template->blogs as $i => $blog ) {
			// add blog ID to array.
			$blog_ids[] = $blog->blog_id;

			// set default follow status to false.
			$blogs_template->blogs[ $i ]->is_following = false;
		}

		if ( empty( $blog_ids ) ) {
			return $has_blogs;
		}

		$following = BP_Follow::bulk_check_follow_status( $blog_ids, bp_loggedin_user_id(), 'blogs' );

		if ( empty( $following ) ) {
			return $has_blogs;
		}

		foreach ( (array) $following as $is_following ) {
			foreach ( (array) $blogs_template->blogs as $i => $blog ) {
				// set follow status to true if the logged-in user is following.
				if ( (int) $is_following->leader_id === (int) $blog->blog_id ) {
					$blogs_template->blogs[ $i ]->is_following = true;
				}
			}
		}

		return $has_blogs;
	}

	/** BUTTON ********************************************************/

	/**
	 * Registers and enqueues our JS.
	 */
	public function enqueue_script() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Register our script.
		wp_register_script( 'bp-follow-blogs', BP_FOLLOW_URL . '_inc/modules/blogs.js', [ 'jquery' ], false, true );

		// Nouveau requires early enqueuing on BP blog pages.
		if ( function_exists( 'bp_nouveau' ) && bp_is_blogs_component() ) {
			wp_enqueue_script( 'bp-follow-blogs' );
		}
	}

	/**
	 * Add a follow button to the blog loop.
	 */
	public function add_follow_button_to_loop() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		echo self::get_button();
	}

	/**
	 * Whether to show the blog footer buttons.
	 *
	 * @return bool Defaults to true. False when on BP root blog and not on a blog
	 *         page deemed by BuddyPress.
	 */
	public static function show_footer_button() {
		$retval = true;

		// @todo might need to tweak this a bit...
		if ( bp_is_root_blog() && ! bp_is_blog_page() ) {
			$retval = false;
		}

		// Bail if JSON request. Mostly for block widget render calls.
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			$retval = false;
		}

		return apply_filters( 'bp_follow_blogs_show_footer_button', $retval );
	}

	/**
	 * Add a follow button to the footer.
	 *
	 * Also adds a "Home" link, which links to the activity directory's "Sites I
	 * Follow" tab.
	 *
	 * This UI mimics Tumblr's.
	 */
	public function add_follow_button_to_footer() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// If blog is not recordable, do not show button.
		if ( ! bp_blogs_is_blog_recordable( get_current_blog_id(), bp_loggedin_user_id() ) ) {
			return;
		}

		// disable the footer button using this filter if needed.
		if ( false === self::show_footer_button() ) {
			return;
		}

		// remove inline CSS later... still testing.
	?>

		<style type="text/css">
			#bpf-blogs-ftr{
				position:fixed;
				bottom:5px;
				right: 5px;
				z-index:9999;
				text-align:right;
			}

			#bpf-blogs-ftr a {
				font: 600 12px/18px "Helvetica Neue","HelveticaNeue",Helvetica,Arial,sans-serif !important;
				color: #fff !important;
				text-decoration:none !important;
				background:rgba(0, 0, 0, 0.48);
				padding:2px 5px !important;
				border-radius: 4px;
			}
			#bpf-blogs-ftr a:hover {
				background:rgba(0, 0, 0, 0.42);
			}

			#bpf-blogs-ftr a:before {
				position: relative;
				top: 3px;
				font: normal 13px/1 'dashicons';
				padding-right:5px;
			}

			#bpf-blogs-ftr a.follow:before {
				content: "\f132";
			}

			#bpf-blogs-ftr a.unfollow:before {
				content: "\f460";
			}

			#bpf-blogs-ftr a.home:before {
				content: "\f155";
				top: 2px;
			}
		</style>

		<div id="bpf-blogs-ftr">
			<?php echo self::get_button( array(
				'leader_id'     => get_current_blog_id(),
				'follow_text'   => _x( 'Follow Site', 'Button', 'buddypress-followers' ),
				'unfollow_text' => _x( 'Unfollow Site', 'Button', 'buddypress-followers' ),
				'wrapper'       => false,
			) ); ?>

 			<?php
 				$btn_args = apply_filters( 'bp_follow_blogs_get_sites_button_args', array(
 					'class' => 'home',
 					'link' => bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_blogs_slug(), constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) ) ),
 					'text' => _x( 'Followed Sites', 'Footer button', 'buddypress-followers' ),
 				) );

				if ( ! empty( $btn_args ) && is_array( $btn_args ) ) {
					echo '<a class=' . esc_attr( $btn_args['class'] ) . ' href=' . esc_url( $btn_args['link'] ) . '>';
					echo $btn_args['text'];
					echo '</a>';
				}
 			?>
		</div>

	<?php
	}

	/**
	 * Static method to generate a follow blogs button.
	 */
	public static function get_button( $args = '' ) {
		global $blogs_template;

		$r = wp_parse_args( $args, array(
			'leader_id'     => ! empty( $blogs_template->in_the_loop ) ? bp_get_blog_id() : get_current_blog_id(),
			'follower_id'   => bp_loggedin_user_id(),
			'follow_text'   => _x( 'Follow', 'Button', 'buddypress-followers' ),
			'unfollow_text' => _x( 'Unfollow', 'Button', 'buddypress-followers' ),
			'link_text'     => '',
			'link_title'    => '',
			'wrapper_class' => 'blog-button',
			'link_class'    => '',
			'button_attr'   => [],
			'wrapper'       => 'div',
		) );

		if ( ! $r['leader_id'] || ! $r['follower_id'] ) {
			return false;
		}

		// Enqueue JS only if BP 2.7+.
		if ( class_exists( 'BP_Core_HTML_Element' ) ) {
			wp_enqueue_script( 'bp-follow-blogs' );
		}

		// if we're checking during a blog loop, then follow status is already
		// queried via bulk_inject_follow_blog_status()
		if ( ! empty( $blogs_template->in_the_loop ) && bp_loggedin_user_id() === $r['follower_id'] && bp_get_blog_id() === $r['leader_id'] ) {
			$is_following = $blogs_template->blog->is_following;

		// else we manually query the follow status
		} else {
			$is_following = bp_follow_is_following( array(
				'leader_id'   => $r['leader_id'],
				'follower_id' => $r['follower_id'],
				'follow_type' => 'blogs',
			) );
		}

		$button_attr = [
			'data-follow-blog-id' => $r['leader_id'],
		];

		// setup some variables.
		if ( $is_following ) {
			$id        = 'following';
			$action    = 'unfollow';

			if ( empty( $r['link_text'] ) ) {
				$r['link_text'] = $r['unfollow_text'];
			}

		} else {
			$id        = 'not-following';
			$action    = 'follow';

			if ( empty( $r['link_text'] ) ) {
				$r['link_text'] = $r['follow_text'];
			}
		}

		$button_attr['data-follow-action'] = $action;
		$button_attr['data-follow-nonce']  = wp_create_nonce( "bp_follow_blog_{$action}" );
		$button_attr['data-follow-text']   = $r['follow_text'];
		$button_attr['data-unfollow-text'] = $r['unfollow_text'];

		$wrapper_class = 'follow-button ' . $id;

		if ( ! empty( $r['wrapper_class'] ) ) {
			$wrapper_class .= ' ' . esc_attr( $r['wrapper_class'] );
		}

		$link_class = $action;

		if ( ! empty( $r['link_class'] ) ) {
			$link_class .= ' ' . esc_attr( $r['link_class'] );
		}

		// setup the button arguments.
		$button = array(
			'id'                => $id,
			'component'         => 'follow',
			'must_be_logged_in' => true,
			'block_self'        => false,
			'wrapper_class'     => $wrapper_class,
			'wrapper_id'        => 'follow-button-' . (int) $r['leader_id'],
			'link_href'         => wp_nonce_url(
				add_query_arg( 'blog_id', $r['leader_id'], home_url( '/' ) ),
				"bp_follow_blog_{$action}",
				"bpfb-{$action}"
			),
			'link_text'         => esc_attr( $r['link_text'] ),
			'link_title'        => esc_attr( $r['link_title'] ),
			'link_id'           => $action . '-' . (int) $r['leader_id'],
			'link_class'        => $link_class,
			'wrapper'           => ! empty( $r['wrapper'] ) ? esc_attr( $r['wrapper'] ) : false,
			'button_attr'       => $button_attr
		);

		// BP Nouveau-specific button arguments.
		if ( function_exists( 'bp_nouveau' ) && ! empty( $blogs_template->in_the_loop ) ) {
			$button['parent_element'] = 'li';
			$button['wrapper_class']  = '';
			$button['link_class']    .= ' button';
		}

		// Filter and return the HTML button.
		return bp_get_button( apply_filters( 'bp_follow_blogs_get_follow_button', $button, $r, $is_following ) );
	}

	/** DELETION ***********************************************************/

	/**
	 * Do stuff when a blog is deleted.
	 *
	 * @param int $blog_id The ID of the blog being deleted.
	 */
	public function on_blog_delete( $blog_id ) {
		global $wpdb;

		$bp = $GLOBALS['bp'];

		$this->clear_cache_on_blog_delete( $blog_id );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->follow->table_name} WHERE leader_id = %d AND follow_type = 'blogs'", $blog_id ) );
	}

	/**
	 * Save routine.
	 *
	 * @param array $r {
	 *     An array of arguments.
	 *     @type string $action  Type of follow action. Either 'follow' or 'unfollow'.
	 *     @type int    $blog_id Blog ID to follow or unfollow.
	 *     @type int    $user_id User ID initiating the follow request.
	 *     @type string $nonce   Nonce for the follow request.
	 * }
	 * @return bool|WP_Error Boolean true on success; WP_Error on failure.
	 */
	public static function save( $r = [] ) {
		if ( empty( $r['action'] ) || empty( $r['nonce'] ) || empty( $r['blog_id'] ) ) {
			return new WP_Error( 'empty', __( 'Missing required arguments', 'buddypress-followers' ) );
		}

		$action  = 'follow';
		$save    = 'bp_follow_start_following';
		$blog_id = (int) $r['blog_id'];
		$user_id = ! empty( $r['user_id'] ) ? (int) $r['user_id'] : bp_loggedin_user_id();

		if ( empty( $user_id ) ) {
			return new WP_Error( 'no_user_id', __( 'Missing user ID', 'buddypress-followers' ) );
		}

		if ( 'unfollow' === $r['action'] ) {
			$action = 'unfollow';
			$save   = 'bp_follow_stop_following';
		}

		if ( ! wp_verify_nonce( $r['nonce'], "bp_follow_blog_{$action}" ) ) {
			return new WP_Error( 'nonce', __( 'Nonce failure', 'buddypress-followers' ) );
		}

		if ( ! $save( array(
			'leader_id'   => $blog_id,
			'follower_id' => $user_id,
			'follow_type' => 'blogs',
		) ) ) {
			if ( 'follow' === $action ) {
				$error_code = 'already_following';
				$message    = __( 'You are already following that blog.', 'buddypress-followers' );

				if ( (int) $user_id !== bp_loggedin_user_id() ) {
					$message = sprintf( __( '%s is already following that blog.', 'buddypress-followers' ), bp_core_get_user_displayname( $user_id ) );
				}
			} else {
				$error_code = 'not_following';
				$message    = __( 'You are not following that blog.', 'buddypress-followers' );

				if ( (int) $user_id !== bp_loggedin_user_id() ) {
					$message = sprintf( __( '%s is not following that blog.', 'buddypress-followers' ), bp_core_get_user_displayname( $user_id ) );
				}
			}

			return new WP_Error( $error_code, $message );

		// success on follow action
		} else {
			$blog_name = bp_blogs_get_blogmeta( $blog_id, 'name' );

			// blog has never been recorded into BP; record it now.
			if ( '' === $blog_name && apply_filters( 'bp_follow_blogs_record_blog', true, $blog_id ) ) {
				// get the admin of the blog.
				$admin = get_users( array(
					'blog_id' => $blog_id,
					'role'    => 'administrator',
					'orderby' => 'ID',
					'number'  => 1,
					'fields'  => array( 'ID' ),
				) );

				// record the blog.
				bp_blogs_record_blog( $blog_id, $admin[0]->ID, true );
			}

			return true;
		}
	}

	/** CACHE **************************************************************/

	/**
	 * Clear count cache when a user follows / unfolows a blog.
	 *
	 * @param BP_Follow $follow
	 */
	public function clear_cache_on_follow( BP_Follow $follow ) {
		// clear followers count for blog.
		wp_cache_delete( $follow->leader_id,   'bp_follow_blogs_followers_count' );

		// clear following blogs count for user.
		wp_cache_delete( $follow->follower_id, 'bp_follow_user_blogs_following_count' );

		// clear queried followers / following.
		wp_cache_delete( $follow->leader_id,   'bp_follow_blogs_followers_query' );
		wp_cache_delete( $follow->follower_id, 'bp_follow_user_blogs_following_query' );

		// clear follow relationship.
		wp_cache_delete( "{$follow->leader_id}:{$follow->follower_id}:blogs", 'bp_follow_data' );
	}

	/**
	 * Clear blog count cache when a user is deleted.
	 *
	 * @param int $user_id The user ID being deleted
	 */
	public function clear_cache_on_user_delete( $user_id = 0 ) {
		// delete user's blog follow count.
		wp_cache_delete( $user_id, 'bp_follow_user_blogs_following_count' );

		// delete queried blogs that user was following.
		wp_cache_delete( $user_id, 'bp_follow_user_blogs_following_query' );

		// delete each blog's followers count that the user was following.
		$blogs = BP_Follow::get_following( $user_id, 'blogs' );
		if ( ! empty( $blogs ) ) {
			foreach ( $blogs as $blog_id ) {
				wp_cache_delete( $blog_id, 'bp_follow_blogs_followers_count' );

				// clear follow relationship.
				wp_cache_delete( "{$blog_id}:{$user_id}:blogs", 'bp_follow_data' );
			}
		}
	}

	/**
	 * Clear blog count cache when a blog is deleted.
	 *
	 * @param int $blog_id The ID of the blog being deleted
	 */
	public function clear_cache_on_blog_delete( $blog_id ) {
		// clear followers count for blog.
		wp_cache_delete( $blog_id, 'bp_follow_blogs_followers_count' );

		// clear queried followers for blog.
		wp_cache_delete( $blog_id, 'bp_follow_blogs_followers_query' );

		// delete each user's blog following count for those that followed the blog.
		$users = BP_Follow::get_followers( $blog_id, 'blogs' );
		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				wp_cache_delete( $user, 'bp_follow_user_blogs_following_count' );

				// clear follow relationship.
				wp_cache_delete( "{$blog_id}:{$user}:blogs", 'bp_follow_data' );
			}
		}
	}

	/** FEED URL ***********************************************************/

	/**
	 * Sets the "RSS" feed URL for the tab on the Sitewide Activity page.
	 *
	 * This occurs when the "Followed Sites" tab is clicked on the Sitewide
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

		// this is done b/c we're filtering 'bp_get_sitewide_activity_feed_link' and
		// we only want to alter the feed link for the "RSS" tab.
		if ( ! defined( 'DOING_AJAX' ) && ! did_action( 'bp_before_directory_activity' ) ) {
			return $retval;
		}

		// get the activity scope.
		$scope = ! empty( $_COOKIE['bp-activity-scope'] ) ? $_COOKIE['bp-activity-scope'] : false;

		if ( 'followblogs' === $scope && bp_loggedin_user_id() ) {
			$retval = bp_follow_get_user_url( bp_loggedin_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ), array( 'feed' ) ) );
		}

		return $retval;
	}
}

/**
 * Screen loader class for BP Follow Blogs.
 *
 * @since 1.3.0
 */
class BP_Follow_Blogs_Screens {

	/** SCREENS *******************************************************/

	/**
	 * Sets up the user blogs screen.
	 */
	public static function user_blogs_screen() {
		add_action( 'bp_template_content', array( __CLASS__, 'user_blogs_screen_content' ) );

		// this is for bp-default themes.
		bp_core_load_template( 'members/single/home' );
	}

	/**
	 * Content for the user blogs screen.
	 */
	public static function user_blogs_screen_content() {
		do_action( 'bp_before_member_blogs_content' );
	?>

		<div class="blogs follow-blogs" role="main">
			<?php bp_get_template_part( 'blogs/blogs-loop' ); ?>
		</div><!-- .blogs.follow-blogs -->

	<?php
		do_action( 'bp_after_member_blogs_content' );
	}

	/**
	 * Inline JS when on a user blogs page.
	 *
	 * We need to:
	 *  - Disable AJAX when clicking on a blogs subnav item (this is a BP bug)
	 *  - Add a following scope when AJAX is submitted
	 */
	public static function user_blogs_inline_js() {
		//jQuery("#blogs-personal-li").attr('id','blogs-following-personal-li');
	?>

		<script type="text/javascript">
		jQuery('#subnav a').on( 'click', function(event) {
			event.stopImmediatePropagation();
		});
		</script>

	<?php
	}

	/**
	 * Sets up the user activity screen.
	 *
	 * eg. /members/admin/activity/followblogs/
	 */
	public static function user_activity_screen() {
		do_action( 'bp_follow_blogs_screen_user_activity' );

		// this is for bp-default themes.
		bp_core_load_template( 'members/single/home' );
	}

	/** ACTIONS *******************************************************/

	/**
	 * RSS handler for a user's followed sites.
	 *
	 * When a user lands on /members/USERNAME/activity/followblogs/feed/, this
	 * method generates the RSS feed for their followed sites.
	 */
	public static function rss_handler() {
		// only available in BP 1.8+
		if ( ! class_exists( 'BP_Activity_Feed' ) ) {
			return;
		}

		if ( ! bp_is_user_activity() || ! bp_is_current_action( constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) || ! bp_is_action_variable( 'feed', 0 ) ) {
			return;
		}

		$bp = $GLOBALS['bp'];

		// get blog IDs that the user is following.
		$following_ids = bp_get_following_ids( array(
			'follow_type' => 'blogs',
		) );

		// if $following_ids is empty, pass a negative number so no blogs can be found.
		$following_ids = empty( $following_ids ) ? -1 : $following_ids;

		$args = array(
			'user_id'    => 0,
			'object'     => 'blogs',
			'primary_id' => $following_ids,
		);

		// setup the feed.
		$bp->activity->feed = new BP_Activity_Feed( array(
			'id'            => 'followedsites',

			/* translators: User's following activity RSS title - "[Site Name] | [User Display Name] | Followed Site Activity" */
			'title'         => sprintf( __( '%1$s | %2$s | Followed Site Activity', 'buddypress-followers' ), bp_get_site_name(), bp_get_displayed_user_fullname() ),

			'link'          => bp_follow_get_user_url( bp_displayed_user_id(), array( bp_get_activity_slug(), constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) ),
			'description'   => sprintf( __( "Activity feed for sites that %s is following.", 'buddypress' ), bp_get_displayed_user_fullname() ),
			'activity_args' => $args,
		) );
	}

	/**
	 * Action handler when a follow blogs button is clicked.
	 *
	 * Handles both following and unfollowing a blog.
	 */
	public static function action_handler() {
		if ( empty( $_GET['blog_id'] ) || ! is_user_logged_in() ) {
			return;
		}

		$action = false;

		if ( ! empty( $_GET['bpfb-follow'] ) || ! empty( $_GET['bpfb-unfollow'] ) ) {
			$nonce   = ! empty( $_GET['bpfb-follow'] ) ? $_GET['bpfb-follow'] : $_GET['bpfb-unfollow'];
			$action  = ! empty( $_GET['bpfb-follow'] ) ? 'follow' : 'unfollow';
		}

		if ( ! $action ) {
			return;
		}

		$save = BP_Follow_Blogs::save( [
			'action' => $action,
			'nonce'  => $nonce,
			'blog_id' => (int) $_GET['blog_id']
		] );

		if ( is_wp_error( $save ) ) {
			if ( 'already_following' === $save->get_error_code() || 'not_following' === $save->get_error_code() ) {
				bp_core_add_message( $save->get_error_message(), 'error' );
			} else {
				return;
			}

		} else {
			$blog_name = bp_blogs_get_blogmeta( (int) $_GET['blog_id'], 'name' );

			if ( 'follow' === $action ) {
				if ( ! empty( $blog_name ) ) {
					$message = sprintf( __( 'You are now following the site, %s.', 'buddypress-followers' ), $blog_name );
				} else {
					$message = __( 'You are now following that site.', 'buddypress-followers' );
				}
			} else {
				if ( ! empty( $blog_name ) ) {
					$message = sprintf( __( 'You are no longer following the site, %s.', 'buddypress-followers' ), $blog_name );
				} else {
					$message = __( 'You are no longer following that site.', 'buddypress-followers' );
				}
			}

			bp_core_add_message( $message );
		}

		// it's possible that wp_get_referer() returns false, so let's fallback to the displayed user's page.
		$redirect = wp_get_referer() ? wp_get_referer() : bp_follow_get_user_url( bp_displayed_user_id(), array( bp_get_blogs_slug(), constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) ) );
		bp_core_redirect( $redirect );
	}

	/**
	 * AJAX handler.
	 */
	public static function ajax_handler() {
		$data = json_decode( stripslashes( $_POST['followData'] ) );
		if ( empty( $data ) ) {
			wp_send_json_error();
		}

		$save = BP_Follow_Blogs::save( [
			'action'  => $data->followAction,
			'nonce'   => $data->followNonce,
			'blog_id' => $data->followBlogId
		] );

		// Error during follow action. Render invalid button for AJAX response.
		if ( is_wp_error( $save ) ) {
			$button = bp_get_button( [
				'id'        => 'invalid',
				'link_href' => 'javascript:;',
				'component' => 'follow',
				'wrapper'   => false,
				'link_text' => esc_html__( 'Error', 'buddypress-followers' )
			] );

		// Success! Render follow button for AJAX response.
		} else {
			$button = BP_Follow_Blogs::get_button( [
				'leader_id'     => $data->followBlogId,
				'follow_text'   => $data->followText,
				'unfollow_text' => $data->unfollowText,
				'wrapper'       => false
			] );
		}

		wp_send_json_success( [ 'button' => $button ] );
	}
}
