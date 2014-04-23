<?php
/**
 * BP Follow Notifications
 *
 * @package BP-Follow
 * @subpackage Notifications
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** NOTIFICATIONS API ***************************************************/

/**
 * Format on screen notifications into something readable by users.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
	global $bp;

	do_action( 'bp_follow_format_notifications', $action, $item_id, $secondary_item_id, $total_items, $format );

	switch ( $action ) {
		case 'new_follow':
			$link = $text = false;

			if ( 1 == $total_items ) {
				$text = sprintf( __( '%s is now following you', 'bp-follow' ), bp_core_get_user_displayname( $item_id ) );
				$link = bp_core_get_user_domain( $item_id  ) . '?bpf_read';

			} else {
				$text = sprintf( __( '%d more users are now following you', 'bp-follow' ), $total_items );

				if ( bp_is_active( 'notifications' ) ) {
					$link = bp_get_notifications_permalink();
				} else {
					$link = bp_loggedin_user_domain() . $bp->follow->followers->slug . '/?new';
				}
			}

		break;

		default :
			$link = apply_filters( 'bp_follow_extend_notification_link', false, $action, $item_id, $secondary_item_id, $total_items );
			$text = apply_filters( 'bp_follow_extend_notification_text', false, $action, $item_id, $secondary_item_id, $total_items );
		break;
	}

	if ( ! $link || ! $text ) {
		return false;
	}

	if ( 'string' == $format ) {
		return apply_filters( 'bp_follow_new_followers_notification', '<a href="' . $link . '">' . $text . '</a>', $total_items, $link, $text, $item_id, $secondary_item_id );

	} else {
		$array = array(
			'text' => $text,
			'link' => $link
		);

		return apply_filters( 'bp_follow_new_followers_return_notification', $array, $item_id, $secondary_item_id, $total_items );
	}
}

/**
 * Removes notifications made by a user.
 *
 * @since 1.2.1
 *
 * @param int $user_id The user ID.
 */
function bp_follow_remove_notifications_for_user( $user_id = 0 ) {
	// BP 1.9+
	if ( bp_is_active( 'notifications' ) ) {
		bp_notifications_delete_all_notifications_by_type( $user_id, buddypress()->follow->id, 'new_follow' );

	// BP < 1.9 - delete notifications the old way
	} elseif ( ! class_exists( 'BP_Core_Login_Widget' ) ) {
		global $bp;

		bp_core_delete_notifications_from_user( $user_id, $bp->follow->id, 'new_follow' );
	}
}
add_action( 'bp_follow_remove_data', 'bp_follow_remove_notifications_for_user' );

/**
 * Adds notification when a user follows another user.
 *
 * @since 1.2.1
 *
 * @param object $follow The BP_Follow object.
 */
function bp_follow_notifications_add_on_follow( BP_Follow $follow ) {
	// Add a screen notification
	//
	// BP 1.9+
	if ( bp_is_active( 'notifications' ) ) {
		bp_notifications_add_notification( array(
			'item_id'           => $follow->follower_id,
			'user_id'           => $follow->leader_id,
			'component_name'    => buddypress()->follow->id,
			'component_action'  => 'new_follow'
		) );

	// BP < 1.9 - add notifications the old way
	} elseif ( ! class_exists( 'BP_Core_Login_Widget' ) ) {
		global $bp;

		bp_core_add_notification(
			$follow->follower_id,
			$follow->leader_id,
			$bp->follow->id,
			'new_follow'
		);
	}

	// Add an email notification
	bp_follow_new_follow_email_notification( array(
		'leader_id'   => $follow->leader_id,
		'follower_id' => $follow->follower_id
	) );
}
add_action( 'bp_follow_start_following', 'bp_follow_notifications_add_on_follow' );

/**
 * Removes notification when a user unfollows another user.
 *
 * @since 1.2.1
 *
 * @param object $follow The BP_Follow object.
 */
function bp_follow_notifications_remove_on_unfollow( BP_Follow $follow ) {
	// BP 1.9+
	if ( bp_is_active( 'notifications' ) ) {
		bp_notifications_delete_notifications_by_item_id( $follow->leader_id, $follow->follower_id, buddypress()->follow->id, 'new_follow' );

	// BP < 1.9 - delete notifications the old way
	} elseif ( ! class_exists( 'BP_Core_Login_Widget' ) ) {
		global $bp;

		bp_core_delete_notifications_by_item_id( $follow->leader_id, $follow->follower_id, $bp->follow->id, 'new_follow' );
	}
}
add_action( 'bp_follow_stop_following', 'bp_follow_notifications_remove_on_unfollow' );

/**
 * Mark notification as read when a logged-in user visits their follower's profile.
 *
 * This is a new feature in BuddyPress 1.9.
 *
 * @since 1.2.1
 */
function bp_follow_notifications_mark_follower_profile_as_read() {
	if ( ! isset( $_GET['bpf_read'] ) ) {
		return;
	}

	// mark notification as read
	if ( bp_is_active( 'notifications' ) ) {
		bp_notifications_mark_notifications_by_item_id( bp_loggedin_user_id(), bp_displayed_user_id(), buddypress()->follow->id, 'new_follow' );

	// check if we're not on BP 1.9
	// if so, delete notification since marked functionality doesn't exist
	} elseif ( ! class_exists( 'BP_Core_Login_Widget' ) ) {
		global $bp;

		bp_core_delete_notifications_by_item_id( bp_loggedin_user_id(), bp_displayed_user_id(), $bp->follow->id, 'new_follow' );
	}
}
add_action( 'bp_members_screen_display_profile', 'bp_follow_notifications_mark_follower_profile_as_read' );

/**
 * Delete notifications when a logged-in user visits their followers page.
 *
 * Since 1.2.1, when the "X users are now following you" notification appears,
 * users will be redirected to the new notifications unread page instead of
 * the logged-in user's followers page.  This is so users can see who followed
 * them and in the date order in which they were followed.
 *
 * For backwards-compatibility, we still keep the old method of redirecting to
 * the logged-in user's followers page so notifications can be deleted for
 * older versions of BuddyPress.
 *
 * Will probably remove this in a future release.
 *
 * @since 1.2.1
 */
function bp_follow_notifications_delete_on_followers_page() {
	if ( ! isset( $_GET['new'] ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		return;
	}

	// BP 1.9+
	if ( bp_is_active( 'notifications' ) ) {
		bp_notifications_delete_notifications_by_type( bp_loggedin_user_id(), $bp->follow->id, 'new_follow' );

	// BP < 1.9
	} elseif ( ! class_exists( 'BP_Core_Login_Widget' ) ) {
		global $bp;

		bp_core_delete_notifications_by_type( bp_loggedin_user_id(), $bp->follow->id, 'new_follow' );
	}
}
add_action( 'bp_follow_screen_followers', 'bp_follow_notifications_delete_on_followers_page' );

/**
 * When we're on the notification's 'read' page, remove 'bpf_read' query arg.
 *
 * Since we are already on the 'read' page, notifications on this page are
 * already marked as read.  So, we no longer need to add our special
 * 'bpf_read' query argument to each notification to determine whether we
 * need to clear it.
 *
 * @since 1.2.1
 */
function bp_follow_notifications_remove_queryarg_from_userlink( $retval ) {
	if ( bp_is_current_action( 'read' ) ) {
		// if notifications loop has finished rendering, stop now!
		// this is so follow notifications in the adminbar are unaffected
		if ( did_action( 'bp_after_member_body' ) ) {
			return $retval;
		}

		$retval = str_replace( '?bpf_read', '', $retval );
	}

	return $retval;
}
add_filter( 'bp_follow_new_followers_notification', 'bp_follow_notifications_remove_queryarg_from_userlink' );

/** SETTINGS ************************************************************/

/**
 * Adds user configurable notification settings for the component.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_screen_notification_settings() {
	if ( !$notify = bp_get_user_meta( bp_displayed_user_id(), 'notification_starts_following', true ) )
		$notify = 'yes';
?>

	<table class="notification-settings" id="follow-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php _e( 'Follow', 'bp-follow' ) ?></th>
				<th class="yes"><?php _e( 'Yes', 'bp-follow' ) ?></th>
				<th class="no"><?php _e( 'No', 'bp-follow' )?></th>
			</tr>
		</thead>

		<tbody>
			<tr>
				<td></td>
				<td><?php _e( 'A member starts following your activity', 'bp-follow' ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_starts_following]" value="yes" <?php checked( $notify, 'yes', true ) ?>/></td>
				<td class="no"><input type="radio" name="notifications[notification_starts_following]" value="no" <?php checked( $notify, 'no', true ) ?>/></td>
			</tr>
		</tbody>

		<?php do_action( 'bp_follow_screen_notification_settings' ); ?>
	</table>
<?php
}
add_action( 'bp_notification_settings', 'bp_follow_screen_notification_settings' );

/** EMAIL ***************************************************************/

/**
 * Send an email to the leader when someone follows them.
 *
 * @uses bp_core_get_user_displayname() Get the display name for a user
 * @uses bp_core_get_user_domain() Get the profile url for a user
 * @uses bp_core_get_core_userdata() Get the core userdata for a user without extra usermeta
 * @uses wp_mail() Send an email using the built in WP mail class
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_follow_new_follow_email_notification( $args = '' ) {

	$defaults = array(
		'leader_id'   => bp_displayed_user_id(),
		'follower_id' => bp_loggedin_user_id()
	);

	$r = wp_parse_args( $args, $defaults );

	if ( 'no' == bp_get_user_meta( (int) $r['leader_id'], 'notification_starts_following', true ) )
		return false;

	// Check to see if this leader has already been notified of this follower before
	$has_notified = bp_get_user_meta( $r['follower_id'], 'bp_follow_has_notified', true );

	// Already notified so don't send another email
	if ( in_array( $r['leader_id'], (array) $has_notified ) )
		return false;

	// Not been notified before, update usermeta and continue to mail
	$has_notified[] = $r['leader_id'];
	bp_update_user_meta( $r['follower_id'], 'bp_follow_has_notified', $has_notified );

	$follower_name = bp_core_get_user_displayname( $r['follower_id'] );
	$follower_link = bp_core_get_user_domain( $r['follower_id'] ) . '?bpf_read';

	$leader_ud = bp_core_get_core_userdata( $r['leader_id'] );

	// Set up and send the message
	$to = $leader_ud->user_email;

	$subject = '[' . wp_specialchars_decode( bp_get_option( 'blogname' ), ENT_QUOTES ) . '] ' . sprintf( __( '%s is now following you', 'bp-follow' ), $follower_name );

	$message = sprintf( __(
'%s is now following your activity.

To view %s\'s profile: %s', 'bp-follow' ), $follower_name, $follower_name, $follower_link );

	// Add notifications link if settings component is enabled
	if ( bp_is_active( 'settings' ) ) {
		$settings_link = bp_core_get_user_domain( $r['leader_id'] ) . BP_SETTINGS_SLUG . '/notifications/';
		$message .= sprintf( __( '

---------------------
To disable these notifications please log in and go to:
%s', 'bp-follow' ), $settings_link );
	}

	// Send the message
	$to      = apply_filters( 'bp_follow_notification_to', $to );
	$subject = apply_filters( 'bp_follow_notification_subject', $subject, $follower_name );
	$message = apply_filters( 'bp_follow_notification_message', $message, $follower_name, $follower_link );

	wp_mail( $to, $subject, $message );
}
