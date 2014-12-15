<?php
/**
 * BP Follow Classes
 *
 * @package BP-Follow
 * @subpackage Classes
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * BuddyPress Follow class.
 *
 * Handles populating and saving follow relationships.
 *
 * @since 1.0.0
 */
class BP_Follow {
	/**
	 * The follow ID.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $id = 0;

	/**
	 * The ID of the item we want to follow.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $leader_id;

	/**
	 * The ID for the item initiating the follow request.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $follower_id;

	/**
	 * The type of follow connection.
	 *
	 * Defaults to nothing, which will fetch users.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	public $follow_type = '';

	/**
	 * Constructor.
	 *
	 * @param int $leader_id The ID of the item wewant to follow.
	 * @param int $follower_id The ID initiating the follow request.
	 * @param string $follow_type The type of follow connection.
	 */
	public function __construct( $leader_id = 0, $follower_id = 0, $follow_type = '' ) {
		if ( ! empty( $leader_id ) && ! empty( $follower_id ) ) {
			$this->leader_id   = (int) $leader_id;
			$this->follower_id = (int) $follower_id;
			$this->follow_type = $follow_type;

			$this->populate();
		}
	}

	/**
	 * Populate method.
	 *
	 * Used in constructor.
	 *
	 * @since 1.0.0
	 */
	protected function populate() {
		global $wpdb;

		// SQL statement
		$sql =  self::get_select_sql( 'id' );
		$sql .= self::get_where_sql( array(
			'leader_id'   => $this->leader_id,
			'follower_id' => $this->follower_id,
			'follow_type' => $this->follow_type,
		) );

		if ( $follow_id = $wpdb->get_var( $sql ) ) {
			$this->id = $follow_id;
		}
	}

	/**
	 * Saves a follow relationship into the database.
	 *
	 * @since 1.0.0
	 */
	public function save() {
		global $wpdb, $bp;

		// do not use these filters
		// use the 'bp_follow_before_save' hook instead
		$this->leader_id   = apply_filters( 'bp_follow_leader_id_before_save',   $this->leader_id,   $this->id );
		$this->follower_id = apply_filters( 'bp_follow_follower_id_before_save', $this->follower_id, $this->id );

		do_action_ref_array( 'bp_follow_before_save', array( &$this ) );

		// update existing entry
		if ( $this->id ) {
			$result = $wpdb->query( $wpdb->prepare(
				"UPDATE {$bp->follow->table_name} SET leader_id = %d, follower_id = %d, follow_type = %s WHERE id = %d",
					$this->leader_id,
					$this->follower_id,
					$this->follow_type,
					$this->id
			) );

		// add new entry
		} else {
			$result = $wpdb->query( $wpdb->prepare(
				"INSERT INTO {$bp->follow->table_name} ( leader_id, follower_id, follow_type ) VALUES ( %d, %d, %s )",
					$this->leader_id,
					$this->follower_id,
					$this->follow_type
			) );
			$this->id = $wpdb->insert_id;
		}

		do_action_ref_array( 'bp_follow_after_save', array( &$this ) );

		return $result;
	}

	/**
	 * Deletes a follow relationship from the database.
	 *
	 * @since 1.0.0
	 */
	public function delete() {
		global $wpdb, $bp;

		// SQL statement
		$sql  = "DELETE FROM {$bp->follow->table_name} ";
		$sql .= self::get_where_sql( array(
			'id' => $this->id,
		) );

		return $wpdb->query( $sql );
	}

	/** STATIC METHODS *****************************************************/

	/**
	 * Generate the SELECT SQL statement used to query follow relationships.
	 * 
	 * @since 1.3.0
	 *
	 * @param string $column
	 * @return string
	 */
	public static function get_select_sql( $column = '' ) {
		global $bp;

		return sprintf( "SELECT %s FROM %s ", mysql_real_escape_string( $column ), mysql_real_escape_string( $bp->follow->table_name ) );
	}

	/**
	 * Generate the WHERE SQL statement used to query follow relationships.
	 *
	 * @since 1.3.0
	 *
	 * @param array $params
	 * @return string
	 */
	protected static function get_where_sql( $params = array() ) {
		global $wpdb;

		$where_conditions = array();

		if ( ! empty( $params['id'] ) ) {
			$in = implode( ',', wp_parse_id_list( $params['id'] ) );
			$where_conditions['id'] = "id IN ({$in})";
		}

		if ( ! empty( $params['leader_id'] ) ) {
			$leader_ids = implode( ',', wp_parse_id_list( $params['leader_id'] ) );
			$where_conditions['leader_id'] = "leader_id IN ({$leader_ids})";
		}

		if ( ! empty( $params['follower_id'] ) ) {
			$follower_ids = implode( ',', wp_parse_id_list( $params['follower_id'] ) );
			$where_conditions['follower_id'] = "follower_id IN ({$follower_ids})";
		}

		if ( isset( $params['follow_type'] ) ) {
			$where_conditions['follow_type'] = $wpdb->prepare( "follow_type = %s", $params['follow_type'] );
		}

		return 'WHERE ' . join( ' AND ', $where_conditions );

	}

	/**
	 * Get the follower IDs for a given item.
	 *
	 * @since 1.0.0
	 *
	 * @param int $leader_id The leader ID.
	 * @param string $follow_type The follow type.
	 * @return array
	 */
	public static function get_followers( $leader_id = 0, $follow_type = '' ) {
		global $wpdb;

		// SQL statement
		$sql  = self::get_select_sql( 'follower_id' );
		$sql .= self::get_where_sql( array(
			'leader_id'   => $leader_id,
			'follow_type' => $follow_type,
		) );

		// do the query
		$result = $wpdb->get_col( $sql );

		// cache the count while we're at it
		$type = ! empty( $follow_type ) ? "{$follow_type}_" : "";
		wp_cache_set( $leader_id, $wpdb->num_rows, "bp_follow_followers_{$type}count" );

		return $result;
	}

	/**
	 * Get the IDs that a user is following.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID.
	 * @param string $follow_type The follow type.
	 * @return array
	 */
	public static function get_following( $user_id = 0, $follow_type = '' ) {
		global $wpdb;

		// SQL statement
		$sql  = self::get_select_sql( 'leader_id' );
		$sql .= self::get_where_sql( array(
			'follower_id' => $user_id,
			'follow_type' => $follow_type,
		) );

		// do the query
		$result = $wpdb->get_col( $sql );

		// cache the count while we're at it
		$type = ! empty( $follow_type ) ? "{$follow_type}_" : "";
		wp_cache_set( $user_id, $wpdb->num_rows, "bp_follow_following_{$type}count" );

		return $result;
	}
	/**
	 * Get the follower / following counts for a given user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The ID to fetch counts for.
	 * @param string $follow_type The follow type.
	 * @return array
	 */
	public static function get_counts( $id = 0, $follow_type = '' ) {
		global $bp, $wpdb;

		$type = ! empty( $follow_type ) ? "{$follow_type}_" : "";

		// get cache if available
		$followers = wp_cache_get( $id, "bp_follow_followers_{$type}count" );
		$following = wp_cache_get( $id, "bp_follow_following_{$type}count" );

		// query followers count
		if ( false === $followers ) {
			$followers_sql  = self::get_select_sql( 'COUNT(id)' );
			$followers_sql .= self::get_where_sql( array(
				'leader_id'   => $id,
				'follow_type' => $follow_type,
			) );

			$followers = (int) $wpdb->get_var( $followers_sql );
			wp_cache_set( $id, $followers, "bp_follow_followers_{$type}count" );
		}

		// query following count
		if ( false === $following ) {
			$following_sql  = self::get_select_sql( 'COUNT(id)' );
			$following_sql .= self::get_where_sql( array(
				'follower_id' => $id,
				'follow_type' => $follow_type,
			) );

			$following = (int) $wpdb->get_var( $following_sql );
			wp_cache_set( $id, $following, "bp_follow_following_{$type}count" );
		}

		return array( 'followers' => $followers, 'following' => $following );
	}

	/**
	 * Bulk check the follow status for a user against a list of user IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $leader_ids The user IDs to check the follow status for.
	 * @param int $user_id The user ID to check against the list of leader IDs.
	 * @param string $follow_type The type of follow connection.
	 * @return array
	 */
	public static function bulk_check_follow_status( $leader_ids = array(), $user_id = 0, $follow_type = '' ) {
		global $wpdb;

		if ( empty( $follow_type ) && empty( $user_id ) ) {
			$user_id = bp_loggedin_user_id();
		}

		if ( empty( $user_id ) ) {
			return false;
		}

		// SQL statement
		$sql  = self::get_select_sql( 'leader_id, id' );
		$sql .= self::get_where_sql( array(
			'follower_id' => $user_id,
			'leader_id'   => (array) $leader_ids,
			'follow_type' => $follow_type,
		) );

		return $wpdb->get_results( $sql );
	}

	/**
	 * Deletes all follow relationships for a given user.
	 *
	 * @since 1.1.0
	 *
	 * @param int $user_id The user ID
	 */
	public static function delete_all_for_user( $user_id = 0 ) {
		global $bp, $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->follow->table_name} WHERE leader_id = %d OR follower_id = %d AND follow_type = ''", $user_id, $user_id ) );
	}

	/**
	 * Deletes all follow relationships for a given blog.
	 *
	 * @since 1.3.0
	 *
	 * @param int $blog_id The blog ID
	 */
	public static function delete_all_for_blog( $blog_id = 0 ) {
		global $bp, $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->follow->table_name} WHERE leader_id = %d AND follow_type = 'blogs'", $blog_id ) );
	}
}
