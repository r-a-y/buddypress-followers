<?php
/*
Plugin Name: BuddyPress Follow
Plugin URI: http://apeatling.wordpress.com/
Description: Allow your site members to follow other members' activity.
Version: 1.0
Requires at least: 2.9.2 / 1.2.4
Tested up to: 3.0 / 1.2.4.1
License: GNU/GPL 2
Author: Andy Peatling
Author URI: http://apeatling.wordpress.com
*/

/**
 * bp_follow_init()
 *
 * Only load the plugin code if BuddyPress is activated.
 *
 * @package BP-Follow
 */
function bp_follow_init() {
    require( dirname( __FILE__ ) . '/bp-follow.php' );
}
add_action( 'bp_init', 'bp_follow_init' );

/**
 * bp_follow_activate()
 *
 * Run the activation routine when BP-Follow is activated.
 *
 * @package BP-Follow
 * @uses dbDelta() Executes queries and performs selective upgrades on existing tables.
 */
function bp_follow_activate() {
	global $wpdb;

	require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

	$charset_collate = '';
	if ( !empty($wpdb->charset) )
		$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

	$sql[] = "CREATE TABLE {$wpdb->base_prefix}bp_follow (
	  		    id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			    leader_id bigint(20) NOT NULL,
			    follower_id bigint(20) NOT NULL,
		        KEY followers (leader_id, follower_id)
		       ) {$charset_collate};";

	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'bp_follow_activate' );

/**
 * bp_follow_deactivate()
 *
 * Run the deactivation routine when BP-Follow is deactivated.
 *
 * @package BP-Follow
 */
function bp_follow_deactivate() {
	// Cleanup.
}
register_deactivation_hook( __FILE__, 'bp_follow_deactivate' );
?>