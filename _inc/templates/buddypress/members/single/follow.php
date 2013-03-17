
			<?php do_action( 'bp_before_member_' . bp_current_action() . '_content' ); ?>

			<?php bp_get_template_part( 'members/members-loop' ) ?>

			<?php do_action( 'bp_after_member_' . bp_current_action() . '_content' ); ?>