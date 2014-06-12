<?php

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
		// component hooks
		add_action( 'bp_follow_setup_globals', array( $this, 'constants' ) );
		add_action( 'bp_follow_setup_nav',     array( $this, 'setup_nav' ) );
		add_action( 'bp_activity_admin_nav',   array( $this, 'activity_admin_nav' ) );
		add_filter( 'bp_blogs_admin_nav',      array( $this, 'blogs_admin_nav' ) );

		// screen hooks
		add_action( 'bp_after_member_blogs_content', 'BP_Follow_Blogs_Screens::user_blogs_inline_js' );
		add_action( 'bp_actions',                    'BP_Follow_Blogs_Screens::action_handler' );
		add_action( 'bp_actions',                    'BP_Follow_Blogs_Screens::rss_handler' );

		// directory tabs
		add_action( 'bp_before_activity_type_tab_favorites', array( $this, 'add_activity_directory_tab' ) );
		add_action( 'bp_blogs_directory_blog_types',         array( $this, 'add_blog_directory_tab' ) );

		// activity scope setting
		add_action( 'bp_activity_screen_index', array( $this, 'set_activity_scope_on_activity_directory' ) );
		add_action( 'bp_before_activity_loop',  array( $this, 'set_activity_scope_on_user_activity' ) );

		// loop filtering
		add_filter( 'bp_ajax_querystring', array( $this, 'add_blogs_scope_filter' ),    20, 2 );
		add_filter( 'bp_ajax_querystring', array( $this, 'add_activity_scope_filter' ), 20, 2 );
		add_filter( 'bp_has_blogs',        array( $this, 'bulk_inject_blog_follow_status' ) );

		// button injection
		add_action( 'bp_directory_blogs_actions', array( $this, 'add_follow_button_to_loop' ),   20 );
		add_action( 'wp_footer',                  array( $this, 'add_follow_button_to_footer' ), 999 );

		// rss feed link
		add_filter( 'bp_get_sitewide_activity_feed_link', array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_dtheme_activity_feed_url',        array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_legacy_theme_activity_feed_url',  array( $this, 'activity_feed_url' ) );
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
	 * Setup profile nav.
	 */
	public function setup_nav() {
		global $bp;

		// Determine user to use
		if ( bp_displayed_user_domain() ) {
			$user_domain = bp_displayed_user_domain();
		} elseif ( bp_loggedin_user_domain() ) {
			$user_domain = bp_loggedin_user_domain();
		} else {
			return;
		}

		bp_core_new_subnav_item( array(
			'name'            => _x( 'Followed Sites', 'Sites subnav tab', 'bp-follow' ),
			'slug'            => constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ),
			'parent_url'      => trailingslashit( $user_domain . bp_get_blogs_slug() ),
			'parent_slug'     => bp_get_blogs_slug(),
			'screen_function' => 'BP_Follow_Blogs_Screens::user_blogs_screen',
			'position'        => 20,
			'item_css_id'     => 'blogs-following'
		) );

		// Add activity sub nav item
		if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_blogs_show_activity_subnav', true ) ) {
			bp_core_new_subnav_item( array(
				'name'            => _x( 'Followed Sites', 'Activity subnav tab', 'bp-follow' ),
				'slug'            => constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ),
				'parent_url'      => trailingslashit( $user_domain . bp_get_activity_slug() ),
				'parent_slug'     => bp_get_activity_slug(),
				'screen_function' => 'BP_Follow_Blogs_Screens::user_activity_screen',
				'position'        => 22,
				'item_css_id'     => 'activity-followblogs'
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
				'id'     => 'my-account-activity-followblogs',
				'title'  => _x( 'Followed Sites', 'Adminbar activity subnav', 'bp-follow' ),
				'href'   => bp_loggedin_user_domain() . bp_get_activity_slug() . '/' . constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) . '/',
			);

			$inject = array();
			$offset = 4;

			$inject[$offset] = $new_item;
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
	 * @param array $retval
	 * @return array
	 */
	public function blogs_admin_nav( $retval ) {
		if ( ! is_user_logged_in() ) {
			return $retval;
		}

		$new_item = array(
			'parent' => 'my-account-blogs',
			'id'     => 'my-account-blogs-following',
			'title'  => _x( 'Followed Sites', 'Adminbar blogs subnav', 'bp-follow' ),
			'href'   => bp_loggedin_user_domain() . bp_get_blogs_slug() . '/' . constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ). '/',
		);

		$inject = array();
		$last   = end( $retval );

		// inject item in between "My Sites" and "Create a Site" subnav items
		if ( 'my-account-blogs-create' === $last['id'] ) {
			$offset = key( $retval );

			$inject[$offset] = $new_item;

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
		?>
		<li id="activity-followblogs"><a href="<?php echo esc_url( bp_loggedin_user_domain() . bp_get_blogs_slug() . '/' . constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ). '/' ); ?>"><?php printf( __( 'Followed Sites <span>%d</span>', 'bp-follow' ), (int) $counts['following'] ) ?></a></li><?php
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
		?>
		<li id="blogs-following"><a href="<?php echo esc_url( bp_loggedin_user_domain() . bp_get_blogs_slug() . '/' . constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ). '/' ); ?>"><?php printf( __( 'Following <span>%d</span>', 'bp-follow' ), (int) $counts['following'] ) ?></a></li><?php
	}

	/** ACTIVITY SCOPE ************************************************/

	/**
	 * Set activity scope on the activity directory depending on GET parameter.
	 *
	 * @todo Maybe add this in BP Core?
	 */
	function set_activity_scope_on_activity_directory() {
		if ( empty( $_GET['scope'] ) ) {
			return;
		}

		$scope = wp_filter_kses( $_GET['scope'] );

		// set the activity scope by faking an ajax request (loophole!)
		$_POST['cookie'] = "bp-activity-scope%3D{$scope}%3B%20bp-activity-filter%3D-1";

		// reset the selected tab
		@setcookie( 'bp-activity-scope',  $scope, 0, '/' );

		//reset the dropdown menu to 'Everything'
		@setcookie( 'bp-activity-filter', '-1',   0, '/' );
	}

	/**
	 * Set activity scope on a user's "Activity > Followed Sites" page
	 */
	function set_activity_scope_on_user_activity() {
		if ( ! bp_is_current_action( constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) ) {
			return;
		}

		$scope = 'followblogs';

		// if we have a post value already, let's add our scope to the existing cookie value
		if ( !empty( $_POST['cookie'] ) ) {
			$_POST['cookie'] .= "%3B%20bp-activity-scope%3D{$scope}";
		} else {
			$_POST['cookie'] .= "bp-activity-scope%3D{$scope}";
		}

		// set the activity scope by faking an ajax request (loophole!)
		if ( ! defined( 'DOING_AJAX' ) ) {
			$_POST['cookie'] .= "%3B%20bp-activity-filter%3D-1";

			// reset the selected tab
			@setcookie( 'bp-activity-scope',  $scope, 0, '/' );

			//reset the dropdown menu to 'Everything'
			@setcookie( 'bp-activity-filter', '-1',   0, '/' );
		}
	}

	/** LOOP-FILTERING ************************************************/

	/**
	 * Filter the blogs loop.
	 *
	 * Specifically, filter when we're on:
	 *  - a user's "Followed Sites" page
	 *  - the Sites directory and clicking on the "Following" tab
	 *
	 * @param str $qs The querystring for the BP loop
	 * @param str $object The current object for the querystring
	 * @return str Modified querystring
	 */
	function add_blogs_scope_filter( $qs, $object ) {
		// not on the blogs object? stop now!
		if ( $object != 'blogs' ) {
			return $qs;
		}

		// parse querystring into an array
		wp_parse_str( $qs, $r );

		// set scope if a user is on a user's "Followed Sites" page
		if ( bp_is_user_blogs() && bp_is_current_action( constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) ) ) {
			$r['scope'] = 'following';
		}

		if ( 'following' !== $r['scope'] ) {
			return $qs;
		}

		// get blog IDs that the user is following
		$following_ids = bp_get_following_ids( array(
			'follow_type' => 'blogs',
		) );

		// if $following_ids is empty, pass the largest bigint(20) value to ensure
		// no blogs are matched
		$following_ids = empty( $following_ids ) ? '18446744073709551615' : $following_ids;

		$args = array(
			'user_id'          => 0,
			'include_blog_ids' => $following_ids,
		);

		// make sure we add a separator if we have an existing querystring
		if ( ! empty( $qs ) ) {
			$qs .= '&';
		}

		// add our follow parameters to the end of the querystring
		$qs .= build_query( $args );

		return $qs;
	}

	/**
	 * Filter the activity loop.
	 *
	 * Specifically, when on the activity directory and clicking on the "Sites I
	 * Follow" tab.
	 *
	 * @param str $qs The querystring for the BP loop
	 * @param str $object The current object for the querystring
	 * @return str Modified querystring
	 */
	function add_activity_scope_filter( $qs, $object ) {
		// not on the blogs object? stop now!
		if ( $object != 'activity' ) {
			return $qs;
		}

		// parse querystring into an array
		wp_parse_str( $qs, $r );

		if ( bp_is_current_action( constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) ) {
			$r['scope'] = 'followblogs';
		}

		if ( 'followblogs' !== $r['scope'] ) {
			return $qs;
		}

		// get blog IDs that the user is following
		$following_ids = bp_get_following_ids( array(
			'follow_type' => 'blogs',
		) );

		// if $following_ids is empty, pass a negative number so no blogs can be found
		$following_ids = empty( $following_ids ) ? -1 : $following_ids;

		$args = array(
			'user_id'    => 0,
			'object'     => 'blogs',
			'primary_id' => $following_ids,
		);

		// make sure we add a separator if we have an existing querystring
		if ( ! empty( $qs ) ) {
			$qs .= '&';
		}

		// add our follow parameters to the end of the querystring
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

		foreach( (array) $blogs_template->blogs as $i => $blog ) {
			// add blog ID to array
			$blog_ids[] = $blog->blog_id;

			// set default follow status to false
			$blogs_template->blogs[$i]->is_following = false;
		}

		if ( empty( $blog_ids ) ) {
			return $has_blogs;
		}

		$following = BP_Follow::bulk_check_follow_status( $blog_ids, bp_loggedin_user_id(), 'blogs' );

		if ( empty( $following ) ) {
			return $has_blogs;
		}

		foreach( (array) $following as $is_following ) {
			foreach( (array) $blogs_template->blogs as $i => $blog ) {
				// set follow status to true if the logged-in user is following
				if ( $is_following->leader_id == $blog->blog_id ) {
					$blogs_template->blogs[$i]->is_following = true;
				}
			}
		}

		return $has_blogs;
	}

	/** BUTTON ********************************************************/

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

		// If blog is not recordable, do not show button
		if ( ! bp_blogs_is_blog_recordable( get_current_blog_id(), bp_loggedin_user_id() ) ) {
			return;
		}

		// disable the footer button using this filter if needed
		if ( false === self::show_footer_button() ) {
			return;
		}

		// remove inline CSS later... still testing
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
				'leader_id' => get_current_blog_id(),
				'wrapper'   => false,
			) ); ?>

			<a class="home" href="<?php echo esc_url( bp_loggedin_user_domain() . bp_get_blogs_slug() . '/' . constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ). '/' ); ?>"><?php _ex( 'Followed Sites', 'Footer button', 'bp-follow' ); ?></a>
		</div>

	<?php
	}

	/**
	 * Static method to generate a follow blogs button.
	 */
	public static function get_button( $args = '' ) {
		global $bp, $blogs_template;

		$r = wp_parse_args( $args, array(
			'leader_id'     => ! empty( $blogs_template->in_the_loop ) ? bp_get_blog_id() : get_current_blog_id(),
			'follower_id'   => bp_loggedin_user_id(),
			'link_text'     => '',
			'link_title'    => '',
			'wrapper_class' => '',
			'link_class'    => '',
			'wrapper'       => 'div'
		) );

		if ( ! $r['leader_id'] || ! $r['follower_id'] ) {
			return false;
		}

		// if we're checking during a blog loop, then follow status is already
		// queried via bulk_inject_follow_blog_status()
		if ( ! empty( $blogs_template->in_the_loop ) && $r['follower_id'] == bp_loggedin_user_id() && $r['leader_id'] == bp_get_blog_id() ) {
			$is_following = $blogs_template->blog->is_following;

		// else we manually query the follow status
		} else {
			$is_following = bp_follow_is_following( array(
				'leader_id'   => $r['leader_id'],
				'follower_id' => $r['follower_id'],
				'follow_type' => 'blogs',
			) );
		}

		// setup some variables
		if ( $is_following ) {
			$id        = 'following';
			$action    = 'unfollow';
			$link_text = _x( 'Unfollow', 'Button', 'bp-follow' );

			if ( empty( $blogs_template->in_the_loop ) ) {
				$link_text = _x( 'Unfollow Site', 'Button', 'bp-follow' );
			}

			if ( empty( $r['link_text'] ) ) {
				$r['link_text'] = $link_text;
			}

		} else {
			$id        = 'not-following';
			$action    = 'follow';
			$link_text = _x( 'Follow', 'Button', 'bp-follow' );

			if ( empty( $blogs_template->in_the_loop ) ) {
				$link_text = _x( 'Follow Site', 'Button', 'bp-follow' );
			}

			if ( empty( $r['link_text'] ) ) {
				$r['link_text'] = $link_text;
			}

		}

		$wrapper_class = 'follow-button ' . $id;

		if ( ! empty( $r['wrapper_class'] ) ) {
			$wrapper_class .= ' '  . esc_attr( $r['wrapper_class'] );
		}

		$link_class = $action;

		if ( ! empty( $r['link_class'] ) ) {
			$link_class .= ' '  . esc_attr( $r['link_class'] );
		}

		// setup the button arguments
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
			'wrapper'           => ! empty( $r['wrapper'] ) ? esc_attr( $r['wrapper'] ) : false
		);

		// Filter and return the HTML button
		return bp_get_button( apply_filters( 'bp_follow_blogs_get_follow_button', $button, $r, $is_following ) );
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
		// we only want to alter the feed link for the "RSS" tab
		if ( ! defined( 'DOING_AJAX' ) && ! did_action( 'bp_before_directory_activity' ) ) {
			return $retval;
		}

		// get the activity scope
		$scope = ! empty( $_COOKIE['bp-activity-scope'] ) ? $_COOKIE['bp-activity-scope'] : false;

		if ( $scope == 'followblogs' && bp_loggedin_user_id() ) {
			$retval = bp_loggedin_user_domain() . bp_get_activity_slug() . '/' . constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) . '/feed/';
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

		// this is for bp-default themes
		bp_core_load_template( 'members/single/home' );
	}

	/**
	 * Content for the user blogs screen.
	 */
	public static function user_blogs_screen_content() {
		do_action( 'bp_before_member_blogs_content' );
	?>

		<div class="blogs follow-blogs" role="main">
			<?php bp_get_template_part( 'blogs/blogs-loop' ) ?>
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
		jQuery('div.item-list-tabs').on( 'click', function(event) {
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

		// this is for bp-default themes
		bp_core_load_template( 'members/single/home' );
	}

	/** ACTIONS *******************************************************/

	public static function rss_handler() {
		// only available in BP 1.8+
		if ( ! class_exists( 'BP_Activity_Feed' ) ) {
			return;
		}

		if ( ! bp_is_user_activity() || ! bp_is_current_action( constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ) || ! bp_is_action_variable( 'feed', 0 ) ) {
			return;
		}

		// get blog IDs that the user is following
		$following_ids = bp_get_following_ids( array(
			'follow_type' => 'blogs',
		) );

		// if $following_ids is empty, pass a negative number so no blogs can be found
		$following_ids = empty( $following_ids ) ? -1 : $following_ids;

		$args = array(
			'user_id'    => 0,
			'object'     => 'blogs',
			'primary_id' => $following_ids,
		);

		// setup the feed
		buddypress()->activity->feed = new BP_Activity_Feed( array(
			'id'            => 'followedsites',

			/* translators: User's following activity RSS title - "[Site Name] | [User Display Name] | Followed Site Activity" */
			'title'         => sprintf( __( '%1$s | %2$s | Followed Site Activity', 'bp-follow' ), bp_get_site_name(), bp_get_displayed_user_fullname() ),

			'link'          => trailingslashit( bp_displayed_user_domain() . bp_get_activity_slug() . '/' . constant( 'BP_FOLLOW_BLOGS_USER_ACTIVITY_SLUG' ) ),
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
			$save    = ! empty( $_GET['bpfb-follow'] ) ? 'bp_follow_start_following' : 'bp_follow_stop_following';
		}

		if ( ! $action ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, "bp_follow_blog_{$action}" ) ) {
			return;
		}

		if ( ! $save( array(
			'leader_id'   => (int) $_GET['blog_id'],
			'follower_id' => bp_loggedin_user_id(),
			'follow_type' => 'blogs'
		) ) ) {
			if ( 'follow' == $action ) {
				$message = __( 'You are already following that blog.', 'bp-follow' );
			} else {
				$message = __( 'You are not following that blog.', 'bp-follow' );
			}

			bp_core_add_message( $message, 'error' );

		// success on follow action
		} else {
			$blog_name = bp_blogs_get_blogmeta( (int) $_GET['blog_id'], 'name' );

			// blog has never been recorded into BP; record it now
			if ( '' === $blog_name && apply_filters( 'bp_follow_blogs_record_blog', true, (int) $_GET['blog_id'] ) ) {
				// get the admin of the blog
				$admin = get_users( array(
					'blog_id' => get_current_blog_id(),
					'role'    => 'administrator',
					'orderby' => 'ID',
					'number'  => 1,
					'fields'  => array( 'ID' ),
				) );

				// record the blog
				$record_site = bp_blogs_record_blog( (int) $_GET['blog_id'], $admin[0]->ID, true );

				// now refetch the blog name from blogmeta
				if ( false !== $record_site ) {
					$blog_name = bp_blogs_get_blogmeta( (int) $_GET['blog_id'], 'name' );
				}
			}

			if ( 'follow' == $action ) {
				if ( ! empty( $blog_name ) ) {
					$message = sprintf( __( 'You are now following the site, %s.', 'bp-follow' ), $blog_name );
				} else {
					$message = __( 'You are now following that site.', 'bp-follow' );
				}
			} else {
				if ( ! empty( $blog_name ) ) {
					$message = sprintf( __( 'You are no longer following the site, %s.', 'bp-follow' ), $blog_name );
				} else {
					$message = __( 'You are no longer following that site.', 'bp-follow' );
				}
			}

			bp_core_add_message( $message );
		}

		// it's possible that wp_get_referer() returns false, so let's fallback to the displayed user's page
		$redirect = wp_get_referer() ? wp_get_referer() : bp_displayed_user_domain() . bp_get_blogs_slug() . '/' . constant( 'BP_FOLLOW_BLOGS_USER_FOLLOWING_SLUG' ) . '/';
		bp_core_redirect( $redirect );
	}

}

/**
 * Follow Blogs Loader.
 *
 * @since 1.3.0
 */
function bp_follow_blogs_init() {
	global $bp;

	$bp->follow->blogs = new BP_Follow_Blogs;
}

bp_follow_blogs_init();
