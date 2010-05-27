<?php

class BP_Follow {
	var $id;
	var $leader_id;
	var $follower_id;

	function bp_follow( $leader_id = false, $follower_id = false ) {
		if ( !empty( $leader_id ) && !empty( $follower_id ) ) {
			$this->leader_id = $leader_id;
			$this->follower_id = $follower_id;
			$this->populate();
		}
	}

	function populate() {
		global $wpdb, $bp;

		if ( $follow_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$bp->follow->table_name} WHERE leader_id = %d AND follower_id = %d", $this->leader_id, $this->follower_id ) ) )
			$this->id = $follow_id;
	}

	function save() {
		global $wpdb, $bp;

		$this->leader_id = apply_filters( 'bp_follow_leader_id_before_save', $this->leader_id, $this->id );
		$this->follower_id = apply_filters( 'bp_follow_follower_id_before_save', $this->follower_id, $this->id );

		do_action( 'bp_follow_before_save', $this );

		if ( $this->id )
			$result = $wpdb->query( $wpdb->prepare( "UPDATE {$bp->follow->table_name} SET leader_id = %d, follower_id = %d WHERE id = %d", $this->leader_id, $this->follower_id, $this->id ) );
		else {
			// Save
			$result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$bp->follow->table_name} ( leader_id, follower_id ) VALUES ( %d, %d )", $this->leader_id, $this->follower_id ) );
			$this->id = $wpdb->insert_id;
		}

		do_action( 'bp_follow_after_save', $this );

		return $result;
	}

	function delete() {
		global $wpdb, $bp;

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->follow->table_name} WHERE id = %d", $this->id ) );
	}

	/* Static Methods */

	function get_followers( $user_id ) {
		global $bp, $wpdb;
		return $wpdb->get_col( $wpdb->prepare( "SELECT follower_id FROM {$bp->follow->table_name} WHERE leader_id = %d", $user_id ) );
	}

	function get_following( $user_id ) {
		global $bp, $wpdb;
		return $wpdb->get_col( $wpdb->prepare( "SELECT leader_id FROM {$bp->follow->table_name} WHERE follower_id = %d", $user_id ) );
	}

	function get_counts( $user_id ) {
		global $bp, $wpdb;

		$followers = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$bp->follow->table_name} WHERE leader_id = %d", $user_id ) );
		$following = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$bp->follow->table_name} WHERE follower_id = %d", $user_id ) );

		return array( 'followers' => $followers, 'following' => $following );
	}

	function bulk_check_follow_status( $leader_ids, $user_id = false ) {
		global $bp, $wpdb;

		if ( empty( $user_id ) )
			$user_id = $bp->loggedin_user->id;

		$leader_ids = $wpdb->escape( implode( ',', (array)$leader_ids ) );

		return $wpdb->get_results( $wpdb->prepare( "SELECT leader_id, id FROM {$bp->follow->table_name} WHERE follower_id = %d AND leader_id IN ($leader_ids)", $user_id ) );
	}
}
