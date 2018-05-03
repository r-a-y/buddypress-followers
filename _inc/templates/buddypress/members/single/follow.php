
			<?php do_action( 'bp_before_member_' . bp_current_action() . '_content' ); ?>

			<?php // this is important! do not remove the classes in this DIV as AJAX relies on it! ?>
			<div id="members-dir-list" class="dir-list members follow <?php echo bp_current_action(); ?>" data-bp-list="members">
				<?php if ( function_exists( 'bp_nouveau' ) ) : ?>

					<div id="bp-ajax-loader"><?php bp_nouveau_user_feedback( 'generic-loading' ); ?></div>
				
				<?php else : ?>

					<?php bp_get_template_part( 'members/members-loop' ) ?>

				<?php endif; ?>
			</div>

			<?php do_action( 'bp_after_member_' . bp_current_action() . '_content' ); ?>
