<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Follow Activity Loader.
 *
 * @since 1.3.0
 */
function bp_follow_activity_init() {
	$bp = $GLOBALS['bp'];

	$bp->follow->activity = new BP_Follow_Activity_Core();

	// Default 'Follow Activity' to false during dev period
	// @todo Fill out other areas - notifications, etc.
	if ( true === (bool) apply_filters( 'bp_follow_enable_activity', false ) ) {
		$bp->follow->activity->module = new BP_Follow_Activity_Module();
	}

	do_action( 'bp_follow_activity_loaded' );
}
add_action( 'bp_follow_loaded', 'bp_follow_activity_init' );

/**
 * Follow Activity Core.
 *
 * @since 1.3.0
 */
class BP_Follow_Activity_Core {

	/**
	 * Follow Activity Module Class.
	 *
	 * @var BP_Follow_Activity_Module Follow Activity Module class.
	 */
	public $module;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// includes.
		$this->includes();

		// Activity API.
		add_filter( 'bp_activity_get_post_type_tracking_args', array( $this, 'set_follow_args_for_post_type' ) );
		add_filter( 'bp_activity_set_action', array( $this, 'set_follow_args' ), 999 );
		add_action( 'bp_actions', array( $this, 'action_listener' ) );
	}

	/**
	 * Includes.
	 */
	protected function includes() {
		$bp = $GLOBALS['bp'];

		require $bp->follow->path . '/modules/activity-functions.php';

		// Add dependant hooks for the 'activity' module.
		if ( true === (bool) apply_filters( 'bp_follow_enable_activity', false ) ) {
			require $bp->follow->path . '/modules/activity-module.php';
		}
	}

	/**
	 * Allow register_post_type() with 'bp_activity' to support follow arguments.
	 *
	 * See {@link bp_follow_activity_can_follow()} for more info on how to register.
	 *
	 * @param  object $retval Return Value.
	 * @return object
	 */
	public function set_follow_args_for_post_type( $retval ) {
		if ( isset( $retval->follow_button ) ) {
			$retval->contexts['follow_button'] = $retval->follow_button;
			unset( $retval->follow_button );
		}

		if ( isset( $retval->follow_type ) ) {
			$retval->contexts['follow_type'] = $retval->follow_type;
			unset( $retval->follow_type );
		}

		return $retval;
	}

	/**
	 * Hijack bp_activity_set_action() to support custom follow arguments.
	 *
	 * See {@link bp_follow_activity_can_follow()} for more info on how to register.
	 *
	 * bp_activity_set_action() is too limited. Fortunately, we work around this
	 * via array stuffing for the 'context' key.  Workaround-galore!
	 *
	 * @param  array $retval
	 * @return array
	 */
	public function set_follow_args( $retval ) {
		if ( isset( $retval['context']['follow_button'] ) ) {
			$retval['follow_button'] = $retval['context']['follow_button'];
			unset( $retval['context']['follow_button'] );
		}

		if ( isset( $retval['context']['follow_type'] ) ) {
			$retval['follow_type'] = $retval['context']['follow_type'];
			unset( $retval['context']['follow_type'] );
		}

		return $retval;
	}

	/**
	 * Action handler when a follow activity button is clicked.
	 */
	public function action_listener() {
		if ( ! bp_is_activity_component() ) {
			return;
		}

		if ( ! bp_is_current_action( 'follow' ) && ! bp_is_current_action( 'unfollow' ) ) {
			return false;
		}

		if ( empty( $activity_id ) && bp_action_variable( 0 ) ) {
			$activity_id = (int) bp_action_variable( 0 );
		}

		// Not viewing a specific activity item.
		if ( empty( $activity_id ) ) {
			return;
		}

		$action = bp_is_current_action( 'follow' ) ? 'follow' : 'unfollow';

		// Check the nonce.
		check_admin_referer( "bp_follow_activity_{$action}" );

		$save = bp_is_current_action( 'follow' ) ? 'bp_follow_start_following' : 'bp_follow_stop_following';
		$follow_type = bp_follow_activity_get_type( $activity_id );

		// Failure on action.
		if ( ! $save( array(
			'leader_id'   => $activity_id,
			'follower_id' => bp_loggedin_user_id(),
			'follow_type' => $follow_type,
		) ) ) {
			$message_type = 'error';

			if ( 'follow' === $action ) {
				$message = __( 'You are already following that item.', 'buddypress-followers' );
			} else {
				$message = __( 'You were not following that item.', 'buddypress-followers' );
			}

		// Success!
		} else {
			$message_type = 'success';

			if ( 'follow' === $action ) {
				$message = __( 'You are now following that item.', 'buddypress-followers' );
			} else {
				$message = __( 'You are no longer following that item.', 'buddypress-followers' );
			}
		}

		/**
		 * Dynamic filter for the message displayed after the follow button is clicked.
		 *
		 * Default filter name is 'bp_follow_activity_message_activity'.
		 *
		 * Handy for plugin devs.
		 *
		 * @since 1.3.0
		 *
		 * @param string $message      Message that gets displayed after a follow action.
		 * @param string $action       Either 'follow' or 'unfollow'.
		 * @param int    $activity_id  Activity ID.
		 * @param string $message_type Either 'success' or 'error'.
		 */
		$message = apply_filters( "bp_follow_activity_message_{$follow_type}", $message, $action, $activity_id, $message_type );
		bp_core_add_message( $message, $message_type );

		// Redirect.
		$redirect = wp_get_referer() ? wp_get_referer() : bp_get_activity_directory_permalink();
		bp_core_redirect( $redirect );
		die();
	}
}
