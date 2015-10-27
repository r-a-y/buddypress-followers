<?php

/**
 * @group cache
 */
class BP_Follow_Test_Cache extends BP_UnitTestCase {

	/**
	 * @group bp_follow_total_follow_counts
	 */
	public function test_bp_follow_total_follow_counts_cache() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		// create a follow relationship
		bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		) );

		// unfollow
		bp_follow_stop_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		) );

		// make sure cache is invalidated
		$this->assertEmpty( wp_cache_get( $u1, 'bp_follow_user_followers_count' ) );
		$this->assertEmpty( wp_cache_get( $u2, 'bp_follow_user_following_count' ) );

		// get counts and assert
		$u1_counts = bp_follow_total_follow_counts( array(
			'user_id' => $u1,
		) );
		$this->assertSame( 0, $u1_counts['followers'] );

		$u2_counts = bp_follow_total_follow_counts( array(
			'user_id' => $u2,
		) );
		$this->assertSame( 0, $u2_counts['following'] );
	}

	/**
	 * @group bp_follow_data
	 */
	public function test_bp_follow_data() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$args = array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		);

		// create a follow relationship
		bp_follow_start_following( $args );

		// check if user is following - this should generate cache
		bp_follow_is_following( $args );

		// assert that cache is there
		$key = "{$u1}:{$u2}:";
		$cache = wp_cache_get( $key, 'bp_follow_data' );
		$this->assertTrue( ! empty( $cache->id ), (bool) $cache->id );

		// delete the follow relationship
		bp_follow_stop_following( $args );

		// assert
		$this->assertEmpty( wp_cache_get( $key, 'bp_follow_data' ) );
	}

	/**
	 * @group bp_follow_get_following
	 */
	public function test_bp_follow_get_following() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$u4 = $this->factory->user->create();

		// let user 1 follow everyone
		bp_follow_start_following( array(
			'leader_id'   => $u2,
			'follower_id' => $u1,
		) );
		bp_follow_start_following( array(
			'leader_id'   => $u3,
			'follower_id' => $u1,
		) );
		bp_follow_start_following( array(
			'leader_id'   => $u4,
			'follower_id' => $u1,
		) );

		// get following for user 1
		bp_follow_get_following( array( 'user_id' => $u1 ) );

		// assert
		$this->assertEqualSets( array( $u2, $u3, $u4 ), wp_cache_get( $u1, 'bp_follow_user_following_query' ) );

		// stop following one user
		bp_follow_stop_following( array(
			'leader_id'   => $u4,
			'follower_id' => $u1,
		) );

		// make sure cache is invalidated
		$this->assertEmpty( wp_cache_get( $u1, 'bp_follow_user_following_query' ) );
	}

	/**
	 * @group bp_follow_get_following
	 */
	public function test_bp_follow_get_following_no_cache() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$u4 = $this->factory->user->create();

		// let user 1 follow everyone
		bp_follow_start_following( array(
			'leader_id'   => $u2,
			'follower_id' => $u1,
		) );
		bp_follow_start_following( array(
			'leader_id'   => $u3,
			'follower_id' => $u1,
		) );
		bp_follow_start_following( array(
			'leader_id'   => $u4,
			'follower_id' => $u1,
		) );

		// get following for user 1
		bp_follow_get_following( array(
			'user_id' => $u1,

			// add query args
			'query_args' => array(
				'orderby' => 'id',
				'order'   => 'ASC',
			)
		) );

		// we do not cache following calls with query args at the moment
		$this->assertEmpty( wp_cache_get( $u1, 'bp_follow_user_following_query' ) );
	}

	/**
	 * @group bp_follow_get_followers
	 */
	public function test_bp_follow_get_followers() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$u4 = $this->factory->user->create();

		// let user 1 be followed by everyone
		bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		) );
		bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u3,
		) );
		bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u4,
		) );

		// get followers for user 1
		bp_follow_get_followers( array( 'user_id' => $u1 ) );

		// assert
		$this->assertEqualSets( array( $u2, $u3, $u4 ), wp_cache_get( $u1, 'bp_follow_user_followers_query' ) );

		// one user stops following user 1
		bp_follow_stop_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u4,
		) );

		// make sure cache is invalidated
		$this->assertEmpty( wp_cache_get( $u1, 'bp_follow_user_followers_query' ) );
	}
}
