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
		$this->assertEmpty( wp_cache_get( $u1, 'bp_follow_followers_count' ) );
		$this->assertEmpty( wp_cache_get( $u2, 'bp_follow_following_count' ) );

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
}

