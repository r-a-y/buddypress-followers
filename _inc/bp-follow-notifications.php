<?php
/**
 * BP Follow Notifications
 *
 * @package BP-Follow
 * @subpackage Notifications
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

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
			$link = bp_loggedin_user_domain() . $bp->follow->followers->slug . '/?new';

			if ( 1 == $total_items ) {
				$text = __( '1 more user is now following you', 'bp-follow' );
			}
			else {
				$text = sprintf( __( '%d more users are now following you', 'bp-follow' ), $total_items );
			}
		break;

		default :
			$link = apply_filters( 'bp_follow_extend_notification_link', false, $action, $item_id, $secondary_item_id, $total_items );
			$text = apply_filters( 'bp_follow_extend_notification_text', false, $action, $item_id, $secondary_item_id, $total_items );
		break;
	}

	if ( !$link || !$text )
		return false;

	if ( 'string' == $format ) {
		return apply_filters( 'bp_follow_new_followers_notification', '<a href="' . $link . '" title="' . __( 'Your list of followers', 'bp-follow' ) . '">' . $text . '</a>', $total_items, $link, $text, $item_id, $secondary_item_id );
	}
	else {
		$array = array(
			'text' => $text,
			'link' => $link
		);

		return apply_filters( 'bp_follow_new_followers_return_notification', $array, $item_id, $secondary_item_id, $total_items );
	}
}

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
	$follower_link = bp_core_get_user_domain( $r['follower_id'] );

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
