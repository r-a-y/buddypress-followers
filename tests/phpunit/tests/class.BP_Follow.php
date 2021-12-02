<?php
/**
 * @group core
 */
class BP_Follow_Test_Core_Class extends BP_UnitTestCase {
	/**
	 * @group date_query
	 */
	public function test_date_query() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$u4 = $this->factory->user->create();

		// follow all users at different dates
		bp_follow_start_following( array(
			'leader_id'     => $u2,
			'follower_id'   => $u1,
		) );
		bp_follow_start_following( array(
			'leader_id'     => $u3,
			'follower_id'   => $u1,
			'date_recorded' => '2001-01-01 12:00'
		) );
		bp_follow_start_following( array(
			'leader_id'     => $u4,
			'follower_id'   => $u1,
			'date_recorded' => '2005-01-01 12:00'
		) );

		// 'date_query' before test
		$query = BP_Follow::get_following( $u1, '', array(
			'date_query' => array( array(
				'before' => array(
					'year'  => 2004,
					'month' => 1,
					'day'   => 1,
				),
			) )
		) );
		$this->assertEquals( array( $u3 ), $query );

		// 'date_query' range test
		$query = BP_Follow::get_following( $u1, '', array(
			'date_query' => array( array(
				'after'  => 'January 2nd, 2001',
				'before' => array(
					'year'  => 2013,
					'month' => 1,
					'day'   => 1,
				),
				'inclusive' => true,
			) )
		) );
		$this->assertEquals( array( $u4 ), $query );

		// 'date_query' after and relative test
		$query = BP_Follow::get_following( $u1, '', array(
			'date_query' => array( array(
 				'after' => '1 day ago'
			) )
		) );
		$this->assertEquals( array( $u2 ), $query );
	}

	/**
	 * @group null
	 */
	public function test_null_value_for_leader_id_should_return_no_results() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		bp_follow_start_following(
			array(
				'leader_id'   => $u2,
				'follower_id' => $u1,
			)
		);

		$query = BP_Follow::get_followers( NULL );

		$this->assertEmpty( $query );
	}

	/**
	 * @group null
	 */
	public function test_null_value_for_follower_id_should_return_no_results() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		bp_follow_start_following(
			array(
				'leader_id'   => $u2,
				'follower_id' => $u1,
			)
		);

		$query = BP_Follow::get_following( NULL );

		$this->assertEmpty( $query );
	}
}
