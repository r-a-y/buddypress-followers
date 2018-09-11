<?php
/*
Plugin Name: BuddyPress Follow
Plugin URI: http://wordpress.org/extend/plugins/buddypress-followers
Description: Follow members on your BuddyPress site with this nifty plugin.
Version: 1.3-alpha
Author: Andy Peatling, r-a-y
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: buddypress-followers
Domain Path: /languages
*/

/**
 * BP Follow
 *
 * @package BP-Follow
 * @subpackage Loader
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// some pertinent defines.
define( 'BP_FOLLOW_DIR', dirname( __FILE__ ) );
define( 'BP_FOLLOW_URL', plugins_url( basename( BP_FOLLOW_DIR ) ) . '/' );

/**
 * Only load the plugin code if BuddyPress is activated.
 */
function bp_follow_init() {
	// only supported in BP 1.5+
	if ( version_compare( BP_VERSION, '1.3', '>' ) ) {
		require( constant( 'BP_FOLLOW_DIR' ) . '/bp-follow-core.php' );

	// show admin notice for users on BP 1.2.x
	} else {
		$older_version_notice = sprintf( __( "Hey! BP Follow v1.2 requires BuddyPress 1.5 or higher.  If you are still using BuddyPress 1.2 and you don't plan on upgrading, use <a href='%s'>BP Follow v1.1.1 instead</a>.", 'buddypress-followers' ), 'https://github.com/r-a-y/buddypress-followers/archive/1.1.x.zip' );

		add_action( 'admin_notices', function() use ( $older_version_notice ) {
			echo '<div class="error"><p>' . $older_version_notice . '</p></div>';
		} );
	}
}
add_action( 'bp_include', 'bp_follow_init' );

/**
 * Custom textdomain loader.
 *
 * Checks WP_LANG_DIR for the .mo file first, then WP_LANG_DIR/plugins/, then
 * the plugin's language folder.
 *
 * Allows for a custom language file other than those packaged with the plugin.
 *
 * @since 1.1.0
 *
 * @return bool True if textdomain loaded; false if not.
 */
function bp_follow_localization() {
	$domain = 'buddypress-followers';
	$mofile_custom = trailingslashit( WP_LANG_DIR ) . sprintf( '%s-%s.mo', $domain, get_locale() );

	if ( is_readable( $mofile_custom ) ) {
		return load_textdomain( $domain, $mofile_custom );
	} else {
		return load_plugin_textdomain( $domain, false, basename( BP_FOLLOW_DIR ) . '/languages/' );
	}
}
add_action( 'plugins_loaded', 'bp_follow_localization' );
