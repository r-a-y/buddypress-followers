<?php
if ( ! defined( 'BP_TESTS_DIR' ) ) {
	// BP 2.1 and higher
	if ( file_exists( realpath( dirname( __FILE__ ) . '/../../../../buddypress/tests/phpunit' ) ) ) {
		define( 'BP_TESTS_DIR', dirname( __FILE__ ) . '/../../../../buddypress/tests/phpunit' );

	// BP 2.0 and lower
	} else {
		define( 'BP_TESTS_DIR', dirname( __FILE__ ) . '/../../../../buddypress/tests' );
	}
}

if ( ! file_exists( BP_TESTS_DIR . '/bootstrap.php' ) ) {
	return;
}

// Load up WP's unit test functions
require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

/**
 * Load BuddyPress and BP Follow.
 */
function _bp_follow_bootstrap() {
	// Load up BP's specialized unit test loader
	require BP_TESTS_DIR . '/includes/loader.php';

	// make BP pass the bp_is_network_activated() check
	// this is needed when the BP directory is symlinked
	if ( is_multisite() ) {
		tests_add_filter( 'bp_is_network_activated', '__return_true' );
	}

	// Now load BP Follow
	require dirname( __FILE__ ) . '/../../../loader.php';
}
tests_add_filter( 'muplugins_loaded', '_bp_follow_bootstrap' );

/**
 * Install BP Follow's DB tables.
 */
function _bp_follow_install() {
	global $wpdb, $wp_actions;

	// require BP Follow updater class
	require dirname( __FILE__ ) . '/../../../_inc/bp-follow-updater.php';

	// Set DB tables to InnoDB
	$wpdb->query( 'SET storage_engine = INNODB' );

	// Fake that we're in the admin area so BP Follow's did_action() check passes
	$wp_actions['admin_init'] = 1;

	// Start the install
	echo "Installing BP Follow...\n";
	$install = new BP_Follow_Updater;
	$install->_init();

	// undo the hack
	unset( $wp_actions['admin_init'] );
}
tests_add_filter( 'plugins_loaded', '_bp_follow_install', 20 );

// Load WP's test suite
require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';

// Load the BP test files
require BP_TESTS_DIR . '/includes/testcase.php';