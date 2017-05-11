<?php

/**
 * Follow Posts Loader.
 *
 * @since 1.3.0
 */
function bp_follow_posts_init() {
	global $bp;

	$bp->follow->posts = new BP_Follow_Posts;

	do_action( 'bp_follow_posts_loaded' );
}
add_action( 'bp_follow_loaded', 'bp_follow_posts_init' );

/**
 * Follow Posts module.
 *
 * @since 1.3.0
 */
class BP_Follow_Posts {
	/**
	 * Constructor.
	 */
	public function __construct() {

		// component hooks
		add_action( 'bp_follow_setup_globals', array( $this, 'constants' ) );
		add_action( 'bp_follow_setup_globals', array( $this, 'setup_global_cachegroups' ) );
		add_action( 'bp_follow_setup_nav',     array( $this, 'setup_nav' ) );
		add_action( 'bp_activity_admin_nav',   array( $this, 'activity_admin_nav' ) );

		// screen hooks
		// add_action( 'bp_after_member_posts_content', 'BP_Follow_Posts_Screens::user_posts_inline_js' );
		add_action( 'bp_actions',                    'BP_Follow_Posts_Screens::action_handler' ); // To do: Change to ajax!
		add_action( 'bp_actions',                    'BP_Follow_Posts_Screens::rss_handler' );

		// directory tabs
		add_action( 'bp_before_activity_type_tab_favorites', array( $this, 'add_activity_directory_tab' ) );

		// loop filtering
		// add_filter( 'bp_activity_set_followblogs_scope_args', array( $this, 'filter_activity_scope' ), 10, 2 );
		// add_filter( 'bp_ajax_querystring', array( $this, 'add_posts_scope_filter' ),    20, 2 );

		// button injection
		add_action( 'the_content', array( $this, 'add_follow_button_to_post' ),   20 );
		add_action( 'wp_footer',   array( $this, 'add_follow_button_to_footer' ), 999 );

		// post deletion
		add_action( 'before_delete_post', array( $this, 'on_post_delete' ) );

		// cache invalidation
		add_action( 'bp_follow_start_following_posts', array( $this, 'clear_cache_on_follow' ) );
		add_action( 'bp_follow_stop_following_posts',  array( $this, 'clear_cache_on_follow' ) );
		add_action( 'bp_follow_before_remove_data',    array( $this, 'clear_cache_on_user_delete' ) );

		// rss feed link
		add_filter( 'bp_get_sitewide_activity_feed_link', array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_dtheme_activity_feed_url',        array( $this, 'activity_feed_url' ) );
		add_filter( 'bp_legacy_theme_activity_feed_url',  array( $this, 'activity_feed_url' ) );
	}

	/**
	 * Constants.
	 *
	 * @since 1.3.0
	 */
	public function constants() {
		// /members/admin/posts/[FOLLOWING]
		if ( ! defined( 'BP_FOLLOW_POSTS_USER_FOLLOWING_SLUG' ) ) {
			define( 'BP_FOLLOW_POSTS_USER_FOLLOWING_SLUG', 'posts' );
		}

		// /members/admin/activity/[FOLLOWPOSTS]
		if ( ! defined( 'BP_FOLLOW_POSTS_USER_ACTIVITY_SLUG' ) ) {
			define( 'BP_FOLLOW_POSTS_USER_ACTIVITY_SLUG', 'followposts' );
		}

		// Adds the follow button in the end of the post
		if ( ! defined( 'BP_FOLLOW_POSTS_BUTTON_POSITION' ) ) {
			define( 'BP_FOLLOW_POSTS_BUTTON_POSITION', 'bottom' );
		}
	}

	/**
	 * Set up global cachegroups.
	 * 
	 * @since 1.3.0
	 */
	public function setup_global_cachegroups() {
		$bp = buddypress();

		// posts counts
		$bp->follow->global_cachegroups[] = 'bp_follow_user_posts_following_count';
		$bp->follow->global_cachegroups[] = 'bp_follow_posts_followers_count';

		// posts data query
		$bp->follow->global_cachegroups[] = 'bp_follow_user_posts_following_query';
		$bp->follow->global_cachegroups[] = 'bp_follow_posts_followers_query';
	}

	/**
	 * Setup profile nav.
	 *
	 * @since 1.3.0
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
			'name'            => _x( 'Followed Posts', 'Posts subnav tab', 'bp-follow' ),
			'slug'            => constant( 'BP_FOLLOW_POSTS_USER_FOLLOWING_SLUG' ),
			'parent_url'      => trailingslashit( $user_domain . constant( 'BP_FOLLOWING_SLUG' ) ),
			'parent_slug'     => constant( 'BP_FOLLOWING_SLUG' ),
			'screen_function' => 'BP_Follow_Posts_Screens::user_posts_screen',
			'position'        => 20,
			'item_css_id'     => 'posts-following'
		) );

		// Add activity sub nav item
		if ( bp_is_active( 'activity' ) && apply_filters( 'bp_follow_posts_show_activity_subnav', true ) ) {
			bp_core_new_subnav_item( array(
				'name'            => _x( 'Followed Posts', 'Activity subnav tab', 'bp-follow' ),
				'slug'            => constant( 'BP_FOLLOW_POSTS_USER_ACTIVITY_SLUG' ),
				'parent_url'      => trailingslashit( $user_domain . bp_get_activity_slug() ),
				'parent_slug'     => bp_get_activity_slug(),
				'screen_function' => 'BP_Follow_Posts_Screens::user_activity_screen',
				'position'        => 22,
				'item_css_id'     => 'activity-followposts'
			) );
		}
	}

	/**
	 * Inject "Followed Posts" nav item to WP adminbar's "Activity" main nav.
	 *
	 * @since 1.3.0
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
				'id'     => 'my-account-activity-followposts',
				'title'  => _x( 'Followed Posts', 'Adminbar activity subnav', 'bp-follow' ),
				'href'   => bp_loggedin_user_domain() . bp_get_activity_slug() . '/' . constant( 'BP_FOLLOW_POSTS_USER_ACTIVITY_SLUG' ) . '/',
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

	/** DIRECTORY TABS ************************************************/

	/**
	 * Adds a "Followed Posts (X)" tab to the activity directory.
	 *
	 * This is so the logged-in user can filter the activity stream to only posts
	 * that the current user is following.
	 */
	public function add_activity_directory_tab() {
		$counts = bp_follow_total_follow_counts( array(
			'user_id'     => bp_loggedin_user_id(),
			'follow_type' => 'posts',
		) );

		/*
		if ( empty( $counts['following'] ) ) {
			return false;
		}
		*/
		?>
		<li id="activity-followposts"><a href="<?php echo esc_url( bp_loggedin_user_domain() . constant( 'BP_FOLLOWING_SLUG' ) . '/' . constant( 'BP_FOLLOW_POSTS_USER_FOLLOWING_SLUG' ). '/' ); ?>"><?php printf( __( 'Followed Posts <span>%d</span>', 'bp-follow' ), (int) $counts['following'] ) ?></a></li><?php
	}

	/** LOOP-FILTERING ************************************************/

	/**
	 * Set up activity arguments for use with the 'followposts' scope.
	 *
	 * For details on the syntax, see {@link BP_Activity_Query}.
	 *
	 * Only applicable to BuddyPress 2.2+.  Older BP installs uses the code
	 * available in /modules/posts-backpat.php.
	 *
	 * @since 1.3.0
	 *
	 * @param array $retval Empty array by default
	 * @param array $filter Current activity arguments
	 * @return array
	 */
	function filter_activity_scope( $retval = array(), $filter = array() ) {
		// Determine the user_id
		if ( ! empty( $filter['user_id'] ) ) {
			$user_id = $filter['user_id'];
		} else {
			$user_id = bp_displayed_user_id()
				? bp_displayed_user_id()
				: bp_loggedin_user_id();
		}

		// Get posts that the user is following
		$following_ids = bp_follow_get_following( array(
			'user_id'     => $user_id,
			'follow_type' => 'posts',
		) );
		if ( empty( $following_ids ) ) {
			$following_ids = array( 0 );
		}

		// Should we show all items regardless of sitewide visibility?
		$show_hidden = array();
		if ( ! empty( $user_id ) && ( $user_id !== bp_loggedin_user_id() ) ) {
			$show_hidden = array(
				'column' => 'hide_sitewide',
				'value'  => 0
			);
		}

		// support BP Groupblog
		if ( function_exists( 'bp_groupblog_init' ) && $following_ids !== array( 0 ) ) {
			global $wpdb;

			$bp = buddypress();

			// comma-delimit the blog IDs
			$delimited_ids = implode( ',', $following_ids );
			$group_ids_connected_to_blogs = $wpdb->get_col( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = 'groupblog_blog_id' AND meta_value IN ( " . $delimited_ids . " )" );

			$clause = array(
				'relation' => 'OR',

				// general blog activity items
				array(
					'relation' => 'AND',
					array(
						'column' => 'component',
						'value'  => buddypress()->blogs->id
					),
					array(
						'column'  => 'item_id',
						'compare' => 'IN',
						'value'   => (array) $following_ids
					),
				),

				// groupblog posts
				array(
					'relation' => 'AND',
					array(
						'column' => 'component',
						'value'  => buddypress()->groups->id
					),
					array(
						'column'  => 'item_id',
						'compare' => 'IN',
						'value'   => (array) $group_ids_connected_to_blogs
					),
					array(
						'column'  => 'type',
						'value'   => 'new_groupblog_post'
					),
				),
			);

		// Regular follow blog clause
		} else {
			$clause = array(
				'relation' => 'AND',
				array(
					'column' => 'component',
					'value'  => buddypress()->blogs->id
				),
				array(
					'column'  => 'item_id',
					'compare' => 'IN',
					'value'   => (array) $following_ids
				),
			);
		}

		$retval = array(
			'relation' => 'AND',
			$clause,
			$show_hidden,

			// overrides
			'override' => array(
				'filter'      => array( 'user_id' => 0 ),
				'show_hidden' => true
			),
		);

		return $retval;
	}

	/**
	 * Filter the posts loop.
	 *
	 * Specifically, filter when we're on:
	 *  - a user's "Followed Posts" page
	 *
	 * @param str $qs The querystring for the BP loop
	 * @param str $object The current object for the querystring
	 * @return str Modified querystring
	 */
	function add_posts_scope_filter( $qs, $object ) {
		// not on the blogs object? stop now!
		/* if ( $object != 'blogs' ) {
			return $qs;
		} */

		// parse querystring into an array
		$r = wp_parse_args( $qs );

		// set scope if a user is on a user's "Followed Sites" page
		// bp_is_user_blogs() &&
		if ( bp_is_current_action( constant( 'BP_FOLLOW_POSTS_USER_FOLLOWING_SLUG' ) ) ) {
			$r['scope'] = 'following';
		}

		if ( empty( $r['scope'] ) || 'following' !== $r['scope'] ) {
			return $qs;
		}

		// get post IDs that the user is following
		$following_ids = bp_get_following_ids( array(
			'user_id'     => bp_displayed_user_id() ? bp_displayed_user_id() : bp_loggedin_user_id(),
			'follow_type' => 'posts',
		) );

		// if $following_ids is empty, pass the largest bigint(20) value to ensure
		// no posts are matched
		$following_ids = empty( $following_ids ) ? '18446744073709551615' : $following_ids;

		$args = array(
			'user_id'          => 0,
			'include_post_ids' => $following_ids,
		);

		// make sure we add a separator if we have an existing querystring
		if ( ! empty( $qs ) ) {
			$qs .= '&';
		}

		// add our follow parameters to the end of the querystring
		$qs .= build_query( $args );

		return $qs;
	}

	/** BUTTON ********************************************************/

	/**
	 * Add a follow button to the post
	 *
	 * @since 1.3.0
	 *
	 * @return string The post content with the button before or after it.
	 */
	public function add_follow_button_to_post( $content ) {
		if ( ! is_user_logged_in() ) {
			return $content;
		}

		if ( is_page() || ! is_single() ) {
			return $content;
		}

		$btn = self::get_button();
		$pos = constant( 'BP_FOLLOW_POSTS_BUTTON_POSITION' );

		if ( $pos == 'top' ) {
			$out = $btn . $content;
		}

		if ( $pos == 'bottom' ) {
			$out = $content . $btn;
		}

		return $out;
	}

	/**
	 * Whether to show the post footer buttons.
	 *
	 * @since 1.3.0
	 *
	 * @return bool Defaults to true. False when on home, page or not on single post.
	 */
	public static function show_footer_button() {
		$retval = true;

		if ( is_home() || is_page() || ! is_single() ) {
			$retval = false;
		}

		return apply_filters( 'bp_follow_posts_show_footer_button', $retval );
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

		// disable the footer button using this filter if needed
		if ( false === self::show_footer_button() ) {
			return;
		}

		// remove inline CSS later... still testing
	?>

		<style type="text/css">
			#bpf-posts-ftr{
				position:fixed;
				bottom: 5px;
				left: 5px;
				z-index:9999;
				text-align:left;
			}

			#bpf-posts-ftr a {
				font: 600 12px/18px "Helvetica Neue","HelveticaNeue",Helvetica,Arial,sans-serif !important;
				color: #fff !important;
				text-decoration:none !important;
				background:rgba(0, 0, 0, 0.48);
				padding:2px 5px !important;
				border-radius: 4px;
			}
			#bpf-posts-ftr a:hover {
				background:rgba(0, 0, 0, 0.42);
			}

			#bpf-posts-ftr a:before {
				position: relative;
				top: 3px;
				font: normal 13px/1 'dashicons';
				padding-right:5px;
			}

			#bpf-posts-ftr a.follow:before {
				content: "\f132";
			}

			#bpf-posts-ftr a.unfollow:before {
				content: "\f460";
			}

			#bpf-posts-ftr a.home:before {
				content: "\f155";
				top: 2px;
			}
		</style>

		<div id="bpf-posts-ftr">
			<?php echo self::get_button( array(
				'leader_id' => get_the_ID(),
				'wrapper'   => false,
			) ); ?>

 			<?php
 				$btn_args = apply_filters( 'bp_follow_posts_get_sites_button_args', array(
 					'class' => 'home',
 					'link' => bp_loggedin_user_domain() . constant( 'BP_FOLLOWING_SLUG' ) . '/' . constant( 'BP_FOLLOW_POSTS_USER_FOLLOWING_SLUG' ). '/',
 					'text' => _x( 'Followed Posts', 'Footer button', 'bp-follow' ),
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
	 * Static method to generate a follow posts button.
	 *
	 * @since 1.3.0
	 */
	public static function get_button( $args = '' ) {

		$r = wp_parse_args( $args, array(
			'leader_id'     => get_the_ID(),
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

		$is_following = bp_follow_is_following( array(
			'leader_id'   => $r['leader_id'],
			'follower_id' => $r['follower_id'],
			'follow_type' => 'posts',
		) );

		// setup some variables
		if ( $is_following ) {
			$id        = 'following';
			$action    = 'unfollow';
			$link_text = _x( 'Unfollow Post', 'Button', 'bp-follow' );

			if ( empty( $r['link_text'] ) ) {
				$r['link_text'] = $link_text;
			}
		} 
		else {
			$id        = 'not-following';
			$action    = 'follow';
			$link_text = _x( 'Follow Post', 'Button', 'bp-follow' );

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
				add_query_arg( 'post_id', $r['leader_id'], home_url( '/' ) ),
				"bp_follow_post_{$action}",
				"bpfb-{$action}"
			),
			'link_text'         => esc_attr( $r['link_text'] ),
			'link_title'        => esc_attr( $r['link_title'] ),
			'link_id'           => $action . '-' . (int) $r['leader_id'],
			'link_class'        => $link_class,
			'wrapper'           => ! empty( $r['wrapper'] ) ? esc_attr( $r['wrapper'] ) : false
		);

		// Filter and return the HTML button
		return bp_get_button( apply_filters( 'bp_follow_posts_get_follow_button', $button, $r, $is_following ) );
	}

	/** DELETION ***********************************************************/

	/**
	 * Do stuff when a post is deleted.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id The ID of the post being deleted.
	 */
	public function on_post_delete( $post_id ) {
		global $bp, $wpdb, $post_type;
    	
    	// Right now, only the post is being checked, later, 
    	// it'll be needed to check for the allowed/added post types
    	if ( $post_type != 'post' ) {
    		return;
    	}

		$this->clear_cache_on_post_delete( $post_id );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->follow->table_name} WHERE leader_id = %d AND follow_type = 'posts'", $post_id ) );
	}

	/** CACHE **************************************************************/

	/**
	 * Clear count cache when a user follows / unfolows a post.
	 *
	 * @since 1.3.0
	 *
	 * @param BP_Follow $follow
	 */
	public function clear_cache_on_follow( BP_Follow $follow ) {
		// clear followers count for post
		wp_cache_delete( $follow->leader_id,   'bp_follow_posts_followers_count' );

		// clear following posts count for user
		wp_cache_delete( $follow->follower_id, 'bp_follow_user_posts_following_count' );

		// clear queried followers / following
		wp_cache_delete( $follow->leader_id,   'bp_follow_posts_followers_query' );
		wp_cache_delete( $follow->follower_id, 'bp_follow_user_posts_following_query' );

		// clear follow relationship
		wp_cache_delete( "{$follow->leader_id}:{$follow->follower_id}:posts", 'bp_follow_data' );
	}

	/**
	 * Clear post count cache when a user is deleted.
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id The user ID being deleted
	 */
	public function clear_cache_on_user_delete( $user_id = 0 ) {
		// delete user's post follow count
		wp_cache_delete( $user_id, 'bp_follow_user_posts_following_count' );

		// delete queried posts that user was following
		wp_cache_delete( $user_id, 'bp_follow_user_posts_following_query' );

		// delete each post's followers count that the user was following
		$posts = BP_Follow::get_following( $user_id, 'posts' );
		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post_id ) {
				wp_cache_delete( $post_id, 'bp_follow_posts_followers_count' );

				// clear follow relationship
				wp_cache_delete( "{$post_id}:{$user_id}:posts", 'bp_follow_data' );
			}
		}
	}

	/**
	 * Clear post count cache when a post is deleted.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id The ID of the post being deleted
	 */
	public function clear_cache_on_post_delete( $post_id ) {
		// clear followers count for post
		wp_cache_delete( $post_id, 'bp_follow_posts_followers_count' );

		// clear queried followers for post
		wp_cache_delete( $post_id, 'bp_follow_posts_followers_query' );

		// delete each user's post following count for those that followed the post
		$users = BP_Follow::get_followers( $post_id, 'posts' );
		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				wp_cache_delete( $user, 'bp_follow_user_posts_following_count' );

				// clear follow relationship
				wp_cache_delete( "{$post_id}:{$user}:posts", 'bp_follow_data' );
			}
		}
	}

	/** FEED URL ***********************************************************/

	/**
	 * Sets the "RSS" feed URL for the tab on the Sitewide Activity page.
	 *
	 * This occurs when the "Followed Posts" tab is clicked on the Sitewide
	 * Activity page or when the activity scope is already set to "followposts".
	 *
	 * Only do this for BuddyPress 1.8+.
	 *
	 * @since 1.3.0
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

		if ( $scope == 'followposts' && bp_loggedin_user_id() ) {
			$retval = bp_loggedin_user_domain() . bp_get_activity_slug() . '/' . constant( 'BP_FOLLOW_POSTS_USER_ACTIVITY_SLUG' ) . '/feed/';
		}

		return $retval;
	}
}

/**
 * Screen loader class for BP Follow Posts.
 *
 * @since 1.3.0
 */
class BP_Follow_Posts_Screens {

	/** SCREENS *******************************************************/

	/**
	 * Sets up the user post screen.
	 */
	public static function user_posts_screen() {
		add_action( 'bp_template_content', array( __CLASS__, 'user_posts_screen_content' ) );

		// this is for bp-default themes
		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Content for the user posts screen.
	 */
	public static function user_posts_screen_content() {
		do_action( 'bp_before_member_posts_content' );
	?>

		<div class="posts follow-posts" role="main">
			<?php bp_get_template_part( 'posts/post-loop' ) ?>
		</div><!-- .posts.follow-posts -->

	<?php
		do_action( 'bp_after_member_posts_content' );
	}

	/**
	 * Inline JS when on a user blogs page.
	 *
	 * We need to:
	 *  - Disable AJAX when clicking on a posts subnav item (this is a BP bug)
	 *  - Add a following scope when AJAX is submitted
	 */
	public static function user_posts_inline_js() {
		//jQuery("#posts-personal-li").attr('id','posts-following-personal-li');
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
	 * eg. /members/admin/activity/followposts/
	 */
	public static function user_activity_screen() {
		do_action( 'bp_follow_posts_screen_user_activity' );

		// this is for bp-default themes
		bp_core_load_template( 'members/single/home' );
	}

	/** ACTIONS *******************************************************/

	/**
	 * RSS handler for a user's followed sites.
	 *
	 * When a user lands on /members/USERNAME/activity/followposts/feed/, this
	 * method generates the RSS feed for their followed posts.
	 */
	public static function rss_handler() {
		// only available in BP 1.8+
		if ( ! class_exists( 'BP_Activity_Feed' ) ) {
			return;
		}

		if ( ! bp_is_user_activity() || ! bp_is_current_action( constant( 'BP_FOLLOW_POSTS_USER_ACTIVITY_SLUG' ) ) || ! bp_is_action_variable( 'feed', 0 ) ) {
			return;
		}

		// get post IDs that the user is following
		$following_ids = bp_get_following_ids( array(
			'follow_type' => 'posts',
		) );

		// if $following_ids is empty, pass a negative number so no posts can be found
		$following_ids = empty( $following_ids ) ? -1 : $following_ids;

		$args = array(
			'user_id'    => 0,
			'object'     => 'posts',
			'primary_id' => $following_ids,
		);

		// setup the feed
		buddypress()->activity->feed = new BP_Activity_Feed( array(
			'id'            => 'followedposts',

			/* translators: User's following activity RSS title - "[Post Name] | [User Display Name] | Followed Post Activity" */
			'title'         => sprintf( __( '%1$s | %2$s | Followed Post Activity', 'bp-follow' ), get_the_title( $following_ids ), bp_get_displayed_user_fullname() ),

			'link'          => trailingslashit( bp_displayed_user_domain() . bp_get_activity_slug() . '/' . constant( 'BP_FOLLOW_POSTS_USER_ACTIVITY_SLUG' ) ),
			'description'   => sprintf( __( "Activity feed for posts that %s is following.", 'buddypress' ), bp_get_displayed_user_fullname() ),
			'activity_args' => $args,
		) );
	}

	/**
	 * Action handler when a follow posts button is clicked.
	 *
	 * Handles both following and unfollowing a post.
	 */
	public static function action_handler() {
		if ( empty( $_GET['post_id'] ) || ! is_user_logged_in() ) {
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

		if ( ! wp_verify_nonce( $nonce, "bp_follow_post_{$action}" ) ) {
			return;
		}

		if ( ! $save( array(
			'leader_id'   => (int) $_GET['post_id'],
			'follower_id' => bp_loggedin_user_id(),
			'follow_type' => 'posts'
		) ) ) {
			if ( 'follow' == $action ) {
				$message = __( 'You are already following this post.', 'bp-follow' );
			} else {
				$message = __( 'You are not following this post.', 'bp-follow' );
			}

			bp_core_add_message( $message, 'error' );

		// success on follow action
		} 
		else {
			$post_id    = intval( $_GET['post_id'] );
			$post_title = get_the_title( $post_id );

			if ( 'follow' == $action ) {
				if ( ! empty( $post_title ) ) {
					$message = sprintf( __( 'You are now following the post, %s.', 'bp-follow' ), $post_title );
				} else {
					$message = __( 'You are now following that post.', 'bp-follow' );
				}
			} 
			else {
				if ( ! empty( $post_title ) ) {
					$message = sprintf( __( 'You are no longer following the post, %s.', 'bp-follow' ), $post_title );
				} else {
					$message = __( 'You are no longer following that post.', 'bp-follow' );
				}
			}

			bp_core_add_message( $message );
		}

		// it's possible that wp_get_referer() returns false, so let's fallback to the displayed user's page
		$redirect = wp_get_referer() ? wp_get_referer() : bp_displayed_user_domain() . constant( 'BP_FOLLOWING_SLUG' ) . '/' . constant( 'BP_FOLLOW_POSTS_USER_FOLLOWING_SLUG' ) . '/';
		bp_core_redirect( $redirect );
	}
}
