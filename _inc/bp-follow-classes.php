<?php
/**
 * BP Follow Class
 *
 * @package BP-Follow
 * @subpackage Class
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

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
	 * The UTC date the follow item was recorded in 'Y-m-d h:i:s' format.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	public $date_recorded;

	/**
	 * Constructor.
	 *
	 * @param int    $leader_id    The ID of the item wewant to follow.
	 * @param int    $follower_id  The ID initiating the follow request.
	 * @param string $follow_type  The type of follow connection.
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

		// we always require a leader ID.
		if ( empty( $this->leader_id ) ) {
			return;
		}

		// check cache first.
		$key = "{$this->leader_id}:{$this->follower_id}:{$this->follow_type}";
		$data = wp_cache_get( $key, 'bp_follow_data' );

		// Run query if no cache.
		if ( false === $data ) {
			// SQL statement.
			$sql = self::get_select_sql( 'id, date_recorded' );
			$sql .= self::get_where_sql( array(
				'leader_id'   => $this->leader_id,
				'follower_id' => $this->follower_id,
				'follow_type' => $this->follow_type,
			) );

			// Run the query.
			$data = $wpdb->get_results( $sql );

			// Got a match; grab the results.
			if ( ! empty( $data ) ) {
				$data = $data[0];

			// No match. Set cache to zero to prevent further hits to database.
			} else {
				$data = 0;
			}

			// Set the cache.
			wp_cache_set( $key, $data, 'bp_follow_data' );
		}

		// Populate some other properties.
		if ( ! empty( $data ) ) {
			$this->id = $data->id;
			$this->date_recorded = $data->date_recorded;
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
		// use the 'bp_follow_before_save' hook instead.
		$this->leader_id   = apply_filters( 'bp_follow_leader_id_before_save',   $this->leader_id,   $this->id );
		$this->follower_id = apply_filters( 'bp_follow_follower_id_before_save', $this->follower_id, $this->id );

		do_action_ref_array( 'bp_follow_before_save', array( &$this ) );

		// leader ID is required
		// this allows plugins to bail out of saving a follow relationship
		// use hooks above to redeclare 'leader_id' so it is empty if you need to bail.
		if ( empty( $this->leader_id ) ) {
			return false;
		}

		// make sure a date is added for those directly using the save() method.
		if ( empty( $this->date_recorded ) ) {
			$this->date_recorded = bp_core_current_time();
		}

		// update existing entry.
		if ( $this->id ) {
			$result = $wpdb->query( $wpdb->prepare(
				"UPDATE {$bp->follow->table_name} SET leader_id = %d, follower_id = %d, follow_type = %s, date_recorded = %s WHERE id = %d",
				$this->leader_id,
				$this->follower_id,
				$this->follow_type,
				$this->date_recorded,
				$this->id
			) );

		// add new entry
		} else {
			$result = $wpdb->query( $wpdb->prepare(
				"INSERT INTO {$bp->follow->table_name} ( leader_id, follower_id, follow_type, date_recorded ) VALUES ( %d, %d, %s, %s )",
				$this->leader_id,
				$this->follower_id,
				$this->follow_type,
				$this->date_recorded
			) );
			$this->id = $wpdb->insert_id;
		}

		// Save cache.
		$data = new stdClass();
		$data->id = $this->id;
		$data->date_recorded = $this->date_recorded;

		wp_cache_set( "{$this->leader_id}:{$this->follower_id}:{$this->follow_type}", $data, 'bp_follow_data' );

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

		// SQL statement.
		$sql  = "DELETE FROM {$bp->follow->table_name} ";
		$sql .= self::get_where_sql( array(
			'id' => $this->id,
		) );

		// Delete cache.
		wp_cache_delete( "{$this->leader_id}:{$this->follower_id}:{$this->follow_type}", 'bp_follow_data' );

		return $wpdb->query( $sql );
	}

	/** STATIC METHODS *****************************************************/

	/**
	 * Generate the SELECT SQL statement used to query follow relationships.
	 *
	 * @since 1.3.0
	 *
	 * @param string $column Column.
	 * @return string
	 */
	protected static function get_select_sql( $column = '' ) {
		$bp = $GLOBALS['bp'];

		return sprintf( 'SELECT %s FROM %s ', esc_sql( $column ), esc_sql( $bp->follow->table_name ) );
	}

	/**
	 * Generate the WHERE SQL statement used to query follow relationships.
	 *
	 * @todo Add support for date ranges with 'date_recorded' column
	 *
	 * @since 1.3.0
	 *
	 * @param array $params Where params.
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

		// If null, return no results.
		} elseif ( array_key_exists( 'leader_id', $params ) && is_null( $params['leader_id'] ) ) {
			$where_conditions['no_results'] = '1 = 0';
		}

		if ( ! empty( $params['follower_id'] ) ) {
			$follower_ids = implode( ',', wp_parse_id_list( $params['follower_id'] ) );
			$where_conditions['follower_id'] = "follower_id IN ({$follower_ids})";

		// If null, return no results.
		} elseif ( array_key_exists( 'follower_id', $params ) && is_null( $params['follower_id'] ) ) {
			$where_conditions['no_results'] = '1 = 0';
		}

		if ( isset( $params['follow_type'] ) ) {
			$where_conditions['follow_type'] = $wpdb->prepare( 'follow_type = %s', $params['follow_type'] );
		}

		return 'WHERE ' . join( ' AND ', $where_conditions );
	}

	/**
	 * Generate the ORDER BY SQL statement used to query follow relationships.
	 *
	 * @since 1.3.0
	 *
	 * @param array $params {
	 *     Array of arguments.
	 *     @type string $orderby The DB column to order results by. Default: 'id'.
	 *     @type string $order The order. Either 'ASC' or 'DESC'. Default: 'DESC'.
	 * }
	 * @return string
	 */
	protected static function get_orderby_sql( $params = array() ) {
		$r = wp_parse_args( $params, array(
			'orderby' => 'id',
			'order'   => 'DESC',
		) );

		// sanitize 'orderby' DB oclumn lookup.
		switch ( $r['orderby'] ) {
			// columns available for lookup.
			case 'id':
			case 'leader_id':
			case 'follower_id':
			case 'follow_type':
			case 'date_recorded':
				break;

			// fallback to 'id' column on anything else.
			default:
				$r['orderby'] = 'id';
				break;
		}

		// only allow ASC or DESC for order.
		if ( 'ASC' !== $r['order'] || 'DESC' !== $r['order'] ) {
			$r['order'] = 'DESC';
		}

		return sprintf( ' ORDER BY %s %s', $r['orderby'], $r['order'] );
	}

	/**
	 * Get the follower IDs for a given item.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $leader_id The leader ID.
	 * @param string $follow_type The follow type.  Leave blank to query users.
	 * @param array  $query_args {
	 *     Various query arguments
	 *     @type array $date_query See {@link WP_Date_Query}.
	 *     @type string $orderby The DB column to order results by. Default: 'id'.
	 *     @type string $order The order. Either 'ASC' or 'DESC'. Default: 'DESC'.
	 * }
	 * @return array
	 */
	public static function get_followers( $leader_id = 0, $follow_type = '', $query_args = array() ) {
		global $wpdb;

		// SQL statement.
		$sql  = self::get_select_sql( 'follower_id' );
		$sql .= self::get_where_sql( array(
			'leader_id'   => $leader_id,
			'follow_type' => $follow_type,
		) );

		// Setup date query.
		if ( ! empty( $query_args['date_query'] ) && class_exists( 'WP_Date_Query' ) ) {
			add_filter( 'date_query_valid_columns', array( __CLASS__, 'register_date_column' ) );
			$date_query = new WP_Date_Query( $query_args['date_query'], 'date_recorded' );
			$sql .= $date_query->get_sql();
			remove_filter( 'date_query_valid_columns', array( __CLASS__, 'register_date_column' ) );
		}

		// Setup orderby query.
		$orderby = array();
		if ( ! empty( $query_args['orderby'] ) ) {
			$orderby = $query_args['orderby'];
		}
		if ( ! empty( $query_args['order'] ) ) {
			$orderby = $query_args['order'];
		}
		$sql .= self::get_orderby_sql( $orderby );

		// do the query.
		return $wpdb->get_col( $sql );
	}

	/**
	 * Get the IDs that a user is following.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID.
	 * @param string $follow_type The follow type.  Leave blank to query users.
	 * @param array $query_args {
	 *     Various query arguments
	 *     @type array $date_query See {@link WP_Date_Query}.
	 *     @type string $orderby The DB column to order results by. Default: 'id'.
	 *     @type string $order The order. Either 'ASC' or 'DESC'. Default: 'DESC'.
	 * }
	 * @return array
	 */
	public static function get_following( $user_id = 0, $follow_type = '', $query_args = array() ) {
		global $wpdb;

		// SQL statement.
		$sql  = self::get_select_sql( 'leader_id' );
		$sql .= self::get_where_sql( array(
			'follower_id' => $user_id,
			'follow_type' => $follow_type,
		) );

		// Setup date query.
		if ( ! empty( $query_args['date_query'] ) && class_exists( 'WP_Date_Query' ) ) {
			add_filter( 'date_query_valid_columns', array( __CLASS__, 'register_date_column' ) );
			$date_query = new WP_Date_Query( $query_args['date_query'], 'date_recorded' );
			$sql .= $date_query->get_sql();
			remove_filter( 'date_query_valid_columns', array( __CLASS__, 'register_date_column' ) );
		}

		// Setup orderby query.
		$orderby = array();
		if ( ! empty( $query_args['orderby'] ) ) {
			$orderby = $query_args['orderby'];
		}
		if ( ! empty( $query_args['order'] ) ) {
			$orderby = $query_args['order'];
		}
		$sql .= self::get_orderby_sql( $orderby );

		// do the query.
		return $wpdb->get_col( $sql );
	}

	/**
	 * Get the followers count for a particular item.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $leader_id   The leader ID to grab the followers count for.
	 * @param string $follow_type The follow type. Leave blank to query for users.
	 * @return int
	 */
	public static function get_followers_count( $leader_id = 0, $follow_type = '' ) {
		global $wpdb;

		$sql  = self::get_select_sql( 'COUNT(id)' );
		$sql .= self::get_where_sql( array(
			'leader_id'   => $leader_id,
			'follow_type' => $follow_type,
		) );

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get the following count for a particular item.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $id          The object ID to grab the following count for.
	 * @param string $follow_type The follow type. Leave blank to query for users.
	 * @return int
	 */
	public static function get_following_count( $id = 0, $follow_type = '' ) {
		global $wpdb;

		$sql  = self::get_select_sql( 'COUNT(id)' );
		$sql .= self::get_where_sql( array(
			'follower_id' => $id,
			'follow_type' => $follow_type,
		) );

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get the counts for a given item.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id          The ID to fetch counts for.
	 * @param string $follow_type The follow type.
	 * @return array
	 */
	public static function get_counts( $id = 0, $follow_type = '' ) {
		$following = self::get_following_count( $id, $follow_type );
		$followers = self::get_followers_count( $id, $follow_type );

		return array(
			'followers' => $followers,
			'following' => $following,
		);
	}

	/**
	 * Bulk check the follow status for a user against a list of user IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $leader_ids The user IDs to check the follow status for.
	 * @param int    $user_id The user ID to check against the list of leader IDs.
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

		// SQL statement.
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
	 * @param int $user_id The user ID.
	 */
	public static function delete_all_for_user( $user_id = 0 ) {
		global $wpdb;

		$bp = $GLOBALS['bp'];

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->follow->table_name} WHERE leader_id = %d OR follower_id = %d AND follow_type = ''", $user_id, $user_id ) );
	}

	/**
	 * Register our 'date_recorded' DB column to WP's date query columns.
	 *
	 * @since 1.3.0
	 *
	 * @param array $retval Current DB columns.
	 * @return array
	 */
	public static function register_date_column( $retval ) {
		$retval[] = 'date_recorded';

		return $retval;
	}
}
