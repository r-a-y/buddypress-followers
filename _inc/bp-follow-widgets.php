<?php
class BP_Follow_Following_Widget extends WP_Widget {
	function bp_follow_following_widget() {
		parent::WP_Widget( false, $name = __( "Users I'm Following Avatars", 'bp-follow' ) );
	}

	function widget( $args, $instance ) {
		global $bp;

	    extract( $args );

		if ( empty( $instance['max_users'] ) )
			$instance['max_users'] = 25;

		if ( !$following = bp_get_following_ids( array( 'user_id' => $bp->loggedin_user->id ) ) )
			return false;

		if ( bp_has_members( 'include=' . $following . '&max=' . $instance['max_users'] ) ) {

			do_action( 'bp_before_following_widget' );

			echo $before_widget;
			echo $before_title
			   . __( 'Following', 'bp-follow' )
			   . $after_title; ?>

			<div class="avatar-block">
				<?php while ( bp_members() ) : bp_the_member(); ?>
					<div class="item-avatar">
						<a title="<?php bp_member_name() ?>" href="<?php bp_member_permalink() ?>"><?php bp_member_avatar() ?></a>
					</div>
				<?php endwhile; ?>
			</div>

			<?php echo $after_widget; ?>

			<?php do_action( 'bp_after_following_widget' ); ?>

		<?php } ?>

	<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['max_users'] = strip_tags( $new_instance['max_users'] );

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'max_users' => 25 ) );
		$max_users = strip_tags( $instance['max_users'] );
		?>

		<p><label for="bp-follow-widget-users-max"><?php _e('Max users to show:', 'bp-follow'); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'max_users' ); ?>" name="<?php echo $this->get_field_name( 'max_users' ); ?>" type="text" value="<?php echo attribute_escape( $max_users ); ?>" style="width: 30%" /></label></p>
	<?php
	}
}
add_action( 'widgets_init', create_function( '', 'return register_widget("BP_Follow_Following_Widget");' ) );

?>