<?php

/**
 * Updater class.
 *
 * @since 1.3.0
 */
class BP_Follow_Updater {
	/**
	 * Constructor.
	 *
	 * Only load our updater on certain admin pages only.  This currently includes
	 * the "Dashboard", "Dashboard > Updates" and "Plugins" pages.
	 */
	public function __construct() {
		add_action( 'load-index.php',       array( $this, '_init' ) );
		add_action( 'load-update-core.php', array( $this, '_init' ) );
		add_action( 'load-plugins.php',     array( $this, '_init' ) );
	}

	/**
	 * Stub initializer.
	 *
	 * This is designed to prevent access to the main, protected init method.
	 */
	public function _init() {
		if ( ! did_action( 'admin_init' ) ) {
			return;
		}

		$this->init();
	}

	/**
	 * Update routine.
	 *
	 * Runs the install DB tables method amongst other things.
	 */
	protected function init() {
		$installed_date = (int) self::get_installed_revision_date();

		// v1.3.0 - May 5, 2014
		if ( $installed_date < 1399352400 ) {
			$this->install();
		}

		// bump revision date in DB
		self::bump_revision_date();
	}

	/** INSTALL *******************************************************/

	/**
	 * Installs the BP Follow DB table.
	 */
	protected function install() {
		global $bp, $wpdb;

		$charset_collate = ! empty( $wpdb->charset ) ? "DEFAULT CHARACTER SET $wpdb->charset" : '';

		if ( ! $table_prefix = $bp->table_prefix ) {
			$table_prefix = apply_filters( 'bp_core_get_table_prefix', $wpdb->base_prefix );
		}

		$sql[] = "CREATE TABLE {$table_prefix}bp_follow (
				id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				leader_id bigint(20) NOT NULL,
				follower_id bigint(20) NOT NULL,
				follow_type varchar(75) NOT NULL,
			        KEY followers (leader_id,follower_id),
			        KEY follow_type (follow_type)
			) {$charset_collate};";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/** REVISION DATE *************************************************/

	/**
	 * Returns the current revision date as set in {@link BP_Follow_Component}.
	 *
	 * @return int The current revision date (eg. 2014-01-01 01:00 UTC).
	 */
	public static function get_current_revision_date() {
		if ( false === self::is_loaded() ) {
			return false;
		}

		global $bp;

		return $bp->follow->revision_date;
	}

	/**
	 * Returns the revision date for the BP Follow install as saved in the DB.
	 *
	 * @return int|bool Integer of the installed unix timestamp on success.  Boolean false on failure.
	 */
	public static function get_installed_revision_date() {
		return strtotime( bp_get_option( '_bp_follow_revision_date' ) );
	}

	/**
	 * Bumps the revision date in the DB
	 *
	 * @return void|bool Boolean false on failure only.
	 */
	protected static function bump_revision_date() {
		if ( false === self::is_loaded() ) {
			return false;
		}

		bp_update_option( '_bp_follow_revision_date', self::get_current_revision_date() );
	}

	/** UTILITY *******************************************************/

	/**
	 * See if BP Follow is loaded.
	 *
	 * @return bool
	 */
	public static function is_loaded() {
		global $bp;

		if ( empty( $bp->follow ) ) {
			return false;
		}

		return true;
	}
}