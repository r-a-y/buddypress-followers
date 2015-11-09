<?php

/**
 * @group functions
 */
class BP_Follow_Functions extends BP_UnitTestCase {

	public function test_follow_start_following() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$follow = bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		) );

		$this->assertTrue( $follow );
	}

	public function test_follow_start_following_already_exists() {
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

	/**
	 * Test two follow relationships with the same leader_id and follower_id.
	 *
	 * But, set the follow_type for the second relationship to 'blogs'. This is to
	 * determine if there are any conflicts with setting the same leader and
	 * follower IDs.
	 *
	 * @group blogs
	 */
	public function test_follow_start_following_user_blog_with_same_leader_follower_id() {
		// add a user relationship
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$f1 = bp_follow_start_following( array(
			'leader_id'   => $u1,
			'follower_id' => $u2,
		) );

		// now add a blog relationship
		// use the exact same leader_id and follower_id, but set different type
		$f2 = bp_follow_start_following( array(
			// this is meant to be the blog ID
			// we pretend that a blog ID of $u1 exists
			'leader_id'   => $u1,

			// this is the same user ID as above
			'follower_id' => $u2,

			// different follow type
			'follow_type' => 'blogs',
		) );

		$this->assertTrue( $f2 );
	}

	/**
	 * Check stop following function when follow ID doesn't exist.
	 *
	 * @group bp_follow_stop_following
	 */
	public function test_stop_following_when_follow_id_does_not_exist() {
		$f1 = bp_follow_stop_following( array(
			'leader_id'   => get_current_user_id(),
			'follower_id' => 9999,
		) );


		$this->assertFalse( $f1 );
	}
}

