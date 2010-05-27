<?php get_header() ?>

	<div id="content">
		<div class="padder">

			<?php do_action( 'bp_before_member_home_content' ) ?>

			<div id="item-header">
				<?php locate_template( array( 'members/single/member-header.php' ), true ) ?>
			</div><!-- #item-header -->

			<div id="item-nav">
				<div class="item-list-tabs no-ajax" id="object-nav">
					<ul>
						<?php bp_get_displayed_user_nav() ?>

						<?php do_action( 'bp_members_directory_member_types' ) ?>
					</ul>
				</div>
			</div><!-- #item-nav -->

			<div id="item-body">
				<?php do_action( 'bp_before_following_loop' ) ?>

				<?php if ( bp_has_members( 'include=' . bp_get_following_ids() ) ) : ?>

					<div class="pagination">

						<div class="pag-count">
							<?php bp_members_pagination_count() ?>
						</div>

						<div class="pagination-links">
							<?php bp_members_pagination_links() ?>
						</div>

					</div>

					<?php do_action( 'bp_before_following_list' ) ?>

					<ul id="following-list" class="item-list">
					<?php while ( bp_members() ) : bp_the_member(); ?>

						<li>
							<div class="item-avatar">
								<a href="<?php bp_member_permalink() ?>"><?php bp_member_avatar() ?></a>
							</div>

							<div class="item">
								<div class="item-title">
									<a href="<?php bp_member_permalink() ?>"><?php bp_member_name() ?></a>
									<?php if ( bp_get_member_latest_update() ) : ?>
										<span class="update"> - <?php bp_member_latest_update( 'length=10' ) ?></span>
									<?php endif; ?>
								</div>
								<div class="item-meta"><span class="activity"><?php bp_member_last_active() ?></span></div>

								<?php do_action( 'bp_following_list_item' ) ?>

							</div>

							<div class="action">
								<?php //bp_member_add_friend_button() ?>

								<?php do_action( 'bp_following_list_item_actions' ) ?>
							</div>
						</li>

					<?php endwhile; ?>
					</ul>

					<?php do_action( 'bp_after_following_list' ) ?>

				<?php else: ?>

					<div id="message" class="info">
						<p><?php _e( "Sorry, this member has no following.", 'buddypress' ) ?></p>
					</div>

				<?php endif; ?>

				<?php do_action( 'bp_after_following_loop' ) ?>
			</div><!-- #item-body -->

			<?php do_action( 'bp_after_member_home_content' ) ?>

		</div><!-- .padder -->
	</div><!-- #content -->

	<?php locate_template( array( 'sidebar.php' ), true ) ?>

<?php get_footer() ?>