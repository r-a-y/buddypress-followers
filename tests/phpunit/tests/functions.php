<?php

/**
 * @group functions
 */
class BP_Follow_Functions extends BP_UnitTestCase {

	function test_follow_start_following() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$follow = bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		) );

		$this->assertTrue( $follow );
	}

	function test_follow_start_following_already_exists() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$f1 = bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		) );

		$f2 = bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		) );

		$this->assertFalse( $f2 );
	}
}

