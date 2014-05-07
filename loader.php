<?php
/*
Plugin Name: BuddyPress Follow
Plugin URI: http://wordpress.org/extend/plugins/buddypress-followers
Description: Follow members on your BuddyPress site with this nifty plugin.
Version: 1.3-alpha
Author: Andy Peatling, r-a-y
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: bp-follow
Domain Path: /languages
*/

/**
 * BP Follow
 *
 * @package BP-Follow
 * @subpackage Loader
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Only load the plugin code if BuddyPress is activated.
 */
function bp_follow_init() {
	// some pertinent defines
	define( 'BP_FOLLOW_DIR', dirname( __FILE__ ) );
	define( 'BP_FOLLOW_URL', plugins_url( basename( BP_FOLLOW_DIR ) ) ) . '/';

	// only supported in BP 1.5+
	if ( version_compare( BP_VERSION, '1.3', '>' ) ) {
		require( constant( 'BP_FOLLOW_DIR' ) . '/bp-follow-core.php' );

	// show admin notice for users on BP 1.2.x
	} else {
		$older_version_notice = sprintf( __( "Hey! BP Follow v1.2 requires BuddyPress 1.5 or higher.  If you are still using BuddyPress 1.2 and you don't plan on upgrading, use <a href='%s'>BP Follow v1.1.1 instead</a>.", 'bp-follow' ), 'https://github.com/r-a-y/buddypress-followers/archive/1.1.x.zip' );

		add_action( 'admin_notices', create_function( '', "
			echo '<div class=\"error\"><p>' . $older_version_notice . '</p></div>';
		" ) );
	}
}
add_action( 'bp_include', 'bp_follow_init' );

/**
 * Custom textdomain loader.
 *
 * Checks WP_LANG_DIR for the .mo file first, then the plugin's language folder.
 * Allows for a custom language file other than those packaged with the plugin.
 *
 * @uses load_textdomain() Loads a .mo file into WP
 */
function bp_follow_localization() {
	$mofile		= sprintf( 'bp-follow-%s.mo', get_locale() );
	$mofile_global	= trailingslashit( WP_LANG_DIR ) . $mofile;
	$mofile_local	= plugin_dir_path( __FILE__ ) . 'languages/' . $mofile;

	if ( is_readable( $mofile_global ) ) {
		return load_textdomain( 'bp-follow', $mofile_global );
	} elseif ( is_readable( $mofile_local ) ) {
		return load_textdomain( 'bp-follow', $mofile_local );
	} else {
		return false;
	}
}
add_action( 'plugins_loaded', 'bp_follow_localization' );
