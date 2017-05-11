<?php

/**
 * @group posts
 */
class BP_Follow_Test_Posts extends BP_UnitTestCase {

	/**
	 * @group delete
	 */
	public function test_follow_and_delete_posts() {

		// create user and post
		$u = $this->factory->user->create();
		$b = $this->factory->post->create( array(
			'post_title'  => 'The Foo Bar Post',
			'post_content' => 'The Foor Bar Post Content',
			'post_author' => $u,
		) );

		// make post creator follow own post
		$f = bp_follow_start_following( array(
			'leader_id'   => $b,
			'follower_id' => $u,
			'follow_type' => 'posts',
		) );

		// assert that follow relationship worked
		$this->assertTrue( $f );

		// prime cache
		new BP_Follow( $b, $u, 'posts' );
		bp_follow_get_the_following_count( array(
			'user_id' => $u,
			'follow_type' => 'posts',
		) );
		bp_follow_get_the_followers_count( array(
			'object_id'   => $b,
			'follow_type' => 'posts'
		) );

		// now delete post
		wp_delete_post( $b, true );

		// check if cache was deleted
		$this->assertEmpty( wp_cache_get( "{$b}:{$u}:posts", 'bp_follow_data' ) );
		$this->assertEmpty( wp_cache_get( $u, 'bp_follow_user_posts_following_count' ) );
		$this->assertEmpty( wp_cache_get( $b, 'bp_follow_posts_followers_count' ) );
	}
}
