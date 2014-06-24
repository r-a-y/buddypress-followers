<?php

/**
 * @group blogs
 */
class BP_Follow_Test_Blogs extends BP_UnitTestCase {

	/**
	 * @group delete
	 */
	public function test_follow_and_delete_blog() {
		if ( ! is_multisite() ) {
			return;
		}

		// create user and blog
		$u = $this->create_user();
		$b = $this->factory->blog->create( array(
			'title' => 'The Foo Bar Blog',
			'user_id' => $u,
		) );

		// make blog creator follow own blog
		$f = bp_follow_start_following( array(
			'leader_id'   => $b,
			'follower_id' => $u,
			'follow_type' => 'blogs',
		) );

		// assert that follow relationship worked
		$this->assertTrue( $f );

		// now delete blog
		wpmu_delete_blog( $b );

		// check if cache was deleted
		$this->assertEmpty( wp_cache_get( $u, 'bp_follow_following_blogs_count' ) );
		$this->assertEmpty( wp_cache_get( $b, 'bp_follow_followers_blogs_count' ) );

		// check if follow relationship was deleted
		$is_following = bp_follow_is_following( array(
			'leader_id'   => $b,
			'follower_id' => $u,
			'follow_type' => 'blogs',
		) );
		$this->assertSame( 0, $is_following );
	}
}
