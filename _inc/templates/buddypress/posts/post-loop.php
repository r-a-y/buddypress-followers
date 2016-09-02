<?php

/**
 * BuddyPress - Posts Loop
 *
 * Querystring is set via AJAX in _inc/ajax.php - bp_dtheme_object_filter()
 *
 * @package BuddyPress
 * @subpackage bp-default
 */

do_action( 'bp_before_posts_loop' );

// This is only a proof of concept, needs improving.

$p = bp_get_following_ids( array(
	'user_id'     => bp_loggedin_user_id(),
	'follow_type' => 'posts',
) );

$posts = explode(',', $p);

do_action( 'bp_before_directory_posts_list' );

if ( ! empty( $posts ) ) : ?>

	<ul id="posts-list" class="item-list" role="main">
		<?php foreach ( $posts as $post_id ) : ?>

			<li>
				<div class="item-thumbnail">
					<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
						<?php echo get_the_post_thumbnail( $post_id, 'thumbnail' ); ?>
					</a>
				</div>

				<div class="item">
					<div class="item-title">
						<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
							<?php echo get_the_title( $post_id ); ?>
							</a>
						</div>

					<?php do_action( 'bp_directory_posts_item' ); ?>
				</div>

				<div class="action">

					<?php do_action( 'bp_directory_posts_actions' ); ?>

				</div>

				<div class="clear"></div>
			</li>

		<?php endforeach; ?>
	</ul>

	<?php do_action( 'bp_after_directory_posts_list' ); ?>

<?php else : ?>

	<div id="message" class="info">
		<p><?php _e( 'Sorry, no posts found.', 'bp-follow' ); ?></p>
	</div>

<?php endif; ?>

<?php do_action( 'bp_after_posts_loop' ); ?>
